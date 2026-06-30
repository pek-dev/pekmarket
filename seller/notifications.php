<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'seller') { header('Location: ' . BASE_URL); exit; }

$userId = $_SESSION['user_id'];

// Marquer comme lu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
        } else {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);
        }
    }
    elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?")->execute([intval($_POST['id'] ?? 0), $userId]);
    }
    elseif ($action === 'delete_all') {
        $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$userId]);
    }
}

$notifications = [];
$unreadCount = 0;
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();
    
    foreach ($notifications as $n) if (!$n['is_read']) $unreadCount++;
} catch (Exception $e) {}

$pageTitle = 'Notifications';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
                <i class="fas fa-bell text-yellow-500"></i> Notifications
                <?php if ($unreadCount > 0): ?>
                    <span class="bg-red-500 text-white text-sm px-3 py-1 rounded-full"><?= $unreadCount ?></span>
                <?php endif; ?>
            </h1>
        </div>
        <?php if (count($notifications) > 0): ?>
            <div class="flex gap-2">
                <form method="POST"><input type="hidden" name="action" value="mark_read">
                    <button class="px-3 py-2 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 text-sm font-semibold">
                        <i class="fas fa-check-double mr-1"></i>Tout marquer lu
                    </button>
                </form>
                <form method="POST" onsubmit="return confirm('Supprimer toutes les notifications ?');">
                    <input type="hidden" name="action" value="delete_all">
                    <button class="px-3 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 text-sm font-semibold">
                        <i class="fas fa-trash mr-1"></i>Tout supprimer
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <?php if (count($notifications) > 0): ?>
            <div class="divide-y dark:divide-gray-700">
                <?php foreach ($notifications as $n): 
                    $typeIcons = ['order' => 'fa-shopping-bag', 'message' => 'fa-comment', 'review' => 'fa-star', 'payment' => 'fa-credit-card', 'system' => 'fa-cog', 'promotion' => 'fa-gift'];
                    $typeColors = ['order' => 'blue', 'message' => 'green', 'review' => 'yellow', 'payment' => 'purple', 'system' => 'gray', 'promotion' => 'orange'];
                    $icon = $typeIcons[$n['type']] ?? 'fa-bell';
                    $color = $typeColors[$n['type']] ?? 'gray';
                ?>
                    <div class="p-4 flex items-start gap-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition <?= !$n['is_read'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' ?>">
                        <div class="w-10 h-10 bg-<?= $color ?>-100 dark:bg-<?= $color ?>-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas <?= $icon ?> text-<?= $color ?>-600"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <p class="font-semibold text-gray-800 dark:text-white text-sm"><?= clean($n['title']) ?></p>
                                <?php if (!$n['is_read']): ?>
                                    <span class="w-2 h-2 bg-blue-500 rounded-full"></span>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-300"><?= clean($n['message']) ?></p>
                            <p class="text-xs text-gray-400 mt-1"><?= timeAgo($n['created_at']) ?></p>
                        </div>
                        <div class="flex gap-1">
                            <?php if ($n['link']): ?>
                                <a href="<?= $n['link'] ?>" class="w-8 h-8 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 flex items-center justify-center">
                                    <i class="fas fa-external-link-alt text-xs"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!$n['is_read']): ?>
                                <form method="POST"><input type="hidden" name="id" value="<?= $n['id'] ?>"><input type="hidden" name="action" value="mark_read">
                                    <button class="w-8 h-8 bg-green-100 text-green-600 rounded-lg hover:bg-green-200 flex items-center justify-center">
                                        <i class="fas fa-check text-xs"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form method="POST"><input type="hidden" name="id" value="<?= $n['id'] ?>"><input type="hidden" name="action" value="delete">
                                <button class="w-8 h-8 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 flex items-center justify-center">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="p-12 text-center text-gray-500">
                <i class="fas fa-bell-slash text-6xl text-gray-300 mb-4"></i>
                <p>Aucune notification</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>