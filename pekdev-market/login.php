<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    $redirect = BASE_URL . '/';
    if ($_SESSION['user_role'] === 'admin') $redirect = BASE_URL . '/admin/';
    redirect($redirect);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Token invalide';
    } else {
        $result = loginUser($_POST['email'] ?? '', $_POST['password'] ?? '', $pdo);
        if ($result['success']) {
            $redirect = BASE_URL . '/';
            if ($_SESSION['user_role'] === 'admin') $redirect = BASE_URL . '/admin/';
            elseif ($_SESSION['user_role'] === 'seller') $redirect = BASE_URL . '/sell.php';
            redirect($redirect);
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af', secondary: '#f97316' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-4xl w-full grid md:grid-cols-2 gap-6">
        <!-- Formulaire -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-user text-white text-2xl"></i>
                </div>
                <h2 class="text-3xl font-bold text-gray-800 dark:text-white">Connexion</h2>
            </div>
            <?php if ($error): ?>
                <div class="bg-red-50 dark:bg-red-900/20 text-red-700 px-4 py-3 rounded-lg mb-4"><?= clean($error) ?></div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <?= csrfField() ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Email</label>
                    <input type="email" name="email" required class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg focus:border-blue-600 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Mot de passe</label>
                    <input type="password" name="password" required class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg focus:border-blue-600 focus:outline-none">
                </div>
                <button type="submit" class="w-full py-3 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700">Se connecter</button>
            </form>
            <p class="text-center mt-4 text-gray-600 dark:text-gray-300">
                Pas de compte ? <a href="<?= BASE_URL ?>/register.php" class="text-blue-600 font-semibold">S'inscrire</a>
            </p>
        </div>
        
        <!-- Comptes démo -->
        <div class="bg-gradient-to-br from-blue-50 to-orange-50 dark:from-gray-800 dark:to-gray-700 rounded-2xl shadow-xl p-6">
            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-4 text-center">⚡ Connexion rapide (Démo)</h3>
            <div class="space-y-3">
                <form method="POST" class="bg-white dark:bg-gray-800 rounded-xl p-4 hover:shadow-lg transition">
                    <?= csrfField() ?>
                    <input type="hidden" name="email" value="admin@pekdev.bi">
                    <input type="hidden" name="password" value="admin123">
                    <button type="submit" class="w-full flex items-center gap-3">
                        <div class="w-10 h-10 bg-red-500 rounded-lg flex items-center justify-center"><i class="fas fa-user-shield text-white"></i></div>
                        <div class="flex-1 text-left">
                            <p class="font-bold text-gray-800 dark:text-white">Admin</p>
                            <p class="text-xs text-gray-500">admin@pekdev.bi</p>
                        </div>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                    </button>
                </form>
                <form method="POST" class="bg-white dark:bg-gray-800 rounded-xl p-4 hover:shadow-lg transition">
                    <?= csrfField() ?>
                    <input type="hidden" name="email" value="vendeur@pekdev.bi">
                    <input type="hidden" name="password" value="admin123">
                    <button type="submit" class="w-full flex items-center gap-3">
                        <div class="w-10 h-10 bg-orange-500 rounded-lg flex items-center justify-center"><i class="fas fa-store text-white"></i></div>
                        <div class="flex-1 text-left">
                            <p class="font-bold text-gray-800 dark:text-white">Vendeur</p>
                            <p class="text-xs text-gray-500">vendeur@pekdev.bi</p>
                        </div>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                    </button>
                </form>
                <form method="POST" class="bg-white dark:bg-gray-800 rounded-xl p-4 hover:shadow-lg transition">
                    <?= csrfField() ?>
                    <input type="hidden" name="email" value="client@pekdev.bi">
                    <input type="hidden" name="password" value="admin123">
                    <button type="submit" class="w-full flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center"><i class="fas fa-user text-white"></i></div>
                        <div class="flex-1 text-left">
                            <p class="font-bold text-gray-800 dark:text-white">Client</p>
                            <p class="text-xs text-gray-500">client@pekdev.bi</p>
                        </div>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>