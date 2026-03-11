-- Database Luxury Hotel
-- Script per creare il database e le tabelle

CREATE DATABASE IF NOT EXISTS luxury_hotel;
USE luxury_hotel;

-- Tabella prenotazioni
CREATE TABLE IF NOT EXISTS prenotazioni (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id VARCHAR(50) UNIQUE NOT NULL,
    room_type VARCHAR(50) NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    guests INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    requests TEXT,
    nights INT NOT NULL,
    price_per_night DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('unpaid', 'paid') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_booking_id (booking_id),
    INDEX idx_email (email),
    INDEX idx_room_type (room_type),
    INDEX idx_check_in (check_in),
    INDEX idx_check_out (check_out),
    INDEX idx_status (status)
);

-- Tabella camere (per gestire disponibilità)
CREATE TABLE IF NOT EXISTS rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL UNIQUE,
    max_guests INT NOT NULL,
    price_per_night DECIMAL(10, 2) NOT NULL,
    description TEXT,
    amenities JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Inserisci tipi di camere
INSERT INTO rooms (type, max_guests, price_per_night, description, amenities) VALUES
('Standard', 2, 120, 'Perfect per coppie o viaggi singoli', '["WiFi", "TV", "Bagno privato", "Aria condizionata"]'),
('Deluxe', 3, 180, 'Spaziosa con vista panoramica', '["WiFi", "TV", "Bagno privato", "Aria condizionata", "Balcone"]'),
('Suite', 4, 280, 'Lussuosa con jacuzzi privata', '["WiFi", "TV", "Bagno privato", "Aria condizionata", "Balcone", "Jacuzzi"]');

-- Tabella utenti (per admin)
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'staff') DEFAULT 'staff',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabella log attività
CREATE TABLE IF NOT EXISTS logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Crea indici per performance
CREATE INDEX idx_prenotazioni_date ON prenotazioni(check_in, check_out);
CREATE INDEX idx_prenotazioni_room_date ON prenotazioni(room_type, check_in, check_out);

-- Trigger per aggiornare updated_at
DELIMITER $$
CREATE TRIGGER update_prenotazioni_timestamp
BEFORE UPDATE ON prenotazioni
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$
DELIMITER ;
