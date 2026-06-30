<?php
require_once __DIR__ . '/config/bootstrap.php';

require_once __DIR__ . '/includes/header.php';




// Récupérer l'ID du vendeur
$sellerId = $_GET['seller'] ?? 0;

if (!$sellerId || !is_numeric($sellerId)) {
    header('Location: ' . BASE_URL);
    exit;
}

// Récupérer les informations du vendeur
$sellerStmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(DISTINCT p.id) as total_products,
           COUNT(DISTINCT CASE WHEN p.is_active = 1 THEN p.id END) as active_products,
           SUM(p.sales_count) as total_sales,
           AVG(r.rating) as avg_rating,
           COUNT(DISTINCT r.id) as total_reviews
    FROM users u
    LEFT JOIN products p ON u.id = p.seller_id
    LEFT JOIN reviews r ON p.id = r.product_id AND r.is_approved = 1
    WHERE u.id = ? AND u.role = 'seller' AND u.is_active = 1
    GROUP BY u.id
");
$sellerStmt->execute([$sellerId]);
$seller = $sellerStmt->fetch();

if (!$seller) {
    http_response_code(404);
    $pageTitle = 'Boutique non trouvée';
    
    echo '<div class="max-w-7xl mx-auto px-4 py-16 text-center">';
    echo '<i class="fas fa-store-slash text-6xl text-gray-300 mb-4"></i>';
    echo '<h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">Boutique non trouvée</h1>';
    echo '<p class="text-gray-500 mb-6">Cette boutique n\'existe pas ou n\'est plus active.</p>';
    echo '<a href="' . BASE_URL . '" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-800">Retour à l\'accueil</a>';
    echo '</div>';
    
    exit;
}

