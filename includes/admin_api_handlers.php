<?php

require_once __DIR__ . '/AdminService.php';
require_once __DIR__ . '/sms_helpers.php';

function adminApiJson($payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function adminApiJsonWithSms(array $payload): void
{
    $smsDispatch = $payload['sms_dispatch'] ?? null;
    unset($payload['sms_dispatch']);

    if (!is_array($smsDispatch) || trim((string)($smsDispatch['message'] ?? '')) === '') {
        adminApiJson($payload);
    }

    strgs_finish_json_response($payload, static function () use ($smsDispatch) {
        $conn = adminApiDb();
        strgs_send_announcement_sms_to_users(
            $conn,
            (string)($smsDispatch['title'] ?? ''),
            (string)$smsDispatch['message'],
            !empty($smsDispatch['emergency'])
        );
    });
}

function adminApiDb(): PDO
{
    $database = new Database();
    $db = $database->getConnection();
    if ($db === null) {
        throw new Exception('Database connection failed');
    }
    return $db;
}

function handleAdminApiAction(string $action): void
{
    requireAdmin();

    $userId = (int)$_SESSION['user_id'];

    try {
        $db = adminApiDb();
        $service = new AdminService($db);

        switch ($action) {
            case 'admin_home':
                adminApiJson(['success' => true, 'home' => $service->getDashboardHome($userId)]);
                break;

            case 'admin_users':
                $result = $service->listUsers(
                    trim($_POST['search'] ?? ''),
                    trim($_POST['role'] ?? ''),
                    trim($_POST['status'] ?? ''),
                    max(1, (int)($_POST['page'] ?? 1)),
                    max(5, min(50, (int)($_POST['per_page'] ?? 15)))
                );
                adminApiJson(['success' => true] + $result);
                break;

            case 'admin_create_user':
                adminApiJson($service->createUser($userId, $_POST));
                break;

            case 'admin_update_user':
                adminApiJson($service->updateUser($userId, (int)($_POST['user_id'] ?? 0), $_POST));
                break;

            case 'admin_toggle_user':
                adminApiJson($service->setUserActive(
                    $userId,
                    (int)($_POST['user_id'] ?? 0),
                    (int)($_POST['is_active'] ?? 0) === 1
                ));
                break;

            case 'admin_delete_user':
                adminApiJson($service->deleteUser($userId, (int)($_POST['user_id'] ?? 0)));
                break;

            case 'admin_reset_password':
                adminApiJson($service->resetUserPassword(
                    $userId,
                    (int)($_POST['user_id'] ?? 0),
                    (string)($_POST['new_password'] ?? '')
                ));
                break;

            case 'admin_traffic_monitoring':
                adminApiJson([
                    'success' => true,
                    'monitoring' => $service->getTrafficMonitoring(
                        trim($_POST['tab'] ?? 'road-status'),
                        max(10, min(100, (int)($_POST['limit'] ?? 30)))
                    ),
                ]);
                break;

            case 'admin_get_settings':
                adminApiJson(['success' => true, 'settings' => $service->getSystemSettings()]);
                break;

            case 'admin_save_settings':
                adminApiJson($service->saveSystemSettings($userId, $_POST));
                break;

            case 'admin_announcements':
                adminApiJson([
                    'success' => true,
                    'announcements' => $service->getAnnouncements(max(5, min(100, (int)($_POST['limit'] ?? 30)))),
                ]);
                break;

            case 'admin_send_announcement':
                adminApiJsonWithSms($service->sendAnnouncement($userId, $_POST));
                break;

            case 'admin_activity_logs':
                $result = $service->getActivityLogs(
                    trim($_POST['type'] ?? 'all'),
                    max(1, (int)($_POST['page'] ?? 1)),
                    max(10, min(50, (int)($_POST['per_page'] ?? 20)))
                );
                adminApiJson(['success' => true] + $result);
                break;

            case 'admin_login_history':
                adminApiJson([
                    'success' => true,
                    'logins' => $service->getLoginHistory(max(5, min(100, (int)($_POST['limit'] ?? 30)))),
                ]);
                break;

            case 'admin_profile_get':
                $profile = $service->getProfile($userId);
                adminApiJson($profile ? ['success' => true, 'profile' => $profile] : ['success' => false, 'message' => 'Profile not found']);
                break;

            case 'admin_profile_update':
                $user = new User($db);
                if (!$user->findById($userId)) {
                    adminApiJson(['success' => false, 'message' => 'User not found']);
                }
                $first = trim($_POST['first_name'] ?? '');
                $last = trim($_POST['last_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone_number'] ?? '');
                if ($first === '' || $last === '') {
                    adminApiJson(['success' => false, 'message' => 'First and last name are required']);
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    adminApiJson(['success' => false, 'message' => 'Invalid email']);
                }
                $user->updateProfile($first, $last, $email, $phone);
                $_SESSION['user_name'] = $first . ' ' . $last;
                $_SESSION['first_name'] = $first;
                $_SESSION['user_email'] = $email;
                adminApiJson(['success' => true, 'message' => 'Profile updated successfully']);
                break;

            case 'admin_upload_avatar':
                if (empty($_FILES['profile_picture']['tmp_name'])) {
                    adminApiJson(['success' => false, 'message' => 'Please select an image']);
                }
                $file = $_FILES['profile_picture'];
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                if (!in_array($file['type'], $allowed, true)) {
                    adminApiJson(['success' => false, 'message' => 'Only JPG, PNG, or WEBP allowed']);
                }
                if ($file['size'] > 2 * 1024 * 1024) {
                    adminApiJson(['success' => false, 'message' => 'Image must be under 2MB']);
                }
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
                $dir = dirname(__DIR__) . '/uploads/profiles';
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                $filename = 'admin_' . $userId . '_' . time() . '.' . $ext;
                $path = $dir . '/' . $filename;
                if (!move_uploaded_file($file['tmp_name'], $path)) {
                    adminApiJson(['success' => false, 'message' => 'Failed to save image']);
                }
                $public = 'uploads/profiles/' . $filename;
                $user = new User($db);
                $user->findById($userId);
                $user->updateProfilePicture($public);
                $_SESSION['profile_picture'] = $public;
                adminApiJson(['success' => true, 'message' => 'Profile photo updated', 'profile_picture' => $public]);
                break;

            default:
                adminApiJson(['success' => false, 'message' => 'Unknown admin action']);
        }
    } catch (Exception $e) {
        adminApiJson(['success' => false, 'message' => $e->getMessage()]);
    }
}
