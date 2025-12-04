<?php
require_once __DIR__ . '/../config.php';

header("Content-Type: application/json");

$pid = intval($_GET['personnel_id'] ?? 0);

$stmt = $pdo->prepare("SELECT start_date FROM dispatch WHERE personnel_id = ?");
$stmt->execute([$pid]);

$booked = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // force format YYYY-MM-DD
    $booked[] = date("Y-m-d", strtotime($row['start_date']));
}

echo json_encode($booked);
