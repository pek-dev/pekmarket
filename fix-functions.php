<?php
/**
 * 🔧 Ajoute la fonction displayFlash() manquante
 */

require_once __DIR__ . '/includes/header.php';

$functionsFile = __DIR__ . '/includes/functions.php';

echo "<h1>🔧 Correction de functions.php</h1>";

if (!file_exists($functionsFile)) {
    die("❌ Fichier introuvable : $functionsFile");
}

// Fonction à ajouter
$functionCode = '
if (!function_exists("displayFlash")) {
    function displayFlash() {
        if (isset($_SESSION["flash"])) {
            $flash = $_SESSION["flash"];
            unset($_SESSION["flash"]);
            
            $colors = [
                "success" => "green",
                "error" => "red",
                "warning" => "orange",
                "info" => "blue"
            ];
            $icons = [
                "success" => "fa-check-circle",
                "error" => "fa-exclamation-circle",
                "warning" => "fa-exclamation-triangle",
                "info" => "fa-info-circle"
            ];
            
            $color = $colors[$flash["type"]] ?? "blue";
            $icon = $icons[$flash["type"]] ?? "fa-info-circle";
            
            echo "<div class=\"fixed top-24 right-4 z-50 bg-{$color}-500 text-white px-6 py-3 rounded-lg shadow-xl flex items-center gap-3 min-w-[300px] animate-slide-in-right\">
                    <i class=\"fas {$icon}\"></i>
                    <span class=\"font-medium\">" . htmlspecialchars($flash["message"]) . "</span>
                    <button onclick=\"this.parentElement.remove()\" class=\"ml-auto hover:bg-white/20 rounded p-1\">
                        <i class=\"fas fa-times\"></i>
                    </button>
                  </div>";
        }
    }
}
';

// Lire le contenu actuel
$content = file_get_contents($functionsFile);

// Vérifier si la fonction existe déjà
if (strpos($content, 'function displayFlash') !== false) {
    echo "<div style='background:#d1fae5;padding:15px;border-radius:10px;'>";
    echo "<h2 style='color:#065f46;'>✅ La fonction existe déjà !</h2>";
    echo "</div>";
} else {
    // Ajouter la fonction à la fin du fichier
    $content .= "\n" . $functionCode;
    file_put_contents($functionsFile, $content);
    
    echo "<div style='background:#d1fae5;padding:15px;border-radius:10px;'>";
    echo "<h2 style='color:#065f46;'>✅ Fonction displayFlash() ajoutée !</h2>";
    echo "</div>";
}

echo "<h3>🎯 Testez maintenant :</h3>";
echo "<ul>";
echo "<li>🏠 <a href='index.php'>Accueil</a></li>";
echo "<li>🔐 <a href='login.php'>Connexion</a></li>";
echo "<li>📱 <a href='product.php?slug=samsung-galaxy-a14'>Produit</a></li>";
echo "</ul>";

echo "<p style='color:red;font-weight:bold;'>⚠️ SUPPRIMEZ CE FICHIER (fix-functions.php) APRÈS !</p>";