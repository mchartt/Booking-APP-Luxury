<?php
/**
 * api/bookings.php - REST API per prenotazioni
 * Con error handling robusto e protezione SQL injection
 */

// Security headers e sessione centralizzati
require_once __DIR__ . '/security_headers.php';

header('Content-Type: application/json; charset=utf-8');

require_once '../config.php';

/**
 * Verifica se l'utente è autenticato come admin
 */
function requireAdminAuth() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Autenticazione richiesta',
            'code' => 'AUTH_REQUIRED'
        ]);
        exit;
    }
}

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
$action = $_GET['action'] ?? null;

try {
    // Verifica che la connessione al database sia disponibile
    if ($conn === null) {
        throw new Exception('Connessione al database non disponibile');
    }

    switch ($method) {
        case 'GET':
            if ($action === 'booked-dates') {
                getBookedDates();
            } elseif ($action === 'availability') {
                checkAvailability();
            } else {
                getAllBookings();
            }
            break;

        case 'POST':
            validateCsrfToken();
            createBooking();
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
    error_log('API Error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server',
        'errors' => ['Qualcosa è andato storto. Riprova più tardi.']
    ]);
}

// ===== FUNZIONI API =====

/**
 * Ritorna tutte le prenotazioni (limitate a 100) - Solo per admin autenticati
 */
function getAllBookings() {
    global $conn;

    // SICUREZZA: Richiede autenticazione admin
    requireAdminAuth();

    try {
        // Prepared statement (anche se senza parametri, per consistenza)
        $stmt = $conn->prepare("SELECT id, booking_id, room_type, check_in, check_out, guests, name, email,
                  created_at, status, payment_status FROM prenotazioni ORDER BY created_at DESC LIMIT 100");

        if (!$stmt) {
            error_log('GetAllBookings Query Error: ' . $conn->error);
            throw new Exception('Errore nel caricamento prenotazioni. Riprova più tardi.');
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $bookings = [];
        while ($row = $result->fetch_assoc()) {
            // NOTA: Email in chiaro per admin autenticati (necessario per contattare clienti)
            // L'endpoint è già protetto da requireAdminAuth()
            $bookings[] = $row;
        }

        $stmt->close();;

        echo json_encode([
            'success' => true,
            'bookings' => $bookings,
            'count' => count($bookings)
        ]);

    } catch (Exception $e) {
        error_log('getAllBookings Error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Ritorna date prenotate organizzate per camera
 */
function getBookedDates() {
    try {
        $roomType = $_GET['room_type'] ?? null;
        $bookedDatesByRoom = getBookedDateRanges($roomType);

        echo json_encode([
            'success' => true,
            'dates' => $bookedDatesByRoom
        ]);

    } catch (Exception $e) {
        error_log('getBookedDates Error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Verifica disponibilità di una camera per specifiche date
 */
function checkAvailability() {
    try {
        $checkIn = $_GET['check_in'] ?? null;
        $checkOut = $_GET['check_out'] ?? null;
        $roomType = $_GET['room_type'] ?? null;

        if (!$checkIn || !$checkOut || !$roomType) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Parametri mancanti',
                'errors' => ['Fornisci check_in, check_out e room_type']
            ]);
            return;
        }

        $available = isRoomAvailable($roomType, $checkIn, $checkOut);

        echo json_encode([
            'success' => true,
            'available' => $available,
            'room_type' => $roomType,
            'check_in' => $checkIn,
            'check_out' => $checkOut
        ]);

    } catch (Exception $e) {
        error_log('checkAvailability Error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Crea nuova prenotazione (con prepared statements per sicurezza)
 */
function createBooking() {
    global $conn;

    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            throw new Exception('Request JSON non valido');
        }

        // Mappatura chiavi frontend -> backend
        $input['room_type'] = $input['room_type'] ?? $input['roomType'] ?? '';
        $input['check_in'] = $input['check_in'] ?? $input['checkIn'] ?? '';
        $input['check_out'] = $input['check_out'] ?? $input['checkOut'] ?? '';

        // Validazione dati
        $validation = validateBooking($input);

        if (!$validation['valid']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'errors' => $validation['errors'],
                'message' => 'Errori nella validazione dei dati'
            ]);
            return;
        }

        // Estrai e valida input (NO sanitize per prepared statements)
        $roomType = trim($input['room_type']);
        $checkIn = trim($input['check_in']);
        $checkOut = trim($input['check_out']);
        $guests = (int)$input['guests'];
        $name = trim($input['name']);
        $email = trim($input['email']);
        $phone = trim($input['phone']);
        $requests = trim($input['requests'] ?? '');

        // Validazione aggiuntiva tipo stanza (whitelist)
        $validRoomTypes = ['Standard', 'Deluxe', 'Suite'];
        if (!in_array($roomType, $validRoomTypes)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Tipo di stanza non valido'
            ]);
            return;
        }

        // Calcola notti e prezzo
        try {
            $start = new DateTime($checkIn);
            $end = new DateTime($checkOut);
            $nights = $start->diff($end)->days;
        } catch (Exception $e) {
            throw new Exception('Errore nel calcolo delle notti');
        }

        // Prezzi fissi lato server (non fidarsi del client)
        $roomPrices = [
            'Standard' => 120,
            'Deluxe' => 180,
            'Suite' => 280
        ];

        $pricePerNight = $roomPrices[$roomType];
        $totalPrice = $nights * $pricePerNight;

        // Genera ID univoco sicuro
        $bookingId = 'BK' . date('YmdHis') . '_' . bin2hex(random_bytes(4));

        // Prepared statement per prevenire SQL injection
        $stmt = $conn->prepare("INSERT INTO prenotazioni
            (booking_id, room_type, check_in, check_out, guests, name, email, phone,
             requests, nights, price_per_night, total_price, status, payment_status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 'pending', NOW())");

        if (!$stmt) {
            error_log('CreateBooking Query Error: ' . $conn->error);
            throw new Exception('Errore nel salvataggio della prenotazione. Riprova più tardi.');
        }

        $stmt->bind_param(
            "ssssissssidi",
            $bookingId,
            $roomType,
            $checkIn,
            $checkOut,
            $guests,
            $name,
            $email,
            $phone,
            $requests,
            $nights,
            $pricePerNight,
            $totalPrice
        );

        if (!$stmt->execute()) {
            error_log('CreateBooking Execute Error: ' . $stmt->error);
            throw new Exception('Errore nel salvataggio della prenotazione. Riprova più tardi.');
        }

        $insertId = $conn->insert_id;
        $stmt->close();

        $bookingData = [
            'id' => $insertId,
            'booking_id' => $bookingId,
            'room_type' => $roomType,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guests' => $guests,
            'name' => $name,
            'email' => $email,
            'nights' => $nights,
            'total_price' => $totalPrice
        ];

        // Invia email di conferma (opzionale)
        sendConfirmationEmail($bookingData);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Prenotazione confermata con successo',
            'booking_id' => $bookingId,
            'booking' => $bookingData
        ]);

    } catch (Exception $e) {
        error_log('createBooking Error: ' . $e->getMessage());

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Errore nel salvataggio della prenotazione',
            'errors' => ['Qualcosa è andato storto. Riprova più tardi.']
        ]);
    }
}

