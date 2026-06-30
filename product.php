<?php
require_once __DIR__ . '/config/bootstrap.php';

$slug = trim($_GET['slug'] ?? '');
if (empty($slug)) {
    header('Location: ' . BASE_URL . '/products.php');
    exit;
}

// Récupérer le produit
$product = null;
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon, c.color as category_color,
               u.first_name as seller_first, u.last_name as seller_last, u.id as seller_id, u.city as seller_city, u.is_verified as seller_verified
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.seller_id = u.id
        WHERE p.slug = ? AND p.is_active = 1
    ");
    $stmt->execute([$slug]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header('Location: ' . BASE_URL . '/products.php');
        exit;
    }
    
    // Incrémenter les vues
    $pdo->prepare("UPDATE products SET views_count = views_count + 1 WHERE id = ?")->execute([$product['id']]);
} catch (Exception $e) {
    header('Location: ' . BASE_URL . '/products.php');
    exit;
}

// Images
$images = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");
    $stmt->execute([$product['id']]);
    $images = $stmt->fetchAll();
} catch (Exception $e) {}

// Avis
$reviews = [];
$reviewStats = ['total' => 0, 'avg' => 0, '5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.first_name, u.last_name 
        FROM reviews r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.product_id = ? AND r.is_approved = 1 
        ORDER BY r.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$product['id']]);
    $reviews = $stmt->fetchAll();
    
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            COALESCE(AVG(rating), 0) as avg,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as s5,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as s4,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as s3,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as s2,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as s1
        FROM reviews WHERE product_id = ? AND is_approved = 1
    ");
    $statsStmt->execute([$product['id']]);
    $s = $statsStmt->fetch();
    $reviewStats = ['total' => $s['total'], 'avg' => $s['avg'], '5' => $s['s5'], '4' => $s['s4'], '3' => $s['s3'], '2' => $s['s2'], '1' => $s['s1']];
} catch (Exception $e) {}

// Produits similaires
$similarProducts = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.*, (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_path
        FROM products p
        WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1
        ORDER BY p.sales_count DESC
        LIMIT 4
    ");
    $stmt->execute([$product['category_id'], $product['id']]);
    $similarProducts = $stmt->fetchAll();
} catch (Exception $e) {}

// Vérifier si en favori
$isFavorite = false;
if (isLoggedIn()) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $product['id']]);
        $isFavorite = $stmt->fetch() !== false;
    } catch (Exception $e) {}
}

// Traitement avis
$reviewMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_review') {
    if (!isLoggedIn()) {
        $reviewMessage = "❌ Vous devez être connecté pour laisser un avis.";
    } else {
        $rating = intval($_POST['rating'] ?? 0);
        $title = clean(trim($_POST['title'] ?? ''));
        $comment = clean(trim($_POST['comment'] ?? ''));
        
        if ($rating < 1 || $rating > 5) $reviewMessage = "❌ Note invalide.";
        elseif (empty($comment) || strlen($comment) < 10) $reviewMessage = "❌ Le commentaire doit contenir au moins 10 caractères.";
        else {
            try {
                $checkStmt = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
                $checkStmt->execute([$_SESSION['user_id'], $product['id']]);
                
                if ($checkStmt->fetch()) {
                    $reviewMessage = "❌ Vous avez déjà laissé un avis pour ce produit.";
                } else {
                    $pdo->prepare("INSERT INTO reviews (user_id, product_id, rating, title, comment, is_approved) VALUES (?, ?, ?, ?, ?, 0)")
                        ->execute([$_SESSION['user_id'], $product['id'], $rating, $title, $comment]);
                    $reviewMessage = "✅ Merci ! Votre avis sera publié après modération.";
                }
            } catch (Exception $e) {
                $reviewMessage = "❌ Erreur: " . $e->getMessage();
            }
        }
    }
}

$discount = calculateDiscount($product['price'], $product['old_price']);
$pageTitle = $product['name'];
$pageDescription = $product['short_description'] ?: substr(strip_tags($product['description']), 0, 160);
require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<div class="bg-gray-100 dark:bg-gray-800 py-3">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <nav class="text-sm text-gray-500 flex items-center gap-2 flex-wrap">
            <a href="<?= BASE_URL ?>" class="hover:text-blue-600">Accueil</a>
            <i class="fas fa-chevron-right text-xs"></i>
            <a href="<?= BASE_URL ?>/category.php?slug=<?= $product['category_slug'] ?>" class="hover:text-blue-600"><?= clean($product['category_name']) ?></a>
            <i class="fas fa-chevron-right text-xs"></i>
            <span class="text-gray-800 dark:text-white font-medium truncate max-w-xs"><?= clean($product['name']) ?></span>
        </nav>
    </div>
