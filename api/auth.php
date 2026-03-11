<?php
/**
 * api/auth.php - API REST per autenticazione admin
 * Gestisce login, registrazione, verifica email, logout
 */

// Security headers e sessione centralizzati
require_once __DIR__ . '/security_headers.php';

header('Content-Type: application/json; charset=utf-8');

require_once '../config.php';

// ===== CSRF PROTECTION =====

/**
 * Genera un nuovo token CSRF e lo salva in sessione
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida il token CSRF dalla richiesta
 * @param bool $required Se true, blocca la richiesta se il token manca o non è valido
 */
function validateCsrfToken($required = true) {
    // Ottieni token dall'header o dal body
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    if (!$token) {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['csrf_token'] ?? null;
    }

    // Se non richiesto e manca, passa (per compatibilità)
    if (!$required && empty($token)) {
        return true;
    }

    // Verifica presenza token in sessione
    if (empty($_SESSION['csrf_token'])) {
        if ($required) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF mancante nella sessione']);
            exit;
        }
        return false;
    }

    // Verifica scadenza token (1 ora)
    $tokenAge = time() - ($_SESSION['csrf_token_time'] ?? 0);
    if ($tokenAge > 3600) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
        if ($required) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF scaduto', 'code' => 'CSRF_EXPIRED']);
            exit;
        }
        return false;
    }

    // Confronto timing-safe
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        if ($required) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF non valido']);
            exit;
        }
        return false;
    }

    return true;
}

// Configurazione
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minuti
define('TOKEN_EXPIRY', 86400); // 24 ore
define('MIN_PASSWORD_LENGTH', 8);

// Determina URL del sito automaticamente
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = dirname(dirname($_SERVER['SCRIPT_NAME']));
define('SITE_URL', $protocol . '://' . $host . $path);

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
    error_log('Auth API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server'
    ]);
}

// ===== HANDLER GET =====
function handleGetRequest($action) {
    switch ($action) {
        case 'check':
            checkSession();
            break;

        case 'verify':
            verifyEmail();
            break;

        case 'logout':
            logout();
            break;

        case 'pending-users':
            getPendingUsers();
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Azione non specificata']);
    }
}

// ===== HANDLER POST =====
function handlePostRequest($action) {
    $input = json_decode(file_get_contents('php://input'), true);

    switch ($action) {
        case 'register':
            register($input);
            break;

        case 'login':
            login($input);
            break;

        case 'approve':
            approveUser($input);
            break;

        case 'reject':
            rejectUser($input);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Azione non specificata']);
    }
}

// ===== REGISTRAZIONE =====
function register($input) {
    global $conn;

    // Validazione input
    $errors = [];

    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';

    // Validazione username
    if (empty($username)) {
        $errors[] = 'Username obbligatorio';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = 'Username deve essere tra 3 e 50 caratteri';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Username puo contenere solo lettere, numeri e underscore';
    }

    // Validazione email
    if (empty($email)) {
        $errors[] = 'Email obbligatoria';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email non valida';
    }

    // Validazione password (policy sicura)
    if (empty($password)) {
        $errors[] = 'Password obbligatoria';
    } elseif (strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors[] = 'Password deve avere almeno ' . MIN_PASSWORD_LENGTH . ' caratteri';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password deve contenere almeno una lettera maiuscola';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password deve contenere almeno una lettera minuscola';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password deve contenere almeno un numero';
    } elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':\"\\|,.<>\/?~`]/', $password)) {
        $errors[] = 'Password deve contenere almeno un carattere speciale (!@#$%^&*...)';
    }

    // Conferma password
    if ($password !== $confirmPassword) {
        $errors[] = 'Le password non coincidono';
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $errors]);
        return;
    }

    // Verifica se username o email esistono gia
    $stmt = $conn->prepare("SELECT id FROM admin_users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Username o email gia registrati'
        ]);
        $stmt->close();
        return;
    }
    $stmt->close();

    // Genera token verifica email
    $verificationToken = bin2hex(random_bytes(32));
    $tokenExpires = date('Y-m-d H:i:s', time() + TOKEN_EXPIRY);

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Inserisci utente
    $stmt = $conn->prepare("INSERT INTO admin_users
        (username, email, password_hash, verification_token, token_expires_at, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
    $stmt->bind_param("sssss", $username, $email, $passwordHash, $verificationToken, $tokenExpires);

    if (!$stmt->execute()) {
        error_log('Registration DB Error: ' . $stmt->error);
        throw new Exception('Errore nella registrazione. Riprova più tardi.');
    }
    $stmt->close();

    // Invia email di verifica
    $verificationUrl = SITE_URL . "/api/auth.php?action=verify&token=" . $verificationToken;
    $emailSent = sendVerificationEmail($email, $username, $verificationUrl);

    // NOTA: In produzione, il token NON deve mai essere esposto nei log o nelle risposte API

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Registrazione completata! Controlla la tua email per verificare l\'account.',
        'email_sent' => $emailSent
    ]);
}

