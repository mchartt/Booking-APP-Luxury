// ========== CONFIGURAZIONE ==========
const API_BASE_URL = './api';

// Variabili globali per gestire date prenotate
let bookedDates = {};
let selectedRoomType = null;

// ========== FUNZIONI AUSILIARIE DATE ==========
/**
 * Ottiene le date prenotate dal server PHP
 */
async function fetchBookedDates() {
    try {
        const response = await fetch(`${API_BASE_URL}/bookings.php?action=booked-dates`);
        if (response.ok) {
            const data = await response.json();
            if (data.success && data.dates) {
                bookedDates = data.dates;
                updateDatePickerDisabledDates();
            }
        }
    } catch (error) {
        console.warn('Errore nel caricamento delle date prenotate:', error);
    }
}

/**
 * Converte un oggetto di date in un array di stringhe ISO
 */
function getDisabledDatesArray() {
    const disabledArray = [];
    const roomType = document.getElementById('roomType').value;

    if (roomType && bookedDates[roomType]) {
        // bookedDates[roomType] è un array di range come [{start: '2026-03-15', end: '2026-03-18'}, ...]
        bookedDates[roomType].forEach(range => {
            const start = new Date(range.start);
            const end = new Date(range.end);
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                disabledArray.push(d.toISOString().split('T')[0]);
            }
        });
    }
    return disabledArray;
}

/**
 * Aggiorna gli attributi dei date picker per disabilitare date prenotate
 */
function updateDatePickerDisabledDates() {
    const checkInInput = document.getElementById('checkIn');
    const checkOutInput = document.getElementById('checkOut');
    const disabledDates = getDisabledDatesArray();

    // Salva le date disabilitate come attributo data
    checkInInput.dataset.disabledDates = JSON.stringify(disabledDates);
    checkOutInput.dataset.disabledDates = JSON.stringify(disabledDates);

    // Validazione al cambio di data
    if (checkInInput) {
        checkInInput.addEventListener('input', validateDateNotBooked);
    }
    if (checkOutInput) {
        checkOutInput.addEventListener('input', validateDateNotBooked);
    }
}

/**
 * Valida che la data selezionata non sia prenotata
 */
function validateDateNotBooked(e) {
    const disabledDates = JSON.parse(this.dataset.disabledDates || '[]');
    const selectedDate = this.value;

    if (selectedDate && disabledDates.includes(selectedDate)) {
        showNotification('Questa data è già prenotata. Scegli un\'altra data.', 'error');
        this.value = '';
        resetPriceSummary();
    }
}

// ========== GESTIONE MENU MOBILE ==========
const menuToggle = document.getElementById('menuToggle');
const nav = document.querySelector('.nav');

if (menuToggle) {
    menuToggle.addEventListener('click', () => {
        nav.classList.toggle('active');
    });
}

document.querySelectorAll('.nav a').forEach(link => {
    link.addEventListener('click', () => {
        nav.classList.remove('active');
    });
});

// ========== FUNZIONI CAMERE ==========
function selectRoom(roomType, price) {
    const roomTypeSelect = document.getElementById('roomType');
    roomTypeSelect.value = `${roomType}`;
    selectedRoomType = roomType;

    // Scroll con effetto
    scrollToSection('booking');
    updatePriceCalculation();
    updateDatePickerDisabledDates();

    // Animazione di highlights
    roomTypeSelect.style.animation = 'pulse 0.6s ease-out';
}

// ========== CALCOLO PREZZO ==========
function updatePriceCalculation() {
    const checkInInput = document.getElementById('checkIn').value;
    const checkOutInput = document.getElementById('checkOut').value;
    const roomTypeSelect = document.getElementById('roomType');

    if (!checkInInput || !checkOutInput || !roomTypeSelect.value) {
        resetPriceSummary();
        return;
    }

    const checkIn = new Date(checkInInput);
    const checkOut = new Date(checkOutInput);

    // Validazione date
    if (checkOut <= checkIn) {
        resetPriceSummary();
        return;
    }

    // Calcola notti
    const timeDiff = checkOut - checkIn;
    const nights = Math.ceil(timeDiff / (1000 * 60 * 60 * 24));

    // Estrai prezzo
    const selectedOption = roomTypeSelect.options[roomTypeSelect.selectedIndex];
    const priceMatch = selectedOption.text.match(/€(\d+)/);
    const pricePerNight = priceMatch ? parseInt(priceMatch[1]) : 0;

    // Calcola totale
    const totalPrice = nights * pricePerNight;

    // Aggiorna UI con animazione
    animateNumberChange('nightsCount', nights);
    animateNumberChange('pricePerNight', pricePerNight, '€');
    animateNumberChange('totalPrice', totalPrice, '€');
}

function resetPriceSummary() {
    document.getElementById('nightsCount').textContent = '0';
    document.getElementById('pricePerNight').textContent = '€0';
    document.getElementById('totalPrice').textContent = '€0';
}

function animateNumberChange(elementId, newValue, prefix = '') {
    const element = document.getElementById(elementId);
    const currentValue = parseInt(element.textContent.replace('€', '')) || 0;
    const difference = newValue - currentValue;
    const steps = 20;
    let current = currentValue;

    const interval = setInterval(() => {
        current += difference / steps;
        if (difference > 0 && current >= newValue) {
            current = newValue;
            clearInterval(interval);
        } else if (difference < 0 && current <= newValue) {
            current = newValue;
            clearInterval(interval);
        }
        element.textContent = prefix + Math.round(current);
    }, 30);
}

