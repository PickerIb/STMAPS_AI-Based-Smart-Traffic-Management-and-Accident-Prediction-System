<?php
/** Driver-only dashboard sections — included from dashboard.php */
$driver_user_id = (int)($_SESSION['user_id'] ?? 0);
$driver_first = $_SESSION['first_name'] ?? explode(' ', $user_name)[0] ?? 'Driver';
$driver_avatar = $_SESSION['profile_picture'] ?? null;

// Load profile picture from DB if available
try {
    $dbTmp = (new Database())->getConnection();
    if ($dbTmp) {
        $u = new User($dbTmp);
        if ($u->findById($driver_user_id)) {
            $driver_avatar = $u->profile_picture;
            $_SESSION['profile_picture'] = $driver_avatar;
        }
    }
} catch (Exception $e) {
    // non-fatal
}
?>

<!-- Driver: Home -->
<div id="dashboard-section" class="content-section driver-section">
    <div class="driver-welcome card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h2 class="mb-1">Welcome, <?php echo htmlspecialchars($driver_first); ?></h2>
                <p class="text-muted mb-0">Smart Traffic Route Guidance System — Remera Sector, Kigali City</p>
            </div>
            <div class="text-end">
                <div class="small text-muted">Current location</div>
                <div id="driverHomeLocation" class="fw-semibold">Waiting for GPS…</div>
                <div id="driverGpsHelp" class="small text-muted mt-1">Allow location access to detect your live position automatically.</div>
                <button type="button" class="btn btn-sm btn-outline-dark mt-2" id="btnRefreshGpsHome">
                    <i class="fas fa-location-crosshairs me-1"></i>Refresh GPS
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2 ms-2" id="btnOpenGoogleTraffic">
                    <i class="fab fa-google me-1"></i>Google Traffic
                </button>
            </div>
        </div>
    </div>

    <div class="stats-grid driver-stats mb-4">
        <div class="stat-card stat-clickable stat-road-normal" data-road-filter="normal">
            <div class="stat-icon stat-icon-success"><i class="fas fa-road-circle-check" aria-hidden="true"></i></div>
            <div class="stat-number" id="statNormalRoads">—</div>
            <div class="stat-label">Normal Roads</div>
            <small class="text-muted">Clear traffic flow</small>
        </div>
        <div class="stat-card stat-clickable stat-road-congested" data-road-filter="congested">
            <div class="stat-icon stat-icon-warning stat-icon-jam" title="Congested roads">
                <i class="fas fa-traffic-light stat-jam-light" aria-hidden="true"></i>
                <i class="fas fa-car stat-jam-car" aria-hidden="true"></i>
            </div>
            <div class="stat-number" id="statCongested">—</div>
            <div class="stat-label">Congested Roads</div>
            <small class="text-muted">Slow or heavy traffic</small>
        </div>
        <div class="stat-card stat-clickable stat-road-blocked" data-road-filter="blocked">
            <div class="stat-icon stat-icon-danger"><i class="fas fa-ban" aria-hidden="true"></i></div>
            <div class="stat-number" id="statBlocked">—</div>
            <div class="stat-label">Blocked Roads</div>
            <small class="text-muted">Closed to traffic</small>
        </div>
        <div class="stat-card stat-clickable stat-road-issues" data-road-filter="issues">
            <div class="stat-icon stat-icon-issues"><i class="fas fa-screwdriver-wrench" aria-hidden="true"></i></div>
            <div class="stat-number" id="statRoadIssues">—</div>
            <div class="stat-label">Roads Not Working</div>
            <small class="text-muted">Faults &amp; maintenance</small>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="dashboard-card driver-home-map-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h3 class="card-title mb-0"><i class="fas fa-map-marked-alt"></i> Remera Sector — Traffic Overview</h3>
                        <small class="text-muted">Live traffic status across study area roads</small>
                    </div>
                    <span class="badge bg-dark" id="driverMapZoneBadge">Remera Sector, Kigali</span>
                </div>
                <div class="driver-map-legend driver-home-map-legend px-3 py-2 small border-bottom">
                    <span class="me-3"><span class="legend-dot legend-normal"></span> Normal</span>
                    <span class="me-3"><span class="legend-dot legend-congested"></span> Congested</span>
                    <span class="me-3"><span class="legend-dot legend-heavy"></span> Heavy</span>
                    <span class="me-3"><span class="legend-dot legend-severe"></span> Severe</span>
                    <span><i class="fas fa-location-dot text-primary me-1"></i>Landmarks</span>
                </div>
                <div class="driver-map-shell">
                    <div id="driverHomeMap" class="driver-map driver-home-map"></div>
                    <div class="driver-map-toolbar" role="toolbar" aria-label="Home map tools">
                        <button type="button" class="driver-map-tool-btn" id="btnFitRemeraHome" title="Fit Remera Sector">
                            <i class="fas fa-expand"></i><span>Sector</span>
                        </button>
                        <button type="button" class="driver-map-tool-btn" id="btnFocusGpsHomeMap" title="Center on my GPS">
                            <i class="fas fa-location-crosshairs"></i><span>My GPS</span>
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0"><i class="fas fa-broadcast-tower"></i> Recent Road Updates</h3>
                    <small class="text-muted" id="driverUpdatesLive"><i class="fas fa-circle text-success me-1 live-pulse"></i>Loading…</small>
                </div>
                <div id="driverRecentUpdates" class="driver-feed p-3">Loading Remera road updates…</div>
            </div>
            <div class="dashboard-card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-bell"></i> Notifications</h3></div>
                <div id="driverHomeNotifications" class="driver-feed p-3">Loading…</div>
            </div>
        </div>
    </div>

    <div class="quick-actions mt-4">
        <a href="#route-map" class="action-btn driver-nav-jump" data-section="route-map"><div class="action-icon"><i class="fas fa-route"></i></div><span>Get Route Guidance</span></a>
        <a href="#road-status" class="action-btn driver-nav-jump" data-section="road-status"><div class="action-icon"><i class="fas fa-road"></i></div><span>Check Road Status</span></a>
        <a href="#notifications" class="action-btn driver-nav-jump" data-section="notifications"><div class="action-icon"><i class="fas fa-bell"></i></div><span>View Alerts</span></a>
        <a href="#profile" class="action-btn driver-nav-jump" data-section="profile"><div class="action-icon"><i class="fas fa-user"></i></div><span>My Profile</span></a>
    </div>
