<?php

class DriverRouteService
{
    private PDO $conn;

    public const STUDY_ZONE = 'Remera Sector';

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

        $stmt = $this->conn->prepare(
            "SELECT name, zone,
                    ((start_lat + end_lat) / 2) AS lat,
                    ((start_lng + end_lng) / 2) AS lng
             FROM road_segments
             WHERE is_active = 1 AND zone = :zone AND (name LIKE :q OR zone LIKE :q)
             ORDER BY name ASC LIMIT 10"
        );
        $stmt->execute([':zone' => self::STUDY_ZONE, ':q' => '%' . $query . '%']);
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
        $alertStmt = $this->conn->query(
            "SELECT segment_id, type, severity, message, alert_time
             FROM alerts
             WHERE segment_id IS NOT NULL
               AND alert_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY alert_time DESC"
        );
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

            if (isset($latestAlertBySegment[$segmentId])) {
                $alert = $latestAlertBySegment[$segmentId];
                if ($alert['type'] === 'road_blockage') {
                    $status = ['status' => 'blocked', 'congestion_level' => 'severe', 'label' => 'Blocked road'];
                    $reason = $alert['message'];
                } elseif ($alert['type'] === 'incident') {
                    $status = ['status' => 'issue', 'congestion_level' => 'high', 'label' => 'Incident reported'];
                    $reason = $alert['message'];
                } elseif ($alert['type'] === 'system' && in_array($alert['severity'], ['warning', 'danger'], true)) {
                    $status = ['status' => 'issue', 'congestion_level' => 'high', 'label' => 'Road issue reported'];
                    $reason = $alert['message'];
                }
            }

            if ($reason === null) {
                if ($status['status'] === 'normal') {
                    $reason = 'Traffic is flowing normally';
                } elseif ($status['congestion_level'] === 'moderate') {
                    $reason = 'Moderate traffic volume';
                } elseif ($status['congestion_level'] === 'high') {
                    $reason = 'High congestion due to vehicle build-up';
                } elseif ($status['congestion_level'] === 'severe') {
                    $reason = 'Very slow movement reported on this road';
                } else {
                    $reason = 'Status updated from latest traffic reading';
                }
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
            if ($row['type'] === 'road_blockage') {
                $summary['blocked'] = max($summary['blocked'], (int)$row['cnt']);
            } elseif ($row['type'] === 'incident') {
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
            $readingTs = strtotime((string)$row['reading_time']) ?: $now;
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

        $originSeg = $this->nearestSegment($segments, $originLat, $originLng);
        $destSeg = $this->nearestSegment($segments, $destLat, $destLng);

        $graph = $this->buildGraph($segments);
        $paths = [];

        $primary = $this->findPath($graph, $segments, $originSeg['id'], $destSeg['id'], 'balanced');
        if ($primary) {
            $paths[] = $this->buildRouteResult($primary, $segments, $originLat, $originLng, $destLat, $destLng, 'Best Route', 'primary');
        }

        $alt1 = $this->findPath($graph, $segments, $originSeg['id'], $destSeg['id'], 'shortest');
        if ($alt1 && !$this->samePath($alt1, $primary)) {
            $paths[] = $this->buildRouteResult($alt1, $segments, $originLat, $originLng, $destLat, $destLng, 'Fastest Route', 'fastest');
        }

        $alt2 = $this->findPath($graph, $segments, $originSeg['id'], $destSeg['id'], 'clear');
        if ($alt2 && !$this->samePath($alt2, $primary) && !$this->samePath($alt2, $alt1)) {
            $paths[] = $this->buildRouteResult($alt2, $segments, $originLat, $originLng, $destLat, $destLng, 'Least Congested', 'clear');
        }

        if (empty($paths) && $originSeg['id'] === $destSeg['id']) {
            $paths[] = $this->buildRouteResult(
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

        return [
            'origin' => ['lat' => $originLat, 'lng' => $originLng],
            'destination' => ['lat' => $destLat, 'lng' => $destLng, 'label' => $destLabel],
            'routes' => $paths,
        ];
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

    private function findPath(array $graph, array $segments, int $start, int $end, string $mode): ?array
    {
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
            'polyline' => $this->buildPolyline($routeSegments, $originLat, $originLng, $destLat, $destLng),
        ];
    }

    private function buildPolyline(array $segments, float $oLat, float $oLng, float $dLat, float $dLng): array
    {
        $points = [['lat' => $oLat, 'lng' => $oLng]];
        foreach ($segments as $seg) {
            $points[] = ['lat' => $seg['start_lat'], 'lng' => $seg['start_lng']];
            $points[] = ['lat' => $seg['end_lat'], 'lng' => $seg['end_lng']];
        }
        $points[] = ['lat' => $dLat, 'lng' => $dLng];
        return $points;
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
            ':segment_ids' => json_encode($route['segment_ids']),
            ':route_summary' => json_encode([
                'key' => $route['key'],
                'segments' => array_map(fn($s) => $s['name'], $route['segments']),
            ]),
        ]);
        return (int)$this->conn->lastInsertId();
    }

    public function getTravelHistory(int $userId, int $limit = 30): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id, origin_label, destination_label, route_name, distance_km,
                    estimated_minutes, congestion_level, created_at
             FROM route_requests
             WHERE user_id = :uid
             ORDER BY created_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
