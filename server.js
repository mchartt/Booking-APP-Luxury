// ===== LUXURY HOTEL BACKEND =====
// Express.js server per gestire le prenotazioni

const express = require('express');
const cors = require('cors');
const fs = require('fs');
const path = require('path');
const { v4: uuidv4 } = require('uuid');

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Path per il file di database
const bookingsFile = path.join(__dirname, 'bookings.json');

// Funzione per leggere le prenotazioni dal file
function getBookings() {
    try {
        if (fs.existsSync(bookingsFile)) {
            const data = fs.readFileSync(bookingsFile, 'utf8');
            return JSON.parse(data);
        }
    } catch (error) {
        console.error('Errore nella lettura file bookings:', error);
    }
    return [];
}

// Funzione per salvare le prenotazioni nel file
function saveBookings(bookings) {
    try {
        fs.writeFileSync(bookingsFile, JSON.stringify(bookings, null, 2));
        return true;
    } catch (error) {
        console.error('Errore nel salvataggio file bookings:', error);
        return false;
    }
}

// ===== PREZZI CAMERE =====
const roomPrices = {
    'Standard': 120,
    'Deluxe': 180,
    'Suite': 280
};

// ===== ROUTES =====

// Health Check
app.get('/api/health', (req, res) => {
    res.json({ status: 'OK', message: 'Hotel Booking API is running' });
});

// GET - Ottieni tutte le prenotazioni
app.get('/api/bookings', (req, res) => {
    const bookings = getBookings();
    res.json({
        success: true,
        count: bookings.length,
        bookings: bookings
    });
});

// GET - Ottieni una prenotazione specifica
app.get('/api/bookings/:id', (req, res) => {
    const bookings = getBookings();
    const booking = bookings.find(b => b.id === req.params.id);

    if (!booking) {
        return res.status(404).json({
            success: false,
            message: 'Prenotazione non trovata'
        });
    }

    res.json({
        success: true,
        booking: booking
    });
});

// POST - Crea una nuova prenotazione
app.post('/api/bookings', (req, res) => {
    try {
        const { roomType, checkIn, checkOut, guests, name, email, phone, requests, totalPrice } = req.body;

        // Validazione dei dati
        if (!roomType || !checkIn || !checkOut || !guests || !name || !email || !phone) {
            return res.status(400).json({
                success: false,
                message: 'Campi obbligatori mancanti'
            });
        }

        // Validazione email
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            return res.status(400).json({
                success: false,
                message: 'Email non valida'
            });
        }

        // Validazione date
        const checkInDate = new Date(checkIn);
        const checkOutDate = new Date(checkOut);
        if (checkOutDate <= checkInDate) {
            return res.status(400).json({
                success: false,
                message: 'La data di check-out deve essere successiva al check-in'
            });
        }

        // Verifica tipo camera
        if (!roomPrices[roomType]) {
            return res.status(400).json({
                success: false,
                message: 'Tipo camera non valido'
            });
        }

        // Crea la prenotazione
        const booking = {
            id: uuidv4(),
            roomType,
            checkIn,
            checkOut,
            guests,
            name,
            email,
            phone,
            requests: requests || '',
            totalPrice: parseFloat(totalPrice),
            status: 'confirmed',
            createdAt: new Date().toISOString(),
            confirmationToken: Math.random().toString(36).substr(2, 9).toUpperCase()
        };

        // Salva la prenotazione
        const bookings = getBookings();
        bookings.push(booking);
        saveBookings(bookings);

        // Log
        console.log(`✓ Nuova prenotazione: ${booking.id} - ${name} - ${roomType}`);

        res.status(201).json({
            success: true,
            message: 'Prenotazione creata con successo',
            id: booking.id,
            confirmationToken: booking.confirmationToken
        });

    } catch (error) {
        console.error('Errore nella creazione prenotazione:', error);
        res.status(500).json({
            success: false,
            message: 'Errore nel server'
        });
    }
});

// PUT - Aggiorna una prenotazione
app.put('/api/bookings/:id', (req, res) => {
    try {
        const bookings = getBookings();
        const index = bookings.findIndex(b => b.id === req.params.id);

        if (index === -1) {
            return res.status(404).json({
                success: false,
                message: 'Prenotazione non trovata'
            });
        }

        // Aggiorna solo i campi forniti
        bookings[index] = {
            ...bookings[index],
            ...req.body,
            id: req.params.id, // Impedisci modifica ID
            createdAt: bookings[index].createdAt // Impedisci modifica createdAt
        };

        saveBookings(bookings);

        console.log(`✓ Prenotazione aggiornata: ${req.params.id}`);

        res.json({
            success: true,
            message: 'Prenotazione aggiornata con successo',
            booking: bookings[index]
        });

    } catch (error) {
        console.error('Errore nell\'aggiornamento prenotazione:', error);
        res.status(500).json({
            success: false,
            message: 'Errore nel server'
        });
    }
});

