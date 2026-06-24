<?php

require_once __DIR__ . '/DriverRouteService.php';

function driverApiJson($payload) {
    echo json_encode($payload);
    exit;
}

function driverApiDb(): PDO {
    $database = new Database();
    $db = $database->getConnection();
    if ($db === null) {
        throw new Exception('Database connection failed');
    }
    return $db;
}

function handleDriverApiAction(string $action): void {
    $roadStatusReadActions = ['driver_road_status', 'driver_segments_map'];
    $authorityWriteActions = ['authority_submit_road_reading'];

    if (in_array($action, $roadStatusReadActions, true)) {
        requireLogin();
        if (!in_array($_SESSION['user_type'] ?? '', ['driver', 'traffic_authority'], true)) {
            driverApiJson(['success' => false, 'message' => 'Access denied']);
        }
    } elseif (in_array($action, $authorityWriteActions, true)) {
        requireLogin();
        if (($_SESSION['user_type'] ?? '') !== 'traffic_authority') {
            driverApiJson(['success' => false, 'message' => 'Traffic police access only']);
        }
    } else {
        requireDriver();
    }

    $userId = (int)$_SESSION['user_id'];

    try {
        $db = driverApiDb();
        $service = new DriverRouteService($db);

        switch ($action) {
            case 'driver_home':
                $user = new User($db);
                $user->findById($userId);
                driverApiJson([
                    'success' => true,
                    'welcome' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                    'profile' => [
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'phone_number' => $user->phone_number,
                        'license_id' => $user->license_id,
                        'profile_picture' => $user->profile_picture,
                    ],
                    'traffic_summary' => $service->getRoadStatusSummary(),
                    'recent_updates' => $service->getRecentRoadUpdates(10),
                    'notifications' => $service->getDriverNotifications($userId, 5, 0),
                    'notification_count' => $service->countDriverNotifications($userId),
                    'map_config' => $service->getRemeraMapConfig(),
                ]);
                break;

            case 'driver_profile_get':
                $user = new User($db);
                if (!$user->findById($userId)) {
                    driverApiJson(['success' => false, 'message' => 'User not found']);
                }
                driverApiJson([
                    'success' => true,
                    'profile' => [
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'phone_number' => $user->phone_number,
                        'username' => $user->username,
                        'license_id' => $user->license_id,
                        'profile_picture' => $user->profile_picture,
                    ],
                ]);
                break;

            case 'driver_profile_update':
                $first = trim($_POST['first_name'] ?? '');
                $last = trim($_POST['last_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone_number'] ?? '');
                $normalized = normalizeRwandaPhone($phone);

                if ($first === '' || $last === '') {
                    driverApiJson(['success' => false, 'message' => 'First and last name are required']);
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    driverApiJson(['success' => false, 'message' => 'Invalid email address']);
                }
                if ($normalized === false) {
                    driverApiJson(['success' => false, 'message' => 'Invalid Rwanda phone number']);
                }

                $user = new User($db);
                if (!$user->findById($userId)) {
                    driverApiJson(['success' => false, 'message' => 'User not found']);
                }
                $user->updateProfile($first, $last, $email, $normalized);

                $_SESSION['user_name'] = $first . ' ' . $last;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_phone'] = $normalized;
                $_SESSION['first_name'] = $first;
                $_SESSION['last_name'] = $last;

                driverApiJson(['success' => true, 'message' => 'Profile updated successfully']);
                break;

            case 'driver_upload_avatar':
                if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
                    driverApiJson(['success' => false, 'message' => 'Please select an image to upload']);
                }
                $file = $_FILES['profile_picture'];
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $mime = mime_content_type($file['tmp_name']);
                if (!isset($allowed[$mime])) {
                    driverApiJson(['success' => false, 'message' => 'Only JPG, PNG, or WEBP images are allowed']);
                }
                if ($file['size'] > 2 * 1024 * 1024) {
                    driverApiJson(['success' => false, 'message' => 'Image must be under 2MB']);
                }

                $dir = __DIR__ . '/../uploads/profiles';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $filename = 'user_' . $userId . '_' . time() . '.' . $allowed[$mime];
                $dest = $dir . '/' . $filename;
                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    driverApiJson(['success' => false, 'message' => 'Failed to save image']);
                }

                $publicPath = 'uploads/profiles/' . $filename;
                $user = new User($db);
                $user->findById($userId);
                $user->updateProfilePicture($publicPath);
                $_SESSION['profile_picture'] = $publicPath;

                driverApiJson(['success' => true, 'profile_picture' => $publicPath]);
                break;

            case 'driver_road_status':
                $segments = $service->enrichSegmentsForDisplay($service->getSegmentsWithStatus());
                $blockedRoads = array_values(array_filter($segments, static fn($s) => $s['status'] === 'blocked'));
                $congestedRoads = array_values(array_filter($segments, static fn($s) => $s['status'] === 'congested'));
                $normalRoads = array_values(array_filter($segments, static fn($s) => $s['status'] === 'normal'));
                $issueRoads = array_values(array_filter($segments, static fn($s) => in_array($s['status'], ['blocked', 'issue'], true) || in_array($s['congestion_level'], ['high', 'severe'], true)));
                $accidents = $db->query(
                    "SELECT a.id, a.accident_time, a.severity, a.cause, rs.name AS segment_name
                     FROM accidents a
                     LEFT JOIN road_segments rs ON rs.id = a.segment_id
                     ORDER BY a.accident_time DESC LIMIT 15"
                )->fetchAll(PDO::FETCH_ASSOC);

                driverApiJson([
                    'success' => true,
                    'summary' => $service->getRoadStatusSummary(),
                    'segments' => $segments,
                    'normal_roads' => $normalRoads,
                    'congested_roads' => $congestedRoads,
                    'blocked_roads' => $blockedRoads,
                    'issue_roads' => $issueRoads,
                    'accidents' => $accidents,
                    'meta' => $service->getRoadStatusLiveMeta($segments),
                    'updated_at' => date('c'),
                ]);
                break;

            case 'authority_submit_road_reading':
                $result = $service->submitTrafficReading($userId, [
                    'segment_id' => $_POST['segment_id'] ?? 0,
                    'reading_time' => $_POST['reading_time'] ?? '',
                    'vehicle_count' => $_POST['vehicle_count'] ?? 0,
                    'avg_speed_kmh' => $_POST['avg_speed_kmh'] ?? 0,
                    'density_veh_per_km' => $_POST['density_veh_per_km'] ?? 0,
                    'weather' => $_POST['weather'] ?? '',
                    'status_note' => $_POST['status_note'] ?? '',
                ]);
                driverApiJson($result);
                break;

            case 'driver_alerts':
                $limit = max(5, min(100, (int)($_POST['limit'] ?? 30)));
                $offset = max(0, (int)($_POST['offset'] ?? 0));
                $notifications = $service->getDriverNotifications($userId, $limit, $offset);
                driverApiJson([
                    'success' => true,
                    'notifications' => $notifications,
                    'count' => count($notifications),
                    'unread_count' => $service->countDriverNotifications($userId),
                    'alerts' => $notifications,
                ]);
                break;

            case 'driver_mark_notifications_read':
                $raw = $_POST['notification_ids'] ?? '[]';
                $ids = is_array($raw) ? $raw : json_decode((string)$raw, true);
                if (!is_array($ids)) {
                    $ids = [];
                }
                $result = $service->markDriverNotificationsRead($userId, $ids);
                driverApiJson([
                    'success' => true,
                    'marked' => $result['marked'],
                    'unread_count' => $service->countDriverNotifications($userId),
                ]);
                break;

            case 'driver_search_destinations':
                $q = trim($_POST['query'] ?? $_GET['query'] ?? '');
                driverApiJson([
                    'success' => true,
                    'results' => $service->searchDestinations($q),
                ]);
                break;

            case 'driver_segments_map':
                driverApiJson([
                    'success' => true,
                    'segments' => $service->getSegmentsWithStatus(),
                    'landmarks' => $service->getRemeraLandmarks(),
                    'map_config' => $service->getRemeraMapConfig(),
                ]);
                break;

            case 'driver_recent_updates':
                driverApiJson([
                    'success' => true,
                    'recent_updates' => $service->getRecentRoadUpdates(10),
                    'map_config' => $service->getRemeraMapConfig(),
                ]);
                break;

            case 'driver_request_route':
                $originLat = (float)($_POST['origin_lat'] ?? 0);
                $originLng = (float)($_POST['origin_lng'] ?? 0);
                $destLat = (float)($_POST['dest_lat'] ?? 0);
                $destLng = (float)($_POST['dest_lng'] ?? 0);
                $destLabel = trim($_POST['destination_label'] ?? 'Destination');

                if (!$originLat || !$originLng || !$destLat || !$destLng) {
                    driverApiJson(['success' => false, 'message' => 'Origin and destination coordinates are required']);
                }

                $result = $service->recommendRoutes($originLat, $originLng, $destLat, $destLng, $destLabel);
                if (empty($result['routes'])) {
                    driverApiJson(['success' => false, 'message' => 'No route could be calculated. Try a destination within Remera Sector.']);
                }

                driverApiJson(['success' => true, 'guidance' => $result]);
                break;

            case 'driver_confirm_route':
                $originLat = (float)($_POST['origin_lat'] ?? 0);
                $originLng = (float)($_POST['origin_lng'] ?? 0);
                $destLat = (float)($_POST['dest_lat'] ?? 0);
                $destLng = (float)($_POST['dest_lng'] ?? 0);
                $destLabel = trim($_POST['destination_label'] ?? 'Destination');
                $originLabel = trim($_POST['origin_label'] ?? 'Current location');
                $routeIndex = (int)($_POST['route_index'] ?? -1);

                if (!$originLat || !$originLng || !$destLat || !$destLng) {
                    driverApiJson(['success' => false, 'message' => 'Origin and destination coordinates are required']);
                }

                $result = $service->recommendRoutes($originLat, $originLng, $destLat, $destLng, $destLabel);
                $routes = $result['routes'] ?? [];
                if ($routeIndex < 0 || !isset($routes[$routeIndex])) {
                    driverApiJson(['success' => false, 'message' => 'Please select a valid route option.']);
                }

                $service->saveRouteRequest($userId, $routes[$routeIndex], [
                    'origin_label' => $originLabel,
                    'origin_lat' => $originLat,
                    'origin_lng' => $originLng,
                    'destination_label' => $destLabel,
                    'destination_lat' => $destLat,
                    'destination_lng' => $destLng,
                ]);

                driverApiJson([
                    'success' => true,
                    'message' => 'Your route has been saved. Drive safely.',
                    'route' => $routes[$routeIndex],
                ]);
                break;

            case 'driver_travel_history':
                $page = max(1, (int)($_POST['page'] ?? 1));
                $perPage = max(5, min(50, (int)($_POST['per_page'] ?? 10)));
                $history = $service->getTravelHistoryPaginated($userId, $page, $perPage);
                driverApiJson([
                    'success' => true,
                    'history' => $history['items'],
                    'pagination' => [
                        'page' => $history['page'],
                        'per_page' => $history['per_page'],
                        'total' => $history['total'],
                        'total_pages' => $history['total_pages'],
                    ],
                ]);
                break;

            default:
                driverApiJson(['success' => false, 'message' => 'Unknown driver action']);
        }
    } catch (Exception $e) {
        driverApiJson(['success' => false, 'message' => $e->getMessage()]);
    }
}
