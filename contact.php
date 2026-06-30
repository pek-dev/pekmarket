<?php
require_once __DIR__ . '/config/bootstrap.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean(trim($_POST['name'] ?? ''));
    $email = clean(trim($_POST['email'] ?? ''));
    $subject = clean(trim($_POST['subject'] ?? ''));
    $messageContent = clean(trim($_POST['message'] ?? ''));
    
    if (empty($name)) $message = "❌ Le nom est requis.";
    elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $message = "❌ Email invalide.";
    elseif (empty($subject)) $message = "❌ Le sujet est requis.";
    elseif (strlen($messageContent) < 10) $message = "❌ Le message doit contenir au moins 10 caractères.";
    else {
        try {
            $pdo->prepare("INSERT INTO messages (from_user_id, to_user_id, message, is_read) VALUES (0, 1, ?, 0)")
                ->execute(["De: $name ($email)\nSujet: $subject\n\n$messageContent"]);
            $message = "✅ Message envoyé avec succès ! Nous vous répondrons rapidement.";
            $messageType = 'success';
            $name = $email = $subject = $messageContent = '';
        } catch (Exception $e) {
            $message = "❌ Erreur: " . $e->getMessage();
        }
    }
}

$pageTitle = 'Contact';
require_once __DIR__ . '/includes/header.php';
?>

<section class="bg-gradient-to-r from-blue-600 to-orange-500 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-white">
        <h1 class="text-4xl font-bold mb-2">Contactez-nous</h1>
        <p class="text-white/90">Notre équipe est là pour vous aider</p>
    </div>
</section>

<section class="py-12 bg-gray-50 dark:bg-gray-900">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-3 gap-8">
            
            <!-- Infos de contact -->
            <div class="space-y-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-map-marker-alt text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800 dark:text-white mb-1">Adresse</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-300">Bujumbura, Burundi</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-phone text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800 dark:text-white mb-1">Téléphone</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-300"><?= SITE_PHONE ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-envelope text-orange-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800 dark:text-white mb-1">Email</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-300"><?= SITE_EMAIL ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-clock text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800 dark:text-white mb-1">Horaires</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-300">Lundi - Samedi : 8h - 20h</p>
                            <p class="text-sm text-gray-600 dark:text-gray-300">Dimanche : 9h - 18h</p>
                        </div>
                    </div>
                </div>

                <!-- Réseaux sociaux -->
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                    <h3 class="font-bold text-gray-800 dark:text-white mb-3">Suivez-nous</h3>
                    <div class="flex gap-3">
                        <a href="#" class="w-10 h-10 bg-blue-600 text-white rounded-lg flex items-center justify-center hover:bg-blue-700 transition"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="w-10 h-10 bg-pink-600 text-white rounded-lg flex items-center justify-center hover:bg-pink-700 transition"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="w-10 h-10 bg-blue-400 text-white rounded-lg flex items-center justify-center hover:bg-blue-500 transition"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="w-10 h-10 bg-green-600 text-white rounded-lg flex items-center justify-center hover:bg-green-700 transition"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
            </div>

            <!-- Formulaire -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 md:p-8 border border-gray-100 dark:border-gray-700">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6">Envoyez-nous un message</h2>
                    
                    <?php if ($message): ?>
                        <div class="bg-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-50 border border-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-200 text-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-700 px-4 py-3 rounded-xl mb-6"><?= $message ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" class="space-y-4">
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Nom complet *</label>
                                <input type="text" name="name" value="<?= clean($name ?? '') ?>" required class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Email *</label>
                                <input type="email" name="email" value="<?= clean($email ?? '') ?>" required class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Sujet *</label>
                            <select name="subject" required class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">-- Sélectionner --</option>
                                <option value="Question générale">Question générale</option>
                                <option value="Problème de commande">Problème de commande</option>
                                <option value="Support vendeur">Support vendeur</option>
                                <option value="Partenariat">Partenariat</option>
                                <option value="Signalement">Signalement</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Message *</label>
                            <textarea name="message" rows="6" required minlength="10" class="w-full px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white" placeholder="Décrivez votre demande en détail..."><?= clean($messageContent ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white py-3.5 rounded-lg font-semibold hover:from-blue-700 hover:to-blue-800 transition shadow-lg">
                            <i class="fas fa-paper-plane mr-2"></i>Envoyer le message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>