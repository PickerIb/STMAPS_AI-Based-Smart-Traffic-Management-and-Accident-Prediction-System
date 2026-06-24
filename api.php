<?php
require_once 'config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/STMAPS', '', $path); // Remove base path if needed

// Route the request
try {
    switch ($path) {
        case '/api/segments':
            handleSegments($method);
            break;
        case '/api/readings':
            handleTrafficReadings($method);
            break;
        case '/api/accidents':
            handleAccidents($method);
            break;
        case '/api/predictions':
            handleAIPredictions($method);
            break;
        case '/api/alerts':
            handleAlerts($method);
            break;
        case '/api/models':
            handleAIModels($method);
            break;
        case '/api/analytics':
            handleAnalytics($method);
            break;
        case '/api/train':
            handleTrainModel($method);
            break;
        case '/api/predict':
            handlePredict($method);
            break;
        case '/api/driver-licenses':
            handleDriverLicenses($method);
            break;
        case '/api/police-ids':
            handlePoliceIds($method);
            break;
        default:
            echo APIResponse::error('Endpoint not found', 404);
            break;
    }
} catch (Exception $e) {
    Utils::logError($e->getMessage());
    echo APIResponse::error('Internal server error: ' . $e->getMessage(), 500);
}

function handleSegments($method) {
    $db = new Database();
    $conn = $db->getConnection();
    
    switch ($method) {
        case 'GET':
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : RECORDS_PER_PAGE;
            $offset = ($page - 1) * $limit;
            
            $query = "SELECT * FROM road_segments ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $segments = $stmt->fetchAll();
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM road_segments";
            $countStmt = $conn->prepare($countQuery);
            $countStmt->execute();
            $total = $countStmt->fetch()['total'];
            
            echo APIResponse::success([
                'segments' => $segments,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            $required = ['name', 'zone'];
            $errors = [];
            
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    $errors[] = "Field '$field' is required";
                }
            }
            
            if (!empty($errors)) {
                echo APIResponse::validationError($errors);
                return;
            }
            
            $query = "INSERT INTO road_segments (name, zone, start_lat, start_lng, end_lat, end_lng, speed_limit_kmh, lanes, is_active)
                      VALUES (:name, :zone, :start_lat, :start_lng, :end_lat, :end_lng, :speed_limit_kmh, :lanes, :is_active)";
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':name', Utils::sanitizeInput($data['name']));
            $stmt->bindValue(':zone', Utils::sanitizeInput($data['zone']));
            $stmt->bindValue(':start_lat', $data['start_lat'] ?? null);
            $stmt->bindValue(':start_lng', $data['start_lng'] ?? null);
            $stmt->bindValue(':end_lat', $data['end_lat'] ?? null);
            $stmt->bindValue(':end_lng', $data['end_lng'] ?? null);
            $stmt->bindValue(':speed_limit_kmh', $data['speed_limit_kmh'] ?? 50);
            $stmt->bindValue(':lanes', $data['lanes'] ?? 2);
            $stmt->bindValue(':is_active', $data['is_active'] ?? 1);

            if ($stmt->execute()) {
                echo APIResponse::success(['id' => $conn->lastInsertId()], 'Road segment created successfully');
            } else {
                echo APIResponse::error('Failed to create road segment', 500);
            }
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id'])) {
                echo APIResponse::error('Segment ID is required', 400);
                return;
            }

            $query = "UPDATE road_segments SET
                        name = COALESCE(:name, name),
                        zone = COALESCE(:zone, zone),
                        start_lat = COALESCE(:start_lat, start_lat),
                        start_lng = COALESCE(:start_lng, start_lng),
                        end_lat = COALESCE(:end_lat, end_lat),
                        end_lng = COALESCE(:end_lng, end_lng),
                        speed_limit_kmh = COALESCE(:speed_limit_kmh, speed_limit_kmh),
                        lanes = COALESCE(:lanes, lanes),
                        is_active = COALESCE(:is_active, is_active)
                      WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':id', $data['id'], PDO::PARAM_INT);
            $stmt->bindValue(':name', isset($data['name']) ? Utils::sanitizeInput($data['name']) : null);
            $stmt->bindValue(':zone', isset($data['zone']) ? Utils::sanitizeInput($data['zone']) : null);
            $stmt->bindValue(':start_lat', $data['start_lat'] ?? null);
            $stmt->bindValue(':start_lng', $data['start_lng'] ?? null);
            $stmt->bindValue(':end_lat', $data['end_lat'] ?? null);
            $stmt->bindValue(':end_lng', $data['end_lng'] ?? null);
            $stmt->bindValue(':speed_limit_kmh', $data['speed_limit_kmh'] ?? null);
            $stmt->bindValue(':lanes', $data['lanes'] ?? null);
            $stmt->bindValue(':is_active', $data['is_active'] ?? null);

            if ($stmt->execute() && $stmt->rowCount() > 0) {
                echo APIResponse::success(null, 'Road segment updated successfully');
            } else {
                echo APIResponse::error('Failed to update segment (not found or no changes)', 400);
            }
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id'])) {
                echo APIResponse::error('Segment ID is required', 400);
                return;
            }

            $query = "DELETE FROM road_segments WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':id', $data['id'], PDO::PARAM_INT);
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                echo APIResponse::success(null, 'Road segment deleted successfully');
            } else {
                echo APIResponse::error('Failed to delete segment (not found)', 404);
            }
            break;
            
        default:
            echo APIResponse::error('Method not allowed', 405);
            break;
    }
}

