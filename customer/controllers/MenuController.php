<?php
/**
 * MenuController - Orchestrates dish queries, category grouping, and filters
 * MCSP-232: E-Commerce Platform for Food Order
 */
require_once __DIR__ . '/../models/MenuItem.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/AprioriEngine.php';

class MenuController {
    public static function getRestaurantPageData(): array {
        $categories = Category::getAll();
        $vegOnly = isset($_GET['veg']) && $_GET['veg'] === '1';
        $search = trim($_GET['search'] ?? '');
        $items = MenuItem::getAll(null, $vegOnly ? true : null, $search ?: null);

        // Group items by category name
        $menuGrouped = [];
        foreach ($categories as $cat) {
            $catItems = array_filter($items, fn($it) => $it['category_id'] == $cat['category_id']);
            if (!empty($catItems)) {
                $menuGrouped[$cat['category_name']] = array_values($catItems);
            }
        }

        // Bestsellers for the top spotlight
        $bestsellers = array_filter($items, fn($it) => !empty($it['is_bestseller']));

        // Algorithmic Apriori cross-sell recommendations
        $aprioriRecs = AprioriEngine::getRecommendations([['id' => 1, 'name' => 'Biryani']]);

        return [
            'categories'   => $categories,
            'menuGrouped'  => $menuGrouped,
            'bestsellers'  => array_values($bestsellers),
            'aprioriRecs'  => $aprioriRecs,
            'vegOnly'      => $vegOnly,
            'search'       => $search
        ];
    }

    public static function getHomePageData(): array {
        $categories = Category::getAll();
        $bestsellers = MenuItem::getBestsellers(8);
        return [
            'categories'  => $categories,
            'bestsellers' => $bestsellers
        ];
    }
}
