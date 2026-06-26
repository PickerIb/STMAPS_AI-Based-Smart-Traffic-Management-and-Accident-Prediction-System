<?php
/** Administrator dashboard — included from dashboard.php */
$admin_user_id = (int)($_SESSION['user_id'] ?? 0);
$admin_first = $_SESSION['first_name'] ?? explode(' ', $user_name)[0] ?? 'Admin';
$admin_last = trim((string)($_SESSION['last_name'] ?? ''));
$admin_display_name = trim($admin_first . ($admin_last !== '' ? ' ' . $admin_last : ''));
if ($admin_display_name === '') {
    $admin_display_name = $user_name;
}
$admin_avatar = $_SESSION['profile_picture'] ?? null;
$admin_initials = strtoupper(substr($admin_first, 0, 1) . ($admin_last !== '' ? substr($admin_last, 0, 1) : ''));
if ($admin_initials === '') {
    $admin_initials = strtoupper(substr($user_name, 0, 1));
}

try {
    $dbTmp = (new Database())->getConnection();
    if ($dbTmp) {
        $u = new User($dbTmp);
        if ($u->findById($admin_user_id)) {
            $admin_avatar = $u->profile_picture;
            $_SESSION['profile_picture'] = $admin_avatar;
        }
    }
} catch (Exception $e) {
    // non-fatal
}
?>

