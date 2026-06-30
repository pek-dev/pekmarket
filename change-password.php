<?php
require_once __DIR__ . '/config/bootstrap.php';
requireLogin();

$userId = $_SESSION['user_id'];
$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword)) $errors[] = "Le mot de passe actuel est requis.";
    if (strlen($newPassword) < 6) $errors[] = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
    if ($newPassword !== $confirmPassword) $errors[] = "Les mots de passe ne correspondent pas.";
    
    if (count($errors) === 0) {
        try {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($currentPassword, $user['password'])) {
                $errors[] = "Mot de passe actuel incorrect.";
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashedPassword, $userId]);
                
                // Déconnecter les autres sessions
                $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?")->execute([$userId]);
                
                $message = "✅ Mot de passe modifié avec succès !";
            }
        } catch (Exception $e) {
            $errors[] = "Erreur: " . $e->getMessage();
        }
    }
}

$pageTitle = 'Changer le mot de passe';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <nav class="text-sm text-gray-500 mb-4">
        <a href="<?= BASE_URL ?>/profile.php" class="hover:text-blue-600">Mon Profil</a>
        <i class="fas fa-chevron-right text-xs mx-2"></i>
        <span class="text-gray-800 dark:text-white font-medium">Changer le mot de passe</span>
    </nav>
    
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8 border border-gray-100 dark:border-gray-700">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-lock text-red-500 text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Changer le mot de passe</h1>
                <p class="text-sm text-gray-500">Sécurisez votre compte avec un nouveau mot de passe</p>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6"><?= clean($message) ?></div>
        <?php endif; ?>
        
        <?php if (count($errors) > 0): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6">
                <ul class="list-disc list-inside text-sm space-y-1">
                    <?php foreach ($errors as $error): ?><li><?= clean($error) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Mot de passe actuel *</label>
                <input type="password" name="current_password" required class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Nouveau mot de passe *</label>
                <input type="password" name="new_password" required minlength="6" class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                <p class="text-xs text-gray-500 mt-1">Minimum 6 caractères</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Confirmer le nouveau mot de passe *</label>
                <input type="password" name="confirm_password" required minlength="6" class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
            </div>
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition">
                    <i class="fas fa-save mr-2"></i>Mettre à jour
                </button>
                <a href="<?= BASE_URL ?>/profile.php" class="px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white rounded-lg font-semibold hover:bg-gray-300 transition">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>