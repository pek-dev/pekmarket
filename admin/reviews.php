<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: ' . BASE_URL); exit; }

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $reviewId = intval($_POST['review_id'] ?? 0);
    try {
        if ($action === 'approve') { $pdo->prepare("UPDATE reviews SET is_approved = 1 WHERE id = ?")->execute([$reviewId]); $message = "Avis approuvé."; }
        elseif ($action === 'reject') { $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$reviewId]); $message = "Avis supprimé."; }
    } catch (Exception $e) { $message = "Erreur: " . $e->getMessage(); }
}

$reviews = [];
try { $reviews = $pdo->query("SELECT r.*, u.first_name, u.last_name, p.name as product_name, p.slug as product_slug FROM reviews r JOIN users u ON r.user_id = u.id JOIN products p ON r.product_id = p.id ORDER BY r.is_approved ASC, r.created_at DESC LIMIT 200")->fetchAll(); } catch (Exception $e) {}

$pageTitle = 'Modération des Avis';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <nav class="text-sm text-gray-500 mb-2"><a href="<?= BASE_URL ?>/dashboard/admin.php" class="hover:text-blue-600">Admin</a> <i class="fas fa-chevron-right text-xs mx-2"></i> Avis</nav>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white"><i class="fas fa-star text-indigo-500 mr-2"></i>Modération des Avis</h1>
    </div>

    <?php if ($message): ?><div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6"><?= clean($message) ?></div><?php endif; ?>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="divide-y dark:divide-gray-700">
            <?php foreach ($reviews as $review): ?>
                <div class="p-4 flex items-start gap-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 <?= !$review['is_approved'] ? 'bg-yellow-50/50 dark:bg-yellow-900/10' : '' ?>">
                    <div class="w-10 h-10 bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center font-bold text-gray-600 dark:text-gray-300 flex-shrink-0"><?= strtoupper(substr($review['first_name'], 0, 1)) ?></div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1"><p class="font-semibold text-gray-800 dark:text-white"><?= clean($review['first_name'] . ' ' . $review['last_name']) ?></p><span class="text-xs text-gray-500">sur</span><a href="<?= BASE_URL ?>/product.php?slug=<?= $review['product_slug'] ?>" class="text-sm text-blue-600 hover:underline"><?= clean($review['product_name']) ?></a><?php if (!$review['is_approved']): ?><span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-0.5 rounded font-semibold">En attente</span><?php endif; ?></div>
                        <div class="mb-2"><?= renderStars($review['rating']) ?></div>
                        <?php if ($review['title']): ?><p class="font-semibold text-sm text-gray-800 dark:text-white mb-1"><?= clean($review['title']) ?></p><?php endif; ?>
                        <p class="text-sm text-gray-600 dark:text-gray-300"><?= clean($review['comment']) ?></p>
                        <p class="text-xs text-gray-500 mt-2"><?= date('d/m/Y H:i', strtotime($review['created_at'])) ?></p>
                    </div>
                    <div class="flex gap-1 flex-shrink-0">
                        <?php if (!$review['is_approved']): ?><form method="POST"><input type="hidden" name="review_id" value="<?= $review['id'] ?>"><input type="hidden" name="action" value="approve"><button class="px-3 py-2 bg-green-100 text-green-600 rounded-lg hover:bg-green-200 text-xs font-semibold"><i class="fas fa-check mr-1"></i>Approuver</button></form><?php endif; ?>
                        <form method="POST" onsubmit="return confirm('Supprimer ?');"><input type="hidden" name="review_id" value="<?= $review['id'] ?>"><input type="hidden" name="action" value="reject"><button class="px-3 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 text-xs font-semibold"><i class="fas fa-trash mr-1"></i>Supprimer</button></form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($reviews) === 0): ?><div class="p-12 text-center text-gray-500"><i class="fas fa-comment-slash text-4xl mb-4 text-gray-300"></i><p>Aucun avis à modérer</p></div><?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>