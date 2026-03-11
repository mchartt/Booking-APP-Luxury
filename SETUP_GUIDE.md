# 🏨 Luxury Hotel - Setup Guide Completo

Questa guida ti aiuterà a configurare completamente il sistema di prenotazione Luxury Hotel con PHP e MySQL.

## 📋 Prerequisiti

### Hardware/Software Richiesti
- **PHP**: 7.4 o superiore (con MySQLi extension)
- **MySQL**: 5.7 o superiore
- **Server Web**: Apache, Nginx, o IIS con PHP support
- **Browser**: Qualsiasi browser moderno (Chrome, Firefox, Safari, Edge)

### Verifica Prerequisiti

#### Su Windows
```bash
# Verifica PHP versione
php -v

# Verifica MySQL versione
mysql --version
```

#### Su macOS/Linux
```bash
# Verifica PHP versione
php -v

# Verifica MySQL versione
mysql --version
```

Se non hai PHP o MySQL installati, scarica:
- **PHP**: https://www.php.net/downloads
- **MySQL**: https://dev.mysql.com/downloads/mysql/
- **Alternativa One-Click**: XAMPP, WAMP, o MAMP

## 🗄️ Passo 1: Setup Database MySQL

### Opzione A: Interfaccia Visuale (Consigliato per Principianti)

1. **Apri MySQL Workbench o phpMyAdmin**
   - Accedi con le tue credenziali root

2. **Crea nuovo schema**
   - Click su "+" o crea nuovo database
   - Nome: `luxury_hotel`

3. **Import SQL Schema**
   - Apri file `database-setup.sql` nel progetto
   - Copia il contenuto
   - Incolla in MySQL Workbench > Execute Script (⚡ icona)
   - Oppure in phpMyAdmin > tab "SQL" > incolla > Execute

4. **Verifica importazione**
   - Dovresti vedere le tabelle:
     - `prenotazioni`
     - `rooms`
     - `users`
     - `logs`

### Opzione B: Riga di Comando

```bash
# Accedi a MySQL
mysql -u root -p

# Crea database
CREATE DATABASE IF NOT EXISTS luxury_hotel;
USE luxury_hotel;

# Importa schema (dalla cartella del progetto)
source database-setup.sql;

# Verifica tabelle
SHOW TABLES;
```

### Opzione C: Importazione Diretta

```bash
# Importa direttamente il file SQL (dalla cartella del progetto)
mysql -u root -p luxury_hotel < database-setup.sql
```

## 🔑 Passo 2: Configurazione Credenziali Database

### 1. Copia file .env

```bash
# Dalla cartella Booking-APP-Luxury
cp .env.example .env

# Su Windows (PowerShell)
Copy-Item .env.example .env
```

### 2. Modifica file .env

Apri il file `.env` con un editor (VS Code, Sublime, Notepad++):

```ini
# DATABASE CONFIGURATION
DB_HOST=localhost          # Cambia se MySQL è su server remoto
DB_USER=root               # Cambia con il tuo user MySQL
DB_PASSWORD=               # Metti la tua password se presente
DB_NAME=luxury_hotel       # Nome database creato
DB_PORT=3306               # Porta MySQL (default 3306)

# EMAIL CONFIGURATION (opzionale)
MAIL_FROM=info@luxuryhotel.it
MAIL_FROM_NAME=Luxury Hotel

# DEBUG MODE
DEBUG=true
```

### 3. Verifica Connessione

Crea un file `test-connection.php` nella cartella root:

```php
<?php
require_once 'config.php';

if (isset($conn) && !$conn->connect_error) {
    echo "✓ Connessione al database riuscita!";
    echo "<br>Database: " . $conn->select_db('luxury_hotel') ? 'OK' : 'ERRORE';
} else {
    echo "✗ Errore connessione: " . $conn->connect_error;
}
?>
```

Apri nel browser: `http://localhost/Booking-APP-Luxury/test-connection.php`

