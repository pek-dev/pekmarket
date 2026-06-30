<?php
/**
 * Fonctions utilitaires globales de PekDev Market
 */

// ============================================
// FONCTIONS DE SÉCURITÉ
// ============================================

/**
 * Nettoyer une chaîne de caractères (protection XSS)
 */
if (!function_exists('clean')) {
    function clean($string) {
        if ($string === null) return '';
        return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Générer un token CSRF
 */
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Vérifier un token CSRF
 */
if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// ============================================
// FONCTIONS D'AUTHENTIFICATION
// ============================================

/**
 * Vérifier si l'utilisateur est connecté
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

/**
 * Exiger la connexion
 */
if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
    }
}

/**
 * Rediriger selon le rôle
 */
if (!function_exists('redirectByRole')) {
    function redirectByRole() {
        if (!isLoggedIn()) {
            return BASE_URL . '/login.php';
        }
        
        switch ($_SESSION['user_role']) {
            case 'admin':
                return BASE_URL . '/dashboard/admin.php';
            case 'seller':
                return BASE_URL . '/dashboard/seller.php';
            case 'customer':
            default:
                return BASE_URL . '/dashboard/customer.php';
        }
    }
}

/**
 * Vérifier si l'utilisateur est vendeur
 */
if (!function_exists('isSeller')) {
    function isSeller() {
        return isLoggedIn() && $_SESSION['user_role'] === 'seller';
    }
}

/**
 * Vérifier si l'utilisateur est admin
 */
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isLoggedIn() && $_SESSION['user_role'] === 'admin';
    }
}

/**
 * Vérifier si l'utilisateur est client
 */
if (!function_exists('isCustomer')) {
    function isCustomer() {
        return isLoggedIn() && $_SESSION['user_role'] === 'customer';
    }
}

// ============================================
// FONCTIONS DE PANIER ET FAVORIS
// ============================================

/**
 * Compter les articles dans le panier
 */
if (!function_exists('getCartCount')) {
    function getCartCount($userId, $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ?");
            $stmt->execute([$userId]);
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
}

/**
 * Compter les favoris
 */
if (!function_exists('getFavoritesCount')) {
    function getFavoritesCount($userId, $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
            $stmt->execute([$userId]);
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
}

/**
 * Vérifier si un produit est en favori
 */
if (!function_exists('isFavorite')) {
    function isFavorite($userId, $productId, $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$userId, $productId]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            return false;
        }
    }
}

// ============================================
// FONCTIONS D'AFFICHAGE
// ============================================

/**
 * Formater un prix en FBu
 */
if (!function_exists('formatPrice')) {
    function formatPrice($price) {
        return number_format((float)$price, 0, ',', ' ') . ' FBu';
    }
}

/**
 * Calculer le pourcentage de réduction
 */
if (!function_exists('calculateDiscount')) {
    function calculateDiscount($price, $oldPrice) {
        if (!$oldPrice || $oldPrice <= $price) return 0;
        return round((($oldPrice - $price) / $oldPrice) * 100);
    }
}

/**
 * Afficher les étoiles de notation
 */
if (!function_exists('renderStars')) {
    function renderStars($rating) {
        $html = '';
        $fullStars = floor($rating);
        $halfStar = ($rating - $fullStars) >= 0.5;
        $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
        
        for ($i = 0; $i < $fullStars; $i++) {
            $html .= '<i class="fas fa-star text-yellow-400"></i>';
        }
        if ($halfStar) {
            $html .= '<i class="fas fa-star-half-alt text-yellow-400"></i>';
        }
        for ($i = 0; $i < $emptyStars; $i++) {
            $html .= '<i class="far fa-star text-gray-300"></i>';
        }
        return $html;
    }
}

/**
 * Récupérer l'image principale d'un produit
 */
if (!function_exists('getProductImage')) {
    function getProductImage($productId, $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1");
            $stmt->execute([$productId]);
            $image = $stmt->fetch();
            
            if ($image && !empty($image['image_path'])) {
                return $image['image_path'];
            }
        } catch (Exception $e) {
            // Ignorer l'erreur
        }
        
        return 'https://via.placeholder.com/400x400?text=Pas+d\'image';
    }
}

/**
 * Récupérer toutes les images d'un produit
 */
