<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $productId = (int)$_POST['product_id'];
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
    $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
    $stmt->execute([$userId, $productId, $quantity]);
    setFlash('success', 'Produit ajouté au panier');
    redirect(BASE_URL . '/cart.php');
}

if (isset($_GET['remove'])) {
    $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?")->execute([(int)$_GET['remove'], $userId]);
    redirect(BASE_URL . '/cart.php');
}

$items = $pdo->prepare("SELECT c.*, p.name, p.price, p.stock, p.slug, pi.image_path FROM cart c JOIN products p ON c.product_id = p.id LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1 WHERE c.user_id = ?");
$items->execute([$userId]);
$items = $items->fetchAll();

$total = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Panier - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af', secondary: '#f97316' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="max-w-5xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-6"><i class="fas fa-shopping-cart text-blue-600 mr-2"></i>Mon Panier</h1>
        <?php if (empty($items)): ?>
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-12 text-center">
                <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 mb-4">Votre panier est vide</p>
                <a href="products.php" class="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg">Voir les produits</a>
            </div>
        <?php else: ?>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm overflow-hidden mb-6">
                <?php foreach ($items as $item): ?>
                    <div class="flex gap-4 p-4 border-b dark:border-gray-700">
                        <img src="<?= $item['image_path'] ?? 'https://via.placeholder.com/100' ?>" class="w-24 h-24 object-cover rounded-lg">
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-800 dark:text-white"><?= clean($item['name']) ?></h3>
                            <p class="text-blue-600 font-bold"><?= formatPrice($item['price']) ?></p>
                            <p class="text-sm text-gray-500">Quantité : <?= $item['quantity'] ?></p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-lg"><?= formatPrice($item['price'] * $item['quantity']) ?></p>
                            <a href="?remove=<?= $item['id'] ?>" class="text-red-500 text-sm mt-2 inline-block"><i class="fas fa-trash"></i> Supprimer</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm">
                <div class="flex justify-between text-xl font-bold mb-4">
                    <span>Total :</span>
                    <span class="text-blue-600"><?= formatPrice($total) ?></span>
                </div>
                <a href="checkout.php" class="block w-full py-3 bg-orange-500 text-white rounded-lg font-semibold text-center hover:bg-orange-600">Commander</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>