<?php
require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($file, 'r')) !== false) {
        $row = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $row++;
            if ($row === 1) continue; // Skip header row

            $name = trim($data[0]);
            $description = trim($data[1]);
            $price = floatval($data[2]);

            // Generate SKU
            $stmt = $pdo->query("SELECT sku FROM products ORDER BY id DESC LIMIT 1");
            $last = $stmt->fetchColumn();
            $number = $last ? intval(substr($last, 1)) + 1 : 1;
            $sku = "P" . str_pad($number, 4, "0", STR_PAD_LEFT);

            // Insert
            $stmt = $pdo->prepare("INSERT INTO products (sku, name, description, price, category, created_at) 
                                   VALUES (?, ?, ?, ?, 'Product', NOW())");
            $stmt->execute([$sku, $name, $description, $price]);
        }
        fclose($handle);
    }
}

// Redirect back to materials page
header("Location: materials.php");
exit;
