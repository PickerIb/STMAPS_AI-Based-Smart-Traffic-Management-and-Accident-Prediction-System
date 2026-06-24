<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'smart_traffic_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application Configuration
define('APP_NAME', 'Smart Traffic Route Guidance System');
define('APP_ACRONYM', 'STRGS');
define('APP_SUBTITLE', 'A Case of Remera Sector');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/STMAPS');

// ML Service Configuration
define('ML_SERVICE_URL', 'http://localhost:5000');
define('ML_SERVICE_TIMEOUT', 30);
// Toggle to control whether PHP should try to use the local Python engine
// Set to false to force fast PHP-only predictions
if (!defined('USE_PYTHON_ENGINE')) {
    define('USE_PYTHON_ENGINE', false);
}

// Security Configuration
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600); // 1 hour

// File Upload Configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['csv', 'xlsx', 'xls']);

// Pagination Configuration
define('RECORDS_PER_PAGE', 20);

// Email Configuration (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('FROM_EMAIL', 'noreply@smarttraffic.local');
define('FROM_NAME', 'STRGS');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone — Remera Sector, Kigali, Rwanda (Central Africa Time, UTC+2)
define('APP_TIMEZONE', 'Africa/Kigali');
date_default_timezone_set(APP_TIMEZONE);

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS

// Database Connection Class
class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("SET time_zone = '+02:00'");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}

// Authentication Functions
function requireLogin() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        // Clear any existing session data
        session_destroy();
        // Redirect to login page
        header('Location: login.php');
        exit();
    }
}

function requireGuest() {
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        // User is already logged in, redirect to dashboard
        header('Location: dashboard.php');
        exit();
    }
}

function requireDriver() {
    requireLogin();
    if (($_SESSION['user_type'] ?? '') !== 'driver') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Driver access only']);
        exit();
    }
}

