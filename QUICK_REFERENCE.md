# Luxury Hotel - Quick Reference

Riferimento tecnico rapido per sviluppatori.

---

## Stack Tecnologico

| Componente | Tecnologia | Versione |
|------------|------------|----------|
| Frontend | HTML5 + CSS3 + Vanilla JS | - |
| Backend | PHP | 7.4+ |
| Database | MySQL | 5.7+ |
| Pagamenti | Stripe API | v1 |
| Server | Apache/Nginx | - |

---

## Struttura Progetto

```
Booking-APP-Luxury/
├── index.html              # Homepage pubblica
├── payment.html            # Pagina pagamento Stripe
├── login.html              # Login admin
├── admin.html              # Pannello admin
├── styles.css              # CSS principale (responsive)
├── script.js               # JS prenotazioni
├── login.js                # JS autenticazione
├── admin.js                # JS pannello admin
├── config.php              # Config DB + funzioni utility
├── install.php             # Wizard installazione
├── create_superadmin.php   # CLI per creare admin
├── .env.example            # Template configurazione
├── .env                    # Configurazione (gitignored)
├── .htaccess               # Regole Apache + security
├── api/
│   ├── bookings.php        # API prenotazioni
│   ├── payments.php        # API pagamenti Stripe
│   ├── auth.php            # API autenticazione
│   └── admin.php           # API admin
├── database_setup.sql      # Schema database
├── README.md               # Documentazione principale
├── INSTALLA.txt            # Guida per principianti
├── SETUP_GUIDE.md          # Guida tecnica dettagliata
└── QUICK_REFERENCE.md      # Questo file
```

---

## API Endpoints

### Bookings API (`api/bookings.php`)

| Metodo | Endpoint | Descrizione |
|--------|----------|-------------|
| GET | `?action=booked-dates` | Date prenotate per room type |
| GET | `?action=booked-dates&room_type=X` | Date per camera specifica |
| GET | `?action=availability&room_type=X&check_in=Y&check_out=Z` | Verifica disponibilita |
| POST | (body JSON) | Crea nuova prenotazione |

**Esempio GET date prenotate:**
```bash
curl "http://localhost/Booking-APP-Luxury/api/bookings.php?action=booked-dates"
```

**Response:**
```json
{
  "success": true,
  "dates": {
    "Standard": [{"start": "2026-03-15", "end": "2026-03-18"}],
    "Deluxe": [],
    "Suite": [{"start": "2026-03-20", "end": "2026-03-22"}]
  }
}
```

**Esempio POST prenotazione:**
```bash
curl -X POST http://localhost/Booking-APP-Luxury/api/bookings.php \
  -H "Content-Type: application/json" \
  -d '{
    "roomType": "Deluxe",
    "checkIn": "2026-03-25",
    "checkOut": "2026-03-27",
    "guests": "2",
    "name": "Mario Rossi",
    "email": "mario@example.com",
    "phone": "+39 123456890",
    "requests": "Vista giardino",
    "totalPrice": "360"
  }'
```

### Payments API (`api/payments.php`)

| Metodo | Endpoint | Descrizione |
|--------|----------|-------------|
| POST | `?action=create-intent` | Crea PaymentIntent Stripe |
| POST | `?action=confirm` | Conferma pagamento |
| POST | `?action=webhook` | Riceve eventi Stripe |
| GET | `?action=status&booking_id=X` | Stato pagamento |

### Auth API (`api/auth.php`)

| Metodo | Endpoint | Descrizione |
|--------|----------|-------------|
| POST | `?action=login` | Login admin |
| POST | `?action=register` | Registrazione nuovo admin |
| POST | `?action=logout` | Logout |
| GET | `?action=check` | Verifica sessione |
| POST | `?action=verify-email` | Verifica email |

### Admin API (`api/admin.php`)

| Metodo | Endpoint | Descrizione |
|--------|----------|-------------|
| GET | `?action=bookings` | Lista prenotazioni |
| GET | `?action=stats` | Statistiche |
| POST | `?action=update-status` | Aggiorna stato prenotazione |
| POST | `?action=cancel` | Cancella prenotazione |
| GET | `?action=users` | Lista admin users |
| POST | `?action=approve-user` | Approva nuovo admin |