// ===== VERIFICA EMAIL =====
function verifyEmail() {
    global $conn;

    $token = $_GET['token'] ?? '';

    if (empty($token) || strlen($token) !== 64) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Token non valido']);
        return;
    }

    // Trova utente con questo token
    $stmt = $conn->prepare("SELECT id, username, email, status, token_expires_at FROM admin_users WHERE verification_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        // Redirect con errore
        header('Location: ../login.html?error=invalid_token');
        exit;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Verifica scadenza token
    if (strtotime($user['token_expires_at']) < time()) {
        header('Location: ../login.html?error=token_expired');
        exit;
    }

    // ===== PROTEZIONE RACE CONDITION (TOCTOU) =====
    // Usa transazione con locking per prevenire che due utenti vengano
    // promossi ad admin contemporaneamente quando count == 0
    $conn->begin_transaction();

    try {
        // Lock esclusivo sulla tabella admin_users per questa operazione
        // FOR UPDATE blocca le righe lette fino al commit della transazione
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin_users WHERE status = 'active' FOR UPDATE");
        $stmt->execute();
        $result = $stmt->get_result();
        $activeCount = $result->fetch_assoc()['count'];
        $stmt->close();

        // Se è il primo utente, approvalo automaticamente
        $newStatus = ($activeCount == 0) ? 'active' : 'pending';

        // Aggiorna utente (all'interno della stessa transazione)
        $stmt = $conn->prepare("UPDATE admin_users SET
            email_verified = TRUE,
            verification_token = NULL,
            token_expires_at = NULL,
            status = ?
            WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $user['id']);
        $stmt->execute();
        $stmt->close();

        // Commit della transazione - rilascia i lock
        $conn->commit();

    } catch (Exception $e) {
        // Rollback in caso di errore
        $conn->rollback();
        error_log('verifyEmail transaction error: ' . $e->getMessage());
        header('Location: ../login.html?error=verification_failed');
        exit;
    }

    // Redirect a login con messaggio appropriato
    if ($newStatus === 'active') {
        header('Location: ../login.html?verified=1&first_admin=1');
    } else {
        header('Location: ../login.html?verified=1&pending=1');
    }
    exit;
}

