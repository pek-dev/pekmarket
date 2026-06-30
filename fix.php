<?php
/**
 * 🎯 Déplace tout vers le dossier courant et corrige les URLs
 */

require_once __DIR__ . '/includes/header.php';

$source = __DIR__ . '/pekdev-market';
$destination = __DIR__;

echo "<h1>🔄 Déplacement des fichiers</h1>";

if (!is_dir($source)) {
    die("❌ Le dossier pekdev-market/ n'existe pas. Tout est déjà en place !");
}

// Déplacer tous les fichiers
function moveAll($src, $dst) {
    $count = 0;
    $dir = opendir($src);
    
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..' || $file === 'fix.php') continue;
        
        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;
        
        if (is_dir($srcPath)) {
            if (!is_dir($dstPath)) mkdir($dstPath, 0755, true);
            $count += moveAll($srcPath, $dstPath);
            @rmdir($srcPath);
        } else {
            if (rename($srcPath, $dstPath)) {
                $count++;
                echo "✓ $file<br>";
            }
        }
    }
    closedir($dir);
    return $count;
}

$count = moveAll($source, $destination);
@rmdir($source);

echo "<h2>✅ $count fichiers déplacés</h2>";

// Corriger BASE_URL dans config/constants.php
$configFile = $destination . '/config/constants.php';
if (file_exists($configFile)) {
    $content = file_get_contents($configFile);
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $newUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
    $newUrl = rtrim($newUrl, '/');
    
    $content = preg_replace("/define\('BASE_URL',\s*'[^']*'\);/", "define('BASE_URL', '$newUrl');", $content);
    file_put_contents($configFile, $content);
    
    echo "<h3>✓ BASE_URL mise à jour : $newUrl</h3>";
}

echo "<hr>";
echo "<h2>🎉 Terminé !</h2>";
echo "<p><strong>Vos URLs :</strong></p>";
echo "<ul>";
echo "<li>🏠 Accueil : <a href='$newUrl/'>$newUrl/</a></li>";
echo "<li>🔐 Connexion : <a href='$newUrl/login.php'>$newUrl/login.php</a></li>";
echo "<li>📱 Produit : <a href='$newUrl/product.php?slug=samsung-galaxy-a14'>$newUrl/product.php?slug=samsung-galaxy-a14</a></li>";
echo "</ul>";
echo "<p style='color:red;'><strong>⚠️ SUPPRIMEZ CE FICHIER (fix.php) MAINTENANT !</strong></p>";