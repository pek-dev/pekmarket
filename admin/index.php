<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/includes/header.php';





requireAdmin();

$stats = [
    'products' => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'orders' => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn(),
    'revenue' => $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn(),
];

$recentOrders = $pdo->query("SELECT o.*, u.first_name, u.last_name FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Admin - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-8"><i class="fas fa-chart-line text-blue-600 mr-2"></i>Dashboard Admin</h1>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border-l-4 border-blue-500">
                <p class="text-sm text-gray-500">Produits</p>
                <p class="text-2xl font-bold"><?= $stats['products'] ?></p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border-l-4 border-green-500">
                <p class="text-sm text-gray-500">Commandes</p>
                <p class="text-2xl font-bold"><?= $stats['orders'] ?></p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border-l-4 border-orange-500">
                <p class="text-sm text-gray-500">Clients</p>
                <p class="text-2xl font-bold"><?= $stats['users'] ?></p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border-l-4 border-purple-500">
                <p class="text-sm text-gray-500">Revenus</p>
                <p class="text-lg font-bold"><?= formatPrice($stats['revenue']) ?></p>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-6 border-b dark:border-gray-700">
                <h2 class="text-lg font-bold">Commandes récentes</h2>
            </div>
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">N°</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($recentOrders as $o): ?>
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-blue-600"><?= clean($o['order_number']) ?></td>
                            <td class="px-6 py-4 text-sm"><?= clean($o['first_name'] . ' ' . $o['last_name']) ?></td>
                            <td class="px-6 py-4 text-sm font-bold"><?= formatPrice($o['total']) ?></td>
                            <td class="px-6 py-4"><span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800"><?= $o['status'] ?></span></td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?= formatDate($o['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>