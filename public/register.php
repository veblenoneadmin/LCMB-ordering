<?php
require_once __DIR__ . '/../config.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check if username exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->rowCount() > 0) {
        $message = "Username already taken.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        if ($stmt->execute([$username, $password])) {
            header("Location: login.php");
            exit;
        } else {
            $message = "Registration failed. Try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
</head>
<body>
<h2>Register</h2>
<form method="POST">
    <input type="text" name="username" placeholder="Username" required><br><br>
    <input type="password" name="password" placeholder="Password" required><br><br>
    <button type="submit">Register</button>
</form>
<p style="color:red;"><?= $message ?></p>
<p>Already have an account? <a href="login.php">Login</a></p>
</body>
</html>
