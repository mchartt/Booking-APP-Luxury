<?php
/**
 * api/stripe-config.php - Configurazione Stripe PaymentIntent
 *
 * PCI-DSS COMPLIANT: Questo endpoint crea un PaymentIntent sul server
 * e restituisce solo il client_secret al frontend.
 *
 * SICUREZZA:
 * - La secret key è solo lato server (mai esposta)
 * - Il frontend riceve solo la publishable key (pubblica per design)
 * - Il client_secret è monouso e legato al singolo PaymentIntent
 * - Nessun dato carta transita per questo endpoint
 */

// Security headers
require_once __DIR__ . '/security_headers.php';

header('Content-Type: application/json; charset=utf-8');

require_once '../config.php';
require_once __DIR__ . '/stripe-constants.php';

// Carica Stripe SDK via Composer (se disponibile) o configurazione manuale
// In produzione: composer require stripe/stripe-php
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// ===== CONFIGURAZIONE STRIPE (SECURE) =====
// Le chiavi vengono lette ESCLUSIVAMENTE dalle variabili d'ambiente
// NON usare MAI fallback hardcoded - sono un rischio di sicurezza!

// Leggi chiavi dalle variabili d'ambiente
$stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY') ?: '';
$stripePublishableKey = $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? getenv('STRIPE_PUBLISHABLE_KEY') ?: '';

// ===== FAIL-SAFE: Blocca se le chiavi non sono configurate =====
// SICUREZZA: Il sistema NON deve mai funzionare senza chiavi valide

if (empty($stripeSecretKey) || empty($stripePublishableKey)) {
    error_log('CRITICAL: Stripe API keys not configured. Check .env file.');
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Configurazione gateway di pagamento mancante',
        'setup_required' => true
    ]);
    exit;
}

// Verifica che le chiavi non siano placeholder
if (strpos($stripeSecretKey, 'inserisci_qui') !== false ||
    strpos($stripePublishableKey, 'inserisci_qui') !== false ||
    strpos($stripeSecretKey, 'XXXX') !== false ||
    strpos($stripePublishableKey, 'XXXX') !== false) {
    error_log('CRITICAL: Stripe API keys contain placeholder values. Configure real keys in .env');
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Configurazione gateway di pagamento mancante',
        'setup_required' => true
    ]);
    exit;
}

// Verifica formato chiavi (deve iniziare con sk_ per secret e pk_ per publishable)
if (!preg_match('/^sk_(test|live)_[a-zA-Z0-9]+$/', $stripeSecretKey)) {
    error_log('CRITICAL: Invalid Stripe secret key format');
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Configurazione gateway di pagamento non valida'
    ]);
    exit;
}

if (!preg_match('/^pk_(test|live)_[a-zA-Z0-9]+$/', $stripePublishableKey)) {
    error_log('CRITICAL: Invalid Stripe publishable key format');
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Configurazione gateway di pagamento non valida'
    ]);
    exit;
}

// ===== GESTIONE RICHIESTA =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Metodo non consentito'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'JSON non valido'
    ]);
    exit;
}

// ===== VALIDAZIONE INPUT =====
$errors = [];

$bookingId = trim($input['booking_id'] ?? '');
// ZERO-TRUST: L'importo NON viene accettato dal client
// Viene recuperato esclusivamente dal database
$description = trim($input['description'] ?? '');
$customerEmail = trim($input['customer_email'] ?? '');
// La valuta di Stripe deve essere determinata esclusivamente dal server.
// Le prenotazioni vengono memorizzate in EUR, quindi non accettiamo override dal client.
$currency = STRIPE_DEFAULT_CURRENCY;

// Validazione booking_id
if (empty($bookingId) || !preg_match('/^BK\d{14}_[a-f0-9]{8}$/i', $bookingId)) {
    $errors[] = 'ID prenotazione non valido';
}

// Validazione email
if (!empty($customerEmail) && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email non valida';
}

if (count($errors) > 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Dati non validi',
        'errors' => $errors
    ]);
    exit;
}

// ===== RECUPERO IMPORTO AUTORITATIVO DAL DATABASE (ZERO-TRUST) =====
$amount = 0;

if ($conn === null) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Connessione database non disponibile'
    ]);
    exit;
}

$stmt = $conn->prepare("SELECT id, total_price, payment_status, status FROM prenotazioni WHERE booking_id = ?");
$stmt->bind_param("s", $bookingId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Prenotazione non trovata'
    ]);
    $stmt->close();
    exit;
}

$booking = $result->fetch_assoc();
$stmt->close();

// Verifica stato prenotazione
if ($booking['status'] === 'cancelled') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Questa prenotazione è stata cancellata'
    ]);
    exit;
}

