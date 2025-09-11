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
$path = str_replace('/AI_SAPP', '', $path); // Remove base path if needed

// Route the request
try {
    switch ($path) {
        case '/api/students':
            handleStudents($method);
            break;
        case '/api/assessments':
            handleAssessments($method);
            break;
        case '/api/predictions':
            handlePredictions($method);
            break;
        case '/api/models':
            handleModels($method);
            break;
        case '/api/train':
            handleTrainModel($method);
            break;
        case '/api/predict':
            handlePredict($method);
            break;
        case '/api/analytics':
            handleAnalytics($method);
            break;
        default:
            echo APIResponse::error('Endpoint not found', 404);
            break;
    }
} catch (Exception $e) {
    Utils::logError($e->getMessage());
    echo APIResponse::error('Internal server error: ' . $e->getMessage(), 500);
}

function handleStudents($method) {
    $db = new Database();
    $conn = $db->getConnection();
    $student = new Student($conn);
    
    switch ($method) {
        case 'GET':
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : RECORDS_PER_PAGE;
            $offset = ($page - 1) * $limit;
            
            $query = "SELECT * FROM students ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $students = $stmt->fetchAll();
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM students";
            $countStmt = $conn->prepare($countQuery);
            $countStmt->execute();
            $total = $countStmt->fetch()['total'];
            
            echo APIResponse::success([
                'students' => $students,
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
            $required = ['first_name', 'last_name', 'dob', 'gender', 'class'];
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
            
            // Sanitize and set data
            $student->first_name = Utils::sanitizeInput($data['first_name']);
            $student->last_name = Utils::sanitizeInput($data['last_name']);
            $student->dob = $data['dob'];
            $student->gender = $data['gender'];
            $student->socio_econ_index = isset($data['socio_econ_index']) ? $data['socio_econ_index'] : 0.50;
            $student->class = Utils::sanitizeInput($data['class']);
            
            if ($student->create()) {
                echo APIResponse::success(null, 'Student created successfully');
            } else {
                echo APIResponse::error('Failed to create student', 500);
            }
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id'])) {
                echo APIResponse::error('Student ID is required', 400);
                return;
            }
            
            $student->id = $data['id'];
            
            if (!$student->readOne()) {
                echo APIResponse::error('Student not found', 404);
                return;
            }
            
            // Update fields
            if (isset($data['first_name'])) $student->first_name = Utils::sanitizeInput($data['first_name']);
            if (isset($data['last_name'])) $student->last_name = Utils::sanitizeInput($data['last_name']);
            if (isset($data['dob'])) $student->dob = $data['dob'];
            if (isset($data['gender'])) $student->gender = $data['gender'];
            if (isset($data['socio_econ_index'])) $student->socio_econ_index = $data['socio_econ_index'];
            if (isset($data['class'])) $student->class = Utils::sanitizeInput($data['class']);
            
            if ($student->update()) {
                echo APIResponse::success(null, 'Student updated successfully');
            } else {
                echo APIResponse::error('Failed to update student', 500);
            }
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id'])) {
                echo APIResponse::error('Student ID is required', 400);
                return;
            }
            
            $student->id = $data['id'];
            
            if ($student->delete()) {
                echo APIResponse::success(null, 'Student deleted successfully');
            } else {
                echo APIResponse::error('Failed to delete student', 500);
            }
            break;
            
        default:
            echo APIResponse::error('Method not allowed', 405);
            break;
    }
}

function handleAssessments($method) {
    $db = new Database();
    $conn = $db->getConnection();
    
    switch ($method) {
        case 'GET':
            $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
            $term = isset($_GET['term']) ? $_GET['term'] : null;
            
            $query = "SELECT * FROM assessments WHERE 1=1";
            $params = [];
            
            if ($student_id) {
                $query .= " AND student_id = :student_id";
                $params[':student_id'] = $student_id;
            }
            
            if ($term) {
                $query .= " AND term = :term";
                $params[':term'] = $term;
            }
            
            $query .= " ORDER BY created_at DESC";
            
            $stmt = $conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $assessments = $stmt->fetchAll();
            echo APIResponse::success($assessments);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['student_id', 'term'];
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
            
            $query = "INSERT INTO assessments (student_id, term, assignment_avg, quiz_avg, exam_prev, 
                     attendance_rate, participation_score, on_time_submissions) 
                     VALUES (:student_id, :term, :assignment_avg, :quiz_avg, :exam_prev, 
                     :attendance_rate, :participation_score, :on_time_submissions)";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':student_id', $data['student_id']);
            $stmt->bindParam(':term', $data['term']);
            $stmt->bindParam(':assignment_avg', $data['assignment_avg'] ?? 0);
            $stmt->bindParam(':quiz_avg', $data['quiz_avg'] ?? 0);
            $stmt->bindParam(':exam_prev', $data['exam_prev'] ?? 0);
            $stmt->bindParam(':attendance_rate', $data['attendance_rate'] ?? 0);
            $stmt->bindParam(':participation_score', $data['participation_score'] ?? 0);
            $stmt->bindParam(':on_time_submissions', $data['on_time_submissions'] ?? 0);
            
            if ($stmt->execute()) {
                echo APIResponse::success(null, 'Assessment created successfully');
            } else {
                echo APIResponse::error('Failed to create assessment', 500);
            }
            break;
            
        default:
            echo APIResponse::error('Method not allowed', 405);
            break;
    }
}

