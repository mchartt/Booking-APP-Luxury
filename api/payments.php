<?php
/**
 * api/payments.php - API REST per gestione pagamenti
 * Gestisce transazioni, validazione e aggiornamento stato prenotazioni
 */

// Security headers centralizzati
require_once __DIR__ . '/security_headers.php';

header('Content-Type: application/json; charset=utf-8');

require_once '../config.php';

// ===== SESSION TIMEOUT & RATE LIMITING =====

// Secure session cookie settings
$isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Strict'
]);

// Session timeout lato server (15 minuti)
session_start();
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

// Rate limiting basato su IP (usa database per persistenza)
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
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
    // SICUREZZA: Valida token CSRF
    validateCsrfToken();

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'JSON non valido'
        ]);
        return;
    }

    // Validazione dati base
    $validation = validatePaymentRequest($input);
    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Dati pagamento non validi',
            'errors' => $validation['errors']
        ]);
        return;
    }

    // Processa in base al metodo
    $method = $input['method'];

    switch ($method) {
        case 'card':
            processCardPayment($input);
            break;

        case 'paypal':
            processPayPalPayment($input);
            break;

        case 'iban':
            processIBANPayment($input);
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
 * Valida richiesta pagamento
 */
function validatePaymentRequest($data) {
    $errors = [];

    // Campi obbligatori
    if (empty($data['booking_id'])) {
        $errors[] = 'ID prenotazione obbligatorio';
    } elseif (!validateBookingId($data['booking_id'])) {
        $errors[] = 'ID prenotazione non valido';
    }

    if (empty($data['amount'])) {
        $errors[] = 'Importo obbligatorio';
    } elseif (!is_numeric($data['amount']) || $data['amount'] <= 0 || $data['amount'] > 100000) {
        $errors[] = 'Importo non valido';
    }

    if (empty($data['method'])) {
        $errors[] = 'Metodo di pagamento obbligatorio';
    } elseif (!in_array($data['method'], VALID_PAYMENT_METHODS)) {
        $errors[] = 'Metodo di pagamento non supportato';
    }

    // Verifica che la prenotazione esista e non sia già pagata
    if (empty($errors) && !empty($data['booking_id'])) {
        $bookingCheck = checkBookingForPayment($data['booking_id'], $data['amount']);
        if (!$bookingCheck['valid']) {
            $errors = array_merge($errors, $bookingCheck['errors']);
        }
    }

    return [
        'valid' => count($errors) === 0,
        'errors' => $errors
    ];
}

/**
 * Verifica prenotazione per pagamento
 */
function checkBookingForPayment($bookingId, $amount) {
    global $conn;

    $errors = [];
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

        // Verifica importo (tolleranza di 0.01 per arrotondamenti)
        if (abs(floatval($row['total_price']) - floatval($amount)) > 0.01) {
            $errors[] = 'Importo non corrispondente alla prenotazione';
        }
    }

    $stmt->close();

    return [
        'valid' => count($errors) === 0,
        'errors' => $errors
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
 * Processa pagamento con carta
 */
function processCardPayment($data) {
    global $conn;

    try {
        // In produzione: qui ci sarebbe l'integrazione con Stripe/Braintree/etc.
        // Per demo, simuliamo il processo

        $bookingId = trim($data['booking_id']);
        $amount = floatval($data['amount']);
        $cardLastFour = trim($data['card_last_four'] ?? '****');
        $cardBrand = trim($data['card_brand'] ?? 'unknown');

        // Genera transaction ID
        $transactionId = 'TXN_' . date('YmdHis') . '_' . bin2hex(random_bytes(8));

        // Inizia transazione
        $conn->begin_transaction();

        // Inserisci record pagamento
        $stmt = $conn->prepare("INSERT INTO payments
            (booking_id, transaction_id, amount, method, card_last_four, card_brand, status, created_at)
            VALUES (?, ?, ?, 'card', ?, ?, 'completed', NOW())");

        if (!$stmt) {
            // Se la tabella payments non esiste, aggiorna solo prenotazioni
            updateBookingPaymentStatus($bookingId, 'completed', 'card', $transactionId);
        } else {
            $stmt->bind_param("ssdss", $bookingId, $transactionId, $amount, $cardLastFour, $cardBrand);
            $stmt->execute();
            $stmt->close();

            // Aggiorna stato prenotazione
            updateBookingPaymentStatus($bookingId, 'completed', 'card', $transactionId);
        }

        $conn->commit();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Pagamento completato con successo',
            'transaction_id' => $transactionId,
            'payment_method' => 'card',
            'amount' => $amount
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log('Card Payment Error: ' . $e->getMessage());

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Errore nell\'elaborazione del pagamento',
            'errors' => ['Si è verificato un errore. Riprova o contatta l\'assistenza.']
        ]);
    }
}

/**
 * Processa pagamento PayPal
 */
function processPayPalPayment($data) {
    global $conn;

    try {
        // In produzione: qui ci sarebbe l'integrazione con PayPal SDK
        // Per demo, simuliamo il redirect

        $bookingId = trim($data['booking_id']);
        $amount = floatval($data['amount']);

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
        error_log('PayPal Payment Error: ' . $e->getMessage());

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Errore nell\'elaborazione del pagamento PayPal'
        ]);
    }
}

