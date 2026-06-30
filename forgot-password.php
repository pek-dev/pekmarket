<?php
require_once __DIR__ . '/config/bootstrap.php';

require_once __DIR__ . '/includes/header.php';



$message = '';
$messageType = 'success';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean(trim($_POST['email'] ?? ''));
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Adresse email invalide.";
    }
    else {
        // Vérifier si l'email existe
        $stmt = $pdo->prepare("SELECT id, first_name FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Générer un token de réinitialisation
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Sauvegarder le token (ajouter une colonne reset_token à la table users si nécessaire)
            // Pour cet exemple, on simule l'envoi
            $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?")
                ->execute([$token, $expires, $user['id']]);
            
            // Envoi d'email (à implémenter avec PHPMailer ou mail())
            // mail($email, "Réinitialisation...", "Lien: ...");
            
            $message = "Si un compte existe avec cette adresse email, vous recevrez un lien de réinitialisation.";
        }
        else {
            // Message générique pour éviter l'énumération d'emails
            $message = "Si un compte existe avec cette adresse email, vous recevrez un lien de réinitialisation.";
        }
    }
}

$pageTitle = 'Mot de passe oublié - PekDev Market';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e40af',
                        primaryDark: '#1e3a8a',
                        secondary: '#f97316',
                        secondaryDark: '#ea580c'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <!-- Logo -->
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-store text-white text-3xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-800">PekDev Market</h1>
            </div>

            <div class="text-center mb-6">
                <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-key text-blue-600 text-3xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Mot de passe oublié ?</h2>
                <p class="text-gray-500">Entrez votre email pour recevoir un lien de réinitialisation</p>
            </div>

            <?php if ($message): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 flex items-start gap-3">
                    <i class="fas fa-check-circle mt-1"></i>
                    <p class="text-sm"><?= clean($message) ?></p>
                </div>
            <?php endif; ?>

            <?php if (count($errors) > 0): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6">
                    <?php foreach ($errors as $error): ?>
                        <p class="text-sm"><?= clean($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Adresse email</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" name="email" required
                               class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-600 transition"
                               placeholder="exemple@email.com">
                    </div>
                </div>

                <button type="submit" class="w-full bg-blue-600 text-white py-3.5 rounded-xl font-semibold hover:bg-blue-800 transition shadow-lg shadow-primary/30">
                    <i class="fas fa-paper-plane mr-2"></i>Envoyer le lien
                </button>
            </form>

            <div class="text-center mt-6">
                <a href="<?= BASE_URL ?>/login.php" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                    <i class="fas fa-arrow-left mr-1"></i> Retour à la connexion
                </a>
            </div>
        </div>
    </div>
</body>
</html>