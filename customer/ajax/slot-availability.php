<?php
declare(strict_types=1);
/**
 * Customer AJAX API: Check FCFS Scheduled Meal Slot Capacity
 * Zippy - Online Food Ordering Platform
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$date = cleanInput((string)($_GET['date'] ?? date('Y-m-d')));
$slots = OrderService::getScheduledSlotAvailability($date);
$slotsByTime = [];
$slotsByLabel = [];
foreach ($slots as $slotInfo) {
    $slotsByTime[(string)$slotInfo['time']] = $slotInfo;
    $slotsByLabel[(string)$slotInfo['label']] = $slotInfo;
}

echo json_encode([
    'success' => true,
    'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d'),
    'policy' => 'FCFS',
    'slots' => $slots,
    'slots_by_time' => $slotsByTime,
    'slots_by_label' => $slotsByLabel,
]);
exit;
