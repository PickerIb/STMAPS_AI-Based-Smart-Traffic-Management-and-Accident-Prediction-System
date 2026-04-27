<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'smart_traffic_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application Configuration
define('APP_NAME', 'AI-Based Smart Traffic Management and Accident Prediction System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/AI_SAPP');

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
define('FROM_NAME', 'Smart Traffic AI');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('UTC');

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

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
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
    public $username;
    public $email;
    public $password_hash;
    public $role;
    public $is_active;
    public $last_login;
    public $updated_at;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (username, email, password_hash, role, is_active) 
                  VALUES (:username, :email, :password_hash, :role, :is_active)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':email', $this->email);
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
            $this->username = $row['username'];
            $this->email = $row['email'];
            $this->password_hash = $row['password_hash'];
            $this->role = $row['role'];
            $this->is_active = $row['is_active'];
            $this->last_login = $row['last_login'];
            $this->updated_at = $row['updated_at'];
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
            $this->username = $row['username'];
            $this->email = $row['email'];
            $this->password_hash = $row['password_hash'];
            $this->role = $row['role'];
            $this->is_active = $row['is_active'];
            $this->last_login = $row['last_login'];
            $this->updated_at = $row['updated_at'];
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


