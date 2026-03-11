/**
 * Payment Page - Luxury Hotel
 * Gestione sicura dei pagamenti con validazioni robuste
 */

// ========== CONFIGURAZIONE ==========
const PAYMENT_CONFIG = {
    API_URL: '/api/payments.php',
    SESSION_TIMEOUT: 15 * 60 * 1000, // 15 minuti
    MIN_CARD_LENGTH: 13,
    MAX_CARD_LENGTH: 19,
    CVV_LENGTH: { min: 3, max: 4 },
    IBAN: {
        beneficiary: 'Luxury Hotel S.r.l.',
        iban: 'IT60 X054 2811 1010 0000 0123 456',
        bic: 'BPPIITRRXXX',
        bank: 'Banca Popolare Italiana'
    }
};

// ========== STATO APPLICAZIONE ==========
let bookingData = null;
let selectedPaymentMethod = 'card';
let sessionTimer = null;
let timeRemaining = PAYMENT_CONFIG.SESSION_TIMEOUT;

// ========== UTILITY FUNCTIONS ==========

/**
 * Sanitizza input HTML per prevenire XSS
 */
function sanitizeHTML(str) {
    if (!str) return '';
    const temp = document.createElement('div');
    temp.textContent = str;
    return temp.innerHTML;
}

/**
 * Formatta data in italiano
 */
