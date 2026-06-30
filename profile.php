<?php
require_once __DIR__ . '/config/bootstrap.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'customer';
$message = '';
$messageType = 'success';
$errors = [];

// Récupérer les infos actuelles
$user = null;
try {
    $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch();
} catch (Exception $e) { header('Location: ' . BASE_URL . '/login.php'); exit; }

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $firstName = clean(trim($_POST['first_name'] ?? ''));
    $lastName = clean(trim($_POST['last_name'] ?? ''));
    $phone = clean(trim($_POST['phone'] ?? ''));
    $province = clean(trim($_POST['province'] ?? ''));
    $city = clean(trim($_POST['city'] ?? ''));
    $address = clean(trim($_POST['address'] ?? ''));
    
    if (empty($firstName)) $errors[] = "Le prénom est requis.";
    if (empty($lastName)) $errors[] = "Le nom est requis.";
    if (empty($phone) || strlen(preg_replace('/[^0-9]/', '', $phone)) < 8) $errors[] = "Téléphone invalide.";
    
    if (count($errors) === 0) {
        try {
            $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, province = ?, city = ?, address = ? WHERE id = ?")->execute([$firstName, $lastName, $phone, $province, $city, $address, $user_id]);
            $_SESSION['user_first_name'] = $firstName;
            $_SESSION['user_last_name'] = $lastName;
            $_SESSION['user_name'] = $firstName . ' ' . $lastName;
            $message = "Profil mis à jour avec succès !";
            $userStmt->execute([$user_id]);
            $user = $userStmt->fetch();
        } catch (Exception $e) { $errors[] = "Erreur: " . $e->getMessage(); $messageType = 'error'; }
    } else { $messageType = 'error'; }
}

// Changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (strlen($newPassword) < 6) $errors[] = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
    if ($newPassword !== $confirmPassword) $errors[] = "Les mots de passe ne correspondent pas.";
    if (!password_verify($currentPassword, $user['password'])) $errors[] = "Mot de passe actuel incorrect.";
    
    if (count($errors) === 0) {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashedPassword, $user_id]);
            $message = "Mot de passe modifié avec succès !";
        } catch (Exception $e) { $errors[] = "Erreur: " . $e->getMessage(); $messageType = 'error'; }
    } else { $messageType = 'error'; }
}

// Récupérer les provinces
$provinces = [];
try { $provinces = $pdo->query("SELECT name FROM provinces ORDER BY name ASC")->fetchAll(); } catch (Exception $e) {}

$pageTitle = 'Mon Profil';
require_once __DIR__ . '/includes/header.php';
?>

<section class="bg-gray-50 dark:bg-gray-900 py-8 border-b dark:border-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <nav class="text-sm text-gray-500 dark:text-gray-400 mb-2">
            <a href="<?= BASE_URL ?>/dashboard/<?= $userRole ?>.php" class="hover:text-blue-600 transition">Tableau de bord</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-gray-800 dark:text-white font-medium">Mon Profil</span>
        </nav>
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-white">Gérer mon profil</h1>
    </div>
</section>

