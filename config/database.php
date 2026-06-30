<?php
// Configuration base de données - PekDev Market
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=pekdev_marketog;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Erreur BDD: " . $e->getMessage());
}