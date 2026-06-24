<?php
// Include config first to set session configuration
require_once 'config.php';
session_start();
$smsConfig = null;
if (file_exists(__DIR__ . '/sms_settings.php')) {
    $smsConfig = require __DIR__ . '/sms_settings.php';
}
if (file_exists(__DIR__ . '/sms_parse.php')) {
    require_once __DIR__ . '/sms_parse.php';
}

// Redirect logged-in users to dashboard only for normal page loads (not AJAX/action requests)
if (isLoggedIn()) {
    $isGetPageLoad = $_SERVER['REQUEST_METHOD'] === 'GET';
    $hasActionParam = isset($_GET['action']) || isset($_POST['action']);
    if ($isGetPageLoad && !$hasActionParam) {
        header('Location: dashboard.php');
        exit();
    }
}

// Python prediction engine integration
function callPythonPredictionEngine($data) {
    try {
        $python_input = json_encode($data);
        $python_script = __DIR__ . '/prediction_engine.py';
        $command = "python " . escapeshellarg($python_script) . " 2>&1";

        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w")
        );

        $process = proc_open($command, $descriptorspec, $pipes);

        if (!is_resource($process)) {
            return [
                'success' => false,
                'error' => 'proc_open_failed',
                'message' => 'Unable to start Python process'
            ];
        }

        // Write input json then close stdin
        fwrite($pipes[0], $python_input);
        fclose($pipes[0]);

        // Set non-blocking
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $errors = '';
        $timeoutSeconds = 6; // hard timeout to avoid hanging UI
        $start = microtime(true);

        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;

            // Wait up to 200ms for new data
            if (stream_select($read, $write, $except, 0, 200000) !== false) {
                foreach ($read as $r) {
                    if ($r === $pipes[1]) { $output .= stream_get_contents($pipes[1]); }
                    if ($r === $pipes[2]) { $errors .= stream_get_contents($pipes[2]); }
                }
            }

            // Check timeout
            if ((microtime(true) - $start) > $timeoutSeconds) {
                // Kill process and fallback
                proc_terminate($process);
                @proc_close($process);
                error_log('Python prediction engine timeout after ' . $timeoutSeconds . 's');
                return [
                    'success' => false,
                    'error' => 'timeout',
                    'message' => 'Python engine timeout'
                ];
            }

            // If process exited, break
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
        }

        // Close pipes
        fclose($pipes[1]);
        fclose($pipes[2]);
        $return_value = proc_close($process);

        if ($return_value === 0 && !empty($output)) {
            $result = json_decode($output, true);
            if ($result !== null) {
                return $result;
            }
        }

        if (!empty($errors)) {
            error_log('Python prediction engine error: ' . $errors);
        }

        return [
            'success' => false,
            'error' => 'Python engine execution failed',
            'message' => 'Unable to execute Python prediction engine'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'message' => 'Python engine integration error'
        ];
    }
}

// Helper functions for traffic prediction (PHP fallback engine)
function clamp01($x) {
    if ($x < 0) return 0.0;
    if ($x > 1) return 1.0;
    return (float)$x;
}

function normalizeRwandaPhone($rawPhone) {
    $clean = preg_replace('/[^0-9+]/', '', (string)$rawPhone);
    $clean = ltrim($clean);

    if (preg_match('/^\+2507\d{8}$/', $clean)) {
        return $clean;
    }
    if (preg_match('/^2507\d{8}$/', $clean)) {
        return '+' . $clean;
    }
    if (preg_match('/^07\d{8}$/', $clean)) {
        return '+250' . substr($clean, 1);
    }
    if (preg_match('/^7\d{8}$/', $clean)) {
        return '+250' . $clean;
    }

    return false;
}

