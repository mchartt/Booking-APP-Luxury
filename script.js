// ========== CONFIGURAZIONE ==========
const API_BASE_URL = '/api';

// Variabili globali per gestire date prenotate
let bookedDates = {};
let selectedRoomType = null;
let disabledDatesCache = { roomType: null, dates: [] };

// Prezzi e capacità per tipo di stanza
const roomConfig = {
    'Standard': { price: 120, maxGuests: 2 },
    'Deluxe': { price: 180, maxGuests: 3 },
    'Suite': { price: 280, maxGuests: 4 }
};

// DOM Cache - populate on DOMContentLoaded
const DOM = {};

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
                displayBookedDatesInfo();
            }
        }
    } catch (error) {
        console.warn('Errore nel caricamento delle date prenotate:', error);
    }
}

/**
 * Converte un oggetto di date in un array di stringhe ISO (con cache)
 */
function getDisabledDatesArray() {
    const roomType = DOM.roomType?.value || document.getElementById('roomType').value;

    // Return cached result if room type hasn't changed
    if (disabledDatesCache.roomType === roomType) {
        return disabledDatesCache.dates;
    }

    const disabledArray = [];
    if (roomType && bookedDates[roomType]) {
        bookedDates[roomType].forEach(range => {
            const start = new Date(range.start);
            const end = new Date(range.end);
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                disabledArray.push(d.toISOString().split('T')[0]);
            }
        });
    }

    // Cache the result
    disabledDatesCache = { roomType, dates: disabledArray };
    return disabledArray;
}

/**
 * Mostra le date occupate nella UI
 */
function displayBookedDatesInfo() {
    const roomType = (DOM.roomType || document.getElementById('roomType')).value;
    const container = DOM.bookedDatesInfo || document.getElementById('bookedDatesInfo');

    if (!container) return;

    if (!roomType || !bookedDates[roomType] || bookedDates[roomType].length === 0) {
        container.innerHTML = '<p class="no-booked"><i class="fas fa-calendar-check"></i> Tutte le date disponibili</p>';
        return;
    }

    const datesList = bookedDates[roomType].map(range => {
        const startDate = new Date(range.start).toLocaleDateString('it-IT', { day: 'numeric', month: 'short' });
        const endDate = new Date(range.end).toLocaleDateString('it-IT', { day: 'numeric', month: 'short' });
        return `<span class="booked-range"><i class="fas fa-ban"></i> ${startDate} - ${endDate}</span>`;
    }).join('');

    container.innerHTML = `
        <p class="booked-title"><i class="fas fa-calendar-times"></i> Date occupate:</p>
        <div class="booked-dates-list">${datesList}</div>
    `;
}

/**
 * Aggiorna gli attributi dei date picker per disabilitare date prenotate
 */
function updateDatePickerDisabledDates() {
    const checkInInput = DOM.checkIn || document.getElementById('checkIn');
    const checkOutInput = DOM.checkOut || document.getElementById('checkOut');
    const disabledDates = getDisabledDatesArray();
    const disabledJSON = JSON.stringify(disabledDates);

    if (checkInInput) checkInInput.dataset.disabledDates = disabledJSON;
    if (checkOutInput) checkOutInput.dataset.disabledDates = disabledJSON;
}

/**
 * Valida che la data selezionata non sia prenotata
 */
function validateDateNotBooked(input) {
    const disabledDates = JSON.parse(input.dataset.disabledDates || '[]');
    const selectedDate = input.value;

    if (selectedDate && disabledDates.includes(selectedDate)) {
        showNotification('Questa data è già prenotata. Scegli un\'altra data.', 'error');
        input.value = '';
        return false;
    }
    return true;
}

/**
 * Verifica che il range di date non includa date prenotate
 */
function validateDateRange() {
    const checkIn = document.getElementById('checkIn').value;
    const checkOut = document.getElementById('checkOut').value;
    const disabledDates = getDisabledDatesArray();

    if (!checkIn || !checkOut) return true;

    const start = new Date(checkIn);
    const end = new Date(checkOut);

    for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
        const dateStr = d.toISOString().split('T')[0];
        if (disabledDates.includes(dateStr)) {
            showNotification('Il periodo selezionato include date già prenotate.', 'error');
            return false;
        }
    }
    return true;
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
    const roomTypeSelect = DOM.roomType || document.getElementById('roomType');
    roomTypeSelect.value = roomType;
    selectedRoomType = roomType;

    // Invalidate cache when room changes
    disabledDatesCache = { roomType: null, dates: [] };

    // Scroll con effetto
    scrollToSection('booking');

    // Aggiorna il numero massimo di ospiti
    updateGuestsOptions();
    updateDatePickerDisabledDates();
    displayBookedDatesInfo();
    updatePriceCalculation();

    // Animazione di highlights
    roomTypeSelect.style.animation = 'pulse 0.6s ease-out';
    setTimeout(() => roomTypeSelect.style.animation = '', 600);
}

