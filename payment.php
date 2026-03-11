<?php
/**
 * Payment Page - Luxury Hotel
 * SICUREZZA: Genera token CSRF lato server e lo inietta nel meta tag
 * HARDENING: CSP ultra-restrittiva per pagina pagamenti (anti-XSS, anti-Formjacking)
 */

// Includi gli header di sicurezza e inizializza la sessione
require_once __DIR__ . '/api/security_headers.php';

// ===== PAYMENT PAGE: CSP ULTRA-RESTRITTIVA =====
// Sovrascrive la CSP generica con una versione specifica per pagamenti
// Previene XSS, Formjacking, Clickjacking e data exfiltration

// Ottieni il nonce generato da security_headers.php
$nonce = CSP_NONCE;

// CSP restrittiva per pagina pagamenti
$paymentCsp = implode('; ', [
    // Default: blocca tutto tranne 'self'
    "default-src 'self'",

    // Script: SOLO file esterni specifici + nonce (NO 'unsafe-inline', NO 'unsafe-eval')
    "script-src 'self' 'nonce-{$nonce}' https://js.stripe.com",

    // Stili: SOLO file esterni + nonce per stili inline necessari
    "style-src 'self' 'nonce-{$nonce}' https://fonts.googleapis.com https://cdnjs.cloudflare.com",

    // Font: Google Fonts + Font Awesome
    "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:",

    // Immagini: solo dal nostro dominio + data URI per icone inline
    "img-src 'self' data: https:",

    // Connessioni API: SOLO nostro backend + Stripe API
    "connect-src 'self' https://api.stripe.com https://maps.googleapis.com",

    // Frame: Stripe Elements usa iframe per PCI compliance
    "frame-src https://js.stripe.com https://hooks.stripe.com",

    // CLICKJACKING PREVENTION: nessuno può incapsulare questa pagina
    "frame-ancestors 'none'",

    // Form: SOLO submit al nostro dominio
    "form-action 'self'",

    // Base URI: previene attacchi base tag injection
    "base-uri 'self'",

    // Object/Embed: completamente disabilitati
    "object-src 'none'",

    // Worker: disabilitati (non necessari per pagamenti)
    "worker-src 'none'",

    // Manifest: solo dal nostro dominio
    "manifest-src 'self'",

    // Report violations (opzionale - configura endpoint per logging)
    // "report-uri /api/csp-report.php"
]);

// Applica CSP restrittiva (sovrascrive quella generica)
header("Content-Security-Policy: {$paymentCsp}", true);

// ===== HEADER SICUREZZA AGGIUNTIVI PER PAGAMENTI =====

// X-Content-Type-Options: previene MIME sniffing
header('X-Content-Type-Options: nosniff', true);

// X-Frame-Options: protezione clickjacking legacy (backup per CSP frame-ancestors)
header('X-Frame-Options: DENY', true);

// X-XSS-Protection: protezione XSS browser legacy
header('X-XSS-Protection: 1; mode=block', true);

// Referrer-Policy: non inviare referrer a terze parti (protegge booking_id in URL)
header('Referrer-Policy: strict-origin-when-cross-origin', true);

// Permissions-Policy: disabilita API browser non necessarie per pagamenti
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(*), usb=()', true);

// Cache-Control: NON cachare pagine di pagamento (dati sensibili)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
header('Pragma: no-cache', true);
header('Expires: 0', true);

// HSTS: forza HTTPS (già in security_headers.php, ma rinforziamo)
if (!in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8080'])) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload', true);
}

// Cross-Origin headers per isolamento
header('Cross-Origin-Opener-Policy: same-origin', true);
header('Cross-Origin-Embedder-Policy: credentialless', true);

