<?php
/**
 * Asynchronous Apriori Recommendation API Endpoint
 * Zippy - Online Food Ordering Platform
 * MCSP-232: MCA Major Project | Student: Pooja JP
 * 
 * Computes dynamic cross-sell recommendations using Apriori Association Rule Mining
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/AprioriEngine.php';

$rawItemIds = $_GET['item_ids'] ?? '';
$cartItemIds = [];

if (!empty($rawItemIds)) {
    if (is_array($rawItemIds)) {
        $cartItemIds = array_map('intval', $rawItemIds);
    } else {
        $cartItemIds = array_filter(array_map('intval', explode(',', $rawItemIds)));
    }
}

$limit = isset($_GET['limit']) ? max(1, min(8, (int)$_GET['limit'])) : 4;

$recommendations = AprioriEngine::getRecommendations($cartItemIds, $limit);

echo json_encode([
    'success' => true,
    'algorithm' => 'Apriori Association Rule Mining',
    'cart_item_ids' => array_values($cartItemIds),
    'count' => count($recommendations),
    'recommendations' => $recommendations
]);
