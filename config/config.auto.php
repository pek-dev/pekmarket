<?php
/**
 * Configuration automatique - Détecte le chemin et l'URL automatiquement
 */

// Détection automatique du chemin du projet
define('BASE_PATH', dirname(__DIR__));

// Détection automatique de l'URL de base
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

// Nettoyer le chemin (enlever /config si appelé depuis config/)
$scriptDir = rtrim(str_replace('/config', '', $scriptDir), '/');
if (empty($scriptDir) || $scriptDir === '\\') $scriptDir = '';

define('BASE_URL', $protocol . '://' . $host . $scriptDir);
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');
define('UPLOADS_PATH', BASE_PATH . '/uploads');

// Autres constantes
define('ITEMS_PER_PAGE', 12);
define('MAX_FILE_SIZE', 5242880); // 5MB
define('CURRENCY', 'FBu');
define('SESSION_LIFETIME', 86400);
define('CSRF_TOKEN_NAME', 'csrf_token');

// Timezone
date_default_timezone_set('Africa/Bujumbura');

// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_start();
}

// Afficher les infos de debug (à désactiver en production)
if (isset($_GET['debug'])) {
    echo "<pre style='background:#000;color:#0f0;padding:10px;font-family:monospace;'>";
    echo "BASE_PATH: " . BASE_PATH . "\n";
    echo "BASE_URL: " . BASE_URL . "\n";
    echo "UPLOADS_PATH: " . UPLOADS_PATH . "\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "</pre>";
}