/**
 * Invia email di conferma prenotazione
 */
function sendConfirmationEmail($booking) {
    try {
        $to = $booking['email'];
        $subject = 'Prenotazione Confermata - Luxury Hotel';

        $message = "
        <html>
            <head>
                <meta charset='UTF-8'>
                <title>Prenotazione Confermata</title>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #FF9A56 0%, #FF8C42 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f5f0; padding: 20px; }
                    .details { background: white; padding: 15px; border-radius: 5px; margin: 10px 0; }
                    .footer { background: #4A3728; color: white; padding: 15px; text-align: center; border-radius: 0 0 10px 10px; }
                    .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
                    .detail-row:last-child { border-bottom: none; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Prenotazione Confermata!</h2>
                    </div>
                    <div class='content'>
                        <p>Caro/a <strong>{$booking['name']}</strong>,</p>
                        <p>La tua prenotazione è stata confermata con successo presso <strong>Luxury Hotel</strong>.</p>

                        <div class='details'>
                            <h3>Dettagli Prenotazione:</h3>
                            <div class='detail-row'>
                                <span><strong>ID Prenotazione:</strong></span>
                                <span>{$booking['booking_id']}</span>
                            </div>
                            <div class='detail-row'>
                                <span><strong>Tipo Camera:</strong></span>
                                <span>{$booking['room_type']}</span>
                            </div>
                            <div class='detail-row'>
                                <span><strong>Check-in:</strong></span>
                                <span>" . date('d/m/Y', strtotime($booking['check_in'])) . "</span>
                            </div>
                            <div class='detail-row'>
                                <span><strong>Check-out:</strong></span>
                                <span>" . date('d/m/Y', strtotime($booking['check_out'])) . "</span>
                            </div>
                            <div class='detail-row'>
                                <span><strong>Numero Ospiti:</strong></span>
                                <span>{$booking['guests']}</span>
                            </div>
                            <div class='detail-row'>
                                <span><strong>Notti:</strong></span>
                                <span>{$booking['nights']}</span>
                            </div>
                            <div class='detail-row'>
                                <span><strong>Prezzo Totale:</strong></span>
                                <span style='color: #FF9A56; font-weight: bold;'>€ {$booking['total_price']}</span>
                            </div>
                        </div>

                        <p>Riceverai ulteriori informazioni via email nelle prossime 24 ore.</p>
                        <p>Grazie per aver scelto <strong>Luxury Hotel</strong>!</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2026 Luxury Hotel. Tutti i diritti riservati.</p>
                    </div>
                </div>
            </body>
        </html>
        ";

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: info@luxuryhotel.it\r\n";

        // Decommenta la prossima riga per abilitare l'invio email (richiede mail server configurato)
        // mail($to, $subject, $message, $headers);

    } catch (Exception $e) {
        error_log('sendConfirmationEmail Error: ' . $e->getMessage());
        // Non lanciare eccezione - l'email fallita non deve interrompere la prenotazione
    }
}
?>
