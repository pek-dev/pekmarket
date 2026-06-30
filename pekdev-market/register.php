<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) redirect(BASE_URL);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = clean($_POST['first_name'] ?? '');
    $lastName = clean($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    $role = in_array($_POST['role'] ?? '', ['customer', 'seller']) ? $_POST['role'] : 'customer';
    
    if (empty($firstName)) $errors[] = 'Prénom requis';
    if (empty($lastName)) $errors[] = 'Nom requis';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';
    if (strlen($password) < 6) $errors[] = 'Mot de passe trop court';
    if ($password !== $confirm) $errors[] = 'Mots de passe différents';
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) $errors[] = 'Email déjà utilisé';
    
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$firstName, $lastName, $email, $hash, $role]);
        
        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['user_name'] = $firstName . ' ' . $lastName;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = $role;
        
        redirect(BASE_URL);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af', secondary: '#f97316' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-orange-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-user-plus text-white text-2xl"></i>
            </div>
            <h2 class="text-3xl font-bold text-gray-800 dark:text-white">Inscription</h2>
        </div>
        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 dark:bg-red-900/20 text-red-700 px-4 py-3 rounded-lg mb-4">
                <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <input type="text" name="first_name" required placeholder="Prénom" value="<?= clean($_POST['first_name'] ?? '') ?>" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg focus:border-blue-600 focus:outline-none">
                <input type="text" name="last_name" required placeholder="Nom" value="<?= clean($_POST['last_name'] ?? '') ?>" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg focus:border-blue-600 focus:outline-none">
            </div>
            <input type="email" name="email" required placeholder="Email" value="<?= clean($_POST['email'] ?? '') ?>" class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg focus:border-blue-600 focus:outline-none">
            <div class="grid grid-cols-2 gap-3">
                <input type="password" name="password" required placeholder="Mot de passe" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg focus:border-blue-600 focus:outline-none">
                <input type="password" name="password_confirm" required placeholder="Confirmer" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg focus:border-blue-600 focus:outline-none">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <label class="cursor-pointer">
                    <input type="radio" name="role" value="customer" checked class="hidden peer">
                    <div class="p-3 border-2 border-gray-200 dark:border-gray-700 rounded-lg peer-checked:border-blue-600 text-center">
                        <i class="fas fa-shopping-bag text-blue-600 text-xl mb-1"></i>
                        <p class="text-sm font-semibold">Acheteur</p>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="role" value="seller" class="hidden peer">
                    <div class="p-3 border-2 border-gray-200 dark:border-gray-700 rounded-lg peer-checked:border-orange-500 text-center">
                        <i class="fas fa-store text-orange-500 text-xl mb-1"></i>
                        <p class="text-sm font-semibold">Vendeur</p>
                    </div>
                </label>
            </div>
            <button type="submit" class="w-full py-3 bg-orange-500 text-white rounded-lg font-semibold hover:bg-orange-600">Créer mon compte</button>
        </form>
        <p class="text-center mt-4 text-gray-600 dark:text-gray-300">
            Déjà un compte ? <a href="<?= BASE_URL ?>/login.php" class="text-blue-600 font-semibold">Se connecter</a>
        </p>
    </div>
</body>
</html>