function handleTrafficReadings($method) {
    $db = new Database();
    $conn = $db->getConnection();
    
    switch ($method) {
        case 'GET':
            $segment_id = isset($_GET['segment_id']) ? (int)$_GET['segment_id'] : null;
            $from = isset($_GET['from']) ? $_GET['from'] : null;
            $to = isset($_GET['to']) ? $_GET['to'] : null;
            
            $query = "SELECT * FROM traffic_readings WHERE 1=1";
            $params = [];
            
            if ($segment_id) {
                $query .= " AND segment_id = :segment_id";
                $params[':segment_id'] = $segment_id;
            }
            
            if ($from) {
                $query .= " AND reading_time >= :from";
                $params[':from'] = $from;
            }
            if ($to) {
                $query .= " AND reading_time <= :to";
                $params[':to'] = $to;
            }
            
            $query .= " ORDER BY reading_time DESC LIMIT 500";
            
            $stmt = $conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $readings = $stmt->fetchAll();
            echo APIResponse::success($readings);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['segment_id', 'reading_time', 'vehicle_count', 'avg_speed_kmh', 'density_veh_per_km'];
            $errors = [];
            
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    $errors[] = "Field '$field' is required";
                }
            }
            
            if (!empty($errors)) {
                echo APIResponse::validationError($errors);
                return;
            }
            
            $query = "INSERT INTO traffic_readings (segment_id, reading_time, vehicle_count, avg_speed_kmh, density_veh_per_km, occupancy_pct, weather, visibility_m, temperature_c, source)
                     VALUES (:segment_id, :reading_time, :vehicle_count, :avg_speed_kmh, :density_veh_per_km, :occupancy_pct, :weather, :visibility_m, :temperature_c, :source)";
            
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':segment_id', (int)$data['segment_id'], PDO::PARAM_INT);
            $stmt->bindValue(':reading_time', $data['reading_time']);
            $stmt->bindValue(':vehicle_count', (int)$data['vehicle_count'], PDO::PARAM_INT);
            $stmt->bindValue(':avg_speed_kmh', $data['avg_speed_kmh']);
            $stmt->bindValue(':density_veh_per_km', $data['density_veh_per_km']);
            $stmt->bindValue(':occupancy_pct', $data['occupancy_pct'] ?? null);
            $stmt->bindValue(':weather', $data['weather'] ?? 'unknown');
            $stmt->bindValue(':visibility_m', $data['visibility_m'] ?? null);
            $stmt->bindValue(':temperature_c', $data['temperature_c'] ?? null);
            $stmt->bindValue(':source', $data['source'] ?? null);
            
            if ($stmt->execute()) {
                echo APIResponse::success(['id' => $conn->lastInsertId()], 'Traffic reading saved successfully');
            } else {
                echo APIResponse::error('Failed to save traffic reading', 500);
            }
            break;
            
        default:
            echo APIResponse::error('Method not allowed', 405);
            break;
    }
}

