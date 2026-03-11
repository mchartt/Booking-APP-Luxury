# 🏨 Luxury Hotel - Sistema di Prenotazioni Online

Sistema moderno e responsive di prenotazione hotel con frontend HTML/CSS/JS e backend PHP/MySQL.

## ✨ Caratteristiche

### Frontend
- **Design Moderno Minimalista**: Palette beige/marrone elegante con colori solidi
- **Google Fonts**: Playfair Display (titoli), Lora (corpo testo), Poppins (UI)
- **Immagini Reali**: Immagini lussuose da Unsplash
- **Animazioni Fluide**: Transizioni smooth con cubic-bezier
- **Icone Font Awesome 6.4.0**: Sostituzione completa degli emoji
- **Responsive**: Perfettamente ottimizzato per tutti i device
  - 320px - 374px: Extra Small Mobile
  - 375px - 599px: Small Mobile
  - 600px - 768px: Mobile Landscape
  - 769px - 1023px: Tablet Portrait
  - 1024px - 1439px: Tablet Landscape
  - 1440px+: Desktop
- **JavaScript Avanzato**:
  - Calcolatore prezzo interattivo in tempo reale
  - Validazione form client-side
  - Disabilitazione automatica date prenotate
  - Animazioni numeri nel price summary
  - Scroll smooth tra sezioni
  - Intersection Observer per animazioni al scroll
  - Sync date picker con backend

### Backend
- **PHP Backend**: Server RESTful con PHP 7.4+
- **MySQL Database**: Storage robusto e scalabile
- **Validation API**: Controlli completi su:
  - Formato email e dati
  - Logica date (checkout > checkin, max 30 giorni)
  - Capacità camere (Standard: 2, Deluxe: 3, Suite: 4)
  - Disponibilità camere (no overbooking)
- **REST Endpoints**: CRUD operations complete
- **Disabled Dates**: Mostra date già prenotate al frontend
- **Email Notifications**: Sistema confirmation email pronto
- **CORS Enabled**: Pronto per produzione

## 🚀 Quick Start

### 1. Configurazione Database

#### Prerequisiti
- PHP 7.4+ con extensione MySQLi
- MySQL Server 5.7+
- Apache/Nginx con support PHP

#### Setup Database

```bash
# 1. Apri MySQL e crea il database
mysql -u root -p

# 2. Importa lo schema
mysql -u root -p luxury_hotel < database-setup.sql
```

Oppure, copia e incolla il contenuto di `database-setup.sql` direttamente in MySQL Workbench.

### 2. Configurazione Credenziali

```bash
# 1. Copia il file di configurazione
cp .env.example .env

# 2. Modifica .env con le tue credenziali MySQL
nano .env
# Oppure usa il tuo editor preferito
```

### 3. Avvio Frontend

Apri `index.html` nel browser (o usa Live Server extension in VS Code)

Le API PHP funzioneranno automaticamente a `./api/bookings.php`

## 📂 Struttura File

```
progetto-AI/
├── index.html              # Frontend HTML (minimalista)
├── styles.css              # CSS responsive, no gradients
├── script.js               # JavaScript con PHP API integration
├── config.php              # Database config e validation functions
├── api/
│   └── bookings.php        # REST API endpoints
├── database-setup.sql      # Schema MySQL
├── .env.example            # Template configurazione
├── .gitignore              # Git ignore file
├── COLORS_AND_FONTS.md     # Design system
├── README.md               # Questo file
└── setup.bat               # Setup script Windows
```

## 🔌 API Endpoints

### Ottenere Date Prenotate
```
GET /api/bookings.php?action=booked-dates
GET /api/bookings.php?action=booked-dates&room_type=Standard
```

Response:
```json
{
  "success": true,
  "dates": {
    "Standard": [
      {"start": "2026-03-15", "end": "2026-03-18"},
      {"start": "2026-03-22", "end": "2026-03-25"}
    ],
    "Deluxe": [...],
    "Suite": [...]
  }
}
```

