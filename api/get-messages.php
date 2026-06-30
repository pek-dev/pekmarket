<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$userId = $_SESSION['user_id'];
$otherUserId = intval($_GET['with'] ?? 0);

if ($otherUserId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID invalide']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT m.*, 
               u.first_name, u.last_name
        FROM messages m
        JOIN users u ON m.from_user_id = u.id
        WHERE (m.from_user_id = ? AND m.to_user_id = ?) 
           OR (m.from_user_id = ? AND m.to_user_id = ?)
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$userId, $otherUserId, $otherUserId, $userId]);
    $messages = $stmt->fetchAll();
    
    // Marquer comme lus
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE from_user_id = ? AND to_user_id = ? AND is_read = 0")
        ->execute([$otherUserId, $userId]);
    
    echo json_encode(['success' => true, 'messages' => $messages]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}