<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';


requireLogin();
if (!isSeller()) redirect(BASE_URL . '/register.php?role=seller');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $description = clean($_POST['description'] ?? '');
    
    if (empty($name)) $errors[] = 'Nom requis';
    if ($price <= 0) $errors[] = 'Prix invalide';
    if ($categoryId <= 0) $errors[] = 'Catégorie requise';
    
    if (empty($errors)) {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name)) . '-' . time();
        $stmt = $pdo->prepare("INSERT INTO products (seller_id, category_id, name, slug, description, price, stock, is_new, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)");
        $stmt->execute([$_SESSION['user_id'], $categoryId, $name, $slug, $description, $price, $stock]);
        $productId = $pdo->lastInsertId();
        
        if (!empty($_FILES['image']['name'])) {
            $uploadDir = UPLOADS_PATH . '/products/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('prod_') . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, 1)")->execute([$productId, UPLOADS_URL . '/products/' . $filename]);
            }
        }
        
        setFlash('success', 'Produit publié !');
        redirect(BASE_URL . '/profile.php');
    }
}

$categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Vendre - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af', secondary: '#f97316' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="max-w-3xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-6"><i class="fas fa-store text-orange-500 mr-2"></i>Publier un produit</h1>
        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 text-red-700 px-4 py-3 rounded-lg mb-4">
                <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm space-y-4">
            <input type="text" name="name" required placeholder="Nom du produit *" class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
            <select name="category_id" required class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                <option value="">Catégorie *</option>
                <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option><?php endforeach; ?>
            </select>
            <div class="grid grid-cols-2 gap-4">
                <input type="number" name="price" required min="0" placeholder="Prix (FBu) *" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
                <input type="number" name="stock" required min="0" placeholder="Stock *" class="px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
            </div>
            <textarea name="description" rows="5" placeholder="Description" class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg"></textarea>
            <input type="file" name="image" accept="image/*" required class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg">
            <button type="submit" class="w-full py-3 bg-orange-500 text-white rounded-lg font-semibold hover:bg-orange-600"><i class="fas fa-paper-plane mr-2"></i>Publier</button>
        </form>
    </div>
</body>
</html>