<?php
/**
 * Récupère et affiche les publicités actives pour un emplacement donné
 * Usage: <?php include 'includes/ad-renderer.php'; renderAds('hero_banner'); ?>
 */

function renderAds($placement, $pdo, $limit = 1) {
    $stmt = $pdo->prepare("
        SELECT * FROM ad_campaigns 
        WHERE status = 'active' 
          AND placement = ? 
          AND start_date <= CURDATE() 
          AND end_date >= CURDATE()
          AND (budget_total = 0 OR spent < budget_total)
        ORDER BY priority DESC, RAND()
        LIMIT ?
    ");
    $stmt->execute([$placement, $limit]);
    $ads = $stmt->fetchAll();
    
    if (count($ads) === 0) return;
    
    foreach ($ads as $ad):
        // Incrémenter l'impression
        $pdo->prepare("UPDATE ad_campaigns SET impressions = impressions + 1 WHERE id = ?")->execute([$ad['id']]);
?>
    <div class="ad-container relative group" data-ad-id="<?= $ad['id'] ?>">
        <!-- Ad Label -->
        <span class="absolute top-2 left-2 bg-black/50 text-white text-[10px] px-2 py-0.5 rounded z-10 font-medium">
            Sponsored
        </span>
        
        <?php if ($placement === 'hero_banner'): ?>
            <!-- Bannière Hero -->
            <a href="<?= clean($ad['target_url'] ?: '#') ?>" onclick="trackAdClick(<?= $ad['id'] ?>)" 
               class="block rounded-2xl overflow-hidden relative hover:shadow-2xl transition">
                <img src="<?= $ad['image_path'] ?>" alt="<?= clean($ad['title']) ?>" 
                     class="w-full h-48 md:h-64 lg:h-80 object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent flex items-end p-6 md:p-8">
                    <div class="text-white">
                        <h3 class="text-xl md:text-3xl font-bold mb-2"><?= clean($ad['title']) ?></h3>
                        <?php if ($ad['description']): ?>
                            <p class="text-white/90 text-sm md:text-base mb-4 max-w-xl line-clamp-2"><?= clean($ad['description']) ?></p>
                        <?php endif; ?>
                        <span class="inline-block bg-<?= $ad['cta_color'] ?? 'blue' ?>-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-<?= $ad['cta_color'] ?? 'blue' ?>-700 transition">
                            <?= clean($ad['cta_text']) ?> <i class="fas fa-arrow-right ml-2"></i>
                        </span>
                    </div>
                </div>
            </a>
            
        <?php elseif ($placement === 'feed'): ?>
            <!-- Fil d'actualité -->
            <a href="<?= clean($ad['target_url'] ?: '#') ?>" onclick="trackAdClick(<?= $ad['id'] ?>)" 
               class="block bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow-sm hover:shadow-xl transition border border-gray-100 dark:border-gray-700">
                <img src="<?= $ad['image_path'] ?>" alt="<?= clean($ad['title']) ?>" 
                     class="w-full h-40 object-cover">
                <div class="p-4">
                    <h4 class="font-bold text-gray-800 dark:text-white mb-1 line-clamp-1"><?= clean($ad['title']) ?></h4>
                    <?php if ($ad['description']): ?>
                        <p class="text-sm text-gray-500 line-clamp-2"><?= clean($ad['description']) ?></p>
                    <?php endif; ?>
                    <span class="inline-block mt-2 text-<?= $ad['cta_color'] ?? 'blue' ?>-600 font-semibold text-sm">
                        <?= clean($ad['cta_text']) ?> →
                    </span>
                </div>
            </a>
            
        <?php elseif ($placement === 'between_products'): ?>
            <!-- Entre les produits -->
            <a href="<?= clean($ad['target_url'] ?: '#') ?>" onclick="trackAdClick(<?= $ad['id'] ?>)" 
               class="block bg-gradient-to-r from-<?= $ad['cta_color'] ?? 'blue' ?>-600 to-<?= $ad['cta_color'] ?? 'blue' ?>-800 rounded-xl overflow-hidden hover:shadow-xl transition col-span-full">
                <div class="flex flex-col md:flex-row items-center p-6 text-white">
                    <img src="<?= $ad['image_path'] ?>" alt="<?= clean($ad['title']) ?>" 
                         class="w-full md:w-48 h-32 object-cover rounded-lg mb-4 md:mb-0 md:mr-6">
                    <div class="flex-1 text-center md:text-left">
                        <h4 class="text-xl font-bold mb-2"><?= clean($ad['title']) ?></h4>
                        <?php if ($ad['description']): ?>
                            <p class="text-white/80 text-sm mb-3"><?= clean($ad['description']) ?></p>
                        <?php endif; ?>
                        <span class="inline-block bg-white text-<?= $ad['cta_color'] ?? 'blue' ?>-700 px-4 py-2 rounded-lg font-semibold text-sm">
                            <?= clean($ad['cta_text']) ?>
                        </span>
                    </div>
                </div>
            </a>
            
        <?php elseif ($placement === 'sidebar'): ?>
            <!-- Sidebar -->
            <a href="<?= clean($ad['target_url'] ?: '#') ?>" onclick="trackAdClick(<?= $ad['id'] ?>)" 
               class="block bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow-sm hover:shadow-lg transition border border-gray-100 dark:border-gray-700">
                <img src="<?= $ad['image_path'] ?>" alt="<?= clean($ad['title']) ?>" 
                     class="w-full h-32 object-cover">
                <div class="p-3 text-center">
                    <h4 class="font-bold text-sm text-gray-800 dark:text-white line-clamp-2"><?= clean($ad['title']) ?></h4>
                    <span class="text-xs text-blue-600 font-semibold mt-1 block"><?= clean($ad['cta_text']) ?></span>
                </div>
            </a>
            
        <?php elseif ($placement === 'story'): ?>
            <!-- Story plein écran -->
            <a href="<?= clean($ad['target_url'] ?: '#') ?>" onclick="trackAdClick(<?= $ad['id'] ?>)" 
               class="block relative rounded-2xl overflow-hidden h-96">
                <img src="<?= $ad['image_path'] ?>" alt="<?= clean($ad['title']) ?>" 
                     class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent flex items-end p-6">
                    <div class="text-white w-full">
                        <h3 class="text-2xl font-bold mb-2"><?= clean($ad['title']) ?></h3>
                        <span class="inline-block bg-white text-gray-900 px-6 py-2 rounded-full font-semibold text-sm">
                            <?= clean($ad['cta_text']) ?>
                        </span>
                    </div>
                </div>
            </a>
            
        <?php else: ?>
            <!-- Popup / autre -->
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-lg border dark:border-gray-700">
                <a href="<?= clean($ad['target_url'] ?: '#') ?>" onclick="trackAdClick(<?= $ad['id'] ?>)" class="block">
                    <img src="<?= $ad['image_path'] ?>" alt="<?= clean($ad['title']) ?>" class="w-full h-32 object-cover rounded-lg mb-3">
                    <h4 class="font-bold text-gray-800 dark:text-white text-sm"><?= clean($ad['title']) ?></h4>
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php
    endforeach;
}
?>

<script>
function trackAdClick(adId) {
    fetch('<?= BASE_URL ?>/api/track-ad-click.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ad_id: adId})
    });
}
</script>