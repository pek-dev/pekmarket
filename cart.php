<?php
require_once __DIR__ . '/config/bootstrap.php';
requireLogin();

$userId = $_SESSION['user_id'];
$message = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cartId = intval($_POST['cart_id'] ?? 0);
    
    if ($cartId > 0) {
        try {
            if ($action === 'update_quantity') {
                $quantity = max(1, intval($_POST['quantity'] ?? 1));
                $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?")
                    ->execute([$quantity, $cartId, $userId]);
                $message = "✅ Quantité mise à jour";
            }
            elseif ($action === 'remove') {
                $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?")
                    ->execute([$cartId, $userId]);
                $message = "✅ Article retiré du panier";
            }
            elseif ($action === 'clear_all') {
                $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);
                $message = "✅ Panier vidé";
            }
        } catch (Exception $e) {
            $message = "❌ Erreur: " . $e->getMessage();
        }
    }
}

// Récupérer les articles du panier
$cartItems = [];
$total = 0;
$totalItems = 0;

try {
    $stmt = $pdo->prepare("
        SELECT c.id as cart_id, c.quantity,
               p.id as product_id, p.name, p.slug, p.price, p.old_price, p.stock, p.is_active,
               p.seller_id, u.first_name as seller_first, u.last_name as seller_last,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_path
        FROM cart c
        JOIN products p ON c.product_id = p.id
        LEFT JOIN users u ON p.seller_id = u.id
        WHERE c.user_id = ?
        ORDER BY c.id DESC
    ");
    $stmt->execute([$userId]);
    $cartItems = $stmt->fetchAll();
    
    foreach ($cartItems as $item) {
        $total += $item['price'] * $item['quantity'];
        $totalItems += $item['quantity'];
    }
} catch (Exception $e) {
    $cartItems = [];
}

$pageTitle = 'Mon Panier';
require_once __DIR__ . '/includes/header.php';
?>

<section class="bg-gray-50 dark:bg-gray-900 min-h-screen py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="mb-6">
            <nav class="text-sm text-gray-500 mb-2">
                <a href="<?= BASE_URL ?>" class="hover:text-blue-600">Accueil</a>
                <i class="fas fa-chevron-right text-xs mx-2"></i>
                <span class="text-gray-800 dark:text-white font-medium">Panier</span>
            </nav>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
                <i class="fas fa-shopping-cart text-blue-600"></i> Mon Panier
                <?php if ($totalItems > 0): ?>
                    <span class="bg-orange-500 text-white text-sm px-3 py-1 rounded-full font-bold"><?= $totalItems ?> article<?= $totalItems > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </h1>
        </div>

        <?php if ($message): ?>
            <div class="bg-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-50 border border-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-200 text-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-700 px-4 py-3 rounded-xl mb-6 flex items-center justify-between">
                <span><?= clean($message) ?></span>
                <button onclick="this.parentElement.remove()" class="hover:opacity-70"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>

        <?php if (count($cartItems) > 0): ?>
            <div class="grid lg:grid-cols-3 gap-6">
                
                <!-- Liste des articles -->
                <div class="lg:col-span-2 space-y-4">
                    <?php foreach ($cartItems as $item): 
                        $discount = calculateDiscount($item['price'], $item['old_price']);
                        $isAvailable = $item['is_active'] && $item['stock'] >= $item['quantity'];
                    ?>
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden <?= !$isAvailable ? 'opacity-75' : '' ?>">
                            <div class="flex flex-col sm:flex-row">
                                <!-- Image -->
                                <div class="sm:w-32 h-32 flex-shrink-0 relative">
                                    <a href="<?= BASE_URL ?>/product.php?slug=<?= $item['slug'] ?>">
                                        <img src="<?= $item['image_path'] ?: 'https://via.placeholder.com/150' ?>" 
                                             alt="<?= clean($item['name']) ?>" 
                                             class="w-full h-full object-cover">
                                    </a>
                                    <?php if ($discount > 0): ?>
                                        <span class="absolute top-2 left-2 bg-red-500 text-white text-xs px-2 py-1 rounded font-bold">-<?= $discount ?>%</span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Infos -->
                                <div class="flex-1 p-4">
                                    <div class="flex justify-between items-start gap-3 mb-2">
                                        <div class="flex-1 min-w-0">
                                            <a href="<?= BASE_URL ?>/product.php?slug=<?= $item['slug'] ?>" class="font-semibold text-gray-800 dark:text-white hover:text-blue-600 transition line-clamp-2">
                                                <?= clean($item['name']) ?>
                                            </a>
                                            <p class="text-xs text-gray-500 mt-1">
                                                <i class="fas fa-user mr-1"></i>
                                                <?= clean($item['seller_first'] . ' ' . $item['seller_last']) ?>
                                            </p>
                                        </div>
                                        <form method="POST" class="flex-shrink-0">
                                            <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                                            <input type="hidden" name="action" value="remove">
                                            <button type="submit" class="w-8 h-8 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 flex items-center justify-center transition" title="Supprimer">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <!-- Prix et quantité -->
                                    <div class="flex flex-wrap items-center justify-between gap-3 mt-3">
                                        <div class="flex items-baseline gap-2">
                                            <span class="text-xl font-bold text-blue-600"><?= formatPrice($item['price']) ?></span>
                                            <?php if ($item['old_price']): ?>
                                                <span class="text-sm text-gray-400 line-through"><?= formatPrice($item['old_price']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Quantité -->
                                        <form method="POST" class="flex items-center gap-2">
                                            <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                                            <input type="hidden" name="action" value="update_quantity">
                                            <div class="flex items-center border dark:border-gray-700 rounded-lg overflow-hidden">
                                                <button type="button" onclick="updateQuantity(<?= $item['cart_id'] ?>, -1)" class="w-8 h-8 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 transition">
                                                    <i class="fas fa-minus text-xs"></i>
                                                </button>
                                                <input type="number" name="quantity" id="qty_<?= $item['cart_id'] ?>" 
                                                       value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock'] ?>"
                                                       class="w-12 h-8 text-center border-0 dark:bg-gray-800 dark:text-white focus:ring-0"
                                                       onchange="this.form.submit()">
                                                <button type="button" onclick="updateQuantity(<?= $item['cart_id'] ?>, 1)" class="w-8 h-8 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 transition">
                                                    <i class="fas fa-plus text-xs"></i>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                    
                                    <!-- Sous-total -->
                                    <div class="mt-3 pt-3 border-t dark:border-gray-700 flex justify-between items-center">
                                        <span class="text-sm text-gray-500">Sous-total :</span>
                                        <span class="text-lg font-bold text-gray-800 dark:text-white"><?= formatPrice($item['price'] * $item['quantity']) ?></span>
                                    </div>
                                    
                                    <?php if (!$isAvailable): ?>
                                        <div class="mt-2 bg-red-50 dark:bg-red-900/20 text-red-700 text-xs px-3 py-2 rounded-lg">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            <?php if (!$item['is_active']): ?>
                                                Produit indisponible
                                            <?php else: ?>
                                                Stock insuffisant (<?= $item['stock'] ?> disponible)
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Actions -->
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="<?= BASE_URL ?>/products.php" class="flex-1 px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white rounded-lg font-semibold hover:bg-gray-300 transition text-center">
                            <i class="fas fa-arrow-left mr-2"></i>Continuer mes achats
                        </a>
                        <form method="POST" class="flex-1" onsubmit="return confirm('Vider tout le panier ?');">
                            <input type="hidden" name="action" value="clear_all">
                            <input type="hidden" name="cart_id" value="0">
                            <button type="submit" class="w-full px-6 py-3 bg-red-100 dark:bg-red-900/20 text-red-600 rounded-lg font-semibold hover:bg-red-200 transition">
                                <i class="fas fa-trash mr-2"></i>Vider le panier
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Résumé -->
                <div class="lg:col-span-1">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 sticky top-4">
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                            <i class="fas fa-receipt text-blue-600"></i> Résumé
                        </h2>
                        
                        <div class="space-y-3 mb-6">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-300">Sous-total (<?= $totalItems ?> articles)</span>
                                <span class="font-semibold text-gray-800 dark:text-white"><?= formatPrice($total) ?></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-300">Livraison</span>
                                <span class="font-semibold <?= $total >= 100000 ? 'text-green-600' : 'text-gray-800 dark:text-white' ?>">
                                    <?= $total >= 100000 ? 'GRATUITE' : formatPrice(5000) ?>
                                </span>
                            </div>
                            <?php if ($total < 100000): ?>
                                <div class="bg-blue-50 dark:bg-blue-900/20 text-blue-700 text-xs p-3 rounded-lg">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Ajoutez <?= formatPrice(100000 - $total) ?> pour bénéficier de la livraison gratuite !
                                </div>
                            <?php endif; ?>
                            <div class="border-t dark:border-gray-700 pt-3">
                                <div class="flex justify-between">
                                    <span class="text-lg font-bold text-gray-800 dark:text-white">Total</span>
                                    <span class="text-2xl font-bold text-blue-600">
                                        <?= formatPrice($total + ($total >= 100000 ? 0 : 5000)) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <a href="<?= BASE_URL ?>/checkout.php" class="block w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white py-3.5 rounded-lg font-bold hover:from-blue-700 hover:to-blue-800 transition shadow-lg text-center">
                            <i class="fas fa-lock mr-2"></i>Passer la commande
                        </a>
                        
                        <!-- Garanties -->
                        <div class="mt-6 pt-6 border-t dark:border-gray-700 space-y-3">
                            <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                <i class="fas fa-shield-alt text-green-500"></i>
                                <span>Paiement 100% sécurisé</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                <i class="fas fa-undo text-orange-500"></i>
                                <span>Retours sous 30 jours</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                <i class="fas fa-headset text-purple-500"></i>
                                <span>Support 24/7</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Panier vide -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-12 text-center max-w-2xl mx-auto border border-gray-100 dark:border-gray-700">
                <div class="w-24 h-24 mx-auto bg-blue-50 dark:bg-blue-900/20 rounded-full flex items-center justify-center mb-6">
                    <i class="fas fa-shopping-cart text-5xl text-blue-300"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">Votre panier est vide</h2>
                <p class="text-gray-500 dark:text-gray-400 mb-8 max-w-md mx-auto">
                    Vous n'avez pas encore ajouté de produits à votre panier. Explorez notre catalogue et trouvez des produits qui vous plaisent !
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

<script>
function updateQuantity(cartId, delta) {
    const input = document.getElementById('qty_' + cartId);
    const newQty = parseInt(input.value) + delta;
    const max = parseInt(input.max);
    
    if (newQty >= 1 && newQty <= max) {
        input.value = newQty;
        input.form.submit();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>