if (!function_exists('getProductImages')) {
    function getProductImages($productId, $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");
            $stmt->execute([$productId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}

// ============================================
// FONCTIONS DE MESSAGES FLASH
// ============================================

/**
 * Définir un message flash
 */
if (!function_exists('setFlash')) {
    function setFlash($message, $type = 'success') {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
}

/**
 * Afficher et supprimer le message flash
 */
if (!function_exists('displayFlash')) {
    function displayFlash() {
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            $type = $_SESSION['flash_type'] ?? 'success';
            
            // Couleurs selon le type
            $colors = [
                'success' => 'bg-green-50 border-green-200 text-green-700',
                'error' => 'bg-red-50 border-red-200 text-red-700',
                'warning' => 'bg-yellow-50 border-yellow-200 text-yellow-700',
                'info' => 'bg-blue-50 border-blue-200 text-blue-700'
            ];
            
            $icons = [
                'success' => 'fa-check-circle',
                'error' => 'fa-exclamation-circle',
                'warning' => 'fa-exclamation-triangle',
                'info' => 'fa-info-circle'
            ];
            
            $colorClass = $colors[$type] ?? $colors['info'];
            $iconClass = $icons[$type] ?? $icons['info'];
            
            echo '<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">';
            echo '<div class="' . $colorClass . ' border px-4 py-3 rounded-xl flex items-center justify-between">';
            echo '<span><i class="fas ' . $iconClass . ' mr-2"></i>' . clean($message) . '</span>';
            echo '<button onclick="this.parentElement.parentElement.remove()" class="hover:opacity-70"><i class="fas fa-times"></i></button>';
            echo '</div></div>';
            
            // Supprimer le message après affichage
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_type']);
        }
    }
}

// ============================================
// FONCTIONS UTILITAIRES
// ============================================

/**
 * Générer un slug unique
 */
if (!function_exists('generateSlug')) {
    function generateSlug($string) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string)));
        return $slug . '-' . substr(uniqid(), -4);
    }
}

/**
 * Formater une date
 */
if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'd/m/Y') {
        if (empty($date)) return '';
        return date($format, strtotime($date));
    }
}

/**
 * Formater une date relative (il y a X temps)
 */
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $now = time();
        $time = strtotime($datetime);
        $diff = $now - $time;
        
        if ($diff < 60) return "À l'instant";
        if ($diff < 3600) return floor($diff / 60) . " min";
        if ($diff < 86400) return floor($diff / 3600) . " h";
        if ($diff < 604800) return floor($diff / 86400) . " j";
        if ($diff < 2592000) return floor($diff / 604800) . " sem";
        if ($diff < 31536000) return floor($diff / 2592000) . " mois";
        return floor($diff / 31536000) . " an(s)";
    }
}

/**
 * Tronquer un texte
 */
if (!function_exists('truncate')) {
    function truncate($text, $length = 100) {
        if (strlen($text) <= $length) return $text;
        return substr($text, 0, $length) . '...';
    }
}

/**
 * Obtenir l'initiale d'un nom
 */
if (!function_exists('getInitials')) {
    function getInitials($firstName, $lastName = '') {
        $initials = strtoupper(substr($firstName, 0, 1));
        if (!empty($lastName)) {
            $initials .= strtoupper(substr($lastName, 0, 1));
        }
        return $initials;
    }
}

/**
 * Obtenir la couleur d'un statut de commande
 */
if (!function_exists('getOrderStatusColor')) {
    function getOrderStatusColor($status) {
        $colors = [
            'pending' => 'yellow',
            'confirmed' => 'blue',
            'processing' => 'indigo',
            'shipped' => 'purple',
            'delivered' => 'green',
            'cancelled' => 'red'
        ];
        return $colors[$status] ?? 'gray';
    }
}

/**
 * Obtenir le label d'un statut de commande
 */
if (!function_exists('getOrderStatusLabel')) {
    function getOrderStatusLabel($status) {
        $labels = [
            'pending' => 'En attente',
            'confirmed' => 'Confirmée',
            'processing' => 'En préparation',
            'shipped' => 'Expédiée',
            'delivered' => 'Livrée',
            'cancelled' => 'Annulée'
        ];
        return $labels[$status] ?? ucfirst($status);
    }
}