// Genera token CSRF crittograficamente sicuro se non esiste
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <!-- CSRF Token - Generato dal server, letto dal JavaScript -->
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Pagamento - Luxury Hotel</title>

    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Lora:wght@400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="payment.css">

    <!-- Stripe.js - PCI DSS Compliant (caricato dal CDN Stripe) -->
    <script src="https://js.stripe.com/v3/" nonce="<?php echo htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8'); ?>"></script>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="navbar">
                <a href="index.html" class="logo">
                    <i class="fas fa-hotel"></i> Luxury Hotel
                </a>
                <nav class="nav">
                    <a href="index.html">Home</a>
                    <a href="index.html#rooms">Stanze</a>
                    <a href="index.html#booking">Prenota</a>
                    <a href="index.html#contact">Contatti</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Payment Section -->
    <section class="payment-page">
        <div class="container">
            <div class="payment-header">
                <h1><i class="fas fa-credit-card"></i> Pagamento Sicuro</h1>
                <p>Completa il pagamento per confermare la tua prenotazione</p>
            </div>

            <!-- Timer sessione -->
            <div class="payment-timer">
                <i class="fas fa-clock"></i>
                <span>Tempo rimanente: <span class="timer-value">15:00</span></span>
            </div>

            <div class="payment-container">
                <!-- Riepilogo Prenotazione -->
                <div class="booking-summary-card">
                    <h3><i class="fas fa-receipt"></i> Riepilogo Prenotazione</h3>

                    <div class="summary-details">
                        <div class="summary-item">
                            <span class="label">ID Prenotazione</span>
                            <span class="value" id="summaryBookingId">-</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Camera</span>
                            <span class="value" id="summaryRoom">-</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Ospite</span>
                            <span class="value" id="summaryName">-</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Check-in</span>
                            <span class="value" id="summaryCheckIn">-</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Check-out</span>
                            <span class="value" id="summaryCheckOut">-</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Ospiti</span>
                            <span class="value" id="summaryGuests">-</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Notti</span>
                            <span class="value" id="summaryNights">-</span>
                        </div>
                    </div>

                    <div class="summary-total">
                        <span>Totale da Pagare</span>
                        <span class="total-amount" id="summaryTotal">€0</span>
                    </div>

                    <div class="secure-badge">
                        <i class="fas fa-shield-alt"></i>
                        <span>Pagamento sicuro con crittografia SSL a 256-bit</span>
                    </div>
                </div>

                <!-- Form Pagamento -->
                <div class="payment-form-card">
                    <h3><i class="fas fa-lock"></i> Metodo di Pagamento</h3>

                    <!-- Tabs metodi pagamento -->
                    <div class="payment-methods">
                        <button type="button" class="method-btn active" data-method="card">
                            <i class="fas fa-credit-card"></i> Carta
                        </button>
                        <button type="button" class="method-btn" data-method="paypal">
                            <i class="fab fa-paypal"></i> PayPal
                        </button>
                        <button type="button" class="method-btn" data-method="iban">
                            <i class="fas fa-university"></i> Bonifico
                        </button>
                    </div>

                    <form id="paymentForm" class="payment-form" novalidate>
                        <!-- ===== CARTA DI CREDITO - STRIPE ELEMENTS (PCI DSS COMPLIANT) ===== -->
                        <div id="cardPayment" class="payment-method-content">
                            <!-- Info sicurezza -->
                            <div class="stripe-secure-info">
                                <i class="fas fa-shield-alt"></i>
                                <span>I dati della tua carta sono gestiti in modo sicuro da Stripe. Nessun dato sensibile passa attraverso i nostri server.</span>
                            </div>

                            <!-- Stripe Card Element - L'iframe sicuro di Stripe -->
                            <div class="form-group">
                                <label for="card-element">Dati Carta *</label>
                                <div id="card-element" class="stripe-element">
                                    <!-- Stripe Elements inserirà qui l'iframe sicuro -->
                                </div>
                                <div id="card-errors" class="error-message" role="alert"></div>
                            </div>

                            <!-- Loghi carte accettate -->
                            <div class="accepted-cards">
                                <span>Accettiamo:</span>
                                <i class="fab fa-cc-visa" title="Visa"></i>
                                <i class="fab fa-cc-mastercard" title="Mastercard"></i>
                                <i class="fab fa-cc-amex" title="American Express"></i>
                            </div>
                        </div>

                        <!-- ===== PAYPAL - HOSTED BUTTONS (PCI COMPLIANT) ===== -->
                        <div id="paypalPayment" class="payment-method-content" style="display: none;">
                            <div class="paypal-info">
                                <i class="fab fa-paypal"></i>
                                <p>Paga in modo sicuro con il tuo conto PayPal o con carta.</p>
                            </div>
                            <!-- PayPal Button Container - Il pulsante viene iniettato da PayPal JS SDK -->
                            <div id="paypal-button-container"></div>
                        </div>

                        <!-- ===== BONIFICO IBAN ===== -->
                        <div id="ibanPayment" class="payment-method-content" style="display: none;">
                            <div class="iban-info">
                                <div class="bank-icon">
                                    <i class="fas fa-university"></i>
                                </div>
                                <h4>Coordinate Bancarie per Bonifico</h4>

                                <div class="iban-details">
                                    <div class="iban-row">
                                        <span class="iban-label">Beneficiario</span>
                                        <span class="iban-value" id="ibanBeneficiary">Luxury Hotel S.r.l.</span>
                                    </div>
                                    <div class="iban-row">
                                        <span class="iban-label">IBAN</span>
                                        <span class="iban-value">
                                            <span id="ibanNumber">IT60 X054 2811 1010 0000 0123 456</span>
                                            <button type="button" class="copy-btn" title="Copia IBAN">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </span>
                                    </div>
                                    <div class="iban-row">
                                        <span class="iban-label">BIC/SWIFT</span>
                                        <span class="iban-value">
                                            <span id="ibanBIC">BPPIITRRXXX</span>
                                            <button type="button" class="copy-btn" title="Copia BIC">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </span>
                                    </div>
                                    <div class="iban-row">
                                        <span class="iban-label">Causale</span>
                                        <span class="iban-value">
                                            <span id="ibanCausale">-</span>
                                            <button type="button" class="copy-btn" title="Copia causale">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </span>
                                    </div>
                                </div>

                                <div class="iban-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <div>
                                        <strong>Importante:</strong> La prenotazione sarà confermata solo dopo la ricezione del bonifico (1-3 giorni lavorativi).
                                        Indica sempre l'ID prenotazione come causale.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== TERMINI E CONDIZIONI ===== -->
                        <div class="form-group" style="margin-top: var(--space-lg);">
                            <label class="checkbox-label">
                                <input type="checkbox" id="acceptTerms" name="acceptTerms" required>
                                <span>
                                    Accetto i <a href="#" class="link" onclick="return false;">Termini e Condizioni</a>
                                    e la <a href="#" class="link" onclick="return false;">Privacy Policy</a> *
                                </span>
                            </label>
                        </div>

                        <!-- ===== PULSANTE PAGAMENTO ===== -->
                        <button type="submit" class="btn btn-primary btn-large btn-pay" id="payBtn">
                            <span class="btn-text">
                                <i class="fas fa-lock"></i> Paga Ora <span id="btnAmount">€0</span>
                            </span>
                            <span class="btn-loader" style="display: none;">
                                <i class="fas fa-spinner fa-spin"></i> Elaborazione...
                            </span>
                        </button>
                    </form>

                    <!-- Badge sicurezza -->
                    <div class="payment-security">
                        <div class="security-item">
                            <i class="fas fa-lock"></i>
                            <span>SSL 256-bit</span>
                        </div>
                        <div class="security-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>PCI DSS</span>
                        </div>
                        <div class="security-item">
                            <i class="fas fa-check-circle"></i>
                            <span>3D Secure</span>
                        </div>
                        <div class="security-item">
                            <i class="fas fa-user-shield"></i>
                            <span>GDPR</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner"></div>
        <p>Elaborazione pagamento in corso...</p>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <div class="modal-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2>Pagamento Completato!</h2>
            <p>La tua prenotazione è stata confermata con successo.</p>

            <div class="confirmation-details">
                <div class="detail-row">
                    <span>ID Prenotazione:</span>
                    <strong id="confirmBookingId">-</strong>
                </div>
                <div class="detail-row">
                    <span>Importo Pagato:</span>
                    <strong id="confirmAmount">-</strong>
                </div>
            </div>

            <p class="email-notice">
                <i class="fas fa-envelope"></i>
                Una email di conferma è stata inviata a <strong id="confirmEmail">-</strong>
            </p>

            <a href="index.html" class="btn btn-primary">
                <i class="fas fa-home"></i> Torna alla Home
            </a>
        </div>
    </div>

    <!-- Notification Toast -->
    <div id="notification" class="notification"></div>

    <!-- JavaScript -->
    <script src="payment.js" nonce="<?php echo htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
