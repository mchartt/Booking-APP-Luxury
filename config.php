<?php
/**
 * config.php - Configurazione database con auto-setup
 * Il database e le tabelle vengono creati automaticamente se non esistono
 *
 * SICUREZZA: Le credenziali DB devono essere configurate tramite variabili d'ambiente.
 * Copia .env.example in .env e configura i valori appropriati.
 */

// Carica variabili d'ambiente da file .env se esiste
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignora commenti
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        // Parse NAME=value
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            // Rimuovi virgolette se presenti
            $value = trim($value, '"\'');
            if (!empty($name) && !isset($_ENV[$name])) {
                $_ENV[$name] = $value;
                putenv("$name=$value");
            }
        }
    }
}

// Configurazione database da variabili d'ambiente (SENZA fallback insicuri)
defined('DB_HOST') || define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost');
defined('DB_USER') || define('DB_USER', $_ENV['DB_USER'] ?? getenv('DB_USER') ?: null);
defined('DB_PASS') || define('DB_PASS', $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '');
defined('DB_NAME') || define('DB_NAME', $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: null);

// FAIL-SAFE: Blocca l'applicazione se le credenziali obbligatorie mancano
if (empty(DB_USER) || DB_USER === null) {
    $errorMsg = 'FATAL: Impossibile avviare l\'applicazione - DB_USER non configurato. Configura il file .env';
    error_log($errorMsg);
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Errore di configurazione server']);
    }
    exit(1);
}

if (empty(DB_NAME) || DB_NAME === null) {
    $errorMsg = 'FATAL: Impossibile avviare l\'applicazione - DB_NAME non configurato. Configura il file .env';
    error_log($errorMsg);
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Errore di configurazione server']);
    }
    exit(1);
}

// Variabile globale per connessione
$conn = null;

// Crea connessione con auto-setup database
try {
    // Prima connessione senza database (per crearlo se non esiste)
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

    if ($conn->connect_error) {
        throw new Exception('Connessione fallita: ' . $conn->connect_error);
    }

    // Crea database se non esiste
    $conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Seleziona il database
    if (!$conn->select_db(DB_NAME)) {
        throw new Exception('Impossibile selezionare il database: ' . $conn->error);
    }

    // Imposta charset UTF-8
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception('Errore nel setting charset: ' . $conn->error);
    }

    // NOTA: Le migrazioni NON vengono più eseguite automaticamente ad ogni richiesta.
    // Per il setup iniziale o aggiornamenti schema, eseguire: php install.php --migrate

} catch (Exception $e) {
    error_log('Database Connection Error: ' . $e->getMessage());

    // Se siamo in una richiesta API, restituisci JSON
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'Errore di connessione al database. Riprova più tardi.',
            'errors' => ['Servizio temporaneamente non disponibile']
        ]);
        exit;
    }
}

// ===== AUTO-MIGRATION SYSTEM =====

/**
 * Esegue migrazioni automatiche per creare/aggiornare tabelle
 */
function runAutoMigrations($conn) {
    // Flag per evitare migrazioni ripetute nella stessa richiesta
    static $migrationsRun = false;
    if ($migrationsRun) return;
    $migrationsRun = true;

    try {
        // 1. Crea tabella prenotazioni se non esiste
        createPrenotazioniTable($conn);

        // 2. Aggiungi colonne pagamento se mancanti
        addPaymentColumns($conn);

        // 3. Crea tabella payments se non esiste
        createPaymentsTable($conn);

        // 4. Crea indici se mancanti
        createIndexes($conn);

        // 5. Crea tabella admin_users se non esiste
        createAdminUsersTable($conn);

    } catch (Exception $e) {
        error_log('Auto-Migration Error: ' . $e->getMessage());
        // Non bloccare l'applicazione per errori di migrazione
    }
}

/**
 * Crea tabella prenotazioni
 */
function createPrenotazioniTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS prenotazioni (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id VARCHAR(50) UNIQUE NOT NULL,
        room_type ENUM('Standard', 'Deluxe', 'Suite') NOT NULL,
        check_in DATE NOT NULL,
        check_out DATE NOT NULL,
        guests INT NOT NULL DEFAULT 1,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(50) NOT NULL,
        requests TEXT,
        nights INT NOT NULL DEFAULT 1,
        price_per_night DECIMAL(10,2) NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'confirmed', 'paid', 'cancelled') DEFAULT 'pending',
        payment_status ENUM('pending', 'processing', 'completed', 'failed', 'pending_transfer', 'refunded') DEFAULT 'pending',
        payment_method ENUM('card', 'paypal', 'iban') NULL,
        transaction_id VARCHAR(100) NULL,
        paid_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        throw new Exception('Errore creazione tabella prenotazioni: ' . $conn->error);
    }
}

/**
 * Aggiunge colonne pagamento alla tabella esistente
 */
