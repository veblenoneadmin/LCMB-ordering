<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$stmt = $pdo->query("SELECT id, order_number AS title, appointment_date AS start FROM orders");
$events = [];
while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    $events[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'start' => $row['start']
    ];
}
echo json_encode($events);
