-- AI Student Performance Predictor Database Schema
-- Created for MySQL/MariaDB

CREATE DATABASE IF NOT EXISTS student_performance_db;
USE student_performance_db;

-- Students table
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    dob DATE NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    socio_econ_index DECIMAL(3,2) DEFAULT 0.50,
    class VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (first_name, last_name),
    INDEX idx_class (class),
    INDEX idx_created (created_at)
);

-- Assessments table
CREATE TABLE IF NOT EXISTS assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    term VARCHAR(20) NOT NULL,
    assignment_avg DECIMAL(5,2) DEFAULT 0.00,
    quiz_avg DECIMAL(5,2) DEFAULT 0.00,
    exam_prev DECIMAL(5,2) DEFAULT 0.00,
    attendance_rate DECIMAL(5,2) DEFAULT 0.00,
    participation_score DECIMAL(5,2) DEFAULT 0.00,
    on_time_submissions DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_term (term),
    INDEX idx_created (created_at)
);

-- Labels table (target outcomes)
CREATE TABLE IF NOT EXISTS labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    term VARCHAR(20) NOT NULL,
    target_grade DECIMAL(5,2) DEFAULT 0.00,
    target_label ENUM('Poor', 'Average', 'Good', 'Excellent') DEFAULT 'Average',
    actual_grade DECIMAL(5,2) DEFAULT NULL,
    actual_label ENUM('Poor', 'Average', 'Good', 'Excellent') DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_term (term),
    INDEX idx_target_label (target_label),
    INDEX idx_actual_label (actual_label)
);

-- Models table (ML model information)
CREATE TABLE IF NOT EXISTS models (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    algorithm VARCHAR(50) NOT NULL,
    params_json JSON,
    metrics_json JSON,
    artifact_path VARCHAR(255),
    is_production BOOLEAN DEFAULT FALSE,
    version VARCHAR(20) DEFAULT '1.0.0',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_algorithm (algorithm),
    INDEX idx_production (is_production),
    INDEX idx_created (created_at)
);

-- Predictions table (store prediction results)
CREATE TABLE IF NOT EXISTS predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    model_id INT NOT NULL,
    input_data JSON NOT NULL,
    predicted_label ENUM('Poor', 'Average', 'Good', 'Excellent') NOT NULL,
    confidence_scores JSON NOT NULL,
    performance_score DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_model (model_id),
    INDEX idx_predicted_label (predicted_label),
    INDEX idx_created (created_at)
);

-- User sessions table (for authentication)
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_token (session_token),
    INDEX idx_expires (expires_at)
);

-- Users table (for admin access)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'viewer') DEFAULT 'viewer',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
);

-- Audit log table
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_table (table_name),
    INDEX idx_created (created_at)
);

-- Insert sample data
INSERT INTO students (first_name, last_name, dob, gender, socio_econ_index, class) VALUES
('John', 'Doe', '2005-03-15', 'Male', 0.75, 'Grade 10A'),
('Jane', 'Smith', '2005-07-22', 'Female', 0.65, 'Grade 10A'),
('Mike', 'Johnson', '2005-01-10', 'Male', 0.45, 'Grade 10B'),
('Sarah', 'Williams', '2005-11-05', 'Female', 0.85, 'Grade 10A'),
('David', 'Brown', '2005-09-18', 'Male', 0.55, 'Grade 10B');

INSERT INTO assessments (student_id, term, assignment_avg, quiz_avg, exam_prev, attendance_rate, participation_score, on_time_submissions) VALUES
(1, 'Term 1', 85.5, 78.0, 82.0, 95.0, 88.0, 92.0),
(2, 'Term 1', 92.0, 89.5, 91.0, 98.0, 94.0, 96.0),
(3, 'Term 1', 72.0, 68.5, 70.0, 85.0, 75.0, 78.0),
(4, 'Term 1', 88.0, 85.0, 87.0, 96.0, 90.0, 94.0),
(5, 'Term 1', 79.0, 76.5, 78.0, 90.0, 82.0, 85.0);

