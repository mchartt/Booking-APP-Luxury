-- =====================================================
-- Database Update Script - Luxury Hotel Payment System
-- =====================================================
-- Eseguire questo script per aggiungere le colonne di pagamento
-- alla tabella prenotazioni esistente.
--
-- Eseguire in phpMyAdmin o altro client MySQL:
-- mysql -u root -p luxury_hotel < database_update.sql
-- =====================================================

-- Seleziona il database
USE luxury_hotel;

-- =====================================================
-- AGGIORNA TABELLA PRENOTAZIONI
-- =====================================================

-- Aggiungi colonne per gestione pagamenti (se non esistono)
ALTER TABLE prenotazioni
    ADD COLUMN IF NOT EXISTS payment_status ENUM('pending', 'processing', 'completed', 'failed', 'pending_transfer', 'refunded') DEFAULT 'pending' AFTER status,
    ADD COLUMN IF NOT EXISTS payment_method ENUM('card', 'paypal', 'iban') NULL AFTER payment_status,
    ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100) NULL AFTER payment_method,
    ADD COLUMN IF NOT EXISTS paid_at TIMESTAMP NULL AFTER transaction_id;

-- Aggiungi indici per performance
CREATE INDEX IF NOT EXISTS idx_payment_status ON prenotazioni(payment_status);
CREATE INDEX IF NOT EXISTS idx_booking_id ON prenotazioni(booking_id);
CREATE INDEX IF NOT EXISTS idx_check_in ON prenotazioni(check_in);

-- =====================================================
-- CREA TABELLA PAGAMENTI (Log transazioni)
-- =====================================================

CREATE TABLE IF NOT EXISTS payments (
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
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),

    FOREIGN KEY (booking_id) REFERENCES prenotazioni(booking_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CREA TABELLA RATE LIMITING
-- =====================================================

CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    endpoint VARCHAR(100) NOT NULL,
    request_count INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_ip_endpoint (ip_address, endpoint),
    INDEX idx_window_start (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- AGGIORNA PRENOTAZIONI ESISTENTI
-- =====================================================

-- Imposta payment_status = 'completed' per prenotazioni già confermate
-- (assumi che le prenotazioni esistenti siano già pagate)
UPDATE prenotazioni
SET payment_status = 'completed'
WHERE status = 'confirmed' AND payment_status = 'pending';

-- =====================================================
-- VERIFICA
-- =====================================================

-- Mostra struttura aggiornata
DESCRIBE prenotazioni;
DESCRIBE payments;

-- Conta record
SELECT COUNT(*) as total_bookings FROM prenotazioni;
SELECT COUNT(*) as total_payments FROM payments;

-- =====================================================
-- NOTE IMPORTANTI
-- =====================================================
-- 1. Eseguire BACKUP del database prima di lanciare questo script
-- 2. Se ricevi errori "IF NOT EXISTS", la tua versione MySQL potrebbe non supportarlo.
--    In tal caso, rimuovi "IF NOT EXISTS" e gestisci manualmente le colonne esistenti.
-- 3. Per produzione, considera l'aggiunta di audit trail e soft delete.
-- =====================================================