### Verificare Disponibilità
```
GET /api/bookings.php?action=availability&room_type=Standard&check_in=2026-03-15&check_out=2026-03-18
```

Response:
```json
{
  "success": true,
  "available": true,
  "room_type": "Standard",
  "check_in": "2026-03-15",
  "check_out": "2026-03-18"
}
```

### Creare Prenotazione
```
POST /api/bookings.php
Content-Type: application/json

{
  "roomType": "Deluxe",
  "checkIn": "2026-03-15",
  "checkOut": "2026-03-18",
  "guests": "2",
  "name": "Mario Rossi",
  "email": "mario@example.com",
  "phone": "+39 123 456 7890",
  "requests": "Letto matrimoniale",
  "totalPrice": "540"
}
```

Response Success:
```json
{
  "success": true,
  "booking_id": "BK1710133056_a1b2c3d4e5f6",
  "message": "Prenotazione creata con successo"
}
```

Response Error (Validation):
```json
{
  "success": false,
  "errors": [
    "La data di check-out deve essere dopo il check-in",
    "Camera non disponibile per le date selezionate"
  ],
  "message": "Prenotazione non valida"
}
```

## 🎨 Colori e Design

### Palette Colori (Beige/Marrone)
- **Primary**: #8B6F47 (Marrone Elegante)
- **Secondary**: #C9A876 (Beige Marrone)
- **Accent**: #D4C4B8 (Beige Chiaro)
- **Dark**: #4A3728 (Marrone Profondo)
- **Light**: #F9F5F0 (Panna)
- **Text**: #5a5a5a (Testo standard)

### Shadow System
```css
--shadow-sm: 0 2px 8px rgba(74, 55, 40, 0.08);
--shadow-md: 0 8px 24px rgba(74, 55, 40, 0.12);
--shadow-lg: 0 16px 48px rgba(74, 55, 40, 0.15);
--shadow-xl: 0 24px 56px rgba(139, 111, 71, 0.2);
```

## 🔒 Sicurezza

### Validazioni Implementate (Server-side)
- ✓ Sanitizzazione input con `mysqli_real_escape_string()`
- ✓ Validazione formato email regex
- ✓ Validazione telefono (minimo 10 caratteri)
- ✓ Validazione date (formato Y-m-d, checkout > checkin)
- ✓ Validazione capacità camere
- ✓ Verifica disponibilità con query overlap detection
- ✓ Prepared statements per query SQL
- ✓ CORS headers controllati
- ✓ Errori descrittivi agli utenti

### Validazioni Implementate (Client-side)
- ✓ Regex email
- ✓ Data logic (checkout > checkin)
- ✓ Array errori dal server mostrati all'utente
- ✓ Disabilitazione date prenotate nel date picker

## 📊 Struttura Database

### Tabella: prenotazioni
```sql
- id (PRIMARY KEY, AUTO_INCREMENT)
- booking_id (UNIQUE VARCHAR) - ID prenotazione univoco
- room_type (VARCHAR) - Tipo di camera
- check_in (DATE) - Data check-in
- check_out (DATE) - Data check-out
- guests (INT) - Numero ospiti
- name (VARCHAR) - Nome completo
- email (VARCHAR) - Email ospite
- phone (VARCHAR) - Telefono
- requests (TEXT) - Richieste speciali
- nights (INT) - Numero notti
- price_per_night (DECIMAL) - Prezzo per notte
- total_price (DECIMAL) - Prezzo totale
- status (ENUM) - pending/confirmed/cancelled
- payment_status (ENUM) - unpaid/paid
- created_at (TIMESTAMP) - Data creazione
- updated_at (TIMESTAMP) - Data aggiornamento

Indici: booking_id, email, room_type, check_in, check_out, status, composite (room_type, check_in, check_out)
```

