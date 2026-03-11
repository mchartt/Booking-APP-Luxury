<?php
/**
 * api/admin.php - API REST per dashboard amministrativa
 * Statistiche, gestione prenotazioni e insight
 * PROTETTO DA AUTENTICAZIONE
 */

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

session_start();

// Security headers centralizzati
require_once __DIR__ . '/security_headers.php';

header('Content-Type: application/json; charset=utf-8');

require_once '../config.php';

// Verifica autenticazione
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Non autenticato',
        'redirect' => 'login.html'
    ]);
    exit;
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
    if ($conn === null) {
        throw new Exception('Connessione al database non disponibile');
    }

    switch ($method) {
        case 'GET':
            handleGetRequest($action);
            break;

        case 'POST':
            handlePostRequest($action);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    }
} catch (Exception $e) {
    error_log('Admin API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server'
    ]);
}

// ===== HANDLER GET =====
function handleGetRequest($action) {
    switch ($action) {
        case 'dashboard':
            getDashboardStats();
            break;

        case 'stats':
            getDetailedStats();
            break;

        case 'bookings':
            getBookings();
            break;

        case 'revenue':
            getRevenueData();
            break;

        case 'rooms':
            getRoomStats();
            break;

        default:
            getDashboardStats();
    }
}

// ===== HANDLER POST =====
function handlePostRequest($action) {
    // SICUREZZA: Valida token CSRF per tutte le azioni sensibili
    validateCsrfToken();

    $input = json_decode(file_get_contents('php://input'), true);

    switch ($action) {
        case 'confirm-payment':
            confirmPayment($input);
            break;

        case 'cancel-booking':
            cancelBooking($input);
            break;

        case 'update-booking':
            updateBooking($input);
            break;

        case 'delete-booking':
            deleteBooking($input);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Azione non specificata']);
    }
}

// ===== DASHBOARD STATS =====
function getDashboardStats() {
    global $conn;

    try {
        $stats = [];

        // Totale prenotazioni
        $result = $conn->query("SELECT COUNT(*) as total FROM prenotazioni");
        $stats['total_bookings'] = $result->fetch_assoc()['total'];

        // Totale guadagni
        $result = $conn->query("SELECT COALESCE(SUM(total_price), 0) as total FROM prenotazioni WHERE status IN ('confirmed', 'paid')");
        $stats['total_revenue'] = floatval($result->fetch_assoc()['total']);

        // Guadagni questo mese
        $result = $conn->query("SELECT COALESCE(SUM(total_price), 0) as total FROM prenotazioni
                               WHERE status IN ('confirmed', 'paid')
                               AND MONTH(created_at) = MONTH(CURRENT_DATE())
                               AND YEAR(created_at) = YEAR(CURRENT_DATE())");
        $stats['monthly_revenue'] = floatval($result->fetch_assoc()['total']);

        // Pagamenti in attesa
        $result = $conn->query("SELECT COUNT(*) as total FROM prenotazioni WHERE payment_status IN ('pending', 'pending_transfer')");
        $stats['pending_payments'] = $result->fetch_assoc()['total'];

        // Valore medio prenotazione
        $stats['avg_booking_value'] = $stats['total_bookings'] > 0
            ? round($stats['total_revenue'] / $stats['total_bookings'], 2)
            : 0;

        // Notti medie
        $result = $conn->query("SELECT AVG(nights) as avg_nights FROM prenotazioni WHERE nights > 0");
        $stats['avg_nights'] = round(floatval($result->fetch_assoc()['avg_nights']), 1);

        // Tasso occupazione (semplificato)
        $daysInMonth = date('t');
        $totalRoomDays = $daysInMonth * 3; // 3 camere
        $result = $conn->query("SELECT COALESCE(SUM(nights), 0) as booked_days FROM prenotazioni
                               WHERE status IN ('confirmed', 'paid')
                               AND MONTH(check_in) = MONTH(CURRENT_DATE())");
        $bookedDays = $result->fetch_assoc()['booked_days'];
        $stats['occupancy_rate'] = min(100, round(($bookedDays / $totalRoomDays) * 100));

        // Prenotazioni recenti
        $result = $conn->query("SELECT * FROM prenotazioni ORDER BY created_at DESC LIMIT 5");
        $recentBookings = [];
        while ($row = $result->fetch_assoc()) {
            $recentBookings[] = $row;
        }

        // Tutte le prenotazioni per i grafici
        $result = $conn->query("SELECT * FROM prenotazioni ORDER BY created_at DESC LIMIT 100");
        $allBookings = [];
        while ($row = $result->fetch_assoc()) {
            $allBookings[] = $row;
        }

        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'recent_bookings' => $recentBookings,
            'bookings' => $allBookings
        ]);

    } catch (Exception $e) {
        throw $e;
    }
}

