/**
 * Payment Page - Luxury Hotel
 * PCI-DSS COMPLIANT: Nessun dato carta passa attraverso il nostro codice.
 * Utilizza Stripe Elements per la raccolta sicura dei dati.
 */

// ========== CONFIGURAZIONE ==========
const PAYMENT_CONFIG = {
    API_URL: './api/payments.php',
    STRIPE_CONFIG_URL: './api/stripe-config.php',
    SESSION_TIMEOUT: 15 * 60 * 1000, // 15 minuti
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

// Stripe Elements (inizializzati dopo il caricamento della pagina)
let stripe = null;
let cardElement = null;
let clientSecret = null;

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
 * Legge il token CSRF dal meta tag (generato dal server)
 * SICUREZZA: Il token è generato esclusivamente dal backend PHP
 * e iniettato nel DOM tramite il meta tag. Non generare MAI token lato client.
 * @returns {string} Il token CSRF o stringa vuota se non trovato
 */
function getCSRFToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (!metaTag) {
        console.error('CSRF token meta tag non trovato. Assicurati di usare payment.php');
        return '';
    }
    return metaTag.getAttribute('content') || '';
}

// ========== VALIDAZIONE DATI PRENOTAZIONE ==========

/**
 * Verifica integrità dati prenotazione
 */
function validateBookingData(data) {
    const errors = [];

    const requiredFields = ['booking_id', 'roomType', 'checkIn', 'checkOut', 'name', 'email', 'totalPrice'];
    for (const field of requiredFields) {
        if (!data[field]) {
            errors.push(`Campo mancante: ${field}`);
        }
    }

    if (data.booking_id && !/^BK\d{14}_[a-f0-9]{8}$/i.test(data.booking_id)) {
        errors.push('ID prenotazione non valido');
    }

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

    const price = parseFloat(data.totalPrice);
    if (isNaN(price) || price <= 0 || price > 100000) {
        errors.push('Prezzo non valido');
    }

    const validRoomTypes = ['Standard', 'Deluxe', 'Suite'];
    if (data.roomType && !validRoomTypes.includes(data.roomType)) {
        errors.push('Tipo stanza non valido');
    }

    if (data.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) {
        errors.push('Email non valida');
    }

    return {
        valid: errors.length === 0,
        errors
    };
}

// ========== STRIPE ELEMENTS INITIALIZATION ==========

/**
 * Inizializza Stripe e crea il PaymentIntent sul backend
 * SICUREZZA: La publishable key è pubblica, il client_secret viene dal backend
 * ZERO-TRUST: Non inviamo amount - il backend lo recupera dal DB
 */
async function initializeStripe() {
    try {
        // 1. Ottieni la configurazione Stripe dal backend
        // NOTA: L'importo viene recuperato dal DB usando booking_id (Zero-Trust)
        const response = await fetch(PAYMENT_CONFIG.STRIPE_CONFIG_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                booking_id: bookingData.booking_id,
                currency: 'eur',
                description: `Prenotazione ${bookingData.roomType} - ${bookingData.booking_id}`,
                customer_email: bookingData.email
            })
        });

        if (!response.ok) {
            throw new Error('Errore nella configurazione del pagamento');
        }

        const config = await response.json();

        if (!config.success) {
            throw new Error(config.message || 'Configurazione Stripe fallita');
        }

        // 2. Inizializza Stripe con la publishable key
        stripe = Stripe(config.publishable_key);
        clientSecret = config.client_secret;

        // 3. Crea e monta il Card Element
        const elements = stripe.elements({
            clientSecret: clientSecret,
            appearance: {
                theme: 'stripe',
                variables: {
                    colorPrimary: '#8B6F47',
                    colorBackground: '#FDFBF7',
                    colorText: '#2D2A24',
                    colorDanger: '#e63946',
                    fontFamily: 'Lora, serif',
                    borderRadius: '8px',
                    spacingUnit: '4px'
                },
                rules: {
                    '.Input': {
                        border: '1px solid #C9B99A',
                        boxShadow: 'none',
                        padding: '12px 16px'
                    },
                    '.Input:focus': {
                        border: '2px solid #8B6F47',
                        boxShadow: '0 0 0 3px rgba(139, 111, 71, 0.1)'
                    },
                    '.Label': {
                        fontWeight: '500',
                        marginBottom: '8px'
                    }
                }
            },
            locale: 'it'
        });

        // 4. Monta il Payment Element (include carta, Apple Pay, Google Pay, etc.)
        cardElement = elements.create('payment', {
            layout: 'tabs'
        });
        cardElement.mount('#card-element');

        // 5. Gestisci errori di validazione in tempo reale
        cardElement.on('change', function(event) {
            const errorElement = document.getElementById('card-errors');
            if (event.error) {
                errorElement.textContent = event.error.message;
                errorElement.classList.add('show');
            } else {
                errorElement.textContent = '';
                errorElement.classList.remove('show');
            }
        });

        console.log('Stripe Elements inizializzato con successo');

    } catch (error) {
        console.error('Errore inizializzazione Stripe:', error);
        showNotification('Errore nel caricamento del sistema di pagamento. Ricarica la pagina.', 'error');

        // Disabilita l'opzione carta se Stripe non è disponibile
        const cardBtn = document.querySelector('[data-method="card"]');
        if (cardBtn) {
            cardBtn.disabled = true;
            cardBtn.title = 'Pagamento con carta non disponibile';
        }
    }
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

    // Popola IBAN
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
 * Gestisce submit form
 * SICUREZZA: Per carta, usa Stripe confirmPayment - nessun dato carta nel nostro codice
 */
