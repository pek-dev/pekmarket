<?php
require_once __DIR__ . '/config/bootstrap.php';

$sellerId = intval($_GET['seller'] ?? 0);
if ($sellerId <= 0) {
    header('Location: ' . BASE_URL);
    exit;
}

// Récupérer les infos du vendeur
$seller = null;
$stats = ['products' => 0, 'rating' => 0, 'reviews' => 0, 'sales' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(DISTINCT p.id) as products_count,
               COALESCE(AVG(p.rating_avg), 0) as avg_rating,
               SUM(p.rating_count) as reviews_count,
               COALESCE(SUM(p.sales_count), 0) as total_sales
        FROM users u
        LEFT JOIN products p ON u.id = p.seller_id AND p.is_active = 1
        WHERE u.id = ? AND u.role = 'seller' AND u.is_active = 1
        GROUP BY u.id
    ");
    $stmt->execute([$sellerId]);
    $seller = $stmt->fetch();
    
    if (!$seller) {
        header('Location: ' . BASE_URL);
        exit;
    }
    
    $stats = [
        'products' => $seller['products_count'],
        'rating' => $seller['avg_rating'],
        'reviews' => $seller['reviews_count'],
        'sales' => $seller['total_sales']
    ];
} catch (Exception $e) {
    header('Location: ' . BASE_URL);
    exit;
}

// Filtres
$categoryFilter = intval($_GET['category'] ?? 0);
$searchQuery = trim($_GET['q'] ?? '');
$sortBy = $_GET['sort'] ?? 'newest';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$where = ["p.seller_id = ?", "p.is_active = 1"];
$params = [$sellerId];

if ($categoryFilter > 0) { $where[] = "p.category_id = ?"; $params[] = $categoryFilter; }
if ($searchQuery) { $where[] = "(p.name LIKE ? OR p.description LIKE ?)"; $params[] = "%$searchQuery%"; $params[] = "%$searchQuery%"; }

$whereClause = implode(' AND ', $where);

$orderBy = match($sortBy) {
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'popular' => 'p.sales_count DESC',
    'rating' => 'p.rating_avg DESC',
    default => 'p.created_at DESC'
};

try {
    $productsStmt = $pdo->prepare("
        SELECT p.*, c.name as category_name,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_path
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE $whereClause
        ORDER BY $orderBy
        LIMIT ? OFFSET ?
    ");
    $params[] = $perPage; $params[] = $offset;
    $productsStmt->execute($params);
    $products = $productsStmt->fetchAll();
    
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereClause");
    $countStmt->execute(array_slice($params, 0, -2));
    $totalProducts = $countStmt->fetchColumn();
    $totalPages = ceil($totalProducts / $perPage);
} catch (Exception $e) { $products = []; $totalPages = 0; }

// Catégories du vendeur
$sellerCategories = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.name, c.slug, c.icon, c.color, COUNT(p.id) as count
        FROM categories c
        JOIN products p ON c.id = p.category_id
        WHERE p.seller_id = ? AND p.is_active = 1
        GROUP BY c.id
        ORDER BY c.name ASC
    ");
    $stmt->execute([$sellerId]);
    $sellerCategories = $stmt->fetchAll();
} catch (Exception $e) {}

