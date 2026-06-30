<?php
require_once __DIR__ . '/config/bootstrap.php';
$pageTitle = 'FAQ';
require_once __DIR__ . '/includes/header.php';

$faqs = [
    ['Q: Comment créer un compte ?', 'R: Cliquez sur "Inscription" en haut à droite, remplissez le formulaire et choisissez votre rôle (client ou vendeur).'],
    ['Q: Comment acheter un produit ?', 'R: Parcourez les produits, cliquez sur "Ajouter au panier", puis "Commander" pour finaliser votre achat.'],
    ['Q: Quels sont les moyens de paiement ?', 'R: Nous acceptons Mobile Money (Lumicash, Ecocash), carte bancaire et paiement à la livraison.'],
    ['Q: Combien coûte la livraison ?', 'R: La livraison est GRATUITE à Bujumbura pour les commandes supérieures à 100 000 FBu. Sinon, 5 000 FBu.'],
    ['Q: Comment devenir vendeur ?', 'R: Inscrivez-vous en tant que vendeur, puis ajoutez vos produits via votre dashboard. C\'est gratuit !'],
    ['Q: Comment suivre ma commande ?', 'R: Connectez-vous et allez dans "Mes commandes" pour voir le statut en temps réel.'],
    ['Q: Puis-je retourner un produit ?', 'R: Oui, vous avez 30 jours pour retourner un produit s\'il ne correspond pas à la description.'],
    ['Q: Comment contacter un vendeur ?', 'R: Cliquez sur le nom du vendeur sur la page produit pour accéder à sa boutique et le contacter.'],
    ['Q: Mes données sont-elles sécurisées ?', 'R: Oui, nous utilisons le chiffrement SSL et ne partageons jamais vos données avec des tiers.'],
    ['Q: Comment fonctionne le système d\'avis ?', 'R: Après réception de votre commande, vous pouvez laisser un avis pour aider la communauté.'],
];
?>

<section class="bg-gradient-to-r from-blue-600 to-orange-500 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-white">
        <h1 class="text-4xl font-bold mb-2">Questions fréquentes</h1>
        <p class="text-white/90">Trouvez rapidement les réponses à vos questions</p>
    </div>
</section>

<section class="py-12 bg-gray-50 dark:bg-gray-900">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Barre de recherche -->
        <div class="mb-8">
            <div class="relative max-w-2xl mx-auto">
                <input type="text" id="faqSearch" placeholder="Rechercher une question..." 
                       class="w-full pl-12 pr-4 py-4 rounded-xl border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-blue-500 focus:outline-none">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-xl"></i>
            </div>
        </div>

        <!-- FAQ -->
        <div class="space-y-3" id="faqList">
            <?php foreach ($faqs as $i => $faq): ?>
                <div class="faq-item bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <button onclick="toggleFaq(<?= $i ?>)" class="w-full p-5 text-left flex items-center justify-between gap-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <span class="font-semibold text-gray-800 dark:text-white"><?= clean($faq[0]) ?></span>
                        <i class="fas fa-chevron-down text-blue-600 transition-transform" id="faqIcon<?= $i ?>"></i>
                    </button>
                    <div class="hidden px-5 pb-5 text-gray-600 dark:text-gray-300" id="faqAnswer<?= $i ?>">
                        <?= clean($faq[1]) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Contact -->
        <div class="mt-12 bg-gradient-to-r from-blue-600 to-orange-500 rounded-2xl p-8 text-center text-white">
            <i class="fas fa-question-circle text-5xl mb-4"></i>
            <h2 class="text-2xl font-bold mb-2">Vous n'avez pas trouvé votre réponse ?</h2>
            <p class="text-white/90 mb-4">Contactez notre équipe, nous sommes là pour vous aider</p>
            <a href="<?= BASE_URL ?>/contact.php" class="inline-block bg-white text-blue-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition">
                <i class="fas fa-envelope mr-2"></i>Nous contacter
            </a>
        </div>
    </div>
</section>

<script>
function toggleFaq(i) {
    const answer = document.getElementById('faqAnswer' + i);
    const icon = document.getElementById('faqIcon' + i);
    answer.classList.toggle('hidden');
    icon.style.transform = answer.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
}

document.getElementById('faqSearch').addEventListener('input', function(e) {
    const query = e.target.value.toLowerCase();
    document.querySelectorAll('.faq-item').forEach((item, i) => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(query) ? 'block' : 'none';
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>