// ===== LOGIN =====
function login($input) {
    global $conn;

    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $ip = getClientIp(); // Usa helper per gestire proxy/CDN

    // Verifica rate limiting
    if (isLockedOut($ip)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Troppi tentativi falliti. Riprova tra 15 minuti.'
        ]);
        return;
    }

    // Validazione base
    if (empty($username) || empty($password)) {
        recordLoginAttempt($ip, $username, false);
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Username e password obbligatori'
        ]);
        return;
    }

    // Trova utente
    $stmt = $conn->prepare("SELECT id, username, email, password_hash, status, email_verified FROM admin_users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        recordLoginAttempt($ip, $username, false);
        $stmt->close();
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Credenziali non valide'
        ]);
        return;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Verifica password
    if (!password_verify($password, $user['password_hash'])) {
        recordLoginAttempt($ip, $username, false);
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Credenziali non valide'
        ]);
        return;
    }

    // Verifica email verificata
    if (!$user['email_verified']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Email non verificata. Controlla la tua casella di posta.'
        ]);
        return;
    }

    // Verifica stato utente
    switch ($user['status']) {
        case 'pending':
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Account in attesa di approvazione da parte di un amministratore.'
            ]);
            return;

        case 'rejected':
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'La tua richiesta di accesso e stata rifiutata.'
            ]);
            return;

        case 'suspended':
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Account sospeso. Contatta l\'amministratore.'
            ]);
            return;
    }

    // Login riuscito!
    recordLoginAttempt($ip, $username, true);

    // Rigenera ID sessione
    session_regenerate_id(true);

    // Imposta sessione
    $_SESSION['admin_id'] = $user['id'];
    $_SESSION['admin_username'] = $user['username'];
    $_SESSION['admin_email'] = $user['email'];
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['login_time'] = time();

    // Aggiorna last_login
    $stmt = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Login effettuato',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email']
        ]
    ]);
}

// ===== VERIFICA SESSIONE =====
function checkSession() {
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        // Genera/rinnova token CSRF per utente autenticato
        $csrfToken = generateCsrfToken();

        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'csrf_token' => $csrfToken,
            'user' => [
                'id' => $_SESSION['admin_id'],
                'username' => $_SESSION['admin_username'],
                'email' => $_SESSION['admin_email']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'authenticated' => false
        ]);
    }
}

// ===== LOGOUT =====
function logout() {
    session_unset();
    session_destroy();

    echo json_encode([
        'success' => true,
        'message' => 'Logout effettuato'
    ]);
}

// ===== UTENTI IN ATTESA =====
function getPendingUsers() {
    global $conn;

    // Verifica autenticazione
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Non autenticato']);
        return;
    }

    $stmt = $conn->prepare("SELECT id, username, email, created_at FROM admin_users WHERE status = 'pending' AND email_verified = TRUE ORDER BY created_at ASC");
    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'pending_users' => $users,
        'count' => count($users)
    ]);
}

// ===== APPROVA UTENTE =====
function approveUser($input) {
    global $conn;

    // Verifica autenticazione
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Non autenticato']);
        return;
    }

    // Valida CSRF token per azioni sensibili
    validateCsrfToken(true);

    $userId = intval($input['user_id'] ?? 0);
    $approverId = $_SESSION['admin_id'];

    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID utente non valido']);
        return;
    }

    // Aggiorna stato
    $stmt = $conn->prepare("UPDATE admin_users SET status = 'active', approved_by = ? WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $approverId, $userId);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Utente non trovato o gia approvato']);
        $stmt->close();
        return;
    }
    $stmt->close();

    // Recupera email utente per notifica
    $stmt = $conn->prepare("SELECT email, username FROM admin_users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Invia email di notifica
    if ($user) {
        sendApprovalEmail($user['email'], $user['username']);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Utente approvato con successo'
    ]);
}

// ===== RIFIUTA UTENTE =====
function rejectUser($input) {
    global $conn;

    // Verifica autenticazione
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Non autenticato']);
        return;
    }

    // Valida CSRF token per azioni sensibili
    validateCsrfToken(true);

    $userId = intval($input['user_id'] ?? 0);

    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID utente non valido']);
        return;
    }

    // Aggiorna stato
    $stmt = $conn->prepare("UPDATE admin_users SET status = 'rejected' WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Utente non trovato o gia processato']);
        $stmt->close();
        return;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Utente rifiutato'
    ]);
}

