<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: ' . BASE_URL); exit; }

// Supprimer un message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        $pdo->prepare("DELETE FROM messages WHERE id = ?")->execute([intval($_POST['message_id'] ?? 0)]);
        header('Location: ' . BASE_URL . '/admin/messages.php?deleted=1');
        exit;
    } catch (Exception $e) {}
}

$searchQuery = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = ["1=1"];
$params = [];

if ($searchQuery) {
    $where[] = "(m.message LIKE ? OR fu.first_name LIKE ? OR fu.last_name LIKE ? OR tu.first_name LIKE ? OR tu.last_name LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$whereClause = implode(' AND ', $where);

try {
    $stmt = $pdo->prepare("
        SELECT m.*, 
               fu.first_name as from_first, fu.last_name as from_last, fu.role as from_role,
               tu.first_name as to_first, tu.last_name as to_last, tu.role as to_role,
               p.name as product_name
        FROM messages m
        JOIN users fu ON m.from_user_id = fu.id
        JOIN users tu ON m.to_user_id = tu.id
        LEFT JOIN products p ON m.product_id = p.id
        WHERE $whereClause
        ORDER BY m.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $perPage; $params[] = $offset;
    $stmt->execute($params);
    $messages = $stmt->fetchAll();
    
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM messages m
        JOIN users fu ON m.from_user_id = fu.id
        JOIN users tu ON m.to_user_id = tu.id
        WHERE $whereClause
    ");
    $countStmt->execute(array_slice($params, 0, -2));
    $totalMessages = $countStmt->fetchColumn();
    $totalPages = ceil($totalMessages / $perPage);
    
    $statsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
            COUNT(DISTINCT from_user_id) as senders,
            COUNT(DISTINCT to_user_id) as receivers
        FROM messages
    ");
    $stats = $statsStmt->fetch();
} catch (Exception $e) { $messages = []; $totalPages = 0; $stats = ['total'=>0,'unread'=>0,'senders'=>0,'receivers'=>0]; }

$pageTitle = 'Gestion des Messages';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <nav class="text-sm text-gray-500 mb-2">
            <a href="<?= BASE_URL ?>/dashboard/admin.php" class="hover:text-blue-600">Admin</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-gray-800 dark:text-white font-medium">Messages</span>
        </nav>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
            <i class="fas fa-comments text-blue-600"></i> Gestion des Messages
        </h1>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6">✅ Message supprimé</div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-blue-500">
            <p class="text-xs text-gray-500">Total messages</p>
            <p class="text-2xl font-bold"><?= $stats['total'] ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-red-500">
            <p class="text-xs text-gray-500">Non lus</p>
            <p class="text-2xl font-bold text-red-600"><?= $stats['unread'] ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-green-500">
            <p class="text-xs text-gray-500">Expéditeurs uniques</p>
            <p class="text-2xl font-bold"><?= $stats['senders'] ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-purple-500">
            <p class="text-xs text-gray-500">Destinataires uniques</p>
            <p class="text-2xl font-bold"><?= $stats['receivers'] ?></p>
        </div>
    </div>

    <!-- Filtres -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" class="flex flex-col md:flex-row gap-3">
            <input type="text" name="q" value="<?= clean($searchQuery) ?>" placeholder="Rechercher dans les messages..." 
                   class="flex-1 px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">
                <i class="fas fa-search mr-2"></i>Rechercher
            </button>
        </form>
    </div>

    <!-- Liste des messages -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">De</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">À</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Message</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Produit</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Statut</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    <?php foreach ($messages as $msg): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3">
                                <p class="text-sm font-semibold"><?= clean($msg['from_first'] . ' ' . $msg['from_last']) ?></p>
                                <span class="text-xs px-2 py-0.5 rounded-full font-semibold
                                    <?= $msg['from_role'] == 'admin' ? 'bg-red-100 text-red-800' : 
                                       ($msg['from_role'] == 'seller' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800') ?>">
                                    <?= ucfirst($msg['from_role']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm font-semibold"><?= clean($msg['to_first'] . ' ' . $msg['to_last']) ?></p>
                                <span class="text-xs px-2 py-0.5 rounded-full font-semibold
                                    <?= $msg['to_role'] == 'admin' ? 'bg-red-100 text-red-800' : 
                                       ($msg['to_role'] == 'seller' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800') ?>">
                                    <?= ucfirst($msg['to_role']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 max-w-xs truncate"><?= clean(substr($msg['message'], 0, 60)) ?></td>
                            <td class="px-4 py-3 text-xs text-gray-500"><?= clean($msg['product_name'] ?? '-') ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $msg['is_read'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                    <?= $msg['is_read'] ? 'Lu' : 'Non lu' ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500"><?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?></td>
                            <td class="px-4 py-3">
                                <div class="flex gap-1">
                                    <a href="<?= BASE_URL ?>/messages.php?with=<?= $msg['from_user_id'] ?>" class="w-8 h-8 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 flex items-center justify-center" title="Voir">
                                        <i class="fas fa-eye text-xs"></i>
                                    </a>
                                    <form method="POST" class="inline" onsubmit="return confirm('Supprimer ce message ?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                        <button class="w-8 h-8 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 flex items-center justify-center">
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (count($messages) === 0): ?>
            <div class="p-12 text-center text-gray-500">
                <i class="fas fa-comments text-4xl mb-4 text-gray-300"></i>
                <p>Aucun message trouvé</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="mt-6 flex justify-center gap-2">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&q=<?= urlencode($searchQuery) ?>" 
                   class="px-4 py-2 rounded-lg <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 hover:bg-gray-100' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>