---

## Database Schema

### Tabella `prenotazioni`

```sql
CREATE TABLE prenotazioni (
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
    payment_status ENUM('pending', 'processing', 'completed', 'failed',
                        'pending_transfer', 'refunded') DEFAULT 'pending',
    payment_method ENUM('card', 'paypal', 'iban') NULL,
    transaction_id VARCHAR(100) NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_booking_id (booking_id),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_check_in (check_in),
    INDEX idx_email (email)
);
```

### Tabella `payments`

```sql
CREATE TABLE payments (
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
);
```

### Tabella `admin_users`

```sql
CREATE TABLE admin_users (
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
    INDEX idx_status (status)
);
```

### Tabella `login_attempts`

```sql
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(50) NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE,
    INDEX idx_ip (ip_address),
    INDEX idx_attempted_at (attempted_at)
);
```

---

## Funzioni PHP Principali (config.php)

### `getClientIp(): string`

Ottiene IP reale del client con supporto proxy sicuro.

```php
// Usa TRUSTED_PROXIES da .env per validare header X-Forwarded-For
// Logga warning CRITICAL se header proxy presenti ma TRUSTED_PROXIES vuoto
$ip = getClientIp();
```

### `isIpInTrustedList(string $ip, array $trustedList): bool`

Verifica se IP e nella lista fidati (supporta CIDR).

```php
$trusted = ['192.168.1.0/24', '10.0.0.1'];
$isTrusted = isIpInTrustedList('192.168.1.50', $trusted); // true
```

### `ipInCidr(string $ip, string $cidr): bool`

Verifica appartenenza IP a range CIDR (IPv4 e IPv6).

```php
ipInCidr('192.168.1.50', '192.168.1.0/24'); // true
ipInCidr('10.0.0.1', '192.168.1.0/24');     // false
```

### `validateEmail(string $email): bool`

```php
validateEmail('test@example.com'); // true
validateEmail('invalid');          // false
```

### `validateDate(string $date): bool`

```php
validateDate('2026-03-15'); // true (formato Y-m-d)
validateDate('15/03/2026'); // false
```

### `validatePhone(string $phone): bool`

```php
validatePhone('+39 123 456 7890'); // true (min 10 chars, solo numeri/+/-/spazi)
validatePhone('123');               // false
```

### `isRoomAvailable(string $roomType, string $checkIn, string $checkOut): bool`

```php
$available = isRoomAvailable('Deluxe', '2026-03-20', '2026-03-22');
```

### `getBookedDateRanges(?string $roomType = null): array`

```php
$allDates = getBookedDateRanges();
$deluxeDates = getBookedDateRanges('Deluxe');
```

### `validateBooking(array $data): array`

```php
$result = validateBooking($bookingData);
if ($result['valid']) {
    // Procedi con prenotazione
} else {
    // $result['errors'] contiene lista errori
}
```

---

## Variabili Ambiente (.env)

| Variabile | Tipo | Default | Descrizione |
|-----------|------|---------|-------------|
| `DB_HOST` | string | localhost | Host MySQL |
| `DB_USER` | string | - | Username MySQL |
| `DB_PASS` | string | - | Password MySQL |
| `DB_NAME` | string | - | Nome database |
| `DB_PORT` | int | 3306 | Porta MySQL |
| `DEBUG` | bool | false | Mostra errori |
| `TRUSTED_PROXIES` | string | - | IP proxy fidati (CSV/CIDR) |
| `MAIL_FROM` | string | - | Email mittente |
| `MAIL_FROM_NAME` | string | - | Nome mittente |
| `STRIPE_PUBLISHABLE_KEY` | string | - | Chiave pubblica Stripe |
| `STRIPE_SECRET_KEY` | string | - | Chiave segreta Stripe |
| `STRIPE_WEBHOOK_SECRET` | string | - | Secret webhook Stripe |
| `WEBHOOK_SECRET` | string | - | Secret generico webhook |

---

## Security Features

### Rate Limiting

| Tipo | Limite | Finestra | Blocco |
|------|--------|----------|--------|
| Login falliti | 5 | 15 min | IP bloccato |
| API requests | 100 | 1 min | 429 Too Many Requests |
| Booking per email | 10 | 1 ora | Email bloccata |