</div>

<!-- Driver: Route Guidance + Map -->
<div id="route-map-section" class="content-section driver-section" style="display:none;">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="dashboard-card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-location-dot"></i> Plan Your Trip</h3></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="driverOrigin">Your location (start)</label>
                        <select class="form-select mb-2" id="driverOrigin">
                            <option value="__gps__" selected>Use my live GPS location</option>
                        </select>
                        <div class="input-group">
                            <input type="text" class="form-control" id="driverOriginLabel" readonly value="Use GPS or refresh">
                            <button type="button" class="btn btn-outline-secondary" id="btnEnableGps" title="Enable GPS"><i class="fas fa-crosshairs"></i></button>
                        </div>
                        <small class="text-muted" id="driverOriginCoords">—</small>
                        <div class="mt-2 d-grid gap-2">
                            <button type="button" class="btn btn-outline-warning btn-sm" id="btnSetManualLocation">
                                <i class="fas fa-hand-pointer me-1"></i>Use map center as my location
                            </button>
                            <small class="text-muted">Select a start point above, enable GPS, or move the map and use the fallback button.</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="driverDestination">Destination</label>
                        <select class="form-select" id="driverDestination">
                            <option value="" disabled selected>Choose a Remera Sector destination…</option>
                        </select>
                        <small class="text-muted">Select a destination, then click <strong>Request Route Guidance</strong> to see three route options and choose the best one for you.</small>
                    </div>
                    <button type="button" class="btn btn-dark w-100 mb-2" id="btnRequestRoute">
                        <i class="fas fa-route me-2"></i>Request Route Guidance
                    </button>
                    <button type="button" class="btn btn-outline-secondary w-100" id="btnRefreshGpsRoute">
                        <i class="fas fa-sync me-2"></i>Refresh My Location
                    </button>
                    <button type="button" class="btn btn-outline-primary w-100 mt-2" id="btnOpenGoogleTrafficRoute">
                        <i class="fab fa-google me-2"></i>Open Google Traffic
                    </button>
                </div>
            </div>
            <div class="dashboard-card mt-4" id="driverRouteResults" style="display:none;">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-list-ol"></i> Choose Your Route</h3></div>
                <div class="card-body p-0" id="driverRouteList"></div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="dashboard-card driver-route-map-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h3 class="card-title mb-0"><i class="fas fa-map-location-dot"></i> Remera Sector — Interactive Route Map</h3>
                        <small class="text-muted">Study area map for driver route planning</small>
                    </div>
                    <span class="badge bg-dark" id="driverRouteMapBadge">Remera Sector, Kigali</span>
                </div>
                <div class="driver-map-legend driver-route-map-legend px-3 py-2 small border-bottom">
                    <span class="me-3"><span class="legend-dot legend-normal"></span> Clear</span>
                    <span class="me-3"><span class="legend-dot legend-congested"></span> Moderate</span>
                    <span class="me-3"><span class="legend-dot legend-heavy"></span> Heavy</span>
                    <span class="me-3"><span class="legend-dot legend-severe"></span> Severe</span>
                    <span><i class="fas fa-location-dot text-primary me-1"></i>Landmarks</span>
                </div>
                <div class="driver-map-shell">
                    <div id="driverRouteMap" class="driver-map driver-route-map"></div>
                    <div class="driver-map-toolbar" role="toolbar" aria-label="Map tools">
                        <button type="button" class="driver-map-tool-btn" id="btnFitRemeraRoute" title="Fit Remera Sector">
                            <i class="fas fa-expand"></i><span>Sector</span>
                        </button>
                        <button type="button" class="driver-map-tool-btn" id="btnFocusGpsRoute" title="Center on my GPS">
                            <i class="fas fa-location-crosshairs"></i><span>My GPS</span>
                        </button>
                    </div>
                    <div class="driver-map-layer-toggle" role="group" aria-label="Map base layer">
                        <button type="button" class="driver-layer-btn active" data-map-target="route" data-map-layer="street">
                            <i class="fas fa-road me-1"></i>Street
                        </button>
                        <button type="button" class="driver-layer-btn" data-map-target="route" data-map-layer="satellite">
                            <i class="fas fa-satellite me-1"></i>Satellite
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Driver: Road Status -->
<div id="road-status-section" class="content-section driver-section" style="display:none;">
<?php $road_status_mode = 'view'; include __DIR__ . '/road_status_panel.php'; ?>
</div>

