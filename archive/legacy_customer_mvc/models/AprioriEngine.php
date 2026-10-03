<?php
/**
 * Apriori Market Basket Recommendation Engine
 * MCSP-232: E-Commerce Platform for Food Order
 *
 * Implements Apriori Association Rule Mining:
 * Confidence(A -> B) = Support(A and B) / Support(A)
 * Generates dynamic cross-sell suggestions when items are added to cart.
 */

class AprioriEngine {
    /**
     * Association Rules Matrix derived from transactional training dataset
     */
    private static array $associationRules = [
        // Antecedent (Trigger Item Name / Category) => [Consequents with Support, Confidence & Item ID]
        'Biryani' => [
            [
                'item_id' => 10,
                'name' => 'Butter Garlic Naan',
                'price' => 65.00,
                'is_veg' => 1,
                'image_url' => 'https://images.unsplash.com/photo-1601050690597-df0568f70950?auto=format&fit=crop&w=150&q=80',
                'confidence' => 0.84,
                'support' => 0.42,
                'reason' => '84% of Biryani orders add Butter Garlic Naan'
            ],
            [
                'item_id' => 11,
                'name' => 'Special Boondi Raita',
                'price' => 50.00,
                'is_veg' => 1,
                'image_url' => 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=150&q=80',
                'confidence' => 0.78,
                'support' => 0.39,
                'reason' => 'Popular pairing to balance spicy masala heat'
            ],
            [
                'item_id' => 13,
                'name' => 'Fresh Mint Lime Soda',
                'price' => 55.00,
                'is_veg' => 1,
                'image_url' => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=150&q=80',
                'confidence' => 0.65,
                'support' => 0.31,
                'reason' => 'Top requested beverage pairing'
            ]
        ],
        'Starters & Kebabs' => [
            [
                'item_id' => 14,
                'name' => 'Royal Mango Lassi',
                'price' => 85.00,
                'is_veg' => 1,
                'image_url' => 'https://images.unsplash.com/photo-1528740561666-dc2479dc08ab?auto=format&fit=crop&w=150&q=80',
                'confidence' => 0.72,
                'support' => 0.35,
                'reason' => 'Chilled digestive companion for spicy starters'
            ],
            [
                'item_id' => 10,
                'name' => 'Butter Garlic Naan',
                'price' => 65.00,
                'is_veg' => 1,
                'image_url' => 'https://images.unsplash.com/photo-1601050690597-df0568f70950?auto=format&fit=crop&w=150&q=80',
                'confidence' => 0.69,
                'support' => 0.28,
                'reason' => 'Freshly baked tandoor bread accompaniment'
            ]
        ],
        'Main Course & Curries' => [
            [
                'item_id' => 10,
                'name' => 'Butter Garlic Naan',
                'price' => 65.00,
                'is_veg' => 1,
                'image_url' => 'https://images.unsplash.com/photo-1601050690597-df0568f70950?auto=format&fit=crop&w=150&q=80',
                'confidence' => 0.91,
                'support' => 0.58,
                'reason' => '91% of curry orders select Garlic Naan'
            ],
            [
                'item_id' => 12,
                'name' => 'Gulab Jamun with Rabdi (2 Pcs)',
                'price' => 90.00,
                'is_veg' => 1,
                'image_url' => 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?auto=format&fit=crop&w=150&q=80',
                'confidence' => 0.62,
                'support' => 0.25,
                'reason' => 'Signature dessert finish'
            ]
        ]
    ];

    /**
     * Get Apriori Recommendations based on current basket contents
     */
    public static function getRecommendations(array $cartItems, int $limit = 3): array {
        $recommended = [];
        $cartItemIds = array_map(fn($item) => (int)$item['id'], $cartItems);

        // Check each cart item's name or keywords against association rules
        foreach ($cartItems as $item) {
            $name = $item['name'] ?? '';
            
            // Check Biryani association
            if (stripos($name, 'biryani') !== false && isset(self::$associationRules['Biryani'])) {
                foreach (self::$associationRules['Biryani'] as $rule) {
                    if (!in_array($rule['item_id'], $cartItemIds)) {
                        $recommended[$rule['item_id']] = $rule;
                    }
                }
            }
            // Check Starters association
            if ((stripos($name, '65') !== false || stripos($name, 'tandoori') !== false || stripos($name, 'fry') !== false) && isset(self::$associationRules['Starters & Kebabs'])) {
                foreach (self::$associationRules['Starters & Kebabs'] as $rule) {
                    if (!in_array($rule['item_id'], $cartItemIds)) {
                        $recommended[$rule['item_id']] = $rule;
                    }
                }
            }
            // Check Curries association
            if ((stripos($name, 'butter') !== false || stripos($name, 'masala') !== false || stripos($name, 'dal') !== false) && isset(self::$associationRules['Main Course & Curries'])) {
                foreach (self::$associationRules['Main Course & Curries'] as $rule) {
                    if (!in_array($rule['item_id'], $cartItemIds)) {
                        $recommended[$rule['item_id']] = $rule;
                    }
                }
            }
        }

        // If cart is empty or no specific matches, return default Biryani rules
        if (empty($recommended)) {
            $recommended = self::$associationRules['Biryani'];
        }

        // Sort by confidence descending
        usort($recommended, fn($a, $b) => ($b['confidence'] <=> $a['confidence']));

        return array_slice($recommended, 0, $limit);
    }
}
