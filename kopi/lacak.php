<?php
session_start();
require_once 'config.php';

$order_id   = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$order_code = isset($_GET['order_code']) ? trim($_GET['order_code']) : '';

$trackingData = [
    'order_id'            => 0,
    'order_code'          => 'FK-20261124-001',
    'service_name'        => 'Reguler Pengiriman estimasi 1 jam pengiriman',
    'courier_name'        => 'Bakicrut',
    'courier_phone'       => '+62 821-2301-9456',
    'recipient_name'      => 'Pelanggan',
    'store_name'          => 'Filosofi Kopi Pos Bloc',
    'destination_address' => 'Jl. Pecenongan, Jakarta Pusat',
    'status'              => 'Dikirim',
    'order_date'          => date('Y-m-d H:i:s', strtotime('-30 minutes')),
    'waktu_diproses'      => date('Y-m-d H:i:s', strtotime('-20 minutes')),
    'waktu_dikirim'       => date('Y-m-d 10:30:00'),
    'waktu_selesai'       => null,
    'store_lat'           => -6.16525,
    'store_lng'           => 106.83575,
    'dest_lat'            => -6.16455,
    'dest_lng'            => 106.82665,
    'has_exact_coords'    => false,
];

function sanitizeTextField($val, string $fallback): string {
    if (!is_string($val)) return $fallback;
    $trimmed = trim($val);
    if ($trimmed === '' || is_numeric($trimmed)) {
        return $fallback;
    }
    return $trimmed;
}

