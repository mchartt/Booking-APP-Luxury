#!/usr/bin/env php
<?php
/**
 * create_superadmin.php - Script CLI per creare il primo utente admin
 *
 * SICUREZZA: Questo script puo essere eseguito SOLO da Command Line Interface (CLI).
 * Non puo essere eseguito tramite browser/web server.
 *
 * UTILIZZO:
 *   php create_superadmin.php
 *   php create_superadmin.php --username=admin --email=admin@example.com
 *
 * Se non vengono forniti parametri, lo script chiede i dati in modo interattivo.
 */

// ===== PROTEZIONE CLI-ONLY =====
if (php_sapi_name() !== 'cli') {
    // Blocca esecuzione da browser
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "Accesso negato. Questo script puo essere eseguito solo da linea di comando (CLI).\n";
    exit(1);
}

// ===== COSTANTI =====
define('MIN_PASSWORD_LENGTH', 8);

// ===== CARICA CONFIGURAZIONE DATABASE =====
// Disabilita temporaneamente l'output degli errori web
$_SERVER['REQUEST_URI'] = '/cli';

require_once __DIR__ . '/config.php';

// Verifica connessione
if (!isset($conn) || $conn === null) {
    fwrite(STDERR, "\n[ERRORE] Impossibile connettersi al database.\n");
    fwrite(STDERR, "Verifica il file .env e le credenziali del database.\n\n");
    exit(1);
}

// Esegui migrazioni se necessario (crea tabella admin_users se non esiste)
if (function_exists('runAutoMigrations')) {
    runAutoMigrations($conn);
}

// ===== FUNZIONI HELPER =====

/**
 * Stampa messaggio colorato (se supportato)
 */
function colorOutput($text, $color = 'default') {
    $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'default' => "\033[0m",
        'bold' => "\033[1m",
    ];

    // Verifica se il terminale supporta i colori
    $supportsColor = (DIRECTORY_SEPARATOR !== '\\' || getenv('ANSICON') !== false);

    if ($supportsColor && isset($colors[$color])) {
        return $colors[$color] . $text . $colors['default'];
    }
    return $text;
}

/**
 * Chiede input all'utente da CLI
 */
function promptInput($prompt, $default = '', $required = true) {
    $defaultDisplay = $default ? " [$default]" : '';
    echo $prompt . $defaultDisplay . ": ";

    $input = trim(fgets(STDIN));

    if (empty($input) && !empty($default)) {
        return $default;
    }

    if (empty($input) && $required) {
        echo colorOutput("  [!] Questo campo e obbligatorio.\n", 'yellow');
        return promptInput($prompt, $default, $required);
    }

    return $input;
}

/**
 * Chiede password nascosta (se possibile)
 */
function promptPassword($prompt, $confirm = true) {
    // Su Windows, la password non puo essere nascosta facilmente senza estensioni
    $isWindows = (DIRECTORY_SEPARATOR === '\\');

    if ($isWindows) {
        echo $prompt . ": ";
        $password = trim(fgets(STDIN));
    } else {
        // Su Linux/Mac possiamo nascondere l'input
        echo $prompt . ": ";
        system('stty -echo');
        $password = trim(fgets(STDIN));
        system('stty echo');
        echo "\n";
    }

    if (empty($password)) {
        echo colorOutput("  [!] La password e obbligatoria.\n", 'yellow');
        return promptPassword($prompt, $confirm);
    }

    if ($confirm) {
        if ($isWindows) {
            echo "Conferma password: ";
            $confirm = trim(fgets(STDIN));
        } else {
            echo "Conferma password: ";
            system('stty -echo');
            $confirm = trim(fgets(STDIN));
            system('stty echo');
            echo "\n";
        }

        if ($password !== $confirm) {
            echo colorOutput("  [!] Le password non coincidono. Riprova.\n", 'red');
            return promptPassword($prompt, true);
        }
    }

    return $password;
}

/**
 * Valida username
 */
function validateUsername($username) {
    $errors = [];

    if (strlen($username) < 3) {
        $errors[] = "Username troppo corto (minimo 3 caratteri)";
    }
    if (strlen($username) > 50) {
        $errors[] = "Username troppo lungo (massimo 50 caratteri)";
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username puo contenere solo lettere, numeri e underscore";
    }

    return $errors;
}

/**
 * Valida email
 */
function validateEmailFormat($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ["Formato email non valido"];
    }
    return [];
}

/**
 * Valida password (stessa policy di auth.php)
 */
function validatePasswordStrength($password) {
    $errors = [];

    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors[] = "Password deve avere almeno " . MIN_PASSWORD_LENGTH . " caratteri";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password deve contenere almeno una lettera maiuscola";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password deve contenere almeno una lettera minuscola";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password deve contenere almeno un numero";
    }
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':\"\\|,.<>\/?~`]/', $password)) {
        $errors[] = "Password deve contenere almeno un carattere speciale (!@#$%^&*...)";
    }

    return $errors;
}

/**
 * Verifica se esistono gia admin attivi
 */
function hasActiveAdmins($conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin_users WHERE status = 'active'");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['count'] > 0;
}

/**
 * Verifica se username o email sono gia in uso
 */
function isDuplicate($conn, $username, $email) {
    $stmt = $conn->prepare("SELECT id, username, email FROM admin_users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $existing = $result->fetch_assoc();
        $stmt->close();
        return $existing;
    }

    $stmt->close();
    return false;
}

