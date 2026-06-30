<?php
/**
 * Script de configuration automatique
 * À exécuter UNE SEULE FOIS après avoir copié les fichiers
 */
require_once __DIR__ . '/includes/header.php';
echo "<h1>🔧 Configuration PekDev Market</h1>";

// Créer les dossiers
$dirs = [
    __DIR__ . '/uploads',
    __DIR__ . '/uploads/products',
    __DIR__ . '/uploads/avatars',
    __DIR__ . '/uploads/temp',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✅ Dossier créé : $dir<br>";
        } else {
            echo "❌ Erreur création : $dir<br>";
        }
    } else {
        echo "✓ Existe déjà : $dir<br>";
    }
}

// Vérifier permissions
echo "<h2>Vérification des permissions</h2>";
foreach ($dirs as $dir) {
    if (is_writable($dir)) {
        echo "✅ Writable : $dir<br>";
    } else {
        echo "⚠️ Non-writable : $dir (chmod 777)<br>";
    }
}

// Vérifier extensions PHP
echo "<h2>Extensions PHP</h2>";
$extensions = ['pdo', 'pdo_mysql', 'gd', 'json', 'mbstring'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext chargé<br>";
    } else {
        echo "❌ $ext manquant<br>";
    }
}

echo "<h2>🎉 Configuration terminée !</h2>";
echo "<p>Accédez à <a href='install.php'>install.php</a> pour finaliser l'installation.</p>";