<?php
// Chargement sécurisé avec chemins absolus
require_once __DIR__ . '/../config/bootstrap.php';

// Vérification de sécurité : Admin uniquement
requireLogin();
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL);
    exit;
}

// ============================================
// RÉCUPÉRATION DES STATISTIQUES (Sécurisé)
// ============================================
$stats = [
    'total_users' => 0, 'sellers' => 0, 'active_products' => 0, 
    'total_orders' => 0, 'total_revenue' => 0, 'pending_orders' => 0,
    'pending_ads' => 0, 'active_ads' => 0, 'ad_revenue' => 0,
    'subscription_revenue' => 0, 'active_subscriptions' => 0, 'pending_reviews' => 0
];

try {
    $statsStmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM users WHERE role = 'seller' AND is_active = 1) as sellers,
            (SELECT COUNT(*) FROM products WHERE is_active = 1) as active_products,
            (SELECT COUNT(*) FROM orders) as total_orders,
            (SELECT COALESCE(SUM(total), 0) FROM orders WHERE payment_status = 'paid') as total_revenue,
            (SELECT COUNT(*) FROM orders WHERE status = 'pending') as pending_orders,
            (SELECT COUNT(*) FROM ad_campaigns WHERE status = 'pending') as pending_ads,
            (SELECT COUNT(*) FROM ad_campaigns WHERE status = 'active') as active_ads,
            (SELECT COALESCE(SUM(spent), 0) FROM ad_campaigns) as ad_revenue,
            (SELECT COALESCE(SUM(amount_paid), 0) FROM subscriptions WHERE payment_status = 'paid') as subscription_revenue,
            (SELECT COUNT(*) FROM subscriptions WHERE status = 'active') as active_subscriptions,
            (SELECT COUNT(*) FROM reviews WHERE is_approved = 0) as pending_reviews
    ");
    $stats = $statsStmt->fetch();
} catch (Exception $e) {
    // En cas d'erreur SQL, on garde les valeurs par défaut à 0
}

// ============================================
// DONNÉES RÉCENTES
// ============================================
$pendingAds = [];
$recentSubs = [];