// Filtres
$categoryFilter = $_GET['category'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$searchQuery = $_GET['q'] ?? '';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$productsPerPage = 12;
$offset = ($page - 1) * $productsPerPage;

// Construire la requête SQL avec filtres
$whereConditions = ["p.seller_id = ?", "p.is_active = 1"];
$params = [$sellerId];

if ($categoryFilter) {
    $whereConditions[] = "c.slug = ?";
    $params[] = $categoryFilter;
}

if ($minPrice) {
    $whereConditions[] = "p.price >= ?";
    $params[] = $minPrice;
}

if ($maxPrice) {
    $whereConditions[] = "p.price <= ?";
    $params[] = $maxPrice;
}

if ($searchQuery) {
    $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = implode(' AND ', $whereConditions);

// Tri
$orderBy = match($sortBy) {
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'popular' => 'p.sales_count DESC',
    'rating' => 'p.rating_avg DESC',
    default => 'p.created_at DESC'
};

// Récupérer les produits avec filtres
$productsStmt = $pdo->prepare("
    SELECT p.*, 
           c.name as category_name,
           c.slug as category_slug,
           c.icon as category_icon,
           c.color as category_color
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE $whereClause
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
");
$params[] = $productsPerPage;
$params[] = $offset;
$productsStmt->execute($params);
$products = $productsStmt->fetchAll();

// Compter le total pour la pagination
$countParams = array_slice($params, 0, -2); // Retirer LIMIT et OFFSET
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE $whereClause
");
$countStmt->execute($countParams);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $productsPerPage);

// Catégories du vendeur
$categoriesStmt = $pdo->prepare("
    SELECT DISTINCT c.*, COUNT(p.id) as product_count
    FROM categories c
    JOIN products p ON c.id = p.category_id
    WHERE p.seller_id = ? AND p.is_active = 1
    GROUP BY c.id
    ORDER BY product_count DESC
");
$categoriesStmt->execute([$sellerId]);
$sellerCategories = $categoriesStmt->fetchAll();

// Avis récents sur les produits du vendeur
$reviewsStmt = $pdo->prepare("
    SELECT r.*, u.first_name, u.last_name, p.name as product_name, p.slug as product_slug
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    WHERE p.seller_id = ? AND r.is_approved = 1
    ORDER BY r.created_at DESC
    LIMIT 5
");
$reviewsStmt->execute([$sellerId]);
$recentReviews = $reviewsStmt->fetchAll();

$pageTitle = clean($seller['first_name'] . ' ' . $seller['last_name']) . ' - Boutique';
$pageDescription = 'Découvrez la boutique de ' . $seller['first_name'] . ' ' . $seller['last_name'] . ' sur PekDev Market';


?>

<!-- Shop Header -->
<section class="bg-gradient-to-r from-blue-600 to-orange-600 py-12 md:py-16 relative overflow-hidden">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 left-0 w-64 h-64 bg-white rounded-full -translate-x-1/2 -translate-y-1/2"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full translate-x-1/3 translate-y-1/3"></div>
    </div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="flex flex-col md:flex-row items-center gap-6 md:gap-8">
            <!-- Avatar du vendeur -->
            <div class="w-24 h-24 md:w-32 md:h-32 bg-white rounded-full flex items-center justify-center text-4xl md:text-5xl font-bold text-blue-600 shadow-2xl">
                <?= strtoupper(substr($seller['first_name'], 0, 1) . substr($seller['last_name'], 0, 1)) ?>
            </div>
            
            <!-- Informations du vendeur -->
            <div class="text-center md:text-left text-white flex-1">
                <h1 class="text-3xl md:text-4xl font-bold mb-2"><?= clean($seller['first_name'] . ' ' . $seller['last_name']) ?></h1>
                <p class="text-white/90 mb-4">
                    <i class="fas fa-map-marker-alt mr-2"></i>
                    <?= clean($seller['city'] ?? $seller['province']) ?>, Burundi
                </p>
                
                <!-- Stats du vendeur -->
                <div class="flex flex-wrap justify-center md:justify-start gap-3 md:gap-4">
                    <div class="bg-white/20 backdrop-blur-sm px-4 py-2 rounded-full text-sm flex items-center gap-2">
                        <i class="fas fa-box"></i>
                        <span class="font-semibold"><?= $seller['active_products'] ?></span> produits
                    </div>
                    <div class="bg-white/20 backdrop-blur-sm px-4 py-2 rounded-full text-sm flex items-center gap-2">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="font-semibold"><?= number_format($seller['total_sales']) ?></span> ventes
                    </div>
                    <?php if ($seller['avg_rating']): ?>
                    <div class="bg-white/20 backdrop-blur-sm px-4 py-2 rounded-full text-sm flex items-center gap-2">
                        <?= renderStars($seller['avg_rating']) ?>
                        <span class="font-semibold"><?= number_format($seller['avg_rating'], 1) ?></span>
                        <span class="text-white/80">(<?= $seller['total_reviews'] ?>)</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Bouton contacter -->
            <?php if (isLoggedIn() && $_SESSION['user_id'] != $seller['id']): ?>
            <button onclick="contactSeller(<?= $seller['id'] ?>)" 
                    class="bg-white text-blue-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 shadow-lg flex items-center gap-2">
                <i class="fas fa-envelope"></i>
                <span class="hidden sm:inline">Contacter</span>
            </button>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Shop Content -->
<section class="py-8 md:py-12 bg-gray-50 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-4 gap-6 md:gap-8">
            
            <!-- Sidebar Filters -->
            <aside class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 sticky top-4">
                    <h3 class="font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                        <i class="fas fa-filter text-blue-600"></i>
                        Filtres
                    </h3>
                    
                    <!-- Recherche -->
                    <form method="GET" action="" class="mb-6">
                        <input type="hidden" name="seller" value="<?= $sellerId ?>">
                        <?php if ($categoryFilter): ?>
                        <input type="hidden" name="category" value="<?= clean($categoryFilter) ?>">
                        <?php endif; ?>
                        <div class="relative">
                            <input type="text" name="q" value="<?= clean($searchQuery) ?>" 
                                   placeholder="Rechercher..." 
                                   class="w-full px-4 py-2 pr-10 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-600">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                    
                    <!-- Catégories -->
                    <?php if (count($sellerCategories) > 0): ?>
                    <div class="mb-6">
                        <h4 class="font-semibold text-gray-800 dark:text-white mb-3">Catégories</h4>
                        <div class="space-y-2">
                            <a href="?seller=<?= $sellerId ?>" 
                               class="block px-3 py-2 rounded-lg text-sm <?= !$categoryFilter ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200' ?>">
                                Toutes les catégories
                                <span class="float-right text-xs">(<?= $seller['active_products'] ?>)</span>
                            </a>
                            <?php foreach ($sellerCategories as $cat): ?>
                            <a href="?seller=<?= $sellerId ?>&category=<?= $cat['slug'] ?>" 
                               class="block px-3 py-2 rounded-lg text-sm <?= $categoryFilter == $cat['slug'] ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200' ?>">
                                <i class="<?= $cat['icon'] ?> text-<?= $cat['color'] ?>-500 mr-2"></i>
                                <?= clean($cat['name']) ?>
                                <span class="float-right text-xs">(<?= $cat['product_count'] ?>)</span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Prix -->
                    <div class="mb-6">
                        <h4 class="font-semibold text-gray-800 dark:text-white mb-3">Prix (FBu)</h4>
                        <form method="GET" action="" class="space-y-2">
                            <input type="hidden" name="seller" value="<?= $sellerId ?>">
                            <?php if ($categoryFilter): ?>
                            <input type="hidden" name="category" value="<?= clean($categoryFilter) ?>">
                            <?php endif; ?>
                            <div class="flex gap-2">
                                <input type="number" name="min_price" value="<?= $minPrice ?>" 
                                       placeholder="Min" 
                                       class="w-1/2 px-3 py-2 border dark:border-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                <input type="number" name="max_price" value="<?= $maxPrice ?>" 
                                       placeholder="Max" 
                                       class="w-1/2 px-3 py-2 border dark:border-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-800 text-sm font-semibold">
                                Appliquer
                            </button>
                        </form>
                    </div>
                    
                    <!-- Avis récents -->
                    <?php if (count($recentReviews) > 0): ?>
                    <div>
                        <h4 class="font-semibold text-gray-800 dark:text-white mb-3">Avis récents</h4>
                        <div class="space-y-3">
                            <?php foreach (array_slice($recentReviews, 0, 3) as $review): ?>
                            <div class="border-b dark:border-gray-700 pb-3 last:border-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <?= renderStars($review['rating']) ?>
                                </div>
                                <p class="text-xs text-gray-600 dark:text-gray-400 line-clamp-2"><?= clean($review['comment']) ?></p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?= clean($review['first_name']) ?> - <?= date('d/m/Y', strtotime($review['created_at'])) ?>
                                </p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </aside>
            
            <!-- Products Grid -->
            <div class="lg:col-span-3">
                <!-- Header avec tri -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">
                            <?= $totalProducts ?> produit<?= $totalProducts > 1 ? 's' : '' ?>
                        </h2>
                        <?php if ($categoryFilter || $searchQuery || $minPrice || $maxPrice): ?>
                        <p class="text-sm text-gray-500 mt-1">
                            Filtres actifs: 
                            <?php if ($categoryFilter): ?>Catégorie: <?= clean($categoryFilter) ?> | <?php endif; ?>
                            <?php if ($searchQuery): ?>Recherche: "<?= clean($searchQuery) ?>" | <?php endif; ?>
                            <?php if ($minPrice || $maxPrice): ?>Prix: <?= $minPrice ?: '0' ?> - <?= $maxPrice ?: '∞' ?> FBu<?php endif; ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex gap-2">
                        <select onchange="window.location.href=this.value" 
                                class="border dark:border-gray-700 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            <?php
                            $baseUrl = '?seller=' . $sellerId;
                            if ($categoryFilter) $baseUrl .= '&category=' . $categoryFilter;
                            if ($searchQuery) $baseUrl .= '&q=' . urlencode($searchQuery);
                            if ($minPrice) $baseUrl .= '&min_price=' . $minPrice;
                            if ($maxPrice) $baseUrl .= '&max_price=' . $maxPrice;
                            ?>
                            <option value="<?= $baseUrl ?>&sort=newest" <?= $sortBy == 'newest' ? 'selected' : '' ?>>Plus récents</option>
                            <option value="<?= $baseUrl ?>&sort=popular" <?= $sortBy == 'popular' ? 'selected' : '' ?>>Popularité</option>
                            <option value="<?= $baseUrl ?>&sort=price_asc" <?= $sortBy == 'price_asc' ? 'selected' : '' ?>>Prix croissant</option>
                            <option value="<?= $baseUrl ?>&sort=price_desc" <?= $sortBy == 'price_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                            <option value="<?= $baseUrl ?>&sort=rating" <?= $sortBy == 'rating' ? 'selected' : '' ?>>Meilleures notes</option>
                        </select>
                    </div>
                </div>
                
                <!-- Products -->
                <?php if (count($products) > 0): ?>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 md:gap-6">
                    <?php foreach ($products as $product): 
                        $image = getProductImage($product['id'], $pdo);
                        $discount = calculateDiscount($product['price'], $product['old_price']);
                    ?>
                    <div class="product-card bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-2xl transition duration-300 overflow-hidden group border border-gray-100 dark:border-gray-700">
                        <div class="relative overflow-hidden">
                            <a href="<?= BASE_URL ?>/product.php?slug=<?= $product['slug'] ?>">
                                <img src="<?= $image ?>" 
                                     alt="<?= clean($product['name']) ?>" 
                                     class="w-full h-40 md:h-48 object-cover group-hover:scale-105 transition duration-500" loading="lazy">
                            </a>
                            <?php if ($product['is_new']): ?>
                            <span class="absolute top-2 left-2 bg-orange-500 text-white text-xs px-2 py-1 rounded font-semibold">Nouveau</span>
                            <?php endif; ?>
                            <?php if ($discount > 0): ?>
                            <span class="absolute top-2 <?= $product['is_new'] ? 'left-20' : 'left-2' ?> bg-red-500 text-white text-xs px-2 py-1 rounded font-semibold">-<?= $discount ?>%</span>
                            <?php endif; ?>
                            
                            <?php if (isLoggedIn()): ?>
                            <button class="absolute top-2 right-2 w-8 h-8 bg-white dark:bg-gray-800 rounded-full shadow-md flex items-center justify-center text-gray-400 hover:text-red-500 transition" 
                                    onclick="toggleFavorite(<?= $product['id'] ?>, this)">
                                <i class="<?= isFavorite($_SESSION['user_id'], $product['id'], $pdo) ? 'fas text-red-500' : 'far' ?> fa-heart text-sm"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="p-4">
                            <a href="<?= BASE_URL ?>/product.php?slug=<?= $product['slug'] ?>" class="block">
                                <h3 class="font-semibold text-gray-800 dark:text-white mb-2 text-sm md:text-base line-clamp-2 hover:text-blue-600 transition">
                                    <?= clean($product['name']) ?>
                                </h3>
                            </a>
                            
                            <?php if ($product['rating_count'] > 0): ?>
                            <div class="flex items-center gap-1 mb-2">
                                <?= renderStars($product['rating_avg']) ?>
                                <span class="text-xs text-gray-500">(<?= $product['rating_count'] ?>)</span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-lg font-bold text-blue-600"><?= formatPrice($product['price']) ?></span>
                                    <?php if ($product['old_price']): ?>
                                    <span class="text-xs text-gray-400 line-through ml-1"><?= formatPrice($product['old_price']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <button class="w-9 h-9 bg-blue-50 dark:bg-blue-900 rounded-full flex items-center justify-center text-blue-600 hover:bg-blue-600 hover:text-white transition" 
                                        onclick="addToCart(<?= $product['id'] ?>)">
                                    <i class="fas fa-shopping-cart text-sm"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="mt-8 flex justify-center gap-2">
                    <?php
                    $paginationBaseUrl = '?seller=' . $sellerId;
                    if ($categoryFilter) $paginationBaseUrl .= '&category=' . $categoryFilter;
                    if ($searchQuery) $paginationBaseUrl .= '&q=' . urlencode($searchQuery);
                    if ($minPrice) $paginationBaseUrl .= '&min_price=' . $minPrice;
                    if ($maxPrice) $paginationBaseUrl .= '&max_price=' . $maxPrice;
                    if ($sortBy) $paginationBaseUrl .= '&sort=' . $sortBy;
                    ?>
                    
                    <?php if ($page > 1): ?>
                    <a href="<?= $paginationBaseUrl ?>&page=<?= $page - 1 ?>" 
                       class="px-4 py-2 bg-white dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1): ?>
                    <a href="<?= $paginationBaseUrl ?>&page=1" 
                       class="px-4 py-2 bg-white dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">1</a>
                    <?php if ($startPage > 2): ?>
                    <span class="px-2 py-2 text-gray-400">...</span>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <a href="<?= $paginationBaseUrl ?>&page=<?= $i ?>" 
                       class="px-4 py-2 rounded-lg transition <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                    <span class="px-2 py-2 text-gray-400">...</span>
                    <?php endif; ?>
                    <a href="<?= $paginationBaseUrl ?>&page=<?= $totalPages ?>" 
                       class="px-4 py-2 bg-white dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"><?= $totalPages ?></a>
                    <?php endif; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="<?= $paginationBaseUrl ?>&page=<?= $page + 1 ?>" 
                       class="px-4 py-2 bg-white dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-12 text-center">
                    <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Aucun produit trouvé</h3>
                    <p class="text-gray-500 mb-6">Essayez de modifier vos filtres ou recherche</p>
                    <a href="?seller=<?= $sellerId ?>" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-800 inline-block">
                        Réinitialiser les filtres
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Contact Seller Modal -->
<div id="contactModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800 dark:text-white">Contacter le vendeur</h3>
            <button onclick="closeContactModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="contactForm" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Message</label>
                <textarea name="message" rows="4" required 
                          class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                          placeholder="Écrivez votre message..."></textarea>
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-800">
                <i class="fas fa-paper-plane mr-2"></i>Envoyer
            </button>
        </form>
    </div>
</div>

<script>
function contactSeller(sellerId) {
    document.getElementById('contactModal').classList.remove('hidden');
}

function closeContactModal() {
    document.getElementById('contactModal').classList.add('hidden');
}

document.getElementById('contactForm').addEventListener('submit', function(e) {
    e.preventDefault();
    alert('Message envoyé au vendeur !');
    closeContactModal();
});
</script>

<?php  ?>