<section class="py-8 md:py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-4 gap-8">
            
            <!-- Sidebar Navigation -->
            <aside class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 sticky top-4">
                    <div class="flex items-center gap-3 p-3 mb-4 border-b dark:border-gray-700">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-orange-500 rounded-full flex items-center justify-center text-white font-bold text-lg"><?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?></div>
                        <div><p class="font-bold text-gray-800 dark:text-white"><?= clean($user['first_name'] . ' ' . $user['last_name']) ?></p><p class="text-xs text-gray-500 truncate max-w-[150px]"><?= clean($user['email']) ?></p><span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full <?= $user['role'] == 'admin' ? 'bg-red-100 text-red-800' : ($user['role'] == 'seller' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800') ?>"><?= ucfirst($user['role']) ?></span></div>
                    </div>
                    <nav class="space-y-1">
                        <a href="<?= BASE_URL ?>/dashboard/<?= $userRole ?>.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"><i class="fas fa-tachometer-alt w-5"></i> Tableau de bord</a>
                        <a href="<?= BASE_URL ?>/profile.php" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-600 font-semibold transition"><i class="fas fa-user w-5"></i> Mon Profil</a>
                        <a href="<?= BASE_URL ?>/orders.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"><i class="fas fa-shopping-bag w-5"></i> Mes Commandes</a>
                        <a href="<?= BASE_URL ?>/favorites.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"><i class="fas fa-heart w-5"></i> Mes Favoris</a>
                        <?php if ($userRole === 'seller'): ?>
                            <a href="<?= BASE_URL ?>/shop.php?seller=<?= $user_id ?>" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"><i class="fas fa-store w-5"></i> Ma Boutique</a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/logout.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition mt-4 border-t dark:border-gray-700 pt-4"><i class="fas fa-sign-out-alt w-5"></i> Déconnexion</a>
                    </nav>
                </div>
            </aside>

            <!-- Main Content -->
            <div class="lg:col-span-3">
                
                <?php if ($message): ?><div class="bg-<?= $messageType == 'success' ? 'green' : 'red' ?>-50 border border-<?= $messageType == 'success' ? 'green' : 'red' ?>-200 text-<?= $messageType == 'success' ? 'green' : 'red' ?>-700 px-4 py-3 rounded-xl mb-6 flex items-center justify-between"><span><i class="fas fa-<?= $messageType == 'success' ? 'check-circle' : 'exclamation-circle' ?> mr-2"></i><?= clean($message) ?></span><button onclick="this.parentElement.remove()" class="hover:opacity-70"><i class="fas fa-times"></i></button></div><?php endif; ?>

                <?php if (count($errors) > 0): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6"><ul class="list-disc list-inside text-sm space-y-1"><?php foreach ($errors as $error): ?><li><?= clean($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

                <!-- Informations personnelles -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 md:p-8 border border-gray-100 dark:border-gray-700 mb-6">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6 flex items-center gap-2"><i class="fas fa-user-edit text-blue-600"></i> Informations personnelles</h2>
                    <form method="POST" action="" class="space-y-6">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="grid md:grid-cols-2 gap-6">
                            <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Prénom *</label><input type="text" name="first_name" value="<?= clean($_POST['first_name'] ?? $user['first_name']) ?>" required class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></div>
                            <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Nom *</label><input type="text" name="last_name" value="<?= clean($_POST['last_name'] ?? $user['last_name']) ?>" required class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></div>
                            <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Adresse email</label><input type="email" value="<?= clean($user['email']) ?>" readonly class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg bg-gray-100 dark:bg-gray-900 text-gray-500 cursor-not-allowed"><p class="text-xs text-gray-500 mt-1"><i class="fas fa-lock mr-1"></i>L'email ne peut pas être modifié.</p></div>
                            <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Téléphone *</label><input type="tel" name="phone" value="<?= clean($_POST['phone'] ?? $user['phone']) ?>" required placeholder="+257 79 000 000" class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></div>
                        </div>
                        <div class="border-t dark:border-gray-700 pt-6">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2"><i class="fas fa-map-marker-alt text-orange-500"></i> Adresse de livraison</h3>
                            <div class="grid md:grid-cols-2 gap-6">
                                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Province *</label><select name="province" required class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"><option value="">-- Sélectionner --</option><?php foreach ($provinces as $prov): ?><option value="<?= clean($prov['name']) ?>" <?= ($_POST['province'] ?? $user['province']) == $prov['name'] ? 'selected' : '' ?>><?= clean($prov['name']) ?></option><?php endforeach; ?></select></div>
                                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Ville / Commune *</label><input type="text" name="city" value="<?= clean($_POST['city'] ?? $user['city']) ?>" required class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></div>
                                <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Adresse détaillée *</label><textarea name="address" rows="3" required placeholder="Quartier, avenue, numéro..." class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"><?= clean($_POST['address'] ?? $user['address']) ?></textarea></div>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t dark:border-gray-700">
                            <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition shadow-lg flex items-center justify-center gap-2"><i class="fas fa-save"></i> Enregistrer</button>
                        </div>
                    </form>
                </div>

                <!-- Changement de mot de passe -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 md:p-8 border border-gray-100 dark:border-gray-700">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6 flex items-center gap-2"><i class="fas fa-lock text-red-500"></i> Changer le mot de passe</h2>
                    <form method="POST" action="" class="space-y-4 max-w-md">
                        <input type="hidden" name="action" value="change_password">
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Mot de passe actuel</label><input type="password" name="current_password" required class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></div>
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Nouveau mot de passe</label><input type="password" name="new_password" required minlength="6" class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></div>
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Confirmer le nouveau mot de passe</label><input type="password" name="confirm_password" required minlength="6" class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></div>
                        <button type="submit" class="bg-gray-800 dark:bg-gray-700 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-900 dark:hover:bg-gray-600 transition">Mettre à jour le mot de passe</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>