<?php
require_once __DIR__ . '/../config.php';
header("Content-Type: application/json");

$personnel_id = intval($_GET['personnel_id'] ?? 0);

$stmt = $pdo->prepare("SELECT start_date FROM dispatch WHERE personnel_id = ?");
$stmt->execute([$personnel_id]);

$dates = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dates[] = $row['start_date'];
}

echo json_encode($dates);
