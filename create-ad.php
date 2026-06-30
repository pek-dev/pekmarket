<?php
require_once __DIR__ . '/config/bootstrap.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$errors = [];

// Récupérer les produits du vendeur (si vendeur)
$myProducts = [];
if ($_SESSION['user_role'] === 'seller') {
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM products WHERE seller_id = ? AND is_active = 1 ORDER BY name ASC");
        $stmt->execute([$user_id]);
        $myProducts = $stmt->fetchAll();
    } catch (Exception $e) {}
}

// ============================================
// TRAITEMENT DU FORMULAIRE (AVANT HTML)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = clean(trim($_POST['title'] ?? ''));
    $description = clean(trim($_POST['description'] ?? ''));
    $ad_type = $_POST['ad_type'] ?? 'product';
    $target_url = clean(trim($_POST['target_url'] ?? ''));
    $product_id = intval($_POST['product_id'] ?? 0) ?: null;
    $cta_text = clean(trim($_POST['cta_text'] ?? 'En savoir plus'));
    $placement = $_POST['placement'] ?? 'feed';
    $budget_total = floatval($_POST['budget_total'] ?? 0);
    $budget_daily = floatval($_POST['budget_daily'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $image_path = '';

    // Validation
    if (empty($title)) $errors[] = "Le titre est requis.";
    if (strlen($title) > 255) $errors[] = "Le titre est trop long (max 255 caractères).";
    if (empty($start_date)) $errors[] = "La date de début est requise.";
    if (empty($end_date)) $errors[] = "La date de fin est requise.";
    if ($end_date < $start_date) $errors[] = "La date de fin doit être après la date de début.";
    if ($budget_total < 5000) $errors[] = "Le budget minimum est de 5 000 FBu.";
    
    // Upload image
    if (isset($_FILES['ad_image']) && $_FILES['ad_image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['ad_image']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $errors[] = "Format d'image non autorisé (JPG, PNG, GIF, WEBP).";
        } elseif ($_FILES['ad_image']['size'] > 5 * 1024 * 1024) {
            $errors[] = "L'image ne doit pas dépasser 5MB.";
        } else {
            $uploadDir = UPLOAD_DIR . 'ads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $filename = 'ad_' . time() . '_' . uniqid() . '.' . $ext;
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['ad_image']['tmp_name'], $filepath)) {
                $image_path = BASE_URL . '/uploads/ads/' . $filename;
            } else {
                $errors[] = "Erreur lors de l'upload de l'image.";
            }
        }
    } else {
        $errors[] = "L'image de la publicité est requise.";
    }

    // ✅ CRÉATION (AVANT HTML)
    if (count($errors) === 0) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ad_campaigns 
                (user_id, title, description, ad_type, target_url, product_id, image_path, 
                 cta_text, placement, budget_total, budget_daily, start_date, end_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $user_id, $title, $description, $ad_type, $target_url, $product_id, $image_path,
                $cta_text, $placement, $budget_total, $budget_daily, $start_date, $end_date
            ]);

            $adId = $pdo->lastInsertId();

            // Enregistrer la transaction
            $pdo->prepare("
                INSERT INTO revenue_transactions (user_id, type, reference_id, amount, status, description)
                VALUES (?, 'ad_campaign', ?, ?, 'pending', ?)
            ")->execute([$user_id, $adId, $budget_total, "Publicité: $title"]);

            // 🔴 REDIRECTION AVANT HTML
            header('Location: ' . BASE_URL . '/seller/my-ads.php?created=1');
            exit;
            
        } catch (Exception $e) {
            $errors[] = "Erreur lors de la création : " . $e->getMessage();
        }
    }
}

