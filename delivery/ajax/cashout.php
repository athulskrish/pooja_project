<?php
declare(strict_types=1);

/**
 * Instant IMPS Rider Payout / Cashout API Endpoint
 * Zippy - Online Food Ordering Platform
 * MCSP-232: MCA Major Project | Student: Pooja JP
 * 
 * Verifies rider's real trip earnings against previously cashed out amounts,
 * generates an immutable IMPS banking reference, and records atomic ACID entries in:
 * 1. `staff_salary_payouts` (Staff Payroll Subledger)
 * 2. `money_transactions` (Master Corporate Inward/Outward Audit Ledger)
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce Rider Authentication
requireRider();
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method Not Allowed. Use POST.'
    ]);
    exit;
}

// Defense-in-depth CSRF Token Validation
$submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCsrfToken((string)$submittedToken)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Security token invalid or expired. Please refresh the page and try again.'
    ]);
    exit;
}

$riderId = (int)$currentUser['id'];
$riderName = (string)($currentUser['name'] ?? 'Delivery Partner');

// 1. Calculate Real Accumulated Earnings & Available Payout Balance
$earnings = getRiderEarningsSummary($riderId, $riderName);
$availableBalance = (float)$earnings['available_balance'];

if ($availableBalance <= 0.0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'No balance available to cash out. Complete active deliveries to earn trip fees and tips.',
        'available_balance' => 0.00
    ]);
    exit;
}

$pdo = getDbConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed. Payout could not be processed.'
    ]);
    exit;
}

try {
    // 2. BEGIN ACID TRANSACTION
    $pdo->beginTransaction();

    // Re-verify under pessimistic lock to prevent concurrent double-cashout race conditions
    $alreadyPaidStmt = $pdo->prepare("
        SELECT COALESCE(SUM(`net_paid`), 0) 
        FROM `staff_salary_payouts` 
        WHERE `user_id` = :uid 
          AND `payout_status` = 'PAID'
          AND (`transaction_reference` LIKE 'IMPS-%' OR `notes` LIKE '%Instant IMPS%')
        FOR UPDATE
    ");
    $alreadyPaidStmt->execute([':uid' => $riderId]);
    $lockedPaid = (float)$alreadyPaidStmt->fetchColumn();

    $recheckedBalance = max(0.00, round($earnings['total_earned'] - $lockedPaid, 2));
    if ($recheckedBalance <= 0.0) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'All earnings have already been cashed out.',
            'available_balance' => 0.00
        ]);
        exit;
    }

    $cashoutAmount = $recheckedBalance;
    $monthYear = date('F Y');
    $impsReference = 'IMPS-' . date('Ymd') . '-' . str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

    // Compute Base vs Incentive breakdown proportionally
    $baseRatio = $earnings['total_earned'] > 0
        ? ($earnings['base_fare_total'] / $earnings['total_earned'])
        : 0.5625;
    $baseAmount = round($cashoutAmount * $baseRatio, 2);
    $incentivesAmount = round($cashoutAmount - $baseAmount, 2);

    $notes = "Instant IMPS Fleet Cashout to HDFC Bank A/C ••••4019";

    // 3. Insert into `staff_salary_payouts`
    $payoutStmt = $pdo->prepare("
        INSERT INTO `staff_salary_payouts` 
        (`user_id`, `month_year`, `base_amount`, `incentives_amount`, `deductions_amount`, `net_paid`, `payment_mode`, `transaction_reference`, `payout_status`, `paid_at`, `notes`) 
        VALUES (:uid, :my, :base, :inc, 0.00, :net, 'BANK_TRANSFER', :ref, 'PAID', NOW(), :notes)
    ");
    $payoutStmt->execute([
        ':uid'   => $riderId,
        ':my'    => $monthYear,
        ':base'  => $baseAmount,
        ':inc'   => $incentivesAmount,
        ':net'   => $cashoutAmount,
        ':ref'   => $impsReference,
        ':notes' => $notes
    ]);

    // 4. Record into `money_transactions` Central Ledger
    recordMoneyTransaction(
        null,
        $riderId,
        'STAFF_SALARY_PAYOUT',
        'OUTWARD',
        'BANK_TRANSFER',
        $cashoutAmount,
        'COMPLETED',
        $impsReference,
        'Rider: ' . $riderName,
        "Instant IMPS fleet payout disbursed to {$riderName} (HDFC Bank ••••4019)"
    );

    // Commit ACID transaction
    $pdo->commit();

    echo json_encode([
        'success'                     => true,
        'amount'                      => $cashoutAmount,
        'amount_formatted'            => formatInr($cashoutAmount),
        'reference_id'                => $impsReference,
        'remaining_balance'           => 0.00,
        'remaining_balance_formatted' => formatInr(0.00),
        'bank_name'                   => 'HDFC Bank Ltd',
        'account_mask'                => '••••4019',
        'ifsc'                        => 'HDFC0001234',
        'timestamp'                   => date('d M Y, h:i A'),
        'message'                     => "Instant IMPS payout of " . formatInr($cashoutAmount) . " successfully transferred to HDFC Bank A/C ••••4019 (Ref: {$impsReference})."
    ], JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Cashout Error for Rider {$riderId}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while processing your bank payout. Please try again.'
    ]);
}
