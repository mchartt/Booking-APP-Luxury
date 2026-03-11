# Luxury Hotel - Sistema di Prenotazioni

Sistema completo per prenotazioni hotel con pagina pubblica, pagamenti Stripe e pannello admin.

---

## INDICE

1. [Cos'e questo progetto](#cose-questo-progetto)
2. [Requisiti](#requisiti)
3. [Installazione Rapida (5 minuti)](#installazione-rapida-5-minuti)
4. [Installazione Dettagliata](#installazione-dettagliata)
5. [Configurazione Ambiente (.env)](#configurazione-ambiente-env)
6. [Come Usare il Sistema](#come-usare-il-sistema)
7. [Mettere Online (Produzione)](#mettere-online-produzione)
8. [Sicurezza](#sicurezza)
9. [Risoluzione Problemi](#risoluzione-problemi)
10. [Struttura File](#struttura-file)

---

## COS'E QUESTO PROGETTO

Il progetto ha **4 parti principali**:

```
+------------------+     +------------------+     +------------------+
|  SITO PUBBLICO   |     |    PAGAMENTO     |     |  PANNELLO ADMIN  |
|   index.html     | --> |  payment.html    | --> |   admin.html     |
|                  |     |                  |     |                  |
| I clienti vedono |     | I clienti pagano |     | Tu gestisci le   |
| le camere e      |     | con carta/PayPal |     | prenotazioni     |
| prenotano        |     |                  |     |                  |
+------------------+     +------------------+     +------------------+
         |                       |                       |
         +-----------------------+-----------------------+
                                 |
                         +-------v-------+
                         |   BACKEND     |
                         |   api/*.php   |
                         |               |
                         | Parla con il  |
                         | database e    |
                         | gestisce tutto|
                         +---------------+
                                 |
                         +-------v-------+
                         |    MySQL      |
                         |   Database    |
                         +---------------+
```

---

## REQUISITI

### Per sviluppo locale (sul tuo PC)

Ti serve **XAMPP** (gratuito) che installa tutto insieme:
- Apache (server web)
- PHP 7.4+ (linguaggio backend)
- MySQL (database)

**Download**: https://www.apachefriends.org/download.html

### Per produzione (online)

Qualsiasi hosting con:
- PHP 7.4 o superiore
- MySQL 5.7 o superiore
- Supporto HTTPS (SSL)

---

## INSTALLAZIONE RAPIDA (5 minuti)

Se hai fretta, segui questi 5 passi:

### Passo 1: Installa XAMPP
```
1. Scarica XAMPP da https://www.apachefriends.org/download.html
2. Installa (clicca Avanti, Avanti, Fine)
3. Apri "XAMPP Control Panel"
4. Clicca START su "Apache" e "MySQL" (devono diventare VERDI)
```

### Passo 2: Copia i file
```
Copia TUTTA questa cartella in:
   C:\xampp\htdocs\

Risultato:
   C:\xampp\htdocs\Booking-APP-Luxury\
```

### Passo 3: Configura le credenziali
```
1. Trova il file ".env.example" nella cartella
2. COPIALO e rinomina la copia in ".env"
3. Aprilo con Blocco Note e modifica:

   DB_HOST=localhost
   DB_USER=root
   DB_PASS=
   DB_NAME=luxury_hotel
```

### Passo 4: Avvia l'installazione guidata
```
Apri il browser e vai su:
   http://localhost/Booking-APP-Luxury/install.php

Segui la procedura guidata (2 minuti).
L'installer crea automaticamente:
   - Il database
   - Le tabelle
   - L'utente admin iniziale
```

### Passo 5: Usa il sito!
```
- Sito pubblico: http://localhost/Booking-APP-Luxury/
- Pannello Admin: http://localhost/Booking-APP-Luxury/login.html
```

**FATTO!** Il sistema e pronto.

---

## INSTALLAZIONE DETTAGLIATA

Se l'installazione rapida non funziona o vuoi capire meglio, segui questa guida.

### Passo 1: Installa e Avvia XAMPP

1. **Scarica XAMPP**
   - Vai su https://www.apachefriends.org/download.html
   - Scarica la versione per Windows (circa 150MB)

2. **Installa XAMPP**
   - Apri il file scaricato
   - Se Windows chiede permessi, clicca "Si"
   - Clicca "Next" (Avanti) su tutte le schermate
   - Lascia la cartella di default `C:\xampp`
   - Clicca "Finish" alla fine

3. **Avvia i servizi**
   - Apri "XAMPP Control Panel" dal menu Start
   - Clicca il bottone **Start** accanto a **Apache**
   - Clicca il bottone **Start** accanto a **MySQL**
   - Entrambi devono diventare VERDI

   ```
   Se non diventano verdi:
   - Chiudi Skype (usa la stessa porta 80)
   - Disattiva IIS se presente
   - Controlla il firewall Windows
   ```

### Passo 2: Copia i File del Progetto

1. **Trova la cartella htdocs**
   ```
   C:\xampp\htdocs\
   ```

2. **Copia la cartella del progetto**
   - Copia TUTTA la cartella `Booking-APP-Luxury` dentro `htdocs`
   - Risultato finale:
   ```
   C:\xampp\htdocs\Booking-APP-Luxury\
       ├── index.html
       ├── admin.html
       ├── login.html
       ├── payment.html
       ├── config.php
       ├── install.php
       ├── .env.example
       └── api\
           ├── bookings.php
           ├── payments.php
           ├── auth.php
           └── admin.php
   ```

### Passo 3: Crea il File di Configurazione (.env)

1. **Trova il file `.env.example`** nella cartella del progetto

2. **Copia e rinomina**
   - Fai copia del file `.env.example`
   - Rinomina la copia in `.env` (senza .example)

3. **Modifica il file `.env`** con Blocco Note:
   ```ini
   # ========== DATABASE ==========
   DB_HOST=localhost
   DB_USER=root
   DB_PASS=
   DB_NAME=luxury_hotel

   # ========== DEBUG ==========
   # Metti "true" solo durante lo sviluppo, MAI in produzione!
   DEBUG=true
   ```

   > **NOTA**: Su XAMPP locale, utente e "root" e password e vuota.

### Passo 4: Esegui l'Installazione Automatica

1. **Apri il browser** (Chrome, Firefox, Edge)

2. **Vai all'installer**:
   ```
   http://localhost/Booking-APP-Luxury/install.php
   ```

3. **Segui la procedura guidata**:
   - L'installer verifica i requisiti
   - Crea il database `luxury_hotel`
   - Crea tutte le tabelle necessarie
   - Ti permette di creare il primo utente admin

4. **Al termine**, verrai reindirizzato al sito.

### Passo 5: Verifica che Tutto Funzioni

1. **Sito pubblico**:
   ```
   http://localhost/Booking-APP-Luxury/
   ```
   Dovresti vedere la homepage dell'hotel con le camere.

2. **Pannello Admin**:
   ```
   http://localhost/Booking-APP-Luxury/login.html
   ```
   Accedi con le credenziali create nell'installer.

3. **Test prenotazione**:
   - Vai alla homepage
   - Clicca "Prenota Ora" su una camera
   - Compila il form con dati di prova
   - Verifica che la prenotazione venga salvata

---

## CONFIGURAZIONE AMBIENTE (.env)

Il file `.env` contiene tutte le configurazioni sensibili. **Non committare mai questo file su Git!**

### Configurazioni Obbligatorie

```ini
# ========== DATABASE ==========
DB_HOST=localhost              # Indirizzo server MySQL
DB_USER=your_database_user     # Utente MySQL (NON usare root in produzione!)
DB_PASS=your_secure_password   # Password MySQL
DB_NAME=luxury_hotel           # Nome del database
DB_PORT=3306                   # Porta MySQL (default: 3306)
```

### Configurazioni Opzionali

```ini
# ========== EMAIL ==========
MAIL_FROM=info@luxuryhotel.it
MAIL_FROM_NAME=Luxury Hotel

# ========== DEBUG ==========
# ATTENZIONE: Metti "false" in produzione!
DEBUG=false
```

### Configurazioni di Sicurezza (Produzione)

```ini
# ========== PROXY / LOAD BALANCER ==========
# Se l'app e dietro un proxy o Load Balancer, configura gli IP fidati.
# Altrimenti il rate limiting non funzionera correttamente!
#
# Esempi:
# - Cloudflare: 173.245.48.0/20,103.21.244.0/22,103.22.200.0/22
# - AWS ELB: 10.0.0.0/8
# - Proxy locale: 127.0.0.1
#
# LASCIA VUOTO se non usi proxy (piu sicuro)
TRUSTED_PROXIES=

# ========== STRIPE (Pagamenti) ==========
# Ottieni le chiavi da: https://dashboard.stripe.com/apikeys
STRIPE_PUBLISHABLE_KEY=pk_test_xxx    # Sicura per frontend
STRIPE_SECRET_KEY=sk_test_xxx          # MAI esporre nel frontend!
STRIPE_WEBHOOK_SECRET=whsec_xxx        # Per validare webhook

# ========== WEBHOOK ==========
# Segreto per validare webhook dai payment provider
# Genera con: php -r "echo bin2hex(random_bytes(32));"
WEBHOOK_SECRET=
```

### Spiegazione TRUSTED_PROXIES

Quando l'applicazione e dietro un **Load Balancer** o **CDN** (come Cloudflare):

1. Tutte le richieste arrivano dall'IP del Load Balancer
2. L'IP reale del cliente e nell'header `X-Forwarded-For`
3. Senza `TRUSTED_PROXIES`, l'app vede tutti gli utenti con lo stesso IP
4. Questo **rompe il rate limiting** (protezione da attacchi)!

**Come configurare:**
```ini
# Se usi Cloudflare:
TRUSTED_PROXIES=173.245.48.0/20,103.21.244.0/22,103.22.200.0/22,103.31.4.0/22,141.101.64.0/18,108.162.192.0/18,190.93.240.0/20,188.114.96.0/20,197.234.240.0/22,198.41.128.0/17,162.158.0.0/15,104.16.0.0/13,104.24.0.0/14,172.64.0.0/13,131.0.72.0/22

# Se usi AWS ELB:
TRUSTED_PROXIES=10.0.0.0/8

# Se NON usi proxy (sviluppo locale):
TRUSTED_PROXIES=
```

---

## COME USARE IL SISTEMA

### Per i Clienti (Sito Pubblico)

1. Aprono il sito (`http://tuodominio.com/`)
2. Sfogliano le camere disponibili
3. Cliccano "Prenota Ora" sulla camera desiderata
4. Compilano il form con:
   - Date check-in e check-out
   - Numero ospiti
   - Nome, email, telefono
   - Richieste speciali (opzionale)
5. Cliccano "Conferma Prenotazione"
6. Vengono reindirizzati alla pagina di pagamento
7. Pagano con carta o PayPal
8. Ricevono conferma via email

### Per Te (Amministratore)

1. Vai su `http://tuodominio.com/login.html`
2. Accedi con email e password
3. Nel pannello admin puoi:
   - **Vedere** tutte le prenotazioni
   - **Filtrare** per stato (confermate, in attesa, cancellate)
   - **Confermare** i pagamenti manuali
   - **Cancellare** prenotazioni
   - **Vedere** statistiche e incassi
   - **Gestire** gli utenti admin

### Primo Accesso Admin

Se non hai ancora un account admin:

**Opzione 1: Usa l'installer**
```
http://localhost/Booking-APP-Luxury/install.php
```

**Opzione 2: Crea un Superadmin da terminale**
```bash
cd C:\xampp\htdocs\Booking-APP-Luxury
php create_superadmin.php
```

**Opzione 3: Registrati dal sito**
```
http://localhost/Booking-APP-Luxury/login.html
Clicca "Registrati" e crea un account.
Un admin esistente dovra approvare la richiesta.
```

---

## METTERE ONLINE (PRODUZIONE)

### Opzione 1: Hosting Condiviso (Facile, 3-10 euro/mese)

Servizi consigliati:
- **SiteGround** - https://www.siteground.com
- **Aruba** - https://www.aruba.it
- **Netsons** - https://www.netsons.com

**Passi:**

1. **Acquista l'hosting** con supporto PHP + MySQL

2. **Accedi al pannello** (cPanel o Plesk)

3. **Crea il database**:
   - Cerca "MySQL Database" nel pannello
   - Crea un database (es: `tuonome_hotel`)
   - Crea un utente database con password FORTE
   - Associa l'utente al database con tutti i permessi

4. **Carica i file**:
   - Usa il "File Manager" del pannello oppure
   - Usa FileZilla (client FTP gratuito)
   - Carica TUTTI i file nella cartella `public_html`

5. **Configura .env**:
   ```ini
   DB_HOST=localhost
   DB_USER=tuonome_utente
   DB_PASS=la_tua_password_forte
   DB_NAME=tuonome_hotel
   DEBUG=false
   ```

6. **Esegui l'installer**:
   ```
   https://tuodominio.com/install.php
   ```

7. **Attiva HTTPS**:
   - La maggior parte degli hosting offre SSL gratuito
   - Cerca "SSL" o "Let's Encrypt" nel pannello

8. **Configura Stripe** (se usi i pagamenti):
   - Vai su https://dashboard.stripe.com
   - Ottieni le chiavi API di PRODUZIONE (pk_live_*, sk_live_*)
   - Inseriscile nel file .env

### Opzione 2: VPS (Avanzato)

Se hai un VPS (DigitalOcean, Hetzner, OVH):

```bash
# Installa LAMP stack
sudo apt update
sudo apt install apache2 php php-mysql mysql-server

# Configura MySQL
sudo mysql_secure_installation

# Copia i file
sudo cp -r Booking-APP-Luxury /var/www/html/

# Imposta permessi
sudo chown -R www-data:www-data /var/www/html/Booking-APP-Luxury
sudo chmod -R 755 /var/www/html/Booking-APP-Luxury

# Configura Apache virtual host
sudo nano /etc/apache2/sites-available/hotel.conf

# Abilita mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2
```

---

## SICUREZZA

### Checklist Pre-Produzione

Prima di andare online, verifica:

- [ ] **DEBUG=false** nel file .env
- [ ] **Password database FORTE** (non lasciare vuota!)
- [ ] **HTTPS attivo** (certificato SSL)
- [ ] **File .env NON accessibile** dal web
- [ ] **Backup database** configurato
- [ ] **TRUSTED_PROXIES** configurato (se usi CDN/Load Balancer)

### Cosa Fare Subito

1. **Cambia la password admin**
   - Accedi al pannello admin
   - Vai su Impostazioni > Cambia Password

2. **Disabilita il DEBUG**
   ```ini
   # Nel file .env
   DEBUG=false
   ```

3. **Proteggi il file .env**

   Il file `.htaccess` incluso gia blocca l'accesso a `.env`.
   Verifica che funzioni:
   ```
   https://tuodominio.com/.env
   ```
   Deve dare errore 403 Forbidden, NON mostrare il contenuto!

4. **Configura TRUSTED_PROXIES** se necessario

   Se l'app e dietro Cloudflare o un Load Balancer, configura gli IP fidati.
   Altrimenti vedrai questo warning nei log:
   ```
   [CRITICAL] PROXY_MISCONFIGURATION - Header proxy rilevati...
   ```

### Rate Limiting

Il sistema include protezione contro attacchi brute-force:
- **5 tentativi** di login falliti = blocco temporaneo
- **100 richieste/minuto** per IP = blocco temporaneo

Se usi un proxy/CDN e NON configuri `TRUSTED_PROXIES`:
- Tutti gli utenti sembreranno avere lo stesso IP
- Il rate limiting blocchera utenti legittimi!

---

## RISOLUZIONE PROBLEMI

### "Pagina bianca" o Errore 500

1. **Verifica XAMPP**
   - Apache e MySQL devono essere VERDI nel pannello

2. **Controlla il file .env**
   - Esiste? E nella cartella giusta?
   - Le credenziali sono corrette?

3. **Guarda i log**
   ```
   C:\xampp\apache\logs\error.log
   C:\xampp\php\logs\php_error.log
   ```

### "Connessione al database fallita"

1. **MySQL e acceso?**
   - Controlla che sia VERDE in XAMPP

2. **Il database esiste?**
   - Apri http://localhost/phpmyadmin
   - Verifica che `luxury_hotel` sia nella lista

3. **Credenziali corrette?**
   ```ini
   # Su XAMPP locale:
   DB_USER=root
   DB_PASS=
   ```

### "Impossibile fare login admin"

1. **Hai creato un utente?**
   - Esegui `install.php` per creare il primo admin

2. **L'utente e approvato?**
   - In phpMyAdmin, verifica che `status` sia `active`

3. **Troppi tentativi falliti?**
   - Aspetta 15 minuti (rate limiting)
   - Oppure svuota la tabella `login_attempts`

### "Rate limiting blocca tutti"

Se tutti gli utenti vengono bloccati:

1. **Sei dietro un proxy/CDN?**
   - Configura `TRUSTED_PROXIES` nel file .env

2. **Controlla i log per questo warning:**
   ```
   [CRITICAL] PROXY_MISCONFIGURATION - Header proxy rilevati...
   ```

3. **Soluzione temporanea** (solo per debug):
   - In phpMyAdmin, svuota la tabella `login_attempts`

### "Stripe non funziona"

1. **Chiavi API corrette?**
   - Usa chiavi di TEST per sviluppo (`pk_test_*`, `sk_test_*`)
   - Usa chiavi LIVE per produzione (`pk_live_*`, `sk_live_*`)

2. **Webhook configurato?**
   - Vai su Stripe Dashboard > Developers > Webhooks
   - Aggiungi endpoint: `https://tuodominio.com/api/payments.php?action=webhook`

---

## STRUTTURA FILE

```
Booking-APP-Luxury/
│
├── PAGINE PUBBLICHE
│   ├── index.html          <- Homepage con camere
│   ├── payment.html        <- Pagina pagamento Stripe
│   ├── styles.css          <- Stili grafici
│   └── script.js           <- Logica prenotazione
│
├── PAGINE ADMIN
│   ├── login.html          <- Login amministratore
│   ├── login.js            <- Logica login
│   ├── login.css           <- Stili login
│   ├── admin.html          <- Pannello gestione
│   ├── admin.js            <- Logica pannello
│   └── admin.css           <- Stili pannello
│
├── BACKEND (API)
│   └── api/
│       ├── bookings.php    <- Gestisce prenotazioni
│       ├── payments.php    <- Gestisce pagamenti Stripe
│       ├── auth.php        <- Login/registrazione/sessioni
│       └── admin.php       <- Funzioni pannello admin
│
├── CONFIGURAZIONE
│   ├── config.php          <- Connessione DB + funzioni
│   ├── .env.example        <- Template configurazione
│   ├── .env                <- TUA configurazione (da creare)
│   └── .htaccess           <- Regole sicurezza Apache
│
├── INSTALLAZIONE
│   ├── install.php         <- Wizard installazione guidata
│   ├── create_superadmin.php <- Crea admin da terminale
│   └── database_setup.sql  <- Schema database
│
└── DOCUMENTAZIONE
    ├── README.md           <- Questo file
    ├── INSTALLA.txt        <- Guida rapida
    ├── SETUP_GUIDE.md      <- Guida dettagliata
    └── QUICK_REFERENCE.md  <- Riferimento tecnico
```

---

## PREZZI CAMERE

| Camera   | Prezzo/Notte | Ospiti Max | Servizi                    |
|----------|--------------|------------|----------------------------|
| Standard | 120 euro     | 2          | WiFi, TV, Bagno privato    |
| Deluxe   | 180 euro     | 3          | + Minibar, Vista giardino  |
| Suite    | 280 euro     | 4          | + Jacuzzi, Terrazza        |

---

## SUPPORTO

Per problemi tecnici:
1. Leggi la sezione "Risoluzione Problemi" sopra
2. Controlla i log di Apache/PHP
3. Apri una issue su GitHub

---

**Versione**: 4.0.0
**Stack**: PHP 7.4+ / MySQL 5.7+ / Stripe
**Ultimo aggiornamento**: Marzo 2026
