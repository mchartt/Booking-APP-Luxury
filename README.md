# Luxury Hotel - Sistema di Prenotazioni

Sistema completo per prenotazioni hotel con pagina pubblica, pagamenti e pannello admin.

---

## COME FUNZIONA (Spiegazione Semplice)

Il progetto ha **3 parti**:

| Parte | File | Cosa fa |
|-------|------|---------|
| **Sito pubblico** | `index.html` | I clienti vedono le camere e prenotano |
| **Pagamento** | `payment.html` | I clienti pagano la prenotazione |
| **Admin** | `admin.html` + `login.html` | Tu gestisci le prenotazioni |

Il **backend** (la parte che salva i dati) usa:
- **PHP** = il linguaggio che parla col database
- **MySQL** = il database dove si salvano prenotazioni e utenti

---

## REQUISITI (Cosa ti serve)

### Opzione A: XAMPP (Consigliato per principianti)

XAMPP ti installa tutto insieme: Apache (server web) + PHP + MySQL.

1. Scarica XAMPP da: https://www.apachefriends.org/download.html
2. Installa (clicca Avanti, Avanti, Fine)
3. Apri **XAMPP Control Panel**
4. Clicca **Start** su **Apache** e **MySQL**

Se vedi le lucette verdi, funziona!

### Opzione B: Hosting online

Se vuoi metterlo direttamente online, salta alla sezione "METTERE ONLINE".

---

## INSTALLAZIONE LOCALE (Sul tuo PC)

### Passo 1: Copia i file

Copia TUTTA la cartella `progetto-AI` dentro:
```
C:\xampp\htdocs\
```

Quindi avrai:
```
C:\xampp\htdocs\progetto-AI\
    ├── index.html
    ├── admin.html
    ├── api/
    │   ├── bookings.php
    │   ├── admin.php
    │   ├── auth.php
    │   └── payments.php
    ├── config.php
    └── ... altri file
```

### Passo 2: Crea il database

1. Apri il browser e vai su: **http://localhost/phpmyadmin**
2. Clicca **"Nuovo"** nella colonna sinistra
3. Scrivi come nome: `luxury_hotel`
4. Clicca **"Crea"**
5. Clicca sulla tab **"SQL"** in alto
6. Copia e incolla questo codice:

```sql
-- Tabella prenotazioni
CREATE TABLE prenotazioni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id VARCHAR(50) UNIQUE NOT NULL,
    room_type ENUM('Standard', 'Deluxe', 'Suite') NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    guests INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    notes TEXT,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    payment_status ENUM('pending', 'processing', 'completed', 'failed', 'pending_transfer', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(20),
    transaction_id VARCHAR(100),
    paid_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabella admin (per il login)
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME
);

-- Tabella tentativi login (sicurezza)
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success TINYINT(1) DEFAULT 0
);

-- Crea primo admin (password: Admin123!)
INSERT INTO admin_users (username, email, password, status) VALUES
('admin', 'admin@hotel.com', '$2y$10$YourHashedPasswordHere', 'approved');
```

7. Clicca **"Esegui"**

### Passo 3: Configura la connessione

1. Nella cartella del progetto, trova il file `.env.example`
2. **Copialo** e rinomina la copia in `.env`
3. Aprilo con Blocco Note e modifica:

```env
DB_HOST=localhost
DB_NAME=luxury_hotel
DB_USER=root
DB_PASS=
DEBUG=false
```

> **Nota**: Su XAMPP, l'utente e `root` e la password e vuota.

### Passo 4: Prova!

1. Assicurati che XAMPP abbia Apache e MySQL accesi (lucette verdi)
2. Apri il browser
3. Vai su: **http://localhost/progetto-AI/**

Dovresti vedere il sito dell'hotel!

---

## COME USARE

### Per i CLIENTI (Sito pubblico)

1. Aprono `http://tuosito.com/` (o `localhost/progetto-AI/`)
2. Scelgono una camera cliccando "Prenota Ora"
3. Compilano il form con date e dati
4. Cliccano "Conferma Prenotazione"
5. Vengono mandati alla pagina di pagamento
6. Pagano e ricevono conferma

### Per TE (Admin)

1. Vai su `http://localhost/progetto-AI/login.html`
2. **Prima volta?** Clicca "Registrati" e crea un account
3. Accedi con email e password
4. Nel pannello admin puoi:
   - Vedere tutte le prenotazioni
   - Confermare i pagamenti
   - Cancellare prenotazioni
   - Vedere statistiche

---

