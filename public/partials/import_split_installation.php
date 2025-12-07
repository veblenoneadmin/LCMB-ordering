<?php
require_once __DIR__ . '/../../config.php';

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== 0) {
    die("No file uploaded.");
}

$file_tmp = $_FILES['csv_file']['tmp_name'];
$file_name = $_FILES['csv_file']['name'];

$ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
if ($ext !== 'csv') die("Invalid file type. Only CSV allowed.");

if (($handle = fopen($file_tmp, "r")) === false) die("Failed to open file.");

// Skip header
fgetcsv($handle);

$insert = $pdo->prepare("
    INSERT INTO split_installation (item_name, unit_price, quantity, category)
    VALUES (?, ?, ?, ?)
");

$count = 0;

while (($row = fgetcsv($handle, 1000, ",")) !== false) {
    if (count($row) < 4) continue;

    [$item_name, $unit_price, $quantity, $category] = array_map('trim', $row);

    if ($item_name && $unit_price && $quantity && $category) {
        $insert->execute([$item_name, $unit_price, $quantity, $category]);
        $count++;
    }
}

fclose($handle);
header("Location: ../split_installation.php?imported=$count");
exit;
?>
