<?php
/**
 * api/payments.php - API REST per gestione pagamenti
 * Gestisce transazioni, validazione e aggiornamento stato prenotazioni
 */

// Security headers e sessione centralizzati
require_once __DIR__ . '/security_headers.php';

header('Content-Type: application/json; charset=utf-8');

// ===== HARDENING: Header sicurezza aggiuntivi per API Pagamenti =====
// X-Content-Type-Options: previene MIME sniffing (interpreta JSON solo come JSON)
header('X-Content-Type-Options: nosniff', true);

// Cache-Control: MAI cachare risposte API di pagamento
header('Cache-Control: no-store, no-cache, must-revalidate, private', true);
header('Pragma: no-cache', true);

// X-Frame-Options: le API non devono essere caricate in iframe
header('X-Frame-Options: DENY', true);

// Cross-Origin: restrizioni per isolamento
header('Cross-Origin-Resource-Policy: same-origin', true);

require_once '../config.php';

// ===== CSRF TOKEN GENERATION =====
// Genera token CSRF all'avvio della sessione (se non esiste)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Endpoint per ottenere il token CSRF (chiamato dal frontend)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'csrf-token') {
    echo json_encode([
        'success' => true,
        'csrf_token' => $_SESSION['csrf_token']
    ]);
    exit;
}

// ===== SESSION TIMEOUT & RATE LIMITING =====

// Session timeout lato server (15 minuti)
define('SESSION_TIMEOUT', 900); // 15 minuti in secondi

if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        // Session scaduta
        session_unset();
        session_destroy();
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Sessione scaduta. Ricarica la pagina di pagamento.',
            'code' => 'SESSION_EXPIRED'
        ]);
        exit;
    }
}
$_SESSION['last_activity'] = time();

// Rate limiting basato su IP reale (gestisce proxy/CDN)
$clientIp = getClientIp();
$rateLimitWindow = 60; // secondi
$maxRequests = 30; // max richieste per finestra

if ($conn !== null) {
    // Pulisci tentativi vecchi (più di 1 ora)
    $conn->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");

    // Conta richieste recenti da questo IP
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $stmt->bind_param("si", $clientIp, $rateLimitWindow);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $requestCount = (int)$row['count'];
    $stmt->close();

    if ($requestCount >= $maxRequests) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Troppe richieste. Riprova tra un minuto.',
            'retry_after' => $rateLimitWindow
        ]);
        exit;
    }

    // Registra questa richiesta
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, attempted_at, success) VALUES (?, NOW(), 1)");
    $stmt->bind_param("s", $clientIp);
    $stmt->execute();
    $stmt->close();
}

// Carica Stripe SDK se disponibile
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Configurazione Stripe (da environment)
$stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY') ?: '';

// Configurazione PayPal (da environment)
$paypalClientId = $_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID') ?: '';
$paypalClientSecret = $_ENV['PAYPAL_CLIENT_SECRET'] ?? getenv('PAYPAL_CLIENT_SECRET') ?: '';
$paypalMode = $_ENV['PAYPAL_MODE'] ?? getenv('PAYPAL_MODE') ?: 'sandbox'; // 'sandbox' o 'live'
$paypalApiBase = ($paypalMode === 'live')
    ? 'https://api-m.paypal.com'
    : 'https://api-m.sandbox.paypal.com';

// Configurazione pagamenti
define('VALID_PAYMENT_METHODS', ['card', 'paypal', 'iban']);
define('VALID_PAYMENT_STATUSES', ['pending', 'processing', 'completed', 'failed', 'pending_transfer', 'refunded']);

/**
 * Valida token CSRF per richieste sensibili
 */
function validateCsrfToken() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    if (!$token) {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['csrf_token'] ?? null;
    }

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF mancante']);
        exit;
    }

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF non valido']);
        exit;
    }
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($conn === null) {
        throw new Exception('Connessione al database non disponibile');
    }

    switch ($method) {
        case 'GET':
            handleGetRequest();
            break;

        case 'POST':
            handlePostRequest();
            break;

        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Metodo HTTP non consentito'
            ]);
            break;
    }
} catch (Exception $e) {
    error_log('Payment API Error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server',
        'errors' => ['Qualcosa è andato storto. Riprova più tardi.']
    ]);
}

// ===== HANDLER RICHIESTE GET =====

function handleGetRequest() {
    $action = $_GET['action'] ?? null;

    switch ($action) {
        case 'status':
            getPaymentStatus();
            break;

        case 'verify':
            verifyPayment();
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Azione non specificata'
            ]);
    }
}

/**
 * Ottiene stato pagamento per booking_id
 */
function getPaymentStatus() {
    global $conn;

    $bookingId = trim($_GET['booking_id'] ?? '');

    if (!validateBookingId($bookingId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID prenotazione non valido'
        ]);
        return;
    }

    $stmt = $conn->prepare("SELECT payment_status, payment_method, paid_at FROM prenotazioni WHERE booking_id = ?");
    $stmt->bind_param("s", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Prenotazione non trovata'
        ]);
        return;
    }

    $row = $result->fetch_assoc();

    echo json_encode([
        'success' => true,
        'booking_id' => $bookingId,
        'payment_status' => $row['payment_status'] ?? 'pending',
        'payment_method' => $row['payment_method'] ?? null,
        'paid_at' => $row['paid_at'] ?? null
    ]);

    $stmt->close();
}

