<?php
/** Traffic Police Officer dashboard — included from dashboard.php */
$authority_user_id = (int)($_SESSION['user_id'] ?? 0);
$authority_first = $_SESSION['first_name'] ?? explode(' ', $user_name)[0] ?? 'Officer';
$authority_avatar = $_SESSION['profile_picture'] ?? null;

try {
    $dbTmp = (new Database())->getConnection();
    if ($dbTmp) {
        $u = new User($dbTmp);
        if ($u->findById($authority_user_id)) {
            $authority_avatar = $u->profile_picture;
            $_SESSION['profile_picture'] = $authority_avatar;
        }
    }
} catch (Exception $e) {
    // non-fatal
}
?>

<!-- Authority: Home -->
<div id="dashboard-section" class="content-section authority-section">
    <div class="driver-welcome card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h2 class="mb-1">Welcome, <?php echo htmlspecialchars($authority_first); ?></h2>
                <p class="text-muted mb-0">Traffic Police Dashboard — Remera Sector, Kigali City</p>
            </div>
            <div class="text-end">
                <div class="small text-muted">Officer role</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($user_type_label); ?></div>
                <small class="text-muted">Provide timely updates for driver route guidance</small>
            </div>
        </div>
    </div>

    <div class="stats-grid driver-stats mb-4">
        <div class="stat-card stat-clickable" data-section-jump="road-status">
            <div class="stat-icon stat-icon-success"><i class="fas fa-road"></i></div>
            <div class="stat-number" id="authStatRoadUpdates">—</div>
            <div class="stat-label">Road Status Updates</div>
            <small class="text-muted">Last 7 days (sector)</small>
        </div>
        <div class="stat-card stat-clickable" data-section-jump="incidents">
            <div class="stat-icon stat-icon-danger"><i class="fas fa-car-burst"></i></div>
            <div class="stat-number" id="authStatAccidents">—</div>
            <div class="stat-label">Accident Reports</div>
            <small class="text-muted">Recent incidents</small>
        </div>
        <div class="stat-card stat-clickable" data-section-jump="incidents">
            <div class="stat-icon stat-icon-warning"><i class="fas fa-traffic-light"></i></div>
            <div class="stat-number" id="authStatCongestion">—</div>
            <div class="stat-label">Congestion Reports</div>
            <small class="text-muted">Traffic alerts</small>
        </div>
        <div class="stat-card stat-clickable" data-section-jump="incidents">
            <div class="stat-icon stat-icon-issues"><i class="fas fa-ban"></i></div>
            <div class="stat-number" id="authStatBlockages">—</div>
            <div class="stat-label">Road Blockages</div>
            <small class="text-muted">Closed roads</small>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="dashboard-card driver-home-map-card auth-map-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h3 class="card-title mb-0"><i class="fas fa-map-marked-alt"></i> Traffic Monitoring Map</h3>
                        <small class="text-muted">Accidents, congestion, and blocked roads in Remera Sector</small>
                    </div>
                    <span class="badge bg-dark" id="authHomeMapBadge">Remera Sector, Kigali</span>
                </div>
                <div class="driver-map-legend driver-home-map-legend px-3 py-2 small border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <span class="me-3"><span class="legend-dot legend-normal"></span> Normal</span>
                        <span class="me-3"><span class="legend-dot legend-congested"></span> Congested</span>
                        <span class="me-3"><span class="legend-dot legend-heavy"></span> Heavy</span>
                        <span class="me-3"><span class="legend-dot legend-severe"></span> Blocked / Severe</span>
                        <span><i class="fas fa-car-burst text-danger me-1"></i>Accidents</span>
                    </div>
                    <small class="text-muted" id="authHomeMapLive"><i class="fas fa-circle text-success me-1 live-pulse"></i>Live traffic overlay</small>
                </div>
                <div class="driver-map-shell auth-map-shell">
                    <div id="authHomeMap" class="driver-map auth-home-map"></div>
                    <div class="driver-map-toolbar" role="toolbar" aria-label="Home map tools">
                        <button type="button" class="driver-map-tool-btn" id="btnFitRemeraAuthHome" title="Fit Remera Sector">
                            <i class="fas fa-expand"></i><span>Sector</span>
                        </button>
                        <button type="button" class="driver-map-tool-btn" id="btnRefreshAuthHomeMap" title="Refresh map data">
                            <i class="fas fa-sync"></i><span>Refresh</span>
                        </button>
                    </div>
                    <div class="driver-map-layer-toggle" role="group" aria-label="Home map base layer">
                        <button type="button" class="driver-layer-btn active" data-map-target="home" data-map-layer="street">
                            <i class="fas fa-road me-1"></i>Street
                        </button>
                        <button type="button" class="driver-layer-btn" data-map-target="home" data-map-layer="satellite">
                            <i class="fas fa-satellite me-1"></i>Satellite
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="dashboard-card mb-4">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-list"></i> Recent Road Status Updates</h3></div>
                <div id="authRecentUpdates" class="driver-feed p-3">Loading…</div>
            </div>
            <div class="dashboard-card">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-clock-rotate-left"></i> Recent Activities</h3></div>
                <div id="authRecentActivity" class="driver-feed p-3">Loading…</div>
            </div>
        </div>
    </div>

    <div class="quick-actions mt-4">
        <a href="#road-status" class="action-btn authority-nav-jump" data-section="road-status"><div class="action-icon"><i class="fas fa-road"></i></div><span>Manage Road Status</span></a>
        <a href="#incidents" class="action-btn authority-nav-jump" data-section="incidents"><div class="action-icon"><i class="fas fa-triangle-exclamation"></i></div><span>Report Incident</span></a>
        <a href="#map-monitoring" class="action-btn authority-nav-jump" data-section="map-monitoring"><div class="action-icon"><i class="fas fa-map"></i></div><span>Monitor Map</span></a>
        <a href="#notifications" class="action-btn authority-nav-jump" data-section="notifications"><div class="action-icon"><i class="fas fa-bell"></i></div><span>Notifications</span></a>
    </div>
