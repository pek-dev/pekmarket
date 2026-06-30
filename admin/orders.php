<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: ' . BASE_URL); exit; }

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $orderId = intval($_POST['order_id'] ?? 0);
    if ($orderId > 0) {
        try {
            if ($action === 'update_status') { $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$_POST['new_status'] ?? 'pending', $orderId]); $message = "Statut mis à jour."; }
            elseif ($action === 'update_payment') { $pdo->prepare("UPDATE orders SET payment_status = ? WHERE id = ?")->execute([$_POST['new_payment'] ?? 'pending', $orderId]); $message = "Paiement mis à jour."; }
        } catch (Exception $e) { $message = "Erreur: " . $e->getMessage(); }
    }
}

$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ["1=1"]; $params = [];
if ($statusFilter !== 'all') { $where[] = "o.status = ?"; $params[] = $statusFilter; }
if ($searchQuery) { $where[] = "(o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)"; $params[] = "%$searchQuery%"; $params[] = "%$searchQuery%"; $params[] = "%$searchQuery%"; }
$whereClause = implode(' AND ', $where);

try {
    $ordersStmt = $pdo->prepare("SELECT o.*, u.first_name, u.last_name, u.email, COUNT(oi.id) as items_count FROM orders o JOIN users u ON o.user_id = u.id LEFT JOIN order_items oi ON o.id = oi.order_id WHERE $whereClause GROUP BY o.id ORDER BY o.created_at DESC LIMIT ? OFFSET ?");
    $params[] = $perPage; $params[] = $offset;
    $ordersStmt->execute($params);
    $orders = $ordersStmt->fetchAll();
    $countStmt = $pdo->prepare("SELECT COUNT(DISTINCT o.id) FROM orders o JOIN users u ON o.user_id = u.id WHERE $whereClause");
    $countStmt->execute(array_slice($params, 0, -2));
    $totalOrders = $countStmt->fetchColumn();
    $totalPages = ceil($totalOrders / $perPage);
} catch (Exception $e) { $orders = []; $totalPages = 0; }

$pageTitle = 'Gestion des Commandes';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <nav class="text-sm text-gray-500 mb-2"><a href="<?= BASE_URL ?>/dashboard/admin.php" class="hover:text-blue-600">Admin</a> <i class="fas fa-chevron-right text-xs mx-2"></i> Commandes</nav>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white"><i class="fas fa-shopping-cart text-orange-600 mr-2"></i>Gestion des Commandes</h1>
    </div>

    <?php if ($message): ?><div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6"><?= clean($message) ?></div><?php endif; ?>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" class="flex flex-col md:flex-row gap-3">
            <input type="text" name="q" value="<?= clean($searchQuery) ?>" placeholder="Rechercher..." class="flex-1 px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
            <select name="status" onchange="this.form.submit()" class="px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                <option value="all" <?= $statusFilter == 'all' ? 'selected' : '' ?>>Tous les statuts</option>
                <option value="pending" <?= $statusFilter == 'pending' ? 'selected' : '' ?>>En attente</option>
                <option value="confirmed" <?= $statusFilter == 'confirmed' ? 'selected' : '' ?>>Confirmée</option>
                <option value="processing" <?= $statusFilter == 'processing' ? 'selected' : '' ?>>En préparation</option>
                <option value="shipped" <?= $statusFilter == 'shipped' ? 'selected' : '' ?>>Expédiée</option>
                <option value="delivered" <?= $statusFilter == 'delivered' ? 'selected' : '' ?>>Livrée</option>
                <option value="cancelled" <?= $statusFilter == 'cancelled' ? 'selected' : '' ?>>Annulée</option>
            </select>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold"><i class="fas fa-search mr-2"></i>Filtrer</button>
        </form>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden border border-gray-100 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">N° Commande</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Client</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Articles</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Total</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Paiement</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Statut</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    <?php foreach ($orders as $order): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3"><p class="font-bold text-blue-600 text-sm"><?= $order['order_number'] ?></p><p class="text-xs text-gray-500"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p></td>
                            <td class="px-4 py-3"><p class="text-sm font-medium text-gray-800 dark:text-white"><?= clean($order['first_name'] . ' ' . $order['last_name']) ?></p><p class="text-xs text-gray-500"><?= clean($order['email']) ?></p></td>
                            <td class="px-4 py-3 text-sm"><?= $order['items_count'] ?></td>
                            <td class="px-4 py-3 font-bold text-gray-800 dark:text-white"><?= formatPrice($order['total']) ?></td>
                            <td class="px-4 py-3">
                                <form method="POST" class="inline"><input type="hidden" name="order_id" value="<?= $order['id'] ?>"><input type="hidden" name="action" value="update_payment"><select name="new_payment" onchange="this.form.submit()" class="text-xs px-2 py-1 rounded border dark:border-gray-700 dark:bg-gray-700 <?= $order['payment_status'] == 'paid' ? 'text-green-600' : 'text-yellow-600' ?>"><option value="pending" <?= $order['payment_status'] == 'pending' ? 'selected' : '' ?>>En attente</option><option value="paid" <?= $order['payment_status'] == 'paid' ? 'selected' : '' ?>>Payé</option><option value="failed" <?= $order['payment_status'] == 'failed' ? 'selected' : '' ?>>Échoué</option></select></form>
                            </td>
                            <td class="px-4 py-3"><span class="inline-block px-2 py-1 rounded-full text-xs font-semibold <?= $order['status'] == 'delivered' ? 'bg-green-100 text-green-800' : ($order['status'] == 'cancelled' ? 'bg-red-100 text-red-800' : ($order['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800')) ?>"><?= ucfirst($order['status']) ?></span></td>
                            <td class="px-4 py-3">
                                <form method="POST" class="inline"><input type="hidden" name="order_id" value="<?= $order['id'] ?>"><input type="hidden" name="action" value="update_status"><select name="new_status" onchange="this.form.submit()" class="text-xs px-2 py-1 rounded border dark:border-gray-700 dark:bg-gray-700"><option value="pending" <?= $order['status'] == 'pending' ? 'selected' : '' ?>>En attente</option><option value="confirmed" <?= $order['status'] == 'confirmed' ? 'selected' : '' ?>>Confirmée</option><option value="processing" <?= $order['status'] == 'processing' ? 'selected' : '' ?>>Préparation</option><option value="shipped" <?= $order['status'] == 'shipped' ? 'selected' : '' ?>>Expédiée</option><option value="delivered" <?= $order['status'] == 'delivered' ? 'selected' : '' ?>>Livrée</option><option value="cancelled" <?= $order['status'] == 'cancelled' ? 'selected' : '' ?>>Annulée</option></select></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($orders) === 0): ?><div class="p-12 text-center text-gray-500"><i class="fas fa-shopping-bag text-4xl mb-4 text-gray-300"></i><p>Aucune commande trouvée</p></div><?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="mt-6 flex justify-center gap-2">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&status=<?= $statusFilter ?>&q=<?= urlencode($searchQuery) ?>" class="px-4 py-2 rounded-lg <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>