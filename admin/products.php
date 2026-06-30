<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: ' . BASE_URL); exit; }

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $productId = intval($_POST['product_id'] ?? 0);
    if ($productId > 0) {
        try {
            if ($action === 'toggle_active') { $pdo->prepare("UPDATE products SET is_active = NOT is_active WHERE id = ?")->execute([$productId]); $message = "Statut modifié."; }
            elseif ($action === 'toggle_featured') { $pdo->prepare("UPDATE products SET is_featured = NOT is_featured WHERE id = ?")->execute([$productId]); $message = "Vedette modifiée."; }
            elseif ($action === 'delete') { $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$productId]); $message = "Produit supprimé."; }
        } catch (Exception $e) { $message = "Erreur: " . $e->getMessage(); }
    }
}

$categoryFilter = $_GET['category'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ["1=1"]; $params = [];
if ($categoryFilter !== 'all') { $where[] = "p.category_id = ?"; $params[] = $categoryFilter; }
if ($statusFilter === 'active') { $where[] = "p.is_active = 1"; }
elseif ($statusFilter === 'inactive') { $where[] = "p.is_active = 0"; }
if ($statusFilter === 'featured') { $where[] = "p.is_featured = 1"; }
if ($searchQuery) { $where[] = "p.name LIKE ?"; $params[] = "%$searchQuery%"; }
$whereClause = implode(' AND ', $where);

try {
    $productsStmt = $pdo->prepare("SELECT p.*, c.name as category_name, u.first_name as seller_first, u.last_name as seller_last, (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as main_image FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN users u ON p.seller_id = u.id WHERE $whereClause ORDER BY p.created_at DESC LIMIT ? OFFSET ?");
    $params[] = $perPage; $params[] = $offset;
    $productsStmt->execute($params);
    $products = $productsStmt->fetchAll();
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereClause");
    $countStmt->execute(array_slice($params, 0, -2));
    $totalProducts = $countStmt->fetchColumn();
    $totalPages = ceil($totalProducts / $perPage);
} catch (Exception $e) { $products = []; $totalPages = 0; }

$categories = [];
try { $categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll(); } catch (Exception $e) {}

$pageTitle = 'Gestion des Produits';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <nav class="text-sm text-gray-500 mb-2"><a href="<?= BASE_URL ?>/dashboard/admin.php" class="hover:text-blue-600">Admin</a> <i class="fas fa-chevron-right text-xs mx-2"></i> Produits</nav>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white"><i class="fas fa-box text-green-600 mr-2"></i>Gestion des Produits</h1>
        <p class="text-gray-500 text-sm"><?= $totalProducts ?> produits au total</p>
    </div>

    <?php if ($message): ?><div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6"><?= clean($message) ?></div><?php endif; ?>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" class="flex flex-col md:flex-row gap-3">
            <input type="text" name="q" value="<?= clean($searchQuery) ?>" placeholder="Rechercher..." class="flex-1 px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
            <select name="category" onchange="this.form.submit()" class="px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                <option value="all" <?= $categoryFilter == 'all' ? 'selected' : '' ?>>Toutes catégories</option>
                <?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>><?= clean($cat['name']) ?></option><?php endforeach; ?>
            </select>
            <select name="status" onchange="this.form.submit()" class="px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                <option value="all" <?= $statusFilter == 'all' ? 'selected' : '' ?>>Tous statuts</option>
                <option value="active" <?= $statusFilter == 'active' ? 'selected' : '' ?>>Actifs</option>
                <option value="inactive" <?= $statusFilter == 'inactive' ? 'selected' : '' ?>>Inactifs</option>
                <option value="featured" <?= $statusFilter == 'featured' ? 'selected' : '' ?>>En vedette</option>
            </select>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold"><i class="fas fa-search mr-2"></i>Filtrer</button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <?php foreach ($products as $product): 
            $discount = calculateDiscount($product['price'], $product['old_price']);
        ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden <?= !$product['is_active'] ? 'opacity-60' : '' ?>">
                <div class="relative">
                    <img src="<?= $product['main_image'] ?: 'https://via.placeholder.com/300' ?>" alt="<?= clean($product['name']) ?>" class="w-full h-40 object-cover">
                    <div class="absolute top-2 left-2 flex gap-1">
                        <?php if ($product['is_featured']): ?><span class="bg-yellow-500 text-white text-xs px-2 py-1 rounded font-semibold"><i class="fas fa-star"></i> Vedette</span><?php endif; ?>
                        <?php if ($discount > 0): ?><span class="bg-red-500 text-white text-xs px-2 py-1 rounded font-semibold">-<?= $discount ?>%</span><?php endif; ?>
                    </div>
                </div>
                <div class="p-4">
                    <h3 class="font-semibold text-gray-800 dark:text-white text-sm line-clamp-2 mb-1"><?= clean($product['name']) ?></h3>
                    <p class="text-xs text-gray-500 mb-2"><?= clean($product['seller_first'] . ' ' . $product['seller_last']) ?></p>
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-lg font-bold text-blue-600"><?= formatPrice($product['price']) ?></span>
                        <span class="text-xs text-gray-500">Stock: <?= $product['stock'] ?></span>
                    </div>
                    <div class="flex gap-1">
                        <form method="POST" class="flex-1"><input type="hidden" name="product_id" value="<?= $product['id'] ?>"><input type="hidden" name="action" value="toggle_active"><button class="w-full py-2 bg-<?= $product['is_active'] ? 'yellow' : 'green' ?>-100 text-<?= $product['is_active'] ? 'yellow' : 'green' ?>-600 rounded-lg hover:bg-<?= $product['is_active'] ? 'yellow' : 'green' ?>-200 text-xs font-semibold"><i class="fas fa-<?= $product['is_active'] ? 'ban' : 'check' ?>"></i> <?= $product['is_active'] ? 'Désactiver' : 'Activer' ?></button></form>
                        <form method="POST" class="flex-1"><input type="hidden" name="product_id" value="<?= $product['id'] ?>"><input type="hidden" name="action" value="toggle_featured"><button class="w-full py-2 bg-yellow-100 text-yellow-600 rounded-lg hover:bg-yellow-200 text-xs font-semibold"><i class="fas fa-star"></i> <?= $product['is_featured'] ? 'Retirer' : 'Vedette' ?></button></form>
                        <form method="POST" onsubmit="return confirm('Supprimer ?');"><input type="hidden" name="product_id" value="<?= $product['id'] ?>"><input type="hidden" name="action" value="delete"><button class="w-10 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 text-xs font-semibold"><i class="fas fa-trash"></i></button></form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (count($products) === 0): ?><div class="bg-white dark:bg-gray-800 rounded-xl p-12 text-center text-gray-500"><i class="fas fa-box-open text-4xl mb-4 text-gray-300"></i><p>Aucun produit trouvé</p></div><?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <div class="mt-6 flex justify-center gap-2">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&category=<?= $categoryFilter ?>&status=<?= $statusFilter ?>&q=<?= urlencode($searchQuery) ?>" class="px-4 py-2 rounded-lg <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>