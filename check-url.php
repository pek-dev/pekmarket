<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/header.php';


echo "<h1>🔍 Vérification BASE_URL</h1>";
echo "<div style='background:#f0f9ff;padding:20px;border-radius:10px;margin:20px 0;'>";
echo "<h2>BASE_URL actuelle :</h2>";
echo "<code style='font-size:20px;color:#1e40af;'>" . BASE_URL . "</code>";
echo "</div>";

echo "<h3>✅ URLs à utiliser :</h3>";
echo "<ul style='font-size:16px;'>";
echo "<li>🏠 <a href='" . BASE_URL . "/'>" . BASE_URL . "/</a></li>";
echo "<li>🔐 <a href='" . BASE_URL . "/login.php'>" . BASE_URL . "/login.php</a></li>";
echo "<li>📱 <a href='" . BASE_URL . "/product.php?slug=samsung-galaxy-a14'>" . BASE_URL . "/product.php?slug=samsung-galaxy-a14</a></li>";
echo "</ul>";

if (BASE_URL === 'http://localhost/pekdev/pekdevmarket') {
    echo "<div style='background:#d1fae5;padding:15px;border-radius:10px;margin:20px 0;'>";
    echo "<h3 style='color:#065f46;'>✅ BASE_URL est CORRECT !</h3>";
    echo "</div>";
} else {
    echo "<div style='background:#fee2e2;padding:15px;border-radius:10px;margin:20px 0;'>";
    echo "<h3 style='color:#991b1b;'>❌ BASE_URL est INCORRECT !</h3>";
    echo "<p>Devrait être : <code>http://localhost/pekdev/pekdevmarket</code></p>";
    echo "</div>";
}

echo "<p style='color:red;'><strong>⚠️ SUPPRIMEZ CE FICHIER APRÈS VÉRIFICATION !</strong></p>";