</div>

<!-- Authority: Road Status -->
<div id="road-status-section" class="content-section authority-section" style="display:none;">
<?php $road_status_mode = 'authority'; include __DIR__ . '/road_status_panel.php'; ?>
</div>

<!-- Authority: Incidents (Accidents, Congestion, Blockages) -->
<div id="incidents-section" class="content-section authority-section" style="display:none;">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="dashboard-card mb-4">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-car-burst"></i> Report Accident</h3></div>
                <div class="card-body">
                    <form id="authAccidentForm" class="authority-form">
                        <div class="mb-3">
                            <label class="form-label" for="accidentSegment">Location (Road)</label>
                            <select class="form-select" id="accidentSegment" name="segment_id" required></select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="accidentTime">Accident Time</label>
                            <input type="datetime-local" class="form-control" id="accidentTime" name="accident_time" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="accidentSeverity">Severity</label>
                            <select class="form-select" id="accidentSeverity" name="severity">
                                <option value="minor">Minor</option>
                                <option value="moderate">Moderate</option>
                                <option value="severe">Severe</option>
                                <option value="fatal">Fatal</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="accidentCause">Description / Cause</label>
                            <textarea class="form-control" id="accidentCause" name="cause" rows="2" required></textarea>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-4"><label class="form-label">Vehicles</label><input type="number" class="form-control" name="vehicles_involved" min="0" value="1"></div>
                            <div class="col-4"><label class="form-label">Injuries</label><input type="number" class="form-control" name="injuries" min="0" value="0"></div>
                            <div class="col-4"><label class="form-label">Fatalities</label><input type="number" class="form-control" name="fatalities" min="0" value="0"></div>
                        </div>
                        <button type="submit" class="btn btn-danger w-100"><i class="fas fa-paper-plane me-2"></i>Submit Accident Report</button>
                    </form>
                    <div id="authAccidentMsg" class="mt-2"></div>
                </div>
            </div>
            <div class="dashboard-card mb-4">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-traffic-light"></i> Report Congestion</h3></div>
                <div class="card-body">
                    <form id="authCongestionForm" class="authority-form">
                        <input type="hidden" name="type" value="congestion">
                        <div class="mb-3"><label class="form-label">Road</label><select class="form-select auth-road-select" name="segment_id" required></select></div>
                        <div class="mb-3"><label class="form-label">Congestion Level</label>
                            <select class="form-select" name="severity">
                                <option value="info">Moderate</option>
                                <option value="warning" selected>Heavy</option>
                                <option value="critical">Severe</option>
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label">Details</label><textarea class="form-control" name="message" rows="2" required placeholder="e.g. Heavy traffic due to rush hour"></textarea></div>
                        <button type="submit" class="btn btn-warning w-100 text-dark"><i class="fas fa-paper-plane me-2"></i>Report Congestion</button>
                    </form>
                    <div id="authCongestionMsg" class="mt-2"></div>
                </div>
            </div>
            <div class="dashboard-card">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-ban"></i> Report Road Blockage</h3></div>
                <div class="card-body">
                    <form id="authBlockageForm" class="authority-form">
                        <input type="hidden" name="type" value="road_blockage">
                        <div class="mb-3"><label class="form-label">Road</label><select class="form-select auth-road-select" name="segment_id" required></select></div>
                        <div class="mb-3"><label class="form-label">Blockage Details</label><textarea class="form-control" name="message" rows="2" required placeholder="e.g. Road closed for maintenance"></textarea></div>
                        <input type="hidden" name="severity" value="critical">
                        <button type="submit" class="btn btn-dark w-100"><i class="fas fa-paper-plane me-2"></i>Report Blockage</button>
                    </form>
                    <div id="authBlockageMsg" class="mt-2"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="dashboard-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0"><i class="fas fa-list"></i> Accident Reports</h3>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRefreshAccidents"><i class="fas fa-sync"></i></button>
                </div>
                <div id="authAccidentList" class="p-3">Loading…</div>
            </div>
            <div class="dashboard-card">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-screwdriver-wrench"></i> Quick Road Status Mark</h3></div>
                <div class="card-body">
                    <form id="authQuickStatusForm" class="row g-3 align-items-end">
                        <div class="col-md-5"><label class="form-label">Road</label><select class="form-select auth-road-select" name="segment_id" required></select></div>
                        <div class="col-md-4"><label class="form-label">Status</label>
                            <select class="form-select" name="road_status" required>
                                <option value="normal">Normal</option>
                                <option value="congested">Congested</option>
                                <option value="blocked">Blocked</option>
                                <option value="maintenance">Under Maintenance</option>
                            </select>
                        </div>
                        <div class="col-md-3"><button type="submit" class="btn btn-primary w-100">Apply</button></div>
                        <div class="col-12"><input type="text" class="form-control" name="status_note" placeholder="Optional note for drivers"></div>
                    </form>
                    <div id="authQuickStatusMsg" class="mt-2"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Authority: Map Monitoring -->
