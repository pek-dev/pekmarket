<?php
require_once __DIR__ . '/config/bootstrap.php';
requireLogin();

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Récupérer l'ID de la conversation sélectionnée
$otherUserId = intval($_GET['with'] ?? 0);
$productId = intval($_GET['product'] ?? 0);
$openModal = isset($_GET['new']) ? true : false;

// Si pas de conversation sélectionnée, prendre la première
if ($otherUserId <= 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                CASE WHEN from_user_id = ? THEN to_user_id ELSE from_user_id END as other_id
            FROM messages 
            WHERE from_user_id = ? OR to_user_id = ?
            GROUP BY other_id
            ORDER BY MAX(created_at) DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $userId, $userId]);
        $first = $stmt->fetch();
        if ($first) $otherUserId = $first['other_id'];
    } catch (Exception $e) {}
}

// Récupérer les infos de l'autre utilisateur
$otherUser = null;
if ($otherUserId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, role, avatar FROM users WHERE id = ?");
        $stmt->execute([$otherUserId]);
        $otherUser = $stmt->fetch();
        
        // Marquer les messages comme lus
        $pdo->prepare("UPDATE messages SET is_read = 1 WHERE from_user_id = ? AND to_user_id = ? AND is_read = 0")
            ->execute([$otherUserId, $userId]);
    } catch (Exception $e) {}
}

// Récupérer les conversations
$conversations = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id, u.first_name, u.last_name, u.role, u.avatar,
            (SELECT message FROM messages 
             WHERE (from_user_id = ? AND to_user_id = u.id) 
                OR (from_user_id = u.id AND to_user_id = ?)
             ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT created_at FROM messages 
             WHERE (from_user_id = ? AND to_user_id = u.id) 
                OR (from_user_id = u.id AND to_user_id = ?)
             ORDER BY created_at DESC LIMIT 1) as last_time,
            (SELECT COUNT(*) FROM messages 
             WHERE from_user_id = u.id AND to_user_id = ? AND is_read = 0) as unread_count
        FROM users u
        WHERE u.id IN (
            SELECT DISTINCT 
                CASE WHEN from_user_id = ? THEN to_user_id ELSE from_user_id END
            FROM messages 
            WHERE from_user_id = ? OR to_user_id = ?
        )
        ORDER BY last_time DESC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
    $conversations = $stmt->fetchAll();
} catch (Exception $e) {}

// Récupérer les messages de la conversation
$messages = [];
if ($otherUserId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, p.name as product_name, p.slug as product_slug,
                   (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as product_image
            FROM messages m
            LEFT JOIN products p ON m.product_id = p.id
            WHERE (m.from_user_id = ? AND m.to_user_id = ?) 
               OR (m.from_user_id = ? AND m.to_user_id = ?)
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$userId, $otherUserId, $otherUserId, $userId]);
        $messages = $stmt->fetchAll();
    } catch (Exception $e) {}
}

// Produit lié
$linkedProduct = null;
if ($productId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, u.id as seller_id, u.first_name, u.last_name,
                   (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_path
            FROM products p
            JOIN users u ON p.seller_id = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$productId]);
        $linkedProduct = $stmt->fetch();
        
        if ($linkedProduct && $otherUserId <= 0) {
            $otherUserId = $linkedProduct['seller_id'];
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, role FROM users WHERE id = ?");
            $stmt->execute([$otherUserId]);
            $otherUser = $stmt->fetch();
        }
    } catch (Exception $e) {}
}

