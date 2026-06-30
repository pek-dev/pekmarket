<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'seller') { header('Location: ' . BASE_URL); exit; }

$sellerId = $_SESSION['user_id'];

// Récupérer les publicités du vendeur
$ads = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM ad_campaigns 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$sellerId]);
    $ads = $stmt->fetchAll();
} catch (Exception $e) {}

// Stats
$stats = ['total' => 0, 'active' => 0, 'pending' => 0, 'spent' => 0, 'clicks' => 0, 'impressions' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            COALESCE(SUM(spent), 0) as spent,
            COALESCE(SUM(clicks), 0) as clicks,
            COALESCE(SUM(impressions), 0) as impressions
        FROM ad_campaigns WHERE user_id = ?
    ");
    $stmt->execute([$sellerId]);
    $stats = $stmt->fetch();
} catch (Exception $e) {}

$pageTitle = 'Mes Publicités';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <nav class="text-sm text-gray-500 mb-2">
                <a href="<?= BASE_URL ?>/dashboard/seller.php" class="hover:text-blue-600">Dashboard</a>
                <i class="fas fa-chevron-right text-xs mx-2"></i>
                <span class="text-gray-800 dark:text-white font-medium">Mes Publicités</span>
            </nav>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
                <i class="fas fa-ad text-red-500"></i> Mes Publicités
            </h1>
        </div>
        <a href="<?= BASE_URL ?>/create-ad.php" class="px-5 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold flex items-center gap-2 shadow-md">
            <i class="fas fa-plus"></i> Nouvelle publicité
        </a>
    </div>

    <?php if (isset($_GET['created'])): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6">
            <i class="fas fa-check-circle mr-2"></i>
            Publicité soumise avec succès ! Elle sera examinée par un administrateur.
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-blue-500">
            <p class="text-xs text-gray-500">Total</p>
            <p class="text-2xl font-bold"><?= $stats['total'] ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-green-500">
            <p class="text-xs text-gray-500">Actives</p>
            <p class="text-2xl font-bold text-green-600"><?= $stats['active'] ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-yellow-500">
            <p class="text-xs text-gray-500">En attente</p>
            <p class="text-2xl font-bold text-yellow-600"><?= $stats['pending'] ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-purple-500">
            <p class="text-xs text-gray-500">Dépensé</p>
            <p class="text-xl font-bold"><?= formatPrice($stats['spent']) ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-orange-500">
            <p class="text-xs text-gray-500">Clics</p>
            <p class="text-2xl font-bold"><?= number_format($stats['clicks']) ?></p>
        </div>
    </div>

    <!-- Liste des publicités -->
    <?php if (count($ads) > 0): ?>
        <div class="space-y-4">
            <?php foreach ($ads as $ad): 
                $statusColors = [
                    'pending' => 'yellow', 'approved' => 'blue', 'active' => 'green',
                    'paused' => 'gray', 'rejected' => 'red', 'expired' => 'gray'
                ];
                $color = $statusColors[$ad['status']] ?? 'gray';
                $ctr = $ad['impressions'] > 0 ? round(($ad['clicks'] / $ad['impressions']) * 100, 2) : 0;
            ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="flex flex-col md:flex-row">
                        <img src="<?= clean($ad['image_path']) ?>" alt="" class="w-full md:w-48 h-40 md:h-auto object-cover">
                        <div class="flex-1 p-4 md:p-6">
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div>
                                    <h3 class="font-bold text-gray-800 dark:text-white text-lg"><?= clean($ad['title']) ?></h3>
                                    <div class="flex flex-wrap items-center gap-2 mt-1">
                                        <span class="text-xs px-2 py-0.5 rounded bg-blue-100 text-blue-800 font-semibold"><?= ucfirst($ad['ad_type']) ?></span>
                                        <span class="text-xs px-2 py-0.5 rounded bg-purple-100 text-purple-800 font-semibold"><?= ucfirst(str_replace('_', ' ', $ad['placement'])) ?></span>
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-<?= $color ?>-100 text-<?= $color ?>-800"><?= ucfirst($ad['status']) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($ad['description']): ?>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3 line-clamp-2"><?= clean($ad['description']) ?></p>
                            <?php endif; ?>
                            
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-xs">
                                <div>
                                    <p class="text-gray-500">Budget</p>
                                    <p class="font-bold text-gray-800 dark:text-white"><?= formatPrice($ad['budget_total']) ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Dépensé</p>
                                    <p class="font-bold text-gray-800 dark:text-white"><?= formatPrice($ad['spent']) ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Vues</p>
                                    <p class="font-bold text-gray-800 dark:text-white"><?= number_format($ad['impressions']) ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Clics</p>
                                    <p class="font-bold text-gray-800 dark:text-white"><?= number_format($ad['clicks']) ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500">CTR</p>
                                    <p class="font-bold text-gray-800 dark:text-white"><?= $ctr ?>%</p>
                                </div>
                            </div>
                            
                            <div class="mt-3 pt-3 border-t dark:border-gray-700 flex justify-between items-center text-xs text-gray-500">
                                <span>Créée le <?= date('d/m/Y', strtotime($ad['created_at'])) ?></span>
                                <span>Valable du <?= date('d/m/Y', strtotime($ad['start_date'])) ?> au <?= date('d/m/Y', strtotime($ad['end_date'])) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-12 text-center">
            <i class="fas fa-ad text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Aucune publicité</h3>
            <p class="text-gray-500 mb-6">Créez votre première publicité pour booster votre visibilité</p>
            <a href="<?= BASE_URL ?>/create-ad.php" class="inline-block bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 font-semibold">
                <i class="fas fa-plus mr-2"></i>Créer une publicité
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>