<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: ' . BASE_URL); exit; }

$typeFilter = $_GET['type'] ?? 'all';
$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo = $_GET['to'] ?? date('Y-m-d');

$where = ["1=1"]; $params = [];
if ($typeFilter !== 'all') { $where[] = "rt.type = ?"; $params[] = $typeFilter; }
if ($dateFrom) { $where[] = "DATE(rt.created_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo) { $where[] = "DATE(rt.created_at) <= ?"; $params[] = $dateTo; }
$whereClause = implode(' AND ', $where);

$transactions = []; $statsByType = []; $totalRevenue = 0;
try {
    $transStmt = $pdo->prepare("SELECT rt.*, u.first_name, u.last_name, u.email FROM revenue_transactions rt JOIN users u ON rt.user_id = u.id WHERE $whereClause ORDER BY rt.created_at DESC LIMIT 200");
    $transStmt->execute($params);
    $transactions = $transStmt->fetchAll();
    
    $statsStmt = $pdo->prepare("SELECT type, COUNT(*) as count, SUM(CASE WHEN status='completed' THEN amount ELSE 0 END) as total_completed FROM revenue_transactions WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY type");
    $statsStmt->execute([$dateFrom, $dateTo]);
    $statsByType = $statsStmt->fetchAll();
    $totalRevenue = array_sum(array_column($statsByType, 'total_completed'));
} catch (Exception $e) {}

$pageTitle = 'Revenus & Finances';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <nav class="text-sm text-gray-500 mb-2"><a href="<?= BASE_URL ?>/dashboard/admin.php" class="hover:text-blue-600">Admin</a> <i class="fas fa-chevron-right text-xs mx-2"></i> Revenus</nav>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white"><i class="fas fa-wallet text-teal-500 mr-2"></i>Revenus & Finances</h1>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" class="flex flex-col md:flex-row gap-3">
            <input type="date" name="from" value="<?= $dateFrom ?>" class="px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
            <input type="date" name="to" value="<?= $dateTo ?>" class="px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
            <select name="type" onchange="this.form.submit()" class="px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                <option value="all" <?= $typeFilter == 'all' ? 'selected' : '' ?>>Tous les types</option>
                <option value="subscription" <?= $typeFilter == 'subscription' ? 'selected' : '' ?>>Abonnements</option>
                <option value="ad_campaign" <?= $typeFilter == 'ad_campaign' ? 'selected' : '' ?>>Publicités</option>
                <option value="shop_boost" <?= $typeFilter == 'shop_boost' ? 'selected' : '' ?>>Boutiques sponsor</option>
                <option value="commission" <?= $typeFilter == 'commission' ? 'selected' : '' ?>>Commissions</option>
            </select>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold"><i class="fas fa-filter mr-2"></i>Filtrer</button>
        </form>
    </div>

    <div class="grid md:grid-cols-4 gap-4 mb-8">
        <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl p-5 text-white"><p class="text-green-100 text-sm">Revenu total</p><p class="text-2xl font-bold mt-1"><?= formatPrice($totalRevenue) ?></p></div>
        <?php foreach ($statsByType as $stat): 
            $colors = ['subscription' => 'purple', 'ad_campaign' => 'blue', 'shop_boost' => 'pink', 'commission' => 'orange'];
            $color = $colors[$stat['type']] ?? 'gray';
        ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border-t-4 border-<?= $color ?>-500"><p class="text-xs text-gray-500 uppercase"><?= ucfirst(str_replace('_', ' ', $stat['type'])) ?></p><p class="text-xl font-bold text-<?= $color ?>-600 mt-1"><?= formatPrice($stat['total_completed']) ?></p><p class="text-xs text-gray-500"><?= $stat['count'] ?> transactions</p></div>
        <?php endforeach; ?>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="p-4 border-b dark:border-gray-700"><h2 class="font-bold text-gray-800 dark:text-white"><?= count($transactions) ?> transactions</h2></div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700"><tr><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Utilisateur</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Type</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Description</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Montant</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Statut</th></tr></thead>
                <tbody class="divide-y dark:divide-gray-700">
                    <?php foreach ($transactions as $t): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 text-sm"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                            <td class="px-4 py-3"><p class="text-sm font-medium"><?= clean($t['first_name'] . ' ' . $t['last_name']) ?></p><p class="text-xs text-gray-500"><?= clean($t['email']) ?></p></td>
                            <td class="px-4 py-3"><span class="px-2 py-1 rounded text-xs font-semibold <?= $t['type'] == 'subscription' ? 'bg-purple-100 text-purple-800' : ($t['type'] == 'ad_campaign' ? 'bg-blue-100 text-blue-800' : ($t['type'] == 'shop_boost' ? 'bg-pink-100 text-pink-800' : 'bg-orange-100 text-orange-800')) ?>"><?= ucfirst(str_replace('_', ' ', $t['type'])) ?></span></td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300"><?= clean($t['description'] ?? '-') ?></td>
                            <td class="px-4 py-3 font-bold text-green-600"><?= formatPrice($t['amount']) ?></td>
                            <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-semibold <?= $t['status'] == 'completed' ? 'bg-green-100 text-green-800' : ($t['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>"><?= ucfirst($t['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>