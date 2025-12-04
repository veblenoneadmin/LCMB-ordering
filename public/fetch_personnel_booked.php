<?php
require_once __DIR__ . '/../config.php';

// Nothing must echo before this line:
header("Content-Type: application/json");

// Prevent warnings from breaking JSON
error_reporting(E_ERROR | E_PARSE);

$pid = intval($_GET['personnel_id'] ?? 0);

$stmt = $pdo->prepare("SELECT start_date FROM dispatch WHERE personnel_id = ?");
$stmt->execute([$pid]);

$booked = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!empty($row['start_date'])) {
        $booked[] = date("Y-m-d", strtotime($row['start_date']));
    }
}

echo json_encode($booked);
exit;
