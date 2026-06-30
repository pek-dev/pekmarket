<?php
$files = [
    'dashboard/admin.php',
    'dashboard/seller.php',
    'dashboard/customer.php',
    'admin/users.php',
    'admin/products.php',
    'admin/categories.php',
    'admin/orders.php',
    'admin/subscriptions.php',
    'admin/ads.php',
    'admin/sponsored.php',
    'admin/reviews.php',
    'admin/revenue.php',
    'admin/export-users.php',
    'admin/ads-action.php'
];

$fixed = 0;

foreach ($files as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (!file_exists($fullPath)) {
        echo "❌ $file n'existe pas<br>";
        continue;
    }
    
    $content = file_get_contents($fullPath);
    $original = $content;
    
    // Corriger les require_once incorrects
    $replacements = [
        // Header
        "/require_once\s+['\"]includes\/header\.php['\"]\s*;?/" => "require_once __DIR__ . '/../includes/header.php';",
        "/require_once\s+['\"]\.\.\/includes\/header\.php['\"]\s*;?/" => "require_once __DIR__ . '/../includes/header.php';",
        
        // Footer
        "/require_once\s+['\"]includes\/footer\.php['\"]\s*;?/" => "require_once __DIR__ . '/../includes/footer.php';",
        "/require_once\s+['\"]\.\.\/includes\/footer\.php['\"]\s*;?/" => "require_once __DIR__ . '/../includes/footer.php';",
        
        // Config
        "/require_once\s+['\"]config\/constants\.php['\"]\s*;?/" => "require_once __DIR__ . '/../config/constants.php';",
        "/require_once\s+['\"]config\/database\.php['\"]\s*;?/" => "require_once __DIR__ . '/../config/database.php';",
        "/require_once\s+['\"]config\/functions\.php['\"]\s*;?/" => "require_once __DIR__ . '/../config/functions.php';",
        "/require_once\s+['\"]config\/bootstrap\.php['\"]\s*;?/" => "require_once __DIR__ . '/../config/bootstrap.php';",
        
        // Includes
        "/require_once\s+['\"]includes\/auth\.php['\"]\s*;?/" => "require_once __DIR__ . '/../includes/auth.php';",
        "/require_once\s+['\"]includes\/functions\.php['\"]\s*;?/" => "require_once __DIR__ . '/../includes/functions.php';",
    ];
    
    foreach ($replacements as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }
    
    // Nettoyer les lignes vides multiples
    $content = preg_replace('/\n{3,}/', "\n\n", $content);
    
    if ($content !== $original) {
        file_put_contents($fullPath, $content);
        $fixed++;
        echo "✅ $file corrigé<br>";
    } else {
        echo "➖ $file déjà correct<br>";
    }
}

echo "<hr><strong>✅ $fixed fichiers corrigés</strong><br>";
echo "<p style='color:red;'><strong>⚠️ SUPPRIMEZ ce fichier maintenant !</strong></p>";