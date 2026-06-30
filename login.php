<?php
// ============================================
// 1. CHARGEMENT (AVANT TOUT)
// ============================================
require_once __DIR__ . '/config/bootstrap.php';

// ============================================
// 2. REDIRECTIONS (AVANT TOUT HTML)
// ============================================
// Si déjà connecté → rediriger immédiatement
if (isLoggedIn()) {
    header('Location: ' . redirectByRole());
    exit; // ← TOUJOURS exit après header()
}

// ============================================
// 3. VARIABLES
// ============================================
$errors = [];
$email = '';
$remember = false;

// Générer le token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer l'email depuis le cookie "Se souvenir de moi"
if (isset($_COOKIE['remember_email']) && empty($email)) {
    $email = $_COOKIE['remember_email'];
    $remember = true;
}

// ============================================
// 4. TRAITEMENT DU FORMULAIRE (AVANT HTML)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Token de sécurité invalide. Veuillez réessayer.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        // Rate limiting
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_attempts_time'] = time();
        }
        
        if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['login_attempts_time']) < 900) {
            $errors[] = "Trop de tentatives. Veuillez réessayer dans 15 minutes.";
        } else {
            // Validation
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Adresse email invalide.";
            }
            if (empty($password)) {
                $errors[] = "Le mot de passe est requis.";
            }
            
            // Tentative de connexion
            if (count($errors) === 0) {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();
                    
                    if ($user && password_verify($password, $user['password'])) {
                        // ✅ Connexion réussie
                        $_SESSION['login_attempts'] = 0;
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['user_first_name'] = $user['first_name'];
                        $_SESSION['user_last_name'] = $user['last_name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                        
                        // Mettre à jour la dernière connexion
                        $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                        
                        // Cookie "Se souvenir de moi"
                        if ($remember) {
                            $token = bin2hex(random_bytes(32));
                            $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?")->execute([$token, $user['id']]);
                            setcookie('remember_token', $token, time() + (30 * 86400), '/', '', false, true);
                            setcookie('remember_email', $email, time() + (30 * 86400), '/', '', false, true);
                        }
                        
                        // 🔴 REDIRECTION (AVANT TOUT HTML)
                        $redirect = $_SESSION['redirect_after_login'] ?? redirectByRole();
                        unset($_SESSION['redirect_after_login']);
                        header('Location: ' . $redirect);
                        exit; // ← OBLIGATOIRE
                        
                    } else {
                        $_SESSION['login_attempts']++;
                        $errors[] = "Email ou mot de passe incorrect.";
                    }
                } catch (Exception $e) {
                    $errors[] = "Erreur de connexion : " . $e->getMessage();
                }
            }
        }
    }
}

// ============================================
// 5. MAINTENANT ON PEUT AFFICHER LE HTML
// ============================================
$pageTitle = 'Connexion - PekDev Market';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ============================================
     HTML DU FORMULAIRE DE CONNEXION
     ============================================ -->
<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-blue-50 to-orange-50 dark:from-gray-900 dark:to-gray-800">
    <div class="max-w-md w-full">
        
        <!-- Card -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-8 border border-gray-100 dark:border-gray-700">
            
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-gradient-to-br from-blue-600 to-orange-500 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <i class="fas fa-store text-white text-3xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Bon retour ! 👋</h2>
                <p class="text-gray-500 dark:text-gray-400 mt-2">Connectez-vous à votre compte PekDev Market</p>
            </div>

            <!-- Erreurs -->
            <?php if (count($errors) > 0): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 flex items-start gap-3">
                    <i class="fas fa-exclamation-circle mt-0.5"></i>
                    <div class="flex-1">
                        <?php foreach ($errors as $error): ?>
                            <p class="text-sm"><?= clean($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Messages de succès -->
            <?php if (isset($_GET['registered'])): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-3">
                    <i class="fas fa-check-circle"></i>
                    <p class="text-sm">Inscription réussie ! Vous pouvez maintenant vous connecter.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['logout'])): ?>
                <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-3">
                    <i class="fas fa-info-circle"></i>
                    <p class="text-sm">Vous avez été déconnecté avec succès.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['password_reset'])): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-3">
                    <i class="fas fa-key"></i>
                    <p class="text-sm">Mot de passe réinitialisé. Connectez-vous avec votre nouveau mot de passe.</p>
                </div>
            <?php endif; ?>

            <!-- Formulaire -->
            <form method="POST" action="" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <!-- Email -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                        Adresse email
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" name="email" value="<?= clean($email) ?>" required autofocus
                               class="w-full pl-11 pr-4 py-3 border dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white transition"
                               placeholder="exemple@email.com">
                    </div>
                </div>

                <!-- Mot de passe -->
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Mot de passe
                        </label>
                        <a href="<?= BASE_URL ?>/forgot-password.php" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                            Oublié ?
                        </a>
                    </div>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" name="password" id="password" required
                               class="w-full pl-11 pr-12 py-3 border dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white transition"
                               placeholder="••••••••">
                        <button type="button" onclick="togglePassword()" 
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Se souvenir de moi -->
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" <?= $remember ? 'checked' : '' ?>
                               class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                        <span class="text-sm text-gray-600 dark:text-gray-300">Se souvenir de moi</span>
                    </label>
                </div>

                <!-- Bouton de connexion -->
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white py-3.5 rounded-xl font-semibold hover:from-blue-700 hover:to-blue-800 transition shadow-lg flex items-center justify-center gap-2">
                    <i class="fas fa-sign-in-alt"></i>
                    Se connecter
                </button>
            </form>

            <!-- Séparateur -->
            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300 dark:border-gray-600"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-4 bg-white dark:bg-gray-800 text-gray-500">ou</span>
                </div>
            </div>

            <!-- Lien inscription -->
            <a href="<?= BASE_URL ?>/register.php" 
               class="block w-full bg-gradient-to-r from-orange-500 to-orange-600 text-white py-3.5 rounded-xl font-semibold hover:from-orange-600 hover:to-orange-700 transition shadow-lg text-center">
                <i class="fas fa-user-plus mr-2"></i>Créer un compte
            </a>
        </div>

        <!-- Retour accueil -->
        <div class="text-center mt-6">
            <a href="<?= BASE_URL ?>" class="text-sm text-gray-500 dark:text-gray-400 hover:text-blue-600 transition">
                <i class="fas fa-arrow-left mr-1"></i> Retour à l'accueil
            </a>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    const icon = document.getElementById('toggleIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>