function handleAccidents($method) {
    $db = new Database();
    $conn = $db->getConnection();
    
    switch ($method) {
        case 'GET':
            $segment_id = isset($_GET['segment_id']) ? (int)$_GET['segment_id'] : null;

            $query = "SELECT a.*, rs.name as segment_name, rs.zone
                     FROM accidents a
                     JOIN road_segments rs ON a.segment_id = rs.id
                     WHERE 1=1";
            $params = [];
            
            if ($segment_id) {
                $query .= " AND a.segment_id = :segment_id";
                $params[':segment_id'] = $segment_id;
            }
            
            $query .= " ORDER BY a.accident_time DESC LIMIT 500";
            
            $stmt = $conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $accidents = $stmt->fetchAll();
            echo APIResponse::success($accidents);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['segment_id', 'accident_time', 'severity'];
            $errors = [];
            
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    $errors[] = "Field '$field' is required";
                }
            }
            
            if (!empty($errors)) {
                echo APIResponse::validationError($errors);
                return;
            }
            
            $query = "INSERT INTO accidents (segment_id, accident_time, severity, cause, vehicles_involved, injuries, fatalities, notes)
                     VALUES (:segment_id, :accident_time, :severity, :cause, :vehicles_involved, :injuries, :fatalities, :notes)";
            
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':segment_id', (int)$data['segment_id'], PDO::PARAM_INT);
            $stmt->bindValue(':accident_time', $data['accident_time']);
            $stmt->bindValue(':severity', $data['severity']);
            $stmt->bindValue(':cause', isset($data['cause']) ? Utils::sanitizeInput($data['cause']) : null);
            $stmt->bindValue(':vehicles_involved', $data['vehicles_involved'] ?? null);
            $stmt->bindValue(':injuries', $data['injuries'] ?? null);
            $stmt->bindValue(':fatalities', $data['fatalities'] ?? null);
            $stmt->bindValue(':notes', isset($data['notes']) ? Utils::sanitizeInput($data['notes']) : null);
            
            if ($stmt->execute()) {
                echo APIResponse::success(['id' => $conn->lastInsertId()], 'Accident saved successfully');
            } else {
                echo APIResponse::error('Failed to save accident', 500);
            }
            break;
            
        default:
            echo APIResponse::error('Method not allowed', 405);
            break;
    }
}

function handleAIPredictions($method) {
    $db = new Database();
    $conn = $db->getConnection();
    
    switch ($method) {
        case 'GET':
            $segment_id = isset($_GET['segment_id']) ? (int)$_GET['segment_id'] : null;
            $query = "SELECT p.*, rs.name as segment_name, rs.zone, m.name as model_name
                     FROM ai_predictions p
                     JOIN road_segments rs ON p.segment_id = rs.id
                     LEFT JOIN ai_models m ON p.model_id = m.id
                     WHERE 1=1";
            $params = [];
            if ($segment_id) {
                $query .= " AND p.segment_id = :segment_id";
                $params[':segment_id'] = $segment_id;
            }
            $query .= " ORDER BY p.prediction_time DESC LIMIT 500";
            $stmt = $conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $predictions = $stmt->fetchAll();
            echo APIResponse::success($predictions);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['segment_id', 'input_json', 'congestion_level', 'accident_probability', 'confidence'];
            $errors = [];
            
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    $errors[] = "Field '$field' is required";
                }
            }
            
            if (!empty($errors)) {
                echo APIResponse::validationError($errors);
                return;
            }
            
            $query = "INSERT INTO ai_predictions (segment_id, model_id, prediction_time, input_json, congestion_level, accident_probability, confidence, explanations_json)
                     VALUES (:segment_id, :model_id, :prediction_time, :input_json, :congestion_level, :accident_probability, :confidence, :explanations_json)";
            
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':segment_id', (int)$data['segment_id'], PDO::PARAM_INT);
            $stmt->bindValue(':model_id', $data['model_id'] ?? null);
            $stmt->bindValue(':prediction_time', $data['prediction_time'] ?? date('Y-m-d H:i:s'));
            $stmt->bindValue(':input_json', json_encode($data['input_json']));
            $stmt->bindValue(':congestion_level', $data['congestion_level']);
            $stmt->bindValue(':accident_probability', $data['accident_probability']);
            $stmt->bindValue(':confidence', $data['confidence']);
            $stmt->bindValue(':explanations_json', json_encode($data['explanations_json'] ?? null));
            
            if ($stmt->execute()) {
                echo APIResponse::success(['id' => $conn->lastInsertId()], 'Prediction saved successfully');
            } else {
                echo APIResponse::error('Failed to save prediction', 500);
            }
            break;
            
        default:
            echo APIResponse::error('Method not allowed', 405);
            break;
    }
}

