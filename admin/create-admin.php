<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'admin') { 
    header('Location: ' . BASE_URL); 
    exit; 
}

$errors = [];
$success = false;
$createdAdmin = null;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = clean(trim($_POST['first_name'] ?? ''));
    $lastName = clean(trim($_POST['last_name'] ?? ''));
    $email = clean(trim($_POST['email'] ?? ''));
    $phone = clean(trim($_POST['phone'] ?? ''));
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $province = clean(trim($_POST['province'] ?? ''));
    $city = clean(trim($_POST['city'] ?? ''));
    $sendWelcomeEmail = isset($_POST['send_welcome']);
    
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
    if (strlen($password) < 8) {
        $errors[] = "Le mot de passe doit contenir au moins 8 caractères.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins une lettre majuscule.";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins un chiffre.";
    }
    if ($password !== $passwordConfirm) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }
    if (empty($province)) {
        $errors[] = "Veuillez sélectionner une province.";
    }
    if (empty($city)) {
        $errors[] = "La ville est requise.";
    }
    
    // Vérifier si l'email existe déjà
    if (count($errors) === 0) {
        try {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $checkStmt->execute([$email]);
            if ($checkStmt->fetch()) {
                $errors[] = "Cette adresse email est déjà utilisée.";
            }
        } catch (Exception $e) {
            $errors[] = "Erreur lors de la vérification de l'email.";
        }
    }
    
    // Vérifier si le téléphone existe déjà
    if (count($errors) === 0 && !empty($phone)) {
        try {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $checkStmt->execute([$phone]);
            if ($checkStmt->fetch()) {
                $errors[] = "Ce numéro de téléphone est déjà utilisé.";
            }
        } catch (Exception $e) {
            $errors[] = "Erreur lors de la vérification du téléphone.";
        }
    }
    
    // Création de l'administrateur
    if (count($errors) === 0) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users 
                (first_name, last_name, email, phone, password, role, province, city, is_verified, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, 'admin', ?, ?, 1, 1, NOW())
            ");
            $stmt->execute([
                $firstName, $lastName, $email, $phone, 
                $hashedPassword, $province, $city
            ]);
            
            $newAdminId = $pdo->lastInsertId();
            $success = true;
            $createdAdmin = [
                'id' => $newAdminId,
                'name' => $firstName . ' ' . $lastName,
                'email' => $email,
                'password' => $password // Pour affichage temporaire
            ];
            
            // Optionnel : Envoyer un email de bienvenue
            if ($sendWelcomeEmail) {
                // Ici vous pouvez ajouter l'envoi d'email
                // mail($email, "Bienvenue sur PekDev Market", "Votre compte admin a été créé...");
            }
            
        } catch (Exception $e) {
            $errors[] = "Erreur lors de la création : " . $e->getMessage();
        }
    }
}