function requireAuthority() {
    requireLogin();
    if (($_SESSION['user_type'] ?? '') !== 'traffic_authority') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Traffic police access only']);
        exit();
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getUserTypeLabel($userType) {
    $labels = [
        'driver' => 'Driver / Road User',
        'traffic_authority' => 'Traffic Police Officer',
        'administrator' => 'Admin',
    ];
    return $labels[$userType] ?? ucwords(str_replace('_', ' ', (string)$userType));
}

function resolveUserTypeFromUser($user) {
    if ($user->role === 'admin') {
        return 'administrator';
    }
    if ($user->role === 'authority') {
        return 'traffic_authority';
    }
    $storedType = $user->user_type ?? 'driver';
    return ($storedType === '' || $storedType === null) ? 'driver' : $storedType;
}

function logout() {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

// Utility Functions
class Utils {
    public static function generateCSRFToken() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    public static function validateCSRFToken($token) {
        return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    public static function sanitizeInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    public static function formatNumber($number, $decimals = 2) {
        return number_format($number, $decimals);
    }
    
    public static function formatPercentage($number, $decimals = 1) {
        return number_format($number * 100, $decimals) . '%';
    }
    
    public static function logError($message, $file = 'error.log') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        file_put_contents($file, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

// API Response Helper
class APIResponse {
    public static function success($data = null, $message = 'Success') {
        return json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ]);
    }
    
    public static function error($message = 'Error', $code = 400, $data = null) {
        http_response_code($code);
        return json_encode([
            'success' => false,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ]);
    }
    
    public static function validationError($errors) {
        return self::error('Validation failed', 422, ['errors' => $errors]);
    }
}

// Model Classes
class User {
    private $conn;
    private $table_name = "users";
    
    public $id;
    public $first_name;
    public $last_name;
    public $username;
    public $email;
    public $phone_number;
    public $user_type;
    public $license_id;
    public $police_id;
    public $password_hash;
    public $role;
    public $is_active;
    public $last_login;
    public $updated_at;
    public $profile_picture;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (first_name, last_name, username, email, phone_number, user_type, license_id, police_id, password_hash, role, is_active) 
                  VALUES (:first_name, :last_name, :username, :email, :phone_number, :user_type, :license_id, :police_id, :password_hash, :role, :is_active)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':first_name', $this->first_name);
        $stmt->bindParam(':last_name', $this->last_name);
        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':phone_number', $this->phone_number);
        $stmt->bindParam(':user_type', $this->user_type);
        $stmt->bindParam(':license_id', $this->license_id);
        $stmt->bindParam(':police_id', $this->police_id);
        $stmt->bindParam(':password_hash', $this->password_hash);
        $stmt->bindParam(':role', $this->role);
        $stmt->bindParam(':is_active', $this->is_active);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }
    
    public function findByEmail($email) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            $this->id = $row['id'];
            $this->first_name = $row['first_name'] ?? null;
            $this->last_name = $row['last_name'] ?? null;
            $this->username = $row['username'];
            $this->email = $row['email'];
            $this->phone_number = $row['phone_number'] ?? null;
            $this->user_type = $row['user_type'] ?? null;
            $this->license_id = $row['license_id'] ?? null;
            $this->police_id = $row['police_id'] ?? null;
            $this->password_hash = $row['password_hash'];
            $this->role = $row['role'];
            $this->is_active = $row['is_active'];
            $this->last_login = $row['last_login'];
            $this->updated_at = $row['updated_at'];
            $this->profile_picture = $row['profile_picture'] ?? null;
            return true;
        }
        return false;
    }
    
    public function findByUsername($username) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE username = :username LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            $this->id = $row['id'];
            $this->first_name = $row['first_name'] ?? null;
            $this->last_name = $row['last_name'] ?? null;
            $this->username = $row['username'];
            $this->email = $row['email'];
            $this->phone_number = $row['phone_number'] ?? null;
            $this->user_type = $row['user_type'] ?? null;
            $this->license_id = $row['license_id'] ?? null;
            $this->police_id = $row['police_id'] ?? null;
            $this->password_hash = $row['password_hash'];
            $this->role = $row['role'];
            $this->is_active = $row['is_active'];
            $this->last_login = $row['last_login'];
            $this->updated_at = $row['updated_at'];
            $this->profile_picture = $row['profile_picture'] ?? null;
            return true;
        }
        return false;
    }
    
    public function updateLastLogin() {
        $query = "UPDATE " . $this->table_name . " SET last_login = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }
    
    public function verifyPassword($password) {
        return password_verify($password, $this->password_hash);
    }
    
    public function emailExists($email) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
    
    public function usernameExists($username) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE username = :username LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function phoneExists($phone_number) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE phone_number = :phone_number LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':phone_number', $phone_number);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function licenseIdExists($license_id) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE license_id = :license_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':license_id', $license_id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function policeIdExists($police_id) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE police_id = :police_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':police_id', $police_id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function findById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $this->hydrateFromRow($row);
        return true;
    }

    private function hydrateFromRow(array $row) {
        $this->id = $row['id'];
        $this->first_name = $row['first_name'] ?? null;
        $this->last_name = $row['last_name'] ?? null;
        $this->username = $row['username'];
        $this->email = $row['email'];
        $this->phone_number = $row['phone_number'] ?? null;
        $this->user_type = $row['user_type'] ?? null;
        $this->license_id = $row['license_id'] ?? null;
        $this->police_id = $row['police_id'] ?? null;
        $this->profile_picture = $row['profile_picture'] ?? null;
        $this->password_hash = $row['password_hash'];
        $this->role = $row['role'];
        $this->is_active = $row['is_active'];
        $this->last_login = $row['last_login'];
        $this->updated_at = $row['updated_at'];
    }

    public function updateProfile($first_name, $last_name, $email, $phone_number) {
        $query = "UPDATE " . $this->table_name . "
                  SET first_name = :first_name, last_name = :last_name,
                      email = :email, phone_number = :phone_number, updated_at = NOW()
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone_number', $phone_number);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function updateProfilePicture($path) {
        $query = "UPDATE " . $this->table_name . " SET profile_picture = :pic, updated_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':pic', $path);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}

class IdRegistry {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public static function normalizeLicenseId($license_id) {
        return strtoupper(trim((string)$license_id));
    }

    public static function normalizePoliceId($police_id) {
        return strtoupper(trim((string)$police_id));
    }

    public function licenseIdRegistered($license_id) {
        $license_id = self::normalizeLicenseId($license_id);
        $query = "SELECT id FROM api_driver_licenses WHERE license_id = :license_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':license_id', $license_id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function policeIdRegistered($police_id) {
        $police_id = self::normalizePoliceId($police_id);
        $query = "SELECT id FROM api_police_ids WHERE police_id = :police_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':police_id', $police_id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function findLicense($license_id) {
        $license_id = self::normalizeLicenseId($license_id);
        $query = "SELECT id, name, license_id FROM api_driver_licenses WHERE license_id = :license_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':license_id', $license_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findPolice($police_id) {
        $police_id = self::normalizePoliceId($police_id);
        $query = "SELECT id, name, police_id FROM api_police_ids WHERE police_id = :police_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':police_id', $police_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function validateLicenseForRegistration($license_id, User $user) {
        $license_id = self::normalizeLicenseId($license_id);

        if ($license_id === '') {
            return [
                'valid' => false,
                'reason' => 'required',
                'message' => 'Driving license ID is required for drivers',
                'id' => ''
            ];
        }

        if (!preg_match('/^RW-\d{8}$/', $license_id)) {
            return [
                'valid' => false,
                'reason' => 'invalid_format',
                'message' => 'Driving license ID must exactly match the format RW-12345678 (8 digits after RW-)',
                'id' => $license_id
            ];
        }

        if (!$this->licenseIdRegistered($license_id)) {
            return [
                'valid' => false,
                'reason' => 'not_in_registry',
                'message' => 'This driving license ID was not found in the national driver registry. Enter the exact number shown on your license.',
                'id' => $license_id
            ];
        }

        if ($user->licenseIdExists($license_id)) {
            return [
                'valid' => false,
                'reason' => 'already_registered',
                'message' => 'This driving license ID is already linked to another STRGS account.',
                'id' => $license_id
            ];
        }

        return [
            'valid' => true,
            'reason' => 'valid',
            'message' => 'Driving license ID verified against the national registry',
            'id' => $license_id
        ];
    }

    public function validatePoliceForRegistration($police_id, User $user) {
        $police_id = self::normalizePoliceId($police_id);

        if ($police_id === '') {
            return [
                'valid' => false,
                'reason' => 'required',
                'message' => 'Police ID is required for traffic police officers',
                'id' => ''
            ];
        }

        if (!preg_match('/^RNP-\d{5}$/', $police_id)) {
            return [
                'valid' => false,
                'reason' => 'invalid_format',
                'message' => 'Police ID must exactly match the format RNP-12345 (5 digits after RNP-)',
                'id' => $police_id
            ];
        }

        if (!$this->policeIdRegistered($police_id)) {
            return [
                'valid' => false,
                'reason' => 'not_in_registry',
                'message' => 'This police ID was not found in the Rwanda National Police registry. Enter your exact service ID.',
                'id' => $police_id
            ];
        }

        if ($user->policeIdExists($police_id)) {
            return [
                'valid' => false,
                'reason' => 'already_registered',
                'message' => 'This police ID is already linked to another STRGS account.',
                'id' => $police_id
            ];
        }

        return [
            'valid' => true,
            'reason' => 'valid',
            'message' => 'Police ID verified against the national registry',
            'id' => $police_id
        ];
    }
}

class RoadSegment {
    private $conn;
    private $table_name = "road_segments";
    
    public $id;
    public $name;
    public $zone;
    public $start_lat;
    public $start_lng;
    public $end_lat;
    public $end_lng;
    public $speed_limit_kmh;
    public $lanes;
    public $created_at;
    public $updated_at;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (name, zone, start_lat, start_lng, end_lat, end_lng, speed_limit_kmh, lanes) 
                  VALUES (:name, :zone, :start_lat, :start_lng, :end_lat, :end_lng, :speed_limit_kmh, :lanes)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':zone', $this->zone);
        $stmt->bindParam(':start_lat', $this->start_lat);
        $stmt->bindParam(':start_lng', $this->start_lng);
        $stmt->bindParam(':end_lat', $this->end_lat);
        $stmt->bindParam(':end_lng', $this->end_lng);
        $stmt->bindParam(':speed_limit_kmh', $this->speed_limit_kmh);
        $stmt->bindParam(':lanes', $this->lanes);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }
    
    public function read() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }
    
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            $this->name = $row['name'];
            $this->zone = $row['zone'];
            $this->start_lat = $row['start_lat'];
            $this->start_lng = $row['start_lng'];
            $this->end_lat = $row['end_lat'];
            $this->end_lng = $row['end_lng'];
            $this->speed_limit_kmh = $row['speed_limit_kmh'];
            $this->lanes = $row['lanes'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        return false;
    }
    
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET name = :name, zone = :zone, start_lat = :start_lat, start_lng = :start_lng,
                      end_lat = :end_lat, end_lng = :end_lng, speed_limit_kmh = :speed_limit_kmh, lanes = :lanes
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':zone', $this->zone);
        $stmt->bindParam(':start_lat', $this->start_lat);
        $stmt->bindParam(':start_lng', $this->start_lng);
        $stmt->bindParam(':end_lat', $this->end_lat);
        $stmt->bindParam(':end_lng', $this->end_lng);
        $stmt->bindParam(':speed_limit_kmh', $this->speed_limit_kmh);
        $stmt->bindParam(':lanes', $this->lanes);
        $stmt->bindParam(':id', $this->id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }
    
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }
}

// ML Service Integration
class MLService {
    private $base_url;
    private $timeout;
    
    public function __construct() {
        $this->base_url = ML_SERVICE_URL;
        $this->timeout = ML_SERVICE_TIMEOUT;
    }
    
    public function trainModel() {
        return $this->makeRequest('/train', 'POST');
    }
    
    public function predict($data) {
        return $this->makeRequest('/predict', 'POST', $data);
    }
    
    public function getModelInfo() {
        return $this->makeRequest('/model/info', 'GET');
    }
    
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->base_url . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error: " . $error);
        }
        
        if ($http_code >= 400) {
            throw new Exception("HTTP Error: " . $http_code);
        }
        
        return json_decode($response, true);
    }
}
?>


