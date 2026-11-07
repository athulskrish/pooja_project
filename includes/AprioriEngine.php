<?php
declare(strict_types=1);

/**
 * Apriori Market Basket Analysis Engine
 * Algorithm: Apriori Association Rule Mining (Agrawal & Srikant, 1994)
 * Architecture Domain: Data Science, Cart Recommendations & Cross-Selling
 * MCA Major Project: MCSP-232 | Student: Pooja JP
 *
 * Implements:
 * 1. Level-Wise Frequent Itemset Generation with Downward-Closure Property:
 *    - L1 frequent 1-itemsets filtered by minimum support threshold
 *    - C_k candidate generation via (k-1)-itemset join
 *    - Downward-closure subset pruning (all (k-1) subsets must be frequent)
 *    - Full transaction scanning and frequency counting
 * 2. Association Rule Generation:
 *    - Support(A U B) = Orders(A U B) / Total Orders
 *    - Confidence(A -> B) = Support(A U B) / Support(A)
 *    - Lift(A -> B) = Confidence(A -> B) / Support(B)
 *    - Minimum Support >= 0.05 (5%), Minimum Confidence >= 0.20 (20%), Lift > 1.0
 * 3. Real-Time Dynamic Cart Recommendations:
 *    - Matches active cart items with high-confidence rule antecedents
 *    - Ranks suggestions by Lift > Confidence > Support
 *    - Graceful defensive fallback to top-rated catalog items when data < 5 orders
 * 4. Enterprise Decoupling:
 *    - Pure algorithm can run standalone or with live PDO connections
 *    - In-memory TTL caching to avoid repeated database scans on rapid cart events
 */

require_once __DIR__ . '/functions.php';

class AprioriEngine
{
    /** Default minimum support threshold (5% of orders) */
    public const DEFAULT_MIN_SUPPORT = 0.05;

    /** Default minimum confidence threshold (20% conditional probability) */
    public const DEFAULT_MIN_CONFIDENCE = 0.20;

    /** Default minimum lift threshold (Lift > 1 implies positive correlation) */
    public const DEFAULT_MIN_LIFT = 1.0;

    /** Minimum historical transaction volume before enabling association rules */
    public const MIN_TRANSACTIONS_THRESHOLD = 5;

    /** Maximum itemset length to mine (1-itemsets, pairs, triplets) */
    public const MAX_ITEMSET_K = 3;

    /** In-memory cache TTL for mined rules in seconds */
    public const CACHE_TTL_SECONDS = 60;

    /** Cached association rules */
    private static ?array $rulesCache = null;

    /** Timestamp when rules were cached */
    private static int $rulesCacheTime = 0;

