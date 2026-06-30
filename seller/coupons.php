<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'seller') { header('Location: ' . BASE_URL); exit; }

$sellerId = $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $code = strtoupper(clean(trim($_POST['code'] ?? '')));
        $description = clean(trim($_POST['description'] ?? ''));
        $type = $_POST['type'] ?? 'percentage';
        $value = floatval($_POST['value'] ?? 0);
        $minOrder = floatval($_POST['min_order_amount'] ?? 0);
        $maxDiscount = !empty($_POST['max_discount']) ? floatval($_POST['max_discount']) : null;
        $usageLimit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
        $startDate = $_POST['start_date'] ?? date('Y-m-d H:i:s');
        $endDate = $_POST['end_date'] ?? date('Y-m-d H:i:s', strtotime('+30 days'));
        
        if (empty($code)) $message = "❌ Le code est requis";
        elseif ($value <= 0) $message = "❌ La valeur doit être positive";
        else {
            try {
                if ($action === 'create') {
                    $pdo->prepare("
                        INSERT INTO coupons (seller_id, code, description, type, value, min_order_amount, max_discount, usage_limit, start_date, end_date)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ")->execute([$sellerId, $code, $description, $type, $value, $minOrder, $maxDiscount, $usageLimit, $startDate, $endDate]);
                    $message = "✅ Coupon créé";
                } else {
                    $pdo->prepare("
                        UPDATE coupons SET code=?, description=?, type=?, value=?, min_order_amount=?, max_discount=?, usage_limit=?, start_date=?, end_date=?
                        WHERE id=? AND seller_id=?
                    ")->execute([$code, $description, $type, $value, $minOrder, $maxDiscount, $usageLimit, $startDate, $endDate, $id, $sellerId]);
                    $message = "✅ Coupon mis à jour";
                }
            } catch (Exception $e) { $message = "❌ Erreur: " . $e->getMessage(); }
        }
    }
    elseif ($action === 'delete') {
        try {
            $pdo->prepare("DELETE FROM coupons WHERE id = ? AND seller_id = ?")->execute([intval($_POST['id'] ?? 0), $sellerId]);
            $message = "✅ Coupon supprimé";
        } catch (Exception $e) { $message = "❌ Erreur: " . $e->getMessage(); }
    }
    elseif ($action === 'toggle') {
        try {
            $pdo->prepare("UPDATE coupons SET is_active = NOT is_active WHERE id = ? AND seller_id = ?")->execute([intval($_POST['id'] ?? 0), $sellerId]);
            $message = "✅ Statut modifié";
        } catch (Exception $e) { $message = "❌ Erreur: " . $e->getMessage(); }
    }
}

$coupons = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE seller_id = ? ORDER BY created_at DESC");
    $stmt->execute([$sellerId]);
    $coupons = $stmt->fetchAll();
} catch (Exception $e) {}

$editCoupon = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ? AND seller_id = ?");
    $stmt->execute([intval($_GET['edit']), $sellerId]);
    $editCoupon = $stmt->fetch();
}

