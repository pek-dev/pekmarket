<?php
require_once __DIR__ . '/config/bootstrap.php';
$pageTitle = 'Politique de confidentialité';
require_once __DIR__ . '/includes/header.php';
?>

<section class="bg-gradient-to-r from-blue-600 to-blue-800 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-white">
        <h1 class="text-4xl font-bold mb-2">Politique de confidentialité</h1>
        <p class="text-white/80">Dernière mise à jour : <?= date('d/m/Y') ?></p>
    </div>
</section>

<section class="py-12 bg-white dark:bg-gray-900">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 prose dark:prose-invert max-w-none">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">1. Données collectées</h2>
        <p class="text-gray-600 dark:text-gray-300">Nous collectons les données suivantes :</p>
        <ul class="text-gray-600 dark:text-gray-300">
            <li>Nom, prénom, email, téléphone</li>
            <li>Adresse de livraison</li>
            <li>Historique des commandes</li>
            <li>Données de navigation (cookies)</li>
        </ul>

        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">2. Utilisation des données</h2>
        <p class="text-gray-600 dark:text-gray-300">Vos données sont utilisées pour :</p>
        <ul class="text-gray-600 dark:text-gray-300">
            <li>Traiter vos commandes</li>
            <li>Vous envoyer des notifications</li>
            <li>Améliorer nos services</li>
            <li>Vous proposer des offres personnalisées</li>
        </ul>

        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">3. Protection des données</h2>
        <p class="text-gray-600 dark:text-gray-300">Nous mettons en place des mesures de sécurité pour protéger vos données : chiffrement, accès restreint, sauvegardes régulières.</p>

        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">4. Partage des données</h2>
        <p class="text-gray-600 dark:text-gray-300">Nous ne vendons jamais vos données. Elles peuvent être partagées avec :</p>
        <ul class="text-gray-600 dark:text-gray-300">
            <li>Les vendeurs (pour traiter vos commandes)</li>
            <li>Les services de livraison</li>
            <li>Les autorités si requis par la loi</li>
        </ul>

        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">5. Vos droits</h2>
        <p class="text-gray-600 dark:text-gray-300">Vous avez le droit de :</p>
        <ul class="text-gray-600 dark:text-gray-300">
            <li>Accéder à vos données</li>
            <li>Les corriger</li>
            <li>Les supprimer</li>
            <li>Vous opposer au traitement</li>
        </ul>

        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">6. Cookies</h2>
        <p class="text-gray-600 dark:text-gray-300">Nous utilisons des cookies pour améliorer votre expérience. Vous pouvez les désactiver dans les paramètres de votre navigateur.</p>

        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">7. Contact</h2>
        <p class="text-gray-600 dark:text-gray-300">Pour toute question sur la confidentialité : <a href="mailto:<?= SITE_EMAIL ?>" class="text-blue-600"><?= SITE_EMAIL ?></a></p>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>