<?php
declare(strict_types=1);
/**
 * Real-Time Server-Sent Events (SSE) Push Stream
 * Zippy - Online Food Ordering Platform
 *
 * Replaces fixed-interval client polling across Kitchen KDS, Delivery Fleet,
 * Admin Live Orders, and Customer Live Order Tracking using native EventSource.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Authentication required.']);
    exit;
}

$portal = strtolower(trim((string)($_GET['portal'] ?? 'kitchen')));
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$clientHash = trim((string)($_GET['last_hash'] ?? ''));
$currentUser = getCurrentUser();
$loggedInUserId = (int)($currentUser['id'] ?? 0);

// Strict per-portal RBAC verification BEFORE releasing session lock
if ($portal === 'kitchen') {
    if (!hasRole(['KITCHEN', 'ADMIN'])) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Forbidden. Kitchen or Admin role required.']);
        exit;
    }
} elseif ($portal === 'delivery') {
    if (!hasRole(['RIDER', 'ADMIN'])) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Forbidden. Rider or Admin role required.']);
        exit;
    }
} elseif ($portal === 'admin') {
    if (!hasRole('ADMIN')) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Forbidden. Administrator role required.']);
        exit;
    }
} elseif ($portal === 'customer') {
    if ($orderId <= 0 || !canAccessOrder($orderId, $loggedInUserId)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Forbidden. Cannot stream order status for another account.']);
        exit;
    }
} else {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Unsupported SSE portal stream.']);
    exit;
}

// CRITICAL: Release PHP session lock immediately so parallel AJAX/navigation requests do not block
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Configure SSE stream headers and clean output buffers
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
@ini_set('zlib.output_compression', '0');
@set_time_limit(35);

$emitEvent = static function (string $event, array $payload): void {
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n\n";
    @flush();
};

echo "retry: 2000\n\n";
@flush();

$computeFingerprint = static function () use ($portal, $orderId): array {
    if ($portal === 'customer') {
        $row = dbFetchOne("
            SELECT o.order_id, o.order_status, o.placed_at,
                   d.delivery_status, d.route_progress, d.distance_remaining_km, d.estimated_minutes_remaining, d.delivery_partner_name
            FROM `orders` o
            LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
            WHERE o.order_id = :oid
            LIMIT 1
        ", [':oid' => $orderId]);
        $hash = md5((string)json_encode($row ?: []));
        return ['hash' => $hash, 'payload' => $row ?: []];
    }

    $kdsHash = getKdsStateHash();
    if ($portal === 'admin') {
        $ticketsOpen = 0;
        try {
            $ticketsOpen = (int)(dbFetchVal("SELECT COUNT(*) FROM `support_tickets` WHERE `ticket_status` IN ('OPEN', 'IN_PROGRESS')") ?: 0);
        } catch (Throwable $e) {
            $ticketsOpen = 0;
        }
        $hash = md5($kdsHash . '|' . $ticketsOpen);
        return ['hash' => $hash, 'payload' => ['kds_hash' => $kdsHash, 'open_tickets' => $ticketsOpen]];
    }

    return ['hash' => $kdsHash, 'payload' => ['kds_hash' => $kdsHash]];
};

$portalEvent = match ($portal) {
    'kitchen' => 'kds_update',
    'delivery' => 'delivery_update',
    'admin' => 'admin_update',
    'customer' => 'order_update',
    default => 'state_update',
};
$onceMode = !empty($_GET['once']);

$lastSentHash = $clientHash;
$maxSeconds = 24;
$startedAt = time();
$tick = 0;

$initialSnapshot = $computeFingerprint();
if ($lastSentHash === '') {
    $lastSentHash = $initialSnapshot['hash'];
}

$emitEvent('connected', [
    'portal' => $portal,
    'event' => $portalEvent,
    'hash' => $initialSnapshot['hash'],
    'timestamp' => time(),
]);

if ($onceMode) {
    $emitEvent($portalEvent, [
        'portal' => $portal,
        'hash' => $initialSnapshot['hash'],
        'timestamp' => time(),
        'data' => $initialSnapshot['payload'],
    ]);
    exit;
}

while (!connection_aborted() && (time() - $startedAt) < $maxSeconds) {
    $snapshot = ($tick === 0 && $clientHash !== '') ? $initialSnapshot : $computeFingerprint();
    $currentHash = $snapshot['hash'];

    if ($currentHash !== $lastSentHash) {
        $emitEvent($portalEvent, [
            'portal' => $portal,
            'hash' => $currentHash,
            'timestamp' => time(),
            'data' => $snapshot['payload'],
        ]);
        $lastSentHash = $currentHash;
    } elseif ($tick > 0 && ($tick % 10) === 0) {
        $emitEvent('heartbeat', [
            'portal' => $portal,
            'hash' => $currentHash,
            'timestamp' => time(),
        ]);
    }

    $tick++;
    sleep(1);
}

$emitEvent('reconnect', ['portal' => $portal, 'hash' => $lastSentHash, 'timestamp' => time()]);
exit;
