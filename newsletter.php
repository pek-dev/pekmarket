<?php
require_once __DIR__ . '/config/bootstrap.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Adresse email invalide.";
    } else {
        try {
            // Vérifier si déjà inscrit
            $stmt = $pdo->prepare("SELECT id FROM newsletters WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $message = "Cet email est déjà inscrit à notre newsletter.";
            } else {
                $pdo->prepare("INSERT INTO newsletters (email, is_active) VALUES (?, 1)")->execute([$email]);
                $message = "✅ Merci ! Vous êtes inscrit à notre newsletter.";
                $success = true;
            }
        } catch (Exception $e) {
            $message = "Erreur lors de l'inscription.";
        }
    }
}

// Redirection avec message
$_SESSION['flash_message'] = $message;
$_SESSION['flash_type'] = $success ? 'success' : 'error';
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL));
exit;