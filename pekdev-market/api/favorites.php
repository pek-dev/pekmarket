<?php
require_once __DIR__ . '/../config/bootstrap.php';






header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$userId = $_SESSION['user_id'];
$productId = (int)($_POST['product_id'] ?? 0);

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Produit invalide']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND product_id = ?");
$stmt->execute([$userId, $productId]);

if ($stmt->fetch()) {
    $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?")->execute([$userId, $productId]);
    echo json_encode(['success' => true, 'action' => 'removed']);
} else {
    $pdo->prepare("INSERT INTO favorites (user_id, product_id) VALUES (?, ?)")->execute([$userId, $productId]);
    echo json_encode(['success' => true, 'action' => 'added']);
}