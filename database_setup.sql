-- =====================================================
-- LUXURY HOTEL - Setup Database Completo
-- =====================================================
-- ISTRUZIONI:
-- 1. Apri phpMyAdmin (http://localhost/phpmyadmin)
-- 2. Clicca "Nuovo" per creare database "luxury_hotel"
-- 3. Seleziona il database
-- 4. Vai su tab "SQL"
-- 5. Copia-incolla TUTTO questo file
-- 6. Clicca "Esegui"
-- =====================================================

-- Usa il database (crealo prima da phpMyAdmin!)
USE luxury_hotel;

-- =====================================================
-- TABELLA: PRENOTAZIONI
-- Contiene tutte le prenotazioni dei clienti
-- =====================================================
CREATE TABLE IF NOT EXISTS prenotazioni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id VARCHAR(50) UNIQUE NOT NULL COMMENT 'ID univoco prenotazione (es: BK20260311120000_abc12345)',

    -- Dettagli camera
    room_type ENUM('Standard', 'Deluxe', 'Suite') NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    guests INT NOT NULL,

    -- Dati cliente
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    notes TEXT,

    -- Prezzo
    total_price DECIMAL(10,2) NOT NULL,

    -- Stati
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    payment_status ENUM('pending', 'processing', 'completed', 'failed', 'pending_transfer', 'refunded') DEFAULT 'pending',
    payment_method ENUM('card', 'paypal', 'iban') NULL,
    transaction_id VARCHAR(100) NULL,
    paid_at DATETIME NULL,

    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indici per performance
    INDEX idx_booking_id (booking_id),
    INDEX idx_check_in (check_in),
    INDEX idx_check_out (check_out),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELLA: ADMIN USERS
-- Account amministratori per il pannello di gestione
-- =====================================================
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL COMMENT 'Hash bcrypt della password',

    -- Stato account
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',

    -- Verifica email (opzionale)
    email_verified TINYINT(1) DEFAULT 0,
    verification_token VARCHAR(100) NULL,
    verification_expires DATETIME NULL,

    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL,

    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELLA: LOGIN ATTEMPTS
-- Traccia tentativi di login per sicurezza (rate limiting)
-- =====================================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success TINYINT(1) DEFAULT 0,

    INDEX idx_ip_address (ip_address),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELLA: PAYMENTS (Log transazioni)
-- Storico dettagliato di tutti i pagamenti
-- =====================================================
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id VARCHAR(50) NOT NULL,
    transaction_id VARCHAR(100) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    method ENUM('card', 'paypal', 'iban') NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',

    -- Dettagli carta (solo ultime 4 cifre per sicurezza)
    card_last_four VARCHAR(4) NULL,
    card_brand VARCHAR(20) NULL,

    -- Dettagli PayPal
    paypal_email VARCHAR(255) NULL,

    -- Errori
    error_message TEXT NULL,

    -- Info richiesta
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,

    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_booking_id (booking_id),
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CREA PRIMO AMMINISTRATORE
-- =====================================================
-- Username: admin
-- Email: admin@hotel.com
-- Password: Admin123!
--
-- IMPORTANTE: Cambia questa password dopo il primo login!
-- =====================================================

INSERT INTO admin_users (username, email, password, status, email_verified) VALUES
('admin', 'admin@hotel.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'approved', 1);

-- La password hash sopra corrisponde a "password" - SOLO PER TEST!
-- Per generare un hash sicuro per "Admin123!" usa PHP:
-- echo password_hash('Admin123!', PASSWORD_DEFAULT);

-- =====================================================
-- DATI DI ESEMPIO (Opzionale - Rimuovi in produzione)
-- =====================================================

-- Prenotazione di esempio
INSERT INTO prenotazioni (booking_id, room_type, check_in, check_out, guests, name, email, phone, total_price, status, payment_status) VALUES
('BK20260315100000_demo1234', 'Deluxe', '2026-04-01', '2026-04-05', 2, 'Mario Rossi', 'mario.rossi@email.com', '+39 333 1234567', 720.00, 'confirmed', 'completed'),
('BK20260316110000_demo5678', 'Suite', '2026-04-10', '2026-04-12', 3, 'Laura Bianchi', 'laura.bianchi@email.com', '+39 340 9876543', 560.00, 'pending', 'pending');

-- =====================================================
-- VERIFICA INSTALLAZIONE
-- =====================================================

-- Mostra tabelle create
SHOW TABLES;

-- Conta record
SELECT 'prenotazioni' as tabella, COUNT(*) as record FROM prenotazioni
UNION ALL
SELECT 'admin_users', COUNT(*) FROM admin_users
UNION ALL
SELECT 'login_attempts', COUNT(*) FROM login_attempts
UNION ALL
SELECT 'payments', COUNT(*) FROM payments;

-- =====================================================
-- SETUP COMPLETATO!
--
-- Prossimi passi:
-- 1. Copia i file del progetto in htdocs
-- 2. Crea il file .env dalla copia di .env.example
-- 3. Configura le credenziali database in .env
-- 4. Apri http://localhost/progetto-AI/
-- 5. Accedi come admin: admin@hotel.com / password
-- 6. CAMBIA SUBITO LA PASSWORD!
-- =====================================================