function addPaymentColumns($conn) {
    $columns = [
        'payment_status' => "ALTER TABLE prenotazioni ADD COLUMN payment_status ENUM('pending', 'processing', 'completed', 'failed', 'pending_transfer', 'refunded') DEFAULT 'pending' AFTER status",
        'payment_method' => "ALTER TABLE prenotazioni ADD COLUMN payment_method ENUM('card', 'paypal', 'iban') NULL AFTER payment_status",
        'transaction_id' => "ALTER TABLE prenotazioni ADD COLUMN transaction_id VARCHAR(100) NULL AFTER payment_method",
        'paid_at' => "ALTER TABLE prenotazioni ADD COLUMN paid_at TIMESTAMP NULL AFTER transaction_id",
        'updated_at' => "ALTER TABLE prenotazioni ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    ];

    foreach ($columns as $column => $sql) {
        if (!columnExists($conn, 'prenotazioni', $column)) {
            $conn->query($sql);
            // Ignora errori (colonna potrebbe già esistere con nome diverso)
        }
    }
}

/**
 * Crea tabella pagamenti
 */
function createPaymentsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id VARCHAR(50) NOT NULL,
        transaction_id VARCHAR(100) UNIQUE NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        method ENUM('card', 'paypal', 'iban') NOT NULL,
        status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
        card_last_four VARCHAR(4) NULL,
        card_brand VARCHAR(20) NULL,
        paypal_email VARCHAR(255) NULL,
        error_message TEXT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_booking_id (booking_id),
        INDEX idx_transaction_id (transaction_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        throw new Exception('Errore creazione tabella payments: ' . $conn->error);
    }
}

/**
 * Crea tabella admin_users per autenticazione
 */
function createAdminUsersTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        status ENUM('pending', 'active', 'rejected', 'suspended') DEFAULT 'pending',
        email_verified BOOLEAN DEFAULT FALSE,
        verification_token VARCHAR(100) NULL,
        token_expires_at TIMESTAMP NULL,
        approved_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        INDEX idx_email (email),
        INDEX idx_status (status),
        INDEX idx_verification_token (verification_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        throw new Exception('Errore creazione tabella admin_users: ' . $conn->error);
    }

    // Crea tabella login_attempts per rate limiting
    $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        username VARCHAR(50) NULL,
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        success BOOLEAN DEFAULT FALSE,
        INDEX idx_ip (ip_address),
        INDEX idx_attempted_at (attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        throw new Exception('Errore creazione tabella login_attempts: ' . $conn->error);
    }
}

/**
 * Crea indici per performance
 */
function createIndexes($conn) {
    $indexes = [
        "CREATE INDEX idx_payment_status ON prenotazioni(payment_status)",
        "CREATE INDEX idx_booking_id ON prenotazioni(booking_id)",
        "CREATE INDEX idx_check_in ON prenotazioni(check_in)",
        "CREATE INDEX idx_status ON prenotazioni(status)",
        "CREATE INDEX idx_email ON prenotazioni(email)"
    ];

    foreach ($indexes as $sql) {
        // Ignora errori se indice già esiste
        @$conn->query($sql);
    }
}

/**
 * Verifica se una colonna esiste nella tabella
 * SICUREZZA: Usa whitelist per prevenire SQL injection sugli identificatori
 */
