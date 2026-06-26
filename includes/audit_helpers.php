<?php

require_once __DIR__ . '/../config.php';

function strgs_log_audit_event(
    PDO $conn,
    int $userId,
    string $action,
    ?string $tableName = null,
    ?int $recordId = null,
    array $payload = []
): void {
    try {
        $stmt = $conn->prepare(
            'INSERT INTO audit_log (user_id, action, table_name, record_id, new_values, created_at)
             VALUES (:uid, :action, :tbl, :rid, :vals, NOW())'
        );
        $stmt->execute([
            ':uid' => $userId > 0 ? $userId : null,
            ':action' => $action,
            ':tbl' => $tableName,
            ':rid' => $recordId,
            ':vals' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    } catch (Throwable $e) {
        error_log('STRGS audit log failed: ' . $e->getMessage());
    }
}

function strgs_audit_role_label(?string $userType): string
{
    if ($userType === null || $userType === '') {
        return 'User';
    }
    return getUserTypeLabel($userType);
}

function strgs_format_audit_details(string $action, ?string $json): string
{
    if ($json === null || trim($json) === '') {
        return 'No additional details recorded.';
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        $plain = trim($json);
        return $plain !== '' ? $plain : 'No additional details recorded.';
    }

    return match ($action) {
        'login' => sprintf(
            'Signed in successfully as %s (%s).',
            $data['email'] ?? ($data['username'] ?? 'this account'),
            strgs_audit_role_label($data['user_type'] ?? null)
        ),
        'logout' => sprintf(
            'Signed out from %s (%s).',
            $data['email'] ?? 'this account',
            strgs_audit_role_label($data['user_type'] ?? null)
        ),
        'password_change' => 'Password was changed for this account.',
        'admin_user_create' => sprintf(
            'Created account for %s (%s, %s).',
            trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')) ?: ($data['email'] ?? 'new user'),
            $data['email'] ?? 'no email',
            strgs_audit_role_label($data['user_type'] ?? null)
        ),
        'admin_user_update' => sprintf(
            'Updated account settings — email: %s, role: %s.',
            $data['email'] ?? 'unchanged',
            strgs_audit_role_label($data['user_type'] ?? null)
        ),
        'admin_user_delete' => 'User account was permanently removed from the system.',
        'admin_user_activate' => 'User account was activated and can sign in again.',
        'admin_user_deactivate' => 'User account was deactivated and cannot sign in.',
        'admin_password_reset' => 'Administrator reset the password for this user account.',
        'admin_settings_update' => strgs_format_settings_audit_details($data),
        'admin_announcement' => sprintf(
            'Announcement published: %s — %s',
            $data['title'] ?? 'System announcement',
            $data['message'] ?? ''
        ),
        'admin_emergency_alert' => sprintf(
            'Emergency alert sent to all users: %s — %s',
            $data['title'] ?? 'Emergency alert',
            $data['message'] ?? ''
        ),
        'road_status_update' => sprintf(
            'Road status updated — vehicles: %s, average speed: %s km/h, source: %s.',
            $data['vehicle_count'] ?? '—',
            $data['avg_speed_kmh'] ?? '—',
            $data['source'] ?? 'traffic police'
        ),
        'incident_report' => sprintf(
            'Accident reported — severity: %s, cause: %s.',
            ucfirst((string)($data['severity'] ?? 'unknown')),
            $data['cause'] ?? ($data['description'] ?? 'not specified')
        ),
        'alert_report' => sprintf(
            'Traffic alert reported — type: %s, severity: %s, message: %s',
            str_replace('_', ' ', (string)($data['type'] ?? 'alert')),
            ucfirst((string)($data['severity'] ?? 'info')),
            $data['message'] ?? '—'
        ),
        'alert_cleared' => 'Traffic alert was marked as cleared or resolved.',
        'accident_resolved' => 'Accident report was marked as resolved.',
        'route_guidance' => sprintf(
            'Route guidance requested from %s to %s (traffic level: %s).',
            $data['origin'] ?? 'current location',
            $data['destination'] ?? 'destination',
            ucfirst((string)($data['congestion_level'] ?? 'unknown'))
        ),
        default => strgs_format_audit_details_generic($data),
    };
}

function strgs_format_settings_audit_details(array $data): string
{
    $labels = [
        'app_name' => 'Application name',
        'timezone' => 'Timezone',
        'study_zone' => 'Study zone',
        'notifications_enabled' => 'In-app notifications',
        'sms_alerts_enabled' => 'SMS alerts',
        'map_center_lat' => 'Map latitude',
        'map_center_lng' => 'Map longitude',
        'map_default_zoom' => 'Map zoom',
        'auto_refresh_seconds' => 'Auto-refresh (seconds)',
        'backup_auto_enabled' => 'Automatic backup',
        'backup_retention_days' => 'Backup retention (days)',
        'session_timeout_minutes' => 'Session timeout (minutes)',
        'maintenance_mode' => 'Maintenance mode',
    ];

    $parts = [];
    foreach ($labels as $key => $label) {
        if (!array_key_exists($key, $data)) {
            continue;
        }
        $value = $data[$key];
        if (is_bool($value)) {
            $value = $value ? 'On' : 'Off';
        }
        $parts[] = $label . ': ' . $value;
    }

    return $parts
        ? 'System settings saved — ' . implode('; ', $parts) . '.'
        : 'System settings were updated.';
}

function strgs_format_audit_details_generic(array $data): string
{
    $parts = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            continue;
        }
        $label = ucwords(str_replace('_', ' ', (string)$key));
        if (is_bool($value)) {
            $value = $value ? 'Yes' : 'No';
        }
        $text = trim((string)$value);
        if ($text === '') {
            continue;
        }
        $parts[] = $label . ': ' . $text;
        if (count($parts) >= 4) {
            break;
        }
    }

    return $parts ? implode(' · ', $parts) . '.' : 'Activity recorded.';
}

function strgs_format_relative_time(?string $datetime): string
{
    if ($datetime === null || trim($datetime) === '') {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $mins = (int)floor($diff / 60);
        return $mins . ' minute' . ($mins === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $hours = (int)floor($diff / 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 604800) {
        $days = (int)floor($diff / 86400);
        return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    }
    return date('M j, Y g:i A', $ts);
}