/**
 * Verifica autenticità pagamento (per webhook)
 */
function verifyPayment() {
    global $conn;

    $transactionId = trim($_GET['transaction_id'] ?? '');

    if (empty($transactionId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Transaction ID richiesto'
        ]);
        return;
    }

    $stmt = $conn->prepare("SELECT booking_id, payment_status FROM payments WHERE transaction_id = ?");
    $stmt->bind_param("s", $transactionId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'verified' => false,
            'message' => 'Transazione non trovata'
        ]);
        return;
    }

    $row = $result->fetch_assoc();

    echo json_encode([
        'success' => true,
        'verified' => true,
        'booking_id' => $row['booking_id'],
        'payment_status' => $row['payment_status']
    ]);

    $stmt->close();
}

// ===== HANDLER RICHIESTE POST =====

function handlePostRequest() {
    global $conn;

    // SICUREZZA: Valida token CSRF
    validateCsrfToken();

    // HARDENING: Limita dimensione payload per prevenire JSON DoS
    $maxPayloadSize = 16384; // 16KB - sufficiente per richieste di pagamento
    $rawInput = file_get_contents('php://input');

    if ($rawInput === false) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Impossibile leggere la richiesta'
        ]);
        return;
    }

    if (strlen($rawInput) > $maxPayloadSize) {
        http_response_code(413); // Payload Too Large
        echo json_encode([
            'success' => false,
            'message' => 'Richiesta troppo grande'
        ]);
        return;
    }

    // HARDENING: Parsing JSON con controllo errori rigoroso
    $input = json_decode($rawInput, true);

    // Controllo strict: distingue tra JSON vuoto/null e errore di parsing
    if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
        $errorCode = json_last_error();
        error_log('JSON Parse Error: ' . json_last_error_msg() . ' (code: ' . $errorCode . ')');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Formato richiesta non valido'
        ]);
        return;
    }

    // Verifica che sia un array/oggetto valido
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Formato richiesta non valido'
        ]);
        return;
    }

    // Controlla se è una conferma pagamento Stripe
    $action = $input['action'] ?? null;
    if ($action === 'confirm_stripe_payment') {
        confirmStripePayment($input);
        return;
    }

    // ===== PAYPAL ENDPOINTS (ZERO-TRUST) =====
    if ($action === 'create_paypal_order') {
        createPayPalOrder($input);
        return;
    }

    if ($action === 'capture_paypal_order') {
        capturePayPalOrder($input);
        return;
    }

    // Validazione dati base per altri metodi (paypal, iban)
    // ZERO-TRUST: validatePaymentRequest ora recupera anche i dati dal DB
    $validation = validatePaymentRequest($input);
    if ($validation['valid'] !== true) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Dati pagamento non validi',
            'errors' => $validation['errors']
        ]);
        return;
    }

    // ZERO-TRUST: Recupera l'importo autoritativo dal database
    $bookingId = trim($input['booking_id']);
    $bookingData = checkBookingForPayment($bookingId);

    if ($bookingData['valid'] !== true || $bookingData['booking'] === null) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Impossibile recuperare dati prenotazione',
            'errors' => $bookingData['errors'] ?? ['Errore interno']
        ]);
        return;
    }

    // L'importo viene ESCLUSIVAMENTE dal database
    $authoritativeAmount = $bookingData['booking']['amount'];

    // Processa in base al metodo
    $method = $input['method'];

    switch ($method) {
        case 'card':
            // PCI-DSS: I pagamenti carta devono passare SOLO tramite Stripe
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'I pagamenti con carta devono essere processati tramite Stripe Elements'
            ]);
            break;

        case 'paypal':
            // ZERO-TRUST: Passa l'importo dal DB, non dal client
            processPayPalPayment($bookingId, $authoritativeAmount);
            break;

        case 'iban':
            // ZERO-TRUST: Passa l'importo dal DB, non dal client
            processIBANPayment($bookingId, $authoritativeAmount);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Metodo di pagamento non supportato'
            ]);
    }
}

/**
 * Valida richiesta pagamento (ZERO-TRUST: non accetta importo dal client)
 */
function validatePaymentRequest($data) {
    $errors = [];

    // Campi obbligatori (amount NON è più accettato dal client)
    if (empty($data['booking_id'])) {
        $errors[] = 'ID prenotazione obbligatorio';
    } elseif (!validateBookingId($data['booking_id'])) {
        $errors[] = 'ID prenotazione non valido';
    }

    if (empty($data['method'])) {
        $errors[] = 'Metodo di pagamento obbligatorio';
    } elseif (!in_array($data['method'], VALID_PAYMENT_METHODS, true)) {
        $errors[] = 'Metodo di pagamento non supportato';
    }

    // Verifica che la prenotazione esista e non sia già pagata
    // ZERO-TRUST: L'importo viene recuperato dal DB, non validato dal client
    if (empty($errors) && !empty($data['booking_id'])) {
        $bookingCheck = checkBookingForPayment($data['booking_id']);
        if ($bookingCheck['valid'] !== true) {
            $errors = array_merge($errors, $bookingCheck['errors']);
        }
    }

    return [
        'valid' => count($errors) === 0,
        'errors' => $errors
    ];
}

/**
 * Verifica prenotazione per pagamento e recupera importo autoritativo
 * ZERO-TRUST: Non accetta importo dal client, lo legge solo dal DB
 *
 * @param string $bookingId ID prenotazione
 * @return array ['valid' => bool, 'errors' => array, 'booking' => array|null]
 */
