-- PEKDEV MARKET - Base de données MySQL
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+02:00";

CREATE DATABASE IF NOT EXISTS `pekdev_marketog` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pekdev_marketog`;

CREATE TABLE `users` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(20) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `role` ENUM('customer','seller','admin') DEFAULT 'customer',
  `province` VARCHAR(50) DEFAULT NULL,
  `city` VARCHAR(50) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `is_verified` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `categories` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `icon` VARCHAR(50) DEFAULT 'fas fa-tag',
  `color` VARCHAR(20) DEFAULT 'blue',
  `description` TEXT DEFAULT NULL,
  `sort_order` INT(11) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `seller_id` INT(11) UNSIGNED NOT NULL,
  `category_id` INT(11) UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `description` TEXT,
  `short_description` VARCHAR(500) DEFAULT NULL,
  `price` DECIMAL(12,2) NOT NULL,
  `old_price` DECIMAL(12,2) DEFAULT NULL,
  `stock` INT(11) DEFAULT 0,
  `province` VARCHAR(50) DEFAULT NULL,
  `city` VARCHAR(50) DEFAULT NULL,
  `is_featured` TINYINT(1) DEFAULT 0,
  `is_new` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `views_count` INT(11) DEFAULT 0,
  `sales_count` INT(11) DEFAULT 0,
  `rating_avg` DECIMAL(3,2) DEFAULT 0.00,
  `rating_count` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_images` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `alt_text` VARCHAR(255) DEFAULT NULL,
  `is_primary` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `orders` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `order_number` VARCHAR(20) NOT NULL UNIQUE,
  `status` ENUM('pending','confirmed','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `payment_method` ENUM('cash','mobile_money','card') DEFAULT 'cash',
  `payment_status` ENUM('pending','paid','failed') DEFAULT 'pending',
  `subtotal` DECIMAL(12,2) NOT NULL,
  `shipping_cost` DECIMAL(12,2) DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL,
  `shipping_first_name` VARCHAR(50) NOT NULL,
  `shipping_last_name` VARCHAR(50) NOT NULL,
  `shipping_phone` VARCHAR(20) NOT NULL,
  `shipping_province` VARCHAR(50) NOT NULL,
  `shipping_city` VARCHAR(50) NOT NULL,
  `shipping_address` TEXT NOT NULL,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_items` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) UNSIGNED NOT NULL,
  `product_id` INT(11) UNSIGNED NOT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `product_image` VARCHAR(255) DEFAULT NULL,
  `quantity` INT(11) NOT NULL,
  `price` DECIMAL(12,2) NOT NULL,
  `total` DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cart` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `product_id` INT(11) UNSIGNED NOT NULL,
  `quantity` INT(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_product` (`user_id`, `product_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `favorites` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `product_id` INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_product` (`user_id`, `product_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reviews` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `product_id` INT(11) UNSIGNED NOT NULL,
  `rating` TINYINT(1) NOT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `comment` TEXT,
  `is_approved` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Données de démo (mot de passe: admin123)
INSERT INTO `users` (`first_name`, `last_name`, `email`, `phone`, `password`, `role`, `province`, `city`, `is_verified`) VALUES
('Admin', 'PekDev', 'admin@pekdev.bi', '+257 79 000 000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Bujumbura Mairie', 'Bujumbura', 1),
('Vendeur', 'Demo', 'vendeur@pekdev.bi', '+257 79 111 111', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seller', 'Bujumbura Mairie', 'Bujumbura', 1),
('Jean', 'Mugabo', 'client@pekdev.bi', '+257 79 222 222', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', 'Bujumbura Mairie', 'Bujumbura', 1);

INSERT INTO `categories` (`name`, `slug`, `icon`, `color`, `description`, `sort_order`) VALUES
('Électronique', 'electronique', 'fas fa-laptop', 'blue', 'Smartphones, ordinateurs', 1),
('Mode & Beauté', 'mode-beaute', 'fas fa-tshirt', 'pink', 'Vêtements, chaussures', 2),
('Maison', 'maison', 'fas fa-home', 'green', 'Meubles, électroménager', 3),
('Téléphones', 'telephones', 'fas fa-mobile-alt', 'purple', 'Smartphones', 4),
('Alimentation', 'alimentation', 'fas fa-utensils', 'orange', 'Produits alimentaires', 5),
('Autos & Motos', 'autos-motos', 'fas fa-car', 'red', 'Véhicules', 6),
('Services', 'services', 'fas fa-tools', 'teal', 'Services divers', 7);

INSERT INTO `products` (`seller_id`, `category_id`, `name`, `slug`, `description`, `short_description`, `price`, `old_price`, `stock`, `province`, `city`, `is_featured`, `is_new`, `is_active`, `views_count`, `sales_count`, `rating_avg`, `rating_count`) VALUES
(2, 1, 'Smartphone Samsung Galaxy A14', 'samsung-galaxy-a14', 'Smartphone dernière génération avec écran AMOLED 6.6 pouces, 128GB, 4GB RAM, 50MP.', 'Samsung A14 - 128GB - 4GB RAM', 250000.00, 300000.00, 25, 'Bujumbura Mairie', 'Bujumbura', 1, 1, 1, 1245, 89, 4.50, 24),
(2, 2, 'Chaussures Nike Air Max', 'nike-air-max', 'Chaussures de sport Nike Air Max originales. Confort optimal.', 'Nike Air Max - Confort et style', 120000.00, 150000.00, 40, 'Bujumbura Mairie', 'Bujumbura', 1, 0, 1, 892, 56, 5.00, 48),
(2, 5, 'Riz Local Premium 5kg', 'riz-local-premium-5kg', 'Riz local de première qualité, cultivé au Burundi.', 'Riz local burundais - 5kg', 15000.00, NULL, 200, 'Gitega', 'Gitega', 1, 0, 1, 2341, 345, 5.00, 89),
(2, 1, 'TV Smart 32 pouces Android', 'tv-smart-32-pouces', 'Téléviseur Smart TV 32 pouces avec Android TV intégré.', 'Smart TV 32 - Android - HD', 350000.00, 400000.00, 15, 'Bujumbura Mairie', 'Bujumbura', 1, 1, 1, 1567, 67, 4.50, 36),
(2, 4, 'iPhone 13 Pro Max 256GB', 'iphone-13-pro-max', 'iPhone 13 Pro Max neuf, 256GB. Écran Super Retina XDR 6.7 pouces.', 'iPhone 13 Pro Max - 256GB', 1500000.00, 1700000.00, 5, 'Bujumbura Mairie', 'Bujumbura', 1, 1, 1, 3421, 23, 5.00, 15),
(2, 5, 'Café du Burundi 1kg', 'cafe-burundi-1kg', 'Café 100% arabica du Burundi, cultivé en altitude.', 'Café arabica burundais - 1kg', 25000.00, NULL, 150, 'Ngozi', 'Ngozi', 1, 0, 1, 1234, 178, 5.00, 67);

INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`) VALUES
(1, 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?w=600', 'Samsung A14', 1),
(2, 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600', 'Nike Air', 1),
(3, 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=600', 'Riz', 1),
(4, 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=600', 'TV', 1),
(5, 'https://images.unsplash.com/photo-1592286927505-1def25115558?w=600', 'iPhone', 1),
(6, 'https://images.unsplash.com/photo-1447933601403-0c6688de566e?w=600', 'Café', 1);


-- Ajouter à la fin de database.sql

CREATE TABLE IF NOT EXISTS `provinces` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `provinces` (`name`) VALUES
('Bubanza'), ('Bujumbura Mairie'), ('Bujumbura Rural'), ('Bururi'), ('Cankuzo'),
('Cibitoke'), ('Gitega'), ('Karuzi'), ('Kayanza'), ('Kirundo'),
('Makamba'), ('Muramvya'), ('Muyinga'), ('Mwaro'), ('Ngozi'),
('Rutana'), ('Ruyigi'), ('Rumonge');

CREATE TABLE IF NOT EXISTS `newsletters` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Ajouter à database.sql

CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_user_id` INT(11) UNSIGNED NOT NULL,
  `to_user_id` INT(11) UNSIGNED NOT NULL,
  `product_id` INT(11) UNSIGNED DEFAULT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_from_user` (`from_user_id`),
  INDEX `idx_to_user` (`to_user_id`),
  INDEX `idx_product` (`product_id`),
  FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`to_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

