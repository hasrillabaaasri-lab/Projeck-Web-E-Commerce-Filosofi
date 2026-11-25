<?php
// String QRIS Statis dari GoPay Merchant (HASRIL LABASRI, Otomotif)
$qris_statis = '00020101021126610014COM.GO-JEK.WWW01189360091434074719920210G4074719920303UMI51440014ID.CO.QRIS.WWW0215ID10264918962900303UMI5204753853033605802ID5924HASRIL LABASRI, Otomotif6006BEKASI61051715862140703A0111035216304A576';

function hitungCrc16Qris($str) {
    $crc = 0xFFFF;
    $strlen = strlen($str);
    for ($c = 0; $c < $strlen; $c++) {
        $crc ^= ord($str[$c]) << 8;
        for ($i = 0; $i < 8; $i++) {
            if ($crc & 0x8000) {
                $crc = ($crc << 1) ^ 0x1021;
            } else {
                $crc = $crc << 1;
            }
            $crc &= 0xFFFF;
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

function generateDynamicQris($static_qris, $amount) {
    $static_qris = trim($static_qris);
    if (strlen($static_qris) < 20 || strpos($static_qris, '5802ID') === false) {
        throw new Exception("Format teks QRIS statis tidak valid.");
    }

    // 1. Hapus 4 karakter CRC lama di akhir
    $qris_tanpa_crc = substr($static_qris, 0, -4);

    // 2. Ubah penanda QR Statis (010211) menjadi QR Dinamis (010212)
    $qris_dinamis = str_replace('010211', '010212', $qris_tanpa_crc);

    // 3. Susun Tag 54 (Nominal Tagihan) + 2 digit panjang karakter + angka nominal
    $nominal_str = (string) (int) round($amount);
    $panjang_nominal = str_pad(strlen($nominal_str), 2, '0', STR_PAD_LEFT);
    $tag_nominal = '54' . $panjang_nominal . $nominal_str;

    // 4. Sisipkan Tag 54 tepat sebelum Tag Negara (5802ID)
    $pecah = explode('5802ID', $qris_dinamis, 2);
    $payload_baru = $pecah[0] . $tag_nominal . '5802ID' . $pecah[1];

    // 5. Hitung ulang checksum CRC16 dan gabungkan di akhir
    return $payload_baru . hitungCrc16Qris($payload_baru);
}
?>