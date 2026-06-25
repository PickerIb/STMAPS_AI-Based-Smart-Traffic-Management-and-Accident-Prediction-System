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
        <div class="stat-card stat-clickable" data-section-jump="road-status" data-road-filter="blocked">
            <div class="stat-icon stat-icon-issues"><i class="fas fa-ban"></i></div>
            <div class="stat-number" id="authStatBlockages">—</div>
            <div class="stat-label">Road Blockages</div>
            <small class="text-muted">Closed roads — view on Road Status</small>
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
    <div class="incidents-page-header mb-4">
        <div>
            <h2 class="incidents-page-title mb-1"><i class="fas fa-triangle-exclamation me-2"></i>Report Incidents</h2>
            <p class="text-muted mb-0">Submit accident, congestion, and road blockage reports for Remera Sector. Drivers receive alerts from these reports.</p>
        </div>
    </div>

    <div class="row g-4 incidents-layout">
        <div class="col-lg-5">
            <div class="dashboard-card incidents-report-card h-100">
                <div class="card-header incidents-report-card-header">
                    <div>
                        <h3 class="card-title mb-0"><i class="fas fa-clipboard-list"></i> New Field Report</h3>
                        <small class="text-muted">Choose a report type and complete the form below</small>
                    </div>
                </div>

                <div class="incidents-form-tabs" role="tablist" aria-label="Incident report type">
                    <button type="button" class="incidents-tab active" data-incident-tab="accident" role="tab" aria-selected="true" aria-controls="incidentPanelAccident">
                        <i class="fas fa-car-burst"></i>
                        <span>Accident</span>
                    </button>
                    <button type="button" class="incidents-tab" data-incident-tab="congestion" role="tab" aria-selected="false" aria-controls="incidentPanelCongestion">
                        <i class="fas fa-traffic-light"></i>
                        <span>Congestion</span>
                    </button>
                    <button type="button" class="incidents-tab" data-incident-tab="blockage" role="tab" aria-selected="false" aria-controls="incidentPanelBlockage">
                        <i class="fas fa-ban"></i>
                        <span>Road Blockage</span>
                    </button>
                </div>

                <div class="card-body incidents-form-body">
                    <div id="incidentPanelAccident" class="incident-form-panel active" role="tabpanel">
                        <div class="incident-panel-intro incident-panel-intro-danger">
                            <i class="fas fa-car-burst"></i>
                            <div>
                                <strong>Accident Report</strong>
                                <p class="mb-0">Record collision or crash details at a specific road location.</p>
                            </div>
                        </div>
                        <form id="authAccidentForm" class="authority-form incident-form">
                            <div class="incident-form-grid">
                                <div class="incident-field incident-field-full">
                                    <label class="form-label" for="accidentSegment"><i class="fas fa-location-dot me-1 text-muted"></i>Location (Road) <span class="text-danger">*</span></label>
                                    <select class="form-select" id="accidentSegment" name="segment_id" required>
                                        <option value="" disabled selected>Select road in Remera Sector…</option>
                                    </select>
                                </div>
                                <div class="incident-field">
                                    <label class="form-label" for="accidentTime"><i class="fas fa-clock me-1 text-muted"></i>Accident Time <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" id="accidentTime" name="accident_time" required>
                                </div>
                                <div class="incident-field">
                                    <label class="form-label" for="accidentSeverity"><i class="fas fa-gauge-high me-1 text-muted"></i>Severity <span class="text-danger">*</span></label>
                                    <select class="form-select" id="accidentSeverity" name="severity">
                                        <option value="minor">Minor</option>
                                        <option value="moderate">Moderate</option>
                                        <option value="severe">Severe</option>
                                        <option value="fatal">Fatal</option>
                                    </select>
                                </div>
                                <div class="incident-field incident-field-full">
                                    <label class="form-label" for="accidentCause"><i class="fas fa-align-left me-1 text-muted"></i>Description / Cause <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="accidentCause" name="cause" rows="3" required placeholder="Describe what happened, vehicles involved, and current road impact…"></textarea>
                                </div>
                                <div class="incident-field">
                                    <label class="form-label" for="accidentVehicles">Vehicles</label>
                                    <input type="number" class="form-control" id="accidentVehicles" name="vehicles_involved" min="0" value="1">
                                </div>
                                <div class="incident-field">
                                    <label class="form-label" for="accidentInjuries">Injuries</label>
                                    <input type="number" class="form-control" id="accidentInjuries" name="injuries" min="0" value="0">
                                    <div class="form-text">People hurt, not killed</div>
                                </div>
                                <div class="incident-field">
                                    <label class="form-label" for="accidentFatalities">Fatalities</label>
                                    <input type="number" class="form-control" id="accidentFatalities" name="fatalities" min="0" value="0">
                                    <div class="form-text">People who died</div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-danger incident-submit-btn w-100">
                                <i class="fas fa-paper-plane me-2"></i>Submit Accident Report
                            </button>
                        </form>
                        <div id="authAccidentMsg" class="incident-form-msg"></div>
                    </div>

                    <div id="incidentPanelCongestion" class="incident-form-panel" role="tabpanel" hidden>
                        <div class="incident-panel-intro incident-panel-intro-warning">
                            <i class="fas fa-traffic-light"></i>
                            <div>
                                <strong>Report Congestion</strong>
                                <p class="mb-0">Alert drivers about slow or heavy traffic on a road segment.</p>
                            </div>
                        </div>
                        <form id="authCongestionForm" class="authority-form incident-form">
                            <input type="hidden" name="type" value="congestion">
                            <div class="incident-form-grid">
                                <div class="incident-field incident-field-full">
                                    <label class="form-label"><i class="fas fa-road me-1 text-muted"></i>Road <span class="text-danger">*</span></label>
                                    <select class="form-select auth-road-select" name="segment_id" required>
                                        <option value="" disabled selected>Select road in Remera Sector…</option>
                                    </select>
                                </div>
                                <div class="incident-field incident-field-full">
                                    <label class="form-label"><i class="fas fa-layer-group me-1 text-muted"></i>Congestion Level <span class="text-danger">*</span></label>
                                    <select class="form-select" name="severity">
                                        <option value="info">Moderate — slower than normal</option>
                                        <option value="warning" selected>Heavy — significant delays</option>
                                        <option value="critical">Severe — standstill or near standstill</option>
                                    </select>
                                </div>
                                <div class="incident-field incident-field-full">
                                    <label class="form-label"><i class="fas fa-comment-dots me-1 text-muted"></i>Details <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="message" rows="3" required placeholder="e.g. Heavy queue near roundabout; advise alternate route via KK 15"></textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-warning incident-submit-btn w-100 text-dark">
                                <i class="fas fa-paper-plane me-2"></i>Report Congestion
                            </button>
                        </form>
                        <div id="authCongestionMsg" class="incident-form-msg"></div>
                    </div>

                    <div id="incidentPanelBlockage" class="incident-form-panel" role="tabpanel" hidden>
                        <div class="incident-panel-intro incident-panel-intro-dark">
                            <i class="fas fa-ban"></i>
                            <div>
                                <strong>Report Road Blockage</strong>
                                <p class="mb-0">Notify drivers when a road is closed or not passable.</p>
                            </div>
                        </div>
                        <form id="authBlockageForm" class="authority-form incident-form">
                            <input type="hidden" name="type" value="road_blockage">
                            <input type="hidden" name="severity" value="critical">
                            <div class="incident-form-grid">
                                <div class="incident-field incident-field-full">
                                    <label class="form-label"><i class="fas fa-road me-1 text-muted"></i>Road <span class="text-danger">*</span></label>
                                    <select class="form-select auth-road-select" name="segment_id" required>
                                        <option value="" disabled selected>Select road in Remera Sector…</option>
                                    </select>
                                </div>
                                <div class="incident-field incident-field-full">
                                    <label class="form-label"><i class="fas fa-comment-dots me-1 text-muted"></i>Blockage Details <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="message" rows="3" required placeholder="e.g. Road closed for maintenance until 18:00; use KG 11 alternate"></textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-dark incident-submit-btn w-100">
                                <i class="fas fa-paper-plane me-2"></i>Report Road Blockage
                            </button>
                        </form>
                        <div id="authBlockageMsg" class="incident-form-msg"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="dashboard-card incidents-list-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h3 class="card-title mb-0"><i class="fas fa-list-check"></i> Accident Reports</h3>
                        <small class="text-muted">Submitted accident records for Remera Sector</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRefreshAccidents" title="Refresh list">
                        <i class="fas fa-sync me-1"></i>Refresh
                    </button>
                </div>
                <div id="authAccidentList" class="incidents-list-body p-3">Loading…</div>
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

