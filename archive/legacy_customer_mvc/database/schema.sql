-- =============================================================================
-- ZIPPY FOOD ORDERING PLATFORM - DATABASE SCHEMA (3NF NORMALIZED)
-- Course: MCSP-232 (MCA Major Project, IGNOU) | Student: Pooja JP
-- Engine: MySQL 8.0 InnoDB with Referential Integrity, Triggers & Procedures
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `zippy_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `zippy_db`;

SET FOREIGN_KEY_CHECKS = 0;
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
SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- 1. ROLES TABLE (RBAC)
-- -----------------------------------------------------------------------------
CREATE TABLE `roles` (
    `role_id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_name` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 2. USERS TABLE
-- -----------------------------------------------------------------------------
CREATE TABLE `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT NOT NULL DEFAULT 1,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone_number` VARCHAR(20) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 3. USER ADDRESSES TABLE (3NF Normalization - Multiple Addresses per User)
-- -----------------------------------------------------------------------------
CREATE TABLE `user_addresses` (
    `address_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `address_type` ENUM('HOME', 'WORK', 'OTHER') NOT NULL DEFAULT 'HOME',
    `address_line` TEXT NOT NULL,
    `landmark` VARCHAR(150) NULL,
    `pincode` VARCHAR(10) NOT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 4. CATEGORIES TABLE
-- -----------------------------------------------------------------------------
CREATE TABLE `categories` (
    `category_id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_name` VARCHAR(100) NOT NULL UNIQUE,
    `category_description` TEXT NULL,
    `category_slug` VARCHAR(100) NOT NULL UNIQUE,
    `icon_class` VARCHAR(50) DEFAULT 'fa-solid fa-utensils',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `display_order` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 5. MENU ITEMS TABLE
-- -----------------------------------------------------------------------------
CREATE TABLE `menu_items` (
    `item_id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `item_name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `stock_quantity` INT NOT NULL DEFAULT 100,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `is_veg` TINYINT(1) NOT NULL DEFAULT 0,
    `prep_time_minutes` INT NOT NULL DEFAULT 20,
    `image_url` VARCHAR(255) NULL,
    `rating` DECIMAL(2,1) NOT NULL DEFAULT 4.5,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_item_price` CHECK (`price` > 0),
    CONSTRAINT `chk_item_stock` CHECK (`stock_quantity` >= 0),
    CONSTRAINT `fk_menu_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 6. ORDERS TABLE
-- -----------------------------------------------------------------------------
CREATE TABLE `orders` (
    `order_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `address_id` INT NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 40.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `order_status` ENUM('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY', 'DELIVERED', 'CANCELLED') NOT NULL DEFAULT 'PLACED',
    `order_notes` TEXT NULL,
    `placed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `estimated_delivery_time` TIMESTAMP NULL,
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
    CONSTRAINT `fk_orders_address` FOREIGN KEY (`address_id`) REFERENCES `user_addresses` (`address_id`)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 7. ORDER ITEMS TABLE (Line Items Junction)
-- -----------------------------------------------------------------------------
CREATE TABLE `order_items` (
    `order_item_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `item_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `line_total` DECIMAL(10,2) NOT NULL,
    CONSTRAINT `chk_item_qty` CHECK (`quantity` > 0),
    CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_items_menu` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 8. PAYMENTS TABLE
-- -----------------------------------------------------------------------------
CREATE TABLE `payments` (
    `payment_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL UNIQUE,
    `payment_mode` ENUM('UPI', 'CREDIT_CARD', 'DEBIT_CARD', 'NET_BANKING', 'COD') NOT NULL,
    `transaction_reference` VARCHAR(100) NOT NULL UNIQUE,
    `amount_paid` DECIMAL(10,2) NOT NULL,
    `payment_status` ENUM('PENDING', 'SUCCESSFUL', 'FAILED', 'REFUNDED') NOT NULL DEFAULT 'PENDING',
    `payment_timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 9. DELIVERY DISPATCHES TABLE
-- -----------------------------------------------------------------------------
CREATE TABLE `delivery_dispatches` (
    `dispatch_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL UNIQUE,
    `delivery_partner_name` VARCHAR(100) NOT NULL,
    `delivery_partner_phone` VARCHAR(20) NOT NULL,
    `delivery_otp` VARCHAR(6) NOT NULL,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `dispatched_at` TIMESTAMP NULL,
    `delivered_at` TIMESTAMP NULL,
    `delivery_status` ENUM('ASSIGNED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED', 'FAILED') NOT NULL DEFAULT 'ASSIGNED',
    CONSTRAINT `fk_dispatches_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 10. ORDER AUDIT TRAIL TABLE (Immutable Audit Log)
-- -----------------------------------------------------------------------------
CREATE TABLE `order_audit_trail` (
    `audit_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `old_status` VARCHAR(50) NULL,
    `new_status` VARCHAR(50) NOT NULL,
    `updated_by_user_id` INT NULL,
    `change_timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_audit_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- TRIGGERS
-- -----------------------------------------------------------------------------
DELIMITER //

-- Trigger: Audit log on order status change
CREATE TRIGGER `trg_order_status_audit`
AFTER UPDATE ON `orders`
FOR EACH ROW
BEGIN
    IF OLD.order_status <> NEW.order_status THEN
        INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`)
        VALUES (NEW.order_id, OLD.order_status, NEW.order_status, NEW.user_id);
    END IF;
END //

-- Trigger: Mark dish unavailable when stock hits zero
CREATE TRIGGER `trg_menu_stock_out`
BEFORE UPDATE ON `menu_items`
FOR EACH ROW
BEGIN
    IF NEW.stock_quantity <= 0 THEN
        SET NEW.is_available = 0;
    ELSE
        SET NEW.is_available = 1;
    END IF;
END //

DELIMITER ;