</div>

<section class="py-8 bg-white dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-8">
            
            <!-- Galerie -->
            <div>
                <div class="relative bg-gray-100 dark:bg-gray-800 rounded-2xl overflow-hidden mb-4 aspect-square">
                    <img id="mainImage" src="<?= $images[0]['image_path'] ?? 'https://via.placeholder.com/600' ?>" 
                         alt="<?= clean($product['name']) ?>" class="w-full h-full object-cover">
                    <?php if ($discount > 0): ?>
                        <span class="absolute top-4 left-4 bg-red-500 text-white px-3 py-1 rounded-lg font-bold shadow-lg">-<?= $discount ?>%</span>
                    <?php endif; ?>
                    <?php if ($product['is_new']): ?>
                        <span class="absolute top-4 <?= $discount > 0 ? 'left-20' : 'left-4' ?> bg-orange-500 text-white px-3 py-1 rounded-lg font-bold shadow-lg">NOUVEAU</span>
                    <?php endif; ?>
                    <?php if ($product['stock'] <= 5 && $product['stock'] > 0): ?>
                        <span class="absolute bottom-4 left-4 bg-yellow-500 text-white px-3 py-1 rounded-lg font-semibold shadow-lg">
                            <i class="fas fa-exclamation-triangle mr-1"></i>Plus que <?= $product['stock'] ?> en stock !
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php if (count($images) > 1): ?>
                    <div class="grid grid-cols-5 gap-2">
                        <?php foreach ($images as $i => $img): ?>
                            <button onclick="changeImage('<?= $img['image_path'] ?>', this)" 
                                    class="aspect-square rounded-lg overflow-hidden border-2 <?= $i == 0 ? 'border-blue-600' : 'border-gray-200 dark:border-gray-700' ?> hover:border-blue-600 transition">
                                <img src="<?= $img['image_path'] ?>" alt="" class="w-full h-full object-cover">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Infos -->
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <a href="<?= BASE_URL ?>/category.php?slug=<?= $product['category_slug'] ?>" 
                       class="inline-flex items-center gap-1 bg-<?= $product['category_color'] ?>-100 dark:bg-<?= $product['category_color'] ?>-900/30 text-<?= $product['category_color'] ?>-700 dark:text-<?= $product['category_color'] ?>-300 text-xs px-3 py-1 rounded-full font-semibold">
                        <i class="<?= $product['category_icon'] ?>"></i>
                        <?= clean($product['category_name']) ?>
                    </a>
                    <?php if ($product['is_featured']): ?>
                        <span class="inline-flex items-center gap-1 bg-yellow-100 text-yellow-800 text-xs px-3 py-1 rounded-full font-semibold">
                            <i class="fas fa-star"></i> En vedette
                        </span>
                    <?php endif; ?>
                </div>

                <h1 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-white mb-3"><?= clean($product['name']) ?></h1>

                <!-- Note -->
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex items-center gap-1">
                        <?= renderStars($product['rating_avg']) ?>
                        <span class="text-sm font-semibold text-gray-800 dark:text-white ml-1"><?= number_format($product['rating_avg'], 1) ?></span>
                    </div>
                    <span class="text-sm text-gray-500">(<?= $product['rating_count'] ?> avis)</span>
                    <span class="text-sm text-gray-500">•</span>
                    <span class="text-sm text-gray-500"><i class="fas fa-eye mr-1"></i><?= number_format($product['views_count']) ?></span>
                    <span class="text-sm text-gray-500">•</span>
                    <span class="text-sm text-gray-500"><i class="fas fa-shopping-bag mr-1"></i><?= $product['sales_count'] ?> vendus</span>
                </div>

                <!-- Prix -->
                <div class="bg-gradient-to-r from-blue-50 to-orange-50 dark:from-gray-800 dark:to-gray-800 rounded-xl p-5 mb-5">
                    <div class="flex items-baseline gap-3">
                        <span class="text-4xl font-bold text-blue-600"><?= formatPrice($product['price']) ?></span>
                        <?php if ($product['old_price']): ?>
                            <span class="text-xl text-gray-400 line-through"><?= formatPrice($product['old_price']) ?></span>
                            <span class="bg-red-500 text-white text-sm px-2 py-1 rounded font-bold">-<?= $discount ?>%</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($product['old_price']): ?>
                        <p class="text-sm text-green-600 font-semibold mt-2">
                            <i class="fas fa-tag mr-1"></i>Vous économisez <?= formatPrice($product['old_price'] - $product['price']) ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Vendeur -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 mb-5 flex items-center gap-3">
                    <a href="<?= BASE_URL ?>/shop.php?seller=<?= $product['seller_id'] ?>" class="flex items-center gap-3 flex-1 hover:bg-gray-50 dark:hover:bg-gray-700 p-2 rounded-lg transition">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-orange-500 rounded-full flex items-center justify-center text-white font-bold">
                            <?= strtoupper(substr($product['seller_first'], 0, 1) . substr($product['seller_last'], 0, 1)) ?>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-gray-800 dark:text-white flex items-center gap-1">
                                <?= clean($product['seller_first'] . ' ' . $product['seller_last']) ?>
                                <?php if ($product['seller_verified']): ?>
                                    <i class="fas fa-check-circle text-blue-500 text-sm" title="Vendeur vérifié"></i>
                                <?php endif; ?>
                            </p>
                            <p class="text-xs text-gray-500"><i class="fas fa-map-marker-alt mr-1"></i><?= clean($product['seller_city']) ?></p>
                        </div>
                    </a>
                    <a href="<?= BASE_URL ?>/shop.php?seller=<?= $product['seller_id'] ?>" class="text-sm text-blue-600 hover:text-blue-800 font-semibold">Voir boutique →</a>
                </div>

                <!-- Localisation -->
                <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 mb-5">
                    <i class="fas fa-map-marker-alt text-orange-500"></i>
                    <span>Disponible à <strong><?= clean($product['city'] ?? $product['province']) ?></strong></span>
                </div>

                <!-- Actions -->
                <div class="flex flex-col sm:flex-row gap-3 mb-5">
                    <?php if ($product['stock'] > 0): ?>
                        <button onclick="addToCart(<?= $product['id'] ?>)" 
                                class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 text-white py-4 rounded-xl font-bold hover:from-blue-700 hover:to-blue-800 transition shadow-lg flex items-center justify-center gap-2">
                            <i class="fas fa-cart-plus"></i> Ajouter au panier
                        </button>
                        <button onclick="buyNow(<?= $product['id'] ?>)" 
                                class="flex-1 bg-gradient-to-r from-orange-500 to-orange-600 text-white py-4 rounded-xl font-bold hover:from-orange-600 hover:to-orange-700 transition shadow-lg flex items-center justify-center gap-2">
                            <i class="fas fa-bolt"></i> Acheter maintenant
                        </button>
                    <?php else: ?>
                        <button disabled class="flex-1 bg-gray-300 text-gray-500 py-4 rounded-xl font-bold cursor-not-allowed">
                            <i class="fas fa-ban mr-2"></i>Rupture de stock
                        </button>
                    <?php endif; ?>
                    
                    <?php if (isLoggedIn()): ?>
                        <button onclick="toggleFavorite(<?= $product['id'] ?>, this)" 
                                class="w-14 h-14 border-2 <?= $isFavorite ? 'border-red-500 text-red-500 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-700 text-gray-400 hover:text-red-500 hover:border-red-500' ?> rounded-xl flex items-center justify-center transition">
                            <i class="<?= $isFavorite ? 'fas' : 'far' ?> fa-heart text-xl"></i>
                        </button>
                        
                        <?php if ($product['seller_id'] != $_SESSION['user_id']): ?>
                            <a href="<?= BASE_URL ?>/messages.php?with=<?= $product['seller_id'] ?>&product=<?= $product['id'] ?>" 
                               class="w-14 h-14 border-2 border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:text-blue-600 hover:border-blue-600 rounded-xl flex items-center justify-center transition" 
                               title="Contacter le vendeur">
                                <i class="fas fa-comment-dots text-xl"></i>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Garanties -->
                <div class="grid grid-cols-3 gap-3 mb-5">
                    <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <i class="fas fa-shipping-fast text-green-600 text-xl mb-1"></i>
                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">Livraison rapide</p>
                    </div>
                    <div class="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <i class="fas fa-shield-alt text-blue-600 text-xl mb-1"></i>
                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">Paiement sécurisé</p>
                    </div>
                    <div class="text-center p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
                        <i class="fas fa-undo text-orange-600 text-xl mb-1"></i>
                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">Retours 30 jours</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="mt-12 bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6 md:p-8 border border-gray-100 dark:border-gray-700">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <i class="fas fa-info-circle text-blue-600"></i> Description
            </h2>
            <?php if ($product['short_description']): ?>
                <p class="text-gray-700 dark:text-gray-300 mb-4 font-medium"><?= clean($product['short_description']) ?></p>
            <?php endif; ?>
            <div class="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300 whitespace-pre-line">
                <?= nl2br(clean($product['description'])) ?>
            </div>
        </div>

        <!-- Avis -->
        <div class="mt-8 bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6 md:p-8 border border-gray-100 dark:border-gray-700">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center gap-2">
                <i class="fas fa-star text-yellow-500"></i> Avis clients (<?= $reviewStats['total'] ?>)
            </h2>

            <?php if ($reviewStats['total'] > 0): ?>
                <div class="grid md:grid-cols-3 gap-6 mb-8 pb-6 border-b dark:border-gray-700">
                    <div class="text-center">
                        <p class="text-5xl font-bold text-yellow-500"><?= number_format($reviewStats['avg'], 1) ?></p>
                        <div class="mt-2"><?= renderStars($reviewStats['avg']) ?></div>
                        <p class="text-xs text-gray-500 mt-1"><?= $reviewStats['total'] ?> avis</p>
                    </div>
                    <div class="md:col-span-2 space-y-2">
                        <?php for ($i = 5; $i >= 1; $i--): 
                            $count = $reviewStats[$i];
                            $percent = $reviewStats['total'] > 0 ? ($count / $reviewStats['total']) * 100 : 0;
                        ?>
                            <div class="flex items-center gap-2">
                                <span class="text-sm w-4"><?= $i ?></span>
                                <i class="fas fa-star text-yellow-400 text-xs"></i>
                                <div class="flex-1 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                    <div class="h-full bg-yellow-400" style="width: <?= $percent ?>%"></div>
                                </div>
                                <span class="text-xs text-gray-500 w-8"><?= $count ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Formulaire avis -->
            <?php if (isLoggedIn()): ?>
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-5 mb-6">
                    <h3 class="font-bold text-gray-800 dark:text-white mb-3">Laisser un avis</h3>
                    <?php if ($reviewMessage): ?>
                        <div class="bg-<?= strpos($reviewMessage, '✅') !== false ? 'green' : 'red' ?>-50 border border-<?= strpos($reviewMessage, '✅') !== false ? 'green' : 'red' ?>-200 text-<?= strpos($reviewMessage, '✅') !== false ? 'green' : 'red' ?>-700 px-4 py-2 rounded-lg mb-3 text-sm"><?= $reviewMessage ?></div>
                    <?php endif; ?>
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="submit_review">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Votre note *</label>
                            <div class="flex gap-1" id="ratingStars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <button type="button" onclick="setRating(<?= $i ?>)" class="text-3xl text-gray-300 hover:text-yellow-400 transition">
                                        <i class="fas fa-star"></i>
                                    </button>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="rating" id="ratingInput" value="0" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Titre</label>
                            <input type="text" name="title" maxlength="255" class="w-full px-4 py-2 border dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white" placeholder="Ex: Excellent produit !">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Commentaire *</label>
                            <textarea name="comment" rows="3" required minlength="10" class="w-full px-4 py-2 border dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white" placeholder="Partagez votre expérience..."></textarea>
                        </div>
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-blue-700 transition">
                            <i class="fas fa-paper-plane mr-2"></i>Publier l'avis
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Liste avis -->
            <?php if (count($reviews) > 0): ?>
                <div class="space-y-4">
                    <?php foreach ($reviews as $review): ?>
                        <div class="border-b dark:border-gray-700 pb-4 last:border-0">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-orange-500 rounded-full flex items-center justify-center text-white font-bold flex-shrink-0">
                                    <?= strtoupper(substr($review['first_name'], 0, 1)) ?>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <p class="font-semibold text-gray-800 dark:text-white"><?= clean($review['first_name'] . ' ' . $review['last_name']) ?></p>
                                        <span class="text-xs text-gray-500">•</span>
                                        <span class="text-xs text-gray-500"><?= date('d/m/Y', strtotime($review['created_at'])) ?></span>
                                    </div>
                                    <div class="mb-2"><?= renderStars($review['rating']) ?></div>
                                    <?php if ($review['title']): ?>
                                        <p class="font-semibold text-gray-800 dark:text-white mb-1"><?= clean($review['title']) ?></p>
                                    <?php endif; ?>
                                    <p class="text-sm text-gray-600 dark:text-gray-300"><?= clean($review['comment']) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-gray-500 py-6">Aucun avis pour le moment. Soyez le premier à donner votre avis !</p>
            <?php endif; ?>
        </div>

        <!-- Produits similaires -->
        <?php if (count($similarProducts) > 0): ?>
            <div class="mt-8">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-th-large text-blue-600"></i> Produits similaires
                </h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <?php foreach ($similarProducts as $p): 
                        $d = calculateDiscount($p['price'], $p['old_price']);
                    ?>
                        <a href="<?= BASE_URL ?>/product.php?slug=<?= $p['slug'] ?>" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-lg transition overflow-hidden group">
                            <div class="relative overflow-hidden">
                                <img src="<?= $p['image_path'] ?: 'https://via.placeholder.com/300' ?>" alt="<?= clean($p['name']) ?>" class="w-full h-40 object-cover group-hover:scale-105 transition">
                                <?php if ($d > 0): ?>
                                    <span class="absolute top-2 left-2 bg-red-500 text-white text-xs px-2 py-1 rounded font-semibold">-<?= $d ?>%</span>
                                <?php endif; ?>
                            </div>
                            <div class="p-3">
                                <h3 class="font-semibold text-gray-800 dark:text-white text-sm line-clamp-2 mb-2"><?= clean($p['name']) ?></h3>
                                <p class="text-lg font-bold text-blue-600"><?= formatPrice($p['price']) ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