function formatDate(dateStr) {
    if (!dateStr) return '-';
    try {
        const date = new Date(dateStr);
        return date.toLocaleDateString('it-IT', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    } catch {
        return '-';
    }
}

/**
 * Formatta prezzo con simbolo euro
 */
function formatPrice(amount) {
    const num = parseFloat(amount);
    if (isNaN(num)) return '€0';
    return `€${num.toFixed(2).replace('.', ',')}`;
}

/**
 * Genera token CSRF
 */
function generateCSRFToken() {
    const array = new Uint8Array(32);
    crypto.getRandomValues(array);
    return Array.from(array, b => b.toString(16).padStart(2, '0')).join('');
}

// ========== VALIDAZIONE DATI PRENOTAZIONE ==========

/**
 * Verifica integrità dati prenotazione
 */
function validateBookingData(data) {
    const errors = [];

    // Campi obbligatori
    const requiredFields = ['booking_id', 'roomType', 'checkIn', 'checkOut', 'name', 'email', 'totalPrice'];
    for (const field of requiredFields) {
        if (!data[field]) {
            errors.push(`Campo mancante: ${field}`);
        }
    }

    // Validazione booking_id
    if (data.booking_id && !/^BK\d{14}_[a-f0-9]{8}$/i.test(data.booking_id)) {
        errors.push('ID prenotazione non valido');
    }

    // Validazione date
    if (data.checkIn && data.checkOut) {
        const checkIn = new Date(data.checkIn);
        const checkOut = new Date(data.checkOut);
        const now = new Date();
        now.setHours(0, 0, 0, 0);

        if (isNaN(checkIn.getTime()) || isNaN(checkOut.getTime())) {
            errors.push('Date non valide');
        } else if (checkIn < now) {
            errors.push('Data check-in nel passato');
        } else if (checkOut <= checkIn) {
            errors.push('Check-out deve essere dopo check-in');
        }
    }

    // Validazione prezzo
    const price = parseFloat(data.totalPrice);
    if (isNaN(price) || price <= 0 || price > 100000) {
        errors.push('Prezzo non valido');
    }

    // Validazione tipo stanza
    const validRoomTypes = ['Standard', 'Deluxe', 'Suite'];
    if (data.roomType && !validRoomTypes.includes(data.roomType)) {
        errors.push('Tipo stanza non valido');
    }

    // Validazione email
    if (data.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) {
        errors.push('Email non valida');
    }

    return {
        valid: errors.length === 0,
        errors
    };
}

// ========== VALIDAZIONE CARTA ==========

/**
 * Algoritmo di Luhn per validare numeri carta
 */
function luhnCheck(cardNumber) {
    const digits = cardNumber.replace(/\D/g, '');
    if (digits.length < PAYMENT_CONFIG.MIN_CARD_LENGTH) return false;

    let sum = 0;
    let isEven = false;

    for (let i = digits.length - 1; i >= 0; i--) {
        let digit = parseInt(digits[i], 10);

        if (isEven) {
            digit *= 2;
            if (digit > 9) digit -= 9;
        }

        sum += digit;
        isEven = !isEven;
    }

    return sum % 10 === 0;
}

/**
 * Rileva brand carta
 */
function detectCardBrand(cardNumber) {
    const number = cardNumber.replace(/\D/g, '');

    const patterns = {
        visa: /^4/,
        mastercard: /^5[1-5]|^2[2-7]/,
        amex: /^3[47]/,
        discover: /^6(?:011|5)/
    };

    for (const [brand, pattern] of Object.entries(patterns)) {
        if (pattern.test(number)) return brand;
    }

    return null;
}

/**
 * Valida data scadenza
 */
function validateExpiry(expiry) {
    const match = expiry.match(/^(\d{2})\/(\d{2})$/);
    if (!match) return { valid: false, error: 'Formato non valido (MM/AA)' };

    const month = parseInt(match[1], 10);
    const year = parseInt('20' + match[2], 10);

    if (month < 1 || month > 12) {
        return { valid: false, error: 'Mese non valido' };
    }

    const now = new Date();
    const expDate = new Date(year, month, 0); // Ultimo giorno del mese

    if (expDate < now) {
        return { valid: false, error: 'Carta scaduta' };
    }

    return { valid: true };
}

/**
 * Valida CVV
 */
function validateCVV(cvv, cardBrand) {
    const length = cvv.length;
    const expectedLength = cardBrand === 'amex' ? 4 : 3;

    if (!/^\d+$/.test(cvv)) {
        return { valid: false, error: 'Solo numeri' };
    }

    if (length !== expectedLength) {
        return { valid: false, error: `Deve essere ${expectedLength} cifre` };
    }

    return { valid: true };
}

// ========== FORMATTAZIONE INPUT ==========

/**
 * Formatta numero carta con spazi
 */
function formatCardNumber(input) {
    let value = input.value.replace(/\D/g, '');
    const brand = detectCardBrand(value);

    // Limita lunghezza
    const maxLength = brand === 'amex' ? 15 : 16;
    value = value.substring(0, maxLength);

    // Formatta con spazi
    if (brand === 'amex') {
        // AMEX: 4-6-5
        value = value.replace(/(\d{4})(\d{0,6})(\d{0,5})/, (_, a, b, c) => {
            return [a, b, c].filter(Boolean).join(' ');
        });
    } else {
        // Altre: 4-4-4-4
        value = value.match(/.{1,4}/g)?.join(' ') || value;
    }

    input.value = value;
    updateCardIcons(brand);
    updateCardPreview();
}

/**
 * Formatta scadenza MM/AA
 */
function formatExpiry(input) {
    let value = input.value.replace(/\D/g, '');

    if (value.length >= 2) {
        const month = parseInt(value.substring(0, 2), 10);
        if (month > 12) value = '12' + value.substring(2);
        if (month === 0) value = '01' + value.substring(2);
        value = value.substring(0, 2) + '/' + value.substring(2, 4);
    }

    input.value = value;
    updateCardPreview();
}

/**
 * Formatta CVV (solo numeri)
 */
function formatCVV(input) {
    const brand = detectCardBrand(document.getElementById('cardNumber')?.value || '');
    const maxLength = brand === 'amex' ? 4 : 3;
    input.value = input.value.replace(/\D/g, '').substring(0, maxLength);
}

/**
 * Formatta nome carta (uppercase)
 */
function formatCardName(input) {
    input.value = input.value.toUpperCase().replace(/[^A-Z\s]/g, '');
    updateCardPreview();
}

// ========== UI UPDATES ==========

/**
 * Aggiorna icone carte
 */
function updateCardIcons(activeBrand) {
    const icons = document.querySelectorAll('.card-icons i');
    icons.forEach(icon => {
        icon.classList.remove('active');
        const brandClass = icon.className.match(/fa-cc-(\w+)/)?.[1];
        if (brandClass === activeBrand) {
            icon.classList.add('active');
        }
    });
}

/**
 * Aggiorna preview carta
 */
function updateCardPreview() {
    const cardNumberEl = document.getElementById('cardNumber');
    const cardNameEl = document.getElementById('cardName');
    const cardExpiryEl = document.getElementById('cardExpiry');

    const displayNumber = document.querySelector('.card-number-display');
    const displayName = document.querySelector('.card-info .card-holder strong');
    const displayExpiry = document.querySelector('.card-info .card-expiry strong');
    const brandIcon = document.querySelector('.card-brand');

    if (displayNumber && cardNumberEl) {
        const num = cardNumberEl.value || '**** **** **** ****';
        displayNumber.textContent = num.padEnd(19, '*').replace(/\*{4}/g, m => m + ' ').trim();
    }

    if (displayName && cardNameEl) {
        displayName.textContent = cardNameEl.value || 'NOME COGNOME';
    }

    if (displayExpiry && cardExpiryEl) {
        displayExpiry.textContent = cardExpiryEl.value || 'MM/AA';
    }

    if (brandIcon && cardNumberEl) {
        const brand = detectCardBrand(cardNumberEl.value);
        const iconMap = {
            visa: 'fab fa-cc-visa',
            mastercard: 'fab fa-cc-mastercard',
            amex: 'fab fa-cc-amex',
            discover: 'fab fa-cc-discover'
        };
        brandIcon.className = 'card-brand ' + (iconMap[brand] || 'fas fa-credit-card');
    }
}

/**
 * Mostra errore su input
 */
function showInputError(inputId, message) {
    const input = document.getElementById(inputId);
    const errorEl = document.getElementById(`${inputId}Error`);

    if (input) {
        input.classList.add('error');
        input.classList.remove('valid');
    }

    if (errorEl) {
        errorEl.textContent = message;
        errorEl.classList.add('show');
    }
}

/**
 * Rimuovi errore da input
 */
function clearInputError(inputId) {
    const input = document.getElementById(inputId);
    const errorEl = document.getElementById(`${inputId}Error`);

    if (input) {
        input.classList.remove('error');
    }

    if (errorEl) {
        errorEl.classList.remove('show');
    }
}

/**
 * Marca input come valido
 */
function markInputValid(inputId) {
    const input = document.getElementById(inputId);
    if (input) {
        input.classList.remove('error');
        input.classList.add('valid');
    }
    clearInputError(inputId);
}

// ========== GESTIONE METODI PAGAMENTO ==========

/**
 * Cambia metodo di pagamento
 */
function switchPaymentMethod(method) {
    selectedPaymentMethod = method;

    // Aggiorna tabs
    document.querySelectorAll('.method-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.method === method);
    });

    // Mostra/nascondi contenuti
    document.querySelectorAll('.payment-method-content').forEach(content => {
        content.style.display = 'none';
    });

    const activeContent = document.getElementById(`${method}Payment`);
    if (activeContent) {
        activeContent.style.display = 'block';
    }

    // Aggiorna required sui campi carta
    const cardInputs = document.querySelectorAll('#cardPayment input');
    cardInputs.forEach(input => {
        if (input.hasAttribute('data-required')) {
            input.required = method === 'card';
        }
    });

    // Aggiorna testo pulsante
    updatePayButtonText();
}

