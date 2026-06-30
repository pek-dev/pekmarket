<?php
require_once __DIR__ . '/config/bootstrap.php';
$pageTitle = 'Conditions d\'utilisation';
require_once __DIR__ . '/includes/header.php';
?>

<section class="bg-gradient-to-r from-blue-600 to-blue-800 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-white">
        <h1 class="text-4xl font-bold mb-2">Conditions d'utilisation</h1>
        <p class="text-white/80">Dernière mise à jour : <?= date('d/m/Y') ?></p>
    </div>
</section>

<section class="py-12 bg-white dark:bg-gray-900">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 prose dark:prose-invert max-w-none">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">1. Acceptation des conditions</h2>
        <p class="text-gray-600 dark:text-gray-300">En utilisant PekDev Market, vous acceptez ces conditions d'utilisation. Si vous n'acceptez pas, veuillez ne pas utiliser notre plateforme.</p>

        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">2. Inscription</h2>
        <p class="text-gray-600 dark:text-gray-300">Pour créer un compte, vous devez fournir des informations exactes et complètes. Vous êtes responsable de la confidentialité de votre mot de passe.</p>

        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">3. Utilisation de la plateforme</h2>
        <ul class="text-gray-600 dark:text-gray-300">
            <li>Vous devez avoir au moins 18 ans pour utiliser nos services</li>
            <li>Vous ne pouvez pas publier de contenu illégal ou offensant</li>
            <li>Vous ne pouvez pas vendre de produits contrefaits</li>
            <li>Vous devez respecter les droits de propriété intellectuelle</li>
        </ul>

        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">4. Transactions</h2>
        <p class="text-gray-600 dark:text-gray-300">PekDev Market met en relation acheteurs et vendeurs. Les transactions sont effectuées directement entre les parties. Nous ne sommes pas responsables de la qualité des produits.</p>

        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">5. Commissions</h2>
        <p class="text-gray-600 dark:text-gray-300">PekDev Market peut prélever une commission sur les ventes effectuées via la plateforme. Les tarifs sont communiqués aux vendeurs lors de leur inscription.</p>

        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">6. Résiliation</h2>
        <p class="text-gray-600 dark:text-gray-300">Nous nous réservons le droit de suspendre ou supprimer tout compte qui viole ces conditions.</p>

        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">7. Modification</h2>
        <p class="text-gray-600 dark:text-gray-300">Nous pouvons modifier ces conditions à tout moment. Les utilisateurs seront informés des changements importants.</p>

        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">8. Contact</h2>
        <p class="text-gray-600 dark:text-gray-300">Pour toute question : <a href="mailto:<?= SITE_EMAIL ?>" class="text-blue-600"><?= SITE_EMAIL ?></a></p>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>