## 🚀 Passo 3: Avvio Applicazione

### Opzione 1: Usando Apache/XAMPP (Consigliato)

```bash
# 1. Avvia XAMPP/Apache
# Windows: Start XAMPP Control Panel > Apache Start
# macOS: sudo apachectl start
# Linux: sudo systemctl start apache2

# 2. Copia cartella Booking-APP-Luxury in htdocs/
# Windows: C:\xampp\htdocs\
# macOS: /Applications/MAMP/htdocs/
# Linux: /var/www/html/

# 3. Apri browser
http://localhost/Booking-APP-Luxury/index.html
```

### Opzione 2: Usando PHP Built-in Server (Sviluppo)

```bash
# Dalla cartella Booking-APP-Luxury
php -S localhost:8000

# Apri browser
http://localhost:8000/index.html
```

### Opzione 3: Usando Nginx (Avanzato)

```bash
# Configura nginx.conf per puntare alla cartella Booking-APP-Luxury
# Riavvia Nginx
sudo systemctl restart nginx

# Apri browser
http://localhost/index.html
```

## ✅ Test Funzionamento

### 1. Test Frontend

Apri `http://localhost/Booking-APP-Luxury/index.html` e verifica:

- ✓ Pagina carica completamente
- ✓ Immagini visibili
- ✓ Font Google caricati (Playfair Display se title è elegante)
- ✓ Navbar responsive funziona (menu hamburger su mobile)

### 2. Test API Endpoints

Apri il browser console (F12) e testa gli endpoint:

```javascript
// Test: Ottieni date prenotate
fetch('./api/bookings.php?action=booked-dates')
    .then(r => r.json())
    .then(d => console.log(d));

// Test: Crea prenotazione
fetch('./api/bookings.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        roomType: 'Standard',
        checkIn: '2026-03-20',
        checkOut: '2026-03-22',
        guests: '2',
        name: 'Test User',
        email: 'test@example.com',
        phone: '+39 1234567890',
        requests: 'Test request',
        totalPrice: '240'
    })
})
.then(r => r.json())
.then(d => console.log(d));
```

### 3. Test Form Booking

1. Clicca su "Prenota Ora" nella hero section
2. Seleziona una camera dalla sezione "Le Nostre Stanze"
3. Compila il form:
   - Check-in: data futura
   - Check-out: 2-3 giorni dopo
   - Ospiti: numero valido per camera
   - Nome, Email, Telefono: dati validi
4. Clicca "Conferma Prenotazione"
5. Dovresti vedere notifica di successo
6. Pagina dopo 2 secondi si ricarica

## 🐛 Troubleshooting

### Errore: "Connessione fallita al database"

```
Soluzione:
1. Verifica che MySQL sia running
   - Windows: XAMPP Control Panel > MySQL Start
   - macOS: mysql.server start
   - Linux: sudo systemctl start mysql

2. Verifica credenziali in .env
   - Esegui test-connection.php

3. Assicurati che database esista
   ```bash
   mysql -u root -p
   SHOW DATABASES;  # Vedi se "luxury_hotel" esiste
   ```
```

### Errore: "404 - API non trovata"

```
Soluzione:
1. Verifica struttura file:
   └── api/
       └── bookings.php

2. Verifica che config.php esista nella root

3. Prova accesso diretto:
   http://localhost/Booking-APP-Luxury/api/bookings.php
   Dovrebbe dare JSON response o errore di validazione
```

### Errore: "CORS Error" nella console

```
Soluzione:
1. Se usi server separato frontend/backend, verifica CORS in config.php
2. Per localhost, solitamente non ci sono problemi CORS
3. Controlla Network tab in browser (F12) per vedere request reale
```

### Date prenotate non si caricano

```
Soluzione:
1. Apri Console (F12) e controlla errori
2. Verifica che api/bookings.php?action=booked-dates funzioni
3. Testa direttamente nel browser:
   http://localhost/Booking-APP-Luxury/api/bookings.php?action=booked-dates
   Dovrebbe ritornare JSON con dates
```