function handleAlerts($method) {
    $db = new Database();
    $conn = $db->getConnection();

    switch ($method) {
        case 'GET':
            $segment_id = isset($_GET['segment_id']) ? (int)$_GET['segment_id'] : null;
            $only_unacked = isset($_GET['only_unacked']) ? (int)$_GET['only_unacked'] : 0;

            $query = "SELECT al.*, rs.name as segment_name, rs.zone
                     FROM alerts al
                     JOIN road_segments rs ON al.segment_id = rs.id
                     WHERE 1=1";
            $params = [];
            if ($segment_id) {
                $query .= " AND al.segment_id = :segment_id";
                $params[':segment_id'] = $segment_id;
            }
            if ($only_unacked) {
                $query .= " AND al.is_acknowledged = 0";
            }
            $query .= " ORDER BY al.alert_time DESC LIMIT 500";

            $stmt = $conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            echo APIResponse::success($stmt->fetchAll());
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $required = ['segment_id', 'type', 'severity', 'message'];
            $errors = [];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    $errors[] = "Field '$field' is required";
                }
            }
            if (!empty($errors)) {
                echo APIResponse::validationError($errors);
                return;
            }

            $query = "INSERT INTO alerts (segment_id, alert_time, type, severity, message)
                     VALUES (:segment_id, :alert_time, :type, :severity, :message)";
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':segment_id', (int)$data['segment_id'], PDO::PARAM_INT);
            $stmt->bindValue(':alert_time', $data['alert_time'] ?? date('Y-m-d H:i:s'));
            $stmt->bindValue(':type', $data['type']);
            $stmt->bindValue(':severity', $data['severity']);
            $stmt->bindValue(':message', Utils::sanitizeInput($data['message']));

            if ($stmt->execute()) {
                echo APIResponse::success(['id' => $conn->lastInsertId()], 'Alert created successfully');
            } else {
                echo APIResponse::error('Failed to create alert', 500);
            }
            break;

        default:
            echo APIResponse::error('Method not allowed', 405);
            break;
    }
}

