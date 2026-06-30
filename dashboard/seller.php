<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'seller') { 
    header('Location: ' . BASE_URL . '/dashboard/' . ($_SESSION['user_role'] ?? 'customer') . '.php'); 
    exit; 
}

$sellerId = $_SESSION['user_id'];

// Statistiques complètes
$stats = [
    'total_sales' => 0, 'revenue' => 0, 'products_sold' => 0, 'products_stock' => 0,
    'pending_orders' => 0, 'delivered_orders' => 0, 'avg_rating' => 0,
    'new_customers' => 0, 'conversion_rate' => 0, 'total_products' => 0,
    'total_views' => 0, 'pending_withdrawals' => 0, 'available_balance' => 0,
    'unread_messages' => 0, 'unread_notifications' => 0
];

try {
    // Ventes totales et revenus
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT o.id) as total_sales,
            COALESCE(SUM(oi.total), 0) as revenue,
            COALESCE(SUM(oi.quantity), 0) as products_sold
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = ? AND o.payment_status = 'paid'
    ");
    $stmt->execute([$sellerId]);
    $s = $stmt->fetch();
    $stats['total_sales'] = $s['total_sales'];
    $stats['revenue'] = $s['revenue'];
    $stats['products_sold'] = $s['products_sold'];
    
    // Produits
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            COALESCE(SUM(stock), 0) as stock,
            COALESCE(SUM(views_count), 0) as views
        FROM products WHERE seller_id = ?
    ");
    $stmt->execute([$sellerId]);
    $p = $stmt->fetch();
    $stats['total_products'] = $p['total'];
    $stats['products_stock'] = $p['stock'];
    $stats['total_views'] = $p['views'];
    
    // Commandes
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN o.status IN ('pending','confirmed','processing') THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as delivered
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = ?
    ");
    $stmt->execute([$sellerId]);
    $c = $stmt->fetch();
    $stats['pending_orders'] = $c['pending'];
    $stats['delivered_orders'] = $c['delivered'];
    
    // Note moyenne
    $stmt = $pdo->prepare("SELECT COALESCE(AVG(rating_avg), 0) FROM products WHERE seller_id = ? AND rating_count > 0");
    $stmt->execute([$sellerId]);
    $stats['avg_rating'] = $stmt->fetchColumn();
    
    // Nouveaux clients (30 derniers jours)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT o.user_id) 
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = ? AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$sellerId]);
    $stats['new_customers'] = $stmt->fetchColumn();
    
    // Taux de conversion
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT o.user_id) as buyers,
               (SELECT COUNT(DISTINCT user_id) FROM favorites WHERE product_id IN (SELECT id FROM products WHERE seller_id = ?)) as interested
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = ?
    ");
    $stmt->execute([$sellerId, $sellerId]);
    $cv = $stmt->fetch();
    $stats['conversion_rate'] = $cv['interested'] > 0 ? round(($cv['buyers'] / $cv['interested']) * 100, 1) : 0;
    
    // Finances
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as withdrawn
        FROM withdrawals WHERE seller_id = ? AND status IN ('approved','completed')
    ");
    $stmt->execute([$sellerId]);
    $withdrawn = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE seller_id = ? AND status = 'pending'");
    $stmt->execute([$sellerId]);
    $stats['pending_withdrawals'] = $stmt->fetchColumn();
    
    $stats['available_balance'] = ($stats['revenue'] * 0.95) - $withdrawn - $stats['pending_withdrawals'];
    
    // Messages non lus
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE to_user_id = ? AND is_read = 0");
    $stmt->execute([$sellerId]);
    $stats['unread_messages'] = $stmt->fetchColumn();
    
    // Notifications non lues
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$sellerId]);
    $stats['unread_notifications'] = $stmt->fetchColumn();
    
} catch (Exception $e) {}

// Produits populaires
$topProducts = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.name, p.sales_count, p.views_count,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
        FROM products p
        WHERE p.seller_id = ? AND p.is_active = 1
        ORDER BY p.sales_count DESC
        LIMIT 5
    ");
    $stmt->execute([$sellerId]);
    $topProducts = $stmt->fetchAll();
} catch (Exception $e) {}