<!-- Admin: Home -->
<div id="dashboard-section" class="content-section admin-section">
    <div class="admin-welcome-hero card border-0 shadow-sm mb-4">
        <div class="admin-welcome-hero__accent" aria-hidden="true"></div>
        <div class="card-body admin-welcome-hero__body">
            <div class="admin-welcome-hero__profile">
                <div class="admin-welcome-hero__avatar" aria-hidden="true">
                    <?php if (!empty($admin_avatar)): ?>
                        <img src="<?php echo htmlspecialchars($admin_avatar); ?>" alt="">
                    <?php else: ?>
                        <span><?php echo htmlspecialchars($admin_initials); ?></span>
                    <?php endif; ?>
                </div>
                <span class="admin-welcome-hero__status">
                    <i class="fas fa-circle" aria-hidden="true"></i> Online
                </span>
            </div>

            <div class="admin-welcome-hero__main">
                <p class="admin-welcome-hero__eyebrow">
                    <i class="fas fa-gauge-high" aria-hidden="true"></i> Dashboard Home
                </p>
                <h2 class="admin-welcome-hero__title">
                    Welcome, <span class="admin-welcome-hero__name"><?php echo htmlspecialchars($admin_first); ?></span>
                </h2>
                <p class="admin-welcome-hero__subtitle">Administrator Dashboard — STRGS System Management</p>
                <ul class="admin-welcome-hero__highlights">
                    <li><i class="fas fa-users" aria-hidden="true"></i><span>Manage users</span></li>
                    <li><i class="fas fa-traffic-light" aria-hidden="true"></i><span>Monitor traffic data</span></li>
                    <li><i class="fas fa-sliders" aria-hidden="true"></i><span>Configure system settings</span></li>
                </ul>
            </div>

            <div class="admin-welcome-hero__aside">
                <div class="admin-welcome-hero__role-card">
                    <div class="admin-welcome-hero__role-icon" aria-hidden="true">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div>
                        <div class="admin-welcome-hero__role-label">Your role</div>
                        <div class="admin-welcome-hero__role-badge"><?php echo htmlspecialchars($user_type_label); ?></div>
                    </div>
                </div>
                <p class="admin-welcome-hero__role-desc">
                    Signed in as <strong><?php echo htmlspecialchars($admin_display_name); ?></strong>.
                    Oversee accounts, review traffic operations, and keep STRGS running smoothly.
                </p>
                <div class="admin-welcome-hero__meta">
                    <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i> Remera Sector</span>
                    <span><i class="fas fa-shield-halved" aria-hidden="true"></i> System Admin</span>
                </div>
            </div>
        </div>
    </div>

    <div class="stats-grid driver-stats mb-4">
        <div class="stat-card stat-clickable" data-section-jump="users">
            <div class="stat-icon stat-icon-success"><i class="fas fa-car"></i></div>
            <div class="stat-number" id="adminStatDrivers">—</div>
            <div class="stat-label">Drivers</div>
            <small class="text-muted">Registered driver accounts</small>
        </div>
        <div class="stat-card stat-clickable" data-section-jump="users">
            <div class="stat-icon stat-icon-warning"><i class="fas fa-user-shield"></i></div>
            <div class="stat-number" id="adminStatOfficers">—</div>
            <div class="stat-label">Traffic Officers</div>
            <small class="text-muted">Traffic police accounts</small>
        </div>
        <div class="stat-card stat-clickable" data-section-jump="users">
            <div class="stat-icon stat-icon-issues"><i class="fas fa-user-gear"></i></div>
            <div class="stat-number" id="adminStatAdmins">—</div>
            <div class="stat-label">Administrators</div>
            <small class="text-muted">System admin accounts</small>
        </div>
        <div class="stat-card stat-clickable" data-section-jump="users">
            <div class="stat-icon stat-icon-success"><i class="fas fa-circle-check"></i></div>
            <div class="stat-number" id="adminStatActive">—</div>
            <div class="stat-label">Active Users</div>
            <small class="text-muted">Currently enabled accounts</small>
        </div>
        <div class="stat-card stat-clickable" data-section-jump="traffic-monitoring" data-monitor-tab="road-status">
            <div class="stat-icon stat-icon-success"><i class="fas fa-road"></i></div>
            <div class="stat-number" id="adminStatRoadRecords">—</div>
            <div class="stat-label">Road Status Records</div>
            <small class="text-muted">Recent road updates</small>
        </div>
        <div class="stat-card stat-clickable" data-section-jump="traffic-monitoring" data-monitor-tab="accidents">
            <div class="stat-icon stat-icon-danger"><i class="fas fa-car-burst"></i></div>
            <div class="stat-number" id="adminStatAccidents">—</div>
            <div class="stat-label">Accidents</div>
            <small class="text-muted">Reported incidents</small>
        </div>
        <div class="stat-card stat-clickable" data-section-jump="traffic-monitoring" data-monitor-tab="congestion">
            <div class="stat-icon stat-icon-warning"><i class="fas fa-traffic-light"></i></div>
            <div class="stat-number" id="adminStatCongestion">—</div>
            <div class="stat-label">Congestion Alerts</div>
            <small class="text-muted">Traffic congestion reports</small>
        </div>
        <div class="stat-card stat-clickable" data-section-jump="traffic-monitoring" data-monitor-tab="blockages">
            <div class="stat-icon stat-icon-issues"><i class="fas fa-ban"></i></div>
            <div class="stat-number" id="adminStatBlockages">—</div>
            <div class="stat-label">Road Blockages</div>
            <small class="text-muted">Closed or blocked roads</small>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="dashboard-card h-100">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-chart-line"></i> System Activity</h3>
                    <small class="text-muted">Daily activity across users and traffic operations</small>
                </div>
                <div class="card-body">
                    <div class="auth-chart-wrap auth-chart-wrap-lg">
                        <canvas id="adminActivityChart" aria-label="System activity chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="dashboard-card h-100">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-clock-rotate-left"></i> Recent Activities</h3>
                </div>
                <div id="adminRecentActivity" class="driver-feed p-3">Loading…</div>
            </div>
        </div>
    </div>

    <div class="quick-actions mt-4">
        <a href="#users" class="action-btn admin-nav-jump" data-section="users"><div class="action-icon"><i class="fas fa-users"></i></div><span>Manage Users</span></a>
        <a href="#traffic-monitoring" class="action-btn admin-nav-jump" data-section="traffic-monitoring"><div class="action-icon"><i class="fas fa-traffic-light"></i></div><span>Traffic Monitoring</span></a>
        <a href="#system" class="action-btn admin-nav-jump" data-section="system"><div class="action-icon"><i class="fas fa-cog"></i></div><span>System Settings</span></a>
        <a href="#notifications" class="action-btn admin-nav-jump" data-section="notifications"><div class="action-icon"><i class="fas fa-bell"></i></div><span>Announcements</span></a>
    </div>
