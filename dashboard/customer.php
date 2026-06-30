<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'customer') { 
    header('Location: ' . BASE_URL . '/dashboard/' . $_SESSION['user_role'] . '.php'); 
    exit; 
}

$userId = $_SESSION['user_id'];

// Statistiques client
$stats = [
    'total_orders' => 0, 'pending_orders' => 0, 'delivered_orders' => 0,
    'total_spent' => 0, 'favorites_count' => 0, 'cart_count' => 0
];

try {
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM orders WHERE user_id = ?) as total_orders,
            (SELECT COUNT(*) FROM orders WHERE user_id = ? AND status IN ('pending', 'confirmed', 'processing', 'shipped')) as pending_orders,
            (SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'delivered') as delivered_orders,
            (SELECT COALESCE(SUM(total), 0) FROM orders WHERE user_id = ? AND payment_status = 'paid') as total_spent,
            (SELECT COUNT(*) FROM favorites WHERE user_id = ?) as favorites_count,
            (SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ?) as cart_count
    ");
    $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId]);
    $stats = $stmt->fetch();
} catch (Exception $e) {}

// Commandes récentes
$recentOrders = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.*, COUNT(oi.id) as items_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentOrders = $stmt->fetchAll();
} catch (Exception $e) {}

// Produits récemment consultés (via vues)
$recentProducts = [];
try {
    $stmt = $pdo->query("
        SELECT p.*, (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_path
        FROM products p
        WHERE p.is_active = 1
        ORDER BY p.created_at DESC
        LIMIT 4
    ");
    $recentProducts = $stmt->fetchAll();
} catch (Exception $e) {}

// Favoris récents
$recentFavorites = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.*, (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_path
        FROM favorites f
        JOIN products p ON f.product_id = p.id
        WHERE f.user_id = ? AND p.is_active = 1
        ORDER BY f.id DESC
        LIMIT 4
    ");
    $stmt->execute([$userId]);
    $recentFavorites = $stmt->fetchAll();
} catch (Exception $e) {}

$pageTitle = 'Mon Tableau de Bord';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="bg-gradient-to-r from-blue-600 to-orange-500 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-white">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center text-2xl font-bold">
                <?= strtoupper(substr($_SESSION['user_first_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div>
                <h1 class="text-2xl md:text-3xl font-bold">Bonjour, <?= clean($_SESSION['user_first_name'] ?? 'Utilisateur') ?> ! 👋</h1>
                <p class="text-white/80 mt-1">Bienvenue sur votre tableau de bord PekDev Market</p>
            </div>
        </div>
    </div>
</section>

<section class="py-8 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Stats rapides -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <a href="<?= BASE_URL ?>/orders.php" class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border-l-4 border-blue-500 hover:shadow-lg transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Total commandes</p>
                        <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= $stats['total_orders'] ?></p>
                    </div>
                    <i class="fas fa-shopping-bag text-blue-500 text-2xl opacity-50"></i>
                </div>
            </a>
            <a href="<?= BASE_URL ?>/orders.php?status=pending" class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border-l-4 border-orange-500 hover:shadow-lg transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">En cours</p>
                        <p class="text-2xl font-bold text-orange-600"><?= $stats['pending_orders'] ?></p>
                    </div>
                    <i class="fas fa-clock text-orange-500 text-2xl opacity-50"></i>
                </div>
            </a>
            <a href="<?= BASE_URL ?>/orders.php?status=delivered" class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border-l-4 border-green-500 hover:shadow-lg transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Total dépensé</p>
                        <p class="text-xl font-bold text-green-600"><?= formatPrice($stats['total_spent']) ?></p>
                    </div>
                    <i class="fas fa-wallet text-green-500 text-2xl opacity-50"></i>
                </div>
            </a>
            <a href="<?= BASE_URL ?>/favorites.php" class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border-l-4 border-red-500 hover:shadow-lg transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Favoris</p>
                        <p class="text-2xl font-bold text-red-600"><?= $stats['favorites_count'] ?></p>
                    </div>
                    <i class="fas fa-heart text-red-500 text-2xl opacity-50"></i>
                </div>
            </a>
        </div>

        <!-- Actions rapides -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <a href="<?= BASE_URL ?>/cart.php" class="bg-gradient-to-br from-blue-500 to-blue-600 text-white p-4 rounded-xl hover:shadow-lg transition flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center relative">
                    <i class="fas fa-shopping-cart text-xl"></i>
                    <?php if ($stats['cart_count'] > 0): ?>
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center"><?= $stats['cart_count'] ?></span>
                    <?php endif; ?>
                </div>
                <div>
                    <p class="font-semibold">Panier</p>
                    <p class="text-xs text-blue-100">Voir le panier</p>
                </div>
            </a>
            <a href="<?= BASE_URL ?>/products.php" class="bg-gradient-to-br from-green-500 to-green-600 text-white p-4 rounded-xl hover:shadow-lg transition flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center"><i class="fas fa-box text-xl"></i></div>
                <div><p class="font-semibold">Produits</p><p class="text-xs text-green-100">Explorer</p></div>
            </a>
            <a href="<?= BASE_URL ?>/promotions.php" class="bg-gradient-to-br from-orange-500 to-orange-600 text-white p-4 rounded-xl hover:shadow-lg transition flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center"><i class="fas fa-fire text-xl"></i></div>
                <div><p class="font-semibold">Promos</p><p class="text-xs text-orange-100">Offres du jour</p></div>
            </a>
            <a href="<?= BASE_URL ?>/profile.php" class="bg-gradient-to-br from-purple-500 to-purple-600 text-white p-4 rounded-xl hover:shadow-lg transition flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center"><i class="fas fa-user text-xl"></i></div>
                <div><p class="font-semibold">Profil</p><p class="text-xs text-purple-100">Mon compte</p></div>
            </a>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            
            <!-- Commandes récentes -->
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b dark:border-gray-700 flex justify-between items-center">
                    <h2 class="font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-shopping-bag text-blue-600"></i> Commandes récentes
                    </h2>
                    <a href="<?= BASE_URL ?>/orders.php" class="text-sm text-blue-600 hover:text-blue-800 font-semibold">Voir tout <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
                <div class="divide-y dark:divide-gray-700">
                    <?php if (count($recentOrders) > 0): ?>
                        <?php foreach ($recentOrders as $order): 
                            $statusColors = ['pending' => 'yellow', 'confirmed' => 'blue', 'processing' => 'indigo', 'shipped' => 'purple', 'delivered' => 'green', 'cancelled' => 'red'];
                            $statusLabels = ['pending' => 'En attente', 'confirmed' => 'Confirmée', 'processing' => 'En préparation', 'shipped' => 'Expédiée', 'delivered' => 'Livrée', 'cancelled' => 'Annulée'];
                            $color = $statusColors[$order['status']] ?? 'gray';
                        ?>
                            <div class="p-4 flex items-center gap-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-receipt text-blue-600 text-xl"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-gray-800 dark:text-white text-sm"><?= $order['order_number'] ?></p>
                                    <p class="text-xs text-gray-500"><?= $order['items_count'] ?> articles • <?= date('d/m/Y', strtotime($order['created_at'])) ?></p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="font-bold text-gray-800 dark:text-white"><?= formatPrice($order['total']) ?></p>
                                    <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-<?= $color ?>-100 text-<?= $color ?>-800">
                                        <?= $statusLabels[$order['status']] ?? ucfirst($order['status']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-shopping-bag text-4xl mb-3 text-gray-300"></i>
                            <p>Aucune commande pour le moment</p>
                            <a href="<?= BASE_URL ?>/products.php" class="inline-block mt-3 text-blue-600 hover:text-blue-800 font-semibold text-sm">
                                <i class="fas fa-shopping-bag mr-1"></i>Commencer vos achats
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Favoris récents -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b dark:border-gray-700 flex justify-between items-center">
                    <h2 class="font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-heart text-red-500"></i> Mes favoris
                    </h2>
                    <a href="<?= BASE_URL ?>/favorites.php" class="text-sm text-blue-600 hover:text-blue-800 font-semibold">Voir tout</a>
                </div>
                <div class="divide-y dark:divide-gray-700">
                    <?php if (count($recentFavorites) > 0): ?>
                        <?php foreach ($recentFavorites as $fav): ?>
                            <div class="p-3 flex items-center gap-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <img src="<?= $fav['image_path'] ?: 'https://via.placeholder.com/50' ?>" alt="" class="w-12 h-12 rounded-lg object-cover">
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-gray-800 dark:text-white text-sm truncate"><?= clean($fav['name']) ?></p>
                                    <p class="text-xs text-blue-600 font-bold"><?= formatPrice($fav['price']) ?></p>
                                </div>
                                <a href="<?= BASE_URL ?>/product.php?slug=<?= $fav['slug'] ?>" class="text-xs text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-6 text-center text-gray-500 text-sm">
                            <i class="far fa-heart text-3xl mb-2 text-gray-300"></i>
                            <p>Aucun favori</p>
                            <a href="<?= BASE_URL ?>/products.php" class="inline-block mt-2 text-blue-600 hover:text-blue-800 font-semibold text-xs">
                                Explorer les produits
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Produits récemment ajoutés -->
        <?php if (count($recentProducts) > 0): ?>
            <div class="mt-8">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-sparkles text-yellow-500"></i> Nouveautés
                    </h2>
                    <a href="<?= BASE_URL ?>/products.php" class="text-sm text-blue-600 hover:text-blue-800 font-semibold">Voir tout <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <?php foreach ($recentProducts as $product): ?>
                        <a href="<?= BASE_URL ?>/product.php?slug=<?= $product['slug'] ?>" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-lg transition overflow-hidden group">
                            <div class="relative overflow-hidden">
                                <img src="<?= $product['image_path'] ?: 'https://via.placeholder.com/300' ?>" alt="<?= clean($product['name']) ?>" class="w-full h-40 object-cover group-hover:scale-105 transition">
                            </div>
                            <div class="p-3">
                                <h3 class="font-semibold text-gray-800 dark:text-white text-sm line-clamp-2 mb-2"><?= clean($product['name']) ?></h3>
                                <p class="text-lg font-bold text-blue-600"><?= formatPrice($product['price']) ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>