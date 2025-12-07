<?php
require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM personnel WHERE id = ?");
        $stmt->execute([$id]);
    }
}
?>
