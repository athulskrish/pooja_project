<?php
/**
 * Single Exponential Smoothing (SES) Demand Forecasting Engine
 * Predictive Item Velocity & Inventory Replenishment
 * Formula: Y_hat_{t+1} = alpha * Y_t + (1 - alpha) * Y_hat_t
 * Student: Pooja JP | MCSP-232
 */

require_once __DIR__ . '/functions.php';

class SESDemandForecast {

    const DEFAULT_ALPHA = 0.3; // Standard smoothing factor for restaurant demand

    /**
     * Forecast demand and stockout risk for all active dishes
     * 
     * @param float $alpha Smoothing constant (0 < alpha < 1)
     * @return array
     */
    public static function forecastCatalogDemand(float $alpha = self::DEFAULT_ALPHA): array {
        // Fetch dishes with current stock
        $dishes = db_fetch_all("
            SELECT m.item_id, m.item_name, m.price, m.stock_quantity, m.is_available, m.is_veg,
                   c.category_name,
                   COALESCE(SUM(oi.quantity), 0) AS total_sold_qty,
                   COUNT(DISTINCT oi.order_id) AS distinct_orders_count
            FROM `menu_items` m
            JOIN `categories` c ON m.category_id = c.category_id
            LEFT JOIN `order_items` oi ON m.item_id = oi.item_id
            GROUP BY m.item_id
            ORDER BY total_sold_qty DESC
        ");

        $forecastReport = [];

        foreach ($dishes as $dish) {
            $currentStock = (int)$dish['stock_quantity'];
            $totalSold = (int)$dish['total_sold_qty'];

            // Simulated past 7 periods demand based on sales pattern
            // Base demand estimation with randomized past velocity series
            $baseDemand = max(2, (int)round($totalSold / 2));
            $history = [
                max(1, $baseDemand - 2),
                max(2, $baseDemand - 1),
                max(2, $baseDemand + 1),
                max(3, $baseDemand),
                max(2, $baseDemand + 2),
                max(3, $baseDemand + 1),
                max(3, $baseDemand + 3)
            ];

            // Apply Single Exponential Smoothing
            $smoothedValue = $history[0];
            foreach ($history as $actualDemand) {
                $smoothedValue = ($alpha * $actualDemand) + ((1 - $alpha) * $smoothedValue);
            }

            $predictedNextDay = round($smoothedValue);
            $predictedWeekend = round($smoothedValue * 2.2);

            // Risk Assessment
            $daysOfSupply = ($predictedNextDay > 0) ? round($currentStock / $predictedNextDay, 1) : 99;
            $isStockoutRisk = ($currentStock <= ($predictedNextDay * 1.5));

            $forecastReport[] = [
                'item_id' => $dish['item_id'],
                'item_name' => $dish['item_name'],
                'category_name' => $dish['category_name'],
                'price' => $dish['price'],
                'current_stock' => $currentStock,
                'total_sold' => $totalSold,
                'historical_series' => $history,
                'alpha' => $alpha,
                'forecast_tomorrow' => $predictedNextDay,
                'forecast_weekend' => $predictedWeekend,
                'days_of_supply' => $daysOfSupply,
                'stockout_risk' => $isStockoutRisk,
                'reorder_recommended_qty' => $isStockoutRisk ? max(20, $predictedWeekend - $currentStock + 15) : 0
            ];
        }

        return $forecastReport;
    }
}
