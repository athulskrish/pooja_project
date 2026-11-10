<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/FCFSQueue.php';

echo "=== TESTING FCFS QUEUE ===" . PHP_EOL;
$orders = FCFSQueue::getPrioritizedOrders();
echo "Active orders found in queue: " . count($orders) . PHP_EOL;

foreach ($orders as $o) {
    echo sprintf(
        "Order #%d | FCFS Pos #%d | Status: %-10s | Elapsed: %2dm | MaxPrep: %2dm | SLA Diff: %3dm | Badge: %s | BorderClass: %s" . PHP_EOL,
        $o['order_id'],
        $o['fcfs_position'],
        $o['order_status'],
        $o['elapsed_minutes'],
        $o['max_prep_time'],
        $o['sla_diff'],
        $o['sla_badge']['label'],
        $o['card_sla_class'] ?: 'none'
    );
}

echo PHP_EOL . "=== TESTING OPERATIONAL TRIAGE EXCEPTIONS ===" . PHP_EOL;
$exceptions = FCFSQueue::getTriageExceptions();
echo "Active exceptions detected: " . count($exceptions) . PHP_EOL;

foreach ($exceptions as $ex) {
    echo sprintf(
        "[%s] [%s] %s -> %s" . PHP_EOL,
        $ex['severity'],
        $ex['type'],
        $ex['title'],
        $ex['action_url']
    );
}

echo PHP_EOL . "=== ALL CHECKS COMPLETED SUCCESSFULLY ===" . PHP_EOL;