function checkBookingForPayment($bookingId) {
    global $conn;

    $errors = [];
    $booking = null;
    $bookingId = trim($bookingId);

    $stmt = $conn->prepare("SELECT id, total_price, payment_status, status FROM prenotazioni WHERE booking_id = ?");
    $stmt->bind_param("s", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $errors[] = 'Prenotazione non trovata';
    } else {
        $row = $result->fetch_assoc();

        // Verifica stato prenotazione
        if ($row['status'] === 'cancelled') {
            $errors[] = 'La prenotazione è stata cancellata';
        }

        // Verifica se già pagata
        if ($row['payment_status'] === 'completed') {
            $errors[] = 'La prenotazione è già stata pagata';
        }

        // ZERO-TRUST: Recupera l'importo autoritativo dal database
        $amount = floatval($row['total_price']);

        // Sanity check sull'importo nel DB
        if ($amount <= 0 || $amount > 100000) {
            $errors[] = 'Importo prenotazione non valido nel database';
        }

        // Restituisci i dati della prenotazione per uso successivo
        $booking = [
            'id' => $row['id'],
            'amount' => $amount,
            'payment_status' => $row['payment_status'],
            'status' => $row['status']
        ];
    }

    $stmt->close();

    return [
        'valid' => count($errors) === 0,
        'errors' => $errors,
        'booking' => $booking
    ];
}

/**
 * Valida formato booking_id
 */
function validateBookingId($bookingId) {
    // Formato: BK + 14 cifre (data) + _ + 8 caratteri hex
    return preg_match('/^BK\d{14}_[a-f0-9]{8}$/i', $bookingId);
}

/**
 * La valuta autorevole per i pagamenti Stripe.
 * Le prenotazioni sono memorizzate in EUR nel database.
 */
function getBookingStripeCurrency() {
    return 'eur';
}

/**
 * Conferma pagamento Stripe dopo completamento frontend
 *
 * PCI-DSS COMPLIANT: Questa funzione NON riceve MAI dati carta.
 * Riceve solo il payment_intent_id dal frontend e verifica con Stripe
 * che il pagamento sia effettivamente completato.
 *
 * @param array $data Dati dalla richiesta (booking_id, payment_intent_id)
 */
function confirmStripePayment($data) {
    global $conn, $stripeSecretKey;

    try {
        $bookingId = trim($data['booking_id'] ?? '');
        $paymentIntentId = trim($data['payment_intent_id'] ?? '');

        // Validazione input
        if (!validateBookingId($bookingId)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID prenotazione non valido'
            ]);
            return;
        }

        if (empty($paymentIntentId) || !preg_match('/^pi_[a-zA-Z0-9]+$/', $paymentIntentId)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Payment Intent ID non valido'
            ]);
            return;
        }

        $bookingCheck = checkBookingForPayment($bookingId);
        if ($bookingCheck['valid'] !== true || empty($bookingCheck['booking'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $bookingCheck['errors'][0] ?? 'Prenotazione non valida'
            ]);
            return;
        }

        $expectedAmountInCents = (int) round($bookingCheck['booking']['amount'] * 100);
        $expectedCurrency = getBookingStripeCurrency();

        // Verifica che Stripe sia configurato
        if (empty($stripeSecretKey) || strpos($stripeSecretKey, 'XXXX') !== false) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Sistema di pagamento non configurato'
            ]);
            return;
        }

        // Inizializza Stripe
        \Stripe\Stripe::setApiKey($stripeSecretKey);

        // Recupera il PaymentIntent da Stripe per verificarne lo stato
        $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

        // Verifica che il booking_id corrisponda (metadati)
        $storedBookingId = $paymentIntent->metadata['booking_id'] ?? '';
        if ($storedBookingId !== $bookingId) {
            error_log("Payment mismatch: stored=$storedBookingId, received=$bookingId");
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Prenotazione non corrispondente'
            ]);
            return;
        }

        // Verifica lo stato del pagamento
        if ($paymentIntent->status !== 'succeeded') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Il pagamento non è stato completato',
                'payment_status' => $paymentIntent->status
            ]);
            return;
        }

        if ((int) $paymentIntent->amount !== $expectedAmountInCents || strtolower($paymentIntent->currency ?? '') !== $expectedCurrency) {
            error_log("Stripe amount/currency mismatch: booking=$bookingId expected_amount=$expectedAmountInCents expected_currency=$expectedCurrency actual_amount={$paymentIntent->amount} actual_currency={$paymentIntent->currency}");
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Importo o valuta del pagamento non validi'
            ]);
            return;
        }

        // Pagamento confermato! Aggiorna il database
        $conn->begin_transaction();

        // Estrai info carta in modo sicuro (Stripe maschera il PAN)
        $paymentMethod = $paymentIntent->payment_method;
        $cardBrand = 'card';
        $cardLastFour = '****';

        if ($paymentMethod) {
            try {
                $pm = \Stripe\PaymentMethod::retrieve($paymentMethod);
                if (isset($pm->card)) {
                    $cardBrand = $pm->card->brand ?? 'card';
                    $cardLastFour = $pm->card->last4 ?? '****';
                }
            } catch (Exception $e) {
                // Non critico, usiamo valori default
            }
        }

        // Aggiorna stato prenotazione
        updateBookingPaymentStatus($bookingId, 'completed', 'card', $paymentIntentId);

        // Se esiste tabella payments, inserisci record
        $stmt = $conn->prepare("INSERT INTO payments
            (booking_id, transaction_id, amount, method, card_last_four, card_brand, status, created_at)
            VALUES (?, ?, ?, 'card', ?, ?, 'completed', NOW())
            ON DUPLICATE KEY UPDATE status = 'completed', card_last_four = ?, card_brand = ?");

        if ($stmt) {
            $amount = $bookingCheck['booking']['amount'];
            $stmt->bind_param("ssdssss",
                $bookingId,
                $paymentIntentId,
                $amount,
                $cardLastFour,
                $cardBrand,
                $cardLastFour,
                $cardBrand
            );
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Pagamento confermato con successo',
            'transaction_id' => $paymentIntentId,
            'payment_method' => 'card',
            'amount' => $bookingCheck['booking']['amount']
        ]);

    } catch (\Stripe\Exception\InvalidRequestException $e) {
        // SECURITY: Logga solo codice errore, non messaggio completo
        $errorCode = $e->getError()->code ?? 'invalid_request';
        error_log('Stripe Error (confirm): code=' . $errorCode . ' - pi=' . $paymentIntentId);
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Pagamento non trovato o non valido'
        ]);

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollback();
        }
        // SECURITY: Logga solo tipo eccezione, non messaggio completo
        error_log('Confirm Payment Error: ' . get_class($e) . ' - booking=' . $bookingId);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Errore nella conferma del pagamento'
        ]);
    }
}

