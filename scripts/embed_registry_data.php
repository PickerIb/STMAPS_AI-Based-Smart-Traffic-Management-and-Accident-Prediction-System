<?php
$src = file_get_contents(__DIR__ . '/../database/api_id_registry_data.sql');

if (!preg_match('/INSERT INTO `api_driver_licenses`[^;]+;/s', $src, $driverInsert)) {
    fwrite(STDERR, "Driver INSERT not found\n");
    exit(1);
}
if (!preg_match('/INSERT INTO `api_police_ids`[^;]+;/s', $src, $policeInsert)) {
    fwrite(STDERR, "Police INSERT not found\n");
    exit(1);
}

$dump = str_replace("\r\n", "\n", file_get_contents(__DIR__ . '/../database/smart_traffic_db.sql'));

if (strpos($dump, 'INSERT INTO `api_driver_licenses`') !== false) {
    echo "Registry data already in smart_traffic_db.sql\n";
    exit(0);
}

$anchor = 'CREATE TABLE IF NOT EXISTS `api_police_ids`';
$pos = strpos($dump, $anchor);
if ($pos === false) {
    fwrite(STDERR, "api_police_ids table definition not found\n");
    exit(1);
}

$autoPos = strpos($dump, '-- AUTO_INCREMENT values', $pos);
if ($autoPos === false) {
    fwrite(STDERR, "AUTO_INCREMENT section not found\n");
    exit(1);
}

$insertBlock = "\n" . trim($driverInsert[0]) . "\n\n" . trim($policeInsert[0]) . "\n\n";
$dump = substr($dump, 0, $autoPos) . $insertBlock . substr($dump, $autoPos);

file_put_contents(__DIR__ . '/../database/smart_traffic_db.sql', $dump);
echo "Inserted registry data into smart_traffic_db.sql\n";