</div>

<!-- Admin: User Management -->
<div id="users-section" class="content-section admin-section" style="display:none;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-users me-2"></i>User Management</h2>
            <p class="text-muted mb-0">Create, edit, and manage driver, traffic officer, and administrator accounts.</p>
        </div>
        <button type="button" class="btn btn-dark" id="btnAdminAddUser">
            <i class="fas fa-user-plus me-1"></i>Add User
        </button>
    </div>

    <div class="dashboard-card mb-4">
        <div class="card-header">
            <h3 class="card-title mb-0"><i class="fas fa-list"></i> All Users</h3>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="search" class="form-control" id="adminUserSearch" placeholder="Search by name, email, phone, or username…">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="adminUserRoleFilter">
                        <option value="">All roles</option>
                        <option value="driver">Driver</option>
                        <option value="traffic_authority">Traffic Officer</option>
                        <option value="administrator">Administrator</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="adminUserStatusFilter">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="table-responsive admin-users-table-wrap">
                <table class="table table-hover align-middle mb-0 admin-users-table">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="admin-user-col-num">#</th>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Phone</th>
                            <th scope="col">Role</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last Login</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="adminUsersTable">
                        <tr><td colspan="8" class="text-center text-muted py-4">Loading users…</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top mt-3" id="adminUsersPagination"></div>
        </div>
    </div>

    <div class="dashboard-card d-none" id="adminUserFormCard">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0" id="adminUserFormTitle"><i class="fas fa-user-edit"></i> User Account</h3>
            <button type="button" class="btn-close" id="btnAdminUserFormClose" aria-label="Close"></button>
        </div>
        <div class="card-body">
            <form id="adminUserForm">
                <input type="hidden" name="user_id" id="adminUserId" value="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="adminUserFirstName">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="first_name" id="adminUserFirstName" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="adminUserLastName">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="last_name" id="adminUserLastName" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="adminUserEmail">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" id="adminUserEmail" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="adminUserUsername">Username</label>
                        <input type="text" class="form-control" name="username" id="adminUserUsername" placeholder="Auto-generated if empty">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="adminUserPhone">Phone Number</label>
                        <input type="tel" class="form-control" name="phone_number" id="adminUserPhone">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="adminUserType">User Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="user_type" id="adminUserType" required>
                            <option value="driver">Driver</option>
                            <option value="traffic_authority">Traffic Officer</option>
                            <option value="administrator">Administrator</option>
                        </select>
                        <div class="form-text admin-type-lock-hint d-none">Administrator role cannot be changed after account creation.</div>
                    </div>
                    <div class="col-md-6 admin-field-driver">
                        <label class="form-label" for="adminUserLicenseId">License ID</label>
                        <input type="text" class="form-control" name="license_id" id="adminUserLicenseId">
                        <div class="form-text admin-id-lock-hint d-none">License ID is set at registration and cannot be changed.</div>
                    </div>
                    <div class="col-md-6 admin-field-officer">
                        <label class="form-label" for="adminUserPoliceId">Police ID</label>
                        <input type="text" class="form-control" name="police_id" id="adminUserPoliceId">
                        <div class="form-text admin-id-lock-hint d-none">Police ID is set at registration and cannot be changed.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="adminUserPassword">Password <span class="text-danger admin-pw-required">*</span></label>
                        <input type="password" class="form-control" name="password" id="adminUserPassword" minlength="8" autocomplete="new-password">
                        <div class="form-text admin-pw-hint">Required for new users; leave blank to keep existing password when editing.</div>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="adminUserIsActive" value="1" checked>
                            <label class="form-check-label" for="adminUserIsActive">Account is active</label>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-dark" id="btnAdminSaveUser"><i class="fas fa-save me-1"></i>Save User</button>
                    <button type="button" class="btn btn-outline-secondary" id="btnAdminCancelUser">Cancel</button>
                </div>
            </form>
            <div id="adminUserFormMsg" class="mt-2"></div>
        </div>
    </div>
</div>

