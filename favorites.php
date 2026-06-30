<?php
require_once __DIR__ . '/config/bootstrap.php';

// Rediriger si non connecté
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = BASE_URL . '/favorites.php';
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$messageType = 'success';

// Traitement des actions (Supprimer / Ajouter au panier)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $product_id = intval($_POST['product_id'] ?? 0);
    
    if ($product_id > 0) {
        if ($action === 'remove') {
            $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            $message = "Article retiré de vos favoris.";
        } 
        elseif ($action === 'add_to_cart') {
            // Vérifier si le produit est déjà dans le panier
            $checkStmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $checkStmt->execute([$user_id, $product_id]);
            $existing = $checkStmt->fetch();
            
            if ($existing) {
                $pdo->prepare("UPDATE cart SET quantity = quantity + 1 WHERE id = ?")->execute([$existing['id']]);
            } else {
                $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)")->execute([$user_id, $product_id]);
            }
            $message = "Article ajouté au panier avec succès !";
        }
        elseif ($action === 'clear_all') {
            $pdo->prepare("DELETE FROM favorites WHERE user_id = ?")->execute([$user_id]);
            $message = "Tous vos favoris ont été supprimés.";
        }
    }
}

// Récupérer les favoris (SANS created_at)
$favoritesStmt = $pdo->prepare("
    SELECT f.id as fav_id,
           p.id as product_id, p.name, p.slug, p.price, p.old_price, p.stock, p.is_active, p.seller_id,
           c.name as category_name, c.slug as category_slug,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_path,
           u.first_name as seller_first, u.last_name as seller_last
    FROM favorites f
    JOIN products p ON f.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.seller_id = u.id
    WHERE f.user_id = ?
    ORDER BY f.id DESC
");
$favoritesStmt->execute([$user_id]);
$favorites = $favoritesStmt->fetchAll();

// Calculs
$totalItems = count($favorites);
$totalValue = 0;
$availableItems = 0;

foreach ($favorites as $item) {
    if ($item['is_active'] && $item['stock'] > 0) {
        $totalValue += $item['price'];
        $availableItems++;
    }
}

$pageTitle = 'Mes Favoris';
$pageDescription = 'Retrouvez tous vos produits préférés';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<section class="bg-gray-50 dark:bg-gray-900 py-8 border-b dark:border-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <nav class="text-sm text-gray-500 dark:text-gray-400 mb-2">
            <a href="<?= BASE_URL ?>/dashboard/customer.php" class="hover:text-blue-600 transition">Tableau de bord</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-gray-800 dark:text-white font-medium">Mes Favoris</span>
        </nav>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
                    <i class="fas fa-heart text-red-500"></i> Mes Favoris
                </h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1">
                    <?= $totalItems ?> article<?= $totalItems > 1 ? 's' : '' ?> enregistré<?= $totalItems > 1 ? 's' : '' ?>
                </p>
            </div>
            <?php if ($totalItems > 0): ?>
                <div class="flex gap-3">
                    <div class="bg-white dark:bg-gray-800 px-4 py-2 rounded-lg shadow-sm border dark:border-gray-700">
                        <p class="text-xs text-gray-500">Valeur totale</p>
                        <p class="font-bold text-blue-600"><?= formatPrice($totalValue) ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Main Content -->
<section class="py-8 md:py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 flex items-center justify-between">
                <span><i class="fas fa-check-circle mr-2"></i><?= clean($message) ?></span>
                <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>

        <?php if ($totalItems > 0): ?>
            
            <!-- Toolbar -->
            <div class="flex flex-wrap justify-between items-center gap-3 mb-6 bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    <span class="font-semibold text-green-600"><?= $availableItems ?></span> article<?= $availableItems > 1 ? 's' : '' ?> disponible<?= $availableItems > 1 ? 's' : '' ?> sur <?= $totalItems ?>
                </div>
                <div class="flex gap-3">
                    <a href="<?= BASE_URL ?>/cart.php" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 text-sm font-semibold transition flex items-center gap-2">
                        <i class="fas fa-shopping-cart"></i> Voir le panier
                    </a>
                    <form method="POST" action="" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer tous vos favoris ?');" class="inline">
                        <input type="hidden" name="action" value="clear_all">
                        <button type="submit" class="px-4 py-2 bg-red-50 dark:bg-red-900/20 text-red-600 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/40 text-sm font-semibold transition flex items-center gap-2">
                            <i class="fas fa-trash-alt"></i> Tout supprimer
                        </button>
                    </form>
                </div>
            </div>

            <!-- Favorites Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($favorites as $item): 
                    $discount = calculateDiscount($item['price'], $item['old_price']);
                    $isAvailable = $item['is_active'] && $item['stock'] > 0;
                    $image = $item['image_path'] ?: 'https://via.placeholder.com/400x400?text=Pas+d\'image';
                ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-2xl transition duration-300 overflow-hidden border border-gray-100 dark:border-gray-700 flex flex-col relative group <?= !$isAvailable ? 'opacity-75 grayscale-[50%]' : '' ?>">
                        
                        <!-- Image -->
                        <div class="relative overflow-hidden">
                            <a href="<?= BASE_URL ?>/product.php?slug=<?= $item['slug'] ?>">
                                <img src="<?= $image ?>" 
                                     alt="<?= clean($item['name']) ?>" 
                                     class="w-full h-48 object-cover group-hover:scale-105 transition duration-500" loading="lazy">
                            </a>
                            
                            <?php if ($discount > 0 && $isAvailable): ?>
                                <span class="absolute top-2 left-2 bg-red-500 text-white text-xs px-2 py-1 rounded font-semibold shadow-sm">-<?= $discount ?>%</span>
                            <?php endif; ?>

                            <?php if (!$isAvailable): ?>
                                <div class="absolute inset-0 bg-black/40 flex items-center justify-center">
                                    <span class="bg-white text-gray-800 px-3 py-1 rounded-full text-sm font-bold shadow-lg">
                                        <i class="fas fa-ban mr-1"></i> Indisponible
                                    </span>
                                </div>
                            <?php endif; ?>

                            <!-- Remove Button -->
                            <form method="POST" action="" class="absolute top-2 right-2">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                <button type="submit" class="w-8 h-8 bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm rounded-full shadow-md flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-white transition transform hover:scale-110" title="Retirer des favoris">
                                    <i class="fas fa-times text-sm"></i>
                                </button>
                            </form>
                        </div>
                        
                        <!-- Content -->
                        <div class="p-4 flex flex-col flex-1">
                            <?php if ($item['category_name']): ?>
                                <a href="<?= BASE_URL ?>/category.php?slug=<?= $item['category_slug'] ?>" class="text-xs text-blue-600 hover:text-orange-500 font-medium mb-1">
                                    <?= clean($item['category_name']) ?>
                                </a>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>/product.php?slug=<?= $item['slug'] ?>" class="block mb-2 flex-1">
                                <h3 class="font-semibold text-gray-800 dark:text-white text-sm md:text-base line-clamp-2 hover:text-blue-600 transition leading-tight">
                                    <?= clean($item['name']) ?>
                                </h3>
                            </a>
                            
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                                <i class="fas fa-user text-orange-500 mr-1"></i> <?= clean($item['seller_first'] . ' ' . $item['seller_last']) ?>
                            </p>
                            
                            <div class="mt-auto pt-3 border-t border-gray-100 dark:border-gray-700">
                                <div class="flex items-end gap-2 mb-3">
                                    <span class="text-lg font-bold text-blue-600"><?= formatPrice($item['price']) ?></span>
                                    <?php if ($item['old_price']): ?>
                                        <span class="text-xs text-gray-400 line-through mb-1"><?= formatPrice($item['old_price']) ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($isAvailable): ?>
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="add_to_cart">
                                        <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                        <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg font-semibold hover:bg-blue-700 transition flex items-center justify-center gap-2 text-sm shadow-md">
                                            <i class="fas fa-cart-plus"></i> Ajouter au panier
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button disabled class="w-full bg-gray-200 dark:bg-gray-700 text-gray-500 py-2 rounded-lg font-semibold cursor-not-allowed flex items-center justify-center gap-2 text-sm">
                                        <i class="fas fa-ban"></i> Indisponible
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <!-- Empty State -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-12 text-center max-w-2xl mx-auto border border-gray-100 dark:border-gray-700">
                <div class="w-24 h-24 mx-auto bg-red-50 dark:bg-red-900/20 rounded-full flex items-center justify-center mb-6">
                    <i class="far fa-heart text-4xl text-red-400"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">Votre liste de favoris est vide</h2>
                <p class="text-gray-500 dark:text-gray-400 mb-8 max-w-md mx-auto">
                    Vous n'avez pas encore ajouté de produits à vos favoris. Parcourez notre catalogue et cliquez sur le cœur pour sauvegarder vos articles préférés.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="<?= BASE_URL ?>/products.php" class="inline-block bg-blue-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-blue-700 transition shadow-lg">
                        <i class="fas fa-shopping-bag mr-2"></i>Découvrir les produits
                    </a>
                    <a href="<?= BASE_URL ?>/promotions.php" class="inline-block bg-orange-500 text-white px-8 py-3 rounded-xl font-semibold hover:bg-orange-600 transition shadow-lg">
                        <i class="fas fa-fire mr-2"></i>Voir les promotions
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>