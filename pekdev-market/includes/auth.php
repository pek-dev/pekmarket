<?php
require_once __DIR__ . '/../config/bootstrap.php';



function loginUser($email, $password, $pdo) {
    if (empty($email) || empty($password)) {
        return ['success' => false, 'message' => 'Veuillez remplir tous les champs'];
    }
    
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, password, role, avatar, is_active FROM users WHERE email = ?");
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();
    
    if (!$user) return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
    if (!$user['is_active']) return ['success' => false, 'message' => 'Compte désactivé'];
    if (!password_verify($password, $user['password'])) return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
    
    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_avatar'] = $user['avatar'];
    
    return ['success' => true, 'user' => $user];
}

function logoutUser() {
    $_SESSION = [];
    session_destroy();
}

function getCurrentUser($pdo) {
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function requireLogin($redirectUrl = null) {
    if (!isLoggedIn()) {
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Veuillez vous connecter'];
        header("Location: " . ($redirectUrl ?? BASE_URL . '/login.php'));
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Accès refusé'];
        header("Location: " . BASE_URL);
        exit;
    }
}