function changeImage(src, btn) {
    document.getElementById('mainImage').src = src;
    document.querySelectorAll('.grid-cols-5 button').forEach(b => {
        b.classList.remove('border-blue-600');
        b.classList.add('border-gray-200');
    });
    btn.classList.remove('border-gray-200');
    btn.classList.add('border-blue-600');
}

function setRating(rating) {
    document.getElementById('ratingInput').value = rating;
    const stars = document.querySelectorAll('#ratingStars button');
    stars.forEach((s, i) => {
        s.classList.toggle('text-yellow-400', i < rating);
        s.classList.toggle('text-gray-300', i >= rating);
    });
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
            const c = document.getElementById('cartCount');
            if (c) c.textContent = parseInt(c.textContent || 0) + 1;
            showToast('✅ Produit ajouté au panier !', 'success');
        } else {
            showToast(data.message || 'Erreur', 'error');
        }
    });
}

function buyNow(productId) {
    <?php if (!isLoggedIn()): ?>
        alert('Connectez-vous pour acheter');
        window.location.href = '<?= BASE_URL ?>/login.php';
        return;
    <?php endif; ?>
    addToCart(productId);
    setTimeout(() => window.location.href = '<?= BASE_URL ?>/checkout.php', 500);
}

function toggleFavorite(productId, btn) {
    fetch('<?= BASE_URL ?>/api/toggle-favorite.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({product_id: productId})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const icon = btn.querySelector('i');
            if (data.action === 'added') {
                icon.classList.remove('far');
                icon.classList.add('fas');
                btn.classList.remove('border-gray-300', 'text-gray-400');
                btn.classList.add('border-red-500', 'text-red-500', 'bg-red-50');
                showToast('❤️ Ajouté aux favoris', 'success');
            } else {
                icon.classList.remove('fas');
                icon.classList.add('far');
                btn.classList.remove('border-red-500', 'text-red-500', 'bg-red-50');
                btn.classList.add('border-gray-300', 'text-gray-400');
                showToast('Retiré des favoris', 'info');
            }
        }
    });
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = 'fixed top-20 right-4 z-50 bg-' + (type === 'success' ? 'green' : type === 'error' ? 'red' : 'blue') + '-500 text-white px-6 py-3 rounded-lg shadow-lg animate-fade-in flex items-center gap-2';
    toast.innerHTML = message + '<button onclick="this.parentElement.remove()" class="ml-2"><i class="fas fa-times"></i></button>';
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>