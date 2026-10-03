-- ============================================================================
-- ZIPPY ONLINE FOOD ORDERING PLATFORM - 3NF RELATIONAL SCHEMA
-- Course: MCSP-232 (MCA Major Project) | Student: Pooja JP
-- RDBMS: MySQL 8.0 / MariaDB (InnoDB Storage Engine)
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `zippy_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `zippy_db`;

-- Drop tables in reverse FK dependency order
DROP TABLE IF EXISTS `order_audit_trail`;
DROP TABLE IF EXISTS `delivery_dispatches`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `menu_items`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `user_addresses`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

-- ----------------------------------------------------------------------------
-- Table 1: ROLES (Role-Based Access Control)
-- ----------------------------------------------------------------------------
CREATE TABLE `roles` (
    `role_id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_name` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`role_id`, `role_name`, `description`) VALUES
(1, 'ADMIN', 'Full system access, catalog CRUD, reports, user RBAC'),
(2, 'CUSTOMER', 'End-user: food browsing, cart checkout, order tracking'),
(3, 'KITCHEN', 'Kitchen display system, WSJF prep queue, cooking status updates'),
(4, 'RIDER', 'Delivery partner: GPS routing, OTP verification, order fulfillment');

-- ----------------------------------------------------------------------------
-- Table 2: USERS (Argon2id/Bcrypt Authentication & Profiles)
-- ----------------------------------------------------------------------------
CREATE TABLE `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT NOT NULL DEFAULT 2,
    `full_name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone_number` VARCHAR(20) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 3: USER_ADDRESSES (3NF Normalization of Customer Locations)
