<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: ' . BASE_URL); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adId = intval($_POST['ad_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($adId > 0) {
        try {
            if ($action === 'approve') { $pdo->prepare("UPDATE ad_campaigns SET status = 'active' WHERE id = ?")->execute([$adId]); }
            elseif ($action === 'reject') { $pdo->prepare("UPDATE ad_campaigns SET status = 'rejected' WHERE id = ?")->execute([$adId]); }
            elseif ($action === 'pause') { $pdo->prepare("UPDATE ad_campaigns SET status = 'paused' WHERE id = ?")->execute([$adId]); }
            elseif ($action === 'delete') { $pdo->prepare("DELETE FROM ad_campaigns WHERE id = ?")->execute([$adId]); }
        } catch (Exception $e) {}
        header('Location: ' . BASE_URL . '/admin/ads.php?status=' . ($_GET['status'] ?? 'all'));
        exit;
    }
}

$statusFilter = $_GET['status'] ?? 'all';
$typeFilter = $_GET['type'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = ["1=1"]; $params = [];
if ($statusFilter !== 'all') { $where[] = "a.status = ?"; $params[] = $statusFilter; }
if ($typeFilter !== 'all') { $where[] = "a.ad_type = ?"; $params[] = $typeFilter; }
if ($searchQuery) { $where[] = "(a.title LIKE ? OR u.first_name LIKE ?)"; $params[] = "%$searchQuery%"; $params[] = "%$searchQuery%"; }
$whereClause = implode(' AND ', $where);

try {
    $adsStmt = $pdo->prepare("SELECT a.*, u.first_name, u.last_name FROM ad_campaigns a JOIN users u ON a.user_id = u.id WHERE $whereClause ORDER BY a.created_at DESC LIMIT ? OFFSET ?");
    $params[] = $perPage; $params[] = $offset;
    $adsStmt->execute($params);
    $ads = $adsStmt->fetchAll();
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ad_campaigns a JOIN users u ON a.user_id = u.id WHERE $whereClause");
    $countStmt->execute(array_slice($params, 0, -2));
    $totalAds = $countStmt->fetchColumn();
    $totalPages = ceil($totalAds / $perPage);
} catch (Exception $e) { $ads = []; $totalPages = 0; }

$pageTitle = 'Gestion des Publicités';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <nav class="text-sm text-gray-500 mb-2"><a href="<?= BASE_URL ?>/dashboard/admin.php" class="hover:text-blue-600">Admin</a> <i class="fas fa-chevron-right text-xs mx-2"></i> Publicités</nav>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white"><i class="fas fa-ad text-red-500 mr-2"></i>Gestion des Publicités</h1>
        <p class="text-gray-500 text-sm"><?= $totalAds ?> campagnes au total</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" class="flex flex-col md:flex-row gap-3">
            <input type="text" name="q" value="<?= clean($searchQuery) ?>" placeholder="Rechercher..." class="flex-1 px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
            <select name="status" onchange="this.form.submit()" class="px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                <option value="all" <?= $statusFilter == 'all' ? 'selected' : '' ?>>Tous les statuts</option>
                <option value="pending" <?= $statusFilter == 'pending' ? 'selected' : '' ?>>En attente</option>
                <option value="active" <?= $statusFilter == 'active' ? 'selected' : '' ?>>Actives</option>
                <option value="paused" <?= $statusFilter == 'paused' ? 'selected' : '' ?>>En pause</option>
                <option value="rejected" <?= $statusFilter == 'rejected' ? 'selected' : '' ?>>Rejetées</option>
            </select>
            <select name="type" onchange="this.form.submit()" class="px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                <option value="all" <?= $typeFilter == 'all' ? 'selected' : '' ?>>Tous les types</option>
                <option value="product" <?= $typeFilter == 'product' ? 'selected' : '' ?>>Produit</option>
                <option value="shop" <?= $typeFilter == 'shop' ? 'selected' : '' ?>>Boutique</option>
                <option value="event" <?= $typeFilter == 'event' ? 'selected' : '' ?>>Événement</option>
                <option value="conference" <?= $typeFilter == 'conference' ? 'selected' : '' ?>>Conférence</option>
            </select>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold"><i class="fas fa-search mr-2"></i>Filtrer</button>
        </form>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden border border-gray-100 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Publicité</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Type</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Annonceur</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Budget</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Stats</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Statut</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th></tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    <?php foreach ($ads as $ad): 
                        $ctr = $ad['impressions'] > 0 ? round(($ad['clicks'] / $ad['impressions']) * 100, 2) : 0;
                    ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3"><div class="flex items-center gap-3"><img src="<?= clean($ad['image_path']) ?>" alt="" class="w-12 h-12 rounded-lg object-cover"><div><p class="font-semibold text-gray-800 dark:text-white text-sm line-clamp-1"><?= clean($ad['title']) ?></p><p class="text-xs text-gray-500"><?= ucfirst($ad['placement']) ?></p></div></div></td>
                            <td class="px-4 py-3"><span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800"><?= ucfirst($ad['ad_type']) ?></span></td>
                            <td class="px-4 py-3"><p class="text-sm font-medium"><?= clean($ad['first_name'] . ' ' . $ad['last_name']) ?></p></td>
                            <td class="px-4 py-3"><p class="text-sm font-bold"><?= formatPrice($ad['budget_total']) ?></p><p class="text-xs text-gray-500">Dépensé: <?= formatPrice($ad['spent']) ?></p></td>
                            <td class="px-4 py-3 text-xs"><p><i class="fas fa-eye mr-1 text-gray-400"></i><?= number_format($ad['impressions']) ?></p><p><i class="fas fa-mouse-pointer mr-1 text-gray-400"></i><?= number_format($ad['clicks']) ?> (<?= $ctr ?>%)</p></td>
                            <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-semibold <?= $ad['status'] == 'active' ? 'bg-green-100 text-green-800' : ($ad['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : ($ad['status'] == 'rejected' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) ?>"><?= ucfirst($ad['status']) ?></span></td>
                            <td class="px-4 py-3">
                                <div class="flex gap-1">
                                    <?php if ($ad['status'] === 'pending'): ?>
                                        <form method="POST" class="inline"><input type="hidden" name="ad_id" value="<?= $ad['id'] ?>"><input type="hidden" name="action" value="approve"><button class="w-8 h-8 bg-green-100 text-green-600 rounded-lg hover:bg-green-200 flex items-center justify-center"><i class="fas fa-check text-xs"></i></button></form>
                                        <form method="POST" class="inline"><input type="hidden" name="ad_id" value="<?= $ad['id'] ?>"><input type="hidden" name="action" value="reject"><button class="w-8 h-8 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 flex items-center justify-center"><i class="fas fa-times text-xs"></i></button></form>
                                    <?php endif; ?>
                                    <?php if ($ad['status'] === 'active'): ?>
                                        <form method="POST" class="inline"><input type="hidden" name="ad_id" value="<?= $ad['id'] ?>"><input type="hidden" name="action" value="pause"><button class="w-8 h-8 bg-yellow-100 text-yellow-600 rounded-lg hover:bg-yellow-200 flex items-center justify-center"><i class="fas fa-pause text-xs"></i></button></form>
                                    <?php endif; ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Supprimer ?');"><input type="hidden" name="ad_id" value="<?= $ad['id'] ?>"><input type="hidden" name="action" value="delete"><button class="w-8 h-8 bg-gray-100 text-gray-600 rounded-lg hover:bg-red-100 hover:text-red-600 flex items-center justify-center"><i class="fas fa-trash text-xs"></i></button></form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($ads) === 0): ?><div class="p-12 text-center text-gray-500"><i class="fas fa-ad text-4xl mb-4 text-gray-300"></i><p>Aucune publicité trouvée</p></div><?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>