    /**
     * Get cross-sell recommendations for a given list of cart item IDs
     *
     * @param array<int> $cartItemIds Array of menu item IDs currently in customer cart
     * @param int $limit Maximum number of recommendations to return (default: 4)
     * @param float $minSupport Minimum support threshold
     * @param float $minConfidence Minimum confidence threshold
     * @return array<int, array<string, mixed>> List of recommended menu item associative arrays
     */
    public static function getRecommendations(
        array $cartItemIds,
        int $limit = 4,
        float $minSupport = self::DEFAULT_MIN_SUPPORT,
        float $minConfidence = self::DEFAULT_MIN_CONFIDENCE
    ): array {
        $limit = max(1, min(12, $limit));
        $cartIds = self::sanitizeItemIds($cartItemIds);

        // Fetch or mine rules
        $rules = self::mineAssociationRules($minSupport, $minConfidence, self::DEFAULT_MIN_LIFT);

        $recommendedItemMeta = [];

        if (!empty($cartIds)) {
            // Find rules where Antecedent is a subset of Cart and Consequent has items not in Cart
            foreach ($rules as $rule) {
                if (self::isSubset($rule['antecedent'], $cartIds)) {
                    foreach ($rule['consequent'] as $consequentItemId) {
                        if (!in_array($consequentItemId, $cartIds, true) && !isset($recommendedItemMeta[$consequentItemId])) {
                            $recommendedItemMeta[$consequentItemId] = [
                                'item_id' => $consequentItemId,
                                'support' => $rule['support'],
                                'confidence' => $rule['confidence'],
                                'lift' => $rule['lift'],
                                'pairing_reason' => sprintf(
                                    'Frequently paired (%.0f%% confidence, %.1fx lift)',
                                    $rule['confidence'] * 100,
                                    $rule['lift']
                                ),
                                'algorithm_source' => 'APRIORI_RULE'
                            ];

                            if (count($recommendedItemMeta) >= $limit) {
                                break 2;
                            }
                        }
                    }
                }
            }
        } else {
            // Empty cart: surface top global association combos (highest lift & confidence pairings)
            foreach ($rules as $rule) {
                foreach (array_merge($rule['consequent'], $rule['antecedent']) as $comboItemId) {
                    if (!isset($recommendedItemMeta[$comboItemId])) {
                        $recommendedItemMeta[$comboItemId] = [
                            'item_id' => $comboItemId,
                            'support' => $rule['support'],
                            'confidence' => $rule['confidence'],
                            'lift' => $rule['lift'],
                            'pairing_reason' => sprintf(
                                'Top culinary pairing (%.0f%% confidence, %.1fx lift)',
                                $rule['confidence'] * 100,
                                $rule['lift']
                            ),
                            'algorithm_source' => 'GLOBAL_COMBO'
                        ];

                        if (count($recommendedItemMeta) >= $limit) {
                            break 2;
                        }
                    }
                }
            }
        }

        // Populate full dish catalog details
        $populatedDishes = self::populateDishDetails($recommendedItemMeta);

        // If Apriori rules yielded fewer than $limit dishes, backfill with complementary dishes
        if (count($populatedDishes) < $limit) {
            $alreadyIncluded = array_merge($cartIds, array_column($populatedDishes, 'item_id'));
            $remainingNeeded = $limit - count($populatedDishes);
            $fallbackDishes = self::getFallbackItems($alreadyIncluded, $remainingNeeded);
            $populatedDishes = array_merge($populatedDishes, $fallbackDishes);
        }

        return array_slice($populatedDishes, 0, $limit);
    }

    /**
     * Mine association rules from completed database orders or cache
     *
     * @param float $minSupport Minimum support threshold (0.0 < support <= 1.0)
     * @param float $minConfidence Minimum confidence threshold (0.0 < confidence <= 1.0)
     * @param float $minLift Minimum lift threshold (lift > 1.0)
     * @param bool $forceRefresh Bypass cache and re-mine rules
     * @return array<int, array<string, mixed>> List of mined association rules
     */
    public static function mineAssociationRules(
        float $minSupport = self::DEFAULT_MIN_SUPPORT,
        float $minConfidence = self::DEFAULT_MIN_CONFIDENCE,
        float $minLift = self::DEFAULT_MIN_LIFT,
        bool $forceRefresh = false
    ): array {
        $now = time();
        if (!$forceRefresh && self::$rulesCache !== null && ($now - self::$rulesCacheTime) < self::CACHE_TTL_SECONDS) {
            return self::$rulesCache;
        }

        $transactions = self::getTransactionsFromDB();
        if (count($transactions) < self::MIN_TRANSACTIONS_THRESHOLD) {
            self::$rulesCache = [];
            self::$rulesCacheTime = $now;
            return [];
        }

        $miningResult = self::runApriori(
            $transactions,
            $minSupport,
            $minConfidence,
            $minLift,
            self::MAX_ITEMSET_K
        );

        self::$rulesCache = $miningResult['rules'];
        self::$rulesCacheTime = $now;

        return self::$rulesCache;
    }

