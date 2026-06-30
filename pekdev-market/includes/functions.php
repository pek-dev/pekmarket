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