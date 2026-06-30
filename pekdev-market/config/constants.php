<?php
define('BASE_URL', 'http://localhost/pekdev-market');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');
define('BASE_PATH', __DIR__ . '/..');
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('ITEMS_PER_PAGE', 12);
define('MAX_FILE_SIZE', 5242880);
define('CURRENCY', 'FBu');
define('SESSION_LIFETIME', 86400);
define('CSRF_TOKEN_NAME', 'csrf_token');

date_default_timezone_set('Africa/Bujumbura');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}