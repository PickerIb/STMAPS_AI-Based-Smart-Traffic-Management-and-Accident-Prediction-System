<?php
require_once 'config.php';
session_start();

// Require user to be logged in
requireLogin();

$user_name = $_SESSION['user_name'] ?? 'User';
$user_email = $_SESSION['user_email'] ?? 'user@example.com';
$user_type = $_SESSION['user_type'] ?? 'driver';
$user_type_label = getUserTypeLabel($user_type);

$nav_sections = [];
switch ($user_type) {
    case 'driver':
        $nav_sections = [
            ['id' => 'dashboard', 'icon' => 'fa-home', 'label' => 'Home'],
            ['id' => 'route-map', 'icon' => 'fa-route', 'label' => 'Route Guidance'],
            ['id' => 'road-status', 'icon' => 'fa-road', 'label' => 'Road Status'],
            ['id' => 'notifications', 'icon' => 'fa-bell', 'label' => 'Notifications'],
            ['id' => 'travel-history', 'icon' => 'fa-history', 'label' => 'Travel History'],
            ['id' => 'profile', 'icon' => 'fa-user', 'label' => 'Profile'],
        ];
        break;
    case 'traffic_authority':
        $nav_sections = [
            ['id' => 'dashboard', 'icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
            ['id' => 'road-status', 'icon' => 'fa-road', 'label' => 'Road Status'],
            ['id' => 'predictions', 'icon' => 'fa-edit', 'label' => 'Enter Road Data'],
            ['id' => 'analytics', 'icon' => 'fa-exclamation-triangle', 'label' => 'Report Incidents'],
            ['id' => 'reports', 'icon' => 'fa-chart-pie', 'label' => 'Reports'],
        ];
        break;
    case 'administrator':
        $nav_sections = [
            ['id' => 'dashboard', 'icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
            ['id' => 'settings', 'icon' => 'fa-users', 'label' => 'User Management'],
            ['id' => 'reports', 'icon' => 'fa-file-alt', 'label' => 'Reports'],
            ['id' => 'analytics', 'icon' => 'fa-cog', 'label' => 'System Settings'],
        ];
        break;
    default:
        $nav_sections = [
            ['id' => 'dashboard', 'icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
        ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STRGS - Smart Traffic Route Guidance System</title>
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
    <?php if ($user_type === 'driver'): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <link rel="stylesheet" href="static/driver-dashboard.css">
    <?php elseif ($user_type === 'traffic_authority'): ?>
    <link rel="stylesheet" href="static/driver-dashboard.css">
    <?php endif; ?>

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

        .header-notify-btn {
            position: relative;
            width: 44px;
            height: 44px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: #fff;
            color: var(--primary-color);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--shadow);
        }

        .header-notify-btn:hover {
            background: #f8fafc;
            transform: translateY(-1px);
        }

        .header-notify-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            min-width: 20px;
            height: 20px;
            padding: 0 5px;
            border-radius: 999px;
            background: #dc2626;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 700;
            line-height: 20px;
            text-align: center;
            border: 2px solid #fff;
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.35);
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
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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

        .user-type {
            color: var(--secondary-color);
            font-size: 0.78rem;
            margin: 0;
            text-transform: capitalize;
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
                <img src="logo1.png" alt="STRGS Logo">
            </div>
            <div class="sidebar-title">STRGS</div>
        </div>
        
        <div class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </div>
        
        <nav class="sidebar-nav">
            <?php foreach ($nav_sections as $index => $nav): ?>
            <div class="nav-item">
                <a href="#<?php echo htmlspecialchars($nav['id']); ?>" class="nav-link<?php echo $index === 0 ? ' active' : ''; ?>" data-section="<?php echo htmlspecialchars($nav['id']); ?>">
                    <i class="fas <?php echo htmlspecialchars($nav['icon']); ?> nav-icon"></i>
                    <span class="nav-text"><?php echo htmlspecialchars($nav['label']); ?></span>
                </a>
            </div>
            <?php endforeach; ?>
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
            <h1 class="page-title" id="pageTitle">Smart Traffic Route Guidance System</h1>
            <div class="user-menu">
                <?php if ($user_type === 'driver'): ?>
                <button type="button" class="header-notify-btn" id="btnHeaderNotifications" title="Notifications" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="header-notify-badge" id="headerNotifyBadge" style="display:none">0</span>
                </button>
                <?php endif; ?>
                <div class="user-avatar" id="topUserAvatar">
                    <?php if (!empty($_SESSION['profile_picture'] ?? null)): ?>
                        <img src="<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>" alt="User avatar">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <div class="user-name" id="topUserName"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="user-email" id="topUserEmail"><?php echo htmlspecialchars($user_email); ?></div>
                    <div class="user-type"><?php echo htmlspecialchars($user_type_label); ?></div>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <?php if ($user_type === 'driver'): ?>
                <?php include __DIR__ . '/includes/driver_dashboard.php'; ?>
            <?php else: ?>
            <!-- Dashboard Section -->
            <div id="dashboard-section" class="content-section">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card fade-in">
                        <div class="stat-icon primary">
                            <i class="fas fa-road"></i>
                        </div>
                        <div class="stat-number" id="totalStudents">0</div>
                        <div class="stat-label">Total Road Segments</div>
                    </div>
                    <div class="stat-card fade-in">
                        <div class="stat-icon secondary">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-number" id="accuracyRate">0%</div>
                        <div class="stat-label">Road Status Accuracy</div>
                    </div>
                    <div class="stat-card fade-in">
                        <div class="stat-icon accent">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <div class="stat-number" id="totalPredictions">0</div>
                        <div class="stat-label">Route Analyses</div>
                    </div>
                    <div class="stat-card fade-in">
                        <div class="stat-icon success">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-number" id="systemUptime">99.8%</div>
                        <div class="stat-label">Platform Uptime</div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="dashboard-card fade-in h-100">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-calendar-day"></i>
                                    Current Month Trends (Daily)
                                </h3>
                            </div>
                            <small class="text-muted">Daily route guidance volume and road status trends for this month.</small>
                            <div class="chart-container mt-2">
                                <canvas id="performanceChartMonth"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="dashboard-card fade-in h-100">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-calendar-alt"></i>
                                    Current Year Trends (Monthly)
                                </h3>
                            </div>
                            <small class="text-muted">Month-by-month route guidance summary for the current year.</small>
                            <div class="chart-container mt-2">
                                <canvas id="performanceChartYear"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card fade-in mb-4">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-map-marked-alt"></i>
                            District Road Status Comparison
                        </h3>
                    </div>
                    <small class="text-muted">Average congestion by district (zone) based on road status history.</small>
                    <div class="chart-container mt-2">
                        <canvas id="districtRiskChart"></canvas>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <?php if (in_array($user_type, ['driver', 'traffic_authority'], true)): ?>
                    <a href="#" class="action-btn fade-in" id="newPrediction">
                        <div class="action-icon">
                            <i class="fas fa-<?php echo $user_type === 'driver' ? 'route' : 'edit'; ?>"></i>
                        </div>
                        <span><?php echo $user_type === 'driver' ? 'Get Route Guidance' : 'Update Road Status'; ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if ($user_type === 'traffic_authority'): ?>
                    <a href="#" class="action-btn fade-in" id="reportIncident">
                        <div class="action-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <span>Report Incident</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($user_type === 'administrator'): ?>
                    <a href="#" class="action-btn fade-in" id="manageUsers">
                        <div class="action-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <span>Manage Users</span>
                    </a>
                    <?php endif; ?>
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
            <?php if ($user_type === 'traffic_authority'): ?>
            <div id="road-status-section" class="content-section" style="display: none;">
                <?php $road_status_mode = 'authority'; include __DIR__ . '/includes/road_status_panel.php'; ?>
            </div>
            <?php endif; ?>

            <div id="predictions-section" class="content-section" style="display: none;">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-route"></i>
                            Route Guidance
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
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <?php if ($user_type === 'driver'): ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script>window.STRGS_ROAD_STATUS_MODE = 'view';</script>
    <script src="static/road-status.js"></script>
    <script src="static/driver-dashboard.js"></script>
    <?php else: ?>
    <?php if ($user_type === 'traffic_authority'): ?>
    <script>window.STRGS_ROAD_STATUS_MODE = 'authority';</script>
    <script src="static/road-status.js"></script>
    <?php endif; ?>
    <script>
        const STRGS_USER_TYPE = <?php echo json_encode($user_type); ?>;
        const STRGS_NAV_LABELS = <?php echo json_encode(array_column($nav_sections, 'label', 'id')); ?>;

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
                    'dashboard': 'Smart Traffic Route Guidance System',
                    'road-status': 'Road Status — Remera Sector',
                    'predictions': STRGS_USER_TYPE === 'driver' ? 'Route Guidance' : (STRGS_USER_TYPE === 'traffic_authority' ? 'Enter Road Data' : 'Route Analysis'),
                    'analytics': STRGS_USER_TYPE === 'driver' ? 'Road Status' : (STRGS_USER_TYPE === 'traffic_authority' ? 'Report Incidents' : 'System Settings'),
                    'reports': STRGS_USER_TYPE === 'traffic_authority' ? 'Reports' : 'Reports & Analytics',
                    'settings': 'User Management'
                };
                $('#pageTitle').text(titles[section] || STRGS_NAV_LABELS[section] || 'Smart Traffic Route Guidance System');
                
                // Show/hide sections
                $('.content-section').hide();
                $(`#${section}-section`).show();

                if (section === 'road-status' && window.STRGSRoadStatus) {
                    STRGSRoadStatus.load(true);
                    STRGSRoadStatus.startRefresh();
                } else if (window.STRGSRoadStatus) {
                    STRGSRoadStatus.stopRefresh();
                }
                
                // Load section content via AJAX
                if (section !== 'road-status') {
                    loadSectionContent(section);
                }
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
                                        <h5><i class="fas fa-route me-2"></i>${STRGS_USER_TYPE === 'driver' ? 'Route Guidance' : 'Road Status Updates'}</h5>
                                        <button class="btn btn-primary" id="newPredictionBtn">
                                            <i class="fas fa-plus me-2"></i>${STRGS_USER_TYPE === 'driver' ? 'Get Route Guidance' : 'Update Road Status'}
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Prediction Form -->
                            <div class="row" id="predictionFormSection" style="display: none;">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-calculator me-2"></i>${STRGS_USER_TYPE === 'driver' ? 'Route Guidance Request' : 'Road Status & Congestion Update'}</h6>
                                        </div>
                                        <div class="card-body">
                                            <form id="predictionForm">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="districtName" class="form-label">District <span class="text-danger">*</span></label>
                                                            <input type="text" class="form-control" id="districtName" list="districtOptions"
                                                                   placeholder="Type full district name (e.g., Kirehe)" autocomplete="off" required>
                                                            <datalist id="districtOptions"></datalist>
                                                            <div class="form-text">Type/select a district, then choose a road segment in that district.</div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="segmentSelect" class="form-label">Road Segment <span class="text-danger">*</span></label>
                                                            <select class="form-select" id="segmentSelect" name="segment_id" required>
                                                                <option value="">Select district first</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="readingTime" class="form-label">Reading Time <span class="text-danger">*</span></label>
                                                            <input type="datetime-local" class="form-control" id="readingTime" name="reading_time" required>
                                                            <div class="form-text">Timestamp for the traffic reading</div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="vehicleCount" class="form-label">Vehicle Count <span class="text-danger">*</span></label>
                                                            <input type="number" class="form-control" id="vehicleCount" name="vehicle_count" min="0" max="200000" step="1" required>
                                                            <div class="form-text">Vehicles observed during the interval</div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="avgSpeed" class="form-label">Average Speed (km/h) <span class="text-danger">*</span></label>
                                                            <input type="number" class="form-control" id="avgSpeed" name="avg_speed_kmh" min="0" max="200" step="0.1" required>
                                                            <div class="form-text">Estimated average speed on the segment</div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="density" class="form-label">Density (veh/km) <span class="text-danger">*</span></label>
                                                            <input type="number" class="form-control" id="density" name="density_veh_per_km" min="0" max="500" step="0.1" required>
                                                            <div class="form-text">Estimated density per kilometer</div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="weather" class="form-label">Weather <span class="text-danger">*</span></label>
                                                            <select class="form-select" id="weather" name="weather" required>
                                                                <option value="">Select Weather</option>
                                                                <option value="clear">Clear</option>
                                                                <option value="rain">Rain</option>
                                                                <option value="fog">Fog</option>
                                                                <option value="storm">Storm</option>
                                                                <option value="snow">Snow</option>
                                                                <option value="unknown">Unknown</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="visibility" class="form-label">Visibility (m)</label>
                                                            <input type="number" class="form-control" id="visibility" name="visibility_m" min="0" max="50000" step="1"
                                                                   placeholder="e.g., 1200">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="temperature" class="form-label">Temperature (°C)</label>
                                                            <input type="number" class="form-control" id="temperature" name="temperature_c" min="-40" max="60" step="0.1"
                                                                   placeholder="e.g., 23.5">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-12">
                                                        <div class="d-flex gap-2">
                                                            <button type="submit" class="btn btn-primary">
                                                                <i class="fas fa-route me-2"></i>${STRGS_USER_TYPE === 'driver' ? 'Get Route Guidance' : 'Submit Road Status'}
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
                                            <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Route Guidance Results</h6>
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
                                            <h6 class="mb-0"><i class="fas fa-history me-2"></i>Recent Route Guidance</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="predictionHistory">
                                                <div class="text-center py-4">
                                                    <i class="fas fa-brain fa-3x text-muted mb-3"></i>
                                                    <p class="text-muted">No route guidance requests yet. Start by requesting guidance above.</p>
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
                                    <h5>${STRGS_USER_TYPE === 'driver' ? 'Road Status Overview' : (STRGS_USER_TYPE === 'traffic_authority' ? 'Report Incidents' : 'System Settings')}</h5>
                                    <p>Advanced machine learning analysis of traffic flow patterns, congestion behavior, and accident risk trends.</p>
                                    <div class="mt-3">
                                        <h6>Key Road Status Insights:</h6>
                                        <ul>
                                            <li>Traffic flow trends identified by AI</li>
                                            <li>Risk factors for road safety incidents</li>
                                            <li>Predictive patterns in traffic readings</li>
                                            <li>Congestion and accident risk model metrics</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h5>Congestion Distribution</h5>
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
                                            <small class="text-muted">Based on recent traffic readings, zones, and road status updates</small>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-success btn-sm" id="btnExportCSV"><i class="fas fa-file-excel me-1"></i> Export as CSV</button>
                                            
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title">Congestion Level Distribution</h6>
                                            <canvas id="reportPerfDist" height="220"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title">Traffic Readings in Last 30 Days</h6>
                                            <canvas id="reportTrends" height="220"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title">Zone Distribution</h6>
                                            <canvas id="classDistChart" height="220"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title">Recent Route Guidance & Road Status</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm mb-0">
                                                    <thead>
                                                        <tr><th>Segment</th><th>Congestion</th><th>Road Risk</th><th>Confidence</th><th>When</th></tr>
                                                    </thead>
                                                    <tbody id="recentPredTable"><tr><td colspan="5" class="text-muted">Loading...</td></tr></tbody>
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
                                    <h5>User Management</h5>
                                    <p>Manage drivers, traffic police officers, and admin accounts for STRGS.</p>
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
                                    <h5>System Settings</h5>
                                    <p>Configure STRGS route guidance preferences and system options.</p>
                                    <div class="mt-3">
                                        <h6>System Configuration:</h6>
                                        <ul>
                                            <li>Machine Learning Model: <strong>Active</strong></li>
                                            <li>Risk Detection Rate: <strong>94.7%</strong></li>
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
                { element: '#totalStudents', target: 247, suffix: '' },
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
            const monthEl = document.getElementById('performanceChartMonth');
            const yearEl = document.getElementById('performanceChartYear');
            const districtRiskEl = document.getElementById('districtRiskChart');
            if (monthEl || yearEl || districtRiskEl) {
                $.ajax({
                    url: 'index.php',
                    type: 'POST',
                    data: { action: 'get_reports' },
                    dataType: 'json'
                }).done(function(res){
                    const reports = ((res || {}).reports || {});
                    const dailyTrend = reports.prediction_current_month_daily || [];
                    const yearlyTrend = reports.prediction_current_year_monthly || [];
                    const districtRiskTrend = reports.district_risk_distribution || [];

                    function buildTrendChart(canvasEl, labels, countSeries, confidenceSeries, riskSeries, countAxisTitle) {
                        if (!canvasEl) return null;
                        return new Chart(canvasEl.getContext('2d'), {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [
                                    {
                                        label: 'Routes guided',
                                        data: countSeries,
                                        borderColor: '#0d6efd',
                                        backgroundColor: 'rgba(13, 110, 253, 0.10)',
                                        borderWidth: 3,
                                        fill: true,
                                        tension: 0.35,
                                        yAxisID: 'y'
                                    },
                                    {
                                        label: 'Average confidence (%)',
                                        data: confidenceSeries,
                                        borderColor: '#198754',
                                        backgroundColor: 'rgba(25, 135, 84, 0.08)',
                                        borderWidth: 2,
                                        fill: false,
                                        tension: 0.35,
                                        yAxisID: 'y1'
                                    },
                                    {
                                        label: 'Average accident risk (%)',
                                        data: riskSeries,
                                        borderColor: '#dc3545',
                                        backgroundColor: 'rgba(220, 53, 69, 0.08)',
                                        borderWidth: 2,
                                        fill: false,
                                        tension: 0.35,
                                        yAxisID: 'y1'
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: true, position: 'bottom' },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                const label = context.dataset.label || '';
                                                const val = context.parsed.y;
                                                if (label.includes('%')) return `${label}: ${val}%`;
                                                return `${label}: ${val}`;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        title: { display: true, text: countAxisTitle }
                                    },
                                    y1: {
                                        beginAtZero: true,
                                        max: 100,
                                        position: 'right',
                                        grid: { drawOnChartArea: false },
                                        title: { display: true, text: 'Percentage (%)' }
                                    }
                                }
                            }
                        });
                    }

                    // Daily view for current month (include all days for clarity)
                    if (monthEl) {
                        const now = new Date();
                        const year = now.getFullYear();
                        const month = now.getMonth(); // 0-based
                        const daysInMonth = new Date(year, month + 1, 0).getDate();
                        const byDay = {};
                        dailyTrend.forEach(x => { byDay[Number(x.day_num)] = x; });

                        const dayLabels = [];
                        const dayCounts = [];
                        const dayConfidence = [];
                        const dayRisk = [];
                        for (let d = 1; d <= daysInMonth; d++) {
                            const row = byDay[d] || {};
                            dayLabels.push(String(d));
                            dayCounts.push(Number(row.predictions_count) || 0);
                            dayConfidence.push(Math.round((Number(row.avg_confidence) || 0) * 100));
                            dayRisk.push(Math.round((Number(row.avg_accident_risk) || 0) * 100));
                        }

                        if (window._performanceChartMonth) { window._performanceChartMonth.destroy(); }
                        window._performanceChartMonth = buildTrendChart(
                            monthEl,
                            dayLabels,
                            dayCounts,
                            dayConfidence,
                            dayRisk,
                            'Routes per day'
                        );
                    }

                    // Monthly view for current year (all 12 months)
                    if (yearEl) {
                        const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                        const byMonth = {};
                        yearlyTrend.forEach(x => { byMonth[Number(x.month_num)] = x; });

                        const yearCounts = [];
                        const yearConfidence = [];
                        const yearRisk = [];
                        for (let m = 1; m <= 12; m++) {
                            const row = byMonth[m] || {};
                            yearCounts.push(Number(row.predictions_count) || 0);
                            yearConfidence.push(Math.round((Number(row.avg_confidence) || 0) * 100));
                            yearRisk.push(Math.round((Number(row.avg_accident_risk) || 0) * 100));
                        }

                        if (window._performanceChartYear) { window._performanceChartYear.destroy(); }
                        window._performanceChartYear = buildTrendChart(
                            yearEl,
                            monthNames,
                            yearCounts,
                            yearConfidence,
                            yearRisk,
                            'Routes per month'
                        );
                    }

                    // District risk comparison
                    if (districtRiskEl) {
                        const hasDistrictData = districtRiskTrend.length > 0;
                        const labels = hasDistrictData ? districtRiskTrend.map(x => x.zone || 'Unknown') : ['No data'];
                        const riskData = hasDistrictData ? districtRiskTrend.map(x => Math.round((Number(x.avg_accident_risk) || 0) * 100)) : [0];
                        const predictionCounts = hasDistrictData ? districtRiskTrend.map(x => Number(x.predictions_count) || 0) : [0];

                        if (window._districtRiskChart) { window._districtRiskChart.destroy(); }
                        window._districtRiskChart = new Chart(districtRiskEl.getContext('2d'), {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Average accident risk (%)',
                                    data: riskData,
                                    backgroundColor: 'rgba(220, 53, 69, 0.75)',
                                    borderColor: '#dc3545',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: true, position: 'bottom' },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                const i = context.dataIndex;
                                                const risk = context.parsed.y;
                                                const count = predictionCounts[i] || 0;
                                                return `Congestion: ${risk}% (from ${count} route analyses)`;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        max: 100,
                                        title: { display: true, text: 'Average accident risk (%)' }
                                    },
                                    x: {
                                        title: { display: true, text: 'Districts (zones)' }
                                    }
                                }
                            }
                        });
                    }
                });
            }

            // Grade Distribution Chart (Analytics section)
            const gradeEl = document.getElementById('gradeChart');
            if (gradeEl) {
                const gradeCtx = gradeEl.getContext('2d');
                // Build from live traffic reports if available, else static example
                $.ajax({
                    url: 'index.php',
                    type: 'POST',
                    data: { action: 'get_reports' },
                    dataType: 'json'
                }).done(function(res){
                    let labels = ['Low','Moderate','High','Severe'];
                    let counts = [0,0,0,0];
                    if (res && res.success && res.reports && res.reports.congestion_distribution) {
                        const dist = res.reports.congestion_distribution;
                        labels = dist.map(x => {
                            const raw = String(x.congestion_level || 'no_data');
                            return raw.charAt(0).toUpperCase() + raw.slice(1);
                        });
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
                const target = STRGS_USER_TYPE === 'traffic_authority' ? 'road-status' : 'predictions';
                $('.nav-link[data-section="' + target + '"]').click();
                const msg = STRGS_USER_TYPE === 'driver'
                    ? 'Route guidance is ready. Click "Get Route Guidance" to find the best route.'
                    : 'Road Status is ready. Use Enter / Update Road Data to submit field readings.';
                showAlert(msg, 'success');
            });

            $('#reportIncident').on('click', function(e) {
                e.preventDefault();
                $('.nav-link[data-section="analytics"]').click();
                showAlert('Report accidents, congestion, or road blockages in this section.', 'info');
            });

            $('#manageUsers').on('click', function(e) {
                e.preventDefault();
                $('.nav-link[data-section="settings"]').click();
                showAlert('Manage drivers, traffic police officers, and admin accounts here.', 'info');
            });

            $('#viewReports').on('click', function(e) {
                e.preventDefault();
                const target = STRGS_USER_TYPE === 'traffic_authority' ? 'road-status' : (STRGS_USER_TYPE === 'driver' ? 'analytics' : 'reports');
                $('.nav-link[data-section="' + target + '"]').click();
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

                // Congestion Distribution Pie
                const pd = r.congestion_distribution || [];
                const pdFallback = [{ congestion_level: 'no_data', count: 1 }];
                const pdDataSource = (pd && pd.length ? pd : pdFallback);
                const pdLabels = pdDataSource.map(x => {
                    const raw = String(x.congestion_level || 'no_data');
                    return raw.charAt(0).toUpperCase() + raw.slice(1);
                });
                const pdData = pdDataSource.map(x => Number(x.count) || 1);
                if (document.getElementById('reportPerfDist')) {
                    const ctx = document.getElementById('reportPerfDist').getContext('2d');
                    if (window._perfChart) { window._perfChart.destroy(); }
                    window._perfChart = new Chart(ctx, {
                        type: 'doughnut',
                        data: { labels: pdLabels, datasets: [{ data: pdData, backgroundColor: ['#28a745','#0d6efd','#ffc107','#dc3545'] }] },
                        options: { plugins: { legend: { position: 'bottom' } } }
                    });
                }

                // Traffic trends line
                const tr = (r.traffic_trends || []).reverse();
                const tLabels = tr.map(x => x.date);
                const tData = tr.map(x => Number(x.count));
                if (document.getElementById('reportTrends')) {
                    new Chart(document.getElementById('reportTrends'), {
                        type: 'line',
                        data: { labels: tLabels, datasets: [{ label: 'Traffic readings', data: tData, borderColor: '#0d6efd', fill: false }] },
                        options: { scales: { y: { beginAtZero: true } } }
                    });
                }

                // Zone Distribution Bar
                const cd = r.zone_distribution || [];
                const cLabels = cd.map(x => x.zone || 'Unknown');
                const cData = cd.map(x => Number(x.count));
                if (document.getElementById('classDistChart')) {
                    new Chart(document.getElementById('classDistChart'), {
                        type: 'bar',
                        data: { labels: cLabels, datasets: [{ label: 'Road segments', data: cData, backgroundColor: '#6c757d' }] },
                        options: { scales: { y: { beginAtZero: true } } }
                    });
                }

                // Recent traffic predictions table
                const rp = r.recent_predictions || [];
                const rows = rp.map(x => {
                    const confidence = Math.round((Number(x.confidence) || 0) * 100);
                    const accidentRisk = Math.round((Number(x.accident_probability) || 0) * 100);
                    const congestion = x.congestion_level ? (x.congestion_level.charAt(0).toUpperCase() + x.congestion_level.slice(1)) : '-';
                    const segment = x.segment_name || (x.segment_id ? `Segment #${x.segment_id}` : '-');
                    const when = x.created_at ? new Date(x.created_at).toLocaleString() : '-';
                    return `<tr><td>${segment}</td><td>${congestion}</td><td>${accidentRisk}%</td><td>${confidence}%</td><td>${when}</td></tr>`;
                }).join('');
                $('#recentPredTable').html(rows || '<tr><td colspan="5" class="text-muted">No recent route guidance records</td></tr>');
            });
        }

        // Export: CSV of recent AI traffic predictions
        function exportReportsCSV() {
            $.ajax({
                url: 'index.php',
                type: 'POST',
                data: { action: 'get_reports' },
                dataType: 'json'
            }).done(function(res){
                if (!res.success) { showAlert('Failed to export reports', 'danger'); return; }
                const r = res.reports || {};
                const rows = [['Segment','Congestion Level','Road Risk','Confidence','Created At']];
                (r.recent_predictions || []).forEach(x => {
                    rows.push([
                        x.segment_name || (x.segment_id ? `Segment #${x.segment_id}` : ''),
                        x.congestion_level || '',
                        Math.round((Number(x.accident_probability)||0)*100) + '%',
                        Math.round((Number(x.confidence)||0)*100) + '%',
                        x.created_at || ''
                    ]);
                });
                const csv = rows.map(a => a.map(v => '"'+String(v).replace(/"/g,'""')+'"').join(',')).join('\n');
                const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'strgs_route_guidance_reports.csv';
                document.body.appendChild(a); a.click(); document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });
        }

        // Print: create a print view of the reports section
        function printReportsSection() {
            const printable = $('#reportsContent').html();
            const w = window.open('', '_blank');
            w.document.write('<html><head><title>STRGS Reports</title>');
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
                                    <div class="form-text">Expected traffic headers: segment_name, reading_time, vehicle_count, avg_speed_kmh, density_veh_per_km, weather (optional: visibility_m, temperature_c, source)</div>
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
            let allSegments = [];
            let districts = [];

            function loadDistricts() {
                $.ajax({
                    url: 'index.php',
                    type: 'POST',
                    data: { action: 'get_districts' },
                    dataType: 'json'
                }).done(function(res) {
                    if (!res || !res.success || !Array.isArray(res.districts)) return;
                    districts = res.districts;
                    $('#districtOptions').html(districts.map(d => `<option value="${d}"></option>`).join(''));
                });
            }

            function loadSegmentsByDistrict(districtName) {
                $('#segmentSelect').html('<option value="">Loading segments...</option>');
                $.ajax({
                    url: 'index.php',
                    type: 'POST',
                    data: { action: 'get_segments', district: districtName || '' },
                    dataType: 'json'
                }).done(function(res) {
                    if (!res || !res.success || !Array.isArray(res.segments)) {
                        $('#segmentSelect').html('<option value="">No segments found</option>');
                        return;
                    }
                    allSegments = res.segments;
                    if (!allSegments.length) {
                        $('#segmentSelect').html('<option value="">No segments in this district</option>');
                        return;
                    }
                    const options = ['<option value="">Select road segment</option>']
                        .concat(allSegments.map(s => `<option value="${s.id}">${s.name}</option>`));
                    $('#segmentSelect').html(options.join(''));
                }).fail(function() {
                    $('#segmentSelect').html('<option value="">Failed to load segments</option>');
                });
            }

            // New Prediction Button
            $(document).on('click', '#newPredictionBtn', function(e) {
                e.preventDefault();
                $('#predictionFormSection').slideDown();
                $('#predictionResultsSection').hide();
                loadDistricts();
                $('html, body').animate({
                    scrollTop: $('#predictionFormSection').offset().top - 100
                }, 500);
            });

            // District search -> load district segments
            $(document).on('change blur', '#districtName', function() {
                const district = ($(this).val() || '').trim();
                if (!district) {
                    $('#segmentSelect').html('<option value="">Select district first</option>');
                    return;
                }
                loadSegmentsByDistrict(district);
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
                        // Fill core fields
                        const now = new Date();
                        const localDate = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0,16);
                        $('#readingTime').val(data.reading_time ? String(data.reading_time).replace(' ', 'T').slice(0,16) : localDate);
                        $('#vehicleCount').val(data.vehicle_count);
                        $('#avgSpeed').val(data.avg_speed_kmh);
                        $('#density').val(data.density_veh_per_km);
                        $('#weather').val(data.weather);
                        $('#visibility').val(data.visibility_m ?? '');
                        $('#temperature').val(data.temperature_c ?? '');

                        // Resolve district + segment for sample
                        $.ajax({
                            url: 'index.php',
                            type: 'POST',
                            data: { action: 'get_segments' },
                            dataType: 'json'
                        }).done(function(res){
                            if (!res || !res.success || !Array.isArray(res.segments)) return;
                            const match = res.segments.find(x => String(x.id) === String(data.segment_id));
                            if (match) {
                                $('#districtName').val(match.zone);
                                loadSegmentsByDistrict(match.zone);
                                setTimeout(function() {
                                    $('#segmentSelect').val(String(match.id));
                                }, 150);
                            }
                        });

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
                submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Processing route guidance...').prop('disabled', true);
                
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
                            displayPredictionResults(response);
                            addToPredictionHistory(response, formData.get('segment_id'));
                            $('#predictionResultsSection').slideDown();
                            $('html, body').animate({
                                scrollTop: $('#predictionResultsSection').offset().top - 100
                            }, 500);
                            
                            // Show generic success message without engine/internal wording
                            showAlert('Route guidance generated successfully!', 'success');
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

            // District and segment must be selected
            const districtName = ($('#districtName').val() || '').trim();
            const segmentId = parseInt($('#segmentSelect').val(), 10);
            if (!districtName) {
                $('#districtName').addClass('is-invalid');
                isValid = false;
            } else {
                $('#districtName').addClass('is-valid');
            }
            if (Number.isNaN(segmentId) || segmentId <= 0) {
                $('#segmentSelect').addClass('is-invalid');
                isValid = false;
            } else {
                $('#segmentSelect').addClass('is-valid');
            }

            // Core required traffic inputs
            const vehicleCount = parseInt($('#vehicleCount').val(), 10);
            const avgSpeed = parseFloat($('#avgSpeed').val());
            const density = parseFloat($('#density').val());
            const readingTime = $('#readingTime').val();

            if (!readingTime) { $('#readingTime').addClass('is-invalid'); isValid = false; } else { $('#readingTime').addClass('is-valid'); }
            if (Number.isNaN(vehicleCount) || vehicleCount < 0 || vehicleCount > 200000) { $('#vehicleCount').addClass('is-invalid'); isValid = false; } else { $('#vehicleCount').addClass('is-valid'); }
            if (Number.isNaN(avgSpeed) || avgSpeed < 0 || avgSpeed > 200) { $('#avgSpeed').addClass('is-invalid'); isValid = false; } else { $('#avgSpeed').addClass('is-valid'); }
            if (Number.isNaN(density) || density < 0 || density > 500) { $('#density').addClass('is-invalid'); isValid = false; } else { $('#density').addClass('is-valid'); }
            
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
                                    <h5 class="mb-3">STRGS Route Guidance Processing</h5>
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
                                        <span>Loading road status data...</span>
                                    </div>
                                    <div class="step" id="step3">
                                        <i class="fas fa-spinner text-muted me-2"></i>
                                        <span>Analyzing performance factors...</span>
                                    </div>
                                    <div class="step" id="step4">
                                        <i class="fas fa-spinner text-muted me-2"></i>
                                        <span>Calculating best route...</span>
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
                    2: 'Loading road status data...',
                    3: 'Analyzing performance factors...',
                    4: 'Calculating best route...',
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
            const generatedNow = new Date().toLocaleString([], {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });

            // Smart Traffic format (new project)
            if (response && (response.congestion_level !== undefined || response.accident_probability !== undefined)) {
                const level = (response.congestion_level || 'moderate').toString();
                const confidencePct = Math.round((response.confidence || 0) * 100);
                const accidentPct = Math.round((response.accident_probability || 0) * 100);
                const congestionIndex = (response.congestion_index !== undefined && response.congestion_index !== null) ? response.congestion_index : '—';
                const explanations = response.explanations || [];
                const recommendations = response.recommendations || [];
                const timestamp = generatedNow;

                const badgeMap = {
                    low:    { cls: 'bg-success text-white', icon: 'fas fa-check-circle', color: '#28a745' },
                    moderate:{ cls: 'bg-warning text-dark', icon: 'fas fa-circle-half-stroke', color: '#ffc107' },
                    high:   { cls: 'bg-danger text-white', icon: 'fas fa-triangle-exclamation', color: '#dc3545' },
                    severe: { cls: 'bg-dark text-white', icon: 'fas fa-skull-crossbones', color: '#212529' },
                };
                const b = badgeMap[level] || badgeMap.moderate;

                const resultsHtml = `
                    <div class="row">
                        <div class="col-md-8">
                            <div class="prediction-main-result mb-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="prediction-badge ${b.cls} me-3">
                                        <i class="${b.icon} me-2"></i>${level.toUpperCase()}
                                    </div>
                                    <div>
                                        <h5 class="mb-1">Route Guidance Result</h5>
                                        <p class="text-muted mb-0">Confidence: ${confidencePct}% | Generated: ${timestamp}</p>
                                    </div>
                                </div>
                                <div class="progress mb-3" style="height: 18px;">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: ${confidencePct}%; background-color: ${b.color};"
                                         aria-valuenow="${confidencePct}" aria-valuemin="0" aria-valuemax="100">
                                        ${confidencePct}%
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h6 class="card-title">Road Risk Level</h6>
                                                <h3 class="text-danger">${accidentPct}%</h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h6 class="card-title">Congestion Index</h6>
                                                <h3 class="text-primary">${congestionIndex}</h3>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            ${explanations.length ? `
                            <div class="mb-4">
                                <h6><i class="fas fa-circle-info text-info me-2"></i>Key Explanations</h6>
                                <ul class="list-group">
                                    ${explanations.map(x => `<li class="list-group-item"><i class="fas fa-chevron-right me-2 text-muted"></i>${x}</li>`).join('')}
                                </ul>
                            </div>` : ''}

                            ${recommendations.length ? `
                            <div class="recommendations">
                                <h6><i class="fas fa-lightbulb text-warning me-2"></i>Recommended Actions</h6>
                                <div class="list-group">
                                    ${recommendations.map(rec => `
                                        <div class="list-group-item">
                                            <i class="fas fa-arrow-right text-primary me-2"></i>${rec}
                                        </div>
                                    `).join('')}
                                </div>
                            </div>` : ''}
                        </div>
                        <div class="col-md-4">
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
                return;
            }

            const prediction = response.prediction;
            const confidence = Math.round(response.confidence * 100);
            const performanceScore = response.performance_score;
            const probabilities = response.probabilities;
            const riskFactors = response.risk_factors || [];
            const recommendations = response.recommendations || [];
            const timestamp = generatedNow;
            
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
                            <h6><i class="fas fa-lightbulb text-info me-2"></i>Route Recommendations</h6>
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
                            <h6><i class="fas fa-chart-pie text-success me-2"></i>Route Guidance Summary</h6>
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
            // Smart Traffic format (new project)
            if (response && (response.congestion_level !== undefined || response.accident_probability !== undefined)) {
                const segmentId = studentName; // caller now passes segment_id
                const level = (response.congestion_level || 'moderate').toString();
                const confidence = Math.round((response.confidence || 0) * 100);
                const accidentPct = Math.round((response.accident_probability || 0) * 100);
                const timestamp = new Date().toLocaleString();

                const badgeMap = {
                    low: 'badge bg-success',
                    moderate: 'badge bg-warning text-dark',
                    high: 'badge bg-danger',
                    severe: 'badge bg-dark'
                };
                const badgeClass = badgeMap[level] || badgeMap.moderate;
                const iconClass = (level === 'low') ? 'fas fa-check-circle'
                    : (level === 'moderate') ? 'fas fa-circle-half-stroke'
                    : (level === 'high') ? 'fas fa-triangle-exclamation'
                    : 'fas fa-skull-crossbones';

                const historyItem = `
                    <div class="prediction-history-item border-bottom py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="${iconClass} fa-2x text-muted"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Segment #${segmentId || '—'}</h6>
                                    <small class="text-muted">${timestamp} • Road risk: ${accidentPct}%</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="${badgeClass}">${level.toUpperCase()}</span><br>
                                <small class="text-muted">${confidence}% confidence</small>
                            </div>
                        </div>
                    </div>
                `;

                $('#predictionHistory').prepend(historyItem);
                return;
            }

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
                    <title>STRGS Route Guidance Results</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                </head>
                <body>
                    <div class="container mt-4">
                        <h2>STRGS Route Guidance Results</h2>
                        <p>Generated on: ${new Date().toLocaleString()}</p>
                        ${results}
                    </div>
                </body>
                </html>
            `], { type: 'text/html' });
            
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `strgs-route-results-${new Date().toISOString().split('T')[0]}.html`;
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
    <?php endif; ?>
</body>
</html>