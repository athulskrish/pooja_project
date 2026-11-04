-- ============================================================================
-- ZIPPY ONLINE FOOD ORDERING PLATFORM - 3NF RELATIONAL SCHEMA
-- Course: MCSP-232 (MCA Major Project) | Student: Pooja JP
-- RDBMS: MySQL 8.0 / MariaDB (InnoDB Storage Engine)
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `zippy_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `zippy_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables in reverse FK dependency order
DROP TABLE IF EXISTS `staff_salary_payouts`;
DROP TABLE IF EXISTS `staff_profiles`;
DROP TABLE IF EXISTS `cash_register_closures`;
DROP TABLE IF EXISTS `money_transactions`;
DROP TABLE IF EXISTS `dish_reviews`;
DROP TABLE IF EXISTS `order_reviews`;
DROP TABLE IF EXISTS `support_tickets`;
DROP TABLE IF EXISTS `faqs`;
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
    `price` DECIMAL(10,2) NOT NULL CHECK (`price` > 0),
    `stock_quantity` INT NOT NULL DEFAULT 50,
    `rating` DECIMAL(3,1) NOT NULL DEFAULT 4.5,
    `total_reviews` INT NOT NULL DEFAULT 120,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `is_veg` TINYINT(1) NOT NULL DEFAULT 1,
    `is_healthy` TINYINT(1) NOT NULL DEFAULT 0,
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
    `quantity` INT NOT NULL DEFAULT 1 CHECK (`quantity` > 0),
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
    `rider_id` INT NULL,
    `delivery_partner_name` VARCHAR(120) NOT NULL DEFAULT 'Zippy Express Rider',
    `delivery_partner_phone` VARCHAR(20) NOT NULL DEFAULT '+91 98765 43210',
    `delivery_otp` VARCHAR(6) NOT NULL,
    `current_latitude` DECIMAL(10, 8) NULL DEFAULT 12.97840000,
    `current_longitude` DECIMAL(11, 8) NULL DEFAULT 77.64080000,
    `delivery_latitude` DECIMAL(10, 8) NULL DEFAULT 12.96980000,
    `delivery_longitude` DECIMAL(11, 8) NULL DEFAULT 77.64990000,
    `route_progress` INT NOT NULL DEFAULT 0,
    `distance_remaining_km` DECIMAL(5, 2) NOT NULL DEFAULT 2.40,
    `estimated_minutes_remaining` INT NOT NULL DEFAULT 15,
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

