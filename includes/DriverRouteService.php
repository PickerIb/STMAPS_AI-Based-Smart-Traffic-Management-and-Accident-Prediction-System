<?php

class DriverRouteService
{
    private PDO $conn;

    public const STUDY_ZONE = 'Remera Sector';

    public static function appTimezone(): string
    {
        return defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Africa/Kigali';
    }

    public static function parseReadingTimestamp(string $value): ?int
    {
        $value = trim(str_replace('T', ' ', $value));
        if ($value === '') {
            return null;
        }

        $tz = new DateTimeZone(self::appTimezone());
        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i'];
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $value, $tz);
            if ($dt instanceof DateTime) {
                return $dt->getTimestamp();
            }
        }

        try {
            $dt = new DateTime($value, $tz);
            return $dt->getTimestamp();
        } catch (Exception $e) {
            return null;
        }
    }

    public static function formatLocalDateTime(?int $timestamp, string $format = 'd M Y, H:i'): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        return (new DateTime('@' . $timestamp))
            ->setTimezone(new DateTimeZone(self::appTimezone()))
            ->format($format);
    }

    public static function timezoneLabel(): string
    {
        return 'Kigali time (CAT)';
    }

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getRemeraMapConfig(): array
    {
        return [
            'zone' => self::STUDY_ZONE,
            'center' => ['lat' => -1.9536, 'lng' => 30.1065],
            'bounds' => [
                'south' => -1.9655,
                'north' => -1.9430,
                'west' => 30.0975,
                'east' => 30.1195,
            ],
            'label' => 'Remera Sector, Kigali',
            'min_zoom' => 14,
            'max_zoom' => 18,
        ];
    }

    public function getRemeraLandmarks(): array
    {
        return array_values(array_filter(
            $this->getLandmarks(),
            static fn(array $lm) => !in_array($lm['name'], ['City Centre (CBD)'], true)
        ));
    }

    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function getLandmarks(): array
    {
        return [
            ['name' => 'Amahoro Stadium', 'lat' => -1.9517, 'lng' => 30.1130],
            ['name' => 'Remera Taxi Park', 'lat' => -1.9582, 'lng' => 30.1095],
            ['name' => 'Kisimenti', 'lat' => -1.9465, 'lng' => 30.1012],
            ['name' => 'Kimironko', 'lat' => -1.9442, 'lng' => 30.1142],
            ['name' => 'Remera Hospital', 'lat' => -1.9568, 'lng' => 30.1112],
            ['name' => 'BPR Roundabout', 'lat' => -1.9555, 'lng' => 30.1071],
            ['name' => 'Sonatubes', 'lat' => -1.9582, 'lng' => 30.1088],
            ['name' => 'Airport Road Junction', 'lat' => -1.9616, 'lng' => 30.1153],
            ['name' => 'City Centre (CBD)', 'lat' => -1.9441, 'lng' => 30.0619],
            ['name' => 'Nyabisindu', 'lat' => -1.9458, 'lng' => 30.1165],
        ];
    }

    public function searchDestinations(string $query): array
    {
        $query = trim($query);
        $results = [];
        foreach ($this->getLandmarks() as $landmark) {
            if ($query === '' || stripos($landmark['name'], $query) !== false) {
                $results[] = $landmark;
            }
        }

        $sql = "SELECT name, zone,
                       ((start_lat + end_lat) / 2) AS lat,
                       ((start_lng + end_lng) / 2) AS lng
                FROM road_segments
                WHERE is_active = 1 AND zone = :zone";
        if ($query !== '') {
            $sql .= " AND (name LIKE :q OR zone LIKE :q)";
        }
        $sql .= " ORDER BY name ASC";
        if ($query !== '') {
            $sql .= " LIMIT 10";
        }
        $stmt = $this->conn->prepare($sql);
        $params = [':zone' => self::STUDY_ZONE];
        if ($query !== '') {
            $params[':q'] = '%' . $query . '%';
        }
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'name' => $row['name'] . ' (' . $row['zone'] . ')',
                'lat' => (float)$row['lat'],
                'lng' => (float)$row['lng'],
            ];
        }

        return array_values(array_unique($results, SORT_REGULAR));
    }

    public function getSegmentsWithStatus(): array
    {
        $sql = "SELECT rs.id, rs.name, rs.zone, rs.start_lat, rs.start_lng, rs.end_lat, rs.end_lng,
                       rs.speed_limit_kmh, rs.lanes,
                       tr.vehicle_count, tr.avg_speed_kmh, tr.weather, tr.reading_time
                FROM road_segments rs
                LEFT JOIN traffic_readings tr ON tr.id = (
                    SELECT id FROM traffic_readings
                    WHERE segment_id = rs.id
                    ORDER BY reading_time DESC LIMIT 1
                )
                WHERE rs.is_active = 1 AND rs.zone = :zone
                ORDER BY rs.name ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':zone' => self::STUDY_ZONE]);
        $alertStmt = $this->conn->prepare(
            "SELECT segment_id, type, severity, message, alert_time
             FROM alerts
             WHERE segment_id IS NOT NULL
               AND is_acknowledged = 0
               AND alert_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY alert_time DESC"
        );
        $alertStmt->execute();
        $latestAlertBySegment = [];
        foreach ($alertStmt->fetchAll(PDO::FETCH_ASSOC) as $alert) {
            $sid = (int)$alert['segment_id'];
            if (!isset($latestAlertBySegment[$sid])) {
                $latestAlertBySegment[$sid] = $alert;
            }
        }
        $segments = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = $this->deriveSegmentStatus($row);
            $segmentId = (int)$row['id'];
            $reason = null;
            $readingTs = !empty($row['reading_time'])
                ? (self::parseReadingTimestamp((string)$row['reading_time']) ?? 0)
                : 0;

            if (isset($latestAlertBySegment[$segmentId])) {
                $alert = $latestAlertBySegment[$segmentId];
                $alertTs = self::parseReadingTimestamp((string)$alert['alert_time']) ?? 0;
                $alertType = (string)($alert['type'] ?? '');

                if ($alertType === 'road_blockage') {
                    $status = $this->statusFromAlert($alert);
                    $reason = (string)$alert['message'];
                } elseif ($alertType === 'incident' && ($readingTs === 0 || $alertTs >= $readingTs)) {
                    $status = $this->statusFromAlert($alert);
                    $reason = (string)$alert['message'];
                } else {
                    $readingIsCurrent = $readingTs > 0 && $readingTs >= $alertTs;

                    if (!$readingIsCurrent) {
                        $status = $this->statusFromAlert($alert);
                        $reason = (string)$alert['message'];
                    } elseif (trim((string)$alert['message']) !== '' && $alertTs >= $readingTs - 60) {
                        // Officer note saved with the same reading — show note, keep reading-based status.
                        $reason = (string)$alert['message'];
                    }
                }
            }

            $pairedNote = $this->findOfficerNoteNearReading($segmentId, $row['reading_time'] ?? null);
            if ($pairedNote !== null) {
                $reason = $pairedNote;
            } elseif ($reason === null || trim((string)$reason) === '') {
                $reason = '—';
            }

            $segments[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'zone' => $row['zone'],
                'start_lat' => (float)$row['start_lat'],
                'start_lng' => (float)$row['start_lng'],
                'end_lat' => (float)$row['end_lat'],
                'end_lng' => (float)$row['end_lng'],
                'speed_limit_kmh' => (int)$row['speed_limit_kmh'],
                'lanes' => (int)$row['lanes'],
                'vehicle_count' => $row['vehicle_count'] !== null ? (int)$row['vehicle_count'] : null,
                'avg_speed_kmh' => $row['avg_speed_kmh'] !== null ? (float)$row['avg_speed_kmh'] : null,
                'weather' => $row['weather'],
                'reading_time' => $row['reading_time'],
                'status' => $status['status'],
                'congestion_level' => $status['congestion_level'],
                'status_label' => $status['label'],
                'reason' => $reason,
            ];
        }

        return $segments;
    }

    public function deriveSegmentStatus(array $row): array
    {
        $speed = $row['avg_speed_kmh'] ?? null;
        $limit = (int)($row['speed_limit_kmh'] ?? 50);
        $vehicles = $row['vehicle_count'] ?? null;

        if ($speed === null) {
            return ['status' => 'normal', 'congestion_level' => 'low', 'label' => 'Normal traffic'];
        }

        if ((float)$speed <= 0.01 && ($vehicles === null || (int)$vehicles === 0)) {
            return ['status' => 'blocked', 'congestion_level' => 'severe', 'label' => 'Blocked road'];
        }

        $ratio = $speed / max($limit, 1);
        if ($ratio >= 0.75) {
            return ['status' => 'normal', 'congestion_level' => 'low', 'label' => 'Normal traffic'];
        }
        if ($ratio >= 0.5) {
            return ['status' => 'congested', 'congestion_level' => 'moderate', 'label' => 'Moderate congestion'];
        }
        if ($ratio >= 0.3 || ($vehicles !== null && $vehicles > 150)) {
            return ['status' => 'congested', 'congestion_level' => 'high', 'label' => 'Heavy congestion'];
        }
        return ['status' => 'congested', 'congestion_level' => 'severe', 'label' => 'Severe congestion'];
    }

    private function findOfficerNoteNearReading(int $segmentId, ?string $readingTime, int $windowSeconds = 180): ?string
    {
        if ($segmentId <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare(
            "SELECT message, alert_time
             FROM alerts
             WHERE segment_id = :sid AND TRIM(message) <> ''
             ORDER BY alert_time DESC
             LIMIT 20"
        );
        $stmt->execute([':sid' => $segmentId]);
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($alerts === []) {
            return null;
        }

        $readingTs = ($readingTime !== null && $readingTime !== '')
            ? self::parseReadingTimestamp((string)$readingTime)
            : null;

        if ($readingTs !== null) {
            foreach ($alerts as $alert) {
                $msg = trim((string)($alert['message'] ?? ''));
                if ($msg === '') {
                    continue;
                }
                $alertTs = self::parseReadingTimestamp((string)$alert['alert_time']) ?? 0;
                if (abs($alertTs - $readingTs) <= $windowSeconds) {
                    return $msg;
                }
            }
        }

        return trim((string)($alerts[0]['message'] ?? '')) ?: null;
    }

    private function adviceToneForStatus(string $status, string $level): string
    {
        if ($status === 'blocked' || $level === 'severe') {
            return 'danger';
        }
        if ($status === 'issue' || $level === 'high') {
            return 'warning';
        }
        if ($level === 'moderate') {
            return 'caution';
        }

        return 'safe';
    }

    private function statusFromAlert(array $alert): array
    {
        $type = (string)($alert['type'] ?? '');
        if ($type === 'road_blockage') {
            return ['status' => 'blocked', 'congestion_level' => 'severe', 'label' => 'Blocked road'];
        }
        if ($type === 'incident') {
            return ['status' => 'issue', 'congestion_level' => 'high', 'label' => 'Incident reported'];
        }
        if ($type === 'congestion') {
            $severityMap = [
                'info' => ['status' => 'congested', 'congestion_level' => 'moderate', 'label' => 'Moderate congestion'],
                'warning' => ['status' => 'congested', 'congestion_level' => 'high', 'label' => 'Heavy congestion'],
                'critical' => ['status' => 'congested', 'congestion_level' => 'severe', 'label' => 'Severe congestion'],
            ];
            return $severityMap[$alert['severity'] ?? 'warning'] ?? $severityMap['warning'];
        }
        if ($type === 'system' && in_array($alert['severity'] ?? '', ['warning', 'danger', 'critical'], true)) {
            return ['status' => 'issue', 'congestion_level' => 'high', 'label' => 'Road issue reported'];
        }

        return ['status' => 'normal', 'congestion_level' => 'low', 'label' => 'Normal traffic'];
    }

    private function supersedeOlderSegmentAlerts(int $segmentId, string $readingTimeDb, int $userId): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE alerts
             SET is_acknowledged = 1, acknowledged_by = :uid, acknowledged_at = NOW()
             WHERE segment_id = :segment_id
               AND is_acknowledged = 0
               AND alert_time < :reading_time"
        );
        $stmt->execute([
            ':uid' => $userId,
            ':segment_id' => $segmentId,
            ':reading_time' => $readingTimeDb,
        ]);
    }

    public function getRoadStatusSummary(): array
    {
        $segments = $this->getSegmentsWithStatus();
        $summary = [
            'total' => count($segments),
            'normal' => 0,
            'congested' => 0,
            'blocked' => 0,
            'road_issues' => 0,
            'accidents' => 0,
            'maintenance' => 0,
        ];

        foreach ($segments as $seg) {
            if ($seg['status'] === 'normal') {
                $summary['normal']++;
            } elseif ($seg['status'] === 'blocked') {
                $summary['blocked']++;
                $summary['road_issues']++;
            } elseif ($seg['status'] === 'issue') {
                $summary['road_issues']++;
            } else {
                $summary['congested']++;
                if (in_array($seg['congestion_level'], ['high', 'severe'], true)) {
                    $summary['road_issues']++;
                }
            }
        }

        $alertStmt = $this->conn->query(
            "SELECT type, COUNT(*) AS cnt FROM alerts
             WHERE alert_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY type"
        );
        foreach ($alertStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['type'] === 'incident') {
                $summary['accidents'] += (int)$row['cnt'];
                $summary['road_issues'] += (int)$row['cnt'];
            }
        }

        $accStmt = $this->conn->query(
            "SELECT COUNT(*) AS cnt FROM accidents
             WHERE accident_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $summary['accidents'] += (int)$accStmt->fetch()['cnt'];

        return $summary;
    }

    public function getDriverAlerts(int $limit = 20): array
    {
        $stmt = $this->conn->prepare(
            "SELECT a.id, a.alert_time, a.type, a.severity, a.message, a.is_acknowledged,
                    rs.name AS segment_name, rs.zone
             FROM alerts a
             LEFT JOIN road_segments rs ON rs.id = a.segment_id
             ORDER BY a.alert_time DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countDriverNotifications(int $userId, int $hours = 168): int
    {
        return $this->countUnreadDriverNotifications($userId, $hours);
    }

    public function countUnreadDriverNotifications(int $userId, int $hours = 168): int
    {
        $pool = $this->buildDriverNotificationPool($hours);
        if ($pool === []) {
            return 0;
        }
        $readKeys = $this->getDriverReadNotificationKeys($userId);
        $unread = 0;
        foreach ($pool as $item) {
            if (empty($readKeys[(string)($item['id'] ?? '')])) {
                $unread++;
            }
        }
        return $unread;
    }

    public function getDriverNotifications(int $userId, int $limit = 30, int $offset = 0, int $hours = 168): array
    {
        $pool = $this->buildDriverNotificationPool($hours);
        $pool = $this->applyReadStatusToNotifications($pool, $userId);
        return array_slice($pool, $offset, $limit);
    }

    public function markDriverNotificationsRead(int $userId, array $notificationKeys): array
    {
        $this->ensureDriverNotificationReadsTable();
        $keys = array_values(array_unique(array_filter(array_map('strval', $notificationKeys))));
        if ($keys === []) {
            return ['marked' => 0];
        }

        $stmt = $this->conn->prepare(
            'INSERT IGNORE INTO driver_notification_reads (user_id, notification_key, read_at)
             VALUES (:uid, :key, NOW())'
        );
        $marked = 0;
        foreach ($keys as $key) {
            $stmt->execute([':uid' => $userId, ':key' => $key]);
            $marked += $stmt->rowCount();
        }

        return ['marked' => $marked];
    }

    private function ensureDriverNotificationReadsTable(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $this->conn->exec(
            'CREATE TABLE IF NOT EXISTS `driver_notification_reads` (
              `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id` int(10) UNSIGNED NOT NULL,
              `notification_key` varchar(64) NOT NULL,
              `read_at` datetime NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_driver_notification_read` (`user_id`, `notification_key`),
              KEY `idx_driver_notification_reads_user` (`user_id`),
              CONSTRAINT `fk_driver_notification_reads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
        $ready = true;
    }

    private function getDriverReadNotificationKeys(int $userId): array
    {
        $this->ensureDriverNotificationReadsTable();
        $stmt = $this->conn->prepare(
            'SELECT notification_key FROM driver_notification_reads WHERE user_id = :uid'
        );
        $stmt->execute([':uid' => $userId]);
        $keys = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $key) {
            $keys[(string)$key] = true;
        }
        return $keys;
    }

    private function applyReadStatusToNotifications(array $items, int $userId): array
    {
        if ($items === []) {
            return $items;
        }
        $readKeys = $this->getDriverReadNotificationKeys($userId);
        foreach ($items as &$item) {
            $item['is_read'] = !empty($readKeys[(string)($item['id'] ?? '')]);
        }
        unset($item);
        return $items;
    }

    private function buildDriverNotificationPool(int $hours = 168): array
    {
        $since = date('Y-m-d H:i:s', time() - ($hours * 3600));
        $items = [];
        $seen = [];
        $roadMinuteKeys = [];

        $readingStmt = $this->conn->prepare(
            "SELECT tr.id, tr.reading_time, tr.vehicle_count, tr.avg_speed_kmh, tr.weather, tr.source,
                    rs.id AS segment_id, rs.name AS segment_name, rs.zone, rs.speed_limit_kmh
             FROM traffic_readings tr
             INNER JOIN road_segments rs ON rs.id = tr.segment_id
             WHERE tr.reading_time >= :since
               AND (tr.source = 'traffic_police' OR tr.source LIKE '%police%')
             ORDER BY tr.reading_time DESC"
        );
        $readingStmt->execute([':since' => $since]);
        foreach ($readingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ts = self::parseReadingTimestamp((string)$row['reading_time']) ?? 0;
            $segmentId = (int)$row['segment_id'];
            $dedupeKey = 'road:' . $segmentId . ':' . intdiv($ts, 60);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;
            $roadMinuteKeys[$segmentId . ':' . intdiv($ts, 60)] = true;

            $status = $this->deriveSegmentStatus($row);
            $weather = ucfirst((string)($row['weather'] ?? 'unknown'));
            $officerNote = $this->findOfficerNoteNearReading($segmentId, (string)$row['reading_time']);
            $message = $officerNote !== null && $officerNote !== ''
                ? $officerNote
                : sprintf(
                    'Traffic police updated %s — %s, %d vehicles, %.1f km/h avg, %s weather.',
                    $row['segment_name'],
                    $status['label'],
                    (int)$row['vehicle_count'],
                    (float)$row['avg_speed_kmh'],
                    $weather
                );

            $severity = in_array($status['congestion_level'], ['high', 'severe'], true) ? 'warning' : 'info';
            if ($status['status'] === 'blocked') {
                $severity = 'danger';
            }

            $items[] = [
                'id' => 'road-' . (int)$row['id'],
                'kind' => 'road_update',
                'type' => 'road_update',
                'title' => 'Road status update',
                'severity' => $severity,
                'message' => $message,
                'segment_name' => $row['segment_name'],
                'zone' => $row['zone'],
                'alert_time' => $row['reading_time'],
                'time_label' => self::formatRelativeSeconds(max(0, time() - $ts)),
                'sort_ts' => $ts,
                'congestion_level' => $status['congestion_level'],
                'status_label' => $status['label'],
            ];
        }

        $alertStmt = $this->conn->prepare(
            "SELECT a.id, a.segment_id, a.alert_time, a.type, a.severity, a.message, a.is_acknowledged,
                    rs.name AS segment_name, rs.zone
             FROM alerts a
             LEFT JOIN road_segments rs ON rs.id = a.segment_id
             WHERE a.alert_time >= :since
             ORDER BY a.alert_time DESC"
        );
        $alertStmt->execute([':since' => $since]);
        foreach ($alertStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ts = self::parseReadingTimestamp((string)$row['alert_time']) ?? 0;
            $segmentId = (int)($row['segment_id'] ?? 0);
            $type = (string)($row['type'] ?? 'system');

            if ($type === 'congestion' && isset($roadMinuteKeys[$segmentId . ':' . intdiv($ts, 60)])) {
                continue;
            }
            if ($type === 'road_blockage' && isset($roadMinuteKeys[$segmentId . ':' . intdiv($ts, 60)])) {
                continue;
            }
            if ($type === 'system' && isset($roadMinuteKeys[$segmentId . ':' . intdiv($ts, 60)])) {
                continue;
            }

            $dedupeKey = 'alert:' . $segmentId . ':' . intdiv($ts, 60) . ':' . $type;
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $title = match ($type) {
                'road_blockage' => 'Road closure',
                'incident' => 'Accident reported',
                'congestion' => 'Traffic alert',
                default => 'System alert',
            };

            $items[] = [
                'id' => 'alert-' . (int)$row['id'],
                'kind' => 'alert',
                'type' => $type,
                'title' => $title,
                'severity' => $row['severity'] ?: 'info',
                'message' => (string)$row['message'],
                'segment_name' => $row['segment_name'] ?? null,
                'zone' => $row['zone'] ?? null,
                'alert_time' => $row['alert_time'],
                'time_label' => self::formatRelativeSeconds(max(0, time() - $ts)),
                'sort_ts' => $ts,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return ($b['sort_ts'] ?? 0) <=> ($a['sort_ts'] ?? 0);
        });

        return $items;
    }

    public function getRecentRoadUpdates(int $limit = 10): array
    {
        $stmt = $this->conn->prepare(
            "SELECT rs.id AS segment_id, rs.name AS segment_name, rs.zone,
                    tr.reading_time, tr.vehicle_count, tr.avg_speed_kmh, tr.weather,
                    rs.speed_limit_kmh
             FROM road_segments rs
             INNER JOIN traffic_readings tr ON tr.id = (
                 SELECT id FROM traffic_readings
                 WHERE segment_id = rs.id
                 ORDER BY reading_time DESC
                 LIMIT 1
             )
             WHERE rs.is_active = 1 AND rs.zone = :zone
             ORDER BY tr.reading_time DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':zone', self::STUDY_ZONE);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $updates = [];
        $now = time();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = $this->deriveSegmentStatus($row);
            $readingTs = self::parseReadingTimestamp((string)$row['reading_time']) ?? $now;
            $secondsAgo = max(0, $now - $readingTs);
            $updates[] = [
                'segment_id' => (int)$row['segment_id'],
                'segment_name' => $row['segment_name'],
                'zone' => $row['zone'],
                'reading_time' => $row['reading_time'],
                'seconds_ago' => $secondsAgo,
                'updated_label' => self::formatRelativeSeconds($secondsAgo),
                'vehicle_count' => $row['vehicle_count'] !== null ? (int)$row['vehicle_count'] : null,
                'avg_speed_kmh' => $row['avg_speed_kmh'] !== null ? (float)$row['avg_speed_kmh'] : null,
                'weather' => $row['weather'],
                'status' => $status['status'],
                'congestion_level' => $status['congestion_level'],
                'status_label' => $status['label'],
            ];
        }

        return $updates;
    }

    public static function formatRelativeSeconds(int $seconds): string
    {
        if ($seconds < 10) {
            return 'Just now';
        }
        if ($seconds < 60) {
            return $seconds . ($seconds === 1 ? ' second ago' : ' seconds ago');
        }
        $minutes = intdiv($seconds, 60);
        if ($minutes < 60) {
            return $minutes . ($minutes === 1 ? ' minute ago' : ' minutes ago');
        }
        $hours = intdiv($seconds, 3600);
        if ($hours < 24) {
            return $hours . ($hours === 1 ? ' hour ago' : ' hours ago');
        }
        $days = intdiv($seconds, 86400);
        if ($days < 7) {
            return $days . ($days === 1 ? ' day ago' : ' days ago');
        }
        $weeks = intdiv($days, 7);
        return $weeks . ($weeks === 1 ? ' week ago' : ' weeks ago');
    }

    public function recommendRoutes(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        string $destLabel = 'Destination'
    ): array {
        $segments = $this->getSegmentsWithStatus();
        if (empty($segments)) {
            return [];
        }

        $segmentOptions = $this->buildSegmentRouteOptions(
            $segments,
            $originLat,
            $originLng,
            $destLat,
            $destLng
        );

        $pool = $segmentOptions;
        $routingSource = 'segments';

        $osrmRoutes = $this->fetchOsrmRoadRoutes($originLat, $originLng, $destLat, $destLng);
        if (!empty($osrmRoutes)) {
            $pool = array_merge($this->buildRoutesFromOsrm($osrmRoutes, $segments), $segmentOptions);
            $routingSource = 'osrm';
        }

        $routes = $this->mergeDistinctRoutes($pool, 3);

        return [
            'origin' => ['lat' => $originLat, 'lng' => $originLng],
            'destination' => ['lat' => $destLat, 'lng' => $destLng, 'label' => $destLabel],
            'routes' => $routes,
            'routing_source' => $routingSource,
            'traffic_updated_at' => date('c'),
        ];
    }

    private function fetchOsrmRoadRoutes(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng
    ): array {
        $url = sprintf(
            'https://router.project-osrm.org/route/v1/driving/%F,%F;%F,%F?overview=full&geometries=geojson&alternatives=true&steps=false',
            $originLng,
            $originLat,
            $destLng,
            $destLat
        );

        $data = $this->httpGetJson($url, 10);
        if (($data['code'] ?? '') !== 'Ok' || empty($data['routes'])) {
            return [];
        }

        $routes = [];
        foreach ($data['routes'] as $route) {
            $coords = $route['geometry']['coordinates'] ?? [];
            if (count($coords) < 2) {
                continue;
            }

            $geometry = [];
            foreach ($coords as $coord) {
                $geometry[] = ['lat' => (float)$coord[1], 'lng' => (float)$coord[0]];
            }

            $routes[] = [
                'geometry' => $geometry,
                'distance_km' => round(((float)($route['distance'] ?? 0)) / 1000, 2),
                'estimated_minutes' => (int)ceil(((float)($route['duration'] ?? 0)) / 60),
            ];
        }

        return $routes;
    }

    private function buildRoutesFromOsrm(array $osrmRoutes, array $segments): array
    {
        $built = [];
        foreach ($osrmRoutes as $idx => $osrmRoute) {
            $analysis = $this->analyzeGeometryCongestion($osrmRoute['geometry'], $segments);
            $built[] = [
                'key' => $idx === 0 ? 'primary' : 'alt' . $idx,
                'name' => 'Route option ' . ($idx + 1),
                'distance_km' => $osrmRoute['distance_km'],
                'estimated_minutes' => $osrmRoute['estimated_minutes'],
                'congestion_level' => $analysis['congestion_level'],
                'segment_ids' => array_map(static fn(array $s) => $s['id'], $analysis['segments']),
                'segments' => $analysis['segments'],
                'road_geometry' => $osrmRoute['geometry'],
                'road_lines' => [],
                'polyline' => $osrmRoute['geometry'],
            ];
        }

        return $built;
    }

    private function buildSegmentRouteOptions(
        array $segments,
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng
    ): array {
        $originSeg = $this->nearestSegment($segments, $originLat, $originLng);
        $destSeg = $this->nearestSegment($segments, $destLat, $destLng);
        $graph = $this->buildGraph($segments);

        $defs = [
            ['mode' => 'balanced', 'name' => 'Best Route', 'key' => 'primary'],
            ['mode' => 'shortest', 'name' => 'Fastest Route', 'key' => 'fastest'],
            ['mode' => 'clear', 'name' => 'Least Congested', 'key' => 'clear'],
        ];

        $paths = [];
        $results = [];

        foreach ($defs as $def) {
            $path = $this->findPath($graph, $segments, $originSeg['id'], $destSeg['id'], $def['mode']);
            if (!$path || $this->pathInList($path, $paths)) {
                continue;
            }
            $paths[] = $path;
            $results[] = $this->buildRouteResult(
                $path,
                $segments,
                $originLat,
                $originLng,
                $destLat,
                $destLng,
                $def['name'],
                $def['key']
            );
        }

        if (count($paths) < 3 && !empty($paths[0])) {
            $avoidPath = $this->findPath(
                $graph,
                $segments,
                $originSeg['id'],
                $destSeg['id'],
                'balanced',
                $paths[0]
            );
            if ($avoidPath && !$this->pathInList($avoidPath, $paths)) {
                $paths[] = $avoidPath;
                $results[] = $this->buildRouteResult(
                    $avoidPath,
                    $segments,
                    $originLat,
                    $originLng,
                    $destLat,
                    $destLng,
                    'Alternative Route',
                    'alt2'
                );
            }
        }

        if (empty($results) && $originSeg['id'] === $destSeg['id']) {
            $results[] = $this->buildRouteResult(
                [$originSeg['id']],
                $segments,
                $originLat,
                $originLng,
                $destLat,
                $destLng,
                'Direct (same segment)',
                'primary'
            );
        }

        return $results;
    }

    private function mergeDistinctRoutes(array $routes, int $limit = 3): array
    {
        $merged = [];
        $seen = [];

        foreach ($routes as $route) {
            if (empty($route)) {
                continue;
            }
            $sig = $this->routeSignature($route);
            if (isset($seen[$sig])) {
                continue;
            }
            $seen[$sig] = true;
            $merged[] = $route;
            if (count($merged) >= $limit) {
                break;
            }
        }

        usort($merged, function (array $a, array $b): int {
            $rank = ['low' => 1, 'moderate' => 2, 'high' => 3, 'severe' => 4];
            $congestionCompare = ($rank[$a['congestion_level']] ?? 2) <=> ($rank[$b['congestion_level']] ?? 2);
            if ($congestionCompare !== 0) {
                return $congestionCompare;
            }
            return $a['estimated_minutes'] <=> $b['estimated_minutes'];
        });

        $labels = [
            'Route 1 — Recommended',
            'Route 2 — Faster option',
            'Route 3 — Alternative',
        ];
        $summaries = [
            'Balanced travel time and traffic — good default choice.',
            'Shorter or quicker path — compare traffic before you go.',
            'Different roads — may help you avoid congestion.',
        ];

        foreach ($merged as $idx => &$route) {
            $route['option_number'] = $idx + 1;
            $route['name'] = $labels[$idx] ?? ('Route ' . ($idx + 1));
            $route['summary'] = $summaries[$idx] ?? 'Alternative route option.';
            $route['key'] = $idx === 0 ? 'primary' : ($idx === 1 ? 'fastest' : 'alt' . $idx);
        }
        unset($route);

        return $merged;
    }

    private function routeSignature(array $route): string
    {
        $ids = $route['segment_ids'] ?? [];
        if (!empty($ids)) {
            $ids = array_map('intval', $ids);
            sort($ids);
            return 'seg:' . implode(',', $ids);
        }

        $geom = $route['road_geometry'] ?? [];
        if (count($geom) >= 2) {
            $first = $geom[0];
            $last = $geom[count($geom) - 1];
            $mid = $geom[intdiv(count($geom), 2)];
            return sprintf(
                'geom:%.3f,%.3f|%.3f,%.3f|%.3f,%.3f',
                $first['lat'],
                $first['lng'],
                $mid['lat'],
                $mid['lng'],
                $last['lat'],
                $last['lng']
            );
        }

        return 'meta:' . round((float)($route['distance_km'] ?? 0), 1) . '|' . (int)($route['estimated_minutes'] ?? 0);
    }

    private function pathInList(array $path, array $list): bool
    {
        foreach ($list as $existing) {
            if ($this->samePath($path, $existing)) {
                return true;
            }
        }
        return false;
    }

    private function analyzeGeometryCongestion(array $geometry, array $segments): array
    {
        $worstLevel = 'low';
        $levelRank = ['low' => 1, 'moderate' => 2, 'high' => 3, 'severe' => 4];
        $matched = [];
        $matchedIds = [];

        $sample = $geometry;
        if (count($geometry) > 50) {
            $sample = [];
            $step = max(1, intdiv(count($geometry), 50));
            for ($i = 0; $i < count($geometry); $i += $step) {
                $sample[] = $geometry[$i];
            }
            $sample[] = $geometry[count($geometry) - 1];
        }

        foreach ($segments as $seg) {
            $refs = [
                ['lat' => $seg['start_lat'], 'lng' => $seg['start_lng']],
                ['lat' => ($seg['start_lat'] + $seg['end_lat']) / 2, 'lng' => ($seg['start_lng'] + $seg['end_lng']) / 2],
                ['lat' => $seg['end_lat'], 'lng' => $seg['end_lng']],
            ];

            foreach ($refs as $ref) {
                $hit = false;
                foreach ($sample as $pt) {
                    if (self::haversineKm($pt['lat'], $pt['lng'], $ref['lat'], $ref['lng']) < 0.08) {
                        $hit = true;
                        break;
                    }
                }
                if ($hit) {
                    if (!isset($matchedIds[$seg['id']])) {
                        $matchedIds[$seg['id']] = true;
                        $matched[] = $seg;
                        if ($levelRank[$seg['congestion_level']] > $levelRank[$worstLevel]) {
                            $worstLevel = $seg['congestion_level'];
                        }
                    }
                    break;
                }
            }
        }

        return ['congestion_level' => $worstLevel, 'segments' => $matched];
    }

    private function httpGetJson(string $url, int $timeout = 8): ?array
    {
        $body = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
                CURLOPT_USERAGENT => 'STRGS/1.0',
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $body = curl_exec($ch);
            curl_close($ch);
        }

        if ($body === false || $body === '') {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => $timeout,
                    'user_agent' => 'STRGS/1.0',
                ],
            ]);
            $body = @file_get_contents($url, false, $ctx);
        }

        if ($body === false || $body === '') {
            return null;
        }

        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    private function nearestSegment(array $segments, float $lat, float $lng): array
    {
        $best = $segments[0];
        $bestDist = PHP_FLOAT_MAX;
        foreach ($segments as $seg) {
            $midLat = ($seg['start_lat'] + $seg['end_lat']) / 2;
            $midLng = ($seg['start_lng'] + $seg['end_lng']) / 2;
            $d = self::haversineKm($lat, $lng, $midLat, $midLng);
            if ($d < $bestDist) {
                $bestDist = $d;
                $best = $seg;
            }
        }
        return $best;
    }

    private function buildGraph(array $segments): array
    {
        $graph = [];
        $n = count($segments);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $segments[$i];
                $b = $segments[$j];
                $dist = self::haversineKm(
                    ($a['start_lat'] + $a['end_lat']) / 2,
                    ($a['start_lng'] + $a['end_lng']) / 2,
                    ($b['start_lat'] + $b['end_lat']) / 2,
                    ($b['start_lng'] + $b['end_lng']) / 2
                );
                if ($dist < 1.2) {
                    $graph[$a['id']][] = ['to' => $b['id'], 'dist' => $dist];
                    $graph[$b['id']][] = ['to' => $a['id'], 'dist' => $dist];
                }
            }
        }
        return $graph;
    }

    private function congestionWeight(string $level): float
    {
        return match ($level) {
            'low' => 1.0,
            'moderate' => 1.4,
            'high' => 2.0,
            'severe' => 3.0,
            default => 1.2,
        };
    }

    private function findPath(
        array $graph,
        array $segments,
        int $start,
        int $end,
        string $mode,
        array $avoidPathIds = []
    ): ?array {
        $segMap = [];
        foreach ($segments as $s) {
            $segMap[$s['id']] = $s;
        }

        $dist = [$start => 0];
        $prev = [];
        $visited = [];
        $queue = new SplPriorityQueue();
        $queue->insert($start, 0);

        while (!$queue->isEmpty()) {
            $current = $queue->extract();
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            if ($current === $end) {
                break;
            }

            foreach ($graph[$current] ?? [] as $edge) {
                $to = $edge['to'];
                $seg = $segMap[$to] ?? null;
                if (!$seg) {
                    continue;
                }

                $w = $edge['dist'];
                if ($mode === 'clear') {
                    $w *= $this->congestionWeight($seg['congestion_level']);
                } elseif ($mode === 'balanced') {
                    $w *= 1 + ($this->congestionWeight($seg['congestion_level']) - 1) * 0.6;
                }
                if (!empty($avoidPathIds) && in_array($to, $avoidPathIds, true) && $to !== $end) {
                    $w += 2.0;
                }

                $newDist = ($dist[$current] ?? PHP_FLOAT_MAX) + $w;
                if ($newDist < ($dist[$to] ?? PHP_FLOAT_MAX)) {
                    $dist[$to] = $newDist;
                    $prev[$to] = $current;
                    $queue->insert($to, -$newDist);
                }
            }
        }

        if (!isset($dist[$end])) {
            return $segMap[$start]['id'] === $end ? [$start] : null;
        }

        $path = [];
        $node = $end;
        while (isset($prev[$node])) {
            $path[] = $node;
            $node = $prev[$node];
        }
        $path[] = $start;
        return array_reverse($path);
    }

    private function samePath(?array $a, ?array $b): bool
    {
        if (!$a || !$b) {
            return false;
        }
        return $a === $b;
    }

    private function buildRouteResult(
        array $pathIds,
        array $segments,
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        string $name,
        string $key
    ): array {
        $segMap = [];
        foreach ($segments as $s) {
            $segMap[$s['id']] = $s;
        }

        $routeSegments = [];
        $distance = 0;
        $worstLevel = 'low';
        $levelRank = ['low' => 1, 'moderate' => 2, 'high' => 3, 'severe' => 4];

        for ($i = 0; $i < count($pathIds); $i++) {
            $seg = $segMap[$pathIds[$i]];
            $routeSegments[] = $seg;
            if ($i > 0) {
                $prev = $segMap[$pathIds[$i - 1]];
                $distance += self::haversineKm(
                    ($prev['end_lat'] + $prev['start_lat']) / 2,
                    ($prev['end_lng'] + $prev['start_lng']) / 2,
                    ($seg['start_lat'] + $seg['end_lat']) / 2,
                    ($seg['start_lng'] + $seg['end_lng']) / 2
                );
            }
            if ($levelRank[$seg['congestion_level']] > $levelRank[$worstLevel]) {
                $worstLevel = $seg['congestion_level'];
            }
        }

        $distance += self::haversineKm($originLat, $originLng, $routeSegments[0]['start_lat'], $routeSegments[0]['start_lng']);
        $last = $routeSegments[count($routeSegments) - 1];
        $distance += self::haversineKm($last['end_lat'], $last['end_lng'], $destLat, $destLng);
        $distance = round(max($distance, 0.5), 2);

        $avgSpeed = 35;
        foreach ($routeSegments as $seg) {
            if ($seg['avg_speed_kmh']) {
                $avgSpeed = min($avgSpeed, max(15, (float)$seg['avg_speed_kmh']));
            }
        }
        if ($worstLevel === 'high') {
            $avgSpeed = max(12, $avgSpeed - 10);
        } elseif ($worstLevel === 'severe') {
            $avgSpeed = max(8, $avgSpeed - 15);
        }

        $eta = (int)ceil(($distance / max($avgSpeed, 1)) * 60);

        return [
            'key' => $key,
            'name' => $name,
            'distance_km' => $distance,
            'estimated_minutes' => $eta,
            'congestion_level' => $worstLevel,
            'segment_ids' => $pathIds,
            'segments' => $routeSegments,
            'road_geometry' => $this->buildPolyline($routeSegments, $originLat, $originLng, $destLat, $destLng),
            'road_lines' => $this->buildRoadLines($routeSegments, $originLat, $originLng, $destLat, $destLng),
            'polyline' => $this->buildPolyline($routeSegments, $originLat, $originLng, $destLat, $destLng),
        ];
    }

    private function buildRoadLines(
        array $segments,
        float $oLat,
        float $oLng,
        float $dLat,
        float $dLng
    ): array {
        if (empty($segments)) {
            return [];
        }

        $lines = [];
        $prevExit = null;

        foreach ($segments as $idx => $seg) {
            if ($idx === 0) {
                $useForward = self::haversineKm($oLat, $oLng, $seg['start_lat'], $seg['start_lng'])
                    <= self::haversineKm($oLat, $oLng, $seg['end_lat'], $seg['end_lng']);
            } else {
                $useForward = self::haversineKm($prevExit['lat'], $prevExit['lng'], $seg['start_lat'], $seg['start_lng'])
                    <= self::haversineKm($prevExit['lat'], $prevExit['lng'], $seg['end_lat'], $seg['end_lng']);
            }

            if ($useForward) {
                $lines[] = [
                    ['lat' => (float)$seg['start_lat'], 'lng' => (float)$seg['start_lng']],
                    ['lat' => (float)$seg['end_lat'], 'lng' => (float)$seg['end_lng']],
                ];
            } else {
                $lines[] = [
                    ['lat' => (float)$seg['end_lat'], 'lng' => (float)$seg['end_lng']],
                    ['lat' => (float)$seg['start_lat'], 'lng' => (float)$seg['start_lng']],
                ];
            }

            $prevExit = $lines[count($lines) - 1][1];
        }

        return $lines;
    }

    private function buildPolyline(array $segments, float $oLat, float $oLng, float $dLat, float $dLng): array
    {
        $points = [];
        foreach ($this->buildRoadLines($segments, $oLat, $oLng, $dLat, $dLng) as $line) {
            if (empty($points)) {
                $points[] = $line[0];
            } elseif (!$this->pointsEqual($points[count($points) - 1], $line[0])) {
                $points[] = $line[0];
            }
            $points[] = $line[1];
        }

        return $points;
    }

    private function pointsEqual(array $a, array $b): bool
    {
        return abs($a['lat'] - $b['lat']) < 0.00001
            && abs($a['lng'] - $b['lng']) < 0.00001;
    }

    public function submitTrafficReading(int $userId, array $data): array
    {
        $segmentId = (int)($data['segment_id'] ?? 0);
        $readingTime = trim((string)($data['reading_time'] ?? ''));
        $vehicleCount = (int)($data['vehicle_count'] ?? 0);
        $avgSpeed = (float)($data['avg_speed_kmh'] ?? 0);
        $density = (float)($data['density_veh_per_km'] ?? 0);
        $weather = strtolower(trim((string)($data['weather'] ?? 'unknown')));
        $statusNote = trim((string)($data['status_note'] ?? ''));

        if ($segmentId <= 0) {
            return ['success' => false, 'message' => 'Please select a road.'];
        }
        if ($readingTime === '') {
            return ['success' => false, 'message' => 'Reading time is required.'];
        }
        if ($vehicleCount < 0 || $vehicleCount > 200000) {
            return ['success' => false, 'message' => 'Vehicle count must be between 0 and 200000.'];
        }
        if ($avgSpeed < 0 || $avgSpeed > 200) {
            return ['success' => false, 'message' => 'Average speed must be between 0 and 200 km/h.'];
        }
        if ($density < 0 || $density > 500) {
            return ['success' => false, 'message' => 'Density must be between 0 and 500 veh/km.'];
        }

        $segStmt = $this->conn->prepare(
            'SELECT id, name, zone FROM road_segments WHERE id = :id AND is_active = 1 AND zone = :zone LIMIT 1'
        );
        $segStmt->execute([':id' => $segmentId, ':zone' => self::STUDY_ZONE]);
        $segment = $segStmt->fetch(PDO::FETCH_ASSOC);
        if (!$segment) {
            return ['success' => false, 'message' => 'Selected road was not found in Remera Sector.'];
        }

        $readingTimeDb = trim(str_replace('T', ' ', $readingTime));
        if (strlen($readingTimeDb) === 16) {
            $readingTimeDb .= ':00';
        }
        $readingTs = self::parseReadingTimestamp($readingTimeDb);
        if ($readingTs === null) {
            return ['success' => false, 'message' => 'Reading time is not valid. Use the local Kigali date and time.'];
        }
        $readingTimeDb = self::formatLocalDateTime($readingTs, 'Y-m-d H:i:s');

        $stmt = $this->conn->prepare(
            'INSERT INTO traffic_readings
            (segment_id, reading_time, vehicle_count, avg_speed_kmh, density_veh_per_km, weather, source)
            VALUES (:segment_id, :reading_time, :vehicle_count, :avg_speed_kmh, :density_veh_per_km, :weather, :source)'
        );
        $stmt->execute([
            ':segment_id' => $segmentId,
            ':reading_time' => $readingTimeDb,
            ':vehicle_count' => $vehicleCount,
            ':avg_speed_kmh' => $avgSpeed,
            ':density_veh_per_km' => $density,
            ':weather' => $weather !== '' ? $weather : 'unknown',
            ':source' => 'traffic_police',
        ]);

        $this->supersedeOlderSegmentAlerts($segmentId, $readingTimeDb, $userId);

        if ($statusNote !== '') {
            $alertStmt = $this->conn->prepare(
                "INSERT INTO alerts (segment_id, alert_time, type, severity, message, is_acknowledged)
                 VALUES (:segment_id, :alert_time, 'system', 'info', :message, 0)"
            );
            $alertStmt->execute([
                ':segment_id' => $segmentId,
                ':alert_time' => $readingTimeDb,
                ':message' => mb_substr($statusNote, 0, 255),
            ]);
        }

        $audit = $this->conn->prepare(
            'INSERT INTO audit_log (user_id, action, table_name, record_id, new_values, created_at)
             VALUES (:user_id, :action, :table_name, :record_id, :new_values, NOW())'
        );
        $audit->execute([
            ':user_id' => $userId,
            ':action' => 'road_status_update',
            ':table_name' => 'traffic_readings',
            ':record_id' => (int)$this->conn->lastInsertId(),
            ':new_values' => json_encode([
                'segment_id' => $segmentId,
                'segment_name' => $segment['name'],
                'vehicle_count' => $vehicleCount,
                'avg_speed_kmh' => $avgSpeed,
                'density_veh_per_km' => $density,
                'weather' => $weather,
                'status_note' => $statusNote,
                'source' => 'traffic_police',
            ]),
        ]);

        return [
            'success' => true,
            'message' => 'Road status saved for ' . $segment['name'] . '. Drivers will see the updated data.',
            'segment_id' => $segmentId,
        ];
    }

    public function getSafeTravelAdvice(array $seg): array
    {
        $status = (string)($seg['status'] ?? 'normal');
        $level = (string)($seg['congestion_level'] ?? 'low');
        $weather = strtolower((string)($seg['weather'] ?? ''));
        $reason = trim((string)($seg['reason'] ?? ''));

        if ($reason !== '' && $reason !== '—') {
            return [
                'text' => $reason,
                'tone' => $this->adviceToneForStatus($status, $level),
            ];
        }

        if ($status === 'blocked') {
            return [
                'text' => 'Do not use this road — it is blocked. Choose an alternate route.',
                'tone' => 'danger',
            ];
        }

        if ($status === 'issue') {
            return ['text' => 'Proceed with extra caution or use an alternate route.', 'tone' => 'warning'];
        }

        $weatherNote = '';
        if (in_array($weather, ['rain', 'storm'], true)) {
            $weatherNote = ' Wet roads — reduce speed and increase following distance.';
        } elseif ($weather === 'fog') {
            $weatherNote = ' Low visibility — use headlights and drive slowly.';
        }

        if ($level === 'severe') {
            return [
                'text' => 'Avoid if possible; severe delays expected.' . $weatherNote,
                'tone' => 'danger',
            ];
        }
        if ($level === 'high') {
            return [
                'text' => 'Safe travel possible, but expect heavy delays — allow extra time.' . $weatherNote,
                'tone' => 'warning',
            ];
        }
        if ($level === 'moderate') {
            return [
                'text' => 'Generally safe — drive with caution and moderate speed.' . $weatherNote,
                'tone' => 'caution',
            ];
        }

        return [
            'text' => 'Safe to travel — traffic is flowing normally.' . $weatherNote,
            'tone' => 'safe',
        ];
    }

    public function enrichSegmentsForDisplay(array $segments): array
    {
        $now = time();
        foreach ($segments as &$seg) {
            if (!empty($seg['reading_time'])) {
                $ts = self::parseReadingTimestamp((string)$seg['reading_time']);
                if ($ts !== null) {
                    $seg['reading_time_local'] = self::formatLocalDateTime($ts);
                    $seg['updated_label'] = self::formatRelativeSeconds(max(0, $now - $ts));
                } else {
                    $seg['reading_time_local'] = (string)$seg['reading_time'];
                    $seg['updated_label'] = 'Unknown time';
                }
            } else {
                $seg['reading_time_local'] = null;
                $seg['updated_label'] = 'No reading yet';
            }

            $advice = $this->getSafeTravelAdvice($seg);
            $seg['safe_travel_advice'] = $advice['text'];
            $seg['safe_travel_tone'] = $advice['tone'];
        }
        unset($seg);
        return $segments;
    }

    public function getRoadStatusLiveMeta(array $segments): array
    {
        $now = time();
        $latestTs = null;
        $roadsWithReadings = 0;

        foreach ($segments as $seg) {
            if (empty($seg['reading_time'])) {
                continue;
            }
            $roadsWithReadings++;
            $ts = self::parseReadingTimestamp((string)$seg['reading_time']);
            if ($ts !== null && ($latestTs === null || $ts > $latestTs)) {
                $latestTs = $ts;
            }
        }

        $secondsAgo = $latestTs !== null ? max(0, $now - $latestTs) : null;
        $liveStatus = 'no_data';
        if ($secondsAgo !== null) {
            if ($secondsAgo <= 900) {
                $liveStatus = 'live';
            } elseif ($secondsAgo <= 3600) {
                $liveStatus = 'recent';
            } else {
                $liveStatus = 'stale';
            }
        }

        return [
            'zone_label' => self::STUDY_ZONE . ', Kigali',
            'total_roads' => count($segments),
            'roads_with_readings' => $roadsWithReadings,
            'latest_reading_at' => $latestTs ? self::formatLocalDateTime($latestTs, 'Y-m-d H:i:s') : null,
            'latest_reading_at_local' => $latestTs ? self::formatLocalDateTime($latestTs) : null,
            'latest_reading_epoch' => $latestTs,
            'latest_reading_seconds_ago' => $secondsAgo,
            'latest_reading_label' => $secondsAgo !== null
                ? self::formatRelativeSeconds($secondsAgo)
                : 'No traffic readings yet',
            'live_status' => $liveStatus,
            'server_time' => date('c'),
            'server_time_local' => self::formatLocalDateTime($now),
            'timezone' => self::appTimezone(),
            'timezone_label' => self::timezoneLabel(),
        ];
    }

    public function saveRouteRequest(int $userId, array $route, array $meta): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO route_requests
            (user_id, origin_label, origin_lat, origin_lng, destination_label, destination_lat, destination_lng,
             route_name, distance_km, estimated_minutes, congestion_level, segment_ids, route_summary)
             VALUES
            (:user_id, :origin_label, :origin_lat, :origin_lng, :destination_label, :destination_lat, :destination_lng,
             :route_name, :distance_km, :estimated_minutes, :congestion_level, :segment_ids, :route_summary)"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':origin_label' => $meta['origin_label'] ?? 'Current location',
            ':origin_lat' => $meta['origin_lat'],
            ':origin_lng' => $meta['origin_lng'],
            ':destination_label' => $meta['destination_label'],
            ':destination_lat' => $meta['destination_lat'],
            ':destination_lng' => $meta['destination_lng'],
            ':route_name' => $route['name'],
            ':distance_km' => $route['distance_km'],
            ':estimated_minutes' => $route['estimated_minutes'],
            ':congestion_level' => $route['congestion_level'],
            ':segment_ids' => json_encode($route['segment_ids'] ?? []),
            ':route_summary' => json_encode([
                'key' => $route['key'],
                'segments' => array_map(fn($s) => $s['name'], $route['segments']),
            ]),
        ]);
        return (int)$this->conn->lastInsertId();
    }

    public function getTravelHistory(int $userId, int $limit = 30): array
    {
        return $this->getTravelHistoryPaginated($userId, 1, $limit)['items'];
    }

    public function getTravelHistoryPaginated(int $userId, int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->conn->prepare(
            'SELECT COUNT(*) AS total FROM route_requests WHERE user_id = :uid'
        );
        $countStmt->execute([':uid' => $userId]);
        $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt = $this->conn->prepare(
            "SELECT id, origin_label, destination_label, route_name, distance_km,
                    estimated_minutes, congestion_level, created_at
             FROM route_requests
             WHERE user_id = :uid
             ORDER BY created_at DESC
             LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    public function getAuthorityDashboardStats(int $userId): array
    {
        $summary = $this->getRoadStatusSummary();

        $stmt = $this->conn->prepare(
            "SELECT action, COUNT(*) AS cnt FROM audit_log
             WHERE user_id = :uid
             GROUP BY action"
        );
        $stmt->execute([':uid' => $userId]);
        $byAction = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byAction[$row['action']] = (int)$row['cnt'];
        }

        $alertStmt = $this->conn->query(
            "SELECT type, COUNT(*) AS cnt FROM alerts
             WHERE alert_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY type"
        );
        $alertsByType = ['congestion' => 0, 'incident' => 0, 'road_blockage' => 0, 'system' => 0];
        foreach ($alertStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $alertsByType[$row['type']] = (int)$row['cnt'];
        }

        $readingStmt = $this->conn->query(
            "SELECT COUNT(*) AS cnt FROM traffic_readings
             WHERE source = 'traffic_police'
               AND reading_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $policeReadings = (int)$readingStmt->fetch(PDO::FETCH_ASSOC)['cnt'];

        $accStmt = $this->conn->query(
            "SELECT COUNT(*) AS cnt FROM accidents
             WHERE accident_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $accidentTotal = (int)$accStmt->fetch(PDO::FETCH_ASSOC)['cnt'];

        return [
            'road_updates' => $policeReadings,
            'my_road_updates' => (int)($byAction['road_status_update'] ?? 0),
            'accident_reports' => max($accidentTotal, $alertsByType['incident']),
            'congestion_reports' => $alertsByType['congestion'],
            'road_blockages' => $alertsByType['road_blockage'],
            'maintenance_alerts' => $alertsByType['system'],
            'sector_summary' => $summary,
            'unacknowledged_alerts' => $this->countUnacknowledgedAlerts(),
        ];
    }

    public function countUnacknowledgedAlerts(): int
    {
        $stmt = $this->conn->query(
            "SELECT COUNT(*) AS cnt FROM alerts WHERE is_acknowledged = 0"
        );
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    }

    public function getAuthorityRecentActivity(int $userId, int $limit = 10): array
    {
        return $this->getAuthorityActivityPaginated($userId, 1, $limit)['items'];
    }

    public function getAuthorityActivityPaginated(int $userId, int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->conn->prepare('SELECT COUNT(*) AS total FROM audit_log WHERE user_id = :uid');
        $countStmt->execute([':uid' => $userId]);
        $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt = $this->conn->prepare(
            "SELECT id, action, table_name, record_id, new_values, created_at
             FROM audit_log
             WHERE user_id = :uid
             ORDER BY created_at DESC
             LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $meta = json_decode((string)($row['new_values'] ?? '{}'), true) ?: [];
            $items[] = [
                'id' => (int)$row['id'],
                'action' => $row['action'],
                'label' => $this->formatAuditActionLabel($row['action'], $meta),
                'details' => $this->formatAuditActionDetails($meta),
                'created_at' => $row['created_at'],
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $total > 0 ? (int)ceil($total / $perPage) : 1,
        ];
    }

    private function formatAuditActionLabel(string $action, array $meta): string
    {
        return match ($action) {
            'road_status_update' => 'Road status updated',
            'incident_report' => 'Accident reported',
            'alert_report' => 'Traffic alert sent',
            'alert_cleared' => 'Alert cleared',
            'accident_resolved' => 'Accident resolved',
            default => ucwords(str_replace('_', ' ', $action)),
        };
    }

    private function formatAuditActionDetails(array $meta): string
    {
        if (!empty($meta['segment_name'])) {
            return (string)$meta['segment_name'];
        }
        if (!empty($meta['message'])) {
            return (string)$meta['message'];
        }
        if (!empty($meta['cause'])) {
            return (string)$meta['cause'];
        }
        return 'Remera Sector';
    }

    public function reportAccident(int $userId, array $data): array
    {
        $segmentId = (int)($data['segment_id'] ?? 0);
        $accidentTime = trim((string)($data['accident_time'] ?? ''));
        $severity = strtolower(trim((string)($data['severity'] ?? 'minor')));
        $cause = trim((string)($data['cause'] ?? ''));
        $notes = trim((string)($data['notes'] ?? ''));
        $vehicles = isset($data['vehicles_involved']) ? (int)$data['vehicles_involved'] : null;
        $injuries = isset($data['injuries']) ? (int)$data['injuries'] : null;
        $fatalities = isset($data['fatalities']) ? (int)$data['fatalities'] : null;

        if ($segmentId <= 0) {
            return ['success' => false, 'message' => 'Please select a road location.'];
        }
        if ($accidentTime === '') {
            return ['success' => false, 'message' => 'Accident time is required.'];
        }
        if (!in_array($severity, ['minor', 'moderate', 'severe', 'fatal'], true)) {
            $severity = 'minor';
        }

        $segment = $this->getSegmentById($segmentId);
        if (!$segment) {
            return ['success' => false, 'message' => 'Selected road was not found.'];
        }

        $accidentTimeDb = $this->normalizeDateTimeInput($accidentTime);
        if ($accidentTimeDb === null) {
            return ['success' => false, 'message' => 'Accident time is not valid.'];
        }

        $messageParts = array_values(array_filter([$cause, $notes], static fn ($part) => trim((string)$part) !== ''));
        $message = $messageParts !== []
            ? implode(' — ', $messageParts)
            : ('Accident reported on ' . $segment['name']);
        $alertSeverity = in_array($severity, ['severe', 'fatal'], true) ? 'critical' : 'warning';

        $stmt = $this->conn->prepare(
            "INSERT INTO accidents
            (segment_id, accident_time, severity, cause, vehicles_involved, injuries, fatalities, notes, reported_by)
            VALUES (:segment_id, :accident_time, :severity, :cause, :vehicles, :injuries, :fatalities, :notes, :reported_by)"
        );
        $stmt->execute([
            ':segment_id' => $segmentId,
            ':accident_time' => $accidentTimeDb,
            ':severity' => $severity,
            ':cause' => $cause !== '' ? $cause : null,
            ':vehicles' => $vehicles,
            ':injuries' => $injuries,
            ':fatalities' => $fatalities,
            ':notes' => $notes !== '' ? $notes : null,
            ':reported_by' => $userId,
        ]);
        $accidentId = (int)$this->conn->lastInsertId();

        $alertStmt = $this->conn->prepare(
            "INSERT INTO alerts (segment_id, alert_time, type, severity, message, is_acknowledged)
             VALUES (:segment_id, :alert_time, 'incident', :severity, :message, 0)"
        );
        $alertStmt->execute([
            ':segment_id' => $segmentId,
            ':alert_time' => $accidentTimeDb,
            ':severity' => $alertSeverity,
            ':message' => mb_substr($message, 0, 255),
        ]);

        $this->writeAuditLog($userId, 'incident_report', 'accidents', $accidentId, [
            'segment_id' => $segmentId,
            'segment_name' => $segment['name'],
            'severity' => $severity,
            'cause' => $cause,
            'notes' => $notes,
        ]);

        return [
            'success' => true,
            'message' => 'Accident report saved. Drivers will be notified.',
            'accident_id' => $accidentId,
        ];
    }

    public function reportTrafficAlert(int $userId, array $data): array
    {
        $segmentId = (int)($data['segment_id'] ?? 0);
        $type = strtolower(trim((string)($data['type'] ?? 'congestion')));
        $message = trim((string)($data['message'] ?? ''));
        $severity = strtolower(trim((string)($data['severity'] ?? 'warning')));
        $alertTime = trim((string)($data['alert_time'] ?? ''));
        $syncStatus = !array_key_exists('sync_status', $data) || (int)$data['sync_status'] === 1;

        $allowedTypes = ['congestion', 'road_blockage', 'system', 'incident'];
        if (!in_array($type, $allowedTypes, true)) {
            return ['success' => false, 'message' => 'Invalid alert type.'];
        }
        if ($segmentId <= 0) {
            return ['success' => false, 'message' => 'Please select a road.'];
        }
        if ($message === '') {
            return ['success' => false, 'message' => 'Please enter alert details.'];
        }
        if (!in_array($severity, ['info', 'warning', 'critical'], true)) {
            $severity = 'warning';
        }

        $segment = $this->getSegmentById($segmentId);
        if (!$segment) {
            return ['success' => false, 'message' => 'Selected road was not found.'];
        }

        $alertTimeDb = $alertTime !== '' ? $this->normalizeDateTimeInput($alertTime) : date('Y-m-d H:i:s');
        if ($alertTimeDb === null) {
            return ['success' => false, 'message' => 'Alert time is not valid.'];
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO alerts (segment_id, alert_time, type, severity, message, is_acknowledged)
             VALUES (:segment_id, :alert_time, :type, :severity, :message, 0)"
        );
        $stmt->execute([
            ':segment_id' => $segmentId,
            ':alert_time' => $alertTimeDb,
            ':type' => $type,
            ':severity' => $severity,
            ':message' => mb_substr($message, 0, 255),
        ]);
        $alertId = (int)$this->conn->lastInsertId();

        // Keep driver road status in sync with incident alerts (real-time behavior).
        if ($syncStatus && in_array($type, ['congestion', 'road_blockage', 'system'], true)) {
            $levelBySeverity = ['info' => 'moderate', 'warning' => 'high', 'critical' => 'severe'];
            $status = 'congested';
            $level = $levelBySeverity[$severity] ?? 'high';
            if ($type === 'road_blockage') {
                $status = 'blocked';
                $level = 'severe';
            } elseif ($type === 'system') {
                $status = 'maintenance';
                $level = 'high';
            }

            $speedLimit = (int)($segment['speed_limit_kmh'] ?? 50);
            $metrics = $this->trafficMetricsForOfficerUpdate($status, $level, $speedLimit);
            $this->submitTrafficReading($userId, [
                'segment_id' => $segmentId,
                'reading_time' => $alertTimeDb,
                'vehicle_count' => $metrics['vehicle_count'],
                'avg_speed_kmh' => $metrics['avg_speed_kmh'],
                'density_veh_per_km' => max(10, (int)round($metrics['vehicle_count'] / 2)),
                'weather' => 'unknown',
                'status_note' => '',
            ]);
        } else {
            $this->supersedeOlderSegmentAlerts($segmentId, $alertTimeDb, $userId);
        }

        $this->writeAuditLog($userId, 'alert_report', 'alerts', $alertId, [
            'segment_id' => $segmentId,
            'segment_name' => $segment['name'],
            'type' => $type,
            'severity' => $severity,
            'message' => $message,
        ]);

        $smsDispatch = null;
        $smsDriverCount = 0;
        if ($type === 'road_blockage') {
            require_once __DIR__ . '/sms_helpers.php';
            $smsDriverCount = strgs_count_sms_eligible_drivers($this->conn);
            $smsDispatch = [
                'road' => (string)$segment['name'],
                'details' => $message,
            ];
        }

        $responseMessage = 'Alert sent to drivers successfully.';
        if ($type === 'road_blockage') {
            $responseMessage = 'Road blocked. Drivers notified in the app.';
            if ($smsDriverCount > 0) {
                $responseMessage .= ' SMS is being sent to ' . $smsDriverCount . ' driver(s).';
            } else {
                $responseMessage .= ' No driver phone numbers found for SMS.';
            }
        }

        return [
            'success' => true,
            'message' => $responseMessage,
            'alert_id' => $alertId,
            'sms_dispatch' => $smsDispatch,
            'sms_driver_count' => $smsDriverCount,
        ];
    }

    public function acknowledgeAlert(int $userId, int $alertId): array
    {
        $stmt = $this->conn->prepare(
            "UPDATE alerts
             SET is_acknowledged = 1, acknowledged_by = :uid, acknowledged_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([':uid' => $userId, ':id' => $alertId]);
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Alert not found.'];
        }

        $this->writeAuditLog($userId, 'alert_cleared', 'alerts', $alertId, [
            'alert_id' => $alertId,
            'message' => 'Alert marked as acknowledged',
        ]);

        return ['success' => true, 'message' => 'Alert marked as acknowledged.'];
    }

    public function resolveAccident(int $userId, int $accidentId, string $resolutionNote = ''): array
    {
        $stmt = $this->conn->prepare(
            'SELECT a.id, a.segment_id, a.notes, rs.name AS segment_name
             FROM accidents a
             INNER JOIN road_segments rs ON rs.id = a.segment_id
             WHERE a.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $accidentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'message' => 'Accident record not found.'];
        }

        $note = trim($resolutionNote);
        $existing = trim((string)($row['notes'] ?? ''));
        $combined = $existing;
        if ($note !== '') {
            $combined = $existing !== '' ? $existing . ' | RESOLVED: ' . $note : 'RESOLVED: ' . $note;
        } elseif ($existing === '' || stripos($existing, 'resolved') === false) {
            $combined = 'RESOLVED by traffic police.';
        }

        $up = $this->conn->prepare('UPDATE accidents SET notes = :notes WHERE id = :id');
        $up->execute([':notes' => $combined, ':id' => $accidentId]);

        $this->writeAuditLog($userId, 'accident_resolved', 'accidents', $accidentId, [
            'segment_id' => (int)$row['segment_id'],
            'segment_name' => $row['segment_name'],
            'message' => $note !== '' ? $note : 'Accident marked as resolved',
        ]);

        return ['success' => true, 'message' => 'Accident marked as resolved.'];
    }

    public function getAuthorityAlerts(int $limit = 40, int $offset = 0): array
    {
        $stmt = $this->conn->prepare(
            "SELECT a.id, a.alert_time, a.type, a.severity, a.message, a.is_acknowledged,
                    rs.name AS segment_name, rs.zone
             FROM alerts a
             LEFT JOIN road_segments rs ON rs.id = a.segment_id
             ORDER BY a.is_acknowledged ASC, a.alert_time DESC
             LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAccidentsList(int $limit = 30, int $offset = 0): array
    {
        $stmt = $this->conn->prepare(
            "SELECT a.id, a.accident_time, a.severity, a.cause, a.notes, a.vehicles_involved,
                    a.injuries, a.fatalities, rs.name AS segment_name, rs.zone,
                    (SELECT COUNT(*) + 1
                     FROM accidents a_num
                     WHERE a_num.accident_time > a.accident_time
                        OR (a_num.accident_time = a.accident_time AND a_num.id > a.id)
                    ) AS report_number
             FROM accidents a
             INNER JOIN road_segments rs ON rs.id = a.segment_id
             ORDER BY a.accident_time DESC
             LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['is_resolved'] = stripos((string)($row['notes'] ?? ''), 'resolved') !== false;
            $row['report_number'] = (int)($row['report_number'] ?? 0);
        }
        unset($row);
        return $rows;
    }

    public function getAuthorityReports(string $period = 'daily'): array
    {
        $interval = match ($period) {
            'weekly' => 7,
            'monthly' => 30,
            default => 1,
        };

        $readingStmt = $this->conn->prepare(
            "SELECT DATE(reading_time) AS day, COUNT(*) AS cnt
             FROM traffic_readings
             WHERE source = 'traffic_police'
               AND reading_time >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(reading_time)
             ORDER BY day ASC"
        );
        $readingStmt->bindValue(':days', $interval === 1 ? 1 : ($interval === 7 ? 7 : 30), PDO::PARAM_INT);
        $readingStmt->execute();
        $readings = $readingStmt->fetchAll(PDO::FETCH_ASSOC);

        $alertStmt = $this->conn->prepare(
            "SELECT type, COUNT(*) AS cnt
             FROM alerts
             WHERE alert_time >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY type"
        );
        $alertStmt->bindValue(':days', $interval === 1 ? 1 : ($interval === 7 ? 7 : 30), PDO::PARAM_INT);
        $alertStmt->execute();
        $alerts = $alertStmt->fetchAll(PDO::FETCH_ASSOC);

        $timelineStmt = $this->conn->prepare(
            "SELECT DATE(alert_time) AS day, COUNT(*) AS cnt
             FROM alerts
             WHERE alert_time >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(alert_time)
             ORDER BY day ASC"
        );
        $timelineStmt->bindValue(':days', $interval === 1 ? 1 : ($interval === 7 ? 7 : 30), PDO::PARAM_INT);
        $timelineStmt->execute();
        $alertsTimeline = $timelineStmt->fetchAll(PDO::FETCH_ASSOC);

        $accStmt = $this->conn->prepare(
            "SELECT COUNT(*) AS cnt FROM accidents
             WHERE accident_time >= DATE_SUB(NOW(), INTERVAL :days DAY)"
        );
        $accStmt->bindValue(':days', $interval === 1 ? 1 : ($interval === 7 ? 7 : 30), PDO::PARAM_INT);
        $accStmt->execute();
        $accidents = (int)$accStmt->fetch(PDO::FETCH_ASSOC)['cnt'];

        $blockages = 0;
        $congestion = 0;
        $incidents = 0;
        $maintenance = 0;
        foreach ($alerts as $row) {
            $type = (string)($row['type'] ?? '');
            $cnt = (int)($row['cnt'] ?? 0);
            if ($type === 'road_blockage') {
                $blockages = $cnt;
            } elseif ($type === 'congestion') {
                $congestion = $cnt;
            } elseif ($type === 'incident') {
                $incidents = $cnt;
            } elseif ($type === 'system') {
                $maintenance = $cnt;
            }
        }

        return [
            'period' => $period,
            'period_label' => match ($period) {
                'weekly' => 'Last 7 days',
                'monthly' => 'Last 30 days',
                default => 'Today',
            },
            'road_readings' => $readings,
            'alerts_by_type' => $alerts,
            'alerts_timeline' => $alertsTimeline,
            'accident_count' => $accidents,
            'total_readings' => array_sum(array_map(static fn($r) => (int)($r['cnt'] ?? 0), $readings)),
            'total_alerts' => array_sum(array_map(static fn($a) => (int)($a['cnt'] ?? 0), $alerts)),
            'blockage_count' => $blockages,
            'congestion_count' => $congestion,
            'incident_alert_count' => $incidents,
            'maintenance_count' => $maintenance,
            'summary' => $this->getAuthorityDashboardStats(0),
        ];
    }

    public function markRoadStatus(int $userId, array $data): array
    {
        $segmentId = (int)($data['segment_id'] ?? 0);
        $status = strtolower(trim((string)($data['road_status'] ?? '')));
        $congestionLevel = strtolower(trim((string)($data['congestion_level'] ?? 'low')));
        $note = trim((string)($data['status_note'] ?? ''));

        $validStatuses = ['normal', 'congested', 'blocked', 'maintenance'];
        $validCongestion = ['low', 'moderate', 'high', 'severe'];

        if ($segmentId <= 0) {
            return ['success' => false, 'message' => 'Please select a road.'];
        }
        if (!in_array($status, $validStatuses, true)) {
            return ['success' => false, 'message' => 'Invalid road status.'];
        }
        if (!in_array($congestionLevel, $validCongestion, true)) {
            $congestionLevel = 'low';
        }
        if ($status === 'blocked') {
            $congestionLevel = 'severe';
        }

        $segment = $this->getSegmentById($segmentId);
        if (!$segment) {
            return ['success' => false, 'message' => 'Selected road was not found in Remera Sector.'];
        }

        $speedLimit = (int)($segment['speed_limit_kmh'] ?? 50);
        $metrics = $this->trafficMetricsForOfficerUpdate($status, $congestionLevel, $speedLimit);
        $readingTimeIso = date('Y-m-d\TH:i');
        $readingData = [
            'segment_id' => $segmentId,
            'reading_time' => $readingTimeIso,
            'vehicle_count' => $metrics['vehicle_count'],
            'avg_speed_kmh' => $metrics['avg_speed_kmh'],
            'density_veh_per_km' => max(10, (int)round($metrics['vehicle_count'] / 2)),
            'weather' => 'clear',
            'status_note' => in_array($status, ['blocked', 'congested', 'maintenance'], true) ? '' : $note,
        ];

        $result = $this->submitTrafficReading($userId, $readingData);
        if (empty($result['success'])) {
            return $result;
        }

        $smsDispatch = null;
        $alertResult = [];
        if ($status === 'blocked') {
            $blockMsg = $note !== '' ? $note : ('Road blocked — ' . $segment['name']);
            $alertResult = $this->reportTrafficAlert($userId, [
                'segment_id' => $segmentId,
                'type' => 'road_blockage',
                'severity' => 'critical',
                'message' => $blockMsg,
                'alert_time' => $readingTimeIso,
                'sync_status' => 0,
            ]);
            $smsDispatch = $alertResult['sms_dispatch'] ?? null;
        } elseif ($status === 'maintenance') {
            $maintMsg = $note !== '' ? $note : ('Road under maintenance — ' . $segment['name']);
            $this->reportTrafficAlert($userId, [
                'segment_id' => $segmentId,
                'type' => 'system',
                'severity' => 'warning',
                'message' => $maintMsg,
                'alert_time' => $readingTimeIso,
                'sync_status' => 0,
            ]);
        } elseif ($status === 'congested') {
            $congMsg = $note !== '' ? $note : ('Road congested — ' . $segment['name']);
            $this->reportTrafficAlert($userId, [
                'segment_id' => $segmentId,
                'type' => 'congestion',
                'severity' => in_array($congestionLevel, ['high', 'severe'], true) ? 'warning' : 'info',
                'message' => $congMsg,
                'alert_time' => $readingTimeIso,
                'sync_status' => 0,
            ]);
        }

        $resultMessage = 'Road status updated for ' . $segment['name'] . '.';
        if ($status === 'blocked') {
            $driverCount = is_array($smsDispatch) ? (int)($alertResult['sms_driver_count'] ?? 0) : 0;
            $resultMessage = 'Road blocked. Drivers notified in the app.';
            if ($driverCount > 0) {
                $resultMessage .= ' SMS is being sent to ' . $driverCount . ' driver(s).';
            } else {
                $resultMessage .= ' No driver phone numbers found for SMS.';
            }
        }

        return [
            'success' => true,
            'message' => $resultMessage,
            'segment_id' => $segmentId,
            'sms_dispatch' => $smsDispatch,
        ];
    }

    private function trafficMetricsForOfficerUpdate(string $status, string $congestionLevel, int $speedLimit): array
    {
        $limit = max($speedLimit, 40);
        $map = [
            'low' => ['vehicle_count' => 45, 'avg_speed_kmh' => round($limit * 0.80, 1)],
            'moderate' => ['vehicle_count' => 95, 'avg_speed_kmh' => round($limit * 0.55, 1)],
            'high' => ['vehicle_count' => 165, 'avg_speed_kmh' => round($limit * 0.35, 1)],
            'severe' => ['vehicle_count' => 200, 'avg_speed_kmh' => round($limit * 0.15, 1)],
        ];

        if ($status === 'blocked') {
            return ['vehicle_count' => 0, 'avg_speed_kmh' => 0.0];
        }
        if ($status === 'maintenance') {
            return ['vehicle_count' => 25, 'avg_speed_kmh' => round($limit * 0.25, 1)];
        }
        if ($status === 'normal' && $congestionLevel === 'low') {
            return $map['low'];
        }

        return $map[$congestionLevel] ?? $map['low'];
    }

    private function getSegmentById(int $segmentId): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT id, name, zone, speed_limit_kmh FROM road_segments WHERE id = :id AND is_active = 1 AND zone = :zone LIMIT 1'
        );
        $stmt->execute([':id' => $segmentId, ':zone' => self::STUDY_ZONE]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function normalizeDateTimeInput(string $value): ?string
    {
        $readingTimeDb = trim(str_replace('T', ' ', $value));
        if (strlen($readingTimeDb) === 16) {
            $readingTimeDb .= ':00';
        }
        $ts = self::parseReadingTimestamp($readingTimeDb);
        if ($ts === null) {
            return null;
        }
        return self::formatLocalDateTime($ts, 'Y-m-d H:i:s');
    }

    private function writeAuditLog(int $userId, string $action, string $table, ?int $recordId, array $payload): void
    {
        $stmt = $this->conn->prepare(
            'INSERT INTO audit_log (user_id, action, table_name, record_id, new_values, created_at)
             VALUES (:user_id, :action, :table_name, :record_id, :new_values, NOW())'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':action' => $action,
            ':table_name' => $table,
            ':record_id' => $recordId,
            ':new_values' => json_encode($payload),
        ]);
    }
}
