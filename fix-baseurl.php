<?php
/**
 * 🔧 Correction forcée de BASE_URL
 */

$configFile = __DIR__ . '/config/constants.php';

echo "<h1>🔧 Correction de BASE_URL</h1>";

if (!file_exists($configFile)) {
    die("❌ Fichier introuvable : $configFile");
}

// Nouvelle URL correcte
$newBaseUrl = 'http://localhost/pekdev/pekdevmarket';

// Lire le contenu actuel
$content = file_get_contents($configFile);
echo "<h3>📄 Avant :</h3><pre style='background:#fee2e2;padding:10px;border-radius:5px;'>";
echo htmlspecialchars($content);
echo "</pre>";

// Remplacer TOUTES les définitions de BASE_URL
$content = preg_replace(
    "/define\s*\(\s*['\"]BASE_URL['\"]\s*,\s*['\"][^'\"]*['\"]\s*\)\s*;/",
    "define('BASE_URL', '$newBaseUrl');",
    $content
);

// Écrire le fichier
file_put_contents($configFile, $content);

echo "<h3>📄 Après :</h3><pre style='background:#d1fae5;padding:10px;border-radius:5px;'>";
echo htmlspecialchars($content);
echo "</pre>";

// Vérification
require_once $configFile;

if (BASE_URL === $newBaseUrl) {
    echo "<div style='background:#d1fae5;padding:20px;border-radius:10px;margin:20px 0;border:2px solid #10b981;'>";
    echo "<h2 style='color:#065f46;'>✅ BASE_URL corrigée avec succès !</h2>";
    echo "<p><strong>Nouvelle valeur :</strong> <code>$newBaseUrl</code></p>";
    echo "</div>";
} else {
    echo "<div style='background:#fee2e2;padding:20px;border-radius:10px;margin:20px 0;border:2px solid #ef4444;'>";
    echo "<h2 style='color:#991b1b;'>❌ Échec de la correction</h2>";
    echo "<p>Valeur actuelle : <code>" . BASE_URL . "</code></p>";
    echo "</div>";
}

echo "<h3>🎯 URLs à utiliser maintenant :</h3>";
echo "<ul style='font-size:16px;'>";
echo "<li>🏠 <a href='$newBaseUrl/'>$newBaseUrl/</a></li>";
echo "<li>🔐 <a href='$newBaseUrl/login.php'>$newBaseUrl/login.php</a></li>";
echo "<li>📱 <a href='$newBaseUrl/product.php?slug=samsung-galaxy-a14'>$newBaseUrl/product.php?slug=samsung-galaxy-a14</a></li>";
echo "</ul>";

echo "<p style='color:red;font-weight:bold;'>⚠️ SUPPRIMEZ CE FICHIER (fix-baseurl.php) APRÈS CORRECTION !</p>";