function handleAIModels($method) {
    $db = new Database();
    $conn = $db->getConnection();

    switch ($method) {
        case 'GET':
            $query = "SELECT * FROM ai_models ORDER BY created_at DESC";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            echo APIResponse::success($stmt->fetchAll());
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            $required = ['name', 'task', 'algorithm'];
            $errors = [];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    $errors[] = "Field '$field' is required";
                }
            }
            if (!empty($errors)) {
                echo APIResponse::validationError($errors);
                return;
            }

            $query = "INSERT INTO ai_models (name, task, algorithm, params_json, metrics_json, artifact_path, is_production, version)
                     VALUES (:name, :task, :algorithm, :params_json, :metrics_json, :artifact_path, :is_production, :version)";
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':name', Utils::sanitizeInput($data['name']));
            $stmt->bindValue(':task', $data['task']);
            $stmt->bindValue(':algorithm', Utils::sanitizeInput($data['algorithm']));
            $stmt->bindValue(':params_json', json_encode($data['params_json'] ?? null));
            $stmt->bindValue(':metrics_json', json_encode($data['metrics_json'] ?? null));
            $stmt->bindValue(':artifact_path', $data['artifact_path'] ?? null);
            $stmt->bindValue(':is_production', !empty($data['is_production']) ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':version', $data['version'] ?? '1.0.0');

            if ($stmt->execute()) {
                echo APIResponse::success(['id' => $conn->lastInsertId()], 'AI model created successfully');
            } else {
                echo APIResponse::error('Failed to create AI model', 500);
            }
            break;

        default:
            echo APIResponse::error('Method not allowed', 405);
            break;
    }
}

function handleTrainModel($method) {
    if ($method !== 'POST') {
        echo APIResponse::error('Method not allowed', 405);
        return;
    }
    
    try {
        $mlService = new MLService();
        $result = $mlService->trainModel();
        
        // Save model information to database
        $db = new Database();
        $conn = $db->getConnection();
        
        $query = "INSERT INTO ai_models (name, task, algorithm, metrics_json, is_production, version) 
                 VALUES (:name, :task, :algorithm, :metrics_json, :is_production, :version)";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':name', $result['model_name'] ?? 'Trained Model');
        $stmt->bindValue(':task', $result['task'] ?? 'accident_risk');
        $stmt->bindParam(':algorithm', $result['algorithm'] ?? 'RandomForest');
        $stmt->bindParam(':metrics_json', json_encode($result['metrics'] ?? []));
        $stmt->bindParam(':is_production', true, PDO::PARAM_BOOL);
        $stmt->bindParam(':version', $result['version'] ?? '1.0.0');
        $stmt->execute();
        
        echo APIResponse::success($result, 'Model trained successfully');
    } catch (Exception $e) {
        echo APIResponse::error('Training failed: ' . $e->getMessage(), 500);
    }
}

function handlePredict($method) {
    if ($method !== 'POST') {
        echo APIResponse::error('Method not allowed', 405);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required_fields = [
        'segment_id', 'reading_time', 'vehicle_count', 'avg_speed_kmh', 'density_veh_per_km'
    ];
    
    $errors = [];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            $errors[] = "Field '$field' is required";
        }
    }
    
    if (!empty($errors)) {
        echo APIResponse::validationError($errors);
        return;
    }
    
    try {
        $mlService = new MLService();
        $result = $mlService->predict($data);
        
        // Optionally persist predictions if the ML service returns the required fields
        if (!empty($result['success']) && isset($data['segment_id'])) {
            $db = new Database();
            $conn = $db->getConnection();

            $modelQuery = "SELECT id FROM ai_models WHERE is_production = 1 ORDER BY created_at DESC LIMIT 1";
            $modelStmt = $conn->prepare($modelQuery);
            $modelStmt->execute();
            $model = $modelStmt->fetch();

            $query = "INSERT INTO ai_predictions (segment_id, model_id, prediction_time, input_json, congestion_level, accident_probability, confidence, explanations_json)
                     VALUES (:segment_id, :model_id, :prediction_time, :input_json, :congestion_level, :accident_probability, :confidence, :explanations_json)";
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':segment_id', (int)$data['segment_id'], PDO::PARAM_INT);
            $stmt->bindValue(':model_id', $model['id'] ?? null);
            $stmt->bindValue(':prediction_time', date('Y-m-d H:i:s'));
            $stmt->bindValue(':input_json', json_encode($data));
            $stmt->bindValue(':congestion_level', $result['congestion_level'] ?? 'moderate');
            $stmt->bindValue(':accident_probability', $result['accident_probability'] ?? 0);
            $stmt->bindValue(':confidence', $result['confidence'] ?? 0.7);
            $stmt->bindValue(':explanations_json', json_encode($result['explanations'] ?? null));
            $stmt->execute();
        }
        
        echo APIResponse::success($result, 'Prediction completed successfully');
    } catch (Exception $e) {
        echo APIResponse::error('Prediction failed: ' . $e->getMessage(), 500);
    }
}

