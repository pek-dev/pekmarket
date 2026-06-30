<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$user = getCurrentUser($pdo);
$orders = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$orders->execute([$userId = $_SESSION['user_id']]);
$orders = $orders->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Profil - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af', secondary: '#f97316' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="max-w-5xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-6">Mon Profil</h1>
        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm">
                <div class="text-center mb-4">
                    <div class="w-20 h-20 bg-blue-600 rounded-full flex items-center justify-center text-white text-3xl font-bold mx-auto mb-3"><?= strtoupper(substr($user['first_name'], 0, 1)) ?></div>
                    <p class="font-bold"><?= clean($user['first_name'] . ' ' . $user['last_name']) ?></p>
                    <p class="text-xs text-gray-500"><?= clean($user['email']) ?></p>
                </div>
                <nav class="space-y-1">
                    <a href="profile.php" class="block px-4 py-2 bg-blue-50 dark:bg-blue-900/20 text-blue-600 rounded-lg font-semibold">Mon profil</a>
                    <a href="orders.php" class="block px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">Mes commandes</a>
                    <a href="favorites.php" class="block px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">Favoris</a>
                    <?php if (isSeller() || isAdmin()): ?>
                        <a href="admin/" class="block px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">Dashboard</a>
                        <a href="sell.php" class="block px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">Vendre</a>
                    <?php endif; ?>
                    <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg">Déconnexion</a>
                </nav>
            </div>
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm">
                    <h2 class="font-bold mb-4">Informations personnelles</h2>
                    <form method="POST" class="space-y-4">
                        <div class="grid md:grid-cols-2 gap-4">
                            <input type="text" name="first_name" value="<?= clean($user['first_name']) ?>" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                            <input type="text" name="last_name" value="<?= clean($user['last_name']) ?>" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                            <input type="tel" name="phone" value="<?= clean($user['phone']) ?>" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                            <input type="text" name="city" value="<?= clean($user['city']) ?>" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                        </div>
                        <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg font-semibold">Enregistrer</button>
                    </form>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm">
                    <h2 class="font-bold mb-4">Mes commandes (<?= count($orders) ?>)</h2>
                    <?php if (empty($orders)): ?>
                        <p class="text-gray-500">Aucune commande</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($orders as $o): ?>
                                <div class="border dark:border-gray-700 rounded-lg p-3">
                                    <div class="flex justify-between">
                                        <span class="font-semibold"><?= clean($o['order_number']) ?></span>
                                        <span class="text-blue-600 font-bold"><?= formatPrice($o['total']) ?></span>
                                    </div>
                                    <p class="text-xs text-gray-500"><?= formatDate($o['created_at']) ?> - <?= $o['status'] ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>