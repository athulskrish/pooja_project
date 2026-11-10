# Module: Computational Algorithms

All analytical engines live in `includes/`, declare `strict_types=1`, execute parameterized PDO queries via `dbFetchAll()` / `dbFetchVal()` / `getDbConnection()`, and follow PSR-12 class/method formatting.

---

## 1. Apriori Association Rule Mining (`includes/AprioriEngine.php`)
Computes **"Frequently Bought Together"** cross-sell suggestions from completed/placed historical orders.
- **Thresholds & Constants:**
  - `DEFAULT_MIN_SUPPORT = 0.05` *(5% minimum co-occurrence)*
  - `DEFAULT_MIN_CONFIDENCE = 0.20` *(20% conditional likelihood)*
  - `DEFAULT_MIN_LIFT = 1.0` *(positive correlation filter)*
  - `MIN_TRANSACTIONS_THRESHOLD = 5`, `MAX_ITEMSET_K = 3`, `CACHE_TTL_SECONDS = 60`
- **Key Methods:**
  - `AprioriEngine::getRecommendations(array $cartItemIds, int $limit = 4, float $minSupport = ..., float $minConfidence = ...): array`
  - `AprioriEngine::mineAssociationRules(...)`, `runApriori(...)`, `getAssociationRulesReport(...)`, `clearCache()`
  - `private static function getTransactionsFromDb(): array` *(reads multi-item baskets for non-cancelled orders)*
- **Governance:** Enforces `is_active = 1` when hydrating dish metadata or fallback recommendations (`populateDishDetails()`, `getFallbackItems()`).

---

## 2. Single Exponential Smoothing (`includes/SESDemandForecast.php`)
Predicts next-day and 7-day culinary demand per active dish using real daily sales history.
- **Formula:**
  $$\hat{Y}_{t+1} = \alpha Y_t + (1 - \alpha)\hat{Y}_t$$
  - `DEFAULT_ALPHA = 0.3` *(clamped to `[0.05, 0.95]`)*
  - `HISTORY_PERIOD_DAYS = 7`
- **Key Methods:**
  - `SESDemandForecast::forecastCatalogDemand(float $alpha = self::DEFAULT_ALPHA): array`
  - `SESDemandForecast::forecastSingleItem(int $itemId, float $alpha = self::DEFAULT_ALPHA): array`
- **Governance:** Filters catalog dishes by `is_active = 1` so archived menu items do not distort restock alerts.

---

## 3. Weighted Shortest Job First (`includes/WSJFQueue.php`)
Maximizes kitchen throughput and prevents starvation of waiting customer orders.
- **Core Implementation (`WSJFQueue::getPrioritizedOrders()`):**
  - `jobDuration = maxPrepTime + (totalPortions * 1.5)` *(floored to at least `1.0` minute)*
  - **Immediate orders (`is_scheduled = 0`):**
    - `costOfDelay = elapsedMinutes * urgencyWeight`
    - `urgencyWeight`: `1.5` (`PLACED` / `CONFIRMED`), `1.2` (`COOKING`), `0.5` (`READY`)
    - `WSJF Score = costOfDelay / jobDuration`
  - **Scheduled orders (`is_scheduled = 1`):** Computed dynamically relative to target `scheduled_for` lead window so upcoming advance orders escalate cleanly ahead of prep deadline without blocking urgent immediate tickets prematurely.

---

## 4. First-Come First-Served (`includes/FCFSQueue.php` & `includes/functions.php`)
Enforces fair chronological dispatch assignment and SLA exception monitoring for ready orders and delivery riders.
- **Key Functions / Methods:**
  - `FCFSQueue::getPrioritizedOrders()` & `FCFSQueue::getTriageExceptions()` *(sorts queued/dispatched orders strictly chronologically and flags SLA breaches)*
  - `autoAllocateReadyOrdersFcfs()` in `includes/functions.php` *(runs inside `dbTransactional()` locking ready orders, free `ON_DUTY` riders, and `delivery_dispatches` via `FOR UPDATE`, assigning at most one active order per rider)*
