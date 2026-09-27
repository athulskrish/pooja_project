-- =============================================================================
-- ZIPPY FOOD ORDERING PLATFORM - SEED DATA SCRIPT
-- =============================================================================

USE `zippy_food`;

-- 1. Insert Roles
INSERT INTO `roles` (`role_id`, `role_name`, `description`) VALUES
(1, 'CUSTOMER', 'End-user food ordering customer with cart, address and order management privileges'),
(2, 'KITCHEN', 'Kitchen staff & chef role with access to KDS queue and preparation status updates'),
(3, 'RIDER', 'Delivery executive with dispatch assignment, turn-by-turn navigation, and OTP verification'),
(4, 'ADMIN', 'Restaurant administrator with store KPIs, dish CRUD, user management, and BI analytics')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- 2. Insert Users (Password: 'password123' hashed with Argon2id / Bcrypt)
-- Hash generated for 'password123': $2y$10$e8wScmK2q4K8wz1pC0XWLe43hW7hM4qT0nKq3f3R6bYgI3i0.x.tC (compatible bcrypt fallback)
INSERT INTO `users` (`user_id`, `role_id`, `full_name`, `email`, `phone_number`, `password_hash`, `is_active`) VALUES
(1, 1, 'Pooja JP', 'pooja@zippy.test', '+91 9876543210', '$2y$10$e8wScmK2q4K8wz1pC0XWLe43hW7hM4qT0nKq3f3R6bYgI3i0.x.tC', 1),
(2, 1, 'Rahul Sharma', 'rahul@zippy.test', '+91 9876543211', '$2y$10$e8wScmK2q4K8wz1pC0XWLe43hW7hM4qT0nKq3f3R6bYgI3i0.x.tC', 1),
(3, 2, 'Chef Vikram Rao', 'chef@zippy.test', '+91 9876543212', '$2y$10$e8wScmK2q4K8wz1pC0XWLe43hW7hM4qT0nKq3f3R6bYgI3i0.x.tC', 1),
(4, 3, 'Rajesh Kumar', 'rider@zippy.test', '+91 9876543213', '$2y$10$e8wScmK2q4K8wz1pC0XWLe43hW7hM4qT0nKq3f3R6bYgI3i0.x.tC', 1),
(5, 4, 'Pooja Admin', 'admin@zippy.test', '+91 9876543214', '$2y$10$e8wScmK2q4K8wz1pC0XWLe43hW7hM4qT0nKq3f3R6bYgI3i0.x.tC', 1)
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- 3. Insert User Addresses
INSERT INTO `user_addresses` (`address_id`, `user_id`, `address_type`, `address_line`, `landmark`, `pincode`, `is_default`) VALUES
(1, 1, 'HOME', 'Flat 402, Green Glen Layout, 3rd Cross, Bellandur', 'Near Sobha Silicon Oasis', '560103', 1),
(2, 1, 'WORK', '5th Floor, Salarpuria Softzone, Outer Ring Road, Bellandur', 'Opposite Intel Campus', '560103', 0),
(3, 2, 'HOME', 'Villa 12, Rainbow Drive, Sarjapur Road', 'Opposite Wipro SEZ', '560035', 1)
ON DUPLICATE KEY UPDATE `address_line` = VALUES(`address_line`);

-- 4. Insert Categories
INSERT INTO `categories` (`category_id`, `category_name`, `category_description`, `category_slug`, `icon_class`, `is_active`, `display_order`) VALUES
(1, 'Biryani Special', 'Slow-cooked aromatic dum basmati rice delicacies infused with secret Mughlai and Andhra spices', 'biryani', 'fa-solid fa-bowl-rice', 1, 1),
(2, 'Starters & Grills', 'Tantalizing tandoori appetizers, sizzling kebabs, and crispy spiced finger food', 'starters', 'fa-solid fa-fire', 1, 2),
(3, 'Main Course Curries', 'Rich slow-simmered gravies, creamy butter gravies, and aromatic curries', 'main-course', 'fa-solid fa-utensils', 1, 3),
(4, 'Breads & Rice', 'Freshly baked butter tandoori naans, rotis, parathas, and flavored jeera rice', 'breads-rice', 'fa-solid fa-bread-slice', 1, 4),
(5, 'Chinese & Wok', 'Smoky stir-fried noodles, schezwan fried rice, and Indo-Chinese delights', 'chinese', 'fa-solid fa-bowl-food', 1, 5),
(6, 'Desserts & Sweets', 'Traditional Indian milk delicacies, warm gulab jamuns, and royal puddings', 'desserts', 'fa-solid fa-ice-cream', 1, 6),
(7, 'Beverages & Coolers', 'Refreshing chilled lassis, spiced buttermilk, and signature coolers', 'beverages', 'fa-solid fa-glass-water', 1, 7)
ON DUPLICATE KEY UPDATE `category_name` = VALUES(`category_name`);

