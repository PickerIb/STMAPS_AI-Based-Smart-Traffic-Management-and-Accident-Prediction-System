<?php
require_once 'config.php';
session_start();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (isLoggedIn()) {
    echo json_encode(['valid' => true, 'message' => 'Session is valid']);
} else {
    echo json_encode(['valid' => false, 'message' => 'Session expired']);
}
?>