/**
 * Aggiorna testo pulsante pagamento
 */
function updatePayButtonText() {
    const btnText = document.querySelector('.btn-pay .btn-text');
    if (!btnText || !bookingData) return;

    const amount = formatPrice(bookingData.totalPrice);

    switch (selectedPaymentMethod) {
        case 'card':
            btnText.innerHTML = `<i class="fas fa-lock"></i> Paga Ora ${amount}`;
            break;
        case 'paypal':
            btnText.innerHTML = `<i class="fab fa-paypal"></i> Paga con PayPal ${amount}`;
            break;
        case 'iban':
            btnText.innerHTML = `<i class="fas fa-check"></i> Conferma Prenotazione`;
            break;
    }
}

// ========== TIMER SESSIONE ==========

/**
 * Avvia timer sessione
 */
function startSessionTimer() {
    const timerEl = document.querySelector('.timer-value');
    if (!timerEl) return;

    sessionTimer = setInterval(() => {
        timeRemaining -= 1000;

        if (timeRemaining <= 0) {
            clearInterval(sessionTimer);
            handleSessionExpired();
            return;
        }

        const minutes = Math.floor(timeRemaining / 60000);
        const seconds = Math.floor((timeRemaining % 60000) / 1000);
        timerEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

        // Warning quando rimane poco tempo
        const timerContainer = document.querySelector('.payment-timer');
        if (timeRemaining <= 60000 && timerContainer) {
            timerContainer.classList.add('expired');
        }
    }, 1000);
}

