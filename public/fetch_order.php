<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("
        SELECT id, customer_name, appointment_date
        FROM orders
        WHERE appointment_date IS NOT NULL
        ORDER BY appointment_date ASC
    ");

    $events = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $events[] = [
            'id' => $row['id'],
            'title' => "Order #{$row['id']} - " . $row['customer_name'],
            'start' => $row['appointment_date'],
            'color' => '#3b82f6' // blue
        ];
    }

    echo json_encode($events);

} catch (Exception $e) {
    echo json_encode([]);
}