/**
 * Ottiene un access token OAuth2 da PayPal
 * SICUREZZA: Le credentials sono lette da environment variables
 *
 * @return string|null Access token o null in caso di errore
 */
function getPayPalAccessToken() {
    global $paypalClientId, $paypalClientSecret, $paypalApiBase;

    if (empty($paypalClientId) || empty($paypalClientSecret)) {
        error_log('PayPal Error: Credenziali non configurate');
        return null;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $paypalApiBase . '/v1/oauth2/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: it_IT'
        ],
        CURLOPT_USERPWD => $paypalClientId . ':' . $paypalClientSecret,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log('PayPal OAuth Error: HTTP ' . $httpCode);
        return null;
    }

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

/**
 * Crea un ordine PayPal con importo recuperato dal database
 * ZERO-TRUST: L'importo NON viene dal client, solo il booking_id
 *
 * @param array $input Dati dalla richiesta (solo booking_id)
 */
function createPayPalOrder($input) {
    global $conn, $paypalApiBase;

    try {
        $bookingId = trim($input['booking_id'] ?? '');

        // Validazione booking_id
        if (!validateBookingId($bookingId)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID prenotazione non valido'
            ]);
            return;
        }

        // ZERO-TRUST: Recupera l'importo dal database
        $bookingData = checkBookingForPayment($bookingId);
        if ($bookingData['valid'] !== true || $bookingData['booking'] === null) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Prenotazione non valida o già pagata',
                'errors' => $bookingData['errors'] ?? []
            ]);
            return;
        }

        $amount = $bookingData['booking']['amount'];

        // Ottieni access token PayPal
        $accessToken = getPayPalAccessToken();
        if (!$accessToken) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Errore di configurazione PayPal'
            ]);
            return;
        }

        // Crea ordine PayPal via API REST
        $orderPayload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $bookingId,
                'description' => 'Prenotazione Luxury Hotel - ' . $bookingId,
                'custom_id' => $bookingId,
                'amount' => [
                    'currency_code' => 'EUR',
                    'value' => number_format($amount, 2, '.', '')
                ]
            ]],
            'application_context' => [
                'brand_name' => 'Luxury Hotel',
                'locale' => 'it-IT',
                'landing_page' => 'NO_PREFERENCE',
                'user_action' => 'PAY_NOW',
                'return_url' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/payment-success.html',
                'cancel_url' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/payment.php'
            ]
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $paypalApiBase . '/v2/checkout/orders',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($orderPayload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
                'PayPal-Request-Id: ' . $bookingId . '_' . time()
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201) {
            // SECURITY: Non loggare payload completo per protezione PII
            $errorCode = json_decode($response, true)['name'] ?? 'UNKNOWN';
            error_log('PayPal Create Order Error: HTTP ' . $httpCode . ' - code: ' . $errorCode . ' - booking: ' . $bookingId);
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Errore nella creazione ordine PayPal'
            ]);
            return;
        }

        $orderData = json_decode($response, true);
        $orderId = $orderData['id'] ?? null;

        if (!$orderId) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Risposta PayPal non valida'
            ]);
            return;
        }

        // Aggiorna stato prenotazione a processing
        $stmt = $conn->prepare("UPDATE prenotazioni SET payment_status = 'processing' WHERE booking_id = ?");
        $stmt->bind_param("s", $bookingId);
        $stmt->execute();
        $stmt->close();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'order_id' => $orderId
        ]);

    } catch (Exception $e) {
        // SECURITY: Logga solo tipo eccezione
        error_log('PayPal Create Order Exception: ' . get_class($e) . ' - booking=' . $bookingId);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Errore interno nella creazione ordine'
        ]);
    }
}

