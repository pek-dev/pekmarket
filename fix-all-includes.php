<?php
/**
 * Script de migration automatique
 * Remplace tous les require_once par le bootstrap
 * À exécuter UNE SEULE FOIS puis SUPPRIMER
 */

$root = __DIR__;
$fixed = 0;
$errors = 0;

// Liste des fichiers à corriger
$files = [];

// Scanner récursivement tous les fichiers PHP
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->getExtension() === 'php' && 
        $file->getFilename() !== 'fix-all-includes.php' &&
        $file->getFilename() !== 'bootstrap.php') {
        $files[] = $file->getPathname();
    }
}

echo "<h2>🔧 Migration automatique des includes</h2>";
echo "<p>Found " . count($files) . " PHP files to check</p><hr>";

foreach ($files as $filePath) {
    $content = file_get_contents($filePath);
    $original = $content;
    $relativePath = str_replace($root . '/', '', $filePath);
    
    // Calculer le chemin relatif vers config/bootstrap.php
    $fileDir = dirname($filePath);
    $relToBootstrap = pathinfo(
        str_replace($root . '/config/bootstrap.php', '', $filePath),
        PATHINFO_DIRNAME
    );
    $relToBootstrap = $relToBootstrap === '.' ? '' : $relToBootstrap . '/';
    $bootstrapPath = str_repeat('../', substr_count($relToBootstrap, '/')) . 'config/bootstrap.php';
    
    // Patterns à remplacer (anciens require)
    $patterns = [
        // Fichiers à la racine
        "/require_once\s+['\"]config\/constants\.php['\"]\s*;?/",
        "/require_once\s+['\"]config\/database\.php['\"]\s*;?/",
        "/require_once\s+['\"]config\/functions\.php['\"]\s*;?/",
        "/require_once\s+['\"]includes\/auth\.php['\"]\s*;?/",
        "/require_once\s+['\"]includes\/functions\.php['\"]\s*;?/",
        
        // Fichiers dans sous-dossiers
        "/require_once\s+__DIR__\s*\.\s*['\"]\/\.\.\/config\/constants\.php['\"]\s*;?/",
        "/require_once\s+__DIR__\s*\.\s*['\"]\/\.\.\/config\/database\.php['\"]\s*;?/",
        "/require_once\s+__DIR__\s*\.\s*['\"]\/\.\.\/config\/functions\.php['\"]\s*;?/",
        "/require_once\s+__DIR__\s*\.\s*['\"]\/\.\.\/includes\/auth\.php['\"]\s*;?/",
        "/require_once\s+__DIR__\s*\.\s*['\"]\/\.\.\/includes\/functions\.php['\"]\s*;?/",
        
        // Header et footer
        "/require_once\s+['\"]includes\/header\.php['\"]\s*;?/",
        "/require_once\s+['\"]includes\/footer\.php['\"]\s*;?/",
        "/require_once\s+__DIR__\s*\.\s*['\"]\/\.\.\/includes\/header\.php['\"]\s*;?/",
        "/require_once\s+__DIR__\s*\.\s*['\"]\/\.\.\/includes\/footer\.php['\"]\s*;?/",
    ];
    
    $modified = false;
    
    // Remplacer tous les anciens require par le bootstrap
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, '', $content);
            $modified = true;
        }
    }
    
    // Nettoyer les lignes vides multiples
    $content = preg_replace('/\n{3,}/', "\n\n", $content);
    
    // Ajouter le bootstrap au début si des modifications ont été faites
    if ($modified) {
        // Trouver la première ligne <?php
        if (preg_match('/<\?php/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $phpPos = $matches[0][1] + 5;
            $bootstrapInclude = "\nrequire_once __DIR__ . '/{$bootstrapPath}';\n";
            
            // Pour les fichiers dans sous-dossiers, utiliser __DIR__
            if (dirname($filePath) !== $root) {
                $bootstrapInclude = "\nrequire_once __DIR__ . '/{$bootstrapPath}';\n";
            } else {
                $bootstrapInclude = "\nrequire_once __DIR__ . '/config/bootstrap.php';\n";
            }
            
            $content = substr($content, 0, $phpPos) . $bootstrapInclude . substr($content, $phpPos);
        }
        
        file_put_contents($filePath, $content);
        $fixed++;
        echo "✅ <strong>$relativePath</strong> - Corrigé<br>";
    } else {
        echo "➖ <strong>$relativePath</strong> - Déjà OK<br>";
    }
}

echo "<hr>";
echo "<h3 style='color:green;'>✅ Migration terminée !</h3>";
echo "<p><strong>$fixed</strong> fichiers corrigés sur " . count($files) . " total</p>";
echo "<p style='color:red;'><strong>⚠️ IMPORTANT : Supprimez ce fichier (fix-all-includes.php) maintenant pour des raisons de sécurité !</strong></p>";