-- ----------------------------------------------------------------------------
CREATE TABLE `user_addresses` (
    `address_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `address_type` ENUM('HOME', 'WORK', 'OTHER') NOT NULL DEFAULT 'HOME',
    `address_line` TEXT NOT NULL,
    `landmark` VARCHAR(150) NULL,
    `pincode` VARCHAR(10) NOT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 4: CATEGORIES (Menu Taxonomy)
-- ----------------------------------------------------------------------------
CREATE TABLE `categories` (
    `category_id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_name` VARCHAR(100) NOT NULL UNIQUE,
    `category_description` TEXT NULL,
    `category_slug` VARCHAR(100) NOT NULL UNIQUE,
    `image_url` VARCHAR(255) NULL,
    `display_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 5: MENU_ITEMS (Dishes, Inventory & Prep Parameters)
-- ----------------------------------------------------------------------------
CREATE TABLE `menu_items` (
    `item_id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `item_name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `stock_quantity` INT NOT NULL DEFAULT 50,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `is_veg` TINYINT(1) NOT NULL DEFAULT 1,
    `prep_time_minutes` INT NOT NULL DEFAULT 20,
    `kitchen_station` VARCHAR(50) NOT NULL DEFAULT 'Main Kitchen',
    `image_url` VARCHAR(500) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_menu_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 6: ORDERS (High-concurrency Transaction Header)
-- ----------------------------------------------------------------------------
CREATE TABLE `orders` (
    `order_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(50) NULL UNIQUE,
    `user_id` INT NOT NULL,
    `address_id` INT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `platform_fee` DECIMAL(10,2) NOT NULL DEFAULT 5.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `coupon_code` VARCHAR(50) NULL,
    `order_status` ENUM('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY', 'DELIVERED', 'CANCELLED') NOT NULL DEFAULT 'PLACED',
    `order_notes` TEXT NULL,
    `placed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `estimated_delivery_time` TIMESTAMP NULL,
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_orders_address` FOREIGN KEY (`address_id`) REFERENCES `user_addresses` (`address_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 7: ORDER_ITEMS (Normalized Line Items with Historical Pricing)
-- ----------------------------------------------------------------------------
CREATE TABLE `order_items` (
    `order_item_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `item_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `line_total` DECIMAL(10,2) NOT NULL,
    CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_items_dish` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 8: PAYMENTS (Decoupled Multi-stage Financial Settlement)
-- ----------------------------------------------------------------------------
CREATE TABLE `payments` (
    `payment_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL UNIQUE,
    `payment_mode` ENUM('UPI', 'CREDIT_CARD', 'DEBIT_CARD', 'NET_BANKING', 'COD') NOT NULL DEFAULT 'COD',
    `transaction_reference` VARCHAR(100) NOT NULL UNIQUE,
    `amount_paid` DECIMAL(10,2) NOT NULL,
    `payment_status` ENUM('PENDING', 'SUCCESSFUL', 'FAILED', 'REFUNDED') NOT NULL DEFAULT 'PENDING',
    `payment_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 9: DELIVERY_DISPATCHES (Courier Dispatch & Turn-by-Turn OTP)
-- ----------------------------------------------------------------------------
CREATE TABLE `delivery_dispatches` (
    `dispatch_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL UNIQUE,
    `delivery_partner_name` VARCHAR(120) NOT NULL DEFAULT 'Zippy Express Rider',
    `delivery_partner_phone` VARCHAR(20) NOT NULL DEFAULT '+91 98765 43210',
    `delivery_otp` VARCHAR(6) NOT NULL,
    `assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `dispatched_at` TIMESTAMP NULL,
    `delivered_at` TIMESTAMP NULL,
    `delivery_status` ENUM('ASSIGNED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED', 'FAILED') NOT NULL DEFAULT 'ASSIGNED',
    CONSTRAINT `fk_dispatches_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 10: ORDER_AUDIT_TRAIL (Audit Logging for Regulatory Compliance)
-- ----------------------------------------------------------------------------
CREATE TABLE `order_audit_trail` (
    `audit_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `old_status` VARCHAR(50) NULL,
    `new_status` VARCHAR(50) NOT NULL,
    `updated_by_user_id` INT NULL,
    `change_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_audit_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TRIGGERS FOR ACID INTEGRITY & AUTOMATION
-- ============================================================================

DELIMITER $$

-- Trigger 1: Automatically log status transitions in order_audit_trail
CREATE TRIGGER `trg_after_order_status_update`
AFTER UPDATE ON `orders`
FOR EACH ROW
BEGIN
    IF OLD.order_status <> NEW.order_status THEN
        INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`)
        VALUES (NEW.order_id, OLD.order_status, NEW.order_status, NEW.user_id, NOW());
    END IF;
END$$

-- Trigger 2: Automatically disable availability if stock hits 0
CREATE TRIGGER `trg_after_menu_stock_update`
BEFORE UPDATE ON `menu_items`
FOR EACH ROW
BEGIN
    IF NEW.stock_quantity <= 0 THEN
        SET NEW.is_available = 0;
        SET NEW.stock_quantity = 0;
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- SEED DATA (INITIAL DATA FOR DEMO & TESTING)
-- ============================================================================

-- Users: Passwords are encrypted (Default password for all demo accounts: 'password123')
-- Bcrypt hash for 'password123': $2y$10$wT8hO.P2O6u/E4kZ6b6Q.OKl7p8Z2b.qYVp8T8hO.P2O6u/E4kZ6b
-- Note: seed.php will dynamically hash using password_hash('password123', PASSWORD_DEFAULT)
INSERT INTO `users` (`user_id`, `role_id`, `full_name`, `email`, `phone_number`, `password_hash`, `is_active`) VALUES
(1, 1, 'Pooja JP (Admin)', 'admin@zippy.com', '+91 99001 12233', '$2y$10$K9W4e2H5OcbFv/3iY5rD0evZ8G1J.p9W6zL6d4H8r6X.j4b2sO0te', 1),
(2, 2, 'Pooja JP (Customer)', 'pooja@example.com', '+91 98860 12345', '$2y$10$K9W4e2H5OcbFv/3iY5rD0evZ8G1J.p9W6zL6d4H8r6X.j4b2sO0te', 1),
(3, 2, 'Rahul Sharma', 'rahul@example.com', '+91 98450 67890', '$2y$10$K9W4e2H5OcbFv/3iY5rD0evZ8G1J.p9W6zL6d4H8r6X.j4b2sO0te', 1),
(4, 3, 'Chef Sanjeev (Head Cook)', 'chef@zippy.com', '+91 97410 54321', '$2y$10$K9W4e2H5OcbFv/3iY5rD0evZ8G1J.p9W6zL6d4H8r6X.j4b2sO0te', 1),
(5, 4, 'Suresh Kumar (Rider)', 'rider@zippy.com', '+91 96320 98765', '$2y$10$K9W4e2H5OcbFv/3iY5rD0evZ8G1J.p9W6zL6d4H8r6X.j4b2sO0te', 1);

-- Customer Addresses
INSERT INTO `user_addresses` (`address_id`, `user_id`, `address_type`, `address_line`, `landmark`, `pincode`, `is_default`) VALUES
(1, 2, 'HOME', 'Flat 402, Green Glen Layout, Bellandur', 'Near Sobha Carnation', '560103', 1),
(2, 2, 'WORK', 'Prestige Tech Park, Outer Ring Road, Kadubeesanahalli', 'Opp. JP Morgan', '560087', 0),
(3, 3, 'HOME', 'House No 12, 100 Feet Road, Indiranagar', 'Near Metro Pillar 84', '560038', 1);

-- Categories
INSERT INTO `categories` (`category_id`, `category_name`, `category_description`, `category_slug`, `image_url`, `display_order`, `is_active`) VALUES
(1, 'Biryani & Rice', 'Aromatic dum biryanis, jeera rice and pulao cooked with authentic spices', 'biryani', 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?auto=format&fit=crop&w=240&q=80', 1, 1),
(2, 'North Indian Curries', 'Rich butter masala, paneer curries, dal makhani, and tandoori breads', 'north-indian', 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?auto=format&fit=crop&w=240&q=80', 2, 1),
(3, 'South Indian Specials', 'Crispy dosas, fluffy idlis, vada, and flavorful sambar & chutneys', 'south-indian', 'https://images.unsplash.com/photo-1668236543090-82eba5ee5976?auto=format&fit=crop&w=240&q=80', 3, 1),
(4, 'Rolls & Appetizers', 'Hot kathi rolls, crispy paneer tikka, chicken 65 and tandoori bites', 'rolls', 'https://images.unsplash.com/photo-1626777552726-4a6b54c97e46?auto=format&fit=crop&w=240&q=80', 4, 1),
(5, 'Burgers & Fast Food', 'Juicy grilled patties, crispy potato fries, cheesy nachos, and sliders', 'burgers', 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=240&q=80', 5, 1),
(6, 'Desserts & Sweets', 'Traditional gulab jamun, rasmalai, ice creams, and chocolate lava cake', 'desserts', 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?auto=format&fit=crop&w=240&q=80', 6, 1),
(7, 'Beverages & Shakes', 'Refreshing mango lassi, cold brew, fresh lime soda, and thick shakes', 'beverages', 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=240&q=80', 7, 1);

-- Menu Items
INSERT INTO `menu_items` (`item_id`, `category_id`, `item_name`, `description`, `price`, `stock_quantity`, `is_available`, `is_veg`, `prep_time_minutes`, `image_url`) VALUES
(1, 1, 'Meghana Special Chicken Biryani', 'Hyderabadi style slow-cooked spicy chicken layered with fragrant basmati rice, served with mirchi ka salan and onion raita.', 319.00, 45, 1, 0, 25, 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?auto=format&fit=crop&w=600&q=80'),
(2, 1, 'Paneer Tikka Dum Biryani', 'Chargrilled marinated cottage cheese cubes cooked in saffron infused dum rice with mint and brown onions.', 279.00, 30, 1, 1, 20, 'https://images.unsplash.com/photo-1633945274405-b6c8069047b0?auto=format&fit=crop&w=600&q=80'),
(3, 1, 'Mutton Dum Biryani (Lucknowi)', 'Tender prime mutton pieces marinated overnight in rich spices and sealed in handi.', 419.00, 20, 1, 0, 30, 'https://images.unsplash.com/photo-1589302168068-964664d93dc0?auto=format&fit=crop&w=600&q=80'),
(4, 2, 'Butter Chicken Delhi Style', 'Tandoori boneless chicken simmered in silky tomato gravy infused with fresh cream and kasoori methi.', 299.00, 35, 1, 0, 20, 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?auto=format&fit=crop&w=600&q=80'),
(5, 2, 'Paneer Butter Masala', 'Fresh malai paneer simmered in a mildly spiced, rich buttery gravy.', 249.00, 40, 1, 1, 15, 'https://images.unsplash.com/photo-1633945274405-b6c8069047b0?auto=format&fit=crop&w=600&q=80'),
(6, 2, 'Dal Makhani Slow Handi', 'Black lentils slow cooked overnight on charcoal with butter and cream.', 199.00, 50, 1, 1, 15, 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=600&q=80'),
(7, 2, 'Garlic Butter Naan (2 Pcs)', 'Clay oven tandoor bread garnished with chopped garlic and butter glaze.', 89.00, 80, 1, 1, 10, 'https://images.unsplash.com/photo-1513104890138-7c749659a591?auto=format&fit=crop&w=600&q=80'),
(8, 3, 'Mysore Masala Dosa', 'Golden crisp fermented crepe layered with spicy red chili-garlic chutney and aloo bhaji.', 139.00, 40, 1, 1, 12, 'https://images.unsplash.com/photo-1668236543090-82eba5ee5976?auto=format&fit=crop&w=600&q=80'),
(9, 3, 'Steamed Ghee Podi Idli (4 Pcs)', 'Mini soft rice cakes tossed in aromatic gunpowder spices and pure melted ghee.', 119.00, 35, 1, 1, 10, 'https://images.unsplash.com/photo-1589302168068-964664d93dc0?auto=format&fit=crop&w=600&q=80'),
(10, 4, 'Double Egg Chicken Kathi Roll', 'Flaky paratha loaded with spiced juicy chicken chunks, beaten eggs, onions, and tangy mint mayo.', 179.00, 30, 1, 0, 15, 'https://images.unsplash.com/photo-1626777552726-4a6b54c97e46?auto=format&fit=crop&w=600&q=80'),
(11, 4, 'Crispy Paneer Tikka Roll', 'Smoked cottage cheese cubes with bell peppers wrapped in rumali roti.', 159.00, 25, 1, 1, 15, 'https://images.unsplash.com/photo-1550547660-d9450f859349?auto=format&fit=crop&w=600&q=80'),
(12, 5, 'Lush Forest Gourmet Burger', 'Double crispy patty, aged cheddar slice, caramelised onions and mint-herb mayo on brioche.', 229.00, 25, 1, 1, 15, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=600&q=80'),
(13, 5, 'Crispy Fried Chicken Burger', 'Buttermilk spiced chicken breast, pickled cucumber and chipotle mayo.', 249.00, 20, 1, 0, 18, 'https://images.unsplash.com/photo-1550547660-d9450f859349?auto=format&fit=crop&w=600&q=80'),
(14, 6, 'Gulab Jamun with Rabri (2 Pcs)', 'Warm khoya dumplings steeped in rose cardamom syrup served with chilled rabri.', 129.00, 50, 1, 1, 8, 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?auto=format&fit=crop&w=600&q=80'),
(15, 6, 'Royal Kesar Rasmalai (2 Pcs)', 'Delicate cottage cheese discs soaked in saffron milk and pistachio flakes.', 139.00, 40, 1, 1, 8, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?auto=format&fit=crop&w=600&q=80'),
(16, 7, 'Fresh Alphonso Mango Lassi', 'Thick creamy yogurt blended with real Alphonso mango pulp and cardamom.', 99.00, 60, 1, 1, 5, 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=600&q=80'),
(17, 7, 'Mint Lime Cooler Soda', 'Crushed fresh garden mint, lime juice, black salt, and sparkling soda.', 69.00, 70, 1, 1, 5, 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=600&q=80');

-- Sample Orders
INSERT INTO `orders` (`order_id`, `user_id`, `address_id`, `subtotal`, `tax_amount`, `delivery_fee`, `discount_amount`, `total_amount`, `order_status`, `order_notes`, `placed_at`, `estimated_delivery_time`) VALUES
(1001, 2, 1, 608.00, 30.40, 40.00, 50.00, 628.40, 'DELIVERED', 'Please leave with security if unreachable', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 35 MINUTE),
(1002, 2, 1, 508.00, 25.40, 0.00, 0.00, 533.40, 'OUT_FOR_DELIVERY', 'Extra green chutney please', DATE_SUB(NOW(), INTERVAL 25 MINUTE), NOW() + INTERVAL 15 MINUTE),
(1003, 3, 3, 319.00, 15.95, 40.00, 0.00, 374.95, 'COOKING', 'Less spicy', DATE_SUB(NOW(), INTERVAL 15 MINUTE), NOW() + INTERVAL 25 MINUTE),
(1004, 2, 2, 388.00, 19.40, 40.00, 40.00, 407.40, 'CONFIRMED', 'Ring doorbell twice', DATE_SUB(NOW(), INTERVAL 5 MINUTE), NOW() + INTERVAL 30 MINUTE);

-- Order Items
INSERT INTO `order_items` (`order_item_id`, `order_id`, `item_id`, `quantity`, `unit_price`, `line_total`) VALUES
(1, 1001, 1, 1, 319.00, 319.00),
(2, 1001, 7, 2, 89.00, 178.00),
(3, 1001, 14, 1, 129.00, 129.00),
(4, 1002, 4, 1, 299.00, 299.00),
(5, 1002, 7, 1, 89.00, 89.00),
(6, 1002, 16, 2, 99.00, 198.00),
(7, 1003, 1, 1, 319.00, 319.00),
(8, 1004, 8, 2, 139.00, 278.00),
(9, 1004, 9, 1, 119.00, 119.00);

-- Payments
INSERT INTO `payments` (`payment_id`, `order_id`, `payment_mode`, `transaction_reference`, `amount_paid`, `payment_status`, `payment_timestamp`) VALUES
(1, 1001, 'UPI', 'UPI-982498234-AXIS', 628.40, 'SUCCESSFUL', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 1002, 'UPI', 'UPI-472910482-HDFC', 533.40, 'SUCCESSFUL', DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
(3, 1003, 'CREDIT_CARD', 'CC-VISA-88219401', 374.95, 'SUCCESSFUL', DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
(4, 1004, 'COD', 'COD-PENDING-1004', 407.40, 'PENDING', DATE_SUB(NOW(), INTERVAL 5 MINUTE));

-- Delivery Dispatches
INSERT INTO `delivery_dispatches` (`dispatch_id`, `order_id`, `delivery_partner_name`, `delivery_partner_phone`, `delivery_otp`, `assigned_at`, `dispatched_at`, `delivered_at`, `delivery_status`) VALUES
(1, 1001, 'Suresh Kumar', '+91 96320 98765', '482194', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 20 MINUTE, DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 35 MINUTE, 'DELIVERED'),
(2, 1002, 'Suresh Kumar', '+91 96320 98765', '852914', DATE_SUB(NOW(), INTERVAL 20 MINUTE), DATE_SUB(NOW(), INTERVAL 10 MINUTE), NULL, 'IN_TRANSIT'),
(3, 1003, 'Ramesh Patil', '+91 98450 11223', '391054', DATE_SUB(NOW(), INTERVAL 10 MINUTE), NULL, NULL, 'ASSIGNED'),
(4, 1004, 'Ramesh Patil', '+91 98450 11223', '610482', DATE_SUB(NOW(), INTERVAL 2 MINUTE), NULL, NULL, 'ASSIGNED');

-- Audit Trail
INSERT INTO `order_audit_trail` (`audit_id`, `order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`) VALUES
(1, 1001, 'PLACED', 'CONFIRMED', 1, DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 2 MINUTE),
(2, 1001, 'CONFIRMED', 'COOKING', 4, DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 5 MINUTE),
(3, 1001, 'COOKING', 'READY', 4, DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 18 MINUTE),
(4, 1001, 'READY', 'OUT_FOR_DELIVERY', 5, DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 20 MINUTE),
(5, 1001, 'OUT_FOR_DELIVERY', 'DELIVERED', 5, DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 35 MINUTE),
(6, 1002, 'PLACED', 'CONFIRMED', 1, DATE_SUB(NOW(), INTERVAL 24 MINUTE)),
(7, 1002, 'CONFIRMED', 'COOKING', 4, DATE_SUB(NOW(), INTERVAL 20 MINUTE)),
(8, 1002, 'COOKING', 'READY', 4, DATE_SUB(NOW(), INTERVAL 12 MINUTE)),
(9, 1002, 'READY', 'OUT_FOR_DELIVERY', 5, DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
(10, 1003, 'PLACED', 'CONFIRMED', 1, DATE_SUB(NOW(), INTERVAL 14 MINUTE)),
(11, 1003, 'CONFIRMED', 'COOKING', 4, DATE_SUB(NOW(), INTERVAL 12 MINUTE)),
(12, 1004, 'PLACED', 'CONFIRMED', 1, DATE_SUB(NOW(), INTERVAL 4 MINUTE));