// DELETE - Cancella una prenotazione
app.delete('/api/bookings/:id', (req, res) => {
    try {
        let bookings = getBookings();
        const index = bookings.findIndex(b => b.id === req.params.id);

        if (index === -1) {
            return res.status(404).json({
                success: false,
                message: 'Prenotazione non trovata'
            });
        }

        const deleted = bookings.splice(index, 1);
        saveBookings(bookings);

        console.log(`✓ Prenotazione cancellata: ${req.params.id}`);

        res.json({
            success: true,
            message: 'Prenotazione cancellata con successo',
            booking: deleted[0]
        });

    } catch (error) {
        console.error('Errore nella cancellazione prenotazione:', error);
        res.status(500).json({
            success: false,
            message: 'Errore nel server'
        });
    }
});

// GET - Disponibilità camere per un periodo
app.get('/api/availability', (req, res) => {
    try {
        const { checkIn, checkOut } = req.query;

        if (!checkIn || !checkOut) {
            return res.status(400).json({
                success: false,
                message: 'checkIn e checkOut sono obbligatori'
            });
        }

        const bookings = getBookings();
        const checkInDate = new Date(checkIn);
        const checkOutDate = new Date(checkOut);

        // Verifica quale camere sono disponibili
        const availability = {};
        const roomTypes = Object.keys(roomPrices);

        roomTypes.forEach(roomType => {
            const bookedRooms = bookings.filter(b => {
                const bCheckIn = new Date(b.checkIn);
                const bCheckOut = new Date(b.checkOut);
                return b.roomType === roomType &&
                       b.status === 'confirmed' &&
                       !(checkOutDate <= bCheckIn || checkInDate >= bCheckOut);
            }).length;

            availability[roomType] = {
                available: true,
                price: roomPrices[roomType],
                booked: bookedRooms
            };
        });

        res.json({
            success: true,
            availability: availability
        });

    } catch (error) {
        console.error('Errore nel check disponibilità:', error);
        res.status(500).json({
            success: false,
            message: 'Errore nel server'
        });
    }
});

// GET - Statistiche
app.get('/api/stats', (req, res) => {
    try {
        const bookings = getBookings();

        const stats = {
            totalBookings: bookings.length,
            confirmedBookings: bookings.filter(b => b.status === 'confirmed').length,
            totalRevenue: bookings.reduce((sum, b) => sum + b.totalPrice, 0),
            roomsBooked: {
                Standard: bookings.filter(b => b.roomType === 'Standard').length,
                Deluxe: bookings.filter(b => b.roomType === 'Deluxe').length,
                Suite: bookings.filter(b => b.roomType === 'Suite').length
            }
        };

        res.json({
            success: true,
            stats: stats
        });

    } catch (error) {
        console.error('Errore nel recupero statistiche:', error);
        res.status(500).json({
            success: false,
            message: 'Errore nel server'
        });
    }
});

// 404 - Route non trovata
app.use((req, res) => {
    res.status(404).json({
        success: false,
        message: 'Route non trovata'
    });
});

// Error handler
app.use((err, req, res, next) => {
    console.error('Errore:', err);
    res.status(500).json({
        success: false,
        message: 'Errore interno del server'
    });
});

// Start server
app.listen(PORT, () => {
    console.log(`
╔════════════════════════════════════────────╗
║   🏨 LUXURY HOTEL BOOKING API v1.0        ║
║   Server running on: http://localhost:${PORT}  ║
║   Environment: ${process.env.NODE_ENV || 'development'}                ║
║   Database: ${bookingsFile}        ║
╚════════════════════════════════════════════╝
    `);
    console.log('Available endpoints:');
    console.log('  GET    /api/health              - Health check');
    console.log('  GET    /api/bookings            - Ottieni tutte le prenotazioni');
    console.log('  GET    /api/bookings/:id        - Ottieni una prenotazione');
    console.log('  POST   /api/bookings            - Crea una prenotazione');
    console.log('  PUT    /api/bookings/:id        - Aggiorna una prenotazione');
    console.log('  DELETE /api/bookings/:id        - Cancella una prenotazione');
    console.log('  GET    /api/availability        - Verifica disponibilità');
    console.log('  GET    /api/stats               - Statistiche');
});

module.exports = app;
