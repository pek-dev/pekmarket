<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'seller') { header('Location: ' . BASE_URL); exit; }

$sellerId = $_SESSION['user_id'];
$errors = [];
$message = '';

// Récupérer l'ID du produit
$productId = intval($_GET['id'] ?? 0);
if ($productId <= 0) {
    header('Location: ' . BASE_URL . '/seller/products.php');
    exit;
}

// Récupérer le produit (doit appartenir au vendeur)
$product = null;
$productImages = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
    $stmt->execute([$productId, $sellerId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header('Location: ' . BASE_URL . '/seller/products.php?error=not_found');
        exit;
    }
    
    // Récupérer les images existantes
    $imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");
    $imgStmt->execute([$productId]);
    $productImages = $imgStmt->fetchAll();
} catch (Exception $e) {
    header('Location: ' . BASE_URL . '/seller/products.php');
    exit;
}

// Récupérer catégories et provinces
$categories = [];
$provinces = [];
try {
    $categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
    $provinces = $pdo->query("SELECT name FROM provinces ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';
    
    try {
        // Mise à jour des informations
        if ($action === 'update') {
            $name = clean(trim($_POST['name'] ?? ''));
            $description = clean(trim($_POST['description'] ?? ''));
            $shortDescription = clean(trim($_POST['short_description'] ?? ''));
            $price = floatval($_POST['price'] ?? 0);
            $oldPrice = !empty($_POST['old_price']) ? floatval($_POST['old_price']) : null;
            $stock = intval($_POST['stock'] ?? 0);
            $categoryId = intval($_POST['category_id'] ?? 0);
            $province = clean(trim($_POST['province'] ?? ''));
            $city = clean(trim($_POST['city'] ?? ''));
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
            $isNew = isset($_POST['is_new']) ? 1 : 0;
            
            // Validation
            if (empty($name) || strlen($name) < 3) $errors[] = "Le nom doit contenir au moins 3 caractères.";
            if (empty($description)) $errors[] = "La description est requise.";
            if ($price <= 0) $errors[] = "Le prix doit être supérieur à 0.";
            if ($oldPrice && $oldPrice <= $price) $errors[] = "L'ancien prix doit être supérieur au prix actuel.";
            if ($stock < 0) $errors[] = "Le stock ne peut pas être négatif.";
            if ($categoryId <= 0) $errors[] = "Veuillez sélectionner une catégorie.";
            if (empty($province)) $errors[] = "Veuillez sélectionner une province.";
            if (empty($city)) $errors[] = "La ville est requise.";
            
            if (count($errors) === 0) {
                // Générer un nouveau slug si le nom a changé
                $slug = $product['slug'];
                if ($name !== $product['name']) {
                    $newSlug = generateSlug($name);
                    $checkStmt = $pdo->prepare("SELECT id FROM products WHERE slug = ? AND id != ?");
                    $checkStmt->execute([$newSlug, $productId]);
                    if (!$checkStmt->fetch()) {
                        $slug = $newSlug;
                    } else {
                        $slug = $newSlug . '-' . substr(uniqid(), -4);
                    }
                }
                
                $pdo->prepare("
                    UPDATE products 
                    SET name = ?, slug = ?, description = ?, short_description = ?, 
                        price = ?, old_price = ?, stock = ?, category_id = ?, 
                        province = ?, city = ?, is_featured = ?, is_new = ?
                    WHERE id = ? AND seller_id = ?
                ")->execute([
                    $name, $slug, $description, $shortDescription,
                    $price, $oldPrice, $stock, $categoryId,
                    $province, $city, $isFeatured, $isNew,
                    $productId, $sellerId
                ]);
                
                $message = "✅ Produit mis à jour avec succès !";
                
                // Recharger le produit
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                $stmt->execute([$productId]);
                $product = $stmt->fetch();
            }
        }
        
        // Suppression d'une image
        elseif ($action === 'delete_image') {
            $imageId = intval($_POST['image_id'] ?? 0);
            if ($imageId > 0) {
                $imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE id = ? AND product_id = ?");
                $imgStmt->execute([$imageId, $productId]);
                $image = $imgStmt->fetch();
                
                if ($image) {
                    // Supprimer le fichier physique
                    if (strpos($image['image_path'], '/uploads/') !== false) {
                        $localPath = __DIR__ . '/../' . parse_url($image['image_path'], PHP_URL_PATH);
                        if (file_exists($localPath)) {
                            unlink($localPath);
                        }
                    }
                    
                    $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([$imageId]);
                    
                    // Si c'était l'image principale, définir une autre image comme principale
                    if ($image['is_primary']) {
                        $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE product_id = ? LIMIT 1")->execute([$productId]);
                    }
                    
                    $message = "✅ Image supprimée";
                    
                    // Recharger les images
                    $imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");
                    $imgStmt->execute([$productId]);
                    $productImages = $imgStmt->fetchAll();
                }
            }
        }
        
        // Définir une image comme principale
        elseif ($action === 'set_primary') {
            $imageId = intval($_POST['image_id'] ?? 0);
            if ($imageId > 0) {
                $pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?")->execute([$productId]);
                $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?")->execute([$imageId, $productId]);
                $message = "✅ Image principale mise à jour";
                
                $imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");
                $imgStmt->execute([$productId]);
                $productImages = $imgStmt->fetchAll();
            }
        }
        
        // Ajouter de nouvelles images
        elseif ($action === 'add_images') {
            if (isset($_FILES['images']) && count(array_filter($_FILES['images']['name'])) > 0) {
                $uploadDir = UPLOAD_DIR . 'products/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                $imageCount = count($_FILES['images']['name']);
                $hasPrimary = count($productImages) > 0;
                
                for ($i = 0; $i < $imageCount; $i++) {
                    if ($_FILES['images']['error'][$i] === 0) {
                        $file = $_FILES['images']['tmp_name'][$i];
                        $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                        
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                            $filename = 'product_' . $productId . '_' . time() . '_' . $i . '.' . $ext;
                            $filepath = $uploadDir . $filename;
                            
                            if (move_uploaded_file($file, $filepath)) {
                                $isPrimary = !$hasPrimary ? 1 : 0;
                                if ($isPrimary) $hasPrimary = true;
                                
                                $imagePath = BASE_URL . '/uploads/products/' . $filename;
                                $pdo->prepare("INSERT INTO product_images (product_id, image_path, alt_text, is_primary) VALUES (?, ?, ?, ?)")
                                    ->execute([$productId, $imagePath, $product['name'], $isPrimary]);
                            }
                        }
                    }
                }
                
                $message = "✅ Images ajoutées avec succès";
                
                $imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");
                $imgStmt->execute([$productId]);
                $productImages = $imgStmt->fetchAll();
            }
        }
        
    } catch (Exception $e) {
        $errors[] = "Erreur: " . $e->getMessage();
    }
}