<div id="map-monitoring-section" class="content-section authority-section" style="display:none;">
    <div class="dashboard-card driver-home-map-card auth-map-card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title mb-0"><i class="fas fa-map"></i> Live Traffic Monitoring Map</h3>
            <span class="badge bg-dark" id="authMonitorMapBadge">Remera Sector</span>
        </div>
        <div class="driver-map-legend driver-home-map-legend px-3 py-2 small border-bottom">
            <span class="me-3"><span class="legend-dot legend-normal"></span> Normal</span>
            <span class="me-3"><span class="legend-dot legend-congested"></span> Congested</span>
            <span class="me-3"><span class="legend-dot legend-heavy"></span> Heavy</span>
            <span class="me-3"><span class="legend-dot legend-severe"></span> Severe</span>
            <span><i class="fas fa-car-burst text-danger me-1"></i>Accidents</span>
        </div>
        <div class="driver-map-shell auth-map-shell auth-monitor-shell">
            <div id="authMonitorMap" class="driver-map auth-monitor-map"></div>
            <div class="driver-map-toolbar" role="toolbar" aria-label="Monitor map tools">
                <button type="button" class="driver-map-tool-btn" id="btnFitRemeraAuthMonitor" title="Fit Remera Sector">
                    <i class="fas fa-expand"></i><span>Sector</span>
                </button>
                <button type="button" class="driver-map-tool-btn" id="btnRefreshAuthMonitorMap" title="Refresh map data">
                    <i class="fas fa-sync"></i><span>Refresh</span>
                </button>
            </div>
            <div class="driver-map-layer-toggle" role="group" aria-label="Monitor map base layer">
                <button type="button" class="driver-layer-btn active" data-map-target="monitor" data-map-layer="street">
                    <i class="fas fa-road me-1"></i>Street
                </button>
                <button type="button" class="driver-layer-btn" data-map-target="monitor" data-map-layer="satellite">
                    <i class="fas fa-satellite me-1"></i>Satellite
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Authority: Notifications -->
<div id="notifications-section" class="content-section authority-section" style="display:none;">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="dashboard-card">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-bullhorn"></i> Send Notification</h3></div>
                <div class="card-body">
                    <form id="authNotifyForm">
                        <div class="mb-3"><label class="form-label">Road</label><select class="form-select auth-road-select" name="segment_id" required></select></div>
                        <div class="mb-3"><label class="form-label">Alert Type</label>
                            <select class="form-select" name="type">
                                <option value="congestion">Traffic Congestion</option>
                                <option value="incident">Accident / Incident</option>
                                <option value="road_blockage">Road Blockage</option>
                                <option value="system">Road Maintenance</option>
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label">Severity</label>
                            <select class="form-select" name="severity">
                                <option value="info">Info</option>
                                <option value="warning" selected>Warning</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label">Message for Drivers</label><textarea class="form-control" name="message" rows="3" required placeholder="Include alternative route advice if possible"></textarea></div>
                        <button type="submit" class="btn btn-dark w-100"><i class="fas fa-paper-plane me-2"></i>Send Alert</button>
                    </form>
                    <div id="authNotifyMsg" class="mt-2"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="dashboard-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h3 class="card-title mb-0"><i class="fas fa-bell"></i> Notifications & Alerts</h3>
                    <div class="d-flex align-items-center gap-2">
                        <small class="text-muted" id="authAlertsRefreshed"></small>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRefreshAuthAlerts"><i class="fas fa-sync me-1"></i>Refresh</button>
                    </div>
                </div>
                <div id="authAlertsList" class="driver-feed p-3">Loading…</div>
            </div>
        </div>
    </div>
