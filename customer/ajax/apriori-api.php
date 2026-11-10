<?php
declare(strict_types=1);

/**
 * Asynchronous Apriori Recommendation API Endpoint
 * Zippy - Online Food Ordering Platform
 * MCSP-232: MCA Major Project | Student: Pooja JP
 * 
 * Computes dynamic cross-sell recommendations using Apriori Association Rule Mining
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/AprioriEngine.php';

$action = cleanInput((string)($_GET['action'] ?? 'recommend'));

// Support for full association rules BI audit report
if ($action === 'report') {
    $minSupport = isset($_GET['min_support']) ? (float)$_GET['min_support'] : AprioriEngine::DEFAULT_MIN_SUPPORT;
    $minConfidence = isset($_GET['min_confidence']) ? (float)$_GET['min_confidence'] : AprioriEngine::DEFAULT_MIN_CONFIDENCE;
    $report = AprioriEngine::getAssociationRulesReport($minSupport, $minConfidence);
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$rawItemIds = $_GET['item_ids'] ?? '';
$cartItemIds = [];

if (!empty($rawItemIds)) {
    if (is_array($rawItemIds)) {
        $cartItemIds = array_map('intval', $rawItemIds);
    } else {
        $cartItemIds = array_filter(array_map('intval', explode(',', (string)$rawItemIds)));
    }
}

$limit = isset($_GET['limit']) ? max(1, min(12, (int)$_GET['limit'])) : 4;
$minSupport = isset($_GET['min_support']) ? (float)$_GET['min_support'] : AprioriEngine::DEFAULT_MIN_SUPPORT;
$minConfidence = isset($_GET['min_confidence']) ? (float)$_GET['min_confidence'] : AprioriEngine::DEFAULT_MIN_CONFIDENCE;

$recommendations = AprioriEngine::getRecommendations($cartItemIds, $limit, $minSupport, $minConfidence);

echo json_encode([
    'success' => true,
    'algorithm' => 'Apriori Association Rule Mining',
    'cart_item_ids' => array_values($cartItemIds),
    'min_support' => $minSupport,
    'min_confidence' => $minConfidence,
    'count' => count($recommendations),
    'recommendations' => $recommendations
], JSON_UNESCAPED_SLASHES);