-- 5. Insert Menu Items
INSERT INTO `menu_items` (`item_id`, `category_id`, `item_name`, `description`, `price`, `stock_quantity`, `is_available`, `is_veg`, `prep_time_minutes`, `image_url`, `rating`) VALUES
(1, 1, 'Meghana Special Chicken Biryani', 'Signature Andhra style spicy boneless chicken biryani served with spicy gravy and thick onion raita.', 330.00, 50, 1, 0, 18, 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=500&auto=format&fit=crop&q=60', 4.8),
(2, 1, 'Hyderabadi Paneer Dum Biryani', 'Layers of long grain fragrant basmati rice slow cooked on dum with marinated cottage cheese cubes and saffron.', 280.00, 40, 1, 1, 16, 'https://images.unsplash.com/photo-1642821373181-696a54913e9a?w=500&auto=format&fit=crop&q=60', 4.6),
(3, 1, 'Royal Mutton Dum Biryani', 'Tender cuts of fresh goat meat cooked to perfection in rich spices with golden caramelized onions and basmati.', 420.00, 30, 1, 0, 24, 'https://images.unsplash.com/photo-1589302168068-964664d93dc0?w=500&auto=format&fit=crop&q=60', 4.9),
(4, 2, 'Chicken 65 Andhra Classic', 'Tender chicken florets fried crisp with curry leaves, cracked black pepper, and green chilies.', 260.00, 45, 1, 0, 12, 'https://images.unsplash.com/photo-1610057099443-fde8c4d50f91?w=500&auto=format&fit=crop&q=60', 4.7),
(5, 2, 'Tandoori Paneer Tikka', 'Charcoal grilled cottage cheese chunks infused with hung curd marinade and roasted carom seeds.', 250.00, 35, 1, 1, 15, 'https://images.unsplash.com/photo-1567188040759-fb8a883dc6d8?w=500&auto=format&fit=crop&q=60', 4.6),
(6, 2, 'Crispy Corn Pepper Salt', 'Sweet corn kernels flash-fried with scallions, garlic, crushed black pepper, and spring onions.', 210.00, 50, 1, 1, 10, 'https://images.unsplash.com/photo-1534422298391-e4f8c172dddb?w=500&auto=format&fit=crop&q=60', 4.5),
(7, 3, 'Murgh Makhani (Butter Chicken)', 'Tender roasted chicken tikka pulled into a velvety satin tomato cream gravy finished with fenugreek.', 320.00, 60, 1, 0, 16, 'https://images.unsplash.com/photo-1588166524941-3bf61a9c41db?w=500&auto=format&fit=crop&q=60', 4.8),
(8, 3, 'Paneer Butter Masala', 'Fresh cottage cheese batons cooked in rich cashew-tomato gravy garnished with fresh cream.', 270.00, 50, 1, 1, 15, 'https://images.unsplash.com/photo-1631452180519-c014fe946bc7?w=500&auto=format&fit=crop&q=60', 4.7),
(9, 3, 'Dal Makhani Royal Bukhara', 'Overnight slow simmered whole black lentils with fresh cream, churned butter, and mild aromatic spices.', 230.00, 55, 1, 1, 14, 'https://images.unsplash.com/photo-1546833999-b9f581a1996d?w=500&auto=format&fit=crop&q=60', 4.8),
(10, 4, 'Butter Garlic Naan', 'Clay oven leavened bread slathered with roasted garlic butter and fresh coriander.', 65.00, 120, 1, 1, 6, 'https://images.unsplash.com/photo-1601050690597-df0568f70950?w=500&auto=format&fit=crop&q=60', 4.9),
(11, 4, 'Laccha Paratha', 'Multi-layered crispy flaky whole wheat bread baked in earthen tandoor with clarified ghee.', 55.00, 100, 1, 1, 7, 'https://images.unsplash.com/photo-1626074353765-517a681e40be?w=500&auto=format&fit=crop&q=60', 4.6),
(12, 5, 'Chilli Garlic Chicken Fried Rice', 'Wok tossed aromatic rice with shredded chicken, crunchy bell peppers, and crushed burnt garlic.', 240.00, 40, 1, 0, 14, 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?w=500&auto=format&fit=crop&q=60', 4.5),
(13, 6, 'Shahi Gulab Jamun (2 Pcs)', 'Hot golden milk-solid dumplings soaked in rose water and cardamom saffron sugar syrup.', 90.00, 80, 1, 1, 5, 'https://images.unsplash.com/photo-1601050690597-df0568f70950?w=500&auto=format&fit=crop&q=60', 4.9),
(14, 6, 'Kolkata Rasmalai (2 Pcs)', 'Soft spongy chenna cakes soaked in saffron scented creamy rabri with crushed pistachios.', 110.00, 60, 1, 1, 5, 'https://images.unsplash.com/photo-1589301760014-d929f3979dbc?w=500&auto=format&fit=crop&q=60', 4.8),
(15, 7, 'Alphonso Mango Lassi', 'Rich whipped thick yogurt blended with real Ratnagiri Alphonso mango pulp and cardamom.', 100.00, 75, 1, 1, 4, 'https://images.unsplash.com/photo-1546173159-315724a31696?w=500&auto=format&fit=crop&q=60', 4.9),
(16, 7, 'Fresh Mint Lime Soda', 'Crisp effervescent soda with freshly squeezed lime, mint leaves, and roasted cumin rock salt.', 70.00, 90, 1, 1, 3, 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=500&auto=format&fit=crop&q=60', 4.7)
ON DUPLICATE KEY UPDATE `price` = VALUES(`price`), `stock_quantity` = VALUES(`stock_quantity`);

-- 6. Insert Sample Orders
INSERT INTO `orders` (`order_id`, `user_id`, `address_id`, `subtotal`, `tax_amount`, `delivery_fee`, `discount_amount`, `total_amount`, `order_status`, `order_notes`, `placed_at`, `estimated_delivery_time`) VALUES
(1001, 1, 1, 650.00, 32.50, 40.00, 50.00, 672.50, 'OUT_FOR_DELIVERY', 'Extra raita and spicy salan please. Ring doorbell twice.', DATE_SUB(NOW(), INTERVAL 25 MINUTE), DATE_ADD(NOW(), INTERVAL 15 MINUTE)),
(1002, 1, 1, 380.00, 19.00, 0.00, 0.00, 399.00, 'DELIVERED', 'Leave package at doorstep', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1003, 1, 2, 530.00, 26.50, 40.00, 100.00, 496.50, 'DELIVERED', 'Deliver at office reception', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY))
ON DUPLICATE KEY UPDATE `order_status` = VALUES(`order_status`);

-- 7. Insert Order Items (Enabling Apriori Market Basket Analysis)
INSERT INTO `order_items` (`order_item_id`, `order_id`, `item_id`, `quantity`, `unit_price`, `line_total`) VALUES
(1, 1001, 1, 1, 330.00, 330.00), -- Chicken Biryani
(2, 1001, 4, 1, 260.00, 260.00), -- Chicken 65
(3, 1001, 10, 1, 65.00, 65.00),   -- Butter Garlic Naan
(4, 1002, 7, 1, 320.00, 320.00),  -- Butter Chicken
(5, 1002, 10, 1, 65.00, 65.00),   -- Butter Garlic Naan
(6, 1003, 2, 1, 280.00, 280.00),  -- Paneer Dum Biryani
(7, 1003, 5, 1, 250.00, 250.00)   -- Paneer Tikka
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);

-- 8. Insert Payments
INSERT INTO `payments` (`payment_id`, `order_id`, `payment_mode`, `transaction_reference`, `amount_paid`, `payment_status`, `payment_timestamp`) VALUES
(1, 1001, 'UPI', 'UPI-TXN-984729183921', 672.50, 'SUCCESSFUL', DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
(2, 1002, 'CREDIT_CARD', 'CC-TXN-482910482910', 399.00, 'SUCCESSFUL', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 1003, 'UPI', 'UPI-TXN-109284719283', 496.50, 'SUCCESSFUL', DATE_SUB(NOW(), INTERVAL 5 DAY))
ON DUPLICATE KEY UPDATE `payment_status` = VALUES(`payment_status`);

-- 9. Insert Delivery Dispatches
INSERT INTO `delivery_dispatches` (`dispatch_id`, `order_id`, `delivery_partner_name`, `delivery_partner_phone`, `delivery_otp`, `assigned_at`, `dispatched_at`, `delivered_at`, `delivery_status`) VALUES
(1, 1001, 'Rajesh Kumar', '+91 9876543213', '7482', DATE_SUB(NOW(), INTERVAL 20 MINUTE), DATE_SUB(NOW(), INTERVAL 10 MINUTE), NULL, 'IN_TRANSIT'),
(2, 1002, 'Suresh Nair', '+91 9876543215', '3195', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), 'DELIVERED'),
(3, 1003, 'Rajesh Kumar', '+91 9876543213', '8204', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), 'DELIVERED')
ON DUPLICATE KEY UPDATE `delivery_status` = VALUES(`delivery_status`);

-- 10. Insert Audit Trail
INSERT INTO `order_audit_trail` (`audit_id`, `order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`) VALUES
(1, 1001, 'PLACED', 'CONFIRMED', 5, DATE_SUB(NOW(), INTERVAL 24 MINUTE)),
(2, 1001, 'CONFIRMED', 'COOKING', 3, DATE_SUB(NOW(), INTERVAL 20 MINUTE)),
(3, 1001, 'COOKING', 'READY', 3, DATE_SUB(NOW(), INTERVAL 12 MINUTE)),
(4, 1001, 'READY', 'OUT_FOR_DELIVERY', 4, DATE_SUB(NOW(), INTERVAL 10 MINUTE))
ON DUPLICATE KEY UPDATE `new_status` = VALUES(`new_status`);
