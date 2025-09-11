<?php
require_once 'config.php';
session_start();

// Use the logout function from config.php
logout();

// Redirect to login page
header('Location: login.php');
exit();
?>
