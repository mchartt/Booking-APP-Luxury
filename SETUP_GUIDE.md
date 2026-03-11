# Luxury Hotel - Setup Guide Completo

Questa guida ti fornisce istruzioni dettagliate per configurare completamente il sistema di prenotazione Luxury Hotel.

## Indice

1. [Prerequisiti](#prerequisiti)
2. [Installazione Locale](#installazione-locale)
3. [Configurazione Database](#configurazione-database)
4. [Configurazione Ambiente (.env)](#configurazione-ambiente-env)
5. [Configurazione Sicurezza](#configurazione-sicurezza)
6. [Configurazione Pagamenti (Stripe)](#configurazione-pagamenti-stripe)
7. [Avvio Applicazione](#avvio-applicazione)
8. [Test Funzionamento](#test-funzionamento)
9. [Deployment Produzione](#deployment-produzione)
10. [Troubleshooting](#troubleshooting)

---

## Prerequisiti

### Hardware/Software Richiesti

| Requisito | Versione Minima | Consigliata |
|-----------|-----------------|-------------|
| PHP | 7.4 | 8.1+ |
| MySQL | 5.7 | 8.0+ |
| Apache/Nginx | 2.4 | Latest |
| Browser | Qualsiasi moderno | Chrome/Firefox |

### Estensioni PHP Richieste

```
- mysqli (connessione database)
- json (parsing JSON)
- mbstring (supporto UTF-8)
- openssl (connessioni sicure)
- curl (per Stripe API)
```

### Verifica Prerequisiti

#### Windows (XAMPP)
```bash
# Apri il prompt dei comandi e verifica PHP
php -v

# Verifica estensioni
php -m | findstr mysqli
```

#### Linux/macOS
```bash
# Verifica PHP versione
php -v

# Verifica estensioni installate
php -m | grep -E "mysqli|json|mbstring|curl"
```

### Installazione Rapida Prerequisiti

**Windows**: Scarica [XAMPP](https://www.apachefriends.org/download.html) (include tutto)

**Ubuntu/Debian**:
```bash
sudo apt update
sudo apt install apache2 php php-mysql php-mbstring php-curl mysql-server
```

**macOS**:
```bash
brew install php mysql
brew services start mysql
```

---

## Installazione Locale

### Metodo 1: Installer Automatico (Consigliato)

1. **Copia i file** nella document root del web server:
   ```
   Windows (XAMPP): C:\xampp\htdocs\Booking-APP-Luxury\
   Linux: /var/www/html/Booking-APP-Luxury/
   macOS (MAMP): /Applications/MAMP/htdocs/Booking-APP-Luxury/
   ```

2. **Crea il file .env**:
   ```bash
   # Copia il template
   cp .env.example .env

   # Modifica con le tue credenziali
   nano .env   # Linux/macOS
   notepad .env   # Windows
   ```

3. **Esegui l'installer**:
   ```
   http://localhost/Booking-APP-Luxury/install.php
   ```

4. **Segui la procedura guidata** che:
   - Verifica i requisiti di sistema
   - Crea il database e le tabelle
   - Crea il primo utente admin

### Metodo 2: Installazione Manuale

Se preferisci il controllo manuale:

1. **Crea il database**:
   ```sql
   CREATE DATABASE luxury_hotel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Importa lo schema**:
   ```bash
   mysql -u root -p luxury_hotel < database_setup.sql
   ```

3. **Configura .env** come descritto sotto

4. **Crea il primo admin**:
   ```bash
   php create_superadmin.php
   ```

---

## Configurazione Database

### Schema Database

Il sistema usa 4 tabelle principali:

| Tabella | Descrizione |
|---------|-------------|
| `prenotazioni` | Tutte le prenotazioni con stato e pagamento |
| `payments` | Log dettagliato dei pagamenti |
| `admin_users` | Utenti amministratori |
| `login_attempts` | Tentativi di login per rate limiting |

### Creazione Manuale (se necessario)

```sql
-- Connettiti a MySQL
mysql -u root -p

-- Crea database
CREATE DATABASE IF NOT EXISTS luxury_hotel
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Seleziona database
USE luxury_hotel;

-- Importa schema (dalla cartella del progetto)
SOURCE database_setup.sql;

-- Verifica tabelle create
SHOW TABLES;
```

### Utente Database Dedicato (Raccomandato per Produzione)

```sql
-- Crea utente dedicato (NON usare root in produzione!)
CREATE USER 'hotel_app'@'localhost' IDENTIFIED BY 'PasswordMoltoForte123!';

-- Concedi permessi solo sul database dell'app
GRANT SELECT, INSERT, UPDATE, DELETE ON luxury_hotel.* TO 'hotel_app'@'localhost';

-- Applica permessi
FLUSH PRIVILEGES;
```

---

## Configurazione Ambiente (.env)

Il file `.env` contiene tutte le configurazioni sensibili.

### Template Completo

```ini
# ===============================================
# DATABASE CONFIGURATION
# ===============================================
DB_HOST=localhost
DB_USER=hotel_app
DB_PASS=PasswordMoltoForte123!
DB_NAME=luxury_hotel
DB_PORT=3306

# ===============================================
# EMAIL CONFIGURATION
# ===============================================
MAIL_FROM=info@luxuryhotel.it
MAIL_FROM_NAME=Luxury Hotel

# ===============================================
# DEBUG MODE
# ===============================================
# ATTENZIONE: Mai "true" in produzione!
DEBUG=false

# ===============================================
# SECURITY - TRUSTED PROXIES
# ===============================================
# Configura SOLO se l'app e dietro un proxy/CDN/Load Balancer
# Lascia vuoto se non usi proxy (piu sicuro)
#
# Formati supportati:
#   - IP singolo: 192.168.1.1
#   - Range CIDR: 10.0.0.0/8
#   - Lista: 192.168.1.1,10.0.0.0/8
#
# Esempi comuni:
#   Cloudflare: 173.245.48.0/20,103.21.244.0/22,103.22.200.0/22,...
#   AWS ELB: 10.0.0.0/8,172.16.0.0/12
#   Nginx locale: 127.0.0.1
#
TRUSTED_PROXIES=

# ===============================================
# WEBHOOK SECRET
# ===============================================
# Segreto per validare webhook dai payment provider
# Genera con: php -r "echo bin2hex(random_bytes(32));"
WEBHOOK_SECRET=

# ===============================================
# STRIPE CONFIGURATION
# ===============================================
# Ottieni le chiavi da: https://dashboard.stripe.com/apikeys
#
# TEST (sviluppo):
#   pk_test_xxx, sk_test_xxx
#
# LIVE (produzione):
#   pk_live_xxx, sk_live_xxx
#
STRIPE_PUBLISHABLE_KEY=pk_test_xxx
STRIPE_SECRET_KEY=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
```

### Spiegazione Variabili

| Variabile | Descrizione | Obbligatorio |
|-----------|-------------|--------------|
| `DB_HOST` | Indirizzo server MySQL | Si |
| `DB_USER` | Username MySQL | Si |
| `DB_PASS` | Password MySQL | Si |
| `DB_NAME` | Nome database | Si |
| `DEBUG` | Mostra errori dettagliati | Si |
| `TRUSTED_PROXIES` | IP proxy fidati | Solo con CDN/LB |
| `STRIPE_*` | Credenziali Stripe | Solo per pagamenti |

---

## Configurazione Sicurezza

### TRUSTED_PROXIES - Perche e Importante

Quando l'app e dietro un **Load Balancer**, **CDN** (Cloudflare), o **Reverse Proxy**:

```
                                    +-----------------+
  Utente A (IP: 203.0.113.50) ---> |                 |
  Utente B (IP: 198.51.100.23) --> | Load Balancer   | ---> App (vede IP: 10.0.0.5)
  Utente C (IP: 192.0.2.100) ----> | (IP: 10.0.0.5)  |
                                    +-----------------+
```

**Problema**: L'app vede TUTTI gli utenti con lo stesso IP (quello del Load Balancer)!

**Conseguenze GRAVI**:
- Rate limiting non funziona (blocca tutti o nessuno)
- Audit log inutili (stesso IP per tutti)
- Geolocalizzazione impossibile
- Potenziale blocco di utenti legittimi

**Soluzione**: Configura `TRUSTED_PROXIES` con gli IP dei tuoi proxy.

### Configurazione per Provider Comuni

#### Cloudflare
```ini
# Lista completa IP Cloudflare (aggiornata a Marzo 2026)
TRUSTED_PROXIES=173.245.48.0/20,103.21.244.0/22,103.22.200.0/22,103.31.4.0/22,141.101.64.0/18,108.162.192.0/18,190.93.240.0/20,188.114.96.0/20,197.234.240.0/22,198.41.128.0/17,162.158.0.0/15,104.16.0.0/13,104.24.0.0/14,172.64.0.0/13,131.0.72.0/22
```

#### AWS (ELB/ALB)
```ini
# VPC privata AWS tipica
TRUSTED_PROXIES=10.0.0.0/8,172.16.0.0/12
```

#### Nginx Locale (stesso server)
```ini
TRUSTED_PROXIES=127.0.0.1
```

### Rilevamento Automatico Misconfiguration

Il sistema rileva automaticamente se `X-Forwarded-For` e presente ma `TRUSTED_PROXIES` non e configurato.

**Nei log vedrai**:
```
[CRITICAL] PROXY_MISCONFIGURATION - Header proxy rilevati (X-Forwarded-For)
ma TRUSTED_PROXIES non configurato. REMOTE_ADDR=10.0.0.5 usato per TUTTI gli utenti.
IMPATTO: Rate limiting non funzionante, audit log incorretti.
AZIONE: Configurare TRUSTED_PROXIES in .env con IP del Load Balancer/CDN.
```

**Azione richiesta**: Configura immediatamente `TRUSTED_PROXIES`!

### Rate Limiting

Il sistema include protezione automatica:

| Protezione | Limite | Blocco |
|------------|--------|--------|
| Login falliti | 5 tentativi | 15 minuti |
| API requests | 100/minuto | 1 minuto |
| Booking spam | 10/ora per email | 1 ora |

### Headers di Sicurezza

Il file `.htaccess` configura automaticamente:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- Blocco accesso a `.env` e file sensibili

---

## Configurazione Pagamenti (Stripe)

### 1. Crea Account Stripe

1. Vai su https://dashboard.stripe.com/register
2. Completa la registrazione
3. Vai su **Developers > API keys**

### 2. Ottieni le Chiavi API

| Chiave | Formato | Uso |
|--------|---------|-----|
| Publishable Key | `pk_test_*` / `pk_live_*` | Frontend (sicura da esporre) |
| Secret Key | `sk_test_*` / `sk_live_*` | Backend (MAI esporre!) |
| Webhook Secret | `whsec_*` | Validare webhook |

### 3. Configura nel .env

```ini
# Per SVILUPPO (test mode)
STRIPE_PUBLISHABLE_KEY=pk_test_51ABC...
STRIPE_SECRET_KEY=sk_test_51ABC...

# Per PRODUZIONE (live mode)
STRIPE_PUBLISHABLE_KEY=pk_live_51ABC...
STRIPE_SECRET_KEY=sk_live_51ABC...
```

### 4. Configura Webhook

1. Vai su **Developers > Webhooks** in Stripe Dashboard
2. Clicca **Add endpoint**
3. URL: `https://tuodominio.com/api/payments.php?action=webhook`
4. Eventi da ascoltare:
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
   - `charge.refunded`
5. Copia il **Signing secret** nel .env:
   ```ini
   STRIPE_WEBHOOK_SECRET=whsec_xxx
   ```

### 5. Test Pagamenti

Usa queste carte di test:
- **Successo**: `4242 4242 4242 4242`
- **Rifiutata**: `4000 0000 0000 0002`
- **Richiede 3D Secure**: `4000 0000 0000 3220`

---

## Avvio Applicazione

### Opzione 1: XAMPP (Windows)

```bash
# 1. Avvia XAMPP Control Panel
# 2. Start Apache
# 3. Start MySQL
# 4. Apri browser: http://localhost/Booking-APP-Luxury/
```

### Opzione 2: PHP Built-in Server (Sviluppo)

```bash
cd /path/to/Booking-APP-Luxury
php -S localhost:8000

# Apri: http://localhost:8000/
```

### Opzione 3: Apache (Linux)

```bash
# Copia files
sudo cp -r Booking-APP-Luxury /var/www/html/

# Permessi
sudo chown -R www-data:www-data /var/www/html/Booking-APP-Luxury
sudo chmod -R 755 /var/www/html/Booking-APP-Luxury

# Riavvia Apache
sudo systemctl restart apache2

# Apri: http://localhost/Booking-APP-Luxury/
```

---

## Test Funzionamento

### 1. Test Connessione Database

Crea `test-db.php`:
```php
<?php
require_once 'config.php';
echo $conn ? "DB OK" : "DB ERRORE: " . $conn->connect_error;
```

### 2. Test API

```bash
# Test date prenotate
curl http://localhost/Booking-APP-Luxury/api/bookings.php?action=booked-dates

# Output atteso:
# {"success":true,"dates":{...}}
```

### 3. Test Prenotazione Completa

1. Vai alla homepage
2. Clicca "Prenota Ora" su una camera
3. Compila form con dati validi
4. Invia prenotazione
5. Verifica nel pannello admin

### 4. Test Pagamento (Stripe)

1. Completa una prenotazione
2. Nella pagina pagamento, usa carta test: `4242 4242 4242 4242`
3. Verifica conferma pagamento
4. Controlla dashboard Stripe

---

## Deployment Produzione

### Checklist Pre-Deploy

- [ ] `DEBUG=false` nel .env
- [ ] Password database FORTE (non vuota!)
- [ ] Utente database dedicato (non root)
- [ ] HTTPS attivo (SSL certificato)
- [ ] `TRUSTED_PROXIES` configurato (se usi CDN/LB)
- [ ] Chiavi Stripe LIVE (non test)
- [ ] Webhook Stripe configurato
- [ ] Backup database automatizzato
- [ ] File .env NON accessibile dal web

### Deploy su Hosting Condiviso

1. **Carica files** via FTP/File Manager in `public_html/`
2. **Crea database** da cPanel > MySQL Databases
3. **Configura .env** con credenziali hosting
4. **Esegui installer**: `https://tuodominio.com/install.php`
5. **Attiva SSL**: cPanel > SSL/TLS o Let's Encrypt
6. **Testa** tutto il flusso

### Deploy su VPS

```bash
# 1. Installa LAMP stack
sudo apt update
sudo apt install apache2 php php-mysql php-curl mysql-server certbot

# 2. Configura MySQL
sudo mysql_secure_installation

# 3. Crea database e utente
sudo mysql -e "CREATE DATABASE luxury_hotel;"
sudo mysql -e "CREATE USER 'hotel_app'@'localhost' IDENTIFIED BY 'password';"
sudo mysql -e "GRANT ALL ON luxury_hotel.* TO 'hotel_app'@'localhost';"

# 4. Copia files
sudo cp -r Booking-APP-Luxury /var/www/html/
sudo chown -R www-data:www-data /var/www/html/Booking-APP-Luxury

# 5. Configura .env
sudo nano /var/www/html/Booking-APP-Luxury/.env

# 6. SSL con Let's Encrypt
sudo certbot --apache -d tuodominio.com

# 7. Importa database
mysql -u hotel_app -p luxury_hotel < database_setup.sql
```

---

## Troubleshooting

### Errore: "Connessione database fallita"

**Cause possibili**:
1. MySQL non avviato
2. Credenziali errate in .env
3. Database non esiste

**Soluzione**:
```bash
# Verifica MySQL
sudo systemctl status mysql

# Verifica credenziali
mysql -u hotel_app -p luxury_hotel

# Crea database se mancante
mysql -u root -p -e "CREATE DATABASE luxury_hotel;"
```

### Errore: "Rate limiting blocca tutti"

**Causa**: App dietro proxy ma `TRUSTED_PROXIES` non configurato

**Soluzione**:
1. Controlla i log per `[CRITICAL] PROXY_MISCONFIGURATION`
2. Configura `TRUSTED_PROXIES` nel .env con IP del proxy
3. Svuota tabella `login_attempts` per sbloccare temporaneamente

### Errore: "Stripe webhook fallisce"

**Cause possibili**:
1. URL webhook errato
2. `STRIPE_WEBHOOK_SECRET` errato
3. Firewall blocca richieste Stripe

**Soluzione**:
1. Verifica URL in Stripe Dashboard
2. Rigenera webhook secret e aggiorna .env
3. Apri porta 443 nel firewall

### Errore: "Permission denied" (Linux)

```bash
# Fix permessi
sudo chown -R www-data:www-data /var/www/html/Booking-APP-Luxury
sudo chmod -R 755 /var/www/html/Booking-APP-Luxury
sudo chmod 640 /var/www/html/Booking-APP-Luxury/.env
```

---

## Risorse Utili

- [PHP Documentation](https://www.php.net/manual/)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [Stripe Documentation](https://stripe.com/docs)
- [Apache Documentation](https://httpd.apache.org/docs/)
- [Cloudflare IP Ranges](https://www.cloudflare.com/ips/)

---

**Versione**: 4.0.0
**Ultimo Aggiornamento**: Marzo 2026
