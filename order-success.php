<?php
require_once __DIR__ . '/config/bootstrap.php';

require_once __DIR__ . '/includes/header.php';




if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$orderNumber = $_GET['order'] ?? '';

if (!$orderNumber) {
    header('Location: ' . BASE_URL);
    exit;
}

// Récupérer la commande
$orderStmt = $pdo->prepare("
    SELECT o.*, 
           COUNT(oi.id) as items_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.order_number = ? AND o.user_id = ?
    GROUP BY o.id
");
$orderStmt->execute([$orderNumber, $_SESSION['user_id']]);
$order = $orderStmt->fetch();

if (!$order) {
    header('Location: ' . BASE_URL);
    exit;
}

// Récupérer les articles
$itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemsStmt->execute([$order['id']]);
$items = $itemsStmt->fetchAll();

$pageTitle = 'Commande confirmée - ' . $orderNumber;

?>

<section class="py-12 md:py-16 bg-gradient-to-br from-green-50 to-blue-50 dark:from-gray-900 dark:to-gray-800 min-h-screen">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Success Card -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-green-500 to-green-600 p-8 text-center text-white">
                <div class="w-20 h-20 mx-auto bg-white rounded-full flex items-center justify-center mb-4 shadow-xl">
                    <i class="fas fa-check text-4xl text-green-600"></i>
                </div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2">Commande confirmée !</h1>
                <p class="text-green-100">Merci pour votre achat sur PekDev Market</p>
            </div>
            
            <div class="p-6 md:p-8">
                <!-- Order Info -->
                <div class="grid md:grid-cols-3 gap-4 mb-8">
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 text-center">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Numéro de commande</p>
                        <p class="font-bold text-blue-600 text-lg"><?= $order['order_number'] ?></p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 text-center">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Date</p>
                        <p class="font-semibold text-gray-800 dark:text-white"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 text-center">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Statut</p>
                        <span class="inline-block px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-semibold">
                            En attente
                        </span>
                    </div>
                </div>
                
                <!-- Payment Info -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-info-circle text-blue-500 text-xl mt-1"></i>
                        <div>
                            <h3 class="font-bold text-blue-900 dark:text-blue-200 mb-1">Mode de paiement : <?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?></h3>
                            <?php if ($order['payment_method'] === 'cash'): ?>
                                <p class="text-sm text-blue-800 dark:text-blue-300">Vous paierez en espèces à la livraison de votre commande.</p>
                            <?php elseif ($order['payment_method'] === 'mobile_money'): ?>
                                <p class="text-sm text-blue-800 dark:text-blue-300">Un agent vous contactera pour finaliser le paiement via Mobile Money.</p>
                            <?php else: ?>
                                <p class="text-sm text-blue-800 dark:text-blue-300">Le paiement par carte sera traité sous 24h.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Items -->
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Articles commandés</h3>
                <div class="space-y-3 mb-6">
                    <?php foreach ($items as $item): ?>
                        <div class="flex items-center gap-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                            <img src="<?= $item['product_image'] ?: 'https://via.placeholder.com/60' ?>" 
                                 alt="<?= clean($item['product_name']) ?>" 
                                 class="w-14 h-14 object-cover rounded-lg">
                            <div class="flex-1">
                                <p class="font-semibold text-gray-800 dark:text-white"><?= clean($item['product_name']) ?></p>
                                <p class="text-sm text-gray-500"><?= $item['quantity'] ?> x <?= formatPrice($item['price']) ?></p>
                            </div>
                            <p class="font-bold text-blue-600"><?= formatPrice($item['total']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Totals -->
                <div class="border-t dark:border-gray-700 pt-4 space-y-2">
                    <div class="flex justify-between text-gray-600 dark:text-gray-300">
                        <span>Sous-total</span>
                        <span><?= formatPrice($order['subtotal']) ?></span>
                    </div>
                    <div class="flex justify-between text-gray-600 dark:text-gray-300">
                        <span>Livraison</span>
                        <span><?= $order['shipping_cost'] == 0 ? 'Gratuite' : formatPrice($order['shipping_cost']) ?></span>
                    </div>
                    <div class="flex justify-between items-center pt-3 border-t dark:border-gray-700">
                        <span class="text-lg font-bold text-gray-800 dark:text-white">Total payé</span>
                        <span class="text-2xl font-bold text-blue-600"><?= formatPrice($order['total']) ?></span>
                    </div>
                </div>
                
                <!-- Delivery Address -->
                <div class="mt-6 bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                    <h4 class="font-bold text-gray-800 dark:text-white mb-2 flex items-center gap-2">
                        <i class="fas fa-map-marker-alt text-orange-500"></i> Adresse de livraison
                    </h4>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        <?= clean($order['shipping_first_name'] . ' ' . $order['shipping_last_name']) ?><br>
                        <?= clean($order['shipping_phone']) ?><br>
                        <?= clean($order['shipping_address']) ?><br>
                        <?= clean($order['shipping_city']) ?>, <?= clean($order['shipping_province']) ?>
                    </p>
                </div>
                
                <!-- Actions -->
                <div class="flex flex-col sm:flex-row gap-3 mt-8">
                    <a href="<?= BASE_URL ?>/orders.php" class="flex-1 bg-blue-600 text-white text-center py-3 rounded-xl font-semibold hover:bg-blue-800 transition">
                        <i class="fas fa-list mr-2"></i>Voir mes commandes
                    </a>
                    <a href="<?= BASE_URL ?>/products.php" class="flex-1 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white text-center py-3 rounded-xl font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                        <i class="fas fa-shopping-bag mr-2"></i>Continuer mes achats
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Support -->
        <div class="text-center mt-8 text-gray-500 dark:text-gray-400 text-sm">
            <p>Besoin d'aide ? Contactez notre support : <a href="mailto:<?= SITE_EMAIL ?>" class="text-blue-600 hover:underline"><?= SITE_EMAIL ?></a></p>
        </div>
    </div>
</section>

<?php  ?>