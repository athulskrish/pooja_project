<?php
declare(strict_types=1);
/**
 * Shared Presentation View Helpers
 * Extracted from portal templates per MVC/PSR-12 decoupling standards.
 * Zippy - Online Food Ordering Platform
 */

/**
 * Compute uppercase initials from a full name (e.g. "Pooja JP" => "PJ").
 */
function getUserInitials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    if (!empty($parts[0])) {
        $initials .= mb_strtoupper(mb_substr($parts[0], 0, 1));
    }
    if ($parts && count($parts) > 1 && !empty(end($parts))) {
        $initials .= mb_strtoupper(mb_substr((string)end($parts), 0, 1));
    }
    return $initials ?: 'U';
}

/**
 * Format KDS wait time into a compact label and CSS tag class.
 *
 * @return array{label: string, class: string}
 */
function formatKdsWaitTime(int $minutes, ?string $placedAt = null): array
{
    if ($minutes < 1) {
        return ['label' => '< 1m', 'class' => 'tag-wait-fresh'];
    } elseif ($minutes < 60) {
        $cls = ($minutes >= 20) ? 'tag-wait-alert' : (($minutes >= 10) ? 'tag-wait-warm' : 'tag-wait-fresh');
        return ['label' => "{$minutes}m", 'class' => $cls];
    } elseif ($minutes < 1440) {
        $h = floor($minutes / 60);
        $m = $minutes % 60;
        $txt = $m > 0 ? "{$h}h {$m}m" : "{$h}h";
        return ['label' => "{$txt} DELAY", 'class' => 'tag-wait-alert'];
    }
    return ['label' => 'OVERDUE', 'class' => 'tag-wait-alert'];
}

/**
 * Resolve normalized priority badge metadata for a KDS order ticket.
 *
 * @param array<string, mixed> $ticket
 * @return array{label: string, class: string, icon: string}
 */
function getKdsPriorityBadge(array $ticket): array
{
    $badge = $ticket['urgency_badge'] ?? null;
    $label = is_array($badge) ? ($badge['label'] ?? 'NORMAL') : 'NORMAL';
    if ($label === 'CRITICAL DELAY') {
        return ['label' => 'RUSH', 'class' => 'tag-priority-critical', 'icon' => 'bi-fire'];
    } elseif ($label === 'HIGH PRIORITY') {
        return ['label' => 'PRIORITY', 'class' => 'tag-priority-high', 'icon' => 'bi-lightning-charge-fill'];
    }
    return ['label' => 'STANDARD', 'class' => 'tag-priority-normal', 'icon' => 'bi-check-circle'];
}

/**
 * Render an individual customer order card with items and contextual actions.
 *
 * @param array<string, mixed> $order
 * @param array<int, array<string, mixed>> $items
 */