// ========== EVENT LISTENERS INPUT ==========
const checkInInput = document.getElementById('checkIn');
const checkOutInput = document.getElementById('checkOut');
const roomTypeSelect = document.getElementById('roomType');

if (checkInInput) checkInInput.addEventListener('change', updatePriceCalculation);
if (checkOutInput) checkOutInput.addEventListener('change', updatePriceCalculation);
if (roomTypeSelect) {
    roomTypeSelect.addEventListener('change', function() {
        selectedRoomType = this.value;
        updatePriceCalculation();
        updateDatePickerDisabledDates();
    });
}

// ========== FORM PRENOTAZIONE ==========
document.getElementById('bookingForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    // Raccogli dati
    const formData = {
        roomType: document.getElementById('roomType').value,
        checkIn: document.getElementById('checkIn').value,
        checkOut: document.getElementById('checkOut').value,
        guests: document.getElementById('guests').value,
        name: document.getElementById('name').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        requests: document.getElementById('requests').value,
        totalPrice: document.getElementById('totalPrice').textContent.replace('€', '')
    };

    // Validazione
    const validationError = validateBookingForm(formData);
    if (validationError) {
        showNotification(validationError, 'error');
        return;
    }

    // Invia prenotazione
    await submitBooking(formData);
});

function validateBookingForm(data) {
    if (!data.roomType || !data.checkIn || !data.checkOut || !data.guests || !data.name || !data.email || !data.phone) {
        return 'Per favore compila tutti i campi obbligatori';
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(data.email)) {
        return 'Per favore inserisci un\'email valida';
    }

    const checkInDate = new Date(data.checkIn);
    const checkOutDate = new Date(data.checkOut);
    if (checkOutDate <= checkInDate) {
        return 'La data di check-out deve essere successiva al check-in';
    }

    return null;
}

async function submitBooking(formData) {
    const submitBtn = document.getElementById('submitBtn');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoader = submitBtn.querySelector('.btn-loader');

    // Disabilita bottone e mostra loader
    submitBtn.disabled = true;
    btnText.style.display = 'none';
    btnLoader.style.display = 'inline';

    try {
        // Invia al backend PHP
        const response = await fetch(`${API_BASE_URL}/bookings.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        });

        const result = await response.json();

        if (result.success) {
            // Successo: prenotazione confermata
            showNotification(`Prenotazione confermata! ID: ${result.booking_id}. Riceverai un'email di conferma a ${formData.email}`, 'success');
            resetFormAndUI();
            // Ricarica le date prenotate
            await fetchBookedDates();
        } else if (result.errors && Array.isArray(result.errors)) {
            // Errori di validazione dal server
            const errorList = result.errors.join('\n• ');
            showNotification(`Errori nella prenotazione:\n• ${errorList}`, 'error');
        } else {
            // Errore generico dal server
            showNotification(result.message || 'Errore nell\'invio della prenotazione', 'error');
        }
    } catch (error) {
        console.error('Errore:', error);
        showNotification('Errore di connessione al server. Riprova più tardi.', 'error');
    } finally {
        // Ripristina bottone
        submitBtn.disabled = false;
        btnText.style.display = 'inline';
        btnLoader.style.display = 'none';
    }
}

function resetFormAndUI() {
    setTimeout(() => {
        document.getElementById('bookingForm').reset();
        resetPriceSummary();
    }, 2000);
}

// ========== NOTIFICHE ==========
function showNotification(message, type = 'info') {
    const notification = document.getElementById('notification');
    notification.textContent = message;
    notification.className = `notification show ${type}`;

    setTimeout(() => {
        notification.classList.remove('show');
    }, 5000);
}

// ========== VALIDAZIONE TELEFONO ==========
const phoneInput = document.getElementById('phone');
if (phoneInput) {
    phoneInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9\s+\-()]/g, '');
    });
}

// ========== INIZIALIZZAZIONE DATE ==========
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    if (checkInInput) checkInInput.min = today;
    if (checkOutInput) checkOutInput.min = today;

    if (checkInInput) {
        checkInInput.addEventListener('change', function() {
            const checkInDate = new Date(this.value);
            const minCheckOut = new Date(checkInDate);
            minCheckOut.setDate(minCheckOut.getDate() + 1);
            if (checkOutInput) {
                checkOutInput.min = minCheckOut.toISOString().split('T')[0];
            }
        });
    }

    // Carica le date prenotate
    fetchBookedDates();

    // Intersection Observer per animazioni scroll
    setupScrollAnimations();
});

// ========== ANIMAZIONI AL SCROLL ==========
function setupScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, index * 100);
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Applica osservatore
    document.querySelectorAll('.room-card, .about-card, .review-card, .contact-item, .booking-form, .booking-info').forEach(element => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(element);
    });
}

// ========== SMOOTH SCROLL ==========
function scrollToSection(sectionId) {
    const target = document.querySelector(`#${sectionId}`);
    if (target) {
        target.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// ========== PARALLAX EFFECT HEADER ==========
let lastScrollTop = 0;
const header = document.querySelector('.header');

window.addEventListener('scroll', function() {
    let scrollTop = window.pageYOffset || document.documentElement.scrollTop;

    if (scrollTop > lastScrollTop) {
        // Scroll down
        header.style.transform = 'translateY(-100%)';
    } else {
        // Scroll up
        header.style.transform = 'translateY(0)';
    }

    lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
}, false);

// ========== CARICAMENTO INIZIALE ==========
console.log('Hotel Booking System Loaded - v2.0');
