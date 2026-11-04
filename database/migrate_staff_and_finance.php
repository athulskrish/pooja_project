<?php
/**
 * Migration & Seeder: Staff Profiles, Salaries, and Money Transactions
 * Zippy - Online Food Ordering Platform
 * Exactly 1 Chef (Sajeev) and 3 Drivers (Shaji, Kichu, Abhi)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDBConnection();
if (!$pdo) {
    die("Database connection failed.\n");
}

echo "1. Creating `staff_profiles` table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `staff_profiles` (
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
");
echo "   -> `staff_profiles` table ready.\n";

echo "2. Creating `staff_salary_payouts` table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `staff_salary_payouts` (
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
");
echo "   -> `staff_salary_payouts` table ready.\n";

echo "3. Creating `money_transactions` table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `money_transactions` (
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
        CONSTRAINT `fk_money_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "   -> `money_transactions` table ready.\n";

echo "4. Removing legacy extra staff (Vikram, Priya, Ramesh, Rajesh) if present...\n";
$pdo->exec("DELETE FROM `users` WHERE `email` IN ('vikram@zippy.com', 'priya.kitchen@zippy.com', 'ramesh.rider@zippy.com', 'rajesh.rider@zippy.com')");

echo "5. Seeding Exact Staff Roster (1 Chef: Sajeev, 3 Drivers: Shaji, Kichu, Abhi)...\n";
$staffList = [
    // Chef Sajeev (User ID 5)
    [
        'email' => 'chef@zippy.com',
        'dept' => 'KITCHEN',
        'desig' => 'Executive Head Chef',
        'spec' => 'Master Culinary Operations & WSJF Queue Management',
        'status' => 'ON_DUTY',
        'shift' => '08:00 AM - 05:00 PM',
        'veh' => null,
        'dl' => null,
        'sal' => 35000.00,
        'rate' => 40.00,
        'joined' => '2025-01-01',
        'cod' => 0.00,
        'orders' => 940,
        'rating' => 4.95
    ],
    // Driver 1: Shaji (User ID 6)
    [
        'email' => 'shaji@zippy.com',
        'dept' => 'DELIVERY',
        'desig' => 'Senior Delivery Captain',
        'spec' => 'Bellandur & Outer Ring Road Express Hub',
        'status' => 'ON_DUTY',
        'shift' => '09:00 AM - 06:00 PM',
        'veh' => 'Hero Electric Optima (KA-03-EM-4921)',
        'dl' => 'DL-0120170048192',
        'sal' => 20000.00,
        'rate' => 45.00,
        'joined' => '2025-03-15',
        'cod' => 0.00,
        'orders' => 810,
        'rating' => 4.90
    ],
    // Driver 2: Kichu (User ID 7)
    [
        'email' => 'kichu@zippy.com',
        'dept' => 'DELIVERY',
        'desig' => 'Rapid Fleet Partner',
        'spec' => 'Indiranagar & Domlur Rapid Dispatch',
        'status' => 'ON_DUTY',
        'shift' => '10:00 AM - 07:00 PM',
        'veh' => 'Honda Activa 6G (KA-05-EJ-8120)',
        'dl' => 'DL-0420180091823',
        'sal' => 19000.00,
        'rate' => 45.00,
        'joined' => '2025-06-20',
        'cod' => 0.00,
        'orders' => 520,
        'rating' => 4.85
    ],
    // Driver 3: Abhi (User ID 8)
    [
        'email' => 'abhi@zippy.com',
        'dept' => 'DELIVERY',
        'desig' => 'Rapid Fleet Partner',
        'spec' => 'Whitefield & ITPL Rapid Delivery',
        'status' => 'ON_DUTY',
        'shift' => '11:00 AM - 08:00 PM',
        'veh' => 'TVS NTORQ (KA-04-HF-4491)',
        'dl' => 'DL-0320190019204',
        'sal' => 18500.00,
        'rate' => 40.00,
        'joined' => '2025-11-01',
        'cod' => 0.00,
        'orders' => 480,
        'rating' => 4.88
    ],
];

foreach ($staffList as $staff) {
    $user = db_fetch_one("SELECT user_id FROM `users` WHERE `email` = :em", [':em' => $staff['email']]);
    if (!$user) {
        continue;
    }
    $uid = (int)$user['user_id'];

    db_query("
        INSERT INTO `staff_profiles` 
        (`user_id`, `department`, `designation`, `specialization`, `duty_status`, `shift_timings`, `vehicle_details`, `driving_license`, `base_salary`, `hourly_or_order_rate`, `joined_date`, `cod_balance`, `total_orders_handled`, `rating`) 
        VALUES (:uid, :dept, :desig, :spec, :duty, :shift, :veh, :dl, :sal, :rate, :joined, :cod, :ord, :rat)
        ON DUPLICATE KEY UPDATE 
            `department` = VALUES(`department`),
            `designation` = VALUES(`designation`),
            `specialization` = VALUES(`specialization`),
            `duty_status` = VALUES(`duty_status`),
            `shift_timings` = VALUES(`shift_timings`),
            `vehicle_details` = VALUES(`vehicle_details`),
            `driving_license` = VALUES(`driving_license`),
            `base_salary` = VALUES(`base_salary`),
            `hourly_or_order_rate` = VALUES(`hourly_or_order_rate`),
            `cod_balance` = VALUES(`cod_balance`),
            `total_orders_handled` = VALUES(`total_orders_handled`),
            `rating` = VALUES(`rating`)
    ", [
        ':uid' => $uid,
        ':dept' => $staff['dept'],
        ':desig' => $staff['desig'],
        ':spec' => $staff['spec'],
        ':duty' => $staff['status'],
        ':shift' => $staff['shift'],
        ':veh' => $staff['veh'],
        ':dl' => $staff['dl'],
        ':sal' => $staff['sal'],
        ':rate' => $staff['rate'],
        ':joined' => $staff['joined'],
        ':cod' => $staff['cod'],
        ':ord' => $staff['orders'],
        ':rat' => $staff['rating']
    ]);
}
echo "   -> Staff profiles upserted for Chef Sajeev, Shaji, Kichu, Abhi.\n";

echo "6. Seeding Historical Salary Payouts...\n";
$chefId = (int)(db_fetch_val("SELECT user_id FROM `users` WHERE `email` = 'chef@zippy.com'") ?: 5);
$shajiId = (int)(db_fetch_val("SELECT user_id FROM `users` WHERE `email` = 'shaji@zippy.com'") ?: 6);
$kichuId = (int)(db_fetch_val("SELECT user_id FROM `users` WHERE `email` = 'kichu@zippy.com'") ?: 7);
$abhiId = (int)(db_fetch_val("SELECT user_id FROM `users` WHERE `email` = 'abhi@zippy.com'") ?: 8);

$salarySeedData = [
    [
        'user_id' => $chefId,
        'month_year' => 'October 2026',
        'base' => 35000.00,
        'incentive' => 2500.00,
        'deduction' => 0.00,
        'net' => 37500.00,
        'mode' => 'BANK_TRANSFER',
        'ref' => 'NEFT-SAL-CHEF-202610-9812',
        'status' => 'PAID',
        'paid_at' => '2026-10-31 18:30:00',
        'notes' => 'Monthly executive culinary salary & peak preparation bonus'
    ],
    [
        'user_id' => $chefId,
        'month_year' => 'September 2026',
        'base' => 35000.00,
        'incentive' => 2100.00,
        'deduction' => 0.00,
        'net' => 37100.00,
        'mode' => 'BANK_TRANSFER',
        'ref' => 'NEFT-SAL-CHEF-202609-8472',
        'status' => 'PAID',
        'paid_at' => '2026-09-30 18:00:00',
        'notes' => 'Monthly executive culinary salary'
    ],
    [
        'user_id' => $shajiId,
        'month_year' => 'October 2026',
        'base' => 20000.00,
        'incentive' => 4350.00,
        'deduction' => 0.00,
        'net' => 24350.00,
        'mode' => 'UPI',
        'ref' => 'UPI-PAYOUT-SHAJI-202610-5512',
        'status' => 'PAID',
        'paid_at' => '2026-10-31 19:15:00',
        'notes' => 'Base stipend + 97 fulfilled doorstep deliveries'
    ],
    [
        'user_id' => $shajiId,
        'month_year' => 'September 2026',
        'base' => 20000.00,
        'incentive' => 3900.00,
        'deduction' => 0.00,
        'net' => 23900.00,
        'mode' => 'UPI',
        'ref' => 'UPI-PAYOUT-SHAJI-202609-4419',
        'status' => 'PAID',
        'paid_at' => '2026-09-30 19:00:00',
        'notes' => 'Base stipend + 87 fulfilled doorstep deliveries'
    ],
    [
        'user_id' => $kichuId,
        'month_year' => 'October 2026',
        'base' => 19000.00,
        'incentive' => 3200.00,
        'deduction' => 0.00,
        'net' => 22200.00,
        'mode' => 'UPI',
        'ref' => 'UPI-PAYOUT-KICHU-202610-1192',
        'status' => 'PAID',
        'paid_at' => '2026-10-31 19:30:00',
        'notes' => 'Base stipend + 72 fulfilled deliveries'
    ],
    [
        'user_id' => $abhiId,
        'month_year' => 'October 2026',
        'base' => 18500.00,
        'incentive' => 2800.00,
        'deduction' => 0.00,
        'net' => 21300.00,
        'mode' => 'UPI',
        'ref' => 'UPI-PAYOUT-ABHI-202610-7731',
        'status' => 'PAID',
        'paid_at' => '2026-10-31 19:45:00',
        'notes' => 'Base stipend + 64 fulfilled deliveries'
    ]
];

foreach ($salarySeedData as $sal) {
    if ($sal['user_id'] <= 0) continue;
    $exists = db_fetch_val("SELECT payout_id FROM `staff_salary_payouts` WHERE `user_id` = :uid AND `month_year` = :my", [
        ':uid' => $sal['user_id'],
        ':my' => $sal['month_year']
    ]);
    if (!$exists) {
        db_query("
            INSERT INTO `staff_salary_payouts` 
            (`user_id`, `month_year`, `base_amount`, `incentives_amount`, `deductions_amount`, `net_paid`, `payment_mode`, `transaction_reference`, `payout_status`, `paid_at`, `notes`) 
            VALUES (:uid, :my, :base, :inc, :ded, :net, :mode, :ref, :st, :paid_at, :notes)
        ", [
            ':uid' => $sal['user_id'],
            ':my' => $sal['month_year'],
            ':base' => $sal['base'],
            ':inc' => $sal['incentive'],
            ':ded' => $sal['deduction'],
            ':net' => $sal['net'],
            ':mode' => $sal['mode'],
            ':ref' => $sal['ref'],
            ':st' => $sal['status'],
            ':paid_at' => $sal['paid_at'],
            ':notes' => $sal['notes']
        ]);
    }
}
echo "   -> Salary payout records seeded.\n";

echo "7. Backfilling Money Transactions from existing orders & payments...\n";
$orders = db_fetch_all("
    SELECT o.*, p.payment_mode, p.payment_status, p.transaction_reference, p.amount_paid, p.payment_timestamp,
           d.delivery_partner_name
    FROM `orders` o
    LEFT JOIN `payments` p ON o.order_id = p.order_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
");

foreach ($orders as $ord) {
    $orderId = (int)$ord['order_id'];
    $mode = $ord['payment_mode'] ?: 'COD';
    $amount = (float)$ord['total_amount'];
    $status = $ord['payment_status'] ?: 'PENDING';
    $ref = $ord['transaction_reference'] ?: ('TXN-ORD-' . $orderId);
    $rider = $ord['delivery_partner_name'] ?: 'Shaji (Rider)';
    $time = $ord['placed_at'];

    // Check if txn already exists
    $existingTxn = db_fetch_val("SELECT txn_id FROM `money_transactions` WHERE `order_id` = :oid AND `txn_category` != 'CUSTOMER_REFUND'", [':oid' => $orderId]);
    if (!$existingTxn) {
        $isCod = ($mode === 'COD');
        $txnCategory = $isCod ? 'CUSTOMER_PAYMENT_COD' : 'CUSTOMER_PAYMENT_ONLINE';
        $payMethod = match($mode) {
            'UPI' => 'UPI',
            'CREDIT_CARD' => 'CREDIT_CARD',
            'DEBIT_CARD' => 'DEBIT_CARD',
            'NET_BANKING' => 'NET_BANKING',
            default => 'COD_CASH'
        };

        $txnStatus = 'COMPLETED';
        if ($isCod) {
            $txnStatus = ($ord['order_status'] === 'DELIVERED') ? 'COMPLETED' : 'PENDING';
        }

        $handledBy = $isCod ? "Rider: {$rider}" : "Razorpay Digital Gateway";
        $notes = $isCod 
            ? ($txnStatus === 'COMPLETED' ? "Cash on delivery collected at doorstep by {$rider}" : "Cash collection pending delivery handover")
            : "Prepaid online settlement via {$mode}";

        $txnNum = 'TXN-' . date('Ym', strtotime($time)) . '-' . str_pad((string)$orderId, 5, '0', STR_PAD_LEFT);

        db_query("
            INSERT INTO `money_transactions` 
            (`txn_number`, `order_id`, `user_id`, `txn_category`, `flow_direction`, `payment_method`, `amount`, `status`, `reference_id`, `handled_by`, `notes`, `created_at`) 
            VALUES (:tnum, :oid, :uid, :cat, 'INWARD', :method, :amt, :st, :ref, :hby, :notes, :created)
        ", [
            ':tnum' => $txnNum,
            ':oid' => $orderId,
            ':uid' => $ord['user_id'],
            ':cat' => $txnCategory,
            ':method' => $payMethod,
            ':amt' => $amount,
            ':st' => $txnStatus,
            ':ref' => $ref,
            ':hby' => $handledBy,
            ':notes' => $notes,
            ':created' => $time
        ]);
    }
}
echo "   -> Money transactions backfilled for all orders.\n";
echo "MIGRATION COMPLETE!\n";
