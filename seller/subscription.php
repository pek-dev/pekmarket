<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'seller') { header('Location: ' . BASE_URL); exit; }

$sellerId = $_SESSION['user_id'];
$message = '';

// Récupérer l'abonnement actif
$currentSubscription = null;
try {
    $stmt = $pdo->prepare("
        SELECT s.*, sp.name as plan_name, sp.price as plan_price, sp.badge_text, sp.badge_color,
               sp.max_products, sp.featured_products, sp.is_boosted, sp.duration_days
        FROM subscriptions s
        JOIN subscription_plans sp ON s.plan_id = sp.id
        WHERE s.user_id = ? AND s.status = 'active'
        ORDER BY s.end_date DESC
        LIMIT 1
    ");
    $stmt->execute([$sellerId]);
    $currentSubscription = $stmt->fetch();
} catch (Exception $e) {}

// Récupérer tous les plans
$plans = [];
try {
    $plans = $pdo->query("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY priority_sort ASC")->fetchAll();
} catch (Exception $e) {}

// Traitement de la souscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'subscribe') {
        $planId = intval($_POST['plan_id'] ?? 0);
        $paymentMethod = $_POST['payment_method'] ?? 'mobile_money';
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1");
            $stmt->execute([$planId]);
            $plan = $stmt->fetch();
            
            if ($plan) {
                $pdo->beginTransaction();
                
                // Créer l'abonnement (en attente de paiement)
                $pdo->prepare("
                    INSERT INTO subscriptions (user_id, plan_id, status, payment_method, payment_status, start_date, end_date, amount_paid)
                    VALUES (?, ?, 'pending', ?, 'pending', NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), ?)
                ")->execute([$sellerId, $planId, $paymentMethod, $plan['duration_days'], $plan['price']]);
                
                $subId = $pdo->lastInsertId();
                
                // Enregistrer la transaction
                $pdo->prepare("
                    INSERT INTO revenue_transactions (user_id, type, reference_id, amount, status, description)
                    VALUES (?, 'subscription', ?, ?, 'pending', ?)
                ")->execute([$sellerId, $subId, $plan['price'], "Abonnement: " . $plan['name']]);
                
                $pdo->commit();
                $message = "✅ Demande d'abonnement enregistrée ! Un administrateur va valider votre paiement.";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "❌ Erreur: " . $e->getMessage();
        }
    }
}

// Calcul des jours restants
$daysLeft = 0;
if ($currentSubscription) {
    $daysLeft = max(0, ceil((strtotime($currentSubscription['end_date']) - time()) / 86400));
}

// Compter les produits du vendeur
$productsCount = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ?");
    $stmt->execute([$sellerId]);
    $productsCount = $stmt->fetchColumn();
} catch (Exception $e) {}

$pageTitle = 'Mon Abonnement';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="bg-gradient-to-r from-purple-600 to-pink-600 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-white">
        <nav class="text-sm text-white/80 mb-2">
            <a href="<?= BASE_URL ?>/dashboard/seller.php" class="hover:text-white">Dashboard</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-white font-medium">Abonnement</span>
        </nav>
        <h1 class="text-3xl font-bold flex items-center gap-3">
            <i class="fas fa-crown"></i> Mon Abonnement
        </h1>
        <p class="text-white/80 mt-2">Gérez votre plan d'abonnement pour plus de visibilité</p>
    </div>
</section>

