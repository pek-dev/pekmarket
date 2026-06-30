<?php
require_once __DIR__ . '/../config/bootstrap.php';

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$fromUserId = $_SESSION['user_id'];
$toUserId = intval($_POST['to_user_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
$productId = intval($_POST['product_id'] ?? 0) ?: null;

if ($toUserId <= 0) {
    header('Location: ' . BASE_URL . '/messages.php');
    exit;
}

if (empty($message) || strlen($message) < 1) {
    header('Location: ' . BASE_URL . '/messages.php?with=' . $toUserId . '&error=empty');
    exit;
}

if (strlen($message) > 2000) {
    $message = substr($message, 0, 2000);
}

try {
    // Vérifier que le destinataire existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$toUserId]);
    if (!$stmt->fetch()) {
        header('Location: ' . BASE_URL . '/messages.php?error=user_not_found');
        exit;
    }
    
    // Empêcher l'envoi à soi-même
    if ($toUserId == $fromUserId) {
        header('Location: ' . BASE_URL . '/messages.php?error=self_message');
        exit;
    }
    
    // Insérer le message
    $stmt = $pdo->prepare("
        INSERT INTO messages (from_user_id, to_user_id, product_id, message, is_read, created_at) 
        VALUES (?, ?, ?, ?, 0, NOW())
    ");
    $stmt->execute([$fromUserId, $toUserId, $productId, $message]);
    
    header('Location: ' . BASE_URL . '/messages.php?with=' . $toUserId . '#bottom');
    exit;
    
} catch (Exception $e) {
    header('Location: ' . BASE_URL . '/messages.php?with=' . $toUserId . '&error=server');
    exit;
}