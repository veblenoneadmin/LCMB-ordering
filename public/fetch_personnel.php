<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("
        SELECT d.id, d.personnel_id, d.date, d.hours, p.name AS personnel_name
        FROM dispatch d
        LEFT JOIN personnel p ON p.id = d.personnel_id
        ORDER BY d.date ASC
    ");

    $events = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $events[] = [
            'id' => $row['id'],
            'title' => $row['personnel_name'] . ' ('. $row['hours'] .'h)',
            'start' => $row['date'],
            'color' => '#10b981' // green
        ];
    }

    echo json_encode($events);

} catch (Exception $e) {
    echo json_encode([]);
}