// ===== RATE LIMITING =====
function isLockedOut($ip) {
    global $conn;

    $lockoutTime = date('Y-m-d H:i:s', time() - LOCKOUT_TIME);

    $stmt = $conn->prepare("SELECT COUNT(*) as failed FROM login_attempts
        WHERE ip_address = ? AND success = FALSE AND attempted_at > ?");
    $stmt->bind_param("ss", $ip, $lockoutTime);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $result['failed'] >= MAX_LOGIN_ATTEMPTS;
}

function recordLoginAttempt($ip, $username, $success) {
    global $conn;

    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, username, success) VALUES (?, ?, ?)");
    $successInt = $success ? 1 : 0;
    $stmt->bind_param("ssi", $ip, $username, $successInt);
    $stmt->execute();
    $stmt->close();

    // Pulisci vecchi tentativi (piu di 24h)
    $conn->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
}

// ===== EMAIL FUNCTIONS =====
function sendVerificationEmail($email, $username, $verificationUrl) {
    $subject = 'Verifica il tuo account - Luxury Hotel Admin';

    $message = "
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Verifica Email</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f5efe7; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
            .header { text-align: center; border-bottom: 2px solid #8B6F47; padding-bottom: 20px; margin-bottom: 20px; }
            .header h1 { color: #8B6F47; margin: 0; }
            .content { color: #333; line-height: 1.6; }
            .btn { display: inline-block; background: #8B6F47; color: white; padding: 12px 30px;
                   text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; color: #666; font-size: 12px; margin-top: 30px;
                      padding-top: 20px; border-top: 1px solid #eee; }
            .warning { background: #fff3cd; padding: 10px; border-radius: 5px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Luxury Hotel</h1>
                <p>Area Amministrativa</p>
            </div>
            <div class='content'>
                <p>Ciao <strong>$username</strong>,</p>
                <p>Grazie per esserti registrato come amministratore di Luxury Hotel.</p>
                <p>Per completare la registrazione, clicca sul pulsante qui sotto:</p>
                <p style='text-align: center;'>
                    <a href='$verificationUrl' class='btn'>Verifica Email</a>
                </p>
                <div class='warning'>
                    <strong>Nota:</strong> Dopo la verifica, il tuo account sara in attesa di approvazione
                    da parte di un amministratore esistente (a meno che tu non sia il primo admin).
                </div>
                <p>Se non hai richiesto questa registrazione, ignora questa email.</p>
                <p>Il link scade tra 24 ore.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " Luxury Hotel. Tutti i diritti riservati.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: noreply@luxuryhotel.it\r\n";

    // In sviluppo, logga invece di inviare
    // return @mail($email, $subject, $message, $headers);
    error_log("EMAIL TO: $email - SUBJECT: $subject");
    return true; // Simula invio riuscito
}

function sendApprovalEmail($email, $username) {
    $subject = 'Account Approvato - Luxury Hotel Admin';
    $loginUrl = SITE_URL . '/login.html';

    $message = "
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Account Approvato</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f5efe7; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
            .header { text-align: center; border-bottom: 2px solid #22c55e; padding-bottom: 20px; margin-bottom: 20px; }
            .header h1 { color: #22c55e; margin: 0; }
            .content { color: #333; line-height: 1.6; }
            .btn { display: inline-block; background: #8B6F47; color: white; padding: 12px 30px;
                   text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; color: #666; font-size: 12px; margin-top: 30px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Account Approvato!</h1>
            </div>
            <div class='content'>
                <p>Ciao <strong>$username</strong>,</p>
                <p>Il tuo account amministratore e stato approvato.</p>
                <p>Ora puoi accedere alla dashboard di Luxury Hotel:</p>
                <p style='text-align: center;'>
                    <a href='$loginUrl' class='btn'>Accedi Ora</a>
                </p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " Luxury Hotel</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: noreply@luxuryhotel.it\r\n";

    // return @mail($email, $subject, $message, $headers);
    error_log("APPROVAL EMAIL TO: $email");
    return true;
}
?>