<!-- Authority: Reports -->
<div id="reports-section" class="content-section authority-section" style="display:none;">
    <div class="auth-reports-page">
        <div class="auth-reports-hero mb-4">
            <div class="auth-reports-hero-text">
                <h2 class="auth-reports-title mb-1"><i class="fas fa-chart-line me-2"></i>Traffic Reports</h2>
                <p class="text-muted mb-0" id="authReportPeriodLabel">Sector activity and incident overview for Remera</p>
            </div>
            <div class="auth-reports-hero-actions d-flex flex-wrap align-items-center gap-2">
                <div class="btn-group btn-group-sm auth-report-period-toggle" role="group" id="authReportPeriod" aria-label="Report period">
                    <button type="button" class="btn btn-outline-dark active" data-period="daily">Today</button>
                    <button type="button" class="btn btn-outline-dark" data-period="weekly">7 Days</button>
                    <button type="button" class="btn btn-outline-dark" data-period="monthly">30 Days</button>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExportAuthReport">
                    <i class="fas fa-download me-1"></i>Export CSV
                </button>
            </div>
        </div>

        <div class="row g-3 mb-4" id="authReportKpis">
            <div class="col-6 col-lg-3">
                <div class="auth-report-kpi">
                    <div class="auth-report-kpi-icon kpi-readings"><i class="fas fa-road"></i></div>
                    <div class="auth-report-kpi-value" id="authKpiReadings">—</div>
                    <div class="auth-report-kpi-label">Road Updates</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="auth-report-kpi">
                    <div class="auth-report-kpi-icon kpi-accidents"><i class="fas fa-car-burst"></i></div>
                    <div class="auth-report-kpi-value" id="authKpiAccidents">—</div>
                    <div class="auth-report-kpi-label">Accidents</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="auth-report-kpi">
                    <div class="auth-report-kpi-icon kpi-alerts"><i class="fas fa-bell"></i></div>
                    <div class="auth-report-kpi-value" id="authKpiAlerts">—</div>
                    <div class="auth-report-kpi-label">Traffic Alerts</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="auth-report-kpi">
                    <div class="auth-report-kpi-icon kpi-blocked"><i class="fas fa-ban"></i></div>
                    <div class="auth-report-kpi-value" id="authKpiBlockages">—</div>
                    <div class="auth-report-kpi-label">Road Blockages</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="dashboard-card auth-report-chart-card h-100">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="fas fa-chart-area"></i> Daily Activity</h3>
                        <small class="text-muted">Road status updates vs traffic alerts</small>
                    </div>
                    <div class="card-body">
                        <div class="auth-chart-wrap auth-chart-wrap-lg">
                            <canvas id="authReportActivityChart" aria-label="Daily activity chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="dashboard-card auth-report-chart-card h-100">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="fas fa-chart-pie"></i> Alerts by Type</h3>
                        <small class="text-muted">Share of incident categories</small>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="auth-chart-wrap auth-chart-wrap-donut flex-grow-1">
                            <canvas id="authReportAlertsChart" aria-label="Alerts breakdown chart"></canvas>
                        </div>
                        <div id="authReportAlertLegend" class="auth-report-type-legend mt-2"></div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="dashboard-card auth-report-chart-card">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="fas fa-chart-column"></i> Road Status Updates</h3>
                        <small class="text-muted">Police readings recorded per day</small>
                    </div>
                    <div class="card-body">
                        <div class="auth-chart-wrap">
                            <canvas id="authReportReadingsChart" aria-label="Road readings chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="authReportSummary" class="auth-report-footnote mt-3"></div>
        <div id="authReportLoading" class="auth-report-loading d-none">
            <i class="fas fa-spinner fa-spin me-2"></i>Loading report data…
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
