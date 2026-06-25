<?php
$road_status_mode = $road_status_mode ?? 'view';
$is_authority = $road_status_mode === 'authority';
?>
<div class="road-status-page">
    <div class="road-status-hero mb-4">
        <div class="road-status-hero-text">
            <h2 class="road-status-title mb-1" id="roadStatusZoneTitle">
                <i class="fas fa-road me-2"></i><span id="roadStatusTitleText">Remera Sector — Road Status</span>
            </h2>
            <p class="road-status-subtitle mb-0" id="roadStatusHeaderMeta">
                <?php if ($is_authority): ?>
                    Loading live road register from Traffic Police field data…
                <?php else: ?>
                    Loading live road conditions from Traffic Police updates…
                <?php endif; ?>
            </p>
        </div>
        <div class="road-status-hero-meta text-end">
            <div class="road-live-status-wrap">
                <span class="badge road-live-badge road-live-unknown" id="roadStatusLiveBadge">
                    <i class="fas fa-circle-notch fa-spin me-1"></i>Checking live status…
                </span>
                <small class="d-block road-last-sync mt-1" id="roadStatusLastSync">Waiting for first sync…</small>
            </div>
            <?php if ($is_authority): ?>
            <button type="button" class="btn btn-dark btn-sm mt-2" id="btnToggleRoadEntry">
                <i class="fas fa-pen-to-square me-1"></i>Enter / Update Road Data
            </button>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-light btn-sm mt-2" id="btnRefreshRoadStatus" title="Reload road data from database">
                <i class="fas fa-sync me-1" id="roadRefreshIcon"></i><span id="roadRefreshLabel">Refresh now</span>
            </button>
        </div>
    </div>

    <?php if ($is_authority): ?>
    <div class="dashboard-card mb-4 road-entry-card" id="roadEntryCard" style="display:none;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0"><i class="fas fa-clipboard-list"></i> Update Road Status</h3>
            <span class="badge bg-dark">Traffic Police Entry</span>
        </div>
        <div class="card-body">
            <div id="authorityRoadFormMount"></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="road-summary-grid mb-4" id="driverRoadSummary">Loading road summary…</div>

    <div class="dashboard-card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h3 class="card-title mb-0"><i class="fas fa-filter"></i> Filter by Condition</h3>
            <div class="road-filter-group" id="driverRoadFilters" role="group" aria-label="Filter roads">
                <button type="button" class="road-filter-btn active" data-filter="all"><i class="fas fa-list me-1"></i>All Roads</button>
                <button type="button" class="road-filter-btn" data-filter="normal"><i class="fas fa-circle-check me-1"></i>Normal</button>
                <button type="button" class="road-filter-btn" data-filter="congested"><i class="fas fa-traffic-light me-1"></i>Congested</button>
                <button type="button" class="road-filter-btn" data-filter="blocked"><i class="fas fa-ban me-1"></i>Blocked</button>
                <button type="button" class="road-filter-btn" data-filter="issues"><i class="fas fa-triangle-exclamation me-1"></i>Needs Attention</button>
            </div>
        </div>
    </div>

    <div class="dashboard-card road-table-card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h3 class="card-title mb-0"><i class="fas fa-table-list"></i> Road Status</h3>
                <small class="text-muted">Remera Sector — evaluate traffic flow, speed, and road condition at a glance</small>
            </div>
            <span class="badge bg-light text-dark border" id="roadTableCountBadge">0 roads</span>
        </div>
        <div class="table-responsive road-status-table-wrap">
            <table class="table road-status-table mb-0" id="driverRoadTable">
                <thead>
                    <tr>
                        <th>Road</th>
                        <th>Status</th>
                        <?php if ($is_authority): ?>
                        <th>Congestion</th>
                        <th>Officer Notes</th>
                        <th class="text-end">Action</th>
                        <?php else: ?>
                        <th>Safe Travel Advice</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-6">
            <div class="dashboard-card h-100 road-issue-card">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-circle-exclamation text-warning"></i> Roads Needing Attention</h3></div>
                <div id="driverRoadIssueList" class="road-side-list p-3">Loading…</div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="dashboard-card h-100 road-accident-card">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-car-burst text-danger"></i> Recent Accidents</h3></div>
                <div id="driverAccidentList" class="road-side-list p-3">Loading…</div>
            </div>
        </div>
    </div>
</div>