if (isset($pdo)) {
    try {
        $orderRow = null;
        if ($order_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
            $stmt->execute([$order_id]);
            $orderRow = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif ($order_code !== '') {
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_code = ? LIMIT 1");
            $stmt->execute([$order_code]);
            $orderRow = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif (!empty($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([(int)$_SESSION['user_id']]);
            $orderRow = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->query("SELECT * FROM orders WHERE status IN ('Dikirim','Diproses','Selesai') ORDER BY id DESC LIMIT 1");
            $orderRow = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($orderRow) {
            $trackingData['order_id']       = (int)$orderRow['id'];
            $trackingData['order_code']     = $orderRow['order_code'] ?? $trackingData['order_code'];
            $trackingData['status']         = $orderRow['status'] ?? 'Pending';
            $trackingData['order_date']     = $orderRow['order_date'] ?? $trackingData['order_date'];
            $trackingData['waktu_diproses'] = $orderRow['waktu_diproses'] ?? null;
            $trackingData['waktu_dikirim']  = $orderRow['waktu_dikirim'] ?? null;
            $trackingData['waktu_selesai']  = $orderRow['waktu_selesai'] ?? null;

            if (!empty($orderRow['courier_name'])) {
                $trackingData['courier_name'] = sanitizeTextField($orderRow['courier_name'], $trackingData['courier_name']);
            }
            if (!empty($orderRow['courier_phone'])) {
                $trackingData['courier_phone'] = trim($orderRow['courier_phone']);
            }

            $uid = (int)($orderRow['user_id'] ?? 0);
            if ($uid > 0) {
                $stmtU = $pdo->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
                $stmtU->execute([$uid]);
                $uName = $stmtU->fetchColumn();
                if ($uName) {
                    $trackingData['recipient_name'] = sanitizeTextField($uName, $trackingData['recipient_name']);
                }

                $stmtA = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_primary DESC, id DESC LIMIT 1");
                $stmtA->execute([$uid]);
                $addrRow = $stmtA->fetch(PDO::FETCH_ASSOC);

                if ($addrRow) {
                    if (!empty($addrRow['recipient_name'])) {
                        $trackingData['recipient_name'] = sanitizeTextField($addrRow['recipient_name'], $trackingData['recipient_name']);
                    }
                    $parts = [];
                    foreach (['full_address', 'address', 'alamat', 'street', 'subdistrict', 'kecamatan', 'city', 'kota', 'province', 'provinsi'] as $col) {
                        if (!empty($addrRow[$col]) && !is_numeric(trim((string)$addrRow[$col]))) {
                            $parts[] = trim((string)$addrRow[$col]);
                        }
                    }
                    if (!empty($parts)) {
                        $trackingData['destination_address'] = implode(', ', array_unique($parts));
                    }

                    $latVal = $addrRow['latitude'] ?? ($addrRow['lat'] ?? null);
                    $lngVal = $addrRow['longitude'] ?? ($addrRow['lng'] ?? null);
                    if (is_numeric($latVal) && is_numeric($lngVal) && (float)$latVal !== 0.0 && (float)$lngVal !== 0.0) {
                        $trackingData['dest_lat']         = (float)$latVal;
                        $trackingData['dest_lng']         = (float)$lngVal;
                        $trackingData['has_exact_coords'] = true;
                    }
                }
            }
        }
    } catch (PDOException $e) {}
}

if (isset($_GET['ajax_status']) && $trackingData['order_id'] > 0 && isset($pdo)) {
    header('Content-Type: application/json');
    echo json_encode([
        'status'         => $trackingData['status'],
        'waktu_diproses' => $trackingData['waktu_diproses'],
        'waktu_dikirim'  => $trackingData['waktu_dikirim'],
        'waktu_selesai'  => $trackingData['waktu_selesai'],
    ]);
    exit();
}

$statusNow   = $trackingData['status'];
$tOrder      = strtotime($trackingData['order_date']);
$tProses     = !empty($trackingData['waktu_diproses']) ? strtotime($trackingData['waktu_diproses']) : ($tOrder + 120);
$jamKirimStr = !empty($trackingData['waktu_dikirim']) ? date('h:i A', strtotime($trackingData['waktu_dikirim'])) : '10:30 AM';
$tSelesai    = !empty($trackingData['waktu_selesai']) ? strtotime($trackingData['waktu_selesai']) : time();

$isShipped   = in_array($statusNow, ['Dikirim', 'Selesai'], true);
$isDelivered = ($statusNow === 'Selesai');
$isProcessed = in_array($statusNow, ['Diproses', 'Dikirim', 'Selesai'], true);

$timelineSteps = [
    [
        'id'          => 'step_delivered',
        'time'        => $isDelivered ? date('h:i A', $tSelesai) : 'Estimasi',
        'title'       => 'Paket Sudah Di serahkan',
        'description' => $isDelivered
            ? 'pesanan mu telah tiba di lokasi pengantaran (' . $trackingData['destination_address'] . ')'
            : 'menunggu kurir tiba di alamat tujuan: ' . $trackingData['destination_address'],
        'completed'   => false,
        'is_active'   => $isDelivered,
        'courier_box' => false,
    ],
    [
        'id'          => 'step_on_way',
        'time'        => $isShipped ? $jamKirimStr : '--:--',
        'title'       => 'Kurir dalam perjalanan mengambil pesanan mu',
        'description' => 'pesanan anda sedang di kirim menuju ' . $trackingData['destination_address'],
        'completed'   => $isShipped,
        'is_active'   => false,
        'courier_box' => false,
    ],
    [
        'id'          => 'step_preparing_ship',
        'time'        => $isProcessed ? date('h:i A', $tProses) : '--:--',
        'title'       => 'Sedang Menyiapkan Pengiriman',
        'description' => 'Paket Anda Sedang Di siapkan Oleh kedai Dan Akan Di serahkan kepada kurir untuk tindak lanjutkan Pengiriman',
        'completed'   => $isProcessed,
        'is_active'   => false,
        'courier_box' => true,
    ],
    [
        'id'          => 'step_store_prep',
        'time'        => date('h:i A', $tOrder),
        'title'       => 'kedai sedang menyiapkan pesanan',
        'description' => 'Paket Anda Telah Di kedai. Penerima:' . $trackingData['recipient_name'],
        'completed'   => true,
        'is_active'   => false,
        'courier_box' => false,
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lacak Pengiriman #<?= htmlspecialchars($trackingData['order_code']) ?> - Filosofi Kopi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <link rel="stylesheet" href="lacak.css">
</head>
<body>

    <header class="top-header">
        <a href="beranda.php" class="brand-link" title="Kembali ke Beranda">
            <div class="logo-circle">
                <img src="image 24.png" alt="Logo Filosofi Kopi" onerror="this.style.display='none'">
            </div>
            <span class="brand-name">Filosofi Kopi</span>
        </a>
    </header>

    <main class="tracking-container">

        <section class="map-wrapper">
            <div id="deliveryMap" class="delivery-map"></div>
        </section>

        <!-- INFORMASI KURIR + AUTO GPS -->
        <section class="courier-header-section">
            <div class="courier-meta">
                <p class="service-estimate">
                    <?= htmlspecialchars($trackingData['service_name']) ?>
                    <span id="liveStatusBadge" style="display:inline-block; margin-left:6px; padding:2px 8px; border-radius:10px; font-size:11px; background:#2b170e; color:#fff;">
                        <?= htmlspecialchars($statusNow) ?>
                    </span>
                    <span id="gpsIndicator" style="display:inline-block; margin-left:4px; padding:2px 8px; border-radius:10px; font-size:11px; background:#e8f5e9; color:#1b5e20; font-weight:600;">
                        <i class="fa-solid fa-location-crosshairs"></i> GPS Otomatis
                    </span>
                </p>
                <div class="driver-plate-row">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($trackingData['courier_name']) ?>&background=2b170e&color=fff&size=80"
                         alt="Foto Kurir"
                         class="driver-avatar">
                    <span id="liveStageNote" style="font-size:12.5px; font-weight:600; color:#3b2316;"></span>
                </div>
            </div>
        </section>

        <!-- TIMELINE RIWAYAT PENGIRIMAN -->
        <section class="timeline-section">
            <?php foreach ($timelineSteps as $index => $step): ?>
                <div class="timeline-item <?= $index === count($timelineSteps) - 1 ? 'is-last' : '' ?>"
                     id="<?= htmlspecialchars($step['id']) ?>">
                    <div class="timeline-time" data-role="time">
                        <?= htmlspecialchars($step['time']) ?>
                    </div>

                    <div class="timeline-marker-col">
                        <?php if ($step['completed']): ?>
                            <span class="marker-dot is-done" data-role="dot">
                                <i class="fa-solid fa-check"></i>
                            </span>
                        <?php else: ?>
                            <span class="marker-dot is-current" data-role="dot" style="<?= $step['is_active'] ? 'background-color:#22b14c;' : '' ?>"></span>
                        <?php endif; ?>
                        <?php if ($index < count($timelineSteps) - 1): ?>
                            <span class="timeline-line"></span>
                        <?php endif; ?>
                    </div>

                    <div class="timeline-content">
                        <h3 class="step-title"><?= htmlspecialchars($step['title']) ?></h3>
                        <p class="step-desc" data-role="desc"><?= htmlspecialchars($step['description']) ?></p>

                        <?php if ($step['courier_box']): ?>
                            <div class="courier-detail-card">
                                <div>Nama Kurir : <?= htmlspecialchars($trackingData['courier_name']) ?></div>
                                <div>Nomor Tlp : <?= htmlspecialchars($trackingData['courier_phone']) ?></div>
                                <div>Tujuan : <?= htmlspecialchars($trackingData['destination_address']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

    </main>

    <footer class="site-footer">
        <div class="footer-left">
            <div class="footer-logo-circle">
                <img src="image 24.png" alt="Logo Footer Filosofi Kopi" onerror="this.style.display='none'">
            </div>
            <span class="footer-brand">Filosofi Kopi</span>
        </div>

        <div class="footer-right">
            <div class="footer-top-row">
                <div class="footer-col">
                    <h4>Metode Pembayaran</h4>
                </div>
                <div class="footer-col">
                    <h4>Ikuti Kami</h4>
                    <div class="social-icons">
                        <a href="#" class="social-btn fb" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                        <a href="#" class="social-btn ig" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    </div>
                </div>
            </div>

            <div class="app-promo">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-note">$ Terdapat Diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://filosofikopi.id/app"
                     alt="QR Code Aplikasi"
                     class="footer-qr">
            </div>
        </div>
    </footer>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', async function () {
            const orderId            = <?= (int)$trackingData['order_id'] ?>;
            let orderStatus          = <?= json_encode($trackingData['status']) ?>;
            const jamKirimStr        = <?= json_encode($jamKirimStr) ?>;
            const storeCoords        = [<?= (float)$trackingData['store_lat'] ?>, <?= (float)$trackingData['store_lng'] ?>];
            let destCoords           = [<?= (float)$trackingData['dest_lat'] ?>, <?= (float)$trackingData['dest_lng'] ?>];
            const hasExactCoords     = <?= $trackingData['has_exact_coords'] ? 'true' : 'false' ?>;
            const destinationAddress = <?= json_encode($trackingData['destination_address']) ?>;
            const recipientName      = <?= json_encode($trackingData['recipient_name']) ?>;
            const storeName          = <?= json_encode($trackingData['store_name']) ?>;
            const courierName        = <?= json_encode($trackingData['courier_name']) ?>;

            const gpsBadge  = document.getElementById('gpsIndicator');
            const stageNote = document.getElementById('liveStageNote');

            function formatNowTime() {
                return new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
            }

            function markStepDone(stepId, timeStr, customDesc = null) {
                const el = document.getElementById(stepId);
                if (!el) return;
                const dot  = el.querySelector('[data-role="dot"]');
                const tEl  = el.querySelector('[data-role="time"]');
                const dEl  = el.querySelector('[data-role="desc"]');
                if (dot && !dot.classList.contains('is-done')) {
                    dot.className = 'marker-dot is-done';
                    dot.style.backgroundColor = '';
                    dot.innerHTML = '<i class="fa-solid fa-check"></i>';
                }
                if (tEl && timeStr) tEl.textContent = timeStr;
                if (dEl && customDesc) dEl.textContent = customDesc;
            }

            function setGpsBadge(text, active) {
                if (!gpsBadge) return;
                gpsBadge.innerHTML = `<i class="fa-solid fa-location-crosshairs"></i> ${text}`;
                gpsBadge.style.background = active ? '#dcfce7' : '#f3f4f6';
                gpsBadge.style.color      = active ? '#166534' : '#4b5563';
            }

            if (orderStatus === 'Dikirim' && 'geolocation' in navigator) {
                setGpsBadge('GPS Aktif', true);
                navigator.geolocation.getCurrentPosition(
                    () => setGpsBadge('GPS Aktif (Live)', true),
                    () => setGpsBadge('GPS Rute Otomatis', true),
                    { enableHighAccuracy: true, timeout: 6000, maximumAge: 0 }
                );
            } else {
                setGpsBadge(orderStatus === 'Selesai' ? 'Tiba di Tujuan' : 'Menunggu Kirim', false);
            }

            const map = L.map('deliveryMap', {
                zoomControl: true,
                attributionControl: false
            }).setView(storeCoords, 15);

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19
            }).addTo(map);

            async function geocodeAddress(addressText) {
                if (hasExactCoords || !addressText) return destCoords;
                try {
                    const query = encodeURIComponent(addressText + ', Indonesia');
                    const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${query}`);
                    const list = await res.json();
                    if (Array.isArray(list) && list.length > 0) {
                        return [parseFloat(list[0].lat), parseFloat(list[0].lon)];
                    }
                } catch (e) {}
                return destCoords;
            }

            destCoords = await geocodeAddress(destinationAddress);

            async function fetchFullRoadRoute(startLatLng, endLatLng) {
                try {
                    const url = `https://router.project-osrm.org/route/v1/driving/${startLatLng[1]},${startLatLng[0]};${endLatLng[1]},${endLatLng[0]}?overview=full&geometries=geojson`;
                    const response = await fetch(url);
                    const data = await response.json();
                    if (data.code === 'Ok' && data.routes && data.routes.length > 0) {
                        return data.routes[0].geometry.coordinates.map(coord => [coord[1], coord[0]]);
                    }
                } catch (e) {}
                return [startLatLng, endLatLng];
            }

            const fullRoute = await fetchFullRoadRoute(storeCoords, destCoords);

            const storeIcon = L.divIcon({
                className: 'custom-map-pin',
                html: '<div class="pin-store"><i class="fa-solid fa-mug-hot"></i></div>',
                iconSize: [28, 28],
                iconAnchor: [14, 28]
            });

            const courierIcon = L.divIcon({
                className: 'custom-map-pin',
                html: '<div class="pin-courier"><i class="fa-solid fa-motorcycle"></i></div>',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });

            const destIcon = L.divIcon({
                className: 'custom-map-pin',
                html: '<div class="pin-dest"><i class="fa-solid fa-location-dot"></i></div>',
                iconSize: [30, 30],
                iconAnchor: [15, 30]
            });

            L.marker(storeCoords, { icon: storeIcon })
                .addTo(map)
                .bindPopup(`<b>${storeName}</b><br>Titik Penjemputan`);

            L.marker(destCoords, { icon: destIcon })
                .addTo(map)
                .bindPopup(`<b>Alamat Tujuan (${recipientName})</b><br>${destinationAddress}`);

            const passedPolyline    = L.polyline([], { color: '#22c55e', weight: 5, opacity: 0.95 }).addTo(map);
            const remainingPolyline = L.polyline(fullRoute, { color: '#ef4444', weight: 5, opacity: 0.95 }).addTo(map);

            let currentStepIndex = 0;
            if (orderStatus === 'Selesai') {
                currentStepIndex = fullRoute.length - 1;
            } else if (orderStatus === 'Dikirim') {
                currentStepIndex = Math.min(1, fullRoute.length - 1);
            }

            const courierMarker = L.marker(fullRoute[currentStepIndex], { icon: courierIcon })
                .addTo(map)
                .bindPopup(`<b>Kurir: ${courierName}</b>`);

            function updateRouteVisual(idx) {
                const clampedIdx = Math.max(0, Math.min(idx, fullRoute.length - 1));
                const pos = fullRoute[clampedIdx];
                courierMarker.setLatLng(pos);
                passedPolyline.setLatLngs(fullRoute.slice(0, clampedIdx + 1));
                remainingPolyline.setLatLngs(fullRoute.slice(clampedIdx));
            }

            updateRouteVisual(currentStepIndex);
            map.fitBounds(L.latLngBounds(fullRoute), { padding: [40, 40] });

            let driveTimer = null;

            function startAutoProgression() {
                if (driveTimer || fullRoute.length <= 1) return;

                markStepDone('step_preparing_ship', formatNowTime());
                markStepDone('step_on_way', jamKirimStr, `pesanan anda sedang di kirim menuju ${destinationAddress}`);

                if (stageNote) {
                    stageNote.textContent = `Kurir melaju langsung ke ${destinationAddress} (${jamKirimStr})...`;
                }

                driveTimer = setInterval(() => {
                    if (orderStatus !== 'Dikirim') {
                        clearInterval(driveTimer);
                        driveTimer = null;
                        return;
                    }

                    if (currentStepIndex < fullRoute.length - 1) {
                        currentStepIndex++;
                        updateRouteVisual(currentStepIndex);
                    } else {
                        clearInterval(driveTimer);
                        driveTimer = null;
                        if (stageNote) {
                            stageNote.textContent = `Paket sudah diserahkan di ${destinationAddress}`;
                        }
                        markStepDone(
                            'step_delivered',
                            formatNowTime(),
                            `pesanan mu telah tiba di lokasi pengantaran (${destinationAddress})`
                        );
                    }
                }, 400);
            }

            if (orderStatus === 'Dikirim') {
                startAutoProgression();
            }

            if (orderId > 0) {
                setInterval(async () => {
                    try {
                        const res = await fetch(`lacak.php?order_id=${orderId}&ajax_status=1`);
                        const info = await res.json();
                        if (info && info.status && info.status !== orderStatus) {
                            orderStatus = info.status;
                            const badge = document.getElementById('liveStatusBadge');
                            if (badge) badge.textContent = orderStatus;

                            if (orderStatus === 'Dikirim') {
                                setGpsBadge('GPS Aktif', true);
                                startAutoProgression();
                            } else {
                                location.reload();
                            }
                        }
                    } catch (e) {}
                }, 5000);
            }
        });
    </script>
</body>
</html>