function renderOrderCard(array $order, array $items): void
{
    $status = strtoupper((string)$order['order_status']);
    $isOngoing = in_array($status, ['PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY'], true);
    $isDelivered = ($status === 'DELIVERED');
    $isCancelled = ($status === 'CANCELLED');

    // Check 60-second cancellation window
    $placedTs = (int)strtotime((string)$order['placed_at']);
    $elapsedSec = time() - $placedTs;
    $canCancel = ($elapsedSec <= 60 && in_array($status, ['PLACED', 'CONFIRMED'], true));
    $remainingSec = max(0, 60 - $elapsedSec);

    // Card border classification
    $borderClass = $isOngoing ? 'is-ongoing' : ($isDelivered ? 'is-delivered' : 'is-cancelled');

    // Build search string for client-side filtering
    $itemNames = array_map(static fn(array $it): string => (string)$it['item_name'], $items);
    $searchCorpus = strtolower(
        "ZIP-" . $order['order_id'] . " " .
        $order['order_status'] . " " .
        implode(' ', $itemNames) . " " .
        ($order['delivery_partner_name'] ?? '') . " " .
        ($order['order_notes'] ?? '')
    );

    // JSON payload for 1-click Reorder feature
    $reorderPayload = array_map(static function (array $it): array {
        return [
            'id' => (int)$it['item_id'],
            'name' => $it['item_name'],
            'price' => (float)$it['unit_price'],
            'isVeg' => (bool)$it['is_veg'],
            'restaurant' => 'Meghana Foods',
            'qty' => (int)$it['quantity'],
            'customizationText' => $it['customizations'] ?? '',
            'customizationTotal' => (float)($it['customization_total'] ?? 0),
            'cartItemId' => !empty($it['customizations']) ? ($it['item_id'] . '_' . md5((string)$it['customizations'])) : (string)$it['item_id'],
        ];
    }, $items);
    $reorderJson = htmlspecialchars((string)json_encode($reorderPayload), ENT_QUOTES, 'UTF-8');
    ?>
    <div class="col-12 order-card-wrapper" data-search="<?= e($searchCorpus) ?>" data-status="<?= e(strtolower($status)) ?>">
      <div class="order-card p-4 shadow-sm mb-4 <?= $borderClass ?>">

        <!-- Card Header: Order ID, Date, Status -->
        <div class="d-flex flex-wrap align-items-center justify-content-between border-bottom pb-3 mb-3 gap-2">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="fw-bold fs-5 text-dark font-outfit">Order #ZIP-<?= (int)$order['order_id'] ?></span>
            <span class="text-muted small">
              <i class="bi bi-calendar3 me-1"></i><?= date('d M Y, h:i A', $placedTs) ?>
            </span>

            <?php if (!empty($order['is_scheduled'])): ?>
              <span class="badge rounded-pill text-white shadow-xs px-2 py-1" style="background:#7C3AED; font-size:0.75rem;">
                <i class="bi bi-calendar2-check-fill me-1"></i> ADVANCE: <?= e($order['scheduled_slot'] ?: date('d M, h:i A', (int)strtotime((string)$order['scheduled_for']))) ?>
              </span>
            <?php endif; ?>

            <?php if ($isOngoing): ?>
              <span class="badge bg-mint text-forest fw-semibold px-2 py-1 rounded-pill small d-inline-flex align-items-center gap-1">
                <span class="pulsing-beacon"></span> Active Order
              </span>
            <?php endif; ?>

            <?php if ($canCancel): ?>
              <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle rounded-pill px-2 py-1 small cancel-timer-badge" data-seconds="<?= $remainingSec ?>" data-order-id="<?= (int)$order['order_id'] ?>">
                <i class="bi bi-stopwatch me-1"></i> <span class="timer-countdown"><?= $remainingSec ?>s</span> left to cancel
              </span>
            <?php endif; ?>
          </div>

          <div class="d-flex align-items-center gap-2">
            <?= getStatusBadge((string)$order['order_status']) ?>
            <span class="fs-5 fw-bold text-forest font-outfit"><?= formatInr($order['total_amount']) ?></span>
          </div>
        </div>

        <!-- Card Body: Grid of Items, Details, Actions -->
        <div class="row g-4 align-items-start">

          <!-- Column 1: Ordered Items -->
          <div class="col-lg-5 col-md-6">
            <div class="small fw-bold text-muted text-uppercase mb-2" style="font-size: 0.72rem; letter-spacing: 0.5px;">
              <i class="bi bi-basket2 me-1 text-success"></i> Ordered Dishes (<?= count($items) ?>):
            </div>

            <div class="d-flex flex-column">
              <?php foreach ($items as $item): ?>
                <div class="order-item-row d-flex align-items-start gap-2 small py-1">
                  <div class="<?= $item['is_veg'] ? 'veg-box' : 'nonveg-box' ?> mt-1" title="<?= $item['is_veg'] ? 'Vegetarian' : 'Non-Vegetarian' ?>">
                    <?php if ($item['is_veg']): ?>
                      <div class="dot"></div>
                    <?php else: ?>
                      <div class="triangle"></div>
                    <?php endif; ?>
                  </div>
                  <span class="badge bg-light text-dark border px-2 py-1 fw-bold mt-1"><?= (int)$item['quantity'] ?>x</span>
                  <div class="flex-grow-1 overflow-hidden">
                    <span class="text-dark fw-semibold text-truncate d-block" style="max-width: 260px;"><?= e($item['item_name']) ?></span>
                    <?php if (!empty($item['customizations'])): ?>
                      <div class="mt-1">
                        <?= formatCustomizationsHtml((string)$item['customizations']) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <span class="text-muted ms-auto fw-medium"><?= formatInr($item['line_total']) ?></span>
                </div>
              <?php endforeach; ?>
            </div>

            <?php if (!empty($order['order_notes'])): ?>
              <div class="mt-2 p-2 rounded-3 bg-light border border-light-subtle small text-muted">
                <i class="bi bi-chat-left-dots text-forest me-1"></i>
                <strong>Cooking Note:</strong> <?= e($order['order_notes']) ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Column 2: Delivery & Payment Details -->
          <div class="col-lg-4 col-md-6 border-start-lg ps-lg-4">

            <!-- Delivery Location -->
            <div class="small fw-bold text-muted text-uppercase mb-2" style="font-size: 0.72rem; letter-spacing: 0.5px;">
              <i class="bi bi-geo-alt me-1 text-success"></i> Delivery Address:
            </div>
            <div class="small mb-3">
              <span class="badge bg-light text-dark border mb-1"><?= e($order['address_type'] ?? 'HOME') ?></span>
              <div class="text-dark fw-medium text-truncate">
                <?= e($order['address_line'] ?? 'Indiranagar') ?>
                <?= !empty($order['landmark']) ? ', ' . e($order['landmark']) : '' ?>
              </div>
              <div class="text-muted">Bangalore - <?= e($order['pincode'] ?? '560038') ?></div>
            </div>

            <!-- Payment Details -->
            <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
              <i class="bi bi-credit-card me-1 text-success"></i> Payment Summary:
            </div>
            <div class="small d-flex flex-wrap align-items-center gap-1 mb-2">
              <span class="badge bg-light text-dark border"><?= e($order['payment_mode'] ?? 'COD') ?></span>
              <?php if (($order['payment_status'] ?? '') === 'SUCCESSFUL'): ?>
                <span class="badge bg-success bg-opacity-25 text-success">Paid</span>
              <?php elseif (($order['payment_status'] ?? '') === 'REFUNDED'): ?>
                <span class="badge bg-danger bg-opacity-25 text-danger"><i class="bi bi-arrow-counterclockwise"></i> 100% Refunded</span>
              <?php else: ?>
                <span class="badge bg-warning bg-opacity-25 text-dark"><?= e($order['payment_status'] ?? 'Pending') ?></span>
              <?php endif; ?>
            </div>

            <!-- Rider & OTP (For Ongoing) -->
            <?php if ($isOngoing && !empty($order['delivery_otp'])): ?>
              <div class="p-2 rounded-3 bg-mint-soft border border-mint d-flex align-items-center justify-content-between mt-2">
                <div>
                  <span class="small text-muted d-block" style="font-size: 0.72rem;">Share Delivery OTP:</span>
                  <strong class="font-monospace fs-5 text-dark"><?= e($order['delivery_otp']) ?></strong>
                </div>
                <div>
                  <i class="bi bi-shield-check text-forest fs-4"></i>
                </div>
              </div>
            <?php endif; ?>

            <?php if (!empty($order['delivery_partner_name']) && $order['delivery_partner_name'] !== 'Zippy Express Rider'): ?>
              <div class="small text-muted mt-2">
                <i class="bi bi-person-badge text-forest me-1"></i> Rider: <strong><?= e($order['delivery_partner_name']) ?></strong>
                <?php if (!empty($order['delivery_partner_phone'])): ?>
                  <a href="tel:<?= e($order['delivery_partner_phone']) ?>" class="text-decoration-none text-forest ms-1">
                    <i class="bi bi-telephone-fill"></i>
                  </a>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <!-- Customer Review Display (For Completed) -->
            <?php if ($isDelivered && !empty($order['food_rating'])): ?>
              <div class="p-2 rounded-3 bg-light border mt-2 small">
                <div class="text-warning fw-bold">
                  <?php for ($s = 1; $s <= 5; $s++): ?>
                    <i class="bi bi-star<?= $s <= (int)$order['food_rating'] ? '-fill' : '' ?>"></i>
                  <?php endfor; ?>
                  <span class="text-dark ms-1"><?= (int)$order['food_rating'] ?>.0</span>
                </div>
                <?php if (!empty($order['review_comments'])): ?>
                  <div class="text-muted fst-italic mt-1 text-truncate">"<?= e($order['review_comments']) ?>"</div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <!-- Cancellation Reason (For Cancelled) -->
            <?php if ($isCancelled): ?>
              <div class="p-2 rounded-3 bg-danger bg-opacity-10 border border-danger-subtle text-danger small mt-2">
                <i class="bi bi-info-circle-fill me-1"></i>
                Order cancelled. Refund of <?= formatInr($order['total_amount']) ?> initiated to original source.
              </div>
            <?php endif; ?>

          </div>

          <!-- Column 3: Smart Action Buttons -->
          <div class="col-lg-3 text-lg-end border-start-lg ps-lg-4">
            <div class="d-flex flex-column gap-2">

              <!-- Ongoing Actions -->
              <?php if ($isOngoing): ?>
                <a href="<?= CUSTOMER_URL ?>/order-status.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-success btn-sm rounded-pill fw-semibold py-2 shadow-sm d-flex align-items-center justify-content-center gap-2" style="background:#2E6F40; border-color:#2E6F40;">
                  <i class="bi bi-geo-alt-fill"></i> Live Tracker
                </a>

                <?php if ($canCancel): ?>
                  <button type="button" class="btn btn-outline-danger btn-sm rounded-pill py-2 fw-semibold cancel-order-trigger-btn" data-order-id="<?= (int)$order['order_id'] ?>" data-bs-toggle="modal" data-bs-target="#cancelConfirmModal">
                    <i class="bi bi-x-circle me-1"></i> Cancel Order
                  </button>
                <?php endif; ?>

                <a href="<?= CUSTOMER_URL ?>/invoice.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-light btn-sm rounded-pill border py-2 text-muted d-flex align-items-center justify-content-center gap-1" target="_blank">
                  <i class="bi bi-file-earmark-text text-forest"></i> Tax Invoice
                </a>

                <a href="<?= CUSTOMER_URL ?>/help.php?order_id=%23ZIP-<?= (int)$order['order_id'] ?>" class="btn btn-light btn-sm rounded-pill border py-2 text-muted d-flex align-items-center justify-content-center gap-1">
                  <i class="bi bi-question-circle text-primary"></i> Need Help?
                </a>
              <?php endif; ?>

              <!-- Completed Actions -->
              <?php if ($isDelivered): ?>
                <button type="button" class="btn btn-success btn-sm rounded-pill fw-semibold py-2 shadow-sm d-flex align-items-center justify-content-center gap-2" style="background:#2E6F40; border-color:#2E6F40;" onclick="reorderItems(<?= $reorderJson ?>, <?= (int)$order['order_id'] ?>)">
                  <i class="bi bi-arrow-repeat"></i> Reorder Food
                </button>

                <?php if (empty($order['food_rating'])): ?>
                  <a href="<?= CUSTOMER_URL ?>/rate-order.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-warning btn-sm rounded-pill py-2 text-dark fw-bold d-flex align-items-center justify-content-center gap-1 shadow-sm" style="background:#FFC107; border-color:#FFC107;">
                    <i class="bi bi-star-fill text-dark"></i> Rate Food ⭐
                  </a>
                <?php else: ?>
                  <div class="p-2 rounded-3 bg-light border text-center">
                    <div class="small fw-bold text-muted mb-1" style="font-size:0.7rem;">YOUR FOOD RATING</div>
                    <div class="d-flex align-items-center justify-content-center gap-1 text-warning mb-1 fs-6">
                      <?= str_repeat('<i class="bi bi-star-fill"></i>', (int)$order['food_rating']) . str_repeat('<i class="bi bi-star text-muted opacity-50"></i>', 5 - (int)$order['food_rating']) ?>
                      <span class="text-dark fw-bold ms-1" style="font-size:0.75rem;"><?= (int)$order['food_rating'] ?>/5</span>
                    </div>
                    <a href="<?= CUSTOMER_URL ?>/rate-order.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-sm btn-link p-0 text-success text-decoration-none fw-semibold" style="font-size:0.72rem;">
                      <i class="bi bi-pencil-square me-1"></i>Edit Review ⭐
                    </a>
                  </div>
                <?php endif; ?>

                <a href="<?= CUSTOMER_URL ?>/invoice.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-light btn-sm rounded-pill border py-2 text-muted d-flex align-items-center justify-content-center gap-1" target="_blank">
                  <i class="bi bi-receipt text-forest"></i> Tax Invoice
                </a>

                <a href="<?= CUSTOMER_URL ?>/help.php?order_id=%23ZIP-<?= (int)$order['order_id'] ?>" class="btn btn-light btn-sm rounded-pill border py-2 text-muted d-flex align-items-center justify-content-center gap-1">
                  <i class="bi bi-chat-left-heart text-success"></i> Feedback & Support
                </a>
              <?php endif; ?>

              <!-- Cancelled Actions -->
              <?php if ($isCancelled): ?>
                <button type="button" class="btn btn-outline-success btn-sm rounded-pill fw-semibold py-2 d-flex align-items-center justify-content-center gap-2" onclick="reorderItems(<?= $reorderJson ?>, <?= (int)$order['order_id'] ?>)">
                  <i class="bi bi-arrow-repeat"></i> Order Again
                </button>

                <a href="<?= CUSTOMER_URL ?>/invoice.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-light btn-sm rounded-pill border py-2 text-muted d-flex align-items-center justify-content-center gap-1" target="_blank">
                  <i class="bi bi-file-earmark-text text-secondary"></i> Cancellation Receipt
                </a>

                <a href="<?= CUSTOMER_URL ?>/help.php?order_id=%23ZIP-<?= (int)$order['order_id'] ?>" class="btn btn-light btn-sm rounded-pill border py-2 text-muted d-flex align-items-center justify-content-center gap-1">
                  <i class="bi bi-headset text-danger"></i> Refund Support
                </a>
              <?php endif; ?>

            </div>
          </div>

        </div>

      </div>
    </div>
    <?php
}

