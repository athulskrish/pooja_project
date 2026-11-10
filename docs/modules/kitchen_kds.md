# Module: Kitchen Display System (KDS) & Queue Routing

## 1. Overview
The Kitchen Portal guides chefs through live ticket preparation using Weighted Shortest Job First (**WSJF**) ticket prioritization, station ticket printing (**KOT**), real-time polling, and rapid stock availability toggles.

## 2. WSJF Prioritization Engine (`includes/WSJFQueue.php`)
Active kitchen orders (`PLACED`, `CONFIRMED`, `COOKING`, `READY`) are ranked descending by `WSJF Score = Cost of Delay / Job Duration` via `WSJFQueue::getPrioritizedOrders()`:
- **Job Duration Estimate:**
  - `jobDuration = maxPrepTime + (totalPortions * 1.5)` (minutes, floored >= 1.0)
- **Cost of Delay (Immediate Orders):**
  - `costOfDelay = elapsedMinutes * urgencyWeight`, where `urgencyWeight` is:
    - `1.5` for `PLACED` / `CONFIRMED` *(waiting to start prep)*
    - `1.2` for `COOKING` *(currently on line)*
    - `0.5` for `READY` *(awaiting rider pickup)*
- **Advance Scheduled Orders (`is_scheduled = 1`):** Dynamic urgency adjustment surfaces scheduled tickets into active prep priority inside their target `scheduled_for` lead window while holding far-future tickets out of premature prep.

## 3. Core Entrypoints & Helpers
- **Views:** `kitchen/index.php` (`OrderService::getKitchenDashboardData()`), `kitchen/kot.php` (`OrderService::getKitchenKotData()`), `kitchen/login.php`, `kitchen/logout.php`.
- **Shared Render Helpers:** `includes/view_helpers.php` (`renderOrderCard()`, `formatKdsWaitTime()`, `getKdsPriorityBadge()`, `renderEmptyState()`).
- **AJAX / Realtime Endpoints:**
  - `kitchen/ajax/poll-orders.php` (`getKdsStateHash()` — light hash comparison for delta refreshes)
  - `kitchen/ajax/update-status.php` (`transitionOrderStatus()`)
  - `kitchen/ajax/toggle-stock.php` (`OrderService::toggleKitchenDishAvailability()`)
  - `api/live-stream.php` (role-scoped KDS/ops SSE stream)

## 4. State Transition Rules
1. KDS staff advance tickets through `PLACED` / `CONFIRMED` -> `COOKING` -> `READY`.
2. Marking an order `READY` sets `orders.ready_at = NOW()` inside `transitionOrderStatus()` and automatically invokes `autoAllocateReadyOrdersFcfs()` inside `dbTransactional()` + `FOR UPDATE` row locks.
3. All `kitchen/*` views and `kitchen/ajax/*` endpoints enforce `requireKitchen()`, and mutating requests enforce `verifyRequestCsrf()`.