$pageTitle = 'Boutique de ' . $seller['first_name'] . ' ' . $seller['last_name'];
$pageDescription = 'Découvrez les produits de ' . $seller['first_name'] . ' ' . $seller['last_name'] . ' sur PekDev Market';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header de la boutique -->
<section class="bg-gradient-to-r from-blue-600 to-orange-500 py-8 md:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <nav class="text-sm text-white/80 mb-4">
            <a href="<?= BASE_URL ?>" class="hover:text-white">Accueil</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-white font-medium">Boutique</span>
        </nav>
        
        <div class="flex flex-col md:flex-row items-center gap-6">
            <div class="w-24 h-24 md:w-32 md:h-32 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center text-white text-4xl md:text-5xl font-bold shadow-xl">
                <?= strtoupper(substr($seller['first_name'], 0, 1) . substr($seller['last_name'], 0, 1)) ?>
            </div>
            <div class="text-center md:text-left flex-1">
                <div class="flex items-center justify-center md:justify-start gap-2 mb-2">
                    <h1 class="text-2xl md:text-4xl font-bold text-white"><?= clean($seller['first_name'] . ' ' . $seller['last_name']) ?></h1>
                    <?php if ($seller['is_verified']): ?>
                        <span class="bg-green-500 text-white text-xs px-2 py-1 rounded-full font-semibold">
                            <i class="fas fa-check-circle mr-1"></i>Vérifié
                        </span>
                    <?php endif; ?>
                </div>
                <p class="text-white/80 mb-3">
                    <i class="fas fa-map-marker-alt mr-1"></i>
                    <?= clean($seller['city'] ?? $seller['province'] ?? 'Burundi') ?>
                </p>
                <div class="flex flex-wrap justify-center md:justify-start gap-4 text-white">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-box text-yellow-300"></i>
                        <span><strong><?= $stats['products'] ?></strong> produits</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="fas fa-star text-yellow-300"></i>
                        <span><strong><?= number_format($stats['rating'], 1) ?></strong>/5 (<?= $stats['reviews'] ?> avis)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="fas fa-shopping-bag text-yellow-300"></i>
                        <span><strong><?= number_format($stats['sales']) ?></strong> ventes</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-8 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-4 gap-6">
            
            <!-- Sidebar : Filtres -->
            <aside class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 sticky top-4 space-y-4">
                    
                    <!-- Recherche -->
                    <form method="GET" class="space-y-3">
                        <input type="hidden" name="seller" value="<?= $sellerId ?>">
                        <div class="relative">
                            <input type="text" name="q" value="<?= clean($searchQuery) ?>" placeholder="Rechercher..." 
                                   class="w-full pl-10 pr-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white text-sm">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        </div>
                    </form>
                    
                    <!-- Catégories -->
                    <?php if (count($sellerCategories) > 0): ?>
                        <div>
                            <h3 class="font-bold text-gray-800 dark:text-white mb-3 text-sm uppercase">Catégories</h3>
                            <div class="space-y-1">
                                <a href="?seller=<?= $sellerId ?>" class="flex items-center justify-between py-2 px-3 rounded-lg text-sm <?= $categoryFilter == 0 ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 font-semibold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                                    <span>Toutes</span>
                                    <span class="text-xs"><?= $stats['products'] ?></span>
                                </a>
                                <?php foreach ($sellerCategories as $cat): ?>
                                    <a href="?seller=<?= $sellerId ?>&category=<?= $cat['id'] ?>" class="flex items-center justify-between py-2 px-3 rounded-lg text-sm <?= $categoryFilter == $cat['id'] ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 font-semibold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                                        <span class="flex items-center gap-2">
                                            <i class="<?= $cat['icon'] ?> text-<?= $cat['color'] ?>-500"></i>
                                            <?= clean($cat['name']) ?>
                                        </span>
                                        <span class="text-xs"><?= $cat['count'] ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Tri -->
                    <div>
                        <h3 class="font-bold text-gray-800 dark:text-white mb-3 text-sm uppercase">Trier par</h3>
                        <div class="space-y-1">
                            <?php 
                            $sortOptions = [
                                'newest' => ['label' => 'Plus récents', 'icon' => 'fas fa-clock'],
                                'popular' => ['label' => 'Populaires', 'icon' => 'fas fa-fire'],
                                'price_asc' => ['label' => 'Prix croissant', 'icon' => 'fas fa-arrow-up'],
                                'price_desc' => ['label' => 'Prix décroissant', 'icon' => 'fas fa-arrow-down'],
                                'rating' => ['label' => 'Mieux notés', 'icon' => 'fas fa-star']
                            ];
                            foreach ($sortOptions as $key => $opt): 
                            ?>
                                <a href="?seller=<?= $sellerId ?>&sort=<?= $key ?>&category=<?= $categoryFilter ?>&q=<?= urlencode($searchQuery) ?>" 
                                   class="flex items-center gap-2 py-2 px-3 rounded-lg text-sm <?= $sortBy == $key ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 font-semibold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                                    <i class="<?= $opt['icon'] ?> text-xs"></i>
                                    <?= $opt['label'] ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Produits -->
            <div class="lg:col-span-3">
                <div class="flex justify-between items-center mb-4">
                    <p class="text-sm text-gray-500"><?= $totalProducts ?> produit(s) trouvé(s)</p>
                </div>

                <?php if (count($products) > 0): ?>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <?php foreach ($products as $product): 
                            $discount = calculateDiscount($product['price'], $product['old_price']);
                        ?>
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-lg transition overflow-hidden group">
                                <div class="relative overflow-hidden">
                                    <a href="<?= BASE_URL ?>/product.php?slug=<?= $product['slug'] ?>">
                                        <img src="<?= $product['image_path'] ?: 'https://via.placeholder.com/300' ?>" alt="<?= clean($product['name']) ?>" class="w-full h-40 md:h-48 object-cover group-hover:scale-105 transition">
                                    </a>
                                    <?php if ($discount > 0): ?>
                                        <span class="absolute top-2 left-2 bg-red-500 text-white text-xs px-2 py-1 rounded font-semibold">-<?= $discount ?>%</span>
                                    <?php endif; ?>
                                    <?php if ($product['is_featured']): ?>
                                        <span class="absolute top-2 right-2 bg-yellow-500 text-white text-xs px-2 py-1 rounded font-semibold"><i class="fas fa-star"></i></span>
                                    <?php endif; ?>
                                </div>
                                <div class="p-3">
                                    <a href="<?= BASE_URL ?>/product.php?slug=<?= $product['slug'] ?>">
                                        <h3 class="font-semibold text-gray-800 dark:text-white text-sm line-clamp-2 mb-2 hover:text-blue-600 transition"><?= clean($product['name']) ?></h3>
                                    </a>
                                    <?php if ($product['rating_count'] > 0): ?>
                                        <div class="flex items-center gap-1 mb-2">
                                            <?= renderStars($product['rating_avg']) ?>
                                            <span class="text-xs text-gray-500">(<?= $product['rating_count'] ?>)</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex items-center justify-between">
                                        <span class="text-lg font-bold text-blue-600"><?= formatPrice($product['price']) ?></span>
                                        <button onclick="addToCart(<?= $product['id'] ?>)" class="w-8 h-8 bg-blue-50 dark:bg-blue-900 rounded-full flex items-center justify-center text-blue-600 hover:bg-blue-600 hover:text-white transition">
                                            <i class="fas fa-cart-plus text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="mt-6 flex justify-center gap-2">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?seller=<?= $sellerId ?>&page=<?= $i ?>&sort=<?= $sortBy ?>&category=<?= $categoryFilter ?>&q=<?= urlencode($searchQuery) ?>" 
                                   class="px-4 py-2 rounded-lg <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 hover:bg-gray-100' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-12 text-center">
                        <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Aucun produit trouvé</h3>
                        <p class="text-gray-500">Essayez de modifier vos filtres</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
function addToCart(productId) {
    <?php if (!isLoggedIn()): ?>
        alert('Vous devez être connecté pour ajouter au panier !');
        window.location.href = '<?= BASE_URL ?>/login.php';
        return;
    <?php endif; ?>
    
    fetch('<?= BASE_URL ?>/api/add-to-cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({product_id: productId, quantity: 1})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const cartCount = document.getElementById('cartCount');
            if (cartCount) cartCount.textContent = parseInt(cartCount.textContent || 0) + 1;
            alert('✅ Produit ajouté au panier !');
        } else {
            alert(data.message || 'Erreur');
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>