/**
 * Gestisce scadenza sessione
 */
function handleSessionExpired() {
    showNotification('Sessione scaduta. Torna alla pagina di prenotazione.', 'error');
    sessionStorage.removeItem('pendingBooking');
    setTimeout(() => {
        window.location.href = 'index.html#booking';
    }, 2000);
}

// ========== COPIA IBAN ==========

/**
 * Copia testo negli appunti
 */
async function copyToClipboard(text, button) {
    try {
        await navigator.clipboard.writeText(text.replace(/\s/g, ''));
        button.classList.add('copied');
        button.innerHTML = '<i class="fas fa-check"></i>';

        setTimeout(() => {
            button.classList.remove('copied');
            button.innerHTML = '<i class="fas fa-copy"></i>';
        }, 2000);

        showNotification('Copiato negli appunti!', 'success');
    } catch {
        showNotification('Impossibile copiare', 'error');
    }
}

// ========== POPOLAMENTO RIEPILOGO ==========

/**
 * Popola riepilogo prenotazione
 */
function populateSummary() {
    // Recupera dati da sessionStorage
    const rawData = sessionStorage.getItem('pendingBooking');

    if (!rawData) {
        showNotification('Nessuna prenotazione trovata. Torna alla pagina principale.', 'error');
        setTimeout(() => window.location.href = 'index.html', 2000);
        return false;
    }

    try {
        bookingData = JSON.parse(rawData);
    } catch {
        showNotification('Dati prenotazione corrotti. Riprova.', 'error');
        sessionStorage.removeItem('pendingBooking');
        setTimeout(() => window.location.href = 'index.html', 2000);
        return false;
    }

    // Valida dati
    const validation = validateBookingData(bookingData);
    if (!validation.valid) {
        console.error('Errori validazione:', validation.errors);
        showNotification('Dati prenotazione non validi. Riprova.', 'error');
        sessionStorage.removeItem('pendingBooking');
        setTimeout(() => window.location.href = 'index.html', 2000);
        return false;
    }

    // Popola UI con dati sanitizzati
    setElementText('summaryBookingId', sanitizeHTML(bookingData.booking_id));
    setElementText('summaryRoom', `Camera ${sanitizeHTML(bookingData.roomType)}`);
    setElementText('summaryName', sanitizeHTML(bookingData.name));
    setElementText('summaryCheckIn', formatDate(bookingData.checkIn));
    setElementText('summaryCheckOut', formatDate(bookingData.checkOut));
    setElementText('summaryGuests', `${bookingData.guests} ospite/i`);
    setElementText('summaryNights', `${bookingData.nights} notte/i`);
    setElementText('summaryTotal', formatPrice(bookingData.totalPrice));
    setElementText('btnAmount', formatPrice(bookingData.totalPrice));

    // Popola IBAN con dati hotel
    setElementText('ibanBeneficiary', PAYMENT_CONFIG.IBAN.beneficiary);
    setElementText('ibanNumber', PAYMENT_CONFIG.IBAN.iban);
    setElementText('ibanBIC', PAYMENT_CONFIG.IBAN.bic);
    setElementText('ibanCausale', bookingData.booking_id);

    return true;
}

/**
 * Helper per impostare testo elemento
 */