<!-- Driver: Notifications -->
<div id="notifications-section" class="content-section driver-section" style="display:none;">
    <div class="dashboard-card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title mb-0"><i class="fas fa-bell"></i> Traffic Alerts & Notifications</h3>
            <div class="d-flex align-items-center gap-2">
                <small class="text-muted" id="alertsLastRefreshed"></small>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRefreshAlerts">
                    <i class="fas fa-sync me-1"></i>Refresh
                </button>
            </div>
        </div>
        <div id="driverAlertsList" class="driver-feed p-3">Loading…</div>
    </div>
</div>

<!-- Driver: Travel History -->
<div id="travel-history-section" class="content-section driver-section" style="display:none;">
    <div class="dashboard-card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title mb-0"><i class="fas fa-history"></i> Travel History</h3>
            <small class="text-muted" id="historyPageSummary"></small>
        </div>
        <div id="driverHistoryList" class="p-3">Loading…</div>
        <div class="card-footer bg-white border-top" id="driverHistoryPagination"></div>
    </div>
</div>

<!-- Driver: Profile -->
<div id="profile-section" class="content-section driver-section" style="display:none;">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="dashboard-card text-center">
                <div class="card-body">
                    <div class="driver-avatar-lg mx-auto mb-3" id="profileAvatarPreview">
                        <?php if ($driver_avatar): ?>
                            <img src="<?php echo htmlspecialchars($driver_avatar); ?>" alt="Profile">
                        <?php else: ?>
                            <span><?php echo strtoupper(substr($user_name, 0, 1)); ?></span>
                        <?php endif; ?>
                    </div>
                    <form id="avatarUploadForm" enctype="multipart/form-data">
                        <input type="file" name="profile_picture" id="profilePictureInput" accept="image/*" class="d-none">
                        <button type="button" class="btn btn-outline-dark btn-sm" id="btnChooseAvatar"><i class="fas fa-camera me-1"></i>Upload Photo</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="dashboard-card mb-4">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-user-edit"></i> Personal Information</h3></div>
                <div class="card-body">
                    <form id="driverProfileForm">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">First Name</label><input type="text" class="form-control" name="first_name" id="profileFirstName" required></div>
                            <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" class="form-control" name="last_name" id="profileLastName" required></div>
                            <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" id="profileEmail" required></div>
                            <div class="col-md-6"><label class="form-label">Phone</label><input type="tel" class="form-control" name="phone_number" id="profilePhone" required></div>
                            <div class="col-md-6"><label class="form-label">License ID</label><input type="text" class="form-control" id="profileLicense" readonly disabled></div>
                            <div class="col-12"><button type="submit" class="btn btn-dark">Save Profile</button></div>
                        </div>
                    </form>
                    <div id="profileFormMsg" class="mt-2"></div>
                </div>
            </div>
            <div class="dashboard-card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-lock"></i> Change Password</h3></div>
                <div class="card-body">
                    <form id="driverPasswordForm">
                        <div class="mb-3"><label class="form-label">Current Password</label><input type="password" class="form-control" name="current_password" required></div>
                        <div class="mb-3"><label class="form-label">New Password</label><input type="password" class="form-control" name="new_password" minlength="8" required></div>
                        <button type="submit" class="btn btn-outline-dark">Update Password</button>
                    </form>
                    <div id="passwordFormMsg" class="mt-2"></div>
                </div>
            </div>
        </div>
    </div>
</div>