// Récupérer les provinces
$provinces = [];
try {
    $provinces = $pdo->query("SELECT name FROM provinces ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {}

$pageTitle = 'Créer un Administrateur';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <nav class="text-sm text-gray-500 mb-2">
            <a href="<?= BASE_URL ?>/dashboard/admin.php" class="hover:text-blue-600">Admin</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <a href="<?= BASE_URL ?>/admin/users.php" class="hover:text-blue-600">Utilisateurs</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-gray-800 dark:text-white font-medium">Créer Admin</span>
        </nav>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
            <i class="fas fa-user-shield text-red-600"></i> Créer un Administrateur
        </h1>
        <p class="text-gray-500 mt-2">Ajoutez un nouvel administrateur avec accès complet au système</p>
    </div>

    <!-- Message de succès -->
    <?php if ($success): ?>
        <div class="bg-green-50 border-2 border-green-200 rounded-xl p-6 mb-6">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-check text-green-600 text-2xl"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-green-800 mb-2">Administrateur créé avec succès !</h3>
                    <div class="bg-white rounded-lg p-4 space-y-2">
                        <p class="text-sm text-gray-700"><strong>Nom :</strong> <?= clean($createdAdmin['name']) ?></p>
                        <p class="text-sm text-gray-700"><strong>Email :</strong> <?= clean($createdAdmin['email']) ?></p>
                        <p class="text-sm text-gray-700"><strong>Mot de passe temporaire :</strong> 
                            <code class="bg-gray-100 px-2 py-1 rounded font-mono text-red-600"><?= clean($createdAdmin['password']) ?></code>
                        </p>
                    </div>
                    <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                        <p class="text-sm text-yellow-800">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>Important :</strong> Copiez ce mot de passe et communiquez-le à l'administrateur. 
                            Il devra le changer lors de sa première connexion.
                        </p>
                    </div>
                    <div class="mt-4 flex gap-3">
                        <a href="<?= BASE_URL ?>/admin/create-admin.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold text-sm">
                            <i class="fas fa-plus mr-2"></i>Créer un autre admin
                        </a>
                        <a href="<?= BASE_URL ?>/admin/users.php" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white rounded-lg hover:bg-gray-300 font-semibold text-sm">
                            <i class="fas fa-list mr-2"></i>Voir la liste
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Erreurs -->
    <?php if (count($errors) > 0): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6">
            <div class="flex items-start gap-3">
                <i class="fas fa-exclamation-circle mt-1"></i>
                <div class="flex-1">
                    <p class="font-semibold mb-2">Veuillez corriger les erreurs suivantes :</p>
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

    <!-- Formulaire -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-4 text-white">
            <h2 class="text-xl font-bold flex items-center gap-2">
                <i class="fas fa-user-plus"></i> Informations de l'administrateur
            </h2>
            <p class="text-red-100 text-sm mt-1">Tous les champs marqués * sont obligatoires</p>
        </div>
        
        <form method="POST" action="" class="p-6 md:p-8 space-y-6">
            
            <!-- Nom et Prénom -->
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                        Prénom *
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" name="first_name" 
                               value="<?= clean($_POST['first_name'] ?? '') ?>" required
                               class="w-full pl-11 pr-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent dark:bg-gray-700 dark:text-white transition"
                               placeholder="Jean">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                        Nom *
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" name="last_name" 
                               value="<?= clean($_POST['last_name'] ?? '') ?>" required
                               class="w-full pl-11 pr-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent dark:bg-gray-700 dark:text-white transition"
                               placeholder="Dupont">
                    </div>
                </div>
            </div>

            <!-- Email et Téléphone -->
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                        Adresse email *
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" name="email" 
                               value="<?= clean($_POST['email'] ?? '') ?>" required
                               class="w-full pl-11 pr-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent dark:bg-gray-700 dark:text-white transition"
                               placeholder="admin@exemple.com">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                        Téléphone *
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="fas fa-phone"></i>
                        </span>
                        <input type="tel" name="phone" 
                               value="<?= clean($_POST['phone'] ?? '') ?>" required
                               class="w-full pl-11 pr-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent dark:bg-gray-700 dark:text-white transition"
                               placeholder="+257 79 000 000">
                    </div>
                </div>
            </div>

            <!-- Mot de passe -->
            <div class="border-t dark:border-gray-700 pt-6">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-lock text-red-600"></i> Sécurité du compte
                </h3>
                
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                            Mot de passe *
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                                <i class="fas fa-key"></i>
                            </span>
                            <input type="password" name="password" id="password" required minlength="8"
                                   class="w-full pl-11 pr-12 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent dark:bg-gray-700 dark:text-white transition"
                                   placeholder="Min. 8 caractères"
                                   oninput="checkPasswordStrength(this.value)">
                            <button type="button" onclick="togglePassword('password', 'toggleIcon1')" 
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </button>
                        </div>
                        <div class="mt-2">
                            <div class="flex gap-1">
                                <div class="h-1 flex-1 bg-gray-200 rounded-full" id="strength1"></div>
                                <div class="h-1 flex-1 bg-gray-200 rounded-full" id="strength2"></div>
                                <div class="h-1 flex-1 bg-gray-200 rounded-full" id="strength3"></div>
                                <div class="h-1 flex-1 bg-gray-200 rounded-full" id="strength4"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1" id="strengthText">Force du mot de passe</p>
                        </div>
                        <ul class="text-xs text-gray-500 mt-2 space-y-1">
                            <li id="req-length"><i class="fas fa-circle text-xs mr-1"></i>Au moins 8 caractères</li>
                            <li id="req-upper"><i class="fas fa-circle text-xs mr-1"></i>Au moins une majuscule</li>
                            <li id="req-number"><i class="fas fa-circle text-xs mr-1"></i>Au moins un chiffre</li>
                        </ul>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                            Confirmer le mot de passe *
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                                <i class="fas fa-key"></i>
                            </span>
                            <input type="password" name="password_confirm" id="password_confirm" required minlength="8"
                                   class="w-full pl-11 pr-12 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent dark:bg-gray-700 dark:text-white transition"
                                   placeholder="Retapez le mot de passe">
                            <button type="button" onclick="togglePassword('password_confirm', 'toggleIcon2')" 
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Les deux mots de passe doivent être identiques
                        </p>
                    </div>
                </div>
            </div>

            <!-- Localisation -->
            <div class="border-t dark:border-gray-700 pt-6">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-map-marker-alt text-red-600"></i> Localisation
                </h3>
                
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                            Province *
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                                <i class="fas fa-map"></i>
                            </span>
                            <select name="province" required
                                    class="w-full pl-11 pr-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent dark:bg-gray-700 dark:text-white transition appearance-none">
                                <option value="">-- Sélectionner une province --</option>
                                <?php foreach ($provinces as $prov): ?>
                                    <option value="<?= clean($prov['name']) ?>" <?= ($_POST['province'] ?? '') == $prov['name'] ? 'selected' : '' ?>>
                                        <?= clean($prov['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                            Ville / Commune *
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                                <i class="fas fa-city"></i>
                            </span>
                            <input type="text" name="city" 
                                   value="<?= clean($_POST['city'] ?? '') ?>" required
                                   class="w-full pl-11 pr-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent dark:bg-gray-700 dark:text-white transition"
                                   placeholder="Bujumbura">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Options supplémentaires -->
            <div class="border-t dark:border-gray-700 pt-6">
                <div class="flex items-start gap-3">
                    <input type="checkbox" name="send_welcome" id="send_welcome" 
                           class="w-5 h-5 text-red-600 rounded border-gray-300 focus:ring-red-500 mt-0.5">
                    <label for="send_welcome" class="text-sm text-gray-700 dark:text-gray-200 cursor-pointer">
                        <strong>Envoyer un email de bienvenue</strong> à l'administrateur avec ses identifiants de connexion
                    </label>
                </div>
            </div>

            <!-- Boutons d'action -->
            <div class="flex flex-col sm:flex-row gap-3 pt-6 border-t dark:border-gray-700">
                <button type="submit" 
                        class="flex-1 bg-red-600 text-white py-3.5 rounded-lg font-semibold hover:bg-red-700 transition shadow-lg flex items-center justify-center gap-2">
                    <i class="fas fa-user-shield"></i>
                    Créer l'administrateur
                </button>
                <a href="<?= BASE_URL ?>/admin/users.php" 
                   class="px-6 py-3.5 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white rounded-lg font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition text-center">
                    <i class="fas fa-times mr-2"></i>Annuler
                </a>
            </div>
        </form>
    </div>

    <!-- Informations de sécurité -->
    <div class="mt-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-6">
        <h3 class="font-bold text-blue-800 dark:text-blue-200 mb-3 flex items-center gap-2">
            <i class="fas fa-shield-alt"></i> Informations de sécurité
        </h3>
        <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-2">
            <li><i class="fas fa-check mr-2"></i>Les administrateurs ont accès complet à toutes les fonctionnalités du système</li>
            <li><i class="fas fa-check mr-2"></i>Le mot de passe est hashé avec bcrypt avant stockage</li>
            <li><i class="fas fa-check mr-2"></i>L'email doit être unique dans le système</li>
            <li><i class="fas fa-check mr-2"></i>Le compte est créé actif et vérifié par défaut</li>
            <li><i class="fas fa-check mr-2"></i>Communiquez le mot de passe de manière sécurisée à l'administrateur</li>
        </ul>
    </div>
</div>

<script>
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
    
    // Vérifications
    const hasLength = password.length >= 8;
    const hasUpper = /[A-Z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[^A-Za-z0-9]/.test(password);
    
    if (hasLength) strength++;
    if (hasUpper) strength++;
    if (hasNumber) strength++;
    if (hasSpecial) strength++;
    
    // Mise à jour des barres
    const colors = ['bg-gray-200', 'bg-red-500', 'bg-yellow-500', 'bg-blue-500', 'bg-green-500'];
    const texts = ['Force du mot de passe', 'Très faible', 'Faible', 'Moyen', 'Fort', 'Très fort'];
    
    for (let i = 1; i <= 4; i++) {
        const el = document.getElementById('strength' + i);
        el.className = 'h-1 flex-1 rounded-full ' + (i <= strength ? colors[strength] : 'bg-gray-200');
    }
    
    document.getElementById('strengthText').textContent = texts[strength];
    document.getElementById('strengthText').className = 'text-xs mt-1 ' + 
        (strength === 0 ? 'text-gray-500' : strength <= 2 ? 'text-red-500' : strength === 3 ? 'text-blue-500' : 'text-green-500');
    
    // Mise à jour des exigences
    document.getElementById('req-length').innerHTML = '<i class="fas fa-' + (hasLength ? 'check-circle text-green-500' : 'circle text-gray-400') + ' text-xs mr-1"></i>Au moins 8 caractères';
    document.getElementById('req-upper').innerHTML = '<i class="fas fa-' + (hasUpper ? 'check-circle text-green-500' : 'circle text-gray-400') + ' text-xs mr-1"></i>Au moins une majuscule';
    document.getElementById('req-number').innerHTML = '<i class="fas fa-' + (hasNumber ? 'check-circle text-green-500' : 'circle text-gray-400') + ' text-xs mr-1"></i>Au moins un chiffre';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>