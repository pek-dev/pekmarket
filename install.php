<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/includes/header.php';

/**
 * 🚀 PekDev Market - Fichier d'installation automatique
 * Ce fichier crée TOUT automatiquement : structure, BDD, comptes démo
 * 
 * INSTRUCTIONS :
 * 1. Placez ce fichier dans C:\xampp\htdocs\ (ou équivalent)
 * 2. Accédez à http://localhost/pekdev-install.php
 * 3. Tout se fait automatiquement !
 */

// ============================================
// CONFIGURATION
// ============================================
$projectName = 'pekdev-market';
$projectDir = __DIR__ . '/' . $projectName;
$dbName = 'pekdev_market';
$dbUser = 'root';
$dbPass = ''; // Mettez votre mot de passe MySQL si nécessaire

// ============================================
// DÉMARRAGE
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

$logs = [];
$errors = [];

function log_msg($msg, $type = 'success') {
    global $logs;
    $logs[] = ['msg' => $msg, 'type' => $type];
}

function log_error($msg) {
    global $errors;
    $errors[] = $msg;
}

// ============================================
// ÉTAPE 1 : CRÉER LA STRUCTURE DES DOSSIERS
// ============================================
log_msg("📁 Création de la structure du projet...");

$dirs = [
    $projectDir,
    $projectDir . '/config',
    $projectDir . '/includes',
    $projectDir . '/assets/css',
    $projectDir . '/assets/js',
    $projectDir . '/uploads/products',
    $projectDir . '/uploads/avatars',
    $projectDir . '/uploads/temp',
    $projectDir . '/admin',
    $projectDir . '/api',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            log_msg("✓ Dossier créé : " . str_replace(__DIR__, '', $dir));
        } else {
            log_error("Impossible de créer : $dir");
        }
    } else {
        log_msg("✓ Existe déjà : " . str_replace(__DIR__, '', $dir), 'info');
    }
}

// ============================================
// ÉTAPE 2 : CRÉER LES FICHIERS DE CONFIGURATION
// ============================================
log_msg("⚙️ Création des fichiers de configuration...");

// config/database.php
$dbConfig = <<<PHP
<?php
// Configuration base de données - PekDev Market
try {
    \$pdo = new PDO(
        "mysql:host=localhost;dbname=$dbName;charset=utf8mb4",
        "$dbUser",
        "$dbPass",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException \$e) {
    die("Erreur BDD: " . \$e->getMessage());
}
PHP;

file_put_contents($projectDir . '/config/database.php', $dbConfig);
log_msg("✓ config/database.php créé");

// config/constants.php
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/' . $projectName;

$constants = <<<PHP
<?php
define('BASE_URL', '$baseUrl');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');
define('BASE_PATH', __DIR__ . '/..');
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('ITEMS_PER_PAGE', 12);
define('MAX_FILE_SIZE', 5242880);
define('CURRENCY', 'FBu');
define('SESSION_LIFETIME', 86400);
define('CSRF_TOKEN_NAME', 'csrf_token');

date_default_timezone_set('Africa/Bujumbura');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}
PHP;

file_put_contents($projectDir . '/config/constants.php', $constants);
log_msg("✓ config/constants.php créé");

// ============================================
// ÉTAPE 3 : CRÉER LES FONCTIONS
// ============================================
log_msg("🛠️ Création des fonctions...");

$functions = <<<'PHP'
<?php
function formatPrice($price) {
    return number_format($price, 0, ',', ' ') . ' FBu';
}

function clean($data) {
    if (is_array($data)) return array_map('clean', $data);
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    if ($format === 'relative') {
        $diff = time() - $timestamp;
        if ($diff < 60) return 'à l\'instant';
        if ($diff < 3600) return floor($diff / 60) . ' min';
        if ($diff < 86400) return floor($diff / 3600) . ' h';
        if ($diff < 604800) return floor($diff / 86400) . ' j';
        return date('d/m/Y', $timestamp);
    }
    return date($format, $timestamp);
}

function renderStars($rating, $max = 5) {
    $html = '<div class="flex text-yellow-400 text-xs">';
    for ($i = 1; $i <= $max; $i++) {
        if ($i <= floor($rating)) $html .= '<i class="fas fa-star"></i>';
        elseif ($i - 0.5 <= $rating) $html .= '<i class="fas fa-star-half-alt"></i>';
        else $html .= '<i class="far fa-star"></i>';
    }
    return $html . '</div>';
}

function calculateDiscount($price, $oldPrice) {
    if (empty($oldPrice) || $oldPrice <= $price) return 0;
    return round((($oldPrice - $price) / $oldPrice) * 100);
}

function generateCSRFToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function csrfField() {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . generateCSRFToken() . '">';
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isSeller() {
    return isLoggedIn() && isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['seller', 'admin']);
}

function isFavorite($userId, $productId, $pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);
    return (int)$stmt->fetchColumn() > 0;
}