// Verifica se già pagata
if ($booking['payment_status'] === 'completed') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Questa prenotazione è già stata pagata'
    ]);
    exit;
}

// ZERO-TRUST: Usa ESCLUSIVAMENTE l'importo dal database
$amount = floatval($booking['total_price']);

// Validazione importo dal DB (sanity check)
if ($amount <= 0 || $amount > 100000) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Importo prenotazione non valido nel database'
    ]);
    exit;
}

// ===== CREA PAYMENTINTENT CON STRIPE =====
try {
    // Imposta la chiave segreta Stripe
    \Stripe\Stripe::setApiKey($stripeSecretKey);

    // Converti importo in centesimi (Stripe usa la più piccola unità di valuta)
    $amountInCents = (int)round($amount * 100);

    // Crea il PaymentIntent
    $paymentIntentParams = [
        'amount' => $amountInCents,
        'currency' => $currency,
        'automatic_payment_methods' => [
            'enabled' => true, // Abilita carte, Apple Pay, Google Pay, etc.
        ],
        'metadata' => [
            'booking_id' => $bookingId,
            'integration_source' => 'luxury_hotel_website'
        ]
    ];

    // Aggiungi descrizione se fornita
    if (!empty($description)) {
        $paymentIntentParams['description'] = substr($description, 0, 500); // Max 500 chars
    }

    // Aggiungi email per la ricevuta se fornita
    if (!empty($customerEmail)) {
        $paymentIntentParams['receipt_email'] = $customerEmail;
    }

    $paymentIntent = \Stripe\PaymentIntent::create($paymentIntentParams);

    // Salva il PaymentIntent ID nel database per riferimento
    if ($conn !== null) {
        $stmt = $conn->prepare("UPDATE prenotazioni SET transaction_id = ?, payment_status = 'processing' WHERE booking_id = ?");
        $paymentIntentId = $paymentIntent->id;
        $stmt->bind_param("ss", $paymentIntentId, $bookingId);
        $stmt->execute();
        $stmt->close();
    }

    // Restituisci la configurazione al frontend
    // SICUREZZA: Mai restituire la secret key!
    echo json_encode([
        'success' => true,
        'publishable_key' => $stripePublishableKey,
        'client_secret' => $paymentIntent->client_secret,
        'payment_intent_id' => $paymentIntent->id,
        'amount' => $amount,
        'currency' => $currency
    ]);

} catch (\Stripe\Exception\CardException $e) {
    // Errore relativo alla carta (declinata, etc.)
    // SECURITY: Logga solo codice errore, non messaggio completo che potrebbe contenere info carta
    $errorCode = $e->getError()->code ?? 'card_error';
    error_log('Stripe CardException: code=' . $errorCode . ' - booking=' . $bookingId);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Carta non valida o rifiutata',
        'error_code' => $errorCode
    ]);

} catch (\Stripe\Exception\RateLimitException $e) {
    // Troppi request a Stripe
    error_log('Stripe RateLimitException - booking=' . $bookingId);
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Servizio temporaneamente non disponibile. Riprova tra poco.'
    ]);

} catch (\Stripe\Exception\InvalidRequestException $e) {
    // Parametri non validi
    // SECURITY: Logga solo codice errore
    $errorCode = $e->getError()->code ?? 'invalid_request';
    error_log('Stripe InvalidRequestException: code=' . $errorCode . ' - booking=' . $bookingId);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Errore nella richiesta di pagamento'
    ]);

} catch (\Stripe\Exception\AuthenticationException $e) {
    // Chiave API non valida
    error_log('Stripe AuthenticationException: API key configuration error');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore di configurazione del sistema di pagamento'
    ]);

} catch (\Stripe\Exception\ApiConnectionException $e) {
    // Problema di rete con Stripe
    error_log('Stripe ApiConnectionException - booking=' . $bookingId);
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Impossibile connettersi al sistema di pagamento. Riprova.'
    ]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    // Errore generico Stripe
    // SECURITY: Logga solo codice errore
    $errorCode = method_exists($e, 'getError') && $e->getError() ? ($e->getError()->code ?? 'api_error') : 'api_error';
    error_log('Stripe ApiErrorException: code=' . $errorCode . ' - booking=' . $bookingId);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore nel sistema di pagamento. Riprova più tardi.'
    ]);

} catch (Exception $e) {
    // Altro errore
    // SECURITY: Logga solo tipo eccezione, non messaggio completo
    error_log('Payment Config Error: ' . get_class($e) . ' - booking=' . $bookingId);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno. Riprova più tardi.'
    ]);
}
?>
