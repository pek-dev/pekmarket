<?php
/**
 * Script pour créer les comptes de démo
 * À exécuter UNE SEULE FOIS après installation
 */
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>Installation des comptes démo</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
</head>
<body class='bg-gray-100 p-8'>
<div class='max-w-3xl mx-auto bg-white rounded-2xl shadow-xl p-8'>";

echo "<h1 class='text-3xl font-bold text-gray-800 mb-6'>
        <i class='fas fa-user-plus text-blue-600 mr-2'></i>Installation des comptes de démo
      </h1>";

// Comptes à créer
$demoAccounts = [
    [
        'first_name' => 'Admin',
        'last_name' => 'PekDev',
        'email' => 'admin@pekdev.bi',
        'phone' => '+257 79 000 000',
        'password' => 'admin123',
        'role' => 'admin',
        'province' => 'Bujumbura Mairie',
        'city' => 'Bujumbura',
        'icon' => '👑',
        'color' => 'red'
    ],
    [
        'first_name' => 'Vendeur',
        'last_name' => 'Demo',
        'email' => 'vendeur@pekdev.bi',
        'phone' => '+257 79 111 111',
        'password' => 'admin123',
        'role' => 'seller',
        'province' => 'Bujumbura Mairie',
        'city' => 'Bujumbura',
        'icon' => '🏪',
        'color' => 'orange'
    ],
    [
        'first_name' => 'Jean',
        'last_name' => 'Mugabo',
        'email' => 'client@pekdev.bi',
        'phone' => '+257 79 222 222',
        'password' => 'admin123',
        'role' => 'customer',
        'province' => 'Bujumbura Mairie',
        'city' => 'Bujumbura',
        'icon' => '👤',
        'color' => 'blue'
    ]
];

$successCount = 0;
$skippedCount = 0;
$errorCount = 0;

echo "<div class='space-y-3'>";

foreach ($demoAccounts as $account) {
    try {
        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ?");
        $stmt->execute([$account['email']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Mettre à jour le mot de passe et le rôle
            $hash = password_hash($account['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password = ?, role = ?, first_name = ?, last_name = ?, phone = ?, 
                    province = ?, city = ?, is_verified = 1, is_active = 1
                WHERE email = ?
            ");
            $stmt->execute([
                $hash, $account['role'], $account['first_name'], $account['last_name'],
                $account['phone'], $account['province'], $account['city'], $account['email']
            ]);
            
            echo "<div class='flex items-center gap-3 p-4 bg-yellow-50 border border-yellow-200 rounded-lg'>
                    <span class='text-2xl'>{$account['icon']}</span>
                    <div class='flex-1'>
                        <p class='font-semibold text-yellow-900'>{$account['email']}</p>
                        <p class='text-sm text-yellow-700'>✓ Mis à jour (rôle: {$account['role']})</p>
                    </div>
                    <i class='fas fa-sync text-yellow-600'></i>
                  </div>";
            $skippedCount++;
        } else {
            // Créer le compte
            $hash = password_hash($account['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("
                INSERT INTO users (first_name, last_name, email, phone, password, role, province, city, is_verified, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())
            ");
            $stmt->execute([
                $account['first_name'], $account['last_name'], $account['email'],
                $account['phone'], $hash, $account['role'], $account['province'], $account['city']
            ]);
            
            echo "<div class='flex items-center gap-3 p-4 bg-green-50 border border-green-200 rounded-lg'>
                    <span class='text-2xl'>{$account['icon']}</span>
                    <div class='flex-1'>
                        <p class='font-semibold text-green-900'>{$account['email']}</p>
                        <p class='text-sm text-green-700'>✓ Créé avec succès (rôle: {$account['role']})</p>
                    </div>
                    <i class='fas fa-check-circle text-green-600'></i>
                  </div>";
            $successCount++;
        }
        
    } catch (Exception $e) {
        echo "<div class='flex items-center gap-3 p-4 bg-red-50 border border-red-200 rounded-lg'>
                <span class='text-2xl'>❌</span>
                <div class='flex-1'>
                    <p class='font-semibold text-red-900'>{$account['email']}</p>
                    <p class='text-sm text-red-700'>Erreur: " . htmlspecialchars($e->getMessage()) . "</p>
                </div>
              </div>";
        $errorCount++;
    }
}

echo "</div>";

// Résumé
echo "<div class='mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg'>
        <h2 class='font-bold text-blue-900 mb-2'><i class='fas fa-chart-bar mr-2'></i>Résumé</h2>
        <div class='grid grid-cols-3 gap-3 text-center'>
            <div class='bg-white p-3 rounded-lg'>
                <p class='text-2xl font-bold text-green-600'>$successCount</p>
                <p class='text-xs text-gray-600'>Créés</p>
            </div>
            <div class='bg-white p-3 rounded-lg'>
                <p class='text-2xl font-bold text-yellow-600'>$skippedCount</p>
                <p class='text-xs text-gray-600'>Mis à jour</p>
            </div>
            <div class='bg-white p-3 rounded-lg'>
                <p class='text-2xl font-bold text-red-600'>$errorCount</p>
                <p class='text-xs text-gray-600'>Erreurs</p>
            </div>
        </div>
      </div>";

// Tableau récapitulatif
echo "<div class='mt-6 bg-gray-50 rounded-lg p-4'>
        <h2 class='font-bold text-gray-800 mb-3'><i class='fas fa-key mr-2'></i>Identifiants de connexion</h2>
        <div class='overflow-x-auto'>
            <table class='w-full text-sm'>
                <thead class='bg-gray-200'>
                    <tr>
                        <th class='px-4 py-2 text-left'>Rôle</th>
                        <th class='px-4 py-2 text-left'>Email</th>
                        <th class='px-4 py-2 text-left'>Mot de passe</th>
                    </tr>
                </thead>
                <tbody>";

foreach ($demoAccounts as $account) {
    echo "<tr class='border-b border-gray-200'>
            <td class='px-4 py-2 font-semibold'>{$account['icon']} " . ucfirst($account['role']) . "</td>
            <td class='px-4 py-2'><code class='bg-white px-2 py-1 rounded'>{$account['email']}</code></td>
            <td class='px-4 py-2'><code class='bg-white px-2 py-1 rounded'>{$account['password']}</code></td>
          </tr>";
}

echo "      </tbody>
            </table>
        </div>
      </div>";

// Avertissement sécurité
echo "<div class='mt-6 p-4 bg-red-50 border border-red-200 rounded-lg'>
        <h2 class='font-bold text-red-900 mb-2'><i class='fas fa-exclamation-triangle mr-2'></i>⚠️ Sécurité</h2>
        <ul class='text-sm text-red-800 space-y-1'>
            <li>• <strong>Supprimez ce fichier</strong> après utilisation en production</li>
            <li>• <strong>Changez les mots de passe</strong> avant la mise en production</li>
            <li>• <strong>Ne jamais utiliser</strong> ces identifiants sur un site public</li>
        </ul>
      </div>";

echo "<div class='mt-6 flex gap-3'>
        <a href='login.php' class='flex-1 py-3 bg-blue-600 text-white rounded-lg font-semibold text-center hover:bg-blue-800'>
            <i class='fas fa-sign-in-alt mr-2'></i>Aller à la connexion
        </a>
        <a href='index.php' class='flex-1 py-3 bg-gray-200 text-gray-800 rounded-lg font-semibold text-center hover:bg-gray-300'>
            <i class='fas fa-home mr-2'></i>Accueil
        </a>
      </div>";

echo "</div></body></html>";