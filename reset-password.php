<?php
/**
 * 🔐 Réinitialisation des mots de passe
 * À SUPPRIMER après utilisation !
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>Réinitialisation - PekDev Market</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap' rel='stylesheet'>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class='bg-gray-100 min-h-screen p-8'>
<div class='max-w-3xl mx-auto bg-white rounded-2xl shadow-xl p-8'>";

echo "<h1 class='text-3xl font-bold text-gray-800 mb-6'>
        <i class='fas fa-key text-blue-600 mr-2'></i>Réinitialisation des mots de passe
      </h1>";

// Comptes à créer/réinitialiser
$accounts = [
    ['Admin', 'PekDev', 'admin@pekdev.bi', 'admin123', 'admin'],
    ['Vendeur', 'Demo', 'vendeur@pekdev.bi', 'admin123', 'seller'],
    ['Jean', 'Mugabo', 'client@pekdev.bi', 'admin123', 'customer'],
];

echo "<div class='space-y-3'>";

foreach ($accounts as $acc) {
    try {
        // Vérifier si l'utilisateur existe
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$acc[2]]);
        $exists = $stmt->fetch();
        
        // Générer le hash du mot de passe
        $hash = password_hash($acc[3], PASSWORD_BCRYPT, ['cost' => 12]);
        
        if ($exists) {
            // Mettre à jour
            $stmt = $pdo->prepare("UPDATE users SET password = ?, role = ?, first_name = ?, last_name = ?, is_active = 1 WHERE email = ?");
            $stmt->execute([$hash, $acc[4], $acc[0], $acc[1], $acc[2]]);
            
            echo "<div class='flex items-center gap-3 p-4 bg-green-50 border border-green-200 rounded-lg'>
                    <i class='fas fa-check-circle text-green-500 text-2xl'></i>
                    <div class='flex-1'>
                        <p class='font-semibold text-green-900'>{$acc[2]}</p>
                        <p class='text-sm text-green-700'>✓ Mot de passe réinitialisé (rôle: {$acc[4]})</p>
                    </div>
                  </div>";
        } else {
            // Créer
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, is_active, is_verified, created_at) VALUES (?, ?, ?, ?, ?, 1, 1, NOW())");
            $stmt->execute([$acc[0], $acc[1], $acc[2], $hash, $acc[4]]);
            
            echo "<div class='flex items-center gap-3 p-4 bg-blue-50 border border-blue-200 rounded-lg'>
                    <i class='fas fa-user-plus text-blue-500 text-2xl'></i>
                    <div class='flex-1'>
                        <p class='font-semibold text-blue-900'>{$acc[2]}</p>
                        <p class='text-sm text-blue-700'>✓ Compte créé (rôle: {$acc[4]})</p>
                    </div>
                  </div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='flex items-center gap-3 p-4 bg-red-50 border border-red-200 rounded-lg'>
                <i class='fas fa-times-circle text-red-500 text-2xl'></i>
                <div class='flex-1'>
                    <p class='font-semibold text-red-900'>{$acc[2]}</p>
                    <p class='text-sm text-red-700'>Erreur: " . htmlspecialchars($e->getMessage()) . "</p>
                </div>
              </div>";
    }
}

echo "</div>";

// Tableau récapitulatif
echo "<div class='mt-6 bg-gray-50 rounded-lg p-4'>
        <h2 class='font-bold text-gray-800 mb-3'><i class='fas fa-table mr-2'></i>Identifiants de connexion</h2>
        <table class='w-full text-sm'>
            <thead class='bg-gray-200'>
                <tr>
                    <th class='px-4 py-2 text-left'>Rôle</th>
                    <th class='px-4 py-2 text-left'>Email</th>
                    <th class='px-4 py-2 text-left'>Mot de passe</th>
                </tr>
            </thead>
            <tbody>";

foreach ($accounts as $acc) {
    $icon = $acc[4] === 'admin' ? '👑' : ($acc[4] === 'seller' ? '🏪' : '👤');
    echo "<tr class='border-b border-gray-200'>
            <td class='px-4 py-2 font-semibold'>$icon " . ucfirst($acc[4]) . "</td>
            <td class='px-4 py-2'><code class='bg-white px-2 py-1 rounded'>{$acc[2]}</code></td>
            <td class='px-4 py-2'><code class='bg-white px-2 py-1 rounded'>{$acc[3]}</code></td>
          </tr>";
}

echo "      </tbody>
        </table>
      </div>";

// Diagnostic
echo "<div class='mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4'>
        <h2 class='font-bold text-blue-900 mb-3'><i class='fas fa-stethoscope mr-2'></i>Diagnostic</h2>";

try {
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "<p class='text-sm text-blue-800'>✓ Nombre total d'utilisateurs : <strong>$count</strong></p>";
    
    foreach ($accounts as $acc) {
        $stmt = $pdo->prepare("SELECT id, role, is_active FROM users WHERE email = ?");
        $stmt->execute([$acc[2]]);
        $user = $stmt->fetch();
        
        if ($user) {
            $status = $user['is_active'] ? '✅ Actif' : '❌ Inactif';
            echo "<p class='text-sm text-blue-800'>✓ {$acc[2]} : ID={$user['id']}, rôle={$user['role']}, $status</p>";
            
            // Tester le mot de passe
            if (password_verify($acc[3], $user['password'] ?? '')) {
                echo "<p class='text-xs text-green-600 ml-6'>✓ Mot de passe correct</p>";
            } else {
                echo "<p class='text-xs text-red-600 ml-6'>❌ Mot de passe incorrect (devrait être corrigé maintenant)</p>";
            }
        } else {
            echo "<p class='text-sm text-red-800'>❌ {$acc[2]} : N'existe pas</p>";
        }
    }
} catch (Exception $e) {
    echo "<p class='text-sm text-red-800'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div>";

// Avertissement
echo "<div class='mt-6 bg-red-50 border border-red-200 rounded-lg p-4'>
        <h2 class='font-bold text-red-900 mb-2'><i class='fas fa-exclamation-triangle mr-2'></i>⚠️ SÉCURITÉ</h2>
        <ul class='text-sm text-red-800 space-y-1'>
            <li>• <strong>SUPPRIMEZ CE FICHIER</strong> après utilisation : <code class='bg-red-100 px-2 py-0.5 rounded'>reset-password.php</code></li>
            <li>• Changez les mots de passe avant la mise en production</li>
        </ul>
      </div>";

// Boutons d'action
echo "<div class='mt-6 flex gap-3'>
        <a href='login.php' class='flex-1 py-3 bg-blue-600 text-white rounded-lg font-semibold text-center hover:bg-blue-700 flex items-center justify-center gap-2'>
            <i class='fas fa-sign-in-alt'></i> Se connecter maintenant
        </a>
        <a href='index.php' class='flex-1 py-3 bg-gray-200 text-gray-800 rounded-lg font-semibold text-center hover:bg-gray-300 flex items-center justify-center gap-2'>
            <i class='fas fa-home'></i> Accueil
        </a>
      </div>";

echo "</div></body></html>";