function handlePredictions($method) {
    $db = new Database();
    $conn = $db->getConnection();
    
    switch ($method) {
        case 'GET':
            $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
            $model_id = isset($_GET['model_id']) ? (int)$_GET['model_id'] : null;
            
            $query = "SELECT p.*, s.first_name, s.last_name, m.name as model_name 
                     FROM predictions p 
                     JOIN students s ON p.student_id = s.id 
                     JOIN models m ON p.model_id = m.id 
                     WHERE 1=1";
            $params = [];
            
            if ($student_id) {
                $query .= " AND p.student_id = :student_id";
                $params[':student_id'] = $student_id;
            }
            
            if ($model_id) {
                $query .= " AND p.model_id = :model_id";
                $params[':model_id'] = $model_id;
            }
            
            $query .= " ORDER BY p.created_at DESC";
            
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
            
            $required = ['student_id', 'model_id', 'input_data', 'predicted_label', 'confidence_scores'];
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
            
            $query = "INSERT INTO predictions (student_id, model_id, input_data, predicted_label, 
                     confidence_scores, performance_score) 
                     VALUES (:student_id, :model_id, :input_data, :predicted_label, 
                     :confidence_scores, :performance_score)";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':student_id', $data['student_id']);
            $stmt->bindParam(':model_id', $data['model_id']);
            $stmt->bindParam(':input_data', json_encode($data['input_data']));
            $stmt->bindParam(':predicted_label', $data['predicted_label']);
            $stmt->bindParam(':confidence_scores', json_encode($data['confidence_scores']));
            $stmt->bindParam(':performance_score', $data['performance_score'] ?? 0);
            
            if ($stmt->execute()) {
                echo APIResponse::success(null, 'Prediction saved successfully');
            } else {
                echo APIResponse::error('Failed to save prediction', 500);
            }
            break;
            
        default:
            echo APIResponse::error('Method not allowed', 405);
            break;
    }
}

function handleModels($method) {
    $db = new Database();
    $conn = $db->getConnection();
    
    switch ($method) {
        case 'GET':
            $query = "SELECT * FROM models ORDER BY created_at DESC";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            
            $models = $stmt->fetchAll();
            echo APIResponse::success($models);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['name', 'algorithm'];
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
            
            $query = "INSERT INTO models (name, algorithm, params_json, metrics_json, 
                     artifact_path, is_production, version) 
                     VALUES (:name, :algorithm, :params_json, :metrics_json, 
                     :artifact_path, :is_production, :version)";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':algorithm', $data['algorithm']);
            $stmt->bindParam(':params_json', json_encode($data['params_json'] ?? []));
            $stmt->bindParam(':metrics_json', json_encode($data['metrics_json'] ?? []));
            $stmt->bindParam(':artifact_path', $data['artifact_path'] ?? null);
            $stmt->bindParam(':is_production', $data['is_production'] ?? false, PDO::PARAM_BOOL);
            $stmt->bindParam(':version', $data['version'] ?? '1.0.0');
            
            if ($stmt->execute()) {
                echo APIResponse::success(null, 'Model created successfully');
            } else {
                echo APIResponse::error('Failed to create model', 500);
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
        
        $query = "INSERT INTO models (name, algorithm, metrics_json, is_production, version) 
                 VALUES (:name, :algorithm, :metrics_json, :is_production, :version)";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':name', $result['model_name'] ?? 'Trained Model');
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
        'study_hours', 'attendance_rate', 'previous_gpa', 'extracurricular_hours',
        'family_income', 'parent_education', 'sleep_hours', 'social_media_hours',
        'study_group_participation', 'assignment_completion_rate'
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
        
        // Save prediction to database if student_id is provided
        if (isset($data['student_id'])) {
            $db = new Database();
            $conn = $db->getConnection();
            
            // Get the latest production model
            $modelQuery = "SELECT id FROM models WHERE is_production = 1 ORDER BY created_at DESC LIMIT 1";
            $modelStmt = $conn->prepare($modelQuery);
            $modelStmt->execute();
            $model = $modelStmt->fetch();
            
            if ($model) {
                $query = "INSERT INTO predictions (student_id, model_id, input_data, predicted_label, 
                         confidence_scores, performance_score) 
                         VALUES (:student_id, :model_id, :input_data, :predicted_label, 
                         :confidence_scores, :performance_score)";
                
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':student_id', $data['student_id']);
                $stmt->bindParam(':model_id', $model['id']);
                $stmt->bindParam(':input_data', json_encode($data));
                $stmt->bindParam(':predicted_label', $result['prediction']);
                $stmt->bindParam(':confidence_scores', json_encode($result['probabilities']));
                $stmt->bindParam(':performance_score', $result['performance_score'] ?? 0);
                $stmt->execute();
            }
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
    
    // Total students
    $query = "SELECT COUNT(*) as total FROM students";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $analytics['total_students'] = $stmt->fetch()['total'];
    
    // Performance distribution
    $query = "SELECT predicted_label, COUNT(*) as count FROM predictions GROUP BY predicted_label";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $analytics['performance_distribution'] = $stmt->fetchAll();
    
    // Model accuracy
    $query = "SELECT name, metrics_json FROM models WHERE is_production = 1 ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $model = $stmt->fetch();
    if ($model) {
        $metrics = json_decode($model['metrics_json'], true);
        $analytics['model_accuracy'] = $metrics['accuracy'] ?? 0;
    }
    
    // Recent predictions
    $query = "SELECT COUNT(*) as count FROM predictions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $analytics['recent_predictions'] = $stmt->fetch()['count'];
    
    echo APIResponse::success($analytics);
}
?>