/**
 * Processa conferma pagamento IBAN
 */
function processIBANPayment($data) {
    global $conn;

    try {
        $bookingId = trim($data['booking_id']);
        $amount = floatval($data['amount']);

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
        error_log('IBAN Payment Error: ' . $e->getMessage());

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
 */
function updateBookingPaymentStatus($bookingId, $status, $method, $transactionId) {
    global $conn;

    // Whitelist per validazione (defense in depth)
    $validStatuses = ['pending', 'processing', 'completed', 'failed', 'pending_transfer', 'refunded'];
    $validMethods = ['card', 'paypal', 'iban', 'unknown'];

    if (!in_array($status, $validStatuses, true)) {
        throw new Exception('Stato pagamento non valido');
    }
    if (!in_array($method, $validMethods, true)) {
        throw new Exception('Metodo pagamento non valido');
    }
    if (!validateBookingId($bookingId)) {
        throw new Exception('ID prenotazione non valido');
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

    if (!$stmt) {
        error_log('Payment Query Prep Error: ' . $conn->error);
        throw new Exception('Errore nel sistema di pagamento. Riprova più tardi.');
    }

    $result = $stmt->execute();

    if (!$result) {
        error_log('Payment Update Error: ' . $stmt->error);
        $stmt->close();
        throw new Exception('Errore aggiornamento stato prenotazione. Riprova più tardi.');
    }

    if ($stmt->affected_rows === 0) {
        $stmt->close();
        throw new Exception('Prenotazione non trovata o già aggiornata');
    }

    $stmt->close();
    return true;
}

// ===== WEBHOOK HANDLER (per integrazioni future) =====

/**
 * Gestisce webhook da payment provider
 * Da chiamare con endpoint separato o action=webhook
 */
function handleWebhook() {
    // Verifica firma webhook (provider-specific)
    $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
    $payload = file_get_contents('php://input');

    // In produzione: verificare la firma con la chiave segreta del provider

    $data = json_decode($payload, true);

    if (!$data || empty($data['event'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid webhook payload']);
        return;
    }

    switch ($data['event']) {
        case 'payment.completed':
            // Aggiorna stato pagamento
            if (!empty($data['booking_id'])) {
                updateBookingPaymentStatus(
                    $data['booking_id'],
                    'completed',
                    $data['method'] ?? 'unknown',
                    $data['transaction_id'] ?? ''
                );
            }
            break;

        case 'payment.failed':
            if (!empty($data['booking_id'])) {
                updateBookingPaymentStatus(
                    $data['booking_id'],
                    'failed',
                    $data['method'] ?? 'unknown',
                    $data['transaction_id'] ?? ''
                );
            }
            break;

        case 'payment.refunded':
            if (!empty($data['booking_id'])) {
                updateBookingPaymentStatus(
                    $data['booking_id'],
                    'refunded',
                    $data['method'] ?? 'unknown',
                    $data['transaction_id'] ?? ''
                );
            }
            break;
    }

    http_response_code(200);
    echo json_encode(['received' => true]);
}
?>
