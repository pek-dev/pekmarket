<?php
require_once __DIR__ . '/config/bootstrap.php';
/**
 * Script de diagnostic PekDev Market
 * À supprimer après utilisation !
 */


echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostic PekDev Market</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
</head>
<body class='bg-gray-100 p-8'>
<div class='max-w-4xl mx-auto bg-white rounded-2xl shadow-xl p-8'>
    <h1 class='text-3xl font-bold text-gray-800 mb-6'>
        <i class='fas fa-stethoscope text-blue-600 mr-2'></i>Diagnostic PekDev Market
    </h1>";

$checks = [];

// 1. Vérifier PHP
$checks[] = [
    'label' => 'Version PHP >= 7.4',
    'ok' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'info' => 'PHP ' . PHP_VERSION
];

// 2. Vérifier les extensions
$extensions = ['pdo', 'pdo_mysql', 'gd', 'json', 'mbstring'];
foreach ($extensions as $ext) {
    $checks[] = [
        'label' => "Extension PHP : $ext",
        'ok' => extension_loaded($ext),
        'info' => extension_loaded($ext) ? 'Chargée' : 'Manquante'
    ];
}

// 3. Vérifier les fichiers essentiels
$files = [
    'config/database.php' => 'Configuration BDD',
    'config/constants.php' => 'Constantes',
    'includes/header.php' => 'Header',
    'includes/footer.php' => 'Footer',
    'includes/functions.php' => 'Fonctions',
    'includes/auth.php' => 'Authentification',
    'index.php' => 'Page d\'accueil',
    'product.php' => 'Fiche produit',
    'login.php' => 'Connexion',
    'register.php' => 'Inscription',
];

foreach ($files as $file => $label) {
    $exists = file_exists(__DIR__ . '/' . $file);
    $checks[] = [
        'label' => "Fichier : $label",
        'ok' => $exists,
        'info' => $exists ? "✓ $file" : "✗ $file manquant"
    ];
}

// 4. Vérifier les dossiers
$dirs = ['uploads', 'uploads/products', 'uploads/avatars'];
foreach ($dirs as $dir) {
    $exists = is_dir(__DIR__ . '/' . $dir);
    $writable = $exists && is_writable(__DIR__ . '/' . $dir);
    $checks[] = [
        'label' => "Dossier : $dir",
        'ok' => $writable,
        'info' => !$exists ? 'Manquant' : ($writable ? 'Writable' : 'Non-writable')
    ];
}

// 5. Vérifier la connexion BDD
try {
    require_once __DIR__ . '/config/constants.php';
    require_once __DIR__ . '/config/database.php';
    
    $pdo->query("SELECT 1");
    $checks[] = [
        'label' => 'Connexion MySQL',
        'ok' => true,
        'info' => 'Connectée'
    ];
    
    // Vérifier les tables
    $tables = ['users', 'products', 'categories', 'orders'];
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            $checks[] = [
                'label' => "Table : $table",
                'ok' => true,
                'info' => "$count enregistrements"
            ];
        } catch (Exception $e) {
            $checks[] = [
                'label' => "Table : $table",
                'ok' => false,
                'info' => 'Inexistante'
            ];
        }
    }
    
    // Vérifier le produit spécifique
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE slug = 'samsung-galaxy-a14'");
    $exists = $stmt->fetchColumn() > 0;
    $checks[] = [
        'label' => 'Produit samsung-galaxy-a14',
        'ok' => $exists,
        'info' => $exists ? 'Existe en BDD' : 'N\'existe pas'
    ];
    
} catch (Exception $e) {
    $checks[] = [
        'label' => 'Connexion MySQL',
        'ok' => false,
        'info' => 'Erreur : ' . $e->getMessage()
    ];
}

// 6. Vérifier mod_rewrite
$checks[] = [
    'label' => 'Apache mod_rewrite',
    'ok' => function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules()),
    'info' => function_exists('apache_get_modules') 
        ? (in_array('mod_rewrite', apache_get_modules()) ? 'Activé' : 'Désactivé')
        : 'Non détectable (pas Apache)'
];

// Afficher les résultats
$okCount = count(array_filter($checks, fn($c) => $c['ok']));
$totalCount = count($checks);

echo "<div class='mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg'>
        <p class='font-semibold text-blue-900'>
            <i class='fas fa-chart-pie mr-2'></i>Score : $okCount / $totalCount vérifications réussies
        </p>
      </div>";

echo "<div class='space-y-2'>";
foreach ($checks as $check) {
    $bg = $check['ok'] ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
    $icon = $check['ok'] ? 'fa-check-circle text-green-500' : 'fa-times-circle text-red-500';
    $textColor = $check['ok'] ? 'text-green-800' : 'text-red-800';
    
    echo "<div class='flex items-center justify-between p-3 border rounded-lg $bg'>
            <div class='flex items-center gap-3'>
                <i class='fas $icon text-xl'></i>
                <span class='font-medium $textColor'>{$check['label']}</span>
            </div>
            <span class='text-sm $textColor'>{$check['info']}</span>
          </div>";
}
echo "</div>";

// Informations serveur
echo "<div class='mt-8 p-4 bg-gray-50 rounded-lg'>
        <h2 class='font-bold text-gray-800 mb-3'><i class='fas fa-server mr-2'></i>Informations serveur</h2>
        <div class='grid md:grid-cols-2 gap-2 text-sm'>
            <div><strong>OS :</strong> " . PHP_OS . "</div>
            <div><strong>Server Software :</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "</div>
            <div><strong>Document Root :</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "</div>
            <div><strong>Script Filename :</strong> " . (__FILE__) . "</div>
            <div><strong>BASE_URL :</strong> " . (defined('BASE_URL') ? BASE_URL : 'Non défini') . "</div>
            <div><strong>PHP SAPI :</strong> " . php_sapi_name() . "</div>
        </div>
      </div>";

// Recommendations
echo "<div class='mt-8 p-4 bg-yellow-50 border border-yellow-200 rounded-lg'>
        <h2 class='font-bold text-yellow-900 mb-3'><i class='fas fa-lightbulb mr-2'></i>Recommandations</h2>
        <ul class='space-y-2 text-sm text-yellow-800'>";

if (!file_exists(__DIR__ . '/product.php')) {
    echo "<li>❌ Le fichier <code>product.php</code> est manquant. Créez-le à la racine.</li>";
}
if (!is_dir(__DIR__ . '/uploads')) {
    echo "<li>❌ Le dossier <code>uploads/</code> est manquant. Créez-le avec les sous-dossiers.</li>";
}
if (file_exists(__DIR__ . '/.htaccess')) {
    echo "<li>⚠️ Renommez temporairement <code>.htaccess</code> en <code>.htaccess.bak</code> pour tester.</li>";
}

echo "<li>✅ Accédez à <a href='http://localhost/pekdev-market/' class='text-blue-600 underline'>http://localhost/pekdev-market/</a></li>";
echo "<li>✅ Si ça ne marche pas, vérifiez que Apache est démarré dans XAMPP</li>";
echo "<li>✅ Consultez les logs : <code>C:\\xampp\\apache\\logs\\error.log</code></li>";
echo "</ul></div>";

echo "</div></body></html>";