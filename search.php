<?php
require_once __DIR__ . '/config/bootstrap.php';

$query = trim($_GET['q'] ?? '');
$categoryFilter = intval($_GET['category'] ?? 0);
$sortBy = $_GET['sort'] ?? 'relevance';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$products = [];
$totalProducts = 0;
$totalPages = 0;

if (!empty($query) || $categoryFilter > 0) {
    $where = ["p.is_active = 1"];
    $params = [];
    
    if (!empty($query)) {
        $where[] = "(p.name LIKE ? OR p.description LIKE ? OR p.short_description LIKE ?)";
        $params[] = "%$query%";
        $params[] = "%$query%";
        $params[] = "%$query%";
    }
    if ($categoryFilter > 0) {
        $where[] = "p.category_id = ?";
        $params[] = $categoryFilter;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $orderBy = match($sortBy) {
        'price_asc' => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'popular' => 'p.sales_count DESC',
        'newest' => 'p.created_at DESC',
        default => 'p.sales_count DESC'
    };
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name,
                   (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_path
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE $whereClause
            ORDER BY $orderBy
            LIMIT ? OFFSET ?
        ");
        $params[] = $perPage; $params[] = $offset;
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereClause");
        $countStmt->execute(array_slice($params, 0, -2));
        $totalProducts = $countStmt->fetchColumn();
        $totalPages = ceil($totalProducts / $perPage);
    } catch (Exception $e) {}
}

// Catégories
$categories = [];
try { $categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll(); } catch (Exception $e) {}

$pageTitle = !empty($query) ? 'Recherche : ' . $query : 'Recherche';
require_once __DIR__ . '/includes/header.php';
?>

<section class="bg-gradient-to-r from-blue-600 to-blue-800 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-3xl font-bold text-white mb-4">
            <i class="fas fa-search mr-2"></i>Rechercher un produit
        </h1>
        <form method="GET" action="" class="flex gap-2 max-w-2xl mx-auto">
            <input type="text" name="q" value="<?= clean($query) ?>" placeholder="Que recherchez-vous ?" autofocus
                   class="flex-1 px-6 py-3 rounded-xl text-gray-800 focus:outline-none focus:ring-4 focus:ring-white/30">
            <button type="submit" class="px-6 py-3 bg-orange-500 text-white rounded-xl font-semibold hover:bg-orange-600 transition">
                <i class="fas fa-search"></i>
            </button>
        </form>
    </div>
</section>

<section class="py-8 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <?php if (!empty($query) || $categoryFilter > 0): ?>
            <div class="mb-6 flex flex-wrap justify-between items-center gap-3">
                <p class="text-gray-600 dark:text-gray-300">
                    <?php if (!empty($query)): ?>
                        <strong><?= $totalProducts ?></strong> résultat(s) pour "<strong><?= clean($query) ?></strong>"
                    <?php else: ?>
                        <strong><?= $totalProducts ?></strong> produit(s) trouvé(s)
                    <?php endif; ?>
                </p>
                
                <div class="flex gap-2">
                    <select onchange="applySort(this.value)" class="px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-800 dark:text-white text-sm">
                        <option value="relevance" <?= $sortBy == 'relevance' ? 'selected' : '' ?>>Pertinence</option>
                        <option value="newest" <?= $sortBy == 'newest' ? 'selected' : '' ?>>Plus récents</option>
                        <option value="popular" <?= $sortBy == 'popular' ? 'selected' : '' ?>>Populaires</option>
                        <option value="price_asc" <?= $sortBy == 'price_asc' ? 'selected' : '' ?>>Prix croissant</option>
                        <option value="price_desc" <?= $sortBy == 'price_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                    </select>
                    <select onchange="applyCategory(this.value)" class="px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-800 dark:text-white text-sm">
                        <option value="0">Toutes catégories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>><?= clean($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <?php if (count($products) > 0): ?>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
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
                            </div>
                            <div class="p-3">
                                <p class="text-xs text-gray-500 mb-1"><?= clean($product['category_name'] ?? '') ?></p>
                                <a href="<?= BASE_URL ?>/product.php?slug=<?= $product['slug'] ?>">
                                    <h3 class="font-semibold text-gray-800 dark:text-white text-sm line-clamp-2 mb-2 hover:text-blue-600"><?= clean($product['name']) ?></h3>
                                </a>
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
                            <a href="?q=<?= urlencode($query) ?>&category=<?= $categoryFilter ?>&sort=<?= $sortBy ?>&page=<?= $i ?>" 
                               class="px-4 py-2 rounded-lg <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 hover:bg-gray-100' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-12 text-center">
                    <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Aucun résultat</h3>
                    <p class="text-gray-500 mb-4">Essayez avec d'autres mots-clés</p>
                    <a href="<?= BASE_URL ?>/products.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-semibold">
                        Voir tous les produits
                    </a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-12 text-center">
                <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Commencez votre recherche</h3>
                <p class="text-gray-500">Entrez un mot-clé dans la barre de recherche ci-dessus</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
function applySort(sort) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sort);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}
function applyCategory(cat) {
    const url = new URL(window.location.href);
    url.searchParams.set('category', cat);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}
function addToCart(productId) {
    <?php if (!isLoggedIn()): ?>
        alert('Connectez-vous pour ajouter au panier');
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
            alert('✅ Ajouté au panier !');
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>