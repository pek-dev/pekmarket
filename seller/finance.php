<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'seller') { header('Location: ' . BASE_URL); exit; }

$sellerId = $_SESSION['user_id'];
$message = '';
$messageType = 'success';

// Récupérer les infos bancaires du vendeur
$seller = null;
try {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone, bank_name, account_number, mobile_money_number, account_holder FROM users WHERE id = ?");
    $stmt->execute([$sellerId]);
    $seller = $stmt->fetch();
} catch (Exception $e) {}

// Mise à jour des infos bancaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_bank_info') {
        $bankName = clean(trim($_POST['bank_name'] ?? ''));
        $accountNumber = clean(trim($_POST['account_number'] ?? ''));
        $mobileMoney = clean(trim($_POST['mobile_money_number'] ?? ''));
        $accountHolder = clean(trim($_POST['account_holder'] ?? ''));
        
        try {
            $pdo->prepare("UPDATE users SET bank_name = ?, account_number = ?, mobile_money_number = ?, account_holder = ? WHERE id = ?")
                ->execute([$bankName, $accountNumber, $mobileMoney, $accountHolder, $sellerId]);
            $message = "✅ Informations bancaires mises à jour";
        } catch (Exception $e) {
            $message = "❌ Erreur: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Statistiques financières
$financeStats = [
    'total_revenue' => 0,
    'total_withdrawn' => 0,
    'pending_withdrawals' => 0,
    'available_balance' => 0,
    'total_orders' => 0,
    'commission_rate' => 5, // 5% de commission
    'total_commission' => 0
];

try {
    // Revenus totaux (ventes payées)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(oi.total), 0) as total
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE p.seller_id = ? AND o.payment_status = 'paid'
    ");
    $stmt->execute([$sellerId]);
    $financeStats['total_revenue'] = $stmt->fetchColumn();
    
    // Total déjà retiré
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total
        FROM withdrawals 
        WHERE seller_id = ? AND status IN ('approved', 'completed')
    ");
    $stmt->execute([$sellerId]);
    $financeStats['total_withdrawn'] = $stmt->fetchColumn();
    
    // Retraits en attente
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total
        FROM withdrawals 
        WHERE seller_id = ? AND status = 'pending'
    ");
    $stmt->execute([$sellerId]);
    $financeStats['pending_withdrawals'] = $stmt->fetchColumn();
    
    // Nombre de commandes
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT o.id)
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = ?
    ");
    $stmt->execute([$sellerId]);
    $financeStats['total_orders'] = $stmt->fetchColumn();
    
    // Commission
    $financeStats['total_commission'] = $financeStats['total_revenue'] * ($financeStats['commission_rate'] / 100);
    
    // Solde disponible
    $financeStats['available_balance'] = $financeStats['total_revenue'] 
        - $financeStats['total_commission'] 
        - $financeStats['total_withdrawn'] 
        - $financeStats['pending_withdrawals'];
    
} catch (Exception $e) {}

// Historique des retraits
$withdrawals = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM withdrawals 
        WHERE seller_id = ? 
        ORDER BY requested_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$sellerId]);
    $withdrawals = $stmt->fetchAll();
} catch (Exception $e) {}

// Transactions récentes (ventes)
$recentSales = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.order_number, o.created_at, oi.product_name, oi.quantity, oi.total,
               u.first_name, u.last_name
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        JOIN users u ON o.user_id = u.id
        WHERE p.seller_id = ? AND o.payment_status = 'paid'
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$sellerId]);
    $recentSales = $stmt->fetchAll();
} catch (Exception $e) {}

// Revenus par mois (graphique)
$monthlyRevenue = [];
try {
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(o.created_at, '%Y-%m') as month, 
               SUM(oi.total) as revenue,
               COUNT(DISTINCT o.id) as orders
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = ? 
          AND o.payment_status = 'paid'
          AND o.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    $stmt->execute([$sellerId]);
    $monthlyRevenue = $stmt->fetchAll();
} catch (Exception $e) {}

