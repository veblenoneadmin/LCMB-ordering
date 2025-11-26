<?php
// Load Railway / environment variables
$servicem8_email   = getenv('SERVICEM8_EMAIL');
$servicem8_api_key = getenv('SERVICEM8_API_KEY');

if (!$servicem8_email || !$servicem8_api_key) {
    die("ServiceM8 credentials not set. Please check Railway variables.");
}

// Test connection to ServiceM8
$ch = curl_init("https://api.servicem8.com/api_1.0/job.json");
curl_setopt($ch, CURLOPT_USERPWD, "$servicem8_email:$servicem8_api_key");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Accept: application/json"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    die("cURL error: " . curl_error($ch));
}

curl_close($ch);

// Output results
echo "HTTP Code: $http_code\n";
echo "Response: $response\n";

if ($http_code === 200) {
    echo "\n✅ Connection successful! API credentials are valid.";
} else {
    echo "\n❌ Connection failed. Please check email/password or regenerate API key.";
}
