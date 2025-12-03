<?php
require_once __DIR__ . '/../config.php';

header("Content-Type: application/json");

// Fetch dispatch + personnel
$stmt = $pdo->query("
    SELECT 
        d.id,
        d.title,
        d.start_date,
        d.end_date,
        DAY(d.start_date) AS day_num,
        p.name AS personnel_name
    FROM dispatch d
    LEFT JOIN personnel p ON p.id = d.personnel_id
");

$data = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $data[] = [
        "day"       => (int) $row["day_num"],
        "title"     => $row["title"],
        "personnel" => $row["personnel_name"]
    ];
}

echo json_encode($data);
