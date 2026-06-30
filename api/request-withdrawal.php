<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

if ($_SESSION['user_role'] !== 'seller') {
    header('Location: ' . BASE_URL);
    exit;
}

$sellerId = $_SESSION['user_id'];
$amount = floatval($_POST['amount'] ?? 0);
$method = $_POST['method'] ?? 'mobile_money';

// Validation
if ($amount < 10000) {
    $_SESSION['flash_message'] = "❌ Le montant minimum est de 10 000 FBu.";
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . BASE_URL . '/seller/finance.php');
    exit;
}

if (!in_array($method, ['mobile_money', 'bank', 'cash'])) {
    $_SESSION['flash_message'] = "❌ Méthode de paiement invalide.";
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . BASE_URL . '/seller/finance.php');
    exit;
}

try {
    // Calculer le solde disponible
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COALESCE(SUM(oi.total), 0) FROM order_items oi JOIN products p ON oi.product_id = p.id JOIN orders o ON oi.order_id = o.id WHERE p.seller_id = ? AND o.payment_status = 'paid') as revenue,
            (SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE seller_id = ? AND status IN ('approved', 'completed')) as withdrawn,
            (SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE seller_id = ? AND status = 'pending') as pending
    ");
    $stmt->execute([$sellerId, $sellerId, $sellerId]);
    $stats = $stmt->fetch();
    
    $available = $stats['revenue'] * 0.95 - $stats['withdrawn'] - $stats['pending']; // 5% commission
    
    if ($amount > $available) {
        $_SESSION['flash_message'] = "❌ Solde insuffisant. Disponible: " . number_format($available, 0, ',', ' ') . " FBu";
        $_SESSION['flash_type'] = 'error';
        header('Location: ' . BASE_URL . '/seller/finance.php');
        exit;
    }
    
    // Récupérer les infos bancaires
    $stmt = $pdo->prepare("SELECT mobile_money_number, account_number, account_holder FROM users WHERE id = ?");
    $stmt->execute([$sellerId]);
    $seller = $stmt->fetch();
    
    // Créer la demande de retrait
    $stmt = $pdo->prepare("
        INSERT INTO withdrawals (seller_id, amount, method, account_number, account_name, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $sellerId,
        $amount,
        $method,
        $method === 'mobile_money' ? $seller['mobile_money_number'] : $seller['account_number'],
        $seller['account_holder']
    ]);
    
    $_SESSION['flash_message'] = "✅ Demande de retrait de " . number_format($amount, 0, ',', ' ') . " FBu soumise avec succès ! Un administrateur va la valider.";
    $_SESSION['flash_type'] = 'success';
    header('Location: ' . BASE_URL . '/seller/finance.php');
    exit;
    
} catch (Exception $e) {
    $_SESSION['flash_message'] = "❌ Erreur: " . $e->getMessage();
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . BASE_URL . '/seller/finance.php');
    exit;
}