$pageTitle = 'Mes Finances';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="bg-gradient-to-r from-green-600 to-emerald-600 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <nav class="text-sm text-white/80 mb-2">
            <a href="<?= BASE_URL ?>/dashboard/seller.php" class="hover:text-white">Dashboard</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-white font-medium">Finances</span>
        </nav>
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white flex items-center gap-3">
                    <i class="fas fa-wallet"></i> Mes Finances
                </h1>
                <p class="text-white/80 mt-1">Gérez vos revenus et retraits</p>
            </div>
            <button onclick="document.getElementById('withdrawModal').classList.remove('hidden')" 
                    class="px-6 py-3 bg-white text-green-600 rounded-lg font-bold hover:bg-gray-100 transition shadow-lg flex items-center gap-2">
                <i class="fas fa-money-bill-wave"></i> Demander un retrait
            </button>
        </div>
    </div>
</section>

<section class="py-8 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <?php if ($message): ?>
            <div class="bg-<?= $messageType == 'success' ? 'green' : 'red' ?>-50 border border-<?= $messageType == 'success' ? 'green' : 'red' ?>-200 text-<?= $messageType == 'success' ? 'green' : 'red' ?>-700 px-4 py-3 rounded-xl mb-6 flex items-center justify-between">
                <span><?= clean($message) ?></span>
                <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>

        <!-- Cartes statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl p-5 text-white shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm">Solde disponible</p>
                        <p class="text-2xl font-bold mt-1"><?= formatPrice($financeStats['available_balance']) ?></p>
                    </div>
                    <i class="fas fa-wallet text-3xl text-green-200"></i>
                </div>
                <p class="text-xs text-green-100 mt-2">Disponible pour retrait</p>
            </div>
            
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-5 text-white shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm">Revenus totaux</p>
                        <p class="text-2xl font-bold mt-1"><?= formatPrice($financeStats['total_revenue']) ?></p>
                    </div>
                    <i class="fas fa-chart-line text-3xl text-blue-200"></i>
                </div>
                <p class="text-xs text-blue-100 mt-2"><?= $financeStats['total_orders'] ?> commandes</p>
            </div>
            
            <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-5 text-white shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-sm">Déjà retiré</p>
                        <p class="text-2xl font-bold mt-1"><?= formatPrice($financeStats['total_withdrawn']) ?></p>
                    </div>
                    <i class="fas fa-hand-holding-usd text-3xl text-orange-200"></i>
                </div>
                <p class="text-xs text-orange-100 mt-2">Total des retraits</p>
            </div>
            
            <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl p-5 text-white shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-yellow-100 text-sm">En attente</p>
                        <p class="text-2xl font-bold mt-1"><?= formatPrice($financeStats['pending_withdrawals']) ?></p>
                    </div>
                    <i class="fas fa-clock text-3xl text-yellow-200"></i>
                </div>
                <p class="text-xs text-yellow-100 mt-2">Retraits en cours</p>
            </div>
        </div>

        <!-- Graphique revenus mensuels -->
        <?php if (count($monthlyRevenue) > 0): ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 mb-6 border border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <i class="fas fa-chart-area text-blue-600"></i> Revenus des 6 derniers mois
            </h2>
            <div class="h-64 flex items-end gap-2">
                <?php 
                $maxRevenue = max(array_column($monthlyRevenue, 'revenue'));
                foreach ($monthlyRevenue as $month): 
                    $height = $maxRevenue > 0 ? ($month['revenue'] / $maxRevenue) * 100 : 0;
                ?>
                    <div class="flex-1 flex flex-col items-center group">
                        <div class="w-full bg-gradient-to-t from-green-600 to-green-400 rounded-t hover:from-green-700 hover:to-green-500 transition relative" 
                             style="height: <?= $height ?>%; min-height: 4px;"
                             title="<?= formatPrice($month['revenue']) ?>">
                        </div>
                        <p class="text-[10px] text-gray-500 mt-2"><?= date('M Y', strtotime($month['month'] . '-01')) ?></p>
                        <p class="text-[10px] text-gray-400"><?= $month['orders'] ?> cmd</p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid lg:grid-cols-3 gap-6">
            
            <!-- Historique des retraits -->
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b dark:border-gray-700 flex justify-between items-center">
                    <h2 class="font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-history text-orange-600"></i> Historique des retraits
                    </h2>
                    <span class="text-xs text-gray-500"><?= count($withdrawals) ?> retraits</span>
                </div>
                <div class="divide-y dark:divide-gray-700 max-h-96 overflow-y-auto custom-scrollbar">
                    <?php if (count($withdrawals) > 0): ?>
                        <?php foreach ($withdrawals as $w): 
                            $statusColors = [
                                'pending' => 'yellow', 'approved' => 'blue', 'completed' => 'green',
                                'rejected' => 'red', 'cancelled' => 'gray'
                            ];
                            $statusLabels = [
                                'pending' => 'En attente', 'approved' => 'Approuvé', 'completed' => 'Complété',
                                'rejected' => 'Rejeté', 'cancelled' => 'Annulé'
                            ];
                            $color = $statusColors[$w['status']] ?? 'gray';
                        ?>
                            <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <div class="flex justify-between items-start gap-3">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <p class="font-bold text-gray-800 dark:text-white"><?= formatPrice($w['amount']) ?></p>
                                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-<?= $color ?>-100 text-<?= $color ?>-800">
                                                <?= $statusLabels[$w['status']] ?>
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500">
                                            <i class="fas fa-<?= $w['method'] == 'mobile_money' ? 'mobile-alt' : ($w['method'] == 'bank' ? 'university' : 'money-bill') ?> mr-1"></i>
                                            <?= ucfirst(str_replace('_', ' ', $w['method'])) ?>
                                            <?php if ($w['account_number']): ?>
                                                • <?= clean($w['account_number']) ?>
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-xs text-gray-400 mt-1">
                                            <i class="fas fa-calendar mr-1"></i>
                                            <?= date('d/m/Y H:i', strtotime($w['requested_at'])) ?>
                                        </p>
                                        <?php if ($w['admin_notes']): ?>
                                            <p class="text-xs text-blue-600 mt-1 bg-blue-50 dark:bg-blue-900/20 px-2 py-1 rounded">
                                                <i class="fas fa-comment mr-1"></i><?= clean($w['admin_notes']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-money-bill-wave text-4xl mb-3 text-gray-300"></i>
                            <p>Aucun retrait effectué</p>
                            <p class="text-xs mt-1">Demandez votre premier retrait !</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Informations bancaires -->
            <div class="space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="p-4 border-b dark:border-gray-700 bg-gradient-to-r from-purple-600 to-purple-700 text-white">
                        <h2 class="font-bold flex items-center gap-2">
                            <i class="fas fa-university"></i> Informations bancaires
                        </h2>
                    </div>
                    <form method="POST" class="p-4 space-y-3">
                        <input type="hidden" name="action" value="update_bank_info">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1">Titulaire du compte</label>
                            <input type="text" name="account_holder" value="<?= clean($seller['account_holder'] ?? '') ?>" 
                                   class="w-full px-3 py-2 text-sm border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1">Nom de la banque</label>
                            <input type="text" name="bank_name" value="<?= clean($seller['bank_name'] ?? '') ?>" 
                                   class="w-full px-3 py-2 text-sm border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white" placeholder="Ex: BK, COOPEG">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1">N° de compte</label>
                            <input type="text" name="account_number" value="<?= clean($seller['account_number'] ?? '') ?>" 
                                   class="w-full px-3 py-2 text-sm border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1">Mobile Money</label>
                            <input type="tel" name="mobile_money_number" value="<?= clean($seller['mobile_money_number'] ?? '') ?>" 
                                   class="w-full px-3 py-2 text-sm border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white" placeholder="+257 ...">
                        </div>
                        <button type="submit" class="w-full bg-purple-600 text-white py-2 rounded-lg text-sm font-semibold hover:bg-purple-700 transition">
                            <i class="fas fa-save mr-1"></i>Enregistrer
                        </button>
                    </form>
                </div>

                <!-- Résumé des commissions -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
                    <h3 class="font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <i class="fas fa-percentage text-orange-500"></i> Détail des commissions
                    </h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-300">Revenus bruts</span>
                            <span class="font-semibold"><?= formatPrice($financeStats['total_revenue']) ?></span>
                        </div>
                        <div class="flex justify-between text-red-600">
                            <span>Commission (<?= $financeStats['commission_rate'] ?>%)</span>
                            <span class="font-semibold">-<?= formatPrice($financeStats['total_commission']) ?></span>
                        </div>
                        <div class="flex justify-between text-orange-600">
                            <span>Déjà retiré</span>
                            <span class="font-semibold">-<?= formatPrice($financeStats['total_withdrawn']) ?></span>
                        </div>
                        <div class="flex justify-between text-yellow-600">
                            <span>En attente</span>
                            <span class="font-semibold">-<?= formatPrice($financeStats['pending_withdrawals']) ?></span>
                        </div>
                        <div class="border-t dark:border-gray-700 pt-2 flex justify-between">
                            <span class="font-bold text-gray-800 dark:text-white">Solde net</span>
                            <span class="font-bold text-green-600"><?= formatPrice($financeStats['available_balance']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ventes récentes -->
        <?php if (count($recentSales) > 0): ?>
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-4 border-b dark:border-gray-700 flex justify-between items-center">
                <h2 class="font-bold text-gray-800 dark:text-white flex items-center gap-2">
                    <i class="fas fa-shopping-bag text-green-600"></i> Ventes récentes
                </h2>
                <a href="<?= BASE_URL ?>/seller/orders.php" class="text-sm text-blue-600 hover:text-blue-800 font-semibold">Voir tout</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Commande</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Client</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Produit</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Total</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        <?php foreach ($recentSales as $sale): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 text-sm font-semibold text-blue-600"><?= $sale['order_number'] ?></td>
                                <td class="px-4 py-3 text-sm"><?= clean($sale['first_name'] . ' ' . $sale['last_name']) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300"><?= clean($sale['product_name']) ?> (x<?= $sale['quantity'] ?>)</td>
                                <td class="px-4 py-3 text-sm font-bold text-green-600"><?= formatPrice($sale['total']) ?></td>
                                <td class="px-4 py-3 text-xs text-gray-500"><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Modal demande de retrait -->
<div id="withdrawModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full">
        <div class="bg-gradient-to-r from-green-600 to-emerald-600 p-6 rounded-t-2xl text-white">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold flex items-center gap-2">
                    <i class="fas fa-money-bill-wave"></i> Demander un retrait
                </h3>
                <button onclick="document.getElementById('withdrawModal').classList.add('hidden')" class="text-white/80 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <p class="text-green-100 text-sm mt-2">Solde disponible: <strong><?= formatPrice($financeStats['available_balance']) ?></strong></p>
        </div>
        
        <form method="POST" action="<?= BASE_URL ?>/api/request-withdrawal.php" class="p-6 space-y-4">
            <?php if ($financeStats['available_balance'] < 10000): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Le montant minimum de retrait est de <strong>10 000 FBu</strong>.
                </div>
            <?php else: ?>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Montant (FBu) *</label>
                    <input type="number" name="amount" min="10000" max="<?= $financeStats['available_balance'] ?>" required
                           class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white text-lg font-bold"
                           placeholder="10000">
                    <p class="text-xs text-gray-500 mt-1">Min: 10 000 FBu • Max: <?= formatPrice($financeStats['available_balance']) ?></p>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Méthode de paiement *</label>
                    <div class="grid grid-cols-3 gap-2">
                        <label class="cursor-pointer">
                            <input type="radio" name="method" value="mobile_money" checked class="peer sr-only">
                            <div class="border-2 border-gray-200 dark:border-gray-700 peer-checked:border-green-500 peer-checked:bg-green-50 dark:peer-checked:bg-green-900/20 rounded-lg p-3 text-center transition">
                                <i class="fas fa-mobile-alt text-xl text-green-600 mb-1"></i>
                                <p class="text-xs font-semibold">Mobile Money</p>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="method" value="bank" class="peer sr-only">
                            <div class="border-2 border-gray-200 dark:border-gray-700 peer-checked:border-green-500 peer-checked:bg-green-50 dark:peer-checked:bg-green-900/20 rounded-lg p-3 text-center transition">
                                <i class="fas fa-university text-xl text-blue-600 mb-1"></i>
                                <p class="text-xs font-semibold">Banque</p>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="method" value="cash" class="peer sr-only">
                            <div class="border-2 border-gray-200 dark:border-gray-700 peer-checked:border-green-500 peer-checked:bg-green-50 dark:peer-checked:bg-green-900/20 rounded-lg p-3 text-center transition">
                                <i class="fas fa-money-bill text-xl text-orange-600 mb-1"></i>
                                <p class="text-xs font-semibold">Cash</p>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 text-xs text-blue-700 dark:text-blue-300">
                    <i class="fas fa-info-circle mr-1"></i>
                    Assurez-vous que vos informations bancaires sont à jour avant de demander un retrait.
                </div>
                
                <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-3.5 rounded-lg font-bold hover:from-green-700 hover:to-emerald-700 transition shadow-lg">
                    <i class="fas fa-paper-plane mr-2"></i>Soumettre la demande
                </button>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>