<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$stmt = $pdo->query("SELECT id, personnel_id AS title, date AS start FROM dispatch");
$events = [];
while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    $events[] = [
        'id' => $row['id'],
        'title' => "Personnel #" . $row['title'],
        'start' => $row['start']
    ];
}
echo json_encode($events);
