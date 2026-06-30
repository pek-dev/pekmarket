<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: ' . BASE_URL); exit; }

$message = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $withdrawalId = intval($_POST['withdrawal_id'] ?? 0);
    $notes = clean(trim($_POST['admin_notes'] ?? ''));
    
    if ($withdrawalId > 0) {
        try {
            if ($action === 'approve') {
                $pdo->prepare("UPDATE withdrawals SET status = 'approved', admin_notes = ?, processed_at = NOW() WHERE id = ?")
                    ->execute([$notes, $withdrawalId]);
                $message = "✅ Retrait approuvé";
            }
            elseif ($action === 'complete') {
                $pdo->prepare("UPDATE withdrawals SET status = 'completed', completed_at = NOW() WHERE id = ?")
                    ->execute([$withdrawalId]);
                $message = "✅ Retrait marqué comme complété";
            }
            elseif ($action === 'reject') {
                $pdo->prepare("UPDATE withdrawals SET status = 'rejected', admin_notes = ?, processed_at = NOW() WHERE id = ?")
                    ->execute([$notes, $withdrawalId]);
                $message = "❌ Retrait rejeté";
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
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ["1=1"];
$params = [];

if ($statusFilter !== 'all') { $where[] = "w.status = ?"; $params[] = $statusFilter; }
if ($searchQuery) {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR w.account_number LIKE ?)";
    $params[] = "%$searchQuery%"; $params[] = "%$searchQuery%"; $params[] = "%$searchQuery%";
}

$whereClause = implode(' AND ', $where);

try {
    $stmt = $pdo->prepare("
        SELECT w.*, u.first_name, u.last_name, u.email, u.phone
        FROM withdrawals w
        JOIN users u ON w.seller_id = u.id
        WHERE $whereClause
        ORDER BY 
            CASE w.status 
                WHEN 'pending' THEN 1 
                WHEN 'approved' THEN 2 
                WHEN 'completed' THEN 3 
                WHEN 'rejected' THEN 4 
                ELSE 5 
            END,
            w.requested_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $perPage; $params[] = $offset;
    $stmt->execute($params);
    $withdrawals = $stmt->fetchAll();
    
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM withdrawals w JOIN users u ON w.seller_id = u.id WHERE $whereClause");
    $countStmt->execute(array_slice($params, 0, -2));
    $totalWithdrawals = $countStmt->fetchColumn();
    $totalPages = ceil($totalWithdrawals / $perPage);
    
    // Stats globales
    $statsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_amount,
            SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_amount
        FROM withdrawals
    ");
    $stats = $statsStmt->fetch();
} catch (Exception $e) { 
    $withdrawals = []; $totalPages = 0; 
    $stats = ['total' => 0, 'pending_count' => 0, 'pending_amount' => 0, 'completed_amount' => 0, 'approved_amount' => 0];
}

$pageTitle = 'Gestion des Retraits';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <nav class="text-sm text-gray-500 mb-2">
            <a href="<?= BASE_URL ?>/dashboard/admin.php" class="hover:text-blue-600">Admin</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-gray-800 dark:text-white font-medium">Retraits vendeurs</span>
        </nav>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
            <i class="fas fa-money-bill-wave text-green-600"></i> Gestion des Retraits
        </h1>
    </div>

    <?php if ($message): ?>
        <div class="bg-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-50 border border-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-200 text-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-700 px-4 py-3 rounded-xl mb-6"><?= clean($message) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-yellow-500">
            <p class="text-xs text-gray-500">En attente</p>
            <p class="text-2xl font-bold text-yellow-600"><?= $stats['pending_count'] ?></p>
            <p class="text-xs text-gray-500"><?= formatPrice($stats['pending_amount']) ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-blue-500">
            <p class="text-xs text-gray-500">Approuvés</p>
            <p class="text-2xl font-bold text-blue-600"><?= formatPrice($stats['approved_amount']) ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-green-500">
            <p class="text-xs text-gray-500">Complétés</p>
            <p class="text-2xl font-bold text-green-600"><?= formatPrice($stats['completed_amount']) ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-purple-500">
            <p class="text-xs text-gray-500">Total demandes</p>
            <p class="text-2xl font-bold"><?= $stats['total'] ?></p>
        </div>
    </div>

    <!-- Filtres -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" class="flex flex-col md:flex-row gap-3">
            <input type="text" name="q" value="<?= clean($searchQuery) ?>" placeholder="Rechercher (vendeur, compte)..." 
                   class="flex-1 px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
            <select name="status" onchange="this.form.submit()" class="px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                <option value="all" <?= $statusFilter == 'all' ? 'selected' : '' ?>>Tous les statuts</option>
                <option value="pending" <?= $statusFilter == 'pending' ? 'selected' : '' ?>>En attente</option>
                <option value="approved" <?= $statusFilter == 'approved' ? 'selected' : '' ?>>Approuvés</option>
                <option value="completed" <?= $statusFilter == 'completed' ? 'selected' : '' ?>>Complétés</option>
                <option value="rejected" <?= $statusFilter == 'rejected' ? 'selected' : '' ?>>Rejetés</option>
            </select>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">
                <i class="fas fa-search mr-2"></i>Filtrer
            </button>
        </form>
    </div>

    <!-- Liste des retraits -->
    <div class="space-y-4">
        <?php foreach ($withdrawals as $w): 
            $statusColors = [
                'pending' => 'yellow', 'approved' => 'blue', 'completed' => 'green',
                'rejected' => 'red', 'cancelled' => 'gray'
            ];
            $color = $statusColors[$w['status']] ?? 'gray';
        ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 md:p-6">
                    <div class="flex flex-col md:flex-row justify-between gap-4">
                        <div class="flex items-start gap-4 flex-1">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-orange-500 rounded-full flex items-center justify-center text-white font-bold flex-shrink-0">
                                <?= strtoupper(substr($w['first_name'], 0, 1) . substr($w['last_name'], 0, 1)) ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1 flex-wrap">
                                    <p class="font-bold text-gray-800 dark:text-white"><?= clean($w['first_name'] . ' ' . $w['last_name']) ?></p>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-<?= $color ?>-100 text-<?= $color ?>-800">
                                        <?= ucfirst($w['status']) ?>
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500"><?= clean($w['email']) ?> • <?= clean($w['phone']) ?></p>
                                <div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-600 dark:text-gray-400">
                                    <span><i class="fas fa-<?= $w['method'] == 'mobile_money' ? 'mobile-alt' : ($w['method'] == 'bank' ? 'university' : 'money-bill') ?> mr-1"></i><?= ucfirst(str_replace('_', ' ', $w['method'])) ?></span>
                                    <?php if ($w['account_number']): ?>
                                        <span><i class="fas fa-hashtag mr-1"></i><?= clean($w['account_number']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($w['account_name']): ?>
                                        <span><i class="fas fa-user mr-1"></i><?= clean($w['account_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($w['admin_notes']): ?>
                                    <p class="mt-2 text-xs bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 px-3 py-2 rounded-lg">
                                        <i class="fas fa-comment mr-1"></i><?= clean($w['admin_notes']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="flex flex-col items-end gap-2">
                            <p class="text-2xl font-bold text-green-600"><?= formatPrice($w['amount']) ?></p>
                            <p class="text-xs text-gray-500"><?= date('d/m/Y H:i', strtotime($w['requested_at'])) ?></p>
                            
                            <?php if ($w['status'] === 'pending'): ?>
                                <div class="flex gap-2 mt-2">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="withdrawal_id" value="<?= $w['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button class="px-3 py-1.5 bg-green-100 text-green-600 rounded-lg hover:bg-green-200 text-xs font-semibold">
                                            <i class="fas fa-check mr-1"></i>Approuver
                                        </button>
                                    </form>
                                    <form method="POST" class="inline" onsubmit="return confirm('Rejeter ce retrait ?');">
                                        <input type="hidden" name="withdrawal_id" value="<?= $w['id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button class="px-3 py-1.5 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 text-xs font-semibold">
                                            <i class="fas fa-times mr-1"></i>Rejeter
                                        </button>
                                    </form>
                                </div>
                            <?php elseif ($w['status'] === 'approved'): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="withdrawal_id" value="<?= $w['id'] ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <button class="px-3 py-1.5 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 text-xs font-semibold">
                                        <i class="fas fa-check-double mr-1"></i>Marquer complété
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (count($withdrawals) === 0): ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-12 text-center">
            <i class="fas fa-money-bill-wave text-6xl text-gray-300 mb-4"></i>
            <p class="text-gray-500">Aucun retrait trouvé</p>
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <div class="mt-6 flex justify-center gap-2">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&status=<?= $statusFilter ?>&q=<?= urlencode($searchQuery) ?>" 
                   class="px-4 py-2 rounded-lg <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 hover:bg-gray-100' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>