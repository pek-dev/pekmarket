<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'seller') { header('Location: ' . BASE_URL); exit; }

$sellerId = $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_shipment') {
        $orderId = intval($_POST['order_id'] ?? 0);
        $trackingNumber = clean(trim($_POST['tracking_number'] ?? ''));
        $carrier = clean(trim($_POST['carrier'] ?? ''));
        $estimatedDelivery = $_POST['estimated_delivery'] ?? null;
        $notes = clean(trim($_POST['notes'] ?? ''));
        
        try {
            $pdo->prepare("
                INSERT INTO shipments (order_id, seller_id, tracking_number, carrier, estimated_delivery, notes, status)
                VALUES (?, ?, ?, ?, ?, ?, 'shipped')
            ")->execute([$orderId, $sellerId, $trackingNumber, $carrier, $estimatedDelivery, $notes]);
            
            $pdo->prepare("UPDATE orders SET status = 'shipped' WHERE id = ?")->execute([$orderId]);
            $message = "✅ Expédition créée avec succès";
        } catch (Exception $e) { $message = "❌ Erreur: " . $e->getMessage(); }
    }
    elseif ($action === 'update_status') {
        $shipmentId = intval($_POST['shipment_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';
        
        try {
            $pdo->prepare("UPDATE shipments SET status = ?, updated_at = NOW() WHERE id = ? AND seller_id = ?")
                ->execute([$newStatus, $shipmentId, $sellerId]);
            
            if ($newStatus === 'delivered') {
                $stmt = $pdo->prepare("SELECT order_id FROM shipments WHERE id = ?");
                $stmt->execute([$shipmentId]);
                $orderId = $stmt->fetchColumn();
                $pdo->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?")->execute([$orderId]);
                $pdo->prepare("UPDATE shipments SET actual_delivery = NOW() WHERE id = ?")->execute([$shipmentId]);
            }
            $message = "✅ Statut mis à jour";
        } catch (Exception $e) { $message = "❌ Erreur: " . $e->getMessage(); }
    }
}

// Récupérer les expéditions
$shipments = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.*, o.order_number, o.total, o.created_at as order_date,
               u.first_name, u.last_name, u.email, u.phone,
               o.shipping_address, o.shipping_city, o.shipping_province
        FROM shipments s
        JOIN orders o ON s.order_id = o.id
        JOIN users u ON o.user_id = u.id
        WHERE s.seller_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$sellerId]);
    $shipments = $stmt->fetchAll();
} catch (Exception $e) {}

// Commandes prêtes à expédier
$readyToShip = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT o.*, u.first_name, u.last_name,
               o.shipping_address, o.shipping_city, o.shipping_province
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        JOIN users u ON o.user_id = u.id
        WHERE p.seller_id = ? 
          AND o.status IN ('confirmed', 'processing')
          AND o.id NOT IN (SELECT order_id FROM shipments)
        GROUP BY o.id
        ORDER BY o.created_at ASC
    ");
    $stmt->execute([$sellerId]);
    $readyToShip = $stmt->fetchAll();
} catch (Exception $e) {}

