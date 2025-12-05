<?php
session_start();
require_once __DIR__ . '/../config.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: dashboard.php");
        exit;
    } else {
        $message = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Login - Material Dashboard</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background-color: #f4f5f7; /* Light Material background */
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .card {
            background: #ffffff;
            padding: 40px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1); /* subtle shadow */
            width: 350px;
            text-align: center;
        }

        .card h2 {
            margin-bottom: 30px;
            color: #333;
            font-weight: 500;
        }

        .card input[type="text"],
        .card input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            margin: 10px 0 20px 0;
            border: 1px solid #ccc;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 14px;
        }

        .card input:focus {
            border-color: #3f51b5;
            box-shadow: 0 0 5px rgba(63,81,181,0.2);
            outline: none;
        }

        .card button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background-color: #3f51b5;
            color: #fff;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
        }

        .card button:hover {
            background-color: #303f9f;
        }

        .card p {
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }

        .card p a {
            color: #3f51b5;
            text-decoration: none;
            font-weight: 500;
        }

        .error-message {
            color: #e53935;
            font-size: 14px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
<div class="card">
    <h2>Login</h2>
    <?php if($message): ?>
        <div class="error-message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <form id="loginForm" method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
    <p>No account? <a href="register.php">Register here</a></p>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', function(e){
    const username = this.username.value.trim();
    const password = this.password.value;
    if(!username || !password){
        e.preventDefault();
        alert('Both fields are required!');
    }
});
</script>
</body>
</html>