<!-- Admin: Traffic Monitoring -->
<div id="traffic-monitoring-section" class="content-section admin-section" style="display:none;">
    <div class="mb-4">
        <h2 class="mb-1"><i class="fas fa-traffic-light me-2"></i>Traffic Monitoring</h2>
        <p class="text-muted mb-0">View road status updates, accidents, congestion alerts, and blockages submitted by traffic officers.</p>
    </div>

    <div class="admin-monitor-tabs mb-4" role="tablist" aria-label="Traffic monitoring views">
        <button type="button" class="admin-tab active" data-monitor-tab="road-status" role="tab" aria-selected="true">
            <i class="fas fa-road"></i><span>Road Status</span>
        </button>
        <button type="button" class="admin-tab" data-monitor-tab="accidents" role="tab" aria-selected="false">
            <i class="fas fa-car-burst"></i><span>Accidents</span>
        </button>
        <button type="button" class="admin-tab" data-monitor-tab="congestion" role="tab" aria-selected="false">
            <i class="fas fa-traffic-light"></i><span>Congestion</span>
        </button>
        <button type="button" class="admin-tab" data-monitor-tab="blockages" role="tab" aria-selected="false">
            <i class="fas fa-ban"></i><span>Blockages</span>
        </button>
    </div>

    <div class="row g-3 mb-4" id="adminMonitorStats">
        <div class="col-6 col-lg-3">
            <div class="auth-report-kpi">
                <div class="auth-report-kpi-icon kpi-readings"><i class="fas fa-road"></i></div>
                <div class="auth-report-kpi-value" id="adminMonitorStatTotal">—</div>
                <div class="auth-report-kpi-label">Total Records</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="auth-report-kpi">
                <div class="auth-report-kpi-icon kpi-accidents"><i class="fas fa-clock"></i></div>
                <div class="auth-report-kpi-value" id="adminMonitorStatRecent">—</div>
                <div class="auth-report-kpi-label">Last 24 Hours</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="auth-report-kpi">
                <div class="auth-report-kpi-icon kpi-alerts"><i class="fas fa-map-marker-alt"></i></div>
                <div class="auth-report-kpi-value" id="adminMonitorStatSector">Remera</div>
                <div class="auth-report-kpi-label">Study Zone</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="auth-report-kpi">
                <div class="auth-report-kpi-icon kpi-blocked"><i class="fas fa-sync"></i></div>
                <div class="auth-report-kpi-value" id="adminMonitorStatUpdated">—</div>
                <div class="auth-report-kpi-label">Last Updated</div>
            </div>
        </div>
    </div>

    <div class="dashboard-card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title mb-0" id="adminMonitorTableTitle"><i class="fas fa-table"></i> Monitoring Data</h3>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAdminRefreshMonitor">
                <i class="fas fa-sync me-1"></i>Refresh
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light" id="adminMonitorTableHead">
                    <tr>
                        <th>Date / Time</th>
                        <th>Location</th>
                        <th>Details</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="adminMonitorTable">
                    <tr><td colspan="4" class="text-center text-muted py-4">Loading monitoring data…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Admin: System Management -->