INSERT INTO labels (student_id, term, target_grade, target_label, actual_grade, actual_label) VALUES
(1, 'Term 1', 85.0, 'Good', 82.0, 'Good'),
(2, 'Term 1', 90.0, 'Excellent', 91.0, 'Excellent'),
(3, 'Term 1', 70.0, 'Average', 70.0, 'Average'),
(4, 'Term 1', 88.0, 'Good', 87.0, 'Good'),
(5, 'Term 1', 78.0, 'Average', 78.0, 'Average');

INSERT INTO models (name, algorithm, params_json, metrics_json, is_production, version) VALUES
('Student Performance v1.0', 'RandomForest', '{"n_estimators": 100, "max_depth": 10}', '{"accuracy": 0.87, "precision": 0.85, "recall": 0.83}', TRUE, '1.0.0'),
('Student Performance v1.1', 'XGBoost', '{"n_estimators": 200, "max_depth": 8}', '{"accuracy": 0.89, "precision": 0.87, "recall": 0.86}', FALSE, '1.1.0');

-- Create views for easier querying
CREATE VIEW student_performance_summary AS
SELECT 
    s.id,
    s.first_name,
    s.last_name,
    s.class,
    a.term,
    a.assignment_avg,
    a.quiz_avg,
    a.exam_prev,
    a.attendance_rate,
    a.participation_score,
    a.on_time_submissions,
    l.target_label,
    l.actual_label,
    l.target_grade,
    l.actual_grade
FROM students s
LEFT JOIN assessments a ON s.id = a.student_id
LEFT JOIN labels l ON s.id = l.student_id AND a.term = l.term;

-- Create stored procedures
DELIMITER //

CREATE PROCEDURE GetStudentPredictions(IN student_id INT)
BEGIN
    SELECT 
        p.id,
        p.predicted_label,
        p.confidence_scores,
        p.performance_score,
        p.created_at,
        m.name as model_name,
        m.algorithm
    FROM predictions p
    JOIN models m ON p.model_id = m.id
    WHERE p.student_id = student_id
    ORDER BY p.created_at DESC;
END //

CREATE PROCEDURE GetModelPerformance()
BEGIN
    SELECT 
        m.name,
        m.algorithm,
        m.metrics_json,
        m.is_production,
        COUNT(p.id) as prediction_count,
        AVG(JSON_EXTRACT(p.confidence_scores, '$.confidence')) as avg_confidence
    FROM models m
    LEFT JOIN predictions p ON m.id = p.model_id
    GROUP BY m.id
    ORDER BY m.created_at DESC;
END //

DELIMITER ;

-- Create triggers for audit logging
DELIMITER //

CREATE TRIGGER students_audit_insert
AFTER INSERT ON students
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (action, table_name, record_id, new_values, created_at)
    VALUES ('INSERT', 'students', NEW.id, JSON_OBJECT(
        'first_name', NEW.first_name,
        'last_name', NEW.last_name,
        'dob', NEW.dob,
        'gender', NEW.gender,
        'socio_econ_index', NEW.socio_econ_index,
        'class', NEW.class
    ), NOW());
END //

CREATE TRIGGER students_audit_update
AFTER UPDATE ON students
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (action, table_name, record_id, old_values, new_values, created_at)
    VALUES ('UPDATE', 'students', NEW.id, JSON_OBJECT(
        'first_name', OLD.first_name,
        'last_name', OLD.last_name,
        'dob', OLD.dob,
        'gender', OLD.gender,
        'socio_econ_index', OLD.socio_econ_index,
        'class', OLD.class
    ), JSON_OBJECT(
        'first_name', NEW.first_name,
        'last_name', NEW.last_name,
        'dob', NEW.dob,
        'gender', NEW.gender,
        'socio_econ_index', NEW.socio_econ_index,
        'class', NEW.class
    ), NOW());
END //

DELIMITER ;

-- Create indexes for better performance
CREATE INDEX idx_students_class_gender ON students(class, gender);
CREATE INDEX idx_assessments_student_term ON assessments(student_id, term);
CREATE INDEX idx_predictions_model_created ON predictions(model_id, created_at);
CREATE INDEX idx_labels_student_term ON labels(student_id, term);

-- Grant permissions (adjust as needed for your environment)
-- GRANT ALL PRIVILEGES ON student_performance_db.* TO 'your_user'@'localhost';
-- FLUSH PRIVILEGES;


