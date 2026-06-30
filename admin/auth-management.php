<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: ' . BASE_URL); exit; }

$message = '';
$messageType = 'success';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = intval($_POST['user_id'] ?? 0);
    
    if ($userId > 0 && $userId != $_SESSION['user_id']) {
        try {
            if ($action === 'reset_password') {
                // Générer un mot de passe temporaire
                $tempPassword = bin2hex(random_bytes(4)); // 8 caractères
                $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ?, reset_token = ?, reset_token_expires = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?")
                    ->execute([$hashedPassword, $tempPassword, $userId]);
                $message = "Mot de passe réinitialisé. Nouveau mot de passe: $tempPassword";
            }
            elseif ($action === 'force_logout') {
                // Supprimer le remember_token pour forcer la déconnexion
                $pdo->prepare("UPDATE users SET remember_token = NULL, reset_token = NULL WHERE id = ?")->execute([$userId]);
                $message = "Utilisateur déconnecté de force.";
            }
            elseif ($action === 'clear_sessions') {
                $pdo->prepare("UPDATE users SET remember_token = NULL, reset_token = NULL, reset_token_expires = NULL WHERE id = ?")->execute([$userId]);
                $message = "Toutes les sessions de l'utilisateur ont été supprimées.";
            }
            elseif ($action === 'verify_email') {
                $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$userId]);
                $message = "Email vérifié avec succès.";
            }
            elseif ($action === 'toggle_active') {
                $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")->execute([$userId]);
                $message = "Statut du compte modifié.";
            }
        } catch (Exception $e) {
            $message = "Erreur: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Filtres
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ["1=1"];
$params = [];

if ($statusFilter === 'active') { $where[] = "u.is_active = 1"; }
elseif ($statusFilter === 'inactive') { $where[] = "u.is_active = 0"; }
elseif ($statusFilter === 'verified') { $where[] = "u.is_verified = 1"; }
elseif ($statusFilter === 'unverified') { $where[] = "u.is_verified = 0"; }

if ($searchQuery) {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$searchQuery%"; $params[] = "%$searchQuery%"; $params[] = "%$searchQuery%";
}

$whereClause = implode(' AND ', $where);

// Récupérer les utilisateurs avec stats de connexion
try {
    $usersStmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(DISTINCT o.id) as orders_count,
               COUNT(DISTINCT p.id) as products_count,
               (SELECT COUNT(*) FROM favorites WHERE user_id = u.id) as favorites_count,
               (SELECT COUNT(*) FROM cart WHERE user_id = u.id) as cart_count
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id
        LEFT JOIN products p ON u.id = p.seller_id
        WHERE $whereClause
        GROUP BY u.id
        ORDER BY u.last_login DESC NULLS LAST
        LIMIT ? OFFSET ?
    ");
    $params[] = $perPage; $params[] = $offset;
    $usersStmt->execute($params);
    $users = $usersStmt->fetchAll();
    
    $countStmt = $pdo->prepare("SELECT COUNT(DISTINCT u.id) FROM users u WHERE $whereClause");
    $countStmt->execute(array_slice($params, 0, -2));
    $totalUsers = $countStmt->fetchColumn();
    $totalPages = ceil($totalUsers / $perPage);
} catch (Exception $e) {
    $users = []; $totalPages = 0;
}

// Statistiques globales
try {
    $authStats = $pdo->query("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_users,
            SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as recent_logins,
            SUM(CASE WHEN remember_token IS NOT NULL THEN 1 ELSE 0 END) as remember_me,
            SUM(CASE WHEN reset_token IS NOT NULL AND reset_token_expires > NOW() THEN 1 ELSE 0 END) as pending_resets
        FROM users
    ")->fetch();
} catch (Exception $e) {
    $authStats = ['total_users' => 0, 'active_users' => 0, 'verified_users' => 0, 'recent_logins' => 0, 'remember_me' => 0, 'pending_resets' => 0];
}

$pageTitle = 'Gestion des Authentifications';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <nav class="text-sm text-gray-500 mb-2">
            <a href="<?= BASE_URL ?>/dashboard/admin.php" class="hover:text-blue-600">Admin</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-gray-800 dark:text-white font-medium">Authentification</span>
        </nav>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
            <i class="fas fa-shield-alt text-blue-600"></i> Gestion des Authentifications
        </h1>
        <p class="text-gray-500 mt-2">Gérez les comptes utilisateurs, mots de passe et sessions</p>
    </div>

    <?php if ($message): ?>
        <div class="bg-<?= $messageType == 'success' ? 'green' : 'red' ?>-50 border border-<?= $messageType == 'success' ? 'green' : 'red' ?>-200 text-<?= $messageType == 'success' ? 'green' : 'red' ?>-700 px-4 py-3 rounded-xl mb-6 flex items-center justify-between">
            <span><i class="fas fa-<?= $messageType == 'success' ? 'check-circle' : 'exclamation-circle' ?> mr-2"></i><?= clean($message) ?></span>
            <button onclick="this.parentElement.remove()" class="hover:opacity-70"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>

    <!-- Statistiques d'authentification -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-blue-500">
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= $authStats['total_users'] ?></p>
            <p class="text-xs text-gray-500">Total utilisateurs</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-green-500">
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= $authStats['active_users'] ?></p>
            <p class="text-xs text-gray-500">Comptes actifs</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-purple-500">
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= $authStats['verified_users'] ?></p>
            <p class="text-xs text-gray-500">Emails vérifiés</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-orange-500">
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= $authStats['recent_logins'] ?></p>
            <p class="text-xs text-gray-500">Connexions (24h)</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-yellow-500">
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= $authStats['remember_me'] ?></p>
            <p class="text-xs text-gray-500">Se souvenir</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border-l-4 border-red-500">
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= $authStats['pending_resets'] ?></p>
            <p class="text-xs text-gray-500">Réinitialisations</p>
        </div>
    </div>

    <!-- Filtres -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" class="flex flex-col md:flex-row gap-3">
            <input type="text" name="q" value="<?= clean($searchQuery) ?>" placeholder="Rechercher par nom ou email..." 
                   class="flex-1 px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
            <select name="status" onchange="this.form.submit()" class="px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                <option value="all" <?= $statusFilter == 'all' ? 'selected' : '' ?>>Tous les statuts</option>
                <option value="active" <?= $statusFilter == 'active' ? 'selected' : '' ?>>Actifs</option>
                <option value="inactive" <?= $statusFilter == 'inactive' ? 'selected' : '' ?>>Inactifs</option>
                <option value="verified" <?= $statusFilter == 'verified' ? 'selected' : '' ?>>Vérifiés</option>
                <option value="unverified" <?= $statusFilter == 'unverified' ? 'selected' : '' ?>>Non vérifiés</option>
            </select>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">
                <i class="fas fa-search mr-2"></i>Filtrer
            </button>
        </form>
    </div>

    <!-- Liste des utilisateurs -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden border border-gray-100 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Utilisateur</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Rôle</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Dernière connexion</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Statut</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Sécurité</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    <?php foreach ($users as $user): 
                        $lastLogin = $user['last_login'] ? strtotime($user['last_login']) : 0;
                        $isRecent = $lastLogin > (time() - 86400); // 24 heures
                        $hasRemember = !empty($user['remember_token']);
                        $hasReset = !empty($user['reset_token']) && strtotime($user['reset_token_expires'] ?? '2000-01-01') > time();
                    ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-orange-500 rounded-full flex items-center justify-center text-white font-bold">
                                        <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800 dark:text-white text-sm">
                                            <?= clean($user['first_name'] . ' ' . $user['last_name']) ?>
                                            <?php if ($user['is_verified']): ?>
                                                <i class="fas fa-check-circle text-blue-500 text-xs" title="Email vérifié"></i>
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-xs text-gray-500">Inscrit le <?= date('d/m/Y', strtotime($user['created_at'])) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm text-gray-800 dark:text-white"><?= clean($user['email']) ?></p>
                                <p class="text-xs text-gray-500"><?= clean($user['phone'] ?? '-') ?></p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-block px-2 py-1 rounded text-xs font-semibold 
                                    <?= $user['role'] == 'admin' ? 'bg-red-100 text-red-800' : 
                                       ($user['role'] == 'seller' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800') ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($user['last_login']): ?>
                                    <p class="text-sm text-gray-800 dark:text-white">
                                        <?= date('d/m/Y H:i', strtotime($user['last_login'])) ?>
                                        <?php if ($isRecent): ?>
                                            <span class="inline-block w-2 h-2 bg-green-500 rounded-full ml-1" title="Récent"></span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="text-xs text-gray-500"><?= timeAgo($user['last_login']) ?></p>
                                <?php else: ?>
                                    <p class="text-sm text-gray-400">Jamais connecté</p>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-1">
                                    <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold w-fit
                                        <?= $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= $user['is_active'] ? 'Actif' : 'Inactif' ?>
                                    </span>
                                    <?php if ($hasRemember): ?>
                                        <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 w-fit">
                                            <i class="fas fa-cookie-bite mr-1"></i>Remember
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($hasReset): ?>
                                        <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-800 w-fit">
                                            <i class="fas fa-key mr-1"></i>Reset
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <p><i class="fas fa-shopping-bag text-gray-400 mr-1"></i><?= $user['orders_count'] ?> commandes</p>
                                <p><i class="fas fa-heart text-gray-400 mr-1"></i><?= $user['favorites_count'] ?> favoris</p>
                                <?php if ($user['role'] === 'seller'): ?>
                                    <p><i class="fas fa-box text-gray-400 mr-1"></i><?= $user['products_count'] ?> produits</p>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <!-- Réinitialiser mot de passe -->
                                    <form method="POST" class="inline" onsubmit="return confirm('Réinitialiser le mot de passe de cet utilisateur ? Un mot de passe temporaire sera généré.');">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="action" value="reset_password">
                                        <button class="w-8 h-8 bg-purple-100 text-purple-600 rounded-lg hover:bg-purple-200 flex items-center justify-center" title="Réinitialiser mot de passe">
                                            <i class="fas fa-key text-xs"></i>
                                        </button>
                                    </form>
                                    
                                    <!-- Forcer déconnexion -->
                                    <form method="POST" class="inline" onsubmit="return confirm('Forcer la déconnexion de cet utilisateur ?');">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="action" value="force_logout">
                                        <button class="w-8 h-8 bg-orange-100 text-orange-600 rounded-lg hover:bg-orange-200 flex items-center justify-center" title="Forcer déconnexion">
                                            <i class="fas fa-sign-out-alt text-xs"></i>
                                        </button>
                                    </form>
                                    
                                    <!-- Vérifier email -->
                                    <?php if (!$user['is_verified']): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="action" value="verify_email">
                                            <button class="w-8 h-8 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 flex items-center justify-center" title="Vérifier email">
                                                <i class="fas fa-check text-xs"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <!-- Activer/Désactiver -->
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <button class="w-8 h-8 bg-<?= $user['is_active'] ? 'red' : 'green' ?>-100 text-<?= $user['is_active'] ? 'red' : 'green' ?>-600 rounded-lg hover:bg-<?= $user['is_active'] ? 'red' : 'green' ?>-200 flex items-center justify-center" title="<?= $user['is_active'] ? 'Désactiver' : 'Activer' ?>">
                                            <i class="fas fa-<?= $user['is_active'] ? 'ban' : 'check' ?> text-xs"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (count($users) === 0): ?>
            <div class="p-12 text-center text-gray-500">
                <i class="fas fa-users text-4xl mb-4 text-gray-300"></i>
                <p>Aucun utilisateur trouvé</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="mt-6 flex justify-center gap-2">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&status=<?= $statusFilter ?>&q=<?= urlencode($searchQuery) ?>" 
                   class="px-4 py-2 rounded-lg <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 hover:bg-gray-100' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

    <!-- Légende -->
    <div class="mt-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-700">
        <h3 class="font-bold text-gray-800 dark:text-white mb-4"><i class="fas fa-info-circle text-blue-600 mr-2"></i>Légende des actions</h3>
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
            <div class="flex items-center gap-2">
                <button class="w-8 h-8 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center"><i class="fas fa-key text-xs"></i></button>
                <span class="text-gray-600 dark:text-gray-300">Réinitialiser le mot de passe (génère un mot de passe temporaire de 8 caractères)</span>
            </div>
            <div class="flex items-center gap-2">
                <button class="w-8 h-8 bg-orange-100 text-orange-600 rounded-lg flex items-center justify-center"><i class="fas fa-sign-out-alt text-xs"></i></button>
                <span class="text-gray-600 dark:text-gray-300">Forcer la déconnexion (supprime les tokens de session)</span>
            </div>
            <div class="flex items-center gap-2">
                <button class="w-8 h-8 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center"><i class="fas fa-check text-xs"></i></button>
                <span class="text-gray-600 dark:text-gray-300">Vérifier l'email manuellement</span>
            </div>
            <div class="flex items-center gap-2">
                <button class="w-8 h-8 bg-red-100 text-red-600 rounded-lg flex items-center justify-center"><i class="fas fa-ban text-xs"></i></button>
                <span class="text-gray-600 dark:text-gray-300">Activer/Désactiver le compte</span>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>