<?php
require_once __DIR__ . '/config/bootstrap.php';



// Supprimer les cookies "Se souvenir de moi"
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}
if (isset($_COOKIE['remember_email'])) {
    setcookie('remember_email', '', time() - 3600, '/', '', false, true);
}

// Détruire la session
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redirection vers la page de connexion
header('Location: ' . BASE_URL . '/login.php?logout=1');
exit;