// ========== GESTIONE OSPITI ==========
function updateGuestsOptions() {
    const roomTypeEl = DOM.roomType || document.getElementById('roomType');
    const guestsSelect = DOM.guests || document.getElementById('guests');
    const roomType = roomTypeEl.value;
    const currentValue = guestsSelect.value;

    // Massimo globale è 4, ma dipende dalla stanza
    const maxGuests = roomConfig[roomType]?.maxGuests || 4;

    // Ricostruisci le opzioni
    guestsSelect.innerHTML = '<option value="">Seleziona</option>';
    for (let i = 1; i <= maxGuests; i++) {
        const option = document.createElement('option');
        option.value = i;
        option.textContent = i === 1 ? '1 ospite' : `${i} ospiti`;
        guestsSelect.appendChild(option);
    }

    // Ripristina il valore se ancora valido
    if (currentValue && parseInt(currentValue) <= maxGuests) {
        guestsSelect.value = currentValue;
    } else if (currentValue && parseInt(currentValue) > maxGuests) {
        guestsSelect.value = maxGuests;
        showNotification(`Il massimo di ospiti per questa stanza è ${maxGuests}`, 'warning');
    }

    updatePriceCalculation();
}

// ========== CALCOLO PREZZO ==========
function updatePriceCalculation() {
    const checkInEl = DOM.checkIn || document.getElementById('checkIn');
    const checkOutEl = DOM.checkOut || document.getElementById('checkOut');
    const roomTypeEl = DOM.roomType || document.getElementById('roomType');

    const checkInInput = checkInEl.value;
    const checkOutInput = checkOutEl.value;
    const roomType = roomTypeEl.value;

    // Aggiorna info stanza se selezionata
    updateRoomSummary(roomType);

    if (!checkInInput || !checkOutInput || !roomType) {
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

    // Ottieni prezzo dalla config
    const pricePerNight = roomConfig[roomType]?.price || 0;

    // Calcola totale
    const totalPrice = nights * pricePerNight;

    // Aggiorna UI con animazione
    animateNumberChange('nightsCount', nights);
    animateNumberChange('pricePerNight', pricePerNight, '€');
    animateNumberChange('totalPrice', totalPrice, '€');

    // Mostra le date formattate
    updateDatesSummary(checkIn, checkOut);
}

function updateRoomSummary(roomType) {
    const roomSummaryEl = DOM.roomSummary || document.getElementById('roomSummary');
    if (!roomSummaryEl) return;

    if (roomType && roomConfig[roomType]) {
        roomSummaryEl.textContent = `Camera ${roomType}`;
        roomSummaryEl.classList.add('active');
    } else {
        roomSummaryEl.textContent = 'Non selezionata';
        roomSummaryEl.classList.remove('active');
    }
}

function updateDatesSummary(checkIn, checkOut) {
    const checkInSummary = DOM.checkInSummary || document.getElementById('checkInSummary');
    const checkOutSummary = DOM.checkOutSummary || document.getElementById('checkOutSummary');

    if (checkInSummary && checkIn) {
        checkInSummary.textContent = checkIn.toLocaleDateString('it-IT', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    }
    if (checkOutSummary && checkOut) {
        checkOutSummary.textContent = checkOut.toLocaleDateString('it-IT', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    }
}

function resetPriceSummary() {
    const nightsCount = DOM.nightsCount || document.getElementById('nightsCount');
    const pricePerNight = DOM.pricePerNight || document.getElementById('pricePerNight');
    const totalPrice = DOM.totalPrice || document.getElementById('totalPrice');
    const checkInSummary = DOM.checkInSummary || document.getElementById('checkInSummary');
    const checkOutSummary = DOM.checkOutSummary || document.getElementById('checkOutSummary');

    if (nightsCount) nightsCount.textContent = '0';
    if (pricePerNight) pricePerNight.textContent = '€0';
    if (totalPrice) totalPrice.textContent = '€0';
    if (checkInSummary) checkInSummary.textContent = '-';
    if (checkOutSummary) checkOutSummary.textContent = '-';
}

function animateNumberChange(elementId, newValue, prefix = '') {
    const element = DOM[elementId] || document.getElementById(elementId);
    if (!element) return;

    const currentValue = parseInt(element.textContent.replace('€', '')) || 0;
    const difference = newValue - currentValue;

    if (difference === 0) return;

    const duration = 400; // ms
    const startTime = performance.now();

    function easeOutCubic(t) {
        return 1 - Math.pow(1 - t, 3);
    }

    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easedProgress = easeOutCubic(progress);

        const current = currentValue + (difference * easedProgress);
        element.textContent = prefix + Math.round(current);

        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }

    requestAnimationFrame(update);
}

// ========== GESTIONE DATE ==========
function handleCheckInChange() {
    const checkInInput = DOM.checkIn || document.getElementById('checkIn');
    const checkOutInput = DOM.checkOut || document.getElementById('checkOut');

    if (!validateDateNotBooked(checkInInput)) {
        resetPriceSummary();
        return;
    }

    const checkInDate = new Date(checkInInput.value);
    const minCheckOut = new Date(checkInDate);
    minCheckOut.setDate(minCheckOut.getDate() + 1);

    // Imposta il minimo per checkout
    checkOutInput.min = minCheckOut.toISOString().split('T')[0];

    // Se checkout è prima del nuovo checkin, resettalo
    if (checkOutInput.value && new Date(checkOutInput.value) <= checkInDate) {
        checkOutInput.value = '';
        showNotification('Il check-out è stato resettato. Seleziona una nuova data.', 'warning');
    }

    updatePriceCalculation();
}

function handleCheckOutChange() {
    const checkInInput = DOM.checkIn || document.getElementById('checkIn');
    const checkOutInput = DOM.checkOut || document.getElementById('checkOut');

    if (!validateDateNotBooked(checkOutInput)) {
        resetPriceSummary();
        return;
    }

    // Validazione: checkout deve essere dopo checkin
    if (checkInInput.value && checkOutInput.value) {
        const checkIn = new Date(checkInInput.value);
        const checkOut = new Date(checkOutInput.value);

        if (checkOut <= checkIn) {
            showNotification('Il check-out deve essere successivo al check-in.', 'error');
            checkOutInput.value = '';
            resetPriceSummary();
            return;
        }

        // Verifica che il range non includa date prenotate
        if (!validateDateRange()) {
            checkOutInput.value = '';
            resetPriceSummary();
            return;
        }
    }

    updatePriceCalculation();
}

// ========== EVENT LISTENERS INPUT ==========
const checkInInput = document.getElementById('checkIn');
const checkOutInput = document.getElementById('checkOut');
const roomTypeSelect = document.getElementById('roomType');
const guestsSelect = document.getElementById('guests');

if (checkInInput) {
    checkInInput.addEventListener('change', handleCheckInChange);
}

if (checkOutInput) {
    checkOutInput.addEventListener('change', handleCheckOutChange);
}

if (roomTypeSelect) {
    roomTypeSelect.addEventListener('change', function() {
        selectedRoomType = this.value;
        updateGuestsOptions();
        updateDatePickerDisabledDates();
        displayBookedDatesInfo();
        updatePriceCalculation();
    });
}

if (guestsSelect) {
    guestsSelect.addEventListener('change', updatePriceCalculation);
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

    // Validazione numero ospiti
    const maxGuests = roomConfig[data.roomType]?.maxGuests || 4;
    if (parseInt(data.guests) > maxGuests) {
        return `Il massimo di ospiti per la camera ${data.roomType} è ${maxGuests}`;
    }

    // Verifica date prenotate
    if (!validateDateRange()) {
        return 'Il periodo selezionato include date già prenotate';
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
            // Calcola numero notti
            const checkIn = new Date(formData.checkIn);
            const checkOut = new Date(formData.checkOut);
            const nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));

            // Salva dati per pagina pagamento
            const bookingForPayment = {
                ...formData,
                booking_id: result.booking_id,
                id: result.booking?.id,
                nights: nights
            };
            sessionStorage.setItem('pendingBooking', JSON.stringify(bookingForPayment));

            // Mostra notifica e redirect a pagamento
            showNotification('Prenotazione creata! Redirect al pagamento...', 'success');
            setTimeout(() => {
                window.location.href = 'payment.php';
            }, 1500);
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
        updateRoomSummary('');
        displayBookedDatesInfo();
    }, 2000);
}