// Commandes récentes
$recentOrders = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.order_number, o.status, o.total, o.created_at, u.first_name, u.last_name
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        JOIN users u ON o.user_id = u.id
        WHERE p.seller_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$sellerId]);
    $recentOrders = $stmt->fetchAll();
} catch (Exception $e) {}

$pageTitle = 'Dashboard Vendeur';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Sidebar vendeur -->
<div class="flex">
    <aside class="hidden lg:block w-64 bg-white dark:bg-gray-800 min-h-screen border-r dark:border-gray-700 sticky top-0">
        <div class="p-4 border-b dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-orange-500 rounded-full flex items-center justify-center text-white font-bold">
                    <?= strtoupper(substr($_SESSION['user_first_name'] ?? 'V', 0, 1)) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-gray-800 dark:text-white text-sm truncate"><?= clean($_SESSION['user_name'] ?? 'Vendeur') ?></p>
                    <p class="text-xs text-green-600"><i class="fas fa-circle text-[8px] mr-1"></i>En ligne</p>
                </div>
            </div>
        </div>
        <nav class="p-3 space-y-1">
            <?php
            $currentFile = basename($_SERVER['PHP_SELF']);
            $menuItems = [
                ['file' => 'seller.php', 'url' => '/dashboard/seller.php', 'icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'badge' => null],
                ['file' => 'products.php', 'url' => '/seller/products.php', 'icon' => 'fa-box', 'label' => 'Produits', 'badge' => $stats['total_products']],
                ['file' => 'add-product.php', 'url' => '/seller/add-product.php', 'icon' => 'fa-plus-circle', 'label' => 'Ajouter', 'badge' => null],
                ['file' => 'orders.php', 'url' => '/seller/orders.php', 'icon' => 'fa-shopping-bag', 'label' => 'Commandes', 'badge' => $stats['pending_orders']],
                ['file' => 'shipments.php', 'url' => '/seller/shipments.php', 'icon' => 'fa-truck', 'label' => 'Expéditions', 'badge' => null],
                ['file' => 'finance.php', 'url' => '/seller/finance.php', 'icon' => 'fa-wallet', 'label' => 'Revenus', 'badge' => null],
                ['file' => 'analytics.php', 'url' => '/seller/analytics.php', 'icon' => 'fa-chart-line', 'label' => 'Statistiques', 'badge' => null],
                ['file' => 'reviews.php', 'url' => '/seller/reviews.php', 'icon' => 'fa-star', 'label' => 'Avis clients', 'badge' => null],
                ['file' => 'messages.php', 'url' => '/messages.php', 'icon' => 'fa-comment-dots', 'label' => 'Messages', 'badge' => $stats['unread_messages']],
                ['file' => 'promotions.php', 'url' => '/seller/promotions.php', 'icon' => 'fa-gift', 'label' => 'Promotions', 'badge' => null],
                ['file' => 'coupons.php', 'url' => '/seller/coupons.php', 'icon' => 'fa-ticket-alt', 'label' => 'Coupons', 'badge' => null],
                ['file' => 'customers.php', 'url' => '/seller/customers.php', 'icon' => 'fa-users', 'label' => 'Clients', 'badge' => null],
                ['file' => 'shop-settings.php', 'url' => '/seller/shop-settings.php', 'icon' => 'fa-store', 'label' => 'Ma boutique', 'badge' => null],
                ['file' => 'profile.php', 'url' => '/seller/profile.php', 'icon' => 'fa-user', 'label' => 'Profil', 'badge' => null],
                ['file' => 'notifications.php', 'url' => '/seller/notifications.php', 'icon' => 'fa-bell', 'label' => 'Notifications', 'badge' => $stats['unread_notifications']],
            ];
            foreach ($menuItems as $item):
                $active = $currentFile == $item['file'];
            ?>
                <a href="<?= BASE_URL . $item['url'] ?>" 
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $active ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 font-semibold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                    <i class="fas <?= $item['icon'] ?> w-5 text-center"></i>
                    <span class="flex-1 text-sm"><?= $item['label'] ?></span>
                    <?php if ($item['badge'] && $item['badge'] > 0): ?>
                        <span class="bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full font-bold"><?= $item['badge'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
            
            <div class="border-t dark:border-gray-700 mt-3 pt-3">
                <a href="<?= BASE_URL ?>/logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                    <i class="fas fa-sign-out-alt w-5"></i>
                    <span class="text-sm">Déconnexion</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main content -->
    <main class="flex-1 p-4 md:p-8 bg-gray-50 dark:bg-gray-900 min-h-screen">
        
        <!-- Header -->
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-white">
                    Bonjour, <?= clean($_SESSION['user_first_name'] ?? 'Vendeur') ?> 👋
                </h1>
                <p class="text-gray-500 mt-1">Voici un aperçu de votre activité aujourd'hui</p>
            </div>
            <div class="flex gap-2">
                <a href="<?= BASE_URL ?>/seller/add-product.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold text-sm flex items-center gap-2">
                    <i class="fas fa-plus"></i> Nouveau produit
                </a>
            </div>
        </div>

        <!-- Stats principales -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-5 text-white shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-xs">Chiffre d'affaires</p>
                        <p class="text-2xl font-bold mt-1"><?= formatPrice($stats['revenue']) ?></p>
                    </div>
                    <i class="fas fa-chart-line text-3xl text-blue-200"></i>
                </div>
                <p class="text-xs text-blue-100 mt-2"><i class="fas fa-arrow-up mr-1"></i><?= $stats['total_sales'] ?> ventes</p>
            </div>
            
            <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl p-5 text-white shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-xs">Produits vendus</p>
                        <p class="text-2xl font-bold mt-1"><?= number_format($stats['products_sold']) ?></p>
                    </div>
                    <i class="fas fa-box text-3xl text-green-200"></i>
                </div>
                <p class="text-xs text-green-100 mt-2"><i class="fas fa-boxes mr-1"></i><?= $stats['products_stock'] ?> en stock</p>
            </div>
            
            <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-5 text-white shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-xs">Commandes en attente</p>
                        <p class="text-2xl font-bold mt-1"><?= $stats['pending_orders'] ?></p>
                    </div>
                    <i class="fas fa-clock text-3xl text-orange-200"></i>
                </div>
                <p class="text-xs text-orange-100 mt-2"><i class="fas fa-check mr-1"></i><?= $stats['delivered_orders'] ?> livrées</p>
            </div>
            
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-5 text-white shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-xs">Note moyenne</p>
                        <p class="text-2xl font-bold mt-1"><?= number_format($stats['avg_rating'], 1) ?>/5</p>
                    </div>
                    <i class="fas fa-star text-3xl text-purple-200"></i>
                </div>
                <p class="text-xs text-purple-100 mt-2"><?= renderStars($stats['avg_rating']) ?></p>
            </div>
        </div>

        <!-- Stats secondaires -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-yellow-500">
                <p class="text-xs text-gray-500">Taux conversion</p>
                <p class="text-xl font-bold text-gray-800 dark:text-white"><?= $stats['conversion_rate'] ?>%</p>
            </div>
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-pink-500">
                <p class="text-xs text-gray-500">Nouveaux clients</p>
                <p class="text-xl font-bold text-gray-800 dark:text-white"><?= $stats['new_customers'] ?></p>
            </div>
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-teal-500">
                <p class="text-xs text-gray-500">Vues totales</p>
                <p class="text-xl font-bold text-gray-800 dark:text-white"><?= number_format($stats['total_views'] / 1000, 1) ?>K</p>
            </div>
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-indigo-500">
                <p class="text-xs text-gray-500">Solde disponible</p>
                <p class="text-xl font-bold text-green-600"><?= formatPrice($stats['available_balance']) ?></p>
            </div>
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-red-500">
                <p class="text-xs text-gray-500">Produits actifs</p>
                <p class="text-xl font-bold text-gray-800 dark:text-white"><?= $stats['total_products'] ?></p>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            
            <!-- Produits populaires -->
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b dark:border-gray-700 flex justify-between items-center">
                    <h2 class="font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-fire text-orange-500"></i> Produits populaires
                    </h2>
                    <a href="<?= BASE_URL ?>/seller/products.php" class="text-sm text-blue-600 hover:text-blue-800 font-semibold">Voir tout</a>
                </div>
                <div class="divide-y dark:divide-gray-700">
                    <?php if (count($topProducts) > 0): ?>
                        <?php foreach ($topProducts as $i => $prod): ?>
                            <div class="p-4 flex items-center gap-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <div class="w-8 h-8 bg-<?= $i == 0 ? 'yellow' : ($i == 1 ? 'gray' : 'orange') ?>-100 rounded-full flex items-center justify-center font-bold text-<?= $i == 0 ? 'yellow' : ($i == 1 ? 'gray' : 'orange') ?>-600 text-sm">
                                    <?= $i + 1 ?>
                                </div>
                                <img src="<?= $prod['image'] ?: 'https://via.placeholder.com/40' ?>" class="w-10 h-10 rounded-lg object-cover">
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-gray-800 dark:text-white text-sm truncate"><?= clean($prod['name']) ?></p>
                                    <p class="text-xs text-gray-500"><?= $prod['sales_count'] ?> vendus • <?= $prod['views_count'] ?> vues</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-box-open text-4xl mb-3 text-gray-300"></i>
                            <p>Ajoutez vos premiers produits !</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Commandes récentes -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b dark:border-gray-700 flex justify-between items-center">
                    <h2 class="font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-shopping-bag text-blue-600"></i> Commandes récentes
                    </h2>
                    <a href="<?= BASE_URL ?>/seller/orders.php" class="text-sm text-blue-600 hover:text-blue-800 font-semibold">Voir tout</a>
                </div>
                <div class="divide-y dark:divide-gray-700 max-h-96 overflow-y-auto custom-scrollbar">
                    <?php if (count($recentOrders) > 0): ?>
                        <?php foreach ($recentOrders as $order): 
                            $statusColors = ['pending' => 'yellow', 'confirmed' => 'blue', 'processing' => 'indigo', 'shipped' => 'purple', 'delivered' => 'green', 'cancelled' => 'red'];
                            $color = $statusColors[$order['status']] ?? 'gray';
                        ?>
                            <div class="p-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <div class="flex justify-between items-start gap-2 mb-1">
                                    <p class="font-semibold text-sm text-gray-800 dark:text-white truncate"><?= $order['order_number'] ?></p>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-<?= $color ?>-100 text-<?= $color ?>-800"><?= ucfirst($order['status']) ?></span>
                                </div>
                                <p class="text-xs text-gray-500 truncate"><?= clean($order['first_name'] . ' ' . $order['last_name']) ?></p>
                                <div class="flex justify-between items-center mt-1">
                                    <span class="text-xs text-gray-400"><?= date('d/m/Y', strtotime($order['created_at'])) ?></span>
                                    <span class="text-sm font-bold text-green-600"><?= formatPrice($order['total']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-shopping-bag text-4xl mb-3 text-gray-300"></i>
                            <p>Aucune commande</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-3">
            <a href="<?= BASE_URL ?>/seller/add-product.php" class="bg-gradient-to-br from-green-500 to-green-600 text-white p-4 rounded-xl hover:shadow-lg transition text-center">
                <i class="fas fa-plus-circle text-2xl mb-2"></i>
                <p class="font-semibold text-sm">Nouveau produit</p>
            </a>
            <a href="<?= BASE_URL ?>/seller/coupons.php" class="bg-gradient-to-br from-purple-500 to-purple-600 text-white p-4 rounded-xl hover:shadow-lg transition text-center">
                <i class="fas fa-ticket-alt text-2xl mb-2"></i>
                <p class="font-semibold text-sm">Créer coupon</p>
            </a>
            <a href="<?= BASE_URL ?>/seller/promotions.php" class="bg-gradient-to-br from-orange-500 to-orange-600 text-white p-4 rounded-xl hover:shadow-lg transition text-center">
                <i class="fas fa-gift text-2xl mb-2"></i>
                <p class="font-semibold text-sm">Promotion</p>
            </a>
            <a href="<?= BASE_URL ?>/seller/finance.php" class="bg-gradient-to-br from-blue-500 to-blue-600 text-white p-4 rounded-xl hover:shadow-lg transition text-center">
                <i class="fas fa-money-bill-wave text-2xl mb-2"></i>
                <p class="font-semibold text-sm">Retirer fonds</p>
            </a>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>