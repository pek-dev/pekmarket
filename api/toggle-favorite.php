<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$productId = intval($input['product_id'] ?? 0);

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Produit invalide']);
    exit;
}

try {
    // Vérifier si déjà en favori
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$_SESSION['user_id'], $productId]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Retirer des favoris
        $pdo->prepare("DELETE FROM favorites WHERE id = ?")->execute([$existing['id']]);
        echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Retiré des favoris']);
    } else {
        // Ajouter aux favoris
        $pdo->prepare("INSERT INTO favorites (user_id, product_id) VALUES (?, ?)")
            ->execute([$_SESSION['user_id'], $productId]);
        echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Ajouté aux favoris']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}