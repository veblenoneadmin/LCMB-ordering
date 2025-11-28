<?php
$data = [
    'customer_name' => $order['customer_name'],
    'contact_number' => $order['contact_number'],
    'technician_uuid' => $staff_uuid,
    'date' => $order['service_date'],
    'total' => $total
];

$webhook = "https://primary-s0q-production.up.railway.app/webhook/8dc36143-3e26-4e47-a0f7-ab0cb8b2143d";
$ch = curl_init($webhook);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP: $code\nResponse: $response";
