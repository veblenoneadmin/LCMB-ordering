<?php
$servicem8_email   = getenv('SERVICEM8_EMAIL');
$servicem8_api_key = getenv('SERVICEM8_API_KEY');

if (!$servicem8_email || !$servicem8_api_key) {
    die("Variables not set or empty.");
}

$ch = curl_init("https://api.servicem8.com/api_1.0/job.json");
curl_setopt($ch, CURLOPT_USERPWD, "$servicem8_email:$servicem8_api_key");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Accept: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response: $response\n";
