<?php
/**
 * LUXURY HOTEL - Installazione Automatica
 * Apri questo file nel browser per configurare tutto
 */

session_start();

// Se il form è stato inviato
$step = $_POST['step'] ?? $_GET['step'] ?? '1';
$message = '';
$messageType = '';

// ========== FUNZIONI DI UTILITÀ ==========

function testConnection($host, $user, $pass) {
    try {
        $conn = @new mysqli($host, $user, $pass);
        if ($conn->connect_error) {
            return ['success' => false, 'error' => $conn->connect_error];
        }
        $conn->close();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function setupDatabase($host, $user, $pass, $dbname) {
    try {
        $conn = new mysqli($host, $user, $pass);
        if ($conn->connect_error) {
            throw new Exception('Connessione fallita: ' . $conn->connect_error);
        }

        // Crea database
        $conn->query("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->select_db($dbname);

        // Crea tabella prenotazioni
        $conn->query("CREATE TABLE IF NOT EXISTS prenotazioni (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Crea tabella pagamenti
        $conn->query("CREATE TABLE IF NOT EXISTS payments (
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
            INDEX idx_booking_id (booking_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Crea tabella admin
        $conn->query("CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            status ENUM('pending', 'active', 'rejected', 'suspended') DEFAULT 'active',
            email_verified BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Crea tabella login_attempts
        $conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            username VARCHAR(50) NULL,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            success BOOLEAN DEFAULT FALSE,
            INDEX idx_ip (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->close();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function createAdmin($host, $user, $pass, $dbname, $adminUser, $adminEmail, $adminPass) {
    try {
        $conn = new mysqli($host, $user, $pass, $dbname);
        if ($conn->connect_error) {
            throw new Exception('Connessione fallita');
        }

        // Verifica se esiste già un admin
        $result = $conn->query("SELECT COUNT(*) as count FROM admin_users");
        $row = $result->fetch_assoc();

        if ($row['count'] > 0) {
            $conn->close();
            return ['success' => true, 'message' => 'Admin già esistente'];
        }

        // Crea admin
        $hash = password_hash($adminPass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admin_users (username, email, password_hash, status) VALUES (?, ?, ?, 'active')");
        $stmt->bind_param("sss", $adminUser, $adminEmail, $hash);
        $stmt->execute();
        $stmt->close();
        $conn->close();

        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function saveEnvFile($host, $user, $pass, $dbname) {
    $envContent = "# Configurazione Database
DB_HOST=$host
DB_USER=$user
DB_PASS=$pass
DB_NAME=$dbname

# Debug (false in produzione)
DEBUG=false
";
    return file_put_contents(__DIR__ . '/.env', $envContent) !== false;
}

// ========== GESTIONE POST ==========

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($step === '2') {
        // Test connessione
        $host = trim($_POST['db_host'] ?? 'localhost');
        $user = trim($_POST['db_user'] ?? 'root');
        $pass = $_POST['db_pass'] ?? '';
        $dbname = trim($_POST['db_name'] ?? 'luxury_hotel');

        $test = testConnection($host, $user, $pass);
        if ($test['success']) {
            $_SESSION['db'] = compact('host', 'user', 'pass', 'dbname');
            $step = '3';
        } else {
            $message = 'Errore connessione: ' . $test['error'];
            $messageType = 'error';
            $step = '2';
        }
    }

    elseif ($step === '3') {
        $db = $_SESSION['db'] ?? null;
        if ($db) {
            $result = setupDatabase($db['host'], $db['user'], $db['pass'], $db['dbname']);
            if ($result['success']) {
                saveEnvFile($db['host'], $db['user'], $db['pass'], $db['dbname']);
                $step = '4';
            } else {
                $message = 'Errore: ' . $result['error'];
                $messageType = 'error';
            }
        }
    }

    elseif ($step === '4') {
        $db = $_SESSION['db'] ?? null;
        $adminUser = trim($_POST['admin_user'] ?? 'admin');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminPass = $_POST['admin_pass'] ?? '';

        if (empty($adminEmail) || empty($adminPass)) {
            $message = 'Compila tutti i campi';
            $messageType = 'error';
        } elseif (strlen($adminPass) < 6) {
            $message = 'La password deve avere almeno 6 caratteri';
            $messageType = 'error';
        } elseif ($db) {
            $result = createAdmin($db['host'], $db['user'], $db['pass'], $db['dbname'], $adminUser, $adminEmail, $adminPass);
            if ($result['success']) {
                $step = '5';
                session_destroy();
            } else {
                $message = 'Errore: ' . ($result['error'] ?? 'sconosciuto');
                $messageType = 'error';
            }
        }
    }
}

// Verifica se già installato
$envExists = file_exists(__DIR__ . '/.env');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installazione - Luxury Hotel</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #F5EFE7 0%, #FDFBF7 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(139, 111, 71, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #8B6F47 0%, #6B5330 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 24px; margin-bottom: 8px; }
        .header p { opacity: 0.9; font-size: 14px; }
        .content { padding: 30px; }
        .steps {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 30px;
        }
        .step-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #ddd;
        }
        .step-dot.active { background: #8B6F47; }
        .step-dot.done { background: #4CAF50; }
        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #333;
        }
        input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s;
        }
        input:focus {
            outline: none;
            border-color: #8B6F47;
        }
        .hint {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
        }
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #8B6F47 0%, #6B5330 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(139, 111, 71, 0.3);
        }
        .message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .message.error { background: #ffebee; color: #c62828; }
        .message.success { background: #e8f5e9; color: #2e7d32; }
        .success-box {
            text-align: center;
            padding: 20px;
        }
        .success-box .icon {
            width: 80px;
            height: 80px;
            background: #e8f5e9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
        }
        .links { margin-top: 30px; }
        .links a {
            display: block;
            padding: 12px;
            background: #f5f5f5;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            margin-bottom: 10px;
            transition: background 0.2s;
        }
        .links a:hover { background: #eee; }
        .warning {
            background: #fff3e0;
            border: 1px solid #ffcc80;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #e65100;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Luxury Hotel</h1>
            <p>Installazione guidata</p>
        </div>

        <div class="content">
            <div class="steps">
                <div class="step-dot <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>"></div>
                <div class="step-dot <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>"></div>
                <div class="step-dot <?= $step >= 3 ? ($step > 3 ? 'done' : 'active') : '' ?>"></div>
                <div class="step-dot <?= $step >= 4 ? ($step > 4 ? 'done' : 'active') : '' ?>"></div>
                <div class="step-dot <?= $step >= 5 ? 'done' : '' ?>"></div>
            </div>

            <?php if ($message): ?>
                <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($step === '1'): ?>
                <!-- STEP 1: Benvenuto -->
                <h2 style="margin-bottom: 16px;">Benvenuto!</h2>
                <p style="color: #666; margin-bottom: 20px;">
                    Questa procedura ti guiderà nell'installazione del sistema di prenotazioni.
                </p>

                <h3 style="margin: 20px 0 10px;">Requisiti:</h3>
                <ul style="color: #666; margin-left: 20px; margin-bottom: 20px;">
                    <li>XAMPP (o server con Apache + PHP + MySQL)</li>
                    <li>PHP 7.4 o superiore</li>
                    <li>MySQL 5.7 o superiore</li>
                </ul>

                <?php if ($envExists): ?>
                    <div class="warning">
                        Il file .env esiste già. Procedendo sovrascriverai la configurazione esistente.
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="step" value="2">
                    <button type="submit">Inizia installazione</button>
                </form>

            <?php elseif ($step === '2'): ?>
                <!-- STEP 2: Configurazione Database -->
                <h2 style="margin-bottom: 16px;">Configurazione Database</h2>
                <p style="color: #666; margin-bottom: 20px;">
                    Inserisci i dati di connessione MySQL.
                </p>

                <form method="POST">
                    <input type="hidden" name="step" value="2">

                    <div class="form-group">
                        <label>Host</label>
                        <input type="text" name="db_host" value="localhost">
                        <div class="hint">Di solito "localhost" per XAMPP</div>
                    </div>

                    <div class="form-group">
                        <label>Utente MySQL</label>
                        <input type="text" name="db_user" value="root">
                        <div class="hint">Di solito "root" per XAMPP</div>
                    </div>

                    <div class="form-group">
                        <label>Password MySQL</label>
                        <input type="password" name="db_pass" value="">
                        <div class="hint">Lascia vuoto se usi XAMPP di default</div>
                    </div>

                    <div class="form-group">
                        <label>Nome Database</label>
                        <input type="text" name="db_name" value="luxury_hotel">
                        <div class="hint">Verrà creato automaticamente</div>
                    </div>

                    <button type="submit">Testa connessione</button>
                </form>

            <?php elseif ($step === '3'): ?>
                <!-- STEP 3: Creazione tabelle -->
                <h2 style="margin-bottom: 16px;">Creazione Database</h2>
                <p style="color: #666; margin-bottom: 20px;">
                    Connessione riuscita! Ora creeremo il database e le tabelle.
                </p>

                <form method="POST">
                    <input type="hidden" name="step" value="3">
                    <button type="submit">Crea database e tabelle</button>
                </form>

            <?php elseif ($step === '4'): ?>
                <!-- STEP 4: Crea Admin -->
                <h2 style="margin-bottom: 16px;">Crea Amministratore</h2>
                <p style="color: #666; margin-bottom: 20px;">
                    Database creato! Ora crea l'account amministratore.
                </p>

                <form method="POST">
                    <input type="hidden" name="step" value="4">

                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="admin_user" value="admin">
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="admin_email" required placeholder="admin@tuodominio.com">
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="admin_pass" required minlength="6" placeholder="Minimo 6 caratteri">
                    </div>

                    <button type="submit">Crea amministratore</button>
                </form>

            <?php elseif ($step === '5'): ?>
                <!-- STEP 5: Completato -->
                <div class="success-box">
                    <div class="icon">&#10004;</div>
                    <h2 style="margin-bottom: 16px; color: #2e7d32;">Installazione completata!</h2>
                    <p style="color: #666; margin-bottom: 10px;">
                        Il sistema è pronto all'uso.
                    </p>
                </div>

                <div class="warning" style="background: #e3f2fd; border-color: #90caf9; color: #1565c0;">
                    <strong>Importante:</strong> Elimina o rinomina questo file (install.php) per sicurezza!
                </div>

                <div class="links">
                    <a href="index.html">
                        <strong>Sito pubblico</strong><br>
                        <small>Dove i clienti prenotano</small>
                    </a>
                    <a href="login.html">
                        <strong>Pannello Admin</strong><br>
                        <small>Gestisci le prenotazioni</small>
                    </a>
                </div>

            <?php endif; ?>
        </div>
    </div>
</body>
</html>