$pageTitle = 'Expéditions';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
            <i class="fas fa-truck text-blue-600"></i> Expéditions
        </h1>
        <p class="text-gray-500 mt-1">Gérez les livraisons de vos commandes</p>
    </div>

    <?php if ($message): ?>
        <div class="bg-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-50 border border-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-200 text-<?= strpos($message, '✅') !== false ? 'green' : 'red' ?>-700 px-4 py-3 rounded-xl mb-6"><?= clean($message) ?></div>
    <?php endif; ?>

    <!-- Commandes à expédier -->
    <?php if (count($readyToShip) > 0): ?>
    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-xl p-4 mb-6">
        <h2 class="font-bold text-yellow-800 dark:text-yellow-200 mb-3 flex items-center gap-2">
            <i class="fas fa-exclamation-circle"></i> Commandes à expédier (<?= count($readyToShip) ?>)
        </h2>
        <div class="space-y-2">
            <?php foreach ($readyToShip as $order): ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 flex items-center justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm text-gray-800 dark:text-white"><?= $order['order_number'] ?></p>
                        <p class="text-xs text-gray-500 truncate"><?= clean($order['first_name'] . ' ' . $order['last_name']) ?> • <?= clean($order['shipping_city']) ?></p>
                    </div>
                    <button onclick="openShipModal(<?= $order['id'] ?>, '<?= $order['order_number'] ?>')" 
                            class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700">
                        <i class="fas fa-truck mr-1"></i>Expédier
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Liste des expéditions -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="p-4 border-b dark:border-gray-700">
            <h2 class="font-bold text-gray-800 dark:text-white"><?= count($shipments) ?> expédition(s)</h2>
        </div>
        
        <?php if (count($shipments) > 0): ?>
            <div class="divide-y dark:divide-gray-700">
                <?php foreach ($shipments as $ship): 
                    $statusColors = ['preparing' => 'gray', 'shipped' => 'blue', 'in_transit' => 'indigo', 'out_for_delivery' => 'purple', 'delivered' => 'green', 'returned' => 'red'];
                    $statusLabels = ['preparing' => 'Préparation', 'shipped' => 'Expédié', 'in_transit' => 'En transit', 'out_for_delivery' => 'En livraison', 'delivered' => 'Livré', 'returned' => 'Retourné'];
                    $color = $statusColors[$ship['status']] ?? 'gray';
                ?>
                    <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <div class="flex flex-col md:flex-row justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <p class="font-bold text-blue-600"><?= $ship['order_number'] ?></p>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-<?= $color ?>-100 text-<?= $color ?>-800">
                                        <?= $statusLabels[$ship['status']] ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-800 dark:text-white font-semibold"><?= clean($ship['first_name'] . ' ' . $ship['last_name']) ?></p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-map-marker-alt mr-1"></i><?= clean($ship['shipping_address']) ?>, <?= clean($ship['shipping_city']) ?>
                                </p>
                                <?php if ($ship['tracking_number']): ?>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <i class="fas fa-barcode mr-1"></i>Tracking: <strong><?= clean($ship['tracking_number']) ?></strong>
                                    </p>
                                <?php endif; ?>
                                <?php if ($ship['carrier']): ?>
                                    <p class="text-xs text-gray-500">
                                        <i class="fas fa-shipping-fast mr-1"></i><?= clean($ship['carrier']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex flex-col items-end gap-2">
                                <p class="text-lg font-bold text-green-600"><?= formatPrice($ship['total']) ?></p>
                                <p class="text-xs text-gray-500"><?= date('d/m/Y', strtotime($ship['order_date'])) ?></p>
                                
                                <?php if ($ship['status'] !== 'delivered' && $ship['status'] !== 'returned'): ?>
                                    <form method="POST" class="flex gap-1 mt-2">
                                        <input type="hidden" name="shipment_id" value="<?= $ship['id'] ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <select name="new_status" onchange="this.form.submit()" class="text-xs px-2 py-1 border dark:border-gray-700 rounded dark:bg-gray-700 dark:text-white">
                                            <option value="">Changer statut...</option>
                                            <?php if ($ship['status'] === 'preparing'): ?>
                                                <option value="shipped">Expédié</option>
                                            <?php elseif ($ship['status'] === 'shipped'): ?>
                                                <option value="in_transit">En transit</option>
                                            <?php elseif ($ship['status'] === 'in_transit'): ?>
                                                <option value="out_for_delivery">En livraison</option>
                                            <?php elseif ($ship['status'] === 'out_for_delivery'): ?>
                                                <option value="delivered">Livré</option>
                                                <option value="returned">Retourné</option>
                                            <?php endif; ?>
                                        </select>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="p-12 text-center text-gray-500">
                <i class="fas fa-truck text-6xl text-gray-300 mb-4"></i>
                <p>Aucune expédition pour le moment</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal expédition -->
<div id="shipModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-6 rounded-t-2xl text-white">
            <h3 class="text-xl font-bold flex items-center gap-2">
                <i class="fas fa-truck"></i> Créer une expédition
            </h3>
            <p class="text-blue-100 text-sm mt-1">Commande: <strong id="modalOrderNumber"></strong></p>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="create_shipment">
            <input type="hidden" name="order_id" id="modalOrderId">
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Transporteur</label>
                <input type="text" name="carrier" required class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white" placeholder="Ex: DHL, FedEx, Local">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">N° de suivi</label>
                <input type="text" name="tracking_number" class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white" placeholder="Optionnel">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Date de livraison estimée</label>
                <input type="date" name="estimated_delivery" class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Notes</label>
                <textarea name="notes" rows="2" class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white"></textarea>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-blue-600 text-white py-2.5 rounded-lg font-semibold hover:bg-blue-700">
                    <i class="fas fa-paper-plane mr-2"></i>Créer
                </button>
                <button type="button" onclick="document.getElementById('shipModal').classList.add('hidden')" class="px-4 py-2.5 bg-gray-200 dark:bg-gray-700 rounded-lg font-semibold">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openShipModal(orderId, orderNumber) {
    document.getElementById('modalOrderId').value = orderId;
    document.getElementById('modalOrderNumber').textContent = orderNumber;
    document.getElementById('shipModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>