</div>

<!-- Authority: Reports -->
<div id="reports-section" class="content-section authority-section" style="display:none;">
    <div class="dashboard-card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title mb-0"><i class="fas fa-chart-pie"></i> Traffic Reports</h3>
            <div class="btn-group btn-group-sm" role="group" id="authReportPeriod">
                <button type="button" class="btn btn-outline-dark active" data-period="daily">Daily</button>
                <button type="button" class="btn btn-outline-dark" data-period="weekly">Weekly</button>
                <button type="button" class="btn btn-outline-dark" data-period="monthly">Monthly</button>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6"><canvas id="authReportReadingsChart" height="220"></canvas></div>
                <div class="col-md-6"><canvas id="authReportAlertsChart" height="220"></canvas></div>
            </div>
            <div class="mt-3 d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnExportAuthReport"><i class="fas fa-download me-1"></i>Export CSV</button>
            </div>
            <div id="authReportSummary" class="mt-3 small text-muted"></div>
        </div>
    </div>
</div>

<!-- Authority: Activity History -->
<div id="activity-history-section" class="content-section authority-section" style="display:none;">
    <div class="dashboard-card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title mb-0"><i class="fas fa-history"></i> Activity History</h3>
            <small class="text-muted" id="authActivitySummary"></small>
        </div>
        <div id="authActivityList" class="p-3">Loading…</div>
        <div class="card-footer bg-white border-top" id="authActivityPagination"></div>
    </div>
</div>

<!-- Authority: Profile -->
<div id="profile-section" class="content-section authority-section" style="display:none;">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="dashboard-card text-center">
                <div class="card-body">
                    <div class="driver-avatar-lg mx-auto mb-3" id="authProfileAvatar">
                        <?php if ($authority_avatar): ?>
                            <img src="<?php echo htmlspecialchars($authority_avatar); ?>" alt="Profile">
                        <?php else: ?>
                            <span><?php echo strtoupper(substr($user_name, 0, 1)); ?></span>
                        <?php endif; ?>
                    </div>
                    <form id="authAvatarForm" enctype="multipart/form-data">
                        <input type="file" name="profile_picture" id="authProfilePictureInput" accept="image/*" class="d-none">
                        <button type="button" class="btn btn-outline-dark btn-sm" id="btnAuthChooseAvatar"><i class="fas fa-camera me-1"></i>Upload Photo</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="dashboard-card mb-4">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-user-edit"></i> Personal Information</h3></div>
                <div class="card-body">
                    <form id="authProfileForm">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">First Name</label><input type="text" class="form-control" name="first_name" id="authFirstName" required></div>
                            <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" class="form-control" name="last_name" id="authLastName" required></div>
                            <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" id="authEmail" required></div>
                            <div class="col-md-6"><label class="form-label">Phone</label><input type="tel" class="form-control" name="phone_number" id="authPhone" required></div>
                            <div class="col-md-6"><label class="form-label">Police ID</label><input type="text" class="form-control" id="authPoliceId" readonly disabled></div>
                            <div class="col-12"><button type="submit" class="btn btn-dark">Save Profile</button></div>
                        </div>
                    </form>
                    <div id="authProfileMsg" class="mt-2"></div>
                </div>
            </div>
            <div class="dashboard-card">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-lock"></i> Change Password</h3></div>
                <div class="card-body">
                    <form id="authPasswordForm">
                        <div class="mb-3"><label class="form-label">Current Password</label><input type="password" class="form-control" name="current_password" required></div>
                        <div class="mb-3"><label class="form-label">New Password</label><input type="password" class="form-control" name="new_password" minlength="8" required></div>
                        <button type="submit" class="btn btn-outline-dark">Update Password</button>
                    </form>
                    <div id="authPasswordMsg" class="mt-2"></div>
                </div>
            </div>
        </div>
    </div>
</div>