/**
 * Cattura un ordine PayPal dopo l'approvazione utente
 * ZERO-TRUST: Verifica che l'ordine corrisponda al booking_id
 *
 * @param array $input Dati dalla richiesta (order_id, booking_id)
 */
function capturePayPalOrder($input) {
    global $conn, $paypalApiBase;

    try {
        $orderId = trim($input['order_id'] ?? '');
        $bookingId = trim($input['booking_id'] ?? '');

        // Validazione input
        if (empty($orderId) || !preg_match('/^[A-Z0-9]+$/', $orderId)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Order ID PayPal non valido'
            ]);
            return;
        }

        if (!validateBookingId($bookingId)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID prenotazione non valido'
            ]);
            return;
        }

        // Ottieni access token
        $accessToken = getPayPalAccessToken();
        if (!$accessToken) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Errore di configurazione PayPal'
            ]);
            return;
        }

        // Prima recupera l'ordine per verificare booking_id
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $paypalApiBase . '/v2/checkout/orders/' . $orderId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $orderResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log('PayPal Get Order Error: HTTP ' . $httpCode);
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Ordine PayPal non trovato'
            ]);
            return;
        }

        $orderData = json_decode($orderResponse, true);

        // ZERO-TRUST: Verifica che l'ordine sia per questo booking
        $orderBookingId = $orderData['purchase_units'][0]['custom_id'] ?? '';
        if ($orderBookingId !== $bookingId) {
            error_log("PayPal Order mismatch: order=$orderBookingId, request=$bookingId");
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Ordine non corrispondente alla prenotazione'
            ]);
            return;
        }

        // Cattura il pagamento
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $paypalApiBase . '/v2/checkout/orders/' . $orderId . '/capture',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
                'PayPal-Request-Id: capture_' . $bookingId . '_' . time()
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $captureResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201 && $httpCode !== 200) {
            // SECURITY: Non loggare payload completo per protezione PII
            $errorCode = json_decode($captureResponse, true)['name'] ?? 'UNKNOWN';
            error_log('PayPal Capture Error: HTTP ' . $httpCode . ' - code: ' . $errorCode . ' - order: ' . $orderId);
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Errore nella cattura del pagamento'
            ]);
            return;
        }

        $captureData = json_decode($captureResponse, true);

        // Verifica stato cattura
        if (($captureData['status'] ?? '') !== 'COMPLETED') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Pagamento non completato',
                'status' => $captureData['status'] ?? 'unknown'
            ]);
            return;
        }

        // Estrai ID transazione
        $transactionId = $captureData['purchase_units'][0]['payments']['captures'][0]['id'] ?? $orderId;
        $capturedAmount = $captureData['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? '0';

        // Aggiorna database
        $conn->begin_transaction();

        updateBookingPaymentStatus($bookingId, 'completed', 'paypal', $transactionId);

        // Inserisci record pagamento
        $stmt = $conn->prepare("INSERT INTO payments
            (booking_id, transaction_id, amount, method, status, created_at)
            VALUES (?, ?, ?, 'paypal', 'completed', NOW())
            ON DUPLICATE KEY UPDATE status = 'completed'");

        if ($stmt) {
            $stmt->bind_param("ssd", $bookingId, $transactionId, $capturedAmount);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Pagamento PayPal completato',
            'transaction_id' => $transactionId,
            'amount' => $capturedAmount
        ]);

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollback();
        }
        // SECURITY: Logga solo tipo eccezione
        error_log('PayPal Capture Exception: ' . get_class($e) . ' - order=' . $orderId);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Errore interno nella cattura del pagamento'
        ]);
    }
}

/**
 * Processa pagamento PayPal (legacy, mantenuta per compatibilità)
 * ZERO-TRUST: L'importo viene passato dal chiamante (recuperato dal DB),
 * non accettato dal client
 *
 * @param string $bookingId ID prenotazione
 * @param float $amount Importo autoritativo dal database
 * @deprecated Usare createPayPalOrder + capturePayPalOrder
 */
function processPayPalPayment($bookingId, $amount) {
    global $conn;

    try {
        // Legacy: questa funzione è mantenuta per retrocompatibilità
        // I nuovi pagamenti usano createPayPalOrder + capturePayPalOrder

        // Genera transaction ID
        $transactionId = 'PP_' . date('YmdHis') . '_' . bin2hex(random_bytes(8));

        // Inizia transazione
        $conn->begin_transaction();

        // Aggiorna stato prenotazione
        updateBookingPaymentStatus($bookingId, 'completed', 'paypal', $transactionId);

        $conn->commit();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Pagamento PayPal completato',
            'transaction_id' => $transactionId,
            'payment_method' => 'paypal',
            'amount' => $amount
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        // SECURITY: Logga solo tipo eccezione
        error_log('PayPal Payment Error: ' . get_class($e) . ' - booking=' . $bookingId);

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Errore nell\'elaborazione del pagamento PayPal'
        ]);
    }
}

/**
 * Processa conferma pagamento IBAN
 * ZERO-TRUST: L'importo viene passato dal chiamante (recuperato dal DB),
 * non accettato dal client
 *
 * @param string $bookingId ID prenotazione
 * @param float $amount Importo autoritativo dal database
 */
