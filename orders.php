<?php
require_once __DIR__ . '/config/bootstrap.php';

require_once __DIR__ . '/includes/header.php';




requireLogin();
$user_id = $_SESSION['user_id'];

// Filtre par statut
$statusFilter = $_GET['status'] ?? 'all';
$allowedStatuses = ['all', 'pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
if (!in_array($statusFilter, $allowedStatuses)) {
    $statusFilter = 'all';
}

// Récupérer les commandes
$whereClause = "o.user_id = ?";
$params = [$user_id];

if ($statusFilter !== 'all') {
    $whereClause .= " AND o.status = ?";
    $params[] = $statusFilter;
}

$ordersStmt = $pdo->prepare("
    SELECT o.*, COUNT(oi.id) as items_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE $whereClause
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$ordersStmt->execute($params);
$orders = $ordersStmt->fetchAll();

// Statistiques par statut
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status IN ('confirmed', 'processing', 'shipped') THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM orders WHERE user_id = ?
");
$statsStmt->execute([$user_id]);
$stats = $statsStmt->fetch();

// Fonction helper pour les badges de statut
function getStatusBadge($status) {
    $styles = [
        'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
        'confirmed' => 'bg-blue-100 text-blue-800 border-blue-200',
        'processing' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
        'shipped' => 'bg-purple-100 text-purple-800 border-purple-200',
        'delivered' => 'bg-green-100 text-green-800 border-green-200',
        'cancelled' => 'bg-red-100 text-red-800 border-red-200'
    ];
    $labels = [
        'pending' => 'En attente',
        'confirmed' => 'Confirmée',
        'processing' => 'En préparation',
        'shipped' => 'Expédiée',
        'delivered' => 'Livrée',
        'cancelled' => 'Annulée'
    ];
    $style = $styles[$status] ?? 'bg-gray-100 text-gray-800';
    $label = $labels[$status] ?? ucfirst($status);
    return "<span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border $style'>$label</span>";
}

$pageTitle = 'Mes Commandes';

?>

<!-- Header -->
<section class="bg-gray-50 dark:bg-gray-900 py-8 border-b dark:border-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <nav class="text-sm text-gray-500 dark:text-gray-400 mb-2">
            <a href="<?= BASE_URL ?>/dashboard/customer.php" class="hover:text-blue-600 transition">Tableau de bord</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-gray-800 dark:text-white font-medium">Mes Commandes</span>
        </nav>
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-white">Historique des commandes</h1>
    </div>
</section>

<!-- Main Content -->
<section class="py-8 md:py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <a href="?status=all" class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-blue-600 hover:shadow-md transition">
                <p class="text-sm text-gray-500 dark:text-gray-400">Total</p>
                <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= $stats['total'] ?></p>
            </a>
            <a href="?status=pending" class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-yellow-500 hover:shadow-md transition">
                <p class="text-sm text-gray-500 dark:text-gray-400">En attente</p>
                <p class="text-2xl font-bold text-yellow-600"><?= $stats['pending'] ?></p>
            </a>
            <a href="?status=delivered" class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-green-500 hover:shadow-md transition">
                <p class="text-sm text-gray-500 dark:text-gray-400">Livrées</p>
                <p class="text-2xl font-bold text-green-600"><?= $stats['delivered'] ?></p>
            </a>
            <a href="?status=cancelled" class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-red-500 hover:shadow-md transition">
                <p class="text-sm text-gray-500 dark:text-gray-400">Annulées</p>
                <p class="text-2xl font-bold text-red-600"><?= $stats['cancelled'] ?></p>
            </a>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-2 mb-6 bg-white dark:bg-gray-800 p-2 rounded-xl shadow-sm">
            <?php
            $filters = [
                'all' => 'Toutes',
                'pending' => 'En attente',
                'confirmed' => 'Confirmées',
                'processing' => 'En préparation',
                'shipped' => 'Expédiées',
                'delivered' => 'Livrées',
                'cancelled' => 'Annulées'
            ];
            foreach ($filters as $key => $label):
                $isActive = $statusFilter === $key;
            ?>
                <a href="?status=<?= $key ?>" 
                   class="px-4 py-2 rounded-lg text-sm font-medium transition <?= $isActive ? 'bg-blue-600 text-white shadow-md' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Orders List -->
        <?php if (count($orders) > 0): ?>
            <div class="space-y-4">
                <?php foreach ($orders as $order): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden hover:shadow-md transition">
                        <!-- Order Header -->
                        <div class="bg-gray-50 dark:bg-gray-700/50 px-6 py-3 flex flex-wrap justify-between items-center gap-3 border-b dark:border-gray-700">
                            <div class="flex items-center gap-4">
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Commande</p>
                                    <p class="font-bold text-gray-800 dark:text-white"><?= $order['order_number'] ?></p>
                                </div>
                                <div class="hidden sm:block">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Date</p>
                                    <p class="text-sm text-gray-800 dark:text-white"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Articles</p>
                                    <p class="text-sm text-gray-800 dark:text-white"><?= $order['items_count'] ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <?= getStatusBadge($order['status']) ?>
                                <a href="<?= BASE_URL ?>/order-detail.php?order=<?= $order['order_number'] ?>" 
                                   class="text-blue-600 hover:text-orange-500 text-sm font-semibold flex items-center gap-1">
                                    Voir <i class="fas fa-chevron-right text-xs"></i>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Order Body -->
                        <div class="p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-credit-card text-gray-400 text-xl"></i>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Paiement</p>
                                    <p class="text-sm font-medium text-gray-800 dark:text-white">
                                        <?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?>
                                        <span class="ml-2 text-xs px-2 py-0.5 rounded 
                                            <?= $order['payment_status'] == 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                            <?= $order['payment_status'] == 'paid' ? 'Payé' : 'En attente' ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="text-left sm:text-right">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Total</p>
                                <p class="text-xl font-bold text-blue-600"><?= formatPrice($order['total']) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-12 text-center">
                <div class="w-24 h-24 mx-auto bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-6">
                    <i class="fas fa-box-open text-4xl text-gray-400"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Aucune commande trouvée</h3>
                <p class="text-gray-500 dark:text-gray-400 mb-6">
                    <?= $statusFilter !== 'all' ? 'Aucune commande avec ce statut.' : 'Vous n\'avez pas encore passé de commande.' ?>
                </p>
                <a href="<?= BASE_URL ?>/products.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-800 font-semibold transition">
                    <i class="fas fa-shopping-bag mr-2"></i>Découvrir les produits
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php  ?>