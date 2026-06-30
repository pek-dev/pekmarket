<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: ' . BASE_URL); exit; }

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    try {
        if ($action === 'activate') {
            $sellerId = intval($_POST['seller_id'] ?? 0); $days = intval($_POST['days'] ?? 30); $amount = floatval($_POST['amount'] ?? 0); $priority = intval($_POST['priority'] ?? 1);
            $pdo->prepare("DELETE FROM sponsored_shops WHERE seller_id = ? AND status = 'active'")->execute([$sellerId]);
            $pdo->prepare("INSERT INTO sponsored_shops (seller_id, status, end_date, amount_paid, position_priority, is_highlighted) VALUES (?, 'active', DATE_ADD(NOW(), INTERVAL ? DAY), ?, ?, 1)")->execute([$sellerId, $days, $amount, $priority]);
            $message = "Boutique sponsorisée activée.";
        } elseif ($action === 'cancel') { $pdo->prepare("UPDATE sponsored_shops SET status = 'cancelled' WHERE id = ?")->execute([$id]); $message = "Sponsorisation annulée."; }
        elseif ($action === 'extend') { $days = intval($_POST['days'] ?? 30); $pdo->prepare("UPDATE sponsored_shops SET end_date = DATE_ADD(end_date, INTERVAL ? DAY), status = 'active' WHERE id = ?")->execute([$days, $id]); $message = "Prolongée de $days jours."; }
    } catch (Exception $e) { $message = "Erreur: " . $e->getMessage(); }
}

$sponsoredShops = []; $sellers = [];
try { $sponsoredShops = $pdo->query("SELECT ss.*, u.first_name, u.last_name, u.email, u.city, u.province, COUNT(p.id) as products_count FROM sponsored_shops ss JOIN users u ON ss.seller_id = u.id LEFT JOIN products p ON u.id = p.seller_id AND p.is_active = 1 GROUP BY ss.id ORDER BY ss.position_priority ASC, ss.end_date DESC")->fetchAll(); } catch (Exception $e) {}
try { $sellers = $pdo->query("SELECT id, first_name, last_name, email FROM users WHERE role = 'seller' AND is_active = 1 ORDER BY first_name ASC")->fetchAll(); } catch (Exception $e) {}

$pageTitle = 'Boutiques Sponsorisées';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <nav class="text-sm text-gray-500 mb-2"><a href="<?= BASE_URL ?>/dashboard/admin.php" class="hover:text-blue-600">Admin</a> <i class="fas fa-chevron-right text-xs mx-2"></i> Boutiques Sponsor</nav>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white"><i class="fas fa-store text-pink-500 mr-2"></i>Boutiques Sponsorisées</h1>
    </div>

    <?php if ($message): ?><div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6"><?= clean($message) ?></div><?php endif; ?>

    <div class="grid lg:grid-cols-3 gap-8">
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-700 sticky top-4">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4"><i class="fas fa-plus-circle text-pink-500 mr-2"></i>Ajouter un sponsor</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="activate">
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Vendeur *</label><select name="seller_id" required class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white"><option value="">-- Sélectionner --</option><?php foreach ($sellers as $seller): ?><option value="<?= $seller['id'] ?>"><?= clean($seller['first_name'] . ' ' . $seller['last_name']) ?></option><?php endforeach; ?></select></div>
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Durée (jours) *</label><input type="number" name="days" value="30" min="1" required class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white"></div>
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Montant payé (FBu) *</label><input type="number" name="amount" value="50000" min="0" required class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white"></div>
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Priorité (1 = plus haute)</label><input type="number" name="priority" value="1" min="1" class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white"></div>
                    <button type="submit" class="w-full bg-pink-600 text-white py-3 rounded-lg hover:bg-pink-700 font-semibold"><i class="fas fa-rocket mr-2"></i>Activer le sponsor</button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b dark:border-gray-700"><h2 class="font-bold text-gray-800 dark:text-white"><?= count($sponsoredShops) ?> boutiques sponsorisées</h2></div>
                <div class="divide-y dark:divide-gray-700">
                    <?php foreach ($sponsoredShops as $shop): 
                        $daysLeft = max(0, ceil((strtotime($shop['end_date']) - time()) / 86400));
                        $isExpired = $shop['status'] !== 'active' || $daysLeft <= 0;
                    ?>
                        <div class="p-4 flex items-center gap-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 <?= $isExpired ? 'opacity-60' : '' ?>">
                            <div class="w-14 h-14 bg-gradient-to-br from-pink-500 to-purple-600 rounded-xl flex items-center justify-center text-white font-bold text-xl"><?= strtoupper(substr($shop['first_name'], 0, 1) . substr($shop['last_name'], 0, 1)) ?></div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2"><h3 class="font-semibold text-gray-800 dark:text-white"><?= clean($shop['first_name'] . ' ' . $shop['last_name']) ?></h3><?php if (!$isExpired): ?><span class="bg-pink-100 text-pink-800 text-xs px-2 py-0.5 rounded font-semibold"><i class="fas fa-crown"></i> Sponsor #<?= $shop['position_priority'] ?></span><?php endif; ?></div>
                                <p class="text-xs text-gray-500"><?= clean($shop['city'] ?? $shop['province']) ?> • <?= $shop['products_count'] ?> produits</p>
                                <p class="text-xs text-gray-500 mt-1">Expire: <?= date('d/m/Y', strtotime($shop['end_date'])) ?> <span class="font-semibold <?= $daysLeft <= 7 ? 'text-red-500' : 'text-green-600' ?>">(<?= $daysLeft ?> jours)</span></p>
                            </div>
                            <div class="text-right"><p class="font-bold text-blue-600"><?= formatPrice($shop['amount_paid']) ?></p><span class="text-xs px-2 py-1 rounded-full <?= $isExpired ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>"><?= ucfirst($shop['status']) ?></span></div>
                            <div class="flex gap-1">
                                <?php if ($isExpired): ?>
                                    <form method="POST" class="inline flex items-center gap-1"><input type="hidden" name="id" value="<?= $shop['id'] ?>"><input type="hidden" name="seller_id" value="<?= $shop['seller_id'] ?>"><input type="hidden" name="action" value="activate"><input type="hidden" name="amount" value="<?= $shop['amount_paid'] ?>"><input type="hidden" name="priority" value="<?= $shop['position_priority'] ?>"><input type="number" name="days" value="30" min="1" class="w-14 px-2 py-1 text-xs border rounded dark:bg-gray-700"><button class="px-2 py-1 bg-green-100 text-green-600 rounded-lg hover:bg-green-200 text-xs font-semibold">Renouveler</button></form>
                                <?php else: ?>
                                    <form method="POST" class="inline flex items-center gap-1"><input type="hidden" name="id" value="<?= $shop['id'] ?>"><input type="hidden" name="action" value="extend"><input type="number" name="days" value="30" min="1" class="w-14 px-2 py-1 text-xs border rounded dark:bg-gray-700"><button class="px-2 py-1 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 text-xs font-semibold">+Jours</button></form>
                                    <form method="POST" class="inline"><input type="hidden" name="id" value="<?= $shop['id'] ?>"><input type="hidden" name="action" value="cancel"><button class="w-8 h-8 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 flex items-center justify-center"><i class="fas fa-times text-xs"></i></button></form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($sponsoredShops) === 0): ?><div class="p-12 text-center text-gray-500"><i class="fas fa-store text-4xl mb-4 text-gray-300"></i><p>Aucune boutique sponsorisée</p></div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>