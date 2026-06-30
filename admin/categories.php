<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: ' . BASE_URL); exit; }

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add' || $action === 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $name = clean(trim($_POST['name'] ?? ''));
            $slug = clean(trim($_POST['slug'] ?? ''));
            $icon = clean($_POST['icon'] ?? 'fas fa-tag');
            $color = clean($_POST['color'] ?? 'blue');
            $description = clean(trim($_POST['description'] ?? ''));
            $sort_order = intval($_POST['sort_order'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($action === 'add') {
                $pdo->prepare("INSERT INTO categories (name, slug, icon, color, description, sort_order, is_active) VALUES (?,?,?,?,?,?,?)")->execute([$name, $slug, $icon, $color, $description, $sort_order, $is_active]);
                $message = "Catégorie ajoutée.";
            } else {
                $pdo->prepare("UPDATE categories SET name=?, slug=?, icon=?, color=?, description=?, sort_order=?, is_active=? WHERE id=?")->execute([$name, $slug, $icon, $color, $description, $sort_order, $is_active, $id]);
                $message = "Catégorie mise à jour.";
            }
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([intval($_POST['id'] ?? 0)]);
            $message = "Catégorie supprimée.";
        } elseif ($action === 'toggle') {
            $pdo->prepare("UPDATE categories SET is_active = NOT is_active WHERE id = ?")->execute([intval($_POST['id'] ?? 0)]);
            $message = "Statut modifié.";
        }
    } catch (Exception $e) { $message = "Erreur: " . $e->getMessage(); }
}

// CORRECTION : Utiliser COALESCE pour éviter les NULL
$categories = [];
try { 
    $categories = $pdo->query("
        SELECT c.*, COALESCE(COUNT(p.id), 0) as product_count 
        FROM categories c 
        LEFT JOIN products p ON c.id = p.category_id 
        GROUP BY c.id 
        ORDER BY c.sort_order ASC
    ")->fetchAll(); 
} catch (Exception $e) {}

$editCategory = null;
if (isset($_GET['edit'])) { 
    try { 
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?"); 
        $stmt->execute([intval($_GET['edit'])]); 
        $editCategory = $stmt->fetch(); 
    } catch (Exception $e) {} 
}

$pageTitle = 'Gestion des Catégories';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <nav class="text-sm text-gray-500 mb-2">
            <a href="<?= BASE_URL ?>/dashboard/admin.php" class="hover:text-blue-600">Admin</a> 
            <i class="fas fa-chevron-right text-xs mx-2"></i> 
            Catégories
        </nav>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white">
            <i class="fas fa-tags text-purple-600 mr-2"></i>Gestion des Catégories
        </h1>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6"><?= clean($message) ?></div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-3 gap-8">
        <!-- Formulaire -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-700 sticky top-4">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">
                    <i class="fas fa-<?= $editCategory ? 'edit' : 'plus' ?> text-blue-600 mr-2"></i>
                    <?= $editCategory ? 'Modifier' : 'Ajouter' ?> une catégorie
                </h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="<?= $editCategory ? 'edit' : 'add' ?>">
                    <?php if ($editCategory): ?>
                        <input type="hidden" name="id" value="<?= $editCategory['id'] ?>">
                    <?php endif; ?>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Nom *</label>
                        <input type="text" name="name" value="<?= clean($editCategory['name'] ?? '') ?>" required 
                               class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Slug *</label>
                        <input type="text" name="slug" value="<?= clean($editCategory['slug'] ?? '') ?>" required pattern="[a-z0-9\-]+"
                               class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Icône (Font Awesome)</label>
                        <input type="text" name="icon" value="<?= clean($editCategory['icon'] ?? 'fas fa-tag') ?>" 
                               class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Couleur</label>
                        <select name="color" class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                            <?php foreach (['blue','green','red','yellow','purple','pink','orange','teal','indigo'] as $c): ?>
                                <option value="<?= $c ?>" <?= ($editCategory['color'] ?? '') == $c ? 'selected' : '' ?>>
                                    <?= ucfirst($c) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Description</label>
                        <textarea name="description" rows="2" 
                                  class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white"><?= clean($editCategory['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Ordre de tri</label>
                        <input type="number" name="sort_order" value="<?= $editCategory['sort_order'] ?? 0 ?>" 
                               class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" id="is_active" 
                               <?= ($editCategory['is_active'] ?? 1) ? 'checked' : '' ?> class="w-4 h-4">
                        <label for="is_active" class="text-sm text-gray-700 dark:text-gray-200">Active</label>
                    </div>
                    
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 font-semibold">
                            <i class="fas fa-save mr-2"></i><?= $editCategory ? 'Mettre à jour' : 'Ajouter' ?>
                        </button>
                        <?php if ($editCategory): ?>
                            <a href="<?= BASE_URL ?>/admin/categories.php" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded-lg">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des catégories -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b dark:border-gray-700">
                    <h2 class="font-bold text-gray-800 dark:text-white"><?= count($categories) ?> catégories</h2>
                </div>
                <div class="divide-y dark:divide-gray-700">
                    <?php foreach ($categories as $cat): ?>
                        <div class="p-4 flex items-center gap-4 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <div class="w-12 h-12 bg-<?= $cat['color'] ?>-100 dark:bg-<?= $cat['color'] ?>-900 rounded-xl flex items-center justify-center">
                                <i class="<?= $cat['icon'] ?> text-<?= $cat['color'] ?>-500 text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-800 dark:text-white"><?= clean($cat['name']) ?></h3>
                                <p class="text-xs text-gray-500">
                                    Slug: <?= clean($cat['slug']) ?> • 
                                    <strong><?= $cat['product_count'] ?? 0 ?></strong> produits
                                </p>
                            </div>
                            <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $cat['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= $cat['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                            <div class="flex gap-1">
                                <a href="?edit=<?= $cat['id'] ?>" class="w-8 h-8 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 flex items-center justify-center">
                                    <i class="fas fa-edit text-xs"></i>
                                </a>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <button class="w-8 h-8 bg-yellow-100 text-yellow-600 rounded-lg hover:bg-yellow-200 flex items-center justify-center">
                                        <i class="fas fa-power-off text-xs"></i>
                                    </button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Supprimer cette catégorie ?');" class="inline">
                                    <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button class="w-8 h-8 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 flex items-center justify-center">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>