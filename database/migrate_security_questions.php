<?php
declare(strict_types=1);

/**
 * Database Migration: Offline Account Recovery Security Questions
 * Zippy - Online Food Ordering Platform
 * MCSP-232 Major Project | Student: Pooja JP
 */

require_once __DIR__ . '/../config/database.php';

echo "Running Migration: Security Questions for Offline Password Reset...\n";

$pdo = getDbConnection();
if (!$pdo) {
    die("Error: Could not connect to database.\n");
}

try {
    $columns = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'security_question'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("
            ALTER TABLE `users`
            ADD COLUMN `security_question` VARCHAR(255) NULL DEFAULT NULL AFTER `password_hash`,
            ADD COLUMN `security_answer_hash` VARCHAR(255) NULL DEFAULT NULL AFTER `security_question`
        ");
        echo "Added `security_question` and `security_answer_hash` columns to `users` table.\n";
    } else {
        echo "`security_question` column already exists in `users` table.\n";
    }

    // Backfill existing accounts without security questions so they can use password recovery immediately
    $defaultQuestion = 'What was the name of your first school?';
    $defaultAnswerHash = password_hash('zippy', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        UPDATE `users` 
        SET `security_question` = :sq, `security_answer_hash` = :sa 
        WHERE `security_question` IS NULL OR `security_question` = ''
    ");
    $stmt->execute([
        ':sq' => $defaultQuestion,
        ':sa' => $defaultAnswerHash
    ]);
    $updated = $stmt->rowCount();
    echo "Backfilled {$updated} existing user account(s) with default recovery security question.\n";

    echo "Migration completed successfully!\n";
} catch (Throwable $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
    exit(1);
}
