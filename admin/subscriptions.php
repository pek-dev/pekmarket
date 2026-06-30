<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: ' . BASE_URL); exit; }

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'activate') { $pdo->prepare("UPDATE subscriptions SET status = 'active', payment_status = 'paid' WHERE id = ?")->execute([intval($_POST['sub_id'] ?? 0)]); $message = "Abonnement activé."; }
        elseif ($action === 'cancel') { $pdo->prepare("UPDATE subscriptions SET status = 'cancelled' WHERE id = ?")->execute([intval($_POST['sub_id'] ?? 0)]); $message = "Abonnement annulé."; }
        elseif ($action === 'extend') { $days = intval($_POST['days'] ?? 30); $pdo->prepare("UPDATE subscriptions SET end_date = DATE_ADD(end_date, INTERVAL ? DAY), status = 'active' WHERE id = ?")->execute([$days, intval($_POST['sub_id'] ?? 0)]); $message = "Prolongé de $days jours."; }
        elseif ($action === 'add_plan' || $action === 'edit_plan') {
            $planId = intval($_POST['plan_id'] ?? 0);
            $name = clean($_POST['plan_name'] ?? ''); $slug = clean($_POST['plan_slug'] ?? '');
            $price = floatval($_POST['plan_price'] ?? 0); $duration = intval($_POST['plan_duration'] ?? 30);
            $maxProducts = intval($_POST['plan_max_products'] ?? 10); $featured = intval($_POST['plan_featured'] ?? 0);
            $isBoosted = isset($_POST['plan_boosted']) ? 1 : 0; $badge = clean($_POST['plan_badge'] ?? '');
            $badgeColor = clean($_POST['plan_badge_color'] ?? 'blue'); $priority = intval($_POST['plan_priority'] ?? 0);
            
            if ($action === 'add_plan') { $pdo->prepare("INSERT INTO subscription_plans (name, slug, price, duration_days, max_products, featured_products, is_boosted, badge_text, badge_color, priority_sort) VALUES (?,?,?,?,?,?,?,?,?,?)")->execute([$name, $slug, $price, $duration, $maxProducts, $featured, $isBoosted, $badge, $badgeColor, $priority]); $message = "Plan ajouté."; }
            else { $pdo->prepare("UPDATE subscription_plans SET name=?, slug=?, price=?, duration_days=?, max_products=?, featured_products=?, is_boosted=?, badge_text=?, badge_color=?, priority_sort=? WHERE id=?")->execute([$name, $slug, $price, $duration, $maxProducts, $featured, $isBoosted, $badge, $badgeColor, $priority, $planId]); $message = "Plan mis à jour."; }
        }
        elseif ($action === 'delete_plan') { $pdo->prepare("DELETE FROM subscription_plans WHERE id = ?")->execute([intval($_POST['plan_id'] ?? 0)]); $message = "Plan supprimé."; }
    } catch (Exception $e) { $message = "Erreur: " . $e->getMessage(); }
}

$subscriptions = []; $plans = [];
try { $subscriptions = $pdo->query("SELECT s.*, u.first_name, u.last_name, u.email, sp.name as plan_name FROM subscriptions s JOIN users u ON s.user_id = u.id JOIN subscription_plans sp ON s.plan_id = sp.id ORDER BY s.created_at DESC LIMIT 100")->fetchAll(); } catch (Exception $e) {}
try { $plans = $pdo->query("SELECT * FROM subscription_plans ORDER BY priority_sort ASC")->fetchAll(); } catch (Exception $e) {}

