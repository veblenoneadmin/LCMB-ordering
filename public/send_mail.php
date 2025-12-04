<?php
require_once __DIR__ . '/../config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Make sure you installed PHPMailer via Composer:
// composer require phpmailer/phpmailer

require __DIR__ . '/../vendor/autoload.php'; // adjust path if needed

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method Not Allowed");
}

// Get POST data safely
$order_id  = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$recipient = filter_var($_POST['recipient'] ?? '', FILTER_VALIDATE_EMAIL);
$subject   = trim($_POST['subject'] ?? '');
$message   = trim($_POST['message'] ?? '');

if (!$order_id || !$recipient || !$subject || !$message) {
    die("❌ Missing required fields.");
}

// Fetch order items to include in email
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    die("❌ Order not found.");
}

$stmtItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmtItems->execute([$order_id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Build HTML email content
$emailBody = "<h2>Order #{$order_id} Details</h2>";
$emailBody .= "<p>Customer: " . htmlspecialchars($order['customer_name'] ?? '') . "</p>";
$emailBody .= "<p>Appointment Date: " . htmlspecialchars($order['appointment_date'] ?? '') . "</p>";

$emailBody .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse; width:100%;'>";
$emailBody .= "<thead><tr>
<th>Item</th>
<th>Price</th>
<th>Quantity/Hours</th>
<th>Subtotal</th>
</tr></thead><tbody>";

$subtotal = 0;

foreach ($items as $item) {
    $name  = htmlspecialchars($item['name'] ?? 'Unknown');
    $price = floatval($item['price'] ?? 0);
    $qty   = intval($item['qty'] ?? 1);
    $sub   = $price * $qty;
    $subtotal += $sub;

    $emailBody .= "<tr>
        <td>{$name}</td>
        <td>$" . number_format($price, 2) . "</td>
        <td>{$qty}</td>
        <td>$" . number_format($sub, 2) . "</td>
    </tr>";
}

$tax = round($subtotal * 0.10, 2);
$grand_total = $subtotal + $tax;

$emailBody .= "<tr>
    <td colspan='3' style='text-align:right;'>Subtotal</td>
    <td>$" . number_format($subtotal, 2) . "</td>
</tr>";
$emailBody .= "<tr>
    <td colspan='3' style='text-align:right;'>Tax (10%)</td>
    <td>$" . number_format($tax, 2) . "</td>
</tr>";
$emailBody .= "<tr>
    <td colspan='3' style='text-align:right; font-weight:bold;'>Grand Total</td>
    <td><b>$" . number_format($grand_total, 2) . "</b></td>
</tr>";

$emailBody .= "</tbody></table>";

// Send email using PHPMailer
$mail = new PHPMailer(true);

try {
    // SMTP configuration
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; // replace with your SMTP server
    $mail->SMTPAuth   = true;
    $mail->Username   = 'your_email@gmail.com';   // SMTP username
    $mail->Password   = 'your_email_app_password'; // App password or SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Recipients
    $mail->setFrom('your_email@gmail.com', 'Your Company');
    $mail->addAddress($recipient);

    // Content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $emailBody;
    $mail->AltBody = strip_tags($emailBody);

    $mail->send();
    echo "<h2 style='color:green; padding:20px;'>✅ Email successfully sent to {$recipient}</h2>";
} catch (Exception $e) {
    echo "<h2 style='color:red; padding:20px;'>❌ Email could not be sent. Mailer Error: {$mail->ErrorInfo}</h2>";
}

// Redirect back to review_order.php after 3 seconds
echo "<p>Redirecting back...</p><script>setTimeout(()=>{window.location='review_order.php?order_id={$order_id}'}, 3000);</script>";