### Email non inviata

```
Soluzione:
1. Email è dentro commenti in sendConfirmationEmail()
2. Per abilitare, rimuovere commenti in api/bookings.php riga 250
3. Richiede mail server configurato in PHP
4. Alternative: Mailgun, SendGrid, AWS SES APIs
```

### "Permission Denied" su Linux

```
Soluzione:
chmod 755 Booking-APP-Luxury/
chmod 755 Booking-APP-Luxury/api/
# Se ancora problemi:
sudo chown -R www-data:www-data Booking-APP-Luxury/
```

## 🔒 Security Checklist

Prima di andare in produzione:

- [ ] Cambia password MySQL da root di default
- [ ] Copia `config.php` fuori da document root (possibile)
- [ ] Disabilita DEBUG in .env (`DEBUG=false`)
- [ ] Aggiungi input sanitization per tutti gli input
- [ ] Usa HTTPS in produzione
- [ ] Implementa rate limiting per API
- [ ] Aggiungi CSRF tokens al form
- [ ] Configura CORS correttamente (non usare `*` in produzione)
- [ ] Aggiungi validazione ulteriore lato server
- [ ] Abilita logs di audit nella tabella `logs`

## 📊 Gestione Prenotazioni

### Visualizzare Prenotazioni

```sql
-- Tutte le prenotazioni
SELECT * FROM prenotazioni ORDER BY created_at DESC;

-- Prenotazioni per data range
SELECT * FROM prenotazioni
WHERE check_in >= '2026-03-15' AND check_out <= '2026-03-20'
ORDER BY room_type;

-- Statistiche
SELECT room_type, COUNT(*) as booking_count, SUM(total_price) as revenue
FROM prenotazioni
WHERE status = 'confirmed'
GROUP BY room_type;
```

### Cancellare Prenotazione

```sql
UPDATE prenotazioni
SET status = 'cancelled'
WHERE booking_id = 'BK1710133056_a1b2c3d4e5f6';
```

### Backup Database

```bash
# Backup su file
mysqldump -u root -p luxury_hotel > luxury_hotel_backup.sql

# Restore da backup
mysql -u root -p luxury_hotel < luxury_hotel_backup.sql
```

## 🌐 Deployment Produzione

### Hosting Raccomandato
- **Shared Hosting**: Bluehost, SiteGround, HostGator
- **VPS**: DigitalOcean, Linode, Vultr
- **Cloud**: AWS, Google Cloud, Microsoft Azure

### Steps Deploy

1. **Upload files via FTP/SFTP**
   ```
   index.html → /public_html/
   styles.css → /public_html/
   script.js → /public_html/
   config.php → /public_html/
   api/ → /public_html/api/
   ```

2. **Import Database**
   ```
   Usa hosting cpanel/File Manager
   O host PhpMyAdmin e importa database-setup.sql
   ```

3. **Configura .env**
   ```
   DB_HOST = host MySQL (host offer)
   DB_USER = user MySQL (host offer)
   DB_PASSWORD = password (host offer)
   DB_NAME = nome database (solitamente user_luxuryhotel)
   ```

4. **Testa live**
   ```
   Visita: yourdomain.com/index.html
   Verifica form funziona
   Testa booking end-to-end
   ```

## 📞 Supporto

Se hai problemi:

1. **Controlla console browser** (F12 > Console tab)
2. **Visualizza Network requests** (F12 > Network tab)
3. **Verifica log server** (cpanel o shell access)
4. **Testa API endpoints** direttamente nel browser

## 📚 Risorse Utili

- [PHP Documentation](https://www.php.net/manual/)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [W3Schools PHP/MySQL](https://www.w3schools.com/php/)
- [Stack Overflow](https://stackoverflow.com/) - Ricerca errore specifico

---

**Versione**: 2.0
**Data**: 2026-03-11
**Status**: ✅ Pronto per Setup
