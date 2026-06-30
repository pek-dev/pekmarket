<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'seller') { header('Location: ' . BASE_URL); exit; }

$sellerId = $_SESSION['user_id'];
$searchQuery = trim($_GET['q'] ?? '');

$where = ["p.seller_id = ?"];
$params = [$sellerId];
if ($searchQuery) {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$searchQuery%"; $params[] = "%$searchQuery%"; $params[] = "%$searchQuery%";
}
$whereClause = implode(' AND ', $where);

$customers = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.city, u.province, u.created_at,
               COUNT(DISTINCT o.id) as orders_count,
               COALESCE(SUM(o.total), 0) as total_spent,
               MAX(o.created_at) as last_order
        FROM users u
        JOIN orders o ON u.id = o.user_id
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE $whereClause
        GROUP BY u.id
        ORDER BY total_spent DESC
    ");
    $stmt->execute($params);
    $customers = $stmt->fetchAll();
} catch (Exception $e) {}

$pageTitle = 'Mes Clients';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
            <i class="fas fa-users text-blue-600"></i> Mes Clients
        </h1>
        <p class="text-gray-500 mt-1"><?= count($customers) ?> client(s)</p>
    </div>

    <!-- Recherche -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" class="flex gap-3">
            <input type="text" name="q" value="<?= clean($searchQuery) ?>" placeholder="Rechercher un client..." 
                   class="flex-1 px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">
                <i class="fas fa-search mr-2"></i>Rechercher
            </button>
        </form>
    </div>

    <!-- Liste -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Client</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Contact</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Localisation</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Commandes</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Total dépensé</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Dernière commande</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    <?php foreach ($customers as $c): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-orange-500 rounded-full flex items-center justify-center text-white font-bold">
                                        <?= strtoupper(substr($c['first_name'], 0, 1) . substr($c['last_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800 dark:text-white text-sm"><?= clean($c['first_name'] . ' ' . $c['last_name']) ?></p>
                                        <p class="text-xs text-gray-500">Client depuis <?= date('m/Y', strtotime($c['created_at'])) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm text-gray-800 dark:text-white"><?= clean($c['email']) ?></p>
                                <p class="text-xs text-gray-500"><?= clean($c['phone'] ?? '-') ?></p>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300"><?= clean($c['city'] ?? $c['province'] ?? '-') ?></td>
                            <td class="px-4 py-3"><span class="font-bold text-blue-600"><?= $c['orders_count'] ?></span></td>
                            <td class="px-4 py-3"><span class="font-bold text-green-600"><?= formatPrice($c['total_spent']) ?></span></td>
                            <td class="px-4 py-3 text-xs text-gray-500"><?= $c['last_order'] ? date('d/m/Y', strtotime($c['last_order'])) : '-' ?></td>
                            <td class="px-4 py-3">
                                <a href="<?= BASE_URL ?>/messages.php?with=<?= $c['id'] ?>" class="px-3 py-1 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 text-xs font-semibold">
                                    <i class="fas fa-comment mr-1"></i>Message
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (count($customers) === 0): ?>
            <div class="p-12 text-center text-gray-500">
                <i class="fas fa-users text-4xl mb-4 text-gray-300"></i>
                <p>Aucun client pour le moment</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>