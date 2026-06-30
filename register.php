<?php
require_once __DIR__ . '/config/bootstrap.php';

require_once __DIR__ . '/includes/header.php';



// Si déjà connecté, rediriger
if (isLoggedIn()) {
    header('Location: ' . redirectByRole());
    exit;
}

$errors = [];

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer les provinces
$provincesStmt = $pdo->query("SELECT name FROM provinces ORDER BY name ASC");
$provinces = $provincesStmt->fetchAll();

// Traitement de l'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Token de sécurité invalide.";
    } else {
        $firstName = clean(trim($_POST['first_name'] ?? ''));
        $lastName = clean(trim($_POST['last_name'] ?? ''));
        $email = clean(trim($_POST['email'] ?? ''));
        $phone = clean(trim($_POST['phone'] ?? ''));
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $role = $_POST['role'] ?? 'customer';
        $province = clean(trim($_POST['province'] ?? ''));
        $city = clean(trim($_POST['city'] ?? ''));
        $acceptTerms = isset($_POST['accept_terms']);
        
        // Validation
        if (empty($firstName) || strlen($firstName) < 2) {
            $errors[] = "Le prénom doit contenir au moins 2 caractères.";
        }
        if (empty($lastName) || strlen($lastName) < 2) {
            $errors[] = "Le nom doit contenir au moins 2 caractères.";
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Adresse email invalide.";
        }
        if (empty($phone) || strlen(preg_replace('/[^0-9]/', '', $phone)) < 8) {
            $errors[] = "Numéro de téléphone invalide (min 8 chiffres).";
        }
        if (strlen($password) < 6) {
            $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
        }
        if ($password !== $passwordConfirm) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        }
        if (!in_array($role, ['customer', 'seller'])) {
            $errors[] = "Rôle invalide.";
        }
        if (empty($province)) {
            $errors[] = "Veuillez sélectionner une province.";
        }
        if (empty($city)) {
            $errors[] = "La ville est requise.";
        }
        if (!$acceptTerms) {
            $errors[] = "Vous devez accepter les conditions d'utilisation.";
        }
        
        // Vérifier si l'email existe déjà
        if (count($errors) === 0) {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $checkStmt->execute([$email]);
            if ($checkStmt->fetch()) {
                $errors[] = "Cette adresse email est déjà utilisée.";
            }
        }
        
        // Vérifier si le téléphone existe déjà
        if (count($errors) === 0 && !empty($phone)) {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $checkStmt->execute([$phone]);
            if ($checkStmt->fetch()) {
                $errors[] = "Ce numéro de téléphone est déjà utilisé.";
            }
        }
        
        // Inscription
        if (count($errors) === 0) {
            try {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO users 
                    (first_name, last_name, email, phone, password, role, province, city, is_verified, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 1)
                ");
                $stmt->execute([
                    $firstName, $lastName, $email, $phone, 
                    $hashedPassword, $role, $province, $city
                ]);
                
                $userId = $pdo->lastInsertId();
                
                // Connexion automatique après inscription
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_role'] = $role;
                $_SESSION['user_first_name'] = $firstName;
                $_SESSION['user_last_name'] = $lastName;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                
                // Redirection selon le rôle
                header('Location: ' . redirectByRole());
                exit;
                
            } catch (Exception $e) {
                $errors[] = "Erreur lors de l'inscription : " . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Inscription - PekDev Market';
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
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.6s ease-out; }
        .role-card { transition: all 0.3s ease; }
        .role-card:hover { transform: translateY(-4px); }
        .role-card.selected {
            border-color: #1e40af;
            background-color: #eff6ff;
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }
        .password-strength { height: 4px; border-radius: 2px; transition: all 0.3s; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen flex">
        
        <!-- Left Side: Branding -->
        <div class="hidden lg:flex lg:w-2/5 bg-gradient-to-br from-orange-500 via-orange-500 to-secondaryDark relative overflow-hidden">
            <div class="absolute top-20 right-20 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
            <div class="absolute bottom-20 left-20 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl"></div>
            
            <div class="relative z-10 flex flex-col justify-between p-12 text-white w-full">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center">
                        <i class="fas fa-store text-orange-500 text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold">PekDev Market</h1>
                        <p class="text-sm text-orange-100">La marketplace du Burundi</p>
                    </div>
                </div>
                
                <div class="space-y-8">
                    <div>
                        <h2 class="text-4xl font-bold mb-4 leading-tight">
                            Rejoignez la plus<br>
                            <span class="text-yellow-300">grande marketplace</span><br>
                            du Burundi
                        </h2>
                        <p class="text-orange-100 text-lg max-w-md">
                            Créez votre compte en 2 minutes et commencez à acheter ou vendre dès aujourd'hui.
                        </p>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-check"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold">Inscription gratuite</h3>
                                <p class="text-sm text-orange-100">Aucun frais caché</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-check"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold">Accès instantané</h3>
                                <p class="text-sm text-orange-100">Commencez immédiatement</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-check"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold">Support dédié</h3>
                                <p class="text-sm text-orange-100">Assistance en kirundi & français</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4 text-center">
                        <p class="text-2xl font-bold">10K+</p>
                        <p class="text-xs text-orange-100">Utilisateurs</p>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4 text-center">
                        <p class="text-2xl font-bold">5K+</p>
                        <p class="text-xs text-orange-100">Produits</p>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4 text-center">
                        <p class="text-2xl font-bold">18</p>
                        <p class="text-xs text-orange-100">Provinces</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Register Form -->
        <div class="w-full lg:w-3/5 flex items-center justify-center p-6 md:p-12 overflow-y-auto">
            <div class="w-full max-w-2xl animate-fade-in">
                
                <!-- Mobile Logo -->
                <div class="lg:hidden flex items-center gap-3 mb-6 justify-center">
                    <div class="w-12 h-12 bg-orange-500 rounded-xl flex items-center justify-center">
                        <i class="fas fa-store text-white text-2xl"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">PekDev Market</h1>
                </div>
                
                <!-- Header -->
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-gray-800 mb-2">Créer un compte 🚀</h2>
                    <p class="text-gray-500">Rejoignez des milliers d'utilisateurs au Burundi</p>
                </div>

                <!-- Errors -->
                <?php if (count($errors) > 0): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-exclamation-circle mt-1"></i>
                            <div class="flex-1">
                                <p class="font-semibold mb-1">Veuillez corriger les erreurs suivantes :</p>
                                <ul class="list-disc list-inside text-sm space-y-1">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= clean($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <button onclick="this.parentElement.parentElement.remove()" class="text-red-500 hover:text-red-700">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Register Form -->
                <form method="POST" action="" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <!-- Role Selection -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Je souhaite m'inscrire en tant que *</label>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="role-card cursor-pointer border-2 border-gray-200 rounded-xl p-4 <?= ($_POST['role'] ?? 'customer') == 'customer' ? 'selected' : '' ?>">
                                <input type="radio" name="role" value="customer" <?= ($_POST['role'] ?? 'customer') == 'customer' ? 'checked' : '' ?> class="sr-only" onchange="updateRoleCard(this)">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-shopping-bag text-blue-600 text-xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-gray-800">Client</h3>
                                        <p class="text-xs text-gray-500">Acheter des produits</p>
                                    </div>
                                </div>
                            </label>
                            
                            <label class="role-card cursor-pointer border-2 border-gray-200 rounded-xl p-4 <?= ($_POST['role'] ?? '') == 'seller' ? 'selected' : '' ?>">
                                <input type="radio" name="role" value="seller" <?= ($_POST['role'] ?? '') == 'seller' ? 'checked' : '' ?> class="sr-only" onchange="updateRoleCard(this)">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-store text-orange-500 text-xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-gray-800">Vendeur</h3>
                                        <p class="text-xs text-gray-500">Vendre mes produits</p>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Name fields -->
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Prénom *</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"><i class="fas fa-user"></i></span>
                                <input type="text" name="first_name" value="<?= clean($_POST['first_name'] ?? '') ?>" required
                                       class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-600 transition"
                                       placeholder="Jean">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Nom *</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"><i class="fas fa-user"></i></span>
                                <input type="text" name="last_name" value="<?= clean($_POST['last_name'] ?? '') ?>" required
                                       class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-600 transition"
                                       placeholder="Mugabo">
                            </div>
                        </div>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Adresse email *</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" value="<?= clean($_POST['email'] ?? '') ?>" required
                                   class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-600 transition"
                                   placeholder="exemple@email.com">
                        </div>
                    </div>

                    <!-- Phone -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Téléphone *</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"><i class="fas fa-phone"></i></span>
                            <input type="tel" name="phone" value="<?= clean($_POST['phone'] ?? '') ?>" required
                                   class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-600 transition"
                                   placeholder="+257 79 000 000">
                        </div>
                        <p class="text-xs text-gray-500 mt-1"><i class="fas fa-info-circle mr-1"></i>Format: +257 XX XXX XXX</p>
                    </div>

                    <!-- Location -->
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Province *</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"><i class="fas fa-map-marker-alt"></i></span>
                                <select name="province" required class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-600 transition appearance-none bg-white">
                                    <option value="">-- Sélectionner --</option>
                                    <?php foreach ($provinces as $prov): ?>
                                        <option value="<?= clean($prov['name']) ?>" <?= ($_POST['province'] ?? '') == $prov['name'] ? 'selected' : '' ?>>
                                            <?= clean($prov['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"><i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Ville / Commune *</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"><i class="fas fa-city"></i></span>
                                <input type="text" name="city" value="<?= clean($_POST['city'] ?? '') ?>" required
                                       class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-600 transition"
                                       placeholder="Bujumbura">
                            </div>
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Mot de passe *</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" id="password" required minlength="6"
                                   class="w-full pl-11 pr-12 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-600 transition"
                                   placeholder="Minimum 6 caractères"
                                   oninput="checkPasswordStrength(this.value)">
                            <button type="button" onclick="togglePassword('password', 'toggleIcon1')" 
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </button>
                        </div>
                        <div class="mt-2">
                            <div class="flex gap-1">
                                <div class="password-strength flex-1 bg-gray-200" id="strength1"></div>
                                <div class="password-strength flex-1 bg-gray-200" id="strength2"></div>
                                <div class="password-strength flex-1 bg-gray-200" id="strength3"></div>
                                <div class="password-strength flex-1 bg-gray-200" id="strength4"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1" id="strengthText">Force du mot de passe</p>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Confirmer le mot de passe *</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password_confirm" id="password_confirm" required
                                   class="w-full pl-11 pr-12 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-600 transition"
                                   placeholder="Retapez le mot de passe">
                            <button type="button" onclick="togglePassword('password_confirm', 'toggleIcon2')" 
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Terms -->
                    <div>
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" name="accept_terms" required class="w-5 h-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500 mt-0.5">
                            <span class="text-sm text-gray-600">
                                J'accepte les <a href="terms.php" class="text-blue-600 hover:underline font-medium">conditions d'utilisation</a> 
                                et la <a href="privacy.php" class="text-blue-600 hover:underline font-medium">politique de confidentialité</a> de PekDev Market.
                            </span>
                        </label>
                    </div>

                    <!-- Submit -->
                    <button type="submit" 
                            class="w-full bg-orange-500 text-white py-3.5 rounded-xl font-semibold hover:bg-orange-600 transition shadow-lg shadow-secondary/30 flex items-center justify-center gap-2">
                        <i class="fas fa-user-plus"></i>
                        Créer mon compte
                    </button>
                </form>

                <!-- Divider -->
                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-4 bg-gray-50 text-gray-500">Déjà un compte ?</span>
                    </div>
                </div>

                <!-- Login link -->
                <a href="login.php" 
                   class="block w-full bg-blue-600 text-white py-3.5 rounded-xl font-semibold hover:bg-blue-800 transition shadow-lg shadow-primary/30 text-center">
                    <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                </a>

                <!-- Back to home -->
                <div class="text-center mt-6">
                    <a href="index.php" class="text-sm text-gray-500 hover:text-blue-600 transition">
                        <i class="fas fa-arrow-left mr-1"></i> Retour à l'accueil
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateRoleCard(radio) {
            document.querySelectorAll('.role-card').forEach(card => card.classList.remove('selected'));
            radio.closest('.role-card').classList.add('selected');
        }

        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
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

        function checkPasswordStrength(password) {
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[A-Z]/.test(password) && /[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password)) strength++;

            const colors = ['bg-gray-200', 'bg-red-500', 'bg-yellow-500', 'bg-blue-500', 'bg-green-500'];
            const texts = ['Force du mot de passe', 'Très faible', 'Faible', 'Moyen', 'Fort', 'Très fort'];

            for (let i = 1; i <= 4; i++) {
                const el = document.getElementById('strength' + i);
                el.className = 'password-strength flex-1 ' + (i <= strength ? colors[strength] : 'bg-gray-200');
            }
            document.getElementById('strengthText').textContent = texts[strength];
            document.getElementById('strengthText').className = 'text-xs mt-1 ' + 
                (strength === 0 ? 'text-gray-500' : strength <= 2 ? 'text-red-500' : strength === 3 ? 'text-blue-500' : 'text-green-500');
        }
    </script>
</body>
</html>