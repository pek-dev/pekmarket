<?php
// Charger le bootstrap si pas déjà fait
if (!defined('BOOTSTRAP_LOADED')) {
    require_once __DIR__ . '/../config/bootstrap.php';
}

// ============================================
// RÉCUPÉRATION DES CONFIGURATIONS
// ============================================
$siteSettings = [];
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE is_active = 1");
    while ($row = $settingsStmt->fetch()) {
        $siteSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $siteSettings = [
        'site_name' => 'PekDev Market',
        'site_tagline' => 'La marketplace du Burundi',
        'site_logo_text' => 'P',
        'site_primary_color' => '#1e40af',
        'site_secondary_color' => '#f97316',
        'banner_enabled' => '1',
        'banner_text' => 'Livraison gratuite à Bujumbura pour toute commande supérieure à 100,000 FBu !',
        'banner_icon' => 'fas fa-truck',
        'banner_color_from' => '#1e40af',
        'banner_color_to' => '#f97316',
        'search_placeholder' => 'Rechercher un produit...',
        'menu_home' => 'Accueil',
        'menu_categories' => 'Catégories',
        'menu_products' => 'Produits',
        'menu_promotions' => 'Promotions',
        'show_cart' => '1',
        'show_favorites' => '1',
        'show_dark_mode' => '1'
    ];
}

function getSetting($key, $default = '') {
    global $siteSettings;
    return $siteSettings[$key] ?? $default;
}

// ============================================
// VARIABLES PAR DÉFAUT
// ============================================
if (!isset($pageTitle)) $pageTitle = 'PekDev Market';
if (!isset($pageDescription)) $pageDescription = 'La plus grande marketplace du Burundi';

$cartCount = 0;
$favoritesCount = 0;
$totalUnread = 0;
$userName = '';
$userInitial = 'U';
$userEmail = '';
$userRole = '';

if (isLoggedIn()) {
    try {
        $cartCount = getCartCount($_SESSION['user_id'], $pdo);
        $favoritesCount = getFavoritesCount($_SESSION['user_id'], $pdo);
        
        // Messages non lus
        $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE to_user_id = ? AND is_read = 0");
        $unreadStmt->execute([$_SESSION['user_id']]);
        $totalUnread = $unreadStmt->fetchColumn();
    } catch (Exception $e) {}
    
    if (isset($_SESSION['user_name']) && !empty($_SESSION['user_name'])) {
        $userName = $_SESSION['user_name'];
    } elseif (isset($_SESSION['user_first_name']) && isset($_SESSION['user_last_name'])) {
        $userName = $_SESSION['user_first_name'] . ' ' . $_SESSION['user_last_name'];
    } else {
        $userName = 'Utilisateur';
    }
    
    $userInitial = strtoupper(mb_substr($userName, 0, 1, 'UTF-8'));
    $userEmail = $_SESSION['user_email'] ?? '';
    $userRole = $_SESSION['user_role'] ?? 'customer';
}

// Récupérer les catégories
$categories = [];
try {
    $categoriesStmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 10");
    $categories = $categoriesStmt->fetchAll();
} catch (Exception $e) {}