### Tabella: rooms
```sql
- id (PRIMARY KEY)
- type (VARCHAR UNIQUE) - Tipo: Standard/Deluxe/Suite
- max_guests (INT) - Capacità massima
- price_per_night (DECIMAL) - Prezzo per notte
- description (TEXT) - Descrizione
- amenities (JSON) - Servizi (WiFi, TV, Bagno privato, etc.)
- is_active (BOOLEAN) - Camera attiva?
```

### Tabella: users
```sql
- id (PRIMARY KEY)
- username (UNIQUE VARCHAR)
- password (VARCHAR) - Hash bcrypt
- email (UNIQUE VARCHAR)
- role (ENUM) - admin/staff
- is_active (BOOLEAN)
- created_at (TIMESTAMP)
```

### Tabella: logs
```sql
- id (PRIMARY KEY)
- user_id (INT) - FK users.id
- action (VARCHAR) - Azione eseguita
- details (TEXT) - Dettagli azione
- ip_address (VARCHAR)
- created_at (TIMESTAMP)
```

## 🛠️ Troubleshooting

### "Errore nella connessione al database"
1. Verifica che MySQL sia avviato: `systemctl status mysql`
2. Controlla credenziali in `.env`
3. Assicurati che il database `luxury_hotel` esista

### "Date prenotate non si caricano"
1. Verifica che `api/bookings.php` sia accessibile
2. Controlla la console del browser (F12) per errori CORS
3. Verifica che le date siano nel formato corretto: YYYY-MM-DD

### "Prenotazione fallisce con errori di validazione"
1. Controlla i messaggi di errore nella notifica
2. Verifica che i dati siano nel formato corretto
3. Assicurati che checkout sia dopo checkin
4. Verifica che la camera non sia già prenotata

### Porta già in uso / File locked
Se hai problemi con file locked, riavvia il server PHP:
```bash
# Se usi Apache
sudo systemctl restart apache2

# Se usi Nginx
sudo systemctl restart nginx

# Se usi PHP built-in server
# Semplicemente chiudi e riapri il file o ricarica il browser
```

## 📱 Mobile Optimization

- ✓ Font 16px nei form (evita zoom involontario)
- ✓ Touch-friendly buttons (minimo 44x44px)
- ✓ Viewport meta tag
- ✓ Media queries per tutti i breakpoint
- ✓ Hamburger menu responsive
- ✓ Swipe-friendly date pickers

## 🚀 Deployment

### Frontend (Static)
- Compatible con Netlify, Vercel, GitHub Pages
- Nessuna build required
- Solo copy/paste i file

### Backend (PHP)
- Richiede hosting con PHP 7.4+ e MySQL
- Suggeriti: Bluehost, HostGator, SiteGround, Kinsta
- Oppure VPS: DigitalOcean, Linode, AWS

### Steps Deployment
1. Upload tutti i file al server web
2. Importa `database-setup.sql` su MySQL hosting
3. Aggiorna `.env` con credenziali MySQL hosting
4. Testa gli API endpoints visitando: `yoursite.com/api/bookings.php`

## 📜 Licenza

MIT License - Usa liberamente per qualsiasi progetto!

---

**Versione**: 2.0.0 (PHP/MySQL Backend)
**Ultima modifica**: 2026-03-11
**Status**: ✅ Production Ready

## ✨ Caratteristiche

### Frontend
- **Design Moderno**: Interfaccia elegante con gradients e shadow sofisticate
- **Immagini Reali**: Immagini delle camere da Unsplash (online)
- **Animazioni Fluide**: Transizioni e effetti di scroll
- **Icone Font Awesome**: Sostituzione complete degli emoji con icone professionali
- **Responsive**: Perfettamente ottimizzato per mobile, tablet e desktop
  - 320px e superiore (tutti i device)
  - Breakpoint specifici per varie risoluzioni
  - Touch-friendly (area di click minima 44x44px)
