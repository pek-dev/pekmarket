<?php
/**
 * Fonctions spécifiques au thème / affichage
 * 
 * ⚠️ ATTENTION : Ne pas redéclarer les fonctions qui existent déjà dans config/functions.php
 * 
 * Fonctions déjà disponibles via config/functions.php :
 * - clean()
 * - formatPrice()
 * - calculateDiscount()
 * - renderStars()
 * - getProductImage()
 * - isFavorite()
 * - getCartCount()
 * - getFavoritesCount()
 * - isLoggedIn()
 * - requireLogin()
 * - redirectByRole()
 * - isSeller()
 * - isAdmin()
 * - generateCSRFToken()
 * - displayFlash()
 * - setFlash()
 * - generateSlug()
 * - formatDate()
 * - timeAgo()
 * - truncate()
 * - getInitials()
 * - getOrderStatusColor()
 * - getOrderStatusLabel()
 */

// ============================================================
// FONCTIONS SPÉCIFIQUES AU THÈME (non dupliquées)
// ============================================================

/**
 * Générer un lien de pagination
 */
if (!function_exists('paginationLink')) {
    function paginationLink($baseUrl, $page, $currentPage) {
        $separator = (strpos($baseUrl, '?') !== false) ? '&' : '?';
        $class = ($page == $currentPage) ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 hover:bg-gray-100';
        return '<a href="' . $baseUrl . $separator . 'page=' . $page . '" class="px-4 py-2 rounded-lg ' . $class . '">' . $page . '</a>';
    }
}

/**
 * Afficher un badge de statut
 */
if (!function_exists('renderStatusBadge')) {
    function renderStatusBadge($status) {
        $colors = [
            'pending' => 'yellow',
            'confirmed' => 'blue',
            'processing' => 'indigo',
            'shipped' => 'purple',
            'delivered' => 'green',
            'cancelled' => 'red'
        ];
        $labels = [
            'pending' => 'En attente',
            'confirmed' => 'Confirmée',
            'processing' => 'En préparation',
            'shipped' => 'Expédiée',
            'delivered' => 'Livrée',
            'cancelled' => 'Annulée'
        ];
        
        $color = $colors[$status] ?? 'gray';
        $label = $labels[$status] ?? ucfirst($status);
        
        return '<span class="px-2 py-1 rounded-full text-xs font-semibold bg-' . $color . '-100 text-' . $color . '-800">' . $label . '</span>';
    }
}

/**
 * Obtenir l'avatar d'un utilisateur
 */
if (!function_exists('getUserAvatar')) {
    function getUserAvatar($user) {
        if (!empty($user['avatar'])) {
            return $user['avatar'];
        }
        return 'https://ui-avatars.com/api/?name=' . urlencode($user['first_name'] . ' ' . $user['last_name']) . '&background=1e40af&color=fff';
    }
}

/**
 * Formater un numéro de téléphone burundais
 */
if (!function_exists('formatPhone')) {
    function formatPhone($phone) {
        $clean = preg_replace('/[^0-9+]/', '', $phone);
        if (strpos($clean, '+257') === 0) {
            return $clean;
        }
        if (strpos($clean, '257') === 0) {
            return '+' . $clean;
        }
        if (strlen($clean) === 8) {
            return '+257 ' . substr($clean, 0, 2) . ' ' . substr($clean, 2, 3) . ' ' . substr($clean, 5);
        }
        return $phone;
    }
}

/**
 * Obtenir les initiales pour un avatar
 */
if (!function_exists('getAvatarInitials')) {
    function getAvatarInitials($firstName, $lastName = '') {
        $initials = strtoupper(mb_substr($firstName, 0, 1, 'UTF-8'));
        if (!empty($lastName)) {
            $initials .= strtoupper(mb_substr($lastName, 0, 1, 'UTF-8'));
        }
        return $initials;
    }
}