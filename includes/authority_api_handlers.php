<?php

require_once __DIR__ . '/DriverRouteService.php';

function authorityApiJson($payload) {
    echo json_encode($payload);
    exit;
}

function authorityApiDb(): PDO {
    $database = new Database();
    $db = $database->getConnection();
    if ($db === null) {
        throw new Exception('Database connection failed');
    }
    return $db;
}

function handleAuthorityApiAction(string $action): void {
    $sharedReadActions = ['driver_road_status', 'driver_segments_map'];
    $authorityActions = [
        'authority_home',
        'authority_profile_get',
        'authority_profile_update',
        'authority_upload_avatar',
        'authority_submit_road_reading',
        'authority_mark_road_status',
        'authority_report_accident',
        'authority_report_alert',
        'authority_alerts',
        'authority_ack_alert',
        'authority_resolve_accident',
        'authority_accidents',
        'authority_activity',
        'authority_reports',
    ];

    if (in_array($action, $sharedReadActions, true)) {
        requireLogin();
        if (($_SESSION['user_type'] ?? '') !== 'traffic_authority') {
            authorityApiJson(['success' => false, 'message' => 'Traffic police access only']);
        }
    } elseif (in_array($action, $authorityActions, true)) {
        requireAuthority();
    } else {
        authorityApiJson(['success' => false, 'message' => 'Unknown authority action']);
    }

    $userId = (int)$_SESSION['user_id'];

    try {
        $db = authorityApiDb();
        $service = new DriverRouteService($db);

        switch ($action) {
            case 'authority_home':
                $user = new User($db);
                $user->findById($userId);
                authorityApiJson([
                    'success' => true,
                    'welcome' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                    'stats' => $service->getAuthorityDashboardStats($userId),
                    'recent_activity' => $service->getAuthorityRecentActivity($userId, 8),
                    'recent_updates' => $service->getRecentRoadUpdates(8),
                    'accidents' => $service->getAccidentsList(5, 0),
                    'alerts' => $service->getAuthorityAlerts(5, 0),
                    'map_config' => $service->getRemeraMapConfig(),
                ]);
                break;

            case 'authority_profile_get':
                $user = new User($db);
                if (!$user->findById($userId)) {
                    authorityApiJson(['success' => false, 'message' => 'User not found']);
                }
                authorityApiJson([
                    'success' => true,
                    'profile' => [
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'phone_number' => $user->phone_number,
                        'username' => $user->username,
                        'police_id' => $user->police_id,
                        'profile_picture' => $user->profile_picture,
                    ],
                ]);
                break;

            case 'authority_profile_update':
                $first = trim($_POST['first_name'] ?? '');
                $last = trim($_POST['last_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone_number'] ?? '');
                $normalized = normalizeRwandaPhone($phone);

                if ($first === '' || $last === '') {
                    authorityApiJson(['success' => false, 'message' => 'First and last name are required']);
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    authorityApiJson(['success' => false, 'message' => 'Invalid email address']);
                }
                if ($normalized === false) {
                    authorityApiJson(['success' => false, 'message' => 'Invalid Rwanda phone number']);
                }

                $user = new User($db);
                if (!$user->findById($userId)) {
                    authorityApiJson(['success' => false, 'message' => 'User not found']);
                }
                $user->updateProfile($first, $last, $email, $normalized);

                $_SESSION['user_name'] = $first . ' ' . $last;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_phone'] = $normalized;
                $_SESSION['first_name'] = $first;
                $_SESSION['last_name'] = $last;

                authorityApiJson(['success' => true, 'message' => 'Profile updated successfully']);
                break;

            case 'authority_upload_avatar':
                if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
                    authorityApiJson(['success' => false, 'message' => 'Please select an image to upload']);
                }
                $file = $_FILES['profile_picture'];
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $mime = mime_content_type($file['tmp_name']);
                if (!isset($allowed[$mime])) {
                    authorityApiJson(['success' => false, 'message' => 'Only JPG, PNG, or WEBP images are allowed']);
                }
                if ($file['size'] > 2 * 1024 * 1024) {
                    authorityApiJson(['success' => false, 'message' => 'Image must be under 2MB']);
                }

                $dir = __DIR__ . '/../uploads/profiles';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $filename = 'user_' . $userId . '_' . time() . '.' . $allowed[$mime];
                $dest = $dir . '/' . $filename;
                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    authorityApiJson(['success' => false, 'message' => 'Failed to save image']);
                }

                $publicPath = 'uploads/profiles/' . $filename;
                $user = new User($db);
                $user->findById($userId);
                $user->updateProfilePicture($publicPath);
                $_SESSION['profile_picture'] = $publicPath;

                authorityApiJson(['success' => true, 'message' => 'Profile photo updated', 'profile_picture' => $publicPath]);
                break;

            case 'driver_road_status':
                $segments = $service->enrichSegmentsForDisplay($service->getSegmentsWithStatus());
                authorityApiJson([
                    'success' => true,
                    'segments' => $segments,
                    'summary' => $service->getRoadStatusSummary(),
                    'accidents' => $service->getAccidentsList(10, 0),
                    'issue_roads' => array_values(array_filter($segments, static function (array $seg): bool {
                        return in_array($seg['status'], ['blocked', 'issue'], true)
                            || in_array($seg['congestion_level'], ['high', 'severe'], true);
                    })),
                    'live_meta' => $service->getRoadStatusLiveMeta($segments),
                ]);
                break;

            case 'driver_segments_map':
                authorityApiJson([
                    'success' => true,
                    'segments' => $service->getSegmentsWithStatus(),
                    'landmarks' => $service->getRemeraLandmarks(),
                    'map_config' => $service->getRemeraMapConfig(),
                    'accidents' => $service->getAccidentsList(50, 0),
                    'alerts' => $service->getAuthorityAlerts(50, 0),
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
                authorityApiJson($result);
                break;

            case 'authority_mark_road_status':
                authorityApiJson($service->markRoadStatus($userId, [
                    'segment_id' => $_POST['segment_id'] ?? 0,
                    'road_status' => $_POST['road_status'] ?? '',
                    'congestion_level' => $_POST['congestion_level'] ?? 'low',
                    'status_note' => $_POST['status_note'] ?? '',
                ]));
                break;

            case 'authority_report_accident':
                authorityApiJson($service->reportAccident($userId, $_POST));
                break;

            case 'authority_report_alert':
                authorityApiJson($service->reportTrafficAlert($userId, $_POST));
                break;

            case 'authority_alerts':
                $limit = max(5, min(100, (int)($_POST['limit'] ?? 40)));
                $offset = max(0, (int)($_POST['offset'] ?? 0));
                authorityApiJson([
                    'success' => true,
                    'alerts' => $service->getAuthorityAlerts($limit, $offset),
                    'count' => $service->countUnacknowledgedAlerts(),
                ]);
                break;

            case 'authority_ack_alert':
                authorityApiJson($service->acknowledgeAlert($userId, (int)($_POST['alert_id'] ?? 0)));
                break;

            case 'authority_resolve_accident':
                authorityApiJson($service->resolveAccident(
                    $userId,
                    (int)($_POST['accident_id'] ?? 0),
                    trim($_POST['resolution_note'] ?? '')
                ));
                break;

            case 'authority_accidents':
                $limit = max(5, min(100, (int)($_POST['limit'] ?? 30)));
                $offset = max(0, (int)($_POST['offset'] ?? 0));
                authorityApiJson([
                    'success' => true,
                    'accidents' => $service->getAccidentsList($limit, $offset),
                ]);
                break;

            case 'authority_activity':
                $page = max(1, (int)($_POST['page'] ?? 1));
                $perPage = max(5, min(50, (int)($_POST['per_page'] ?? 15)));
                authorityApiJson([
                    'success' => true,
                    'activity' => $service->getAuthorityActivityPaginated($userId, $page, $perPage),
                ]);
                break;

            case 'authority_reports':
                $period = in_array($_POST['period'] ?? 'daily', ['daily', 'weekly', 'monthly'], true)
                    ? $_POST['period']
                    : 'daily';
                authorityApiJson([
                    'success' => true,
                    'reports' => $service->getAuthorityReports($period),
                ]);
                break;

            default:
                authorityApiJson(['success' => false, 'message' => 'Unknown authority action']);
        }
    } catch (Exception $e) {
        authorityApiJson(['success' => false, 'message' => $e->getMessage()]);
    }
}
