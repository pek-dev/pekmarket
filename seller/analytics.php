<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'seller') { header('Location: ' . BASE_URL); exit; }

$sellerId = $_SESSION['user_id'];
$period = $_GET['period'] ?? '30';

// Statistiques détaillées
$stats = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            COALESCE(SUM(oi.total), 0) as revenue,
            COALESCE(SUM(oi.quantity), 0) as products_sold,
            COUNT(DISTINCT o.user_id) as unique_customers
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = ? AND o.payment_status = 'paid'
          AND o.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$sellerId, $period]);
    $stats = $stmt->fetch();
    
    // Période précédente pour comparaison
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(oi.total), 0) as prev_revenue
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = ? AND o.payment_status = 'paid'
          AND o.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
          AND o.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$sellerId, $period * 2, $period]);
    $prevRevenue = $stmt->fetchColumn();
    $stats['revenue_growth'] = $prevRevenue > 0 ? round((($stats['revenue'] - $prevRevenue) / $prevRevenue) * 100, 1) : 0;
    
} catch (Exception $e) { $stats = ['total_orders' => 0, 'revenue' => 0, 'products_sold' => 0, 'unique_customers' => 0, 'revenue_growth' => 0]; }

// Revenus par jour
$revenueByDay = [];
try {
    $stmt = $pdo->prepare("
        SELECT DATE(o.created_at) as date, SUM(oi.total) as revenue, COUNT(DISTINCT o.id) as orders
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = ? AND o.payment_status = 'paid'
          AND o.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY date ORDER BY date ASC
    ");
    $stmt->execute([$sellerId, $period]);
    $revenueByDay = $stmt->fetchAll();
} catch (Exception $e) {}

// Top produits
$topProducts = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.price, SUM(oi.quantity) as sold, SUM(oi.total) as revenue,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE p.seller_id = ? AND o.payment_status = 'paid'
          AND o.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY p.id
        ORDER BY revenue DESC
        LIMIT 10
    ");
    $stmt->execute([$sellerId, $period]);
    $topProducts = $stmt->fetchAll();
} catch (Exception $e) {}

// Ventes par catégorie
$salesByCategory = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.name, c.color, SUM(oi.total) as revenue, COUNT(*) as orders
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN categories c ON p.category_id = c.id
        JOIN orders o ON oi.order_id = o.id
        WHERE p.seller_id = ? AND o.payment_status = 'paid'
          AND o.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY c.id
        ORDER BY revenue DESC
    ");
    $stmt->execute([$sellerId, $period]);
    $salesByCategory = $stmt->fetchAll();
} catch (Exception $e) {}

