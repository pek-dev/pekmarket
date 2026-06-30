<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'seller') { header('Location: ' . BASE_URL); exit; }

$sellerId = $_SESSION['user_id'];
$errors = [];

// Récupérer les catégories et provinces
$categories = [];
$provinces = [];
try {
    $categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
    $provinces = $pdo->query("SELECT name FROM provinces ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    // Vérifier les images
    if (!isset($_FILES['images']) || count(array_filter($_FILES['images']['name'])) === 0) {
        $errors[] = "Veuillez télécharger au moins une image.";
    }
    
    // Vérifier le slug unique
    if (count($errors) === 0) {
        $slug = generateSlug($name);
        $checkStmt = $pdo->prepare("SELECT id FROM products WHERE slug = ?");
        $checkStmt->execute([$slug]);
        if ($checkStmt->fetch()) {
            $slug .= '-' . substr(uniqid(), -4);
        }
    }
    
    if (count($errors) === 0) {
        try {
            $pdo->beginTransaction();
            
            // Créer le produit
            $stmt = $pdo->prepare("
                INSERT INTO products 
                (seller_id, category_id, name, slug, description, short_description, price, old_price, stock, province, city, is_featured, is_new, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$sellerId, $categoryId, $name, $slug, $description, $shortDescription, $price, $oldPrice, $stock, $province, $city, $isFeatured, $isNew]);
            $productId = $pdo->lastInsertId();
            
            // Gérer les images
            $uploadDir = UPLOAD_DIR . 'products/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $imageCount = count($_FILES['images']['name']);
            $primarySet = false;
            
            for ($i = 0; $i < $imageCount; $i++) {
                if ($_FILES['images']['error'][$i] === 0) {
                    $file = $_FILES['images']['tmp_name'][$i];
                    $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                    
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                        $filename = 'product_' . $productId . '_' . time() . '_' . $i . '.' . $ext;
                        $filepath = $uploadDir . $filename;
                        
                        if (move_uploaded_file($file, $filepath)) {
                            $isPrimary = !$primarySet ? 1 : 0;
                            if ($isPrimary) $primarySet = true;
                            
                            $imagePath = BASE_URL . '/uploads/products/' . $filename;
                            $pdo->prepare("INSERT INTO product_images (product_id, image_path, alt_text, is_primary) VALUES (?, ?, ?, ?)")
                                ->execute([$productId, $imagePath, $name, $isPrimary]);
                        }
                    }
                }
            }
            
            $pdo->commit();
            header('Location: ' . BASE_URL . '/seller/products.php?added=1');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Erreur: " . $e->getMessage();
        }
    }
}