$pageTitle = 'Modifier : ' . $product['name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <nav class="text-sm text-gray-500 mb-2">
            <a href="<?= BASE_URL ?>/dashboard/seller.php" class="hover:text-blue-600">Dashboard</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <a href="<?= BASE_URL ?>/seller/products.php" class="hover:text-blue-600">Produits</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-gray-800 dark:text-white font-medium">Modifier</span>
        </nav>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
            <i class="fas fa-edit text-blue-600"></i> Modifier le produit
        </h1>
        <p class="text-gray-500 mt-1">Produit ID: #<?= $product['id'] ?> • Créé le <?= date('d/m/Y', strtotime($product['created_at'])) ?></p>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 flex items-center justify-between">
            <span><?= clean($message) ?></span>
            <button onclick="this.parentElement.remove()" class="hover:opacity-70"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>

    <?php if (count($errors) > 0): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6">
            <ul class="list-disc list-inside text-sm space-y-1">
                <?php foreach ($errors as $error): ?><li><?= clean($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-3 gap-6">
        
        <!-- Colonne principale : Formulaire -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Informations de base -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 text-white">
                    <h2 class="text-xl font-bold flex items-center gap-2"><i class="fas fa-info-circle"></i> Informations de base</h2>
                </div>
                <form method="POST" action="" class="p-6 space-y-4">
                    <input type="hidden" name="action" value="update">
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Nom du produit *</label>
                        <input type="text" name="name" value="<?= clean($product['name']) ?>" required maxlength="255"
                               class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Catégorie *</label>
                            <select name="category_id" required class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $product['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= clean($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Slug (URL)</label>
                            <input type="text" value="<?= clean($product['slug']) ?>" readonly
                                   class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg bg-gray-100 dark:bg-gray-900 text-gray-500 cursor-not-allowed">
                            <p class="text-xs text-gray-500 mt-1"><i class="fas fa-lock mr-1"></i>Auto-généré depuis le nom</p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Description courte</label>
                        <input type="text" name="short_description" value="<?= clean($product['short_description']) ?>" maxlength="500"
                               class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Description complète *</label>
                        <textarea name="description" rows="6" required
                                  class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"><?= clean($product['description']) ?></textarea>
                    </div>
                    
                    <!-- Prix et stock -->
                    <div class="border-t dark:border-gray-700 pt-4">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                            <i class="fas fa-tag text-blue-600"></i> Prix et stock
                        </h3>
                        <div class="grid md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Prix (FBu) *</label>
                                <input type="number" name="price" value="<?= $product['price'] ?>" required min="0" step="0.01"
                                       class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Ancien prix (FBu)</label>
                                <input type="number" name="old_price" value="<?= $product['old_price'] ?? '' ?>" min="0" step="0.01"
                                       class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Stock *</label>
                                <input type="number" name="stock" value="<?= $product['stock'] ?>" required min="0"
                                       class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Localisation -->
                    <div class="border-t dark:border-gray-700 pt-4">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                            <i class="fas fa-map-marker-alt text-blue-600"></i> Localisation
                        </h3>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Province *</label>
                                <select name="province" required class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">-- Sélectionner --</option>
                                    <?php foreach ($provinces as $prov): ?>
                                        <option value="<?= clean($prov['name']) ?>" <?= $product['province'] == $prov['name'] ? 'selected' : '' ?>><?= clean($prov['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Ville *</label>
                                <input type="text" name="city" value="<?= clean($product['city']) ?>" required
                                       class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Options -->
                    <div class="border-t dark:border-gray-700 pt-4 space-y-3">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                            <i class="fas fa-cog text-blue-600"></i> Options
                        </h3>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_featured" <?= $product['is_featured'] ? 'checked' : '' ?> class="w-5 h-5 text-blue-600 rounded">
                            <span class="text-sm text-gray-700 dark:text-gray-200"><strong>Mettre en vedette</strong></span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_new" <?= $product['is_new'] ? 'checked' : '' ?> class="w-5 h-5 text-blue-600 rounded">
                            <span class="text-sm text-gray-700 dark:text-gray-200"><strong>Marquer comme nouveau</strong></span>
                        </label>
                    </div>
                    
                    <!-- Boutons -->
                    <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t dark:border-gray-700">
                        <button type="submit" class="flex-1 bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition flex items-center justify-center gap-2">
                            <i class="fas fa-save"></i> Enregistrer les modifications
                        </button>
                        <a href="<?= BASE_URL ?>/seller/products.php" class="px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white rounded-lg font-semibold hover:bg-gray-300 transition text-center">
                            Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Colonne latérale : Images et stats -->
        <div class="space-y-6">
            
            <!-- Gestion des images -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-6 py-4 text-white">
                    <h2 class="text-xl font-bold flex items-center gap-2"><i class="fas fa-images"></i> Images (<?= count($productImages) ?>)</h2>
                </div>
                <div class="p-6">
                    <!-- Images existantes -->
                    <?php if (count($productImages) > 0): ?>
                        <div class="grid grid-cols-2 gap-2 mb-4">
                            <?php foreach ($productImages as $img): ?>
                                <div class="relative group rounded-lg overflow-hidden border-2 <?= $img['is_primary'] ? 'border-blue-500' : 'border-gray-200 dark:border-gray-700' ?>">
                                    <img src="<?= $img['image_path'] ?>" alt="" class="w-full h-24 object-cover">
                                    <?php if ($img['is_primary']): ?>
                                        <span class="absolute top-1 left-1 bg-blue-500 text-white text-[10px] px-2 py-0.5 rounded font-semibold">Principal</span>
                                    <?php endif; ?>
                                    <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-1">
                                        <?php if (!$img['is_primary']): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="set_primary">
                                                <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                                <button class="w-7 h-7 bg-blue-500 text-white rounded-full hover:bg-blue-600" title="Définir comme principal">
                                                    <i class="fas fa-star text-xs"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette image ?');">
                                            <input type="hidden" name="action" value="delete_image">
                                            <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                            <button class="w-7 h-7 bg-red-500 text-white rounded-full hover:bg-red-600" title="Supprimer">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-gray-500 text-sm mb-4">Aucune image</p>
                    <?php endif; ?>
                    
                    <!-- Ajouter des images -->
                    <form method="POST" enctype="multipart/form-data" class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-4 text-center hover:border-blue-500 transition cursor-pointer" onclick="document.getElementById('addImagesInput').click()">
                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                        <p class="text-sm text-gray-600 dark:text-gray-300 font-medium">Ajouter des images</p>
                        <p class="text-xs text-gray-500 mt-1">JPG, PNG, WEBP (max 5MB)</p>
                        <input type="file" name="images[]" id="addImagesInput" accept="image/*" multiple class="hidden" onchange="this.form.submit()">
                        <input type="hidden" name="action" value="add_images">
                    </form>
                </div>
            </div>
            
            <!-- Statistiques du produit -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 text-white">
                    <h2 class="text-xl font-bold flex items-center gap-2"><i class="fas fa-chart-bar"></i> Statistiques</h2>
                </div>
                <div class="p-6 space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-300"><i class="fas fa-eye mr-2 text-blue-500"></i>Vues</span>
                        <span class="font-bold text-gray-800 dark:text-white"><?= number_format($product['views_count']) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-300"><i class="fas fa-shopping-bag mr-2 text-green-500"></i>Ventes</span>
                        <span class="font-bold text-gray-800 dark:text-white"><?= number_format($product['sales_count']) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-300"><i class="fas fa-star mr-2 text-yellow-500"></i>Note</span>
                        <span class="font-bold text-gray-800 dark:text-white"><?= number_format($product['rating_avg'], 1) ?>/5 (<?= $product['rating_count'] ?>)</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-300"><i class="fas fa-box mr-2 text-purple-500"></i>Stock</span>
                        <span class="font-bold <?= $product['stock'] <= 5 ? 'text-red-600' : 'text-gray-800 dark:text-white' ?>"><?= $product['stock'] ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-300"><i class="fas fa-calendar mr-2 text-gray-500"></i>Créé le</span>
                        <span class="text-sm text-gray-800 dark:text-white"><?= date('d/m/Y', strtotime($product['created_at'])) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Aperçu -->
            <a href="<?= BASE_URL ?>/product.php?slug=<?= $product['slug'] ?>" target="_blank" class="block bg-gradient-to-r from-orange-500 to-orange-600 text-white rounded-xl p-4 text-center font-semibold hover:from-orange-600 hover:to-orange-700 transition shadow-lg">
                <i class="fas fa-external-link-alt mr-2"></i> Voir sur le site
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>