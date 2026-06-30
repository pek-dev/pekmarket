<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'seller') { header('Location: ' . BASE_URL); exit; }

$sellerId = $_SESSION['user_id'];
$message = '';

// Récupérer la boutique
$shop = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM shops WHERE seller_id = ?");
    $stmt->execute([$sellerId]);
    $shop = $stmt->fetch();
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean(trim($_POST['name'] ?? ''));
    $slug = clean(trim($_POST['slug'] ?? ''));
    $description = clean(trim($_POST['description'] ?? ''));
    $phone = clean(trim($_POST['phone'] ?? ''));
    $email = clean(trim($_POST['email'] ?? ''));
    $address = clean(trim($_POST['address'] ?? ''));
    $province = clean(trim($_POST['province'] ?? ''));
    $city = clean(trim($_POST['city'] ?? ''));
    $facebook = clean(trim($_POST['facebook'] ?? ''));
    $instagram = clean(trim($_POST['instagram'] ?? ''));
    $whatsapp = clean(trim($_POST['whatsapp'] ?? ''));
    $website = clean(trim($_POST['website'] ?? ''));
    
    if (empty($name)) $message = "❌ Le nom est requis";
    elseif (empty($slug)) $message = "❌ Le slug est requis";
    else {
        try {
            if (!$shop) {
                $pdo->prepare("
                    INSERT INTO shops (seller_id, name, slug, description, phone, email, address, province, city, facebook, instagram, whatsapp, website)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([$sellerId, $name, $slug, $description, $phone, $email, $address, $province, $city, $facebook, $instagram, $whatsapp, $website]);
            } else {
                $pdo->prepare("
                    UPDATE shops SET name=?, slug=?, description=?, phone=?, email=?, address=?, province=?, city=?, facebook=?, instagram=?, whatsapp=?, website=?
                    WHERE seller_id = ?
                ")->execute([$name, $slug, $description, $phone, $email, $address, $province, $city, $facebook, $instagram, $whatsapp, $website, $sellerId]);
            }
            $message = "✅ Boutique mise à jour";
            
            $stmt = $pdo->prepare("SELECT * FROM shops WHERE seller_id = ?");
            $stmt->execute([$sellerId]);
            $shop = $stmt->fetch();
        } catch (Exception $e) { $message = "❌ Erreur: " . $e->getMessage(); }
    }
}

$provinces = [];
try { $provinces = $pdo->query("SELECT name FROM provinces ORDER BY name")->fetchAll(); } catch (Exception $e) {}

$pageTitle = 'Paramètres de la boutique';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
            <i class="fas fa-store text-purple-600"></i> Paramètres de ma boutique
        </h1>
        <p class="text-gray-500 mt-1">Personnalisez votre boutique pour attirer plus de clients</p>
    </div>

    <?php if ($message): ?>
        <div class="bg-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-50 border border-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-200 text-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-700 px-4 py-3 rounded-xl mb-6"><?= clean($message) ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <!-- Infos générales -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <i class="fas fa-info-circle text-blue-600"></i> Informations générales
            </h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Nom de la boutique *</label>
                    <input type="text" name="name" value="<?= clean($shop['name'] ?? '') ?>" required 
                           class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Slug (URL) *</label>
                    <input type="text" name="slug" value="<?= clean($shop['slug'] ?? '') ?>" required pattern="[a-z0-9\-]+"
                           class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white" placeholder="ma-boutique">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Description</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white"><?= clean($shop['description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Contact -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <i class="fas fa-phone text-green-600"></i> Contact
            </h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Téléphone</label>
                    <input type="tel" name="phone" value="<?= clean($shop['phone'] ?? '') ?>" 
                           class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Email</label>
                    <input type="email" name="email" value="<?= clean($shop['email'] ?? '') ?>" 
                           class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                </div>
            </div>
        </div>

        <!-- Adresse -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <i class="fas fa-map-marker-alt text-orange-600"></i> Adresse
            </h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Province</label>
                    <select name="province" class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($provinces as $p): ?>
                            <option value="<?= clean($p['name']) ?>" <?= ($shop['province'] ?? '') == $p['name'] ? 'selected' : '' ?>><?= clean($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Ville</label>
                    <input type="text" name="city" value="<?= clean($shop['city'] ?? '') ?>" 
                           class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Adresse complète</label>
                    <textarea name="address" rows="2" class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white"><?= clean($shop['address'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Réseaux sociaux -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <i class="fas fa-share-alt text-pink-600"></i> Réseaux sociaux
            </h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2"><i class="fab fa-facebook mr-1"></i>Facebook</label>
                    <input type="url" name="facebook" value="<?= clean($shop['facebook'] ?? '') ?>" class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white" placeholder="https://facebook.com/...">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2"><i class="fab fa-instagram mr-1"></i>Instagram</label>
                    <input type="url" name="instagram" value="<?= clean($shop['instagram'] ?? '') ?>" class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white" placeholder="https://instagram.com/...">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2"><i class="fab fa-whatsapp mr-1"></i>WhatsApp</label>
                    <input type="tel" name="whatsapp" value="<?= clean($shop['whatsapp'] ?? '') ?>" class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white" placeholder="+257 ...">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2"><i class="fas fa-globe mr-1"></i>Site web</label>
                    <input type="url" name="website" value="<?= clean($shop['website'] ?? '') ?>" class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white" placeholder="https://...">
                </div>
            </div>
        </div>

        <button type="submit" class="bg-purple-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-purple-700 shadow-lg">
            <i class="fas fa-save mr-2"></i>Enregistrer les modifications
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>