<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'seller') { header('Location: ' . BASE_URL); exit; }

$sellerId = $_SESSION['user_id'];
$message = '';
$errors = [];

// Récupérer les infos vendeur
$seller = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$sellerId]);
    $seller = $stmt->fetch();
} catch (Exception $e) { header('Location: ' . BASE_URL . '/login.php'); exit; }

// Stats du vendeur
$stats = ['products' => 0, 'orders' => 0, 'revenue' => 0, 'rating' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM products WHERE seller_id = ?) as products,
            (SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE p.seller_id = ?) as orders,
            (SELECT COALESCE(SUM(oi.total), 0) FROM order_items oi JOIN products p ON oi.product_id = p.id JOIN orders o ON oi.order_id = o.id WHERE p.seller_id = ? AND o.payment_status = 'paid') as revenue,
            (SELECT COALESCE(AVG(rating_avg), 0) FROM products WHERE seller_id = ? AND rating_count > 0) as rating
    ");
    $stmt->execute([$sellerId, $sellerId, $sellerId, $sellerId]);
    $stats = $stmt->fetch();
} catch (Exception $e) {}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $firstName = clean(trim($_POST['first_name'] ?? ''));
    $lastName = clean(trim($_POST['last_name'] ?? ''));
    $phone = clean(trim($_POST['phone'] ?? ''));
    $province = clean(trim($_POST['province'] ?? ''));
    $city = clean(trim($_POST['city'] ?? ''));
    $address = clean(trim($_POST['address'] ?? ''));
    $shopName = clean(trim($_POST['shop_name'] ?? ''));
    $shopDescription = clean(trim($_POST['shop_description'] ?? ''));
    
    if (empty($firstName)) $errors[] = "Le prénom est requis.";
    if (empty($lastName)) $errors[] = "Le nom est requis.";
    if (empty($phone) || strlen(preg_replace('/[^0-9]/', '', $phone)) < 8) $errors[] = "Téléphone invalide.";
    
    if (count($errors) === 0) {
        try {
            $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, province = ?, city = ?, address = ? WHERE id = ?")
                ->execute([$firstName, $lastName, $phone, $province, $city, $address, $sellerId]);
            
            $_SESSION['user_first_name'] = $firstName;
            $_SESSION['user_last_name'] = $lastName;
            $_SESSION['user_name'] = $firstName . ' ' . $lastName;
            
            $message = "✅ Profil mis à jour avec succès !";
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$sellerId]);
            $seller = $stmt->fetch();
        } catch (Exception $e) {
            $errors[] = "Erreur: " . $e->getMessage();
        }
    }
}

$provinces = [];
try { $provinces = $pdo->query("SELECT name FROM provinces ORDER BY name ASC")->fetchAll(); } catch (Exception $e) {}

$pageTitle = 'Mon Profil Vendeur';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="bg-gradient-to-r from-blue-600 to-orange-500 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-white">
        <nav class="text-sm text-white/80 mb-2">
            <a href="<?= BASE_URL ?>/dashboard/seller.php" class="hover:text-white">Dashboard</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-white font-medium">Mon Profil</span>
        </nav>
        <div class="flex items-center gap-4">
            <div class="w-20 h-20 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center text-3xl font-bold">
                <?= strtoupper(substr($seller['first_name'], 0, 1) . substr($seller['last_name'], 0, 1)) ?>
            </div>
            <div>
                <h1 class="text-3xl font-bold"><?= clean($seller['first_name'] . ' ' . $seller['last_name']) ?></h1>
                <p class="text-white/80"><?= clean($seller['email']) ?></p>
                <span class="inline-block mt-1 bg-green-500 text-white text-xs px-2 py-0.5 rounded-full font-semibold">
                    <i class="fas fa-check-circle mr-1"></i>Vendeur vérifié
                </span>
            </div>
        </div>
    </div>
</section>

