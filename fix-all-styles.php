<?php
require_once __DIR__ . '/config/bootstrap.php';

/**
 * Script de correction automatique des require_once
 * Remplace les chemins relatifs par des chemins absolus avec __DIR__
 * 
 * ️ À exécuter UNE SEULE FOIS puis SUPPRIMER
 */

$root = __DIR__;
$fixed = 0;
$total = 0;
$filesModified = [];

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Includes - PekDev Market</title>
    <script src='https://cdn.tailwindcss.com'></script>
</head>
<body class='bg-gray-100 p-8'>
<div class='max-w-4xl mx-auto bg-white rounded-xl shadow-lg p-8'>
<h1 class='text-3xl font-bold text-blue-600 mb-4'>🔧 Correction automatique des require_once</h1>
<p class='text-gray-600 mb-6'>Remplacement des chemins relatifs par des chemins absolus avec __DIR__</p>
<hr class='mb-6'>";

// Scanner récursivement tous les fichiers PHP
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') continue;
    
    // Exclure ce script et bootstrap
    if (in_array($file->getFilename(), ['fix-all-includes.php', 'bootstrap.php'])) continue;
    
    $filePath = $file->getPathname();
    $relativePath = str_replace($root . '/', '', $filePath);
    $total++;
    
    $content = file_get_contents($filePath);
    $original = $content;
    $changes = [];
    
    // Calculer le chemin relatif vers la racine du projet
    $fileDir = dirname($filePath);
    $depth = substr_count(str_replace($root, '', $fileDir), '/');
    $prefix = str_repeat('../', $depth);
    
    // Patterns à remplacer
    $patterns = [
        // Fichiers à la racine (includes, config)
        "/require_once\s+['\"]includes\/header\.php['\"]\s*;?/" => "require_once __DIR__ . '/includes/header.php';",
        "/require_once\s+['\"]includes\/footer\.php['\"]\s*;?/" => "require_once __DIR__ . '/includes/footer.php';",
        "/require_once\s+['\"]includes\/auth\.php['\"]\s*;?/" => "require_once __DIR__ . '/includes/auth.php';",
        "/require_once\s+['\"]includes\/functions\.php['\"]\s*;?/" => "require_once __DIR__ . '/includes/functions.php';",
        "/require_once\s+['\"]config\/constants\.php['\"]\s*;?/" => "require_once __DIR__ . '/config/constants.php';",
        "/require_once\s+['\"]config\/database\.php['\"]\s*;?/" => "require_once __DIR__ . '/config/database.php';",
        "/require_once\s+['\"]config\/functions\.php['\"]\s*;?/" => "require_once __DIR__ . '/config/functions.php';",
        "/require_once\s+['\"]config\/bootstrap\.php['\"]\s*;?/" => "require_once __DIR__ . '/config/bootstrap.php';",
        
        // Fichiers dans sous-dossiers (admin, dashboard, seller, api)
        "/require_once\s+['\"]\.\.\/includes\/header\.php['\"]\s*;?/" => "",
        "/require_once\s+['\"]\.\.\/includes\/footer\.php['\"]\s*;?/" => "",
        "/require_once\s+['\"]\.\.\/includes\/auth\.php['\"]\s*;?/" => "",
        "/require_once\s+['\"]\.\.\/includes\/functions\.php['\"]\s*;?/" => "",
        "/require_once\s+['\"]\.\.\/config\/constants\.php['\"]\s*;?/" => "",
        "/require_once\s+['\"]\.\.\/config\/database\.php['\"]\s*;?/" => "",
        "/require_once\s+['\"]\.\.\/config\/functions\.php['\"]\s*;?/" => "",
        "/require_once\s+['\"]\.\.\/config\/bootstrap\.php['\"]\s*;?/" => "require_once __DIR__ . '/../config/bootstrap.php';",
    ];
    
    // Appliquer les remplacements
    foreach ($patterns as $pattern => $replacement) {
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $replacement, $content);
            $changes[] = $pattern;
        }
    }
    
    // Si des modifications ont été faites
    if ($content !== $original) {
        file_put_contents($filePath, $content);
        $fixed++;
        $filesModified[] = [
            'file' => $relativePath,
            'changes' => $changes
        ];
        
        echo "<div class='mb-3 p-3 bg-green-50 border-l-4 border-green-500 rounded'>";
        echo "<p class='font-semibold text-green-800'>✅ $relativePath</p>";
        echo "<ul class='text-xs text-gray-600 mt-1 ml-4'>";
        foreach ($changes as $change) {
            echo "<li>$change</li>";
        }
        echo "</ul></div>";
    }
}

echo "<hr class='my-6'>";
echo "<div class='bg-blue-50 border-l-4 border-blue-500 p-4 rounded'>";
echo "<h2 class='text-xl font-bold text-blue-800 mb-2'>📊 Résumé</h2>";
echo "<p class='text-gray-700'>Fichiers scannés : <strong>$total</strong></p>";
echo "<p class='text-gray-700'>Fichiers modifiés : <strong class='text-green-600'>$fixed</strong></p>";
echo "</div>";

if ($fixed > 0) {
    echo "<div class='mt-6 bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded'>";
    echo "<h3 class='font-bold text-yellow-800 mb-2'>⚠️ IMPORTANT</h3>";
    echo "<ol class='list-decimal list-inside text-gray-700 space-y-1'>";
    echo "<li><strong>SUPPRIMEZ ce fichier</strong> (fix-all-includes.php) maintenant !</li>";
    echo "<li>Videz le cache : <kbd>Ctrl + Shift + R</kbd></li>";
    echo "<li>Testez toutes les pages</li>";
    echo "</ol></div>";
} else {
    echo "<div class='mt-6 bg-green-50 border-l-4 border-green-500 p-4 rounded'>";
    echo "<p class='text-green-800 font-semibold'>✅ Tous les fichiers sont déjà corrects !</p>";
    echo "</div>";
}

echo "</div></body></html>";