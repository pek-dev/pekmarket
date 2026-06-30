<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'seller') { header('Location: ' . BASE_URL); exit; }

$sellerId = $_SESSION['user_id'];

// Récupérer les avis sur les produits du vendeur
$reviews = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.first_name, u.last_name, p.name as product_name, p.slug as product_slug,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as product_image
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        JOIN products p ON r.product_id = p.id
        WHERE p.seller_id = ? AND r.is_approved = 1
        ORDER BY r.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$sellerId]);
    $reviews = $stmt->fetchAll();
} catch (Exception $e) {}

// Stats des avis
$reviewStats = ['total' => 0, 'avg_rating' => 0, '5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            COALESCE(AVG(r.rating), 0) as avg_rating,
            SUM(CASE WHEN r.rating = 5 THEN 1 ELSE 0 END) as star_5,
            SUM(CASE WHEN r.rating = 4 THEN 1 ELSE 0 END) as star_4,
            SUM(CASE WHEN r.rating = 3 THEN 1 ELSE 0 END) as star_3,
            SUM(CASE WHEN r.rating = 2 THEN 1 ELSE 0 END) as star_2,
            SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) as star_1
        FROM reviews r
        JOIN products p ON r.product_id = p.id
        WHERE p.seller_id = ? AND r.is_approved = 1
    ");
    $stmt->execute([$sellerId]);
    $stats = $stmt->fetch();
    $reviewStats = [
        'total' => $stats['total'],
        'avg_rating' => $stats['avg_rating'],
        '5' => $stats['star_5'], '4' => $stats['star_4'], '3' => $stats['star_3'],
        '2' => $stats['star_2'], '1' => $stats['star_1']
    ];
} catch (Exception $e) {}

$pageTitle = 'Mes Avis';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <nav class="text-sm text-gray-500 mb-2">
            <a href="<?= BASE_URL ?>/dashboard/seller.php" class="hover:text-blue-600">Dashboard</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-gray-800 dark:text-white font-medium">Avis</span>
        </nav>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
            <i class="fas fa-star text-yellow-500"></i> Avis clients
        </h1>
    </div>

    <!-- Stats -->
    <div class="grid md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-6">
                <div class="text-center">
                    <p class="text-5xl font-bold text-yellow-500"><?= number_format($reviewStats['avg_rating'], 1) ?></p>
                    <div class="mt-2"><?= renderStars($reviewStats['avg_rating']) ?></div>
                    <p class="text-xs text-gray-500 mt-1"><?= $reviewStats['total'] ?> avis</p>
                </div>
                <div class="flex-1 space-y-2">
                    <?php for ($i = 5; $i >= 1; $i--): 
                        $count = $reviewStats[$i];
                        $percent = $reviewStats['total'] > 0 ? ($count / $reviewStats['total']) * 100 : 0;
                    ?>
                        <div class="flex items-center gap-2">
                            <span class="text-xs w-4"><?= $i ?></span>
                            <i class="fas fa-star text-yellow-400 text-xs"></i>
                            <div class="flex-1 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-full bg-yellow-400" style="width: <?= $percent ?>%"></div>
                            </div>
                            <span class="text-xs text-gray-500 w-8"><?= $count ?></span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des avis -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="p-4 border-b dark:border-gray-700">
            <h2 class="font-bold text-gray-800 dark:text-white"><?= count($reviews) ?> avis récents</h2>
        </div>
        <div class="divide-y dark:divide-gray-700">
            <?php if (count($reviews) > 0): ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="p-4 md:p-6 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <div class="flex items-start gap-4">
                            <img src="<?= $review['product_image'] ?: 'https://via.placeholder.com/60' ?>" alt="" class="w-14 h-14 rounded-lg object-cover flex-shrink-0">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <p class="font-semibold text-gray-800 dark:text-white"><?= clean($review['first_name'] . ' ' . $review['last_name']) ?></p>
                                    <span class="text-xs text-gray-500">•</span>
                                    <a href="<?= BASE_URL ?>/product.php?slug=<?= $review['product_slug'] ?>" class="text-xs text-blue-600 hover:underline"><?= clean($review['product_name']) ?></a>
                                </div>
                                <div class="mb-2"><?= renderStars($review['rating']) ?></div>
                                <?php if ($review['title']): ?>
                                    <p class="font-semibold text-gray-800 dark:text-white text-sm mb-1"><?= clean($review['title']) ?></p>
                                <?php endif; ?>
                                <p class="text-sm text-gray-600 dark:text-gray-300"><?= clean($review['comment']) ?></p>
                                <p class="text-xs text-gray-500 mt-2"><?= date('d/m/Y à H:i', strtotime($review['created_at'])) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-12 text-center text-gray-500">
                    <i class="fas fa-comment-slash text-4xl mb-3 text-gray-300"></i>
                    <p>Aucun avis pour le moment</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>