-- ----------------------------------------------------------------------------
-- Table 11: ORDER_REVIEWS (Customer Feedback & Courier Ratings)
-- ----------------------------------------------------------------------------
CREATE TABLE `order_reviews` (
    `review_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL UNIQUE,
    `user_id` INT NOT NULL,
    `food_rating` INT NOT NULL CHECK (`food_rating` BETWEEN 1 AND 5),
    `rider_rating` INT NOT NULL CHECK (`rider_rating` BETWEEN 1 AND 5),
    `rider_tip` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `comments` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reviews_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 12: DISH_REVIEWS (Customer Food Dish Ratings & Reviews)
-- ----------------------------------------------------------------------------
CREATE TABLE `dish_reviews` (
    `dish_review_id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_id` INT NOT NULL,
    `order_id` INT NULL,
    `user_id` INT NOT NULL,
    `rating` INT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
    `review_text` TEXT NULL,
    `tags` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_dish_reviews_item` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dish_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 12: FAQS (Dynamic Help Desk & Customer Knowledge Base)
-- ----------------------------------------------------------------------------
CREATE TABLE `faqs` (
    `faq_id` INT AUTO_INCREMENT PRIMARY KEY,
    `question` VARCHAR(255) NOT NULL,
    `answer` TEXT NOT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'General',
    `display_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 13: SUPPORT_TICKETS (Customer Help Desk & Inquiry Tracking - UC-11)
-- ----------------------------------------------------------------------------
CREATE TABLE `support_tickets` (
    `ticket_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `customer_name` VARCHAR(120) NOT NULL,
    `customer_email` VARCHAR(150) NOT NULL,
    `subject` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `ticket_status` ENUM('OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED') NOT NULL DEFAULT 'OPEN',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tickets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 14: STAFF_PROFILES (Kitchen Chefs & Delivery Rider Roster)
-- ----------------------------------------------------------------------------
CREATE TABLE `staff_profiles` (
    `profile_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `department` ENUM('KITCHEN', 'DELIVERY') NOT NULL,
    `designation` VARCHAR(100) NOT NULL,
    `specialization` VARCHAR(150) NULL,
    `duty_status` ENUM('ON_DUTY', 'ON_BREAK', 'OFF_DUTY', 'ON_LEAVE') NOT NULL DEFAULT 'ON_DUTY',
    `shift_timings` VARCHAR(50) NOT NULL DEFAULT '09:00 AM - 05:00 PM',
    `vehicle_details` VARCHAR(100) NULL,
    `driving_license` VARCHAR(50) NULL,
    `base_salary` DECIMAL(10,2) NOT NULL DEFAULT 25000.00,
    `hourly_or_order_rate` DECIMAL(10,2) NOT NULL DEFAULT 40.00,
    `joined_date` DATE NOT NULL,
    `cod_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_orders_handled` INT NOT NULL DEFAULT 0,
    `rating` DECIMAL(3,2) NOT NULL DEFAULT 4.80,
    CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 15: STAFF_SALARY_PAYOUTS (Monthly Disbursements & Digital Payslips)
-- ----------------------------------------------------------------------------
CREATE TABLE `staff_salary_payouts` (
    `payout_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `month_year` VARCHAR(30) NOT NULL,
    `base_amount` DECIMAL(10,2) NOT NULL,
    `incentives_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `deductions_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `net_paid` DECIMAL(10,2) NOT NULL,
    `payment_mode` ENUM('BANK_TRANSFER', 'UPI', 'CASH') NOT NULL DEFAULT 'BANK_TRANSFER',
    `transaction_reference` VARCHAR(100) NOT NULL,
    `payout_status` ENUM('PAID', 'PENDING', 'PROCESSING') NOT NULL DEFAULT 'PAID',
    `paid_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT NULL,
    CONSTRAINT `fk_salary_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 16: MONEY_TRANSACTIONS (Double-Entry Financial Ledger & Audit Trail)
-- ----------------------------------------------------------------------------
CREATE TABLE `money_transactions` (
    `txn_id` INT AUTO_INCREMENT PRIMARY KEY,
    `txn_number` VARCHAR(50) NOT NULL UNIQUE,
    `order_id` INT NULL,
    `user_id` INT NULL,
    `txn_category` ENUM('CUSTOMER_PAYMENT_ONLINE', 'CUSTOMER_PAYMENT_COD', 'COD_DEPOSIT_STORE', 'CUSTOMER_REFUND', 'STAFF_SALARY_PAYOUT') NOT NULL,
    `flow_direction` ENUM('INWARD', 'OUTWARD') NOT NULL,
    `payment_method` ENUM('UPI', 'CREDIT_CARD', 'DEBIT_CARD', 'NET_BANKING', 'COD_CASH', 'BANK_TRANSFER') NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `status` ENUM('COMPLETED', 'PENDING', 'SETTLED', 'REFUNDED') NOT NULL DEFAULT 'COMPLETED',
    `reference_id` VARCHAR(100) NULL,
    `handled_by` VARCHAR(120) NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_money_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_money_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 17: CASH_REGISTER_CLOSURES (Daily EOD Z-Reports & Cash Drawer Balances)
-- ----------------------------------------------------------------------------
CREATE TABLE `cash_register_closures` (
    `closure_id` INT AUTO_INCREMENT PRIMARY KEY,
    `closure_date` DATE NOT NULL,
    `closing_admin_id` INT NULL,
    `closing_admin_name` VARCHAR(120) NOT NULL,
    `total_orders_count` INT NOT NULL DEFAULT 0,
    `gross_sales` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `online_payments_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `expected_cod_cash` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `actual_physical_cash` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discrepancy_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discrepancy_reason` TEXT NULL,
    `status` ENUM('SEALED', 'OPEN', 'FLAGGED') NOT NULL DEFAULT 'SEALED',
    `closed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
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

-- ============================================================================
-- STORED PROCEDURES (ENTERPRISE RDBMS RIGOR)
-- ============================================================================

-- Stored Procedure 1: sp_GenerateDailySalesReport
-- Aggregates daily financial KPIs, GST collection, and top 5 selling dishes
DROP PROCEDURE IF EXISTS `sp_GenerateDailySalesReport`$$
CREATE PROCEDURE `sp_GenerateDailySalesReport`(IN p_report_date DATE)
BEGIN
    DECLARE target_date DATE;
    SET target_date = COALESCE(p_report_date, CURDATE());

    -- Summary KPI Metrics for the target date
    SELECT 
        target_date AS report_date,
        COUNT(order_id) AS total_orders,
        COALESCE(SUM(CASE WHEN order_status = 'DELIVERED' THEN 1 ELSE 0 END), 0) AS delivered_orders,
        COALESCE(SUM(CASE WHEN order_status = 'CANCELLED' THEN 1 ELSE 0 END), 0) AS cancelled_orders,
        COALESCE(SUM(CASE WHEN order_status NOT IN ('CANCELLED') THEN subtotal ELSE 0.00 END), 0.00) AS net_food_sales,
        COALESCE(SUM(CASE WHEN order_status NOT IN ('CANCELLED') THEN tax_amount ELSE 0.00 END), 0.00) AS total_gst_collected,
        COALESCE(SUM(CASE WHEN order_status NOT IN ('CANCELLED') THEN delivery_fee ELSE 0.00 END), 0.00) AS total_delivery_fees,
        COALESCE(SUM(CASE WHEN order_status NOT IN ('CANCELLED') THEN discount_amount ELSE 0.00 END), 0.00) AS total_discounts_given,
        COALESCE(SUM(CASE WHEN order_status NOT IN ('CANCELLED') THEN total_amount ELSE 0.00 END), 0.00) AS gross_revenue,
        ROUND(COALESCE(AVG(CASE WHEN order_status NOT IN ('CANCELLED') THEN total_amount ELSE NULL END), 0.00), 2) AS average_order_value
    FROM `orders`
    WHERE DATE(placed_at) = target_date;

    -- Top 5 Selling Dishes for the target date
    SELECT 
        m.item_id,
        m.item_name,
        c.category_name,
        SUM(oi.quantity) AS units_sold,
        SUM(oi.line_total) AS item_revenue
    FROM `order_items` oi
    JOIN `orders` o ON oi.order_id = o.order_id
    JOIN `menu_items` m ON oi.item_id = m.item_id
    JOIN `categories` c ON m.category_id = c.category_id
    WHERE DATE(o.placed_at) = target_date
      AND o.order_status NOT IN ('CANCELLED')
    GROUP BY m.item_id, m.item_name, c.category_name
    ORDER BY units_sold DESC
    LIMIT 5;
END$$

-- Stored Procedure 2: sp_ProcessOrderCheckout
-- Atomically creates order header, generates 6-digit delivery OTP,
-- creates delivery dispatch ticket, payment record, and logs to audit trail
DROP PROCEDURE IF EXISTS `sp_ProcessOrderCheckout`$$
CREATE PROCEDURE `sp_ProcessOrderCheckout`(
    IN p_user_id INT,
    IN p_address_id INT,
    IN p_subtotal DECIMAL(10,2),
    IN p_tax_amount DECIMAL(10,2),
    IN p_delivery_fee DECIMAL(10,2),
    IN p_platform_fee DECIMAL(10,2),
    IN p_discount_amount DECIMAL(10,2),
    IN p_total_amount DECIMAL(10,2),
    IN p_coupon_code VARCHAR(50),
    IN p_order_notes TEXT,
    IN p_payment_mode VARCHAR(20),
    IN p_tx_ref VARCHAR(100),
    IN p_eta_minutes INT,
    OUT p_order_id INT,
    OUT p_order_number VARCHAR(50),
    OUT p_delivery_otp VARCHAR(6),
    OUT p_status_message VARCHAR(255)
)
proc_label: BEGIN
    DECLARE v_order_id INT;
    DECLARE v_otp VARCHAR(6);
    DECLARE v_pay_status VARCHAR(20);
    DECLARE v_order_num VARCHAR(50);

    -- Rollback on any SQL exception
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_order_id = 0;
        SET p_order_number = '';
        SET p_delivery_otp = '';
        SET p_status_message = 'ERROR: Database transaction rolled back due to SQL exception';
    END;

    START TRANSACTION;

    -- Validate user
    IF NOT EXISTS (SELECT 1 FROM `users` WHERE `user_id` = p_user_id) THEN
        SET p_order_id = 0;
        SET p_order_number = '';
        SET p_delivery_otp = '';
        SET p_status_message = 'ERROR: User ID does not exist';
        ROLLBACK;
        LEAVE proc_label;
    END IF;

    -- Generate random 6-digit cryptographic verification OTP
    SET v_otp = LPAD(FLOOR(RAND() * 900000 + 100000), 6, '0');

    -- Insert order header
    INSERT INTO `orders` (
        `user_id`, `address_id`, `subtotal`, `tax_amount`,
        `delivery_fee`, `platform_fee`, `discount_amount`, `total_amount`,
        `coupon_code`, `order_status`, `order_notes`, `placed_at`, `estimated_delivery_time`
    ) VALUES (
        p_user_id, p_address_id, p_subtotal, p_tax_amount,
        p_delivery_fee, p_platform_fee, p_discount_amount, p_total_amount,
        p_coupon_code, 'CONFIRMED', p_order_notes, NOW(), DATE_ADD(NOW(), INTERVAL COALESCE(p_eta_minutes, 30) MINUTE)
    );

    SET v_order_id = LAST_INSERT_ID();
    SET v_order_num = CONCAT('#ZIP-', v_order_id);

    UPDATE `orders` SET `order_number` = v_order_num WHERE `order_id` = v_order_id;

    -- Set payment status based on mode
    IF p_payment_mode = 'COD' THEN
        SET v_pay_status = 'PENDING';
    ELSE
        SET v_pay_status = 'SUCCESSFUL';
    END IF;

    -- Insert payment record
    INSERT INTO `payments` (
        `order_id`, `payment_mode`, `transaction_reference`,
        `amount_paid`, `payment_status`, `payment_timestamp`
    ) VALUES (
        v_order_id, p_payment_mode, COALESCE(p_tx_ref, CONCAT('TXN-', v_order_id, '-', UNIX_TIMESTAMP())),
        p_total_amount, v_pay_status, NOW()
    );

    -- Insert delivery dispatch ticket
    INSERT INTO `delivery_dispatches` (
        `order_id`, `delivery_partner_name`, `delivery_partner_phone`,
        `delivery_otp`, `assigned_at`, `delivery_status`
    ) VALUES (
        v_order_id, 'Shaji (Zippy Express)', '+91 96320 98765',
        v_otp, NOW(), 'ASSIGNED'
    );

    -- Record in audit trail
    INSERT INTO `order_audit_trail` (
        `order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`
    ) VALUES (
        v_order_id, 'PLACED', 'CONFIRMED', p_user_id, NOW()
    );

    COMMIT;

    SET p_order_id = v_order_id;
    SET p_order_number = v_order_num;
    SET p_delivery_otp = v_otp;
    SET p_status_message = 'SUCCESS: Order placed and confirmed atomically';
END$$

DELIMITER ;

-- ============================================================================
-- SEED DATA (INITIAL DATA FOR DEMO & TESTING)
-- ============================================================================

-- Users: Passwords are encrypted (Default password for all demo accounts: 'password123')
-- Bcrypt hash for 'password123': $2y$10$wT8hO.P2O6u/E4kZ6b6Q.OKl7p8Z2b.qYVp8T8hO.P2O6u/E4kZ6b
-- Note: seed.php will dynamically hash using password_hash('password123', PASSWORD_DEFAULT)
INSERT INTO `users` (`user_id`, `role_id`, `full_name`, `email`, `phone_number`, `password_hash`, `is_active`) VALUES
(1, 1, 'Pooja (Admin)', 'admin@zippy.com', '+91 99001 12233', '$2y$10$K9W4e2H5OcbFv/3iY5rD0evZ8G1J.p9W6zL6d4H8r6X.j4b2sO0te', 1),
(2, 2, 'Athul (Customer)', 'athul@example.com', '+91 98860 12345', '$2y$10$K9W4e2H5OcbFv/3iY5rD0evZ8G1J.p9W6zL6d4H8r6X.j4b2sO0te', 1),
(3, 2, 'Amal (Customer)', 'amal@example.com', '+91 98450 67890', '$2y$10$K9W4e2H5OcbFv/3iY5rD0evZ8G1J.p9W6zL6d4H8r6X.j4b2sO0te', 1),
(4, 2, 'Abhijith (Customer)', 'abhijith@example.com', '+91 98450 99887', '$2y$10$K9W4e2H5OcbFv/3iY5rD0evZ8G1J.p9W6zL6d4H8r6X.j4b2sO0te', 1),
(5, 3, 'Chef Sajeev (Head Cook)', 'chef@zippy.com', '+91 97410 54321', '$2y$10$K9W4e2H5OcbFv/3iY5rD0evZ8G1J.p9W6zL6d4H8r6X.j4b2sO0te', 1),
(6, 4, 'Shaji (Rider)', 'shaji@zippy.com', '+91 96320 98765', '$2y$10$K9W4e2H5OcbFv/3iY5rD0evZ8G1J.p9W6zL6d4H8r6X.j4b2sO0te', 1),
(7, 4, 'Kichu (Rider)', 'kichu@zippy.com', '+91 96320 11223', '$2y$10$K9W4e2H5OcbFv/3iY5rD0evZ8G1J.p9W6zL6d4H8r6X.j4b2sO0te', 1),
(8, 4, 'Abhi (Rider)', 'abhi@zippy.com', '+91 96320 33445', '$2y$10$K9W4e2H5OcbFv/3iY5rD0evZ8G1J.p9W6zL6d4H8r6X.j4b2sO0te', 1);

-- Customer Addresses
INSERT INTO `user_addresses` (`address_id`, `user_id`, `address_type`, `address_line`, `landmark`, `pincode`, `is_default`) VALUES
(1, 2, 'HOME', 'Flat 402, Green Glen Layout, Bellandur', 'Near Sobha Carnation', '560103', 1),
(2, 2, 'WORK', 'Prestige Tech Park, Outer Ring Road, Kadubeesanahalli', 'Opp. JP Morgan', '560087', 0),
(3, 3, 'HOME', 'House No 12, 100 Feet Road, Indiranagar', 'Near Metro Pillar 84', '560038', 1),
(4, 3, 'WORK', 'Embassy GolfLinks Business Park, Domlur', 'Intermediate Ring Road', '560071', 0),
(5, 4, 'HOME', 'Villa 45, Palm Meadows, Whitefield', 'Near Forum Mall', '560066', 1),
(6, 4, 'WORK', 'RMZ Ecoworld, Campus 3, Bellandur', 'Outer Ring Road', '560103', 0);

-- Categories
INSERT INTO `categories` (`category_id`, `category_name`, `category_description`, `category_slug`, `image_url`, `display_order`, `is_active`) VALUES
(1, 'Biryani & Rice', 'Aromatic dum biryanis, jeera rice and pulao cooked with authentic spices', 'biryani', 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?auto=format&fit=crop&w=240&q=80', 1, 1),
(2, 'North Indian Curries', 'Rich butter masala, paneer curries, dal makhani, and tandoori breads', 'north-indian', 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?auto=format&fit=crop&w=240&q=80', 2, 1),
(3, 'South Indian Specials', 'Crispy dosas, fluffy idlis, vada, and flavorful sambar & chutneys', 'south-indian', 'https://images.unsplash.com/photo-1668236543090-82eba5ee5976?auto=format&fit=crop&w=240&q=80', 3, 1),
(4, 'Healthy & Fit', 'Nutrient-dense superfood bowls, fresh organic salads, high-protein keto & detox meals', 'healthy', 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=240&q=80', 4, 1),
(5, 'Rolls & Appetizers', 'Hot kathi rolls, crispy paneer tikka, chicken 65 and tandoori bites', 'rolls', 'https://images.unsplash.com/photo-1626777552726-4a6b54c97e46?auto=format&fit=crop&w=240&q=80', 5, 1),
(6, 'Burgers & Fast Food', 'Juicy grilled patties, crispy potato fries, cheesy nachos, and sliders', 'burgers', 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=240&q=80', 6, 1),
(7, 'Desserts & Sweets', 'Traditional gulab jamun, rasmalai, ice creams, and chocolate lava cake', 'desserts', 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?auto=format&fit=crop&w=240&q=80', 7, 1),
(8, 'Beverages & Shakes', 'Refreshing mango lassi, cold brew, fresh lime soda, and thick shakes', 'beverages', 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=240&q=80', 8, 1);

-- Menu Items
INSERT INTO `menu_items` (`item_id`, `category_id`, `item_name`, `description`, `price`, `stock_quantity`, `is_available`, `is_veg`, `is_healthy`, `prep_time_minutes`, `kitchen_station`, `image_url`) VALUES
(1, 1, 'Meghana Special Chicken Biryani', 'Hyderabadi style slow-cooked spicy chicken layered with fragrant basmati rice, served with mirchi ka salan and onion raita.', 319.00, 45, 1, 0, 0, 25, 'Main Kitchen', 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?auto=format&fit=crop&w=600&q=80'),
(2, 1, 'Paneer Tikka Dum Biryani', 'Chargrilled marinated cottage cheese cubes cooked in saffron infused dum rice with mint and brown onions.', 279.00, 30, 1, 1, 0, 20, 'Main Kitchen', 'https://images.unsplash.com/photo-1633945274405-b6c8069047b0?auto=format&fit=crop&w=600&q=80'),
(3, 1, 'Mutton Dum Biryani (Lucknowi)', 'Tender prime mutton pieces marinated overnight in rich spices and sealed in handi.', 419.00, 20, 1, 0, 0, 30, 'Main Kitchen', 'https://images.unsplash.com/photo-1589302168068-964664d93dc0?auto=format&fit=crop&w=600&q=80'),
(4, 2, 'Butter Chicken Delhi Style', 'Tandoori boneless chicken simmered in silky tomato gravy infused with fresh cream and kasoori methi.', 299.00, 35, 1, 0, 0, 20, 'Main Kitchen', 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?auto=format&fit=crop&w=600&q=80'),
(5, 2, 'Paneer Butter Masala', 'Fresh malai paneer simmered in a mildly spiced, rich buttery gravy.', 249.00, 40, 1, 1, 0, 15, 'Main Kitchen', 'https://images.unsplash.com/photo-1633945274405-b6c8069047b0?auto=format&fit=crop&w=600&q=80'),
(6, 2, 'Dal Makhani Slow Handi', 'Black lentils slow cooked overnight on charcoal with butter and cream.', 199.00, 50, 1, 1, 0, 15, 'Main Kitchen', 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=600&q=80'),
(7, 2, 'Garlic Butter Naan (2 Pcs)', 'Clay oven tandoor bread garnished with chopped garlic and butter glaze.', 89.00, 80, 1, 1, 0, 10, 'Tandoor Station', 'https://images.unsplash.com/photo-1513104890138-7c749659a591?auto=format&fit=crop&w=600&q=80'),
(8, 3, 'Mysore Masala Dosa', 'Golden crisp fermented crepe layered with spicy red chili-garlic chutney and aloo bhaji.', 139.00, 40, 1, 1, 0, 12, 'South Station', 'https://images.unsplash.com/photo-1668236543090-82eba5ee5976?auto=format&fit=crop&w=600&q=80'),
(9, 3, 'Steamed Ghee Podi Idli (4 Pcs)', 'Mini soft rice cakes tossed in aromatic gunpowder spices and pure melted ghee.', 119.00, 35, 1, 1, 0, 10, 'South Station', 'https://images.unsplash.com/photo-1589302168068-964664d93dc0?auto=format&fit=crop&w=600&q=80'),
(10, 5, 'Double Egg Chicken Kathi Roll', 'Flaky paratha loaded with spiced juicy chicken chunks, beaten eggs, onions, and tangy mint mayo.', 179.00, 30, 1, 0, 0, 15, 'Roll Station', 'https://images.unsplash.com/photo-1626777552726-4a6b54c97e46?auto=format&fit=crop&w=600&q=80'),
(11, 5, 'Crispy Paneer Tikka Roll', 'Smoked cottage cheese cubes with bell peppers wrapped in rumali roti.', 159.00, 25, 1, 1, 0, 15, 'Roll Station', 'https://images.unsplash.com/photo-1550547660-d9450f859349?auto=format&fit=crop&w=600&q=80'),
(12, 6, 'Lush Forest Gourmet Burger', 'Double crispy patty, aged cheddar slice, caramelised onions and mint-herb mayo on brioche.', 229.00, 25, 1, 1, 0, 15, 'Burger Station', 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=600&q=80'),
(13, 6, 'Crispy Fried Chicken Burger', 'Buttermilk spiced chicken breast, pickled cucumber and chipotle mayo.', 249.00, 20, 1, 0, 0, 18, 'Burger Station', 'https://images.unsplash.com/photo-1550547660-d9450f859349?auto=format&fit=crop&w=600&q=80'),
(14, 7, 'Gulab Jamun with Rabri (2 Pcs)', 'Warm khoya dumplings steeped in rose cardamom syrup served with chilled rabri.', 129.00, 50, 1, 1, 0, 8, 'Dessert Station', 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?auto=format&fit=crop&w=600&q=80'),
(15, 7, 'Royal Kesar Rasmalai (2 Pcs)', 'Delicate cottage cheese discs soaked in saffron milk and pistachio flakes.', 139.00, 40, 1, 1, 0, 8, 'Dessert Station', 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?auto=format&fit=crop&w=600&q=80'),
(16, 8, 'Fresh Alphonso Mango Lassi', 'Thick creamy yogurt blended with real Alphonso mango pulp and cardamom.', 99.00, 60, 1, 1, 0, 5, 'Beverage Bar', 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=600&q=80'),
(17, 8, 'Mint Lime Cooler Soda', 'Crushed fresh garden mint, lime juice, black salt, and sparkling soda.', 69.00, 70, 1, 1, 0, 5, 'Beverage Bar', 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=600&q=80'),
(18, 4, 'Mediterranean Quinoa & Avocado Bowl', 'Wholesome tricolor organic quinoa, hass avocado, cherry tomatoes, cucumbers, roasted chickpeas, feta cheese, and citrus lemon-herb vinaigrette.', 249.00, 35, 1, 1, 1, 15, 'Salad & Cold Bar', 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=600&q=80'),
(19, 4, 'Grilled Herb Chicken & Broccoli Bowl', 'Lean rosemary chargrilled chicken breast paired with steamed broccoli florets, brown basmati rice, edamame, and cold-pressed olive-herb dressing.', 299.00, 25, 1, 0, 1, 18, 'Grill Station', 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=600&q=80'),
(20, 4, 'Sprouted Moong & Paneer Super-Salad', 'Organic sprouted green moong, lightly roasted malai paneer cubes, pomegranate pearls, baby spinach, roasted pumpkin seeds, and tangy mint-amchur dressing.', 189.00, 40, 1, 1, 1, 12, 'Salad & Cold Bar', 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=600&q=80'),
(21, 4, 'Cold-Pressed Green Detox Glow Juice', '100% pure raw cold-pressed celery, cucumber, baby spinach, green apple, ginger, and Meyer lemon. Zero added sugar or preservatives.', 129.00, 50, 1, 1, 1, 5, 'Beverage Bar', 'https://images.unsplash.com/photo-1613478223719-2ab802602423?auto=format&fit=crop&w=600&q=80'),
(22, 4, 'Roasted Sweet Potato & Hummus Power Bowl', 'Smoked paprika sweet potato chunks, house-made garlic hummus, baby arugula, kalamata olives, toasted almonds, and tahini drizzle.', 219.00, 30, 1, 1, 1, 15, 'Salad & Cold Bar', 'https://images.unsplash.com/photo-1543339308-43e59d6b73a6?auto=format&fit=crop&w=600&q=80');

-- Sample Orders (Exactly 10 Orders Across 3 Customers: Athul, Amal, Abhijith)
-- Breakdown: 2 Confirmed (reached KDS), 3 Cooking, 2 Ready, 3 Completed (Delivered)
INSERT INTO `orders` (`order_id`, `order_number`, `user_id`, `address_id`, `subtotal`, `tax_amount`, `delivery_fee`, `platform_fee`, `discount_amount`, `total_amount`, `coupon_code`, `order_status`, `order_notes`, `placed_at`, `estimated_delivery_time`) VALUES
(1001, '#ZIP-1001', 2, 1, 397.00, 19.85, 40.00, 5.00, 40.00, 421.85, 'WELCOME', 'CONFIRMED', 'Ring doorbell twice', DATE_SUB(NOW(), INTERVAL 12 MINUTE), NOW() + INTERVAL 25 MINUTE),
(1002, '#ZIP-1002', 3, 3, 418.00, 20.90, 40.00, 5.00, 0.00, 483.90, NULL, 'CONFIRMED', 'Please provide extra raita', DATE_SUB(NOW(), INTERVAL 8 MINUTE), NOW() + INTERVAL 28 MINUTE),
(1003, '#ZIP-1003', 4, 5, 477.00, 23.85, 40.00, 5.00, 50.00, 495.85, 'ZIPPY50', 'COOKING', 'Less spicy gravy please', DATE_SUB(NOW(), INTERVAL 18 MINUTE), NOW() + INTERVAL 20 MINUTE),
(1004, '#ZIP-1004', 2, 2, 378.00, 18.90, 0.00, 5.00, 0.00, 401.90, NULL, 'COOKING', 'Deliver at Office Reception Tower B', DATE_SUB(NOW(), INTERVAL 22 MINUTE), NOW() + INTERVAL 15 MINUTE),
(1005, '#ZIP-1005', 3, 4, 367.00, 18.35, 40.00, 5.00, 0.00, 430.35, NULL, 'COOKING', 'Extra paper napkins', DATE_SUB(NOW(), INTERVAL 25 MINUTE), NOW() + INTERVAL 12 MINUTE),
(1006, '#ZIP-1006', 4, 6, 528.00, 26.40, 40.00, 5.00, 50.00, 549.40, 'FEAST50', 'READY', 'Packed neatly in thermal bag please', DATE_SUB(NOW(), INTERVAL 32 MINUTE), NOW() + INTERVAL 10 MINUTE),
(1007, '#ZIP-1007', 2, 1, 527.00, 26.35, 0.00, 5.00, 0.00, 558.35, NULL, 'READY', 'Call when arriving at main gate', DATE_SUB(NOW(), INTERVAL 35 MINUTE), NOW() + INTERVAL 8 MINUTE),
(1008, '#ZIP-1008', 3, 3, 636.00, 31.80, 40.00, 5.00, 50.00, 662.80, 'ZIPPY50', 'DELIVERED', 'Leave with security guard', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 35 MINUTE),
(1009, '#ZIP-1009', 4, 5, 498.00, 24.90, 0.00, 5.00, 0.00, 527.90, NULL, 'DELIVERED', 'Deliver to 2nd floor', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 30 MINUTE),
(1010, '#ZIP-1010', 2, 1, 508.00, 25.40, 40.00, 5.00, 50.00, 528.40, 'HEALTHY50', 'DELIVERED', 'Please do not ring bell', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 32 MINUTE);

-- Order Items (Normalized Line Items with Historical Pricing)
INSERT INTO `order_items` (`order_item_id`, `order_id`, `item_id`, `quantity`, `unit_price`, `line_total`) VALUES
(1, 1001, 8, 2, 139.00, 278.00),
(2, 1001, 9, 1, 119.00, 119.00),
(3, 1002, 1, 1, 319.00, 319.00),
(4, 1002, 16, 1, 99.00, 99.00),
(5, 1003, 4, 1, 299.00, 299.00),
(6, 1003, 7, 2, 89.00, 178.00),
(7, 1004, 18, 1, 249.00, 249.00),
(8, 1004, 21, 1, 129.00, 129.00),
(9, 1005, 12, 1, 229.00, 229.00),
(10, 1005, 17, 2, 69.00, 138.00),
(11, 1006, 2, 1, 279.00, 279.00),
(12, 1006, 5, 1, 249.00, 249.00),
(13, 1007, 13, 1, 249.00, 249.00),
(14, 1007, 10, 1, 179.00, 179.00),
(15, 1007, 16, 1, 99.00, 99.00),
(16, 1008, 1, 1, 319.00, 319.00),
(17, 1008, 7, 2, 89.00, 178.00),
(18, 1008, 15, 1, 139.00, 139.00),
(19, 1009, 4, 1, 299.00, 299.00),
(20, 1009, 6, 1, 199.00, 199.00),
(21, 1010, 1, 1, 319.00, 319.00),
(22, 1010, 20, 1, 189.00, 189.00);

-- Payments
INSERT INTO `payments` (`payment_id`, `order_id`, `payment_mode`, `transaction_reference`, `amount_paid`, `payment_status`, `payment_timestamp`) VALUES
(1, 1001, 'UPI', 'UPI-AXIS-100192', 421.85, 'SUCCESSFUL', DATE_SUB(NOW(), INTERVAL 12 MINUTE)),
(2, 1002, 'COD', 'COD-PENDING-1002', 483.90, 'PENDING', DATE_SUB(NOW(), INTERVAL 8 MINUTE)),
(3, 1003, 'CREDIT_CARD', 'CC-VISA-88219401', 495.85, 'SUCCESSFUL', DATE_SUB(NOW(), INTERVAL 18 MINUTE)),
(4, 1004, 'UPI', 'UPI-HDFC-992014', 401.90, 'SUCCESSFUL', DATE_SUB(NOW(), INTERVAL 22 MINUTE)),
(5, 1005, 'UPI', 'UPI-PAYTM-441029', 430.35, 'SUCCESSFUL', DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
(6, 1006, 'UPI', 'UPI-GPAY-771928', 549.40, 'SUCCESSFUL', DATE_SUB(NOW(), INTERVAL 32 MINUTE)),
(7, 1007, 'DEBIT_CARD', 'DC-HDFC-332194', 558.35, 'SUCCESSFUL', DATE_SUB(NOW(), INTERVAL 35 MINUTE)),
(8, 1008, 'UPI', 'UPI-982498234-AXIS', 662.80, 'SUCCESSFUL', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(9, 1009, 'NET_BANKING', 'NB-SBI-991204', 527.90, 'SUCCESSFUL', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(10, 1010, 'UPI', 'UPI-472910482-HDFC', 528.40, 'SUCCESSFUL', DATE_SUB(NOW(), INTERVAL 1 DAY));

-- Delivery Dispatches (Assigned to the 3 Riders: Shaji, Kichu, Abhi)
INSERT INTO `delivery_dispatches` (`dispatch_id`, `order_id`, `rider_id`, `delivery_partner_name`, `delivery_partner_phone`, `delivery_otp`, `current_latitude`, `current_longitude`, `delivery_latitude`, `delivery_longitude`, `route_progress`, `distance_remaining_km`, `estimated_minutes_remaining`, `assigned_at`, `dispatched_at`, `delivered_at`, `delivery_status`) VALUES
(1, 1001, 6, 'Shaji (Rider)', '+91 96320 98765', '482194', 12.97840000, 77.64080000, 12.96980000, 77.64990000, 0, 2.40, 15, DATE_SUB(NOW(), INTERVAL 12 MINUTE), NULL, NULL, 'ASSIGNED'),
(2, 1002, 7, 'Kichu (Rider)', '+91 96320 11223', '739201', 12.97840000, 77.64080000, 12.97120000, 77.64200000, 0, 3.10, 20, DATE_SUB(NOW(), INTERVAL 8 MINUTE), NULL, NULL, 'ASSIGNED'),
(3, 1003, 8, 'Abhi (Rider)', '+91 96320 33445', '391054', 12.97840000, 77.64080000, 12.96500000, 77.65100000, 0, 4.20, 22, DATE_SUB(NOW(), INTERVAL 18 MINUTE), NULL, NULL, 'ASSIGNED'),
(4, 1004, 6, 'Shaji (Rider)', '+91 96320 98765', '610482', 12.97840000, 77.64080000, 12.96980000, 77.64990000, 0, 2.10, 15, DATE_SUB(NOW(), INTERVAL 22 MINUTE), NULL, NULL, 'ASSIGNED'),
(5, 1005, 7, 'Kichu (Rider)', '+91 96320 11223', '552914', 12.97840000, 77.64080000, 12.97120000, 77.64200000, 0, 2.80, 18, DATE_SUB(NOW(), INTERVAL 25 MINUTE), NULL, NULL, 'ASSIGNED'),
(6, 1006, 8, 'Abhi (Rider)', '+91 96320 33445', '918234', 12.97840000, 77.64080000, 12.96500000, 77.65100000, 0, 3.50, 15, DATE_SUB(NOW(), INTERVAL 32 MINUTE), NULL, NULL, 'ASSIGNED'),
(7, 1007, 6, 'Shaji (Rider)', '+91 96320 98765', '847291', 12.97840000, 77.64080000, 12.96980000, 77.64990000, 0, 1.80, 12, DATE_SUB(NOW(), INTERVAL 35 MINUTE), NULL, NULL, 'ASSIGNED'),
(8, 1008, 6, 'Shaji (Rider)', '+91 96320 98765', '482194', 12.97840000, 77.64080000, 12.97120000, 77.64200000, 100, 0.00, 0, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 15 MINUTE, DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 35 MINUTE, 'DELIVERED'),
(9, 1009, 7, 'Kichu (Rider)', '+91 96320 11223', '629104', 12.97840000, 77.64080000, 12.96500000, 77.65100000, 100, 0.00, 0, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 12 MINUTE, DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 30 MINUTE, 'DELIVERED'),
(10, 1010, 8, 'Abhi (Rider)', '+91 96320 33445', '852914', 12.97840000, 77.64080000, 12.96980000, 77.64990000, 100, 0.00, 0, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 14 MINUTE, DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 32 MINUTE, 'DELIVERED');

-- Audit Trail
INSERT INTO `order_audit_trail` (`audit_id`, `order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`) VALUES
(1, 1001, 'PLACED', 'CONFIRMED', 1, DATE_SUB(NOW(), INTERVAL 12 MINUTE)),
(2, 1002, 'PLACED', 'CONFIRMED', 1, DATE_SUB(NOW(), INTERVAL 8 MINUTE)),
(3, 1003, 'PLACED', 'CONFIRMED', 1, DATE_SUB(NOW(), INTERVAL 18 MINUTE)),
(4, 1003, 'CONFIRMED', 'COOKING', 5, DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
(5, 1004, 'PLACED', 'CONFIRMED', 1, DATE_SUB(NOW(), INTERVAL 22 MINUTE)),
(6, 1004, 'CONFIRMED', 'COOKING', 5, DATE_SUB(NOW(), INTERVAL 18 MINUTE)),
(7, 1005, 'PLACED', 'CONFIRMED', 1, DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
(8, 1005, 'CONFIRMED', 'COOKING', 5, DATE_SUB(NOW(), INTERVAL 20 MINUTE)),
(9, 1006, 'PLACED', 'CONFIRMED', 1, DATE_SUB(NOW(), INTERVAL 32 MINUTE)),
(10, 1006, 'CONFIRMED', 'COOKING', 5, DATE_SUB(NOW(), INTERVAL 28 MINUTE)),
(11, 1006, 'COOKING', 'READY', 5, DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
(12, 1007, 'PLACED', 'CONFIRMED', 1, DATE_SUB(NOW(), INTERVAL 35 MINUTE)),
(13, 1007, 'CONFIRMED', 'COOKING', 5, DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(14, 1007, 'COOKING', 'READY', 5, DATE_SUB(NOW(), INTERVAL 8 MINUTE)),
(15, 1008, 'PLACED', 'CONFIRMED', 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(16, 1008, 'CONFIRMED', 'COOKING', 5, DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 5 MINUTE),
(17, 1008, 'COOKING', 'READY', 5, DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 15 MINUTE),
(18, 1008, 'READY', 'OUT_FOR_DELIVERY', 6, DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 18 MINUTE),
(19, 1008, 'OUT_FOR_DELIVERY', 'DELIVERED', 6, DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 35 MINUTE),
(20, 1009, 'PLACED', 'CONFIRMED', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(21, 1009, 'CONFIRMED', 'COOKING', 5, DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 4 MINUTE),
(22, 1009, 'COOKING', 'READY', 5, DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 12 MINUTE),
(23, 1009, 'READY', 'OUT_FOR_DELIVERY', 7, DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 15 MINUTE),
(24, 1009, 'OUT_FOR_DELIVERY', 'DELIVERED', 7, DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 30 MINUTE),
(25, 1010, 'PLACED', 'CONFIRMED', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(26, 1010, 'CONFIRMED', 'COOKING', 5, DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 5 MINUTE),
(27, 1010, 'COOKING', 'READY', 5, DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 14 MINUTE),
(28, 1010, 'READY', 'OUT_FOR_DELIVERY', 8, DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 16 MINUTE),
(29, 1010, 'OUT_FOR_DELIVERY', 'DELIVERED', 8, DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 32 MINUTE);

-- Order Reviews & Ratings
INSERT INTO `order_reviews` (`review_id`, `order_id`, `user_id`, `food_rating`, `rider_rating`, `rider_tip`, `comments`, `created_at`) VALUES
(1, 1008, 3, 5, 5, 30.00, 'The Meghana biryani was piping hot and authentic! Shaji delivered it 5 minutes ahead of schedule.', DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 45 MINUTE),
(2, 1009, 4, 5, 5, 20.00, 'Dal makhani was rich and creamy. Kichu was very polite and punctual.', DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 40 MINUTE),
(3, 1010, 2, 5, 5, 25.00, 'Loved the combination of Biryani with fresh Moong Salad! Fast delivery by Abhi.', DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 45 MINUTE);

-- Dish Reviews & Ratings
INSERT INTO `dish_reviews` (`dish_review_id`, `item_id`, `order_id`, `user_id`, `rating`, `review_text`, `tags`) VALUES
(1, 1, 1008, 3, 5, 'Absolute perfection! The aromatic spices, succulent chicken pieces and fried onions were heavenly. Best biryani in Bangalore! ⭐⭐⭐⭐⭐', 'Piping Hot & Fresh,Authentic Andhra Spice,Tender & Juicy Cuts'),
(2, 1, 1010, 2, 5, 'Tender chicken and authentic Andhra flavor with just the right spice kick. Biryani lovers must try!', 'Authentic Andhra Spice,Generous Portion Size'),
(3, 2, 1006, 4, 5, 'Paneer was incredibly soft and fresh with smoky tandoori aroma. Loved every bite!', 'Piping Hot & Fresh,Generous Portion Size'),
(4, 4, 1009, 4, 5, 'Rich, creamy gravy with melt-in-mouth chicken. Paired wonderfully with garlic naan.', 'Delicious,Rich Gravy'),
(5, 5, 1006, 4, 4, 'Very flavorful and generous cottage cheese cubes in silky makhani gravy.', 'Good Taste,Spill-Proof Packaging'),
(6, 7, 1008, 3, 5, 'Crisp on the outside, soft on the inside with lovely minced garlic and butter.', 'Piping Hot & Fresh,Buttery'),
(7, 8, 1001, 2, 5, 'Crispy red chutney dosa, nostalgic Bangalore breakfast taste! Chutney was divine.', 'Crispy,Authentic Taste'),
(8, 12, 1005, 3, 5, 'Juicy gourmet patty and fresh brioche bun. Five stars for presentation and taste!', 'Gourmet Quality,Piping Hot & Fresh'),
(9, 15, 1008, 3, 5, 'Warm melt-in-mouth rabri and rasmalai. Pure bliss!', 'Sweet Perfection,Fresh'),
(10, 20, 1010, 2, 5, 'Crunchy sprouted moong with paneer cubes. Nutritious and filling!', 'Healthy,Natural Taste');

-- Staff Profiles (1 Chef: Sajeev, 3 Drivers: Shaji, Kichu, Abhi)
INSERT INTO `staff_profiles` (`profile_id`, `user_id`, `department`, `designation`, `specialization`, `duty_status`, `shift_timings`, `vehicle_details`, `driving_license`, `base_salary`, `hourly_or_order_rate`, `joined_date`, `cod_balance`, `total_orders_handled`, `rating`) VALUES
(1, 5, 'KITCHEN', 'Executive Head Chef', 'Master Culinary Operations & WSJF Queue Management', 'ON_DUTY', '08:00 AM - 05:00 PM', NULL, NULL, 35000.00, 40.00, '2025-01-01', 0.00, 940, 4.95),
(2, 6, 'DELIVERY', 'Senior Delivery Captain', 'Bellandur & Outer Ring Road Express Hub', 'ON_DUTY', '09:00 AM - 06:00 PM', 'Hero Electric Optima (KA-03-EM-4921)', 'DL-0120170048192', 20000.00, 45.00, '2025-03-15', 0.00, 810, 4.90),
(3, 7, 'DELIVERY', 'Rapid Fleet Partner', 'Indiranagar & Domlur Rapid Dispatch', 'ON_DUTY', '10:00 AM - 07:00 PM', 'Honda Activa 6G (KA-05-EJ-8120)', 'DL-0420180091823', 19000.00, 45.00, '2025-06-20', 0.00, 520, 4.85),
(4, 8, 'DELIVERY', 'Rapid Fleet Partner', 'Whitefield & ITPL Rapid Delivery', 'ON_DUTY', '11:00 AM - 08:00 PM', 'TVS NTORQ (KA-04-HF-4491)', 'DL-0320190019204', 18500.00, 40.00, '2025-11-01', 0.00, 480, 4.88);

-- Staff Salary Payouts
INSERT INTO `staff_salary_payouts` (`payout_id`, `user_id`, `month_year`, `base_amount`, `incentives_amount`, `deductions_amount`, `net_paid`, `payment_mode`, `transaction_reference`, `payout_status`, `paid_at`, `notes`) VALUES
(1, 5, 'October 2026', 35000.00, 2500.00, 0.00, 37500.00, 'BANK_TRANSFER', 'NEFT-SAL-CHEF-202610-9812', 'PAID', '2026-10-31 18:30:00', 'Monthly executive culinary salary & peak preparation bonus'),
(2, 5, 'September 2026', 35000.00, 2100.00, 0.00, 37100.00, 'BANK_TRANSFER', 'NEFT-SAL-CHEF-202609-8472', 'PAID', '2026-09-30 18:00:00', 'Monthly executive culinary salary'),
(3, 6, 'October 2026', 20000.00, 4350.00, 0.00, 24350.00, 'UPI', 'UPI-PAYOUT-SHAJI-202610-5512', 'PAID', '2026-10-31 19:15:00', 'Base stipend + 97 fulfilled doorstep deliveries'),
(4, 6, 'September 2026', 20000.00, 3900.00, 0.00, 23900.00, 'UPI', 'UPI-PAYOUT-SHAJI-202609-4419', 'PAID', '2026-09-30 19:00:00', 'Base stipend + 87 fulfilled doorstep deliveries'),
(5, 7, 'October 2026', 19000.00, 3200.00, 0.00, 22200.00, 'UPI', 'UPI-PAYOUT-KICHU-202610-1192', 'PAID', '2026-10-31 19:30:00', 'Base stipend + 72 fulfilled deliveries'),
(6, 8, 'October 2026', 18500.00, 2800.00, 0.00, 21300.00, 'UPI', 'UPI-PAYOUT-ABHI-202610-7731', 'PAID', '2026-10-31 19:45:00', 'Base stipend + 64 fulfilled deliveries');

-- Money Transactions (Backfilled Ledger for Online/COD Inflows and Salary Outflows)
INSERT INTO `money_transactions` (`txn_id`, `txn_number`, `order_id`, `user_id`, `txn_category`, `flow_direction`, `payment_method`, `amount`, `status`, `reference_id`, `handled_by`, `notes`, `created_at`) VALUES
(1, 'TXN-202611-01001', 1001, 2, 'CUSTOMER_PAYMENT_ONLINE', 'INWARD', 'UPI', 421.85, 'COMPLETED', 'UPI-AXIS-100192', 'Razorpay Digital Gateway', 'Prepaid online settlement via UPI', DATE_SUB(NOW(), INTERVAL 12 MINUTE)),
(2, 'TXN-202611-01002', 1002, 3, 'CUSTOMER_PAYMENT_COD', 'INWARD', 'COD_CASH', 483.90, 'PENDING', 'COD-PENDING-1002', 'Rider: Kichu (Rider)', 'Cash collection pending delivery handover', DATE_SUB(NOW(), INTERVAL 8 MINUTE)),
(3, 'TXN-202611-01003', 1003, 4, 'CUSTOMER_PAYMENT_ONLINE', 'INWARD', 'CREDIT_CARD', 495.85, 'COMPLETED', 'CC-VISA-88219401', 'Razorpay Digital Gateway', 'Prepaid online settlement via CREDIT_CARD', DATE_SUB(NOW(), INTERVAL 18 MINUTE)),
(4, 'TXN-202611-01004', 1004, 2, 'CUSTOMER_PAYMENT_ONLINE', 'INWARD', 'UPI', 401.90, 'COMPLETED', 'UPI-HDFC-992014', 'Razorpay Digital Gateway', 'Prepaid online settlement via UPI', DATE_SUB(NOW(), INTERVAL 22 MINUTE)),
(5, 'TXN-202611-01005', 1005, 3, 'CUSTOMER_PAYMENT_ONLINE', 'INWARD', 'UPI', 430.35, 'COMPLETED', 'UPI-PAYTM-441029', 'Razorpay Digital Gateway', 'Prepaid online settlement via UPI', DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
(6, 'TXN-202611-01006', 1006, 4, 'CUSTOMER_PAYMENT_ONLINE', 'INWARD', 'UPI', 549.40, 'COMPLETED', 'UPI-GPAY-771928', 'Razorpay Digital Gateway', 'Prepaid online settlement via UPI', DATE_SUB(NOW(), INTERVAL 32 MINUTE)),
(7, 'TXN-202611-01007', 1007, 2, 'CUSTOMER_PAYMENT_ONLINE', 'INWARD', 'DEBIT_CARD', 558.35, 'COMPLETED', 'DC-HDFC-332194', 'Razorpay Digital Gateway', 'Prepaid online settlement via DEBIT_CARD', DATE_SUB(NOW(), INTERVAL 35 MINUTE)),
(8, 'TXN-202611-01008', 1008, 3, 'CUSTOMER_PAYMENT_ONLINE', 'INWARD', 'UPI', 662.80, 'COMPLETED', 'UPI-982498234-AXIS', 'Razorpay Digital Gateway', 'Prepaid online settlement via UPI', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(9, 'TXN-202611-01009', 1009, 4, 'CUSTOMER_PAYMENT_ONLINE', 'INWARD', 'NET_BANKING', 527.90, 'COMPLETED', 'NB-SBI-991204', 'Razorpay Digital Gateway', 'Prepaid online settlement via NET_BANKING', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(10, 'TXN-202611-01010', 1010, 2, 'CUSTOMER_PAYMENT_ONLINE', 'INWARD', 'UPI', 528.40, 'COMPLETED', 'UPI-472910482-HDFC', 'Razorpay Digital Gateway', 'Prepaid online settlement via UPI', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(11, 'TXN-SAL-202610-0005', NULL, 5, 'STAFF_SALARY_PAYOUT', 'OUTWARD', 'BANK_TRANSFER', 37500.00, 'COMPLETED', 'NEFT-SAL-CHEF-202610-9812', 'Finance Admin: Pooja', 'Executive Head Chef October 2026 compensation disbursement', '2026-10-31 18:30:00'),
(12, 'TXN-SAL-202610-0006', NULL, 6, 'STAFF_SALARY_PAYOUT', 'OUTWARD', 'UPI', 24350.00, 'COMPLETED', 'UPI-PAYOUT-SHAJI-202610-5512', 'Finance Admin: Pooja', 'Rider Shaji October 2026 compensation disbursement', '2026-10-31 19:15:00');

-- Cash Register Closures (EOD Reconciled Z-Reports)
INSERT INTO `cash_register_closures` (`closure_id`, `closure_date`, `closing_admin_id`, `closing_admin_name`, `total_orders_count`, `gross_sales`, `online_payments_total`, `expected_cod_cash`, `actual_physical_cash`, `discrepancy_amount`, `discrepancy_reason`, `status`, `closed_at`) VALUES
(1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 1, 'Pooja (Admin)', 2, 1056.30, 1056.30, 0.00, 0.00, 0.00, 'All settlements reconciled via UPI/Digital Gateways', 'SEALED', DATE_SUB(NOW(), INTERVAL 1 DAY));

-- Dynamic FAQs
INSERT INTO `faqs` (`faq_id`, `question`, `answer`, `category`, `display_order`, `is_active`) VALUES
(1, 'What is the cancellation policy on food orders?', 'Orders can be cancelled with 100% refund within 60 seconds of placement. Once the restaurant kitchen accepts the ticket and marks status as COOKING, cancellations are non-refundable as fresh meals are perishable under FSSAI rules.', 'Orders & Cancellation', 1, 1),
(2, 'How does the 6-Digit Delivery OTP handshake work?', 'Every order generates an immutable 6-digit cryptographic OTP stored in the delivery_dispatches table. The delivery partner cannot mark the delivery complete without receiving and submitting this code, guaranteeing secure doorstep delivery handshakes.', 'Delivery & Tracking', 2, 1),
(3, 'How are delivery ETAs calculated?', 'Our platform utilizes a multi-factor heuristic: ETA = Buffer + Max(Prep Time) + Concurrency Factor + Distance Zone Travel Time. In addition, the kitchen prioritizes tickets via the Weighted Shortest Job First (WSJF) algorithm.', 'Delivery & Tracking', 3, 1),
(4, 'Are GST charges included in the final bill?', 'Yes, 5% GST is split equally into 2.5% CGST and 2.5% SGST under SAC code 996331. An official printable GST Tax Invoice is generated for every completed transaction.', 'Billing & Payments', 4, 1),
(5, 'What payment modes are supported and are they secure?', 'We support UPI (GPay, PhonePe, Paytm), Credit/Debit Cards, Net Banking, and Cash on Delivery (COD). Digital transactions are secured via Razorpay sandbox payment tokens and encrypted session verification.', 'Billing & Payments', 5, 1),
(6, 'How are food hygiene and temperature maintained during transit?', 'All meals are prepared fresh upon order confirmation and packaged in thermal spill-proof food-grade containers. Our riders use insulated carrier bags to ensure meals arrive piping hot.', 'General', 6, 1);

SET FOREIGN_KEY_CHECKS = 1;