// Contacts potentiels (vendeurs, clients, support)
$potentialContacts = [];
try {
    if ($userRole === 'customer') {
        // Client peut contacter : vendeurs, support
        $stmt = $pdo->query("
            SELECT id, first_name, last_name, role, city, province 
            FROM users 
            WHERE role IN ('seller', 'admin') AND is_active = 1 
            ORDER BY role DESC, first_name ASC 
            LIMIT 50
        ");
    } elseif ($userRole === 'seller') {
        // Vendeur peut contacter : clients qui ont acheté, admin
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.first_name, u.last_name, u.role, u.city, u.province
            FROM users u
            JOIN orders o ON u.id = o.user_id
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            WHERE p.seller_id = ? AND u.id != ?
            UNION
            SELECT id, first_name, last_name, role, city, province FROM users WHERE role = 'admin' AND is_active = 1
            ORDER BY role DESC, first_name ASC
            LIMIT 50
        ");
        $stmt->execute([$userId, $userId]);
    } else {
        // Admin peut contacter : tout le monde
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, role, city, province FROM users WHERE id != ? AND is_active = 1 ORDER BY role DESC, first_name ASC LIMIT 50");
        $stmt->execute([$userId]);
    }
    $potentialContacts = $stmt->fetchAll();
} catch (Exception $e) {}

$totalUnread = 0;
foreach ($conversations as $c) $totalUnread += $c['unread_count'];

$pageTitle = 'Messages';
require_once __DIR__ . '/includes/header.php';
?>

<section class="bg-gray-50 dark:bg-gray-900 min-h-screen pt-4">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                    <i class="fas fa-comments text-blue-600"></i> Messagerie
                    <?php if ($totalUnread > 0): ?>
                        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full font-bold"><?= $totalUnread ?></span>
                    <?php endif; ?>
                </h1>
                <p class="text-sm text-gray-500 mt-1">Communiquez avec les vendeurs, clients et le support</p>
            </div>
            <button onclick="openContactSelector()" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold text-sm flex items-center gap-2 shadow-md">
                <i class="fas fa-plus"></i> <span class="hidden md:inline">Nouvelle conversation</span>
            </button>
        </div>

        <!-- Raccourcis de contact rapide -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-4 border border-gray-100 dark:border-gray-700">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-3">Contact rapide</p>
            <div class="flex gap-2 overflow-x-auto custom-scrollbar pb-2">
                <?php 
                // Support admin
                $adminId = 1;
                $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                $stmt->execute([$adminId]);
                $admin = $stmt->fetch();
                ?>
                <a href="?with=<?= $adminId ?>" class="flex-shrink-0 flex items-center gap-2 px-3 py-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg hover:bg-red-100 transition">
                    <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center text-white text-xs font-bold">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-red-800 dark:text-red-300">Support</p>
                        <p class="text-[10px] text-red-600">Assistance 24/7</p>
                    </div>
                </a>
                
                <?php if ($userRole === 'customer'): ?>
                    <!-- Vendeurs populaires -->
                    <?php 
                    $topSellers = $pdo->query("
                        SELECT u.id, u.first_name, u.last_name, COUNT(p.id) as products
                        FROM users u
                        JOIN products p ON u.id = p.seller_id
                        WHERE u.role = 'seller' AND u.is_active = 1
                        GROUP BY u.id
                        ORDER BY products DESC
                        LIMIT 5
                    ")->fetchAll();
                    foreach ($topSellers as $seller): 
                    ?>
                        <a href="?with=<?= $seller['id'] ?>" class="flex-shrink-0 flex items-center gap-2 px-3 py-2 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg hover:bg-green-100 transition">
                            <div class="w-8 h-8 bg-gradient-to-br from-blue-600 to-orange-500 rounded-full flex items-center justify-center text-white text-xs font-bold">
                                <?= strtoupper(substr($seller['first_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-gray-800 dark:text-white"><?= clean($seller['first_name']) ?></p>
                                <p class="text-[10px] text-gray-500"><?= $seller['products'] ?> produits</p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg overflow-hidden border border-gray-100 dark:border-gray-700" style="height: 70vh;">
            <div class="grid md:grid-cols-3 h-full">
                
                <!-- Sidebar : Liste des conversations -->
                <div class="border-r dark:border-gray-700 overflow-y-auto custom-scrollbar <?= $otherUserId > 0 ? 'hidden md:block' : '' ?>" id="conversationsList">
                    <div class="p-4 border-b dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                        <h2 class="font-bold text-gray-800 dark:text-white mb-2">Conversations</h2>
                        <input type="text" id="searchConv" placeholder="Rechercher..." 
                               class="w-full px-3 py-2 text-sm border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <?php if (count($conversations) > 0): ?>
                        <div class="divide-y dark:divide-gray-700">
                            <?php foreach ($conversations as $conv): ?>
                                <a href="?with=<?= $conv['id'] ?>" 
                                   class="conv-item flex items-center gap-3 p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition <?= $otherUserId == $conv['id'] ? 'bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-600' : '' ?>">
                                    <div class="relative flex-shrink-0">
                                        <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-orange-500 rounded-full flex items-center justify-center text-white font-bold">
                                            <?= strtoupper(substr($conv['first_name'], 0, 1) . substr($conv['last_name'], 0, 1)) ?>
                                        </div>
                                        <?php if ($conv['unread_count'] > 0): ?>
                                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] w-5 h-5 rounded-full flex items-center justify-center font-bold"><?= $conv['unread_count'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="font-semibold text-gray-800 dark:text-white text-sm truncate">
                                                <?= clean($conv['first_name'] . ' ' . $conv['last_name']) ?>
                                            </p>
                                            <?php if ($conv['last_time']): ?>
                                                <span class="text-[10px] text-gray-400 flex-shrink-0"><?= timeAgo($conv['last_time']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-xs text-gray-500 truncate mt-0.5">
                                            <?= clean(substr($conv['last_message'] ?? 'Aucun message', 0, 40)) ?>
                                        </p>
                                        <span class="inline-block mt-1 text-[10px] px-2 py-0.5 rounded-full font-semibold
                                            <?= $conv['role'] == 'admin' ? 'bg-red-100 text-red-800' : 
                                               ($conv['role'] == 'seller' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800') ?>">
                                            <?= $conv['role'] == 'admin' ? 'Support' : ucfirst($conv['role']) ?>
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-comments text-4xl mb-3 text-gray-300"></i>
                            <p class="text-sm">Aucune conversation</p>
                            <button onclick="openContactSelector()" class="mt-3 text-blue-600 hover:text-blue-800 font-semibold text-sm">
                                <i class="fas fa-plus mr-1"></i>Démarrer une discussion
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Zone de chat -->
                <div class="md:col-span-2 flex flex-col h-full <?= $otherUserId <= 0 ? 'hidden md:flex' : '' ?>" id="chatArea">
                    
                    <?php if ($otherUser): ?>
                        <!-- Header du chat -->
                        <div class="p-4 border-b dark:border-gray-700 flex items-center justify-between bg-white dark:bg-gray-800 sticky top-0 z-10">
                            <div class="flex items-center gap-3">
                                <a href="?with=<?= $otherUser['id'] ?>" class="md:hidden p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                                    <i class="fas fa-arrow-left"></i>
                                </a>
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-orange-500 rounded-full flex items-center justify-center text-white font-bold">
                                    <?= strtoupper(substr($otherUser['first_name'], 0, 1) . substr($otherUser['last_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800 dark:text-white"><?= clean($otherUser['first_name'] . ' ' . $otherUser['last_name']) ?></p>
                                    <p class="text-xs text-green-500 flex items-center gap-1">
                                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                        <?= $otherUser['role'] == 'admin' ? 'Support' : ($otherUser['role'] == 'seller' ? 'Vendeur' : 'Client') ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <?php if ($otherUser['role'] === 'seller'): ?>
                                    <a href="<?= BASE_URL ?>/shop.php?seller=<?= $otherUser['id'] ?>" target="_blank" 
                                       class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg text-gray-500 hover:text-blue-600" title="Voir boutique">
                                        <i class="fas fa-store"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Messages -->
                        <div class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar bg-gray-50 dark:bg-gray-900" id="messagesContainer">
                            
                            <?php if ($linkedProduct): ?>
                                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-3 flex items-center gap-3">
                                    <img src="<?= $linkedProduct['image_path'] ?: 'https://via.placeholder.com/60' ?>" class="w-14 h-14 rounded-lg object-cover">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-blue-600 font-semibold">À propos du produit</p>
                                        <p class="text-sm font-medium text-gray-800 dark:text-white truncate"><?= clean($linkedProduct['name']) ?></p>
                                        <p class="text-xs text-blue-600 font-bold"><?= formatPrice($linkedProduct['price']) ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (count($messages) > 0): ?>
                                <?php 
                                $currentDate = '';
                                foreach ($messages as $msg): 
                                    $msgDate = date('d/m/Y', strtotime($msg['created_at']));
                                    if ($msgDate !== $currentDate):
                                        $currentDate = $msgDate;
                                ?>
                                    <div class="flex justify-center my-3">
                                        <span class="bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-xs px-3 py-1 rounded-full">
                                            <?= $msgDate == date('d/m/Y') ? "Aujourd'hui" : $msgDate ?>
                                        </span>
                                    </div>
                                <?php endif; 
                                    $isOwn = $msg['from_user_id'] == $userId;
                                ?>
                                    <div class="flex <?= $isOwn ? 'justify-end' : 'justify-start' ?>">
                                        <div class="max-w-[75%] <?= $isOwn ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-800 dark:text-white' ?> rounded-2xl px-4 py-2 shadow-sm">
                                            <?php if ($msg['product_name'] && !$linkedProduct): ?>
                                                <div class="mb-2 pb-2 border-b <?= $isOwn ? 'border-blue-500' : 'border-gray-200 dark:border-gray-700' ?> flex items-center gap-2">
                                                    <img src="<?= $msg['product_image'] ?: 'https://via.placeholder.com/40' ?>" class="w-8 h-8 rounded object-cover">
                                                    <span class="text-xs font-semibold"><?= clean($msg['product_name']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <p class="text-sm whitespace-pre-wrap break-words"><?= clean($msg['message']) ?></p>
                                            <p class="text-[10px] <?= $isOwn ? 'text-blue-200' : 'text-gray-400' ?> mt-1 text-right">
                                                <?= date('H:i', strtotime($msg['created_at'])) ?>
                                                <?php if ($isOwn): ?>
                                                    <i class="fas fa-<?= $msg['is_read'] ? 'check-double' : 'check' ?> ml-1"></i>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-12 text-gray-500">
                                    <i class="fas fa-comment-dots text-5xl mb-3 text-gray-300"></i>
                                    <p>Aucun message</p>
                                    <p class="text-xs mt-1">Commencez la conversation !</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Formulaire d'envoi -->
                        <form method="POST" action="<?= BASE_URL ?>/api/send-message.php" class="p-4 border-t dark:border-gray-700 bg-white dark:bg-gray-800">
                            <input type="hidden" name="to_user_id" value="<?= $otherUserId ?>">
                            <input type="hidden" name="product_id" value="<?= $productId ?>">
                            <div class="flex gap-2">
                                <textarea name="message" id="messageInput" rows="1" required minlength="1" maxlength="2000"
                                          class="flex-1 px-4 py-2 border dark:border-gray-700 rounded-full resize-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                          placeholder="Tapez votre message..." onkeydown="handleEnter(event)"></textarea>
                                <button type="submit" class="w-12 h-12 bg-blue-600 text-white rounded-full hover:bg-blue-700 transition flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="flex-1 flex items-center justify-center text-center p-8">
                            <div>
                                <i class="fas fa-comments text-6xl text-gray-300 mb-4"></i>
                                <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Sélectionnez une conversation</h3>
                                <p class="text-gray-500 mb-4">Choisissez une conversation ou démarrez-en une nouvelle</p>
                                <button onclick="openContactSelector()" class="px-6 py-3 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition">
                                    <i class="fas fa-plus mr-2"></i>Nouvelle conversation
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     MODAL DE SÉLECTION DE CONTACT
     ============================================ -->
<div id="contactSelectorModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-2xl w-full max-h-[85vh] overflow-hidden flex flex-col">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-orange-500 p-6 text-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold flex items-center gap-2">
                    <i class="fas fa-user-plus"></i> Nouvelle conversation
                </h3>
                <button onclick="closeContactSelector()" class="text-white/80 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <p class="text-white/90 text-sm">Choisissez un destinataire pour démarrer une conversation</p>
        </div>
        
        <!-- Tabs -->
        <div class="border-b dark:border-gray-700 px-4">
            <div class="flex gap-1">
                <button onclick="switchTab('all')" id="tabAll" class="tab-btn px-4 py-3 text-sm font-semibold border-b-2 border-blue-600 text-blue-600">
                    <i class="fas fa-users mr-1"></i>Tous
                </button>
                <?php if ($userRole !== 'admin'): ?>
                <button onclick="switchTab('support')" id="tabSupport" class="tab-btn px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                    <i class="fas fa-headset mr-1"></i>Support
                </button>
                <?php endif; ?>
                <?php if ($userRole === 'customer'): ?>
                <button onclick="switchTab('sellers')" id="tabSellers" class="tab-btn px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                    <i class="fas fa-store mr-1"></i>Vendeurs
                </button>
                <?php endif; ?>
                <?php if ($userRole === 'seller'): ?>
                <button onclick="switchTab('customers')" id="tabCustomers" class="tab-btn px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                    <i class="fas fa-shopping-bag mr-1"></i>Clients
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recherche -->
        <div class="p-4 border-b dark:border-gray-700">
            <div class="relative">
                <input type="text" id="contactSearch" placeholder="Rechercher par nom ou email..." 
                       class="w-full pl-10 pr-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white"
                       oninput="filterContacts()">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            </div>
        </div>
        
        <!-- Liste des contacts -->
        <div class="flex-1 overflow-y-auto custom-scrollbar p-2">
            <?php if (count($potentialContacts) > 0): ?>
                <div id="contactsList" class="space-y-1">
                    <?php foreach ($potentialContacts as $contact): 
                        $roleColors = [
                            'admin' => ['bg' => 'red', 'label' => 'Support'],
                            'seller' => ['bg' => 'green', 'label' => 'Vendeur'],
                            'customer' => ['bg' => 'blue', 'label' => 'Client']
                        ];
                        $rc = $roleColors[$contact['role']] ?? ['bg' => 'gray', 'label' => 'Utilisateur'];
                    ?>
                        <a href="?with=<?= $contact['id'] ?>" 
                           class="contact-item flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition"
                           data-role="<?= $contact['role'] ?>"
                           data-name="<?= strtolower(clean($contact['first_name'] . ' ' . $contact['last_name'])) ?>">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-orange-500 rounded-full flex items-center justify-center text-white font-bold flex-shrink-0">
                                <?= strtoupper(substr($contact['first_name'], 0, 1) . substr($contact['last_name'], 0, 1)) ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-800 dark:text-white text-sm truncate">
                                    <?= clean($contact['first_name'] . ' ' . $contact['last_name']) ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <i class="fas fa-map-marker-alt mr-1"></i><?= clean($contact['city'] ?? $contact['province'] ?? 'Burundi') ?>
                                </p>
                            </div>
                            <span class="px-2 py-1 rounded-full text-xs font-semibold bg-<?= $rc['bg'] ?>-100 text-<?= $rc['bg'] ?>-800 flex-shrink-0">
                                <?= $rc['label'] ?>
                            </span>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-user-slash text-4xl mb-3 text-gray-300"></i>
                    <p>Aucun contact disponible</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Auto-scroll vers le bas
const messagesContainer = document.getElementById('messagesContainer');
if (messagesContainer) {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

// Envoyer avec Entrée
function handleEnter(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        e.target.form.submit();
    }
}

// Recherche dans les conversations
document.getElementById('searchConv')?.addEventListener('input', function(e) {
    const query = e.target.value.toLowerCase();
    document.querySelectorAll('.conv-item').forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(query) ? 'flex' : 'none';
    });
});

// ============================================
// GESTION DU MODAL DE SÉLECTION
// ============================================
function openContactSelector() {
    document.getElementById('contactSelectorModal').classList.remove('hidden');
    document.getElementById('contactSearch').focus();
}

function closeContactSelector() {
    document.getElementById('contactSelectorModal').classList.add('hidden');
}

function switchTab(tab) {
    // Reset tous les tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('border-blue-600', 'text-blue-600');
        btn.classList.add('border-transparent', 'text-gray-500');
    });
    
    // Activer le tab sélectionné
    const activeTab = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
    activeTab.classList.remove('border-transparent', 'text-gray-500');
    activeTab.classList.add('border-blue-600', 'text-blue-600');
    
    // Filtrer les contacts
    const roleMap = {
        'all': null,
        'support': 'admin',
        'sellers': 'seller',
        'customers': 'customer'
    };
    
    const targetRole = roleMap[tab];
    document.querySelectorAll('.contact-item').forEach(item => {
        if (targetRole === null || item.dataset.role === targetRole) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

function filterContacts() {
    const query = document.getElementById('contactSearch').value.toLowerCase();
    document.querySelectorAll('.contact-item').forEach(item => {
        const name = item.dataset.name;
        item.style.display = name.includes(query) ? 'flex' : 'none';
    });
}

// Fermer avec Echap
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeContactSelector();
    }
});

// Ouvrir automatiquement si ?new=1 dans l'URL
<?php if ($openModal): ?>
openContactSelector();
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>