// ========== NOTIFICHE ==========
function showNotification(message, type = 'info') {
    const notification = DOM.notification || document.getElementById('notification');
    if (!notification) return;

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
    // Populate DOM cache
    DOM.checkIn = document.getElementById('checkIn');
    DOM.checkOut = document.getElementById('checkOut');
    DOM.roomType = document.getElementById('roomType');
    DOM.guests = document.getElementById('guests');
    DOM.bookingForm = document.getElementById('bookingForm');
    DOM.submitBtn = document.getElementById('submitBtn');
    DOM.notification = document.getElementById('notification');
    DOM.nightsCount = document.getElementById('nightsCount');
    DOM.pricePerNight = document.getElementById('pricePerNight');
    DOM.totalPrice = document.getElementById('totalPrice');
    DOM.roomSummary = document.getElementById('roomSummary');
    DOM.checkInSummary = document.getElementById('checkInSummary');
    DOM.checkOutSummary = document.getElementById('checkOutSummary');
    DOM.bookedDatesInfo = document.getElementById('bookedDatesInfo');

    const today = new Date().toISOString().split('T')[0];

    if (DOM.checkIn) DOM.checkIn.min = today;
    if (DOM.checkOut) DOM.checkOut.min = today;

    // Carica le date prenotate (defer to idle time)
    if ('requestIdleCallback' in window) {
        requestIdleCallback(() => fetchBookedDates(), { timeout: 2000 });
    } else {
        setTimeout(fetchBookedDates, 100);
    }

    // Inizializza il riepilogo
    resetPriceSummary();
    updateRoomSummary('');

    // Intersection Observer per animazioni scroll
    setupScrollAnimations();
});

