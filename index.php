<?php
require_once __DIR__ . '/../config.php';

echo "<h1>Test PHP MySQL on Railway</h1>";

try {
    $stmt = $pdo->query("SELECT * FROM users");
    $users = $stmt->fetchAll();

    echo "<ul>";
    foreach ($users as $user) {
        echo "<li>" . htmlspecialchars($user['name']) . " (" . htmlspecialchars($user['email']) . ")</li>";
    }
    echo "</ul>";
} catch (PDOException $e) {
    echo "Query failed: " . $e->getMessage();
}