// ===== DETAILED STATS =====
function getDetailedStats() {
    global $conn;

    try {
        $stats = [];

        // Stats per tipo camera
        $result = $conn->query("SELECT room_type,
                               COUNT(*) as bookings,
                               COALESCE(SUM(total_price), 0) as revenue
                               FROM prenotazioni
                               WHERE status IN ('confirmed', 'paid')
                               GROUP BY room_type");

        $byRoom = [];
        while ($row = $result->fetch_assoc()) {
            $byRoom[$row['room_type']] = [
                'bookings' => intval($row['bookings']),
                'revenue' => floatval($row['revenue'])
            ];
        }
        $stats['by_room'] = $byRoom;

        // Stats per periodo
        $result = $conn->query("SELECT
                               DATE(created_at) as date,
                               COUNT(*) as bookings,
                               COALESCE(SUM(total_price), 0) as revenue
                               FROM prenotazioni
                               WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
                               GROUP BY DATE(created_at)
                               ORDER BY date");

        $byDate = [];
        while ($row = $result->fetch_assoc()) {
            $byDate[] = [
                'date' => $row['date'],
                'bookings' => intval($row['bookings']),
                'revenue' => floatval($row['revenue'])
            ];
        }
        $stats['by_date'] = $byDate;

        // Stats per metodo pagamento
        $result = $conn->query("SELECT payment_method,
                               COUNT(*) as count,
                               COALESCE(SUM(total_price), 0) as total
                               FROM prenotazioni
                               WHERE payment_status = 'completed'
                               GROUP BY payment_method");

        $byPaymentMethod = [];
        while ($row = $result->fetch_assoc()) {
            $byPaymentMethod[$row['payment_method'] ?? 'N/A'] = [
                'count' => intval($row['count']),
                'total' => floatval($row['total'])
            ];
        }
        $stats['by_payment_method'] = $byPaymentMethod;

        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);

    } catch (Exception $e) {
        throw $e;
    }
}

// ===== GET BOOKINGS =====
function getBookings() {
    global $conn;

    try {
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(10, intval($_GET['limit']))) : 50;
        $offset = ($page - 1) * $limit;

        // Filtri
        $where = [];
        $params = [];
        $types = '';

        if (!empty($_GET['status'])) {
            $where[] = "status = ?";
            $params[] = $_GET['status'];
            $types .= 's';
        }

        if (!empty($_GET['payment_status'])) {
            $where[] = "payment_status = ?";
            $params[] = $_GET['payment_status'];
            $types .= 's';
        }

        if (!empty($_GET['room_type'])) {
            $where[] = "room_type = ?";
            $params[] = $_GET['room_type'];
            $types .= 's';
        }

        if (!empty($_GET['date_from'])) {
            $where[] = "check_in >= ?";
            $params[] = $_GET['date_from'];
            $types .= 's';
        }

        if (!empty($_GET['date_to'])) {
            $where[] = "check_out <= ?";
            $params[] = $_GET['date_to'];
            $types .= 's';
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count totale
        $countQuery = "SELECT COUNT(*) as total FROM prenotazioni $whereClause";
        if (count($params) > 0) {
            $stmt = $conn->prepare($countQuery);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $total = $stmt->get_result()->fetch_assoc()['total'];
        } else {
            $total = $conn->query($countQuery)->fetch_assoc()['total'];
        }

        // Query principale
        $query = "SELECT * FROM prenotazioni $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
        if (count($params) > 0) {
            $stmt = $conn->prepare("SELECT * FROM prenotazioni $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?");
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($query);
        }

        $bookings = [];
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row;
        }

        echo json_encode([
            'success' => true,
            'bookings' => $bookings,
            'total' => intval($total),
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]);

    } catch (Exception $e) {
        throw $e;
    }
}

// ===== REVENUE DATA =====
function getRevenueData() {
    global $conn;

    try {
        // Guadagni per mese (ultimi 12 mesi)
        $result = $conn->query("SELECT
                               DATE_FORMAT(created_at, '%Y-%m') as month,
                               COALESCE(SUM(total_price), 0) as revenue,
                               COUNT(*) as bookings
                               FROM prenotazioni
                               WHERE status IN ('confirmed', 'paid')
                               AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
                               GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                               ORDER BY month");

        $monthly = [];
        while ($row = $result->fetch_assoc()) {
            $monthly[] = $row;
        }

        // Guadagni per giorno (ultimi 30 giorni)
        $result = $conn->query("SELECT
                               DATE(created_at) as day,
                               COALESCE(SUM(total_price), 0) as revenue,
                               COUNT(*) as bookings
                               FROM prenotazioni
                               WHERE status IN ('confirmed', 'paid')
                               AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
                               GROUP BY DATE(created_at)
                               ORDER BY day");

        $daily = [];
        while ($row = $result->fetch_assoc()) {
            $daily[] = $row;
        }

        echo json_encode([
            'success' => true,
            'monthly' => $monthly,
            'daily' => $daily
        ]);

    } catch (Exception $e) {
        throw $e;
    }
}

// ===== ROOM STATS =====
function getRoomStats() {
    global $conn;

    try {
        $rooms = [];

        $result = $conn->query("SELECT
                               room_type,
                               COUNT(*) as total_bookings,
                               COALESCE(SUM(total_price), 0) as total_revenue,
                               COALESCE(AVG(nights), 0) as avg_nights,
                               COALESCE(SUM(nights), 0) as total_nights
                               FROM prenotazioni
                               WHERE status IN ('confirmed', 'paid')
                               GROUP BY room_type");

        while ($row = $result->fetch_assoc()) {
            $rooms[$row['room_type']] = [
                'bookings' => intval($row['total_bookings']),
                'revenue' => floatval($row['total_revenue']),
                'avg_nights' => round(floatval($row['avg_nights']), 1),
                'total_nights' => intval($row['total_nights'])
            ];
        }

        // Date prenotate per camera
        $result = $conn->query("SELECT room_type, check_in, check_out
                               FROM prenotazioni
                               WHERE status IN ('confirmed', 'paid')
                               AND check_out >= CURRENT_DATE()
                               ORDER BY check_in");

        $bookedDates = [];
        while ($row = $result->fetch_assoc()) {
            $room = $row['room_type'];
            if (!isset($bookedDates[$room])) {
                $bookedDates[$room] = [];
            }
            $bookedDates[$room][] = [
                'start' => $row['check_in'],
                'end' => $row['check_out']
            ];
        }

        echo json_encode([
            'success' => true,
            'rooms' => $rooms,
            'booked_dates' => $bookedDates
        ]);

    } catch (Exception $e) {
        throw $e;
    }
}

// ===== CONFIRM PAYMENT =====
function confirmPayment($input) {
    global $conn;

    try {
        if (empty($input['booking_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID prenotazione richiesto']);
            return;
        }

        $bookingId = $input['booking_id'];

        $stmt = $conn->prepare("UPDATE prenotazioni
                               SET payment_status = 'completed',
                                   status = 'paid',
                                   paid_at = NOW()
                               WHERE booking_id = ?");
        $stmt->bind_param("s", $bookingId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Pagamento confermato'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Prenotazione non trovata'
            ]);
        }

        $stmt->close();

    } catch (Exception $e) {
        throw $e;
    }
}

// ===== CANCEL BOOKING =====
function cancelBooking($input) {
    global $conn;

    try {
        if (empty($input['booking_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID prenotazione richiesto']);
            return;
        }

        $bookingId = $input['booking_id'];

        $stmt = $conn->prepare("UPDATE prenotazioni SET status = 'cancelled' WHERE booking_id = ?");
        $stmt->bind_param("s", $bookingId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Prenotazione cancellata'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Prenotazione non trovata'
            ]);
        }

        $stmt->close();

    } catch (Exception $e) {
        throw $e;
    }
}

// ===== UPDATE BOOKING =====
function updateBooking($input) {
    global $conn;

    try {
        if (empty($input['booking_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID prenotazione richiesto']);
            return;
        }

        $bookingId = $input['booking_id'];
        $updates = [];
        $params = [];
        $types = '';

        // Campi aggiornabili
        $allowedFields = ['status', 'payment_status', 'room_type', 'check_in', 'check_out', 'guests', 'name', 'email', 'phone'];

        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $params[] = $input[$field];
                $types .= 's';
            }
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nessun campo da aggiornare']);
            return;
        }

        $params[] = $bookingId;
        $types .= 's';

        $query = "UPDATE prenotazioni SET " . implode(', ', $updates) . " WHERE booking_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Prenotazione aggiornata'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Nessuna modifica effettuata'
            ]);
        }

        $stmt->close();

    } catch (Exception $e) {
        throw $e;
    }
}

// ===== DELETE BOOKING =====
function deleteBooking($input) {
    global $conn;

    try {
        if (empty($input['booking_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID prenotazione richiesto']);
            return;
        }

        $bookingId = $input['booking_id'];

        $stmt = $conn->prepare("DELETE FROM prenotazioni WHERE booking_id = ?");
        $stmt->bind_param("s", $bookingId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Prenotazione eliminata'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Prenotazione non trovata'
            ]);
        }

        $stmt->close();

    } catch (Exception $e) {
        throw $e;
    }
}
?>
