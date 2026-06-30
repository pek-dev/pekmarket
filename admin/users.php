<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: ' . BASE_URL); exit; }

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = intval($_POST['user_id'] ?? 0);
    if ($userId > 0 && $userId != $_SESSION['user_id']) {
        try {
            if ($action === 'toggle_status') {
                $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")->execute([$userId]);
                $message = "Statut modifié.";
            } elseif ($action === 'verify') {
                $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$userId]);
                $message = "Utilisateur vérifié.";
            } elseif ($action === 'delete') {
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
                $message = "Utilisateur supprimé.";
            } elseif ($action === 'change_role') {
                $newRole = $_POST['new_role'] ?? 'customer';
                if (in_array($newRole, ['customer', 'seller', 'admin'])) {
                    $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $userId]);
                    $message = "Rôle modifié en: " . ucfirst($newRole);
                }
            }
        } catch (Exception $e) { $message = "Erreur: " . $e->getMessage(); }
    }
}

$roleFilter = $_GET['role'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ["1=1"]; $params = [];
if ($roleFilter !== 'all') { $where[] = "u.role = ?"; $params[] = $roleFilter; }
if ($statusFilter !== 'all') { $where[] = "u.is_active = ?"; $params[] = $statusFilter === 'active' ? 1 : 0; }
if ($searchQuery) {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$searchQuery%"; $params[] = "%$searchQuery%"; $params[] = "%$searchQuery%";
}
$whereClause = implode(' AND ', $where);

try {
    $usersStmt = $pdo->prepare("SELECT u.*, COUNT(DISTINCT p.id) as products_count, COUNT(DISTINCT o.id) as orders_count FROM users u LEFT JOIN products p ON u.id = p.seller_id LEFT JOIN orders o ON u.id = o.user_id WHERE $whereClause GROUP BY u.id ORDER BY u.created_at DESC LIMIT ? OFFSET ?");
    $params[] = $perPage; $params[] = $offset;
    $usersStmt->execute($params);
    $users = $usersStmt->fetchAll();
    $countStmt = $pdo->prepare("SELECT COUNT(DISTINCT u.id) FROM users u WHERE $whereClause");
    $countStmt->execute(array_slice($params, 0, -2));
    $totalUsers = $countStmt->fetchColumn();
    $totalPages = ceil($totalUsers / $perPage);
} catch (Exception $e) { $users = []; $totalPages = 0; }

$pageTitle = 'Gestion des Utilisateurs';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <nav class="text-sm text-gray-500 mb-2"><a href="<?= BASE_URL ?>/dashboard/admin.php" class="hover:text-blue-600">Admin</a> <i class="fas fa-chevron-right text-xs mx-2"></i> Utilisateurs</nav>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white"><i class="fas fa-users text-blue-600 mr-2"></i>Gestion des Utilisateurs</h1>
    </div>

    <?php if ($message): ?><div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6"><?= clean($message) ?></div><?php endif; ?>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" class="flex flex-col md:flex-row gap-3">
            <input type="text" name="q" value="<?= clean($searchQuery) ?>" placeholder="Rechercher..." class="flex-1 px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
            <select name="role" onchange="this.form.submit()" class="px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                <option value="all" <?= $roleFilter == 'all' ? 'selected' : '' ?>>Tous les rôles</option>
                <option value="customer" <?= $roleFilter == 'customer' ? 'selected' : '' ?>>Clients</option>
                <option value="seller" <?= $roleFilter == 'seller' ? 'selected' : '' ?>>Vendeurs</option>
                <option value="admin" <?= $roleFilter == 'admin' ? 'selected' : '' ?>>Administrateurs</option>
            </select>
            <select name="status" onchange="this.form.submit()" class="px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                <option value="all" <?= $statusFilter == 'all' ? 'selected' : '' ?>>Tous les statuts</option>
                <option value="active" <?= $statusFilter == 'active' ? 'selected' : '' ?>>Actifs</option>
                <option value="inactive" <?= $statusFilter == 'inactive' ? 'selected' : '' ?>>Inactifs</option>
            </select>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold"><i class="fas fa-search mr-2"></i>Filtrer</button>
        </form>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden border border-gray-100 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Utilisateur</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Contact</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Rôle</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Stats</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Statut</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-orange-500 rounded-full flex items-center justify-center text-white font-bold"><?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?></div>
                                    <div>
                                        <p class="font-semibold text-gray-800 dark:text-white text-sm"><?= clean($user['first_name'] . ' ' . $user['last_name']) ?></p>
                                        <?php if ($user['is_verified']): ?><span class="text-xs text-blue-600"><i class="fas fa-check-circle"></i> Vérifié</span><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3"><p class="text-sm text-gray-800 dark:text-white"><?= clean($user['email']) ?></p><p class="text-xs text-gray-500"><?= clean($user['phone'] ?? '-') ?></p></td>
                            <td class="px-4 py-3"><span class="inline-block px-2 py-1 rounded text-xs font-semibold <?= $user['role'] == 'admin' ? 'bg-red-100 text-red-800' : ($user['role'] == 'seller' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800') ?>"><?= ucfirst($user['role']) ?></span></td>
                            <td class="px-4 py-3 text-xs"><p><i class="fas fa-box text-gray-400 mr-1"></i><?= $user['products_count'] ?> produits</p><p><i class="fas fa-shopping-bag text-gray-400 mr-1"></i><?= $user['orders_count'] ?> commandes</p></td>
                            <td class="px-4 py-3"><span class="inline-block px-2 py-1 rounded-full text-xs font-semibold <?= $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>"><?= $user['is_active'] ? 'Actif' : 'Inactif' ?></span></td>
                            <td class="px-4 py-3">
                                <div class="flex gap-1">
                                    <form method="POST" class="inline"><input type="hidden" name="user_id" value="<?= $user['id'] ?>"><input type="hidden" name="action" value="toggle_status"><button class="w-8 h-8 bg-yellow-100 text-yellow-600 rounded-lg hover:bg-yellow-200 flex items-center justify-center" title="Basculer"><i class="fas fa-power-off text-xs"></i></button></form>
                                    <?php if (!$user['is_verified']): ?><form method="POST" class="inline"><input type="hidden" name="user_id" value="<?= $user['id'] ?>"><input type="hidden" name="action" value="verify"><button class="w-8 h-8 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 flex items-center justify-center" title="Vérifier"><i class="fas fa-check text-xs"></i></button></form><?php endif; ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Supprimer ?');"><input type="hidden" name="user_id" value="<?= $user['id'] ?>"><input type="hidden" name="action" value="delete"><button class="w-8 h-8 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 flex items-center justify-center"><i class="fas fa-trash text-xs"></i></button></form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($users) === 0): ?><div class="p-12 text-center text-gray-500"><i class="fas fa-users text-4xl mb-4 text-gray-300"></i><p>Aucun utilisateur trouvé</p></div><?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="mt-6 flex justify-center gap-2">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&role=<?= $roleFilter ?>&status=<?= $statusFilter ?>&q=<?= urlencode($searchQuery) ?>" class="px-4 py-2 rounded-lg <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 hover:bg-gray-100' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>