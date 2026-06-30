<?php
require_once __DIR__ . '/config/bootstrap.php';

http_response_code(404);
require_once __DIR__ . '/includes/header.php';




$pageTitle = 'Page introuvable';

?>

<div class="min-h-[70vh] flex items-center justify-center px-4">
    <div class="text-center">
        <div class="text-9xl font-bold text-blue-600 mb-4">404</div>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-4">Page introuvable</h1>
        <p class="text-gray-600 dark:text-gray-300 mb-8 max-w-md">
            La page que vous recherchez n'existe pas ou a été déplacée.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="<?= BASE_URL ?>/" class="px-6 py-3 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-800">
                <i class="fas fa-home mr-2"></i>Accueil
            </a>
            <a href="<?= BASE_URL ?>/products.php" class="px-6 py-3 border-2 border-blue-600 text-blue-600 rounded-lg font-semibold hover:bg-blue-600 hover:text-white">
                <i class="fas fa-search mr-2"></i>Produits
            </a>
        </div>
    </div>
</div>

<?php  ?>