$pageTitle = 'Statistiques';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
                <i class="fas fa-chart-line text-purple-600"></i> Statistiques
            </h1>
            <p class="text-gray-500 mt-1">Analysez les performances de votre boutique</p>
        </div>
        <div class="flex gap-2 bg-white dark:bg-gray-800 p-1 rounded-lg shadow-sm">
            <a href="?period=7" class="px-4 py-2 rounded-lg text-sm font-semibold <?= $period == '7' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100' ?>">7j</a>
            <a href="?period=30" class="px-4 py-2 rounded-lg text-sm font-semibold <?= $period == '30' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100' ?>">30j</a>
            <a href="?period=90" class="px-4 py-2 rounded-lg text-sm font-semibold <?= $period == '90' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100' ?>">90j</a>
            <a href="?period=365" class="px-4 py-2 rounded-lg text-sm font-semibold <?= $period == '365' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100' ?>">1 an</a>
        </div>
    </div>

    <!-- Stats principales -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-5 text-white shadow-lg">
            <p class="text-blue-100 text-xs">Chiffre d'affaires</p>
            <p class="text-2xl font-bold mt-1"><?= formatPrice($stats['revenue']) ?></p>
            <p class="text-xs text-blue-100 mt-2">
                <?php if ($stats['revenue_growth'] > 0): ?>
                    <i class="fas fa-arrow-up"></i> +<?= $stats['revenue_growth'] ?>%
                <?php elseif ($stats['revenue_growth'] < 0): ?>
                    <i class="fas fa-arrow-down"></i> <?= $stats['revenue_growth'] ?>%
                <?php else: ?>
                    <i class="fas fa-equals"></i> Stable
                <?php endif; ?>
            </p>
        </div>
        <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl p-5 text-white shadow-lg">
            <p class="text-green-100 text-xs">Commandes</p>
            <p class="text-2xl font-bold mt-1"><?= $stats['total_orders'] ?></p>
            <p class="text-xs text-green-100 mt-2">sur <?= $period ?> jours</p>
        </div>
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-5 text-white shadow-lg">
            <p class="text-orange-100 text-xs">Produits vendus</p>
            <p class="text-2xl font-bold mt-1"><?= number_format($stats['products_sold']) ?></p>
            <p class="text-xs text-orange-100 mt-2">articles</p>
        </div>
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-5 text-white shadow-lg">
            <p class="text-purple-100 text-xs">Clients uniques</p>
            <p class="text-2xl font-bold mt-1"><?= $stats['unique_customers'] ?></p>
            <p class="text-xs text-purple-100 mt-2">acheteurs</p>
        </div>
    </div>

    <!-- Graphique revenus -->
    <?php if (count($revenueByDay) > 0): ?>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 mb-6 border border-gray-100 dark:border-gray-700">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
            <i class="fas fa-chart-area text-blue-600"></i> Évolution des revenus
        </h2>
        <div class="h-64 flex items-end gap-1">
            <?php 
            $maxRevenue = max(array_column($revenueByDay, 'revenue'));
            foreach ($revenueByDay as $day): 
                $height = $maxRevenue > 0 ? ($day['revenue'] / $maxRevenue) * 100 : 0;
            ?>
                <div class="flex-1 flex flex-col items-center group">
                    <div class="w-full bg-gradient-to-t from-blue-600 to-blue-400 rounded-t hover:from-blue-700 hover:to-blue-500 transition relative" 
                         style="height: <?= $height ?>%; min-height: 4px;"
                         title="<?= date('d/m/Y', strtotime($day['date'])) ?>: <?= formatPrice($day['revenue']) ?>">
                    </div>
                    <?php if ($period <= 30): ?>
                        <p class="text-[9px] text-gray-500 mt-1"><?= date('d', strtotime($day['date'])) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-2 gap-6">
        
        <!-- Top produits -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-4 border-b dark:border-gray-700 bg-gradient-to-r from-purple-600 to-pink-600 text-white">
                <h2 class="font-bold flex items-center gap-2"><i class="fas fa-trophy"></i> Top 10 produits</h2>
            </div>
            <div class="divide-y dark:divide-gray-700 max-h-96 overflow-y-auto custom-scrollbar">
                <?php if (count($topProducts) > 0): ?>
                    <?php foreach ($topProducts as $i => $p): ?>
                        <div class="p-3 flex items-center gap-3 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <div class="w-8 h-8 bg-<?= $i < 3 ? 'yellow' : 'gray' ?>-100 rounded-full flex items-center justify-center font-bold text-<?= $i < 3 ? 'yellow' : 'gray' ?>-600 text-sm"><?= $i + 1 ?></div>
                            <img src="<?= $p['image'] ?: 'https://via.placeholder.com/40' ?>" class="w-10 h-10 rounded-lg object-cover">
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-800 dark:text-white text-sm truncate"><?= clean($p['name']) ?></p>
                                <p class="text-xs text-gray-500"><?= $p['sold'] ?> vendus</p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-green-600 text-sm"><?= formatPrice($p['revenue']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-8 text-center text-gray-500">Aucune vente</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ventes par catégorie -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-4 border-b dark:border-gray-700 bg-gradient-to-r from-orange-600 to-red-600 text-white">
                <h2 class="font-bold flex items-center gap-2"><i class="fas fa-chart-pie"></i> Ventes par catégorie</h2>
            </div>
            <div class="p-4 space-y-3">
                <?php if (count($salesByCategory) > 0): 
                    $maxCatRevenue = max(array_column($salesByCategory, 'revenue'));
                    foreach ($salesByCategory as $cat): 
                        $percent = $maxCatRevenue > 0 ? ($cat['revenue'] / $maxCatRevenue) * 100 : 0;
                ?>
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm font-semibold text-gray-800 dark:text-white"><?= clean($cat['name']) ?></span>
                            <span class="text-sm font-bold text-<?= $cat['color'] ?>-600"><?= formatPrice($cat['revenue']) ?></span>
                        </div>
                        <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                            <div class="h-full bg-<?= $cat['color'] ?>-500" style="width: <?= $percent ?>%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1"><?= $cat['orders'] ?> commande(s)</p>
                    </div>
                <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-8 text-center text-gray-500">Aucune donnée</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>