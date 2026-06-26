<?php
require_once 'config.php';
require_once __DIR__ . '/includes/audit_helpers.php';
session_start();

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId > 0) {
    try {
        $db = (new Database())->getConnection();
        if ($db) {
            strgs_log_audit_event($db, $userId, 'logout', 'users', $userId, [
                'email' => $_SESSION['user_email'] ?? null,
                'user_type' => $_SESSION['user_type'] ?? null,
            ]);
        }
    } catch (Throwable $e) {
        error_log('STRGS logout audit failed: ' . $e->getMessage());
    }
}

logout();

header('Location: login.php');
exit();
