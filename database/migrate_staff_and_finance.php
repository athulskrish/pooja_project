<?php
/**
 * Migration & Seeder: Staff Profiles, Attendance, Salaries, and Money Transactions
 * Zippy - Online Food Ordering Platform
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

echo "4. Seeding Additional Kitchen & Delivery Users...\n";
$newStaffUsers = [
    // Kitchen (Role 3)
    [
        'role_id' => 3,
        'full_name' => 'Chef Vikram Rathore',
        'email' => 'vikram@zippy.com',
        'phone_number' => '+91 97410 88990',
        'dept' => 'KITCHEN',
        'designation' => 'Sous Chef & Tandoor Master',
        'spec' => 'North Indian, Tandoori Kebabs & Dum Biryani',
        'status' => 'ON_DUTY',
        'shift' => '11:00 AM - 08:00 PM',
        'salary' => 28000.00,
        'rate' => 35.00,
        'joined' => '2025-08-15',
        'orders' => 412,
        'rating' => 4.90
    ],
    [
        'role_id' => 3,
        'full_name' => 'Priya Sharma (Prep Cook)',
        'email' => 'priya.kitchen@zippy.com',
        'phone_number' => '+91 97410 77665',
        'dept' => 'KITCHEN',
        'designation' => 'Line Cook & Cold Bar Specialist',
        'spec' => 'Healthy Salads, Quinoa Bowls & Dosa Griddle',
        'status' => 'ON_BREAK',
        'shift' => '08:00 AM - 04:00 PM',
        'salary' => 22000.00,
        'rate' => 30.00,
        'joined' => '2026-01-10',
        'orders' => 285,
        'rating' => 4.75
    ],
    // Delivery (Role 4)
    [
        'role_id' => 4,
        'full_name' => 'Ramesh Rao (Rider)',
        'email' => 'ramesh.rider@zippy.com',
        'phone_number' => '+91 96320 11223',
        'dept' => 'DELIVERY',
        'designation' => 'Delivery Fleet Specialist',
        'spec' => 'Koramangala & HSR Zone Rapid Dispatch',
        'status' => 'ON_DUTY',
        'shift' => '10:00 AM - 06:00 PM',
        'vehicle' => 'Hero Electric Optima (KA-05-EJ-8120)',
        'dl' => 'DL-0420180091823',
        'salary' => 18000.00,
        'rate' => 45.00,
        'joined' => '2025-11-01',
        'orders' => 520,
        'rating' => 4.85,
        'cod' => 850.00
    ],
    [
        'role_id' => 4,
        'full_name' => 'Rajesh Varma (Rider)',
        'email' => 'rajesh.rider@zippy.com',
        'phone_number' => '+91 96320 33445',
        'dept' => 'DELIVERY',
        'designation' => 'Senior Delivery Partner',
        'spec' => 'Indiranagar Central & Domlur Quick Delivery',
        'status' => 'ON_DUTY',
        'shift' => '04:00 PM - 12:00 AM',
        'vehicle' => 'Honda Activa 6G (KA-03-HF-4491)',
        'dl' => 'DL-0320190019204',
        'salary' => 19500.00,
        'rate' => 50.00,
        'joined' => '2025-06-20',
        'orders' => 680,
        'rating' => 4.92,
        'cod' => 420.00
    ]
];

$pwdHash = password_hash('password123', PASSWORD_BCRYPT);

foreach ($newStaffUsers as $staff) {
    $existing = db_fetch_one("SELECT user_id FROM `users` WHERE `email` = :em", [':em' => $staff['email']]);
    if (!$existing) {
        $stmt = $pdo->prepare("
            INSERT INTO `users` (`role_id`, `full_name`, `email`, `phone_number`, `password_hash`, `is_active`) 
            VALUES (:rid, :fn, :em, :ph, :pw, 1)
        ");
        $stmt->execute([
            ':rid' => $staff['role_id'],
            ':fn'  => $staff['full_name'],
            ':em'  => $staff['email'],
            ':ph'  => $staff['phone_number'],
            ':pw'  => $pwdHash
        ]);
        $uid = (int)$pdo->lastInsertId();
    } else {
        $uid = (int)$existing['user_id'];
    }

    // Upsert Staff Profile
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
            `base_salary` = VALUES(`base_salary`),
            `cod_balance` = VALUES(`cod_balance`)
    ", [
        ':uid' => $uid,
        ':dept' => $staff['dept'],
        ':desig' => $staff['designation'],
        ':spec' => $staff['spec'] ?? null,
        ':duty' => $staff['status'],
        ':shift' => $staff['shift'],
        ':veh' => $staff['vehicle'] ?? null,
        ':dl' => $staff['dl'] ?? null,
        ':sal' => $staff['salary'],
        ':rate' => $staff['rate'],
        ':joined' => $staff['joined'],
        ':cod' => $staff['cod'] ?? 0.00,
        ':ord' => $staff['orders'],
        ':rat' => $staff['rating']
    ]);
}

// 5. Seed Primary Staff (Chef Sanjeev & Suresh Kumar)
$chefId = (int)(db_fetch_val("SELECT user_id FROM `users` WHERE `email` = 'chef@zippy.com'") ?: 4);
if ($chefId > 0) {
    db_query("
        INSERT INTO `staff_profiles` 
        (`user_id`, `department`, `designation`, `specialization`, `duty_status`, `shift_timings`, `base_salary`, `hourly_or_order_rate`, `joined_date`, `total_orders_handled`, `rating`) 
        VALUES (:uid, 'KITCHEN', 'Executive Head Chef', 'Master Culinary Operations & WSJF Queue Management', 'ON_DUTY', '08:00 AM - 05:00 PM', 35000.00, 40.00, '2025-01-01', 940, 4.95)
        ON DUPLICATE KEY UPDATE `duty_status` = 'ON_DUTY', `base_salary` = 35000.00
    ", [':uid' => $chefId]);
}

$riderId = (int)(db_fetch_val("SELECT user_id FROM `users` WHERE `email` = 'rider@zippy.com'") ?: 5);
if ($riderId > 0) {
    db_query("
        INSERT INTO `staff_profiles` 
        (`user_id`, `department`, `designation`, `specialization`, `duty_status`, `shift_timings`, `vehicle_details`, `driving_license`, `base_salary`, `hourly_or_order_rate`, `joined_date`, `cod_balance`, `total_orders_handled`, `rating`) 
        VALUES (:uid, 'DELIVERY', 'Senior Fleet Delivery Captain', 'Indiranagar & Koramangala Hub Specialist', 'ON_DUTY', '09:00 AM - 06:00 PM', 'Ather 450X (KA-03-EM-4921)', 'DL-0120170048192', 20000.00, 45.00, '2025-03-15', 794.85, 810, 4.88)
        ON DUPLICATE KEY UPDATE `duty_status` = 'ON_DUTY', `base_salary` = 20000.00
    ", [':uid' => $riderId]);
}
echo "   -> Staff profiles upserted successfully.\n";

echo "6. Seeding Historical Salary Payouts...\n";
$salarySeedData = [
    // Chef Sanjeev
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
    // Rider Suresh Kumar
    [
        'user_id' => $riderId,
        'month_year' => 'October 2026',
        'base' => 20000.00,
        'incentive' => 4350.00,
        'deduction' => 0.00,
        'net' => 24350.00,
        'mode' => 'UPI',
        'ref' => 'UPI-PAYOUT-RIDER-202610-5512',
        'status' => 'PAID',
        'paid_at' => '2026-10-31 19:15:00',
        'notes' => 'Base stipend + 97 fulfilled doorstep deliveries'
    ],
    [
        'user_id' => $riderId,
        'month_year' => 'September 2026',
        'base' => 20000.00,
        'incentive' => 3900.00,
        'deduction' => 0.00,
        'net' => 23900.00,
        'mode' => 'UPI',
        'ref' => 'UPI-PAYOUT-RIDER-202609-4419',
        'status' => 'PAID',
        'paid_at' => '2026-09-30 19:00:00',
        'notes' => 'Base stipend + 87 fulfilled doorstep deliveries'
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
    $rider = $ord['delivery_partner_name'] ?: 'Zippy Express Rider';
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

    // If cancelled, record refund
    if ($ord['order_status'] === 'CANCELLED') {
        $refundTxn = db_fetch_val("SELECT txn_id FROM `money_transactions` WHERE `order_id` = :oid AND `txn_category` = 'CUSTOMER_REFUND'", [':oid' => $orderId]);
        if (!$refundTxn) {
            $refNum = 'REF-' . date('Ym', strtotime($time)) . '-' . str_pad((string)$orderId, 5, '0', STR_PAD_LEFT);
            db_query("
                INSERT INTO `money_transactions` 
                (`txn_number`, `order_id`, `user_id`, `txn_category`, `flow_direction`, `payment_method`, `amount`, `status`, `reference_id`, `handled_by`, `notes`, `created_at`) 
                VALUES (:tnum, :oid, :uid, 'CUSTOMER_REFUND', 'OUTWARD', 'UPI', :amt, 'REFUNDED', :ref, 'Automated Refund Engine', 'Order cancelled - full refund reversed to source account', NOW())
            ", [
                ':tnum' => $refNum,
                ':oid' => $orderId,
                ':uid' => $ord['user_id'],
                ':amt' => $amount,
                ':ref' => 'REF-' . $ref
            ]);
        }
    }
}
echo "   -> Money transactions backfilled for all orders.\n";
echo "MIGRATION COMPLETE!\n";
