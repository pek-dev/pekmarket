<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'seller') { header('Location: ' . BASE_URL); exit; }

$sellerId = $_SESSION['user_id'];
$message = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $productId = intval($_POST['product_id'] ?? 0);
    
    if ($productId > 0) {
        try {
            // Vérifier que le produit appartient au vendeur
            $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND seller_id = ?");
            $stmt->execute([$productId, $sellerId]);
            
            if ($stmt->fetch()) {
                if ($action === 'toggle_active') {
                    $pdo->prepare("UPDATE products SET is_active = NOT is_active WHERE id = ?")->execute([$productId]);
                    $message = "Statut modifié.";
                } elseif ($action === 'toggle_featured') {
                    $pdo->prepare("UPDATE products SET is_featured = NOT is_featured WHERE id = ?")->execute([$productId]);
                    $message = "Vedette modifiée.";
                } elseif ($action === 'delete') {
                    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$productId]);
                    $message = "Produit supprimé.";
                }
            }
        } catch (Exception $e) { $message = "Erreur: " . $e->getMessage(); }
    }
}

// Filtres
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$where = ["p.seller_id = ?"];
$params = [$sellerId];

if ($statusFilter === 'active') { $where[] = "p.is_active = 1"; }
elseif ($statusFilter === 'inactive') { $where[] = "p.is_active = 0"; }
elseif ($statusFilter === 'featured') { $where[] = "p.is_featured = 1"; }
elseif ($statusFilter === 'low') { $where[] = "p.stock <= 5"; }

if ($searchQuery) { $where[] = "p.name LIKE ?"; $params[] = "%$searchQuery%"; }

$whereClause = implode(' AND ', $where);

try {
    $productsStmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, 
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_path
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE $whereClause
        ORDER BY p.created_at DESC
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

$pageTitle = 'Mes Produits';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <nav class="text-sm text-gray-500 mb-2">
                <a href="<?= BASE_URL ?>/dashboard/seller.php" class="hover:text-blue-600">Dashboard</a>
                <i class="fas fa-chevron-right text-xs mx-2"></i>
                <span class="text-gray-800 dark:text-white font-medium">Mes Produits</span>
            </nav>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
                <i class="fas fa-box text-blue-600"></i> Mes Produits
            </h1>
            <p class="text-gray-500 mt-1"><?= $totalProducts ?> produit(s) au total</p>
        </div>
        <a href="<?= BASE_URL ?>/seller/add-product.php" class="px-5 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold flex items-center gap-2 shadow-md">
            <i class="fas fa-plus"></i> Ajouter un produit
        </a>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 flex items-center justify-between">
            <span><i class="fas fa-check-circle mr-2"></i><?= clean($message) ?></span>
            <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" class="flex flex-col md:flex-row gap-3">
            <input type="text" name="q" value="<?= clean($searchQuery) ?>" placeholder="Rechercher un produit..." 
                   class="flex-1 px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
            <select name="status" onchange="this.form.submit()" class="px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                <option value="all" <?= $statusFilter == 'all' ? 'selected' : '' ?>>Tous</option>
                <option value="active" <?= $statusFilter == 'active' ? 'selected' : '' ?>>Actifs</option>
                <option value="inactive" <?= $statusFilter == 'inactive' ? 'selected' : '' ?>>Inactifs</option>
                <option value="featured" <?= $statusFilter == 'featured' ? 'selected' : '' ?>>En vedette</option>
                <option value="low" <?= $statusFilter == 'low' ? 'selected' : '' ?>>Stock faible</option>
            </select>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">
                <i class="fas fa-search mr-2"></i>Filtrer
            </button>
        </form>
    </div>

    <!-- Grille de produits -->
    <?php if (count($products) > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <?php foreach ($products as $product): 
                $discount = calculateDiscount($product['price'], $product['old_price']);
            ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden <?= !$product['is_active'] ? 'opacity-60' : '' ?>">
                    <div class="relative">
                        <img src="<?= $product['image_path'] ?: 'https://via.placeholder.com/300' ?>" alt="<?= clean($product['name']) ?>" class="w-full h-40 object-cover">
                        <div class="absolute top-2 left-2 flex flex-col gap-1">
                            <?php if ($product['is_featured']): ?><span class="bg-yellow-500 text-white text-xs px-2 py-1 rounded font-semibold"><i class="fas fa-star"></i> Vedette</span><?php endif; ?>
                            <?php if ($discount > 0): ?><span class="bg-red-500 text-white text-xs px-2 py-1 rounded font-semibold">-<?= $discount ?>%</span><?php endif; ?>
                            <?php if ($product['stock'] <= 5): ?><span class="bg-orange-500 text-white text-xs px-2 py-1 rounded font-semibold">Stock: <?= $product['stock'] ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div class="p-4">
                        <p class="text-xs text-gray-500 mb-1"><?= clean($product['category_name'] ?? 'Sans catégorie') ?></p>
                        <h3 class="font-semibold text-gray-800 dark:text-white text-sm line-clamp-2 mb-2"><?= clean($product['name']) ?></h3>
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-lg font-bold text-blue-600"><?= formatPrice($product['price']) ?></span>
                            <span class="text-xs text-gray-500"><i class="fas fa-eye mr-1"></i><?= $product['views_count'] ?> • <i class="fas fa-shopping-bag mr-1"></i><?= $product['sales_count'] ?></span>
                        </div>
                        <div class="flex gap-1">
                            <a href="<?= BASE_URL ?>/seller/edit-product.php?id=<?= $product['id'] ?>" class="flex-1 py-2 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 text-xs font-semibold text-center">
                                <i class="fas fa-edit mr-1"></i>Modifier
                            </a>
                            <form method="POST" class="flex-1"><input type="hidden" name="product_id" value="<?= $product['id'] ?>"><input type="hidden" name="action" value="toggle_active"><button class="w-full py-2 bg-<?= $product['is_active'] ? 'yellow' : 'green' ?>-100 text-<?= $product['is_active'] ? 'yellow' : 'green' ?>-600 rounded-lg hover:bg-<?= $product['is_active'] ? 'yellow' : 'green' ?>-200 text-xs font-semibold"><i class="fas fa-<?= $product['is_active'] ? 'ban' : 'check' ?>"></i> <?= $product['is_active'] ? 'Désactiver' : 'Activer' ?></button></form>
                            <form method="POST" onsubmit="return confirm('Supprimer ce produit ?');"><input type="hidden" name="product_id" value="<?= $product['id'] ?>"><input type="hidden" name="action" value="delete"><button class="w-10 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 text-xs font-semibold"><i class="fas fa-trash"></i></button></form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="mt-6 flex justify-center gap-2">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>&status=<?= $statusFilter ?>&q=<?= urlencode($searchQuery) ?>" class="px-4 py-2 rounded-lg <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 hover:bg-gray-100' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-12 text-center">
            <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Aucun produit trouvé</h3>
            <p class="text-gray-500 mb-6">Commencez par ajouter votre premier produit</p>
            <a href="<?= BASE_URL ?>/seller/add-product.php" class="inline-block bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 font-semibold">
                <i class="fas fa-plus mr-2"></i>Ajouter un produit
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>