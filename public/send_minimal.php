<?php
// Adjust path to your config.php
require_once '/var/www/html/config.php';

// Response helper function
function sendResponse($success, $message, $data = null, $httpCode = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'http_code' => $httpCode
    ]);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method. POST required.');
}

// Validate order_id
if (!isset($_POST['order_id']) || empty($_POST['order_id'])) {
    sendResponse(false, 'Missing order_id parameter.');
}

$order_id = intval($_POST['order_id']);

if ($order_id <= 0) {
    sendResponse(false, 'Invalid order_id. Must be a positive integer.');
}

try {
    // Fetch order details from database
    $stmt = $pdo->prepare("
        SELECT 
            customer_name,
            customer_email,
            customer_phone,
            order_total,
            order_notes,
            created_at
        FROM orders 
        WHERE id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        sendResponse(false, "Order #$order_id not found in database.");
    }

    // Prepare ServiceM8 job data
    $jobData = [
        "job_address" => $order['customer_name'] ?? 'No address provided',
        "status" => "Quote", // or "Work Order", "Completed", etc.
        "job_description" => "Order #$order_id\n\n" . ($order['order_notes'] ?? ''),
        "generated_job_id" => $order_id, // Link to your order ID
    ];

    // Add optional fields if available
    if (!empty($order['customer_email'])) {
        $jobData['job_contact_email'] = $order['customer_email'];
    }
    
    if (!empty($order['customer_phone'])) {
        $jobData['job_contact_phone'] = $order['customer_phone'];
    }

    $jsonData = json_encode($jobData);

    // ServiceM8 API configuration
    $url = "https://api.servicem8.com/api_1.0/job.json";
    $apiKey = "smk-c6666a-425f803efbbda9d8-c800c54f9fb4f1e8";

    // Initialize cURL
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Accept: application/json",
            "X-API-Key: $apiKey"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonData
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Handle cURL errors
    if ($curlError) {
        sendResponse(false, 'Connection error: ' . $curlError, null, $httpCode);
    }

    // Decode response
    $responseData = json_decode($response, true);

    // Check HTTP status code
    if ($httpCode >= 200 && $httpCode < 300) {
        // Success - optionally update your database
        try {
            $updateStmt = $pdo->prepare("
                UPDATE orders 
                SET servicem8_job_uuid = ?, 
                    servicem8_synced_at = NOW() 
                WHERE id = ?
            ");
            $jobUuid = $responseData['uuid'] ?? null;
            $updateStmt->execute([$jobUuid, $order_id]);
        } catch (PDOException $e) {
            // Log error but don't fail the request
            error_log("Failed to update order with ServiceM8 UUID: " . $e->getMessage());
        }

        sendResponse(
            true, 
            "Job successfully created in ServiceM8 for Order #$order_id",
            $responseData,
            $httpCode
        );
    } else {
        // API error
        $errorMessage = 'ServiceM8 API error';
        if (isset($responseData['error'])) {
            $errorMessage .= ': ' . $responseData['error'];
        } elseif (isset($responseData['message'])) {
            $errorMessage .= ': ' . $responseData['message'];
        }
        
        sendResponse(false, $errorMessage, $responseData, $httpCode);
    }

} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(false, 'Unexpected error: ' . $e->getMessage());
}