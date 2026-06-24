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
    requireDriver();
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
                    'notifications' => $service->getDriverAlerts(5),
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
                $segments = $service->getSegmentsWithStatus();
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
                ]);
                break;

            case 'driver_alerts':
                driverApiJson([
                    'success' => true,
                    'alerts' => $service->getDriverAlerts(30),
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
                $originLabel = trim($_POST['origin_label'] ?? 'Current location');
                $save = ($_POST['save_history'] ?? '1') === '1';

                if (!$originLat || !$originLng || !$destLat || !$destLng) {
                    driverApiJson(['success' => false, 'message' => 'Origin and destination coordinates are required']);
                }

                $result = $service->recommendRoutes($originLat, $originLng, $destLat, $destLng, $destLabel);
                if (empty($result['routes'])) {
                    driverApiJson(['success' => false, 'message' => 'No route could be calculated. Try a destination within Remera Sector.']);
                }

                if ($save && !empty($result['routes'][0])) {
                    $service->saveRouteRequest($userId, $result['routes'][0], [
                        'origin_label' => $originLabel,
                        'origin_lat' => $originLat,
                        'origin_lng' => $originLng,
                        'destination_label' => $destLabel,
                        'destination_lat' => $destLat,
                        'destination_lng' => $destLng,
                    ]);
                }

                driverApiJson(['success' => true, 'guidance' => $result]);
                break;

            case 'driver_travel_history':
                driverApiJson([
                    'success' => true,
                    'history' => $service->getTravelHistory($userId, 40),
                ]);
                break;

            default:
                driverApiJson(['success' => false, 'message' => 'Unknown driver action']);
        }
    } catch (Exception $e) {
        driverApiJson(['success' => false, 'message' => $e->getMessage()]);
    }
}
