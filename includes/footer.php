<!-- ============================================
     FOOTER
     ============================================ -->
<footer class="bg-gray-900 text-gray-300">
    
    <!-- Newsletter -->
    <div class="bg-gradient-to-r from-blue-600 to-orange-500 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4 text-white">
                <div>
                    <h3 class="text-xl md:text-2xl font-bold flex items-center gap-2">
                        <i class="fas fa-envelope-open-text"></i>
                        Restez informé de nos offres
                    </h3>
                    <p class="text-white/80 text-sm mt-1">Inscrivez-vous pour recevoir les meilleures promotions</p>
                </div>
                <form method="POST" action="<?= BASE_URL ?>/newsletter.php" class="flex gap-2 w-full md:w-auto">
                    <input type="email" name="email" required placeholder="Votre email"
                           class="flex-1 md:w-80 px-5 py-3 rounded-lg text-gray-800 focus:outline-none focus:ring-4 focus:ring-white/30">
                    <button type="submit" class="px-6 py-3 bg-gray-900 text-white rounded-lg font-semibold hover:bg-gray-800 transition">
                        <i class="fas fa-paper-plane mr-2"></i>S'inscrire
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Main Footer -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
            
            <!-- À propos -->
            <div class="col-span-2 md:col-span-1">
                <a href="<?= BASE_URL ?>/" class="flex items-center gap-2 mb-4">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-orange-500 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-xl">P</span>
                    </div>
                    <div>
                        <p class="text-xl font-bold text-white">PekDev</p>
                        <p class="text-xs text-orange-500">Market</p>
                    </div>
                </a>
                <p class="text-sm text-gray-400 mb-4">La plus grande marketplace du Burundi. Achetez et vendez facilement près de chez vous.</p>
                <div class="flex gap-3">
                    <a href="#" class="w-9 h-9 bg-gray-800 hover:bg-blue-600 rounded-lg flex items-center justify-center transition"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="w-9 h-9 bg-gray-800 hover:bg-pink-600 rounded-lg flex items-center justify-center transition"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="w-9 h-9 bg-gray-800 hover:bg-blue-400 rounded-lg flex items-center justify-center transition"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="w-9 h-9 bg-gray-800 hover:bg-green-600 rounded-lg flex items-center justify-center transition"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
            
            <!-- Liens rapides -->
            <div>
                <h3 class="font-bold text-white mb-4 text-sm uppercase">Liens rapides</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="<?= BASE_URL ?>/" class="hover:text-white transition">Accueil</a></li>
                    <li><a href="<?= BASE_URL ?>/products.php" class="hover:text-white transition">Produits</a></li>
                    <li><a href="<?= BASE_URL ?>/promotions.php" class="hover:text-white transition">Promotions</a></li>
                    <li><a href="<?= BASE_URL ?>/categories.php" class="hover:text-white transition">Catégories</a></li>
                    <li><a href="<?= BASE_URL ?>/register.php?role=seller" class="hover:text-white transition">Devenir vendeur</a></li>
                </ul>
            </div>
            
            <!-- Aide -->
            <div>
                <h3 class="font-bold text-white mb-4 text-sm uppercase">Aide</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="<?= BASE_URL ?>/about.php" class="hover:text-white transition">À propos</a></li>
                    <li><a href="<?= BASE_URL ?>/contact.php" class="hover:text-white transition">Contact</a></li>
                    <li><a href="<?= BASE_URL ?>/faq.php" class="hover:text-white transition">FAQ</a></li>
                    <li><a href="<?= BASE_URL ?>/terms.php" class="hover:text-white transition">Conditions d'utilisation</a></li>
                    <li><a href="<?= BASE_URL ?>/privacy.php" class="hover:text-white transition">Confidentialité</a></li>
                </ul>
            </div>
            
            <!-- Contact -->
            <div>
                <h3 class="font-bold text-white mb-4 text-sm uppercase">Contact</h3>
                <ul class="space-y-3 text-sm">
                    <li class="flex items-start gap-2">
                        <i class="fas fa-map-marker-alt text-orange-500 mt-1"></i>
                        <span>Bujumbura, Burundi</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-phone text-orange-500 mt-1"></i>
                        <span>+257 67 301 044</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-envelope text-orange-500 mt-1"></i>
                        <span>contact@pekdev.bi</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-clock text-orange-500 mt-1"></i>
                        <span>Lun-Sam: 8h-20h</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Bottom Footer -->
    <div class="border-t border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4 text-sm">
                <p class="text-gray-400">© <?= date('Y') ?> PekDev Market. Tous droits réservés.</p>
                <div class="flex items-center gap-4">
                    <span class="text-gray-400">Moyens de paiement :</span>
                    <div class="flex gap-2 text-2xl">
                        <i class="fab fa-cc-visa text-blue-400"></i>
                        <i class="fab fa-cc-mastercard text-red-400"></i>
                        <i class="fas fa-mobile-alt text-green-400" title="Mobile Money"></i>
                        <i class="fas fa-money-bill-wave text-green-400" title="Cash"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Bouton retour en haut -->
<button id="backToTop" class="fixed bottom-6 right-6 w-12 h-12 bg-blue-600 text-white rounded-full shadow-lg hover:bg-blue-700 transition opacity-0 invisible z-40 flex items-center justify-center">
    <i class="fas fa-arrow-up"></i>
</button>

<script>
// Bouton retour en haut
const backToTop = document.getElementById('backToTop');
if (backToTop) {
    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) {
            backToTop.classList.remove('opacity-0', 'invisible');
            backToTop.classList.add('opacity-100', 'visible');
        } else {
            backToTop.classList.add('opacity-0', 'invisible');
            backToTop.classList.remove('opacity-100', 'visible');
        }
    });
    
    backToTop.addEventListener('click', () => {
        window.scrollTo({top: 0, behavior: 'smooth'});
    });
}
</script>