<div id="system-section" class="content-section admin-section" style="display:none;">
    <div class="mb-4">
        <h2 class="mb-1"><i class="fas fa-cog me-2"></i>System Management</h2>
        <p class="text-muted mb-0">Configure application settings, notifications, map defaults, and backup options.</p>
    </div>

    <div class="admin-settings-tabs mb-4" role="tablist" aria-label="System settings categories">
        <button type="button" class="admin-tab active" data-settings-tab="general" role="tab" aria-selected="true">
            <i class="fas fa-sliders"></i><span>General</span>
        </button>
        <button type="button" class="admin-tab" data-settings-tab="notifications" role="tab" aria-selected="false">
            <i class="fas fa-bell"></i><span>Notifications</span>
        </button>
        <button type="button" class="admin-tab" data-settings-tab="map" role="tab" aria-selected="false">
            <i class="fas fa-map"></i><span>Map</span>
        </button>
        <button type="button" class="admin-tab" data-settings-tab="backup" role="tab" aria-selected="false">
            <i class="fas fa-database"></i><span>Backup</span>
        </button>
    </div>

    <div class="dashboard-card">
        <div class="card-body">
            <form id="adminSystemSettingsForm">
                <div class="admin-settings-panel active" data-settings-panel="general">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="settingAppName">Application Name</label>
                            <input type="text" class="form-control" name="app_name" id="settingAppName">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="settingTimezone">Timezone</label>
                            <input type="text" class="form-control" name="timezone" id="settingTimezone" placeholder="Africa/Kigali">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="settingStudyZone">Study Zone</label>
                            <input type="text" class="form-control" name="study_zone" id="settingStudyZone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="settingSessionTimeout">Session Timeout (minutes)</label>
                            <input type="number" class="form-control" name="session_timeout_minutes" id="settingSessionTimeout" min="5" max="480">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="maintenance_mode" id="settingMaintenanceMode" value="1">
                                <label class="form-check-label" for="settingMaintenanceMode">Maintenance mode (restrict non-admin access)</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-settings-panel" data-settings-panel="notifications" hidden>
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="notifications_enabled" id="settingNotificationsEnabled" value="1">
                                <label class="form-check-label" for="settingNotificationsEnabled">Enable in-app notifications</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="sms_alerts_enabled" id="settingSmsAlertsEnabled" value="1">
                                <label class="form-check-label" for="settingSmsAlertsEnabled">Enable SMS alerts to drivers</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="settingAutoRefresh">Auto-refresh Interval (seconds)</label>
                            <input type="number" class="form-control" name="auto_refresh_seconds" id="settingAutoRefresh" min="10" max="300">
                        </div>
                    </div>
                </div>

                <div class="admin-settings-panel" data-settings-panel="map" hidden>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="settingMapLat">Map Center Latitude</label>
                            <input type="number" class="form-control" name="map_center_lat" id="settingMapLat" step="0.0001">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="settingMapLng">Map Center Longitude</label>
                            <input type="number" class="form-control" name="map_center_lng" id="settingMapLng" step="0.0001">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="settingMapZoom">Default Zoom Level</label>
                            <input type="number" class="form-control" name="map_default_zoom" id="settingMapZoom" min="10" max="18">
                        </div>
                    </div>
                </div>

                <div class="admin-settings-panel" data-settings-panel="backup" hidden>
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="backup_auto_enabled" id="settingBackupAuto" value="1">
                                <label class="form-check-label" for="settingBackupAuto">Enable automatic backups</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="settingBackupRetention">Backup Retention (days)</label>
                            <input type="number" class="form-control" name="backup_retention_days" id="settingBackupRetention" min="1" max="365">
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-dark" id="btnAdminSaveSettings">
                        <i class="fas fa-save me-1"></i>Save Settings
                    </button>
                </div>
            </form>
            <div id="adminSettingsMsg" class="mt-2"></div>
        </div>
    </div>
</div>

<!-- Admin: Notifications & Announcements -->
<div id="notifications-section" class="content-section admin-section" style="display:none;">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="dashboard-card h-100">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-bullhorn"></i> Publish Announcement</h3>
                </div>
                <div class="card-body">
                    <form id="adminAnnouncementForm">
                        <div class="mb-3">
                            <label class="form-label" for="adminAnnouncementTitle">Title</label>
                            <input type="text" class="form-control" name="title" id="adminAnnouncementTitle" placeholder="Optional announcement title">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="adminAnnouncementMessage">Message <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="message" id="adminAnnouncementMessage" rows="4" required placeholder="Write your announcement message…"></textarea>
                            <div class="form-text">All active users with a registered phone number will receive this message by SMS.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="adminAnnouncementSeverity">Severity</label>
                            <select class="form-select" name="severity" id="adminAnnouncementSeverity">
                                <option value="info">Info</option>
                                <option value="warning">Warning</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="emergency" id="adminAnnouncementEmergency" value="1">
                                <label class="form-check-label" for="adminAnnouncementEmergency">
                                    <i class="fas fa-triangle-exclamation text-danger me-1"></i>Emergency alert (broadcast to all users)
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-dark w-100">
                            <i class="fas fa-paper-plane me-1"></i>Send Announcement
                        </button>
                    </form>
                    <div id="adminAnnouncementMsg" class="mt-2"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="dashboard-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h3 class="card-title mb-0"><i class="fas fa-list"></i> Recent Announcements</h3>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAdminRefreshAnnouncements">
                        <i class="fas fa-sync me-1"></i>Refresh
                    </button>
                </div>
                <div id="adminAnnouncementsList" class="driver-feed p-3">Loading…</div>
            </div>
        </div>
    </div>
