<?php
/**
 * security_headers.php - Security headers e sessione centralizzati
 * Include questo file all'inizio di ogni API PHP
 *
 * IMPORTANTE: Questo file gestisce l'inizializzazione sicura della sessione.
 * NON chiamare session_start() negli altri file API.
 */

// Determina ambiente (localhost vs produzione)
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8080', '127.0.0.1:8080']);

// ===== INIZIALIZZAZIONE SESSIONE SICURA =====
// Centralizzata qui per evitare duplicazioni e garantire consistenza

if (session_status() === PHP_SESSION_NONE) {
    // Determina se la connessione è HTTPS
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

    // Configura cookie di sessione sicuri
    session_set_cookie_params([
        'lifetime' => 0,           // Cookie di sessione (scade alla chiusura del browser)
        'path' => '/',             // Accessibile da tutto il sito
        'domain' => '',            // Dominio corrente
        'secure' => $isSecure,     // Solo HTTPS in produzione
        'httponly' => true,        // Non accessibile via JavaScript (previene XSS)
        'samesite' => 'Strict'     // Previene CSRF cross-site
    ]);

    session_start();
}

// ===== HTTPS REDIRECT =====
// Forza HTTPS in produzione
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    if (!$isLocalhost && !headers_sent()) {
        $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header("Location: $redirect", true, 301);
        exit;
    }
}

// ===== SECURITY HEADERS =====

// Previene MIME type sniffing
header('X-Content-Type-Options: nosniff');

// Previene clickjacking
header('X-Frame-Options: DENY');

// XSS Protection (legacy browsers)
header('X-XSS-Protection: 1; mode=block');

// Referrer Policy
header('Referrer-Policy: strict-origin-when-cross-origin');

// Permissions Policy (disabilita funzionalità non necessarie)
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');

// HTTP Strict Transport Security (HSTS) - Forza HTTPS lato client
if (!$isLocalhost) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// Content Security Policy
// NOTA: Configura in base alle risorse usate dal sito
$csp = "default-src 'self'; " .
       "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
       "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; " .
       "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
       "img-src 'self' data: https:; " .
       "connect-src 'self'; " .
       "frame-ancestors 'none'; " .
       "form-action 'self'; " .
       "base-uri 'self';";
header("Content-Security-Policy: $csp");

// ===== CORS RESTRITTIVO =====

/**
 * Imposta CORS headers in modo sicuro
 * @param array $allowedOrigins Lista di origin permessi (vuoto = stesso dominio)
 */
function setCorsHeaders($allowedOrigins = []) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    // Se non ci sono origin permessi, usa stesso dominio
    if (empty($allowedOrigins)) {
        // In sviluppo locale, permetti localhost
        $allowedOrigins = [
            'http://localhost',
            'http://localhost:8080',
            'http://localhost:3000',
            'http://127.0.0.1',
            'http://127.0.0.1:8080'
        ];

        // In produzione, aggiungi il dominio reale
        // $allowedOrigins[] = 'https://www.luxuryhotel.it';
    }

    // Verifica se origin è nella whitelist
    if (in_array($origin, $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
    }

    // Headers permessi
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');
    header('Access-Control-Max-Age: 86400'); // Cache preflight per 24h
}

// Applica CORS
setCorsHeaders();

// Gestisci preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
