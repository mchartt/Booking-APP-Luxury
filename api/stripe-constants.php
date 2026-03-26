<?php
/**
 * api/stripe-constants.php - Costanti condivise per i pagamenti Stripe
 *
 * Centralizza le costanti Stripe usate da stripe-config.php e payments.php
 * per evitare valori hardcoded duplicati che potrebbero divergere.
 */

if (!defined('STRIPE_DEFAULT_CURRENCY')) {
    /**
     * Valuta di default per tutti i pagamenti Stripe.
     * Le prenotazioni sono memorizzate in EUR nel database.
     * Codice ISO 4217 in lowercase, come richiesto dall'API Stripe.
     */
    define('STRIPE_DEFAULT_CURRENCY', 'eur');
}
