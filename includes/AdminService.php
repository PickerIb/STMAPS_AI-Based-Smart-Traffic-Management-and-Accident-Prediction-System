<?php

require_once __DIR__ . '/DriverRouteService.php';
require_once __DIR__ . '/system_settings.php';
require_once __DIR__ . '/sms_helpers.php';
require_once __DIR__ . '/audit_helpers.php';

class AdminService
{
    private PDO $conn;
    private string $settingsPath;
    private string $announcementsPath;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $dataDir = dirname(__DIR__) . '/data';
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0777, true);
        }
        $this->settingsPath = $dataDir . '/system_settings.json';
        $this->announcementsPath = $dataDir . '/admin_announcements.json';
    }

    public function getDashboardHome(int $adminId): array
    {
        $counts = ['driver' => 0, 'traffic_authority' => 0, 'administrator' => 0];
        $stmt = $this->conn->query(
            "SELECT user_type, COUNT(*) AS cnt FROM users GROUP BY user_type"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(string)$row['user_type']] = (int)$row['cnt'];
        }

        $activeUsers = (int)$this->conn->query(
            "SELECT COUNT(*) FROM users WHERE is_active = 1"
        )->fetchColumn();

        $roadStatusRecords = (int)$this->conn->query(
            "SELECT COUNT(*) FROM traffic_readings WHERE source = 'traffic_police' OR source LIKE '%police%'"
        )->fetchColumn();

        $accidents = (int)$this->conn->query("SELECT COUNT(*) FROM accidents")->fetchColumn();

        $alertStmt = $this->conn->query(
            "SELECT type, COUNT(*) AS cnt FROM alerts GROUP BY type"
        );
        $alertsByType = ['congestion' => 0, 'incident' => 0, 'road_blockage' => 0, 'system' => 0];
        foreach ($alertStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $alertsByType[(string)$row['type']] = (int)$row['cnt'];
        }

        $recentStmt = $this->conn->prepare(
            "SELECT al.id, al.action, al.table_name, al.created_at, al.new_values,
                    u.first_name, u.last_name, u.user_type
             FROM audit_log al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC
             LIMIT 12"
        );
        $recentStmt->execute();
        $recent = [];
        foreach ($recentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $recent[] = [
                'id' => (int)$row['id'],
                'action' => $row['action'],
                'label' => $this->formatAuditLabel($row),
                'user_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'System',
                'user_type' => $row['user_type'] ?? null,
                'created_at' => $row['created_at'],
            ];
        }

        $chartStmt = $this->conn->query(
            "SELECT DATE(created_at) AS day, COUNT(*) AS cnt
             FROM audit_log
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC"
        );

        return [
            'stats' => [
                'drivers' => $counts['driver'],
                'officers' => $counts['traffic_authority'],
                'admins' => $counts['administrator'],
                'active_users' => $activeUsers,
                'road_status_records' => $roadStatusRecords,
                'accident_reports' => $accidents,
                'congestion_reports' => $alertsByType['congestion'],
                'road_blockage_reports' => $alertsByType['road_blockage'],
                'total_alerts' => array_sum($alertsByType),
            ],
            'activity_chart' => $chartStmt->fetchAll(PDO::FETCH_ASSOC),
            'recent_activities' => $recent,
            'sector_summary' => (new DriverRouteService($this->conn))->getRoadStatusSummary(),
        ];
    }

    public function listUsers(string $search = '', string $roleFilter = '', string $statusFilter = '', int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(50, $perPage));
        $offset = ($page - 1) * $perPage;
        $where = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[] = '(first_name LIKE :q OR last_name LIKE :q OR email LIKE :q OR username LIKE :q OR phone_number LIKE :q OR license_id LIKE :q OR police_id LIKE :q)';
            $params[':q'] = '%' . $search . '%';
        }
        if (in_array($roleFilter, ['driver', 'traffic_authority', 'administrator'], true)) {
            $where[] = 'user_type = :ut';
            $params[':ut'] = $roleFilter;
        }
        if ($statusFilter === 'active') {
            $where[] = 'is_active = 1';
        } elseif ($statusFilter === 'inactive') {
            $where[] = 'is_active = 0';
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE $whereSql");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->conn->prepare(
            "SELECT id, first_name, last_name, username, email, phone_number, user_type,
                    license_id, police_id, role, is_active, last_login, created_at, profile_picture
             FROM users WHERE $whereSql
             ORDER BY created_at DESC
             LIMIT :lim OFFSET :off"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'users' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => max(1, (int)ceil($total / $perPage)),
            ],
        ];
    }

    public function createUser(int $adminId, array $data): array
    {
        $user = new User($this->conn);
        $first = trim((string)($data['first_name'] ?? ''));
        $last = trim((string)($data['last_name'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $phone = trim((string)($data['phone_number'] ?? ''));
        $username = trim((string)($data['username'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $userType = strtolower(trim((string)($data['user_type'] ?? 'driver')));
        $licenseId = trim((string)($data['license_id'] ?? ''));
        $policeId = trim((string)($data['police_id'] ?? ''));

        if ($first === '' || $last === '') {
            return ['success' => false, 'message' => 'First and last name are required.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address.'];
        }
        if ($username === '') {
            $username = strtolower(preg_replace('/[^a-z0-9]/', '', $first . $last)) ?: 'user' . time();
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
        }
        if (!in_array($userType, ['driver', 'traffic_authority', 'administrator'], true)) {
            return ['success' => false, 'message' => 'Invalid user role.'];
        }

        if ($user->emailExists($email)) {
            return ['success' => false, 'message' => 'Email is already registered.'];
        }
        if ($user->usernameExists($username)) {
            return ['success' => false, 'message' => 'Username is already taken.'];
        }
        if ($phone !== '' && $user->phoneExists($phone)) {
            return ['success' => false, 'message' => 'Phone number is already registered.'];
        }

        $user->first_name = $first;
        $user->last_name = $last;
        $user->username = $username;
        $user->email = $email;
        $user->phone_number = $phone !== '' ? $phone : null;
        $user->user_type = $userType;
        $user->license_id = ($userType === 'driver' && $licenseId !== '') ? $licenseId : null;
        $user->police_id = ($userType === 'traffic_authority' && $policeId !== '') ? $policeId : null;
        $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
        $user->role = match ($userType) {
            'administrator' => 'admin',
            'traffic_authority' => 'authority',
            default => 'viewer',
        };
        $user->is_active = !empty($data['is_active']) ? 1 : 1;

        if (!$user->create()) {
            return ['success' => false, 'message' => 'Could not create user account.'];
        }

        $this->writeAudit($adminId, 'admin_user_create', 'users', (int)$user->id, [
            'email' => $email,
            'user_type' => $userType,
        ]);

        return ['success' => true, 'message' => 'User account created successfully.', 'user_id' => (int)$user->id];
    }

    public function updateUser(int $adminId, int $targetId, array $data): array
    {
        if ($targetId <= 0) {
            return ['success' => false, 'message' => 'Invalid user.'];
        }

        $stmt = $this->conn->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $targetId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        $first = trim((string)($data['first_name'] ?? $existing['first_name']));
        $last = trim((string)($data['last_name'] ?? $existing['last_name']));
        $email = trim((string)($data['email'] ?? $existing['email']));
        $phone = trim((string)($data['phone_number'] ?? $existing['phone_number'] ?? ''));
        $username = trim((string)($data['username'] ?? $existing['username']));
        $userType = strtolower(trim((string)($data['user_type'] ?? $existing['user_type'])));
        if ($existing['user_type'] === 'administrator') {
            $userType = 'administrator';
        }
        $licenseId = trim((string)($existing['license_id'] ?? ''));
        $policeId = trim((string)($existing['police_id'] ?? ''));
        $isActive = array_key_exists('is_active', $data)
            ? (int)$data['is_active'] === 1
            : (int)$existing['is_active'] === 1;
        $password = (string)($data['password'] ?? '');
        $role = match ($userType) {
            'administrator' => 'admin',
            'traffic_authority' => 'authority',
            default => 'viewer',
        };

        if ($first === '' || $last === '') {
            return ['success' => false, 'message' => 'First and last name are required.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address.'];
        }
        if ($username === '') {
            $username = strtolower(preg_replace('/[^a-z0-9]/', '', $first . $last)) ?: $existing['username'];
        }
        if (!in_array($userType, ['driver', 'traffic_authority', 'administrator'], true)) {
            return ['success' => false, 'message' => 'Invalid user role.'];
        }
        if ($targetId === $adminId && !$isActive) {
            return ['success' => false, 'message' => 'You cannot deactivate your own account.'];
        }
        if ($password !== '' && strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
        }

        $dup = $this->conn->prepare('SELECT id FROM users WHERE email = :e AND id <> :id LIMIT 1');
        $dup->execute([':e' => $email, ':id' => $targetId]);
        if ($dup->fetch()) {
            return ['success' => false, 'message' => 'Email is used by another account.'];
        }

        $dupUser = $this->conn->prepare('SELECT id FROM users WHERE username = :u AND id <> :id LIMIT 1');
        $dupUser->execute([':u' => $username, ':id' => $targetId]);
        if ($dupUser->fetch()) {
            return ['success' => false, 'message' => 'Username is used by another account.'];
        }

        $sql = "UPDATE users SET first_name = :fn, last_name = :ln, username = :un, email = :em, phone_number = :ph,
                user_type = :ut, license_id = :lic, police_id = :pid, role = :role, is_active = :active, updated_at = NOW()";
        $params = [
            ':fn' => $first,
            ':ln' => $last,
            ':un' => $username,
            ':em' => $email,
            ':ph' => $phone !== '' ? $phone : null,
            ':ut' => $userType,
            ':lic' => $licenseId !== '' ? $licenseId : null,
            ':pid' => $policeId !== '' ? $policeId : null,
            ':role' => $role,
            ':active' => $isActive ? 1 : 0,
            ':id' => $targetId,
        ];

        if ($password !== '') {
            $sql .= ', password_hash = :phash';
            $params[':phash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $sql .= ' WHERE id = :id';

        $up = $this->conn->prepare($sql);
        $up->execute($params);

        $this->writeAudit($adminId, 'admin_user_update', 'users', $targetId, [
            'user_type' => $userType,
            'email' => $email,
        ]);

        return ['success' => true, 'message' => 'User updated successfully.'];
    }

    public function setUserActive(int $adminId, int $targetId, bool $active): array
    {
        if ($targetId <= 0) {
            return ['success' => false, 'message' => 'Invalid user.'];
        }
        if ($targetId === $adminId && !$active) {
            return ['success' => false, 'message' => 'You cannot deactivate your own account.'];
        }

        if (!$active) {
            $stmt = $this->conn->prepare('SELECT user_type FROM users WHERE id = :id');
            $stmt->execute([':id' => $targetId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['user_type'] === 'administrator') {
                return ['success' => false, 'message' => 'Administrator accounts cannot be deactivated from User Management.'];
            }
        }

        $stmt = $this->conn->prepare('UPDATE users SET is_active = :a, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':a' => $active ? 1 : 0, ':id' => $targetId]);

        $this->writeAudit($adminId, $active ? 'admin_user_activate' : 'admin_user_deactivate', 'users', $targetId, []);

        return ['success' => true, 'message' => $active ? 'User account activated.' : 'User account deactivated.'];
    }

    public function deleteUser(int $adminId, int $targetId): array
    {
        if ($targetId <= 0) {
            return ['success' => false, 'message' => 'Invalid user.'];
        }
        if ($targetId === $adminId) {
            return ['success' => false, 'message' => 'You cannot delete your own account.'];
        }

        $stmt = $this->conn->prepare('SELECT user_type FROM users WHERE id = :id');
        $stmt->execute([':id' => $targetId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'message' => 'User not found.'];
        }
        if ($row['user_type'] === 'administrator') {
            return ['success' => false, 'message' => 'Administrator accounts cannot be deleted from User Management.'];
        }

        $del = $this->conn->prepare('DELETE FROM users WHERE id = :id');
        $del->execute([':id' => $targetId]);

        $this->writeAudit($adminId, 'admin_user_delete', 'users', $targetId, []);

        return ['success' => true, 'message' => 'User account deleted.'];
    }

    public function resetUserPassword(int $adminId, int $targetId, string $newPassword): array
    {
        if ($targetId <= 0) {
            return ['success' => false, 'message' => 'Invalid user.'];
        }
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare('UPDATE users SET password_hash = :ph, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':ph' => $hash, ':id' => $targetId]);

        $this->writeAudit($adminId, 'admin_password_reset', 'users', $targetId, []);

        return ['success' => true, 'message' => 'Password reset successfully.'];
    }

    public function getTrafficMonitoring(string $tab = 'road-status', int $limit = 30): array
    {
        $routeService = new DriverRouteService($this->conn);
        $limit = max(10, min(100, $limit));

        $stats = [
            'road_readings' => (int)$this->conn->query("SELECT COUNT(*) FROM traffic_readings")->fetchColumn(),
            'accidents' => (int)$this->conn->query("SELECT COUNT(*) FROM accidents")->fetchColumn(),
            'congestion' => (int)$this->conn->query("SELECT COUNT(*) FROM alerts WHERE type = 'congestion'")->fetchColumn(),
            'blockages' => (int)$this->conn->query("SELECT COUNT(*) FROM alerts WHERE type = 'road_blockage'")->fetchColumn(),
        ];

        $items = [];
        if ($tab === 'accidents') {
            $items = $routeService->getAccidentsList($limit, 0);
        } elseif ($tab === 'congestion') {
            $items = $this->getAlertsByType('congestion', $limit);
        } elseif ($tab === 'blockages') {
            $items = $this->getAlertsByType('road_blockage', $limit);
        } else {
            $stmt = $this->conn->prepare(
                "SELECT tr.reading_time, tr.vehicle_count, tr.avg_speed_kmh, tr.weather, tr.source,
                        rs.name AS segment_name, rs.zone, u.first_name, u.last_name
                 FROM traffic_readings tr
                 INNER JOIN road_segments rs ON rs.id = tr.segment_id
                 LEFT JOIN audit_log al ON al.table_name = 'traffic_readings' AND al.record_id = tr.id
                 LEFT JOIN users u ON u.id = al.user_id
                 WHERE tr.source = 'traffic_police' OR tr.source LIKE '%police%'
                 ORDER BY tr.reading_time DESC
                 LIMIT :lim"
            );
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return [
            'tab' => $tab,
            'stats' => $stats,
            'items' => $items,
            'sector_summary' => $routeService->getRoadStatusSummary(),
        ];
    }

    public function getSystemSettings(): array
    {
        return strgs_system_settings();
    }

    public function saveSystemSettings(int $adminId, array $data): array
    {
        $current = $this->getSystemSettings();
        $allowed = array_keys(strgs_system_settings_defaults());

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $val = $data[$key];
            if (is_bool($current[$key])) {
                $current[$key] = strgs_system_settings_bool($val);
                continue;
            }
            if (is_int($current[$key])) {
                $current[$key] = (int)$val;
                continue;
            }
            if (is_float($current[$key])) {
                $current[$key] = (float)$val;
                continue;
            }
            $current[$key] = trim((string)$val);
        }

        if (trim((string)$current['app_name']) === '') {
            return ['success' => false, 'message' => 'Application name is required.'];
        }
        if (trim((string)$current['study_zone']) === '') {
            return ['success' => false, 'message' => 'Study zone is required.'];
        }
        $tz = trim((string)$current['timezone']);
        if ($tz === '' || !in_array($tz, timezone_identifiers_list(), true)) {
            return ['success' => false, 'message' => 'Invalid timezone. Use a value like Africa/Kigali.'];
        }
        $current['session_timeout_minutes'] = max(5, min(480, (int)$current['session_timeout_minutes']));
        $current['auto_refresh_seconds'] = max(10, min(300, (int)$current['auto_refresh_seconds']));
        $current['map_default_zoom'] = max(10, min(18, (int)$current['map_default_zoom']));
        $current['backup_retention_days'] = max(1, min(365, (int)$current['backup_retention_days']));
        $current['map_center_lat'] = max(-90.0, min(90.0, (float)$current['map_center_lat']));
        $current['map_center_lng'] = max(-180.0, min(180.0, (float)$current['map_center_lng']));

        if (!is_dir(dirname($this->settingsPath))) {
            @mkdir(dirname($this->settingsPath), 0777, true);
        }

        file_put_contents($this->settingsPath, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        strgs_system_settings(true);
        strgs_apply_system_timezone();
        strgs_maybe_run_settings_backup();

        $this->writeAudit($adminId, 'admin_settings_update', 'system_settings', null, $current);

        return ['success' => true, 'message' => 'System settings saved.', 'settings' => $current];
    }

    public function getAnnouncements(int $limit = 30): array
    {
        $items = $this->readAnnouncements();
        usort($items, static fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return array_slice($items, 0, max(1, $limit));
    }

    public function sendAnnouncement(int $adminId, array $data): array
    {
        $title = trim((string)($data['title'] ?? ''));
        $message = trim((string)($data['message'] ?? ''));
        $severity = strtolower(trim((string)($data['severity'] ?? 'info')));
        $isEmergency = !empty($data['emergency']);

        if ($message === '') {
            return ['success' => false, 'message' => 'Announcement message is required.'];
        }
        if (!in_array($severity, ['info', 'warning', 'critical'], true)) {
            $severity = 'info';
        }

        $entry = [
            'id' => 'ann-' . time() . '-' . random_int(1000, 9999),
            'title' => $title !== '' ? $title : ($isEmergency ? 'Emergency Alert' : 'System Announcement'),
            'message' => $message,
            'severity' => $isEmergency ? 'critical' : $severity,
            'emergency' => $isEmergency,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $adminId,
        ];

        $items = $this->readAnnouncements();
        array_unshift($items, $entry);
        $this->writeAnnouncements(array_slice($items, 0, 200));

        $this->writeAudit($adminId, $isEmergency ? 'admin_emergency_alert' : 'admin_announcement', 'announcements', null, $entry);

        $smsEligible = strgs_count_sms_eligible_users($this->conn);
        $smsNote = $smsEligible > 0
            ? ' SMS notifications will be sent to ' . $smsEligible . ' user(s) with registered phone numbers.'
            : ' No users with valid registered phone numbers were found for SMS.';

        return [
            'success' => true,
            'message' => ($isEmergency ? 'Emergency alert published.' : 'Announcement published.') . $smsNote,
            'announcement' => $entry,
            'sms_eligible' => $smsEligible,
            'sms_dispatch' => [
                'title' => $entry['title'],
                'message' => $message,
                'emergency' => $isEmergency,
            ],
        ];
    }

    /**
     * Audit actions that represent a change to existing data or configuration.
     * Excludes new reports/alerts, announcements, logins, and route requests.
     */
    public static function changeAuditActions(): array
    {
        return [
            'admin_user_create',
            'admin_user_update',
            'admin_user_delete',
            'admin_user_activate',
            'admin_user_deactivate',
            'admin_password_reset',
            'admin_settings_update',
            'password_change',
            'road_status_update',
            'alert_cleared',
            'accident_resolved',
        ];
    }

    public function getActivityLogs(string $type = 'all', int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(50, $perPage));
        $offset = ($page - 1) * $perPage;
        $where = '1=1';
        $params = [];

        if ($type === 'login') {
            $where = "al.action IN ('login', 'logout')";
        } elseif ($type === 'security') {
            $where = "al.action LIKE 'admin_%' OR al.action IN ('login', 'logout', 'password_change')";
        } elseif ($type === 'changes') {
            $where = "al.action IN ('" . implode("','", self::changeAuditActions()) . "')";
        }

        $countStmt = $this->conn->prepare("SELECT COUNT(*) FROM audit_log al WHERE $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->conn->prepare(
            "SELECT al.id, al.user_id, al.action, al.table_name, al.record_id, al.new_values, al.created_at,
                    u.first_name, u.last_name, u.email, u.user_type
             FROM audit_log al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE $where
             ORDER BY al.created_at DESC
             LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $logs = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $action = (string)($row['action'] ?? '');
            $logs[] = [
                'id' => (int)$row['id'],
                'action' => $action,
                'label' => $this->formatAuditLabel($row),
                'table_name' => $row['table_name'],
                'user_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'System',
                'email' => $row['email'] ?? null,
                'user_type' => $row['user_type'] ?? null,
                'created_at' => $row['created_at'],
                'time_label' => strgs_format_relative_time($row['created_at']),
                'details' => $row['new_values'],
                'details_text' => strgs_format_audit_details($action, $row['new_values']),
            ];
        }

        return [
            'logs' => $logs,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => max(1, (int)ceil($total / $perPage)),
            ],
            'server_time' => date('Y-m-d H:i:s'),
        ];
    }

    public function getLoginHistory(int $limit = 30): array
    {
        $limit = max(5, min(100, $limit));
        $items = [];

        $stmt = $this->conn->prepare(
            "SELECT al.id, al.action, al.new_values, al.created_at,
                    u.first_name, u.last_name, u.email, u.user_type
             FROM audit_log al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.action IN ('login', 'logout')
             ORDER BY al.created_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $action = (string)($row['action'] ?? 'login');
            $items[] = [
                'id' => (int)$row['id'],
                'first_name' => $row['first_name'] ?? 'Unknown',
                'last_name' => $row['last_name'] ?? 'User',
                'email' => $row['email'] ?? null,
                'user_type' => $row['user_type'] ?? null,
                'action' => $action,
                'created_at' => $row['created_at'],
                'last_login' => $row['created_at'],
                'time_label' => strgs_format_relative_time($row['created_at']),
                'details_text' => strgs_format_audit_details($action, $row['new_values']),
                'action_label' => $action === 'logout' ? 'Signed out' : 'Signed in',
            ];
        }

        if (count($items) < $limit) {
            $seenUsers = [];
            foreach ($items as $item) {
                if (!empty($item['email'])) {
                    $seenUsers[strtolower((string)$item['email'])] = true;
                }
            }

            $fallback = $this->conn->prepare(
                "SELECT u.id, u.first_name, u.last_name, u.email, u.user_type, u.last_login
                 FROM users u
                 WHERE u.last_login IS NOT NULL
                 ORDER BY u.last_login DESC
                 LIMIT :lim"
            );
            $fallback->bindValue(':lim', $limit, PDO::PARAM_INT);
            $fallback->execute();

            foreach ($fallback->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $emailKey = strtolower((string)($row['email'] ?? ''));
                if ($emailKey !== '' && isset($seenUsers[$emailKey])) {
                    continue;
                }
                $items[] = [
                    'id' => 0,
                    'first_name' => $row['first_name'] ?? 'Unknown',
                    'last_name' => $row['last_name'] ?? 'User',
                    'email' => $row['email'] ?? null,
                    'user_type' => $row['user_type'] ?? null,
                    'action' => 'login',
                    'created_at' => $row['last_login'],
                    'last_login' => $row['last_login'],
                    'time_label' => strgs_format_relative_time($row['last_login']),
                    'details_text' => sprintf(
                        'Last known sign-in as %s (%s).',
                        $row['email'] ?? 'this account',
                        strgs_audit_role_label($row['user_type'] ?? null)
                    ),
                    'action_label' => 'Last sign-in',
                ];
                if (count($items) >= $limit) {
                    break;
                }
            }

            usort($items, static fn(array $a, array $b): int => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
            $items = array_slice($items, 0, $limit);
        }

        return $items;
    }

    public function getProfile(int $userId): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT id, first_name, last_name, email, phone_number, username, user_type, role, profile_picture, last_login
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getAlertsByType(string $type, int $limit): array
    {
        $stmt = $this->conn->prepare(
            "SELECT a.id, a.alert_time, a.severity, a.message, a.is_acknowledged,
                    rs.name AS segment_name, rs.zone
             FROM alerts a
             LEFT JOIN road_segments rs ON rs.id = a.segment_id
             WHERE a.type = :type
             ORDER BY a.alert_time DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function defaultSettings(): array
    {
        return strgs_system_settings_defaults();
    }

    private function readAnnouncements(): array
    {
        if (!file_exists($this->announcementsPath)) {
            return [];
        }
        $data = json_decode((string)file_get_contents($this->announcementsPath), true);
        return is_array($data) ? $data : [];
    }

    private function writeAnnouncements(array $items): void
    {
        file_put_contents(
            $this->announcementsPath,
            json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function writeAudit(int $userId, string $action, ?string $table, ?int $recordId, array $payload): void
    {
        $stmt = $this->conn->prepare(
            'INSERT INTO audit_log (user_id, action, table_name, record_id, new_values, created_at)
             VALUES (:uid, :action, :tbl, :rid, :vals, NOW())'
        );
        $stmt->execute([
            ':uid' => $userId,
            ':action' => $action,
            ':tbl' => $table,
            ':rid' => $recordId,
            ':vals' => json_encode($payload),
        ]);
    }

    private function formatAuditLabel(array $row): string
    {
        $map = [
            'road_status_update' => 'Road status updated',
            'incident_report' => 'Accident reported',
            'alert_report' => 'Traffic alert reported',
            'alert_cleared' => 'Traffic alert cleared',
            'accident_resolved' => 'Accident marked resolved',
            'password_change' => 'Password changed',
            'route_guidance' => 'Route guidance requested',
            'admin_user_create' => 'User account created',
            'admin_user_update' => 'User account updated',
            'admin_user_delete' => 'User account deleted',
            'admin_user_activate' => 'User account activated',
            'admin_user_deactivate' => 'User account deactivated',
            'admin_password_reset' => 'Password reset by admin',
            'admin_settings_update' => 'System settings updated',
            'admin_announcement' => 'Announcement published',
            'admin_emergency_alert' => 'Emergency alert sent',
            'login' => 'User logged in',
            'logout' => 'User logged out',
        ];
        $action = (string)($row['action'] ?? '');
        return $map[$action] ?? ucwords(str_replace('_', ' ', $action));
    }
}