// ========== ANIMAZIONI AL SCROLL ==========
function setupScrollAnimations() {
    // Check for reduced motion preference
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.querySelectorAll('.about-card, .room-card, .review-card, .contact-item, .booking-form, .booking-info').forEach(el => {
            el.style.opacity = '1';
        });
        return;
    }

    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -60px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                requestAnimationFrame(() => {
                    entry.target.classList.add('visible');
                });
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Applica animazione ai titoli delle sezioni
    document.querySelectorAll('.about h2, .rooms h2, .booking h2, .reviews h2, .contact h2').forEach(element => {
        element.classList.add('reveal');
        observer.observe(element);
    });

    // Applica animazione alle card con stagger
    document.querySelectorAll('.about-card').forEach((element, index) => {
        element.classList.add('reveal');
        element.classList.add(`delay-${(index % 3) + 1}`);
        observer.observe(element);
    });

    document.querySelectorAll('.room-card').forEach((element, index) => {
        element.classList.add('reveal-scale');
        element.classList.add(`delay-${(index % 3) + 1}`);
        observer.observe(element);
    });

    document.querySelectorAll('.review-card').forEach((element, index) => {
        element.classList.add('reveal');
        element.classList.add(`delay-${(index % 3) + 1}`);
        observer.observe(element);
    });

    document.querySelectorAll('.contact-item').forEach((element, index) => {
        element.classList.add('reveal');
        element.classList.add(`delay-${(index % 3) + 1}`);
        observer.observe(element);
    });

    // Form e sidebar booking
    const bookingForm = document.querySelector('.booking-form');
    const bookingInfo = document.querySelector('.booking-info');

    if (bookingForm) {
        bookingForm.classList.add('reveal-left');
        observer.observe(bookingForm);
    }

    if (bookingInfo) {
        bookingInfo.classList.add('reveal-right');
        bookingInfo.classList.add('delay-2');
        observer.observe(bookingInfo);
    }
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
let ticking = false;
const header = document.querySelector('.header');

function updateHeader(scrollTop) {
    if (scrollTop > lastScrollTop && scrollTop > 100) {
        header.style.transform = 'translate3d(0, -100%, 0)';
    } else {
        header.style.transform = 'translate3d(0, 0, 0)';
    }
    lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
    ticking = false;
}

window.addEventListener('scroll', function() {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

    if (!ticking) {
        requestAnimationFrame(() => updateHeader(scrollTop));
        ticking = true;
    }
}, { passive: true });

// ========== CARICAMENTO INIZIALE ==========
// Sistema di prenotazione hotel inizializzato
