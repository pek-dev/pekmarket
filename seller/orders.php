<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'seller') { header('Location: ' . BASE_URL); exit; }

$sellerId = $_SESSION['user_id'];
$message = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $orderId = intval($_POST['order_id'] ?? 0);
    
    if ($orderId > 0) {
        try {
            // Vérifier que la commande contient les produits du vendeur
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ? AND p.seller_id = ?
            ");
            $checkStmt->execute([$orderId, $sellerId]);
            
            if ($checkStmt->fetchColumn() > 0) {
                if ($action === 'update_status') {
                    $newStatus = $_POST['new_status'] ?? '';
                    $allowedStatuses = ['confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
                    if (in_array($newStatus, $allowedStatuses)) {
                        $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$newStatus, $orderId]);
                        $message = "✅ Statut de la commande mis à jour";
                    }
                }
            }
        } catch (Exception $e) {
            $message = "❌ Erreur: " . $e->getMessage();
        }
    }
}

// Filtres
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = ["p.seller_id = ?"];
$params = [$sellerId];

if ($statusFilter !== 'all') { $where[] = "o.status = ?"; $params[] = $statusFilter; }
if ($searchQuery) {
    $where[] = "(o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $params[] = "%$searchQuery%"; $params[] = "%$searchQuery%"; $params[] = "%$searchQuery%";
}

$whereClause = implode(' AND ', $where);

// Récupérer les commandes
try {
    $ordersStmt = $pdo->prepare("
        SELECT o.*, u.first_name, u.last_name, u.email, u.phone,
               o.shipping_province, o.shipping_city, o.shipping_address,
               GROUP_CONCAT(DISTINCT oi.product_name SEPARATOR ' | ') as products_names,
               COUNT(DISTINCT oi.id) as items_count,
               SUM(oi.total) as seller_total
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE $whereClause
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $perPage; $params[] = $offset;
    $ordersStmt->execute($params);
    $orders = $ordersStmt->fetchAll();
    
    $countStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT o.id) 
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE $whereClause
    ");
    $countStmt->execute(array_slice($params, 0, -2));
    $totalOrders = $countStmt->fetchColumn();
    $totalPages = ceil($totalOrders / $perPage);
} catch (Exception $e) {
    $orders = []; $totalPages = 0;
}

// Statistiques
$stats = ['total' => 0, 'pending' => 0, 'processing' => 0, 'delivered' => 0, 'revenue' => 0];
try {
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT o.id) as total,
            SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN o.status IN ('confirmed', 'processing', 'shipped') THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN o.payment_status = 'paid' THEN oi.total ELSE 0 END) as revenue
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = ?
    ");
    $statsStmt->execute([$sellerId]);
    $stats = $statsStmt->fetch();
} catch (Exception $e) {}