<section class="py-8 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 flex items-center justify-between">
                <span><?= clean($message) ?></span>
                <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>

        <?php if (count($errors) > 0): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6">
                <ul class="list-disc list-inside text-sm space-y-1">
                    <?php foreach ($errors as $error): ?><li><?= clean($error) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Stats rapides -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-blue-500">
                <p class="text-xs text-gray-500">Produits</p>
                <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= $stats['products'] ?></p>
            </div>
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-orange-500">
                <p class="text-xs text-gray-500">Commandes</p>
                <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= $stats['orders'] ?></p>
            </div>
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-green-500">
                <p class="text-xs text-gray-500">Revenus</p>
                <p class="text-xl font-bold text-gray-800 dark:text-white"><?= formatPrice($stats['revenue']) ?></p>
            </div>
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-yellow-500">
                <p class="text-xs text-gray-500">Note moyenne</p>
                <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= number_format($stats['rating'], 1) ?> ⭐</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            
            <!-- Sidebar -->
            <aside class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 sticky top-4">
                    <div class="flex items-center gap-3 p-3 mb-4 border-b dark:border-gray-700">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-orange-500 rounded-full flex items-center justify-center text-white font-bold text-lg">
                            <?= strtoupper(substr($seller['first_name'], 0, 1) . substr($seller['last_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <p class="font-bold text-gray-800 dark:text-white"><?= clean($seller['first_name'] . ' ' . $seller['last_name']) ?></p>
                            <p class="text-xs text-gray-500 truncate"><?= clean($seller['email']) ?></p>
                        </div>
                    </div>
                    <nav class="space-y-1">
                        <a href="<?= BASE_URL ?>/dashboard/seller.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                            <i class="fas fa-tachometer-alt w-5"></i> Dashboard
                        </a>
                        <a href="<?= BASE_URL ?>/seller/profile.php" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-600 font-semibold">
                            <i class="fas fa-user w-5"></i> Mon Profil
                        </a>
                        <a href="<?= BASE_URL ?>/seller/products.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                            <i class="fas fa-box w-5"></i> Mes Produits
                        </a>
                        <a href="<?= BASE_URL ?>/seller/orders.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                            <i class="fas fa-shopping-bag w-5"></i> Commandes
                        </a>
                        <a href="<?= BASE_URL ?>/seller/analytics.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                            <i class="fas fa-chart-line w-5"></i> Statistiques
                        </a>
                        <a href="<?= BASE_URL ?>/seller/reviews.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                            <i class="fas fa-star w-5"></i> Avis
                        </a>
                        <a href="<?= BASE_URL ?>/seller/subscription.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                            <i class="fas fa-crown w-5"></i> Abonnement
                        </a>
                        <a href="<?= BASE_URL ?>/shop.php?seller=<?= $sellerId ?>" target="_blank" class="flex items-center gap-3 px-3 py-2 rounded-lg text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-900/20">
                            <i class="fas fa-store w-5"></i> Voir ma boutique
                        </a>
                        <a href="<?= BASE_URL ?>/logout.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 mt-4 border-t dark:border-gray-700 pt-4">
                            <i class="fas fa-sign-out-alt w-5"></i> Déconnexion
                        </a>
                    </nav>
                </div>
            </aside>

            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Informations personnelles -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-700">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6 flex items-center gap-2">
                        <i class="fas fa-user-edit text-blue-600"></i> Informations personnelles
                    </h2>
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Prénom *</label>
                                <input type="text" name="first_name" value="<?= clean($_POST['first_name'] ?? $seller['first_name']) ?>" required
                                       class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Nom *</label>
                                <input type="text" name="last_name" value="<?= clean($_POST['last_name'] ?? $seller['last_name']) ?>" required
                                       class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Email</label>
                                <input type="email" value="<?= clean($seller['email']) ?>" readonly
                                       class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg bg-gray-100 dark:bg-gray-900 text-gray-500 cursor-not-allowed">
                                <p class="text-xs text-gray-500 mt-1"><i class="fas fa-lock mr-1"></i>Email non modifiable</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Téléphone *</label>
                                <input type="tel" name="phone" value="<?= clean($_POST['phone'] ?? $seller['phone']) ?>" required
                                       class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                        <div class="border-t dark:border-gray-700 pt-6">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                                <i class="fas fa-map-marker-alt text-orange-500"></i> Adresse
                            </h3>
                            <div class="grid md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Province *</label>
                                    <select name="province" required class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                        <option value="">-- Sélectionner --</option>
                                        <?php foreach ($provinces as $prov): ?>
                                            <option value="<?= clean($prov['name']) ?>" <?= ($_POST['province'] ?? $seller['province']) == $prov['name'] ? 'selected' : '' ?>>
                                                <?= clean($prov['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Ville *</label>
                                    <input type="text" name="city" value="<?= clean($_POST['city'] ?? $seller['city']) ?>" required
                                           class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Adresse</label>
                                    <textarea name="address" rows="2" class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"><?= clean($_POST['address'] ?? $seller['address']) ?></textarea>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition shadow-lg flex items-center gap-2">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                    </form>
                </div>

                <!-- Changer mot de passe -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-700">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6 flex items-center gap-2">
                        <i class="fas fa-lock text-red-500"></i> Changer le mot de passe
                    </h2>
                    <form method="POST" action="<?= BASE_URL ?>/change-password.php" class="space-y-4 max-w-md">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Mot de passe actuel</label>
                            <input type="password" name="current_password" required class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Nouveau mot de passe</label>
                            <input type="password" name="new_password" required minlength="6" class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <button type="submit" class="bg-gray-800 dark:bg-gray-700 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-900 transition">
                            Mettre à jour
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>