function processIBANPayment($bookingId, $amount) {
    global $conn;

    try {
        // Genera reference ID per bonifico
        $referenceId = 'BNF_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));

        // Inizia transazione
        $conn->begin_transaction();

        // Aggiorna stato prenotazione come in attesa bonifico
        updateBookingPaymentStatus($bookingId, 'pending_transfer', 'iban', $referenceId);

        $conn->commit();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Prenotazione confermata. In attesa del bonifico.',
            'reference_id' => $referenceId,
            'payment_method' => 'iban',
            'amount' => $amount,
            'status' => 'pending_transfer',
            'instructions' => 'Effettua il bonifico indicando il codice ' . $bookingId . ' come causale.'
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        // SECURITY: Logga solo tipo eccezione
        error_log('IBAN Payment Error: ' . get_class($e) . ' - booking=' . $bookingId);

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Errore nella conferma prenotazione'
        ]);
    }
}

/**
 * Aggiorna stato pagamento prenotazione
 * SICUREZZA: Usa prepared statements per prevenire SQL injection
 * HARDENING: Messaggi di errore generici per prevenire Information Disclosure
 */
function updateBookingPaymentStatus($bookingId, $status, $method, $transactionId) {
    global $conn;

    // Whitelist per validazione (defense in depth)
    $validStatuses = ['pending', 'processing', 'completed', 'failed', 'pending_transfer', 'refunded'];
    $validMethods = ['card', 'paypal', 'iban', 'unknown'];

    // HARDENING: Log dettagliato lato server, messaggio generico per Information Disclosure prevention
    if (!in_array($status, $validStatuses, true)) {
        error_log('updateBookingPaymentStatus: stato non valido - ' . $status);
        throw new Exception('Operazione non consentita');
    }
    if (!in_array($method, $validMethods, true)) {
        error_log('updateBookingPaymentStatus: metodo non valido - ' . $method);
        throw new Exception('Operazione non consentita');
    }
    if (!validateBookingId($bookingId)) {
        error_log('updateBookingPaymentStatus: booking_id non valido - ' . substr($bookingId, 0, 20));
        throw new Exception('Operazione non consentita');
    }

    // Verifica se le colonne pagamento esistono
    $checkColumns = $conn->query("SHOW COLUMNS FROM prenotazioni LIKE 'payment_status'");

    if ($checkColumns && $checkColumns->num_rows > 0) {
        // Prepared statement con tutti i campi pagamento
        if ($status === 'completed') {
            $stmt = $conn->prepare("UPDATE prenotazioni
                SET payment_status = ?,
                    payment_method = ?,
                    transaction_id = ?,
                    paid_at = NOW()
                WHERE booking_id = ?");
            $stmt->bind_param("ssss", $status, $method, $transactionId, $bookingId);
        } else {
            $stmt = $conn->prepare("UPDATE prenotazioni
                SET payment_status = ?,
                    payment_method = ?,
                    transaction_id = ?
                WHERE booking_id = ?");
            $stmt->bind_param("ssss", $status, $method, $transactionId, $bookingId);
        }
    } else {
        // Fallback: aggiorna solo lo status principale con prepared statement
        $newStatus = ($status === 'completed') ? 'paid' : 'pending_payment';
        $stmt = $conn->prepare("UPDATE prenotazioni SET status = ? WHERE booking_id = ?");
        $stmt->bind_param("ss", $newStatus, $bookingId);
    }

    // HARDENING: Controllo statement con messaggio generico
    if ($stmt === false) {
        error_log('Payment Query Prep Error: ' . $conn->error);
        throw new Exception('Errore nel sistema di pagamento');
    }

    $result = $stmt->execute();

    if ($result !== true) {
        error_log('Payment Update Error: ' . $stmt->error);
        $stmt->close();
        throw new Exception('Errore nel sistema di pagamento');
    }

    if ($stmt->affected_rows === 0) {
        error_log('Payment Update: nessuna riga aggiornata per booking_id ' . substr($bookingId, 0, 20));
        $stmt->close();
        // HARDENING: Messaggio generico - non rivelare se prenotazione esiste o è già processata
        throw new Exception('Errore nel sistema di pagamento');
    }

    $stmt->close();
    return true;
}

// ===== PAYPAL API FUNCTIONS (ZERO-TRUST) =====

/**
 * Ottiene un access token PayPal OAuth2
 *
 * @return string|false Access token o false in caso di errore
 */
function getPayPalAccessToken() {
    global $paypalClientId, $paypalClientSecret, $paypalApiBase;

    if (empty($paypalClientId) || empty($paypalClientSecret)) {
        error_log('PayPal Error: Credenziali non configurate');
        return false;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $paypalApiBase . '/v1/oauth2/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_USERPWD => $paypalClientId . ':' . $paypalClientSecret,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('PayPal cURL Error: ' . $curlError);
        return false;
    }

    if ($httpCode !== 200) {
        // SECURITY: Non loggare payload OAuth per protezione credenziali
        $errorType = json_decode($response, true)['error'] ?? 'auth_failed';
        error_log('PayPal Auth Error: HTTP ' . $httpCode . ' - type: ' . $errorType);
        return false;
    }

    $data = json_decode($response, true);
    return $data['access_token'] ?? false;
}

/**
 * Crea un ordine PayPal - ZERO-TRUST
 * L'importo viene recuperato dal database, MAI dal client
 *
 * @param array $input Dati dalla richiesta (booking_id)
 */
function createPayPalOrder($input) {
    global $conn, $paypalApiBase;

    try {
        $bookingId = trim($input['booking_id'] ?? '');

        // Validazione booking_id
        if (!validateBookingId($bookingId)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID prenotazione non valido'
            ]);
            return;
        }

        // ZERO-TRUST: Recupera l'importo autoritativo dal database
        $bookingData = checkBookingForPayment($bookingId);

        if ($bookingData['valid'] !== true || $bookingData['booking'] === null) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Prenotazione non valida o già pagata',
                'errors' => $bookingData['errors'] ?? []
            ]);
            return;
        }

        $amount = $bookingData['booking']['amount'];

        // Ottieni access token PayPal
        $accessToken = getPayPalAccessToken();
        if (!$accessToken) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossibile connettersi a PayPal. Riprova più tardi.'
            ]);
            return;
        }

        // Crea l'ordine PayPal con l'importo dal DB
        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $bookingId,
                    'description' => 'Prenotazione Luxury Hotel - ' . $bookingId,
                    'amount' => [
                        'currency_code' => 'EUR',
                        'value' => number_format($amount, 2, '.', '')
                    ],
                    'custom_id' => $bookingId
                ]
            ],
            'application_context' => [
                'brand_name' => 'Luxury Hotel',
                'locale' => 'it-IT',
                'landing_page' => 'NO_PREFERENCE',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW'
            ]
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $paypalApiBase . '/v2/checkout/orders',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($orderData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
                'PayPal-Request-Id: ' . $bookingId . '_' . time() // Idempotency key
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('PayPal Create Order cURL Error: ' . $curlError);
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Errore di comunicazione con PayPal'
            ]);
            return;
        }

        $responseData = json_decode($response, true);

        if ($httpCode !== 201 || empty($responseData['id'])) {
            // SECURITY: Non loggare payload completo per protezione PII
            $errorCode = $responseData['name'] ?? 'UNKNOWN';
            error_log('PayPal Create Order Error: HTTP ' . $httpCode . ' - code: ' . $errorCode . ' - booking: ' . $bookingId);
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Errore nella creazione dell\'ordine PayPal'
            ]);
            return;
        }

        // Ordine creato con successo - restituisci l'ID al frontend
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'orderID' => $responseData['id'],
            'status' => $responseData['status']
        ]);

    } catch (Exception $e) {
        // SECURITY: Logga solo tipo eccezione
        error_log('PayPal Create Order Exception: ' . get_class($e) . ' - booking=' . $bookingId);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Errore interno durante la creazione dell\'ordine'
        ]);
    }
}

