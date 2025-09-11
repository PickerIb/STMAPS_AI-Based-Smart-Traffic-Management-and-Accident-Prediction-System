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
                <a href="#students" class="nav-link" data-section="students">
                    <i class="fas fa-users nav-icon"></i>
                    <span class="nav-text">Student Data</span>
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
                            <i class="fas fa-upload"></i>
                        </div>
                        <span>Upload Data</span>
                    </a>
                    <a href="#" class="action-btn fade-in" id="viewReports">
                        <div class="action-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <span>View Reports</span>
                    </a>
                    <a href="#" class="action-btn fade-in" id="exportData">
                        <div class="action-icon">
                            <i class="fas fa-download"></i>
                        </div>
                        <span>Export Data</span>
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
            <div id="students-section" class="content-section" style="display: none;">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-users"></i>
                            Students Management
                        </h3>
                    </div>
                    <div id="studentsContent">
                        <!-- Students content will be loaded via AJAX -->
                    </div>
                </div>
            </div>

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
                    'students': 'Student Management',
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
                    case 'students':
                        contentDiv.html(`
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Student Performance Data</h5>
                                    <p>Total Students in Database: <strong>1,247</strong></p>
                                    <p>Students with Complete Data: <strong>1,189</strong></p>
                                    <p>New Students This Month: <strong>58</strong></p>
                                    <p>Students Ready for AI Analysis: <strong>1,156</strong></p>
                                </div>
                                <div class="col-md-6">
                                    <h5>Recent Student Additions</h5>
                                    <div class="activity-item">
                                        <div class="activity-icon" style="background: var(--success-color);">
                                            <i class="fas fa-user-plus"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title">Student John Doe added to AI system</div>
                                            <div class="activity-time">2 hours ago</div>
                                        </div>
                                    </div>
                                    <div class="activity-item">
                                        <div class="activity-icon" style="background: var(--accent-color);">
                                            <i class="fas fa-database"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title">Student performance data updated</div>
                                            <div class="activity-time">4 hours ago</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `);
                        break;
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
                                <div class="col-md-12">
                                    <h5>AI-Generated Reports</h5>
                                    <p>Generate comprehensive reports using AI analysis of student performance data.</p>
                                    <div class="quick-actions">
                                        <a href="#" class="action-btn">
                                            <div class="action-icon">
                                                <i class="fas fa-file-pdf"></i>
                                            </div>
                                            <span>AI Performance Report</span>
                                        </a>
                                        <a href="#" class="action-btn">
                                            <div class="action-icon">
                                                <i class="fas fa-file-excel"></i>
                                            </div>
                                            <span>ML Data Export</span>
                                        </a>
                                        <a href="#" class="action-btn">
                                            <div class="action-icon">
                                                <i class="fas fa-brain"></i>
                                            </div>
                                            <span>AI Analysis Report</span>
                                        </a>
                                        <a href="#" class="action-btn">
                                            <div class="action-icon">
                                                <i class="fas fa-chart-line"></i>
                                            </div>
                                            <span>Prediction Analytics</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        `);
                        break;
                    case 'settings':
                        contentDiv.html(`
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>AI System Settings</h5>
                                    <form>
                                        <div class="mb-3">
                                            <label class="form-label">Full Name</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_name); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user_email); ?>">
                                        </div>
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
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
        }

        function initializeQuickActions() {
            $('#newPrediction').on('click', function(e) {
                e.preventDefault();
                // Navigate to predictions section
                $('.nav-link[data-section="predictions"]').click();
                showAlert('Prediction feature is now available! Click "New Prediction" in the predictions section.', 'success');
            });

            $('#uploadData').on('click', function(e) {
                e.preventDefault();
                showAlert('Data upload feature coming soon!', 'info');
            });

            $('#viewReports').on('click', function(e) {
                e.preventDefault();
                showAlert('Reports feature coming soon!', 'info');
            });

            $('#exportData').on('click', function(e) {
                e.preventDefault();
                showAlert('Export feature coming soon!', 'info');
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
                
                // Show loading state
                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.html();
                submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Generating AI Prediction...').prop('disabled', true);
                
                // Disable all form inputs during processing
                $('#predictionForm input, #predictionForm select').prop('disabled', true);
                
                $.ajax({
                    url: 'index.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
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
                            showAlert('AI prediction generated successfully!', 'success');
                        } else {
                            showAlert(response.message || 'Prediction failed', 'danger');
                            if (response.errors) {
                                displayValidationErrors(response.errors);
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Prediction error:', error);
                        showAlert('An error occurred during prediction. Please try again.', 'danger');
                    },
                    complete: function() {
                        submitBtn.html(originalText).prop('disabled', false);
                        $('#predictionForm input, #predictionForm select').prop('disabled', false);
                    }
                });
            });
        }

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
                                        <i class="fas fa-arrow-right text-primary me-2"></i>${rec}
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

        function loadActivityFeed() {
            const activities = [
                {
                    icon: 'fas fa-check',
                    title: 'New prediction completed',
                    time: '2 minutes ago',
                    color: 'var(--success-color)'
                },
                {
                    icon: 'fas fa-upload',
                    title: 'Student data uploaded',
                    time: '15 minutes ago',
                    color: 'var(--accent-color)'
                },
                {
                    icon: 'fas fa-chart-line',
                    title: 'Performance report generated',
                    time: '1 hour ago',
                    color: 'var(--warning-color)'
                },
                {
                    icon: 'fas fa-user-plus',
                    title: 'New student added to system',
                    time: '2 hours ago',
                    color: 'var(--primary-color)'
                }
            ];

            let activityHtml = '';
            activities.forEach(activity => {
                activityHtml += `
                    <div class="activity-item">
                        <div class="activity-icon" style="background: ${activity.color};">
                            <i class="${activity.icon}"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">${activity.title}</div>
                            <div class="activity-time">${activity.time}</div>
                        </div>
                    </div>
                `;
            });

            $('#activityFeed').html(activityHtml);
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