$pageTitle = 'Mes Commandes';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <nav class="text-sm text-gray-500 mb-2">
            <a href="<?= BASE_URL ?>/dashboard/seller.php" class="hover:text-blue-600">Dashboard</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-gray-800 dark:text-white font-medium">Commandes</span>
        </nav>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
            <i class="fas fa-shopping-bag text-orange-600"></i> Mes Commandes
        </h1>
        <p class="text-gray-500 mt-1"><?= $totalOrders ?> commande(s) au total</p>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 flex items-center justify-between">
            <span><?= clean($message) ?></span>
            <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-blue-500">
            <p class="text-xs text-gray-500">Total</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= $stats['total'] ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-yellow-500">
            <p class="text-xs text-gray-500">En attente</p>
            <p class="text-2xl font-bold text-yellow-600"><?= $stats['pending'] ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-orange-500">
            <p class="text-xs text-gray-500">En cours</p>
            <p class="text-2xl font-bold text-orange-600"><?= $stats['processing'] ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-green-500">
            <p class="text-xs text-gray-500">Livrées</p>
            <p class="text-2xl font-bold text-green-600"><?= $stats['delivered'] ?></p>
        </div>
        <div class="bg-gradient-to-br from-green-500 to-emerald-600 p-4 rounded-xl shadow-sm text-white">
            <p class="text-xs text-green-100">Revenus</p>
            <p class="text-xl font-bold"><?= number_format($stats['revenue'] / 1000, 0) ?>K FBu</p>
        </div>
    </div>

    <!-- Filtres -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" class="flex flex-col md:flex-row gap-3">
            <input type="text" name="q" value="<?= clean($searchQuery) ?>" placeholder="Rechercher (n° commande, client)..." 
                   class="flex-1 px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
            <select name="status" onchange="this.form.submit()" class="px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                <option value="all" <?= $statusFilter == 'all' ? 'selected' : '' ?>>Tous les statuts</option>
                <option value="pending" <?= $statusFilter == 'pending' ? 'selected' : '' ?>>En attente</option>
                <option value="confirmed" <?= $statusFilter == 'confirmed' ? 'selected' : '' ?>>Confirmée</option>
                <option value="processing" <?= $statusFilter == 'processing' ? 'selected' : '' ?>>En préparation</option>
                <option value="shipped" <?= $statusFilter == 'shipped' ? 'selected' : '' ?>>Expédiée</option>
                <option value="delivered" <?= $statusFilter == 'delivered' ? 'selected' : '' ?>>Livrée</option>
                <option value="cancelled" <?= $statusFilter == 'cancelled' ? 'selected' : '' ?>>Annulée</option>
            </select>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">
                <i class="fas fa-search mr-2"></i>Filtrer
            </button>
        </form>
    </div>

    <!-- Liste des commandes -->
    <?php if (count($orders) > 0): ?>
        <div class="space-y-4">
            <?php foreach ($orders as $order): 
                $statusColors = [
                    'pending' => 'yellow', 'confirmed' => 'blue', 'processing' => 'indigo',
                    'shipped' => 'purple', 'delivered' => 'green', 'cancelled' => 'red'
                ];
                $statusLabels = [
                    'pending' => 'En attente', 'confirmed' => 'Confirmée', 'processing' => 'En préparation',
                    'shipped' => 'Expédiée', 'delivered' => 'Livrée', 'cancelled' => 'Annulée'
                ];
                $color = $statusColors[$order['status']] ?? 'gray';
                $label = $statusLabels[$order['status']] ?? ucfirst($order['status']);
            ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    
                    <!-- Header de la commande -->
                    <div class="bg-gray-50 dark:bg-gray-700/50 px-4 md:px-6 py-3 flex flex-wrap justify-between items-center gap-3 border-b dark:border-gray-700">
                        <div class="flex items-center gap-4 flex-wrap">
                            <div>
                                <p class="text-xs text-gray-500">Commande</p>
                                <p class="font-bold text-blue-600"><?= $order['order_number'] ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Date</p>
                                <p class="text-sm text-gray-800 dark:text-white"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Articles</p>
                                <p class="text-sm text-gray-800 dark:text-white"><?= $order['items_count'] ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-<?= $color ?>-100 text-<?= $color ?>-800"><?= $label ?></span>
                            <span class="px-2 py-1 rounded text-xs font-semibold <?= $order['payment_status'] == 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                <?= $order['payment_status'] == 'paid' ? '💰 Payé' : '⏳ En attente' ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Corps de la commande -->
                    <div class="p-4 md:p-6">
                        <div class="grid md:grid-cols-3 gap-6">
                            
                            <!-- Infos client -->
                            <div>
                                <h4 class="font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                                    <i class="fas fa-user text-blue-600"></i> Client
                                </h4>
                                <p class="text-sm font-semibold text-gray-800 dark:text-white"><?= clean($order['first_name'] . ' ' . $order['last_name']) ?></p>
                                <p class="text-xs text-gray-500 mt-1"><i class="fas fa-envelope mr-1"></i><?= clean($order['email']) ?></p>
                                <p class="text-xs text-gray-500 mt-1"><i class="fas fa-phone mr-1"></i><?= clean($order['phone']) ?></p>
                            </div>
                            
                            <!-- Adresse de livraison -->
                            <div>
                                <h4 class="font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                                    <i class="fas fa-map-marker-alt text-orange-500"></i> Livraison
                                </h4>
                                <p class="text-sm text-gray-700 dark:text-gray-300"><?= clean($order['shipping_address']) ?></p>
                                <p class="text-xs text-gray-500 mt-1"><?= clean($order['shipping_city']) ?>, <?= clean($order['shipping_province']) ?></p>
                            </div>
                            
                            <!-- Total et actions -->
                            <div>
                                <h4 class="font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                                    <i class="fas fa-receipt text-green-600"></i> Votre total
                                </h4>
                                <p class="text-2xl font-bold text-green-600"><?= formatPrice($order['seller_total']) ?></p>
                                
                                <?php if ($order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                                    <form method="POST" class="mt-3">
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <select name="new_status" onchange="this.form.submit()" class="w-full px-3 py-2 border dark:border-gray-700 rounded-lg text-sm dark:bg-gray-700 dark:text-white">
                                            <option value="">Changer le statut...</option>
                                            <?php if ($order['status'] === 'pending'): ?>
                                                <option value="confirmed">✓ Confirmer</option>
                                                <option value="cancelled">✗ Annuler</option>
                                            <?php elseif ($order['status'] === 'confirmed'): ?>
                                                <option value="processing">📦 En préparation</option>
                                            <?php elseif ($order['status'] === 'processing'): ?>
                                                <option value="shipped">🚚 Expédier</option>
                                            <?php elseif ($order['status'] === 'shipped'): ?>
                                                <option value="delivered">✅ Marquer livrée</option>
                                            <?php endif; ?>
                                        </select>
                                    </form>
                                <?php else: ?>
                                    <p class="text-xs text-gray-500 mt-2">Commande terminée</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Produits commandés -->
                        <div class="mt-4 pt-4 border-t dark:border-gray-700">
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Produits commandés :</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300"><?= clean($order['products_names']) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="mt-6 flex justify-center gap-2">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>&status=<?= $statusFilter ?>&q=<?= urlencode($searchQuery) ?>" 
                       class="px-4 py-2 rounded-lg <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 hover:bg-gray-100' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-12 text-center">
            <i class="fas fa-shopping-bag text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Aucune commande trouvée</h3>
            <p class="text-gray-500">Les commandes de vos produits apparaîtront ici</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>