$currentFile = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="description" content="<?= clean($pageDescription) ?>">
    <meta name="theme-color" content="<?= getSetting('site_primary_color', '#1e40af') ?>">
    <meta name="base-url" content="<?= BASE_URL ?>">
    <meta name="csrf-token" content="<?= generateCSRFToken() ?>">
    <title><?= clean($pageTitle) ?> - <?= getSetting('site_name', 'PekDev Market') ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '<?= getSetting('site_primary_color', '#1e40af') ?>',
                            Dark: '<?= getSetting('site_primary_color', '#1e3a8a') ?>',
                            Light: '#3b82f6'
                        },
                        secondary: {
                            DEFAULT: '<?= getSetting('site_secondary_color', '#f97316') ?>',
                            Dark: '#ea580c'
                        },
                        accent: '#10b981'
                    },
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
                }
            }
        }
    </script>
    
    <style>
        .dark body { background-color: #111827; }
        body { font-family: 'Inter', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #475569; }
        .dropdown-enter { animation: dropdownIn 0.2s ease-out forwards; }
        @keyframes dropdownIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .banner-gradient {
            background: linear-gradient(to right, <?= getSetting('banner_color_from', '#1e40af') ?>, <?= getSetting('banner_color_to', '#f97316') ?>);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.6s ease-out forwards; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 transition-colors duration-300 antialiased">

    <!-- ============================================
         BANNIÈRE PROMOTIONNELLE
         ============================================ -->
    <?php if (getSetting('banner_enabled', '1') == '1'): ?>
    <div id="topBanner" class="banner-gradient text-white text-center py-2 text-xs sm:text-sm px-4 relative">
        <p class="flex items-center justify-center gap-2 flex-wrap">
            <i class="<?= getSetting('banner_icon', 'fas fa-truck') ?> text-yellow-300"></i>
            <span><?= clean(getSetting('banner_text', 'Livraison gratuite à Bujumbura pour toute commande supérieure à 100,000 FBu !')) ?></span>
            <button onclick="document.getElementById('topBanner').style.display='none'; localStorage.setItem('hideBanner', 'true');" 
                    class="ml-2 hover:bg-white/20 rounded-full p-1 transition" aria-label="Fermer">
                <i class="fas fa-times"></i>
            </button>
        </p>
    </div>
    <?php endif; ?>

    <!-- ============================================
         HEADER PRINCIPAL
         ============================================ -->
    <header class="bg-white dark:bg-gray-800 shadow-md sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 md:h-20">
                
                <!-- Menu mobile -->
                <button id="mobileMenuBtn" class="md:hidden p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition" aria-label="Menu">
                    <i class="fas fa-bars text-2xl"></i>
                </button>

                <!-- Logo -->
                <a href="<?= BASE_URL ?>/" class="flex items-center gap-2 flex-shrink-0">
                    <div class="w-9 h-9 md:w-10 md:h-10 bg-blue-600 rounded-lg flex items-center justify-center shadow-lg">
                        <span class="text-white font-bold text-lg md:text-xl"><?= clean(getSetting('site_logo_text', 'P')) ?></span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xl md:text-2xl font-bold text-blue-600 dark:text-white leading-none"><?= clean(getSetting('site_name', 'PekDev')) ?></span>
                        <span class="text-[10px] md:text-xs text-orange-500 font-medium"><?= clean(getSetting('site_tagline', 'Market')) ?></span>
                    </div>
                </a>

                <!-- Recherche desktop -->
                <form action="<?= BASE_URL ?>/search.php" method="GET" class="hidden md:flex flex-1 max-w-2xl mx-8">
                    <div class="relative w-full">
                        <input type="text" name="q" 
                               class="w-full pl-12 pr-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-full focus:outline-none focus:border-blue-600 transition"
                               placeholder="<?= clean(getSetting('search_placeholder', 'Rechercher un produit...')) ?>"
                               value="<?= isset($_GET['q']) ? clean($_GET['q']) : '' ?>">
                        <button type="submit" class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-blue-600">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>

                <!-- Recherche mobile -->
                <button id="mobileSearchBtn" class="md:hidden p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition" aria-label="Rechercher">
                    <i class="fas fa-search text-xl"></i>
                </button>

                <!-- Actions droite -->
                <div class="flex items-center gap-1 md:gap-3">
                    
                    <!-- Dark mode -->
                    <?php if (getSetting('show_dark_mode', '1') == '1'): ?>
                    <button id="themeToggle" class="p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition" aria-label="Changer de thème">
                        <i class="fas fa-moon text-xl dark:hidden"></i>
                        <i class="fas fa-sun text-xl hidden dark:block text-yellow-400"></i>
                    </button>
                    <?php endif; ?>
                    
                    <?php if (isLoggedIn()): ?>
                        <!-- Messages -->
                        <a href="<?= BASE_URL ?>/messages.php" class="relative p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg hidden sm:block transition" aria-label="Messages">
                            <i class="fas fa-comment-dots text-xl"></i>
                            <?php if ($totalUnread > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center font-bold"><?= $totalUnread ?></span>
                            <?php endif; ?>
                        </a>
                        
                        <!-- Favoris -->
                        <?php if (getSetting('show_favorites', '1') == '1'): ?>
                        <a href="<?= BASE_URL ?>/favorites.php" class="relative p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg hidden sm:block transition" aria-label="Favoris">
                            <i class="far fa-heart text-xl"></i>
                            <?php if ($favoritesCount > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center font-bold"><?= $favoritesCount ?></span>
                            <?php endif; ?>
                        </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Panier -->
                    <?php if (getSetting('show_cart', '1') == '1'): ?>
                    <a href="<?= BASE_URL ?>/cart.php" class="relative p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition" aria-label="Panier">
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <?php if ($cartCount > 0): ?>
                            <span id="cartCount" class="absolute -top-1 -right-1 bg-orange-500 text-white text-[10px] rounded-full w-5 h-5 flex items-center justify-center font-bold"><?= $cartCount ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>

                    <?php if (isLoggedIn()): ?>
                        <!-- Menu utilisateur -->
                        <div class="relative">
                            <button onclick="toggleUserMenu()" class="flex items-center gap-2 p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition" aria-label="Menu utilisateur">
                                <div class="w-8 h-8 bg-gradient-to-br from-blue-600 to-orange-500 rounded-full flex items-center justify-center text-white font-semibold text-sm shadow-md">
                                    <?= $userInitial ?>
                                </div>
                                <span class="hidden md:inline text-sm font-medium text-gray-700 dark:text-gray-200 max-w-[120px] truncate"><?= clean($userName) ?></span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 hidden md:inline"></i>
                            </button>
                            
                            <!-- Dropdown -->
                            <div id="userMenu" class="hidden absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 py-2 z-50 dropdown-enter">
                                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                                    <p class="text-sm font-semibold text-gray-800 dark:text-white truncate"><?= clean($userName) ?></p>
                                    <p class="text-xs text-gray-500 truncate"><?= clean($userEmail) ?></p>
                                    <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full 
                                        <?= $userRole == 'admin' ? 'bg-red-100 text-red-800' : 
                                           ($userRole == 'seller' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800') ?>">
                                        <?= ucfirst($userRole) ?>
                                    </span>
                                </div>
                                
                                <div class="py-1">
                                    <a href="<?= BASE_URL ?>/profile.php" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                        <i class="fas fa-user w-4 text-gray-400"></i> Mon profil
                                    </a>
                                    <a href="<?= BASE_URL ?>/messages.php" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition relative">
                                        <i class="fas fa-comment-dots w-4 text-gray-400"></i> Messages
                                        <?php if ($totalUnread > 0): ?>
                                            <span class="ml-auto bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full font-bold"><?= $totalUnread ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <a href="<?= BASE_URL ?>/orders.php" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                        <i class="fas fa-box w-4 text-gray-400"></i> Mes commandes
                                    </a>
                                    <a href="<?= BASE_URL ?>/favorites.php" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition sm:hidden">
                                        <i class="fas fa-heart w-4 text-gray-400"></i> Mes favoris
                                    </a>
                                </div>
                                
                                <?php if ($userRole === 'seller' || $userRole === 'admin'): ?>
                                    <div class="border-t border-gray-200 dark:border-gray-700 py-1">
                                        <a href="<?= BASE_URL ?>/dashboard/<?= $userRole ?>.php" class="flex items-center gap-3 px-4 py-2 text-sm text-blue-600 font-semibold hover:bg-blue-50 dark:hover:bg-blue-900/20 transition">
                                            <i class="fas fa-chart-line w-4"></i> Dashboard
                                        </a>
                                        <?php if ($userRole === 'seller'): ?>
                                            <a href="<?= BASE_URL ?>/shop.php?seller=<?= $_SESSION['user_id'] ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                                <i class="fas fa-store w-4 text-gray-400"></i> Ma boutique
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="border-t border-gray-200 dark:border-gray-700 mt-1 pt-1">
                                    <a href="<?= BASE_URL ?>/logout.php" class="flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                                        <i class="fas fa-sign-out-alt w-4"></i> Déconnexion
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/login.php" class="hidden md:block px-4 py-2 border-2 border-blue-600 text-blue-600 dark:text-white rounded-lg hover:bg-blue-600 hover:text-white font-semibold text-sm transition">
                            Connexion
                        </a>
                        <a href="<?= BASE_URL ?>/register.php" class="hidden md:block px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 font-semibold text-sm transition shadow-md">
                            Inscription
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recherche mobile -->
            <div id="mobileSearch" class="hidden md:hidden pb-4">
                <form action="<?= BASE_URL ?>/search.php" method="GET">
                    <div class="relative">
                        <input type="text" name="q" class="w-full pl-12 pr-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-full" placeholder="<?= clean(getSetting('search_placeholder', 'Rechercher...')) ?>">
                        <button type="submit" class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Navigation desktop -->
            <nav class="hidden md:flex items-center gap-6 py-3 border-t border-gray-100 dark:border-gray-700">
                <a href="<?= BASE_URL ?>/" class="flex items-center gap-1 font-semibold transition <?= $currentFile == 'index.php' ? 'text-blue-600' : 'text-gray-600 dark:text-gray-300 hover:text-blue-600' ?>">
                    <i class="fas fa-home"></i> <?= clean(getSetting('menu_home', 'Accueil')) ?>
                </a>
                
                <div class="relative group">
                    <button class="text-gray-600 dark:text-gray-300 hover:text-blue-600 flex items-center gap-1 font-medium transition">
                        <i class="fas fa-th-large"></i> <?= clean(getSetting('menu_categories', 'Catégories')) ?>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </button>
                    
                    <?php if (count($categories) > 0): ?>
                    <div class="absolute left-0 top-full mt-1 w-72 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 py-2 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
                        <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700 mb-2">
                            <p class="text-xs font-semibold text-gray-500 uppercase">Toutes les catégories</p>
                        </div>
                        <?php foreach ($categories as $cat): ?>
                            <a href="<?= BASE_URL ?>/category.php?slug=<?= $cat['slug'] ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                <i class="<?= $cat['icon'] ?> text-<?= $cat['color'] ?>-500 w-5 text-center"></i>
                                <span><?= clean($cat['name']) ?></span>
                                <i class="fas fa-chevron-right text-xs text-gray-400 ml-auto"></i>
                            </a>
                        <?php endforeach; ?>
                        <div class="border-t border-gray-200 dark:border-gray-700 mt-2 pt-2">
                            <a href="<?= BASE_URL ?>/categories.php" class="flex items-center gap-3 px-4 py-2 text-sm text-blue-600 font-semibold hover:bg-blue-50 dark:hover:bg-blue-900/20 transition">
                                <i class="fas fa-th w-5"></i> Voir toutes les catégories
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <a href="<?= BASE_URL ?>/products.php" class="text-gray-600 dark:text-gray-300 hover:text-blue-600 font-medium transition <?= $currentFile == 'products.php' ? 'text-blue-600' : '' ?>">
                    <?= clean(getSetting('menu_products', 'Produits')) ?>
                </a>
                <a href="<?= BASE_URL ?>/promotions.php" class="text-gray-600 dark:text-gray-300 hover:text-blue-600 font-medium transition <?= $currentFile == 'promotions.php' ? 'text-blue-600' : '' ?>">
                    <i class="fas fa-fire text-orange-500 mr-1"></i><?= clean(getSetting('menu_promotions', 'Promotions')) ?>
                </a>
                
                <?php if (isLoggedIn() && ($userRole === 'seller' || $userRole === 'admin')): ?>
                    <a href="<?= BASE_URL ?>/dashboard/<?= $userRole ?>.php" class="ml-auto text-gray-600 dark:text-gray-300 hover:text-blue-600 font-medium transition">
                        <i class="fas fa-chart-line mr-1"></i> Dashboard
                    </a>
                <?php endif; ?>
            </nav>
        </div>

        <!-- Menu mobile -->
        <div id="mobileMenu" class="hidden md:hidden bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 absolute w-full shadow-xl z-50">
            <div class="px-4 py-4 space-y-2 max-h-[70vh] overflow-y-auto custom-scrollbar">
                
                <?php if (isLoggedIn()): ?>
                    <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg mb-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-orange-500 rounded-full flex items-center justify-center text-white font-bold"><?= $userInitial ?></div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-800 dark:text-white truncate"><?= clean($userName) ?></p>
                            <p class="text-xs text-gray-500 truncate"><?= clean($userEmail) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <a href="<?= BASE_URL ?>/" class="block py-3 px-4 text-blue-600 font-semibold bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <i class="fas fa-home mr-2"></i> <?= clean(getSetting('menu_home', 'Accueil')) ?>
                </a>
                
                <a href="<?= BASE_URL ?>/products.php" class="block py-3 px-4 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg">
                    <i class="fas fa-box mr-2"></i> <?= clean(getSetting('menu_products', 'Produits')) ?>
                </a>
                
                <a href="<?= BASE_URL ?>/promotions.php" class="block py-3 px-4 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg">
                    <i class="fas fa-fire text-orange-500 mr-2"></i> <?= clean(getSetting('menu_promotions', 'Promotions')) ?>
                </a>
                
                <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-xs font-semibold text-gray-500 uppercase px-4 mb-2">Catégories</p>
                    <?php foreach ($categories as $cat): ?>
                        <a href="<?= BASE_URL ?>/category.php?slug=<?= $cat['slug'] ?>" class="flex items-center gap-3 py-2 px-4 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg">
                            <i class="<?= $cat['icon'] ?> text-<?= $cat['color'] ?>-500 w-5"></i>
                            <?= clean($cat['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <?php if (isLoggedIn()): ?>
                    <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs font-semibold text-gray-500 uppercase px-4 mb-2">Mon compte</p>
                        <a href="<?= BASE_URL ?>/profile.php" class="flex items-center gap-3 py-2 px-4 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg">
                            <i class="fas fa-user w-5"></i> Mon profil
                        </a>
                        <a href="<?= BASE_URL ?>/messages.php" class="flex items-center gap-3 py-2 px-4 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg relative">
                            <i class="fas fa-comment-dots w-5"></i> Messages
                            <?php if ($totalUnread > 0): ?>
                                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-0.5 rounded-full font-bold"><?= $totalUnread ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="<?= BASE_URL ?>/orders.php" class="flex items-center gap-3 py-2 px-4 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg">
                            <i class="fas fa-box w-5"></i> Mes commandes
                        </a>
                        <a href="<?= BASE_URL ?>/favorites.php" class="flex items-center gap-3 py-2 px-4 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg">
                            <i class="fas fa-heart w-5"></i> Mes favoris
                        </a>
                        <?php if ($userRole === 'seller' || $userRole === 'admin'): ?>
                            <a href="<?= BASE_URL ?>/dashboard/<?= $userRole ?>.php" class="flex items-center gap-3 py-2 px-4 text-blue-600 font-semibold hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg">
                                <i class="fas fa-chart-line w-5"></i> Dashboard
                            </a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/logout.php" class="flex items-center gap-3 py-2 px-4 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg mt-2">
                            <i class="fas fa-sign-out-alt w-5"></i> Déconnexion
                        </a>
                    </div>
                <?php else: ?>
                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700 flex gap-3">
                        <a href="<?= BASE_URL ?>/login.php" class="flex-1 py-3 border-2 border-blue-600 text-blue-600 rounded-lg font-semibold text-center hover:bg-blue-600 hover:text-white transition">
                            Connexion
                        </a>
                        <a href="<?= BASE_URL ?>/register.php" class="flex-1 py-3 bg-orange-500 text-white rounded-lg font-semibold text-center hover:bg-orange-600 transition">
                            Inscription
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Messages Flash -->
    <div id="flashContainer">
        <?php if (function_exists('displayFlash')) displayFlash(); ?>
    </div>

    <script>
        // Dark mode
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                document.documentElement.classList.toggle('dark');
                const isDark = document.documentElement.classList.contains('dark');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
            });
            
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        }

        // Menu mobile
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
                const icon = mobileMenuBtn.querySelector('i');
                if (mobileMenu.classList.contains('hidden')) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                } else {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                }
            });
        }

        // Recherche mobile
        const mobileSearchBtn = document.getElementById('mobileSearchBtn');
        const mobileSearch = document.getElementById('mobileSearch');
        if (mobileSearchBtn && mobileSearch) {
            mobileSearchBtn.addEventListener('click', () => {
                mobileSearch.classList.toggle('hidden');
                if (!mobileSearch.classList.contains('hidden')) {
                    mobileSearch.querySelector('input').focus();
                }
            });
        }

        // Menu utilisateur
        function toggleUserMenu() {
            const userMenu = document.getElementById('userMenu');
            if (userMenu) userMenu.classList.toggle('hidden');
        }

        document.addEventListener('click', (e) => {
            const userMenu = document.getElementById('userMenu');
            if (userMenu && !e.target.closest('[onclick*="toggleUserMenu"]') && !e.target.closest('#userMenu')) {
                userMenu.classList.add('hidden');
            }
        });

        // Fermer avec Echap
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const userMenu = document.getElementById('userMenu');
                if (userMenu && !userMenu.classList.contains('hidden')) userMenu.classList.add('hidden');
                
                const mobileMenu = document.getElementById('mobileMenu');
                if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
                    mobileMenu.classList.add('hidden');
                    const icon = document.getElementById('mobileMenuBtn').querySelector('i');
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
        });

        // Auto-cachage bannière
        const topBanner = document.getElementById('topBanner');
        if (topBanner && localStorage.getItem('hideBanner') === 'true') {
            topBanner.style.display = 'none';
        }
    </script>