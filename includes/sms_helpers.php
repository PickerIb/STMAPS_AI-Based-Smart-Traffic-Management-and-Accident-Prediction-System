<?php

function strgs_normalize_rwanda_phone($rawPhone)
{
    $clean = preg_replace('/\D+/', '', (string)$rawPhone);
    if ($clean === '') {
        return false;
    }
    if (str_starts_with($clean, '250') && strlen($clean) === 12) {
        return '+' . $clean;
    }
    if (str_starts_with($clean, '0') && strlen($clean) === 10) {
        return '+250' . substr($clean, 1);
    }
    if (preg_match('/^7\d{8}$/', $clean)) {
        return '+250' . $clean;
    }

    return false;
}

function strgs_get_sms_config(): ?array
{
    $path = dirname(__DIR__) . '/sms_settings.php';
    if (!file_exists($path)) {
        return null;
    }
    $cfg = require $path;
    return is_array($cfg) ? $cfg : null;
}

function strgs_log_sms_attempt(array $payload, string $logFile = 'sms_alerts.log'): void
{
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $payload['timestamp'] = date('c');
    @file_put_contents(
        $logDir . '/' . $logFile,
        json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function strgs_send_sms(string $phone, string $message, ?array $smsConfig = null): array
{
    $smsConfig = $smsConfig ?? strgs_get_sms_config();

    if (!is_array($smsConfig) || empty($smsConfig['enabled'])) {
        return ['sent' => false, 'reason' => 'sms_disabled', 'phone' => $phone];
    }

    $parsePath = dirname(__DIR__) . '/sms_parse.php';
    if (file_exists($parsePath)) {
        require_once $parsePath;
    }
    if (!class_exists('hdev_sms')) {
        return ['sent' => false, 'reason' => 'sms_class_not_loaded', 'phone' => $phone];
    }

    $apiId = trim((string)($smsConfig['api_id'] ?? ''));
    $apiKey = trim((string)($smsConfig['api_key'] ?? ''));
    $senderId = trim((string)($smsConfig['sender_id'] ?? ''));
    if ($apiId === '' || $apiKey === '' || $senderId === '') {
        return ['sent' => false, 'reason' => 'sms_credentials_missing', 'phone' => $phone];
    }

    try {
        hdev_sms::api_id($apiId);
        hdev_sms::api_key($apiKey);
        $result = hdev_sms::send($senderId, $phone, $message);
        return ['sent' => true, 'phone' => $phone, 'sender_id' => $senderId, 'response' => $result];
    } catch (Throwable $e) {
        return ['sent' => false, 'reason' => 'sms_exception', 'phone' => $phone, 'error' => $e->getMessage()];
    }
}

function strgs_count_sms_eligible_drivers(PDO $conn): int
{
    $stmt = $conn->prepare(
        "SELECT id, phone_number
         FROM users
         WHERE user_type = 'driver'
           AND phone_number IS NOT NULL
           AND TRIM(phone_number) <> ''"
    );
    $stmt->execute();

    $seenPhones = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $driver) {
        $phone = strgs_normalize_rwanda_phone((string)$driver['phone_number']);
        if ($phone !== false) {
            $seenPhones[$phone] = true;
        }
    }

    return count($seenPhones);
}

function strgs_finish_json_response(array $payload, ?callable $afterFlush = null): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    $json = json_encode($payload);
    if ($json === false) {
        $json = '{"success":false,"message":"Could not encode response"}';
    }
    if (!headers_sent()) {
        header('Content-Length: ' . strlen($json));
    }

    echo $json;
    if (function_exists('session_write_close')) {
        @session_write_close();
    }
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    @flush();
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    } else {
        @ignore_user_abort(true);
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
    }

    if ($afterFlush !== null) {
        try {
            $afterFlush();
        } catch (Throwable $e) {
            strgs_log_sms_attempt([
                'type' => 'road_blockage',
                'dispatch_error' => $e->getMessage(),
            ]);
        }
    }

    exit;
}

function strgs_send_road_blockage_sms_to_drivers(PDO $conn, string $roadName, string $details): array
{
    $stmt = $conn->prepare(
        "SELECT id, first_name, last_name, phone_number
         FROM users
         WHERE user_type = 'driver'
           AND phone_number IS NOT NULL
           AND TRIM(phone_number) <> ''"
    );
    $stmt->execute();

    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $smsConfig = strgs_get_sms_config();
    $road = trim($roadName) !== '' ? trim($roadName) : 'A road in Remera Sector';
    $note = trim($details) !== '' ? trim($details) : 'Traffic police report this road is blocked.';
    $message = 'STRGS Alert: ' . $road . ' is BLOCKED. ' . $note . ' Please choose an alternate route.';

    $sent = 0;
    $failed = 0;
    $skipped = 0;
    $seenPhones = [];

    foreach ($drivers as $driver) {
        $phone = strgs_normalize_rwanda_phone((string)$driver['phone_number']);
        if ($phone === false) {
            $skipped++;
            continue;
        }
        if (isset($seenPhones[$phone])) {
            continue;
        }
        $seenPhones[$phone] = true;

        $result = strgs_send_sms($phone, $message, $smsConfig);
        strgs_log_sms_attempt(array_merge([
            'driver_id' => (int)$driver['id'],
            'road' => $road,
            'type' => 'road_blockage',
        ], $result));

        if (!empty($result['sent'])) {
            $sent++;
        } else {
            $failed++;
        }
    }

    return [
        'sent' => $sent,
        'failed' => $failed,
        'skipped' => $skipped,
        'total_drivers' => count($drivers),
    ];
}