$pageTitle = 'Coupons';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
            <i class="fas fa-ticket-alt text-purple-600"></i> Coupons de réduction
        </h1>
        <p class="text-gray-500 mt-1">Créez des codes promo pour fidéliser vos clients</p>
    </div>

    <?php if ($message): ?>
        <div class="bg-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-50 border border-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-200 text-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-700 px-4 py-3 rounded-xl mb-6"><?= clean($message) ?></div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Formulaire -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-700 sticky top-4">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">
                    <?= $editCoupon ? '✏️ Modifier' : '➕ Créer' ?> un coupon
                </h2>
                <form method="POST" class="space-y-3">
                    <input type="hidden" name="action" value="<?= $editCoupon ? 'edit' : 'create' ?>">
                    <?php if ($editCoupon): ?><input type="hidden" name="id" value="<?= $editCoupon['id'] ?>"><?php endif; ?>
                    
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1">Code *</label>
                        <input type="text" name="code" value="<?= clean($editCoupon['code'] ?? '') ?>" required 
                               class="w-full px-3 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white uppercase" 
                               placeholder="EX: SOLDE20">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1">Description</label>
                        <textarea name="description" rows="2" class="w-full px-3 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white"><?= clean($editCoupon['description'] ?? '') ?></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1">Type</label>
                            <select name="type" class="w-full px-3 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                                <option value="percentage" <?= ($editCoupon['type'] ?? '') == 'percentage' ? 'selected' : '' ?>>Pourcentage (%)</option>
                                <option value="fixed" <?= ($editCoupon['type'] ?? '') == 'fixed' ? 'selected' : '' ?>>Montant fixe (FBu)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1">Valeur *</label>
                            <input type="number" name="value" value="<?= $editCoupon['value'] ?? '10' ?>" required min="0" step="0.01"
                                   class="w-full px-3 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1">Commande minimum (FBu)</label>
                        <input type="number" name="min_order_amount" value="<?= $editCoupon['min_order_amount'] ?? 0 ?>" min="0"
                               class="w-full px-3 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1">Début</label>
                            <input type="datetime-local" name="start_date" value="<?= $editCoupon['start_date'] ?? date('Y-m-d\TH:i') ?>" required
                                   class="w-full px-2 py-2 text-xs border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1">Fin</label>
                            <input type="datetime-local" name="end_date" value="<?= $editCoupon['end_date'] ?? date('Y-m-d\TH:i', strtotime('+30 days')) ?>" required
                                   class="w-full px-2 py-2 text-xs border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1">Limite utilisation</label>
                            <input type="number" name="usage_limit" value="<?= $editCoupon['usage_limit'] ?? '' ?>" min="1"
                                   class="w-full px-3 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white" placeholder="Illimité">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1">Réduction max</label>
                            <input type="number" name="max_discount" value="<?= $editCoupon['max_discount'] ?? '' ?>" min="0"
                                   class="w-full px-3 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white" placeholder="Illimité">
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-purple-600 text-white py-2.5 rounded-lg font-semibold hover:bg-purple-700">
                        <?= $editCoupon ? 'Mettre à jour' : 'Créer le coupon' ?>
                    </button>
                    <?php if ($editCoupon): ?>
                        <a href="<?= BASE_URL ?>/seller/coupons.php" class="block text-center text-sm text-gray-500 hover:text-gray-700 mt-2">Annuler</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Liste -->
        <div class="lg:col-span-2 space-y-3">
            <?php if (count($coupons) > 0): ?>
                <?php foreach ($coupons as $c): 
                    $isActive = $c['is_active'] && strtotime($c['end_date']) > time();
                    $isExpired = strtotime($c['end_date']) < time();
                    $usagePercent = $c['usage_limit'] ? ($c['usage_count'] / $c['usage_limit']) * 100 : 0;
                ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden <?= !$isActive ? 'opacity-60' : '' ?>">
                        <div class="p-4 flex items-center gap-4">
                            <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl flex items-center justify-center text-white flex-shrink-0">
                                <i class="fas fa-ticket-alt text-2xl"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <code class="bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300 px-2 py-1 rounded font-bold text-sm"><?= clean($c['code']) ?></code>
                                    <?php if ($isExpired): ?>
                                        <span class="text-xs text-red-600 font-semibold">Expiré</span>
                                    <?php elseif (!$c['is_active']): ?>
                                        <span class="text-xs text-gray-500 font-semibold">Désactivé</span>
                                    <?php else: ?>
                                        <span class="text-xs text-green-600 font-semibold">Actif</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-sm font-bold text-gray-800 dark:text-white">
                                    <?= $c['type'] == 'percentage' ? $c['value'] . '%' : formatPrice($c['value']) ?> de réduction
                                </p>
                                <?php if ($c['description']): ?>
                                    <p class="text-xs text-gray-500 mt-1"><?= clean($c['description']) ?></p>
                                <?php endif; ?>
                                <div class="flex items-center gap-3 mt-2 text-xs text-gray-500">
                                    <span><i class="fas fa-users mr-1"></i><?= $c['usage_count'] ?><?= $c['usage_limit'] ? '/' . $c['usage_limit'] : '' ?> utilisations</span>
                                    <span><i class="fas fa-calendar mr-1"></i>Jusqu'au <?= date('d/m/Y', strtotime($c['end_date'])) ?></span>
                                </div>
                                <?php if ($c['usage_limit']): ?>
                                    <div class="mt-2 h-1 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                        <div class="h-full bg-purple-500" style="width: <?= min(100, $usagePercent) ?>%"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex flex-col gap-1">
                                <a href="?edit=<?= $c['id'] ?>" class="w-8 h-8 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 flex items-center justify-center">
                                    <i class="fas fa-edit text-xs"></i>
                                </a>
                                <form method="POST"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden" name="action" value="toggle">
                                    <button class="w-8 h-8 bg-<?= $c['is_active'] ? 'yellow' : 'green' ?>-100 text-<?= $c['is_active'] ? 'yellow' : 'green' ?>-600 rounded-lg hover:opacity-80 flex items-center justify-center">
                                        <i class="fas fa-<?= $c['is_active'] ? 'pause' : 'play' ?> text-xs"></i>
                                    </button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Supprimer ?');"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden" name="action" value="delete">
                                    <button class="w-8 h-8 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 flex items-center justify-center">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-12 text-center">
                    <i class="fas fa-ticket-alt text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">Aucun coupon créé</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>