try {
    $pendingAdsStmt = $pdo->query("
        SELECT a.*, u.first_name, u.last_name 
        FROM ad_campaigns a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.status = 'pending' 
        ORDER BY a.created_at DESC 
        LIMIT 5
    ");
    $pendingAds = $pendingAdsStmt->fetchAll();
} catch (Exception $e) {}

try {
    $recentSubsStmt = $pdo->query("
        SELECT s.*, u.first_name, u.last_name, sp.name as plan_name 
        FROM subscriptions s 
        JOIN users u ON s.user_id = u.id 
        JOIN subscription_plans sp ON s.plan_id = sp.id 
        WHERE s.status = 'active'
        ORDER BY s.created_at DESC 
        LIMIT 5
    ");
    $recentSubs = $recentSubsStmt->fetchAll();
} catch (Exception $e) {}

$pageTitle = 'Panneau d\'Administration';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- En-tête de la page -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
                <i class="fas fa-shield-alt text-blue-600"></i> Panneau d'Administration
            </h1>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Gérez votre marketplace PekDev Market</p>
        </div>
        <div class="flex gap-2">
            <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-semibold flex items-center gap-2">
                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> Système actif
            </span>
        </div>
    </div>

    <!-- ============================================
         CARTES DE REVENUS
         ============================================ -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl p-5 text-white shadow-lg">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-green-100 text-sm font-medium">Revenus totaux</p>
                    <p class="text-2xl font-bold mt-1"><?= formatPrice($stats['total_revenue']) ?></p>
                </div>
                <i class="fas fa-chart-line text-3xl text-green-200"></i>
            </div>
        </div>
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-5 text-white shadow-lg">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-blue-100 text-sm font-medium">Revenus Publicités</p>
                    <p class="text-2xl font-bold mt-1"><?= formatPrice($stats['ad_revenue']) ?></p>
                </div>
                <i class="fas fa-ad text-3xl text-blue-200"></i>
            </div>
        </div>
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-5 text-white shadow-lg">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-purple-100 text-sm font-medium">Revenus Abonnements</p>
                    <p class="text-2xl font-bold mt-1"><?= formatPrice($stats['subscription_revenue']) ?></p>
                </div>
                <i class="fas fa-crown text-3xl text-purple-200"></i>
            </div>
        </div>
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-5 text-white shadow-lg">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-orange-100 text-sm font-medium">Commandes en attente</p>
                    <p class="text-2xl font-bold mt-1"><?= $stats['pending_orders'] ?></p>
                </div>
                <i class="fas fa-clock text-3xl text-orange-200"></i>
            </div>
        </div>
    </div>

    <!-- ============================================
         STATISTIQUES GÉNÉRALES
         ============================================ -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-blue-500">
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= number_format($stats['total_users']) ?></p>
            <p class="text-xs text-gray-500 mt-1">Utilisateurs</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-green-500">
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= number_format($stats['sellers']) ?></p>
            <p class="text-xs text-gray-500 mt-1">Vendeurs</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-purple-500">
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= number_format($stats['active_products']) ?></p>
            <p class="text-xs text-gray-500 mt-1">Produits</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-orange-500">
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= number_format($stats['total_orders']) ?></p>
            <p class="text-xs text-gray-500 mt-1">Commandes</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-red-500">
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= $stats['active_ads'] ?></p>
            <p class="text-xs text-gray-500 mt-1">Pubs actives</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-yellow-500">
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= $stats['active_subscriptions'] ?></p>
            <p class="text-xs text-gray-500 mt-1">Abonnements</p>
        </div>
    </div>

    <!-- ============================================
         GRILLE DE NAVIGATION ADMIN
         ============================================ -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        
        <a href="<?= BASE_URL ?>/admin/users.php" class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm hover:shadow-xl transition border border-gray-100 dark:border-gray-700 group flex items-center gap-4">
            <div class="w-14 h-14 bg-blue-100 dark:bg-blue-900 rounded-xl flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-users text-blue-600 text-2xl"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-gray-800 dark:text-white">Utilisateurs</h3>
                <p class="text-sm text-gray-500"><?= $stats['total_users'] ?> inscrits</p>
            </div>
            <i class="fas fa-chevron-right text-gray-400 group-hover:translate-x-1 transition"></i>
        </a>

        <a href="<?= BASE_URL ?>/admin/products.php" class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm hover:shadow-xl transition border border-gray-100 dark:border-gray-700 group flex items-center gap-4">
            <div class="w-14 h-14 bg-green-100 dark:bg-green-900 rounded-xl flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-box text-green-600 text-2xl"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-gray-800 dark:text-white">Produits</h3>
                <p class="text-sm text-gray-500"><?= $stats['active_products'] ?> actifs</p>
            </div>
            <i class="fas fa-chevron-right text-gray-400 group-hover:translate-x-1 transition"></i>
        </a>

        <a href="<?= BASE_URL ?>/admin/categories.php" class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm hover:shadow-xl transition border border-gray-100 dark:border-gray-700 group flex items-center gap-4">
            <div class="w-14 h-14 bg-purple-100 dark:bg-purple-900 rounded-xl flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-tags text-purple-600 text-2xl"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-gray-800 dark:text-white">Catégories</h3>
                <p class="text-sm text-gray-500">Gérer les catégories</p>
            </div>
            <i class="fas fa-chevron-right text-gray-400 group-hover:translate-x-1 transition"></i>
        </a>

        <a href="<?= BASE_URL ?>/admin/orders.php" class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm hover:shadow-xl transition border border-gray-100 dark:border-gray-700 group flex items-center gap-4 relative">
            <?php if ($stats['pending_orders'] > 0): ?>
                <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold w-6 h-6 rounded-full flex items-center justify-center shadow-md"><?= $stats['pending_orders'] ?></span>
            <?php endif; ?>
            <div class="w-14 h-14 bg-orange-100 dark:bg-orange-900 rounded-xl flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-shopping-cart text-orange-600 text-2xl"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-gray-800 dark:text-white">Commandes</h3>
                <p class="text-sm text-gray-500"><?= $stats['pending_orders'] ?> en attente</p>
            </div>
            <i class="fas fa-chevron-right text-gray-400 group-hover:translate-x-1 transition"></i>
        </a>

        <a href="<?= BASE_URL ?>/admin/subscriptions.php" class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm hover:shadow-xl transition border border-gray-100 dark:border-gray-700 group flex items-center gap-4">
            <div class="w-14 h-14 bg-yellow-100 dark:bg-yellow-900 rounded-xl flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-crown text-yellow-600 text-2xl"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-gray-800 dark:text-white">Abonnements</h3>
                <p class="text-sm text-gray-500"><?= $stats['active_subscriptions'] ?> actifs</p>
            </div>
            <i class="fas fa-chevron-right text-gray-400 group-hover:translate-x-1 transition"></i>
        </a>

        <a href="<?= BASE_URL ?>/admin/ads.php" class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm hover:shadow-xl transition border border-gray-100 dark:border-gray-700 group flex items-center gap-4 relative">
            <?php if ($stats['pending_ads'] > 0): ?>
                <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold w-6 h-6 rounded-full flex items-center justify-center shadow-md"><?= $stats['pending_ads'] ?></span>
            <?php endif; ?>
            <div class="w-14 h-14 bg-red-100 dark:bg-red-900 rounded-xl flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-ad text-red-600 text-2xl"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-gray-800 dark:text-white">Publicités</h3>
                <p class="text-sm text-gray-500"><?= $stats['pending_ads'] ?> en attente</p>
            </div>
            <i class="fas fa-chevron-right text-gray-400 group-hover:translate-x-1 transition"></i>
        </a>

        <a href="<?= BASE_URL ?>/admin/sponsored.php" class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm hover:shadow-xl transition border border-gray-100 dark:border-gray-700 group flex items-center gap-4">
            <div class="w-14 h-14 bg-pink-100 dark:bg-pink-900 rounded-xl flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-store text-pink-600 text-2xl"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-gray-800 dark:text-white">Boutiques Sponsor</h3>
                <p class="text-sm text-gray-500">Gérer les boosts</p>
            </div>
            <i class="fas fa-chevron-right text-gray-400 group-hover:translate-x-1 transition"></i>
        </a>

        <a href="<?= BASE_URL ?>/admin/reviews.php" class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm hover:shadow-xl transition border border-gray-100 dark:border-gray-700 group flex items-center gap-4 relative">
            <?php if ($stats['pending_reviews'] > 0): ?>
                <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold w-6 h-6 rounded-full flex items-center justify-center shadow-md"><?= $stats['pending_reviews'] ?></span>
            <?php endif; ?>
            <div class="w-14 h-14 bg-indigo-100 dark:bg-indigo-900 rounded-xl flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-star text-indigo-600 text-2xl"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-gray-800 dark:text-white">Avis</h3>
                <p class="text-sm text-gray-500"><?= $stats['pending_reviews'] ?> à modérer</p>
            </div>
            <i class="fas fa-chevron-right text-gray-400 group-hover:translate-x-1 transition"></i>
        </a>

        <a href="<?= BASE_URL ?>/admin/revenue.php" class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm hover:shadow-xl transition border border-gray-100 dark:border-gray-700 group flex items-center gap-4">
            <div class="w-14 h-14 bg-teal-100 dark:bg-teal-900 rounded-xl flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-wallet text-teal-600 text-2xl"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-gray-800 dark:text-white">Revenus & Finances</h3>
                <p class="text-sm text-gray-500"><?= formatPrice($stats['ad_revenue'] + $stats['subscription_revenue']) ?></p>
            </div>
            <i class="fas fa-chevron-right text-gray-400 group-hover:translate-x-1 transition"></i>
        </a>

        <a href="<?= BASE_URL ?>/admin/withdrawals.php" class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm hover:shadow-xl transition border border-gray-100 dark:border-gray-700 group flex items-center gap-4 relative">
            <?php if ($stats['pending_withdrawals_count'] ?? 0 > 0): ?>
                <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold w-6 h-6 rounded-full flex items-center justify-center shadow-md"><?= $stats['pending_withdrawals_count'] ?></span>
            <?php endif; ?>
            <div class="w-14 h-14 bg-green-100 dark:bg-green-900 rounded-xl flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-money-bill-wave text-green-600 text-2xl"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-gray-800 dark:text-white">Retraits vendeurs</h3>
                <p class="text-sm text-gray-500">Gérer les demandes</p>
            </div>
            <i class="fas fa-chevron-right text-gray-400 group-hover:translate-x-1 transition"></i>
        </a>
    </div>

    <!-- ============================================
         SECTION BAS : PUBS EN ATTENTE & ABONNEMENTS
         ============================================ -->
    <div class="grid lg:grid-cols-2 gap-8">
        
        <!-- Publicités en attente -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-6 border-b dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-800/50">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                    <i class="fas fa-ad text-red-500"></i> Publicités en attente
                </h2>
                <a href="<?= BASE_URL ?>/admin/ads.php?status=pending" class="text-sm text-blue-600 hover:text-blue-800 font-semibold">Voir tout <i class="fas fa-arrow-right ml-1"></i></a>
            </div>
            <div class="divide-y dark:divide-gray-700">
                <?php if (count($pendingAds) > 0): ?>
                    <?php foreach ($pendingAds as $ad): ?>
                        <div class="p-4 flex items-center gap-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <img src="<?= clean($ad['image_path']) ?>" alt="" class="w-14 h-14 rounded-lg object-cover border dark:border-gray-600">
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-800 dark:text-white truncate"><?= clean($ad['title']) ?></p>
                                <p class="text-xs text-gray-500">
                                    Par <?= clean($ad['first_name'] . ' ' . $ad['last_name']) ?> • 
                                    <span class="px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-800 font-medium">
                                        <?= ucfirst($ad['ad_type']) ?>
                                    </span>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">Budget: <?= formatPrice($ad['budget_total']) ?></p>
                            </div>
                            <div class="flex gap-2">
                                <form method="POST" action="<?= BASE_URL ?>/admin/ads-action.php" class="inline">
                                    <input type="hidden" name="ad_id" value="<?= $ad['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="w-8 h-8 bg-green-100 text-green-600 rounded-lg hover:bg-green-200 flex items-center justify-center transition" title="Approuver">
                                        <i class="fas fa-check text-sm"></i>
                                    </button>
                                </form>
                                <form method="POST" action="<?= BASE_URL ?>/admin/ads-action.php" class="inline">
                                    <input type="hidden" name="ad_id" value="<?= $ad['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="w-8 h-8 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 flex items-center justify-center transition" title="Rejeter">
                                        <i class="fas fa-times text-sm"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-check-circle text-4xl text-green-300 mb-3"></i>
                        <p>Aucune publicité en attente de modération</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Abonnements récents -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-6 border-b dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-800/50">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                    <i class="fas fa-crown text-yellow-500"></i> Abonnements récents
                </h2>
                <a href="<?= BASE_URL ?>/admin/subscriptions.php" class="text-sm text-blue-600 hover:text-blue-800 font-semibold">Voir tout <i class="fas fa-arrow-right ml-1"></i></a>
            </div>
            <div class="divide-y dark:divide-gray-700">
                <?php if (count($recentSubs) > 0): ?>
                    <?php foreach ($recentSubs as $sub): ?>
                        <div class="p-4 flex items-center gap-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-orange-500 rounded-full flex items-center justify-center text-white font-bold flex-shrink-0">
                                <?= strtoupper(substr($sub['first_name'], 0, 1)) ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-800 dark:text-white truncate"><?= clean($sub['first_name'] . ' ' . $sub['last_name']) ?></p>
                                <p class="text-xs text-gray-500">Plan: <span class="font-semibold text-blue-600"><?= clean($sub['plan_name']) ?></span></p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="font-bold text-green-600"><?= formatPrice($sub['amount_paid']) ?></p>
                                <p class="text-xs text-gray-500">Exp: <?= date('d/m/Y', strtotime($sub['end_date'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-crown text-4xl text-gray-300 mb-3"></i>
                        <p>Aucun abonnement actif pour le moment</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>