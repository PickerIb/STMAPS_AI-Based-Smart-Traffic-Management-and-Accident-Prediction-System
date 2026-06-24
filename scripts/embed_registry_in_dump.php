<?php
$src = file_get_contents(__DIR__ . '/../database/api_id_registry_data.sql');
$src = preg_replace('/DROP TABLE IF EXISTS[^;]+;\s*/', '', $src);
$src = str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $src);
$src = preg_replace('/^--.*\n/m', '', $src);

$dump = file_get_contents(__DIR__ . '/../database/smart_traffic_db.sql');
$marker = "-- --------------------------------------------------------\n-- AUTO_INCREMENT values";

$section = "-- --------------------------------------------------------\n"
    . "-- API registry tables (driver licenses & police IDs)\n"
    . "-- --------------------------------------------------------\n\n"
    . trim($src) . "\n\n";

if (strpos($dump, 'api_driver_licenses') !== false) {
    echo "Registry already embedded in smart_traffic_db.sql\n";
    exit(0);
}

$dump = str_replace($marker, $section . $marker, $dump);

// Remove trailing ALTER lines from embedded section only (keep AUTO_INCREMENT block)
$dump = preg_replace(
    '/(INSERT INTO `api_police_ids`[^;]+;)\s*ALTER TABLE `api_driver_licenses` MODIFY[^;]+;\s*ALTER TABLE `api_police_ids` MODIFY[^;]+;\s*(?=-- -{40})/s',
    "$1\n\n",
    $dump
);

file_put_contents(__DIR__ . '/../database/smart_traffic_db.sql', $dump);
echo "Embedded registry into smart_traffic_db.sql\n";
