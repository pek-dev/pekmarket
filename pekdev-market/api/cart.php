<?php
require_once __DIR__ . '/../config/bootstrap.php';






header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$userId = $_SESSION['user_id'];
$productId = (int)($_POST['product_id'] ?? 0);
$quantity = max(1, (int)($_POST['quantity'] ?? 1));

$stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
$stmt->execute([$userId, $productId, $quantity]);

$count = getCartCount($userId, $pdo);
echo json_encode(['success' => true, 'count' => $count, 'message' => 'Produit ajouté']);