function columnExists($conn, $table, $column) {
    // Whitelist delle tabelle permesse
    $allowedTables = ['prenotazioni', 'payments', 'admin_users', 'login_attempts'];
    if (!in_array($table, $allowedTables, true)) {
        error_log("columnExists: tabella non autorizzata: $table");
        return false;
    }

    // Whitelist delle colonne permesse (tutte le colonne usate nel sistema)
    $allowedColumns = [
        'id', 'booking_id', 'room_type', 'check_in', 'check_out', 'guests',
        'name', 'email', 'phone', 'requests', 'nights', 'price_per_night',
        'total_price', 'status', 'payment_status', 'payment_method',
        'transaction_id', 'paid_at', 'created_at', 'updated_at',
        'amount', 'method', 'card_last_four', 'card_brand', 'paypal_email',
        'error_message', 'ip_address', 'user_agent',
        'username', 'password_hash', 'email_verified', 'verification_token',
        'token_expires_at', 'approved_by', 'last_login', 'attempted_at', 'success'
    ];
    if (!in_array($column, $allowedColumns, true)) {
        error_log("columnExists: colonna non autorizzata: $column");
        return false;
    }

    // Ora è sicuro usare gli identificatori (già validati via whitelist)
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// ===== FUNZIONI DI UTILITÀ =====

/**
 * Ottiene l'IP reale del client in modo sicuro con logica Trusted Proxies
 *
 * SICUREZZA: Gli header proxy (X-Forwarded-For, etc.) possono essere falsificati
 * da qualsiasi client. Questa funzione li usa SOLO se la connessione proviene
 * da un proxy fidato (configurato in TRUSTED_PROXIES).
 *
 * @return string L'IP del client (IPv4 o IPv6)
 */
function getClientIp() {
    // IP della connessione diretta (non falsificabile)
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Carica lista proxy fidati da variabile d'ambiente
    // Formato: lista separata da virgole (es: "127.0.0.1,10.0.0.1,173.245.48.0/20")
    $trustedProxiesEnv = $_ENV['TRUSTED_PROXIES'] ?? getenv('TRUSTED_PROXIES') ?: '';

    // Se non ci sono proxy fidati configurati, usa sempre REMOTE_ADDR (più sicuro)
    if (empty($trustedProxiesEnv)) {
        return $remoteAddr;
    }

    // Parsa la lista di proxy fidati
    $trustedProxies = array_map('trim', explode(',', $trustedProxiesEnv));

    // Verifica se REMOTE_ADDR è un proxy fidato
    if (!isIpInTrustedList($remoteAddr, $trustedProxies)) {
        // La connessione NON proviene da un proxy fidato
        // NON fidarsi degli header proxy - potrebbero essere falsificati
        return $remoteAddr;
    }

    // La connessione proviene da un proxy fidato - ora possiamo leggere gli header
    $headersToCheck = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_X_FORWARDED_FOR',      // Standard proxy header
        'HTTP_X_REAL_IP',            // Nginx proxy
    ];

    foreach ($headersToCheck as $header) {
        if (!empty($_SERVER[$header])) {
            // X-Forwarded-For può contenere più IP separati da virgola
            // Il primo è l'IP originale del client
            $ipList = explode(',', $_SERVER[$header]);
            $ip = trim($ipList[0]);

            // Valida che sia un IP valido (IPv4 o IPv6)
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    // Fallback a REMOTE_ADDR
    return $remoteAddr;
}

/**
 * Verifica se un IP appartiene alla lista di proxy fidati
 * Supporta sia IP singoli che notazione CIDR (es: 173.245.48.0/20)
 *
 * @param string $ip L'IP da verificare
 * @param array $trustedList Lista di IP/CIDR fidati
 * @return bool True se l'IP è nella lista fidati
 */
function isIpInTrustedList($ip, $trustedList) {
    foreach ($trustedList as $trusted) {
        $trusted = trim($trusted);

        if (empty($trusted)) {
            continue;
        }

        // Verifica se è una notazione CIDR
        if (strpos($trusted, '/') !== false) {
            if (ipInCidr($ip, $trusted)) {
                return true;
            }
        } else {
            // Confronto diretto IP
            if ($ip === $trusted) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Verifica se un IP appartiene a un range CIDR
 *
 * @param string $ip L'IP da verificare
 * @param string $cidr Il range CIDR (es: 173.245.48.0/20)
 * @return bool True se l'IP è nel range
 */
function ipInCidr($ip, $cidr) {
    list($subnet, $bits) = explode('/', $cidr);

    // IPv4
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
        filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask = -1 << (32 - (int)$bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    // IPv6
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) &&
        filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {

        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);
        $bits = (int)$bits;

        // Confronta bit per bit
        $fullBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        // Confronta i byte completi
        if (substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        // Confronta i bit rimanenti se presenti
        if ($remainingBits > 0 && $fullBytes < 16) {
            $mask = 0xFF << (8 - $remainingBits);
            if ((ord($ipBin[$fullBytes]) & $mask) !== (ord($subnetBin[$fullBytes]) & $mask)) {
                return false;
            }
        }

        return true;
    }

    return false;
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
 * Controlla disponibilità camera con prepared statement
 */
function isRoomAvailable($roomType, $checkIn, $checkOut) {
    global $conn;

    if ($conn === null) {
        throw new Exception('Database non disponibile');
    }

    try {
        // Validazione tipo stanza (whitelist)
        $validRoomTypes = ['Standard', 'Deluxe', 'Suite'];
        if (!in_array($roomType, $validRoomTypes)) {
            return false;
        }

        // Prepared statement per sicurezza
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM prenotazioni
                  WHERE room_type = ?
                  AND status IN ('confirmed', 'paid')
                  AND NOT (check_out <= ? OR check_in >= ?)");

        if (!$stmt) {
            throw new Exception('Errore nella preparazione della query: ' . $conn->error);
        }

        $stmt->bind_param("sss", $roomType, $checkIn, $checkOut);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row['count'] == 0;

    } catch (Exception $e) {
        error_log('isRoomAvailable Error: ' . $e->getMessage());
        throw new Exception('Errore nel controllo disponibilità');
    }
}

/**
 * Ottiene date prenotate per una camera (formato range) con prepared statement
 */
function getBookedDateRanges($roomType = null) {
    global $conn;

    if ($conn === null) {
        throw new Exception('Database non disponibile');
    }

    try {
        if ($roomType) {
            // Validazione tipo stanza (whitelist)
            $validRoomTypes = ['Standard', 'Deluxe', 'Suite'];
            if (!in_array($roomType, $validRoomTypes)) {
                return [];
            }

            $stmt = $conn->prepare("SELECT room_type, check_in, check_out FROM prenotazioni
                                    WHERE status IN ('confirmed', 'paid') AND room_type = ?");
            $stmt->bind_param("s", $roomType);
        } else {
            $stmt = $conn->prepare("SELECT room_type, check_in, check_out FROM prenotazioni
                                    WHERE status IN ('confirmed', 'paid')");
        }

        if (!$stmt) {
            throw new Exception('Errore nella preparazione della query: ' . $conn->error);
        }

        $stmt->execute();
        $result = $stmt->get_result();

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

        $stmt->close();

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