async function handleFormSubmit(e) {
    e.preventDefault();

    // Verifica termini accettati
    if (!document.getElementById('acceptTerms').checked) {
        showNotification('Devi accettare i Termini e Condizioni', 'error');
        return;
    }

    showLoading(true);

    try {
        switch (selectedPaymentMethod) {
            case 'card':
                await processStripePayment();
                break;
            case 'paypal':
                showNotification('Utilizza il pulsante PayPal per procedere', 'warning');
                showLoading(false);
                break;
            case 'iban':
                await processIBANPayment();
                break;
        }
    } catch (error) {
        console.error('Errore pagamento:', error);
        showNotification(error.message || 'Errore durante il pagamento. Riprova.', 'error');
    } finally {
        showLoading(false);
    }
}

/**
 * Processa pagamento con Stripe
 * SICUREZZA PCI-DSS: I dati carta sono gestiti interamente da Stripe
 * Il nostro server riceve solo il payment_intent_id dopo la conferma
 */
async function processStripePayment() {
    if (!stripe || !cardElement || !clientSecret) {
        throw new Error('Sistema di pagamento non inizializzato. Ricarica la pagina.');
    }

    // Stripe gestisce tutti i dati carta nel loro iframe sicuro
    // Noi riceviamo solo il risultato (successo/errore)
    const { error, paymentIntent } = await stripe.confirmPayment({
        elements: cardElement._elements, // L'elements object
        confirmParams: {
            return_url: window.location.origin + '/payment-success.html',
            receipt_email: bookingData.email,
            payment_method_data: {
                billing_details: {
                    name: bookingData.name,
                    email: bookingData.email
                }
            }
        },
        redirect: 'if_required' // Non redireziona se non necessario (es. no 3DS)
    });

    if (error) {
        // Mostra errore all'utente (errore Stripe, non dati carta)
        const errorElement = document.getElementById('card-errors');
        if (errorElement) {
            errorElement.textContent = error.message;
            errorElement.classList.add('show');
        }
        throw new Error(error.message);
    }

    // Pagamento completato con successo
    if (paymentIntent && paymentIntent.status === 'succeeded') {
        // Notifica il backend del pagamento completato (solo ID, nessun dato carta)
        await notifyBackendPaymentComplete(paymentIntent.id);
        showSuccessModal();
    } else if (paymentIntent && paymentIntent.status === 'requires_action') {
        // 3D Secure o altra azione richiesta - Stripe gestisce automaticamente
        showNotification('Azione aggiuntiva richiesta. Segui le istruzioni.', 'warning');
    }
}

/**
 * Notifica il backend del completamento pagamento
 * SICUREZZA: Invia solo payment_intent_id, MAI dati carta
 */
async function notifyBackendPaymentComplete(paymentIntentId) {
    const csrfToken = getCSRFToken();

    const response = await fetch(PAYMENT_CONFIG.API_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            action: 'confirm_stripe_payment',
            booking_id: bookingData.booking_id,
            payment_intent_id: paymentIntentId,
            method: 'card',
            csrf_token: csrfToken
        })
    });

    if (!response.ok) {
        console.error('Errore notifica backend:', await response.text());
        // Il pagamento Stripe è comunque andato a buon fine
        // Il backend sincronizzerà via webhook
    }
}

/**
 * Processa conferma IBAN
 * SICUREZZA: Non inviamo amount - il backend lo recupera dal DB (Zero-Trust)
 */
async function processIBANPayment() {
    const csrfToken = getCSRFToken();

    const paymentData = {
        booking_id: bookingData.booking_id,
        method: 'iban',
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

    showSuccessModal(true);
}

/**
 * Mostra modal successo
 */
function showSuccessModal(isIBAN = false) {
    const modal = document.getElementById('successModal');

    setElementText('confirmBookingId', bookingData.booking_id);
    setElementText('confirmAmount', formatPrice(bookingData.totalPrice));
    setElementText('confirmEmail', bookingData.email);

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

document.addEventListener('DOMContentLoaded', async function() {
    // Popola riepilogo
    if (!populateSummary()) return;

    // Avvia timer
    startSessionTimer();

    // Inizializza Stripe Elements (per pagamento carta)
    await initializeStripe();

    // Event listeners metodi pagamento
    document.querySelectorAll('.method-btn').forEach(btn => {
        btn.addEventListener('click', () => switchPaymentMethod(btn.dataset.method));
    });

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
});