function setElementText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
}

// ========== GESTIONE FORM ==========

/**
 * Valida form carta
 */
function validateCardForm() {
    let isValid = true;

    // Nome carta
    const cardName = document.getElementById('cardName').value.trim();
    if (!cardName || cardName.length < 3) {
        showInputError('cardName', 'Inserisci il nome dell\'intestatario');
        isValid = false;
    } else {
        markInputValid('cardName');
    }

    // Numero carta
    const cardNumber = document.getElementById('cardNumber').value;
    const cardDigits = cardNumber.replace(/\D/g, '');
    if (!luhnCheck(cardDigits)) {
        showInputError('cardNumber', 'Numero carta non valido');
        isValid = false;
    } else {
        markInputValid('cardNumber');
    }

    // Scadenza
    const expiry = document.getElementById('cardExpiry').value;
    const expiryValidation = validateExpiry(expiry);
    if (!expiryValidation.valid) {
        showInputError('cardExpiry', expiryValidation.error);
        isValid = false;
    } else {
        markInputValid('cardExpiry');
    }

    // CVV
    const cvv = document.getElementById('cardCvv').value;
    const brand = detectCardBrand(cardNumber);
    const cvvValidation = validateCVV(cvv, brand);
    if (!cvvValidation.valid) {
        showInputError('cardCvv', cvvValidation.error);
        isValid = false;
    } else {
        markInputValid('cardCvv');
    }

    return isValid;
}

/**
 * Gestisce submit form
 */
async function handleFormSubmit(e) {
    e.preventDefault();

    // Verifica termini accettati
    if (!document.getElementById('acceptTerms').checked) {
        showNotification('Devi accettare i Termini e Condizioni', 'error');
        return;
    }

    // Validazione specifica per metodo
    if (selectedPaymentMethod === 'card' && !validateCardForm()) {
        showNotification('Correggi gli errori nei dati della carta', 'error');
        return;
    }

    // Mostra loading
    showLoading(true);

    try {
        if (selectedPaymentMethod === 'iban') {
            // Per IBAN, conferma solo la prenotazione
            await processIBANPayment();
        } else {
            // Per carta e PayPal, processa pagamento
            await processPayment();
        }
    } catch (error) {
        console.error('Errore pagamento:', error);
        showNotification('Errore durante il pagamento. Riprova.', 'error');
    } finally {
        showLoading(false);
    }
}

/**
 * Processa pagamento carta/PayPal
 */
async function processPayment() {
    const csrfToken = generateCSRFToken();

    const paymentData = {
        booking_id: bookingData.booking_id,
        amount: parseFloat(bookingData.totalPrice),
        method: selectedPaymentMethod,
        csrf_token: csrfToken
    };

    // Aggiungi dati carta se necessario (in produzione, usare tokenizzazione!)
    if (selectedPaymentMethod === 'card') {
        paymentData.card_last_four = document.getElementById('cardNumber').value.replace(/\D/g, '').slice(-4);
        paymentData.card_brand = detectCardBrand(document.getElementById('cardNumber').value);
    }

    // Simula chiamata API
    const response = await fetch(PAYMENT_CONFIG.API_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(paymentData)
    });

    // Per demo, simula successo anche se API non esiste
    await simulatePaymentDelay();

    showSuccessModal();
}

/**
 * Processa conferma IBAN
 */
async function processIBANPayment() {
    const csrfToken = generateCSRFToken();

    const paymentData = {
        booking_id: bookingData.booking_id,
        amount: parseFloat(bookingData.totalPrice),
        method: 'iban',
        status: 'pending_transfer',
        csrf_token: csrfToken
    };

    await fetch(PAYMENT_CONFIG.API_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(paymentData)
    }).catch(() => {});

    await simulatePaymentDelay();

    showSuccessModal(true);
}

/**
 * Simula delay pagamento per demo
 */
function simulatePaymentDelay() {
    return new Promise(resolve => setTimeout(resolve, 2000));
}

/**
 * Mostra modal successo
 */
