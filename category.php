<?php
require_once __DIR__ . '/config/bootstrap.php';

require_once __DIR__ . '/includes/header.php';




// Récupérer le slug de la catégorie
$categorySlug = $_GET['slug'] ?? '';

if (!$categorySlug) {
    header('Location: ' . BASE_URL . '/categories.php');
    exit;
}

// Récupérer les informations de la catégorie
$categoryStmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ? AND is_active = 1");
$categoryStmt->execute([$categorySlug]);
$category = $categoryStmt->fetch();

if (!$category) {
    http_response_code(404);
    $pageTitle = 'Catégorie non trouvée';
    
    echo '<div class="max-w-7xl mx-auto px-4 py-16 text-center">';
    echo '<i class="fas fa-folder-open text-6xl text-gray-300 mb-4"></i>';
    echo '<h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">Catégorie non trouvée</h1>';
    echo '<p class="text-gray-500 mb-6">Cette catégorie n\'existe pas ou n\'est plus active.</p>';
    echo '<a href="' . BASE_URL . '" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-800">Retour à l\'accueil</a>';
    echo '</div>';
    
    exit;
}

// Paramètres de filtre et tri
$searchQuery = trim($_GET['q'] ?? '');
$province = $_GET['province'] ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';
$isNew = isset($_GET['new']) ? (int)$_GET['new'] : 0;
$isFeatured = isset($_GET['featured']) ? (int)$_GET['featured'] : 0;

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$productsPerPage = 12;
$offset = ($page - 1) * $productsPerPage;

// Construction de la requête SQL
$whereConditions = ["p.is_active = 1", "c.slug = ?"];
$params = [$categorySlug];

if ($searchQuery) {
    $whereConditions[] = "(p.name LIKE ? OR p.short_description LIKE ? OR p.description LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

if ($province) {
    $whereConditions[] = "p.province = ?";
    $params[] = $province;
}

if ($minPrice !== '') {
    $whereConditions[] = "p.price >= ?";
    $params[] = (float)$minPrice;
}

if ($maxPrice !== '') {
    $whereConditions[] = "p.price <= ?";
    $params[] = (float)$maxPrice;
}

if ($isNew) {
    $whereConditions[] = "p.is_new = 1";
}

if ($isFeatured) {
    $whereConditions[] = "p.is_featured = 1";
}

$whereClause = implode(' AND ', $whereConditions);

// Tri
switch ($sortBy) {
    case 'price_asc':
        $orderBy = 'p.price ASC';
        break;
    case 'price_desc':
        $orderBy = 'p.price DESC';
        break;
    case 'popular':
        $orderBy = 'p.sales_count DESC';
        break;
    case 'rating':
        $orderBy = 'p.rating_avg DESC, p.rating_count DESC';
        break;
    case 'name_asc':
        $orderBy = 'p.name ASC';
        break;
    default:
        $orderBy = 'p.created_at DESC';
        break;
}

// Requête principale
$productsSql = "
    SELECT p.*, 
           c.name as category_name, 
           c.slug as category_slug,
           u.first_name as seller_first, 
           u.last_name as seller_last,
           u.id as seller_id
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.seller_id = u.id
    WHERE $whereClause
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
";

$productsStmt = $pdo->prepare($productsSql);
$params[] = $productsPerPage;
$params[] = $offset;
$productsStmt->execute($params);
$products = $productsStmt->fetchAll();

// Compter le total
$countSql = "
    SELECT COUNT(*) 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE $whereClause
";
$countStmt = $pdo->prepare($countSql);
$countParams = array_slice($params, 0, -2);
$countStmt->execute($countParams);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $productsPerPage);

