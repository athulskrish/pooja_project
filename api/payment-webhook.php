<?php
/**
 * Asynchronous Payment Gateway Webhook Handler
 * Zippy - Online Food Ordering Platform
 * Student: Pooja JP | Course: MCSP-232
 * Implements: UC-08 & Process 3.4 (Callback Webhook & Payment Verification)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed. Use POST.']);
    exit;
}

// Read raw request body
$rawPayload = file_get_contents('php://input');
$data = json_decode($rawPayload, true);

// If form POST instead of JSON
if (empty($data)) {
    $data = $_POST;
}

$orderId = (int)($data['order_id'] ?? ($data['payload']['payment']['entity']['notes']['order_id'] ?? 0));
$paymentId = clean_input($data['payment_id'] ?? ($data['payload']['payment']['entity']['id'] ?? 'pay_webhook_' . bin2hex(random_bytes(6))));
$status = strtoupper(clean_input($data['status'] ?? ($data['event'] ?? 'SUCCESSFUL')));
$amount = (float)($data['amount'] ?? ($data['payload']['payment']['entity']['amount'] ?? 0));

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid order_id.']);
    exit;
}

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Check order exists
    $orderStmt = $pdo->prepare("SELECT * FROM `orders` WHERE `order_id` = :id FOR UPDATE");
    $orderStmt->execute([':id' => $orderId]);
    $order = $orderStmt->fetch();

    if (!$order) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => "Order #{$orderId} not found."]);
        exit;
    }

    $isSuccess = str_contains($status, 'SUCCESS') || str_contains($status, 'CAPTURED') || str_contains($status, 'AUTHORIZED');
    $payStatus = $isSuccess ? 'SUCCESSFUL' : 'FAILED';

    // Update payment record
    $payStmt = $pdo->prepare("
        UPDATE `payments` 
        SET `payment_status` = :pstat, 
            `transaction_reference` = :tx,
            `payment_timestamp` = NOW()
        WHERE `order_id` = :oid
    ");
    $payStmt->execute([
        ':pstat' => $payStatus,
        ':tx' => $paymentId,
        ':oid' => $orderId
    ]);

    if ($isSuccess && $order['order_status'] === 'PLACED') {
        $ordUpd = $pdo->prepare("UPDATE `orders` SET `order_status` = 'CONFIRMED' WHERE `order_id` = :oid");
        $ordUpd->execute([':oid' => $orderId]);

        $auditStmt = $pdo->prepare("
            INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`)
            VALUES (:oid, 'PLACED', 'CONFIRMED', :uid, NOW())
        ");
        $auditStmt->execute([':oid' => $orderId, ':uid' => $order['user_id']]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'payment_status' => $payStatus,
        'transaction_reference' => $paymentId,
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => 'Webhook received and processed atomically.'
    ]);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
