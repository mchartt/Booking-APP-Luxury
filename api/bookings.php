<?php
// api/bookings.php - REST API per prenotazioni con error handling robusto

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';

// Gestisci OPTIONS richieste (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
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
 * Ritorna tutte le prenotazioni (limitate a 100)
 */
function getAllBookings() {
    global $conn;

    try {
        $query = "SELECT id, room_type, check_in, check_out, guests, name, email,
                  created_at, status FROM prenotazioni ORDER BY created_at DESC LIMIT 100";

        $result = $conn->query($query);

        if (!$result) {
            throw new Exception('Errore nella query: ' . $conn->error);
        }

        $bookings = [];
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row;
        }

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
 * Crea nuova prenotazione
 */
function createBooking() {
    global $conn;

    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            throw new Exception('Request JSON non valido');
        }

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

        // Sanitizza input
        $roomType = sanitize($input['room_type']);
        $checkIn = sanitize($input['check_in']);
        $checkOut = sanitize($input['check_out']);
        $guests = (int)$input['guests'];
        $name = sanitize($input['name']);
        $email = sanitize($input['email']);
        $phone = sanitize($input['phone']);
        $requests = sanitize($input['requests'] ?? '');

        // Calcola notti e prezzo
        try {
            $start = new DateTime($checkIn);
            $end = new DateTime($checkOut);
            $nights = $start->diff($end)->days;
        } catch (Exception $e) {
            throw new Exception('Errore nel calcolo delle notti');
        }

        $roomPrices = [
            'Standard' => 120,
            'Deluxe' => 180,
            'Suite' => 280
        ];

        $pricePerNight = $roomPrices[$roomType] ?? 0;
        $totalPrice = $nights * $pricePerNight;

        // Crea ID univoco
        $bookingId = 'BK' . date('YmdHis') . '_' . substr(md5($email . time() . rand()), 0, 8);

        // Prepara query
        $query = "INSERT INTO prenotazioni
                  (booking_id, room_type, check_in, check_out, guests, name, email, phone,
                   requests, nights, price_per_night, total_price, status, created_at)
                  VALUES
                  ('$bookingId', '$roomType', '$checkIn', '$checkOut', $guests, '$name',
                   '$email', '$phone', '$requests', $nights, $pricePerNight, $totalPrice,
                   'confirmed', NOW())";

        if (!$conn->query($query)) {
            throw new Exception('Errore nel salvataggio della prenotazione: ' . $conn->error);
        }

        $bookingData = [
            'id' => $conn->insert_id,
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