$pageTitle = 'Ajouter un produit';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <nav class="text-sm text-gray-500 mb-2">
            <a href="<?= BASE_URL ?>/dashboard/seller.php" class="hover:text-blue-600">Dashboard</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <a href="<?= BASE_URL ?>/seller/products.php" class="hover:text-blue-600">Produits</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-gray-800 dark:text-white font-medium">Ajouter</span>
        </nav>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
            <i class="fas fa-plus-circle text-green-600"></i> Ajouter un produit
        </h1>
    </div>

    <?php if (count($errors) > 0): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6">
            <ul class="list-disc list-inside text-sm space-y-1">
                <?php foreach ($errors as $error): ?><li><?= clean($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
        
        <!-- Section 1 : Informations de base -->
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 text-white">
            <h2 class="text-xl font-bold flex items-center gap-2"><i class="fas fa-info-circle"></i> Informations de base</h2>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Nom du produit *</label>
                <input type="text" name="name" value="<?= clean($_POST['name'] ?? '') ?>" required maxlength="255"
                       class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white" placeholder="Ex: Smartphone Samsung Galaxy A14">
            </div>
            
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Catégorie *</label>
                    <select name="category_id" required class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= clean($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Description courte</label>
                    <input type="text" name="short_description" value="<?= clean($_POST['short_description'] ?? '') ?>" maxlength="500"
                           class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white" placeholder="Résumé en 1 phrase">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Description complète *</label>
                <textarea name="description" rows="5" required
                          class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white"><?= clean($_POST['description'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Section 2 : Prix et stock -->
        <div class="bg-gray-50 dark:bg-gray-800/50 px-6 py-4 border-t dark:border-gray-700">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2"><i class="fas fa-tag text-green-600"></i> Prix et stock</h2>
        </div>
        <div class="p-6">
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Prix (FBu) *</label>
                    <input type="number" name="price" value="<?= clean($_POST['price'] ?? '') ?>" required min="0" step="0.01"
                           class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white" placeholder="250000">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Ancien prix (FBu)</label>
                    <input type="number" name="old_price" value="<?= clean($_POST['old_price'] ?? '') ?>" min="0" step="0.01"
                           class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white" placeholder="300000">
                    <p class="text-xs text-gray-500 mt-1">Pour afficher une réduction</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Stock *</label>
                    <input type="number" name="stock" value="<?= clean($_POST['stock'] ?? '10') ?>" required min="0"
                           class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white" placeholder="10">
                </div>
            </div>
        </div>

        <!-- Section 3 : Localisation -->
        <div class="bg-gray-50 dark:bg-gray-800/50 px-6 py-4 border-t dark:border-gray-700">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2"><i class="fas fa-map-marker-alt text-green-600"></i> Localisation</h2>
        </div>
        <div class="p-6">
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Province *</label>
                    <select name="province" required class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($provinces as $prov): ?>
                            <option value="<?= clean($prov['name']) ?>" <?= ($_POST['province'] ?? '') == $prov['name'] ? 'selected' : '' ?>><?= clean($prov['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Ville *</label>
                    <input type="text" name="city" value="<?= clean($_POST['city'] ?? '') ?>" required
                           class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white" placeholder="Bujumbura">
                </div>
            </div>
        </div>

        <!-- Section 4 : Images -->
        <div class="bg-gray-50 dark:bg-gray-800/50 px-6 py-4 border-t dark:border-gray-700">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2"><i class="fas fa-images text-green-600"></i> Images du produit</h2>
        </div>
        <div class="p-6">
            <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-8 text-center hover:border-green-500 transition cursor-pointer" onclick="document.getElementById('imagesInput').click()">
                <i class="fas fa-cloud-upload-alt text-5xl text-gray-400 mb-3"></i>
                <p class="text-gray-600 dark:text-gray-300 font-medium">Cliquez pour télécharger des images</p>
                <p class="text-xs text-gray-500 mt-1">JPG, PNG, WEBP, GIF (max 5MB chacune) • Plusieurs images autorisées</p>
                <input type="file" name="images[]" id="imagesInput" accept="image/*" multiple class="hidden" required>
                <div id="imagePreview" class="mt-4 grid grid-cols-3 md:grid-cols-5 gap-2"></div>
            </div>
        </div>

        <!-- Section 5 : Options -->
        <div class="bg-gray-50 dark:bg-gray-800/50 px-6 py-4 border-t dark:border-gray-700">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2"><i class="fas fa-cog text-green-600"></i> Options</h2>
        </div>
        <div class="p-6">
            <div class="space-y-3">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="is_featured" class="w-5 h-5 text-green-600 rounded">
                    <span class="text-sm text-gray-700 dark:text-gray-200"><strong>Mettre en vedette</strong> - Apparaître sur la page d'accueil</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="is_new" checked class="w-5 h-5 text-green-600 rounded">
                    <span class="text-sm text-gray-700 dark:text-gray-200"><strong>Marquer comme nouveau</strong> - Badge "Nouveau" sur le produit</span>
                </label>
            </div>
        </div>

        <!-- Boutons -->
        <div class="flex flex-col sm:flex-row gap-3 p-6 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            <button type="submit" class="flex-1 bg-green-600 text-white py-3.5 rounded-lg font-semibold hover:bg-green-700 transition shadow-lg flex items-center justify-center gap-2">
                <i class="fas fa-save"></i> Enregistrer le produit
            </button>
            <a href="<?= BASE_URL ?>/seller/products.php" class="px-6 py-3.5 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white rounded-lg font-semibold hover:bg-gray-300 transition text-center">
                Annuler
            </a>
        </div>
    </form>
</div>

<script>
document.getElementById('imagesInput').addEventListener('change', function(e) {
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = '';
    
    Array.from(e.target.files).forEach(file => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                const img = document.createElement('img');
                img.src = ev.target.result;
                img.className = 'w-full h-24 object-cover rounded-lg border-2 border-gray-200';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>