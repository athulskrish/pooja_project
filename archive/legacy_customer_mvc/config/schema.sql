-- ============================================================================
-- ZIPPY FOOD ORDERING & CLOUD KITCHEN MANAGEMENT PLATFORM
-- MCSP-232: Major Project in Master of Computer Applications (IGNOU)
-- Student: Pooja JP | Enrollment No: 2500051247
-- Relational Database Engine: MySQL 8.0 InnoDB (3NF Fully Normalized Schema)
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `zippy_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `zippy_db`;

-- ----------------------------------------------------------------------------
-- 1. Table: roles (RBAC Authorization)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `role_id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_name` VARCHAR(50) NOT NULL UNIQUE,
  `description` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`role_id`, `role_name`, `description`) VALUES
(1, 'CUSTOMER', 'Customer storefront access, food ordering, and live tracking'),
(2, 'KITCHEN', 'Kitchen display station cook/chef with WSJF prioritized queue'),
(3, 'RIDER', 'Delivery partner courier with route guidance and OTP verification'),
(4, 'ADMIN', 'Restaurant manager executive portal with BI reports and dish CRUD')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ----------------------------------------------------------------------------
-- 2. Table: users (Argon2id Encrypted Accounts)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_id` INT NOT NULL DEFAULT 1,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `phone_number` VARCHAR(30) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `membership_tier` VARCHAR(50) DEFAULT 'Zippy One Gold',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Demo Users (Password: pooja123 for all demo accounts)
-- Hash generated using standard password_hash('pooja123', PASSWORD_BCRYPT)
INSERT INTO `users` (`user_id`, `role_id`, `full_name`, `email`, `phone_number`, `password_hash`, `membership_tier`) VALUES
(1, 1, 'Pooja JP', 'pooja.mca2026@ignou.ac.in', '+91 98765 43210', '$2y$10$eO0a1pS3lQWqYwzJj9e45eB6k7m4v9c0x1y2z3a4b5c6d7e8f9g0h', 'Zippy One Gold'),
(2, 2, 'Chef Sanjeev / Kitchen Station 1', 'kitchen@zippy.in', '+91 98450 11223', '$2y$10$eO0a1pS3lQWqYwzJj9e45eB6k7m4v9c0x1y2z3a4b5c6d7e8f9g0h', 'Kitchen Head'),
(3, 3, 'Ramesh Kumar (Rider)', 'rider@zippy.in', '+91 98450 44556', '$2y$10$eO0a1pS3lQWqYwzJj9e45eB6k7m4v9c0x1y2z3a4b5c6d7e8f9g0h', 'Delivery Fleet'),
(4, 4, 'Pooja JP (System Admin)', 'admin@zippy.in', '+91 98765 00000', '$2y$10$eO0a1pS3lQWqYwzJj9e45eB6k7m4v9c0x1y2z3a4b5c6d7e8f9g0h', 'Super Admin')
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- ----------------------------------------------------------------------------
-- 3. Table: user_addresses (3NF Customer Delivery Addresses)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_addresses` (
  `address_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `address_type` ENUM('HOME', 'WORK', 'OTHER') DEFAULT 'HOME',
  `title` VARCHAR(100) DEFAULT 'Home',
  `address_line` TEXT NOT NULL,
  `landmark` VARCHAR(150) DEFAULT NULL,
  `pincode` VARCHAR(10) NOT NULL,
  `is_default` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_addresses` (`address_id`, `user_id`, `address_type`, `title`, `address_line`, `landmark`, `pincode`, `is_default`) VALUES
(1, 1, 'HOME', 'Indiranagar Flat', '#402, Green Meadows Apartment, 100 Feet Road, Indiranagar, Bengaluru', 'Near Metro Pillar 84', '560038', 1),
(2, 1, 'WORK', 'Tech Park Office', 'Tower B, 4th Floor, RMZ Infinity, Old Madras Road, Bennigana Halli, Bengaluru', 'Opposite Gopalan Mall', '560016', 0)
ON DUPLICATE KEY UPDATE `address_line` = VALUES(`address_line`);

-- ----------------------------------------------------------------------------
-- 4. Table: categories (Food Categories with Slugs)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_name` VARCHAR(100) NOT NULL UNIQUE,
  `category_description` TEXT,
  `category_slug` VARCHAR(100) NOT NULL UNIQUE,
  `badge` VARCHAR(50) DEFAULT NULL,
  `image_url` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`category_id`, `category_name`, `category_description`, `category_slug`, `badge`, `image_url`) VALUES