function showSuccessModal(isIBAN = false) {
    const modal = document.getElementById('successModal');

    setElementText('confirmBookingId', bookingData.booking_id);
    setElementText('confirmAmount', formatPrice(bookingData.totalPrice));
    setElementText('confirmEmail', bookingData.email);

    // Personalizza messaggio per IBAN
    const messageEl = modal.querySelector('p:not(.email-notice)');
    if (messageEl) {
        if (isIBAN) {
            messageEl.textContent = 'La prenotazione sarà confermata dopo la ricezione del bonifico.';
        } else {
            messageEl.textContent = 'La tua prenotazione è stata confermata con successo.';
        }
    }

    modal.classList.add('show');

    // Pulisci sessione
    sessionStorage.removeItem('pendingBooking');
    clearInterval(sessionTimer);
}

// ========== LOADING OVERLAY ==========

/**
 * Mostra/nasconde loading overlay
 */
function showLoading(show) {
    const overlay = document.getElementById('loadingOverlay');
    const payBtn = document.getElementById('payBtn');

    if (overlay) {
        overlay.classList.toggle('show', show);
    }

    if (payBtn) {
        payBtn.disabled = show;
        const btnText = payBtn.querySelector('.btn-text');
        const btnLoader = payBtn.querySelector('.btn-loader');
        if (btnText) btnText.style.display = show ? 'none' : 'flex';
        if (btnLoader) btnLoader.style.display = show ? 'inline' : 'none';
    }
}

// ========== NOTIFICHE ==========

/**
 * Mostra notifica toast
 */
function showNotification(message, type = 'info') {
    const notification = document.getElementById('notification');
    if (!notification) return;

    notification.textContent = message;
    notification.className = `notification show ${type}`;

    setTimeout(() => {
        notification.classList.remove('show');
    }, 5000);
}

// ========== INIZIALIZZAZIONE ==========

document.addEventListener('DOMContentLoaded', function() {
    // Popola riepilogo
    if (!populateSummary()) return;

    // Avvia timer
    startSessionTimer();

    // Event listeners metodi pagamento
    document.querySelectorAll('.method-btn').forEach(btn => {
        btn.addEventListener('click', () => switchPaymentMethod(btn.dataset.method));
    });

    // Event listeners formattazione input
    const cardNumber = document.getElementById('cardNumber');
    const cardExpiry = document.getElementById('cardExpiry');
    const cardCvv = document.getElementById('cardCvv');
    const cardName = document.getElementById('cardName');

    if (cardNumber) {
        cardNumber.addEventListener('input', () => formatCardNumber(cardNumber));
        cardNumber.addEventListener('blur', () => {
            if (cardNumber.value && !luhnCheck(cardNumber.value.replace(/\D/g, ''))) {
                showInputError('cardNumber', 'Numero carta non valido');
            } else if (cardNumber.value) {
                markInputValid('cardNumber');
            }
        });
    }

    if (cardExpiry) {
        cardExpiry.addEventListener('input', () => formatExpiry(cardExpiry));
        cardExpiry.addEventListener('blur', () => {
            if (cardExpiry.value) {
                const validation = validateExpiry(cardExpiry.value);
                if (!validation.valid) {
                    showInputError('cardExpiry', validation.error);
                } else {
                    markInputValid('cardExpiry');
                }
            }
        });
    }

    if (cardCvv) {
        cardCvv.addEventListener('input', () => formatCVV(cardCvv));
    }

    if (cardName) {
        cardName.addEventListener('input', () => formatCardName(cardName));
    }

    // Form submit
    const form = document.getElementById('paymentForm');
    if (form) {
        form.addEventListener('submit', handleFormSubmit);
    }

    // Copy buttons per IBAN
    document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const text = this.previousElementSibling?.textContent || '';
            copyToClipboard(text, this);
        });
    });

    // Aggiorna testo pulsante iniziale
    updatePayButtonText();

    // Prevenzione back dopo pagamento
    window.history.pushState(null, '', window.location.href);
    window.addEventListener('popstate', function() {
        window.history.pushState(null, '', window.location.href);
    });

    // Sistema di pagamento inizializzato
});