/**
 * Cattura un ordine PayPal dopo l'approvazione dell'utente
 *
 * @param array $input Dati dalla richiesta (order_id, booking_id)
 */
function capturePayPalOrder($input) {
    global $conn, $paypalApiBase;

    try {
        $orderId = trim($input['order_id'] ?? '');
        $bookingId = trim($input['booking_id'] ?? '');

        // Validazione input
        if (empty($orderId) || !preg_match('/^[A-Z0-9]+$/', $orderId)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Order ID PayPal non valido'
            ]);
            return;
        }

        if (!validateBookingId($bookingId)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID prenotazione non valido'
            ]);
            return;
        }

        // Ottieni access token PayPal
        $accessToken = getPayPalAccessToken();
        if (!$accessToken) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossibile connettersi a PayPal'
            ]);
            return;
        }

        // Cattura il pagamento
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $paypalApiBase . '/v2/checkout/orders/' . $orderId . '/capture',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
                'PayPal-Request-Id: capture_' . $bookingId . '_' . time()
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('PayPal Capture cURL Error: ' . $curlError);
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Errore di comunicazione con PayPal'
            ]);
            return;
        }

        $responseData = json_decode($response, true);

        if ($httpCode !== 201 || ($responseData['status'] ?? '') !== 'COMPLETED') {
            // SECURITY: Non loggare payload completo per protezione PII
            $errorCode = $responseData['name'] ?? ($responseData['status'] ?? 'UNKNOWN');
            error_log('PayPal Capture Error: HTTP ' . $httpCode . ' - code: ' . $errorCode . ' - order: ' . $orderId);
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Pagamento PayPal non completato',
                'status' => $responseData['status'] ?? 'UNKNOWN'
            ]);
            return;
        }

        // SICUREZZA: Verifica che il booking_id nel custom_id corrisponda
        $capturedBookingId = $responseData['purchase_units'][0]['payments']['captures'][0]['custom_id'] ?? '';
        if ($capturedBookingId !== $bookingId) {
            error_log('PayPal Capture: booking_id mismatch - expected: ' . $bookingId . ', got: ' . $capturedBookingId);
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Prenotazione non corrispondente'
            ]);
            return;
        }

        // Estrai transaction ID dal capture
        $captureId = $responseData['purchase_units'][0]['payments']['captures'][0]['id'] ?? $orderId;
        $amount = $responseData['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0;
        $payerEmail = $responseData['payer']['email_address'] ?? '';

        // Inizia transazione DB
        $conn->begin_transaction();

        // Aggiorna stato prenotazione
        updateBookingPaymentStatus($bookingId, 'completed', 'paypal', $captureId);

        // Inserisci record pagamento
        $stmt = $conn->prepare("INSERT INTO payments
            (booking_id, transaction_id, amount, method, paypal_email, status, created_at)
            VALUES (?, ?, ?, 'paypal', ?, 'completed', NOW())
            ON DUPLICATE KEY UPDATE status = 'completed', paypal_email = ?");

        if ($stmt) {
            $amountFloat = floatval($amount);
            $stmt->bind_param("ssdss", $bookingId, $captureId, $amountFloat, $payerEmail, $payerEmail);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();

        // Log per audit
        error_log("PayPal payment completed: booking=$bookingId, capture=$captureId, amount=$amount");

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Pagamento PayPal completato con successo',
            'transaction_id' => $captureId,
            'amount' => $amount,
            'payer_email' => $payerEmail
        ]);

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollback();
        }
        // SECURITY: Logga solo tipo eccezione
        error_log('PayPal Capture Exception: ' . get_class($e) . ' - order=' . $orderId);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Errore durante la cattura del pagamento'
        ]);
    }
}