<section class="py-8 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6"><?= clean($message) ?></div>
        <?php endif; ?>

        <!-- Abonnement actuel -->
        <?php if ($currentSubscription): ?>
            <div class="bg-gradient-to-br from-<?= $currentSubscription['badge_color'] ?>-500 to-<?= $currentSubscription['badge_color'] ?>-600 rounded-2xl p-6 md:p-8 text-white shadow-xl mb-8">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <i class="fas fa-crown text-3xl"></i>
                            <h2 class="text-2xl font-bold">Plan <?= clean($currentSubscription['plan_name']) ?></h2>
                            <?php if ($currentSubscription['badge_text']): ?>
                                <span class="bg-white/20 backdrop-blur-sm text-white text-xs px-3 py-1 rounded-full font-semibold">
                                    <?= clean($currentSubscription['badge_text']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-white/80">Votre abonnement est actif</p>
                    </div>
                    <div class="text-right">
                        <p class="text-4xl font-bold"><?= $daysLeft ?></p>
                        <p class="text-white/80">jours restants</p>
                        <p class="text-xs text-white/60 mt-1">Expire le <?= date('d/m/Y', strtotime($currentSubscription['end_date'])) ?></p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6 pt-6 border-t border-white/20">
                    <div>
                        <p class="text-xs text-white/60">Produits</p>
                        <p class="text-xl font-bold"><?= $productsCount ?> / <?= $currentSubscription['max_products'] == 9999 ? '∞' : $currentSubscription['max_products'] ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-white/60">En vedette</p>
                        <p class="text-xl font-bold"><?= $currentSubscription['featured_products'] == 9999 ? '∞' : $currentSubscription['featured_products'] ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-white/60">Boost</p>
                        <p class="text-xl font-bold"><?= $currentSubscription['is_boosted'] ? '✅ Actif' : '❌ Inactif' ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-white/60">Statut paiement</p>
                        <p class="text-xl font-bold"><?= ucfirst($currentSubscription['payment_status']) ?></p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-xl mb-6">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong>Vous n'avez pas d'abonnement actif.</strong> Choisissez un plan ci-dessous pour booster votre visibilité !
            </div>
        <?php endif; ?>

        <!-- Plans disponibles -->
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6">Choisissez votre plan</h2>
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($plans as $plan): 
                $isCurrentPlan = $currentSubscription && $currentSubscription['plan_id'] == $plan['id'];
                $isPopular = $plan['slug'] === 'premium';
            ?>
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border-2 <?= $isPopular ? 'border-purple-500' : 'border-gray-100 dark:border-gray-700' ?> overflow-hidden relative <?= $isCurrentPlan ? 'ring-4 ring-green-500' : '' ?>">
                    <?php if ($isPopular): ?>
                        <div class="absolute top-0 right-0 bg-purple-500 text-white text-xs px-3 py-1 rounded-bl-lg font-bold">
                            ⭐ POPULAIRE
                        </div>
                    <?php endif; ?>
                    <?php if ($isCurrentPlan): ?>
                        <div class="absolute top-0 left-0 bg-green-500 text-white text-xs px-3 py-1 rounded-br-lg font-bold">
                            ✓ ACTUEL
                        </div>
                    <?php endif; ?>
                    
                    <div class="bg-gradient-to-br from-<?= $plan['badge_color'] ?>-500 to-<?= $plan['badge_color'] ?>-600 p-6 text-white">
                        <h3 class="text-xl font-bold mb-1"><?= clean($plan['name']) ?></h3>
                        <?php if ($plan['badge_text']): ?>
                            <span class="inline-block bg-white/20 text-xs px-2 py-0.5 rounded-full"><?= clean($plan['badge_text']) ?></span>
                        <?php endif; ?>
                        <div class="mt-4">
                            <span class="text-4xl font-bold"><?= number_format($plan['price'], 0, ',', ' ') ?></span>
                            <span class="text-white/80"> FBu</span>
                            <p class="text-xs text-white/80 mt-1">/ <?= $plan['duration_days'] ?> jours</p>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <ul class="space-y-3 mb-6">
                            <li class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <i class="fas fa-check-circle text-green-500 mt-0.5"></i>
                                <span><strong><?= $plan['max_products'] == 9999 ? 'Illimité' : $plan['max_products'] ?></strong> produits</span>
                            </li>
                            <li class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <i class="fas fa-check-circle text-green-500 mt-0.5"></i>
                                <span><strong><?= $plan['featured_products'] ?></strong> produits en vedette</span>
                            </li>
                            <?php if ($plan['is_boosted']): ?>
                                <li class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <i class="fas fa-rocket text-purple-500 mt-0.5"></i>
                                    <span>Boost de la boutique</span>
                                </li>
                            <?php endif; ?>
                            <li class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <i class="fas fa-check-circle text-green-500 mt-0.5"></i>
                                <span>Badge <?= clean($plan['name']) ?></span>
                            </li>
                        </ul>
                        
                        <?php if ($isCurrentPlan): ?>
                            <button disabled class="w-full bg-gray-300 text-gray-500 py-3 rounded-lg font-semibold cursor-not-allowed">
                                Plan actuel
                            </button>
                        <?php else: ?>
                            <form method="POST" onsubmit="return confirm('Confirmer la souscription au plan <?= clean($plan['name']) ?> ?');">
                                <input type="hidden" name="action" value="subscribe">
                                <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                                <input type="hidden" name="payment_method" value="mobile_money">
                                <button type="submit" class="w-full bg-gradient-to-r from-<?= $plan['badge_color'] ?>-500 to-<?= $plan['badge_color'] ?>-600 text-white py-3 rounded-lg font-semibold hover:from-<?= $plan['badge_color'] ?>-600 hover:to-<?= $plan['badge_color'] ?>-700 transition shadow-lg">
                                    <i class="fas fa-crown mr-2"></i>Souscrire
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- FAQ -->
        <div class="mt-12 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <i class="fas fa-question-circle text-blue-600"></i> Questions fréquentes
            </h2>
            <div class="space-y-4">
                <div>
                    <h3 class="font-semibold text-gray-800 dark:text-white">Comment fonctionne le paiement ?</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Après souscription, un administrateur valide votre paiement via Mobile Money ou Cash. Votre abonnement est activé immédiatement après validation.</p>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800 dark:text-white">Puis-je changer de plan ?</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Oui, vous pouvez upgrader ou downgrader à tout moment. Le nouveau plan prend effet immédiatement.</p>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800 dark:text-white">Que se passe-t-il à l'expiration ?</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Votre compte reste actif mais vous perdez les avantages Premium (boost, badge). Vous pouvez renouveler à tout moment.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>