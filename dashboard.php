<?php
require_once 'config.php';
session_start();

// Require user to be logged in
requireLogin();

$user_name = $_SESSION['user_name'] ?? 'Student';
$user_email = $_SESSION['user_email'] ?? 'student@example.com';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Based Student Performance Predictor - Dashboard</title>
    <link rel="icon" type="image/png" href="logo1.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Bookman+Old+Style:wght@400;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- GSAP -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    
    <style>
        :root {
            --primary-color: #000000;
            --secondary-color: #333333;
            --accent-color: #666666;
            --success-color: #4a4a4a;
            --warning-color: #555555;
            --danger-color: #222222;
            --light-color: #f8f9fa;
            --dark-color: #000000;
            --white: #ffffff;
            --border-color: #e9ecef;
            --shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow-lg: 0 1rem 3rem rgba(0, 0, 0, 0.175);
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Bookman Old Style', serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 50%, #dee2e6 100%);
            min-height: 100vh;
            color: var(--dark-color);
            overflow-x: hidden;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #000000 0%, #333333 100%);
            color: white;
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-y: auto;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sidebar-logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #666666, #999999);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .sidebar-logo img {
            width: 24px;
            height: 24px;
            filter: brightness(0) invert(1);
        }

        .sidebar-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: white;
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .sidebar-title {
            opacity: 0;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.25rem 1rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, #666666, #999999);
            border-radius: 0 4px 4px 0;
        }

        .nav-icon {
            width: 20px;
            text-align: center;
            flex-shrink: 0;
        }

        .nav-text {
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .nav-text {
            opacity: 0;
        }

        .sidebar-toggle {
            position: absolute;
            top: 1.5rem;
            right: -15px;
            width: 30px;
            height: 30px;
            background: var(--primary-color);
            border: 2px solid white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1001;
        }

        .sidebar-toggle:hover {
            background: var(--secondary-color);
            transform: scale(1.1);
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        .main-content.sidebar-collapsed {
            margin-left: 70px;
        }

        /* Top Bar */
        .top-bar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow);
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
            font-size: 0.9rem;
        }

        .user-email {
            color: var(--accent-color);
            font-size: 0.8rem;
            margin: 0;
        }

        /* Content Area */
        .content-area {
            padding: 2rem;
        }

        /* Dashboard Cards */
        .dashboard-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }

        .stat-icon.primary { background: var(--primary-color); }
        .stat-icon.secondary { background: var(--secondary-color); }
        .stat-icon.accent { background: var(--accent-color); }
        .stat-icon.success { background: var(--success-color); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--accent-color);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Charts */
        .chart-container {
            position: relative;
            height: 300px;
        }

        /* Activity Feed */
        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .activity-time {
            color: var(--accent-color);
            font-size: 0.9rem;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-btn {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            text-decoration: none;
            color: var(--primary-color);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            text-align: center;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            color: var(--primary-color);
        }

        .action-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
        }

        .fade-in.animate {
            opacity: 1;
            transform: translateY(0);
            transition: all 0.6s ease;
        }

        /* Loading States */
        .loading {
            position: relative;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid var(--border-color);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Alert Styles */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: rgba(74, 74, 74, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: rgba(34, 34, 34, 0.1);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .alert-info {
            background: rgba(102, 102, 102, 0.1);
            color: var(--accent-color);
            border-left: 4px solid var(--accent-color);
        }
        
        /* Progress indicator styles */
        .progress-steps .step {
            padding: 8px 0;
            margin: 4px 0;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .progress-steps .step.active {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .progress-steps .step.completed {
            background-color: #e8f5e8;
            color: #2e7d32;
        }
        
        .progress-steps .step i {
            width: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="logo1.png" alt="AI SAPP Logo">
            </div>
            <div class="sidebar-title">AI SAPP</div>
        </div>
        
        <div class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="#dashboard" class="nav-link active" data-section="dashboard">
                    <i class="fas fa-tachometer-alt nav-icon"></i>
                    <span class="nav-text">AI Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#predictions" class="nav-link" data-section="predictions">
                    <i class="fas fa-brain nav-icon"></i>
                    <span class="nav-text">AI Predictions</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#analytics" class="nav-link" data-section="analytics">
                    <i class="fas fa-chart-pie nav-icon"></i>
                    <span class="nav-text">Performance Analytics</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#reports" class="nav-link" data-section="reports">
                    <i class="fas fa-file-alt nav-icon"></i>
                    <span class="nav-text">AI Reports</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#settings" class="nav-link" data-section="settings">
                    <i class="fas fa-cog nav-icon"></i>
                    <span class="nav-text">AI Settings</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt nav-icon"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1 class="page-title" id="pageTitle">AI Student Performance Predictor</h1>
            <div class="user-menu">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($user_email); ?></div>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Dashboard Section -->
            <div id="dashboard-section" class="content-section">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card fade-in">
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number" id="totalStudents">0</div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="stat-card fade-in">
                        <div class="stat-icon secondary">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-number" id="accuracyRate">0%</div>
                        <div class="stat-label">Prediction Accuracy</div>
                    </div>
                    <div class="stat-card fade-in">
                        <div class="stat-icon accent">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <div class="stat-number" id="totalPredictions">0</div>
                        <div class="stat-label">Predictions Made</div>
                    </div>
                    <div class="stat-card fade-in">
                        <div class="stat-icon success">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-number" id="systemUptime">99.8%</div>
                        <div class="stat-label">System Uptime</div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="dashboard-card fade-in">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-area"></i>
                            Performance Trends
                        </h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <a href="#" class="action-btn fade-in" id="newPrediction">
                        <div class="action-icon">
                            <i class="fas fa-plus"></i>
                        </div>
                        <span>New Prediction</span>
                    </a>
                    <a href="#" class="action-btn fade-in" id="uploadData">
                        <div class="action-icon">
                            <i class="fas fa-file-upload"></i>
                        </div>
                        <span>Upload Data</span>
                    </a>
                    <a href="#" class="action-btn fade-in" id="viewReports">
                        <div class="action-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <span>View Reports</span>
                    </a>
                </div>

                <!-- Recent Activity -->
                <div class="dashboard-card fade-in">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i>
                            Recent Activity
                        </h3>
                    </div>
                    <div id="activityFeed">
                        <!-- Activity items will be loaded via AJAX -->
                    </div>
                </div>
            </div>

            <!-- Other Sections (initially hidden) -->
            <div id="predictions-section" class="content-section" style="display: none;">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            Predictions
                        </h3>
                    </div>
                    <div id="predictionsContent">
                        <!-- Predictions content will be loaded via AJAX -->
                    </div>
                </div>
            </div>

            <div id="analytics-section" class="content-section" style="display: none;">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-pie"></i>
                            Analytics
                        </h3>
                    </div>
                    <div id="analyticsContent">
                        <!-- Analytics content will be loaded via AJAX -->
                    </div>
                </div>
            </div>

            <div id="reports-section" class="content-section" style="display: none;">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-file-alt"></i>
                            Reports
                        </h3>
                    </div>
                    <div id="reportsContent">
                        <!-- Reports content will be loaded via AJAX -->
                    </div>
                </div>
            </div>

            <div id="settings-section" class="content-section" style="display: none;">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-cog"></i>
                            Settings
                        </h3>
                    </div>
                    <div id="settingsContent">
                        <!-- Settings content will be loaded via AJAX -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize dashboard
            initializeDashboard();
            
            // Initialize sidebar
            initializeSidebar();
            
            // Initialize navigation
            initializeNavigation();
            
            // Initialize animations
            initializeAnimations();
            
            // Load initial data
            loadDashboardData();
            
            // Prevent back button access after logout
            preventBackAccess();
        });

        function initializeDashboard() {
            // Initialize counters
            initializeCounters();
            
            // Initialize charts
            initializeCharts();
            
            // Initialize quick actions
            initializeQuickActions();
            
            // Initialize prediction features
            initializePredictionFeatures();
        }

        function initializeSidebar() {
            // Sidebar toggle
            $('#sidebarToggle').on('click', function() {
                $('#sidebar').toggleClass('collapsed');
                $('#mainContent').toggleClass('sidebar-collapsed');
                
                // Update toggle icon
                const icon = $(this).find('i');
                if ($('#sidebar').hasClass('collapsed')) {
                    icon.removeClass('fa-bars').addClass('fa-chevron-right');
                } else {
                    icon.removeClass('fa-chevron-right').addClass('fa-bars');
                }
            });

            // Mobile sidebar toggle
            if (window.innerWidth <= 768) {
                $('#sidebar').addClass('collapsed');
                $('#mainContent').addClass('sidebar-collapsed');
            }
        }

        function initializeNavigation() {
            $('.nav-link[data-section]').on('click', function(e) {
                e.preventDefault();
                
                const section = $(this).data('section');
                
                // Update active nav link
                $('.nav-link').removeClass('active');
                $(this).addClass('active');
                
                // Update page title
                const titles = {
                    'dashboard': 'AI Student Performance Predictor',
                    'predictions': 'Performance Predictions',
                    'analytics': 'Analytics Dashboard',
                    'reports': 'Reports & Analytics',
                    'settings': 'System Settings'
                };
                $('#pageTitle').text(titles[section] || 'AI Student Performance Predictor');
                
                // Show/hide sections
                $('.content-section').hide();
                $(`#${section}-section`).show();
                
                // Load section content via AJAX
                loadSectionContent(section);
            });
        }

        function loadSectionContent(section) {
            const contentDiv = $(`#${section}Content`);
            
            // Show loading state
            contentDiv.addClass('loading').html('<div class="text-center p-4">Loading...</div>');
            
            // Simulate AJAX call
            setTimeout(() => {
                contentDiv.removeClass('loading');
                
                switch(section) {
                    case 'predictions':
                        contentDiv.html(`
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <h5><i class="fas fa-brain me-2"></i>AI Performance Predictions</h5>
                                        <button class="btn btn-primary" id="newPredictionBtn">
                                            <i class="fas fa-plus me-2"></i>New Prediction
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Prediction Form -->
                            <div class="row" id="predictionFormSection" style="display: none;">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-calculator me-2"></i>Student Performance Prediction</h6>
                                        </div>
                                        <div class="card-body">
                                            <form id="predictionForm">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="studentName" class="form-label">Student Name <span class="text-danger">*</span></label>
                                                            <input type="text" class="form-control" id="studentName" name="student_name" required 
                                                                   placeholder="Enter student's full name">
                                                            <div class="invalid-feedback">Please enter the student's name</div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="studyHours" class="form-label">Study Hours per Week</label>
                                                            <input type="number" class="form-control" id="studyHours" name="study_hours" min="0" max="50" step="0.5" required>
                                                            <div class="form-text">Average hours spent studying per week</div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="attendanceRate" class="form-label">Attendance Rate (%)</label>
                                                            <input type="number" class="form-control" id="attendanceRate" name="attendance_rate" min="0" max="100" step="1" required>
                                                            <div class="form-text">Percentage of classes attended</div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="previousGPA" class="form-label">Previous GPA (Grade Point Average)</label>
                                                            <input type="number" class="form-control" id="previousGPA" name="previous_gpa" min="0" max="4" step="0.1" required>
                                                            <div class="form-text">Previous semester GPA (0.0 - 4.0 scale)</div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="extracurricularHours" class="form-label">Extracurricular Hours</label>
                                                            <input type="number" class="form-control" id="extracurricularHours" name="extracurricular_hours" min="0" max="20" step="0.5" required>
                                                            <div class="form-text">Hours per week in extracurricular activities</div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="familyIncome" class="form-label">Family Income (Annual)</label>
                                                            <select class="form-select" id="familyIncome" name="family_income" required>
                                                                <option value="">Select Income Range</option>
                                                                <option value="25000">Under $25,000</option>
                                                                <option value="50000">$25,000 - $50,000</option>
                                                                <option value="75000">$50,000 - $75,000</option>
                                                                <option value="100000">$75,000 - $100,000</option>
                                                                <option value="150000">$100,000 - $150,000</option>
                                                                <option value="200000">Over $150,000</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="parentEducation" class="form-label">Parent Education Level</label>
                                                            <select class="form-select" id="parentEducation" name="parent_education" required>
                                                                <option value="">Select Education Level</option>
                                                                <option value="1">High School or Less</option>
                                                                <option value="2">Some College</option>
                                                                <option value="3">Bachelor's Degree</option>
                                                                <option value="4">Master's Degree</option>
                                                                <option value="5">Doctorate/Professional</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="sleepHours" class="form-label">Sleep Hours per Night</label>
                                                            <input type="number" class="form-control" id="sleepHours" name="sleep_hours" min="4" max="12" step="0.5" required>
                                                            <div class="form-text">Average hours of sleep per night</div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="socialMediaHours" class="form-label">Social Media Hours per Day</label>
                                                            <input type="number" class="form-control" id="socialMediaHours" name="social_media_hours" min="0" max="12" step="0.5" required>
                                                            <div class="form-text">Hours spent on social media daily</div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="studyGroupParticipation" class="form-label">Study Group Participation (%)</label>
                                                            <input type="number" class="form-control" id="studyGroupParticipation" name="study_group_participation" min="0" max="100" step="1" required>
                                                            <div class="form-text">Percentage of time studying in groups</div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="assignmentCompletionRate" class="form-label">Assignment Completion Rate (%)</label>
                                                            <input type="number" class="form-control" id="assignmentCompletionRate" name="assignment_completion_rate" min="0" max="100" step="1" required>
                                                            <div class="form-text">Percentage of assignments completed on time</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Additional Information Section -->
                                                <div class="row mt-4">
                                                    <div class="col-12">
                                                        <h6 class="text-primary border-bottom pb-2 mb-3">
                                                            <i class="fas fa-info-circle me-2"></i>Additional Information
                                                        </h6>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="studentAge" class="form-label">Student Age</label>
                                                            <input type="number" class="form-control" id="studentAge" name="student_age" min="13" max="25" step="1">
                                                            <div class="form-text">Optional: Student's current age</div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="currentGrade" class="form-label">Current Grade Level</label>
                                                            <select class="form-select" id="currentGrade" name="current_grade">
                                                                <option value="">Select Grade Level</option>
                                                                <option value="9">Grade 9</option>
                                                                <option value="10">Grade 10</option>
                                                                <option value="11">Grade 11</option>
                                                                <option value="12">Grade 12</option>
                                                                <option value="13">College Freshman</option>
                                                                <option value="14">College Sophomore</option>
                                                                <option value="15">College Junior</option>
                                                                <option value="16">College Senior</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="learningStyle" class="form-label">Preferred Learning Style</label>
                                                            <select class="form-select" id="learningStyle" name="learning_style">
                                                                <option value="">Select Learning Style</option>
                                                                <option value="visual">Visual Learner</option>
                                                                <option value="auditory">Auditory Learner</option>
                                                                <option value="kinesthetic">Kinesthetic Learner</option>
                                                                <option value="reading">Reading/Writing Learner</option>
                                                                <option value="mixed">Mixed Learning Style</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="motivationLevel" class="form-label">Motivation Level (1-10)</label>
                                                            <input type="range" class="form-range" id="motivationLevel" name="motivation_level" min="1" max="10" value="5">
                                                            <div class="d-flex justify-content-between">
                                                                <small>Low (1)</small>
                                                                <small id="motivationValue">5</small>
                                                                <small>High (10)</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-12">
                                                        <div class="d-flex gap-2">
                                                            <button type="submit" class="btn btn-primary">
                                                                <i class="fas fa-brain me-2"></i>Generate AI Prediction
                                                            </button>
                                                            <button type="button" class="btn btn-secondary" id="loadSampleData">
                                                                <i class="fas fa-download me-2"></i>Load Sample Data
                                                            </button>
                                                            <button type="button" class="btn btn-outline-secondary" id="clearForm">
                                                                <i class="fas fa-eraser me-2"></i>Clear Form
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Prediction Results -->
                            <div class="row" id="predictionResultsSection" style="display: none;">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Prediction Results</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="predictionResults"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Prediction History -->
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-history me-2"></i>Recent Predictions</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="predictionHistory">
                                                <div class="text-center py-4">
                                                    <i class="fas fa-brain fa-3x text-muted mb-3"></i>
                                                    <p class="text-muted">No predictions made yet. Create your first prediction above!</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `);
                        break;
                    case 'analytics':
                        contentDiv.html(`
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>AI Performance Analytics</h5>
                                    <p>Advanced machine learning analysis of student performance patterns and trends.</p>
                                    <div class="mt-3">
                                        <h6>Key AI Analysis:</h6>
                                        <ul>
                                            <li>Performance trends identified by AI</li>
                                            <li>Risk factors for academic success</li>
                                            <li>Predictive patterns in student data</li>
                                            <li>ML model performance metrics</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h5>AI-Generated Grade Distribution</h5>
                                    <div class="chart-container">
                                        <canvas id="gradeChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        `);
                        break;
                    case 'reports':
                        contentDiv.html(`
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <h5 class="mb-1"><i class="fas fa-chart-line me-2"></i>Reports</h5>
                                            <small class="text-muted">Auto-generated from recent predictions and student data</small>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-success btn-sm" id="btnExportCSV"><i class="fas fa-file-excel me-1"></i> Export as CSV</button>
                                            
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title">Performance Distribution</h6>
                                            <canvas id="reportPerfDist" height="220"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title">Predictions in Last 30 Days</h6>
                                            <canvas id="reportTrends" height="220"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title">Class Distribution</h6>
                                            <canvas id="classDistChart" height="220"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title">Recent Predictions</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm mb-0">
                                                    <thead>
                                                        <tr><th>Student</th><th>Prediction</th><th>Confidence</th><th>When</th></tr>
                                                    </thead>
                                                    <tbody id="recentPredTable"><tr><td colspan="4" class="text-muted">Loading...</td></tr></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `);
                        if (typeof loadReportsData === 'function') { loadReportsData(); }
                        // Wire up buttons
                        
                        $(document).off('click', '#btnExportCSV').on('click', '#btnExportCSV', function(){
                            exportReportsCSV();
                        });
                        
                        break;
                    case 'settings':
                        contentDiv.html(`
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>AI System Settings</h5>
                                    <form id="profileForm">
                                        <div class="mb-3">
                                            <label class="form-label">Full Name</label>
                                            <input type="text" class="form-control" id="fullName" name="full_name" value="<?php echo htmlspecialchars($user_name); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" disabled>
                                            <div class="form-text">Email is managed by admin and cannot be changed.</div>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                    </form>
                                    <hr/>
                                    <h5>Password Reset</h5>
                                    <form id="passwordForm">
                                        <div class="mb-3">
                                            <label class="form-label">Current Password</label>
                                            <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">New Password</label>
                                            <input type="password" class="form-control" id="newPassword" name="new_password" minlength="8" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" minlength="8" required>
                                        </div>
                                        <button type="submit" class="btn btn-outline-primary">Change Password</button>
                                    </form>
                                </div>
                                <div class="col-md-6">
                                    <h5>AI Model Settings</h5>
                                    <p>Configure AI prediction models and system preferences.</p>
                                    <div class="mt-3">
                                        <h6>AI Configuration:</h6>
                                        <ul>
                                            <li>Machine Learning Model: <strong>Active</strong></li>
                                            <li>Prediction Accuracy: <strong>94.7%</strong></li>
                                            <li>Data Processing: <strong>Real-time</strong></li>
                                            <li>Model Training: <strong>Automated</strong></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        `);
                        break;
                }
            }, 500);
        }

        function initializeAnimations() {
            // Animate elements on scroll
            gsap.registerPlugin(ScrollTrigger);
            
            gsap.utils.toArray('.fade-in').forEach((element, index) => {
                gsap.fromTo(element, 
                    { opacity: 0, y: 50 },
                    { 
                        opacity: 1, 
                        y: 0, 
                        duration: 0.8, 
                        delay: index * 0.1,
                        scrollTrigger: {
                            trigger: element,
                            start: "top 80%",
                            toggleActions: "play none none reverse"
                        }
                    }
                );
            });
        }

        function initializeCounters() {
            // Animate counters
            const counters = [
                { element: '#totalStudents', target: 15420, suffix: '' },
                { element: '#accuracyRate', target: 94.7, suffix: '%' },
                { element: '#totalPredictions', target: 28847, suffix: '' }
            ];

            counters.forEach(counter => {
                const element = $(counter.element);
                const target = counter.target;
                const suffix = counter.suffix;
                
                gsap.to({ value: 0 }, {
                    value: target,
                    duration: 2,
                    ease: "power2.out",
                    onUpdate: function() {
                        if (counter.element === '#accuracyRate') {
                            element.text(this.targets()[0].value.toFixed(1) + suffix);
                        } else {
                            element.text(Math.floor(this.targets()[0].value).toLocaleString() + suffix);
                        }
                    }
                });
            });
        }

        function initializeCharts() {
            // Performance Trends Chart
            const performanceCtx = document.getElementById('performanceChart').getContext('2d');
            new Chart(performanceCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Average Performance Score',
                        data: [75, 78, 82, 79, 85, 88, 86, 90, 87, 92, 89, 94],
                        borderColor: '#000000',
                        backgroundColor: 'rgba(0, 0, 0, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });

            // Grade Distribution Chart (Analytics section)
            const gradeEl = document.getElementById('gradeChart');
            if (gradeEl) {
                const gradeCtx = gradeEl.getContext('2d');
                // Build from live predictions if available, else static example
                $.ajax({
                    url: 'index.php',
                    type: 'POST',
                    data: { action: 'get_reports' },
                    dataType: 'json'
                }).done(function(res){
                    let labels = ['Excellent','Good','Average','Poor'];
                    let counts = [0,0,0,0];
                    if (res && res.success && res.reports && res.reports.performance_distribution) {
                        const dist = res.reports.performance_distribution;
                        labels = dist.map(x => x.predicted_label);
                        counts = dist.map(x => Number(x.count));
                    } else {
                        counts = [5,9,7,3];
                    }
                    new Chart(gradeCtx, {
                        type: 'bar',
                        data: { labels, datasets: [{ label: 'Count', data: counts, backgroundColor: ['#28a745','#0d6efd','#ffc107','#dc3545'] }] },
                        options: { scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } }
                    });
                });
            }
        }

        function initializeQuickActions() {
            $('#newPrediction').on('click', function(e) {
                e.preventDefault();
                // Navigate to predictions section
                $('.nav-link[data-section="predictions"]').click();
                showAlert('Prediction feature is now available! Click "New Prediction" in the predictions section.', 'success');
            });

            // Re-enable Upload Data quick action
            $('#uploadData').on('click', function(e) {
                e.preventDefault();
                openUploadDataModal();
            });

            $('#viewReports').on('click', function(e) {
                e.preventDefault();
                $('.nav-link[data-section="reports"]').click();
            });

            $('#exportData').on('click', function(e) {
                e.preventDefault();
                showAlert('Export feature coming soon!', 'info');
            });
        }

        // Reports data loader
        function loadReportsData() {
            $.ajax({
                url: 'index.php',
                type: 'POST',
                data: { action: 'get_reports' },
                dataType: 'json'
            }).done(function(res){
                if (!res.success) { return; }
                const r = res.reports || {};

                // Performance Distribution Pie
                const pd = r.performance_distribution || [];
                const pdLabels = (pd && pd.length ? pd : [{predicted_label:'No Data',count:1}]).map(x => x.predicted_label || 'No Data');
                const pdData = (pd && pd.length ? pd : [{predicted_label:'No Data',count:1}]).map(x => Number(x.count) || 1);
                if (document.getElementById('reportPerfDist')) {
                    const ctx = document.getElementById('reportPerfDist').getContext('2d');
                    if (window._perfChart) { window._perfChart.destroy(); }
                    window._perfChart = new Chart(ctx, {
                        type: 'doughnut',
                        data: { labels: pdLabels, datasets: [{ data: pdData, backgroundColor: ['#28a745','#0d6efd','#ffc107','#dc3545'] }] },
                        options: { plugins: { legend: { position: 'bottom' } } }
                    });
                }

                // Trends Line
                const tr = (r.prediction_trends || []).reverse();
                const tLabels = tr.map(x => x.date);
                const tData = tr.map(x => Number(x.count));
                if (document.getElementById('reportTrends')) {
                    new Chart(document.getElementById('reportTrends'), {
                        type: 'line',
                        data: { labels: tLabels, datasets: [{ label: 'Predictions', data: tData, borderColor: '#0d6efd', fill: false }] },
                        options: { scales: { y: { beginAtZero: true } } }
                    });
                }

                // Class Distribution Bar
                const cd = r.class_distribution || [];
                const cLabels = cd.map(x => x.class);
                const cData = cd.map(x => Number(x.count));
                if (document.getElementById('classDistChart')) {
                    new Chart(document.getElementById('classDistChart'), {
                        type: 'bar',
                        data: { labels: cLabels, datasets: [{ label: 'Students', data: cData, backgroundColor: '#6c757d' }] },
                        options: { scales: { y: { beginAtZero: true } } }
                    });
                }

                // Recent predictions table
                const rp = r.recent_predictions || [];
                const rows = rp.map(x => `<tr><td>${x.student_name || '-'}</td><td>${x.prediction}</td><td>${Math.round((x.confidence||0)*100)}%</td><td>${new Date(x.created_at).toLocaleString()}</td></tr>`).join('');
                $('#recentPredTable').html(rows || '<tr><td colspan="4" class="text-muted">No recent predictions</td></tr>');
            });
        }

        // Export: CSV of recent predictions and student stats
        function exportReportsCSV() {
            $.ajax({
                url: 'index.php',
                type: 'POST',
                data: { action: 'get_reports' },
                dataType: 'json'
            }).done(function(res){
                if (!res.success) { showAlert('Failed to export reports', 'danger'); return; }
                const r = res.reports || {};
                const rows = [['Student','Prediction','Confidence','Created At']];
                (r.recent_predictions || []).forEach(x => {
                    rows.push([x.student_name||'', x.prediction||'', Math.round((x.confidence||0)*100)+'%', x.created_at||'']);
                });
                const csv = rows.map(a => a.map(v => '"'+String(v).replace(/"/g,'""')+'"').join(',')).join('\n');
                const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'ai_reports_recent_predictions.csv';
                document.body.appendChild(a); a.click(); document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });
        }

        // Print: create a print view of the reports section
        function printReportsSection() {
            const printable = $('#reportsContent').html();
            const w = window.open('', '_blank');
            w.document.write('<html><head><title>AI Reports</title>');
            w.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
            w.document.write('</head><body class="p-3">');
            w.document.write(printable);
            w.document.write('</body></html>');
            w.document.close(); w.focus();
            setTimeout(() => { w.print(); w.close(); }, 300);
        }

        // (removed) downloadAllReportCharts

        // Upload Data Modal and handlers
        function openUploadDataModal() {
            const modalHtml = `
                <div class="modal fade" id="uploadDataModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-file-upload me-2"></i>Upload CSV Data</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">CSV File</label>
                                    <input type="file" class="form-control" id="uploadCsvFile" accept=".csv" />
                                    <div class="form-text">Expected headers: first_name,last_name,dob,gender,class</div>
                                </div>
                                <div id="uploadPreview" class="small text-muted"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" id="confirmUploadBtn" disabled>
                                    <i class="fas fa-upload me-2"></i>Upload
                                </button>
                            </div>
                        </div>
                    </div>
                </div>`;

            $('#uploadDataModal').remove();
            $('body').append(modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('uploadDataModal'));
            modal.show();

            $('#uploadCsvFile').off('change').on('change', function() {
                const file = this.files && this.files[0];
                if (!file) { $('#confirmUploadBtn').prop('disabled', true); return; }
                $('#uploadPreview').text(`${file.name} • ${(file.size/1024).toFixed(1)} KB`);
                $('#confirmUploadBtn').prop('disabled', false);
            });

            $('#confirmUploadBtn').off('click').on('click', function() {
                const file = $('#uploadCsvFile')[0].files[0];
                if (!file) return;
                const fd = new FormData();
                fd.append('action', 'upload_data');
                fd.append('csv_file', file);
                $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Uploading...');

                $.ajax({
                    url: 'index.php',
                    type: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    dataType: 'json'
                }).done(function(res){
                    if (res.success) {
                        const msg = res.message || 'Data uploaded successfully';
                        const pretty = `
                            <div class="d-flex align-items-start">
                                <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                <div>
                                    <strong>Upload complete</strong><br/>
                                    ${msg}
                                </div>
                            </div>`;
                        showAlert(pretty, 'success');
                        bootstrap.Modal.getInstance(document.getElementById('uploadDataModal')).hide();
                        $('#uploadDataModal').remove();
                    } else {
                        showAlert(res.message || 'Upload failed', 'danger');
                    }
                }).fail(function(xhr){
                    const msg = xhr && xhr.responseText ? xhr.responseText : 'Upload failed. Please check CSV and try again.';
                    showAlert(msg, 'danger');
                }).always(function(){
                    $('#confirmUploadBtn').prop('disabled', false).html('<i class="fas fa-upload me-2"></i>Upload');
                });
            });
        }

        function initializePredictionFeatures() {
            // New Prediction Button
            $(document).on('click', '#newPredictionBtn', function(e) {
                e.preventDefault();
                $('#predictionFormSection').slideDown();
                $('#predictionResultsSection').hide();
                $('html, body').animate({
                    scrollTop: $('#predictionFormSection').offset().top - 100
                }, 500);
            });

            // Load Sample Data
            $(document).on('click', '#loadSampleData', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'index.php',
                    type: 'POST',
                    data: { action: 'load_sample' },
                    dataType: 'json',
                    success: function(data) {
                        $('#studentName').val('John Smith');
                        $('#studyHours').val(data.study_hours);
                        $('#attendanceRate').val(data.attendance_rate);
                        $('#previousGPA').val(data.previous_gpa);
                        $('#extracurricularHours').val(data.extracurricular_hours);
                        $('#familyIncome').val(data.family_income);
                        $('#parentEducation').val(data.parent_education);
                        $('#sleepHours').val(data.sleep_hours);
                        $('#socialMediaHours').val(data.social_media_hours);
                        $('#studyGroupParticipation').val(data.study_group_participation);
                        $('#assignmentCompletionRate').val(data.assignment_completion_rate);
                        
                        // Load additional sample data
                        $('#studentAge').val(17);
                        $('#currentGrade').val('11');
                        $('#learningStyle').val('mixed');
                        $('#motivationLevel').val(7);
                        $('#motivationValue').text('7');
                        
                        showAlert('Sample data loaded successfully! You can now generate a prediction.', 'success');
                    },
                    error: function() {
                        showAlert('Failed to load sample data', 'danger');
                    }
                });
            });

            // Motivation level slider
            $(document).on('input', '#motivationLevel', function() {
                $('#motivationValue').text($(this).val());
            });

            // Clear Form
            $(document).on('click', '#clearForm', function(e) {
                e.preventDefault();
                $('#predictionForm')[0].reset();
                showAlert('Form cleared', 'info');
            });

            // Prediction Form Submission
            $(document).on('submit', '#predictionForm', function(e) {
                e.preventDefault();
                
                // Validate form before submission
                if (!validatePredictionForm()) {
                    showAlert('Please fill in all required fields correctly', 'warning');
                    return;
                }
                
                const formData = new FormData(this);
                formData.append('action', 'predict');
                
                // Show enhanced loading state
                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.html();
                submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Processing with AI Engine...').prop('disabled', true);
                
                // Disable all form inputs during processing
                $('#predictionForm input, #predictionForm select').prop('disabled', true);
                
                // Show progress indicator
                showProgressIndicator();
                
                $.ajax({
                    url: 'index.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    timeout: 8000,
                    success: function(response) {
                        // Ensure progress reaches final step before hiding
                        const modalEl = document.getElementById('predictionProgressModal');
                        const modalStep = $(modalEl).data('currentStep') || 1;
                        if (modalEl) {
                            for (let i = 1; i <= 5; i++) {
                                $(`#step${i}`).toggleClass('completed', i < 5).toggleClass('active', i === 5);
                                if (i < 5) {
                                    $(`#step${i} i`).removeClass('fa-spinner fa-spin text-primary text-muted').addClass('fa-check-circle text-success');
                                }
                            }
                            $('#predictionProgressBar').css('width', '100%');
                            // Reveal close button briefly, then auto-hide unless user clicks it
                            $('#progressCloseBtn').show();
                            setTimeout(function(){
                                if ($('#predictionProgressModal').length) {
                                    hideProgressIndicator();
                                }
                            }, 600);
                        } else {
                            hideProgressIndicator();
                        }
                        
                        if (response.success) {
                            // Add original form data to response for summary analysis
                            response.original_data = {
                                study_hours: parseFloat(formData.get('study_hours')),
                                attendance_rate: parseFloat(formData.get('attendance_rate')),
                                previous_gpa: parseFloat(formData.get('previous_gpa')),
                                assignment_completion_rate: parseFloat(formData.get('assignment_completion_rate')),
                                sleep_hours: parseFloat(formData.get('sleep_hours')),
                                social_media_hours: parseFloat(formData.get('social_media_hours')),
                                study_group_participation: parseFloat(formData.get('study_group_participation')),
                                motivation_level: parseInt(formData.get('motivation_level')) || 5
                            };
                            
                            displayPredictionResults(response);
                            addToPredictionHistory(response, formData.get('student_name'));
                            $('#predictionResultsSection').slideDown();
                            $('html, body').animate({
                                scrollTop: $('#predictionResultsSection').offset().top - 100
                            }, 500);
                            
                            // Show success message with model info
                            const modelType = response.model_info?.type || 'AI Engine';
                            showAlert(`AI prediction generated successfully using ${modelType}!`, 'success');
                        } else {
                            showAlert(response.message || 'Prediction failed', 'danger');
                            if (response.errors) {
                                displayValidationErrors(response.errors);
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        // Ensure modal is closed even on error after showing final step quickly
                        const modalEl = document.getElementById('predictionProgressModal');
                        if (modalEl) {
                            for (let i = 1; i <= 5; i++) {
                                $(`#step${i}`).toggleClass('completed', i < 5).toggleClass('active', i === 5);
                                if (i < 5) {
                                    $(`#step${i} i`).removeClass('fa-spinner fa-spin text-primary text-muted').addClass('fa-check-circle text-success');
                                }
                            }
                            $('#predictionProgressBar').css('width', '100%');
                            setTimeout(hideProgressIndicator, 200);
                        } else {
                            hideProgressIndicator();
                        }
                        console.error('Prediction error:', status, error);
                        if (status === 'timeout') {
                            showAlert('Prediction request timed out. Please try again.', 'warning');
                        } else {
                            showAlert('An error occurred during prediction. Please try again.', 'danger');
                        }
                    },
                    complete: function() {
                        submitBtn.html(originalText).prop('disabled', false);
                        $('#predictionForm input, #predictionForm select').prop('disabled', false);
                    }
                });
            });
        }

        // Save profile (full name only; email locked)
        $(document).on('submit', '#profileForm', function(e) {
            e.preventDefault();
            const fullName = $('#fullName').val().trim();
            if (!fullName) { showAlert('Full name is required', 'warning'); return; }
            $.ajax({
                url: 'index.php',
                type: 'POST',
                dataType: 'json',
                data: { action: 'update_full_name', full_name: fullName }
            }).done(function(res){
                if (res.success) {
                    showAlert('Profile updated successfully', 'success');
                } else {
                    showAlert(res.message || 'Update failed', 'danger');
                }
            }).fail(function(){
                showAlert('Network error while saving profile', 'danger');
            });
        });

        // Change password
        $(document).on('submit', '#passwordForm', function(e) {
            e.preventDefault();
            const current = $('#currentPassword').val();
            const newp = $('#newPassword').val();
            const conf = $('#confirmPassword').val();
            if (newp.length < 8) { showAlert('New password must be at least 8 characters', 'warning'); return; }
            if (newp !== conf) { showAlert('New passwords do not match', 'warning'); return; }
            $.ajax({
                url: 'index.php',
                type: 'POST',
                dataType: 'json',
                data: { action: 'change_password', current_password: current, new_password: newp }
            }).done(function(res){
                if (res.success) {
                    $('#passwordForm')[0].reset();
                    showAlert('Password changed successfully', 'success');
                } else {
                    showAlert(res.message || 'Failed to change password', 'danger');
                }
            }).fail(function(){
                showAlert('Network error while changing password', 'danger');
            });
        });

        function validatePredictionForm() {
            let isValid = true;
            const form = $('#predictionForm');
            
            // Reset validation
            form.find('.form-control').removeClass('is-valid is-invalid');
            form.find('.invalid-feedback').hide();
            
            // Validate student name
            const studentName = $('#studentName').val().trim();
            if (studentName.length < 2) {
                $('#studentName').addClass('is-invalid');
                $('#studentName').siblings('.invalid-feedback').show();
                isValid = false;
            } else {
                $('#studentName').addClass('is-valid');
            }
            
            // Validate study hours
            const studyHours = parseFloat($('#studyHours').val());
            if (isNaN(studyHours) || studyHours < 0 || studyHours > 50) {
                $('#studyHours').addClass('is-invalid');
                isValid = false;
            } else {
                $('#studyHours').addClass('is-valid');
            }
            
            // Validate attendance rate
            const attendanceRate = parseFloat($('#attendanceRate').val());
            if (isNaN(attendanceRate) || attendanceRate < 0 || attendanceRate > 100) {
                $('#attendanceRate').addClass('is-invalid');
                isValid = false;
            } else {
                $('#attendanceRate').addClass('is-valid');
            }
            
            // Validate GPA
            const gpa = parseFloat($('#previousGPA').val());
            if (isNaN(gpa) || gpa < 0 || gpa > 4) {
                $('#previousGPA').addClass('is-invalid');
                isValid = false;
            } else {
                $('#previousGPA').addClass('is-valid');
            }
            
            // Validate sleep hours
            const sleepHours = parseFloat($('#sleepHours').val());
            if (isNaN(sleepHours) || sleepHours < 4 || sleepHours > 12) {
                $('#sleepHours').addClass('is-invalid');
                isValid = false;
            } else {
                $('#sleepHours').addClass('is-valid');
            }
            
            // Check all required fields
            form.find('input[required], select[required]').each(function() {
                if (!$(this).val()) {
                    $(this).addClass('is-invalid');
                    isValid = false;
                }
            });
            
            return isValid;
        }

        function displayValidationErrors(errors) {
            errors.forEach(error => {
                showAlert(error, 'warning');
            });
        }

        function showProgressIndicator() {
            // Create progress modal
            const progressModal = `
                <div class="modal fade" id="predictionProgressModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-body text-center py-4">
                                <div class="mb-3">
                                    <i class="fas fa-brain fa-3x text-primary mb-3"></i>
                                    <h5 class="mb-3">AI Prediction Engine Processing</h5>
                                </div>
                                <div class="progress mb-3" style="height: 8px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                         role="progressbar" style="width: 0%" id="predictionProgressBar"></div>
                                </div>
                                <div class="progress-steps">
                                    <div class="step active" id="step1">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        <span>Validating input data...</span>
                                    </div>
                                    <div class="step" id="step2">
                                        <i class="fas fa-spinner fa-spin text-primary me-2"></i>
                                        <span>Initializing AI model...</span>
                                    </div>
                                    <div class="step" id="step3">
                                        <i class="fas fa-spinner text-muted me-2"></i>
                                        <span>Analyzing performance factors...</span>
                                    </div>
                                    <div class="step" id="step4">
                                        <i class="fas fa-spinner text-muted me-2"></i>
                                        <span>Generating predictions...</span>
                                    </div>
                                    <div class="step" id="step5">
                                        <i class="fas fa-spinner text-muted me-2"></i>
                                        <span>Creating recommendations...</span>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">This may take a few seconds...</small>
                            </div>
                            <div class="modal-footer justify-content-center py-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="progressCloseBtn" style="display:none;">
                                    Close
                                </button>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            $('#predictionProgressModal').remove();
            
            // Add modal to body
            $('body').append(progressModal);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('predictionProgressModal'));
            modal.show();

            // Close button handler (will be revealed at completion)
            $(document).off('click', '#progressCloseBtn').on('click', '#progressCloseBtn', function() {
                hideProgressIndicator();
            });
            
            // Animate progress steps with faster timing
            let currentStep = 1;
            const stepTimings = [200, 400, 350, 450, 250]; // Faster timings for each step
            
            function updateProgressStep() {
                if (currentStep <= 5) {
                    // Update progress bar
                    const progress = (currentStep / 5) * 100;
                    $('#predictionProgressBar').css('width', progress + '%');
                    
                    // Update steps
                    $(`.step`).removeClass('active completed');
                    $(`#step${currentStep}`).addClass('active');
                    
                    // Mark previous steps as completed
                    for (let i = 1; i < currentStep; i++) {
                        $(`#step${i}`).removeClass('active').addClass('completed');
                        $(`#step${i} i`).removeClass('fa-spinner fa-spin text-primary text-muted').addClass('fa-check-circle text-success');
                    }
                    
                    // Update step text based on progress
                    updateStepText(currentStep);
                    
                    currentStep++;
                    // Persist current step so other handlers can know progress
                    $('#predictionProgressModal').data('currentStep', currentStep);
                    
                    // Schedule next step
                    if (currentStep <= 5) {
                        setTimeout(updateProgressStep, stepTimings[currentStep - 1]);
                    } else {
                        // Ensure bar is full when steps complete
                        $('#predictionProgressBar').css('width', '100%');
                    }
                }
            }
            
            function updateStepText(step) {
                const stepTexts = {
                    1: 'Validating input data...',
                    2: 'Initializing AI model...',
                    3: 'Analyzing performance factors...',
                    4: 'Generating predictions...',
                    5: 'Creating recommendations...'
                };
                
                $(`#step${step} span`).text(stepTexts[step]);
            }
            
            // Start the progress animation
            setTimeout(updateProgressStep, stepTimings[0]);
            
            // Store step info for cleanup
            $('#predictionProgressModal').data('currentStep', currentStep);
        }

        function hideProgressIndicator() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('predictionProgressModal'));
            if (modal) {
                modal.hide();
            }
            $('#predictionProgressModal').remove();
        }

        function displayPredictionResults(response) {
            const prediction = response.prediction;
            const confidence = Math.round(response.confidence * 100);
            const performanceScore = response.performance_score;
            const probabilities = response.probabilities;
            const riskFactors = response.risk_factors || [];
            const recommendations = response.recommendations || [];
            const timestamp = response.timestamp || new Date().toLocaleString();
            
            let badgeClass = '';
            let iconClass = '';
            let bgColor = '';
            
            switch(prediction) {
                case 'Excellent':
                    badgeClass = 'bg-success text-white';
                    iconClass = 'fas fa-star';
                    bgColor = '#28a745';
                    break;
                case 'Good':
                    badgeClass = 'bg-primary text-white';
                    iconClass = 'fas fa-thumbs-up';
                    bgColor = '#007bff';
                    break;
                case 'Average':
                    badgeClass = 'bg-warning text-dark';
                    iconClass = 'fas fa-minus';
                    bgColor = '#ffc107';
                    break;
                case 'Poor':
                    badgeClass = 'bg-danger text-white';
                    iconClass = 'fas fa-exclamation-triangle';
                    bgColor = '#dc3545';
                    break;
            }
            
            const resultsHtml = `
                <div class="row">
                    <div class="col-md-8">
                        <div class="prediction-main-result mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="prediction-badge ${badgeClass} me-3">
                                    <i class="${iconClass} me-2"></i>${prediction}
                                </div>
                                <div>
                                    <h5 class="mb-1">Predicted Performance</h5>
                                    <p class="text-muted mb-0">Confidence: ${confidence}% | Generated: ${timestamp}</p>
                                </div>
                            </div>
                            <div class="progress mb-3" style="height: 20px;">
                                <div class="progress-bar" role="progressbar" style="width: ${confidence}%; background-color: ${bgColor};" 
                                     aria-valuenow="${confidence}" aria-valuemin="0" aria-valuemax="100">
                                    ${confidence}%
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h6 class="card-title">Performance Score</h6>
                                            <h3 class="text-primary">${performanceScore}/5.0</h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h6 class="card-title">Confidence Level</h6>
                                            <h3 class="text-success">${confidence}%</h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        ${riskFactors.length > 0 ? `
                        <div class="risk-factors mb-4">
                            <h6><i class="fas fa-exclamation-triangle text-warning me-2"></i>Risk Factors Identified</h6>
                            <div class="row">
                                ${riskFactors.map(factor => `
                                    <div class="col-md-6 mb-2">
                                        <div class="alert alert-warning alert-sm mb-0">
                                            <i class="fas fa-exclamation-circle me-2"></i>${factor}
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        ` : ''}
                        
                        ${recommendations.length > 0 ? `
                        <div class="recommendations">
                            <h6><i class="fas fa-lightbulb text-info me-2"></i>AI Recommendations</h6>
                            <div class="list-group">
                                ${recommendations.map(rec => `
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <i class="fas fa-arrow-right text-primary me-2"></i>
                                                ${typeof rec === 'string' ? rec : rec.description}
                                            </div>
                                            ${typeof rec === 'object' && rec.impact_percentage ? `
                                                <span class="badge bg-primary">${rec.impact_percentage}% Impact</span>
                                            ` : ''}
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        ` : ''}
                        
                        ${response.detailed_analysis ? `
                        <div class="detailed-analysis mt-4">
                            <h6><i class="fas fa-chart-line text-success me-2"></i>Detailed Analysis</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card bg-light mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-check-circle text-success me-2"></i>Academic Strengths</h6>
                                        </div>
                                        <div class="card-body">
                                            ${response.detailed_analysis.academic_strengths && response.detailed_analysis.academic_strengths.length > 0 ? 
                                                response.detailed_analysis.academic_strengths.map(strength => `
                                                    <div class="d-flex align-items-center mb-2">
                                                        <i class="fas fa-check text-success me-2"></i>
                                                        <small>${strength}</small>
                                                    </div>
                                                `).join('') : 
                                                '<small class="text-muted">No specific strengths identified</small>'
                                            }
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Areas for Improvement</h6>
                                        </div>
                                        <div class="card-body">
                                            ${response.detailed_analysis.academic_weaknesses && response.detailed_analysis.academic_weaknesses.length > 0 ? 
                                                response.detailed_analysis.academic_weaknesses.map(weakness => `
                                                    <div class="d-flex align-items-center mb-2">
                                                        <i class="fas fa-arrow-up text-warning me-2"></i>
                                                        <small>${weakness}</small>
                                                    </div>
                                                `).join('') : 
                                                '<small class="text-muted">No major weaknesses identified</small>'
                                            }
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            ${response.detailed_analysis.lifestyle_factors && response.detailed_analysis.lifestyle_factors.length > 0 ? `
                            <div class="card bg-light mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-heart text-danger me-2"></i>Lifestyle Factors</h6>
                                </div>
                                <div class="card-body">
                                    ${response.detailed_analysis.lifestyle_factors.map(factor => `
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-info-circle text-info me-2"></i>
                                            <small>${factor}</small>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                            ` : ''}
                            
                            ${response.detailed_analysis.social_factors && response.detailed_analysis.social_factors.length > 0 ? `
                            <div class="card bg-light mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-users text-primary me-2"></i>Social & Engagement Factors</h6>
                                </div>
                                <div class="card-body">
                                    ${response.detailed_analysis.social_factors.map(factor => `
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-comments text-primary me-2"></i>
                                            <small>${factor}</small>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                            ` : ''}
                        </div>
                        ` : ''}
                        
                        ${response.improvement_suggestions && response.improvement_suggestions.length > 0 ? `
                        <div class="improvement-suggestions mt-4">
                            <h6><i class="fas fa-target text-warning me-2"></i>Specific Improvement Suggestions</h6>
                            <div class="row">
                                ${response.improvement_suggestions.map(suggestion => `
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">${suggestion.area}</h6>
                                                <span class="badge bg-${suggestion.impact === 'Critical' ? 'danger' : suggestion.impact === 'High' ? 'warning' : 'info'}">${suggestion.impact}</span>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-2">
                                                    <small class="text-muted">Current: ${suggestion.current}</small><br>
                                                    <small class="text-muted">Recommended: ${suggestion.recommended}</small>
                                                </div>
                                                <div class="mb-2">
                                                    <div class="d-flex justify-content-between">
                                                        <small>Improvement Potential:</small>
                                                        <small class="fw-bold text-success">${suggestion.improvement_potential}</small>
                                                    </div>
                                                    <div class="progress" style="height: 6px;">
                                                        <div class="progress-bar bg-success" style="width: ${suggestion.improvement_potential}%"></div>
                                                    </div>
                                                </div>
                                                <p class="card-text small">${suggestion.specific_advice}</p>
                                            </div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        ` : ''}
                        
                        <div class="prediction-summary mt-4">
                            <h6><i class="fas fa-chart-pie text-success me-2"></i>Prediction Summary</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Key Strengths</h6>
                                            <ul class="list-unstyled mb-0">
                                                ${getStrengths(response).map(strength => `<li><i class="fas fa-check text-success me-2"></i>${strength}</li>`).join('')}
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Areas for Improvement</h6>
                                            <ul class="list-unstyled mb-0">
                                                ${getImprovementAreas(response).map(area => `<li><i class="fas fa-arrow-up text-warning me-2"></i>${area}</li>`).join('')}
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <h6>Probability Distribution</h6>
                        <div class="probability-chart mb-4">
                            ${Object.entries(probabilities).map(([category, prob]) => `
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>${category}</span>
                                    <div class="d-flex align-items-center">
                                        <div class="progress me-2" style="width: 100px; height: 8px;">
                                            <div class="progress-bar" style="width: ${Math.round(prob * 100)}%; background-color: ${bgColor};"></div>
                                        </div>
                                        <span class="text-muted">${Math.round(prob * 100)}%</span>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                        
                        <div class="prediction-actions">
                            <button class="btn btn-outline-primary btn-sm w-100 mb-2" onclick="window.print()">
                                <i class="fas fa-print me-2"></i>Print Results
                            </button>
                            <button class="btn btn-outline-secondary btn-sm w-100" onclick="exportPredictionResults()">
                                <i class="fas fa-download me-2"></i>Export Data
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            $('#predictionResults').html(resultsHtml);
        }

        function addToPredictionHistory(response, studentName) {
            const prediction = response.prediction;
            const confidence = Math.round(response.confidence * 100);
            const timestamp = new Date().toLocaleString();
            
            let badgeClass = '';
            let iconClass = '';
            
            switch(prediction) {
                case 'Excellent':
                    badgeClass = 'badge bg-success';
                    iconClass = 'fas fa-star';
                    break;
                case 'Good':
                    badgeClass = 'badge bg-primary';
                    iconClass = 'fas fa-thumbs-up';
                    break;
                case 'Average':
                    badgeClass = 'badge bg-warning text-dark';
                    iconClass = 'fas fa-minus';
                    break;
                case 'Poor':
                    badgeClass = 'badge bg-danger';
                    iconClass = 'fas fa-exclamation-triangle';
                    break;
            }
            
            const historyItem = `
                <div class="prediction-history-item border-bottom py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="${iconClass} fa-2x text-muted"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">${studentName || 'Anonymous Student'}</h6>
                                <p class="text-muted mb-0">Predicted: <span class="${badgeClass}">${prediction}</span> (${confidence}% confidence)</p>
                            </div>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">${timestamp}</small>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove the "no predictions" message if it exists
            $('#predictionHistory .text-center').remove();
            
            // Add to top of history
            $('#predictionHistory').prepend(historyItem);
        }

        // Helper functions for prediction summary
        function getStrengths(response) {
            const strengths = [];
            const data = response.original_data || {};
            
            if (data.study_hours >= 7) strengths.push('Strong study habits');
            if (data.attendance_rate >= 90) strengths.push('Excellent attendance');
            if (data.previous_gpa >= 3.5) strengths.push('High academic performance');
            if (data.assignment_completion_rate >= 85) strengths.push('Reliable assignment completion');
            if (data.sleep_hours >= 7) strengths.push('Good sleep habits');
            if (data.social_media_hours <= 2) strengths.push('Balanced social media use');
            if (data.motivation_level >= 7) strengths.push('High motivation level');
            if (data.study_group_participation >= 60) strengths.push('Active in group learning');
            
            return strengths.length > 0 ? strengths : ['Consistent academic effort'];
        }

        function getImprovementAreas(response) {
            const areas = [];
            const data = response.original_data || {};
            
            if (data.study_hours < 6) areas.push('Increase study time');
            if (data.attendance_rate < 85) areas.push('Improve attendance');
            if (data.previous_gpa < 3.0) areas.push('Focus on academic performance');
            if (data.assignment_completion_rate < 80) areas.push('Better assignment management');
            if (data.sleep_hours < 7) areas.push('Optimize sleep schedule');
            if (data.social_media_hours > 3) areas.push('Reduce social media usage');
            if (data.motivation_level < 6) areas.push('Build motivation strategies');
            if (data.study_group_participation < 40) areas.push('Engage more in group study');
            
            return areas.length > 0 ? areas : ['Continue current positive trends'];
        }

        // Export prediction results function
        function exportPredictionResults() {
            const results = $('#predictionResults').html();
            const blob = new Blob([`
                <html>
                <head>
                    <title>AI_SAPP Prediction Results</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                </head>
                <body>
                    <div class="container mt-4">
                        <h2>AI Student Performance Prediction Results</h2>
                        <p>Generated on: ${new Date().toLocaleString()}</p>
                        ${results}
                    </div>
                </body>
                </html>
            `], { type: 'text/html' });
            
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `prediction-results-${new Date().toISOString().split('T')[0]}.html`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        function loadDashboardData() {
            // Load activity feed
            loadActivityFeed();
        }

        function loadActivityFeed(page = 1) {
            $.ajax({
                url: 'index.php',
                type: 'POST',
                data: { action: 'get_activity', page: page, per_page: 10 },
                dataType: 'json'
            }).done(function(res) {
                if (!res.success) { return; }
                const activities = res.activities || [];
                let activityHtml = '';
                activities.forEach(activity => {
                    const when = new Date(activity.created_at).toLocaleString();
                    activityHtml += `
                        <div class="activity-item">
                            <div class="activity-icon" style="background: ${activity.color || 'var(--primary-color)'};">
                                <i class="${activity.icon || 'fas fa-info'}"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">${activity.title}</div>
                                <div class="activity-time">${when}</div>
                            </div>
                        </div>
                    `;
                });

                // Pagination controls
                const pg = res.pagination || { page: 1, pages: 1 };
                let pager = '';
                if (pg.pages > 1) {
                    pager = `
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <button class="btn btn-sm btn-outline-secondary" id="actPrev" ${pg.page <= 1 ? 'disabled' : ''}>Previous</button>
                            <small class="text-muted">Page ${pg.page} of ${pg.pages}</small>
                            <button class="btn btn-sm btn-outline-secondary" id="actNext" ${pg.page >= pg.pages ? 'disabled' : ''}>Next</button>
                        </div>`;
                }

                $('#activityFeed').html((activityHtml || '<p class="text-muted">No recent activity</p>') + pager);

                // Wire buttons
                $('#actPrev').off('click').on('click', function(){ loadActivityFeed(pg.page - 1); });
                $('#actNext').off('click').on('click', function(){ loadActivityFeed(pg.page + 1); });
            });
        }

        function preventBackAccess() {
            // Clear browser history to prevent back button access
            window.history.replaceState(null, null, window.location.href);
            
            // Add beforeunload event to warn about leaving
            window.addEventListener('beforeunload', function(e) {
                e.preventDefault();
                e.returnValue = '';
            });
            
            // Check session validity periodically
            setInterval(function() {
                checkSessionValidity();
            }, 30000); // Check every 30 seconds
        }
        
        function checkSessionValidity() {
            $.ajax({
                url: 'check_session.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (!response.valid) {
                        // Session is invalid, redirect to login
                        window.location.href = 'login.php';
                    }
                },
                error: function() {
                    // If check fails, assume session is invalid
                    window.location.href = 'login.php';
                }
            });
        }

        function showAlert(message, type) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show position-fixed" 
                     style="top: 100px; right: 20px; z-index: 9999; min-width: 300px;">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $('body').append(alertHtml);
            
            setTimeout(() => {
                $('.alert').fadeOut();
            }, 3000);
        }
    </script>
</body>
</html>