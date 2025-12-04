<?php
require_once __DIR__ . '/../config.php';

header("Content-Type: application/json");

// Only show fatal errors to avoid breaking JSON output
error_reporting(E_ERROR | E_PARSE);

$pid = intval($_GET['personnel_id'] ?? 0);

// Fetch rows for this personnel
$stmt = $pdo->prepare("
    SELECT `date` 
    FROM dispatch 
    WHERE personnel_id = ?
");
$stmt->execute([$pid]);

$booked = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!empty($row['date'])) {
        // Always convert to Y-m-d
        $booked[] = date("Y-m-d", strtotime($row['date']));
    }
}

echo json_encode($booked);
exit;
