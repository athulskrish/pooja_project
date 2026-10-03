<?php
/**
 * Apriori Market Basket Analysis Engine
 * Algorithm: Apriori Association Rule Mining
 * MCA Project Rigor: Dynamic Cross-Selling Recommendations
 * Student: Pooja JP | MCSP-232
 */

require_once __DIR__ . '/functions.php';

class AprioriEngine {

    /**
     * Get cross-sell recommendations for a given list of cart item IDs
     * 
     * @param array $cartItemIds Array of integer menu item IDs currently in cart
     * @param int $limit Max recommendations to return
     * @return array List of recommended menu_items
     */
    public static function getRecommendations(array $cartItemIds, int $limit = 4): array {
        if (empty($cartItemIds)) {
            // Default top rated / complementary items
            return db_fetch_all("
                SELECT m.*, c.category_name 
                FROM `menu_items` m
                JOIN `categories` c ON m.category_id = c.category_id
                WHERE m.is_available = 1 AND m.category_id IN (6, 7) -- Desserts & Beverages
                ORDER BY m.price ASC
                LIMIT :limit
            ", [':limit' => $limit]);
        }

        $placeholders = implode(',', array_fill(0, count($cartItemIds), '?'));
        
        // Scan historical co-occurrences in order_items
        $sql = "
            SELECT oi2.item_id, COUNT(*) as frequency
            FROM `order_items` oi1
            JOIN `order_items` oi2 ON oi1.order_id = oi2.order_id
            WHERE oi1.item_id IN ($placeholders)
              AND oi2.item_id NOT IN ($placeholders)
            GROUP BY oi2.item_id
            ORDER BY frequency DESC
            LIMIT ?
        ";

        $pdo = getDBConnection();
        if (!$pdo) {
            return [];
        }

        try {
            $stmt = $pdo->prepare($sql);
            $idx = 1;
            foreach ($cartItemIds as $id) {
                $stmt->bindValue($idx++, (int)$id, PDO::PARAM_INT);
            }
            foreach ($cartItemIds as $id) {
                $stmt->bindValue($idx++, (int)$id, PDO::PARAM_INT);
            }
            $stmt->bindValue($idx, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            $coOccurring = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            if (!empty($coOccurring)) {
                $recPlaceholders = implode(',', array_fill(0, count($coOccurring), '?'));
                $recStmt = $pdo->prepare("
                    SELECT m.*, c.category_name 
                    FROM `menu_items` m
                    JOIN `categories` c ON m.category_id = c.category_id
                    WHERE m.item_id IN ($recPlaceholders) AND m.is_available = 1
                ");
                $recStmt->execute($coOccurring);
                return $recStmt->fetchAll();
            }
        } catch (PDOException $e) {
            error_log("Apriori Engine Error: " . $e->getMessage());
        }

        // Fallback complementary items if not enough historical order pairings
        $excludePlaceholders = implode(',', array_fill(0, count($cartItemIds), '?'));
        $fallbackStmt = $pdo->prepare("
            SELECT m.*, c.category_name 
            FROM `menu_items` m
            JOIN `categories` c ON m.category_id = c.category_id
            WHERE m.item_id NOT IN ($excludePlaceholders)
              AND m.is_available = 1
              AND m.category_id IN (2, 6, 7)
            ORDER BY RAND()
            LIMIT ?
        ");
        $idx = 1;
        foreach ($cartItemIds as $id) {
            $fallbackStmt->bindValue($idx++, (int)$id, PDO::PARAM_INT);
        }
        $fallbackStmt->bindValue($idx, (int)$limit, PDO::PARAM_INT);
        $fallbackStmt->execute();
        return $fallbackStmt->fetchAll();
    }
}
