<?php
require_once __DIR__ . '/../../config.php';

// If no file uploaded
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== 0) {
    die("No file uploaded.");
}

$file_tmp = $_FILES['csv_file']['tmp_name'];
$file_name = $_FILES['csv_file']['name'];

// Validate extension
$ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    die("Invalid file type. Only CSV allowed.");
}

// Open file
if (($handle = fopen($file_tmp, "r")) === false) {
    die("Failed to open file.");
}

// Skip header row
fgetcsv($handle);

$insert = $pdo->prepare("
    INSERT INTO personnel (name, email, role, rate, category, created_at)
    VALUES (?, ?, ?, ?, 'Person', NOW())
");

$count = 0;

while (($row = fgetcsv($handle, 1000, ",")) !== false) {
    if (count($row) < 4) {
        continue; // Skip invalid rows
    }

    $name  = trim($row[0]);
    $email = trim($row[1]);
    $role  = trim($row[2]);
    $rate  = trim($row[3]);

    if ($name === "" || $email === "" || $role === "" || $rate === "") {
        continue; // Skip incomplete rows
    }

    $insert->execute([$name, $email, $role, $rate]);
    $count++;
}

fclose($handle);

// Redirect back to personnel list with message
header("Location: personnel.php?imported=$count");
exit;
?>
