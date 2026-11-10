<?php
declare(strict_types=1);

/**
 * Single Exponential Smoothing (SES) Demand Forecasting Engine
 * Predictive Item Velocity & Inventory Replenishment
 * Formula: Y_hat_{t+1} = alpha * Y_t + (1 - alpha) * Y_hat_t
 * Student: Pooja JP | MCSP-232
 * 
 * Aggregates REAL 7-period historical daily sales from `orders` and `order_items`
 * to forecast next-day and weekend culinary demand, days of supply, and stockout risk.
 */

require_once __DIR__ . '/functions.php';

class SESDemandForecast
{

    public const DEFAULT_ALPHA = 0.3; // Standard smoothing factor for restaurant demand
    public const HISTORY_PERIOD_DAYS = 7;

    /**
     * Forecast demand and stockout risk for all active dishes based on real 7-day sales.
     * 
     * @param float $alpha Smoothing constant (0 < alpha < 1)
     * @return array<int, array<string, mixed>>
     */
    public static function forecastCatalogDemand(float $alpha = self::DEFAULT_ALPHA): array
    {
        // Enforce boundary constraints on smoothing factor alpha
        $alpha = max(0.05, min(0.95, $alpha));

        // 1. Determine the 7-day calendar window based on most recent activity in DB
        $maxPlacedDate = dbFetchVal("
            SELECT MAX(DATE(placed_at)) 
            FROM `orders` 
            WHERE `order_status` != 'CANCELLED'
        ");

        $anchorTime = !empty($maxPlacedDate) ? (strtotime((string)$maxPlacedDate) ?: time()) : time();
        $trailingDates = [];
        for ($i = self::HISTORY_PERIOD_DAYS - 1; $i >= 0; $i--) {
            $trailingDates[] = date('Y-m-d', strtotime("-{$i} days", $anchorTime));
        }
        $windowStart = $trailingDates[0];
        $windowEnd = $trailingDates[count($trailingDates) - 1];

        // 2. Fetch dishes with current stock and all-time sales metrics
        $dishes = dbFetchAll("
            SELECT m.item_id, m.item_name, m.price, m.stock_quantity, m.is_available, m.is_veg,
                   c.category_name,
                   COALESCE(SUM(oi.quantity), 0) AS total_sold_qty,
                   COUNT(DISTINCT oi.order_id) AS distinct_orders_count
            FROM `menu_items` m
            JOIN `categories` c ON m.category_id = c.category_id
            LEFT JOIN `order_items` oi ON m.item_id = oi.item_id
            WHERE m.is_active = 1
            GROUP BY m.item_id
            ORDER BY total_sold_qty DESC, m.item_id ASC
        ");

        if (empty($dishes)) {
            return [];
        }

        // 3. Query real daily sales aggregated by item and date for the trailing 7 days in a single query
        $dailySalesRaw = dbFetchAll("
            SELECT oi.item_id, DATE(o.placed_at) AS sale_date, SUM(oi.quantity) AS daily_quantity
            FROM `order_items` oi
            JOIN `orders` o ON oi.order_id = o.order_id
            WHERE o.order_status != 'CANCELLED'
              AND DATE(o.placed_at) BETWEEN :wstart AND :wend
            GROUP BY oi.item_id, DATE(o.placed_at)
        ", [
            ':wstart' => $windowStart,
            ':wend'   => $windowEnd
        ]);

        $salesByItemDate = [];
        foreach ($dailySalesRaw as $row) {
            $iid = (int)$row['item_id'];
            $dt = (string)$row['sale_date'];
            $salesByItemDate[$iid][$dt] = (int)$row['daily_quantity'];
        }

        $forecastReport = [];

        foreach ($dishes as $dish) {
            $itemId = (int)$dish['item_id'];
            $currentStock = (int)$dish['stock_quantity'];
            $totalSoldAllTime = (int)$dish['total_sold_qty'];

            // 4. Construct chronological 7-period real historical daily demand series
            $history = [];
            $historyWithDates = [];
            $hasAnyWindowSale = false;

            foreach ($trailingDates as $dateStr) {
                $actualQty = (int)($salesByItemDate[$itemId][$dateStr] ?? 0);
                if ($actualQty > 0) {
                    $hasAnyWindowSale = true;
                }
                $history[] = $actualQty;
                $historyWithDates[$dateStr] = $actualQty;
            }

            // Defensive Fallback for items with no sales in this specific 7-day window
            // If historical orders exist prior to the window, estimate daily baseline moving average
            if (!$hasAnyWindowSale && $totalSoldAllTime > 0) {
                $dailyBaseline = max(1, (int)round($totalSoldAllTime / 14));
                $history = array_fill(0, self::HISTORY_PERIOD_DAYS, $dailyBaseline);
                foreach ($trailingDates as $idx => $dateStr) {
                    $historyWithDates[$dateStr] = $history[$idx];
                }
            }

            // 5. Apply Single Exponential Smoothing (SES) Recurrence:
            // S_1 = Y_0
            // S_t = alpha * Y_t + (1 - alpha) * S_{t-1}
            $smoothedValue = (float)$history[0];
            $periodCount = count($history);

            for ($t = 1; $t < $periodCount; $t++) {
                $actualDemand = (float)$history[$t];
                $smoothedValue = ($alpha * $actualDemand) + ((1.0 - $alpha) * $smoothedValue);
            }

            $predictedNextDay = (int)round($smoothedValue);
            $predictedWeekend = (int)round($smoothedValue * 2.2);

            // 6. Quantitative Risk & Replenishment Assessment
            $daysOfSupply = ($predictedNextDay > 0) ? round($currentStock / $predictedNextDay, 1) : 99.0;
            $isStockoutRisk = ($currentStock <= ($predictedNextDay * 1.5));
            $reorderQty = $isStockoutRisk ? max(20, $predictedWeekend - $currentStock + 15) : 0;

            $forecastReport[] = [
                'item_id' => $itemId,
                'item_name' => (string)$dish['item_name'],
                'category_name' => (string)$dish['category_name'],
                'price' => (float)$dish['price'],
                'current_stock' => $currentStock,
                'total_sold' => $totalSoldAllTime,
                'historical_series' => $history,
                'historical_dates' => $historyWithDates,
                'window_start' => $windowStart,
                'window_end' => $windowEnd,
                'alpha' => $alpha,
                'forecast_tomorrow' => $predictedNextDay,
                'forecast_weekend' => $predictedWeekend,
                'days_of_supply' => $daysOfSupply,
                'stockout_risk' => $isStockoutRisk,
                'reorder_recommended_qty' => $reorderQty
            ];
        }

        return $forecastReport;
    }

    /**
     * Forecast demand for a single menu item
     * 
     * @param int $itemId
     * @param float $alpha
     * @return array<string, mixed>|null
     */
    public static function forecastSingleItem(int $itemId, float $alpha = self::DEFAULT_ALPHA): ?array
    {
        $all = self::forecastCatalogDemand($alpha);
        foreach ($all as $item) {
            if ($item['item_id'] === $itemId) {
                return $item;
            }
        }
        return null;
    }
}
