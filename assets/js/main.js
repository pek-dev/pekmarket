// assets/js/main.js - Version corrigée

// Configuration globale
const CONFIG = {
    BASE_URL: document.querySelector('meta[name="base-url"]')?.content || '',
    CSRF_TOKEN: document.querySelector('meta[name="csrf-token"]')?.content || ''
};

// Theme toggle
const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;

if (localStorage.getItem('theme') === 'dark' || 
    (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    html.classList.add('dark');
}

if (themeToggle) {
    themeToggle.addEventListener('click', () => {
        html.classList.toggle('dark');
        localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
    });
}

// Mobile menu
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const mobileMenu = document.getElementById('mobileMenu');
if (mobileMenuBtn && mobileMenu) {
    mobileMenuBtn.addEventListener('click', () => mobileMenu.classList.toggle('hidden'));
}

// Mobile search
const mobileSearchBtn = document.getElementById('mobileSearchBtn');
const mobileSearch = document.getElementById('mobileSearch');
if (mobileSearchBtn && mobileSearch) {
    mobileSearchBtn.addEventListener('click', () => {
        mobileSearch.classList.toggle('hidden');
        if (!mobileSearch.classList.contains('hidden')) {
            mobileSearch.querySelector('input').focus();
        }
    });
}

// Ajouter au panier (AJAX)
function addToCart(productId, quantity = 1) {
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
    formData.append('csrf_token', CONFIG.CSRF_TOKEN);
    
    fetch(CONFIG.BASE_URL + '/api/cart.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            const countEl = document.getElementById('cartCount');
            if (countEl) {
                countEl.textContent = data.count;
                countEl.classList.remove('hidden');
                // Animation bounce
                countEl.classList.add('animate-bounce');
                setTimeout(() => countEl.classList.remove('animate-bounce'), 1000);
            }
        } else {
            showToast(data.message || 'Erreur', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Erreur de connexion', 'error');
    });
}

// Toggle favori
function toggleFavorite(productId, btn) {
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('csrf_token', CONFIG.CSRF_TOKEN);
    
    fetch(CONFIG.BASE_URL + '/api/favorites.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const icon = btn.querySelector('i');
            if (data.action === 'added') {
                icon.classList.remove('far');
                icon.classList.add('fas', 'text-red-500');
                btn.classList.add('border-red-500', 'text-red-500');
                showToast('Ajouté aux favoris', 'success');
            } else {
                icon.classList.remove('fas', 'text-red-500');
                icon.classList.add('far');
                btn.classList.remove('border-red-500', 'text-red-500');
                showToast('Retiré des favoris', 'info');
            }
        } else {
            showToast(data.message || 'Connectez-vous', 'warning');
        }
    });
}

// Toast notifications
function showToast(message, type = 'info') {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500',
        warning: 'bg-orange-500'
    };
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle',
        warning: 'fa-exclamation-triangle'
    };
    
    const toast = document.createElement('div');
    toast.className = `fixed top-24 right-4 z-[100] ${colors[type]} text-white px-6 py-3 rounded-lg shadow-xl flex items-center gap-3 min-w-[300px] animate-slide-in-right`;
    toast.innerHTML = `
        <i class="fas ${icons[type]}"></i>
        <span class="font-medium flex-1">${message}</span>
        <button onclick="this.parentElement.remove()" class="hover:bg-white/20 rounded p-1">
            <i class="fas fa-times"></i>
        </button>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.transition = 'all 0.3s';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Recherche avec autocomplétion
let searchTimeout;
const searchInputs = document.querySelectorAll('input[name="q"]');

searchInputs.forEach(input => {
    input.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();
        
        if (query.length < 2) {
            removeSuggestions();
            return;
        }
        
        searchTimeout = setTimeout(() => {
            fetchSuggestions(query, input);
        }, 300);
    });
    
    input.addEventListener('blur', () => {
        setTimeout(removeSuggestions, 200);
    });
});

function fetchSuggestions(query, input) {
    fetch(`${CONFIG.BASE_URL}/api/search.php?q=${encodeURIComponent(query)}`)
        .then(r => r.json())
        .then(data => {
            removeSuggestions();
            if (data.success && data.products.length > 0) {
                showSuggestions(data.products, input);
            }
        });
}

function showSuggestions(products, input) {
    const rect = input.getBoundingClientRect();
    const dropdown = document.createElement('div');
    dropdown.className = 'suggestions-dropdown absolute left-0 right-0 mt-2 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 max-h-96 overflow-y-auto z-50';
    dropdown.style.top = rect.bottom + 'px';
    dropdown.style.width = rect.width + 'px';
    dropdown.style.position = 'fixed';
    
    products.forEach(p => {
        const item = document.createElement('a');
        item.href = `${CONFIG.BASE_URL}/product.php?slug=${p.slug}`;
        item.className = 'flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition';
        item.innerHTML = `
            <img src="${p.image}" class="w-12 h-12 object-cover rounded" alt="">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-800 dark:text-white truncate">${p.name}</p>
                <p class="text-xs text-primary font-bold">${p.price_formatted}</p>
            </div>
        `;
        dropdown.appendChild(item);
    });
    
    document.body.appendChild(dropdown);
}

function removeSuggestions() {
    document.querySelectorAll('.suggestions-dropdown').forEach(el => el.remove());
}

// Fermer dropdowns au clic extérieur
document.addEventListener('click', (e) => {
    const userMenu = document.getElementById('userMenu');
    if (userMenu && !e.target.closest('[onclick*="userMenu"]') && !e.target.closest('#userMenu')) {
        userMenu.classList.add('hidden');
    }
});

// Back to top
const backToTop = document.getElementById('backToTop');
if (backToTop) {
    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) {
            backToTop.classList.remove('hidden');
            backToTop.classList.add('flex');
        } else {
            backToTop.classList.add('hidden');
            backToTop.classList.remove('flex');
        }
    });
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Confirmation avant suppression
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', (e) => {
        if (!confirm(el.dataset.confirm)) {
            e.preventDefault();
        }
    });
});

// Compteur de caractères
document.querySelectorAll('[data-maxlength]').forEach(input => {
    const counter = document.createElement('span');
    counter.className = 'text-xs text-gray-500 float-right';
    input.parentElement.appendChild(counter);
    
    input.addEventListener('input', () => {
        const max = parseInt(input.dataset.maxlength);
        counter.textContent = `${input.value.length}/${max}`;
    });
});

// Lazy loading des images
if ('IntersectionObserver' in window) {
    const imgObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                }
                imgObserver.unobserve(img);
            }
        });
    });
    
    document.querySelectorAll('img[data-src]').forEach(img => imgObserver.observe(img));
}

// Animation au scroll
const animateObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('animate-fade-in-up');
            animateObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.animate-on-scroll').forEach(el => animateObserver.observe(el));

// Format nombre avec espaces
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

// Export pour utilisation globale
window.addToCart = addToCart;
window.toggleFavorite = toggleFavorite;
window.showToast = showToast;
window.scrollToTop = scrollToTop;