    /**
     * Pure Apriori Algorithm Implementation
     * Can run on any transaction array without database coupling.
     *
     * @param array<int, array<int>> $transactions List of orders, each containing item IDs
     * @param float $minSupport Minimum support threshold
     * @param float $minConfidence Minimum confidence threshold
     * @param float $minLift Minimum lift threshold
     * @param int $maxK Maximum itemset size (default: 3)
     * @return array{frequent_itemsets: array, itemset_counts: array, rules: array, total_transactions: int}
     */
    public static function runApriori(
        array $transactions,
        float $minSupport = self::DEFAULT_MIN_SUPPORT,
        float $minConfidence = self::DEFAULT_MIN_CONFIDENCE,
        float $minLift = self::DEFAULT_MIN_LIFT,
        int $maxK = self::MAX_ITEMSET_K
    ): array {
        $normalized = [];
        foreach ($transactions as $t) {
            $cleaned = self::sanitizeItemIds($t);
            if (!empty($cleaned)) {
                $normalized[] = $cleaned;
            }
        }

        $totalTransactions = count($normalized);
        if ($totalTransactions < self::MIN_TRANSACTIONS_THRESHOLD) {
            return [
                'frequent_itemsets' => [],
                'itemset_counts' => [],
                'rules' => [],
                'total_transactions' => $totalTransactions
            ];
        }

        $frequentItemsets = [];
        $itemsetCounts = [];

        // -------------------------------------------------------------
        // Step 1: L1 Frequent 1-Itemsets
        // -------------------------------------------------------------
        $l1 = self::generateL1($normalized, $totalTransactions, $minSupport, $itemsetCounts);
        if (empty($l1)) {
            return [
                'frequent_itemsets' => [],
                'itemset_counts' => $itemsetCounts,
                'rules' => [],
                'total_transactions' => $totalTransactions
            ];
        }

        $frequentItemsets[1] = $l1;

        // -------------------------------------------------------------
        // Step 2: Level-k Candidate Generation & Support Pruning (k = 2..maxK)
        // -------------------------------------------------------------
        $currentL = $l1;
        for ($k = 2; $k <= $maxK && !empty($currentL); $k++) {
            $candidates = self::generateCandidates($currentL, $k);
            if (empty($candidates)) {
                break;
            }

            $nextL = self::filterCandidates($candidates, $normalized, $totalTransactions, $minSupport, $itemsetCounts);
            if (!empty($nextL)) {
                $frequentItemsets[$k] = $nextL;
            }
            $currentL = $nextL;
        }

        // -------------------------------------------------------------
        // Step 3: Association Rule Generation (A -> B)
        // -------------------------------------------------------------
        $rules = self::deriveRules(
            $frequentItemsets,
            $itemsetCounts,
            $totalTransactions,
            $minConfidence,
            $minLift
        );

        return [
            'frequent_itemsets' => $frequentItemsets,
            'itemset_counts' => $itemsetCounts,
            'rules' => $rules,
            'total_transactions' => $totalTransactions
        ];
    }

    /**
     * Generate an audit & analytics report for administrative or academic inspection
     *
     * @param float $minSupport Minimum support threshold
     * @param float $minConfidence Minimum confidence threshold
     * @param float $minLift Minimum lift threshold
     * @return array<string, mixed> Structured analytics report
     */
    public static function getAssociationRulesReport(
        float $minSupport = self::DEFAULT_MIN_SUPPORT,
        float $minConfidence = self::DEFAULT_MIN_CONFIDENCE,
        float $minLift = self::DEFAULT_MIN_LIFT
    ): array {
        $transactions = self::getTransactionsFromDB();
        $totalTransactions = count($transactions);

        if ($totalTransactions < self::MIN_TRANSACTIONS_THRESHOLD) {
            return [
                'status' => 'INSUFFICIENT_DATA',
                'message' => 'Fewer than 5 historical orders available for association mining.',
                'total_transactions' => $totalTransactions,
                'rules' => []
            ];
        }

        $miningResult = self::runApriori($transactions, $minSupport, $minConfidence, $minLift, self::MAX_ITEMSET_K);

        // Fetch dish names for human-readable report formatting
        $allItemIds = [];
        foreach ($miningResult['rules'] as $rule) {
            foreach ($rule['antecedent'] as $id) {
                $allItemIds[$id] = true;
            }
            foreach ($rule['consequent'] as $id) {
                $allItemIds[$id] = true;
            }
        }

        $itemNames = self::fetchItemNames(array_keys($allItemIds));

        $formattedRules = [];
        foreach ($miningResult['rules'] as $rule) {
            $antNames = array_map(fn($id) => $itemNames[$id] ?? "Item #$id", $rule['antecedent']);
            $conNames = array_map(fn($id) => $itemNames[$id] ?? "Item #$id", $rule['consequent']);

            $formattedRules[] = [
                'antecedent_ids' => $rule['antecedent'],
                'consequent_ids' => $rule['consequent'],
                'antecedent_names' => $antNames,
                'consequent_names' => $conNames,
                'rule_text' => implode(' + ', $antNames) . ' => ' . implode(' + ', $conNames),
                'support' => $rule['support'],
                'confidence' => $rule['confidence'],
                'lift' => $rule['lift'],
                'itemset_count' => $rule['itemset_count']
            ];
        }

        return [
            'status' => 'SUCCESS',
            'algorithm' => 'Apriori Association Rule Mining',
            'total_transactions' => $totalTransactions,
            'frequent_itemsets_count' => array_sum(array_map('count', $miningResult['frequent_itemsets'])),
            'rules_count' => count($formattedRules),
            'min_support' => $minSupport,
            'min_confidence' => $minConfidence,
            'min_lift' => $minLift,
            'rules' => $formattedRules
        ];
    }

