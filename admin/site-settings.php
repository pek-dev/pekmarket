<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: ' . BASE_URL); exit; }

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'setting_') === 0) {
                $settingKey = str_replace('setting_', '', $key);
                $pdo->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?")->execute([$value, $settingKey]);
            }
        }
        $message = "✅ Configuration mise à jour avec succès !";
    } catch (Exception $e) {
        $message = "❌ Erreur: " . $e->getMessage();
    }
}

// Récupérer toutes les configurations
$settings = [];
try {
    $settings = $pdo->query("SELECT * FROM site_settings ORDER BY setting_group, sort_order ASC")->fetchAll();
} catch (Exception $e) {}

// Grouper par catégorie
$groupedSettings = [];
foreach ($settings as $setting) {
    $groupedSettings[$setting['setting_group']][] = $setting;
}

$pageTitle = 'Configuration du Site';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <nav class="text-sm text-gray-500 mb-2">
            <a href="<?= BASE_URL ?>/dashboard/admin.php" class="hover:text-blue-600">Admin</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-gray-800 dark:text-white font-medium">Configuration</span>
        </nav>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
            <i class="fas fa-cog text-blue-600"></i> Configuration du Site
        </h1>
        <p class="text-gray-500 mt-2">Personnalisez l'apparence et le comportement de votre marketplace</p>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6"><?= clean($message) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?php foreach ($groupedSettings as $group => $groupSettings): ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 mb-6 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 text-white">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <i class="fas fa-<?= $group == 'general' ? 'globe' : ($group == 'header' ? 'header' : 'bars') ?>"></i>
                        <?= ucfirst($group) ?>
                    </h2>
                </div>
                <div class="p-6 space-y-4">
                    <?php foreach ($groupSettings as $setting): ?>
                        <div class="grid md:grid-cols-3 gap-4 items-center">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1">
                                    <?= clean($setting['setting_label']) ?>
                                </label>
                                <?php if ($setting['setting_description']): ?>
                                    <p class="text-xs text-gray-500"><?= clean($setting['setting_description']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="md:col-span-2">
                                <?php if ($setting['setting_type'] == 'text'): ?>
                                    <input type="text" name="setting_<?= $setting['setting_key'] ?>" 
                                           value="<?= clean($setting['setting_value']) ?>"
                                           class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                                <?php elseif ($setting['setting_type'] == 'textarea'): ?>
                                    <textarea name="setting_<?= $setting['setting_key'] ?>" rows="3"
                                              class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white"><?= clean($setting['setting_value']) ?></textarea>
                                <?php elseif ($setting['setting_type'] == 'color'): ?>
                                    <input type="color" name="setting_<?= $setting['setting_key'] ?>" 
                                           value="<?= clean($setting['setting_value']) ?>"
                                           class="w-32 h-10 border dark:border-gray-700 rounded-lg cursor-pointer">
                                <?php elseif ($setting['setting_type'] == 'boolean'): ?>
                                    <select name="setting_<?= $setting['setting_key'] ?>" 
                                            class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                                        <option value="1" <?= $setting['setting_value'] == '1' ? 'selected' : '' ?>>Oui</option>
                                        <option value="0" <?= $setting['setting_value'] == '0' ? 'selected' : '' ?>>Non</option>
                                    </select>
                                <?php elseif ($setting['setting_type'] == 'number'): ?>
                                    <input type="number" name="setting_<?= $setting['setting_key'] ?>" 
                                           value="<?= clean($setting['setting_value']) ?>"
                                           class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg dark:bg-gray-700 dark:text-white">
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="flex gap-3">
            <button type="submit" class="flex-1 bg-blue-600 text-white py-3.5 rounded-lg font-semibold hover:bg-blue-700 transition shadow-lg flex items-center justify-center gap-2">
                <i class="fas fa-save"></i> Enregistrer les modifications
            </button>
            <a href="<?= BASE_URL ?>/dashboard/admin.php" class="px-6 py-3.5 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white rounded-lg font-semibold hover:bg-gray-300 transition">
                Annuler
            </a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>