// ===== WEBHOOK HANDLER (per integrazioni future) =====

/**
 * Valida la firma HMAC del webhook
 *
 * @param string $payload Il payload raw della richiesta
 * @param string $signature La firma ricevuta nell'header
 * @param string $secret Il segreto condiviso con il provider
 * @return bool True se la firma è valida
 */
function validateWebhookSignature($payload, $signature, $secret) {
    if (empty($signature) || empty($secret)) {
        return false;
    }

    // Calcola l'HMAC-SHA256 del payload
    $expectedSignature = hash_hmac('sha256', $payload, $secret);

    // Confronto timing-safe per prevenire timing attacks
    return hash_equals($expectedSignature, $signature);
}

/**
 * Gestisce webhook da Stripe
 *
 * SICUREZZA: Richiede validazione della firma webhook Stripe obbligatoria.
 * Il webhook viene processato SOLO se la firma crittografica è valida.
 *
 * Endpoint: POST /api/payments.php?action=webhook
 */
function handleWebhook() {
    global $conn, $stripeSecretKey;

    // Leggi il payload raw PRIMA di qualsiasi parsing
    $payload = file_get_contents('php://input');

    // Ottieni la firma dall'header Stripe
    $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    // Carica il webhook secret dalla configurazione
    $webhookSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? getenv('STRIPE_WEBHOOK_SECRET') ?: '';

    // SICUREZZA: Rifiuta webhook se il segreto non è configurato
    if (empty($webhookSecret)) {
        error_log('Webhook Error: STRIPE_WEBHOOK_SECRET non configurato');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Webhook endpoint not configured'
        ]);
        return;
    }

    // SICUREZZA: Rifiuta webhook senza firma
    if (empty($sigHeader)) {
        error_log('Webhook Error: Firma Stripe mancante - IP: ' . getClientIp());
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Missing webhook signature'
        ]);
        return;
    }

    try {
        // Verifica la firma usando il metodo ufficiale Stripe
        \Stripe\Stripe::setApiKey($stripeSecretKey);
        $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);

    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        // SECURITY: Non loggare dettagli, solo evento e IP
        error_log('Webhook Error: Firma Stripe non valida - IP: ' . getClientIp());
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid webhook signature'
        ]);
        return;
    } catch (\UnexpectedValueException $e) {
        // SECURITY: Non loggare contenuto payload
        error_log('Webhook Error: Payload non valido - IP: ' . getClientIp());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid payload'
        ]);
        return;
    }

    // Log dell'evento webhook ricevuto (per audit)
    error_log('Stripe Webhook ricevuto: ' . $event->type);

    try {
        // Gestisci i diversi tipi di evento Stripe
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                $bookingId = $paymentIntent->metadata['booking_id'] ?? '';

                if (!empty($bookingId) && validateBookingId($bookingId)) {
                    updateBookingPaymentStatus(
                        $bookingId,
                        'completed',
                        'card',
                        $paymentIntent->id
                    );
                    error_log("Webhook: Pagamento completato per booking $bookingId");
                }
                break;

            case 'payment_intent.payment_failed':
                $paymentIntent = $event->data->object;
                $bookingId = $paymentIntent->metadata['booking_id'] ?? '';

                if (!empty($bookingId) && validateBookingId($bookingId)) {
                    updateBookingPaymentStatus(
                        $bookingId,
                        'failed',
                        'card',
                        $paymentIntent->id
                    );
                    error_log("Webhook: Pagamento fallito per booking $bookingId");
                }
                break;

            case 'charge.refunded':
                $charge = $event->data->object;
                $paymentIntentId = $charge->payment_intent;

                // Trova il booking_id dalla transazione
                if ($conn !== null && !empty($paymentIntentId)) {
                    $stmt = $conn->prepare("SELECT booking_id FROM prenotazioni WHERE transaction_id = ?");
                    $stmt->bind_param("s", $paymentIntentId);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        updateBookingPaymentStatus(
                            $row['booking_id'],
                            'refunded',
                            'card',
                            $paymentIntentId
                        );
                        error_log("Webhook: Rimborso processato per booking " . $row['booking_id']);
                    }
                    $stmt->close();
                }
                break;

            default:
                // Eventi non gestiti - log per debug
                error_log('Webhook: evento non gestito: ' . $event->type);
        }

        http_response_code(200);
        echo json_encode(['received' => true]);

    } catch (Exception $e) {
        // SECURITY: Logga solo tipo eccezione e tipo evento
        error_log('Webhook Processing Error: ' . get_class($e) . ' - event_type=' . ($event->type ?? 'unknown'));
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error processing webhook'
        ]);
    }
}
?>
