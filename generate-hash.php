<?php
// Génère un hash pour "admin123"
$password = "admin123";
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>Hash pour 'admin123' :</h2>";
echo "<code style='background:#f3f4f6;padding:10px;display:block;word-break:break-all;'>$hash</code>";
echo "<br><br>";
echo "<h3>Requête SQL :</h3>";
echo "<code style='background:#f3f4f6;padding:10px;display:block;word-break:break-all;'>";
echo "UPDATE users SET password = '$hash' WHERE email IN ('admin@pekdev.bi', 'vendeur@pekdev.bi', 'client@pekdev.bi');";
echo "</code>";
?>