</div>

<!-- Admin: Activity Logs -->
<div id="activity-logs-section" class="content-section admin-section" style="display:none;">
    <div class="mb-4">
        <h2 class="mb-1"><i class="fas fa-history me-2"></i>Activity Logs</h2>
        <p class="text-muted mb-0">Audit trail of logins, security events, and data changes across STRGS.</p>
    </div>

    <div class="admin-logs-tabs mb-2" role="tablist" aria-label="Activity log filters">
        <button type="button" class="admin-tab active" data-logs-tab="all" role="tab" aria-selected="true">
            <i class="fas fa-list"></i><span>All</span>
        </button>
        <button type="button" class="admin-tab" data-logs-tab="login" role="tab" aria-selected="false">
            <i class="fas fa-right-to-bracket"></i><span>Login</span>
        </button>
        <button type="button" class="admin-tab" data-logs-tab="security" role="tab" aria-selected="false">
            <i class="fas fa-shield-halved"></i><span>Security</span>
        </button>
        <button type="button" class="admin-tab" data-logs-tab="changes" role="tab" aria-selected="false" title="User accounts, settings, road status updates, and resolved records">
            <i class="fas fa-pen-to-square"></i><span>Changes</span>
        </button>
    </div>
    <p class="text-muted small mb-4" id="adminLogsTabHint">Showing all system activity.</p>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="dashboard-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h3 class="card-title mb-0"><i class="fas fa-clipboard-list"></i> Audit Log</h3>
                        <small class="text-muted" id="adminLogsUpdatedAt">Updates automatically while you view this page</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAdminRefreshLogs">
                        <i class="fas fa-sync me-1"></i>Refresh now
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date / Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody id="adminLogsTable">
                            <tr><td colspan="4" class="text-center text-muted py-4">Loading activity logs…</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white border-top" id="adminLogsPagination"></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="dashboard-card" id="adminLoginHistory">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h3 class="card-title mb-0"><i class="fas fa-right-to-bracket"></i> Login Activity</h3>
                    <small class="text-muted">Live sign-in / sign-out</small>
                </div>
                <div class="driver-feed p-3">Loading…</div>
            </div>
        </div>
    </div>
</div>

<!-- Admin: Profile -->
<div id="profile-section" class="content-section admin-section" style="display:none;">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="dashboard-card text-center">
                <div class="card-body">
                    <div class="driver-avatar-lg mx-auto mb-3" id="adminProfileAvatar">
                        <?php if ($admin_avatar): ?>
                            <img src="<?php echo htmlspecialchars($admin_avatar); ?>" alt="Profile">
                        <?php else: ?>
                            <span><?php echo strtoupper(substr($user_name, 0, 1)); ?></span>
                        <?php endif; ?>
                    </div>
                    <form id="adminAvatarForm" enctype="multipart/form-data">
                        <input type="file" name="profile_picture" id="adminProfilePictureInput" accept="image/*" class="d-none">
                        <button type="button" class="btn btn-outline-dark btn-sm" id="btnAdminChooseAvatar">
                            <i class="fas fa-camera me-1"></i>Upload Photo
                        </button>
                    </form>
                    <div id="adminAvatarMsg" class="mt-2 small"></div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="dashboard-card mb-4">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-user-edit"></i> Personal Information</h3></div>
                <div class="card-body">
                    <form id="adminProfileForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="adminFirstName">First Name</label>
                                <input type="text" class="form-control" name="first_name" id="adminFirstName" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="adminLastName">Last Name</label>
                                <input type="text" class="form-control" name="last_name" id="adminLastName" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="adminEmail">Email</label>
                                <input type="email" class="form-control" name="email" id="adminEmail" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="adminPhone">Phone</label>
                                <input type="tel" class="form-control" name="phone_number" id="adminPhone">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="adminUsername">Username</label>
                                <input type="text" class="form-control" id="adminUsername" readonly disabled>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-dark">Save Profile</button>
                            </div>
                        </div>
                    </form>
                    <div id="adminProfileMsg" class="mt-2"></div>
                </div>
            </div>
            <div class="dashboard-card">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-lock"></i> Change Password</h3></div>
                <div class="card-body">
                    <form id="adminPasswordForm">
                        <div class="mb-3">
                            <label class="form-label" for="adminProfileCurrentPassword">Current Password</label>
                            <div class="admin-password-field-wrap">
                                <input type="password" class="form-control" name="current_password" id="adminProfileCurrentPassword"
                                       required autocomplete="current-password">
                                <button type="button" class="admin-password-toggle" data-target="adminProfileCurrentPassword"
                                        aria-label="Show current password" title="Show password">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="adminProfileNewPassword">New Password</label>
                            <div class="admin-password-field-wrap">
                                <input type="password" class="form-control" name="new_password" id="adminProfileNewPassword"
                                       minlength="8" required autocomplete="new-password">
                                <button type="button" class="admin-password-toggle" data-target="adminProfileNewPassword"
                                        aria-label="Show new password" title="Show password">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-outline-dark">Update Password</button>
                    </form>
                    <div id="adminPasswordMsg" class="mt-2"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Admin: reset user password -->