function getCartCount($userId, $pdo) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function getFavoritesCount($userId, $pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function getProductImage($productId, $pdo) {
    $stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1");
    $stmt->execute([$productId]);
    $image = $stmt->fetch();
    return $image ? $image['image_path'] : 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?w=400';
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function redirect($url) {
    header("Location: $url");
    exit;
}
PHP;

file_put_contents($projectDir . '/includes/functions.php', $functions);
log_msg("✓ includes/functions.php créé");

// includes/auth.php
$auth = <<<'PHP'
<?php


function loginUser($email, $password, $pdo) {
    if (empty($email) || empty($password)) {
        return ['success' => false, 'message' => 'Veuillez remplir tous les champs'];
    }
    
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, password, role, avatar, is_active FROM users WHERE email = ?");
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();
    
    if (!$user) return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
    if (!$user['is_active']) return ['success' => false, 'message' => 'Compte désactivé'];
    if (!password_verify($password, $user['password'])) return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
    
    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_avatar'] = $user['avatar'];
    
    return ['success' => true, 'user' => $user];
}

function logoutUser() {
    $_SESSION = [];
    session_destroy();
}

function getCurrentUser($pdo) {
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function requireLogin($redirectUrl = null) {
    if (!isLoggedIn()) {
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Veuillez vous connecter'];
        header("Location: " . ($redirectUrl ?? BASE_URL . '/login.php'));
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Accès refusé'];
        header("Location: " . BASE_URL);
        exit;
    }
}
PHP;

file_put_contents($projectDir . '/includes/auth.php', $auth);
log_msg("✓ includes/auth.php créé");

// ============================================
// ÉTAPE 4 : CRÉER LA BASE DE DONNÉES
// ============================================
log_msg("🗄️ Création de la base de données...");

try {
    $pdo = new PDO("mysql:host=localhost", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Créer la base
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbName`");
    log_msg("✓ Base de données '$dbName' créée");
    
    // Créer les tables
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(20) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `role` ENUM('customer','seller','admin') DEFAULT 'customer',
  `province` VARCHAR(50) DEFAULT NULL,
  `city` VARCHAR(50) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `is_verified` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `icon` VARCHAR(50) DEFAULT 'fas fa-tag',
  `color` VARCHAR(20) DEFAULT 'blue',
  `description` TEXT DEFAULT NULL,
  `sort_order` INT(11) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `products` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `seller_id` INT(11) UNSIGNED NOT NULL,
  `category_id` INT(11) UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `description` TEXT,
  `short_description` VARCHAR(500) DEFAULT NULL,
  `price` DECIMAL(12,2) NOT NULL,
  `old_price` DECIMAL(12,2) DEFAULT NULL,
  `stock` INT(11) DEFAULT 0,
  `province` VARCHAR(50) DEFAULT NULL,
  `city` VARCHAR(50) DEFAULT NULL,
  `is_featured` TINYINT(1) DEFAULT 0,
  `is_new` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `views_count` INT(11) DEFAULT 0,
  `sales_count` INT(11) DEFAULT 0,
  `rating_avg` DECIMAL(3,2) DEFAULT 0.00,
  `rating_count` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `product_images` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `is_primary` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `order_number` VARCHAR(20) NOT NULL UNIQUE,
  `status` ENUM('pending','confirmed','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `payment_method` ENUM('cash','mobile_money','card') DEFAULT 'cash',
  `payment_status` ENUM('pending','paid','failed') DEFAULT 'pending',
  `subtotal` DECIMAL(12,2) NOT NULL,
  `shipping_cost` DECIMAL(12,2) DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL,
  `shipping_first_name` VARCHAR(50) NOT NULL,
  `shipping_last_name` VARCHAR(50) NOT NULL,
  `shipping_phone` VARCHAR(20) NOT NULL,
  `shipping_province` VARCHAR(50) NOT NULL,
  `shipping_city` VARCHAR(50) NOT NULL,
  `shipping_address` TEXT NOT NULL,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) UNSIGNED NOT NULL,
  `product_id` INT(11) UNSIGNED NOT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `product_image` VARCHAR(255) DEFAULT NULL,
  `quantity` INT(11) NOT NULL,
  `price` DECIMAL(12,2) NOT NULL,
  `total` DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cart` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `product_id` INT(11) UNSIGNED NOT NULL,
  `quantity` INT(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_product` (`user_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `favorites` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `product_id` INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_product` (`user_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `product_id` INT(11) UNSIGNED NOT NULL,
  `rating` TINYINT(1) NOT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `comment` TEXT,
  `is_approved` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

    foreach (explode(';', $sql) as $query) {
        $query = trim($query);
        if (!empty($query)) {
            $pdo->exec($query);
        }
    }
    log_msg("✓ Toutes les tables créées");
    
    // Insérer les catégories
    $categories = [
        ['Électronique', 'electronique', 'fas fa-laptop', 'blue'],
        ['Mode & Beauté', 'mode-beaute', 'fas fa-tshirt', 'pink'],
        ['Maison', 'maison', 'fas fa-home', 'green'],
        ['Téléphones', 'telephones', 'fas fa-mobile-alt', 'purple'],
        ['Alimentation', 'alimentation', 'fas fa-utensils', 'orange'],
        ['Autos & Motos', 'autos-motos', 'fas fa-car', 'red'],
        ['Services', 'services', 'fas fa-tools', 'teal'],
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO categories (name, slug, icon, color, sort_order) VALUES (?, ?, ?, ?, ?)");
    foreach ($categories as $i => $cat) {
        $stmt->execute([$cat[0], $cat[1], $cat[2], $cat[3], $i + 1]);
    }
    log_msg("✓ " . count($categories) . " catégories insérées");
    
    // Créer les comptes démo
    $demoAccounts = [
        ['Admin', 'PekDev', 'admin@pekdev.bi', '+257 79 000 000', 'admin123', 'admin', 'Bujumbura Mairie', 'Bujumbura'],
        ['Vendeur', 'Demo', 'vendeur@pekdev.bi', '+257 79 111 111', 'admin123', 'seller', 'Bujumbura Mairie', 'Bujumbura'],
        ['Jean', 'Mugabo', 'client@pekdev.bi', '+257 79 222 222', 'admin123', 'customer', 'Bujumbura Mairie', 'Bujumbura'],
    ];
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $insertStmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role, province, city, is_verified, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");
    $updateStmt = $pdo->prepare("UPDATE users SET password = ?, role = ? WHERE email = ?");
    
    foreach ($demoAccounts as $acc) {
        $hash = password_hash($acc[4], PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt->execute([$acc[2]]);
        if ($stmt->fetch()) {
            $updateStmt->execute([$hash, $acc[5], $acc[2]]);
        } else {
            $insertStmt->execute([$acc[0], $acc[1], $acc[2], $acc[3], $hash, $acc[5], $acc[6], $acc[7]]);
        }
    }
    log_msg("✓ 3 comptes démo créés (admin, vendeur, client)");
    
    // Insérer des produits de démo
    $sellerId = $pdo->query("SELECT id FROM users WHERE email = 'vendeur@pekdev.bi'")->fetchColumn();
    
    $products = [
        [$sellerId, 1, 'Smartphone Samsung Galaxy A14', 'samsung-galaxy-a14', 'Smartphone dernière génération avec écran AMOLED 6.6 pouces, 128GB, 4GB RAM, 50MP.', 'Samsung A14 - 128GB', 250000, 300000, 25, 'Bujumbura Mairie', 'Bujumbura', 1, 1],
        [$sellerId, 2, 'Chaussures Nike Air Max', 'nike-air-max', 'Chaussures de sport Nike Air Max originales. Confort optimal.', 'Nike Air Max', 120000, 150000, 40, 'Bujumbura Mairie', 'Bujumbura', 1, 0],
        [$sellerId, 5, 'Riz Local Premium 5kg', 'riz-local-premium-5kg', 'Riz local de première qualité, cultivé au Burundi.', 'Riz local burundais', 15000, null, 200, 'Gitega', 'Gitega', 1, 0],
        [$sellerId, 1, 'TV Smart 32 pouces Android', 'tv-smart-32-pouces', 'Téléviseur Smart TV 32 pouces avec Android TV intégré.', 'Smart TV 32 pouces', 350000, 400000, 15, 'Bujumbura Mairie', 'Bujumbura', 1, 1],
        [$sellerId, 4, 'iPhone 13 Pro Max 256GB', 'iphone-13-pro-max', 'iPhone 13 Pro Max neuf, 256GB.', 'iPhone 13 Pro Max', 1500000, 1700000, 5, 'Bujumbura Mairie', 'Bujumbura', 1, 1],
        [$sellerId, 5, 'Café du Burundi 1kg', 'cafe-burundi-1kg', 'Café 100% arabica du Burundi.', 'Café arabica', 25000, null, 150, 'Ngozi', 'Ngozi', 1, 0],
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO products (seller_id, category_id, name, slug, description, short_description, price, old_price, stock, province, city, is_featured, is_new) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($products as $p) {
        $stmt->execute($p);
    }
    log_msg("✓ 6 produits de démo insérés");
    
    // Insérer les images
    $images = [
        [1, 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?w=600', 1],
        [2, 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600', 1],
        [3, 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=600', 1],
        [4, 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=600', 1],
        [5, 'https://images.unsplash.com/photo-1592286927505-1def25115558?w=600', 1],
        [6, 'https://images.unsplash.com/photo-1447933601403-0c6688de566e?w=600', 1],
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, ?)");
    foreach ($images as $img) {
        $stmt->execute($img);
    }
    log_msg("✓ Images des produits insérées");
    
} catch (Exception $e) {
    log_error("Erreur BDD : " . $e->getMessage());
}

// ============================================
// ÉTAPE 5 : CRÉER LES PAGES PRINCIPALES
// ============================================
log_msg("📄 Création des pages principales...");

// index.php - Page d'accueil
$indexPhp = <<<'PHP'
<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$featured = $pdo->query("
    SELECT p.*, pi.image_path 
    FROM products p 
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
    WHERE p.is_active = 1 AND p.is_featured = 1
    ORDER BY p.created_at DESC LIMIT 8
")->fetchAll();

$categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
$stats = [
    'products' => $pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn(),
    'sellers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('seller', 'admin')")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PekDev Market - Marketplace du Burundi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { primary: '#1e40af', secondary: '#f97316' }, fontFamily: { sans: ['Inter', 'sans-serif'] } } }
        }
    </script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 shadow-md sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="<?= BASE_URL ?>/" class="flex items-center gap-2">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-xl">P</span>
                    </div>
                    <div>
                        <span class="text-xl font-bold text-blue-600 dark:text-white">PekDev</span>
                        <span class="text-xs text-orange-500 block">Market</span>
                    </div>
                </a>
                <form action="<?= BASE_URL ?>/search.php" method="GET" class="hidden md:flex flex-1 max-w-xl mx-8">
                    <div class="relative w-full">
                        <input type="text" name="q" class="w-full pl-12 pr-4 py-2 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-full focus:border-blue-600 focus:outline-none" placeholder="Rechercher...">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    </div>
                </form>
                <div class="flex items-center gap-2">
                    <a href="<?= BASE_URL ?>/cart.php" class="p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                        <i class="fas fa-shopping-cart text-xl"></i>
                    </a>
                    <?php if (isLoggedIn()): ?>
                        <a href="<?= BASE_URL ?>/profile.php" class="flex items-center gap-2 p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                            <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                            </div>
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/login.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 text-sm">Connexion</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero -->
    <section class="bg-gradient-to-br from-blue-50 to-orange-50 dark:from-gray-800 dark:to-gray-900 py-12 md:py-20">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-6xl font-bold text-gray-800 dark:text-white mb-4">
                Bienvenue sur <span class="text-blue-600">PekDev</span> <span class="text-orange-500">Market</span>
            </h1>
            <p class="text-lg md:text-xl text-gray-600 dark:text-gray-300 mb-8">La plus grande marketplace du Burundi 🇧🇮</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="<?= BASE_URL ?>/products.php" class="px-8 py-3 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 shadow-lg">
                    <i class="fas fa-shopping-bag mr-2"></i>Acheter
                </a>
                <a href="<?= BASE_URL ?>/register.php?role=seller" class="px-8 py-3 bg-orange-500 text-white rounded-lg font-semibold hover:bg-orange-600 shadow-lg">
                    <i class="fas fa-store mr-2"></i>Vendre
                </a>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-12 max-w-3xl mx-auto">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow">
                    <p class="text-2xl md:text-3xl font-bold text-blue-600"><?= $stats['products'] ?>+</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Produits</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow">
                    <p class="text-2xl md:text-3xl font-bold text-orange-500"><?= $stats['sellers'] ?>+</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Vendeurs</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow">
                    <p class="text-2xl md:text-3xl font-bold text-green-600">18</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Provinces</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow">
                    <p class="text-2xl md:text-3xl font-bold text-purple-600">24/7</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Support</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Catégories -->
    <section class="py-12 bg-white dark:bg-gray-800">
        <div class="max-w-7xl mx-auto px-4">
            <h2 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-white mb-8 text-center">Catégories</h2>
            <div class="grid grid-cols-3 md:grid-cols-7 gap-4">
                <?php foreach ($categories as $cat): ?>
                    <a href="<?= BASE_URL ?>/category.php?slug=<?= $cat['slug'] ?>" class="bg-gray-50 dark:bg-gray-700 rounded-xl p-4 text-center hover:shadow-xl transition group">
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-xl flex items-center justify-center mx-auto mb-2 group-hover:scale-110 transition">
                            <i class="<?= $cat['icon'] ?> text-blue-600 text-xl"></i>
                        </div>
                        <span class="text-xs md:text-sm font-medium text-gray-700 dark:text-gray-200"><?= clean($cat['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Produits -->
    <section class="py-12 bg-gray-50 dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-4">
            <h2 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-white mb-8">Produits en vedette</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($featured as $p): ?>
                    <a href="<?= BASE_URL ?>/product.php?slug=<?= $p['slug'] ?>" class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow hover:shadow-xl transition group">
                        <div class="relative overflow-hidden">
                            <img src="<?= $p['image_path'] ?? 'https://via.placeholder.com/300' ?>" class="w-full h-40 object-cover group-hover:scale-105 transition" alt="">
                            <?php if ($p['old_price']): ?>
                                <span class="absolute top-2 left-2 bg-red-500 text-white text-xs px-2 py-1 rounded">-<?= calculateDiscount($p['price'], $p['old_price']) ?>%</span>
                            <?php endif; ?>
                        </div>
                        <div class="p-3">
                            <h3 class="font-semibold text-sm text-gray-800 dark:text-white line-clamp-2"><?= clean($p['name']) ?></h3>
                            <p class="text-blue-600 font-bold mt-2"><?= formatPrice($p['price']) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-8">
                <a href="<?= BASE_URL ?>/products.php" class="inline-block px-8 py-3 border-2 border-blue-600 text-blue-600 rounded-lg font-semibold hover:bg-blue-600 hover:text-white">
                    Voir tous les produits <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p>&copy; <?= date('Y') ?> PekDev Market. Tous droits réservés.</p>
        </div>
    </footer>
</body>
</html>
PHP;

file_put_contents($projectDir . '/index.php', $indexPhp);
log_msg("✓ index.php créé");

// login.php
$loginPhp = <<<'PHP'
<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    $redirect = BASE_URL . '/';
    if ($_SESSION['user_role'] === 'admin') $redirect = BASE_URL . '/admin/';
    redirect($redirect);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Token invalide';
    } else {
        $result = loginUser($_POST['email'] ?? '', $_POST['password'] ?? '', $pdo);
        if ($result['success']) {
            $redirect = BASE_URL . '/';
            if ($_SESSION['user_role'] === 'admin') $redirect = BASE_URL . '/admin/';
            elseif ($_SESSION['user_role'] === 'seller') $redirect = BASE_URL . '/sell.php';
            redirect($redirect);
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af', secondary: '#f97316' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-4xl w-full grid md:grid-cols-2 gap-6">
        <!-- Formulaire -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-user text-white text-2xl"></i>
                </div>
                <h2 class="text-3xl font-bold text-gray-800 dark:text-white">Connexion</h2>
            </div>
            <?php if ($error): ?>
                <div class="bg-red-50 dark:bg-red-900/20 text-red-700 px-4 py-3 rounded-lg mb-4"><?= clean($error) ?></div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <?= csrfField() ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Email</label>
                    <input type="email" name="email" required class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg focus:border-blue-600 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Mot de passe</label>
                    <input type="password" name="password" required class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg focus:border-blue-600 focus:outline-none">
                </div>
                <button type="submit" class="w-full py-3 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700">Se connecter</button>
            </form>
            <p class="text-center mt-4 text-gray-600 dark:text-gray-300">
                Pas de compte ? <a href="<?= BASE_URL ?>/register.php" class="text-blue-600 font-semibold">S'inscrire</a>
            </p>
        </div>
        
        <!-- Comptes démo -->
        <div class="bg-gradient-to-br from-blue-50 to-orange-50 dark:from-gray-800 dark:to-gray-700 rounded-2xl shadow-xl p-6">
            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-4 text-center">⚡ Connexion rapide (Démo)</h3>
            <div class="space-y-3">
                <form method="POST" class="bg-white dark:bg-gray-800 rounded-xl p-4 hover:shadow-lg transition">
                    <?= csrfField() ?>
                    <input type="hidden" name="email" value="admin@pekdev.bi">
                    <input type="hidden" name="password" value="admin123">
                    <button type="submit" class="w-full flex items-center gap-3">
                        <div class="w-10 h-10 bg-red-500 rounded-lg flex items-center justify-center"><i class="fas fa-user-shield text-white"></i></div>
                        <div class="flex-1 text-left">
                            <p class="font-bold text-gray-800 dark:text-white">Admin</p>
                            <p class="text-xs text-gray-500">admin@pekdev.bi</p>
                        </div>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                    </button>
                </form>
                <form method="POST" class="bg-white dark:bg-gray-800 rounded-xl p-4 hover:shadow-lg transition">
                    <?= csrfField() ?>
                    <input type="hidden" name="email" value="vendeur@pekdev.bi">
                    <input type="hidden" name="password" value="admin123">
                    <button type="submit" class="w-full flex items-center gap-3">
                        <div class="w-10 h-10 bg-orange-500 rounded-lg flex items-center justify-center"><i class="fas fa-store text-white"></i></div>
                        <div class="flex-1 text-left">
                            <p class="font-bold text-gray-800 dark:text-white">Vendeur</p>
                            <p class="text-xs text-gray-500">vendeur@pekdev.bi</p>
                        </div>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                    </button>
                </form>
                <form method="POST" class="bg-white dark:bg-gray-800 rounded-xl p-4 hover:shadow-lg transition">
                    <?= csrfField() ?>
                    <input type="hidden" name="email" value="client@pekdev.bi">
                    <input type="hidden" name="password" value="admin123">
                    <button type="submit" class="w-full flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center"><i class="fas fa-user text-white"></i></div>
                        <div class="flex-1 text-left">
                            <p class="font-bold text-gray-800 dark:text-white">Client</p>
                            <p class="text-xs text-gray-500">client@pekdev.bi</p>
                        </div>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
PHP;

file_put_contents($projectDir . '/login.php', $loginPhp);
log_msg("✓ login.php créé");

// product.php (version complète)
$productPhp = file_get_contents(__DIR__ . '/product-template.txt') ?: <<<'PHP'
<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) redirect(BASE_URL);

$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon, c.color as category_color,
           u.first_name as seller_first, u.last_name as seller_last, u.id as seller_id, u.phone as seller_phone,
           (SELECT COUNT(*) FROM products WHERE seller_id = u.id AND is_active = 1) as seller_products_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.seller_id = u.id
    WHERE p.slug = ? AND p.is_active = 1
");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) redirect(BASE_URL);

$pdo->prepare("UPDATE products SET views_count = views_count + 1 WHERE id = ?")->execute([$product['id']]);

$images = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC");
$images->execute([$product['id']]);
$images = $images->fetchAll();
if (empty($images)) $images = [['image_path' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?w=600']];

$reviews = $pdo->prepare("SELECT r.*, u.first_name, u.last_name FROM reviews r LEFT JOIN users u ON r.user_id = u.id WHERE r.product_id = ? AND r.is_approved = 1 ORDER BY r.created_at DESC LIMIT 10");
$reviews->execute([$product['id']]);
$reviews = $reviews->fetchAll();

$related = $pdo->prepare("SELECT p.*, pi.image_path FROM products p LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1 WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1 ORDER BY p.sales_count DESC LIMIT 4");
$related->execute([$product['category_id'], $product['id']]);
$related = $related->fetchAll();

$isFav = isLoggedIn() ? isFavorite($_SESSION['user_id'], $product['id'], $pdo) : false;
$discount = calculateDiscount($product['price'], $product['old_price']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="<?= BASE_URL ?>">
    <title><?= clean($product['name']) ?> - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af', secondary: '#f97316' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; } .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <header class="bg-white dark:bg-gray-800 shadow-md sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-16">
            <a href="<?= BASE_URL ?>/" class="flex items-center gap-2">
                <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center"><span class="text-white font-bold text-xl">P</span></div>
                <div><span class="text-xl font-bold text-blue-600 dark:text-white">PekDev</span><span class="text-xs text-orange-500 block">Market</span></div>
            </a>
            <div class="flex items-center gap-2">
                <a href="<?= BASE_URL ?>/cart.php" class="p-2 text-gray-600 dark:text-gray-300"><i class="fas fa-shopping-cart text-xl"></i></a>
                <?php if (isLoggedIn()): ?>
                    <a href="<?= BASE_URL ?>/profile.php" class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold text-sm"><?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?></a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/login.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">Connexion</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 py-6">
        <nav class="text-sm text-gray-500 mb-6">
            <a href="<?= BASE_URL ?>/" class="hover:text-blue-600">Accueil</a> >
            <a href="<?= BASE_URL ?>/category.php?slug=<?= $product['category_slug'] ?>" class="hover:text-blue-600"><?= clean($product['category_name']) ?></a> >
            <span class="text-gray-800 dark:text-white"><?= clean($product['name']) ?></span>
        </nav>

        <div class="grid lg:grid-cols-2 gap-8">
            <!-- Images -->
            <div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl overflow-hidden shadow-sm mb-4 relative group">
                    <img id="mainImage" src="<?= $images[0]['image_path'] ?>" alt="<?= clean($product['name']) ?>" class="w-full h-96 object-cover cursor-zoom-in" onclick="openModal(0)">
                    <div class="absolute top-4 left-4 flex flex-col gap-2">
                        <?php if ($product['is_new']): ?><span class="bg-orange-500 text-white text-xs px-3 py-1 rounded-full font-bold">Nouveau</span><?php endif; ?>
                        <?php if ($discount > 0): ?><span class="bg-red-500 text-white text-xs px-3 py-1 rounded-full font-bold">-<?= $discount ?>%</span><?php endif; ?>
                    </div>
                    <?php if (count($images) > 1): ?>
                        <button onclick="changeImg(-1)" class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/90 rounded-full opacity-0 group-hover:opacity-100 transition"><i class="fas fa-chevron-left"></i></button>
                        <button onclick="changeImg(1)" class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/90 rounded-full opacity-0 group-hover:opacity-100 transition"><i class="fas fa-chevron-right"></i></button>
                    <?php endif; ?>
                </div>
                <?php if (count($images) > 1): ?>
                    <div class="grid grid-cols-5 gap-2">
                        <?php foreach ($images as $i => $img): ?>
                            <button onclick="setImg(<?= $i ?>)" id="thumb-<?= $i ?>" class="border-2 <?= $i === 0 ? 'border-blue-600' : 'border-transparent' ?> rounded-lg overflow-hidden">
                                <img src="<?= $img['image_path'] ?>" class="w-full h-20 object-cover">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Infos -->
            <div>
                <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-3"><?= clean($product['name']) ?></h1>
                <div class="flex items-center gap-4 text-sm mb-4">
                    <?php if ($product['rating_count'] > 0): ?>
                        <?= renderStars($product['rating_avg']) ?>
                        <span class="text-gray-500">(<?= $product['rating_count'] ?> avis)</span>
                    <?php endif; ?>
                    <span class="text-gray-500"><i class="fas fa-eye mr-1"></i><?= $product['views_count'] ?> vues</span>
                </div>

                <div class="bg-gradient-to-r from-blue-50 to-orange-50 dark:from-gray-800 dark:to-gray-700 rounded-2xl p-6 mb-4">
                    <span class="text-4xl font-bold text-blue-600"><?= formatPrice($product['price']) ?></span>
                    <?php if ($product['old_price']): ?>
                        <span class="text-lg text-gray-400 line-through ml-3"><?= formatPrice($product['old_price']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border"><i class="fas fa-map-marker-alt text-orange-500 mr-2"></i><?= clean($product['city'] ?? $product['province']) ?></div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border">
                        <?php if ($product['stock'] > 0): ?>
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>En stock (<?= $product['stock'] ?>)
                        <?php else: ?>
                            <i class="fas fa-times-circle text-red-500 mr-2"></i>Rupture
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($product['stock'] > 0): ?>
                <form method="POST" action="<?= BASE_URL ?>/cart.php" class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm mb-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Quantité</label>
                        <div class="flex items-center gap-3">
                            <div class="flex border-2 rounded-lg">
                                <button type="button" onclick="document.getElementById('qty').stepDown()" class="w-10 h-10">-</button>
                                <input type="number" id="qty" name="quantity" value="1" min="1" max="<?= $product['stock'] ?>" class="w-16 text-center border-0 focus:outline-none">
                                <button type="button" onclick="document.getElementById('qty').stepUp()" class="w-10 h-10">+</button>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="add_to_cart" class="w-full py-3 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 mb-2">
                        <i class="fas fa-shopping-cart mr-2"></i>Ajouter au panier
                    </button>
                    <a href="<?= BASE_URL ?>/checkout.php" class="block w-full py-3 bg-orange-500 text-white rounded-lg font-semibold text-center hover:bg-orange-600">
                        <i class="fas fa-bolt mr-2"></i>Acheter maintenant
                    </a>
                </form>
                <?php endif; ?>

                <!-- Vendeur -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm">
                    <h3 class="font-bold mb-4"><i class="fas fa-store text-blue-600 mr-2"></i>Vendeur</h3>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold"><?= strtoupper(substr($product['seller_first'], 0, 1)) ?></div>
                        <div>
                            <p class="font-semibold"><?= clean($product['seller_first'] . ' ' . $product['seller_last']) ?></p>
                            <p class="text-xs text-gray-500"><?= $product['seller_products_count'] ?> produits</p>
                        </div>
                    </div>
                    <?php if ($product['seller_phone']): ?>
                    <div class="flex gap-2">
                        <a href="tel:<?= $product['seller_phone'] ?>" class="flex-1 py-2 bg-green-500 text-white rounded-lg text-center text-sm"><i class="fas fa-phone mr-1"></i>Appeler</a>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $product['seller_phone']) ?>" target="_blank" class="flex-1 py-2 bg-green-600 text-white rounded-lg text-center text-sm"><i class="fab fa-whatsapp mr-1"></i>WhatsApp</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="mt-8 bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm">
            <h3 class="text-xl font-bold mb-4">Description</h3>
            <div class="text-gray-700 dark:text-gray-300"><?= nl2br(clean($product['description'])) ?></div>
        </div>

        <!-- Avis -->
        <?php if (!empty($reviews)): ?>
        <div class="mt-8 bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm">
            <h3 class="text-xl font-bold mb-4">Avis (<?= count($reviews) ?>)</h3>
            <div class="space-y-4">
                <?php foreach ($reviews as $review): ?>
                <div class="border-b pb-4 last:border-0">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold"><?= strtoupper(substr($review['first_name'], 0, 1)) ?></div>
                        <div>
                            <p class="font-semibold"><?= clean($review['first_name'] . ' ' . $review['last_name']) ?></p>
                            <?= renderStars($review['rating']) ?>
                        </div>
                    </div>
                    <p class="text-gray-600 dark:text-gray-300"><?= nl2br(clean($review['comment'])) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Similaires -->
        <?php if (!empty($related)): ?>
        <div class="mt-8">
            <h3 class="text-xl font-bold mb-4">Produits similaires</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($related as $r): ?>
                <a href="<?= BASE_URL ?>/product.php?slug=<?= $r['slug'] ?>" class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow hover:shadow-xl transition">
                    <img src="<?= $r['image_path'] ?? 'https://via.placeholder.com/300' ?>" class="w-full h-40 object-cover">
                    <div class="p-3">
                        <h4 class="font-semibold text-sm line-clamp-2"><?= clean($r['name']) ?></h4>
                        <p class="text-blue-600 font-bold mt-2"><?= formatPrice($r['price']) ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal -->
    <div id="modal" class="hidden fixed inset-0 bg-black/95 z-50 flex items-center justify-center p-4" onclick="closeModal()">
        <button onclick="closeModal()" class="absolute top-4 right-4 w-12 h-12 bg-white/10 rounded-full text-white text-2xl"><i class="fas fa-times"></i></button>
        <button onclick="event.stopPropagation(); changeImg(-1)" class="absolute left-4 w-12 h-12 bg-white/10 rounded-full text-white text-xl"><i class="fas fa-chevron-left"></i></button>
        <button onclick="event.stopPropagation(); changeImg(1)" class="absolute right-4 w-12 h-12 bg-white/10 rounded-full text-white text-xl"><i class="fas fa-chevron-right"></i></button>
        <img id="modalImg" src="" class="max-w-full max-h-full object-contain">
    </div>

    <!-- WhatsApp flottant -->
    <?php if ($product['seller_phone']): ?>
    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $product['seller_phone']) ?>" target="_blank" class="fixed bottom-6 right-6 w-14 h-14 bg-green-500 text-white rounded-full shadow-xl flex items-center justify-center hover:scale-110 transition z-30">
        <i class="fab fa-whatsapp text-2xl"></i>
    </a>
    <?php endif; ?>

    <script>
    const imgs = <?= json_encode(array_column($images, 'image_path')) ?>;
    let curIdx = 0;
    function setImg(i) {
        curIdx = i;
        document.getElementById('mainImage').src = imgs[i];
        imgs.forEach((_, idx) => {
            const t = document.getElementById('thumb-' + idx);
            if (t) t.classList.toggle('border-blue-600', idx === i);
        });
    }
    function changeImg(d) { setImg((curIdx + d + imgs.length) % imgs.length); }
    function openModal(i) {
        setImg(i);
        document.getElementById('modalImg').src = imgs[i];
        document.getElementById('modal').classList.remove('hidden');
    }
    function closeModal() { document.getElementById('modal').classList.add('hidden'); }
    document.addEventListener('keydown', e => {
        if (document.getElementById('modal').classList.contains('hidden')) return;
        if (e.key === 'Escape') closeModal();
        if (e.key === 'ArrowLeft') changeImg(-1);
        if (e.key === 'ArrowRight') changeImg(1);
    });
    </script>
</body>
</html>
PHP;

file_put_contents($projectDir . '/product.php', $productPhp);
log_msg("✓ product.php créé");

// register.php (version simple)
$registerPhp = <<<'PHP'
<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) redirect(BASE_URL);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = clean($_POST['first_name'] ?? '');
    $lastName = clean($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    $role = in_array($_POST['role'] ?? '', ['customer', 'seller']) ? $_POST['role'] : 'customer';
    
    if (empty($firstName)) $errors[] = 'Prénom requis';
    if (empty($lastName)) $errors[] = 'Nom requis';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';
    if (strlen($password) < 6) $errors[] = 'Mot de passe trop court';
    if ($password !== $confirm) $errors[] = 'Mots de passe différents';
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) $errors[] = 'Email déjà utilisé';
    
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$firstName, $lastName, $email, $hash, $role]);
        
        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['user_name'] = $firstName . ' ' . $lastName;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = $role;
        
        redirect(BASE_URL);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af', secondary: '#f97316' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-orange-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-user-plus text-white text-2xl"></i>
            </div>
            <h2 class="text-3xl font-bold text-gray-800 dark:text-white">Inscription</h2>
        </div>
        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 dark:bg-red-900/20 text-red-700 px-4 py-3 rounded-lg mb-4">
                <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <input type="text" name="first_name" required placeholder="Prénom" value="<?= clean($_POST['first_name'] ?? '') ?>" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg focus:border-blue-600 focus:outline-none">
                <input type="text" name="last_name" required placeholder="Nom" value="<?= clean($_POST['last_name'] ?? '') ?>" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg focus:border-blue-600 focus:outline-none">
            </div>
            <input type="email" name="email" required placeholder="Email" value="<?= clean($_POST['email'] ?? '') ?>" class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg focus:border-blue-600 focus:outline-none">
            <div class="grid grid-cols-2 gap-3">
                <input type="password" name="password" required placeholder="Mot de passe" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg focus:border-blue-600 focus:outline-none">
                <input type="password" name="password_confirm" required placeholder="Confirmer" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg focus:border-blue-600 focus:outline-none">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <label class="cursor-pointer">
                    <input type="radio" name="role" value="customer" checked class="hidden peer">
                    <div class="p-3 border-2 border-gray-200 dark:border-gray-700 rounded-lg peer-checked:border-blue-600 text-center">
                        <i class="fas fa-shopping-bag text-blue-600 text-xl mb-1"></i>
                        <p class="text-sm font-semibold">Acheteur</p>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="role" value="seller" class="hidden peer">
                    <div class="p-3 border-2 border-gray-200 dark:border-gray-700 rounded-lg peer-checked:border-orange-500 text-center">
                        <i class="fas fa-store text-orange-500 text-xl mb-1"></i>
                        <p class="text-sm font-semibold">Vendeur</p>
                    </div>
                </label>
            </div>
            <button type="submit" class="w-full py-3 bg-orange-500 text-white rounded-lg font-semibold hover:bg-orange-600">Créer mon compte</button>
        </form>
        <p class="text-center mt-4 text-gray-600 dark:text-gray-300">
            Déjà un compte ? <a href="<?= BASE_URL ?>/login.php" class="text-blue-600 font-semibold">Se connecter</a>
        </p>
    </div>
</body>
</html>
PHP;

file_put_contents($projectDir . '/register.php', $registerPhp);
log_msg("✓ register.php créé");

// search.php
$searchPhp = <<<'PHP'
<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$q = trim($_GET['q'] ?? '');
$products = [];
if (!empty($q)) {
    $stmt = $pdo->prepare("SELECT p.*, pi.image_path FROM products p LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1 WHERE p.is_active = 1 AND (p.name LIKE ? OR p.description LIKE ?) ORDER BY p.sales_count DESC LIMIT 20");
    $stmt->execute(["%$q%", "%$q%"]);
    $products = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Recherche - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { colors: { primary: '#1e40af' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-6">Recherche : "<?= clean($q) ?>"</h1>
        <p class="text-gray-500 mb-6"><?= count($products) ?> résultat(s)</p>
        <?php if (empty($products)): ?>
            <div class="bg-white rounded-xl p-12 text-center"><i class="fas fa-search text-6xl text-gray-300 mb-4"></i><p>Aucun résultat</p></div>
        <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($products as $p): ?>
                    <a href="product.php?slug=<?= $p['slug'] ?>" class="bg-white rounded-xl overflow-hidden shadow hover:shadow-xl transition">
                        <img src="<?= $p['image_path'] ?? 'https://via.placeholder.com/300' ?>" class="w-full h-40 object-cover">
                        <div class="p-3">
                            <h3 class="font-semibold text-sm line-clamp-2"><?= clean($p['name']) ?></h3>
                            <p class="text-blue-600 font-bold mt-2"><?= formatPrice($p['price']) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
PHP;

file_put_contents($projectDir . '/search.php', $searchPhp);
log_msg("✓ search.php créé");

// products.php
$productsPhp = <<<'PHP'
<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$products = $pdo->query("SELECT p.*, pi.image_path FROM products p LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1 WHERE p.is_active = 1 ORDER BY p.created_at DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Produits - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-6">Tous les produits</h1>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php foreach ($products as $p): ?>
                <a href="product.php?slug=<?= $p['slug'] ?>" class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow hover:shadow-xl transition">
                    <img src="<?= $p['image_path'] ?? 'https://via.placeholder.com/300' ?>" class="w-full h-40 object-cover">
                    <div class="p-3">
                        <h3 class="font-semibold text-sm text-gray-800 dark:text-white"><?= clean($p['name']) ?></h3>
                        <p class="text-blue-600 font-bold mt-2"><?= formatPrice($p['price']) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
PHP;

file_put_contents($projectDir . '/products.php', $productsPhp);
log_msg("✓ products.php créé");

// category.php
$categoryPhp = <<<'PHP'
<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
$stmt->execute([$slug]);
$cat = $stmt->fetch();
if (!$cat) redirect(BASE_URL);

$products = $pdo->prepare("SELECT p.*, pi.image_path FROM products p LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1 WHERE p.category_id = ? AND p.is_active = 1");
$products->execute([$cat['id']]);
$products = $products->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= clean($cat['name']) ?> - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-6"><i class="<?= $cat['icon'] ?> mr-2"></i><?= clean($cat['name']) ?></h1>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php foreach ($products as $p): ?>
                <a href="product.php?slug=<?= $p['slug'] ?>" class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow hover:shadow-xl transition">
                    <img src="<?= $p['image_path'] ?? 'https://via.placeholder.com/300' ?>" class="w-full h-40 object-cover">
                    <div class="p-3">
                        <h3 class="font-semibold text-sm text-gray-800 dark:text-white"><?= clean($p['name']) ?></h3>
                        <p class="text-blue-600 font-bold mt-2"><?= formatPrice($p['price']) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
PHP;

file_put_contents($projectDir . '/category.php', $categoryPhp);
log_msg("✓ category.php créé");

// cart.php
$cartPhp = <<<'PHP'
<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $productId = (int)$_POST['product_id'];
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
    $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
    $stmt->execute([$userId, $productId, $quantity]);
    setFlash('success', 'Produit ajouté au panier');
    redirect(BASE_URL . '/cart.php');
}

if (isset($_GET['remove'])) {
    $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?")->execute([(int)$_GET['remove'], $userId]);
    redirect(BASE_URL . '/cart.php');
}

$items = $pdo->prepare("SELECT c.*, p.name, p.price, p.stock, p.slug, pi.image_path FROM cart c JOIN products p ON c.product_id = p.id LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1 WHERE c.user_id = ?");
$items->execute([$userId]);
$items = $items->fetchAll();

$total = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Panier - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af', secondary: '#f97316' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="max-w-5xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-6"><i class="fas fa-shopping-cart text-blue-600 mr-2"></i>Mon Panier</h1>
        <?php if (empty($items)): ?>
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-12 text-center">
                <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 mb-4">Votre panier est vide</p>
                <a href="products.php" class="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg">Voir les produits</a>
            </div>
        <?php else: ?>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm overflow-hidden mb-6">
                <?php foreach ($items as $item): ?>
                    <div class="flex gap-4 p-4 border-b dark:border-gray-700">
                        <img src="<?= $item['image_path'] ?? 'https://via.placeholder.com/100' ?>" class="w-24 h-24 object-cover rounded-lg">
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-800 dark:text-white"><?= clean($item['name']) ?></h3>
                            <p class="text-blue-600 font-bold"><?= formatPrice($item['price']) ?></p>
                            <p class="text-sm text-gray-500">Quantité : <?= $item['quantity'] ?></p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-lg"><?= formatPrice($item['price'] * $item['quantity']) ?></p>
                            <a href="?remove=<?= $item['id'] ?>" class="text-red-500 text-sm mt-2 inline-block"><i class="fas fa-trash"></i> Supprimer</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm">
                <div class="flex justify-between text-xl font-bold mb-4">
                    <span>Total :</span>
                    <span class="text-blue-600"><?= formatPrice($total) ?></span>
                </div>
                <a href="checkout.php" class="block w-full py-3 bg-orange-500 text-white rounded-lg font-semibold text-center hover:bg-orange-600">Commander</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
PHP;

file_put_contents($projectDir . '/cart.php', $cartPhp);
log_msg("✓ cart.php créé");

// checkout.php
$checkoutPhp = <<<'PHP'
<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$userId = $_SESSION['user_id'];
$user = getCurrentUser($pdo);

$items = $pdo->prepare("SELECT c.*, p.name, p.price, p.stock, pi.image_path FROM cart c JOIN products p ON c.product_id = p.id LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1 WHERE c.user_id = ?");
$items->execute([$userId]);
$items = $items->fetchAll();

if (empty($items)) redirect(BASE_URL . '/cart.php');

$subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
$shipping = $subtotal >= 100000 ? 0 : 5000;
$total = $subtotal + $shipping;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        $orderNumber = 'PKD-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, order_number, status, payment_method, subtotal, shipping_cost, total, shipping_first_name, shipping_last_name, shipping_phone, shipping_province, shipping_city, shipping_address) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $orderNumber, $_POST['payment_method'] ?? 'cash', $subtotal, $shipping, $total, clean($_POST['first_name']), clean($_POST['last_name']), clean($_POST['phone']), clean($_POST['province']), clean($_POST['city']), clean($_POST['address'])]);
        $orderId = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_image, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $stmt->execute([$orderId, $item['product_id'], $item['name'], $item['image_path'], $item['quantity'], $item['price'], $item['price'] * $item['quantity']]);
            $pdo->prepare("UPDATE products SET stock = stock - ?, sales_count = sales_count + ? WHERE id = ?")->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
        }
        
        $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);
        $pdo->commit();
        
        setFlash('success', "Commande $orderNumber créée !");
        redirect(BASE_URL . "/profile.php");
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Erreur : ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Commande - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af', secondary: '#f97316' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="max-w-5xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-6">Finaliser la commande</h1>
        <form method="POST" class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm">
                    <h3 class="font-bold mb-4">Adresse de livraison</h3>
                    <div class="grid md:grid-cols-2 gap-4">
                        <input type="text" name="first_name" required value="<?= clean($user['first_name'] ?? '') ?>" placeholder="Prénom" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                        <input type="text" name="last_name" required value="<?= clean($user['last_name'] ?? '') ?>" placeholder="Nom" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                        <input type="tel" name="phone" required value="<?= clean($user['phone'] ?? '') ?>" placeholder="Téléphone" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                        <input type="text" name="province" required value="<?= clean($user['province'] ?? '') ?>" placeholder="Province" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                        <input type="text" name="city" required value="<?= clean($user['city'] ?? '') ?>" placeholder="Ville" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                        <input type="text" name="address" required placeholder="Adresse complète" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg md:col-span-2">
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm">
                    <h3 class="font-bold mb-4">Mode de paiement</h3>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 p-3 border-2 rounded-lg cursor-pointer hover:border-blue-600">
                            <input type="radio" name="payment_method" value="cash" checked> <i class="fas fa-money-bill text-green-500"></i> Paiement à la livraison
                        </label>
                        <label class="flex items-center gap-3 p-3 border-2 rounded-lg cursor-pointer hover:border-blue-600">
                            <input type="radio" name="payment_method" value="mobile_money"> <i class="fas fa-mobile-alt text-blue-500"></i> Mobile Money
                        </label>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm h-fit">
                <h3 class="font-bold mb-4">Résumé</h3>
                <div class="space-y-2 mb-4">
                    <?php foreach ($items as $item): ?>
                        <div class="flex justify-between text-sm">
                            <span><?= clean($item['name']) ?> x<?= $item['quantity'] ?></span>
                            <span class="font-semibold"><?= formatPrice($item['price'] * $item['quantity']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="border-t pt-3 space-y-2">
                    <div class="flex justify-between text-sm"><span>Sous-total</span><span><?= formatPrice($subtotal) ?></span></div>
                    <div class="flex justify-between text-sm"><span>Livraison</span><span><?= $shipping === 0 ? 'Gratuite' : formatPrice($shipping) ?></span></div>
                    <div class="flex justify-between text-lg font-bold pt-2 border-t"><span>Total</span><span class="text-blue-600"><?= formatPrice($total) ?></span></div>
                </div>
                <button type="submit" class="w-full py-3 bg-orange-500 text-white rounded-lg font-semibold mt-4 hover:bg-orange-600">Confirmer la commande</button>
            </div>
        </form>
    </div>
</body>
</html>
PHP;

file_put_contents($projectDir . '/checkout.php', $checkoutPhp);
log_msg("✓ checkout.php créé");

// logout.php
$logoutPhp = <<<'PHP'
<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/auth.php';
logoutUser();
header("Location: " . BASE_URL . '/login.php');
exit;
PHP;

file_put_contents($projectDir . '/logout.php', $logoutPhp);
log_msg("✓ logout.php créé");

// profile.php
$profilePhp = <<<'PHP'
<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$user = getCurrentUser($pdo);
$orders = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$orders->execute([$userId = $_SESSION['user_id']]);
$orders = $orders->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Profil - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af', secondary: '#f97316' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="max-w-5xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-6">Mon Profil</h1>
        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm">
                <div class="text-center mb-4">
                    <div class="w-20 h-20 bg-blue-600 rounded-full flex items-center justify-center text-white text-3xl font-bold mx-auto mb-3"><?= strtoupper(substr($user['first_name'], 0, 1)) ?></div>
                    <p class="font-bold"><?= clean($user['first_name'] . ' ' . $user['last_name']) ?></p>
                    <p class="text-xs text-gray-500"><?= clean($user['email']) ?></p>
                </div>
                <nav class="space-y-1">
                    <a href="profile.php" class="block px-4 py-2 bg-blue-50 dark:bg-blue-900/20 text-blue-600 rounded-lg font-semibold">Mon profil</a>
                    <a href="orders.php" class="block px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">Mes commandes</a>
                    <a href="favorites.php" class="block px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">Favoris</a>
                    <?php if (isSeller() || isAdmin()): ?>
                        <a href="admin/" class="block px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">Dashboard</a>
                        <a href="sell.php" class="block px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">Vendre</a>
                    <?php endif; ?>
                    <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg">Déconnexion</a>
                </nav>
            </div>
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm">
                    <h2 class="font-bold mb-4">Informations personnelles</h2>
                    <form method="POST" class="space-y-4">
                        <div class="grid md:grid-cols-2 gap-4">
                            <input type="text" name="first_name" value="<?= clean($user['first_name']) ?>" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                            <input type="text" name="last_name" value="<?= clean($user['last_name']) ?>" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                            <input type="tel" name="phone" value="<?= clean($user['phone']) ?>" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                            <input type="text" name="city" value="<?= clean($user['city']) ?>" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                        </div>
                        <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg font-semibold">Enregistrer</button>
                    </form>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm">
                    <h2 class="font-bold mb-4">Mes commandes (<?= count($orders) ?>)</h2>
                    <?php if (empty($orders)): ?>
                        <p class="text-gray-500">Aucune commande</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($orders as $o): ?>
                                <div class="border dark:border-gray-700 rounded-lg p-3">
                                    <div class="flex justify-between">
                                        <span class="font-semibold"><?= clean($o['order_number']) ?></span>
                                        <span class="text-blue-600 font-bold"><?= formatPrice($o['total']) ?></span>
                                    </div>
                                    <p class="text-xs text-gray-500"><?= formatDate($o['created_at']) ?> - <?= $o['status'] ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
PHP;

file_put_contents($projectDir . '/profile.php', $profilePhp);
log_msg("✓ profile.php créé");

// favorites.php
$favoritesPhp = <<<'PHP'
<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$favorites = $pdo->prepare("SELECT p.*, pi.image_path FROM favorites f JOIN products p ON f.product_id = p.id LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1 WHERE f.user_id = ?");
$favorites->execute([$_SESSION['user_id']]);
$favorites = $favorites->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Favoris - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-6"><i class="fas fa-heart text-red-500 mr-2"></i>Mes Favoris</h1>
        <?php if (empty($favorites)): ?>
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-12 text-center">
                <i class="far fa-heart text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">Aucun favori</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($favorites as $p): ?>
                    <a href="product.php?slug=<?= $p['slug'] ?>" class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow hover:shadow-xl transition">
                        <img src="<?= $p['image_path'] ?? 'https://via.placeholder.com/300' ?>" class="w-full h-40 object-cover">
                        <div class="p-3">
                            <h3 class="font-semibold text-sm"><?= clean($p['name']) ?></h3>
                            <p class="text-blue-600 font-bold mt-2"><?= formatPrice($p['price']) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
PHP;

file_put_contents($projectDir . '/favorites.php', $favoritesPhp);
log_msg("✓ favorites.php créé");

// sell.php
$sellPhp = <<<'PHP'
<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
if (!isSeller()) redirect(BASE_URL . '/register.php?role=seller');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $description = clean($_POST['description'] ?? '');
    
    if (empty($name)) $errors[] = 'Nom requis';
    if ($price <= 0) $errors[] = 'Prix invalide';
    if ($categoryId <= 0) $errors[] = 'Catégorie requise';
    
    if (empty($errors)) {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name)) . '-' . time();
        $stmt = $pdo->prepare("INSERT INTO products (seller_id, category_id, name, slug, description, price, stock, is_new, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)");
        $stmt->execute([$_SESSION['user_id'], $categoryId, $name, $slug, $description, $price, $stock]);
        $productId = $pdo->lastInsertId();
        
        if (!empty($_FILES['image']['name'])) {
            $uploadDir = UPLOADS_PATH . '/products/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('prod_') . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, 1)")->execute([$productId, UPLOADS_URL . '/products/' . $filename]);
            }
        }
        
        setFlash('success', 'Produit publié !');
        redirect(BASE_URL . '/profile.php');
    }
}

$categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Vendre - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af', secondary: '#f97316' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="max-w-3xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-6"><i class="fas fa-store text-orange-500 mr-2"></i>Publier un produit</h1>
        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 text-red-700 px-4 py-3 rounded-lg mb-4">
                <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm space-y-4">
            <input type="text" name="name" required placeholder="Nom du produit *" class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
            <select name="category_id" required class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                <option value="">Catégorie *</option>
                <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option><?php endforeach; ?>
            </select>
            <div class="grid grid-cols-2 gap-4">
                <input type="number" name="price" required min="0" placeholder="Prix (FBu) *" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                <input type="number" name="stock" required min="0" placeholder="Stock *" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
            </div>
            <textarea name="description" rows="5" placeholder="Description" class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg"></textarea>
            <input type="file" name="image" accept="image/*" required class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
            <button type="submit" class="w-full py-3 bg-orange-500 text-white rounded-lg font-semibold hover:bg-orange-600"><i class="fas fa-paper-plane mr-2"></i>Publier</button>
        </form>
    </div>
</body>
</html>
PHP;

file_put_contents($projectDir . '/sell.php', $sellPhp);
log_msg("✓ sell.php créé");

// admin/index.php
$adminIndexPhp = <<<'PHP'
<?php





requireAdmin();

$stats = [
    'products' => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'orders' => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn(),
    'revenue' => $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn(),
];

$recentOrders = $pdo->query("SELECT o.*, u.first_name, u.last_name FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Admin - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-8"><i class="fas fa-chart-line text-blue-600 mr-2"></i>Dashboard Admin</h1>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border-l-4 border-blue-500">
                <p class="text-sm text-gray-500">Produits</p>
                <p class="text-2xl font-bold"><?= $stats['products'] ?></p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border-l-4 border-green-500">
                <p class="text-sm text-gray-500">Commandes</p>
                <p class="text-2xl font-bold"><?= $stats['orders'] ?></p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border-l-4 border-orange-500">
                <p class="text-sm text-gray-500">Clients</p>
                <p class="text-2xl font-bold"><?= $stats['users'] ?></p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border-l-4 border-purple-500">
                <p class="text-sm text-gray-500">Revenus</p>
                <p class="text-lg font-bold"><?= formatPrice($stats['revenue']) ?></p>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-6 border-b dark:border-gray-700">
                <h2 class="text-lg font-bold">Commandes récentes</h2>
            </div>
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">N°</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($recentOrders as $o): ?>
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-blue-600"><?= clean($o['order_number']) ?></td>
                            <td class="px-6 py-4 text-sm"><?= clean($o['first_name'] . ' ' . $o['last_name']) ?></td>
                            <td class="px-6 py-4 text-sm font-bold"><?= formatPrice($o['total']) ?></td>
                            <td class="px-6 py-4"><span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800"><?= $o['status'] ?></span></td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?= formatDate($o['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
PHP;

file_put_contents($projectDir . '/admin/index.php', $adminIndexPhp);
log_msg("✓ admin/index.php créé");

// api/favorites.php
$apiFavorites = <<<'PHP'
<?php





header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$userId = $_SESSION['user_id'];
$productId = (int)($_POST['product_id'] ?? 0);

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Produit invalide']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND product_id = ?");
$stmt->execute([$userId, $productId]);

if ($stmt->fetch()) {
    $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?")->execute([$userId, $productId]);
    echo json_encode(['success' => true, 'action' => 'removed']);
} else {
    $pdo->prepare("INSERT INTO favorites (user_id, product_id) VALUES (?, ?)")->execute([$userId, $productId]);
    echo json_encode(['success' => true, 'action' => 'added']);
}
PHP;

file_put_contents($projectDir . '/api/favorites.php', $apiFavorites);
log_msg("✓ api/favorites.php créé");

// api/cart.php
$apiCart = <<<'PHP'
<?php





header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$userId = $_SESSION['user_id'];
$productId = (int)($_POST['product_id'] ?? 0);
$quantity = max(1, (int)($_POST['quantity'] ?? 1));

$stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
$stmt->execute([$userId, $productId, $quantity]);

$count = getCartCount($userId, $pdo);
echo json_encode(['success' => true, 'count' => $count, 'message' => 'Produit ajouté']);
PHP;

file_put_contents($projectDir . '/api/cart.php', $apiCart);
log_msg("✓ api/cart.php créé");

// ============================================
// AFFICHAGE DU RÉSULTAT
// ============================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✅ Installation PekDev Market - Terminée !</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #f97316 100%); }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease-out; }
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background: #f97316;
            animation: confetti-fall 3s linear infinite;
        }
        @keyframes confetti-fall {
            to { transform: translateY(100vh) rotate(360deg); opacity: 0; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Confettis -->
    <?php for ($i = 0; $i < 30; $i++): ?>
        <div class="confetti" style="left: <?= rand(0, 100) ?>%; animation-delay: <?= rand(0, 3000) ?>ms; background: <?= ['#1e40af', '#f97316', '#10b981', '#ef4444', '#8b5cf6'][rand(0, 4)] ?>;"></div>
    <?php endfor; ?>

    <div class="gradient-bg py-12">
        <div class="max-w-4xl mx-auto px-4 text-center text-white">
            <div class="w-24 h-24 bg-white rounded-full flex items-center justify-center mx-auto mb-6 shadow-2xl animate-fade-in-up">
                <i class="fas fa-check text-green-500 text-5xl"></i>
            </div>
            <h1 class="text-4xl md:text-5xl font-bold mb-4 animate-fade-in-up">🎉 Installation terminée !</h1>
            <p class="text-xl text-white/90 animate-fade-in-up">PekDev Market est prêt à être utilisé</p>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 py-8">
        
        <!-- Logs -->
        <div class="bg-white rounded-2xl shadow-xl p-6 md:p-8 mb-6 animate-fade-in-up">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-list-check text-blue-600"></i> Journal d'installation
            </h2>
            <div class="space-y-2 max-h-96 overflow-y-auto">
                <?php foreach ($logs as $log): 
                    $color = $log['type'] === 'success' ? 'green' : ($log['type'] === 'error' ? 'red' : 'blue');
                    $icon = $log['type'] === 'success' ? 'check-circle' : ($log['type'] === 'error' ? 'times-circle' : 'info-circle');
                ?>
                    <div class="flex items-center gap-3 p-2 bg-<?= $color ?>-50 border border-<?= $color ?>-200 rounded-lg">
                        <i class="fas fa-<?= $icon ?> text-<?= $color ?>-500"></i>
                        <span class="text-sm text-<?= $color ?>-800"><?= $log['msg'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Informations importantes -->
        <div class="grid md:grid-cols-2 gap-6 mb-6">
            
            <!-- URL d'accès -->
            <div class="bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl p-6 text-white shadow-xl animate-fade-in-up">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <i class="fas fa-globe"></i> URL d'accès
                </h3>
                <div class="bg-white/20 rounded-lg p-3 mb-3">
                    <p class="text-xs text-white/80 mb-1">Site principal</p>
                    <a href="<?= $baseUrl ?>" class="text-lg font-bold hover:underline break-all"><?= $baseUrl ?></a>
                </div>
                <div class="bg-white/20 rounded-lg p-3">
                    <p class="text-xs text-white/80 mb-1">Admin</p>
                    <a href="<?= $baseUrl ?>/admin/" class="text-lg font-bold hover:underline break-all"><?= $baseUrl ?>/admin/</a>
                </div>
            </div>

            <!-- Comptes -->
            <div class="bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl p-6 text-white shadow-xl animate-fade-in-up">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <i class="fas fa-key"></i> Comptes de connexion
                </h3>
                <div class="space-y-2">
                    <div class="bg-white/20 rounded-lg p-3">
                        <p class="text-xs text-white/80">👑 Admin</p>
                        <p class="font-semibold">admin@pekdev.bi / admin123</p>
                    </div>
                    <div class="bg-white/20 rounded-lg p-3">
                        <p class="text-xs text-white/80">🏪 Vendeur</p>
                        <p class="font-semibold">vendeur@pekdev.bi / admin123</p>
                    </div>
                    <div class="bg-white/20 rounded-lg p-3">
                        <p class="text-xs text-white/80">👤 Client</p>
                        <p class="font-semibold">client@pekdev.bi / admin123</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Emplacement des fichiers -->
        <div class="bg-white rounded-2xl shadow-xl p-6 md:p-8 mb-6 animate-fade-in-up">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-folder-open text-orange-500"></i> Emplacement des fichiers
            </h2>
            <div class="bg-gray-50 rounded-lg p-4 font-mono text-sm">
                <p class="text-gray-600 mb-2">📁 Vos fichiers sont installés dans :</p>
                <p class="text-blue-600 font-bold text-base break-all"><?= $projectDir ?></p>
                <p class="text-gray-500 mt-3 text-xs">⚠️ IMPORTANT : Supprimez ce fichier (pekdev-install.php) après installation !</p>
            </div>
        </div>

        <!-- Produits installés -->
        <div class="bg-white rounded-2xl shadow-xl p-6 md:p-8 mb-6 animate-fade-in-up">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-box text-blue-600"></i> Produits installés
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                <?php
                try {
                    $pdo2 = new PDO("mysql:host=localhost;dbname=$dbName", $dbUser, $dbPass);
                    $products = $pdo2->query("SELECT name, slug, price FROM products LIMIT 6")->fetchAll();
                    foreach ($products as $p):
                ?>
                    <a href="<?= $baseUrl ?>/product.php?slug=<?= $p['slug'] ?>" class="bg-gray-50 hover:bg-gray-100 rounded-lg p-3 transition">
                        <p class="font-semibold text-sm text-gray-800"><?= htmlspecialchars($p['name']) ?></p>
                        <p class="text-blue-600 font-bold text-sm mt-1"><?= number_format($p['price'], 0, ',', ' ') ?> FBu</p>
                    </a>
                <?php endforeach; } catch (Exception $e) {} ?>
            </div>
        </div>

        <!-- Sécurité -->
        <div class="bg-yellow-50 border-2 border-yellow-300 rounded-2xl p-6 mb-6 animate-fade-in-up">
            <h2 class="text-xl font-bold text-yellow-900 mb-3 flex items-center gap-2">
                <i class="fas fa-shield-alt"></i> ⚠️ Sécurité - Actions requises
            </h2>
            <ul class="space-y-2 text-yellow-800">
                <li class="flex items-start gap-2">
                    <i class="fas fa-check-circle mt-1"></i>
                    <span><strong>Supprimez ce fichier</strong> : <code class="bg-yellow-100 px-2 py-0.5 rounded">pekdev-install.php</code></span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-check-circle mt-1"></i>
                    <span><strong>Changez les mots de passe</strong> des comptes de démo</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-check-circle mt-1"></i>
                    <span><strong>Configurez</strong> <code class="bg-yellow-100 px-2 py-0.5 rounded">config/database.php</code> avec vos vrais identifiants MySQL</span>
                </li>
            </ul>
        </div>

        <!-- Boutons d'action -->
        <div class="grid md:grid-cols-3 gap-4 animate-fade-in-up">
            <a href="<?= $baseUrl ?>" class="py-4 bg-blue-600 text-white rounded-xl font-semibold text-center hover:bg-blue-700 shadow-lg flex items-center justify-center gap-2">
                <i class="fas fa-home"></i> Aller au site
            </a>
            <a href="<?= $baseUrl ?>/login.php" class="py-4 bg-orange-500 text-white rounded-xl font-semibold text-center hover:bg-orange-600 shadow-lg flex items-center justify-center gap-2">
                <i class="fas fa-sign-in-alt"></i> Se connecter
            </a>
            <a href="<?= $baseUrl ?>/admin/" class="py-4 bg-purple-600 text-white rounded-xl font-semibold text-center hover:bg-purple-700 shadow-lg flex items-center justify-center gap-2">
                <i class="fas fa-user-shield"></i> Dashboard Admin
            </a>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 text-gray-500 text-sm">
            <p>🇧🇮 PekDev Market - La marketplace du Burundi</p>
            <p class="mt-1">Installé le <?= date('d/m/Y à H:i') ?></p>
        </div>
    </div>
</body>
</html>