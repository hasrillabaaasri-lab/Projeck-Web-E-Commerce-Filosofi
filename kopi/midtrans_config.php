<?php
// Tempel hasil Copy dari tombol "Generate server key" di mode Sandbox
$server_key = 'SB-Mid-server-s.S4t6yRVTYva3e6Hb.cSKcw';
$client_key = 'Mid-client-5pDbNyCx8bVpt1Of';

// Set true selama masih pengujian di Sandbox
$is_sandbox = true;
$midtrans_charge_url = $is_sandbox 
    ? 'https://api.sandbox.midtrans.com/v2/charge' 
    : 'https://api.midtrans.com/v2/charge';

function callMidtrans($url, $data, $server_key) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    // Bypass verifikasi SSL khusus localhost XAMPP
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($server_key . ':')
    ]);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['status_message' => 'cURL Error: ' . $curl_error];
    }

    return json_decode($response, true);
}
?>