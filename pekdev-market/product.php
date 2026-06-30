<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) redirect(BASE_URL);

$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon, c.color as category_color,
           u.first_name as seller_first, u.last_name as seller_last, u.id as seller_id, u.phone as seller_phone,
           (SELECT COUNT(*) FROM products WHERE seller_id = u.id AND is_active = 1) as seller_products_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.seller_id = u.id
    WHERE p.slug = ? AND p.is_active = 1
");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) redirect(BASE_URL);

$pdo->prepare("UPDATE products SET views_count = views_count + 1 WHERE id = ?")->execute([$product['id']]);

$images = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC");
$images->execute([$product['id']]);
$images = $images->fetchAll();
if (empty($images)) $images = [['image_path' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?w=600']];

$reviews = $pdo->prepare("SELECT r.*, u.first_name, u.last_name FROM reviews r LEFT JOIN users u ON r.user_id = u.id WHERE r.product_id = ? AND r.is_approved = 1 ORDER BY r.created_at DESC LIMIT 10");
$reviews->execute([$product['id']]);
$reviews = $reviews->fetchAll();

$related = $pdo->prepare("SELECT p.*, pi.image_path FROM products p LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1 WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1 ORDER BY p.sales_count DESC LIMIT 4");
$related->execute([$product['category_id'], $product['id']]);
$related = $related->fetchAll();

$isFav = isLoggedIn() ? isFavorite($_SESSION['user_id'], $product['id'], $pdo) : false;
$discount = calculateDiscount($product['price'], $product['old_price']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="<?= BASE_URL ?>">
    <title><?= clean($product['name']) ?> - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af', secondary: '#f97316' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; } .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <header class="bg-white dark:bg-gray-800 shadow-md sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-16">
            <a href="<?= BASE_URL ?>/" class="flex items-center gap-2">
                <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center"><span class="text-white font-bold text-xl">P</span></div>
                <div><span class="text-xl font-bold text-blue-600 dark:text-white">PekDev</span><span class="text-xs text-orange-500 block">Market</span></div>
            </a>
            <div class="flex items-center gap-2">
                <a href="<?= BASE_URL ?>/cart.php" class="p-2 text-gray-600 dark:text-gray-300"><i class="fas fa-shopping-cart text-xl"></i></a>
                <?php if (isLoggedIn()): ?>
                    <a href="<?= BASE_URL ?>/profile.php" class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold text-sm"><?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?></a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/login.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">Connexion</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 py-6">
        <nav class="text-sm text-gray-500 mb-6">
            <a href="<?= BASE_URL ?>/" class="hover:text-blue-600">Accueil</a> >
            <a href="<?= BASE_URL ?>/category.php?slug=<?= $product['category_slug'] ?>" class="hover:text-blue-600"><?= clean($product['category_name']) ?></a> >
            <span class="text-gray-800 dark:text-white"><?= clean($product['name']) ?></span>
        </nav>

        <div class="grid lg:grid-cols-2 gap-8">
            <!-- Images -->
            <div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl overflow-hidden shadow-sm mb-4 relative group">
                    <img id="mainImage" src="<?= $images[0]['image_path'] ?>" alt="<?= clean($product['name']) ?>" class="w-full h-96 object-cover cursor-zoom-in" onclick="openModal(0)">
                    <div class="absolute top-4 left-4 flex flex-col gap-2">
                        <?php if ($product['is_new']): ?><span class="bg-orange-500 text-white text-xs px-3 py-1 rounded-full font-bold">Nouveau</span><?php endif; ?>
                        <?php if ($discount > 0): ?><span class="bg-red-500 text-white text-xs px-3 py-1 rounded-full font-bold">-<?= $discount ?>%</span><?php endif; ?>
                    </div>
                    <?php if (count($images) > 1): ?>
                        <button onclick="changeImg(-1)" class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/90 rounded-full opacity-0 group-hover:opacity-100 transition"><i class="fas fa-chevron-left"></i></button>
                        <button onclick="changeImg(1)" class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/90 rounded-full opacity-0 group-hover:opacity-100 transition"><i class="fas fa-chevron-right"></i></button>
                    <?php endif; ?>
                </div>
                <?php if (count($images) > 1): ?>
                    <div class="grid grid-cols-5 gap-2">
                        <?php foreach ($images as $i => $img): ?>
                            <button onclick="setImg(<?= $i ?>)" id="thumb-<?= $i ?>" class="border-2 <?= $i === 0 ? 'border-blue-600' : 'border-transparent' ?> rounded-lg overflow-hidden">
                                <img src="<?= $img['image_path'] ?>" class="w-full h-20 object-cover">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Infos -->
            <div>
                <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-3"><?= clean($product['name']) ?></h1>
                <div class="flex items-center gap-4 text-sm mb-4">
                    <?php if ($product['rating_count'] > 0): ?>
                        <?= renderStars($product['rating_avg']) ?>
                        <span class="text-gray-500">(<?= $product['rating_count'] ?> avis)</span>
                    <?php endif; ?>
                    <span class="text-gray-500"><i class="fas fa-eye mr-1"></i><?= $product['views_count'] ?> vues</span>
                </div>

                <div class="bg-gradient-to-r from-blue-50 to-orange-50 dark:from-gray-800 dark:to-gray-700 rounded-2xl p-6 mb-4">
                    <span class="text-4xl font-bold text-blue-600"><?= formatPrice($product['price']) ?></span>
                    <?php if ($product['old_price']): ?>
                        <span class="text-lg text-gray-400 line-through ml-3"><?= formatPrice($product['old_price']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border"><i class="fas fa-map-marker-alt text-orange-500 mr-2"></i><?= clean($product['city'] ?? $product['province']) ?></div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border">
                        <?php if ($product['stock'] > 0): ?>
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>En stock (<?= $product['stock'] ?>)
                        <?php else: ?>
                            <i class="fas fa-times-circle text-red-500 mr-2"></i>Rupture
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($product['stock'] > 0): ?>
                <form method="POST" action="<?= BASE_URL ?>/cart.php" class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm mb-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Quantité</label>
                        <div class="flex items-center gap-3">
                            <div class="flex border-2 rounded-lg">
                                <button type="button" onclick="document.getElementById('qty').stepDown()" class="w-10 h-10">-</button>
                                <input type="number" id="qty" name="quantity" value="1" min="1" max="<?= $product['stock'] ?>" class="w-16 text-center border-0 focus:outline-none">
                                <button type="button" onclick="document.getElementById('qty').stepUp()" class="w-10 h-10">+</button>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="add_to_cart" class="w-full py-3 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 mb-2">
                        <i class="fas fa-shopping-cart mr-2"></i>Ajouter au panier
                    </button>
                    <a href="<?= BASE_URL ?>/checkout.php" class="block w-full py-3 bg-orange-500 text-white rounded-lg font-semibold text-center hover:bg-orange-600">
                        <i class="fas fa-bolt mr-2"></i>Acheter maintenant
                    </a>
                </form>
                <?php endif; ?>

                <!-- Vendeur -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm">
                    <h3 class="font-bold mb-4"><i class="fas fa-store text-blue-600 mr-2"></i>Vendeur</h3>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold"><?= strtoupper(substr($product['seller_first'], 0, 1)) ?></div>
                        <div>
                            <p class="font-semibold"><?= clean($product['seller_first'] . ' ' . $product['seller_last']) ?></p>
                            <p class="text-xs text-gray-500"><?= $product['seller_products_count'] ?> produits</p>
                        </div>
                    </div>
                    <?php if ($product['seller_phone']): ?>
                    <div class="flex gap-2">
                        <a href="tel:<?= $product['seller_phone'] ?>" class="flex-1 py-2 bg-green-500 text-white rounded-lg text-center text-sm"><i class="fas fa-phone mr-1"></i>Appeler</a>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $product['seller_phone']) ?>" target="_blank" class="flex-1 py-2 bg-green-600 text-white rounded-lg text-center text-sm"><i class="fab fa-whatsapp mr-1"></i>WhatsApp</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="mt-8 bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm">
            <h3 class="text-xl font-bold mb-4">Description</h3>
            <div class="text-gray-700 dark:text-gray-300"><?= nl2br(clean($product['description'])) ?></div>
        </div>

        <!-- Avis -->
        <?php if (!empty($reviews)): ?>
        <div class="mt-8 bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm">
            <h3 class="text-xl font-bold mb-4">Avis (<?= count($reviews) ?>)</h3>
            <div class="space-y-4">
                <?php foreach ($reviews as $review): ?>
                <div class="border-b pb-4 last:border-0">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold"><?= strtoupper(substr($review['first_name'], 0, 1)) ?></div>
                        <div>
                            <p class="font-semibold"><?= clean($review['first_name'] . ' ' . $review['last_name']) ?></p>
                            <?= renderStars($review['rating']) ?>
                        </div>
                    </div>
                    <p class="text-gray-600 dark:text-gray-300"><?= nl2br(clean($review['comment'])) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Similaires -->
        <?php if (!empty($related)): ?>
        <div class="mt-8">
            <h3 class="text-xl font-bold mb-4">Produits similaires</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($related as $r): ?>
                <a href="<?= BASE_URL ?>/product.php?slug=<?= $r['slug'] ?>" class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow hover:shadow-xl transition">
                    <img src="<?= $r['image_path'] ?? 'https://via.placeholder.com/300' ?>" class="w-full h-40 object-cover">
                    <div class="p-3">
                        <h4 class="font-semibold text-sm line-clamp-2"><?= clean($r['name']) ?></h4>
                        <p class="text-blue-600 font-bold mt-2"><?= formatPrice($r['price']) ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal -->
    <div id="modal" class="hidden fixed inset-0 bg-black/95 z-50 flex items-center justify-center p-4" onclick="closeModal()">
        <button onclick="closeModal()" class="absolute top-4 right-4 w-12 h-12 bg-white/10 rounded-full text-white text-2xl"><i class="fas fa-times"></i></button>
        <button onclick="event.stopPropagation(); changeImg(-1)" class="absolute left-4 w-12 h-12 bg-white/10 rounded-full text-white text-xl"><i class="fas fa-chevron-left"></i></button>
        <button onclick="event.stopPropagation(); changeImg(1)" class="absolute right-4 w-12 h-12 bg-white/10 rounded-full text-white text-xl"><i class="fas fa-chevron-right"></i></button>
        <img id="modalImg" src="" class="max-w-full max-h-full object-contain">
    </div>

    <!-- WhatsApp flottant -->
    <?php if ($product['seller_phone']): ?>
    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $product['seller_phone']) ?>" target="_blank" class="fixed bottom-6 right-6 w-14 h-14 bg-green-500 text-white rounded-full shadow-xl flex items-center justify-center hover:scale-110 transition z-30">
        <i class="fab fa-whatsapp text-2xl"></i>
    </a>
    <?php endif; ?>

    <script>
    const imgs = <?= json_encode(array_column($images, 'image_path')) ?>;
    let curIdx = 0;
    function setImg(i) {
        curIdx = i;
        document.getElementById('mainImage').src = imgs[i];
        imgs.forEach((_, idx) => {
            const t = document.getElementById('thumb-' + idx);
            if (t) t.classList.toggle('border-blue-600', idx === i);
        });
    }
    function changeImg(d) { setImg((curIdx + d + imgs.length) % imgs.length); }
    function openModal(i) {
        setImg(i);
        document.getElementById('modalImg').src = imgs[i];
        document.getElementById('modal').classList.remove('hidden');
    }
    function closeModal() { document.getElementById('modal').classList.add('hidden'); }
    document.addEventListener('keydown', e => {
        if (document.getElementById('modal').classList.contains('hidden')) return;
        if (e.key === 'Escape') closeModal();
        if (e.key === 'ArrowLeft') changeImg(-1);
        if (e.key === 'ArrowRight') changeImg(1);
    });
    </script>
</body>
</html>