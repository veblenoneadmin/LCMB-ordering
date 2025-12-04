<?php
require_once __DIR__ . '/../config.php';
header("Content-Type: application/json");

// Fetch booked dates grouped by personnel
$stmt = $pdo->query("
    SELECT personnel_id, date
    FROM dispatch
");
$data = [];
while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    $pid = (int)$row['personnel_id'];
    if(!isset($data[$pid])) $data[$pid] = [];
    $data[$pid][] = $row['date'];
}
echo json_encode($data);