## METTERE ONLINE (Hosting)

### Opzione 1: Hosting condiviso (Facile e economico)

Servizi consigliati (costano 3-10 euro/mese):
- **SiteGround** - https://www.siteground.com
- **Aruba** - https://www.aruba.it
- **Netsons** - https://www.netsons.com

**Passi:**

1. **Compra l'hosting** con supporto PHP + MySQL
2. **Accedi al pannello** (cPanel o Plesk)
3. **Crea il database**:
   - Cerca "MySQL Database" nel pannello
   - Crea un database (es: `tuonome_hotel`)
   - Crea un utente database con password
   - Associa l'utente al database
4. **Importa le tabelle**:
   - Apri phpMyAdmin dal pannello
   - Seleziona il database
   - Vai su "Importa" o "SQL"
   - Incolla il codice SQL del Passo 2
5. **Carica i file**:
   - Usa il "File Manager" del pannello OPPURE
   - Usa FileZilla (programma FTP gratuito)
   - Carica TUTTI i file nella cartella `public_html`
6. **Configura .env**:
   - Modifica il file `.env` con i dati del tuo hosting:
   ```env
   DB_HOST=localhost
   DB_NAME=tuonome_hotel
   DB_USER=tuonome_utente
   DB_PASS=latuapassword
   DEBUG=false
   ```
7. **Fatto!** Vai su `https://tuodominio.com`

### Opzione 2: VPS (Avanzato)

Se hai un VPS (DigitalOcean, Hetzner, OVH):

```bash
# Installa LAMP stack
sudo apt update
sudo apt install apache2 php php-mysql mysql-server

# Copia i file in /var/www/html/
# Configura il database
# Configura Apache virtual host
```

---

## STRUTTURA FILE

```
progetto-AI/
│
├── PAGINE PUBBLICHE
│   ├── index.html        <- Homepage con camere e form prenotazione
│   ├── payment.html      <- Pagina pagamento
│   ├── styles.css        <- Tutti gli stili grafici
│   └── script.js         <- Logica prenotazione
│
├── PAGINE ADMIN
│   ├── login.html        <- Login amministratore
│   ├── login.js          <- Logica login
│   ├── login.css         <- Stili login
│   ├── admin.html        <- Pannello gestione
│   ├── admin.js          <- Logica pannello
│   └── admin.css         <- Stili pannello
│
├── BACKEND (API)
│   └── api/
│       ├── bookings.php  <- Gestisce prenotazioni
│       ├── payments.php  <- Gestisce pagamenti
│       ├── auth.php      <- Gestisce login/registrazione
│       ├── admin.php     <- Gestisce funzioni admin
│       └── security_headers.php <- Sicurezza
│
├── CONFIGURAZIONE
│   ├── config.php        <- Connessione database
│   ├── .env.example      <- Template configurazione
│   ├── .env              <- TUA configurazione (da creare)
│   └── .htaccess         <- Regole server Apache
│
└── README.md             <- Questo file!
```

---

## PREZZI CAMERE

| Camera | Prezzo/Notte | Ospiti Max |
|--------|--------------|------------|
| Standard | 120 euro | 2 |
| Deluxe | 180 euro | 3 |
| Suite | 280 euro | 4 |

---

## RISOLUZIONE PROBLEMI

### "Pagina bianca" o errore 500
- Controlla che Apache e MySQL siano accesi in XAMPP
- Controlla il file `.env` (credenziali database corrette?)
- Guarda il file di log: `C:\xampp\apache\logs\error.log`

### "Connessione al database fallita"
- Il database `luxury_hotel` esiste?
- Le credenziali in `.env` sono giuste?
- MySQL e acceso in XAMPP?

### "Impossibile fare login admin"
- Hai creato un utente admin nel database?
- Lo status dell'utente e 'approved'?

### Il sito non si vede online
- I file sono nella cartella giusta (`public_html`)?
- Il file `.env` ha le credenziali dell'hosting?
- PHP e abilitato sull'hosting?

---

## SICUREZZA (Importante!)

Prima di andare online:

1. **Cambia la password admin** nel database
2. **Non lasciare DEBUG=true** nel file `.env`
3. **Usa HTTPS** (la maggior parte degli hosting lo offre gratis)
4. **Backup regolari** del database

---

## CONTATTI E SUPPORTO

Per problemi tecnici o domande sul codice, apri una issue su GitHub o contatta lo sviluppatore.

---

**Versione**: 3.0.0 (PHP/MySQL Backend)
**Ultimo aggiornamento**: Marzo 2026
