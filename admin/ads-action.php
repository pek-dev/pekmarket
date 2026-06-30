<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: ' . BASE_URL); exit; }

$adId = intval($_POST['ad_id'] ?? 0);
$action = $_POST['action'] ?? '';
$notes = clean($_POST['notes'] ?? '');

if ($adId > 0) {
    switch ($action) {
        case 'approve':
            $pdo->prepare("UPDATE ad_campaigns SET status = 'active', admin_notes = ? WHERE id = ?")->execute([$notes, $adId]);
            break;
        case 'reject':
            $pdo->prepare("UPDATE ad_campaigns SET status = 'rejected', admin_notes = ? WHERE id = ?")->execute([$notes, $adId]);
            break;
        case 'pause':
            $pdo->prepare("UPDATE ad_campaigns SET status = 'paused' WHERE id = ?")->execute([$adId]);
            break;
        case 'resume':
            $pdo->prepare("UPDATE ad_campaigns SET status = 'active' WHERE id = ?")->execute([$adId]);
            break;
        case 'delete':
            $pdo->prepare("DELETE FROM ad_campaigns WHERE id = ?")->execute([$adId]);
            break;
    }
}

header('Location: ' . BASE_URL . '/admin/ads.php');
exit;