// ============================================
// MAINTENANT ON PEUT AFFICHER LE HTML
// ============================================
$pageTitle = 'Créer une publicité';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <nav class="text-sm text-gray-500 mb-4">
        <a href="<?= BASE_URL ?>/dashboard/seller.php" class="hover:text-blue-600">Dashboard</a>
        <i class="fas fa-chevron-right text-xs mx-2"></i>
        <span class="text-gray-800 dark:text-white font-medium">Créer une publicité</span>
    </nav>

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6 md:p-8 border border-gray-100 dark:border-gray-700">
        <div class="flex items-center gap-3 mb-8">
            <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-ad text-red-500 text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Créer une publicité</h1>
                <p class="text-sm text-gray-500">Promouvez votre produit, événement ou marque</p>
            </div>
        </div>

        <?php if (count($errors) > 0): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6">
                <ul class="list-disc list-inside text-sm space-y-1">
                    <?php foreach ($errors as $error): ?><li><?= clean($error) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            
            <!-- Type de publicité -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Type de publicité *</label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <?php
                    $types = [
                        'product' => ['icon' => 'fas fa-box', 'label' => 'Produit', 'desc' => 'Promouvoir un produit'],
                        'shop' => ['icon' => 'fas fa-store', 'label' => 'Boutique', 'desc' => 'Promouvoir votre boutique'],
                        'event' => ['icon' => 'fas fa-calendar-alt', 'label' => 'Événement', 'desc' => 'Concert, festival...'],
                        'conference' => ['icon' => 'fas fa-chalkboard-teacher', 'label' => 'Conférence', 'desc' => 'Séminaire, formation'],
                        'brand' => ['icon' => 'fas fa-tag', 'label' => 'Marque', 'desc' => 'Notoriété de marque'],
                        'custom' => ['icon' => 'fas fa-paint-brush', 'label' => 'Personnalisé', 'desc' => 'Autre type de pub']
                    ];
                    foreach ($types as $key => $t):
                    ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="ad_type" value="<?= $key ?>" <?= ($_POST['ad_type'] ?? 'product') == $key ? 'checked' : '' ?> class="peer sr-only">
                            <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:peer-checked:bg-blue-900/20 transition hover:border-gray-300">
                                <i class="<?= $t['icon'] ?> text-2xl text-blue-600 mb-2"></i>
                                <p class="font-semibold text-sm text-gray-800 dark:text-white"><?= $t['label'] ?></p>
                                <p class="text-xs text-gray-500 mt-1"><?= $t['desc'] ?></p>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Titre et Description -->
            <div class="grid md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Titre de la publicité *</label>
                    <input type="text" name="title" value="<?= clean($_POST['title'] ?? '') ?>" required maxlength="255"
                           class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                           placeholder="Ex: Grande soldes d'été -50% sur l'électronique">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Description</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                              placeholder="Décrivez votre publicité..."><?= clean($_POST['description'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Image -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Image de la publicité *</label>
                <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-8 text-center hover:border-blue-500 transition cursor-pointer"
                     onclick="document.getElementById('adImageInput').click()">
                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                    <p class="text-gray-600 dark:text-gray-300 font-medium">Cliquez pour télécharger une image</p>
                    <p class="text-xs text-gray-500 mt-1">JPG, PNG, WEBP (max 5MB) • Recommandé: 1200x628px</p>
                    <input type="file" name="ad_image" id="adImageInput" accept="image/*" class="hidden" required>
                </div>
                <img id="imagePreview" class="mt-4 rounded-xl max-h-48 hidden mx-auto">
            </div>

            <!-- Lien et Produit -->
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Lien de destination</label>
                    <input type="url" name="target_url" value="<?= clean($_POST['target_url'] ?? '') ?>"
                           class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                           placeholder="https://...">
                </div>
                <?php if (count($myProducts) > 0): ?>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Lier à un produit</label>
                    <select name="product_id" class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        <option value="">-- Aucun produit --</option>
                        <?php foreach ($myProducts as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= clean($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <!-- Placement -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Emplacement *</label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <?php
                    $placements = [
                        'hero_banner' => 'Bannière principale',
                        'feed' => 'Fil d\'actualité',
                        'between_products' => 'Entre les produits',
                        'sidebar' => 'Barre latérale',
                        'story' => 'Story (plein écran)',
                        'popup' => 'Popup'
                    ];
                    foreach ($placements as $key => $label):
                    ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="placement" value="<?= $key ?>" <?= ($_POST['placement'] ?? 'feed') == $key ? 'checked' : '' ?> class="peer sr-only">
                            <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl p-3 text-center text-sm peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:peer-checked:bg-blue-900/20 transition">
                                <?= $label ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Budget -->
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-6">
                <h3 class="font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-wallet text-green-500"></i> Budget
                </h3>
                <div class="grid md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Budget total (FBu) *</label>
                        <input type="number" name="budget_total" value="<?= $_POST['budget_total'] ?? '10000' ?>" min="5000" required
                               class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Budget journalier (FBu)</label>
                        <input type="number" name="budget_daily" value="<?= $_POST['budget_daily'] ?? '2000' ?>" min="500"
                               class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Coût par clic (FBu)</label>
                        <input type="number" value="50" min="10" readonly
                               class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg bg-gray-100 dark:bg-gray-900 text-gray-500">
                    </div>
                </div>
            </div>

            <!-- Dates -->
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Date de début *</label>
                    <input type="date" name="start_date" value="<?= $_POST['start_date'] ?? date('Y-m-d') ?>" required
                           class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Date de fin *</label>
                    <input type="date" name="end_date" value="<?= $_POST['end_date'] ?? date('Y-m-d', strtotime('+7 days')) ?>" required
                           class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                </div>
            </div>

            <!-- CTA -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Texte du bouton</label>
                <input type="text" name="cta_text" value="<?= clean($_POST['cta_text'] ?? 'En savoir plus') ?>" maxlength="50"
                       class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
            </div>

            <!-- Submit -->
            <div class="flex flex-col sm:flex-row gap-3 pt-6 border-t dark:border-gray-700">
                <button type="submit" class="flex-1 bg-blue-600 text-white py-4 rounded-xl font-bold text-lg hover:bg-blue-700 transition shadow-lg flex items-center justify-center gap-2">
                    <i class="fas fa-paper-plane"></i> Soumettre la publicité
                </button>
                <a href="<?= BASE_URL ?>/dashboard/seller.php" class="px-8 py-4 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white rounded-xl font-semibold hover:bg-gray-300 transition text-center">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('adImageInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            const preview = document.getElementById('imagePreview');
            preview.src = ev.target.result;
            preview.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>