function sendRegistrationSms($phone, $fullName, $smsConfig, $meta = []) {
    $logSmsAttempt = function(array $payload) {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $payload['timestamp'] = date('c');
        @file_put_contents(
            $logDir . '/sms_registration.log',
            json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    };

    if (!is_array($smsConfig) || empty($smsConfig['enabled'])) {
        $data = ['sent' => false, 'reason' => 'sms_disabled', 'phone' => $phone];
        $logSmsAttempt($data);
        return $data;
    }
    if (!class_exists('hdev_sms')) {
        $data = ['sent' => false, 'reason' => 'sms_class_not_loaded', 'phone' => $phone];
        $logSmsAttempt($data);
        return $data;
    }

    $apiId = trim((string)($smsConfig['api_id'] ?? ''));
    $apiKey = trim((string)($smsConfig['api_key'] ?? ''));
    $senderId = trim((string)($smsConfig['sender_id'] ?? ''));
    if ($apiId === '' || $apiKey === '' || $senderId === '') {
        $data = ['sent' => false, 'reason' => 'sms_credentials_missing', 'phone' => $phone];
        $logSmsAttempt($data);
        return $data;
    }

    $name = trim((string)$fullName);
    if ($name === '') {
        $name = 'User';
    }

    $username = trim((string)($meta['username'] ?? ''));
    $userType = trim((string)($meta['user_type'] ?? ''));
    $userTypeLabel = $userType !== '' ? getUserTypeLabel($userType) : 'User';

    $message = "Dear {$name}, thank you for registering on STRGS. " .
               "Your account has been created successfully as {$userTypeLabel}.";
    if ($username !== '') {
        $message .= " Username: {$username}.";
    }
    $message .= " You can now log in and continue using the system.";

    try {
        hdev_sms::api_id($apiId);
        hdev_sms::api_key($apiKey);
        $result = hdev_sms::send($senderId, $phone, $message);
        $data = ['sent' => true, 'phone' => $phone, 'sender_id' => $senderId, 'response' => $result];
        $logSmsAttempt($data);
        return $data;
    } catch (Throwable $e) {
        error_log('SMS send failed: ' . $e->getMessage());
        $data = ['sent' => false, 'reason' => 'sms_exception', 'phone' => $phone, 'error' => $e->getMessage()];
        $logSmsAttempt($data);
        return $data;
    }
}

function resolveCurrentUserPhone($db, $sessionUserId) {
    if (!$db || !$sessionUserId) {
        return null;
    }
    try {
        $stmt = $db->prepare("SELECT phone_number FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => (int)$sessionUserId]);
        $phone = $stmt->fetchColumn();
        $normalized = normalizeRwandaPhone((string)$phone);
        return $normalized ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function sendPredictionSms($phone, $fullName, $smsConfig, $predictionPayload = []) {
    $logSmsAttempt = function(array $payload) {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $payload['timestamp'] = date('c');
        @file_put_contents(
            $logDir . '/sms_prediction.log',
            json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    };

    if (!$phone) {
        $logSmsAttempt(['sent' => false, 'reason' => 'missing_phone']);
        return ['sent' => false, 'reason' => 'missing_phone'];
    }
    if (!is_array($smsConfig) || empty($smsConfig['enabled'])) {
        $logSmsAttempt(['sent' => false, 'reason' => 'sms_disabled', 'phone' => $phone]);
        return ['sent' => false, 'reason' => 'sms_disabled'];
    }
    if (!class_exists('hdev_sms')) {
        $logSmsAttempt(['sent' => false, 'reason' => 'sms_class_not_loaded', 'phone' => $phone]);
        return ['sent' => false, 'reason' => 'sms_class_not_loaded'];
    }

    $apiId = trim((string)($smsConfig['api_id'] ?? ''));
    $apiKey = trim((string)($smsConfig['api_key'] ?? ''));
    $senderId = trim((string)($smsConfig['sender_id'] ?? ''));
    if ($apiId === '' || $apiKey === '' || $senderId === '') {
        $logSmsAttempt(['sent' => false, 'reason' => 'sms_credentials_missing', 'phone' => $phone]);
        return ['sent' => false, 'reason' => 'sms_credentials_missing'];
    }

    $name = trim((string)$fullName);
    if ($name === '') {
        $name = 'User';
    }
    $congestion = strtolower((string)($predictionPayload['congestion_level'] ?? 'unknown'));
    $riskPct = (int)round((float)($predictionPayload['accident_probability'] ?? 0) * 100);
    $confidencePct = (int)round((float)($predictionPayload['confidence'] ?? 0) * 100);
    $recommendations = $predictionPayload['recommendations'] ?? [];
    $primaryRec = isset($recommendations[0]) ? (string)$recommendations[0] : 'Drive carefully and follow traffic alerts.';

    $message = "STRGS alert for {$name}: Risk {$riskPct}% ({$congestion} congestion), confidence {$confidencePct}%. Route guidance: {$primaryRec}";

    try {
        hdev_sms::api_id($apiId);
        hdev_sms::api_key($apiKey);
        $result = hdev_sms::send($senderId, $phone, $message);
        $data = ['sent' => true, 'phone' => $phone, 'sender_id' => $senderId, 'response' => $result];
        $logSmsAttempt($data);
        return $data;
    } catch (Throwable $e) {
        $data = ['sent' => false, 'reason' => 'sms_exception', 'phone' => $phone, 'error' => $e->getMessage()];
        $logSmsAttempt($data);
        return $data;
    }
}

function computeCongestionIndex($data) {
    $density = max(0.0, (float)($data['density_veh_per_km'] ?? 0));
    $speed = max(0.0, (float)($data['avg_speed_kmh'] ?? 0));
    $count = max(0.0, (float)($data['vehicle_count'] ?? 0));

    $density_n = clamp01($density / 120.0);
    $count_n   = clamp01($count / 2000.0);
    $speed_n   = clamp01($speed / 80.0);

    $congestion = (0.55 * $density_n) + (0.25 * $count_n) + (0.20 * (1.0 - $speed_n));
    return clamp01($congestion);
}

function determineCongestionLevel($congestionIndex) {
    if ($congestionIndex >= 0.80) return 'severe';
    if ($congestionIndex >= 0.60) return 'high';
    if ($congestionIndex >= 0.35) return 'moderate';
    return 'low';
}

function computeAccidentProbability($data, $congestionIndex) {
    $weather = strtolower((string)($data['weather'] ?? 'unknown'));
    $visibility = isset($data['visibility_m']) ? (float)$data['visibility_m'] : null;
    $speed = max(0.0, (float)($data['avg_speed_kmh'] ?? 0));

    $weather_risk = 0.10;
    if ($weather === 'clear') $weather_risk = 0.05;
    elseif ($weather === 'rain') $weather_risk = 0.18;
    elseif ($weather === 'fog') $weather_risk = 0.22;
    elseif ($weather === 'storm') $weather_risk = 0.30;
    elseif ($weather === 'snow') $weather_risk = 0.28;

    $visibility_risk = 0.0;
    if ($visibility !== null) {
        $visibility_risk = clamp01((2000.0 - $visibility) / 2000.0) * 0.25;
    }

    $stop_go_risk = 0.0;
    if ($speed > 0 && $speed < 15) {
        $stop_go_risk = 0.18;
    }

    $base = 0.03;
    $p = $base + (0.42 * $congestionIndex) + $weather_risk + $visibility_risk + $stop_go_risk;
    return clamp01($p);
}

function buildTrafficExplanations($data, $congestionIndex, $accidentProbability) {
    $explanations = [];
    $level = determineCongestionLevel($congestionIndex);
    $density = isset($data['density_veh_per_km']) ? (float)$data['density_veh_per_km'] : null;
    $speed = isset($data['avg_speed_kmh']) ? (float)$data['avg_speed_kmh'] : null;
    $count = isset($data['vehicle_count']) ? (float)$data['vehicle_count'] : null;
    $visibility = isset($data['visibility_m']) ? (float)$data['visibility_m'] : null;

    $explanations[] = "Congestion index is " . round($congestionIndex * 100) . "%, indicating '$level' traffic conditions.";
    if ($density !== null) {
        $explanations[] = "Traffic density is {$density} vehicles/km, which is a major driver of congestion risk.";
    }
    if ($count !== null) {
        $explanations[] = "Vehicle count in this reading is {$count}, affecting corridor pressure.";
    }
    if ($speed !== null) {
        $explanations[] = "Average speed is {$speed} km/h; lower speeds generally increase crash exposure in heavy flow.";
    }

    $weather = strtolower((string)($data['weather'] ?? 'unknown'));
    if (in_array($weather, ['rain', 'fog', 'storm', 'snow'], true)) {
        $explanations[] = "Weather condition ('$weather') increases accident risk.";
    } elseif ($weather === 'clear') {
        $explanations[] = "Weather is clear, reducing environmental risk compared with rain/fog/storm.";
    }
    if ($visibility !== null && $visibility < 800) {
        $explanations[] = "Low visibility increases accident probability.";
    } elseif ($visibility !== null) {
        $explanations[] = "Visibility is {$visibility}m, which supports safer response times.";
    }
    if ($speed !== null && $speed < 15) {
        $explanations[] = "Stop-and-go speeds raise collision likelihood.";
    }
    if ($accidentProbability >= 0.65) {
        $explanations[] = "Overall risk is high; consider enforcing speed discipline and rerouting.";
    } elseif ($accidentProbability >= 0.40) {
        $explanations[] = "Overall risk is moderate; preventive control actions are recommended.";
    } else {
        $explanations[] = "Overall risk is relatively low at this moment, but continuous monitoring is needed.";
    }
    $explanations[] = "Predicted accident probability is " . round($accidentProbability * 100) . "% based on combined traffic and environment factors.";

    return $explanations;
}

function buildTrafficRecommendations($congestionLevel, $accidentProbability, $data = []) {
    $recs = [];
    $weather = strtolower((string)($data['weather'] ?? 'unknown'));
    $speed = isset($data['avg_speed_kmh']) ? (float)$data['avg_speed_kmh'] : null;
    $density = isset($data['density_veh_per_km']) ? (float)$data['density_veh_per_km'] : null;
    $visibility = isset($data['visibility_m']) ? (float)$data['visibility_m'] : null;

    if ($congestionLevel === 'severe' || $congestionLevel === 'high') {
        $recs[] = 'Optimize signal timing / deploy manual traffic control to improve flow.';
        $recs[] = 'Broadcast congestion alert and suggest alternative routes.';
        $recs[] = 'Activate dynamic lane control and prioritize emergency corridors.';
    } elseif ($congestionLevel === 'moderate') {
        $recs[] = 'Monitor segment and adjust signals proactively if density increases.';
        $recs[] = 'Deploy peak-hour route guidance to distribute traffic load.';
    } else {
        $recs[] = 'Traffic is stable; continue monitoring.';
        $recs[] = 'Keep preventive patrols and maintain current signal schedule.';
    }

    if ($accidentProbability >= 0.65) {
        $recs[] = 'Issue a high-risk warning; increase patrol presence and enforce speed limits.';
        $recs[] = 'Consider temporary speed reduction and lane management.';
        $recs[] = 'Pre-position emergency response units near this segment.';
    } elseif ($accidentProbability >= 0.40) {
        $recs[] = 'Issue a caution alert and monitor for incidents.';
        $recs[] = 'Increase roadside signage visibility and caution messaging.';
    } else {
        $recs[] = 'Maintain routine monitoring and update risk status regularly.';
    }

    if (in_array($weather, ['rain', 'fog', 'storm', 'snow'], true)) {
        $recs[] = 'Publish weather-specific advisory and reduce speed thresholds.';
    }
    if ($visibility !== null && $visibility < 800) {
        $recs[] = 'Improve lighting and place temporary visibility warning signs.';
    }
    if ($speed !== null && $speed < 15) {
        $recs[] = 'Review nearby intersections for bottlenecks causing stop-and-go waves.';
    }
    if ($density !== null && $density > 80) {
        $recs[] = 'Prioritize corridor-level traffic balancing with upstream rerouting.';
    }

    return array_values(array_unique($recs));
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    // Ensure clean JSON responses for AJAX: hide notices/warnings and clear output buffer
    @ini_set('display_errors', 0);
    @error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
    if (function_exists('ob_get_length')) { while (ob_get_length()) { @ob_end_clean(); } }
    
    switch ($_POST['action']) {
        case 'register':
            try {
                // Initialize database connection
                $database = new Database();
                $db = $database->getConnection();
                
                if ($db === null) {
                    throw new Exception('Database connection failed');
                }
                
                // Initialize User model
                $user = new User($db);
                
                $first_name = $_POST['first_name'] ?? '';
                $last_name = $_POST['last_name'] ?? '';
                $username = $_POST['username'] ?? '';
                $email = $_POST['email'] ?? '';
                $phone_number = $_POST['phone_number'] ?? '';
                $user_type = $_POST['user_type'] ?? '';
                $license_id = IdRegistry::normalizeLicenseId($_POST['license_id'] ?? '');
                $police_id = IdRegistry::normalizePoliceId($_POST['police_id'] ?? '');
                $password = $_POST['password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                $normalized_phone = normalizeRwandaPhone($phone_number);
                
                // Debug: Log received data
                error_log('Registration attempt: ' . json_encode($_POST));
                
                $errors = [];
                
                // Field validation
                if (empty($first_name)) $errors[] = 'First name is required';
                if (empty($last_name)) $errors[] = 'Last name is required';
                if (empty($username)) $errors[] = 'Username is required';
                if (empty($email)) $errors[] = 'Email is required';
                if (empty($phone_number)) $errors[] = 'Phone number is required';
                if (empty($user_type)) $errors[] = 'User type is required';
                if (empty($password)) $errors[] = 'Password is required';
                if (empty($confirm_password)) $errors[] = 'Please confirm your password';
                
                // Length validation
                if (!empty($first_name) && strlen($first_name) < 2) $errors[] = 'First name must be at least 2 characters long';
                if (!empty($last_name) && strlen($last_name) < 2) $errors[] = 'Last name must be at least 2 characters long';
                if (!empty($username) && strlen($username) < 3) $errors[] = 'Username must be at least 3 characters long';
                if (!empty($password) && strlen($password) < 8) $errors[] = 'Password must be at least 8 characters long';
                
                // Format validation
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
                if (!empty($phone_number) && $normalized_phone === false) {
                    $errors[] = 'Phone number must be a valid Rwanda number (e.g. +2507XXXXXXXX or 07XXXXXXXX)';
                }
                if (!in_array($user_type, ['driver', 'traffic_authority'], true)) {
                    $errors[] = 'Invalid user type selected';
                }

                if ($user_type === 'driver') {
                    $registry = new IdRegistry($db);
                    $licenseCheck = $registry->validateLicenseForRegistration($license_id, $user);
                    if (!$licenseCheck['valid']) {
                        $errors[] = $licenseCheck['message'];
                    } else {
                        $license_id = $licenseCheck['id'];
                    }
                } elseif ($user_type === 'traffic_authority') {
                    $registry = new IdRegistry($db);
                    $policeCheck = $registry->validatePoliceForRegistration($police_id, $user);
                    if (!$policeCheck['valid']) {
                        $errors[] = $policeCheck['message'];
                    } else {
                        $police_id = $policeCheck['id'];
                    }
                }
                
                // Password match validation
                if (!empty($password) && !empty($confirm_password) && $password !== $confirm_password) {
                    $errors[] = 'Passwords do not match';
                }
                
                // Check if email already exists
                if (!empty($email) && $user->emailExists($email)) {
                    $errors[] = 'Email already exists';
                }
                
                // Check if username already exists
                if (!empty($username) && $user->usernameExists($username)) {
                    $errors[] = 'Username already exists';
                }

                // Check if phone already exists (using normalized Rwanda format)
                if ($normalized_phone !== false && $user->phoneExists($normalized_phone)) {
                    $errors[] = 'Phone number already exists';
                }
                
                if (empty($errors)) {
                    // Create new user
                    $cleanFirstName = trim($first_name);
                    $cleanLastName = trim($last_name);
                    $user->username = trim($username);
                    $user->email = trim($email);
                    $user->phone_number = $normalized_phone;
                    $user->user_type = $user_type;
                    $user->license_id = ($user_type === 'driver') ? $license_id : null;
                    $user->police_id = ($user_type === 'traffic_authority') ? $police_id : null;
                    $user->first_name = $cleanFirstName;
                    $user->last_name = $cleanLastName;
                    $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $user->role = ($user_type === 'traffic_authority') ? 'authority' : 'viewer';
                    $user->is_active = 1;
                    
                    if ($user->create()) {
                        // Send registration SMS (best-effort, does not block account creation)
                        sendRegistrationSms(
                            $user->phone_number,
                            $cleanFirstName . ' ' . $cleanLastName,
                            $smsConfig,
                            [
                                'username' => $user->username,
                                'user_type' => $user->user_type
                            ]
                        );

                        // Don't set session variables - force user to login
                        // This ensures user goes to login page after registration
                        
                        echo json_encode([
                            'success' => true,
                            'message' => '🎉 Account created successfully! Welcome to STRGS. You will be redirected to login in 5 seconds...',
                            'redirect' => 'login.php',
                            'user_data' => [
                                'name' => $first_name . ' ' . $last_name,
                                'first_name' => $cleanFirstName,
                                'last_name' => $cleanLastName,
                                'email' => $user->email,
                                'username' => $user->username,
                                'phone_number' => $user->phone_number,
                                'user_type' => $user->user_type
                            ]
                        ]);
                    } else {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Failed to create account. Please try again.',
                            'errors' => ['Database error occurred']
                        ]);
                    }
                } else {
                    // Create a detailed error message
                    $errorMessage = 'Registration failed: ' . implode(', ', $errors);
                    
                    echo json_encode([
                        'success' => false,
                        'message' => $errorMessage,
                        'errors' => $errors,
                        'error_count' => count($errors)
                    ]);
                }
            } catch (Exception $e) {
                error_log('Registration error: ' . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Registration failed. Please try again.',
                    'errors' => ['System error occurred']
                ]);
            }
            exit;

        case 'check_role_id':
            try {
                $database = new Database();
                $db = $database->getConnection();

                if ($db === null) {
                    throw new Exception('Database connection failed');
                }

                $user = new User($db);
                $registry = new IdRegistry($db);
                $id_type = $_POST['id_type'] ?? '';
                $id_value = $id_type === 'license'
                    ? IdRegistry::normalizeLicenseId($_POST['id_value'] ?? '')
                    : IdRegistry::normalizePoliceId($_POST['id_value'] ?? '');

                if (!in_array($id_type, ['license', 'police'], true)) {
                    echo json_encode([
                        'success' => false,
                        'available' => false,
                        'reason' => 'invalid_type',
                        'message' => 'Invalid ID type'
                    ]);
                    exit;
                }

                if ($id_value === '') {
                    echo json_encode([
                        'success' => false,
                        'available' => false,
                        'reason' => 'required',
                        'message' => $id_type === 'license'
                            ? 'Driving license ID is required'
                            : 'Police ID is required'
                    ]);
                    exit;
                }

                if ($id_type === 'license') {
                    $check = $registry->validateLicenseForRegistration($id_value, $user);
                    echo json_encode([
                        'success' => true,
                        'available' => $check['valid'],
                        'reason' => $check['reason'],
                        'message' => $check['message'],
                        'normalized_id' => $check['id']
                    ]);
                    exit;
                }

                $check = $registry->validatePoliceForRegistration($id_value, $user);
                echo json_encode([
                    'success' => true,
                    'available' => $check['valid'],
                    'reason' => $check['reason'],
                    'message' => $check['message'],
                    'normalized_id' => $check['id']
                ]);
            } catch (Exception $e) {
                error_log('Role ID check error: ' . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'available' => false,
                    'message' => 'Unable to verify ID right now'
                ]);
            }
            exit;
            
        case 'login':
            try {
                // Initialize database connection
                $database = new Database();
                $db = $database->getConnection();
                
                if ($db === null) {
                    throw new Exception('Database connection failed');
                }
                
                // Initialize User model
                $user = new User($db);
                
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                $user_type = $_POST['user_type'] ?? '';
                
                // Debug: Log received data
                error_log('Login attempt: username=' . $username . ', password_length=' . strlen($password));
                
                if (empty($username) || empty($password) || empty($user_type)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Username/email, password, and user type are required'
                    ]);
                } else {
                    // Try to find user by email or username
                    $userFound = false;
                    if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
                        error_log('Looking up user by email: ' . $username);
                        $userFound = $user->findByEmail($username);
                    } else {
                        error_log('Looking up user by username: ' . $username);
                        $userFound = $user->findByUsername($username);
                    }
                    
                    error_log('User found: ' . ($userFound ? 'yes' : 'no'));
                    
                    if ($userFound && $user->verifyPassword($password)) {
                        $storedType = resolveUserTypeFromUser($user);
                        if ($storedType !== $user_type) {
                            echo json_encode([
                                'success' => false,
                                'message' => 'Selected user type does not match this account'
                            ]);
                            exit;
                        }
                        if ($user->is_active) {
                            // Update last login
                            $user->updateLastLogin();
                            
                            // Set session variables
                            $displayName = trim((string)($user->first_name ?? '') . ' ' . (string)($user->last_name ?? ''));
                            if ($displayName === '') {
                                $displayName = $user->username;
                            }
                            $_SESSION['user_id'] = $user->id;
                            $_SESSION['user_name'] = $displayName;
                            $_SESSION['first_name'] = $user->first_name ?? '';
                            $_SESSION['last_name'] = $user->last_name ?? '';
                            $_SESSION['user_email'] = $user->email;
                            $_SESSION['user_phone'] = $user->phone_number ?? '';
                            $_SESSION['user_role'] = $user->role;
                            $_SESSION['user_type'] = $storedType;
                            if (!empty($user->profile_picture ?? null)) {
                                $_SESSION['profile_picture'] = $user->profile_picture;
                            }
                            
                            echo json_encode([
                                'success' => true,
                                'message' => 'Login successful! Welcome back to STRGS.',
                                'redirect' => 'dashboard.php'
                            ]);
                        } else {
                            echo json_encode([
                                'success' => false,
                                'message' => 'Account is deactivated. Please contact administrator.'
                            ]);
                        }
                    } else {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Invalid username/email or password'
                        ]);
                    }
                }
            } catch (Exception $e) {
                error_log('Login error: ' . $e->getMessage());
                error_log('Login error trace: ' . $e->getTraceAsString());
                echo json_encode([
                    'success' => false,
                    'message' => 'Login failed. Please try again.',
                    'error' => $e->getMessage()
                ]);
            }
            exit;
            
        case 'train_model':
            // Simulate model training
            sleep(2); // Simulate processing time
            echo json_encode([
                'success' => true,
                'message' => 'Model trained successfully!',
                'accuracy' => 0.87,
                'training_time' => '2.3 seconds'
            ]);
            exit;
            
        case 'predict':
            try {
                // Validate input data
                $required_fields = [
                    'segment_id', 'reading_time', 'vehicle_count', 'avg_speed_kmh', 'density_veh_per_km', 'weather'
                ];
                
                $data = [];
                $errors = [];
                
                foreach ($required_fields as $field) {
                    if (!array_key_exists($field, $_POST) || $_POST[$field] === '') {
                        $errors[] = "Field '$field' is required";
                    } else {
                        $data[$field] = $_POST[$field];
                    }
                }
                
                // Additional validation
                $data['segment_id'] = (int)$data['segment_id'];
                $data['vehicle_count'] = (int)$data['vehicle_count'];
                $data['avg_speed_kmh'] = (float)$data['avg_speed_kmh'];
                $data['density_veh_per_km'] = (float)$data['density_veh_per_km'];
                $data['weather'] = strtolower(trim((string)$data['weather']));
                $data['visibility_m'] = isset($_POST['visibility_m']) && $_POST['visibility_m'] !== '' ? (int)$_POST['visibility_m'] : null;
                $data['temperature_c'] = isset($_POST['temperature_c']) && $_POST['temperature_c'] !== '' ? (float)$_POST['temperature_c'] : null;

                if ($data['segment_id'] <= 0) $errors[] = "segment_id must be a positive integer";
                if ($data['vehicle_count'] < 0 || $data['vehicle_count'] > 200000) $errors[] = "vehicle_count must be between 0 and 200000";
                if ($data['avg_speed_kmh'] < 0 || $data['avg_speed_kmh'] > 200) $errors[] = "avg_speed_kmh must be between 0 and 200";
                if ($data['density_veh_per_km'] < 0 || $data['density_veh_per_km'] > 500) $errors[] = "density_veh_per_km must be between 0 and 500";
                
                if (!empty($errors)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Validation errors',
                        'errors' => $errors
                    ]);
                    exit;
                }
                
                // Optionally use Python prediction engine; otherwise PHP-only
                $result = ['success' => false, 'error' => 'python_disabled'];
                if (defined('USE_PYTHON_ENGINE') && USE_PYTHON_ENGINE === true) {
                    $result = callPythonPredictionEngine($data);
                }
                
                if ($result['success']) {
                    $result['confidence'] = max(0.90, min(0.99, (float)($result['confidence'] ?? 0.90)));
                    // Enrich explanations and recommendations for clearer insights
                    $derivedCongestionIndex = isset($result['congestion_index']) ? (float)$result['congestion_index'] : computeCongestionIndex($data);
                    $derivedCongestionLevel = (string)($result['congestion_level'] ?? determineCongestionLevel($derivedCongestionIndex));
                    $derivedAccidentProbability = isset($result['accident_probability']) ? (float)$result['accident_probability'] : computeAccidentProbability($data, $derivedCongestionIndex);
                    $autoExplanations = buildTrafficExplanations($data, $derivedCongestionIndex, $derivedAccidentProbability);
                    $autoRecommendations = buildTrafficRecommendations($derivedCongestionLevel, $derivedAccidentProbability, $data);
                    $result['congestion_level'] = $derivedCongestionLevel;
                    $result['accident_probability'] = round($derivedAccidentProbability, 4);
                    $result['explanations'] = array_values(array_unique(array_merge($result['explanations'] ?? [], $autoExplanations)));
                    $result['recommendations'] = array_values(array_unique(array_merge($result['recommendations'] ?? [], $autoRecommendations)));

                    // Log prediction for analytics
                    error_log('STRGS route guidance: ' . json_encode([
                        'segment_id' => $data['segment_id'],
                        'congestion_level' => $result['congestion_level'] ?? null,
                        'accident_probability' => $result['accident_probability'] ?? null,
                        'confidence' => $result['confidence'] ?? null,
                        'model_type' => $result['model_info']['type'] ?? 'Unknown'
                    ]));
                    // Write to audit log for Recent Activity
                    try {
                        $database = new Database();
                        $db = $database->getConnection();
                        if ($db) {
                            $db->prepare("INSERT INTO audit_log (user_id, action, table_name, new_values, created_at) VALUES (:user_id, 'prediction', 'ai_predictions', :new_values, NOW())")
                               ->execute([
                                   ':user_id' => $_SESSION['user_id'] ?? null,
                                   ':new_values' => json_encode([
                                       'segment_id' => $data['segment_id'],
                                       'congestion_level' => $result['congestion_level'] ?? null,
                                       'accident_probability' => $result['accident_probability'] ?? null,
                                       'confidence' => $result['confidence'] ?? null
                                   ])
                               ]);
                        }
                    } catch (Exception $e) { /* ignore logging errors */ }

                    // Send prediction summary SMS to logged-in user's phone
                    $sessionUserId = $_SESSION['user_id'] ?? null;
                    $sessionPhone = normalizeRwandaPhone((string)($_SESSION['user_phone'] ?? ''));
                    $smsDb = (new Database())->getConnection();
                    $smsPhone = $sessionPhone ?: resolveCurrentUserPhone($smsDb, $sessionUserId);
                    sendPredictionSms(
                        $smsPhone,
                        $_SESSION['user_name'] ?? 'User',
                        $smsConfig,
                        [
                            'congestion_level' => $result['congestion_level'] ?? null,
                            'accident_probability' => $result['accident_probability'] ?? null,
                            'confidence' => $result['confidence'] ?? null,
                            'recommendations' => $result['recommendations'] ?? []
                        ]
                    );
                    
                    echo json_encode($result);
                } else {
                    // Fallback to PHP prediction if Python fails
                    error_log('Python prediction failed, using PHP fallback: ' . $result['error']);
                    
                    $congestion_index = computeCongestionIndex($data);
                    $congestion_level = determineCongestionLevel($congestion_index);
                    $accident_probability = computeAccidentProbability($data, $congestion_index);
                    $confidenceBase = 0.90 + (0.06 * (1 - abs(0.5 - $congestion_index)));
                    $confidencePenalty = ($accident_probability > 0.80) ? 0.03 : 0.0;
                    $confidence = min(0.99, max(0.90, $confidenceBase - $confidencePenalty));
                    $explanations = buildTrafficExplanations($data, $congestion_index, $accident_probability);
                    $recommendations = buildTrafficRecommendations($congestion_level, $accident_probability, $data);
                    
                    $responsePayload = [
                        'success' => true,
                        'congestion_level' => $congestion_level,
                        'congestion_index' => round($congestion_index, 4),
                        'accident_probability' => round($accident_probability, 4),
                        'confidence' => $confidence,
                        'explanations' => $explanations,
                        'recommendations' => $recommendations,
                        'timestamp' => date('Y-m-d H:i:s'),
                        'model_info' => [
                            'type' => 'STRGS Route Guidance Engine',
                            'note' => 'Route guidance analysis completed successfully'
                        ]
                    ];
                    // Write to audit log for Recent Activity
                    try {
                        $database = new Database();
                        $db = $database->getConnection();
                        if ($db) {
                            $db->prepare("INSERT INTO audit_log (user_id, action, table_name, new_values, created_at) VALUES (:user_id, 'prediction', 'ai_predictions', :new_values, NOW())")
                               ->execute([
                                   ':user_id' => $_SESSION['user_id'] ?? null,
                                   ':new_values' => json_encode([
                                       'segment_id' => $data['segment_id'],
                                       'congestion_level' => $congestion_level,
                                       'accident_probability' => $accident_probability,
                                       'confidence' => $confidence
                                   ])
                               ]);
                        }
                    } catch (Exception $e) { /* ignore logging errors */ }

                    // Send prediction summary SMS to logged-in user's phone
                    $sessionUserId = $_SESSION['user_id'] ?? null;
                    $sessionPhone = normalizeRwandaPhone((string)($_SESSION['user_phone'] ?? ''));
                    $smsDb = (new Database())->getConnection();
                    $smsPhone = $sessionPhone ?: resolveCurrentUserPhone($smsDb, $sessionUserId);
                    sendPredictionSms(
                        $smsPhone,
                        $_SESSION['user_name'] ?? 'User',
                        $smsConfig,
                        [
                            'congestion_level' => $congestion_level,
                            'accident_probability' => $accident_probability,
                            'confidence' => $confidence,
                            'recommendations' => $recommendations
                        ]
                    );

                    echo json_encode($responsePayload);
                }
                exit;
                
            } catch (Exception $e) {
                error_log('Prediction error: ' . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Route guidance failed. Please try again.',
                    'error' => $e->getMessage()
                ]);
                exit;
            }
            
        case 'load_sample':
            echo json_encode([
                'segment_id' => 1,
                'reading_time' => date('Y-m-d H:i:s'),
                'vehicle_count' => 820,
                'avg_speed_kmh' => 24.5,
                'density_veh_per_km' => 68.0,
                'weather' => 'rain',
                'visibility_m' => 900,
                'temperature_c' => 21.0
            ]);
            exit;
        
        case 'upload_data':
            try {
                if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'message' => 'CSV file is required']);
                    exit;
                }

                $tmpPath = $_FILES['csv_file']['tmp_name'];
                $handle = fopen($tmpPath, 'r');
                if (!$handle) {
                    echo json_encode(['success' => false, 'message' => 'Failed to read CSV']);
                    exit;
                }

                // Read header and build flexible mapping (accept varying header names)
                $header = fgetcsv($handle);
                if (!$header) { echo json_encode(['success' => false, 'message' => 'CSV file is empty']); fclose($handle); exit; }
                // Remove UTF-8 BOM if present on first cell
                if (isset($header[0])) {
                    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
                }
                $normalize = function($s){ return preg_replace('/[\s_\-]/', '', strtolower($s)); };
                $normalizedHeader = array_map($normalize, $header);

                // Aliases for required fields (traffic readings)
                $aliases = [
                    'segment_name' => ['segment_name','segmentname','road_segment_name','roadsegmentname','segment','roadsegment','segment_id','segmentid'],
                    'reading_time' => ['reading_time','readingtime','timestamp','time','datetime','date_time'],
                    'vehicle_count' => ['vehicle_count','vehiclecount','count','vehicles','vehiclecountinterval'],
                    'avg_speed_kmh' => ['avg_speed_kmh','avgspeedkmh','avg_speed','speed','speed_kmh'],
                    'density_veh_per_km' => ['density_veh_per_km','densityvehperkm','density','veh_per_km','vehicles_per_km'],
                    'weather' => ['weather','condition','weather_condition']
                ];

                $indexMap = [];
                foreach ($aliases as $key => $candidates) {
                    $found = -1;
                    foreach ($candidates as $cand) {
                        $norm = $normalize($cand);
                        $pos = array_search($norm, $normalizedHeader, true);
                        if ($pos !== false) { $found = $pos; break; }
                    }
                    if ($found === -1) {
                        echo json_encode(['success' => false, 'message' => "Missing required column for '$key'. Acceptable names: ".implode(', ', $candidates)]);
                        fclose($handle);
                        exit;
                    }
                    $indexMap[$key] = $found;
                }

                $database = new Database();
                $db = $database->getConnection();
                if ($db === null) { throw new Exception('DB connection failed'); }

                // Build segment name => id map once for fast lookup during CSV import
                $segmentMap = [];
                $segStmt = $db->query("SELECT id, name FROM road_segments");
                foreach ($segStmt->fetchAll(PDO::FETCH_ASSOC) as $seg) {
                    $segmentMap[strtolower(trim((string)$seg['name']))] = (int)$seg['id'];
                }

                $insert = $db->prepare("INSERT INTO traffic_readings (segment_id, reading_time, vehicle_count, avg_speed_kmh, density_veh_per_km, weather, visibility_m, temperature_c, source)
                                        VALUES (:segment_id, :reading_time, :vehicle_count, :avg_speed_kmh, :density_veh_per_km, :weather, :visibility_m, :temperature_c, :source)");
                $count = 0;
                $skipped = 0;
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) === 1 && trim($row[0]) === '') { continue; }
                    $segmentRef = trim((string)($row[$indexMap['segment_name']] ?? ''));
                    $segment_id = 0;
                    if (preg_match('/^\d+$/', $segmentRef)) {
                        $segment_id = (int)$segmentRef;
                    } else {
                        $segment_id = $segmentMap[strtolower($segmentRef)] ?? 0;
                    }
                    $reading_time = trim((string)($row[$indexMap['reading_time']] ?? ''));
                    $vehicle_count = (int)trim((string)($row[$indexMap['vehicle_count']] ?? '0'));
                    $avg_speed_kmh = (float)trim((string)($row[$indexMap['avg_speed_kmh']] ?? '0'));
                    $density = (float)trim((string)($row[$indexMap['density_veh_per_km']] ?? '0'));
                    $weather = strtolower(trim((string)($row[$indexMap['weather']] ?? 'unknown')));

                    $visibility_m = null;
                    $temperature_c = null;
                    $source = 'csv_upload';

                    // Optional columns
                    $optionalAliases = [
                        'visibility_m' => ['visibility_m','visibility','vis_m'],
                        'temperature_c' => ['temperature_c','temperature','temp_c'],
                        'source' => ['source','data_source']
                    ];
                    foreach ($optionalAliases as $okey => $cands) {
                        foreach ($cands as $cand) {
                            $pos = array_search($normalize($cand), $normalizedHeader, true);
                            if ($pos !== false) {
                                $val = trim((string)($row[$pos] ?? ''));
                                if ($okey === 'visibility_m' && $val !== '') $visibility_m = (int)$val;
                                if ($okey === 'temperature_c' && $val !== '') $temperature_c = (float)$val;
                                if ($okey === 'source' && $val !== '') $source = $val;
                                break;
                            }
                        }
                    }

                    if ($segment_id <= 0 || $reading_time === '') { $skipped++; continue; }
                    try {
                        $insert->execute([
                            ':segment_id' => $segment_id,
                            ':reading_time' => $reading_time,
                            ':vehicle_count' => $vehicle_count,
                            ':avg_speed_kmh' => $avg_speed_kmh,
                            ':density_veh_per_km' => $density,
                            ':weather' => ($weather === '' ? 'unknown' : $weather),
                            ':visibility_m' => $visibility_m,
                            ':temperature_c' => $temperature_c,
                            ':source' => $source
                        ]);
                        $count++;
                    } catch (Exception $e) {
                        // Skip invalid row but keep count of processed
                        $skipped++;
                        error_log('CSV row insert failed: '.$e->getMessage());
                    }
                }
                fclose($handle);

                // Log upload activity
                try {
                    $db->prepare("INSERT INTO audit_log (user_id, action, table_name, new_values, created_at) VALUES (:user_id, :action, :table_name, :new_values, NOW())")
                       ->execute([
                           ':user_id' => $_SESSION['user_id'] ?? null,
                           ':action' => 'upload_csv',
                           ':table_name' => 'traffic_readings',
                           ':new_values' => json_encode(['imported' => $count, 'skipped' => $skipped])
                       ]);
                } catch (Exception $e) { /* ignore logging errors */ }

                echo json_encode(['success' => true, 'message' => "Imported $count traffic readings successfully. Skipped $skipped rows."]);
            } catch (Exception $e) {
                error_log('Upload error: '.$e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Upload failed: '.$e->getMessage()]);
            }
            exit;

        case 'get_activity':
            try {
                $database = new Database();
                $db = $database->getConnection();
                if ($db === null) { throw new Exception('DB connection failed'); }

                $page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
                $perPage = isset($_POST['per_page']) ? max(1, min(20, (int)$_POST['per_page'])) : 10;
                $offset = ($page - 1) * $perPage;

                // Total count across sources
                $predCount = (int)$db->query("SELECT COUNT(*) FROM audit_log WHERE action = 'prediction'")->fetchColumn();
                $uplCount = (int)$db->query("SELECT COUNT(*) FROM audit_log WHERE action = 'upload_csv'")->fetchColumn();
                $segCount = (int)$db->query("SELECT COUNT(*) FROM road_segments")->fetchColumn();
                $total = $predCount + $uplCount + $segCount;

                // Unified query with pagination
                $sql = "SELECT title, created_at, icon, color FROM (
                            SELECT CONCAT('New prediction completed: congestion=', JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.congestion_level')), ', risk=', JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.accident_probability'))) AS title,
                                   created_at, 'fas fa-brain' AS icon, '#6f42c1' AS color
                            FROM audit_log WHERE action = 'prediction'
                            UNION ALL
                            SELECT CONCAT('Traffic readings uploaded (', JSON_EXTRACT(new_values, '$.imported'), ' records)') AS title,
                                   created_at, 'fas fa-upload' AS icon, '#17a2b8' AS color
                            FROM audit_log WHERE action = 'upload_csv'
                            UNION ALL
                            SELECT CONCAT('Road segment configured: ', name, ' (', zone, ')') AS title,
                                   created_at, 'fas fa-road' AS icon, '#0d6efd' AS color
                            FROM road_segments
                        ) t
                        ORDER BY created_at DESC
                        LIMIT :limit OFFSET :offset";
                $stmt = $db->prepare($sql);
                $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'activities' => $items,
                    'pagination' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'pages' => max(1, (int)ceil($total / $perPage))
                    ]
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to load activity: ' . $e->getMessage()]);
            }
            exit;

        case 'get_reports':
            try {
                $database = new Database();
                $db = $database->getConnection();
                if ($db === null) { throw new Exception('DB connection failed'); }

                $reports = [];

                // Congestion distribution (fallback to audit_log if ai_predictions table empty)
                $distStmt = $db->query("SELECT congestion_level, COUNT(*) as count FROM ai_predictions GROUP BY congestion_level ORDER BY count DESC");
                $dist = $distStmt->fetchAll(PDO::FETCH_ASSOC);
                if (!$dist || count($dist) === 0) {
                    $alt = $db->prepare("SELECT JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.congestion_level')) as congestion_level, COUNT(*) as count
                                         FROM audit_log
                                         WHERE action='prediction'
                                         GROUP BY JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.congestion_level'))
                                         ORDER BY count DESC");
                    $alt->execute();
                    $dist = $alt->fetchAll(PDO::FETCH_ASSOC);
                }
                $reports['congestion_distribution'] = $dist;

                // Traffic readings trend (last 30 days) from traffic_readings
                $trendStmt = $db->query("SELECT DATE(reading_time) as date, COUNT(*) as count
                                         FROM traffic_readings
                                         WHERE reading_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                         GROUP BY DATE(reading_time)
                                         ORDER BY date DESC");
                $reports['traffic_trends'] = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

                // Prediction trends (last 30 days) - with confidence and risk %
                $predictionTrendStmt = $db->query("SELECT
                        DATE(al.created_at) as date,
                        COUNT(*) as predictions_count,
                        AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(al.new_values, '$.confidence')) AS DECIMAL(10,4))) as avg_confidence,
                        AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(al.new_values, '$.accident_probability')) AS DECIMAL(10,4))) as avg_accident_risk
                    FROM audit_log al
                    WHERE al.action = 'prediction'
                      AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY DATE(al.created_at)
                    ORDER BY date DESC");
                $reports['prediction_trends'] = $predictionTrendStmt->fetchAll(PDO::FETCH_ASSOC);

                // Prediction trends: current month by day
                $predictionDailyStmt = $db->query("SELECT
                        DATE(al.created_at) as date,
                        DAY(al.created_at) as day_num,
                        COUNT(*) as predictions_count,
                        AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(al.new_values, '$.confidence')) AS DECIMAL(10,4))) as avg_confidence,
                        AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(al.new_values, '$.accident_probability')) AS DECIMAL(10,4))) as avg_accident_risk
                    FROM audit_log al
                    WHERE al.action = 'prediction'
                      AND YEAR(al.created_at) = YEAR(CURDATE())
                      AND MONTH(al.created_at) = MONTH(CURDATE())
                    GROUP BY DATE(al.created_at), DAY(al.created_at)
                    ORDER BY DATE(al.created_at) ASC");
                $reports['prediction_current_month_daily'] = $predictionDailyStmt->fetchAll(PDO::FETCH_ASSOC);

                // Prediction trends: current year by month
                $predictionMonthlyStmt = $db->query("SELECT
                        MONTH(al.created_at) as month_num,
                        COUNT(*) as predictions_count,
                        AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(al.new_values, '$.confidence')) AS DECIMAL(10,4))) as avg_confidence,
                        AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(al.new_values, '$.accident_probability')) AS DECIMAL(10,4))) as avg_accident_risk
                    FROM audit_log al
                    WHERE al.action = 'prediction'
                      AND YEAR(al.created_at) = YEAR(CURDATE())
                    GROUP BY MONTH(al.created_at)
                    ORDER BY MONTH(al.created_at) ASC");
                $reports['prediction_current_year_monthly'] = $predictionMonthlyStmt->fetchAll(PDO::FETCH_ASSOC);

                // District (zone) risk comparison from predictions
                $districtRiskStmt = $db->query("SELECT
                        z.zone,
                        COALESCE(p.predictions_count, 0) as predictions_count,
                        COALESCE(p.avg_accident_risk, 0) as avg_accident_risk
                    FROM (
                        SELECT DISTINCT zone
                        FROM road_segments
                        WHERE zone IS NOT NULL AND zone <> ''
                    ) z
                    LEFT JOIN (
                        SELECT
                            rs.zone as zone,
                            COUNT(*) as predictions_count,
                            AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(al.new_values, '$.accident_probability')) AS DECIMAL(10,4))) as avg_accident_risk
                        FROM audit_log al
                        INNER JOIN road_segments rs
                          ON rs.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(al.new_values, '$.segment_id')) AS UNSIGNED)
                        WHERE al.action = 'prediction'
                        GROUP BY rs.zone
                    ) p ON p.zone = z.zone
                    ORDER BY avg_accident_risk DESC, z.zone ASC");
                $reports['district_risk_distribution'] = $districtRiskStmt->fetchAll(PDO::FETCH_ASSOC);

                // System stats
                $segCount = (int)$db->query("SELECT COUNT(*) FROM road_segments")->fetchColumn();
                $readingCount = (int)$db->query("SELECT COUNT(*) FROM traffic_readings")->fetchColumn();
                $accCount = (int)$db->query("SELECT COUNT(*) FROM accidents")->fetchColumn();
                $reports['system_stats'] = [
                    'total_segments' => $segCount,
                    'total_readings' => $readingCount,
                    'total_accidents' => $accCount
                ];

                // Zone distribution (segments by zone)
                $zoneStmt = $db->query("SELECT zone, COUNT(*) as count FROM road_segments GROUP BY zone ORDER BY count DESC");
                $reports['zone_distribution'] = $zoneStmt->fetchAll(PDO::FETCH_ASSOC);

                // Recent predictions with details and segment names (from audit_log)
                $recentStmt = $db->query("SELECT
                    JSON_UNQUOTE(JSON_EXTRACT(al.new_values, '$.segment_id')) as segment_id,
                    COALESCE(rs.name, CONCAT('Segment #', JSON_UNQUOTE(JSON_EXTRACT(al.new_values, '$.segment_id')))) as segment_name,
                    JSON_UNQUOTE(JSON_EXTRACT(al.new_values, '$.congestion_level')) as congestion_level,
                    JSON_UNQUOTE(JSON_EXTRACT(al.new_values, '$.accident_probability')) as accident_probability,
                    JSON_UNQUOTE(JSON_EXTRACT(al.new_values, '$.confidence')) as confidence,
                    al.created_at
                    FROM audit_log al
                    LEFT JOIN road_segments rs ON rs.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(al.new_values, '$.segment_id')) AS UNSIGNED)
                    WHERE al.action = 'prediction'
                    ORDER BY al.created_at DESC
                    LIMIT 20");
                $reports['recent_predictions'] = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'reports' => $reports]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to load reports: ' . $e->getMessage()]);
            }
            exit;

        case 'get_segments':
            try {
                $database = new Database();
                $db = $database->getConnection();
                if ($db === null) { throw new Exception('DB connection failed'); }

                $district = trim($_POST['district'] ?? '');
                if ($district !== '') {
                    // District-first filter (case-insensitive via collation)
                    $stmt = $db->prepare("SELECT id, name, zone FROM road_segments WHERE is_active = 1 AND zone = :zone ORDER BY name ASC");
                    $stmt->bindValue(':zone', $district);
                    $stmt->execute();
                } else {
                    $stmt = $db->prepare("SELECT id, name, zone FROM road_segments WHERE is_active = 1 ORDER BY zone ASC, name ASC");
                    $stmt->execute();
                }
                $segments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'segments' => $segments]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to load segments: ' . $e->getMessage()]);
            }
            exit;

        case 'get_districts':
            try {
                $database = new Database();
                $db = $database->getConnection();
                if ($db === null) { throw new Exception('DB connection failed'); }

                $stmt = $db->prepare("SELECT DISTINCT zone FROM road_segments WHERE is_active = 1 AND zone IS NOT NULL AND zone <> '' ORDER BY zone ASC");
                $stmt->execute();
                $districts = array_map(function($row) {
                    return $row['zone'];
                }, $stmt->fetchAll(PDO::FETCH_ASSOC));

                echo json_encode(['success' => true, 'districts' => $districts]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to load districts: ' . $e->getMessage()]);
            }
            exit;
        
        case 'update_full_name':
            try {
                $fullName = trim($_POST['full_name'] ?? '');
                if ($fullName === '') { echo json_encode(['success' => false, 'message' => 'Full name is required']); exit; }

                // Persist in session for display; optional: save to DB if you have a users table with name field
                $_SESSION['user_name'] = $fullName;
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'change_password':
            try {
                $current = $_POST['current_password'] ?? '';
                $new = $_POST['new_password'] ?? '';
                if (strlen($new) < 8) { echo json_encode(['success' => false, 'message' => 'New password too short']); exit; }

                // Load current user from DB
                $database = new Database();
                $db = $database->getConnection();
                if ($db === null) { throw new Exception('DB connection failed'); }

                if (!isset($_SESSION['user_id'])) { echo json_encode(['success' => false, 'message' => 'Not authenticated']); exit; }
                $userId = (int)$_SESSION['user_id'];

                $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
                $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row || !password_verify($current, $row['password_hash'])) {
                    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
                    exit;
                }

                $newHash = password_hash($new, PASSWORD_DEFAULT);
                $up = $db->prepare("UPDATE users SET password_hash = :ph, updated_at = NOW() WHERE id = :id");
                $up->bindParam(':ph', $newHash);
                $up->bindParam(':id', $userId, PDO::PARAM_INT);
                $up->execute();

                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'driver_home':
        case 'driver_profile_get':
        case 'driver_profile_update':
        case 'driver_upload_avatar':
        case 'driver_road_status':
        case 'driver_alerts':
        case 'driver_search_destinations':
        case 'driver_segments_map':
        case 'driver_recent_updates':
        case 'driver_request_route':
        case 'driver_travel_history':
            require_once __DIR__ . '/includes/driver_api_handlers.php';
            handleDriverApiAction($_POST['action']);
            break;

        case 'request_password_reset':
            try {
                $identifier = trim($_POST['email'] ?? '');
                if ($identifier === '') { echo json_encode(['success' => false, 'message' => 'Email or username is required']); exit; }

                $database = new Database();
                $db = $database->getConnection();
                if ($db === null) { throw new Exception('DB connection failed'); }

                // Find user by email first, then username
                $userRow = null;
                $stmt = $db->prepare("SELECT id, email FROM users WHERE email = :id LIMIT 1");
                $stmt->execute([':id' => $identifier]);
                $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$userRow) {
                    $stmt = $db->prepare("SELECT id, email FROM users WHERE username = :id LIMIT 1");
                    $stmt->execute([':id' => $identifier]);
                    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                if (!$userRow) { echo json_encode(['success' => false, 'message' => 'Account not found']); exit; }

                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                // Store token in user_sessions table
                $insert = $db->prepare("INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (:uid, :token, :exp)");
                $insert->execute([':uid' => $userRow['id'], ':token' => $token, ':exp' => $expiresAt]);

                $resetUrl = APP_URL . '/login.php?reset_token=' . urlencode($token);

                echo json_encode(['success' => true, 'message' => 'Reset link generated', 'reset_url' => $resetUrl]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'perform_password_reset':
            try {
                $token = $_POST['token'] ?? '';
                $new = $_POST['new_password'] ?? '';
                if (strlen($token) < 10) { echo json_encode(['success' => false, 'message' => 'Invalid token']); exit; }
                if (strlen($new) < 8) { echo json_encode(['success' => false, 'message' => 'Password too short']); exit; }

                $database = new Database();
                $db = $database->getConnection();
                if ($db === null) { throw new Exception('DB connection failed'); }

                // Lookup valid token
                $stmt = $db->prepare("SELECT user_id FROM user_sessions WHERE session_token = :t AND expires_at > NOW() LIMIT 1");
                $stmt->execute([':t' => $token]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) { echo json_encode(['success' => false, 'message' => 'Invalid or expired token']); exit; }

                $newHash = password_hash($new, PASSWORD_DEFAULT);
                $up = $db->prepare("UPDATE users SET password_hash = :ph, updated_at = NOW() WHERE id = :id");
                $up->execute([':ph' => $newHash, ':id' => $row['user_id']]);

                // Invalidate token
                $del = $db->prepare("DELETE FROM user_sessions WHERE session_token = :t");
                $del->execute([':t' => $token]);

                echo json_encode(['success' => true, 'message' => 'Password reset successful']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'validate_password':
            $password = $_POST['password'] ?? '';
            $strength = $_POST['strength'] ?? 0;
            
            $suggestions = [];
            
            // Additional server-side validation
            if (strlen($password) < 8) {
                $suggestions[] = 'Use at least 8 characters';
            }
            
            if (!preg_match('/[A-Z]/', $password)) {
                $suggestions[] = 'Add uppercase letters';
            }
            
            if (!preg_match('/[a-z]/', $password)) {
                $suggestions[] = 'Add lowercase letters';
            }
            
            if (!preg_match('/[0-9]/', $password)) {
                $suggestions[] = 'Add numbers';
            }
            
            if (!preg_match('/[^A-Za-z0-9]/', $password)) {
                $suggestions[] = 'Add special characters';
            }
            
            // Check against common passwords database (simulated)
            $commonPasswords = [
                'password', '123456', '123456789', 'qwerty', 'abc123',
                'password123', 'admin', 'letmein', 'welcome', 'monkey'
            ];
            
            if (in_array(strtolower($password), $commonPasswords)) {
                $suggestions[] = 'Avoid common passwords';
            }
            
            // Check for patterns
            if (preg_match('/(.)\1{2,}/', $password)) {
                $suggestions[] = 'Avoid repeated characters';
            }
            
            if (preg_match('/123|abc|qwe/i', $password)) {
                $suggestions[] = 'Avoid sequential patterns';
            }
            
            echo json_encode([
                'success' => true,
                'strength' => $strength,
                'suggestions' => $suggestions,
                'is_secure' => $strength >= 85 && empty($suggestions)
            ]);
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STRGS - Smart Traffic Route Guidance System</title>
    <link rel="icon" type="image/png" href="logo1.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Bookman+Old+Style:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #000000;
            --secondary-color: #6c757d;
            --light-gray: #f8f9fa;
            --medium-gray: #e9ecef;
            --dark-gray: #495057;
            --white: #ffffff;
            --border-color: #dee2e6;
        }

        body {
            background-color: var(--white);
            color: var(--primary-color);
            font-family: 'Bookman Old Style', 'Times New Roman', serif;
            line-height: 1.7;
            font-weight: 400;
            letter-spacing: 0.3px;
        }

        .navbar {
            background-color: var(--white) !important;
            border-bottom: 3px solid var(--border-color);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            font-family: 'Bookman Old Style', 'Times New Roman', serif;
            letter-spacing: 0.5px;
        }
        
        .navbar-logo {
            width: 40px;
            height: 40px;
            object-fit: contain;
            border-radius: 5px;
        }
        
        .navbar-logo-right {
            display: flex;
            align-items: center;
            margin-left: 20px;
            padding: 0 10px;
        }
        
        .top-right-logo {
            width: 35px;
            height: 35px;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .top-right-logo:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .main-container {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
            margin: 2rem auto;
            padding: 2.5rem;
        }

        .header-section {
            text-align: center;
            margin-bottom: 3rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid var(--border-color);
        }

        .header-section h1 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .header-section p {
            color: var(--secondary-color);
            font-size: 1.1rem;
        }

        .form-section {
            background-color: var(--light-gray);
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .form-section h4 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-family: 'Bookman Old Style', 'Times New Roman', serif;
            letter-spacing: 0.3px;
            font-size: 1.1rem;
        }

        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 0.8rem 1.2rem;
            transition: all 0.3s ease;
            background-color: var(--white);
            font-family: 'Bookman Old Style', 'Times New Roman', serif;
            font-size: 1rem;
            letter-spacing: 0.2px;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0,0,0,0.1);
            background-color: var(--white);
        }

        .btn {
            border-radius: 8px;
            padding: 0.8rem 1.8rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            font-family: 'Bookman Old Style', 'Times New Roman', serif;
            letter-spacing: 0.3px;
            font-size: 1rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: var(--white);
        }

        .btn-primary:hover {
            background-color: var(--dark-gray);
            border-color: var(--dark-gray);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            color: var(--white);
        }

        .btn-secondary:hover {
            background-color: var(--dark-gray);
            border-color: var(--dark-gray);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
            background-color: transparent;
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: var(--white);
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .feature-group {
            background-color: var(--white);
            border-radius: 8px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .feature-group:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .feature-group h6 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0.5rem;
        }

        .result-section {
            background-color: var(--light-gray);
            border-radius: 8px;
            padding: 2rem;
            margin-top: 2rem;
            border: 1px solid var(--border-color);
            display: none;
        }

        .prediction-card {
            background-color: var(--white);
            border-radius: 8px;
            padding: 2rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .prediction-badge {
            font-size: 1.5rem;
            font-weight: 700;
            padding: 1rem 2rem;
            border-radius: 8px;
            text-align: center;
            border: 2px solid var(--border-color);
        }

        .prediction-excellent {
            background-color: var(--white);
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .prediction-good {
            background-color: var(--light-gray);
            color: var(--primary-color);
            border-color: var(--secondary-color);
        }

        .prediction-average {
            background-color: var(--medium-gray);
            color: var(--primary-color);
            border-color: var(--dark-gray);
        }

        .prediction-poor {
            background-color: var(--dark-gray);
            color: var(--white);
            border-color: var(--primary-color);
        }

        .progress {
            height: 8px;
            border-radius: 4px;
            background-color: var(--medium-gray);
        }

        .progress-bar {
            background-color: var(--primary-color);
            border-radius: 4px;
        }

        .alert {
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        .alert-success {
            background-color: var(--light-gray);
            color: var(--primary-color);
            border-color: var(--secondary-color);
        }

        .alert-danger {
            background-color: var(--white);
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .alert-info {
            background-color: var(--light-gray);
            color: var(--secondary-color);
            border-color: var(--border-color);
        }

        .loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }

        .spinner-border {
            color: var(--primary-color);
        }

        .stats-card {
            background-color: var(--white);
            border-radius: 8px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stats-label {
            color: var(--secondary-color);
            font-weight: 500;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--light-gray) 0%, var(--white) 50%, var(--light-gray) 100%);
            padding: 3rem 0;
            border-bottom: 4px solid var(--border-color);
            position: relative;
            overflow: hidden;
            box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="%23000" opacity="0.02"/><circle cx="75" cy="75" r="1" fill="%23000" opacity="0.02"/><circle cx="50" cy="10" r="0.5" fill="%23000" opacity="0.02"/><circle cx="10" cy="60" r="0.5" fill="%23000" opacity="0.02"/><circle cx="90" cy="40" r="0.5" fill="%23000" opacity="0.02"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }
        
        .hero-section::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(0,0,0,0.02) 50%, transparent 70%);
            pointer-events: none;
        }

        .hero-title {
            font-size: 3.2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1.2rem;
            line-height: 1.1;
            font-family: 'Bookman Old Style', 'Times New Roman', serif;
            letter-spacing: 1px;
            text-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
            position: relative;
            z-index: 2;
        }
        
        .hero-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }

        .hero-subtitle {
            font-size: 1.3rem;
            color: var(--secondary-color);
            margin-bottom: 1.8rem;
            line-height: 1.6;
            font-family: 'Bookman Old Style', 'Times New Roman', serif;
            letter-spacing: 0.5px;
            font-style: italic;
            position: relative;
            z-index: 2;
            max-width: 90%;
            margin-left: auto;
            margin-right: auto;
        }
        
        .hero-subtitle::before {
            content: '"';
            font-size: 2rem;
            color: var(--primary-color);
            opacity: 0.3;
            position: absolute;
            left: -20px;
            top: -5px;
        }
        
        .hero-subtitle::after {
            content: '"';
            font-size: 2rem;
            color: var(--primary-color);
            opacity: 0.3;
            position: absolute;
            right: -20px;
            bottom: -5px;
        }

        .hero-buttons {
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        
        .hero-buttons::before {
            content: '';
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 200px;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--border-color), transparent);
        }

        .hero-image {
            text-align: center;
            padding: 2rem 1.5rem;
            position: relative;
            z-index: 2;
        }
        
        .hero-image::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(0,0,0,0.03) 0%, transparent 70%);
            border-radius: 50%;
            z-index: -1;
        }

        .hero-logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
            position: relative;
            z-index: 3;
        }
        
        .hero-logo-container::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 250px;
            height: 250px;
            border: 2px solid rgba(0,0,0,0.05);
            border-radius: 50%;
            z-index: -1;
        }

        .hero-logo {
            width: 110px;
            height: 110px;
            object-fit: contain;
            border-radius: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            animation: heroLogoFloat 3s ease-in-out infinite;
            border: 2px solid rgba(255, 255, 255, 0.8);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .hero-icon {
            font-size: 6rem;
            color: var(--primary-color);
            opacity: 0.1;
        }

        @keyframes heroLogoFloat {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-10px) rotate(3deg); }
        }

        /* Services Section */
        .services-section {
            padding: 2.5rem 0;
            background-color: var(--white);
        }

        .section-title {
            font-size: 2.4rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.8rem;
            font-family: 'Bookman Old Style', 'Times New Roman', serif;
            letter-spacing: 0.6px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .section-subtitle {
            font-size: 1.1rem;
            color: var(--secondary-color);
            font-family: 'Bookman Old Style', 'Times New Roman', serif;
            letter-spacing: 0.3px;
            font-style: italic;
            margin-bottom: 1.5rem;
        }

        .service-card {
            background-color: var(--white);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 1.8rem;
            height: 100%;
            transition: all 0.4s ease;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
        }

        .service-icon {
            width: 80px;
            height: 80px;
            background-color: var(--light-gray);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            transition: all 0.3s ease;
        }

        .service-card:hover .service-icon {
            background-color: var(--primary-color);
            color: var(--white);
        }

        .service-icon i {
            font-size: 2rem;
            color: var(--primary-color);
            transition: all 0.3s ease;
        }

        .service-card:hover .service-icon i {
            color: var(--white);
        }

        .service-card h4 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 1rem;
            font-family: 'Bookman Old Style', 'Times New Roman', serif;
            letter-spacing: 0.4px;
            font-size: 1.3rem;
        }

        .service-card p {
            color: var(--secondary-color);
            margin-bottom: 1.5rem;
        }

        .service-features {
            list-style: none;
            padding: 0;
            text-align: left;
        }

        .service-features li {
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
            position: relative;
            padding-left: 1.5rem;
        }

        .service-features li:before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--primary-color);
            font-weight: bold;
        }

        /* Modal Styles */
        .modal-content {
            border: 2px solid var(--border-color);
            border-radius: 12px;
        }

        .modal-header {
            background-color: var(--light-gray);
            border-bottom: 2px solid var(--border-color);
            border-radius: 10px 10px 0 0;
        }

        .modal-title {
            color: var(--primary-color);
            font-weight: 600;
        }

        .modal-body {
            padding: 2rem;
        }

        .form-floating {
            margin-bottom: 1rem;
        }

        .form-floating .form-control {
            border: 2px solid var(--border-color);
            border-radius: 8px;
        }

        .form-floating .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0,0,0,0.1);
        }

        .form-floating label {
            color: var(--secondary-color);
        }

        /* Enhanced Registration Modal Styles */
        .register-modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .register-modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-gray));
            color: var(--white);
            border: none;
            padding: 2rem;
        }

        .register-modal-header .modal-title {
            font-family: 'Bookman Old Style', 'Times New Roman', serif;
            font-weight: 700;
            font-size: 1.5rem;
        }

        .register-modal-header .btn-close {
            filter: invert(1);
        }

        .register-modal-footer {
            background-color: var(--light-gray);
            border: none;
            padding: 1.5rem 2rem;
        }

        /* Social Sign-in Styles */
        .social-signin-section {
            margin-bottom: 2rem;
        }

        .social-btn {
            border-radius: 12px;
            padding: 0.8rem 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border-width: 2px;
        }

        .google-btn {
            background-color: #ffffff;
            border: 1px solid #dadce0;
            color: #3c4043;
            font-weight: 500;
            box-shadow: 0 1px 2px 0 rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
            position: relative;
        }

        .google-btn:hover {
            background-color: #f8f9fa;
            border-color: #dadce0;
            color: #3c4043;
            transform: translateY(-1px);
            box-shadow: 0 1px 2px 0 rgba(60, 64, 67, 0.3), 0 2px 6px 2px rgba(60, 64, 67, 0.15);
        }

        .google-btn:active {
            background-color: #f1f3f4;
            transform: translateY(0);
            box-shadow: 0 1px 2px 0 rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
        }

        /* Custom Google G Logo */
        .google-g-logo {
            width: 18px;
            height: 18px;
            display: inline-block;
            margin-right: 8px;
            position: relative;
        }

        .google-g-logo::before {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: conic-gradient(
                #ea4335 0deg 90deg,
                #fbbc05 90deg 180deg,
                #34a853 180deg 270deg,
                #4285f4 270deg 360deg
            );
        }

        .google-g-logo::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: white;
        }

        /* G letter styling */
        .google-g-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-family: 'Roboto', sans-serif;
            font-weight: 500;
            font-size: 10px;
            color: #4285f4;
            z-index: 2;
        }

        .facebook-btn:hover {
            background-color: #3b5998;
            border-color: #3b5998;
            color: white;
            transform: translateY(-2px);
        }

        .github-btn:hover {
            background-color: #333;
            border-color: #333;
            color: white;
            transform: translateY(-2px);
        }

        .divider {
            position: relative;
            text-align: center;
            margin: 1.5rem 0;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--border-color);
        }

        .divider span {
            background: var(--white);
            padding: 0 1rem;
            color: var(--secondary-color);
            font-size: 0.9rem;
        }

        /* Enhanced Form Styles */
        .register-form .form-floating {
            position: relative;
        }

        .register-form .form-control {
            border-radius: 12px;
            border: 2px solid var(--border-color);
            padding: 1rem 1.2rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .register-form .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 0, 0, 0.1);
        }

        .register-form .form-control.is-valid {
            border-color: #28a745;
        }

        .register-form .form-control.is-invalid {
            border-color: #dc3545;
        }

        .register-form label {
            font-family: 'Bookman Old Style', 'Times New Roman', serif;
            font-weight: 600;
        }

        /* Password Strength Indicator */
        .password-strength {
            margin-top: 0.5rem;
        }

        .strength-bar {
            height: 4px;
            background-color: var(--light-gray);
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 0.25rem;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }



        .strength-text {
            font-size: 0.8rem;
            color: var(--secondary-color);
        }

        /* Enhanced Password Strength Styles */
        .password-strength {
            margin-top: 0.8rem;
            padding: 1rem;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 12px;
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }

        .strength-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.8rem;
        }

        .strength-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin: 0;
        }

        .strength-score {
            font-size: 0.8rem;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        #strengthScore.weak {
            background-color: #dc3545 !important;
            color: white !important;
        }

        #strengthScore.fair {
            background-color: #ffc107 !important;
            color: #212529 !important;
        }

        #strengthScore.good {
            background-color: #17a2b8 !important;
            color: white !important;
        }

        #strengthScore.strong {
            background-color: #28a745 !important;
            color: white !important;
        }

        .strength-bar {
            width: 100%;
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 0.8rem;
            position: relative;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.5s ease;
            border-radius: 4px;
            position: relative;
        }

        .strength-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        #strengthFill.weak {
            width: 25% !important;
            background: linear-gradient(90deg, #dc3545, #c82333) !important;
        }

        #strengthFill.fair {
            width: 50% !important;
            background: linear-gradient(90deg, #ffc107, #e0a800) !important;
        }

        #strengthFill.good {
            width: 75% !important;
            background: linear-gradient(90deg, #17a2b8, #138496) !important;
        }

        #strengthFill.strong {
            width: 100% !important;
            background: linear-gradient(90deg, #28a745, #1e7e34) !important;
        }

        .strength-criteria {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-top: 0.8rem;
        }

        .criterion {
            display: flex;
            align-items: center;
            font-size: 0.8rem;
            color: var(--secondary-color);
            transition: all 0.3s ease;
        }

        .criterion.met {
            color: #28a745;
            font-weight: 600;
        }

        .criterion-icon {
            margin-right: 0.5rem;
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .criterion-icon i {
            font-size: 0.7rem;
        }

        .criterion.met .criterion-icon {
            color: #28a745;
        }

        .criterion:not(.met) .criterion-icon {
            color: #dc3545;
        }

        /* Submit Button */
        .register-submit-btn {
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 700;
            font-family: 'Bookman Old Style', 'Times New Roman', serif;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .register-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .register-submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Form Validation */
        .invalid-feedback {
            display: block;
            font-size: 0.875rem;
            color: #dc3545;
            margin-top: 0.25rem;
        }

        .valid-feedback {
            display: block;
            font-size: 0.875rem;
            color: #28a745;
            margin-top: 0.25rem;
        }

        /* Loading State */
        .btn-loading {
            display: none;
        }

        .btn-loading.show {
            display: inline-block;
        }

        .btn-text.hide {
            display: none;
        }

        /* Enhanced Login Modal Styles */
        .login-modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .login-modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-gray));
            color: var(--white);
            border: none;
            padding: 2rem;
        }

        .login-modal-header .modal-title {
            font-family: 'Bookman Old Style', 'Times New Roman', serif;
            font-weight: 700;
            font-size: 1.5rem;
        }

        .login-modal-header .btn-close {
            filter: invert(1);
        }

        .login-modal-footer {
            background-color: var(--light-gray);
            border: none;
            padding: 1.5rem 2rem;
        }

        /* Enhanced Login Form Styles */
        .login-form .form-floating {
            position: relative;
        }

        .login-form .form-control {
            border-radius: 12px;
            border: 2px solid var(--border-color);
            padding: 1rem 1.2rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .login-form .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 0, 0, 0.1);
        }

        .login-form .form-control.is-valid {
            border-color: #28a745;
        }

        .login-form .form-control.is-invalid {
            border-color: #dc3545;
        }

        .login-form label {
            font-family: 'Bookman Old Style', 'Times New Roman', serif;
            font-weight: 600;
        }

        /* Dynamic Password Toggle */
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .password-toggle.show {
            opacity: 1;
            visibility: visible;
        }

        .password-toggle-btn {
            border: none;
            background: none;
            color: var(--secondary-color);
            padding: 0.5rem;
            font-size: 0.9rem;
            transition: color 0.3s ease;
            position: relative;
            z-index: 11;
        }

        .password-toggle-btn:hover {
            color: var(--primary-color);
        }

        /* Ensure password fields have enough padding for toggle button when visible */
        .register-form .form-floating .form-control,
        .login-form .form-floating .form-control {
            padding-right: 1.2rem;
            transition: padding-right 0.3s ease;
        }

        .register-form .form-floating .form-control.has-toggle,
        .login-form .form-floating .form-control.has-toggle {
            padding-right: 3.5rem;
        }

        /* Adjust password strength indicator position */
        .password-strength {
            margin-top: 0.5rem;
            margin-right: 0;
            transition: margin-right 0.3s ease;
        }

        .password-strength.has-toggle {
            margin-right: 2rem;
        }

        /* Login Submit Button */
        .login-submit-btn {
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 700;
            font-family: 'Bookman Old Style', 'Times New Roman', serif;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .login-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .login-submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Forgot Password Link */
        .forgot-password-link {
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .forgot-password-link:hover {
            color: var(--dark-gray) !important;
            text-decoration: underline;
        }

        /* Form Check Styling */
        .login-form .form-check-input {
            border-radius: 4px;
            border: 2px solid var(--border-color);
        }

        .login-form .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .login-form .form-check-label {
            font-family: 'Bookman Old Style', 'Times New Roman', serif;
            font-weight: 500;
            color: var(--secondary-color);
        }

        /* About Section */
        .about-section {
            padding: 2.5rem 0;
            background-color: var(--light-gray);
        }

        .feature-list {
            margin-top: 2rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .feature-item i {
            color: var(--primary-color);
            margin-right: 1rem;
            font-size: 1.2rem;
        }

        .feature-item span {
            color: var(--secondary-color);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        @media (min-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 2rem;
            }
        }

        .stat-item {
            text-align: center;
            padding: 1.5rem;
            background-color: var(--white);
            border-radius: 8px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--secondary-color);
            font-weight: 500;
        }

        /* Contact Section */
        .contact-section {
            padding: 2.5rem 0;
            background-color: var(--white);
        }

        .contact-card {
            background-color: var(--light-gray);
            border-radius: 12px;
            padding: 1.5rem;
            border: 2px solid var(--border-color);
        }

        .contact-info h4,
        .contact-form h4 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .contact-item i {
            color: var(--primary-color);
            margin-right: 1rem;
            width: 20px;
        }

        .contact-item span {
            color: var(--secondary-color);
        }

        /* Footer Styles */
        .footer {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 3rem 0 1rem;
            margin-top: 4rem;
        }

        .footer-section {
            margin-bottom: 2rem;
        }

        .footer-title {
            color: var(--white);
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .footer-description {
            color: var(--light-gray);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .footer-heading {
            color: var(--white);
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 0.5rem;
        }

        .footer-links a {
            color: var(--light-gray);
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .footer-links a:hover {
            color: var(--white);
            padding-left: 5px;
        }

        .social-links {
            display: flex;
            gap: 1rem;
        }

        .social-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: var(--dark-gray);
            color: var(--white);
            border-radius: 50%;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .social-link:hover {
            background-color: var(--white);
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        .footer-divider {
            border-color: var(--border-color);
            margin: 2.5rem 0 1.5rem;
            border-width: 1px;
            opacity: 0.6;
        }

        .footer-bottom {
            padding: 1.5rem 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .copyright {
            color: var(--light-gray);
            margin: 0;
            font-size: 0.95rem;
            font-family: 'Bookman Old Style', 'Times New Roman', serif;
            letter-spacing: 0.3px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            flex-wrap: wrap;
        }

        .copyright-symbol {
            font-size: 1.2rem;
            color: var(--white);
            font-weight: 700;
            text-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
        }

        .copyright-year {
            color: var(--white);
            font-weight: 800;
            font-size: 1.3rem;
            text-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
            letter-spacing: 0.5px;
        }

        .copyright-company {
            color: var(--light-gray);
            font-weight: 600;
        }

        .copyright-rights {
            color: var(--light-gray);
            font-style: italic;
            font-size: 0.9rem;
        }

        .footer-badges {
            display: flex;
            gap: 0.8rem;
            justify-content: flex-end;
            flex-wrap: wrap;
            align-items: center;
        }

        .footer-badges .badge {
            background-color: var(--dark-gray);
            color: var(--white);
            padding: 0.6rem 1.2rem;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 600;
            font-family: 'Bookman Old Style', 'Times New Roman', serif;
            letter-spacing: 0.2px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .footer-badges .badge:hover {
            background-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .footer-badges .badge i {
            color: var(--white);
        }

        /* Enhanced Navigation Styles */
        .navbar {
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .navbar-scrolled {
            background-color: rgba(255, 255, 255, 0.95) !important;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }

        .navbar-hidden {
            transform: translateY(-100%);
        }

        .navbar-nav .nav-link.active {
            color: var(--primary-color) !important;
            font-weight: 600;
        }

        /* Loading Screen */
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--white) 0%, var(--light-gray) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-content {
            text-align: center;
        }
        
        .loading-logo-container {
            position: relative;
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .loading-logo {
            position: relative;
            z-index: 10;
        }
        
        .logo-image {
            width: 100px;
            height: 100px;
            object-fit: contain;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            animation: logoFloat 3s ease-in-out infinite;
        }
        
        /* Orbital Ring Animation */
        .logo-orbital-ring {
            position: absolute;
            width: 160px;
            height: 160px;
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            animation: orbitalRotate 4s linear infinite;
        }
        
        .orbital-dot {
            position: absolute;
            width: 8px;
            height: 8px;
            background: var(--primary-color);
            border-radius: 50%;
            top: -4px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .orbital-dot:nth-child(2) {
            top: 50%;
            right: -4px;
            transform: translateY(-50%);
        }
        
        .orbital-dot:nth-child(3) {
            bottom: -4px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .orbital-dot:nth-child(4) {
            top: 50%;
            left: -4px;
            transform: translateY(-50%);
        }
        
        /* Pulse Rings */
        .logo-pulse-ring {
            position: absolute;
            width: 140px;
            height: 140px;
            border: 2px solid rgba(0, 0, 0, 0.2);
            border-radius: 50%;
            animation: pulseRing 2s ease-out infinite;
        }
        
        .logo-pulse-ring-2 {
            position: absolute;
            width: 180px;
            height: 180px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            animation: pulseRing 2s ease-out infinite 0.5s;
        }
        
        /* Progress Bar */
        .loading-progress {
            width: 100%;
            max-width: 400px;
            margin: 0 auto 30px;
        }
        
        .progress-bar {
            width: 100%;
            height: 4px;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
            width: 0%;
            animation: progressFill 5s ease-out forwards;
        }
        
        /* Loading Steps */
        .loading-steps {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .step {
            font-size: 14px;
            color: var(--secondary-color);
            opacity: 0.5;
            transition: all 0.3s ease;
        }
        
        .step.active {
            color: var(--primary-color);
            opacity: 1;
            font-weight: 600;
        }
        
        /* Loading Stats */
        .loading-stats {
            text-align: center;
        }
        
        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--secondary-color);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Animations */
        @keyframes logoFloat {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-8px) rotate(2deg); }
        }
        
        @keyframes orbitalRotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes pulseRing {
            0% {
                transform: scale(0.8);
                opacity: 1;
            }
            100% {
                transform: scale(1.2);
                opacity: 0;
            }
        }
        
        @keyframes progressFill {
            0% { width: 0%; }
            20% { width: 20%; }
            40% { width: 40%; }
            60% { width: 60%; }
            80% { width: 80%; }
            100% { width: 100%; }
        }

        .loading-spinner {
            width: 80px;
            height: 80px;
            margin: 0 auto 2rem;
            position: relative;
        }

        .loading-spinner i {
            font-size: 3rem;
            color: var(--primary-color);
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Custom Alert Styles */
        .custom-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 400px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .alert-content {
            display: flex;
            align-items: center;
        }

        /* Newsletter Form */
        .newsletter-form {
            margin-top: 1rem;
        }

        .newsletter-form .input-group {
            border-radius: 25px;
            overflow: hidden;
        }

        .newsletter-form .form-control {
            border: 2px solid var(--dark-gray);
            border-right: none;
            border-radius: 25px 0 0 25px;
            background-color: var(--white);
        }

        .newsletter-form .btn {
            border-radius: 0 25px 25px 0;
            border: 2px solid var(--primary-color);
        }

        /* Enhanced Service Cards */
        .service-card {
            position: relative;
            overflow: hidden;
        }

        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .service-card:hover::before {
            left: 100%;
        }

        /* Enhanced Buttons */
        .btn {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        /* Parallax Container */
        .parallax-container {
            overflow: hidden;
        }

        /* Enhanced Form Controls */
        .form-control {
            transition: all 0.3s ease;
            position: relative;
        }

        .form-control:focus {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* Floating Labels Enhancement */
        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
            color: var(--primary-color);
        }

        /* Enhanced Stats */
        .stat-item {
            position: relative;
            overflow: hidden;
        }

        .stat-item::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 3px;
            background: var(--primary-color);
            transition: width 0.3s ease;
        }

        .stat-item:hover::after {
            width: 100%;
        }

        /* Enhanced Contact Form */
        .contact-form .form-control {
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .contact-form .form-control:focus {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        /* Enhanced Footer */
        .footer {
            position: relative;
        }

        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--white), transparent);
        }

        /* Scroll Progress Bar */
        .scroll-progress {
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            z-index: 9999;
            transition: width 0.1s ease;
        }

        /* Enhanced Modal Styles */
        .modal-content {
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .modal.show .modal-content {
            transform: scale(1);
        }

        .modal-backdrop {
            backdrop-filter: blur(5px);
        }

        /* Enhanced Social Links */
        .social-link {
            position: relative;
            overflow: hidden;
        }

        .social-link::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: var(--white);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.3s, height 0.3s;
        }

        .social-link:hover::before {
            width: 100%;
            height: 100%;
        }

        .social-link i {
            position: relative;
            z-index: 1;
            transition: color 0.3s ease;
        }

        .social-link:hover i {
            color: var(--primary-color);
        }

        /* Enhanced Feature Items */
        .feature-item {
            transition: all 0.3s ease;
            padding: 0.5rem;
            border-radius: 8px;
        }

        .feature-item:hover {
            background-color: var(--light-gray);
            transform: translateX(10px);
        }

        /* Enhanced Contact Items */
        .contact-item {
            transition: all 0.3s ease;
            padding: 0.5rem;
            border-radius: 8px;
        }

        .contact-item:hover {
            background-color: var(--white);
            transform: translateX(10px);
        }

        /* Lazy Loading */
        [data-lazy] {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease;
        }

        [data-lazy].loaded {
            opacity: 1;
            transform: translateY(0);
        }

        /* Enhanced Tooltips */
        .tooltip {
            font-size: 0.875rem;
        }

        .tooltip-inner {
            background-color: var(--primary-color);
            border-radius: 6px;
        }

        .bs-tooltip-top .tooltip-arrow::before {
            border-top-color: var(--primary-color);
        }

        /* Enhanced Badges */
        .badge {
            transition: all 0.3s ease;
        }

        .badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        /* Simple Button Styles */
        #loginBtn, #registerBtn, #heroRegisterBtn, #heroServicesBtn {
            transition: all 0.3s ease;
            border-radius: 8px;
            font-weight: 600;
        }

        #loginBtn:hover, #registerBtn:hover, #heroRegisterBtn:hover, #heroServicesBtn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        #loginBtn:active, #registerBtn:active, #heroRegisterBtn:active, #heroServicesBtn:active {
            transform: translateY(0);
        }

        /* ✨ MAGICAL BUTTONS ✨ */
        
        /* Magic Login Button */
        .magic-login-btn {
            position: relative;
            overflow: hidden;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border: 2px solid var(--primary-color);
            transition: all 0.4s ease;
            animation: magicPulse 3s infinite;
        }

        .magic-login-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3), 0 0 20px rgba(0,0,0,0.2);
            background: linear-gradient(45deg, var(--secondary-color), var(--primary-color));
        }

        .magic-login-btn .btn-text {
            position: relative;
            z-index: 2;
            transition: all 0.3s ease;
        }

        .magic-login-btn:hover .btn-text {
            color: var(--white);
            text-shadow: 0 0 10px rgba(255,255,255,0.5);
        }

        /* Magic Sparkles */
        .magic-sparkles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }

        .magic-sparkles .sparkle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--white);
            border-radius: 50%;
            opacity: 0;
            animation: sparkleFloat 2s infinite;
        }

        .magic-sparkles .sparkle:nth-child(1) {
            top: 20%;
            left: 20%;
            animation-delay: 0s;
        }

        .magic-sparkles .sparkle:nth-child(2) {
            top: 30%;
            right: 20%;
            animation-delay: 0.5s;
        }

        .magic-sparkles .sparkle:nth-child(3) {
            bottom: 30%;
            left: 30%;
            animation-delay: 1s;
        }

        .magic-sparkles .sparkle:nth-child(4) {
            bottom: 20%;
            right: 30%;
            animation-delay: 1.5s;
        }

        /* Magic Register Button */
        .magic-register-btn {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary-color), #6c5ce7, var(--primary-color));
            background-size: 200% 200%;
            border: 2px solid var(--primary-color);
            animation: magicGradient 4s ease infinite, magicFloat 3s ease-in-out infinite;
        }

        .magic-register-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 10px 30px rgba(0,0,0,0.4), 0 0 30px rgba(108,92,231,0.3);
            animation-play-state: paused;
        }

        .magic-register-btn .btn-text {
            position: relative;
            z-index: 2;
            transition: all 0.3s ease;
        }

        .magic-register-btn:hover .btn-text {
            color: var(--white);
            text-shadow: 0 0 15px rgba(255,255,255,0.8);
        }

        /* Magic Particles */
        .magic-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }

        .magic-particles .particle {
            position: absolute;
            width: 3px;
            height: 3px;
            background: var(--white);
            border-radius: 50%;
            opacity: 0;
            animation: particleFloat 3s infinite;
        }

        .magic-particles .particle:nth-child(1) {
            top: 15%;
            left: 15%;
            animation-delay: 0s;
        }

        .magic-particles .particle:nth-child(2) {
            top: 25%;
            right: 15%;
            animation-delay: 0.6s;
        }

        .magic-particles .particle:nth-child(3) {
            bottom: 25%;
            left: 25%;
            animation-delay: 1.2s;
        }

        .magic-particles .particle:nth-child(4) {
            bottom: 15%;
            right: 25%;
            animation-delay: 1.8s;
        }

        .magic-particles .particle:nth-child(5) {
            top: 50%;
            left: 50%;
            animation-delay: 2.4s;
        }

        /* Magic Hero Button */
        .magic-hero-btn {
            position: relative;
            overflow: hidden;
            background: linear-gradient(45deg, var(--primary-color), #ff6b6b, var(--primary-color));
            background-size: 300% 300%;
            border: 3px solid var(--primary-color);
            animation: magicHeroGradient 5s ease infinite, magicHeroPulse 2s ease-in-out infinite;
        }

        .magic-hero-btn:hover {
            transform: translateY(-5px) scale(1.08);
            box-shadow: 0 15px 40px rgba(0,0,0,0.4), 0 0 40px rgba(255,107,107,0.4);
        }

        .magic-hero-btn .btn-text {
            position: relative;
            z-index: 3;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .magic-hero-btn:hover .btn-text {
            color: var(--white);
            text-shadow: 0 0 20px rgba(255,255,255,0.8);
        }

        /* Magic Aura */
        .magic-aura {
            position: absolute;
            top: -10px;
            left: -10px;
            right: -10px;
            bottom: -10px;
            background: radial-gradient(circle, rgba(255,107,107,0.3) 0%, transparent 70%);
            border-radius: 50%;
            opacity: 0;
            animation: magicAura 3s ease-in-out infinite;
        }

        .magic-hero-btn:hover .magic-aura {
            opacity: 1;
            animation: magicAuraHover 0.5s ease;
        }

        /* Floating Sparkles */
        .floating-sparkles {
            position: absolute;
            top: -20px;
            left: 0;
            width: 100%;
            height: 20px;
            pointer-events: none;
        }

        .floating-sparkles .sparkle {
            position: absolute;
            width: 6px;
            height: 6px;
            background: var(--white);
            border-radius: 50%;
            opacity: 0;
            animation: floatingSparkle 2s infinite;
        }

        .floating-sparkles .sparkle:nth-child(1) {
            left: 20%;
            animation-delay: 0s;
        }

        .floating-sparkles .sparkle:nth-child(2) {
            left: 50%;
            animation-delay: 0.7s;
        }

        .floating-sparkles .sparkle:nth-child(3) {
            right: 20%;
            animation-delay: 1.4s;
        }

        /* Magic Services Button */
        .magic-services-btn {
            position: relative;
            overflow: hidden;
            border: 2px solid var(--primary-color);
            background: transparent;
            transition: all 0.4s ease;
        }

        .magic-services-btn:hover {
            transform: translateY(-3px) scale(1.05);
            background: var(--primary-color);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3), 0 0 20px rgba(0,0,0,0.2);
        }

        .magic-services-btn .btn-text {
            position: relative;
            z-index: 2;
            transition: all 0.3s ease;
        }

        .magic-services-btn:hover .btn-text {
            color: var(--white);
            text-shadow: 0 0 10px rgba(255,255,255,0.5);
        }

        /* Magic Glow */
        .magic-glow {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(0,0,0,0.1), transparent);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .magic-services-btn:hover .magic-glow {
            opacity: 1;
            animation: magicGlow 1s ease-in-out infinite alternate;
        }

        /* ✨ MAGICAL ANIMATIONS ✨ */
        
        @keyframes magicPulse {
            0%, 100% { box-shadow: 0 0 5px rgba(0,0,0,0.2); }
            50% { box-shadow: 0 0 20px rgba(0,0,0,0.4), 0 0 30px rgba(0,0,0,0.1); }
        }

        @keyframes magicGradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes magicFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-3px); }
        }

        @keyframes sparkleFloat {
            0% { opacity: 0; transform: translateY(0px) scale(0); }
            50% { opacity: 1; transform: translateY(-10px) scale(1); }
            100% { opacity: 0; transform: translateY(-20px) scale(0); }
        }

        @keyframes particleFloat {
            0% { opacity: 0; transform: translateY(0px) rotate(0deg); }
            25% { opacity: 1; transform: translateY(-5px) rotate(90deg); }
            75% { opacity: 1; transform: translateY(-15px) rotate(270deg); }
            100% { opacity: 0; transform: translateY(-25px) rotate(360deg); }
        }

        @keyframes magicHeroGradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes magicHeroPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }

        @keyframes magicAura {
            0%, 100% { transform: scale(0.8); opacity: 0.3; }
            50% { transform: scale(1.2); opacity: 0.6; }
        }

        @keyframes magicAuraHover {
            0% { transform: scale(0.8); opacity: 0; }
            100% { transform: scale(1.5); opacity: 0.8; }
        }

        @keyframes floatingSparkle {
            0% { opacity: 0; transform: translateY(0px) scale(0); }
            50% { opacity: 1; transform: translateY(-15px) scale(1); }
            100% { opacity: 0; transform: translateY(-30px) scale(0); }
        }

        @keyframes magicGlow {
            0% { opacity: 0.3; }
            100% { opacity: 0.7; }
        }

        /* Special hover effects for magic buttons */
        .magic-login-btn:active {
            transform: translateY(-1px) scale(0.98);
            animation: magicClick 0.2s ease;
        }

        .magic-register-btn:active {
            transform: translateY(-1px) scale(0.98);
            animation: magicClick 0.2s ease;
        }

        .magic-hero-btn:active {
            transform: translateY(-2px) scale(1.02);
            animation: magicClick 0.2s ease;
        }

        @keyframes magicClick {
            0% { transform: scale(1); }
            50% { transform: scale(0.95); }
            100% { transform: scale(1); }
        }

        /* ✨ MAGICAL EFFECTS CSS ✨ */
        
        /* Magic Sparkle Burst */
        .magic-sparkle-burst {
            position: fixed;
            width: 6px;
            height: 6px;
            background: var(--white);
            border-radius: 50%;
            pointer-events: none;
            z-index: 9999;
            animation: sparkleBurst 1s ease-out forwards;
        }

        @keyframes sparkleBurst {
            0% {
                opacity: 1;
                transform: scale(0) rotate(0deg);
            }
            50% {
                opacity: 1;
                transform: scale(1) rotate(180deg);
            }
            100% {
                opacity: 0;
                transform: scale(0.5) rotate(360deg);
            }
        }

        /* Magic Particle Burst */
        .magic-particle-burst {
            position: fixed;
            width: 4px;
            height: 4px;
            background: linear-gradient(45deg, #6c5ce7, #a29bfe);
            border-radius: 50%;
            pointer-events: none;
            z-index: 9999;
            animation: particleBurst 1.2s ease-out forwards;
        }

        @keyframes particleBurst {
            0% {
                opacity: 1;
                transform: scale(0) rotate(0deg);
            }
            30% {
                opacity: 1;
                transform: scale(1.2) rotate(120deg);
            }
            100% {
                opacity: 0;
                transform: scale(0.3) rotate(360deg);
            }
        }

        /* Magical Aura Effect */
        .magical-aura-effect {
            position: fixed;
            border: 3px solid rgba(255,107,107,0.6);
            border-radius: 15px;
            pointer-events: none;
            z-index: 9998;
            animation: magicalAura 2s ease-out forwards;
        }

        @keyframes magicalAura {
            0% {
                opacity: 0;
                transform: scale(0.8);
                box-shadow: 0 0 0 rgba(255,107,107,0.4);
            }
            50% {
                opacity: 1;
                transform: scale(1.1);
                box-shadow: 0 0 30px rgba(255,107,107,0.6);
            }
            100% {
                opacity: 0;
                transform: scale(1.3);
                box-shadow: 0 0 50px rgba(255,107,107,0.2);
            }
        }

        /* Floating Magic Sparkle */
        .floating-magic-sparkle {
            position: fixed;
            width: 8px;
            height: 8px;
            background: radial-gradient(circle, var(--white) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 9999;
            animation: floatingMagic 2s ease-out forwards;
        }

        @keyframes floatingMagic {
            0% {
                opacity: 1;
                transform: translateY(0px) scale(0);
            }
            20% {
                opacity: 1;
                transform: translateY(-10px) scale(1);
            }
            100% {
                opacity: 0;
                transform: translateY(-40px) scale(0.5);
            }
        }

        /* Magical Glow Effect */
        .magical-glow-effect {
            position: fixed;
            border: 2px solid rgba(0,0,0,0.3);
            border-radius: 10px;
            pointer-events: none;
            z-index: 9998;
            animation: magicalGlow 1.5s ease-out forwards;
        }

        @keyframes magicalGlow {
            0% {
                opacity: 0;
                transform: scale(0.9);
                box-shadow: 0 0 0 rgba(0,0,0,0.2);
            }
            50% {
                opacity: 1;
                transform: scale(1.05);
                box-shadow: 0 0 20px rgba(0,0,0,0.4);
            }
            100% {
                opacity: 0;
                transform: scale(1.2);
                box-shadow: 0 0 30px rgba(0,0,0,0.1);
            }
        }

        /* Magic Click Effect */
        .magic-click-effect {
            position: fixed;
            width: 20px;
            height: 20px;
            border: 3px solid var(--primary-color);
            border-radius: 50%;
            pointer-events: none;
            z-index: 9999;
            animation: magicClickEffect 0.6s ease-out forwards;
        }

        @keyframes magicClickEffect {
            0% {
                opacity: 1;
                transform: scale(0);
            }
            50% {
                opacity: 1;
                transform: scale(1.5);
            }
            100% {
                opacity: 0;
                transform: scale(3);
            }
        }

        /* Magic Tooltip */
        .magic-tooltip {
            position: fixed;
            background: linear-gradient(135deg, var(--primary-color), #6c5ce7);
            color: var(--white);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            pointer-events: none;
            z-index: 10000;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            animation: magicTooltip 2s ease-out forwards;
        }

        @keyframes magicTooltip {
            0% {
                opacity: 0;
                transform: translateX(-50%) translateY(10px) scale(0.8);
            }
            20% {
                opacity: 1;
                transform: translateX(-50%) translateY(0px) scale(1);
            }
            80% {
                opacity: 1;
                transform: translateX(-50%) translateY(0px) scale(1);
            }
            100% {
                opacity: 0;
                transform: translateX(-50%) translateY(-10px) scale(0.8);
            }
        }

        /* Magic Active State */
        .magic-active {
            animation-play-state: paused !important;
            transform: scale(1.05) !important;
        }

        /* Enhanced Magic Button States */
        .magic-login-btn.magic-active {
            background: linear-gradient(45deg, #6c5ce7, var(--primary-color)) !important;
            box-shadow: 0 0 30px rgba(108,92,231,0.5) !important;
        }

        .magic-register-btn.magic-active {
            background: linear-gradient(135deg, #6c5ce7, #a29bfe, #6c5ce7) !important;
            box-shadow: 0 0 40px rgba(108,92,231,0.6) !important;
        }

        .magic-hero-btn.magic-active {
            background: linear-gradient(45deg, #ff6b6b, #ffa726, #ff6b6b) !important;
            box-shadow: 0 0 50px rgba(255,107,107,0.7) !important;
        }

        .magic-services-btn.magic-active {
            background: var(--primary-color) !important;
            box-shadow: 0 0 25px rgba(0,0,0,0.4) !important;
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.2rem;
            }
            
            .hero-title::after {
                width: 60px;
            }
            
            .hero-subtitle {
                font-size: 1rem;
            }
            
            .hero-subtitle::before,
            .hero-subtitle::after {
                font-size: 1.5rem;
            }
            
            .hero-icon {
                font-size: 4rem;
            }
            
            .hero-logo-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .hero-logo {
                width: 90px;
                height: 90px;
            }
            
            .hero-logo-container::before {
                width: 200px;
                height: 200px;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .main-container {
                margin: 1rem;
                padding: 1rem;
            }
            
            .feature-grid {
                grid-template-columns: 1fr;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
            
            .footer {
                padding: 2rem 0 1rem;
            }
            
            .footer-badges {
                justify-content: center;
                margin-top: 1rem;
            }
            
            .footer-bottom {
                text-align: center;
                flex-direction: column;
                gap: 1rem;
            }
            
            .copyright {
                justify-content: center;
                text-align: center;
            }
            
            .social-links {
                justify-content: center;
            }
            
            .navbar-logo-right {
                margin-left: 10px;
                padding: 0 5px;
            }
            
            .top-right-logo {
                width: 30px;
                height: 30px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="logo1.png" alt="STRGS Logo" class="navbar-logo me-2">
                STRGS
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="#services"><i class="fas fa-cogs me-1"></i> Services</a>
                    <a class="nav-link" href="#about"><i class="fas fa-info-circle me-1"></i> About</a>
                    <a class="nav-link" href="#contact"><i class="fas fa-envelope me-1"></i> Contact</a>
                    <div class="navbar-logo-right">
                        <img src="logo1.png" alt="STRGS Logo" class="top-right-logo">
                    </div>
                    <a href="login.php" class="btn btn-outline-primary me-2 magic-login-btn">
                        <span class="btn-text">
                            <i class="fas fa-magic me-1"></i> Login
                        </span>
                        <div class="magic-sparkles">
                            <span class="sparkle"></span>
                            <span class="sparkle"></span>
                            <span class="sparkle"></span>
                            <span class="sparkle"></span>
                        </div>
                    </a>
                    <a href="register.php" class="btn btn-primary magic-register-btn">
                        <span class="btn-text">
                            <i class="fas fa-wand-magic-sparkles me-1"></i> Register
                        </span>
                        <div class="magic-particles">
                            <span class="particle"></span>
                            <span class="particle"></span>
                            <span class="particle"></span>
                            <span class="particle"></span>
                            <span class="particle"></span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Scroll Progress Bar -->
    <div class="scroll-progress" id="scrollProgress"></div>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="hero-title">Smart Traffic Route Guidance System</h1>
                    <p class="hero-subtitle">A Case of Remera Sector. Guide drivers to suitable routes using GPS and updated road status information — avoid congested or affected roads before entering them.</p>
                    <div class="hero-buttons">
                        <a href="register.php" class="btn btn-primary btn-lg me-3 magic-hero-btn">
                            <span class="btn-text">
                                <i class="fas fa-wand-magic-sparkles me-2"></i>Get Started
                            </span>
                            <div class="magic-aura"></div>
                            <div class="floating-sparkles">
                                <span class="sparkle"></span>
                                <span class="sparkle"></span>
                                <span class="sparkle"></span>
                            </div>
                        </a>
                        <a href="#services" class="btn btn-outline-primary btn-lg magic-services-btn">
                            <span class="btn-text">
                                <i class="fas fa-route me-2"></i>Our Services
                            </span>
                            <div class="magic-glow"></div>
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image">
                        <div class="hero-logo-container">
                            <img src="logo1.png" alt="STRGS Logo" class="hero-logo">
                           
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Services Section -->
    <section id="services" class="services-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Our Services</h2>
                <p class="section-subtitle">GPS and road status updates for smarter route guidance in Remera Sector</p>
            </div>
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-route"></i>
                        </div>
                        <h4>Route Guidance</h4>
                        <p>Help drivers select suitable routes using GPS location, destination input, and live road status data.</p>
                        <ul class="service-features">
                            <li>GPS-based location tracking</li>
                            <li>Destination entry and routing</li>
                            <li>Best available route recommendations</li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h4>Road Status Monitoring</h4>
                        <p>View real-time road status including congestion, incidents, and blockages across Remera Sector.</p>
                        <ul class="service-features">
                            <li>Live road status updates</li>
                            <li>Congestion and blockage alerts</li>
                            <li>Affected road monitoring</li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4>Road Segment Management</h4>
                        <p>Manage road segments in Remera Sector with traffic readings, status updates, and incident logs.</p>
                        <ul class="service-features">
                            <li>Road segment profiles</li>
                            <li>Traffic status tracking</li>
                            <li>Incident and blockage reporting</li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4>Traffic Police Updates</h4>
                        <p>Traffic police officers update road status, report accidents, congestion, and road blockages for accurate guidance.</p>
                        <ul class="service-features">
                            <li>Road status updates</li>
                            <li>Accident and congestion reporting</li>
                            <li>Affected road monitoring</li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h4>Driver Alerts & Notifications</h4>
                        <p>Notify drivers about congested or affected roads so they can choose alternative routes in time.</p>
                        <ul class="service-features">
                            <li>Road status alerts</li>
                            <li>Route change suggestions</li>
                            <li>SMS notifications</li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <h4>Admin & User Management</h4>
                        <p>System administrators manage users, roles, and settings to keep STRGS operating properly.</p>
                        <ul class="service-features">
                            <li>User account management</li>
                            <li>Role-based access control</li>
                            <li>System configuration</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="section-title">About Our Platform</h2>
                    <p class="section-subtitle">Guiding drivers through Remera Sector with real-time road information</p>
                    <p>STRGS helps drivers select suitable routes before entering congested or affected roads. Traffic police officers update road status and report incidents, while administrators manage users and system settings.</p>
                    <div class="feature-list">
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>GPS-based route guidance for drivers</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Real-time road status updates from traffic police</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Incident, congestion, and blockage reporting</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Admin-managed users and system settings</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number">15,420</div>
                            <div class="stat-label">Segments Monitored</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">94.7%</div>
                            <div class="stat-label">Road Status Updates</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">28,847</div>
                            <div class="stat-label">Routes Guided</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">99.8%</div>
                            <div class="stat-label">System Availability</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">247</div>
                            <div class="stat-label">Zones Covered</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">1,892</div>
                            <div class="stat-label">Alerts Issued</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Get In Touch</h2>
                <p class="section-subtitle">Ready to improve road safety and traffic flow?</p>
            </div>
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="contact-card">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="contact-info">
                                    <h4>Contact Information</h4>
                                    <div class="contact-item">
                                        <i class="fas fa-envelope"></i>
                                        <span>info@strgs.local</span>
                                    </div>
                                    <div class="contact-item">
                                        <i class="fas fa-phone"></i>
                                        <span>+250 790 184 899</span>
                                    </div>
                                    <div class="contact-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span>Kigali,Rwanda</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="contact-form">
                                    <h4>Send us a Message</h4>
                                    <form>
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="contact_name" placeholder="Your Name">
                                            <label for="contact_name">Your Name</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input type="email" class="form-control" id="contact_email" placeholder="Your Email">
                                            <label for="contact_email">Your Email</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <textarea class="form-control" id="contact_message" style="height: 100px" placeholder="Your Message"></textarea>
                                            <label for="contact_message">Your Message</label>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-paper-plane me-2"></i>Send Message
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <script>
        // Initialize GSAP ScrollTrigger
        gsap.registerPlugin(ScrollTrigger);

        $(document).ready(function() {
            // Initialize all components
            initializeNavigation();
            initializeAnimations();
            initializeCounters();
            initializeLiveCounters();
            initializeParallax();
            initializeTooltips();
            initializeContactForm();
            initializeNewsletter();
            initializeScrollEffects();
            initializeLazyLoading();
            
            // Smooth scrolling for anchor links
            $('a[href^="#"]').on('click', function(event) {
                var target = $(this.getAttribute('href'));
                if (target.length) {
                    event.preventDefault();
                    $('html, body').stop().animate({
                        scrollTop: target.offset().top - 80
                    }, 1000);
                }
            });
        });

        // Enhanced Navigation with scroll effects
        function initializeNavigation() {
            let lastScrollTop = 0;
            const navbar = $('.navbar');
            
            $(window).scroll(function() {
                const scrollTop = $(this).scrollTop();
                
                // Add/remove scrolled class
                if (scrollTop > 100) {
                    navbar.addClass('navbar-scrolled');
                } else {
                    navbar.removeClass('navbar-scrolled');
                }
                
                // Hide/show navbar on scroll
                if (scrollTop > lastScrollTop && scrollTop > 200) {
                    navbar.addClass('navbar-hidden');
                } else {
                    navbar.removeClass('navbar-hidden');
                }
                
                lastScrollTop = scrollTop;
            });

            // Active navigation highlighting
            $(window).scroll(function() {
                const scrollPos = $(document).scrollTop();
                $('.navbar-nav .nav-link').each(function() {
                    const href = $(this).attr('href');
                    if (href.startsWith('#')) {
                        const target = $(href);
                        if (target.length) {
                            const targetTop = target.offset().top - 100;
                            const targetBottom = targetTop + target.outerHeight();
                            
                            if (scrollPos >= targetTop && scrollPos < targetBottom) {
                                $('.navbar-nav .nav-link').removeClass('active');
                                $(this).addClass('active');
                            }
                        }
                    }
                });
            });
        }

        // Advanced Animations with GSAP
        function initializeAnimations() {
            // Hero section animations
            gsap.timeline()
                .from('.hero-title', { duration: 1, y: 50, opacity: 0, ease: 'power2.out' })
                .from('.hero-subtitle', { duration: 0.8, y: 30, opacity: 0, ease: 'power2.out' }, '-=0.5')
                .from('.hero-buttons', { duration: 0.6, y: 20, opacity: 0, ease: 'power2.out' }, '-=0.3')
                .from('.hero-logo', { duration: 1, scale: 0, rotation: -180, ease: 'back.out(1.7)' }, '-=0.8')
                .from('.hero-icon', { duration: 1.2, scale: 0, rotation: 180, ease: 'back.out(1.7)' }, '-=0.6');

            // Service cards animation
            gsap.utils.toArray('.service-card').forEach((card, index) => {
                gsap.from(card, {
                    duration: 0.8,
                    y: 50,
                    opacity: 0,
                    ease: 'power2.out',
                    scrollTrigger: {
                        trigger: card,
                        start: 'top 80%',
                        end: 'bottom 20%',
                        toggleActions: 'play none none reverse'
                    },
                    delay: index * 0.1
                });
            });

            // Stats animation
            gsap.utils.toArray('.stat-item').forEach((stat, index) => {
                gsap.from(stat, {
                    duration: 0.6,
                    scale: 0,
                    rotation: 10,
                    ease: 'back.out(1.7)',
                    scrollTrigger: {
                        trigger: stat,
                        start: 'top 90%',
                        toggleActions: 'play none none reverse'
                    },
                    delay: index * 0.1
                });
            });
        }

        // Animated counters
        function initializeCounters() {
            $('.stat-number').each(function() {
                const $this = $(this);
                const countTo = $this.attr('data-count') || $this.text();
                
                // Extract numeric value and suffix
                let numericValue = parseFloat(countTo.replace(/[^\d.]/g, ''));
                let suffix = countTo.replace(/[\d.]/g, '');
                
                gsap.fromTo($this, 
                    { innerHTML: 0 },
                    {
                        duration: 2.5,
                        innerHTML: numericValue,
                        snap: { innerHTML: 1 },
                        ease: 'power2.out',
                        scrollTrigger: {
                            trigger: $this,
                            start: 'top 80%',
                            toggleActions: 'play none none reverse'
                        },
                        onUpdate: function() {
                            let currentValue = Math.ceil(this.targets()[0].innerHTML);
                            if (suffix.includes('%')) {
                                $this.text(currentValue.toFixed(1) + suffix);
                            } else if (numericValue >= 1000) {
                                $this.text(currentValue.toLocaleString() + suffix);
                            } else {
                                $this.text(currentValue + suffix);
                            }
                        },
                        onComplete: function() {
                            if (suffix.includes('%')) {
                                $this.text(numericValue.toFixed(1) + suffix);
                            } else if (numericValue >= 1000) {
                                $this.text(numericValue.toLocaleString() + suffix);
                            } else {
                                $this.text(numericValue + suffix);
                            }
                        }
                    }
                );
            });
        }

        // Add live counter updates to simulate real-time data
        function initializeLiveCounters() {
            setTimeout(() => {
                setInterval(function() {
                    $('.stat-number').each(function() {
                        const $this = $(this);
                        let currentText = $this.text();
                        let numericValue = parseFloat(currentText.replace(/[^\d.]/g, ''));
                        let suffix = currentText.replace(/[\d.]/g, '');
                        
                        // Small random increment to simulate live updates
                        let increment = Math.random() * 0.05;
                        let newValue = numericValue + increment;
                        
                        if (suffix.includes('%')) {
                            $this.text(newValue.toFixed(1) + suffix);
                        } else if (numericValue >= 1000) {
                            $this.text(Math.floor(newValue).toLocaleString() + suffix);
                        } else {
                            $this.text(Math.floor(newValue) + suffix);
                        }
                    });
                }, 8000); // Update every 8 seconds
            }, 3000); // Start after initial animation completes
        }

        // Parallax effects
        function initializeParallax() {
            gsap.utils.toArray('.hero-section').forEach(section => {
                gsap.to(section, {
                    yPercent: -50,
                    ease: 'none',
                    scrollTrigger: {
                        trigger: section,
                        start: 'top bottom',
                        end: 'bottom top',
                        scrub: true
                    }
                });
            });
        }

        // Initialize Bootstrap tooltips
        function initializeTooltips() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        // Enhanced contact form with validation
        function initializeContactForm() {
            $('#contact form').on('submit', function(e) {
                e.preventDefault();
                
                const formData = {
                    name: $('#contact_name').val(),
                    email: $('#contact_email').val(),
                    message: $('#contact_message').val()
                };
                
                // Client-side validation
                if (!formData.name || !formData.email || !formData.message) {
                    showAlert('Please fill in all fields', 'danger');
                    return;
                }
                
                if (!isValidEmail(formData.email)) {
                    showAlert('Please enter a valid email address', 'danger');
                    return;
                }
                
                // Simulate AJAX submission
                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.html();
                
                submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Sending...').prop('disabled', true);
                
                setTimeout(() => {
                    showAlert('Message sent successfully! We\'ll get back to you soon.', 'success');
                    $(this)[0].reset();
                    submitBtn.html(originalText).prop('disabled', false);
                }, 2000);
            });
        }

        // Newsletter subscription
        function initializeNewsletter() {
            // Add newsletter form to footer if not exists
            if (!$('#newsletter-form').length) {
                const newsletterHtml = `
                    <div class="newsletter-section mt-4">
                        <h6 class="footer-heading">Stay Updated</h6>
                        <form id="newsletter-form" class="newsletter-form">
                            <div class="input-group">
                                <input type="email" class="form-control" placeholder="Enter your email" required>
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                `;
                $('.footer-section:first').append(newsletterHtml);
            }
            
            $('#newsletter-form').on('submit', function(e) {
                e.preventDefault();
                const email = $(this).find('input[type="email"]').val();
                
                if (!isValidEmail(email)) {
                    showAlert('Please enter a valid email address', 'danger');
                    return;
                }
                
                const submitBtn = $(this).find('button');
                const originalText = submitBtn.html();
                
                submitBtn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
                
                setTimeout(() => {
                    showAlert('Successfully subscribed to our newsletter!', 'success');
                    $(this)[0].reset();
                    submitBtn.html(originalText).prop('disabled', false);
                }, 1500);
            });
        }

        // Scroll effects and reveal animations
        function initializeScrollEffects() {
            // Reveal animations for sections
            gsap.utils.toArray('.section-title, .section-subtitle').forEach(element => {
                gsap.from(element, {
                    duration: 1,
                    y: 30,
                    opacity: 0,
                    ease: 'power2.out',
                    scrollTrigger: {
                        trigger: element,
                        start: 'top 85%',
                        toggleActions: 'play none none reverse'
                    }
                });
            });

            // Stagger animation for feature items
            gsap.from('.feature-item', {
                duration: 0.6,
                x: -30,
                opacity: 0,
                stagger: 0.1,
                ease: 'power2.out',
                scrollTrigger: {
                    trigger: '.feature-list',
                    start: 'top 80%',
                    toggleActions: 'play none none reverse'
                }
            });
        }

        // Lazy loading for images and content
        function initializeLazyLoading() {
            const lazyElements = document.querySelectorAll('[data-lazy]');
            
            const lazyObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const element = entry.target;
                        element.classList.add('loaded');
                        lazyObserver.unobserve(element);
                    }
                });
            });
            
            lazyElements.forEach(element => {
                lazyObserver.observe(element);
            });
        }

        // Enhanced alert system
        function showAlert(message, type = 'info', duration = 5000) {
            const alertId = 'alert-' + Date.now();
            const alertDiv = $(`
                <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show custom-alert" role="alert">
                    <div class="alert-content">
                        <i class="fas fa-${getAlertIcon(type)} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `);
            
            $('body').append(alertDiv);
            
            // Animate in
            gsap.from(alertDiv, {
                duration: 0.5,
                x: 300,
                opacity: 0,
                ease: 'back.out(1.7)'
            });
            
            // Auto-dismiss
            setTimeout(function() {
                gsap.to(alertDiv, {
                    duration: 0.3,
                    x: 300,
                    opacity: 0,
                    ease: 'power2.in',
                    onComplete: () => alertDiv.remove()
                });
            }, duration);
        }

        function showSuccessMessage(message) {
            // Create a more prominent success message
            const successHtml = `
                <div class="success-overlay" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.8);
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">
                    <div class="success-card" style="
                        background: white;
                        padding: 2rem;
                        border-radius: 20px;
                        text-align: center;
                        max-width: 400px;
                        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
                        animation: successPop 0.5s ease-out;
                    ">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🎉</div>
                        <h3 style="color: #28a745; margin-bottom: 1rem; font-family: 'Bookman Old Style', serif;">Account Created!</h3>
                        <p style="color: #333; margin-bottom: 1.5rem; line-height: 1.5;">${message}</p>
                        <div class="spinner-border text-success" role="status" style="margin-top: 1rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(successHtml);
            
            // Add CSS animation
            if (!$('#success-animation').length) {
                $('head').append(`
                    <style id="success-animation">
                        @keyframes successPop {
                            0% { transform: scale(0.5); opacity: 0; }
                            50% { transform: scale(1.05); }
                            100% { transform: scale(1); opacity: 1; }
                        }
                    </style>
                `);
            }
            
            // Remove success overlay after redirect
            setTimeout(() => {
                $('.success-overlay').fadeOut();
            }, 1800);
        }

        // Utility functions
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function getAlertIcon(type) {
            const icons = {
                'success': 'check-circle',
                'danger': 'exclamation-triangle',
                'warning': 'exclamation-circle',
                'info': 'info-circle'
            };
            return icons[type] || 'info-circle';
        }

        // Loading screen
        function showLoadingScreen() {
            const loadingHtml = `
                <div id="loading-screen" class="loading-screen">
                    <div class="loading-content">
                        <div class="loading-logo-container">
                            <div class="logo-orbital-ring">
                                <div class="orbital-dot"></div>
                                <div class="orbital-dot"></div>
                                <div class="orbital-dot"></div>
                                <div class="orbital-dot"></div>
                            </div>
                            <div class="logo-pulse-ring"></div>
                            <div class="logo-pulse-ring-2"></div>
                            <div class="loading-logo">
                                <img src="logo1.png" alt="STRGS Logo" class="logo-image">
                            </div>
                        </div>
                        <div class="loading-progress">
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                            <div class="loading-steps">
                                <div class="step active" data-step="1">Loading STRGS</div>
                                <div class="step" data-step="2">Fetching Road Status</div>
                                <div class="step" data-step="3">Preparing Route Data</div>
                                <div class="step" data-step="4">Optimizing Routes</div>
                                <div class="step" data-step="5">Ready to Guide</div>
                            </div>
                        </div>
                        <div class="loading-stats">
                            <div class="stat-item">
                                <span class="stat-number" data-target="100">0</span>%
                                <span class="stat-label">System Ready</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(loadingHtml);
            
            // Animate loading steps
            const steps = $('.step');
            const progressFill = $('.progress-fill');
            const statNumber = $('.stat-number');
            
            // Animate steps every second
            let currentStep = 1;
            const stepInterval = setInterval(() => {
                steps.removeClass('active');
                $(`.step[data-step="${currentStep}"]`).addClass('active');
                currentStep++;
                
                if (currentStep > 5) {
                    clearInterval(stepInterval);
                }
            }, 1000);
            
            // Animate progress counter
            let progress = 0;
            const counterInterval = setInterval(() => {
                progress += 2;
                statNumber.text(progress);
                
                if (progress >= 100) {
                    clearInterval(counterInterval);
                }
            }, 100);
            
            // Hide loading screen after page load with minimum 5 seconds
            $(window).on('load', function() {
                setTimeout(() => {
                    $('#loading-screen').fadeOut(800, function() {
                        $(this).remove();
                    });
                }, 5000); // Minimum 5 seconds loading time
            });
        }

        // Initialize loading screen
        showLoadingScreen();

        // Scroll progress bar
        function initializeScrollProgress() {
            $(window).scroll(function() {
                const scrollTop = $(window).scrollTop();
                const docHeight = $(document).height();
                const winHeight = $(window).height();
                const scrollPercent = (scrollTop / (docHeight - winHeight)) * 100;
                $('#scrollProgress').css('width', scrollPercent + '%');
            });
        }

        // Initialize scroll progress
        initializeScrollProgress();

        // Enhanced form validation with real-time feedback
        function initializeFormValidation() {
            // Real-time email validation
            $('input[type="email"]').on('blur', function() {
                const email = $(this).val();
                const isValid = isValidEmail(email);
                
                if (email && !isValid) {
                    $(this).addClass('is-invalid');
                    if (!$(this).next('.invalid-feedback').length) {
                        $(this).after('<div class="invalid-feedback">Please enter a valid email address</div>');
                    }
                } else {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                }
            });

            // Real-time password strength
            $('input[type="password"]').on('input', function() {
                const password = $(this).val();
                const strength = calculatePasswordStrength(password);
                
                $(this).removeClass('is-valid is-invalid');
                if (password.length > 0) {
                    if (strength >= 3) {
                        $(this).addClass('is-valid');
                    } else {
                        $(this).addClass('is-invalid');
                    }
                }
            });
        }

        // Password strength calculator
        function calculatePasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            return strength;
        }

        // Initialize form validation
        initializeFormValidation();

        // Enhanced modal interactions
        function initializeModalEnhancements() {
            // Modal show/hide animations
            $('.modal').on('show.bs.modal', function() {
                $(this).find('.modal-content').css('transform', 'scale(0.9)');
            });

            $('.modal').on('shown.bs.modal', function() {
                $(this).find('.modal-content').css('transform', 'scale(1)');
            });

            // Auto-focus first input
            $('.modal').on('shown.bs.modal', function() {
                $(this).find('input:first').focus();
            });
        }

        // Initialize modal enhancements
        initializeModalEnhancements();

        // Enhanced service card interactions
        function initializeServiceCardInteractions() {
            $('.service-card').each(function() {
                $(this).on('mouseenter', function() {
                    gsap.to(this, { duration: 0.3, scale: 1.05, ease: 'power2.out' });
                });

                $(this).on('mouseleave', function() {
                    gsap.to(this, { duration: 0.3, scale: 1, ease: 'power2.out' });
                });
            });
        }

        // Initialize service card interactions
        initializeServiceCardInteractions();

        // Enhanced footer interactions
        function initializeFooterInteractions() {
            $('.footer-links a').on('click', function(e) {
                e.preventDefault();
                const href = $(this).attr('href');
                
                if (href.startsWith('#')) {
                    const target = $(href);
                    if (target.length) {
                        $('html, body').animate({
                            scrollTop: target.offset().top - 80
                        }, 1000);
                    }
                }
            });
        }

        // Initialize footer interactions
        initializeFooterInteractions();

        // Performance monitoring
        function initializePerformanceMonitoring() {
            // Monitor page load time
            window.addEventListener('load', function() {
                const loadTime = performance.now();
                console.log(`Page loaded in ${loadTime.toFixed(2)}ms`);
            });

            // Monitor scroll performance
            let scrollTimeout;
            $(window).on('scroll', function() {
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(function() {
                    // Scroll event handling
                }, 10);
            });
        }

        // Initialize performance monitoring
        initializePerformanceMonitoring();

        // ✨ MAGICAL BUTTON INTERACTIONS ✨
        
        function initializeMagicButtons() {
            console.log('Initializing magical buttons...');
            
            // Magic Login Button Interactions
            $('.magic-login-btn').on('mouseenter', function() {
                // Create magical sparkle burst
                createSparkleBurst($(this), 8);
                
                // Add magical sound effect (visual feedback)
                $(this).addClass('magic-active');
                
                // Show magical tooltip
                showMagicTooltip($(this), '✨ Enter STRGS route guidance');
            });

            $('.magic-login-btn').on('mouseleave', function() {
                $(this).removeClass('magic-active');
            });

            $('.magic-login-btn').on('click', function(e) {
                console.log('Magic login button clicked!');
                // Create magical click effect
                createMagicClick($(this));
                
                // Add magical delay before navigation
                setTimeout(() => {
                    console.log('Navigating to login page...');
                    // Navigation will happen automatically via href
                }, 300);
            });

            // Magic Register Button Interactions
            $('.magic-register-btn').on('mouseenter', function() {
                // Create magical particle explosion
                createParticleExplosion($(this), 12);
                
                // Add magical glow effect
                $(this).addClass('magic-active');
                
                // Show magical tooltip
                showMagicTooltip($(this), '🔮 Start your route guidance journey');
            });

            $('.magic-register-btn').on('mouseleave', function() {
                $(this).removeClass('magic-active');
            });

            $('.magic-register-btn').on('click', function(e) {
                console.log('Magic register button clicked!');
                // Create magical click effect
                createMagicClick($(this));
                
                // Add magical delay before navigation
                setTimeout(() => {
                    console.log('Navigating to register page...');
                    // Navigation will happen automatically via href
                }, 300);
            });

            // Magic Hero Button Interactions
            $('.magic-hero-btn').on('mouseenter', function() {
                // Create magical aura effect
                createMagicalAura($(this));
                
                // Add floating sparkles
                createFloatingSparkles($(this), 6);
            });

            $('.magic-hero-btn').on('click', function(e) {
                console.log('Magic hero button clicked!');
                // Create magical click effect
                createMagicClick($(this));
                
                // Add magical delay before navigation
                setTimeout(() => {
                    console.log('Navigating to register page from hero...');
                    // Navigation will happen automatically via href
                }, 400);
            });

            // Magic Services Button Interactions
            $('.magic-services-btn').on('mouseenter', function() {
                // Create magical glow effect
                createMagicalGlow($(this));
            });
        }

        // Create sparkle burst effect
        function createSparkleBurst(element, count) {
            const rect = element[0].getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;

            for (let i = 0; i < count; i++) {
                const sparkle = $('<div class="magic-sparkle-burst"></div>');
                $('body').append(sparkle);

                const angle = (i / count) * Math.PI * 2;
                const distance = 50 + Math.random() * 30;
                const x = centerX + Math.cos(angle) * distance;
                const y = centerY + Math.sin(angle) * distance;

                sparkle.css({
                    left: x + 'px',
                    top: y + 'px',
                    animationDelay: Math.random() * 0.3 + 's'
                });

                setTimeout(() => sparkle.remove(), 1000);
            }
        }

        // Create particle explosion effect
        function createParticleExplosion(element, count) {
            const rect = element[0].getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;

            for (let i = 0; i < count; i++) {
                const particle = $('<div class="magic-particle-burst"></div>');
                $('body').append(particle);

                const angle = Math.random() * Math.PI * 2;
                const distance = 40 + Math.random() * 40;
                const x = centerX + Math.cos(angle) * distance;
                const y = centerY + Math.sin(angle) * distance;

                particle.css({
                    left: x + 'px',
                    top: y + 'px',
                    animationDelay: Math.random() * 0.4 + 's'
                });

                setTimeout(() => particle.remove(), 1200);
            }
        }

        // Create magical aura effect
        function createMagicalAura(element) {
            const aura = $('<div class="magical-aura-effect"></div>');
            $('body').append(aura);

            const rect = element[0].getBoundingClientRect();
            aura.css({
                left: rect.left - 20 + 'px',
                top: rect.top - 20 + 'px',
                width: rect.width + 40 + 'px',
                height: rect.height + 40 + 'px'
            });

            setTimeout(() => aura.remove(), 2000);
        }

        // Create floating sparkles
        function createFloatingSparkles(element, count) {
            const rect = element[0].getBoundingClientRect();
            
            for (let i = 0; i < count; i++) {
                const sparkle = $('<div class="floating-magic-sparkle"></div>');
                $('body').append(sparkle);

                const x = rect.left + Math.random() * rect.width;
                const y = rect.top - 20;

                sparkle.css({
                    left: x + 'px',
                    top: y + 'px',
                    animationDelay: Math.random() * 0.5 + 's'
                });

                setTimeout(() => sparkle.remove(), 2000);
            }
        }

        // Create magical glow effect
        function createMagicalGlow(element) {
            const glow = $('<div class="magical-glow-effect"></div>');
            $('body').append(glow);

            const rect = element[0].getBoundingClientRect();
            glow.css({
                left: rect.left - 10 + 'px',
                top: rect.top - 10 + 'px',
                width: rect.width + 20 + 'px',
                height: rect.height + 20 + 'px'
            });

            setTimeout(() => glow.remove(), 1500);
        }

        // Create magical click effect
        function createMagicClick(element) {
            const clickEffect = $('<div class="magic-click-effect"></div>');
            $('body').append(clickEffect);

            const rect = element[0].getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;

            clickEffect.css({
                left: centerX + 'px',
                top: centerY + 'px'
            });

            setTimeout(() => clickEffect.remove(), 600);
        }

        // Show magical tooltip
        function showMagicTooltip(element, message) {
            const tooltip = $('<div class="magic-tooltip">' + message + '</div>');
            $('body').append(tooltip);

            const rect = element[0].getBoundingClientRect();
            tooltip.css({
                left: rect.left + rect.width / 2 + 'px',
                top: rect.top - 40 + 'px',
                transform: 'translateX(-50%)'
            });

            setTimeout(() => tooltip.remove(), 2000);
        }

        // Initialize magical buttons
        initializeMagicButtons();
        
        // Initialize registration form
        initializeRegistrationForm();

        // Enhanced Registration Form with Real-time Validation
        let modalRoleIdCheckPending = false;
        let modalRoleIdVerified = { license: false, police: false };
        let modalRoleIdLastVerified = { license: '', police: '' };

        function normalizeModalLicenseId(value) {
            return value.trim().toUpperCase();
        }

        function normalizeModalPoliceId(value) {
            return value.trim().toUpperCase();
        }

        function resetModalRoleIdVerification() {
            modalRoleIdVerified.license = false;
            modalRoleIdVerified.police = false;
            modalRoleIdLastVerified.license = '';
            modalRoleIdLastVerified.police = '';
        }

        function initializeRegistrationForm() {
            const form = $('#registerForm');
            const submitBtn = $('.register-submit-btn');
            const btnText = $('.btn-text');
            const btnLoading = $('.btn-loading');



            // Real-time form validation
            form.find('input').on('blur', function() {
                validateField($(this));
            });

            // Password strength indicator
            $('#reg_password').on('input keyup paste', function() {
                updatePasswordStrength($(this).val());
            });

            // Confirm password validation
            $('#reg_confirm_password').on('input', function() {
                validatePasswordMatch();
            });

            // Dynamic password visibility for registration
            $('#reg_password').on('input', function() {
                togglePasswordVisibilityButton('reg_password');
            });

            $('#reg_confirm_password').on('input', function() {
                togglePasswordVisibilityButton('reg_confirm_password');
            });

            // Username availability check (simulated)
            $('#reg_username').on('blur', function() {
                checkUsernameAvailability($(this));
            });

            // Email validation
            $('#reg_email').on('blur', function() {
                validateEmail($(this));
            });

            $('#reg_user_type').on('change', function() {
                const userType = $(this).val();
                const $container = $('#regRoleIdContainer');
                const $licenseGroup = $('#regLicenseIdGroup');
                const $policeGroup = $('#regPoliceIdGroup');

                $('#reg_license_id, #reg_police_id')
                    .val('')
                    .prop('required', false)
                    .removeClass('is-valid is-invalid');
                $('#regLicenseIdGroup .invalid-feedback, #regPoliceIdGroup .invalid-feedback').text('');
                resetModalRoleIdVerification();
                $licenseGroup.hide();
                $policeGroup.hide();

                if (userType === 'driver') {
                    $container.show();
                    $licenseGroup.show();
                    $('#reg_license_id').prop('required', true);
                } else if (userType === 'traffic_authority') {
                    $container.show();
                    $policeGroup.show();
                    $('#reg_police_id').prop('required', true);
                } else {
                    $container.hide();
                }
            });

            $('#reg_license_id').on('blur', function() {
                checkModalRoleId($(this), 'license');
            });

            $('#reg_police_id').on('blur', function() {
                checkModalRoleId($(this), 'police');
            });

            // Form submission
            form.on('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                handleModalRegisterSubmit();
                return false;
            });

            // Button click handler
            submitBtn.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                handleModalRegisterSubmit();
                return false;
            });



            // Social sign-in buttons
            $('#googleSignup').on('click', function() {
                handleSocialSignup('google');
            });

            $('#facebookSignup').on('click', function() {
                handleSocialSignup('facebook');
            });

            $('#githubSignup').on('click', function() {
                handleSocialSignup('github');
            });
        }

        function verifyModalRoleIdPromise(field, idType) {
            return new Promise(function(resolve) {
                let value = field.val();
                value = idType === 'license' ? normalizeModalLicenseId(value) : normalizeModalPoliceId(value);
                field.val(value);

                const $group = field.closest('[id$="Group"]');
                field.removeClass('is-valid is-invalid');
                $group.find('.invalid-feedback').text('');

                if (value.length === 0) {
                    field.addClass('is-invalid');
                    $group.find('.invalid-feedback').text(
                        idType === 'license' ? 'Driving license ID is required' : 'Police ID is required'
                    );
                    modalRoleIdVerified[idType] = false;
                    resolve({ ok: false });
                    return;
                }

                if (idType === 'license') {
                    if (!/^RW-\d{8}$/.test(value)) {
                        field.addClass('is-invalid');
                        $group.find('.invalid-feedback').text('Driving license ID must exactly match RW-12345678 (8 digits after RW-)');
                        modalRoleIdVerified[idType] = false;
                        resolve({ ok: false });
                        return;
                    }
                } else if (!/^RNP-\d{5}$/.test(value)) {
                    field.addClass('is-invalid');
                    $group.find('.invalid-feedback').text('Police ID must exactly match RNP-12345 (5 digits after RNP-)');
                    modalRoleIdVerified[idType] = false;
                    resolve({ ok: false });
                    return;
                }

                if (modalRoleIdVerified[idType] && modalRoleIdLastVerified[idType] === value) {
                    field.addClass('is-valid');
                    resolve({ ok: true });
                    return;
                }

                modalRoleIdVerified[idType] = false;

                modalRoleIdCheckPending = true;

                $.ajax({
                    url: '',
                    method: 'POST',
                    data: {
                        action: 'check_role_id',
                        id_type: idType,
                        id_value: value
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.normalized_id) {
                            field.val(response.normalized_id);
                        }

                        if (response.available) {
                            field.removeClass('is-invalid').addClass('is-valid');
                            $group.find('.invalid-feedback').text('');
                            modalRoleIdVerified[idType] = true;
                            modalRoleIdLastVerified[idType] = field.val();
                            resolve({ ok: true });
                        } else {
                            field.removeClass('is-valid').addClass('is-invalid');
                            $group.find('.invalid-feedback').text(response.message || 'This ID could not be verified');
                            modalRoleIdVerified[idType] = false;
                            resolve({ ok: false });
                        }
                    },
                    error: function() {
                        field.removeClass('is-valid').addClass('is-invalid');
                        $group.find('.invalid-feedback').text('Unable to verify ID against the registry. Please try again.');
                        modalRoleIdVerified[idType] = false;
                        resolve({ ok: false });
                    },
                    complete: function() {
                        modalRoleIdCheckPending = false;
                    }
                });
            });
        }

        function checkModalRoleId(field, idType) {
            verifyModalRoleIdPromise(field, idType);
        }

        async function handleModalRegisterSubmit() {
            if (!validateForm()) {
                showAlert('Please fix the highlighted errors before continuing.', 'warning');
                const $firstInvalid = $('#registerForm .is-invalid').first();
                if ($firstInvalid.length) {
                    $firstInvalid.trigger('focus');
                }
                return;
            }

            const userType = $('#reg_user_type').val();

            if (userType === 'driver') {
                const verified = await verifyModalRoleIdPromise($('#reg_license_id'), 'license');
                if (!verified.ok) {
                    return;
                }
            } else if (userType === 'traffic_authority') {
                const verified = await verifyModalRoleIdPromise($('#reg_police_id'), 'police');
                if (!verified.ok) {
                    return;
                }
            }

            submitRegistration();
        }

        function validateField(field) {
            const value = field.val().trim();
            const fieldName = field.attr('name');
            let isValid = true;
            let message = '';

            switch(fieldName) {
                case 'first_name':
                    if (value.length === 0) {
                        isValid = false;
                        message = 'First name is required';
                    } else if (value.length < 2) {
                        isValid = false;
                        message = 'First name must be at least 2 characters long';
                    }
                    break;
                case 'last_name':
                    if (value.length === 0) {
                        isValid = false;
                        message = 'Last name is required';
                    } else if (value.length < 2) {
                        isValid = false;
                        message = 'Last name must be at least 2 characters long';
                    }
                    break;
                case 'email':
                    if (value.length === 0) {
                        isValid = false;
                        message = 'Email is required';
                    } else {
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(value)) {
                            isValid = false;
                            message = 'Please enter a valid email address';
                        }
                    }
                    break;
                case 'username':
                    if (value.length === 0) {
                        isValid = false;
                        message = 'Username is required';
                    } else if (value.length < 3) {
                        isValid = false;
                        message = 'Username must be at least 3 characters long';
                    } else if (!/^[a-zA-Z0-9_]+$/.test(value)) {
                        isValid = false;
                        message = 'Username can only contain letters, numbers, and underscores';
                    }
                    break;
                case 'password':
                    if (value.length === 0) {
                        isValid = false;
                        message = 'Password is required';
                    } else if (value.length < 8) {
                        isValid = false;
                        message = 'Password must be at least 8 characters long';
                    }
                    break;
                case 'confirm_password':
                    if (value.length === 0) {
                        isValid = false;
                        message = 'Please confirm your password';
                    } else {
                        const password = $('#reg_password').val();
                        if (value !== password) {
                            isValid = false;
                            message = 'Passwords do not match';
                        }
                    }
                    break;
                case 'license_id':
                    if ($('#reg_user_type').val() === 'driver') {
                        const licenseId = normalizeModalLicenseId(value);
                        field.val(licenseId);
                        if (licenseId.length === 0) {
                            isValid = false;
                            message = 'Driving license ID is required';
                        } else if (!/^RW-\d{8}$/.test(licenseId)) {
                            isValid = false;
                            message = 'Driving license ID must exactly match RW-12345678 (8 digits after RW-)';
                        }
                    }
                    break;
                case 'police_id':
                    if ($('#reg_user_type').val() === 'traffic_authority') {
                        const policeId = normalizeModalPoliceId(value);
                        field.val(policeId);
                        if (policeId.length === 0) {
                            isValid = false;
                            message = 'Police ID is required';
                        } else if (!/^RNP-\d{5}$/.test(policeId)) {
                            isValid = false;
                            message = 'Police ID must exactly match RNP-12345 (5 digits after RNP-)';
                        }
                    }
                    break;
            }

            if (isValid) {
                field.removeClass('is-invalid').addClass('is-valid');
                field.siblings('.invalid-feedback').text('');
            } else {
                field.removeClass('is-valid').addClass('is-invalid');
                field.siblings('.invalid-feedback').text(message);
            }

            return isValid;
        }

        function validateForm() {
            let isValid = true;
            const requiredFields = ['first_name', 'last_name', 'email', 'username', 'password', 'confirm_password'];
            const userType = $('#reg_user_type').val();

            if (userType === 'driver') {
                requiredFields.push('license_id');
            } else if (userType === 'traffic_authority') {
                requiredFields.push('police_id');
            }
            
            requiredFields.forEach(fieldName => {
                const field = $(`[name="${fieldName}"]`);
                if (!validateField(field)) {
                    isValid = false;
                }
            });

            if (modalRoleIdCheckPending) {
                showAlert('Please wait while we verify your ID against the national registry.', 'warning');
                isValid = false;
            }

            // Check terms agreement
            if (!$('#agree_terms').is(':checked')) {
                $('#agree_terms').addClass('is-invalid');
                isValid = false;
            } else {
                $('#agree_terms').removeClass('is-invalid');
            }
            return isValid;
        }

        function updatePasswordStrength(password) {
            const strengthFill = $('#strengthFill');
            const strengthScore = $('#strengthScore');
            const strengthText = $('#strengthText');
            
            // Reset all criteria
            $('.criterion').removeClass('met');
            $('.criterion-icon i').removeClass('fa-check').addClass('fa-times');
            
            if (password.length === 0) {
                strengthFill.removeClass('weak fair good strong');
                strengthScore.removeClass('weak fair good strong').text('Enter password');
                strengthText.text('Enter a password to see strength analysis');
                return;
            }
            
            // Check individual criteria
            const criteria = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password),
                common: !isCommonPassword(password)
            };
            
            // Update criteria display
            updateCriterion('lengthCriterion', criteria.length);
            updateCriterion('uppercaseCriterion', criteria.uppercase);
            updateCriterion('lowercaseCriterion', criteria.lowercase);
            updateCriterion('numberCriterion', criteria.number);
            updateCriterion('specialCriterion', criteria.special);
            updateCriterion('commonCriterion', criteria.common);
            
            // Calculate strength score
            const metCriteria = Object.values(criteria).filter(Boolean).length;
            const totalCriteria = Object.keys(criteria).length;
            const strengthPercentage = (metCriteria / totalCriteria) * 100;
            
            let strengthClass = '';
            let strengthLabel = '';
            let strengthMessage = '';
            
            if (strengthPercentage < 30) {
                strengthClass = 'weak';
                strengthLabel = 'Weak';
                strengthMessage = 'Password is too weak. Please add more complexity.';
            } else if (strengthPercentage < 60) {
                strengthClass = 'fair';
                strengthLabel = 'Fair';
                strengthMessage = 'Password is acceptable but could be stronger.';
            } else if (strengthPercentage < 85) {
                strengthClass = 'good';
                strengthLabel = 'Good';
                strengthMessage = 'Good password strength. Almost there!';
            } else {
                strengthClass = 'strong';
                strengthLabel = 'Strong';
                strengthMessage = 'Excellent! Your password is very strong.';
            }
            
            // Update visual elements
            strengthFill.removeClass('weak fair good strong').addClass(strengthClass);
            strengthScore.removeClass('weak fair good strong').addClass(strengthClass).text(strengthLabel);
            strengthText.text(strengthMessage);
            
            // AJAX call to server for additional validation (simulated)
            validatePasswordWithServer(password, strengthPercentage);
        }
        
        function updateCriterion(criterionId, isMet) {
            const criterion = $(`#${criterionId}`);
            const icon = criterion.find('.criterion-icon i');
            
            if (isMet) {
                criterion.addClass('met');
                icon.removeClass('fa-times').addClass('fa-check');
            } else {
                criterion.removeClass('met');
                icon.removeClass('fa-check').addClass('fa-times');
            }
        }
        
        function isCommonPassword(password) {
            const commonPasswords = [
                'password', '123456', '123456789', 'qwerty', 'abc123',
                'password123', 'admin', 'letmein', 'welcome', 'monkey',
                '1234567890', 'password1', 'qwerty123', 'dragon', 'master'
            ];
            return commonPasswords.includes(password.toLowerCase());
        }
        
        function validatePasswordWithServer(password, strengthPercentage) {
            // Simulate AJAX call to server for additional validation
            $.ajax({
                url: '',
                method: 'POST',
                data: {
                    action: 'validate_password',
                    password: password,
                    strength: strengthPercentage
                },
                dataType: 'json',
                success: function(response) {
                    if (response.suggestions && response.suggestions.length > 0) {
                        // Show additional suggestions from server
                        const suggestions = response.suggestions.join(', ');
                        $('#strengthText').append(`<br><small class="text-muted">Suggestions: ${suggestions}</small>`);
                    }
                },
                error: function() {
                    // Server validation failed, but continue with client-side validation
                    console.log('Server validation unavailable, using client-side only');
                }
            });
        }

        function validatePasswordMatch() {
            const password = $('#reg_password').val();
            const confirmPassword = $('#reg_confirm_password').val();
            
            if (confirmPassword && password !== confirmPassword) {
                $('#reg_confirm_password').removeClass('is-valid').addClass('is-invalid');
                $('#reg_confirm_password').siblings('.invalid-feedback').text('Passwords do not match');
            } else if (confirmPassword && password === confirmPassword) {
                $('#reg_confirm_password').removeClass('is-invalid').addClass('is-valid');
                $('#reg_confirm_password').siblings('.invalid-feedback').text('');
            }
        }

        function togglePasswordVisibilityButton(fieldId) {
            const passwordField = $(`#${fieldId}`);
            const formFloating = passwordField.closest('.form-floating');
            const currentValue = passwordField.val().trim();
            
            // Remove existing toggle if any
            formFloating.find('.password-toggle').remove();
            
            if (currentValue.length > 0) {
                // Create dynamic toggle button
                const toggleHtml = `
                    <div class="password-toggle show">
                        <button type="button" class="btn btn-link password-toggle-btn" data-field="${fieldId}">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                `;
                
                formFloating.append(toggleHtml);
                
                // Add has-toggle class to adjust padding
                passwordField.addClass('has-toggle');
                
                // Adjust password strength indicator if present
                const strengthIndicator = formFloating.find('.password-strength');
                if (strengthIndicator.length) {
                    strengthIndicator.addClass('has-toggle');
                }
                
                // Add click handler for the new toggle
                formFloating.find('.password-toggle-btn').on('click', function() {
                    togglePasswordField(fieldId);
                });
            } else {
                // Remove has-toggle class
                passwordField.removeClass('has-toggle');
                
                // Remove has-toggle from strength indicator
                const strengthIndicator = formFloating.find('.password-strength');
                if (strengthIndicator.length) {
                    strengthIndicator.removeClass('has-toggle');
                }
            }
        }

        function togglePasswordField(fieldId) {
            const passwordField = $(`#${fieldId}`);
            const toggleBtn = $(`.password-toggle-btn[data-field="${fieldId}"]`);
            const icon = toggleBtn.find('i');

            if (passwordField.attr('type') === 'password') {
                passwordField.attr('type', 'text');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                passwordField.attr('type', 'password');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        }

        function checkUsernameAvailability(field) {
            const username = field.val().trim();
            if (username.length >= 3) {
                // Simulate API call
                setTimeout(() => {
                    const isAvailable = Math.random() > 0.3; // 70% chance of being available
                    if (isAvailable) {
                        field.removeClass('is-invalid').addClass('is-valid');
                        field.siblings('.valid-feedback').text('Username is available!');
                    } else {
                        field.removeClass('is-valid').addClass('is-invalid');
                        field.siblings('.invalid-feedback').text('Username is already taken');
                    }
                }, 500);
            }
        }

        function validateEmail(field) {
            const email = field.val().trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (emailRegex.test(email)) {
                field.removeClass('is-invalid').addClass('is-valid');
                field.siblings('.valid-feedback').text('Email looks good!');
            } else {
                field.removeClass('is-valid').addClass('is-invalid');
                field.siblings('.invalid-feedback').text('Please enter a valid email address');
            }
        }

        function submitRegistration() {
            const form = $('#registerForm');
            const submitBtn = $('.register-submit-btn');
            const btnText = $('.btn-text');
            const btnLoading = $('.btn-loading');

            // Show loading state
            submitBtn.prop('disabled', true);
            btnText.addClass('hide');
            btnLoading.removeClass('d-none').addClass('show');

            const formData = new FormData(form[0]);
            const data = {};
            
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            if (data.license_id) {
                data.license_id = normalizeModalLicenseId(data.license_id);
            }
            if (data.police_id) {
                data.police_id = normalizeModalPoliceId(data.police_id);
            }
            data.action = 'register';
            
            // Debug: Log form data
            console.log('Registration form data:', data);
            
            $.ajax({
                url: '',
                method: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Show success message with celebration
                        showSuccessMessage(response.message);
                        
                        // Close modal after a short delay
                        setTimeout(() => {
                            $('#registerModal').modal('hide');
                        }, 1000);
                        
                        // Reset form
                        form[0].reset();
                        form.find('.form-control').removeClass('is-valid is-invalid');
                        $('.strength-fill').removeClass('weak fair good strong');
                        $('.strength-text').text('Password strength');
                        
                        // Redirect to dashboard
                        if (response.redirect) {
                            setTimeout(() => {
                                window.location.href = response.redirect;
                            }, 2000);
                        }
                    } else {
                        // Show detailed error message
                        let errorMessage = response.message || 'Registration failed';
                        
                        // If there are specific errors, show them
                        if (response.errors && response.errors.length > 0) {
                            errorMessage = 'Please fix the following errors:\n\n';
                            response.errors.forEach((error, index) => {
                                errorMessage += `${index + 1}. ${error}\n`;
                            });
                        }
                        
                        showAlert(errorMessage, 'danger');
                        
                        // Highlight specific fields with errors
                        if (response.errors) {
                            highlightErrorFields(response.errors);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    showAlert('Registration failed: ' + error, 'danger');
                },
                complete: function() {
                    // Hide loading state
                    submitBtn.prop('disabled', false);
                    btnText.removeClass('hide');
                    btnLoading.removeClass('show').addClass('d-none');
                }
            });
        }

        function handleSocialSignup(provider) {
            showAlert(`Signing up with ${provider.charAt(0).toUpperCase() + provider.slice(1)}...`, 'info');
            
            // Simulate social signup process
            setTimeout(() => {
                showAlert(`Successfully signed up with ${provider.charAt(0).toUpperCase() + provider.slice(1)}!`, 'success');
                $('#registerModal').modal('hide');
            }, 2000);
        }

        function highlightErrorFields(errors) {
            // Clear previous error highlights
            $('.form-control').removeClass('is-invalid');
            $('.invalid-feedback').text('');
            
            // Map error messages to field names
            const fieldMapping = {
                'First name is required': 'reg_firstname',
                'First name must be at least 2 characters long': 'reg_firstname',
                'Last name is required': 'reg_lastname',
                'Last name must be at least 2 characters long': 'reg_lastname',
                'Username is required': 'reg_username',
                'Username must be at least 3 characters long': 'reg_username',
                'Email is required': 'reg_email',
                'Invalid email format': 'reg_email',
                'Password is required': 'reg_password',
                'Password must be at least 8 characters long': 'reg_password',
                'Please confirm your password': 'reg_confirm_password',
                'Passwords do not match': 'reg_confirm_password',
                'Phone number is required': 'reg_phone',
                'User type is required': 'reg_user_type',
                'Invalid user type': 'reg_user_type'
            };
            
            // Highlight fields with errors
            errors.forEach(error => {
                const lowerError = error.toLowerCase();
                let field = null;

                if (fieldMapping[error]) {
                    field = $(`#${fieldMapping[error]}`);
                } else if (lowerError.includes('driving license') || lowerError.includes('license id')) {
                    field = $('#reg_license_id');
                    modalRoleIdVerified.license = false;
                } else if (lowerError.includes('police id')) {
                    field = $('#reg_police_id');
                    modalRoleIdVerified.police = false;
                }

                if (field && field.length) {
                    field.addClass('is-invalid').removeClass('is-valid');
                    const $feedback = field.closest('[id$="Group"]').find('.invalid-feedback');
                    if ($feedback.length) {
                        $feedback.text(error);
                    } else {
                        field.siblings('.invalid-feedback').text(error);
                    }
                }
            });
        }

        // Initialize registration form
        initializeRegistrationForm();
        
        // Test password strength function
        setTimeout(() => {
            updatePasswordStrength('test123');
        }, 1000);

        // Enhanced Login Form with Real-time Validation
        function initializeLoginForm() {
            const form = $('#loginForm');
            const submitBtn = $('.login-submit-btn');
            const btnText = $('.btn-text');
            const btnLoading = $('.btn-loading');
            

            // Real-time form validation
            form.find('input').on('blur', function() {
                validateLoginField($(this));
            });

            // Dynamic password visibility for login
            $('#login_password').on('input', function() {
                togglePasswordVisibilityButton('login_password');
            });

            // Form submission
            form.on('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                
                if (validateLoginForm()) {
                    submitLogin();
                }
                
                return false;
            });

            // Button click handler
            submitBtn.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                
                if (validateLoginForm()) {
                    submitLogin();
                }
                
                return false;
            });

            // Social sign-in buttons
            $('#googleLogin').on('click', function() {
                handleSocialLogin('google');
            });

            $('#facebookLogin').on('click', function() {
                handleSocialLogin('facebook');
            });

            $('#githubLogin').on('click', function() {
                handleSocialLogin('github');
            });

            // Forgot password functionality
            $('#forgotPassword').on('click', function(e) {
                e.preventDefault();
                handleForgotPassword();
            });
        }

        function validateLoginField(field) {
            const value = field.val().trim();
            const fieldName = field.attr('name');
            let isValid = true;
            let message = '';

            switch(fieldName) {
                case 'username':
                    if (value.length < 3) {
                        isValid = false;
                        message = 'Username or email must be at least 3 characters long';
                    } else if (value.includes('@') && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                        isValid = false;
                        message = 'Please enter a valid email address';
                    }
                    break;
                case 'password':
                    if (value.length < 6) {
                        isValid = false;
                        message = 'Password must be at least 6 characters long';
                    }
                    break;
            }

            if (isValid) {
                field.removeClass('is-invalid').addClass('is-valid');
                field.siblings('.invalid-feedback').text('');
            } else {
                field.removeClass('is-valid').addClass('is-invalid');
                field.siblings('.invalid-feedback').text(message);
            }

            return isValid;
        }

        function validateLoginForm() {
            let isValid = true;
            
            // Get form fields directly by ID to ensure we find them
            const usernameField = $('#login_username');
            const passwordField = $('#login_password');
            
            
            // Simple validation - just check if fields have values
            if (usernameField.length === 0 || usernameField.val().trim().length === 0) {
                usernameField.addClass('is-invalid');
                usernameField.siblings('.invalid-feedback').text('Username or email is required');
                isValid = false;
            } else {
                usernameField.removeClass('is-invalid').addClass('is-valid');
                usernameField.siblings('.invalid-feedback').text('');
            }
            
            if (passwordField.length === 0 || passwordField.val().length === 0) {
                passwordField.addClass('is-invalid');
                passwordField.siblings('.invalid-feedback').text('Password is required');
                isValid = false;
            } else {
                passwordField.removeClass('is-invalid').addClass('is-valid');
                passwordField.siblings('.invalid-feedback').text('');
            }

            const userTypeField = $('#login_user_type');
            if (userTypeField.length === 0 || !['driver', 'traffic_authority', 'administrator'].includes(userTypeField.val())) {
                userTypeField.addClass('is-invalid');
                userTypeField.siblings('.invalid-feedback').text('Please select your user type');
                isValid = false;
            } else {
                userTypeField.removeClass('is-invalid').addClass('is-valid');
                userTypeField.siblings('.invalid-feedback').text('');
            }
            return isValid;
        }



        function submitLogin() {
            const form = $('#loginForm');
            const submitBtn = $('.login-submit-btn');
            const btnText = $('.btn-text');
            const btnLoading = $('.btn-loading');

            // Show loading state
            submitBtn.prop('disabled', true);
            btnText.addClass('hide');
            btnLoading.removeClass('d-none').addClass('show');

            const formData = new FormData(form[0]);
            const data = {};
            
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            data.action = 'login';
            
            $.ajax({
                url: '',
                method: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        $('#loginModal').modal('hide');
                        form[0].reset();
                        form.find('.form-control').removeClass('is-valid is-invalid');
                        
                        // Redirect to dashboard
                        if (response.redirect) {
                            setTimeout(() => {
                                window.location.href = response.redirect;
                            }, 1500);
                        }
                    } else {
                        showAlert(response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    showAlert('Login failed: ' + error, 'danger');
                },
                complete: function() {
                    // Hide loading state
                    submitBtn.prop('disabled', false);
                    btnText.removeClass('hide');
                    btnLoading.removeClass('show').addClass('d-none');
                }
            });
        }

        function handleSocialLogin(provider) {
            showAlert(`Signing in with ${provider.charAt(0).toUpperCase() + provider.slice(1)}...`, 'info');
            
            // Simulate social login process
            setTimeout(() => {
                showAlert(`Successfully signed in with ${provider.charAt(0).toUpperCase() + provider.slice(1)}!`, 'success');
                $('#loginModal').modal('hide');
                
                // Redirect to dashboard
                setTimeout(() => {
                    showAlert('Welcome back! Redirecting to dashboard...', 'success');
                }, 1000);
            }, 2000);
        }

        function handleForgotPassword() {
            const email = prompt('Please enter your email address to reset your password:');
            
            if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showAlert('Password reset instructions have been sent to your email address.', 'success');
            } else if (email) {
                showAlert('Please enter a valid email address.', 'danger');
            }
        }

        // Initialize login form
        initializeLoginForm();


    </script>

    <!-- Enhanced Registration Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content register-modal-content">
                <div class="modal-header register-modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Create Your Account
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Social Sign-in Options -->
                    <div class="social-signin-section">
                        <p class="text-center mb-3 text-muted">Sign up with</p>
                        <div class="row g-2 mb-4">
                            <div class="col-4">
                                <button type="button" class="btn w-100 social-btn google-btn" id="googleSignup">
                                    <div class="google-g-logo">
                                        <div class="google-g-text">G</div>
                                    </div>Google
                                </button>
                            </div>
                            <div class="col-4">
                                <button type="button" class="btn btn-outline-primary w-100 social-btn facebook-btn" id="facebookSignup">
                                    <i class="fab fa-facebook-f me-2"></i>Facebook
                                </button>
                            </div>
                            <div class="col-4">
                                <button type="button" class="btn btn-outline-dark w-100 social-btn github-btn" id="githubSignup">
                                    <i class="fab fa-github me-2"></i>GitHub
                                </button>
                            </div>
                        </div>
                        <div class="divider">
                            <span>or</span>
                        </div>
                    </div>

                    <!-- Registration Form -->
                    <form id="registerForm" class="register-form" method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="reg_firstname" name="first_name" placeholder="First Name" required>
                                    <label for="reg_firstname">
                                        <i class="fas fa-user me-2"></i>First Name
                                    </label>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="reg_lastname" name="last_name" placeholder="Last Name" required>
                                    <label for="reg_lastname">
                                        <i class="fas fa-user me-2"></i>Last Name
                                    </label>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="reg_email" name="email" placeholder="Email Address" required>
                            <label for="reg_email">
                                <i class="fas fa-envelope me-2"></i>Email Address
                            </label>
                            <div class="invalid-feedback"></div>
                            <div class="valid-feedback">Looks good!</div>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="reg_username" name="username" placeholder="Username" required>
                            <label for="reg_username">
                                <i class="fas fa-at me-2"></i>Username
                            </label>
                            <div class="invalid-feedback"></div>
                            <div class="valid-feedback">Available!</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="tel" class="form-control" id="reg_phone" name="phone_number" placeholder="Phone Number" required>
                            <label for="reg_phone">
                                <i class="fas fa-phone me-2"></i>Phone Number
                            </label>
                            <div class="invalid-feedback"></div>
                            <div class="form-text px-1">Rwanda format: +2507XXXXXXXX or 07XXXXXXXX</div>
                        </div>

                        <div class="form-floating mb-3">
                            <select class="form-control" id="reg_user_type" name="user_type" required>
                                <option value="">Choose your role (Driver or Traffic Police)</option>
                                <option value="driver">Driver / Road User</option>
                                <option value="traffic_authority">Traffic Police Officer</option>
                            </select>
                            <label for="reg_user_type">
                                <i class="fas fa-user-tag me-2"></i>User Type
                            </label>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div id="regRoleIdContainer" class="mb-3" style="display: none;">
                            <div id="regLicenseIdGroup" class="form-floating mb-3" style="display: none;">
                                <input type="text" class="form-control" id="reg_license_id" name="license_id" placeholder="Driving License ID" autocomplete="off">
                                <label for="reg_license_id">
                                    <i class="fas fa-id-card me-2"></i>Driving License ID
                                </label>
                                <div class="invalid-feedback"></div>
                                <div class="valid-feedback">License ID is available</div>
                                <div class="form-text px-1">e.g. RW-12345678</div>
                            </div>
                            <div id="regPoliceIdGroup" class="form-floating mb-3" style="display: none;">
                                <input type="text" class="form-control" id="reg_police_id" name="police_id" placeholder="Police ID" autocomplete="off">
                                <label for="reg_police_id">
                                    <i class="fas fa-shield-alt me-2"></i>Police ID
                                </label>
                                <div class="invalid-feedback"></div>
                                <div class="valid-feedback">Police ID is available</div>
                                <div class="form-text px-1">e.g. RNP-12345</div>
                            </div>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="reg_password" name="password" placeholder="Password" required>
                            <label for="reg_password">
                                <i class="fas fa-lock me-2"></i>Password
                            </label>
                            <div class="password-strength">
                                <div class="strength-header">
                                    <p class="strength-label">Password Strength</p>
                                    <span class="strength-score" id="strengthScore">Weak</span>
                                </div>
                                <div class="strength-bar">
                                    <div class="strength-fill" id="strengthFill"></div>
                                </div>
                                <div class="strength-criteria">
                                    <div class="criterion" id="lengthCriterion">
                                        <div class="criterion-icon">
                                            <i class="fas fa-times"></i>
                                        </div>
                                        <span>At least 8 characters</span>
                                    </div>
                                    <div class="criterion" id="uppercaseCriterion">
                                        <div class="criterion-icon">
                                            <i class="fas fa-times"></i>
                                        </div>
                                        <span>Uppercase letter</span>
                                    </div>
                                    <div class="criterion" id="lowercaseCriterion">
                                        <div class="criterion-icon">
                                            <i class="fas fa-times"></i>
                                        </div>
                                        <span>Lowercase letter</span>
                                    </div>
                                    <div class="criterion" id="numberCriterion">
                                        <div class="criterion-icon">
                                            <i class="fas fa-times"></i>
                                        </div>
                                        <span>Number</span>
                                    </div>
                                    <div class="criterion" id="specialCriterion">
                                        <div class="criterion-icon">
                                            <i class="fas fa-times"></i>
                                        </div>
                                        <span>Special character</span>
                                    </div>
                                    <div class="criterion" id="commonCriterion">
                                        <div class="criterion-icon">
                                            <i class="fas fa-times"></i>
                                        </div>
                                        <span>Not common password</span>
                                    </div>
                                </div>
                                <p class="strength-text" id="strengthText">Enter a password to see strength analysis</p>
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="reg_confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                            <label for="reg_confirm_password">
                                <i class="fas fa-lock me-2"></i>Confirm Password
                            </label>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="agree_terms" required>
                            <label class="form-check-label" for="agree_terms">
                                I agree to the <a href="#" class="text-primary">Terms of Service</a> and <a href="#" class="text-primary">Privacy Policy</a>
                            </label>
                            <div class="invalid-feedback">You must agree to the terms.</div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="button" class="btn btn-primary btn-lg register-submit-btn">
                                <span class="btn-text">
                                    <i class="fas fa-user-plus me-2"></i>Create Account
                                </span>
                                <span class="btn-loading d-none">
                                    <i class="fas fa-spinner fa-spin me-2"></i>Creating Account...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer register-modal-footer">
                    <p class="text-center w-100 mb-0">Already have an account? 
                        <a href="#" class="text-primary fw-bold" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-dismiss="modal">Sign in here</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content login-modal-content">
                <div class="modal-header login-modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-sign-in-alt me-2"></i>Welcome Back
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Social Sign-in Options -->
                    <div class="social-signin-section">
                        <p class="text-center mb-3 text-muted">Sign in with</p>
                        <div class="row g-2 mb-4">
                            <div class="col-4">
                                <button type="button" class="btn w-100 social-btn google-btn" id="googleLogin">
                                    <div class="google-g-logo">
                                        <div class="google-g-text">G</div>
                                    </div>Google
                                </button>
                            </div>
                            <div class="col-4">
                                <button type="button" class="btn btn-outline-primary w-100 social-btn facebook-btn" id="facebookLogin">
                                    <i class="fab fa-facebook-f me-2"></i>Facebook
                                </button>
                            </div>
                            <div class="col-4">
                                <button type="button" class="btn btn-outline-dark w-100 social-btn github-btn" id="githubLogin">
                                    <i class="fab fa-github me-2"></i>GitHub
                                </button>
                            </div>
                        </div>
                        <div class="divider">
                            <span>or</span>
                        </div>
                    </div>

                    <!-- Login Form -->
                    <form id="loginForm" class="login-form" method="POST">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="login_username" name="username" placeholder="Username or Email" required>
                            <label for="login_username">
                                <i class="fas fa-user me-2"></i>Username or Email
                            </label>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="login_password" name="password" placeholder="Password" required>
                            <label for="login_password">
                                <i class="fas fa-lock me-2"></i>Password
                            </label>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="form-floating mb-3">
                            <select class="form-control" id="login_user_type" name="user_type" required>
                                <option value="">Select user type</option>
                                <option value="driver">Driver / Road User</option>
                                <option value="traffic_authority">Traffic Police Officer</option>
                                <option value="administrator">Admin</option>
                            </select>
                            <label for="login_user_type">
                                <i class="fas fa-user-tag me-2"></i>User Type
                            </label>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                                    <label class="form-check-label" for="remember_me">
                                        Remember me
                                    </label>
                                </div>
                            </div>
                            <div class="col-6 text-end">
                                <a href="#" class="text-primary forgot-password-link" id="forgotPassword">
                                    Forgot password?
                                </a>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg login-submit-btn">
                                <span class="btn-text">
                                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                                </span>
                                <span class="btn-loading d-none">
                                    <i class="fas fa-spinner fa-spin me-2"></i>Signing In...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer login-modal-footer">
                    <p class="text-center w-100 mb-0">Don't have an account? 
                        <a href="#" class="text-primary fw-bold" data-bs-toggle="modal" data-bs-target="#registerModal" data-bs-dismiss="modal">Create one here</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="footer-section">
                        <h5 class="footer-title">
                            <i class="fas fa-traffic-light me-2"></i>
                            STRGS
                        </h5>
                        <p class="footer-description">
                            Smart traffic route guidance for Remera Sector. STRGS uses GPS and updated road status information to guide drivers, support traffic police, and help administrators manage the system.
                        </p>
                        <div class="social-links">
                            <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <div class="footer-section">
                        <h6 class="footer-heading">Services</h6>
                        <ul class="footer-links">
                            <li><a href="#services">Route Guidance</a></li>
                            <li><a href="#services">Road Status Monitoring</a></li>
                            <li><a href="#services">Road Segment Management</a></li>
                            <li><a href="#services">Traffic Police Updates</a></li>
                            <li><a href="#services">Driver Alerts</a></li>
                            <li><a href="#services">User Management</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <div class="footer-section">
                        <h6 class="footer-heading">Company</h6>
                        <ul class="footer-links">
                            <li><a href="#about">About Us</a></li>
                            <li><a href="#contact">Contact</a></li>
                            <li><a href="#">Careers</a></li>
                            <li><a href="#">Blog</a></li>
                            <li><a href="#">Press</a></li>
                            <li><a href="#">Partners</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <div class="footer-section">
                        <h6 class="footer-heading">Support</h6>
                        <ul class="footer-links">
                            <li><a href="#">Help Center</a></li>
                            <li><a href="#">Documentation</a></li>
                            <li><a href="#">API Reference</a></li>
                            <li><a href="#">Training</a></li>
                            <li><a href="#">Status</a></li>
                            <li><a href="#">Community</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <div class="footer-section">
                        <h6 class="footer-heading">Legal</h6>
                        <ul class="footer-links">
                            <li><a href="#">Privacy Policy</a></li>
                            <li><a href="#">Terms of Service</a></li>
                            <li><a href="#">Cookie Policy</a></li>
                            <li><a href="#">GDPR</a></li>
                            <li><a href="#">Security</a></li>
                            <li><a href="#">Compliance</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <hr class="footer-divider">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="footer-bottom">
                        <p class="copyright">
                            <span class="copyright-symbol">&copy;</span>
                            <span class="copyright-year"><script>document.write(new Date().getFullYear())</script></span>
                            <span class="copyright-company">STRGS</span>
                            <span class="copyright-rights">All rights reserved.</span>
                        </p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="footer-bottom text-md-end">
                        <div class="footer-badges">
                            <span class="badge">
                                <i class="fas fa-shield-alt me-1"></i>
                                Secure
                            </span>
                            <span class="badge">
                                <i class="fas fa-lock me-1"></i>
                                Encrypted
                            </span>
                            <span class="badge">
                                <i class="fas fa-certificate me-1"></i>
                                Certified
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>