/**
 * Render tailored empty state for an order-history tab.
 */
function renderEmptyState(string $tabType): void
{
    $states = [
        'all' => [
            'icon' => 'bi-receipt',
            'title' => 'No orders placed yet',
            'desc' => 'Explore our delicious biryanis, curries, and rolls to place your first feast.',
            'btnText' => 'Browse Dishes',
            'btnLink' => CUSTOMER_URL . '/index.php#dishes',
        ],
        'ongoing' => [
            'icon' => 'bi-bicycle',
            'title' => 'No ongoing orders right now',
            'desc' => 'You do not have any active meals cooking in the kitchen or out for delivery. Hungry?',
            'btnText' => 'Order Food Now',
            'btnLink' => CUSTOMER_URL . '/index.php#dishes',
        ],
        'scheduled' => [
            'icon' => 'bi-calendar2-check',
            'title' => 'No advance scheduled orders',
            'desc' => 'You do not have any advance pre-booked meals at this time. Plan ahead for lunch or dinner!',
            'btnText' => 'Schedule a Meal',
            'btnLink' => CUSTOMER_URL . '/index.php#dishes',
        ],
        'completed' => [
            'icon' => 'bi-check2-circle',
            'title' => 'No completed orders yet',
            'desc' => 'Your delivered feasts, GST tax receipts, and customer feedback will be preserved here.',
            'btnText' => 'Explore Menu',
            'btnLink' => CUSTOMER_URL . '/index.php#dishes',
        ],
        'cancelled' => [
            'icon' => 'bi-shield-check',
            'title' => 'No cancelled orders',
            'desc' => 'Great news! You have a pristine record with zero cancellations.',
            'btnText' => 'Order Food',
            'btnLink' => CUSTOMER_URL . '/index.php#dishes',
        ],
    ];

    $info = $states[$tabType] ?? $states['all'];
    ?>
    <div class="card border-0 rounded-4 shadow-sm p-5 text-center bg-white my-3">
      <div class="mb-3">
        <i class="bi <?= $info['icon'] ?> text-muted opacity-50" style="font-size: 3.5rem;"></i>
      </div>
      <h5 class="fw-bold text-dark font-outfit"><?= e($info['title']) ?></h5>
      <p class="text-muted small mb-4" style="max-width: 420px; margin: 0 auto;"><?= e($info['desc']) ?></p>
      <div>
        <a href="<?= e($info['btnLink']) ?>" class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">
          <?= e($info['btnText']) ?>
        </a>
      </div>
    </div>
    <?php
}