- **JavaScript Avanzato**:
  - Calcolatore prezzo interattivo
  - Validazione form in tempo reale
  - Animazioni al numero (numero animato)
  - Scroll smooth
  - Intersection Observer per animazioni scroll
  - Salvataggio locale (localStorage) come fallback

### Backend
- **Express.js API**: Server RESTful completo
- **Gestione Prenotazioni**: CRUD operations
- **Validazione Dati**: Controlli su email, date, campi obbligatori
- **Database JSON**: Storage semplice e portabile
- **Statistiche**: Endpoint per gestire i report
- **Disponibilità**: Check real-time della disponibilità camere
- **CORS Enabled**: Pronto per produzione

## 🚀 Quick Start

### 1. Installazione Dipendenze Backend

```bash
npm install
```

Installa:
- express (server web)
- cors (gestione richieste cross-origin)
- uuid (generazione ID unici)
- nodemon (dev dependency - auto-reload)

### 2. Avvio Backend

```bash
npm start
```

Il server partirà su `http://localhost:3000`

Oppure in modalità sviluppo (auto-restart):
```bash
npm run dev
```

### 3. Aprire il Frontend

Aprire `index.html` nel browser (oppure usare Live Server)

## 📱 Responsive Design

Il sito si adatta perfettamente a:

| Device | Larghezza | Breakpoint |
|--------|-----------|-----------|
| Extra Small Mobile | 320px - 374px | Ottimizzato |
| Small Mobile | 375px - 599px | Ottimizzato |
| Mobile Landscape | 600px - 768px | Ottimizzato |
| Tablet Portrait | 769px - 1023px | 2 colonne |
| Tablet Landscape | 1024px - 1439px | 3 colonne |
| Desktop | 1440px+ | Full layout |

### Miglioramenti Responsive:
- ✓ Font scaling automatico
- ✓ Padding/margin adattativo
- ✓ Grid layout flessibile
- ✓ Bottoni touch-friendly (min 44x44px)
- ✓ Navigazione mobile con hamburger menu
- ✓ Form ottimizzati per mobile (font 16px per evitare zoom)
- ✓ Immagini responsive

## 🔌 API Endpoints

### Health Check
```
GET /api/health
```

### Prenotazioni
```
GET    /api/bookings              - Lista tutte le prenotazioni
POST   /api/bookings              - Crea nuova prenotazione
GET    /api/bookings/:id          - Ottieni prenotazione
PUT    /api/bookings/:id          - Aggiorna prenotazione
DELETE /api/bookings/:id          - Cancella prenotazione
```

### Disponibilità
```
GET /api/availability?checkIn=2026-03-15&checkOut=2026-03-18
```

### Statistiche
```
GET /api/stats
```

## 📝 Esempio Request

### Creare una Prenotazione

```bash
curl -X POST http://localhost:3000/api/bookings \
  -H "Content-Type: application/json" \
  -d '{
    "roomType": "Deluxe",
    "checkIn": "2026-03-15",
    "checkOut": "2026-03-18",
    "guests": "2",
    "name": "Mario Rossi",
    "email": "mario@example.com",
    "phone": "+39 3201234567",
    "requests": "Letto matrimoniale, vista panoramica",
    "totalPrice": "540"
  }'
```

### Response
```json
{
  "success": true,
  "message": "Prenotazione creata con successo",
  "id": "a1b2c3d4-e5f6-7890-1234-567890abcdef",
  "confirmationToken": "ABC123XYZ"
}
```

## 📂 Struttura File

```
progetto-AI/
├── index.html              # Frontend HTML
├── styles.css              # CSS responsive
├── script.js               # JavaScript con API integration
├── server.js               # Backend Express
├── package.json            # Dipendenze NPM
├── bookings.json           # Database prenotazioni (auto-generato)
└── README.md               # Questo file
```

## 🎨 Colori e Design

