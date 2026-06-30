<?php
require_once __DIR__ . '/config/bootstrap.php';

require_once __DIR__ . '/includes/header.php';




$pageTitle = 'À propos';

?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="text-center mb-12">
        <h1 class="text-3xl md:text-5xl font-bold text-gray-800 dark:text-white mb-4">
            À propos de <span class="text-blue-600">PekDev</span> <span class="text-orange-500">Market</span>
        </h1>
        <p class="text-lg text-gray-600 dark:text-gray-300">La plus grande marketplace du Burundi</p>
    </div>

    <div class="grid md:grid-cols-2 gap-8 mb-12">
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 shadow-sm">
            <div class="w-14 h-14 bg-blue-100 dark:bg-blue-900 rounded-xl flex items-center justify-center mb-4">
                <i class="fas fa-bullseye text-blue-600 text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-3">Notre Mission</h2>
            <p class="text-gray-600 dark:text-gray-300">
                Connecter les acheteurs et vendeurs du Burundi dans un environnement sécurisé, 
                moderne et accessible à tous. Nous croyons au pouvoir du commerce local pour 
                transformer notre économie.
            </p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 shadow-sm">
            <div class="w-14 h-14 bg-orange-100 dark:bg-orange-900 rounded-xl flex items-center justify-center mb-4">
                <i class="fas fa-eye text-orange-500 text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-3">Notre Vision</h2>
            <p class="text-gray-600 dark:text-gray-300">
                Devenir la référence du e-commerce en Afrique de l'Est, en offrant une plateforme 
                innovante qui répond aux besoins spécifiques du marché burundais et de la région.
            </p>
        </div>
    </div>

    <div class="bg-gradient-to-r from-blue-600 to-orange-600 rounded-2xl p-8 md:p-12 text-white text-center">
        <h2 class="text-2xl md:text-3xl font-bold mb-4">Nos Valeurs</h2>
        <div class="grid md:grid-cols-3 gap-6 mt-8">
            <div>
                <i class="fas fa-shield-alt text-4xl mb-3"></i>
                <h3 class="font-bold text-xl mb-2">Sécurité</h3>
                <p class="text-white/90 text-sm">Transactions protégées et vendeurs vérifiés</p>
            </div>
            <div>
                <i class="fas fa-handshake text-4xl mb-3"></i>
                <h3 class="font-bold text-xl mb-2">Confiance</h3>
                <p class="text-white/90 text-sm">Relations transparentes entre acheteurs et vendeurs</p>
            </div>
            <div>
                <i class="fas fa-rocket text-4xl mb-3"></i>
                <h3 class="font-bold text-xl mb-2">Innovation</h3>
                <p class="text-white/90 text-sm">Technologies modernes au service du commerce</p>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php  ?>