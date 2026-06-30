<?php
require_once __DIR__ . '/config/bootstrap.php';

require_once __DIR__ . '/includes/header.php';




$pageTitle = 'Toutes les catégories';
$pageDescription = 'Parcourez toutes les catégories de produits disponibles sur PekDev Market.';

// Récupérer toutes les catégories actives avec le nombre de produits
$categoriesStmt = $pdo->query("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1 
    WHERE c.is_active = 1 
    GROUP BY c.id 
    ORDER BY c.sort_order ASC, c.name ASC
");
$categories = $categoriesStmt->fetchAll();

// Statistiques globales
$totalCategories = count($categories);
$totalProducts = array_sum(array_column($categories, 'product_count'));


?>

<!-- Hero Section -->
<section class="bg-gradient-to-r from-blue-600 to-orange-600 py-12 md:py-16 relative overflow-hidden">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 left-0 w-64 h-64 bg-white rounded-full -translate-x-1/2 -translate-y-1/2"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full translate-x-1/3 translate-y-1/3"></div>
    </div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center text-white">
        <nav class="text-sm text-white/80 mb-4">
            <a href="<?= BASE_URL ?>" class="hover:text-white transition">Accueil</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-white font-medium">Catégories</span>
        </nav>
        <h1 class="text-3xl md:text-5xl font-bold mb-4">Explorez nos catégories</h1>
        <p class="text-white/90 text-lg max-w-2xl mx-auto mb-6">
            Découvrez <?= number_format($totalProducts) ?> produits répartis dans <?= $totalCategories ?> catégories uniques.
        </p>
        
        <!-- Search Bar -->
        <div class="max-w-2xl mx-auto relative">
            <input type="text" id="categorySearch" placeholder="Rechercher une catégorie..." 
                   class="w-full px-6 py-4 pr-12 rounded-xl text-gray-800 focus:outline-none focus:ring-4 focus:ring-white/30 shadow-xl">
            <button class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 bg-blue-600 text-white rounded-lg flex items-center justify-center hover:bg-blue-800 transition">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>
</section>

<!-- Categories Grid -->
<section class="py-12 md:py-16 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-12">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm text-center border-t-4 border-blue-600">
                <i class="fas fa-th-large text-3xl text-blue-600 mb-2"></i>
                <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= $totalCategories ?></p>
                <p class="text-sm text-gray-500">Catégories</p>
            </div>
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm text-center border-t-4 border-orange-500">
                <i class="fas fa-box text-3xl text-orange-500 mb-2"></i>
                <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= number_format($totalProducts) ?></p>
                <p class="text-sm text-gray-500">Produits</p>
            </div>
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm text-center border-t-4 border-green-500">
                <i class="fas fa-store text-3xl text-green-500 mb-2"></i>
                <p class="text-2xl font-bold text-gray-800 dark:text-white">100%</p>
                <p class="text-sm text-gray-500">Vendeurs vérifiés</p>
            </div>
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm text-center border-t-4 border-purple-500">
                <i class="fas fa-truck text-3xl text-purple-500 mb-2"></i>
                <p class="text-2xl font-bold text-gray-800 dark:text-white">18</p>
                <p class="text-sm text-gray-500">Provinces livrées</p>
            </div>
        </div>

        <!-- Categories List -->
        <div id="categoriesGrid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php foreach ($categories as $cat): ?>
                <a href="<?= BASE_URL ?>/category.php?slug=<?= $cat['slug'] ?>" 
                   class="category-card group bg-white dark:bg-gray-800 rounded-2xl shadow-sm hover:shadow-2xl transition duration-300 overflow-hidden border border-gray-100 dark:border-gray-700 flex flex-col">
                    
                    <!-- Colored Top Bar -->
                    <div class="h-2 bg-<?= $cat['color'] ?>-500"></div>
                    
                    <div class="p-6 flex flex-col items-center text-center flex-1">
                        <div class="w-20 h-20 bg-<?= $cat['color'] ?>-100 dark:bg-<?= $cat['color'] ?>-900/30 rounded-2xl flex items-center justify-center mb-4 group-hover:scale-110 transition duration-300">
                            <i class="<?= $cat['icon'] ?> text-<?= $cat['color'] ?>-500 text-3xl"></i>
                        </div>
                        
                        <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2 group-hover:text-<?= $cat['color'] ?>-600 transition">
                            <?= clean($cat['name']) ?>
                        </h3>
                        
                        <?php if ($cat['description']): ?>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4 line-clamp-2 flex-1">
                                <?= clean($cat['description']) ?>
                            </p>
                        <?php else: ?>
                            <div class="mb-4 flex-1"></div>
                        <?php endif; ?>
                        
                        <div class="w-full pt-4 border-t border-gray-100 dark:border-gray-700 flex justify-between items-center">
                            <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">
                                <i class="fas fa-box-open text-<?= $cat['color'] ?>-500 mr-1"></i>
                                <?= $cat['product_count'] ?> produits
                            </span>
                            <span class="text-<?= $cat['color'] ?>-500 group-hover:translate-x-1 transition">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Empty State (Hidden by default) -->
        <div id="emptyState" class="hidden text-center py-12">
            <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Aucune catégorie trouvée</h3>
            <p class="text-gray-500">Essayez avec un autre terme de recherche.</p>
        </div>
    </div>
</section>

<script>
// Recherche instantanée de catégories
document.getElementById('categorySearch').addEventListener('input', function(e) {
    const query = e.target.value.toLowerCase();
    const cards = document.querySelectorAll('.category-card');
    let hasResults = false;
    
    cards.forEach(card => {
        const title = card.querySelector('h3').textContent.toLowerCase();
        const desc = card.querySelector('p') ? card.querySelector('p').textContent.toLowerCase() : '';
        
        if (title.includes(query) || desc.includes(query)) {
            card.style.display = 'flex';
            hasResults = true;
        } else {
            card.style.display = 'none';
        }
    });
    
    document.getElementById('emptyState').classList.toggle('hidden', hasResults);
});
</script>

<?php  ?>