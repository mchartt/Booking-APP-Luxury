<?php
// config.php - Configurazione database con error handling robusto

defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
defined('DB_NAME') || define('DB_NAME', 'luxury_hotel');

// Variabile globale per connessione
$conn = null;

// Crea connessione con error handling completo
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Check connessione
    if ($conn->connect_error) {
        throw new Exception('Connessione fallita: ' . $conn->connect_error);
    }

    // Imposta charset UTF-8
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception('Errore nel setting charset: ' . $conn->error);
    }

} catch (Exception $e) {
    // Log l'errore (opzionale)
    error_log('Database Connection Error: ' . $e->getMessage());

    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Errore di connessione al database. Riprova più tardi.',
        'errors' => ['Servizio temporaneamente non disponibile']
    ]);
    exit;
}

// ===== FUNZIONI DI UTILITÀ =====

/**
 * Sanitizza input per prevenire SQL injection
 */
function sanitize($input) {
    global $conn;
    if ($conn === null) {
        return '';
    }
    return $conn->real_escape_string(trim($input));
}

/**
 * Valida email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? true : false;
}

/**
 * Valida formato data (Y-m-d)
 */
function validateDate($date) {
    try {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return ($d && $d->format('Y-m-d') === $date) ? true : false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Valida telefono (minimo 10 caratteri, solo numeri, spazi, +, -, parentesi)
 */
function validatePhone($phone) {
    return preg_match('/^[0-9\s\+\-\(\)]{10,}$/', trim($phone)) ? true : false;
}

/**
 * Controlla disponibilità camera con query sicura
 */
function isRoomAvailable($roomType, $checkIn, $checkOut) {
    global $conn;

    if ($conn === null) {
        throw new Exception('Database non disponibile');
    }

    try {
        $roomType = sanitize($roomType);
        $checkIn = sanitize($checkIn);
        $checkOut = sanitize($checkOut);

        // Query per verificare overlap di date
        $query = "SELECT COUNT(*) as count FROM prenotazioni
                  WHERE room_type = '$roomType'
                  AND status = 'confirmed'
                  AND NOT (check_out <= '$checkIn' OR check_in >= '$checkOut')";

        $result = $conn->query($query);

        if (!$result) {
            throw new Exception('Errore nella query: ' . $conn->error);
        }

        $row = $result->fetch_assoc();
        return $row['count'] == 0;

    } catch (Exception $e) {
        error_log('isRoomAvailable Error: ' . $e->getMessage());
        throw new Exception('Errore nel controllo disponibilità');
    }
}

/**
 * Ottiene date prenotate per una camera (formato range)
 */
function getBookedDateRanges($roomType = null) {
    global $conn;

    if ($conn === null) {
        throw new Exception('Database non disponibile');
    }

    try {
        $query = "SELECT room_type, check_in, check_out FROM prenotazioni WHERE status = 'confirmed'";

        if ($roomType) {
            $roomType = sanitize($roomType);
            $query .= " AND room_type = '$roomType'";
        }

        $result = $conn->query($query);

        if (!$result) {
            throw new Exception('Errore nella query: ' . $conn->error);
        }

        $bookedDatesByRoom = [];

        while ($row = $result->fetch_assoc()) {
            $room = $row['room_type'];
            if (!isset($bookedDatesByRoom[$room])) {
                $bookedDatesByRoom[$room] = [];
            }
            $bookedDatesByRoom[$room][] = [
                'start' => $row['check_in'],
                'end' => $row['check_out']
            ];
        }

        return $bookedDatesByRoom;

    } catch (Exception $e) {
        error_log('getBookedDateRanges Error: ' . $e->getMessage());
        throw new Exception('Errore nel caricamento date prenotate');
    }
}

/**
 * Valida prenotazione completa
 */
function validateBooking($data) {
    $errors = [];

    // Validazione campi obbligatori
    if (empty($data['room_type'])) $errors[] = 'Tipo di stanza obbligatorio';
    if (empty($data['check_in'])) $errors[] = 'Data check-in obbligatoria';
    if (empty($data['check_out'])) $errors[] = 'Data check-out obbligatoria';
    if (empty($data['guests'])) $errors[] = 'Numero ospiti obbligatorio';
    if (empty($data['name'])) $errors[] = 'Nome obbligatorio';
    if (empty($data['email'])) $errors[] = 'Email obbligatoria';
    if (empty($data['phone'])) $errors[] = 'Telefono obbligatorio';

    if (count($errors) > 0) {
        return ['valid' => false, 'errors' => $errors];
    }

    // Validazione formato
    if (!validateDate($data['check_in'])) {
        $errors[] = 'Formato data check-in non valido (usa YYYY-MM-DD)';
    }
    if (!validateDate($data['check_out'])) {
        $errors[] = 'Formato data check-out non valido (usa YYYY-MM-DD)';
    }
    if (!validateEmail($data['email'])) {
        $errors[] = 'Indirizzo email non valido';
    }
    if (!validatePhone($data['phone'])) {
        $errors[] = 'Numero di telefono non valido (minimo 10 caratteri)';
    }

    if (count($errors) > 0) {
        return ['valid' => false, 'errors' => $errors];
    }

    // Validazione logica date
    try {
        $checkIn = new DateTime($data['check_in']);
        $checkOut = new DateTime($data['check_out']);
        $today = new DateTime();
        $today->setTime(0, 0, 0);

        if ($checkIn < $today) {
            $errors[] = 'La data di check-in non può essere nel passato';
        }
        if ($checkOut <= $checkIn) {
            $errors[] = 'La data di check-out deve essere successiva al check-in';
        }

        $interval = $checkIn->diff($checkOut);
        if ($interval->days > 30) {
            $errors[] = 'Le prenotazioni non possono superare 30 giorni';
        }
    } catch (Exception $e) {
        $errors[] = 'Errore nella validazione delle date';
    }

    // Validazione disponibilità
    try {
        if (!isRoomAvailable($data['room_type'], $data['check_in'], $data['check_out'])) {
            $errors[] = 'La camera non è disponibile per le date selezionate';
        }
    } catch (Exception $e) {
        $errors[] = 'Errore nel controllo della disponibilità';
    }

    // Validazione numero ospiti
    $guestRoomMap = ['Standard' => 2, 'Deluxe' => 3, 'Suite' => 4];
    $maxGuests = $guestRoomMap[$data['room_type']] ?? 0;

    if ((int)$data['guests'] > $maxGuests || (int)$data['guests'] < 1) {
        $errors[] = "Numero di ospiti non valido per questa camera (massimo: $maxGuests)";
    }

    if (count($errors) > 0) {
        return ['valid' => false, 'errors' => $errors];
    }

    return ['valid' => true];
}
?>
