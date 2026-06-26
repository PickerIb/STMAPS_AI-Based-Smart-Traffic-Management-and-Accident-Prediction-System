<?php

/**
 * STRGS system settings — stored in data/system_settings.json
 */
function strgs_system_settings_defaults(): array
{
    return [
        'app_name' => 'STRGS',
        'timezone' => 'Africa/Kigali',
        'study_zone' => 'Remera Sector',
        'notifications_enabled' => true,
        'sms_alerts_enabled' => true,
        'map_center_lat' => -1.9531,
        'map_center_lng' => 30.1065,
        'map_default_zoom' => 14,
        'auto_refresh_seconds' => 30,
        'backup_auto_enabled' => false,
        'backup_retention_days' => 30,
        'session_timeout_minutes' => 60,
        'maintenance_mode' => false,
    ];
}

function strgs_system_settings_path(): string
{
    return dirname(__DIR__) . '/data/system_settings.json';
}

function strgs_system_settings(bool $forceReload = false): array
{
    static $cache = null;
    if ($forceReload) {
        $cache = null;
    }
    if ($cache !== null) {
        return $cache;
    }

    $defaults = strgs_system_settings_defaults();
    $path = strgs_system_settings_path();

    if (!file_exists($path)) {
        $cache = $defaults;
        return $cache;
    }

    $data = json_decode((string)file_get_contents($path), true);
    if (!is_array($data)) {
        $cache = $defaults;
        return $cache;
    }

    // Drop removed legacy keys
    unset($data['email_notifications']);

    $cache = array_merge($defaults, $data);
    return $cache;
}

function strgs_system_settings_bool($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    $normalized = strtolower(trim((string)$value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function strgs_is_maintenance_mode(): bool
{
    return !empty(strgs_system_settings()['maintenance_mode']);
}

function strgs_block_if_maintenance(string $userType): void
{
    if (strgs_is_maintenance_mode() && $userType !== 'administrator') {
        if (str_starts_with(basename($_SERVER['SCRIPT_NAME'] ?? ''), 'index.php')) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(503);
            echo json_encode([
                'success' => false,
                'message' => 'System is under maintenance. Only administrators can access STRGS right now.',
            ]);
            exit;
        }

        http_response_code(503);
        header('Location: login.php?maintenance=1');
        exit;
    }
}

function strgs_apply_system_timezone(): void
{
    $tz = trim((string)(strgs_system_settings()['timezone'] ?? ''));
    if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
        date_default_timezone_set($tz);
    }
}

function strgs_maybe_run_settings_backup(): void
{
    $settings = strgs_system_settings();
    if (empty($settings['backup_auto_enabled'])) {
        return;
    }

    $backupDir = dirname(__DIR__) . '/data/backups';
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0777, true);
    }

    $stamp = date('Y-m-d_His');
    $files = [
        strgs_system_settings_path(),
        dirname(__DIR__) . '/data/admin_announcements.json',
    ];

    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }
        $dest = $backupDir . '/' . basename($file, '.json') . '_' . $stamp . '.json';
        @copy($file, $dest);
    }

    $retentionDays = max(1, (int)($settings['backup_retention_days'] ?? 30));
    $cutoff = time() - ($retentionDays * 86400);
    foreach (glob($backupDir . '/*.json') ?: [] as $old) {
        if (is_file($old) && filemtime($old) < $cutoff) {
            @unlink($old);
        }
    }
}