/**
 * Evaluate dish nutritional metadata and personal dietary conflicts for the signed-in customer.
 *
 * @param array<string, mixed> $dish
 * @param array<int, string> $userDietaryPrefs
 * @return array{allergens: array<int, string>, conflicts: array<int, string>, calories: int, protein: int, carbs: int, fat: int, spice_level: string}
 */
function evaluateDishDietaryConflicts(array $dish, array $userDietaryPrefs = []): array
{
    $rawAllergens = strtolower(trim((string)($dish['allergens'] ?? '')));
    $allergens = array_values(array_filter(array_map('trim', explode(',', $rawAllergens))));
    $calories = (int)($dish['calories_kcal'] ?? 0);
    $protein = (int)($dish['protein_g'] ?? 0);
    $carbs = (int)($dish['carbs_g'] ?? 0);
    $fat = (int)($dish['fat_g'] ?? 0);
    $spiceLevel = strtolower(trim((string)($dish['spice_level'] ?? 'medium')));
    if ($spiceLevel === '') {
        $spiceLevel = 'medium';
    }

    $conflicts = [];
    $prefs = array_flip(array_map('strtolower', $userDietaryPrefs));

    if (isset($prefs['veg_only']) && empty($dish['is_veg'])) {
        $conflicts[] = 'Non-Veg';
    }
    if (isset($prefs['dairy_free']) && in_array('dairy', $allergens, true)) {
        $conflicts[] = 'Contains Dairy';
    }
    if (isset($prefs['nut_free']) && in_array('nuts', $allergens, true)) {
        $conflicts[] = 'Contains Nuts';
    }
    if (isset($prefs['gluten_free']) && in_array('gluten', $allergens, true)) {
        $conflicts[] = 'Contains Gluten';
    }
    if (isset($prefs['mild_spice']) && in_array($spiceLevel, ['spicy', 'extra_hot', 'hot', 'extra_spicy'], true)) {
        $conflicts[] = 'Spicy Dish';
    }

    return [
        'allergens' => $allergens,
        'conflicts' => $conflicts,
        'calories' => $calories,
        'protein' => $protein,
        'carbs' => $carbs,
        'fat' => $fat,
        'spice_level' => $spiceLevel,
    ];
}

