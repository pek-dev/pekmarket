<?php
// Fichier : C:\xampp\htdocs\setup-folders.php
require_once __DIR__ . '/includes/header.php';

$baseDir = __DIR__ . '/pekdev-market';

// Créer le dossier principal
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0755, true);
    echo "✅ Dossier créé : $baseDir<br>";
} else {
    echo "✓ Dossier existe déjà : $baseDir<br>";
}

// Créer tous les sous-dossiers
$dirs = [
    '/config',
    '/includes',
    '/assets',
    '/assets/css',
    '/assets/js',
    '/uploads',
    '/uploads/products',
    '/uploads/avatars',
    '/uploads/temp',
    '/admin',
    '/api',
];

foreach ($dirs as $dir) {
    $fullPath = $baseDir . $dir;
    if (!is_dir($fullPath)) {
        if (mkdir($fullPath, 0755, true)) {
            echo "✅ Créé : $dir<br>";
        }
    } else {
        echo "✓ Existe : $dir<br>";
    }
}

// Créer un fichier index.php de test
$testFile = $baseDir . '/index.php';
if (!file_exists($testFile)) {
    $content = '<?php echo "<h1>🎉 PekDev Market fonctionne !</h1>"; phpinfo(); ?>';
    file_put_contents($testFile, $content);
    echo "✅ Fichier index.php créé<br>";
}

// Créer diagnostic.php
$diagFile = $baseDir . '/diagnostic.php';
$diagContent = <<<'PHP'
<?php
echo "<h1>✅ Diagnostic OK</h1>";
echo "<p>PHP fonctionne : " . PHP_VERSION . "</p>";
echo "<p>Dossier actuel : " . __DIR__ . "</p>";
echo "<p>Document Root : " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<hr>";
echo "<h2>Fichiers présents :</h2><ul>";
foreach (scandir(__DIR__) as $file) {
    if ($file != '.' && $file != '..') {
        $type = is_dir(__DIR__ . '/' . $file) ? '📁' : '📄';
        echo "<li>$type <strong>$file</strong></li>";
    }
}
echo "</ul>";
PHP;
file_put_contents($diagFile, $diagContent);
echo "✅ Fichier diagnostic.php créé<br>";

echo "<hr>";
echo "<h2>🎉 Installation terminée !</h2>";
echo "<p>Testez maintenant : <a href='http://localhost/pekdev-market/'>http://localhost/pekdev-market/</a></p>";
echo "<p>Diagnostic : <a href='http://localhost/pekdev-market/diagnostic.php'>http://localhost/pekdev-market/diagnostic.php</a></p>";