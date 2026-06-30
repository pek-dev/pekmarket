<?php
require_once __DIR__ . '/config/bootstrap.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'reset_all') {
            $newPassword = "admin123";
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email IN (?, ?, ?, ?)");
            $stmt->execute([
                $hash, 
                'admin@pekdev.bi', 
                'vendeur@pekdev.bi', 
                'client@pekdev.bi',
                'admin1@pekdev.bi'
            ]);
            
            $message = "✅ Tous les mots de passe ont été réinitialisés à 'admin123'";
            $messageType = 'success';
        }
        elseif ($action === 'reset_one') {
            $email = trim($_POST['email'] ?? '');
            $newPassword = trim($_POST['new_password'] ?? 'admin123');
            
            if (empty($email)) {
                throw new Exception("Email requis");
            }
            
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hash, $email]);
            
            if ($stmt->rowCount() > 0) {
                $message = "✅ Mot de passe réinitialisé pour $email (nouveau: '$newPassword')";
                $messageType = 'success';
            } else {
                $message = "❌ Aucun utilisateur trouvé avec cet email";
                $messageType = 'error';
            }
        }
        elseif ($action === 'generate_hash') {
            $password = trim($_POST['password'] ?? '');
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $message = "Hash pour '$password' : $hash";
                $messageType = 'info';
            }
        }
    } catch (Exception $e) {
        $message = "❌ Erreur: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Récupérer la liste des utilisateurs
$users = [];
try {
    $users = $pdo->query("SELECT id, email, first_name, last_name, role, is_active FROM users ORDER BY id ASC")->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation des mots de passe</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen p-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-xl shadow-lg p-8">
            <h1 class="text-3xl font-bold text-blue-600 mb-2 flex items-center gap-3">
                <i class="fas fa-key"></i> Réinitialisation des mots de passe
            </h1>
            <p class="text-gray-500 mb-6">Corrigez les problèmes de connexion en réinitialisant les mots de passe</p>

            <?php if ($message): ?>
                <div class="bg-<?= $messageType == 'success' ? 'green' : ($messageType == 'error' ? 'red' : 'blue') ?>-50 border border-<?= $messageType == 'success' ? 'green' : ($messageType == 'error' ? 'red' : 'blue') ?>-200 text-<?= $messageType == 'success' ? 'green' : ($messageType == 'error' ? 'red' : 'blue') ?>-700 px-4 py-3 rounded-lg mb-6">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Action 1 : Réinitialiser tous -->
            <div class="bg-red-50 border-2 border-red-200 rounded-lg p-6 mb-6">
                <h2 class="text-xl font-bold text-red-800 mb-3 flex items-center gap-2">
                    <i class="fas fa-exclamation-triangle"></i> Réinitialiser TOUS les comptes
                </h2>
                <p class="text-sm text-red-700 mb-4">
                    Met à jour tous les comptes principaux avec le mot de passe <strong>admin123</strong>
                </p>
                <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir réinitialiser tous les mots de passe ?');">
                    <input type="hidden" name="action" value="reset_all">
                    <button type="submit" class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 font-semibold">
                        <i class="fas fa-sync-alt mr-2"></i>Réinitialiser tous les comptes
                    </button>
                </form>
            </div>

            <!-- Action 2 : Réinitialiser un compte -->
            <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-6 mb-6">
                <h2 class="text-xl font-bold text-blue-800 mb-3 flex items-center gap-2">
                    <i class="fas fa-user-edit"></i> Réinitialiser un compte spécifique
                </h2>
                <form method="POST" class="space-y-3">
                    <input type="hidden" name="action" value="reset_one">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Email de l'utilisateur</label>
                        <select name="email" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= htmlspecialchars($user['email']) ?>">
                                    <?= htmlspecialchars($user['email']) ?> (<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> - <?= $user['role'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Nouveau mot de passe</label>
                        <input type="text" name="new_password" value="admin123" required class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-semibold">
                        <i class="fas fa-save mr-2"></i>Réinitialiser
                    </button>
                </form>
            </div>

            <!-- Action 3 : Générer un hash -->
            <div class="bg-purple-50 border-2 border-purple-200 rounded-lg p-6 mb-6">
                <h2 class="text-xl font-bold text-purple-800 mb-3 flex items-center gap-2">
                    <i class="fas fa-lock"></i> Générer un hash pour un mot de passe
                </h2>
                <form method="POST" class="space-y-3">
                    <input type="hidden" name="action" value="generate_hash">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Mot de passe à hasher</label>
                        <input type="text" name="password" required class="w-full px-4 py-2 border rounded-lg" placeholder="Entrez un mot de passe">
                    </div>
                    <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 font-semibold">
                        <i class="fas fa-cog mr-2"></i>Générer le hash
                    </button>
                </form>
            </div>

            <!-- Liste des utilisateurs -->
            <div class="bg-gray-50 rounded-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-3 flex items-center gap-2">
                    <i class="fas fa-users"></i> Liste des utilisateurs (<?= count($users) ?>)
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">ID</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Nom</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Email</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Rôle</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="px-4 py-2 text-sm"><?= $user['id'] ?></td>
                                    <td class="px-4 py-2 text-sm"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                    <td class="px-4 py-2 text-sm font-mono"><?= htmlspecialchars($user['email']) ?></td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-1 rounded text-xs font-semibold 
                                            <?= $user['role'] == 'admin' ? 'bg-red-100 text-red-800' : 
                                               ($user['role'] == 'seller' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800') ?>">
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold 
                                            <?= $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                            <?= $user['is_active'] ? 'Actif' : 'Inactif' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Comptes de test -->
            <div class="mt-6 bg-green-50 border border-green-200 rounded-lg p-6">
                <h3 class="font-bold text-green-800 mb-3"><i class="fas fa-info-circle mr-2"></i>Comptes de test (après réinitialisation)</h3>
                <div class="grid md:grid-cols-3 gap-3 text-sm">
                    <div class="bg-white p-3 rounded border">
                        <p class="font-semibold text-red-600">👑 Admin</p>
                        <p>Email: <code>admin@pekdev.bi</code></p>
                        <p>MDP: <code>admin123</code></p>
                    </div>
                    <div class="bg-white p-3 rounded border">
                        <p class="font-semibold text-green-600">🏪 Vendeur</p>
                        <p>Email: <code>vendeur@pekdev.bi</code></p>
                        <p>MDP: <code>admin123</code></p>
                    </div>
                    <div class="bg-white p-3 rounded border">
                        <p class="font-semibold text-blue-600">👤 Client</p>
                        <p>Email: <code>client@pekdev.bi</code></p>
                        <p>MDP: <code>admin123</code></p>
                    </div>
                </div>
            </div>

            <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-sm text-yellow-800">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Sécurité :</strong> Supprimez ce fichier après utilisation pour des raisons de sécurité !
                </p>
            </div>
        </div>
    </div>
</body>
</html>