/**
 * Render interactive Favorite Heart button HTML for storefront/restaurant/search dish cards.
 */
function renderDishFavoriteButtonHtml(int $itemId, bool $isFavorited): string
{
    $cls = $isFavorited ? 'is-favorited' : '';
    $icon = $isFavorited ? 'bi-heart-fill text-danger' : 'bi-heart text-dark';
    $pressed = $isFavorited ? 'true' : 'false';
    return '<button type="button" class="btn btn-light btn-sm rounded-circle shadow-sm d-inline-flex align-items-center justify-content-center dish-fav-btn ' . $cls . '" '
        . 'data-item-id="' . $itemId . '" aria-pressed="' . $pressed . '" title="Toggle Favorite" '
        . 'style="width:32px;height:32px;padding:0;backdrop-filter:blur(4px);background:rgba(255,255,255,0.92);" '
        . 'onclick="event.stopPropagation(); toggleDishFavorite(' . $itemId . ', this);">'
        . '<i class="bi ' . $icon . '"></i></button>';
}

/**
 * Render uniform macro nutrition, spice, allergen pills, and customer dietary warnings.
 *
 * @param array<string, mixed> $dish
 * @param array<int, string> $userDietaryPrefs
 */
function renderDishDietaryMetadataHtml(array $dish, array $userDietaryPrefs = []): string
{
    $meta = evaluateDishDietaryConflicts($dish, $userDietaryPrefs);
    $allergenMap = function_exists('getDishAllergenOptions') ? getDishAllergenOptions() : [];
    $parts = [];

    if ($meta['calories'] > 0 || $meta['protein'] > 0) {
        $macroText = [];
        if ($meta['calories'] > 0) {
            $macroText[] = $meta['calories'] . ' kcal';
        }
        if ($meta['protein'] > 0) {
            $macroText[] = $meta['protein'] . 'g protein';
        }
        $parts[] = '<span class="badge bg-light text-dark border fw-normal" style="font-size:0.66rem;">'
            . '<i class="bi bi-fire text-warning me-1"></i>' . e(implode(' · ', $macroText)) . '</span>';
    }

    $spiceBadge = match ($meta['spice_level']) {
        'mild' => '<span class="badge bg-success-subtle text-success border border-success-subtle fw-normal" style="font-size:0.66rem;">🌿 Mild</span>',
        'spicy', 'hot', 'extra_hot', 'extra_spicy' => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-normal" style="font-size:0.66rem;">🌶️ Spicy</span>',
        default => '',
    };
    if ($spiceBadge !== '') {
        $parts[] = $spiceBadge;
    }

    if (!empty($meta['allergens'])) {
        $labels = [];
        foreach ($meta['allergens'] as $a) {
            $labels[] = ucfirst($a);
        }
        $titleList = [];
        foreach ($meta['allergens'] as $a) {
            $titleList[] = $allergenMap[$a] ?? ucfirst($a);
        }
        $parts[] = '<span class="badge bg-warning-subtle text-dark border border-warning-subtle fw-normal" style="font-size:0.66rem;" title="Contains: ' . e(implode(', ', $titleList)) . '">'
            . '<i class="bi bi-exclamation-circle text-warning me-1"></i>' . e(implode(', ', $labels)) . '</span>';
    }

    $html = '';
    if (!empty($parts)) {
        $html .= '<div class="d-flex flex-wrap gap-1 mb-2">' . implode('', $parts) . '</div>';
    }
    if (!empty($meta['conflicts'])) {
        $html .= '<div class="alert alert-warning py-1 px-2 mb-2 d-flex align-items-center gap-1 rounded-3 border-warning" style="font-size:0.7rem;">'
            . '<i class="bi bi-shield-exclamation text-danger"></i>'
            . '<span>Dietary Alert: <strong>' . e(implode(' · ', $meta['conflicts'])) . '</strong></span></div>';
    }

    return $html;
}