    /**
     * Defensive fallback items when data is sparse or rules are exhausted
     *
     * @param array<int> $excludeItemIds Item IDs to exclude from suggestions
     * @param int $limit Number of items to return
     * @return array<int, array<string, mixed>> List of fallback dishes
     */
    public static function getFallbackItems(array $excludeItemIds = [], int $limit = 4): array
    {
        $excludeIds = self::sanitizeItemIds($excludeItemIds);
        $limit = max(1, min(12, $limit));

        $pdo = getDBConnection();
        if ($pdo) {
            try {
                $whereClause = 'm.is_available = 1';
                $params = [];

                if (!empty($excludeIds)) {
                    $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
                    $whereClause .= " AND m.item_id NOT IN ($placeholders)";
                    $params = $excludeIds;
                }

                $sql = "
                    SELECT m.*, c.category_name
                    FROM `menu_items` m
                    JOIN `categories` c ON m.category_id = c.category_id
                    WHERE $whereClause
                    ORDER BY 
                        CASE WHEN m.category_id IN (2, 6, 7) THEN 0 ELSE 1 END,
                        m.rating DESC,
                        m.price ASC
                    LIMIT " . (int)$limit;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll();

                if (!empty($rows)) {
                    foreach ($rows as &$item) {
                        $item['image_url'] = get_dish_image_url($item['image_url'] ?? '');
                        $item['support'] = 0.0;
                        $item['confidence'] = 0.0;
                        $item['lift'] = 1.0;
                        $item['pairing_reason'] = 'Top-rated complementary pairing';
                        $item['algorithm_source'] = 'COMPLEMENTARY_FALLBACK';
                    }
                    unset($item);
                    return $rows;
                }
            } catch (PDOException $e) {
                error_log("AprioriEngine Fallback DB Error: " . $e->getMessage());
            }
        }

        // Offline mock fallback if database is inaccessible
        return array_slice(self::getMockCatalog($excludeIds), 0, $limit);
    }

    /**
     * Clear cached association rules
     */
    public static function clearCache(): void
    {
        self::$rulesCache = null;
        self::$rulesCacheTime = 0;
    }

    // =========================================================================
    // PRIVATE ALGORITHMIC HELPERS
    // =========================================================================

    /**
     * Step 1: Generate frequent 1-itemsets (L1)
     *
     * @param array<int, array<int>> $transactions
     * @param int $totalTransactions
     * @param float $minSupport
     * @param array<string, int> $itemsetCounts
     * @return array<int, array<int>>
     */
    private static function generateL1(
        array $transactions,
        int $totalTransactions,
        float $minSupport,
        array &$itemsetCounts
    ): array {
        $c1 = [];
        foreach ($transactions as $t) {
            foreach ($t as $itemId) {
                $c1[$itemId] = ($c1[$itemId] ?? 0) + 1;
            }
        }

        $l1 = [];
        foreach ($c1 as $itemId => $count) {
            if (($count / $totalTransactions) >= $minSupport) {
                $set = [$itemId];
                $l1[] = $set;
                $itemsetCounts[implode(',', $set)] = $count;
            }
        }

        usort($l1, fn($a, $b) => $a[0] <=> $b[0]);
        return $l1;
    }

