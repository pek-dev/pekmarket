<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';



requireLogin();
$userId = $_SESSION['user_id'];
$user = getCurrentUser($pdo);

$items = $pdo->prepare("SELECT c.*, p.name, p.price, p.stock, pi.image_path FROM cart c JOIN products p ON c.product_id = p.id LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1 WHERE c.user_id = ?");
$items->execute([$userId]);
$items = $items->fetchAll();

if (empty($items)) redirect(BASE_URL . '/cart.php');

$subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
$shipping = $subtotal >= 100000 ? 0 : 5000;
$total = $subtotal + $shipping;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        $orderNumber = 'PKD-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, order_number, status, payment_method, subtotal, shipping_cost, total, shipping_first_name, shipping_last_name, shipping_phone, shipping_province, shipping_city, shipping_address) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $orderNumber, $_POST['payment_method'] ?? 'cash', $subtotal, $shipping, $total, clean($_POST['first_name']), clean($_POST['last_name']), clean($_POST['phone']), clean($_POST['province']), clean($_POST['city']), clean($_POST['address'])]);
        $orderId = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_image, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $stmt->execute([$orderId, $item['product_id'], $item['name'], $item['image_path'], $item['quantity'], $item['price'], $item['price'] * $item['quantity']]);
            $pdo->prepare("UPDATE products SET stock = stock - ?, sales_count = sales_count + ? WHERE id = ?")->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
        }
        
        $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);
        $pdo->commit();
        
        setFlash('success', "Commande $orderNumber créée !");
        redirect(BASE_URL . "/profile.php");
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Erreur : ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Commande - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af', secondary: '#f97316' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="max-w-5xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-6">Finaliser la commande</h1>
        <form method="POST" class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm">
                    <h3 class="font-bold mb-4">Adresse de livraison</h3>
                    <div class="grid md:grid-cols-2 gap-4">
                        <input type="text" name="first_name" required value="<?= clean($user['first_name'] ?? '') ?>" placeholder="Prénom" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                        <input type="text" name="last_name" required value="<?= clean($user['last_name'] ?? '') ?>" placeholder="Nom" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                        <input type="tel" name="phone" required value="<?= clean($user['phone'] ?? '') ?>" placeholder="Téléphone" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                        <input type="text" name="province" required value="<?= clean($user['province'] ?? '') ?>" placeholder="Province" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                        <input type="text" name="city" required value="<?= clean($user['city'] ?? '') ?>" placeholder="Ville" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                        <input type="text" name="address" required placeholder="Adresse complète" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg md:col-span-2">
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm">
                    <h3 class="font-bold mb-4">Mode de paiement</h3>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 p-3 border-2 rounded-lg cursor-pointer hover:border-blue-600">
                            <input type="radio" name="payment_method" value="cash" checked> <i class="fas fa-money-bill text-green-500"></i> Paiement à la livraison
                        </label>
                        <label class="flex items-center gap-3 p-3 border-2 rounded-lg cursor-pointer hover:border-blue-600">
                            <input type="radio" name="payment_method" value="mobile_money"> <i class="fas fa-mobile-alt text-blue-500"></i> Mobile Money
                        </label>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm h-fit">
                <h3 class="font-bold mb-4">Résumé</h3>
                <div class="space-y-2 mb-4">
                    <?php foreach ($items as $item): ?>
                        <div class="flex justify-between text-sm">
                            <span><?= clean($item['name']) ?> x<?= $item['quantity'] ?></span>
                            <span class="font-semibold"><?= formatPrice($item['price'] * $item['quantity']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="border-t pt-3 space-y-2">
                    <div class="flex justify-between text-sm"><span>Sous-total</span><span><?= formatPrice($subtotal) ?></span></div>
                    <div class="flex justify-between text-sm"><span>Livraison</span><span><?= $shipping === 0 ? 'Gratuite' : formatPrice($shipping) ?></span></div>
                    <div class="flex justify-between text-lg font-bold pt-2 border-t"><span>Total</span><span class="text-blue-600"><?= formatPrice($total) ?></span></div>
                </div>
                <button type="submit" class="w-full py-3 bg-orange-500 text-white rounded-lg font-semibold mt-4 hover:bg-orange-600">Confirmer la commande</button>
            </div>
        </form>
    </div>
</body>
</html>