$pageTitle = 'Gestion des Abonnements';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <nav class="text-sm text-gray-500 mb-2"><a href="<?= BASE_URL ?>/dashboard/admin.php" class="hover:text-blue-600">Admin</a> <i class="fas fa-chevron-right text-xs mx-2"></i> Abonnements</nav>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white"><i class="fas fa-crown text-yellow-500 mr-2"></i>Gestion des Abonnements</h1>
    </div>

    <?php if ($message): ?><div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6"><?= clean($message) ?></div><?php endif; ?>

    <div class="mb-8">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Plans d'abonnement</h2>
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($plans as $plan): ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border-t-4 border-<?= $plan['badge_color'] ?>-500">
                    <div class="flex justify-between items-start mb-3">
                        <h3 class="font-bold text-lg"><?= clean($plan['name']) ?></h3>
                        <?php if ($plan['badge_text']): ?><span class="bg-<?= $plan['badge_color'] ?>-100 text-<?= $plan['badge_color'] ?>-800 text-xs px-2 py-1 rounded font-semibold"><?= clean($plan['badge_text']) ?></span><?php endif; ?>
                    </div>
                    <p class="text-2xl font-bold text-blue-600 mb-2"><?= formatPrice($plan['price']) ?></p>
                    <p class="text-xs text-gray-500 mb-3"><?= $plan['duration_days'] ?> jours</p>
                    <ul class="text-xs text-gray-600 dark:text-gray-300 space-y-1 mb-4">
                        <li><i class="fas fa-check text-green-500 mr-1"></i><?= $plan['max_products'] ?> produits max</li>
                        <li><i class="fas fa-check text-green-500 mr-1"></i><?= $plan['featured_products'] ?> en vedette</li>
                        <?php if ($plan['is_boosted']): ?><li><i class="fas fa-rocket text-purple-500 mr-1"></i>Boost activé</li><?php endif; ?>
                    </ul>
                    <div class="flex gap-1">
                        <a href="?edit_plan=<?= $plan['id'] ?>" class="flex-1 bg-blue-100 text-blue-600 text-center py-1 rounded text-xs font-semibold hover:bg-blue-200">Modifier</a>
                        <form method="POST" onsubmit="return confirm('Supprimer ?');" class="flex-1"><input type="hidden" name="plan_id" value="<?= $plan['id'] ?>"><input type="hidden" name="action" value="delete_plan"><button class="w-full bg-red-100 text-red-600 py-1 rounded text-xs font-semibold hover:bg-red-200">Supprimer</button></form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="p-4 border-b dark:border-gray-700"><h2 class="font-bold text-gray-800 dark:text-white">Abonnements (<?= count($subscriptions) ?>)</h2></div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Utilisateur</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Plan</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Montant</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Période</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Paiement</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Statut</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th></tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    <?php foreach ($subscriptions as $sub): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3"><p class="font-semibold text-sm"><?= clean($sub['first_name'] . ' ' . $sub['last_name']) ?></p><p class="text-xs text-gray-500"><?= clean($sub['email']) ?></p></td>
                            <td class="px-4 py-3"><span class="font-semibold text-blue-600"><?= clean($sub['plan_name']) ?></span></td>
                            <td class="px-4 py-3 font-bold"><?= formatPrice($sub['amount_paid']) ?></td>
                            <td class="px-4 py-3 text-xs"><p><?= date('d/m/Y', strtotime($sub['start_date'])) ?></p><p class="text-gray-500">→ <?= date('d/m/Y', strtotime($sub['end_date'])) ?></p></td>
                            <td class="px-4 py-3"><span class="px-2 py-1 rounded text-xs font-semibold <?= $sub['payment_status'] == 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>"><?= ucfirst($sub['payment_status']) ?></span></td>
                            <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-semibold <?= $sub['status'] == 'active' ? 'bg-green-100 text-green-800' : ($sub['status'] == 'expired' ? 'bg-gray-100 text-gray-800' : 'bg-red-100 text-red-800') ?>"><?= ucfirst($sub['status']) ?></span></td>
                            <td class="px-4 py-3">
                                <div class="flex gap-1">
                                    <?php if ($sub['status'] !== 'active'): ?><form method="POST" class="inline"><input type="hidden" name="sub_id" value="<?= $sub['id'] ?>"><input type="hidden" name="action" value="activate"><button class="w-8 h-8 bg-green-100 text-green-600 rounded-lg hover:bg-green-200 flex items-center justify-center"><i class="fas fa-check text-xs"></i></button></form><?php endif; ?>
                                    <form method="POST" class="inline flex items-center gap-1"><input type="hidden" name="sub_id" value="<?= $sub['id'] ?>"><input type="hidden" name="action" value="extend"><input type="number" name="days" value="30" min="1" class="w-14 px-2 py-1 text-xs border rounded dark:bg-gray-700"><button class="px-2 py-1 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 text-xs font-semibold">+Jours</button></form>
                                    <form method="POST" class="inline"><input type="hidden" name="sub_id" value="<?= $sub['id'] ?>"><input type="hidden" name="action" value="cancel"><button class="w-8 h-8 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 flex items-center justify-center"><i class="fas fa-times text-xs"></i></button></form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>