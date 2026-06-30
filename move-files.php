<?php
require_once __DIR__ . '/config/bootstrap.php';
/**
 * 🔄 Déplacer tous les fichiers de pekdev-market/ vers le dossier courant
 */
require_once __DIR__ . '/includes/header.php';

$source = __DIR__ . '/pekdev-market';
$destination = __DIR__;

if (!is_dir($source)) {
    die("❌ Le dossier source n'existe pas : $source");
}

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>Déplacement des fichiers</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap' rel='stylesheet'>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class='bg-gray-100 min-h-screen p-8'>
<div class='max-w-3xl mx-auto bg-white rounded-2xl shadow-xl p-8'>";

echo "<h1 class='text-3xl font-bold text-gray-800 mb-6'>
        <i class='fas fa-arrows-alt text-blue-600 mr-2'></i>Déplacement des fichiers
      </h1>";

// Fonction récursive pour déplacer
function moveDirectory($src, $dst) {
    $moved = 0;
    $errors = 0;
    
    $dir = opendir($src);
    if (!$dir) {
        return ['moved' => 0, 'errors' => 1];
    }
    
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') continue;
        
        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;
        
        // Ne pas écraser ce script
        if (basename($srcPath) === 'move-files.php') continue;
        
        if (is_dir($srcPath)) {
            $result = moveDirectory($srcPath, $dstPath);
            $moved += $result['moved'];
            $errors += $result['errors'];
            @rmdir($srcPath);
        } else {
            if (rename($srcPath, $dstPath)) {
                $moved++;
            } else {
                // Si rename échoue, copier puis supprimer
                if (copy($srcPath, $dstPath)) {
                    unlink($srcPath);
                    $moved++;
                } else {
                    $errors++;
                }
            }
        }
    }
    
    closedir($dir);
    return ['moved' => $moved, 'errors' => $errors];
}

$result = moveDirectory($source, $destination);

// Supprimer le dossier source vide
@rmdir($source);

echo "<div class='space-y-3'>";

if ($result['errors'] === 0) {
    echo "<div class='flex items-center gap-3 p-4 bg-green-50 border border-green-200 rounded-lg'>
            <i class='fas fa-check-circle text-green-500 text-3xl'></i>
            <div>
                <p class='font-bold text-green-900 text-lg'>✅ Déplacement réussi !</p>
                <p class='text-sm text-green-700'>{$result['moved']} fichiers déplacés</p>
            </div>
          </div>";
} else {
    echo "<div class='flex items-center gap-3 p-4 bg-yellow-50 border border-yellow-200 rounded-lg'>
            <i class='fas fa-exclamation-triangle text-yellow-500 text-3xl'></i>
            <div>
                <p class='font-bold text-yellow-900 text-lg'>⚠️ Déplacement partiel</p>
                <p class='text-sm text-yellow-700'>{$result['moved']} déplacés, {$result['errors']} erreurs</p>
            </div>
          </div>";
}

// Vérifier les fichiers importants
echo "<h2 class='font-bold text-gray-800 mt-6 mb-3'><i class='fas fa-folder-open mr-2'></i>Vérification des fichiers</h2>";
echo "<div class='space-y-2'>";

$importantFiles = [
    'index.php' => 'Page d\'accueil',
    'login.php' => 'Connexion',
    'product.php' => 'Fiche produit',
    'config/database.php' => 'Configuration BDD',
    'includes/functions.php' => 'Fonctions',
];

foreach ($importantFiles as $file => $desc) {
    $exists = file_exists($destination . '/' . $file);
    $icon = $exists ? 'check-circle text-green-500' : 'times-circle text-red-500';
    $bg = $exists ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
    
    echo "<div class='flex items-center gap-3 p-3 border rounded-lg $bg'>
            <i class='fas fa-$icon'></i>
            <span class='flex-1 font-medium'>$desc</span>
            <code class='text-xs bg-white px-2 py-1 rounded'>$file</code>
          </div>";
}

echo "</div>";

// Nouvelle URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$newUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
$newUrl = rtrim($newUrl, '/');

echo "<div class='mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4'>
        <h2 class='font-bold text-blue-900 mb-3'><i class='fas fa-globe mr-2'></i>Nouvelles URLs</h2>
        <div class='space-y-2'>
            <div class='bg-white p-3 rounded'>
                <p class='text-xs text-gray-500 mb-1'>🏠 Accueil</p>
                <a href='$newUrl/' class='text-blue-600 font-semibold hover:underline break-all'>$newUrl/</a>
            </div>
            <div class='bg-white p-3 rounded'>
                <p class='text-xs text-gray-500 mb-1'>🔐 Connexion</p>
                <a href='$newUrl/login.php' class='text-blue-600 font-semibold hover:underline break-all'>$newUrl/login.php</a>
            </div>
            <div class='bg-white p-3 rounded'>
                <p class='text-xs text-gray-500 mb-1'>📱 Produit Samsung</p>
                <a href='$newUrl/product.php?slug=samsung-galaxy-a14' class='text-blue-600 font-semibold hover:underline break-all'>$newUrl/product.php?slug=samsung-galaxy-a14</a>
            </div>
        </div>
      </div>";

echo "<div class='mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4'>
        <h2 class='font-bold text-yellow-900 mb-2'><i class='fas fa-exclamation-triangle mr-2'></i>Actions requises</h2>
        <ol class='list-decimal list-inside space-y-1 text-sm text-yellow-800'>
            <li><strong>SUPPRIMEZ</strong> ce fichier : <code class='bg-yellow-100 px-2 py-0.5 rounded'>move-files.php</code></li>
            <li><strong>SUPPRIMEZ</strong> le dossier vide : <code class='bg-yellow-100 px-2 py-0.5 rounded'>pekdev-market/</code></li>
            <li><strong>Testez</strong> les nouvelles URLs ci-dessus</li>
        </ol>
      </div>";

echo "<div class='mt-6 flex gap-3'>
        <a href='$newUrl/' class='flex-1 py-3 bg-blue-600 text-white rounded-lg font-semibold text-center hover:bg-blue-700 flex items-center justify-center gap-2'>
            <i class='fas fa-home'></i> Aller au site
        </a>
        <a href='$newUrl/login.php' class='flex-1 py-3 bg-orange-500 text-white rounded-lg font-semibold text-center hover:bg-orange-600 flex items-center justify-center gap-2'>
            <i class='fas fa-sign-in-alt'></i> Se connecter
        </a>
      </div>";

echo "</div></body></html>";