    /**
     * Step 2a: Generate C_k candidates by joining L_{k-1} with itself and applying downward closure pruning
     *
     * @param array<int, array<int>> $LkMinus1
     * @param int $k
     * @return array<int, array<int>>
     */
    private static function generateCandidates(array $LkMinus1, int $k): array
    {
        $candidates = [];
        $numSets = count($LkMinus1);

        // Fast O(1) hash map of L_{k-1} for downward closure pruning
        $lookupMap = [];
        foreach ($LkMinus1 as $set) {
            $lookupMap[implode(',', $set)] = true;
        }

        for ($i = 0; $i < $numSets; $i++) {
            for ($j = $i + 1; $j < $numSets; $j++) {
                $p = $LkMinus1[$i];
                $q = $LkMinus1[$j];

                // Join Condition: First (k - 2) elements must match identically
                $canJoin = true;
                for ($m = 0; $m < $k - 2; $m++) {
                    if ($p[$m] !== $q[$m]) {
                        $canJoin = false;
                        break;
                    }
                }

                // Canonical ordering: p[k-2] < q[k-2]
                if ($canJoin && $p[$k - 2] < $q[$k - 2]) {
                    $candidate = $p;
                    $candidate[] = $q[$k - 2];
                    sort($candidate);

                    // Prune Step (Downward Closure Property):
                    // All (k-1) subsets of candidate must be members of L_{k-1}
                    $isPruned = false;
                    $candLength = count($candidate);
                    for ($x = 0; $x < $candLength; $x++) {
                        $subset = $candidate;
                        unset($subset[$x]);
                        $subsetKey = implode(',', array_values($subset));
                        if (!isset($lookupMap[$subsetKey])) {
                            $isPruned = true;
                            break;
                        }
                    }

                    if (!$isPruned) {
                        $candidates[] = $candidate;
                    }
                }
            }
        }

        return $candidates;
    }

    /**
     * Step 2b: Count candidate occurrences in transactions and filter by minimum support to produce L_k
     *
     * @param array<int, array<int>> $candidates
     * @param array<int, array<int>> $transactions
     * @param int $totalTransactions
     * @param float $minSupport
     * @param array<string, int> $itemsetCounts
     * @return array<int, array<int>>
     */
    private static function filterCandidates(
        array $candidates,
        array $transactions,
        int $totalTransactions,
        float $minSupport,
        array &$itemsetCounts
    ): array {
        $nextL = [];

        foreach ($candidates as $candidate) {
            $count = 0;
            foreach ($transactions as $t) {
                if (self::isSubset($candidate, $t)) {
                    $count++;
                }
            }

            if (($count / $totalTransactions) >= $minSupport) {
                $nextL[] = $candidate;
                $itemsetCounts[implode(',', $candidate)] = $count;
            }
        }

        return $nextL;
    }

    /**
     * Step 3: Derive association rules from all frequent itemsets of size >= 2
     *
     * @param array<int, array<int, array<int>>> $frequentItemsets
     * @param array<string, int> $itemsetCounts
     * @param int $totalTransactions
     * @param float $minConfidence
     * @param float $minLift
     * @return array<int, array<string, mixed>>
     */
    private static function deriveRules(
        array $frequentItemsets,
        array $itemsetCounts,
        int $totalTransactions,
        float $minConfidence,
        float $minLift
    ): array {
        $rules = [];

        foreach ($frequentItemsets as $k => $itemsets) {
            if ($k < 2) {
                continue;
            }

            foreach ($itemsets as $itemset) {
                $itemsetKey = implode(',', $itemset);
                $countX = $itemsetCounts[$itemsetKey] ?? 0;
                if ($countX === 0) {
                    continue;
                }

                $suppX = $countX / $totalTransactions;
                $numItems = count($itemset);
                $totalProperSubsets = (1 << $numItems) - 2;

                for ($mask = 1; $mask <= $totalProperSubsets; $mask++) {
                    $antecedent = [];
                    $consequent = [];

                    for ($bit = 0; $bit < $numItems; $bit++) {
                        if ($mask & (1 << $bit)) {
                            $antecedent[] = $itemset[$bit];
                        } else {
                            $consequent[] = $itemset[$bit];
                        }
                    }

                    sort($antecedent);
                    sort($consequent);

                    $antKey = implode(',', $antecedent);
                    $conKey = implode(',', $consequent);

                    $countA = $itemsetCounts[$antKey] ?? 0;
                    $countB = $itemsetCounts[$conKey] ?? 0;

                    if ($countA === 0 || $countB === 0) {
                        continue;
                    }

                    $suppA = $countA / $totalTransactions;
                    $suppB = $countB / $totalTransactions;

                    $confidence = $suppX / $suppA;
                    $lift = $confidence / $suppB;

                    if ($confidence >= $minConfidence && $lift >= $minLift) {
                        $rules[] = [
                            'antecedent' => $antecedent,
                            'consequent' => $consequent,
                            'support' => round($suppX, 4),
                            'confidence' => round($confidence, 4),
                            'lift' => round($lift, 4),
                            'antecedent_count' => $countA,
                            'consequent_count' => $countB,
                            'itemset_count' => $countX
                        ];
                    }
                }
            }
        }

        // Rank rules: Lift DESC > Confidence DESC > Support DESC
        usort($rules, function (array $a, array $b): int {
            if ($b['lift'] !== $a['lift']) {
                return $b['lift'] <=> $a['lift'];
            }
            if ($b['confidence'] !== $a['confidence']) {
                return $b['confidence'] <=> $a['confidence'];
            }
            return $b['support'] <=> $a['support'];
        });

        return $rules;
    }