### TRUSTED_PROXIES Detection

Se header proxy presenti ma `TRUSTED_PROXIES` vuoto:

```
[CRITICAL] PROXY_MISCONFIGURATION - Header proxy rilevati (X-Forwarded-For)
ma TRUSTED_PROXIES non configurato. REMOTE_ADDR=10.0.0.5 usato per TUTTI gli utenti.
```

### Headers Sicurezza (.htaccess)

```apache
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "DENY"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

### Protezione File Sensibili

```apache
<FilesMatch "^\.env|config\.php$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

---

## Design System

### Colori (CSS Variables)

```css
:root {
    --color-marrone: #8B6F47;      /* Primary/Accent */
    --color-crema: #F5EFE7;        /* Background sections */
    --color-panna: #FDFBF7;        /* Background alternato */
    --color-beige: #C9B99A;        /* Bordi */
    --color-text: #5a5a5a;         /* Testo body */
    --color-dark: #4A3728;         /* Testo heading */
}
```

### Typography

```css
/* Headings */
font-family: 'Playfair Display', serif;

/* Body */
font-family: 'Lora', serif;

/* UI Elements */
font-family: 'Poppins', sans-serif;
```

### Spacing (8px base)

```css
--space-xs: 4px;
--space-sm: 8px;
--space-md: 16px;
--space-lg: 24px;
--space-xl: 32px;
--space-2xl: 48px;
--space-3xl: 64px;
```

### Shadows

```css
--shadow-soft: 0 2px 8px rgba(0,0,0,0.03);
--shadow-card: 0 4px 16px rgba(0,0,0,0.05);
--shadow-hover: 0 8px 24px rgba(139,111,71,0.08);
```

---

## Prezzi Camere

| Tipo | Prezzo/Notte | Max Ospiti | Codice |
|------|--------------|------------|--------|
| Standard | 120 EUR | 2 | `Standard` |
| Deluxe | 180 EUR | 3 | `Deluxe` |
| Suite | 280 EUR | 4 | `Suite` |

---

## Comandi Utili

### Creare Superadmin

```bash
php create_superadmin.php
```

### Test connessione DB

```bash
php -r "require 'config.php'; echo \$conn ? 'OK' : 'FAIL';"
```

### Generare segreto webhook

```bash
php -r "echo bin2hex(random_bytes(32));"
```

### Backup database

```bash
mysqldump -u root -p luxury_hotel > backup_$(date +%Y%m%d).sql
```

### Restore database

```bash
mysql -u root -p luxury_hotel < backup_20260311.sql
```

### Svuotare rate limiting

```bash
mysql -u root -p luxury_hotel -e "TRUNCATE login_attempts;"
```

---

## Stripe Test Cards

| Scenario | Card Number |
|----------|-------------|
| Successo | `4242 4242 4242 4242` |
| Rifiutata | `4000 0000 0000 0002` |
| Fondi insufficienti | `4000 0000 0000 9995` |
| 3D Secure | `4000 0000 0000 3220` |

---

## Log Analysis

### Errori critici da monitorare

```bash
# Proxy misconfiguration
grep "\[CRITICAL\] PROXY_MISCONFIGURATION" /var/log/php_errors.log

# Errori database
grep "Database Connection Error" /var/log/php_errors.log

# Rate limiting attivo
grep "Rate limit exceeded" /var/log/php_errors.log
```

### Metriche utili

```sql
-- Prenotazioni oggi
SELECT COUNT(*) FROM prenotazioni WHERE DATE(created_at) = CURDATE();

-- Revenue mese corrente
SELECT SUM(total_price) FROM prenotazioni
WHERE payment_status = 'completed'
AND MONTH(created_at) = MONTH(CURDATE());

-- Login falliti per IP
SELECT ip_address, COUNT(*) as attempts
FROM login_attempts
WHERE success = 0 AND attempted_at > NOW() - INTERVAL 1 HOUR
GROUP BY ip_address ORDER BY attempts DESC;
```

---

**Versione**: 4.0.0
**Ultimo Aggiornamento**: Marzo 2026