function handleAnalytics($method) {
    if ($method !== 'GET') {
        echo APIResponse::error('Method not allowed', 405);
        return;
    }
    
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get various analytics
    $analytics = [];
    
    // Total segments
    $query = "SELECT COUNT(*) as total FROM road_segments";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $analytics['total_segments'] = $stmt->fetch()['total'];
    
    // Congestion distribution
    $query = "SELECT congestion_level, COUNT(*) as count FROM ai_predictions GROUP BY congestion_level";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $analytics['congestion_distribution'] = $stmt->fetchAll();
    
    // Latest model metric snapshot (if provided)
    $query = "SELECT name, metrics_json FROM ai_models WHERE is_production = 1 ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $model = $stmt->fetch();
    if ($model) {
        $metrics = json_decode($model['metrics_json'], true);
        $analytics['model_metric'] = $metrics['accuracy'] ?? ($metrics['note'] ?? null);
    }
    
    // Recent predictions
    $query = "SELECT COUNT(*) as count FROM ai_predictions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $analytics['recent_predictions'] = $stmt->fetch()['count'];

    // Recent accidents
    $query = "SELECT COUNT(*) as count FROM accidents WHERE accident_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $analytics['recent_accidents'] = $stmt->fetch()['count'];
    
    echo APIResponse::success($analytics);
}

function handleDriverLicenses($method) {
    if ($method !== 'GET') {
        echo APIResponse::error('Method not allowed', 405);
        return;
    }

    $db = new Database();
    $conn = $db->getConnection();
    $registry = new IdRegistry($conn);

    $licenseId = isset($_GET['license_id']) ? trim($_GET['license_id']) : '';
    if ($licenseId !== '') {
        if (!preg_match('/^RW-\d{8}$/', $licenseId)) {
            echo APIResponse::error('Invalid license ID format. Use RW-12345678', 400);
            return;
        }
        $row = $registry->findLicense($licenseId);
        if (!$row) {
            echo APIResponse::error('License ID not found', 404);
            return;
        }
        echo APIResponse::success($row);
        return;
    }

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : RECORDS_PER_PAGE;
    $offset = ($page - 1) * $limit;

    $countStmt = $conn->query('SELECT COUNT(*) AS total FROM api_driver_licenses');
    $total = (int)$countStmt->fetch()['total'];

    $stmt = $conn->prepare('SELECT id, name, license_id FROM api_driver_licenses ORDER BY id ASC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    echo APIResponse::success([
        'data' => $stmt->fetchAll(),
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => (int)ceil($total / $limit)
        ]
    ]);
}

function handlePoliceIds($method) {
    if ($method !== 'GET') {
        echo APIResponse::error('Method not allowed', 405);
        return;
    }

    $db = new Database();
    $conn = $db->getConnection();
    $registry = new IdRegistry($conn);

    $policeId = isset($_GET['police_id']) ? trim($_GET['police_id']) : '';
    if ($policeId !== '') {
        if (!preg_match('/^RNP-\d{5}$/', $policeId)) {
            echo APIResponse::error('Invalid police ID format. Use RNP-12345', 400);
            return;
        }
        $row = $registry->findPolice($policeId);
        if (!$row) {
            echo APIResponse::error('Police ID not found', 404);
            return;
        }
        echo APIResponse::success($row);
        return;
    }

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : RECORDS_PER_PAGE;
    $offset = ($page - 1) * $limit;

    $countStmt = $conn->query('SELECT COUNT(*) AS total FROM api_police_ids');
    $total = (int)$countStmt->fetch()['total'];

    $stmt = $conn->prepare('SELECT id, name, police_id FROM api_police_ids ORDER BY id ASC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    echo APIResponse::success([
        'data' => $stmt->fetchAll(),
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => (int)ceil($total / $limit)
        ]
    ]);
}
?>