    /**
     * Retrieve order transaction matrix from the database
     *
     * @return array<int, array<int>> List of transactions (arrays of item IDs)
     */
    private static function getTransactionsFromDB(): array
    {
        $pdo = getDBConnection();
        if (!$pdo) {
            return [];
        }

        try {
            $sql = "
                SELECT oi.order_id, oi.item_id
                FROM `order_items` oi
                JOIN `orders` o ON oi.order_id = o.order_id
                WHERE o.order_status NOT IN ('CANCELLED')
                ORDER BY oi.order_id ASC
            ";
            $stmt = $pdo->query($sql);
            if (!$stmt) {
                return [];
            }

            $transactions = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $orderId = (int)$row['order_id'];
                $itemId = (int)$row['item_id'];
                $transactions[$orderId][] = $itemId;
            }

            // Normalize transactions
            $cleaned = [];
            foreach ($transactions as $items) {
                $set = self::sanitizeItemIds($items);
                if (!empty($set)) {
                    $cleaned[] = $set;
                }
            }

            return $cleaned;
        } catch (PDOException $e) {
            error_log("AprioriEngine DB Transaction Extraction Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Populate catalog details for recommended item IDs
     *
     * @param array<int, array<string, mixed>> $metaMap Keyed by item_id
     * @return array<int, array<string, mixed>>
     */
    private static function populateDishDetails(array $metaMap): array
    {
        if (empty($metaMap)) {
            return [];
        }

        $itemIds = array_keys($metaMap);
        $pdo = getDBConnection();

        if ($pdo) {
            try {
                $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
                $sql = "
                    SELECT m.*, c.category_name
                    FROM `menu_items` m
                    JOIN `categories` c ON m.category_id = c.category_id
                    WHERE m.item_id IN ($placeholders) AND m.is_available = 1
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($itemIds);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $indexedRows = [];
                foreach ($rows as $row) {
                    $id = (int)$row['item_id'];
                    $row['image_url'] = get_dish_image_url($row['image_url'] ?? '');
                    $row['support'] = $metaMap[$id]['support'] ?? 0.0;
                    $row['confidence'] = $metaMap[$id]['confidence'] ?? 0.0;
                    $row['lift'] = $metaMap[$id]['lift'] ?? 1.0;
                    $row['pairing_reason'] = $metaMap[$id]['pairing_reason'] ?? 'High pairing match';
                    $row['algorithm_source'] = $metaMap[$id]['algorithm_source'] ?? 'APRIORI_RULE';
                    $indexedRows[$id] = $row;
                }

                // Preserve original ranking order (by lift/confidence)
                $result = [];
                foreach ($itemIds as $id) {
                    if (isset($indexedRows[$id])) {
                        $result[] = $indexedRows[$id];
                    }
                }
                return $result;
            } catch (PDOException $e) {
                error_log("AprioriEngine Populate Dishes Error: " . $e->getMessage());
            }
        }

        // Mock fallback if DB is offline
        $mock = self::getMockCatalog();
        $result = [];
        foreach ($itemIds as $id) {
            foreach ($mock as $dish) {
                if ($dish['item_id'] === $id) {
                    $dish['support'] = $metaMap[$id]['support'] ?? 0.0;
                    $dish['confidence'] = $metaMap[$id]['confidence'] ?? 0.0;
                    $dish['lift'] = $metaMap[$id]['lift'] ?? 1.0;
                    $dish['pairing_reason'] = $metaMap[$id]['pairing_reason'] ?? 'High pairing match';
                    $dish['algorithm_source'] = $metaMap[$id]['algorithm_source'] ?? 'APRIORI_RULE';
                    $result[] = $dish;
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * Fetch item names for an array of IDs
     *
     * @param array<int> $itemIds
     * @return array<int, string>
     */
    private static function fetchItemNames(array $itemIds): array
    {
        if (empty($itemIds)) {
            return [];
        }

        $pdo = getDBConnection();
        if ($pdo) {
            try {
                $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
                $stmt = $pdo->prepare("SELECT item_id, item_name FROM `menu_items` WHERE item_id IN ($placeholders)");
                $stmt->execute($itemIds);
                return $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
            } catch (PDOException $e) {
                error_log("AprioriEngine fetchItemNames Error: " . $e->getMessage());
            }
        }

        $names = [];
        foreach (self::getMockCatalog() as $d) {
            if (in_array($d['item_id'], $itemIds, true)) {
                $names[$d['item_id']] = $d['item_name'];
            }
        }
        return $names;
    }

    /**
     * Determines whether needle array is a subset of haystack array
     * Both arrays MUST be sorted ascending arrays of integers
     *
     * @param array<int> $needle
     * @param array<int> $haystack
     * @return bool
     */
    private static function isSubset(array $needle, array $haystack): bool
    {
        $needleCount = count($needle);
        $haystackCount = count($haystack);

        if ($needleCount > $haystackCount) {
            return false;
        }

        $i = 0;
        $j = 0;
        while ($i < $needleCount && $j < $haystackCount) {
            if ($needle[$i] === $haystack[$j]) {
                $i++;
                $j++;
            } elseif ($needle[$i] > $haystack[$j]) {
                $j++;
            } else {
                return false;
            }
        }

        return $i === $needleCount;
    }

    /**
     * Sanitize and sort integer item IDs
     *
     * @param array<mixed> $ids
     * @return array<int>
     */
    private static function sanitizeItemIds(array $ids): array
    {
        $clean = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn($val) => $val > 0
        )));
        sort($clean);
        return $clean;
    }

    /**
     * In-memory mock catalog for offline development or testing
     *
     * @param array<int> $excludeIds
     * @return array<int, array<string, mixed>>
     */
    private static function getMockCatalog(array $excludeIds = []): array
    {
        $catalog = [
            [
                'item_id' => 16,
                'item_name' => 'Fresh Sweet Lassi',
                'category_name' => 'Beverages',
                'category_id' => 7,
                'price' => 99.00,
                'rating' => 4.9,
                'is_veg' => 1,
                'prep_time_minutes' => 5,
                'description' => 'Creamy traditional sweet yogurt lassi churned with cardamom and saffron.',
                'image_url' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300'
            ],
            [
                'item_id' => 7,
                'item_name' => 'Garlic Butter Naan',
                'category_name' => 'Breads',
                'category_id' => 4,
                'price' => 89.00,
                'rating' => 4.8,
                'is_veg' => 1,
                'prep_time_minutes' => 10,
                'description' => 'Crisp tandoor baked refined flour bread infused with charred garlic and butter.',
                'image_url' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300'
            ],
            [
                'item_id' => 15,
                'item_name' => 'Gulab Jamun (2 Pcs)',
                'category_name' => 'Desserts',
                'category_id' => 6,
                'price' => 139.00,
                'rating' => 4.9,
                'is_veg' => 1,
                'prep_time_minutes' => 5,
                'description' => 'Warm khoya dumplings steeped in rose and green cardamom scented sugar syrup.',
                'image_url' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300'
            ],
            [
                'item_id' => 17,
                'item_name' => 'Peri Peri French Fries',
                'category_name' => 'Starters',
                'category_id' => 2,
                'price' => 119.00,
                'rating' => 4.7,
                'is_veg' => 1,
                'prep_time_minutes' => 12,
                'description' => 'Crispy golden potato batons tossed in spicy zesty African peri peri seasoning.',
                'image_url' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300'
            ],
            [
                'item_id' => 9,
                'item_name' => 'Traditional Filter Coffee',
                'category_name' => 'Beverages',
                'category_id' => 7,
                'price' => 69.00,
                'rating' => 4.8,
                'is_veg' => 1,
                'prep_time_minutes' => 6,
                'description' => 'Authentic South Indian chicory decoction frothed with boiled full cream milk.',
                'image_url' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300'
            ]
        ];

        return array_values(array_filter(
            $catalog,
            fn($item) => !in_array($item['item_id'], $excludeIds, true)
        ));
    }
}