// Provinces pour le filtre
$provincesStmt = $pdo->prepare("
    SELECT province, COUNT(*) as product_count 
    FROM products 
    WHERE category_id = ? AND is_active = 1 AND province IS NOT NULL AND province != ''
    GROUP BY province 
    ORDER BY product_count DESC
");
$provincesStmt->execute([$category['id']]);
$filterProvinces = $provincesStmt->fetchAll();

// Stats de la catégorie
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_products,
        AVG(price) as avg_price,
        MIN(price) as min_price,
        MAX(price) as max_price,
        SUM(sales_count) as total_sales
    FROM products 
    WHERE category_id = ? AND is_active = 1
");
$statsStmt->execute([$category['id']]);
$categoryStats = $statsStmt->fetch();

// Variables pour la page
$pageTitle = clean($category['name']) . ' - PekDev Market';
$pageDescription = $category['description'] ?? 'Découvrez tous les produits de la catégorie ' . $category['name'] . ' sur PekDev Market';


?>

<!-- Category Header -->
<section class="relative bg-gradient-to-r from-<?= $category['color'] ?>-600 to-<?= $category['color'] ?>-800 py-12 md:py-16 overflow-hidden">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 left-0 w-64 h-64 bg-white rounded-full -translate-x-1/2 -translate-y-1/2"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full translate-x-1/3 translate-y-1/3"></div>
    </div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <nav class="text-sm text-white/80 mb-4">
            <a href="<?= BASE_URL ?>" class="hover:text-white transition">Accueil</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <a href="<?= BASE_URL ?>/products.php" class="hover:text-white transition">Produits</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-white font-medium"><?= clean($category['name']) ?></span>
        </nav>
        
        <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
            <div class="w-20 h-20 md:w-24 md:h-24 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center">
                <i class="<?= $category['icon'] ?> text-white text-4xl md:text-5xl"></i>
            </div>
            <div class="flex-1 text-white">
                <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-2"><?= clean($category['name']) ?></h1>
                <?php if ($category['description']): ?>
                    <p class="text-white/90 text-base md:text-lg max-w-2xl"><?= clean($category['description']) ?></p>
                <?php endif; ?>
                
                <div class="flex flex-wrap gap-4 mt-4 text-sm">
                    <div class="bg-white/20 backdrop-blur-sm px-4 py-2 rounded-full flex items-center gap-2">
                        <i class="fas fa-box"></i>
                        <span class="font-semibold"><?= number_format($categoryStats['total_products']) ?></span> produits
                    </div>
                    <div class="bg-white/20 backdrop-blur-sm px-4 py-2 rounded-full flex items-center gap-2">
                        <i class="fas fa-shopping-bag"></i>
                        <span class="font-semibold"><?= number_format($categoryStats['total_sales']) ?></span> ventes
                    </div>
                    <?php if ($categoryStats['min_price'] && $categoryStats['max_price']): ?>
                    <div class="bg-white/20 backdrop-blur-sm px-4 py-2 rounded-full flex items-center gap-2">
                        <i class="fas fa-tag"></i>
                        <span class="font-semibold"><?= formatPrice($categoryStats['min_price']) ?> - <?= formatPrice($categoryStats['max_price']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Main Content -->
<section class="py-8 md:py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Search Bar -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-6">
            <form method="GET" action="" class="flex gap-3">
                <input type="hidden" name="slug" value="<?= clean($categorySlug) ?>">
                <input type="hidden" name="province" value="<?= clean($province) ?>">
                <input type="hidden" name="min_price" value="<?= $minPrice ?>">
                <input type="hidden" name="max_price" value="<?= $maxPrice ?>">
                <input type="hidden" name="sort" value="<?= clean($sortBy) ?>">
                
                <div class="relative flex-1">
                    <input type="text" name="q" value="<?= clean($searchQuery) ?>" 
                           placeholder="Rechercher dans <?= clean($category['name']) ?>..." 
                           class="w-full px-4 py-3 pr-12 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-<?= $category['color'] ?>-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                    <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 bg-<?= $category['color'] ?>-600 text-white rounded-lg flex items-center justify-center hover:bg-<?= $category['color'] ?>-700 transition">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            
            <!-- Mobile Filter Toggle -->
            <button id="mobileFilterBtn" class="lg:hidden mb-4 bg-white dark:bg-gray-800 text-gray-800 dark:text-white px-4 py-3 rounded-lg shadow-sm flex items-center justify-center gap-2 font-semibold">
                <i class="fas fa-filter"></i> Afficher les filtres
            </button>

            <!-- Sidebar Filters -->
            <aside id="filterSidebar" class="hidden lg:block lg:w-1/4">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 sticky top-4 space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-gray-800 dark:text-white flex items-center gap-2">
                            <i class="fas fa-sliders-h text-<?= $category['color'] ?>-500"></i> Filtres
                        </h3>
                        <a href="<?= BASE_URL ?>/category.php?slug=<?= $categorySlug ?>" class="text-xs text-red-500 hover:text-red-700 font-medium">
                            <i class="fas fa-times mr-1"></i> Réinitialiser
                        </a>
                    </div>

                    <!-- Localisation -->
                    <?php if (count($filterProvinces) > 0): ?>
                    <div>
                        <h4 class="font-semibold text-gray-800 dark:text-white mb-3 text-sm uppercase tracking-wide">Localisation</h4>
                        <div class="space-y-2 max-h-48 overflow-y-auto pr-2 custom-scrollbar">
                            <a href="<?= BASE_URL ?>/category.php?slug=<?= $categorySlug ?>" 
                               class="block px-3 py-2 rounded-lg text-sm transition <?= !$province ? 'bg-' . $category['color'] . '-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                                Tout le Burundi
                            </a>
                            <?php foreach ($filterProvinces as $prov): ?>
                                <a href="?slug=<?= $categorySlug ?>&province=<?= urlencode($prov['province']) ?>&<?= http_build_query(array_diff_key($_GET, ['province' => '', 'page' => ''])) ?>" 
                                   class="flex justify-between items-center px-3 py-2 rounded-lg text-sm transition <?= $province == $prov['province'] ? 'bg-' . $category['color'] . '-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                                    <span><i class="fas fa-map-marker-alt text-orange-500 mr-2"></i><?= clean($prov['province']) ?></span>
                                    <span class="text-xs opacity-75"><?= $prov['product_count'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Prix -->
                    <div>
                        <h4 class="font-semibold text-gray-800 dark:text-white mb-3 text-sm uppercase tracking-wide">Prix (FBu)</h4>
                        <form method="GET" action="" class="space-y-3">
                            <input type="hidden" name="slug" value="<?= clean($categorySlug) ?>">
                            <?php foreach ($_GET as $key => $val): if($key != 'min_price' && $key != 'max_price' && $key != 'page'): ?>
                                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($val) ?>">
                            <?php endif; endforeach; ?>
                            
                            <div class="flex gap-2">
                                <input type="number" name="min_price" value="<?= $minPrice ?>" placeholder="Min" 
                                       class="w-1/2 px-3 py-2 border dark:border-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-<?= $category['color'] ?>-500 dark:bg-gray-700 dark:text-white">
                                <input type="number" name="max_price" value="<?= $maxPrice ?>" placeholder="Max" 
                                       class="w-1/2 px-3 py-2 border dark:border-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-<?= $category['color'] ?>-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <button type="submit" class="w-full bg-<?= $category['color'] ?>-600 text-white py-2 rounded-lg hover:bg-<?= $category['color'] ?>-700 text-sm font-semibold transition">
                                Appliquer le prix
                            </button>
                        </form>
                    </div>

                    <!-- Options rapides -->
                    <div>
                        <h4 class="font-semibold text-gray-800 dark:text-white mb-3 text-sm uppercase tracking-wide">Options</h4>
                        <div class="space-y-2">
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" <?= $isNew ? 'checked' : '' ?> onchange="toggleFilter('new', this.checked)" class="w-4 h-4 text-<?= $category['color'] ?>-600 rounded border-gray-300 focus:ring-<?= $category['color'] ?>-500">
                                <span class="text-sm text-gray-600 dark:text-gray-300 group-hover:text-<?= $category['color'] ?>-600 transition">Nouveaux produits</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" <?= $isFeatured ? 'checked' : '' ?> onchange="toggleFilter('featured', this.checked)" class="w-4 h-4 text-<?= $category['color'] ?>-600 rounded border-gray-300 focus:ring-<?= $category['color'] ?>-500">
                                <span class="text-sm text-gray-600 dark:text-gray-300 group-hover:text-<?= $category['color'] ?>-600 transition">En vedette</span>
                            </label>
                        </div>
                    </div>

                    <!-- Autres catégories -->
                    <div>
                        <h4 class="font-semibold text-gray-800 dark:text-white mb-3 text-sm uppercase tracking-wide">Autres catégories</h4>
                        <?php
                        $otherCategoriesStmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 AND slug != '$categorySlug' ORDER BY sort_order ASC LIMIT 6");
                        $otherCategories = $otherCategoriesStmt->fetchAll();
                        ?>
                        <div class="space-y-2">
                            <?php foreach ($otherCategories as $otherCat): ?>
                                <a href="<?= BASE_URL ?>/category.php?slug=<?= $otherCat['slug'] ?>" 
                                   class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                    <i class="<?= $otherCat['icon'] ?> text-<?= $otherCat['color'] ?>-500"></i>
                                    <?= clean($otherCat['name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Products Grid Area -->
            <div class="flex-1">
                <!-- Toolbar (Sort & View) -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Trier par:</span>
                        <select onchange="updateSort(this.value)" class="border-0 bg-transparent text-sm font-semibold text-gray-800 dark:text-white focus:ring-0 cursor-pointer">
                            <option value="newest" <?= $sortBy == 'newest' ? 'selected' : '' ?>>Plus récents</option>
                            <option value="popular" <?= $sortBy == 'popular' ? 'selected' : '' ?>>Popularité</option>
                            <option value="price_asc" <?= $sortBy == 'price_asc' ? 'selected' : '' ?>>Prix croissant</option>
                            <option value="price_desc" <?= $sortBy == 'price_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                            <option value="rating" <?= $sortBy == 'rating' ? 'selected' : '' ?>>Meilleures notes</option>
                            <option value="name_asc" <?= $sortBy == 'name_asc' ? 'selected' : '' ?>>Nom (A-Z)</option>
                        </select>
                    </div>
                    
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Affichage de <span class="font-semibold text-gray-800 dark:text-white"><?= min($offset + 1, $totalProducts) ?></span> à <span class="font-semibold text-gray-800 dark:text-white"><?= min($offset + $productsPerPage, $totalProducts) ?></span> sur <span class="font-semibold text-gray-800 dark:text-white"><?= $totalProducts ?></span>
                    </div>
                </div>

                <!-- Products Grid -->
                <?php if (count($products) > 0): ?>
                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6">
                        <?php foreach ($products as $product): 
                            $image = getProductImage($product['id'], $pdo);
                            $discount = calculateDiscount($product['price'], $product['old_price']);
                        ?>
                            <div class="product-card bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-2xl transition duration-300 overflow-hidden group border border-gray-100 dark:border-gray-700 flex flex-col">
                                <div class="relative overflow-hidden">
                                    <a href="<?= BASE_URL ?>/product.php?slug=<?= $product['slug'] ?>">
                                        <img src="<?= $image ?>" 
                                             alt="<?= clean($product['name']) ?>" 
                                             class="w-full h-40 sm:h-48 object-cover group-hover:scale-105 transition duration-500" loading="lazy">
                                    </a>
                                    
                                    <!-- Badges -->
                                    <div class="absolute top-2 left-2 flex flex-col gap-1">
                                        <?php if ($product['is_new']): ?>
                                            <span class="bg-orange-500 text-white text-[10px] md:text-xs px-2 py-1 rounded font-semibold shadow-sm">Nouveau</span>
                                        <?php endif; ?>
                                        <?php if ($discount > 0): ?>
                                            <span class="bg-red-500 text-white text-[10px] md:text-xs px-2 py-1 rounded font-semibold shadow-sm">-<?= $discount ?>%</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Actions -->
                                    <?php if (isLoggedIn()): ?>
                                        <button class="absolute top-2 right-2 w-8 h-8 bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm rounded-full shadow-md flex items-center justify-center text-gray-400 hover:text-red-500 transition transform hover:scale-110" 
                                                onclick="toggleFavorite(<?= $product['id'] ?>, this)">
                                            <i class="<?= isFavorite($_SESSION['user_id'], $product['id'], $pdo) ? 'fas text-red-500' : 'far' ?> fa-heart text-sm"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="p-3 md:p-4 flex flex-col flex-1">
                                    <a href="<?= BASE_URL ?>/product.php?slug=<?= $product['slug'] ?>" class="block mb-2 flex-1">
                                        <h3 class="font-semibold text-gray-800 dark:text-white text-sm md:text-base line-clamp-2 hover:text-<?= $category['color'] ?>-600 transition leading-tight">
                                            <?= clean($product['name']) ?>
                                        </h3>
                                    </a>
                                    
                                    <div class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 mb-2">
                                        <i class="fas fa-map-marker-alt text-orange-500"></i>
                                        <span><?= clean($product['city'] ?? $product['province']) ?></span>
                                    </div>
                                    
                                    <?php if ($product['rating_count'] > 0): ?>
                                        <div class="flex items-center gap-1 mb-3">
                                            <?= renderStars($product['rating_avg']) ?>
                                            <span class="text-xs text-gray-500 ml-1">(<?= $product['rating_count'] ?>)</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="mb-3"></div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-auto pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                                        <div>
                                            <span class="text-base md:text-lg font-bold text-<?= $category['color'] ?>-600"><?= formatPrice($product['price']) ?></span>
                                            <?php if ($product['old_price']): ?>
                                                <span class="text-xs text-gray-400 line-through block"><?= formatPrice($product['old_price']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <button class="w-9 h-9 bg-blue-50 dark:bg-blue-900/50 rounded-full flex items-center justify-center text-blue-600 hover:bg-blue-600 hover:text-white transition transform hover:scale-110" 
                                                onclick="addToCart(<?= $product['id'] ?>)" title="Ajouter au panier">
                                            <i class="fas fa-shopping-cart text-sm"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="mt-10 flex justify-center">
                            <nav class="flex items-center gap-1 bg-white dark:bg-gray-800 p-2 rounded-xl shadow-sm">
                                <?php
                                $pagParams = $_GET;
                                unset($pagParams['page']);
                                $baseUrl = BASE_URL . '/category.php?' . http_build_query($pagParams);
                                $separator = empty($pagParams) ? '?' : '&';
                                ?>
                                
                                <?php if ($page > 1): ?>
                                    <a href="<?= $baseUrl . $separator ?>page=<?= $page - 1 ?>" class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="w-10 h-10 flex items-center justify-center rounded-lg text-gray-300 dark:text-gray-600 cursor-not-allowed">
                                        <i class="fas fa-chevron-left"></i>
                                    </span>
                                <?php endif; ?>

                                <?php
                                $start = max(1, $page - 2);
                                $end = min($totalPages, $page + 2);
                                
                                if ($start > 1): ?>
                                    <a href="<?= $baseUrl . $separator ?>page=1" class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition">1</a>
                                    <?php if ($start > 2): ?>
                                        <span class="w-10 h-10 flex items-center justify-center text-gray-400">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $start; $i <= $end; $i++): ?>
                                    <a href="<?= $baseUrl . $separator ?>page=<?= $i ?>" 
                                       class="w-10 h-10 flex items-center justify-center rounded-lg transition font-semibold <?= $i == $page ? 'bg-' . $category['color'] . '-600 text-white shadow-md' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($end < $totalPages): ?>
                                    <?php if ($end < $totalPages - 1): ?>
                                        <span class="w-10 h-10 flex items-center justify-center text-gray-400">...</span>
                                    <?php endif; ?>
                                    <a href="<?= $baseUrl . $separator ?>page=<?= $totalPages ?>" class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition"><?= $totalPages ?></a>
                                <?php endif; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="<?= $baseUrl . $separator ?>page=<?= $page + 1 ?>" class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="w-10 h-10 flex items-center justify-center rounded-lg text-gray-300 dark:text-gray-600 cursor-not-allowed">
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                <?php endif; ?>
                            </nav>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Empty State -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-12 text-center">
                        <div class="w-24 h-24 mx-auto bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-6">
                            <i class="fas fa-search text-4xl text-gray-400"></i>
                        </div>
                        <h3 class="text-xl md:text-2xl font-bold text-gray-800 dark:text-white mb-2">Aucun produit trouvé</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-8 max-w-md mx-auto">
                            Nous n'avons trouvé aucun produit dans la catégorie <strong><?= clean($category['name']) ?></strong> correspondant à vos critères.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <a href="<?= BASE_URL ?>/category.php?slug=<?= $categorySlug ?>" class="bg-<?= $category['color'] ?>-600 text-white px-6 py-3 rounded-lg hover:bg-<?= $category['color'] ?>-700 font-semibold transition">
                                <i class="fas fa-undo mr-2"></i>Réinitialiser les filtres
                            </a>
                            <a href="<?= BASE_URL ?>/products.php" class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white px-6 py-3 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 font-semibold transition">
                                Voir tous les produits
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
// Gestion du tri
function updateSort(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', value);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

// Gestion des filtres checkbox
function toggleFilter(filterName, isChecked) {
    const url = new URL(window.location.href);
    if (isChecked) {
        url.searchParams.set(filterName, '1');
    } else {
        url.searchParams.delete(filterName);
    }
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

// Mobile Filter Toggle
document.getElementById('mobileFilterBtn').addEventListener('click', function() {
    const sidebar = document.getElementById('filterSidebar');
    sidebar.classList.toggle('hidden');
    sidebar.classList.toggle('fixed');
    sidebar.classList.toggle('inset-0');
    sidebar.classList.toggle('z-50');
    sidebar.classList.toggle('bg-white');
    sidebar.classList.toggle('dark:bg-gray-800');
    sidebar.classList.toggle('p-6');
    sidebar.classList.toggle('overflow-y-auto');
    
    if (!sidebar.classList.contains('hidden')) {
        this.innerHTML = '<i class="fas fa-times"></i> Fermer les filtres';
    } else {
        this.innerHTML = '<i class="fas fa-filter"></i> Afficher les filtres';
    }
});
</script>

<style>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 20px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #475569;
}
</style>

<?php  ?>