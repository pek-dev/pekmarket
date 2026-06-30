<?php
require_once __DIR__ . '/config/bootstrap.php';

echo "<h2>🔍 Diagnostic CSS</h2>";

// 1. Vérifier que ASSETS_URL est défini
echo "<h3>1. Constante ASSETS_URL</h3>";
if (defined('ASSETS_URL')) {
    echo "<p style='color:green;'>✅ ASSETS_URL = <code>" . ASSETS_URL . "</code></p>";
} else {
    echo "<p style='color:red;'> ASSETS_URL n'est pas défini !</p>";
}

// 2. Vérifier que le fichier CSS existe physiquement
echo "<h3>2. Fichier CSS physique</h3>";
$cssPath = __DIR__ . '/assets/css/style.css';
if (file_exists($cssPath)) {
    $size = filesize($cssPath);
    echo "<p style='color:green;'>✅ Le fichier existe ($size octets)</p>";
    echo "<p><a href='" . ASSETS_URL . "/css/style.css' target='_blank'>👉 Voir le fichier CSS</a></p>";
} else {
    echo "<p style='color:red;'>❌ Le fichier n'existe pas à : <code>$cssPath</code></p>";
    echo "<p>Créez le dossier et le fichier :</p>";
    echo "<pre>mkdir -p " . dirname($cssPath) . "</pre>";
}

// 3. Vérifier le lien dans le HTML
echo "<h3>3. Lien HTML généré</h3>";
$link = ASSETS_URL . '/css/style.css';
echo "<p>Le lien sera : <code>$link</code></p>";
echo "<p><a href='$link' target='_blank'> Tester le lien</a></p>";

// 4. Vérifier les permissions
echo "<h3>4. Permissions du dossier assets</h3>";
$assetsDir = __DIR__ . '/assets';
if (is_dir($assetsDir)) {
    echo "<p style='color:green;'>✅ Le dossier assets existe</p>";
    echo "<p>Permissions : " . substr(sprintf('%o', fileperms($assetsDir)), -4) . "</p>";
} else {
    echo "<p style='color:red;'>❌ Le dossier assets n'existe pas</p>";
}

// 5. Tester le chargement
echo "<h3>5. Test de chargement</h3>";
echo "<link rel='stylesheet' href='$link'>";
echo "<div style='background: linear-gradient(135deg, #1e40af 0%, #f97316 100%); color: white; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
echo "<h2>✅ Si vous voyez ce dégradé bleu/orange, le CSS fonctionne !</h2>";
echo "</div>";

echo "<hr>";
echo "<h3>📋 Instructions</h3>";
echo "<ol>";
echo "<li>Si le lien 'Voir le fichier CSS' fonctionne → Le problème est dans header.php</li>";
echo "<li>Si le lien ne fonctionne pas → Le fichier n'est pas au bon endroit</li>";
echo "<li>Si le dégradé s'affiche → Le CSS fonctionne, problème de cache navigateur</li>";
echo "</ol>";