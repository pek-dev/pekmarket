<?php
require_once __DIR__ . '/../config/bootstrap.php';




header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$adId = intval($input['ad_id'] ?? 0);

if ($adId > 0) {
    // Incrémenter les clics
    $pdo->prepare("UPDATE ad_campaigns SET clicks = clicks + 1, spent = spent + cost_per_click WHERE id = ?")->execute([$adId]);
    
    // Vérifier si le budget est dépassé
    $pdo->prepare("
        UPDATE ad_campaigns 
        SET status = 'expired' 
        WHERE id = ? AND budget_total > 0 AND spent >= budget_total
    ")->execute([$adId]);
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid ad ID']);
}