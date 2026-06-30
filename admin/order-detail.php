<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once __DIR__ . '/includes/header.php';

requireAdmin();

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) redirect(BASE_URL . '/admin/orders.php');

$stmt = $pdo->prepare("
    SELECT o.*, u.first_name, u.last_name, u.email, u.phone as user_phone
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Commande introuvable');
    redirect(BASE_URL . '/admin/orders.php');
}

$items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items->execute([$orderId]);
$items = $items->fetchAll();

// Mise à jour statut
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $status = $_POST['status'];
    $allowed = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
    
    if (in_array($status, $allowed)) {
        $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $orderId]);
        
        // Si payé
        if ($status === 'delivered') {
            $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")->execute([$orderId]);
        }
        
        setFlash('success', 'Statut mis à jour');
    }
    redirect($_SERVER['REQUEST_URI']);
}

$pageTitle = 'Commande ' . $order['order_number'];
require_once '../includes/header.php';

$statusColors = [
    'pending' => 'bg-yellow-100 text-yellow-800',
    'confirmed' => 'bg-blue-100 text-blue-800',
    'processing' => 'bg-purple-100 text-purple-800',
    'shipped' => 'bg-indigo-100 text-indigo-800',
    'delivered' => 'bg-green-100 text-green-800',
    'cancelled' => 'bg-red-100 text-red-800'
];
$statusLabels = [
    'pending' => 'En attente', 'confirmed' => 'Confirmée', 'processing' => 'En traitement',
    'shipped' => 'Expédiée', 'delivered' => 'Livrée', 'cancelled' => 'Annulée'
];
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <a href="orders.php" class="text-blue-600 hover:underline mb-4 inline-block">
        <i class="fas fa-arrow-left mr-1"></i>Retour aux commandes
    </a>

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm overflow-hidden">
        <!-- Header -->
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex flex-wrap justify-between items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Commande <?= clean($order['order_number']) ?></h1>
                <p class="text-sm text-gray-500 mt-1">Passée le <?= formatDate($order['created_at'], 'd/m/Y à H:i') ?></p>
            </div>
            <form method="POST" class="flex items-center gap-2">
                <select name="status" class="px-3 py-2 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                    <?php foreach ($statusLabels as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $order['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="update_status" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-800">
                    Mettre à jour
                </button>
            </form>
        </div>

        <div class="grid md:grid-cols-2 gap-6 p-6">
            <!-- Client -->
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <h3 class="font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                    <i class="fas fa-user text-blue-600"></i> Client
                </h3>
                <p class="font-semibold text-gray-800 dark:text-white"><?= clean($order['first_name'] . ' ' . $order['last_name'] ?? $order['shipping_first_name'] . ' ' . $order['shipping_last_name']) ?></p>
                <p class="text-sm text-gray-600 dark:text-gray-300"><i class="fas fa-envelope w-5"></i><?= clean($order['email']) ?></p>
                <p class="text-sm text-gray-600 dark:text-gray-300"><i class="fas fa-phone w-5"></i><?= clean($order['shipping_phone']) ?></p>
            </div>

            <!-- Livraison -->
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <h3 class="font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                    <i class="fas fa-truck text-blue-600"></i> Adresse de livraison
                </h3>
                <p class="text-sm text-gray-700 dark:text-gray-200">
                    <?= clean($order['shipping_first_name'] . ' ' . $order['shipping_last_name']) ?><br>
                    <?= clean($order['shipping_address']) ?><br>
                    <?= clean($order['shipping_city'] . ', ' . $order['shipping_province']) ?>
                </p>
            </div>
        </div>

        <!-- Items -->
        <div class="p-6 border-t border-gray-200 dark:border-gray-700">
            <h3 class="font-bold text-gray-800 dark:text-white mb-4">Articles (<?= count($items) ?>)</h3>
            <div class="space-y-3">
                <?php foreach ($items as $item): ?>
                    <div class="flex gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <img src="<?= $item['product_image'] ?? 'https://via.placeholder.com/80' ?>" class="w-16 h-16 object-cover rounded" alt="">
                        <div class="flex-1">
                            <p class="font-semibold text-gray-800 dark:text-white"><?= clean($item['product_name']) ?></p>
                            <p class="text-sm text-gray-500"><?= $item['quantity'] ?> x <?= formatPrice($item['price']) ?></p>
                        </div>
                        <p class="font-bold text-blue-600"><?= formatPrice($item['total']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Total -->
        <div class="p-6 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            <div class="max-w-sm ml-auto space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">Sous-total</span>
                    <span class="font-semibold"><?= formatPrice($order['subtotal']) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">Livraison</span>
                    <span class="font-semibold"><?= formatPrice($order['shipping_cost']) ?></span>
                </div>
                <div class="flex justify-between text-lg font-bold pt-2 border-t border-gray-200 dark:border-gray-700">
                    <span>Total</span>
                    <span class="text-blue-600"><?= formatPrice($order['total']) ?></span>
                </div>
                <div class="flex justify-between text-sm pt-2">
                    <span class="text-gray-600 dark:text-gray-400">Paiement</span>
                    <span class="px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded text-xs font-semibold"><?= $order['payment_method'] ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">Statut paiement</span>
                    <span class="px-2 py-1 <?= $order['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?> rounded text-xs font-semibold">
                        <?= $order['payment_status'] === 'paid' ? 'Payé' : 'En attente' ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if ($order['notes']): ?>
            <div class="p-6 border-t border-gray-200 dark:border-gray-700">
                <h3 class="font-bold text-gray-800 dark:text-white mb-2">Notes du client</h3>
                <p class="text-gray-600 dark:text-gray-300 text-sm bg-yellow-50 dark:bg-yellow-900/20 p-3 rounded-lg">
                    <?= nl2br(clean($order['notes'])) ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>