<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'seller') { header('Location: ' . BASE_URL); exit; }

$sellerId = $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $title = clean(trim($_POST['title'] ?? ''));
        $description = clean(trim($_POST['description'] ?? ''));
        $discountType = $_POST['discount_type'] ?? 'percentage';
        $discountValue = floatval($_POST['discount_value'] ?? 0);
        $applyTo = $_POST['apply_to'] ?? 'all';
        $targetId = intval($_POST['target_id'] ?? 0) ?: null;
        $startDate = $_POST['start_date'] ?? date('Y-m-d H:i:s');
        $endDate = $_POST['end_date'] ?? date('Y-m-d H:i:s', strtotime('+7 days'));
        
        if (empty($title)) $message = "❌ Le titre est requis";
        elseif ($discountValue <= 0) $message = "❌ Valeur invalide";
        else {
            try {
                $pdo->prepare("
                    INSERT INTO seller_promotions (seller_id, title, description, discount_type, discount_value, apply_to, target_id, start_date, end_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([$sellerId, $title, $description, $discountType, $discountValue, $applyTo, $targetId, $startDate, $endDate]);
                $message = "✅ Promotion créée";
            } catch (Exception $e) { $message = "❌ Erreur: " . $e->getMessage(); }
        }
    }
    elseif ($action === 'toggle') {
        try {
            $pdo->prepare("UPDATE seller_promotions SET is_active = NOT is_active WHERE id = ? AND seller_id = ?")
                ->execute([intval($_POST['id'] ?? 0), $sellerId]);
            $message = "✅ Statut modifié";
        } catch (Exception $e) { $message = "❌ Erreur: " . $e->getMessage(); }
    }
    elseif ($action === 'delete') {
        try {
            $pdo->prepare("DELETE FROM seller_promotions WHERE id = ? AND seller_id = ?")->execute([intval($_POST['id'] ?? 0), $sellerId]);
            $message = "✅ Promotion supprimée";
        } catch (Exception $e) { $message = "❌ Erreur: " . $e->getMessage(); }
    }
}

$promotions = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM seller_promotions WHERE seller_id = ? ORDER BY created_at DESC");
    $stmt->execute([$sellerId]);
    $promotions = $stmt->fetchAll();
} catch (Exception $e) {}

$products = [];
$categories = [];
try {
    $products = $pdo->prepare("SELECT id, name FROM products WHERE seller_id = ? AND is_active = 1 ORDER BY name");
    $products->execute([$sellerId]);
    $products = $products->fetchAll();
    
    $categories = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name")->fetchAll();
} catch (Exception $e) {}

$pageTitle = 'Promotions';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
            <i class="fas fa-gift text-orange-600"></i> Promotions
        </h1>
        <p class="text-gray-500 mt-1">Créez des offres spéciales pour attirer plus de clients</p>
    </div>

    <?php if ($message): ?>
        <div class="bg-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-50 border border-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-200 text-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-700 px-4 py-3 rounded-xl mb-6"><?= clean($message) ?></div>
    <?php endif; ?>

    <!-- Formulaire -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-700 mb-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">➕ Créer une promotion</h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Titre *</label>
                    <input type="text" name="title" required class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white" placeholder="Ex: Soldes d'été -30%">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Description</label>
                    <input type="text" name="description" class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white" placeholder="Offre limitée...">
                </div>
            </div>
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Type</label>
                    <select name="discount_type" class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                        <option value="percentage">Pourcentage (%)</option>
                        <option value="fixed">Montant fixe (FBu)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Valeur *</label>
                    <input type="number" name="discount_value" required min="0" step="0.01" class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white" value="10">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Appliquer à</label>
                    <select name="apply_to" id="applyTo" onchange="toggleTarget()" class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                        <option value="all">Tous les produits</option>
                        <option value="category">Une catégorie</option>
                        <option value="product">Un produit spécifique</option>
                    </select>
                </div>
            </div>
            <div id="targetContainer" class="hidden">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Sélectionner</label>
                <select name="target_id" id="targetSelect" class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white"></select>
            </div>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Date début *</label>
                    <input type="datetime-local" name="start_date" value="<?= date('Y-m-d\TH:i') ?>" required class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Date fin *</label>
                    <input type="datetime-local" name="end_date" value="<?= date('Y-m-d\TH:i', strtotime('+7 days')) ?>" required class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                </div>
            </div>
            <button type="submit" class="bg-orange-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-orange-700">
                <i class="fas fa-gift mr-2"></i>Créer la promotion
            </button>
        </form>
    </div>

    <!-- Liste -->
    <div class="space-y-3">
        <?php if (count($promotions) > 0): ?>
            <?php foreach ($promotions as $p): 
                $isActive = $p['is_active'] && strtotime($p['end_date']) > time();
            ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex items-center gap-4">
                    <div class="w-14 h-14 bg-gradient-to-br from-orange-500 to-red-500 rounded-xl flex items-center justify-center text-white flex-shrink-0">
                        <i class="fas fa-gift text-2xl"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="font-bold text-gray-800 dark:text-white"><?= clean($p['title']) ?></h3>
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-800">
                                <?= $p['discount_type'] == 'percentage' ? '-' . $p['discount_value'] . '%' : '-' . formatPrice($p['discount_value']) ?>
                            </span>
                            <?php if ($isActive): ?>
                                <span class="text-xs text-green-600 font-semibold">● Active</span>
                            <?php else: ?>
                                <span class="text-xs text-gray-500 font-semibold">● Inactive</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($p['description']): ?>
                            <p class="text-sm text-gray-500"><?= clean($p['description']) ?></p>
                        <?php endif; ?>
                        <p class="text-xs text-gray-400 mt-1">
                            Du <?= date('d/m/Y', strtotime($p['start_date'])) ?> au <?= date('d/m/Y', strtotime($p['end_date'])) ?>
                            • Appliqué à: <strong><?= ucfirst($p['apply_to']) ?></strong>
                        </p>
                    </div>
                    <div class="flex gap-1">
                        <form method="POST"><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="toggle">
                            <button class="w-8 h-8 bg-<?= $p['is_active'] ? 'yellow' : 'green' ?>-100 text-<?= $p['is_active'] ? 'yellow' : 'green' ?>-600 rounded-lg hover:opacity-80 flex items-center justify-center">
                                <i class="fas fa-<?= $p['is_active'] ? 'pause' : 'play' ?> text-xs"></i>
                            </button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Supprimer ?');"><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="delete">
                            <button class="w-8 h-8 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 flex items-center justify-center">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-12 text-center">
                <i class="fas fa-gift text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">Aucune promotion créée</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const products = <?= json_encode($products) ?>;
const categories = <?= json_encode($categories) ?>;

function toggleTarget() {
    const applyTo = document.getElementById('applyTo').value;
    const container = document.getElementById('targetContainer');
    const select = document.getElementById('targetSelect');
    
    if (applyTo === 'all') {
        container.classList.add('hidden');
    } else {
        container.classList.remove('hidden');
        select.innerHTML = '<option value="">-- Sélectionner --</option>';
        const items = applyTo === 'category' ? categories : products;
        items.forEach(item => {
            select.innerHTML += `<option value="${item.id}">${item.name}</option>`;
        });
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>