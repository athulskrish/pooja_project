<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$hash1 = get_kds_state_hash();
$hash2 = get_kds_state_hash();

echo "Hash 1: $hash1\n";
echo "Hash 2: $hash2\n";
echo "Hashes identical? " . ($hash1 === $hash2 ? "YES" : "NO") . "\n";
