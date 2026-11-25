<?php
require_once 'midtrans_config.php';

// Data dummy untuk testing
$transaction_data = [
    'payment_type' => 'qris',
    'transaction_details' => [
        'order_id' => 'TEST-' . time(),
        'gross_amount' => 15000, // Rp 15.000
    ],
];

echo "<h2>Mengirim data ke Midtrans Sandbox...</h2>";

// Panggil API Sandbox
$result = callMidtrans('https://api.sandbox.midtrans.com/v2/charge', $transaction_data, $server_key);

echo "<h3>Respons dari Midtrans:</h3>";
echo "<pre>";
print_r($result);
echo "</pre>";
?>