/**
 * Crea l'utente superadmin
 */
function createSuperadmin($conn, $username, $email, $password) {
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO admin_users
        (username, email, password_hash, status, email_verified, created_at)
        VALUES (?, ?, ?, 'active', TRUE, NOW())");
    $stmt->bind_param("sss", $username, $email, $passwordHash);

    $success = $stmt->execute();
    $insertId = $success ? $stmt->insert_id : null;
    $stmt->close();

    return $insertId;
}

// ===== MAIN =====

echo "\n";
echo colorOutput("=================================================\n", 'blue');
echo colorOutput("   LUXURY HOTEL - Creazione Superadmin (CLI)\n", 'bold');
echo colorOutput("=================================================\n", 'blue');
echo "\n";

// Verifica se esistono gia admin attivi
if (hasActiveAdmins($conn)) {
    echo colorOutput("[ATTENZIONE] Esistono gia utenti admin attivi nel sistema.\n", 'yellow');
    echo "Continuando, creerai un altro utente admin.\n\n";

    echo "Vuoi continuare? (s/N): ";
    $continue = strtolower(trim(fgets(STDIN)));

    if ($continue !== 's' && $continue !== 'si' && $continue !== 'y' && $continue !== 'yes') {
        echo "\nOperazione annullata.\n\n";
        exit(0);
    }
    echo "\n";
}

// Parsing argomenti CLI
$options = getopt('', ['username:', 'email:', 'help']);

if (isset($options['help'])) {
    echo "Utilizzo: php create_superadmin.php [opzioni]\n\n";
    echo "Opzioni:\n";
    echo "  --username=NOME     Username dell'admin\n";
    echo "  --email=EMAIL       Email dell'admin\n";
    echo "  --help              Mostra questo messaggio\n\n";
    echo "Se non forniti, username e email verranno chiesti interattivamente.\n";
    echo "La password viene sempre chiesta interattivamente per sicurezza.\n\n";
    exit(0);
}

// Username
$username = $options['username'] ?? null;
if (empty($username)) {
    $username = promptInput("Username");
}

$usernameErrors = validateUsername($username);
if (!empty($usernameErrors)) {
    echo colorOutput("\n[ERRORE] Username non valido:\n", 'red');
    foreach ($usernameErrors as $err) {
        echo "  - $err\n";
    }
    exit(1);
}

// Email
$email = $options['email'] ?? null;
if (empty($email)) {
    $email = promptInput("Email");
}

$emailErrors = validateEmailFormat($email);
if (!empty($emailErrors)) {
    echo colorOutput("\n[ERRORE] Email non valida:\n", 'red');
    foreach ($emailErrors as $err) {
        echo "  - $err\n";
    }
    exit(1);
}

// Verifica duplicati
$duplicate = isDuplicate($conn, $username, $email);
if ($duplicate) {
    echo colorOutput("\n[ERRORE] Username o email gia esistenti:\n", 'red');
    if ($duplicate['username'] === $username) {
        echo "  - Username '$username' gia in uso\n";
    }
    if ($duplicate['email'] === $email) {
        echo "  - Email '$email' gia in uso\n";
    }
    exit(1);
}

// Password (sempre interattiva per sicurezza - mai da CLI args)
echo "\n";
echo colorOutput("Requisiti password:\n", 'blue');
echo "  - Minimo " . MIN_PASSWORD_LENGTH . " caratteri\n";
echo "  - Almeno una lettera maiuscola\n";
echo "  - Almeno una lettera minuscola\n";
echo "  - Almeno un numero\n";
echo "  - Almeno un carattere speciale (!@#$%^&*...)\n\n";

$password = promptPassword("Password");

$passwordErrors = validatePasswordStrength($password);
if (!empty($passwordErrors)) {
    echo colorOutput("\n[ERRORE] Password non valida:\n", 'red');
    foreach ($passwordErrors as $err) {
        echo "  - $err\n";
    }
    exit(1);
}

// Conferma creazione
echo "\n";
echo colorOutput("Riepilogo:\n", 'bold');
echo "  Username: $username\n";
echo "  Email:    $email\n";
echo "  Status:   active (admin)\n";
echo "\n";

echo "Creare questo utente admin? (s/N): ";
$confirm = strtolower(trim(fgets(STDIN)));

if ($confirm !== 's' && $confirm !== 'si' && $confirm !== 'y' && $confirm !== 'yes') {
    echo "\nOperazione annullata.\n\n";
    exit(0);
}

// Creazione utente
$userId = createSuperadmin($conn, $username, $email, $password);

if ($userId) {
    echo "\n";
    echo colorOutput("[SUCCESS] Superadmin creato con successo!\n", 'green');
    echo "\n";
    echo "  ID:       $userId\n";
    echo "  Username: $username\n";
    echo "  Email:    $email\n";
    echo "\n";
    echo colorOutput("Ora puoi effettuare il login su login.html\n", 'blue');
    echo "\n";

    // Log dell'operazione
    error_log("SUPERADMIN CREATED via CLI: username=$username, email=$email, id=$userId");

    exit(0);
} else {
    echo colorOutput("\n[ERRORE] Impossibile creare l'utente admin.\n", 'red');
    echo "Controlla i log per maggiori dettagli.\n\n";
    exit(1);
}
