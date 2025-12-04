<?php
// get_personnel_booked_dates.php
require_once __DIR__ . '/../config.php'; // adjust path if needed

header('Content-Type: application/json');

$personnel_id = isset($_GET['personnel_id']) ? intval($_GET['personnel_id']) : 0;
if($personnel_id <= 0){
    echo json_encode([]);
    exit;
}

try {
    // Fetch booked dates from dispatch table for this personnel
    $stmt = $pdo->prepare("SELECT date FROM dispatch WHERE personnel_id = ? AND date >= CURDATE()");
    $stmt->execute([$personnel_id]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode($dates);
} catch(Exception $e) {
    // On error, just return empty array
    echo json_encode([]);
}