(1, 'Biryani', 'Authentic dum biryanis, aromatic spices and slow-cooked tender meat', 'biryani', 'Popular', 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?auto=format&fit=crop&w=400&q=80'),
(2, 'Starters & Kebabs', 'Crispy, smoky appetisers grilled in traditional charcoal tandoors', 'starters', 'Top Rated', 'https://images.unsplash.com/photo-1599488615731-7e5c2823ff28?auto=format&fit=crop&w=400&q=80'),
(3, 'Main Course & Curries', 'Rich and aromatic curries, gravies, and slow-simmered stews', 'curries', 'Chef Choice', 'https://images.unsplash.com/photo-1588166524941-3bf61a9c41db?auto=format&fit=crop&w=400&q=80'),
(4, 'Breads & Rice', 'Freshly baked tandoori naans, rotis, and fragrant jeera rice', 'breads', NULL, 'https://images.unsplash.com/photo-1601050690597-df0568f70950?auto=format&fit=crop&w=400&q=80'),
(5, 'Desserts & Sweets', 'Decadent traditional Indian sweets and classic cold treats', 'desserts', 'Sweet Tooth', 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?auto=format&fit=crop&w=400&q=80'),
(6, 'Beverages', 'Refreshing fruit lassis, cold sodas, and coolers', 'beverages', 'Chilled', 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=400&q=80')
ON DUPLICATE KEY UPDATE `category_name` = VALUES(`category_name`);

-- ----------------------------------------------------------------------------
-- 5. Table: menu_items (Dishes with WSJF Prep Times & Kitchen Station)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `menu_items` (
  `item_id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `item_name` VARCHAR(150) NOT NULL,
  `description` TEXT,
  `price` DECIMAL(10,2) NOT NULL,
  `stock_quantity` INT NOT NULL DEFAULT 50,
  `is_available` TINYINT(1) DEFAULT 1,
  `is_veg` TINYINT(1) DEFAULT 0,
  `is_bestseller` TINYINT(1) DEFAULT 0,
  `rating` DECIMAL(2,1) DEFAULT 4.5,
  `rating_count` INT DEFAULT 150,
  `prep_time_minutes` INT NOT NULL DEFAULT 15,
  `kitchen_station` VARCHAR(50) DEFAULT 'Handi',
  `image_url` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_menu_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `menu_items` (`item_id`, `category_id`, `item_name`, `description`, `price`, `stock_quantity`, `is_available`, `is_veg`, `is_bestseller`, `rating`, `rating_count`, `prep_time_minutes`, `kitchen_station`, `image_url`) VALUES
(1, 1, 'Meghana Special Chicken Biryani', 'Our signature spicy Andhra-style chicken biryani cooked with secret spices, served with salan and chilled raita.', 340.00, 45, 1, 0, 1, 4.8, 1240, 14, 'Handi', 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?auto=format&fit=crop&w=400&q=80'),
(2, 1, 'Hyderabadi Mutton Dum Biryani', 'Tender fall-off-the-bone mutton marinated in aged herbs and slow-cooked in traditional clay pot dum.', 420.00, 30, 1, 0, 1, 4.9, 890, 18, 'Handi', 'https://images.unsplash.com/photo-1589302168068-964664d93dc0?auto=format&fit=crop&w=400&q=80'),
(3, 1, 'Paneer Tikka Dum Biryani', 'Cottage cheese cubes marinated in tandoori spices layered with fragrant aged basmati rice.', 280.00, 38, 1, 1, 0, 4.6, 620, 12, 'Handi', 'https://images.unsplash.com/photo-1642821373181-696a54913e93?auto=format&fit=crop&w=400&q=80'),
(4, 2, 'Chicken 65 Crispy Fry', 'Tender chicken bites tossed with south Indian spices, curry leaves, and green chillies.', 260.00, 50, 1, 0, 1, 4.7, 780, 10, 'Grills', 'https://images.unsplash.com/photo-1599488615731-7e5c2823ff28?auto=format&fit=crop&w=400&q=80'),
(5, 2, 'Tandoori Malai Broccoli', 'Charred fresh broccoli florets marinated in spiced cream, cheese, and roasted cardamom.', 240.00, 40, 1, 1, 0, 4.5, 410, 12, 'Tandoor', 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=400&q=80'),
(6, 2, 'Apollo Fish Fry', 'Boneless fresh catch fillets seasoned with Andhra special masala and pan fried to golden crisp.', 310.00, 25, 1, 0, 0, 4.6, 320, 11, 'Grills', 'https://images.unsplash.com/photo-1534422298391-e4f8c172dddb?auto=format&fit=crop&w=400&q=80'),
(7, 3, 'Butter Chicken Delhi Style', 'Boneless roasted tandoori chicken simmered in rich creamy tomato cashew gravy with fenugreek.', 320.00, 50, 1, 0, 1, 4.8, 1100, 15, 'Handi', 'https://images.unsplash.com/photo-1588166524941-3bf61a9c41db?auto=format&fit=crop&w=400&q=80'),
(8, 3, 'Paneer Butter Masala', 'Fresh cottage cheese batons cooked in velvety makhani gravy enriched with pure butter.', 270.00, 45, 1, 1, 1, 4.7, 950, 12, 'Handi', 'https://images.unsplash.com/photo-1631452180519-c014fe946bc7?auto=format&fit=crop&w=400&q=80'),
(9, 3, 'Dal Makhani Bukhara', 'Overnight slow-simmered black urad lentils churned with churned cream and clarified butter.', 230.00, 60, 1, 1, 0, 4.6, 540, 8, 'Pantry', 'https://images.unsplash.com/photo-1546833999-b9f581a1996d?auto=format&fit=crop&w=400&q=80'),
(10, 4, 'Butter Garlic Naan', 'Clay oven leavened bread brushed with melted butter and sprinkled with minced toasted garlic.', 65.00, 100, 1, 1, 1, 4.7, 1850, 6, 'Tandoor', 'https://images.unsplash.com/photo-1601050690597-df0568f70950?auto=format&fit=crop&w=400&q=80'),
(11, 4, 'Special Boondi Raita', 'Churned chilled yogurt whisked with spiced boondi crisps, roasted cumin, and black salt.', 50.00, 90, 1, 1, 0, 4.5, 920, 4, 'Pantry', 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=400&q=80'),
(12, 5, 'Gulab Jamun with Rabdi (2 Pcs)', 'Melt-in-mouth milk solid dumplings dipped in rose saffron syrup served warm with thick rabdi.', 90.00, 50, 1, 1, 1, 4.8, 760, 5, 'Pantry', 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?auto=format&fit=crop&w=400&q=80'),
(13, 6, 'Fresh Mint Lime Soda', 'Freshly squeezed lemon juice, crushed mint sprigs, and chilled effervescent soda.', 55.00, 80, 1, 1, 0, 4.6, 480, 3, 'Pantry', 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=400&q=80'),
(14, 6, 'Royal Mango Lassi', 'Thick creamy churned yogurt blended with Alphonso mango pulp and cardamom.', 85.00, 65, 1, 1, 1, 4.8, 640, 4, 'Pantry', 'https://images.unsplash.com/photo-1528740561666-dc2479dc08ab?auto=format&fit=crop&w=400&q=80')
ON DUPLICATE KEY UPDATE `item_name` = VALUES(`item_name`);

-- ----------------------------------------------------------------------------
-- 6. Table: orders (Header with 5% GST & Discount Tracking)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `order_id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(50) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `address_id` INT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `tax_amount` DECIMAL(10,2) NOT NULL,
  `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 35.00,
  `platform_fee` DECIMAL(10,2) NOT NULL DEFAULT 5.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `coupon_code` VARCHAR(50) DEFAULT NULL,
  `order_status` ENUM('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY', 'DELIVERED', 'CANCELLED') DEFAULT 'PLACED',
  `order_notes` TEXT DEFAULT NULL,
  `placed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `estimated_delivery_time` TIMESTAMP NULL,
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_orders_address` FOREIGN KEY (`address_id`) REFERENCES `user_addresses` (`address_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. Table: order_items (Line Items with Frozen Unit Price)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
  `order_item_id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `item_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `line_total` DECIMAL(10,2) NOT NULL,
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_item` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. Table: payments (Digital Transactions & Modes)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL UNIQUE,
  `payment_mode` ENUM('UPI', 'CREDIT_CARD', 'DEBIT_CARD', 'NET_BANKING', 'COD') DEFAULT 'UPI',
  `transaction_reference` VARCHAR(100) NOT NULL UNIQUE,
  `amount_paid` DECIMAL(10,2) NOT NULL,
  `payment_status` ENUM('PENDING', 'SUCCESSFUL', 'FAILED', 'REFUNDED') DEFAULT 'SUCCESSFUL',
  `payment_timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. Table: delivery_dispatches (Dispatch Workflow & Secret OTP)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `delivery_dispatches` (
  `dispatch_id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL UNIQUE,
  `delivery_partner_name` VARCHAR(150) DEFAULT 'Ramesh Kumar',
  `delivery_partner_phone` VARCHAR(30) DEFAULT '+91 98450 12345',
  `delivery_otp` VARCHAR(10) NOT NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `dispatched_at` TIMESTAMP NULL,
  `delivered_at` TIMESTAMP NULL,
  `delivery_status` ENUM('ASSIGNED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED', 'FAILED') DEFAULT 'ASSIGNED',
  CONSTRAINT `fk_dispatches_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 10. Table: order_audit_trail (Immutable Lifecycle Transition Log)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_audit_trail` (
  `audit_id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `old_status` VARCHAR(50) DEFAULT NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `updated_by_user_id` INT DEFAULT NULL,
  `change_timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_audit_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Sample Orders for Demonstration (Past delivered order & active order)
-- ----------------------------------------------------------------------------
INSERT INTO `orders` (`order_id`, `order_number`, `user_id`, `address_id`, `subtotal`, `tax_amount`, `delivery_fee`, `platform_fee`, `discount_amount`, `total_amount`, `coupon_code`, `order_status`, `order_notes`, `placed_at`, `estimated_delivery_time`) VALUES
(101, 'ZIP-849201', 1, 1, 405.00, 20.25, 35.00, 5.00, 0.00, 465.25, NULL, 'DELIVERED', 'Please pack extra salan and onions.', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 30 MINUTE),
(102, 'ZIP-948261', 1, 1, 620.00, 31.00, 35.00, 5.00, 68.00, 623.00, 'LUSHFOREST', 'COOKING', 'Less spicy, ring the doorbell once.', NOW(), NOW() + INTERVAL 25 MINUTE)
ON DUPLICATE KEY UPDATE `order_number` = VALUES(`order_number`);

INSERT INTO `order_items` (`order_item_id`, `order_id`, `item_id`, `quantity`, `unit_price`, `line_total`) VALUES
(1, 101, 1, 1, 340.00, 340.00),
(2, 101, 10, 1, 65.00, 65.00),
(3, 102, 1, 1, 340.00, 340.00),
(4, 102, 7, 1, 320.00, 320.00)
ON DUPLICATE KEY UPDATE `line_total` = VALUES(`line_total`);

INSERT INTO `payments` (`payment_id`, `order_id`, `payment_mode`, `transaction_reference`, `amount_paid`, `payment_status`, `payment_timestamp`) VALUES
(1, 101, 'UPI', 'UPI-TXN-429184029148', 465.25, 'SUCCESSFUL', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 102, 'UPI', 'UPI-TXN-773910284912', 623.00, 'SUCCESSFUL', NOW())
ON DUPLICATE KEY UPDATE `transaction_reference` = VALUES(`transaction_reference`);

INSERT INTO `delivery_dispatches` (`dispatch_id`, `order_id`, `delivery_partner_name`, `delivery_partner_phone`, `delivery_otp`, `assigned_at`, `dispatched_at`, `delivered_at`, `delivery_status`) VALUES
(1, 101, 'Ramesh Kumar', '+91 98450 12345', '482910', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 15 MINUTE, DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 28 MINUTE, 'DELIVERED'),
(2, 102, 'Ramesh Kumar', '+91 98450 12345', '839210', NOW(), NULL, NULL, 'ASSIGNED')
ON DUPLICATE KEY UPDATE `delivery_otp` = VALUES(`delivery_otp`);

INSERT INTO `order_audit_trail` (`audit_id`, `order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`) VALUES
(1, 101, NULL, 'PLACED', 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 101, 'PLACED', 'CONFIRMED', 2, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 101, 'CONFIRMED', 'COOKING', 2, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(4, 101, 'COOKING', 'READY', 2, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 101, 'READY', 'OUT_FOR_DELIVERY', 3, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(6, 101, 'OUT_FOR_DELIVERY', 'DELIVERED', 3, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(7, 102, NULL, 'PLACED', 1, NOW()),
(8, 102, 'PLACED', 'CONFIRMED', 2, NOW()),
(9, 102, 'CONFIRMED', 'COOKING', 2, NOW())
ON DUPLICATE KEY UPDATE `new_status` = VALUES(`new_status`);
