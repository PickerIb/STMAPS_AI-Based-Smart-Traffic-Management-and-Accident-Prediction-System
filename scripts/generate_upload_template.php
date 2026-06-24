<?php
require __DIR__ . '/../config.php';

$db = (new Database())->getConnection();
if (!$db) {
    fwrite(STDERR, "DB connection failed" . PHP_EOL);
    exit(1);
}

$segments = $db->query("SELECT name FROM road_segments WHERE is_active = 1 ORDER BY id ASC")
    ->fetchAll(PDO::FETCH_COLUMN);
if (!$segments) {
    fwrite(STDERR, "No segments found" . PHP_EOL);
    exit(1);
}

$start = new DateTime('2026-05-01 00:00:00');
$weathers = ['clear', 'rain', 'fog', 'storm'];
$rows = [];
$rows[] = 'segment_name,reading_time,vehicle_count,avg_speed_kmh,density_veh_per_km,weather,visibility_m,temperature_c,source';

for ($i = 0; $i < 500; $i++) {
    $segment = $segments[$i % count($segments)];
    $dt = clone $start;
    $dt->modify('+' . (5 * $i) . ' minutes');

    $vehicle = 650 + (($i * 41) % 850);
    $speed = round(min(95, 20 + (($i * 3) % 52) + (($i % 6) * 0.4)), 1);
    $density = round(38 + (($i * 5) % 92) + (($i % 7) * 0.5), 1);
    $weather = $weathers[$i % count($weathers)];
    $visibility = 450 + (($i * 19) % 1700);
    $temperature = round(16 + (($i * 2) % 15) + (($i % 4) * 0.3), 1);

    $rows[] = sprintf(
        '%s,%s,%d,%s,%s,%s,%d,%s,csv_upload',
        $segment,
        $dt->format('Y-m-d H:i:s'),
        $vehicle,
        $speed,
        $density,
        $weather,
        $visibility,
        $temperature
    );
}

$outputPath = __DIR__ . '/../data/upload_template_segments.csv';
file_put_contents($outputPath, implode(PHP_EOL, $rows) . PHP_EOL);
echo "Generated 500 rows using " . count($segments) . " live segments" . PHP_EOL;

