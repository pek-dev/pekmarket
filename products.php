<?php
require_once __DIR__ . '/config/bootstrap.php';

require_once __DIR__ . '/includes/header.php';




$pageTitle = 'Tous les produits';
$pageDescription = 'Parcourez tous les produits disponibles sur PekDev Market, la plus grande marketplace du Burundi.';

// Récupération des paramètres de filtre et tri
$searchQuery = trim($_GET['q'] ?? '');
$categorySlug = $_GET['category'] ?? '';
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

// Construction de la requête SQL dynamique
$whereConditions = ["p.is_active = 1"];
$params = [];

if ($searchQuery) {
    $whereConditions[] = "(p.name LIKE ? OR p.short_description LIKE ? OR p.description LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

if ($categorySlug) {
    $whereConditions[] = "c.slug = ?";
    $params[] = $categorySlug;
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

// Requête principale des produits
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

// Compter le total pour la pagination
$countSql = "
    SELECT COUNT(*) 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE $whereClause
";
$countStmt = $pdo->prepare($countSql);
// On enlève les 2 derniers paramètres (LIMIT et OFFSET) pour le COUNT
$countParams = array_slice($params, 0, -2);
$countStmt->execute($countParams);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $productsPerPage);

// Données pour les filtres (Sidebar)
// 1. Catégories
$categoriesStmt = $pdo->query("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1 
    WHERE c.is_active = 1 
    GROUP BY c.id 
    HAVING product_count > 0
    ORDER BY c.sort_order ASC
");
$filterCategories = $categoriesStmt->fetchAll();

// 2. Provinces
$provincesStmt = $pdo->query("
    SELECT province, COUNT(*) as product_count 
    FROM products 
    WHERE is_active = 1 AND province IS NOT NULL AND province != ''
    GROUP BY province 
    ORDER BY product_count DESC
");
$filterProvinces = $provincesStmt->fetchAll();


?>

<!-- Page Header -->
<section class="bg-gradient-to-r from-blue-600 to-orange-600 py-8 md:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div class="text-white">
                <nav class="text-sm text-white/80 mb-2">
                    <a href="<?= BASE_URL ?>" class="hover:text-white">Accueil</a>
                    <i class="fas fa-chevron-right text-xs mx-2"></i>
                    <span class="text-white">Produits</span>
                    <?php if ($categorySlug): ?>
                        <i class="fas fa-chevron-right text-xs mx-2"></i>
                        <span class="text-white"><?= clean(ucfirst(str_replace('-', ' ', $categorySlug))) ?></span>
                    <?php endif; ?>
                </nav>
                <h1 class="text-2xl md:text-4xl font-bold">
                    <?= $searchQuery ? "Résultats pour '" . clean($searchQuery) . "'" : 'Tous les produits' ?>
                </h1>
                <p class="text-white/90 mt-2 text-sm md:text-base">
                    <?= number_format($totalProducts) ?> produit<?= $totalProducts > 1 ? 's' : '' ?> trouvé<?= $totalProducts > 1 ? 's' : '' ?>
                </p>
            </div>
            
            <!-- Search Bar -->
            <form method="GET" action="" class="w-full md:w-96 relative">
                <input type="hidden" name="category" value="<?= clean($categorySlug) ?>">
                <input type="hidden" name="province" value="<?= clean($province) ?>">
                <input type="hidden" name="min_price" value="<?= $minPrice ?>">
                <input type="hidden" name="max_price" value="<?= $maxPrice ?>">
                <input type="hidden" name="sort" value="<?= clean($sortBy) ?>">
                
                <input type="text" name="q" value="<?= clean($searchQuery) ?>" 
                       placeholder="Rechercher un produit..." 
                       class="w-full px-4 py-3 pr-12 rounded-lg text-gray-800 focus:outline-none focus:ring-4 focus:ring-white/30 shadow-lg">
                <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 bg-blue-600 text-white rounded-md flex items-center justify-center hover:bg-blue-800 transition">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </div>
</section>

<!-- Main Content -->
<section class="py-8 md:py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
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
                            <i class="fas fa-sliders-h text-blue-600"></i> Filtres
                        </h3>
                        <a href="<?= BASE_URL ?>/products.php" class="text-xs text-red-500 hover:text-red-700 font-medium">
                            <i class="fas fa-times mr-1"></i> Réinitialiser
                        </a>
                    </div>

                    <!-- Catégories -->
                    <div>
                        <h4 class="font-semibold text-gray-800 dark:text-white mb-3 text-sm uppercase tracking-wide">Catégories</h4>
                        <div class="space-y-2 max-h-64 overflow-y-auto pr-2 custom-scrollbar">
                            <a href="<?= BASE_URL ?>/products.php<?= !empty($_GET) ? '?' . http_build_query(array_diff_key($_GET, ['category' => ''])) : '' ?>" 
                               class="flex justify-between items-center px-3 py-2 rounded-lg text-sm transition <?= !$categorySlug ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                                <span>Toutes</span>
                                <span class="text-xs opacity-75"><?= $totalProducts ?></span>
                            </a>
                            <?php foreach ($filterCategories as $cat): ?>
                                <a href="?category=<?= $cat['slug'] ?>&<?= http_build_query(array_diff_key($_GET, ['category' => '', 'page' => ''])) ?>" 
                                   class="flex justify-between items-center px-3 py-2 rounded-lg text-sm transition <?= $categorySlug == $cat['slug'] ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                                    <span class="flex items-center gap-2">
                                        <i class="<?= $cat['icon'] ?> text-<?= $cat['color'] ?>-500"></i>
                                        <?= clean($cat['name']) ?>
                                    </span>
                                    <span class="text-xs opacity-75"><?= $cat['product_count'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Localisation -->
                    <div>
                        <h4 class="font-semibold text-gray-800 dark:text-white mb-3 text-sm uppercase tracking-wide">Localisation</h4>
                        <div class="space-y-2 max-h-48 overflow-y-auto pr-2 custom-scrollbar">
                            <a href="?<?= http_build_query(array_diff_key($_GET, ['province' => '', 'page' => ''])) ?>" 
                               class="block px-3 py-2 rounded-lg text-sm transition <?= !$province ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                                Tout le Burundi
                            </a>
                            <?php foreach ($filterProvinces as $prov): ?>
                                <a href="?province=<?= urlencode($prov['province']) ?>&<?= http_build_query(array_diff_key($_GET, ['province' => '', 'page' => ''])) ?>" 
                                   class="flex justify-between items-center px-3 py-2 rounded-lg text-sm transition <?= $province == $prov['province'] ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                                    <span><i class="fas fa-map-marker-alt text-orange-500 mr-2"></i><?= clean($prov['province']) ?></span>
                                    <span class="text-xs opacity-75"><?= $prov['product_count'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Prix -->
                    <div>
                        <h4 class="font-semibold text-gray-800 dark:text-white mb-3 text-sm uppercase tracking-wide">Prix (FBu)</h4>
                        <form method="GET" action="" class="space-y-3">
                            <?php foreach ($_GET as $key => $val): if($key != 'min_price' && $key != 'max_price' && $key != 'page'): ?>
                                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($val) ?>">
                            <?php endif; endforeach; ?>
                            
                            <div class="flex gap-2">
                                <input type="number" name="min_price" value="<?= $minPrice ?>" placeholder="Min" 
                                       class="w-1/2 px-3 py-2 border dark:border-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                <input type="number" name="max_price" value="<?= $maxPrice ?>" placeholder="Max" 
                                       class="w-1/2 px-3 py-2 border dark:border-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <button type="submit" class="w-full bg-gray-800 dark:bg-gray-700 text-white py-2 rounded-lg hover:bg-gray-900 text-sm font-semibold transition">
                                Appliquer le prix
                            </button>
                        </form>
                    </div>

                    <!-- Options rapides -->
                    <div>
                        <h4 class="font-semibold text-gray-800 dark:text-white mb-3 text-sm uppercase tracking-wide">Options</h4>
                        <div class="space-y-2">
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" <?= $isNew ? 'checked' : '' ?> onchange="toggleFilter('new', this.checked)" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                                <span class="text-sm text-gray-600 dark:text-gray-300 group-hover:text-blue-600 transition">Nouveaux produits</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" <?= $isFeatured ? 'checked' : '' ?> onchange="toggleFilter('featured', this.checked)" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                                <span class="text-sm text-gray-600 dark:text-gray-300 group-hover:text-blue-600 transition">En vedette</span>
                            </label>
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
                                        <h3 class="font-semibold text-gray-800 dark:text-white text-sm md:text-base line-clamp-2 hover:text-blue-600 transition leading-tight">
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
                                            <span class="text-base md:text-lg font-bold text-blue-600"><?= formatPrice($product['price']) ?></span>
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
                                // Construire l'URL de base pour la pagination
                                $pagParams = $_GET;
                                unset($pagParams['page']);
                                $baseUrl = BASE_URL . '/products.php?' . http_build_query($pagParams);
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
                                       class="w-10 h-10 flex items-center justify-center rounded-lg transition font-semibold <?= $i == $page ? 'bg-blue-600 text-white shadow-md' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
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
                            Nous n'avons trouvé aucun produit correspondant à vos critères. Essayez de modifier vos filtres ou votre recherche.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <a href="<?= BASE_URL ?>/products.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-800 font-semibold transition">
                                <i class="fas fa-undo mr-2"></i>Réinitialiser les filtres
                            </a>
                            <a href="<?= BASE_URL ?>" class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white px-6 py-3 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 font-semibold transition">
                                Retour à l'accueil
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
    url.searchParams.delete('page'); // Reset page on sort change
    window.location.href = url.toString();
}

// Gestion des filtres checkbox (Nouveau, En vedette)
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
/* Custom scrollbar for filter lists */
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