### Palette Colori
- **Primary**: #667eea (Blu Reale)
- **Secondary**: #764ba2 (Viola)
- **Accent**: #f5576c (Rosa)
- **Dark**: #1a1a1a (Nero profondo)
- **Light**: #f8f9fa (Bianco leggero)

### Shadow System
- **Shadow SM**: 0 2px 8px rgba(0,0,0,0.08)
- **Shadow MD**: 0 8px 24px rgba(0,0,0,0.12)
- **Shadow LG**: 0 16px 48px rgba(0,0,0,0.15)
- **Shadow XL**: 0 24px 56px rgba(102,126,234,0.2)

## 🔑 Funzionalità JavaScript

### Seleziona Camera
Clicca su "Seleziona" per auto-popolare il form e scrollare automaticamente

### Calcolo Prezzo Dinamico
Il prezzo si aggiorna in tempo reale quando cambi:
- Tipo di camera
- Data check-in
- Data check-out

### Validazione Form
- Email valida
- Date corrette (checkout > checkin)
- Telefono (solo numeri e caratteri validi)
- Tutti i campi obbligatori

### Salvataggio Locale
Se il backend non è disponibile, le prenotazioni vengono salvate in `localStorage`

### Animazioni
- Scroll smooth tra sezioni
- Animazioni numeri nel price summary
- Fade in al scroll (Intersection Observer)
- Hover effects su card e bottoni

## 🛠️ Configurazione Backend

### CSV di Prenotazione

Ogni prenotazione contiene:
```javascript
{
  "id": "uuid",
  "roomType": "Standard|Deluxe|Suite",
  "checkIn": "YYYY-MM-DD",
  "checkOut": "YYYY-MM-DD",
  "guests": "1|2|3|4",
  "name": "Nome Ospite",
  "email": "email@example.com",
  "phone": "+39...",
  "requests": "Note speciali",
  "totalPrice": 120,
  "status": "confirmed",
  "createdAt": "ISO timestamp",
  "confirmationToken": "ABC123XYZ"
}
```

### Prezzi Camere (Configurabili in server.js)
- **Standard**: €120/notte
- **Deluxe**: €180/notte
- **Suite**: €280/notte

## 📊 Statistiche API

```json
{
  "success": true,
  "stats": {
    "totalBookings": 42,
    "confirmedBookings": 42,
    "totalRevenue": 8540,
    "roomsBooked": {
      "Standard": 15,
      "Deluxe": 18,
      "Suite": 9
    }
  }
}
```

## 🔒 Sicurezza

### Validazioni Implementate
- ✓ Email regex
- ✓ Validazione date (checkout > checkin)
- ✓ Tipo camera verificato
- ✓ UUID per ID unici
- ✓ CORS abilitato
- ✓ Input sanitizing

### Best Practice
- Numeri di telefono validati
- Trim automatico input
- Errori descrittivi
- Logging richieste

## 🐛 Troubleshooting

### "Backend non disponibile"
Non è un errore! Il sito salva la prenotazione in localStorage e continua a funzionare normalmente.

### Porta 3000 già in uso
```bash
lsof -i :3000  # Trova il processo
kill -9 <PID>  # Uccidi il processo
```

### File bookings.json non creato
Crea manualmente:
```json
[]
```

### CORS Error
Assicurati che il backend stia usando la stessa porta e che CORS sia abilitato in server.js

## 🚀 Deployment

### Frontend
- Compatibile con qualsiasi web server (Apache, Nginx, Netlify, Vercel)
- Solo HTML/CSS/JS static

### Backend
- Hosted su Heroku, Railway, Render, DigitalOcean
- Usa variabili d'ambiente per PORT
- Database JSON portabile

## 📞 Supporto

Per problemi o domande, controlla:
1. Console del browser (F12)
2. Network tab per API calls
3. Log del backend

## 📜 Licenza

MIT License - Usa liberamente per qualsiasi progetto!

---

**Versione**: 1.0.0
**Ultima modifica**: 2026-03-11
**Status**: ✅ Production Ready