<div class="modal fade" id="adminResetPasswordModal" tabindex="-1" aria-labelledby="adminResetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content admin-reset-password-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="adminResetPasswordModalLabel">
                    <i class="fas fa-key me-2" aria-hidden="true"></i>Reset User Password
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">
                    Set a new password for <strong id="adminResetPasswordUserName">this user</strong>.
                    They will use this password the next time they sign in.
                </p>
                <div class="mb-3">
                    <label class="form-label" for="adminResetPasswordNew">New Password</label>
                    <div class="admin-password-field-wrap">
                        <input type="password" class="form-control" id="adminResetPasswordNew"
                               minlength="8" autocomplete="new-password" placeholder="At least 8 characters">
                        <button type="button" class="admin-password-toggle" data-target="adminResetPasswordNew"
                                aria-label="Show password" title="Show password">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="adminResetPasswordConfirm">Confirm New Password</label>
                    <div class="admin-password-field-wrap">
                        <input type="password" class="form-control" id="adminResetPasswordConfirm"
                               minlength="8" autocomplete="new-password" placeholder="Re-enter the new password">
                        <button type="button" class="admin-password-toggle" data-target="adminResetPasswordConfirm"
                                aria-label="Show confirm password" title="Show password">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div id="adminResetPasswordMsg"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-dark" id="btnAdminResetPasswordConfirm" disabled>
                    <i class="fas fa-check me-1" aria-hidden="true"></i>Reset Password
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Admin: delete user confirmation -->
<div class="modal fade" id="adminDeleteUserModal" tabindex="-1" aria-labelledby="adminDeleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content admin-delete-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger" id="adminDeleteUserModalLabel">
                    <i class="fas fa-triangle-exclamation me-2" aria-hidden="true"></i>Delete User Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">
                    You are about to permanently remove <strong id="adminDeleteUserName">this user</strong> from STRGS.
                    Their account, login access, and profile data will be deleted. This action cannot be undone.
                </p>
                <label class="form-label fw-semibold" for="adminDeleteConfirmInput">
                    Type <span class="admin-delete-keyword">Delete</span> to confirm
                </label>
                <input type="text" class="form-control" id="adminDeleteConfirmInput"
                       placeholder="Type Delete exactly" autocomplete="off" spellcheck="false">
                <div class="form-text" id="adminDeleteConfirmHint">
                    Must match exactly: capital <strong>D</strong>, then <strong>elete</strong>. Not <em>delete</em> or <em>DELETE</em>.
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btnAdminDeleteConfirm" disabled>
                    <i class="fas fa-trash me-1" aria-hidden="true"></i>Confirm Delete
                </button>
            </div>
        </div>
    </div>
</div>
