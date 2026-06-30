<?php
/**
 * Configuration des constantes globales
 */

// URL de base du site
define('BASE_URL', 'http://localhost/pekdev/pekdevmarket');
define('ASSETS_URL', BASE_URL . '/assets');



// Informations du site
define('SITE_NAME', 'PekDev Market');
define('SITE_EMAIL', 'contact@pekdev.bi');
define('SITE_PHONE', '+257 67 301 044');

// Chemins
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// Devise
define('CURRENCY', 'FBu');
define('CURRENCY_SYMBOL', 'FBu');

// Pagination
define('ITEMS_PER_PAGE', 12);
define('PRODUCTS_PER_PAGE', 12);

// Timezone
date_default_timezone_set('Africa/Bujumbura');

// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}