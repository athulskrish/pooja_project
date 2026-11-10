<?php
declare(strict_types=1);
/**
 * Migration: Create Dish Customizations Tables & Seed Exactly 25 Dishes with Customizations
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDbConnection();
if (!$pdo) {
    die("Database connection failed.\n");
}

echo "=== MIGRATION: DISH CUSTOMIZATIONS & 25 FOOD ITEMS ===\n";

// 1. Create table `dish_customization_groups`
echo "1. Checking table `dish_customization_groups`...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `dish_customization_groups` (
        `group_id` INT AUTO_INCREMENT PRIMARY KEY,
        `item_id` INT NOT NULL,
        `group_name` VARCHAR(100) NOT NULL,
        `selection_type` ENUM('radio', 'checkbox') NOT NULL DEFAULT 'checkbox',
        `is_required` TINYINT(1) NOT NULL DEFAULT 0,
        `min_selection` INT NOT NULL DEFAULT 0,
        `max_selection` INT NOT NULL DEFAULT 0,
        `display_order` INT NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_cust_item` (`item_id`),
        CONSTRAINT `fk_cust_groups_dish` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "   -> `dish_customization_groups` ready.\n";

// 2. Create table `dish_customization_options`
echo "2. Checking table `dish_customization_options`...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `dish_customization_options` (
        `option_id` INT AUTO_INCREMENT PRIMARY KEY,
        `group_id` INT NOT NULL,
        `option_name` VARCHAR(150) NOT NULL,
        `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `is_default` TINYINT(1) NOT NULL DEFAULT 0,
        `is_available` TINYINT(1) NOT NULL DEFAULT 1,
        `display_order` INT NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_cust_group` (`group_id`),
        CONSTRAINT `fk_cust_options_group` FOREIGN KEY (`group_id`) REFERENCES `dish_customization_groups` (`group_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "   -> `dish_customization_options` ready.\n";

// 3. Check `order_items` columns
echo "3. Checking `order_items` table for customization fields...\n";
$cols = dbFetchAll("SHOW COLUMNS FROM `order_items` LIKE 'customizations'");
if (empty($cols)) {
    $pdo->exec("ALTER TABLE `order_items` ADD COLUMN `customizations` TEXT NULL AFTER `line_total`");
    $pdo->exec("ALTER TABLE `order_items` ADD COLUMN `customization_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `customizations`");
    echo "   -> Added `customizations` and `customization_total` columns to `order_items`.\n";
} else {
    echo "   -> `customizations` column already exists in `order_items`.\n";
}

// 4. Ensure Exactly 25 Dishes Exist in `menu_items`
echo "4. Synchronizing 25 Menu Dishes...\n";

// Ensure dishes 23, 24, 25 exist
$extraDishes = [
    [
        'item_id' => 23,
        'category_id' => 6,
        'item_name' => 'Peri Peri Cheesy Fries',
        'description' => 'Golden crispy potato fries tossed in fiery African bird eye chili seasoning, layered with warm melted cheddar cheese sauce.',
        'price' => 149.00,
        'stock_quantity' => 45,
        'is_available' => 1,
        'is_veg' => 1,
        'is_healthy' => 0,
        'prep_time_minutes' => 10,
        'kitchen_station' => 'Fryer & Snacks',
        'image_url' => 'https://images.unsplash.com/photo-1576107232684-1279f3908594?auto=format&fit=crop&w=600&q=80'
    ],
    [
        'item_id' => 24,
        'category_id' => 3,
        'item_name' => 'Classic Ghee Roast Dosa',
        'description' => 'Crispy wafer-thin golden crepe roasted with pure aromatic desi ghee, served with spiced potato masala, vegetable sambar and fresh coconut chutney.',
        'price' => 159.00,
        'stock_quantity' => 35,
        'is_available' => 1,
        'is_veg' => 1,
        'is_healthy' => 0,
        'prep_time_minutes' => 12,
        'kitchen_station' => 'South Station',
        'image_url' => 'https://images.unsplash.com/photo-1668236543090-82eba5ee5976?auto=format&fit=crop&w=600&q=80'
    ],
    [
        'item_id' => 25,
        'category_id' => 8,
        'item_name' => 'Cold Brew Hazelnut Frappe',
        'description' => 'Slow-steeped 16-hour arabica cold brew blended with hazelnut praline syrup, creamy whole milk, and crushed ice.',
        'price' => 169.00,
        'stock_quantity' => 50,
        'is_available' => 1,
        'is_veg' => 1,
        'is_healthy' => 0,
        'prep_time_minutes' => 6,
        'kitchen_station' => 'Beverage Bar',
        'image_url' => 'https://images.unsplash.com/photo-1517256064527-09c73fc73e38?auto=format&fit=crop&w=600&q=80'
    ]
];

foreach ($extraDishes as $d) {
    $exists = dbFetchOne("SELECT item_id FROM `menu_items` WHERE `item_id` = :id", [':id' => $d['item_id']]);
    if (!$exists) {
        $stmt = $pdo->prepare("
            INSERT INTO `menu_items` (`item_id`, `category_id`, `item_name`, `description`, `price`, `stock_quantity`, `is_available`, `is_veg`, `is_healthy`, `prep_time_minutes`, `kitchen_station`, `image_url`)
            VALUES (:id, :cid, :name, :desc, :price, :stock, :avail, :veg, :healthy, :prep, :station, :img)
        ");
        $stmt->execute([
            ':id' => $d['item_id'],
            ':cid' => $d['category_id'],
            ':name' => $d['item_name'],
            ':desc' => $d['description'],
            ':price' => $d['price'],
            ':stock' => $d['stock_quantity'],
            ':avail' => $d['is_available'],
            ':veg' => $d['is_veg'],
            ':healthy' => $d['is_healthy'],
            ':prep' => $d['prep_time_minutes'],
            ':station' => $d['kitchen_station'],
            ':img' => $d['image_url']
        ]);
        echo "   -> Seeded dish #{$d['item_id']} ({$d['item_name']}).\n";
    } else {
        echo "   -> Dish #{$d['item_id']} ({$d['item_name']}) already in place.\n";
    }
}

// Ensure total count is 25: remove any extra items > 25 if created by tests
$countDishes = (int)dbFetchVal("SELECT COUNT(*) FROM `menu_items`");
echo "   -> Active menu items count: {$countDishes}\n";
if ($countDishes > 25) {
    // Delete test items above 25 that are not referenced by orders
    $pdo->exec("DELETE FROM `menu_items` WHERE `item_id` > 25 AND `item_id` NOT IN (SELECT DISTINCT `item_id` FROM `order_items`)");
    $countDishes = (int)dbFetchVal("SELECT COUNT(*) FROM `menu_items`");
    echo "   -> Reduced to: {$countDishes} dishes.\n";
}

// 5. Seed Customizations for All 25 Dishes
echo "5. Seeding Customizations for all 25 Dishes...\n";

// Clear existing customizations to re-seed cleanly
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("TRUNCATE TABLE `dish_customization_options`;");
$pdo->exec("TRUNCATE TABLE `dish_customization_groups`;");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

$customizationData = [
    // 1: Meghana Special Chicken Biryani
    1 => [
        [
            'name' => 'Spice Level',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Mild Spice', 'price' => 0.00, 'default' => 0],
                ['name' => 'Medium Spicy', 'price' => 0.00, 'default' => 1],
                ['name' => 'Andhra Fiery Hot', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Biryani Toppings & Extras',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Cheese', 'price' => 35.00, 'default' => 0],
                ['name' => 'Extra Mirchi Ka Salan', 'price' => 30.00, 'default' => 0],
                ['name' => 'Extra Onion Raita', 'price' => 25.00, 'default' => 0],
                ['name' => 'Boiled Egg (1 pc)', 'price' => 20.00, 'default' => 0],
                ['name' => 'Extra Fried Crispy Onions (Birista)', 'price' => 25.00, 'default' => 0],
                ['name' => 'Extra Roasted Chicken Piece', 'price' => 75.00, 'default' => 0],
            ]
        ]
    ],

    // 2: Paneer Tikka Dum Biryani
    2 => [
        [
            'name' => 'Spice Level',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Mild', 'price' => 0.00, 'default' => 0],
                ['name' => 'Medium Spicy', 'price' => 0.00, 'default' => 1],
                ['name' => 'Spicy', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Biryani Toppings & Extra Cheese',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Grated Cheese', 'price' => 35.00, 'default' => 0],
                ['name' => 'Extra Chargrilled Paneer Cubes (4 pcs)', 'price' => 55.00, 'default' => 0],
                ['name' => 'Roasted Cashews & Raisins', 'price' => 35.00, 'default' => 0],
                ['name' => 'Extra Mint Raita', 'price' => 25.00, 'default' => 0],
                ['name' => 'Extra Salan Gravy', 'price' => 30.00, 'default' => 0],
            ]
        ]
    ],

    // 3: Mutton Dum Biryani (Lucknowi)
    3 => [
        [
            'name' => 'Spice Level',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Awadhi Mild Aromatic', 'price' => 0.00, 'default' => 0],
                ['name' => 'Traditional Medium Dum', 'price' => 0.00, 'default' => 1],
                ['name' => 'Spicy Lucknowi', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Toppings & Accompaniments',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Cheese', 'price' => 35.00, 'default' => 0],
                ['name' => 'Extra Prime Mutton Piece', 'price' => 110.00, 'default' => 0],
                ['name' => 'Boiled Egg (1 pc)', 'price' => 20.00, 'default' => 0],
                ['name' => 'Burani Garlic Raita', 'price' => 35.00, 'default' => 0],
                ['name' => 'Saffron Kewra Ghee Drizzle', 'price' => 25.00, 'default' => 0],
                ['name' => 'Fried Birista Onions', 'price' => 25.00, 'default' => 0],
            ]
        ]
    ],

    // 4: Butter Chicken Delhi Style
    4 => [
        [
            'name' => 'Spice Level',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Delhi Mild Sweet & Creamy', 'price' => 0.00, 'default' => 0],
                ['name' => 'Medium Spiced', 'price' => 0.00, 'default' => 1],
                ['name' => 'Spicy Dhaba Style', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Curry Toppings & Extra Cheese',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Cheese Garnish', 'price' => 35.00, 'default' => 0],
                ['name' => 'Extra Amul Butter Dollop', 'price' => 25.00, 'default' => 0],
                ['name' => 'Fresh Malai Cream Swirl', 'price' => 20.00, 'default' => 0],
                ['name' => 'Extra Boneless Tandoori Chicken', 'price' => 70.00, 'default' => 0],
                ['name' => 'Roasted Kasoori Methi Sprinkle', 'price' => 10.00, 'default' => 0],
            ]
        ]
    ],

    // 5: Paneer Butter Masala
    5 => [
        [
            'name' => 'Spice Level',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Mild', 'price' => 0.00, 'default' => 0],
                ['name' => 'Medium', 'price' => 0.00, 'default' => 1],
                ['name' => 'Spicy', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Toppings & Extra Cheese',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Grated Cheese', 'price' => 35.00, 'default' => 0],
                ['name' => 'Extra Paneer Cubes (4 pcs)', 'price' => 45.00, 'default' => 0],
                ['name' => 'Extra White Butter', 'price' => 25.00, 'default' => 0],
                ['name' => 'Extra Malai Cream', 'price' => 20.00, 'default' => 0],
                ['name' => 'Roasted Cashew Crunch', 'price' => 35.00, 'default' => 0],
            ]
        ]
    ],

    // 6: Dal Makhani Slow Handi
    6 => [
        [
            'name' => 'Spice & Tempering',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Classic Mild Creamy', 'price' => 0.00, 'default' => 1],
                ['name' => 'Desi Ghee Tadka (Medium)', 'price' => 0.00, 'default' => 0],
                ['name' => 'Spicy Garlic Tadka', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Toppings & Extras',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Grated Cheese', 'price' => 35.00, 'default' => 0],
                ['name' => 'Extra Desi Ghee Dollop', 'price' => 30.00, 'default' => 0],
                ['name' => 'Extra Fresh Cream', 'price' => 20.00, 'default' => 0],
                ['name' => 'Roasted Crispy Garlic Crunch', 'price' => 15.00, 'default' => 0],
            ]
        ]
    ],

    // 7: Garlic Butter Naan (2 Pcs)
    7 => [
        [
            'name' => 'Baking Style',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Soft & Fluffy', 'price' => 0.00, 'default' => 1],
                ['name' => 'Crispy Tandoor Baked', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Toppings & Extra Cheese',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Melted Cheese Stuffed / Topping', 'price' => 40.00, 'default' => 0],
                ['name' => 'Extra Garlic Butter Glaze', 'price' => 20.00, 'default' => 0],
                ['name' => 'Spicy Green Chilli Flakes', 'price' => 10.00, 'default' => 0],
                ['name' => 'Toasted Kalonji (Nigella Seeds)', 'price' => 10.00, 'default' => 0],
            ]
        ]
    ],

    // 8: Mysore Masala Dosa
    8 => [
        [
            'name' => 'Chutney & Podi Spice',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Classic Red Mysore Chutney', 'price' => 0.00, 'default' => 1],
                ['name' => 'Extra Fiery Gunpowder (Podi)', 'price' => 0.00, 'default' => 0],
                ['name' => 'Mild Chutney (Less Spicy)', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Dosa Toppings & Extra Cheese',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Grated Amul Cheese', 'price' => 40.00, 'default' => 0],
                ['name' => 'Pure Cow Ghee Roast', 'price' => 30.00, 'default' => 0],
                ['name' => 'Extra Aloo Masala Portion', 'price' => 25.00, 'default' => 0],
                ['name' => 'Extra Vegetable Sambar Bowl', 'price' => 25.00, 'default' => 0],
                ['name' => 'Extra Coconut Chutney Cup', 'price' => 20.00, 'default' => 0],
            ]
        ]
    ],

    // 9: Steamed Ghee Podi Idli (4 Pcs)
    9 => [
        [
            'name' => 'Podi Spice Level',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Mild Aromatic Podi', 'price' => 0.00, 'default' => 0],
                ['name' => 'Traditional Medium Podi', 'price' => 0.00, 'default' => 1],
                ['name' => 'Fiery Gunpowder Podi', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Toppings & Accompaniments',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Grated Cheese', 'price' => 35.00, 'default' => 0],
                ['name' => 'Extra Melted Desi Ghee', 'price' => 25.00, 'default' => 0],
                ['name' => 'Extra Sambar Bowl', 'price' => 25.00, 'default' => 0],
                ['name' => 'Extra Coconut Chutney', 'price' => 20.00, 'default' => 0],
                ['name' => 'Roasted Cashews & Curry Leaves', 'price' => 30.00, 'default' => 0],
            ]
        ]
    ],

    // 10: Double Egg Chicken Kathi Roll
    10 => [
        [
            'name' => 'Spice Level',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Mild Mint Mayo', 'price' => 0.00, 'default' => 0],
                ['name' => 'Medium Spicy', 'price' => 0.00, 'default' => 1],
                ['name' => 'Spicy Green Chilli', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Roll Toppings & Extra Cheese',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Mozzarella & Cheddar Cheese', 'price' => 35.00, 'default' => 0],
                ['name' => 'Extra Juicy Chicken Chunks', 'price' => 50.00, 'default' => 0],
                ['name' => 'Extra Fried Egg Layer', 'price' => 20.00, 'default' => 0],
                ['name' => 'Sautéed Caramelised Onions', 'price' => 15.00, 'default' => 0],
                ['name' => 'Tangy Chipotle Sauce Drizzle', 'price' => 20.00, 'default' => 0],
            ]
        ]
    ],

    // 11: Crispy Paneer Tikka Roll
    11 => [
        [
            'name' => 'Spice Level',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Mild Tandoori', 'price' => 0.00, 'default' => 0],
                ['name' => 'Medium Spicy', 'price' => 0.00, 'default' => 1],
                ['name' => 'Spicy Schezwan', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Roll Toppings & Extra Cheese',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Grated Cheese', 'price' => 35.00, 'default' => 0],
                ['name' => 'Extra Paneer Tikka Cubes', 'price' => 45.00, 'default' => 0],
                ['name' => 'Crunchy Bell Peppers & Onions', 'price' => 15.00, 'default' => 0],
                ['name' => 'Mint Mayo & Chutney Drizzle', 'price' => 15.00, 'default' => 0],
            ]
        ]
    ],

    // 12: Lush Forest Gourmet Burger
    12 => [
        [
            'name' => 'Sauce & Spice Level',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Herb Mayo (Mild)', 'price' => 0.00, 'default' => 0],
                ['name' => 'Spicy Chipotle (Medium)', 'price' => 0.00, 'default' => 1],
                ['name' => 'Fiery Ghost Pepper Mayo (Spicy)', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Burger Toppings & Extra Cheese',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Cheddar Cheese Slice', 'price' => 35.00, 'default' => 0],
                ['name' => 'Double Crispy Veg Patty', 'price' => 55.00, 'default' => 0],
                ['name' => 'Sautéed Garlic Butter Mushrooms', 'price' => 40.00, 'default' => 0],
                ['name' => 'Pickled Jalapeños & Gherkins', 'price' => 25.00, 'default' => 0],
                ['name' => 'Crispy Onion Rings', 'price' => 30.00, 'default' => 0],
            ]
        ]
    ],

    // 13: Crispy Fried Chicken Burger
    13 => [
        [
            'name' => 'Spice Level',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Classic Southern Mild', 'price' => 0.00, 'default' => 0],
                ['name' => 'Zesty Nashville Medium', 'price' => 0.00, 'default' => 1],
                ['name' => 'Fiery Peri Peri Hot', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Burger Toppings & Extra Cheese',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Molten Cheese Slice', 'price' => 35.00, 'default' => 0],
                ['name' => 'Extra Fried Chicken Patty', 'price' => 85.00, 'default' => 0],
                ['name' => 'Pickled Jalapeños', 'price' => 25.00, 'default' => 0],
                ['name' => 'Smoky BBQ Sauce', 'price' => 20.00, 'default' => 0],
                ['name' => 'Creamy Coleslaw Layer', 'price' => 25.00, 'default' => 0],
            ]
        ]
    ],

    // 14: Gulab Jamun with Rabri (2 Pcs)
    14 => [
        [
            'name' => 'Serving Temperature',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Warm Jamuns with Chilled Rabri', 'price' => 0.00, 'default' => 1],
                ['name' => 'Piping Hot', 'price' => 0.00, 'default' => 0],
                ['name' => 'Chilled', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Sweet Toppings & Garnishes',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Thick Malai Rabri Scoop', 'price' => 35.00, 'default' => 0],
                ['name' => 'Roasted Pistachio & Almond Flakes', 'price' => 25.00, 'default' => 0],
                ['name' => 'Pure Saffron Silver Leaf (Vark)', 'price' => 20.00, 'default' => 0],
                ['name' => 'Rose Cardamom Syrup Drizzle', 'price' => 15.00, 'default' => 0],
            ]
        ]
    ],

    // 15: Royal Kesar Rasmalai (2 Pcs)
    15 => [
        [
            'name' => 'Sweetness Level',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Traditional Sweet', 'price' => 0.00, 'default' => 1],
                ['name' => 'Mild Less Sweet', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Toppings & Garnishes',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Kesar Pistachio Milk', 'price' => 30.00, 'default' => 0],
                ['name' => 'Crushed Cardamom & Almond Slivers', 'price' => 25.00, 'default' => 0],
                ['name' => 'Rose Petals & Saffron Strands', 'price' => 20.00, 'default' => 0],
            ]
        ]
    ],

    // 16: Fresh Alphonso Mango Lassi
    16 => [
        [
            'name' => 'Sweetness & Ice',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Normal Sweet & Chilled', 'price' => 0.00, 'default' => 1],
                ['name' => 'Less Sugar', 'price' => 0.00, 'default' => 0],
                ['name' => 'No Added Sugar', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Lassi Toppings & Add-ons',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Alphonso Mango Pulp', 'price' => 30.00, 'default' => 0],
                ['name' => 'Clotted Malai Float', 'price' => 25.00, 'default' => 0],
                ['name' => 'Pistachio & Almond Crumb', 'price' => 25.00, 'default' => 0],
                ['name' => 'Organic Chia Seeds', 'price' => 20.00, 'default' => 0],
            ]
        ]
    ],

    // 17: Mint Lime Cooler Soda
    17 => [
        [
            'name' => 'Fizz & Sweetness',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Sweet & Salty Classic', 'price' => 0.00, 'default' => 1],
                ['name' => 'Pure Sweet', 'price' => 0.00, 'default' => 0],
                ['name' => 'Tangy Salty (No Sugar)', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Add-ins & Toppings',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Crushed Mint & Lime Wedges', 'price' => 15.00, 'default' => 0],
                ['name' => 'Chia / Sabja Seeds', 'price' => 20.00, 'default' => 0],
                ['name' => 'Roasted Jeera & Rock Salt Twist', 'price' => 10.00, 'default' => 0],
            ]
        ]
    ],

    // 18: Mediterranean Quinoa & Avocado Bowl
    18 => [
        [
            'name' => 'Dressing Choice',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Citrus Lemon-Herb Vinaigrette', 'price' => 0.00, 'default' => 1],
                ['name' => 'Creamy Garlic Tahini', 'price' => 0.00, 'default' => 0],
                ['name' => 'Olive-Balsamic Glaze', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Bowl Toppings & Extra Cheese',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Greek Feta Cheese', 'price' => 45.00, 'default' => 0],
                ['name' => 'Extra Hass Avocado Slices', 'price' => 55.00, 'default' => 0],
                ['name' => 'Roasted Chickpeas & Pumpkin Seeds', 'price' => 30.00, 'default' => 0],
                ['name' => 'Boiled Organic Egg', 'price' => 20.00, 'default' => 0],
                ['name' => 'Grilled Tofu Cubes', 'price' => 40.00, 'default' => 0],
            ]
        ]
    ],

    // 19: Grilled Herb Chicken & Broccoli Bowl
    19 => [
        [
            'name' => 'Herb Glaze & Spice',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Rosemary Lemon Herb (Mild)', 'price' => 0.00, 'default' => 1],
                ['name' => 'Smoked Paprika (Medium)', 'price' => 0.00, 'default' => 0],
                ['name' => 'Spicy Peri Peri Glaze', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Protein Toppings & Extra Cheese',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Feta Cheese Crumbles', 'price' => 40.00, 'default' => 0],
                ['name' => 'Extra Grilled Chicken Breast', 'price' => 75.00, 'default' => 0],
                ['name' => 'Extra Steamed Broccoli & Edamame', 'price' => 40.00, 'default' => 0],
                ['name' => 'Extra Virgin Olive Oil Drizzle', 'price' => 25.00, 'default' => 0],
                ['name' => 'Boiled Egg (1 pc)', 'price' => 20.00, 'default' => 0],
            ]
        ]
    ],

    // 20: Sprouted Moong & Paneer Super-Salad
    20 => [
        [
            'name' => 'Dressing & Tanginess',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Tangy Mint-Amchur Dressing', 'price' => 0.00, 'default' => 1],
                ['name' => 'Lemon Olive Dressing', 'price' => 0.00, 'default' => 0],
                ['name' => 'Mild Yogurt Dressing', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Salad Toppings & Extra Cheese',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Roasted Malai Paneer Cubes', 'price' => 45.00, 'default' => 0],
                ['name' => 'Crumbled Feta Cheese', 'price' => 40.00, 'default' => 0],
                ['name' => 'Pomegranate Pearls & Cranberries', 'price' => 30.00, 'default' => 0],
                ['name' => 'Roasted Pumpkin & Sunflower Seeds', 'price' => 25.00, 'default' => 0],
                ['name' => 'Walnut & Almond Flakes', 'price' => 35.00, 'default' => 0],
            ]
        ]
    ],

    // 21: Cold-Pressed Green Detox Glow Juice
    21 => [
        [
            'name' => 'Sweetness & Ginger Kick',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Regular Detox with Fresh Ginger', 'price' => 0.00, 'default' => 1],
                ['name' => 'Extra Ginger & Lemon Kick', 'price' => 0.00, 'default' => 0],
                ['name' => 'Mild Green (No Ginger)', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Superfood Boosters',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Organic Chia Seeds', 'price' => 25.00, 'default' => 0],
                ['name' => 'Fresh Wheatgrass Shot', 'price' => 35.00, 'default' => 0],
                ['name' => 'Crushed Mint & Himalayan Pink Salt', 'price' => 15.00, 'default' => 0],
            ]
        ]
    ],

    // 22: Roasted Sweet Potato & Hummus Power Bowl
    22 => [
        [
            'name' => 'Dressing Choice',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Creamy Garlic Tahini', 'price' => 0.00, 'default' => 1],
                ['name' => 'Lemon Herb Vinaigrette', 'price' => 0.00, 'default' => 0],
                ['name' => 'Spicy Harissa Drizzle', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Bowl Toppings & Extra Cheese',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra House Garlic Hummus Scoop', 'price' => 40.00, 'default' => 0],
                ['name' => 'Crumbled Feta Cheese', 'price' => 40.00, 'default' => 0],
                ['name' => 'Kalamata Olives & Sun-dried Tomatoes', 'price' => 35.00, 'default' => 0],
                ['name' => 'Toasted Pine Nuts & Almonds', 'price' => 40.00, 'default' => 0],
            ]
        ]
    ],

    // 23: Peri Peri Cheesy Fries
    23 => [
        [
            'name' => 'Spice Dusting',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Mild Peri Peri', 'price' => 0.00, 'default' => 0],
                ['name' => 'Medium Spicy Peri Peri', 'price' => 0.00, 'default' => 1],
                ['name' => 'Extra Hot Ghost Peri Peri', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Cheesy Toppings & Dips',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Melted Liquid Cheddar Cheese', 'price' => 35.00, 'default' => 0],
                ['name' => 'Mozzarella Cheese Pull', 'price' => 40.00, 'default' => 0],
                ['name' => 'Pickled Jalapeno Slices', 'price' => 20.00, 'default' => 0],
                ['name' => 'Tangy Chipotle Dip', 'price' => 25.00, 'default' => 0],
                ['name' => 'Garlic Aioli Dip', 'price' => 25.00, 'default' => 0],
            ]
        ]
    ],

    // 24: Classic Ghee Roast Dosa
    24 => [
        [
            'name' => 'Crispiness & Roast Style',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Golden Crisp Ghee Roast', 'price' => 0.00, 'default' => 1],
                ['name' => 'Extra Paper Crispy Roast', 'price' => 0.00, 'default' => 0],
                ['name' => 'Soft Homestyle', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Dosa Toppings & Extra Cheese',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Grated Amul Cheese', 'price' => 40.00, 'default' => 0],
                ['name' => 'Gunpowder (Podi) Coating', 'price' => 25.00, 'default' => 0],
                ['name' => 'Extra Pure Cow Desi Ghee', 'price' => 30.00, 'default' => 0],
                ['name' => 'Extra Vegetable Sambar Bowl', 'price' => 25.00, 'default' => 0],
                ['name' => 'Extra Potato Masala Portion', 'price' => 25.00, 'default' => 0],
            ]
        ]
    ],

    // 25: Cold Brew Hazelnut Frappe
    25 => [
        [
            'name' => 'Sweetness & Coffee Strength',
            'type' => 'radio',
            'required' => 1,
            'options' => [
                ['name' => 'Balanced Sweet & Bold Coffee', 'price' => 0.00, 'default' => 1],
                ['name' => 'Strong Extra Espresso Shot', 'price' => 0.00, 'default' => 0],
                ['name' => 'Mild Less Sweet', 'price' => 0.00, 'default' => 0],
            ]
        ],
        [
            'name' => 'Frappe Toppings & Extras',
            'type' => 'checkbox',
            'required' => 0,
            'options' => [
                ['name' => 'Extra Hazelnut Syrup Swirl', 'price' => 25.00, 'default' => 0],
                ['name' => 'Whipped Cream Float', 'price' => 30.00, 'default' => 0],
                ['name' => 'Vanilla Ice Cream Scoop', 'price' => 35.00, 'default' => 0],
                ['name' => 'Dark Chocolate Chips', 'price' => 20.00, 'default' => 0],
                ['name' => 'Crushed Hazelnut Flakes', 'price' => 25.00, 'default' => 0],
            ]
        ]
    ]
];

$groupInsertStmt = $pdo->prepare("
    INSERT INTO `dish_customization_groups` (`item_id`, `group_name`, `selection_type`, `is_required`, `min_selection`, `max_selection`, `display_order`)
    VALUES (:iid, :gname, :stype, :req, :min_sel, :max_sel, :disp)
");

$optionInsertStmt = $pdo->prepare("
    INSERT INTO `dish_customization_options` (`group_id`, `option_name`, `price`, `is_default`, `is_available`, `display_order`)
    VALUES (:gid, :oname, :price, :isdef, 1, :disp)
");

$totalGroupsSeeded = 0;
$totalOptionsSeeded = 0;

foreach ($customizationData as $itemId => $groups) {
    $groupOrder = 1;
    foreach ($groups as $grp) {
        $groupInsertStmt->execute([
            ':iid' => $itemId,
            ':gname' => $grp['name'],
            ':stype' => $grp['type'],
            ':req' => $grp['required'],
            ':min_sel' => $grp['required'] ? 1 : 0,
            ':max_sel' => $grp['type'] === 'radio' ? 1 : 0,
            ':disp' => $groupOrder++
        ]);
        $groupId = (int)$pdo->lastInsertId();
        $totalGroupsSeeded++;

        $optionOrder = 1;
        foreach ($grp['options'] as $opt) {
            $optionInsertStmt->execute([
                ':gid' => $groupId,
                ':oname' => $opt['name'],
                ':price' => $opt['price'],
                ':isdef' => $opt['default'],
                ':disp' => $optionOrder++
            ]);
            $totalOptionsSeeded++;
        }
    }
}

echo "   -> Seeded {$totalGroupsSeeded} Customization Groups across 25 dishes.\n";
echo "   -> Seeded {$totalOptionsSeeded} Customization Options.\n";

// Update seeded order_items with sample customizations for realism
$pdo->exec("
    UPDATE `order_items` SET `customizations` = 'Medium Spicy, Extra Mirchi Ka Salan (+₹30.00)' WHERE `order_item_id` = 3;
    UPDATE `order_items` SET `customizations` = 'Spicy Dhaba Style, Extra Cheese Garnish (+₹35.00)' WHERE `order_item_id` = 5;
    UPDATE `order_items` SET `customizations` = 'Classic Red Mysore Chutney, Extra Grated Amul Cheese (+₹40.00)' WHERE `order_item_id` = 1;
    UPDATE `order_items` SET `customizations` = 'Spicy Chipotle (Medium), Extra Cheddar Cheese Slice (+₹35.00)' WHERE `order_item_id` = 9;
    UPDATE `order_items` SET `customizations` = 'Zesty Nashville Medium, Extra Molten Cheese Slice (+₹35.00)' WHERE `order_item_id` = 13;
    UPDATE `order_items` SET `customizations` = 'Citrus Lemon-Herb Vinaigrette, Extra Greek Feta Cheese (+₹45.00)' WHERE `order_item_id` = 7;
");
echo "6. Seed order_items customizations updated.\n";

echo "\nMigration finished successfully!\n";
