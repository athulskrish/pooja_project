<?php
declare(strict_types=1);

/**
 * =============================================================================
 * MENU ITEM MODEL & APRIORI RECOMMENDATION ADAPTER
 * Course: MCSP-232 (MCA Major Project, IGNOU) | Student: Pooja JP
 * =============================================================================
 */

require_once __DIR__ . '/../../../includes/AprioriEngine.php';

class MenuItem {
    /**
     * Get all items with optional filters from MySQL database
     */
    public static function getAll(array $filters = []): array {
        $pdo = Database::getInstance();
        if (!$pdo) {
            return [];
        }

        try {
            $sql = "SELECT m.*, c.category_name, c.category_slug 
                    FROM menu_items m 
                    JOIN categories c ON m.category_id = c.category_id 
                    WHERE m.is_available = 1";
            $params = [];

            if (!empty($filters['category_id'])) {
                $sql .= " AND m.category_id = ?";
                $params[] = (int)$filters['category_id'];
            }

            if (!empty($filters['category_slug'])) {
                $sql .= " AND c.category_slug = ?";
                $params[] = $filters['category_slug'];
            }

            if (isset($filters['is_veg']) && $filters['is_veg'] !== '') {
                $sql .= " AND m.is_veg = ?";
                $params[] = (int)$filters['is_veg'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (m.item_name LIKE ? OR m.description LIKE ?)";
                $params[] = '%' . $filters['search'] . '%';
                $params[] = '%' . $filters['search'] . '%';
            }

            $sort = $filters['sort'] ?? '';
            switch ($sort) {
                case 'price_low':
                    $sql .= " ORDER BY m.price ASC";
                    break;
                case 'price_high':
                    $sql .= " ORDER BY m.price DESC";
                    break;
                case 'rating':
                    $sql .= " ORDER BY m.rating DESC";
                    break;
                default:
                    $sql .= " ORDER BY m.rating DESC, m.item_id ASC";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("MenuItem::getAll error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get item by ID
     */
    public static function getById(int $itemId): ?array {
        $pdo = Database::getInstance();
        if (!$pdo) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT m.*, c.category_name, c.category_slug 
                                   FROM menu_items m 
                                   JOIN categories c ON m.category_id = c.category_id 
                                   WHERE m.item_id = ? LIMIT 1");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            return $item ?: null;
        } catch (PDOException $e) {
            error_log("MenuItem::getById error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delegate Apriori Market Basket Recommendations to canonical AprioriEngine
     */
    public static function getAprioriRecommendations(array $currentCartItemIds, int $limit = 4): array {
        return AprioriEngine::getRecommendations($currentCartItemIds, $limit);
    }
}
