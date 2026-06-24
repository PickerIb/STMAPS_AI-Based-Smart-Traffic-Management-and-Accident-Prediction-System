/* global STRGS_ROAD_STATUS_MODE */
(function ($) {
    'use strict';

    const API = 'index.php';
    const MODE = window.STRGS_ROAD_STATUS_MODE || 'view';
    const IS_AUTHORITY = MODE === 'authority';

    let latestRoadStatus = null;
    let currentRoadFilter = 'all';
    let roadStatusRefreshTimer = null;
    let liveMetaTicker = null;
    let lastLiveMeta = null;
    let lastFetchedAt = null;
    let latestReadingEpochMs = null;
    let isRefreshing = false;

    function post(action, data) {
        return $.ajax({
            url: API,
            type: 'POST',
            dataType: 'json',
            cache: false,
            data: Object.assign({ action: action, _t: Date.now() }, data || {})
        });
    }

    function esc(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }

    function parseDbDateTime(value) {
        if (!value) return null;
        const s = String(value).trim();
        const m = s.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
        if (m) {
            return new Date(
                parseInt(m[1], 10),
                parseInt(m[2], 10) - 1,
                parseInt(m[3], 10),
                parseInt(m[4], 10),
                parseInt(m[5], 10),
                parseInt(m[6], 10)
            );
        }
        const dt = new Date(s);
        return Number.isNaN(dt.getTime()) ? null : dt;
    }

    function formatSecondsAgo(seconds) {
        if (seconds == null || seconds < 0) return 'Unknown time';
        if (seconds < 10) return 'Just now';
        if (seconds < 60) return seconds + (seconds === 1 ? ' second ago' : ' seconds ago');
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + (minutes === 1 ? ' minute ago' : ' minutes ago');
        const hours = Math.floor(seconds / 3600);
        if (hours < 24) return hours + (hours === 1 ? ' hour ago' : ' hours ago');
        const days = Math.floor(seconds / 86400);
        return days + (days === 1 ? ' day ago' : ' days ago');
    }

    function statusClass(level) {
        const map = {
            low: 'normal',
            moderate: 'congested',
            high: 'heavy',
            severe: 'severe',
            normal: 'normal',
            congested: 'congested',
            blocked: 'blocked',
            issue: 'issues'
        };
        return map[level] || map[String(level || '').toLowerCase()] || 'moderate';
    }

    function statusIcon(status, level) {
        if (status === 'blocked') return 'fa-ban';
        if (status === 'issue') return 'fa-triangle-exclamation';
        if (level === 'severe' || level === 'high') return 'fa-traffic-light';
        if (level === 'moderate') return 'fa-gauge-high';
        return 'fa-circle-check';
    }

    function formatCongestionLabel(level) {
        const labels = { low: 'Low', moderate: 'Moderate', high: 'High', severe: 'Severe' };
        return labels[level] || 'Unknown';
    }

    function formatWeather(w) {
        if (!w) return '—';
        return String(w).charAt(0).toUpperCase() + String(w).slice(1);
    }

    function formatSpeedCell(seg) {
        const speed = seg.avg_speed_kmh;
        const limit = seg.speed_limit_kmh;
        if (speed == null) return '<span class="text-muted">—</span>';
        let cls = 'speed-ok';
        if (limit && speed < limit * 0.35) cls = 'speed-severe';
        else if (limit && speed < limit * 0.55) cls = 'speed-slow';
        const limitTxt = limit ? ' <small class="text-muted">/ ' + limit + ' limit</small>' : '';
        return '<span class="road-speed-value ' + cls + '">' + speed + ' km/h</span>' + limitTxt;
    }

    function renderSummary(summary) {
        const s = summary || {};
        $('#driverRoadSummary').html(
            '<div class="road-summary-item normal">' +
            '<div class="road-summary-icon"><i class="fas fa-road-circle-check"></i></div>' +
            '<div class="road-summary-count">' + (s.normal ?? 0) + '</div>' +
            '<div class="road-summary-label">Normal Flow</div></div>' +
            '<div class="road-summary-item congested">' +
            '<div class="road-summary-icon"><i class="fas fa-traffic-light"></i></div>' +
            '<div class="road-summary-count">' + (s.congested ?? 0) + '</div>' +
            '<div class="road-summary-label">Congested</div></div>' +
            '<div class="road-summary-item blocked">' +
            '<div class="road-summary-icon"><i class="fas fa-ban"></i></div>' +
            '<div class="road-summary-count">' + (s.blocked ?? 0) + '</div>' +
            '<div class="road-summary-label">Blocked</div></div>' +
            '<div class="road-summary-item issues">' +
            '<div class="road-summary-icon"><i class="fas fa-screwdriver-wrench"></i></div>' +
            '<div class="road-summary-count">' + (s.road_issues ?? 0) + '</div>' +
            '<div class="road-summary-label">Needs Attention</div></div>'
        );
    }

    function getReadingAgeSeconds() {
        if (latestReadingEpochMs == null) {
            return lastLiveMeta?.latest_reading_seconds_ago ?? null;
        }
        return Math.max(0, Math.floor((Date.now() - latestReadingEpochMs) / 1000));
    }

    function getLiveStatusFromAge(seconds) {
        if (seconds == null) return 'no_data';
        if (seconds <= 900) return 'live';
        if (seconds <= 3600) return 'recent';
        return 'stale';
    }

    function computeLiveStatusFromMeta(meta) {
        const ageSecs = getReadingAgeSeconds();
        const tz = meta?.timezone_label || 'Kigali time (CAT)';

        if (ageSecs == null) {
            return {
                status: 'no_data',
                label: 'No live data',
                detail: meta?.latest_reading_label || 'No traffic readings yet'
            };
        }

        const ageLabel = formatSecondsAgo(ageSecs);
        const liveStatus = getLiveStatusFromAge(ageSecs);

        if (liveStatus === 'live') {
            return { status: 'live', label: 'Live now', detail: 'Last field update ' + ageLabel + ' (' + tz + ')' };
        }
        if (liveStatus === 'recent') {
            return { status: 'recent', label: 'Recently updated', detail: 'Last field update ' + ageLabel + ' (' + tz + ')' };
        }
        return { status: 'stale', label: 'Data is stale', detail: 'Last field update ' + ageLabel + ' (' + tz + ')' };
    }

    function updateHeader(meta, summary) {
        const zone = meta?.zone_label || 'Remera Sector, Kigali';
        const totalRoads = meta?.total_roads ?? (latestRoadStatus?.segments?.length || 0);
        const withReadings = meta?.roads_with_readings ?? 0;
        const normal = summary?.normal ?? 0;
        const congested = summary?.congested ?? 0;
        const blocked = summary?.blocked ?? 0;
        const tz = meta?.timezone_label || 'Kigali time (CAT)';
        const serverClock = meta?.server_time_local || '';

        $('#roadStatusTitleText').text(zone + ' — Road Status' + (IS_AUTHORITY ? ' Control' : ''));

        const metaLine = 'Monitoring <strong>' + totalRoads + '</strong> roads · ' +
            '<strong>' + withReadings + '</strong> with field readings · ' +
            '<strong>' + normal + '</strong> normal · <strong>' + congested + '</strong> congested' +
            (blocked > 0 ? ' · <strong>' + blocked + '</strong> blocked' : '') +
            (serverClock ? ' · Server clock: <strong>' + esc(serverClock) + '</strong> (' + esc(tz) + ')' : '') + '.';

        $('#roadStatusHeaderMeta').html(
            (IS_AUTHORITY
                ? 'Traffic Police road register — data you enter here is shown to drivers. '
                : 'Real-time road conditions entered by Traffic Police officers. ') + metaLine
        );
    }

    function updateLiveBadge(meta) {
        lastLiveMeta = meta || null;
        latestReadingEpochMs = meta?.latest_reading_epoch != null
            ? Number(meta.latest_reading_epoch) * 1000
            : null;
        const live = computeLiveStatusFromMeta(meta);
        const $badge = $('#roadStatusLiveBadge');

        $badge.removeClass('road-live-live road-live-recent road-live-stale road-live-unknown road-live-nodata');

        if (live.status === 'live') {
            $badge.addClass('road-live-live').html(
                '<i class="fas fa-circle me-1 live-pulse"></i>' + esc(live.label) + ' · ' + esc(live.detail)
            );
        } else if (live.status === 'recent') {
            $badge.addClass('road-live-recent').html(
                '<i class="fas fa-clock me-1"></i>' + esc(live.label) + ' · ' + esc(live.detail)
            );
        } else if (live.status === 'stale') {
            $badge.addClass('road-live-stale').html(
                '<i class="fas fa-triangle-exclamation me-1"></i>' + esc(live.label) + ' · ' + esc(live.detail)
            );
        } else {
            $badge.addClass('road-live-nodata').html(
                '<i class="fas fa-circle me-1"></i>' + esc(live.label) + ' — waiting for Traffic Police readings'
            );
        }

        updateLastSyncLine();
    }

    function updateLastSyncLine() {
        const $sync = $('#roadStatusLastSync');
        if (!$sync.length) return;

        if (!lastFetchedAt) {
            $sync.text('Not synced yet');
            return;
        }

        const syncSecs = Math.max(0, Math.floor((Date.now() - lastFetchedAt) / 1000));
        const tz = lastLiveMeta?.timezone_label || 'Kigali time (CAT)';
        let syncText = 'Page synced ' + formatSecondsAgo(syncSecs) + ' · ' + tz;
        const localReading = lastLiveMeta?.latest_reading_at_local || lastLiveMeta?.latest_reading_at;
        if (localReading) {
            syncText += ' · Latest reading: ' + localReading;
        }
        $sync.text(syncText);
    }

    function startLiveMetaTicker() {
        stopLiveMetaTicker();
        liveMetaTicker = setInterval(function () {
            if (lastLiveMeta) {
                updateLiveBadge(lastLiveMeta);
            } else {
                updateLastSyncLine();
            }
        }, 30000);
    }

    function stopLiveMetaTicker() {
        if (liveMetaTicker) {
            clearInterval(liveMetaTicker);
            liveMetaTicker = null;
        }
    }

    function setRefreshLoading(loading) {
        isRefreshing = loading;
        const $btn = $('#btnRefreshRoadStatus');
        const $icon = $('#roadRefreshIcon');
        const $label = $('#roadRefreshLabel');
        if (!$btn.length) return;

        $btn.prop('disabled', loading);
        if (loading) {
            $icon.removeClass('fa-sync').addClass('fa-spinner fa-spin');
            $label.text('Refreshing…');
        } else {
            $icon.removeClass('fa-spinner fa-spin').addClass('fa-sync');
            $label.text('Refresh now');
        }
    }

    function getFilteredRoads(filter) {
        if (!latestRoadStatus) return [];
        if (filter === 'normal') return latestRoadStatus.normal_roads || [];
        if (filter === 'congested') return latestRoadStatus.congested_roads || [];
        if (filter === 'blocked') return latestRoadStatus.blocked_roads || [];
        if (filter === 'issues') return latestRoadStatus.issue_roads || [];
        return latestRoadStatus.segments || [];
    }

    function adviceIcon(tone) {
        const t = String(tone || 'safe').toLowerCase();
        if (t === 'danger') return 'fa-circle-xmark';
        if (t === 'warning') return 'fa-triangle-exclamation';
        if (t === 'caution') return 'fa-circle-exclamation';
        return 'fa-circle-check';
    }

    function renderRoadTable(filter) {
        const roads = getFilteredRoads(filter);
        const colSpan = IS_AUTHORITY ? 8 : 3;
        const $tb = $('#driverRoadTable tbody').empty();
        $('#roadTableCountBadge').text(roads.length + ' road' + (roads.length === 1 ? '' : 's'));

        if (!roads.length) {
            $tb.append('<tr><td colspan="' + colSpan + '" class="road-table-empty">No roads match this filter.</td></tr>');
            return;
        }

        roads.forEach(function (seg, index) {
            const pillClass = statusClass(seg.congestion_level || seg.status);
            const icon = statusIcon(seg.status, seg.congestion_level);
            const updated = seg.updated_label || seg.reading_time_local || '';
            const authorityCells = IS_AUTHORITY
                ? '<td class="road-congestion-col"><span class="congestion-chip congestion-' + esc(seg.congestion_level || 'low') + '">' +
                  esc(formatCongestionLabel(seg.congestion_level)) + '</span></td>' +
                  '<td class="text-end road-speed-col">' + formatSpeedCell(seg) + '</td>' +
                  '<td class="text-end road-vehicles-col"><span class="road-vehicle-badge">' +
                  (seg.vehicle_count != null ? seg.vehicle_count : '—') + '</span></td>'
                : '';
            const driverAdviceCell = !IS_AUTHORITY
                ? '<td class="road-advice-col"><span class="safe-travel-advice safe-travel-' + esc(seg.safe_travel_tone || 'safe') + '">' +
                  '<i class="fas ' + adviceIcon(seg.safe_travel_tone) + ' me-1"></i>' +
                  esc(seg.safe_travel_advice || 'Safe to travel — check status before departing.') + '</span></td>'
                : '';
            const authorityTail = IS_AUTHORITY
                ? '<td class="road-weather-col"><span class="road-weather-badge">' +
                  '<i class="fas ' + weatherIcon(seg.weather) + ' me-1"></i>' + esc(formatWeather(seg.weather)) + '</span></td>' +
                  '<td class="road-reason-cell" title="' + esc(seg.reason || '') + '">' + esc(seg.reason || '—') + '</td>' +
                  '<td class="text-end road-action-cell"><button type="button" class="btn btn-sm btn-road-update btn-edit-road" data-segment-id="' +
                  seg.id + '"><i class="fas fa-pen"></i><span class="d-none d-md-inline ms-1">Update</span></button></td>'
                : '';

            $tb.append(
                '<tr class="road-row road-row-' + esc(pillClass) + (index % 2 === 1 ? ' road-row-alt' : '') + '">' +
                '<td class="road-name-col"><div class="road-name-cell">' +
                '<strong class="road-name-text">' + esc(seg.name) + '</strong>' +
                '<small class="road-meta-line">' + esc(seg.zone || 'Remera Sector') +
                (seg.lanes ? ' · ' + seg.lanes + ' lane' + (seg.lanes > 1 ? 's' : '') : '') + '</small>' +
                (updated ? '<small class="road-updated-inline"><i class="fas fa-clock me-1"></i>' + esc(updated) + '</small>' : '') +
                '</div></td>' +
                '<td class="road-status-col"><span class="status-pill ' + esc(pillClass) + '">' +
                '<i class="fas ' + icon + ' me-1"></i>' + esc(seg.status_label || 'Unknown') + '</span></td>' +
                authorityCells +
                driverAdviceCell +
                authorityTail + '</tr>'
            );
        });
    }

    function weatherIcon(weather) {
        const w = String(weather || '').toLowerCase();
        if (w === 'rain' || w === 'storm') return 'fa-cloud-rain';
        if (w === 'fog') return 'fa-smog';
        if (w === 'cloudy') return 'fa-cloud';
        if (w === 'clear') return 'fa-sun';
        return 'fa-cloud-sun';
    }

    function renderFeed(selector, items, tpl) {
        const $el = $(selector);
        if (!items || !items.length) {
            $el.html('<p class="text-muted mb-0">Nothing to report.</p>');
            return;
        }
        $el.html(items.map(tpl).join(''));
    }

    function populateAuthorityRoadSelect(segments) {
        const $sel = $('#authorityRoadSelect');
        if (!$sel.length) return;
        const current = $sel.val();
        $sel.find('option:not(:first)').remove();
        (segments || []).forEach(function (seg) {
            $sel.append(
                $('<option></option>').val(seg.id).text(seg.name + ' (' + (seg.zone || 'Remera Sector') + ')')
            );
        });
        if (current) $sel.val(current);
    }

    function setDefaultReadingTime() {
        const $input = $('#authorityReadingTime');
        if (!$input.length) return;
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        $input.val(now.toISOString().slice(0, 16));
    }

    function prefillAuthorityForm(segmentId) {
        if (!latestRoadStatus || !segmentId) return;
        const seg = (latestRoadStatus.segments || []).find(function (s) { return String(s.id) === String(segmentId); });
        $('#authorityRoadSelect').val(segmentId);
        if (seg) {
            if (seg.vehicle_count != null) $('#authorityVehicles').val(seg.vehicle_count);
            if (seg.avg_speed_kmh != null) $('#authoritySpeed').val(seg.avg_speed_kmh);
            if (seg.weather) $('#authorityWeather').val(String(seg.weather).toLowerCase());
            if (seg.reason) $('#authorityStatusNote').val(seg.reason);
        }
        $('#roadEntryCard').slideDown(200);
        $('html, body').animate({ scrollTop: $('#roadEntryCard').offset().top - 90 }, 300);
    }

    function renderRoadStatusData(res) {
        latestRoadStatus = res;
        lastFetchedAt = Date.now();
        renderSummary(res.summary);
        updateHeader(res.meta, res.summary);
        updateLiveBadge(res.meta);
        renderRoadTable(currentRoadFilter);
        populateAuthorityRoadSelect(res.segments);

        renderFeed('#driverRoadIssueList', res.issue_roads, function (seg) {
            return '<div class="road-side-item road-side-issue">' +
                '<div class="d-flex justify-content-between align-items-start gap-2">' +
                '<strong>' + esc(seg.name) + '</strong>' +
                '<span class="status-pill ' + esc(statusClass(seg.congestion_level)) + '">' + esc(seg.status_label) + '</span></div>' +
                '<p class="mb-1 small">' + esc(seg.reason || 'No reason provided') + '</p>' +
                '<small class="text-muted">' + esc(seg.updated_label || seg.reading_time_local || seg.reading_time || '') + '</small></div>';
        });

        renderFeed('#driverAccidentList', res.accidents, function (a) {
            return '<div class="road-side-item road-side-accident">' +
                '<div class="d-flex justify-content-between align-items-start gap-2">' +
                '<strong>' + esc(a.segment_name || 'Unknown road') + '</strong>' +
                '<span class="badge bg-danger-subtle text-danger border">' + esc(a.severity || '—') + '</span></div>' +
                '<p class="mb-1 small">' + esc(a.cause || 'Cause not recorded') + '</p>' +
                '<small class="text-muted">' + esc(a.accident_time || '') + '</small></div>';
        });
    }

    function showRoadStatusError(message) {
        $('#roadStatusLiveBadge')
            .removeClass('road-live-live road-live-recent road-live-stale road-live-nodata')
            .addClass('road-live-unknown')
            .html('<i class="fas fa-circle-xmark me-1"></i>' + esc(message || 'Could not load road status'));
        $('#roadStatusLastSync').text('Refresh failed — click Refresh now to try again');
    }

    function loadRoadStatus(force) {
        if (isRefreshing && !force) {
            return $.Deferred().resolve().promise();
        }

        setRefreshLoading(true);

        return post('driver_road_status').done(function (res) {
            if (!res || !res.success) {
                showRoadStatusError(res?.message || 'Server returned an invalid response');
                return;
            }
            renderRoadStatusData(res);
        }).fail(function (xhr) {
            let msg = 'Could not reach the server';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.status === 403) {
                msg = 'Access denied — please log in again';
            } else if (xhr.status === 0) {
                msg = 'Network error — check your connection';
            }
            showRoadStatusError(msg);
        }).always(function () {
            setRefreshLoading(false);
        });
    }

    function submitAuthorityRoadForm(e) {
        e.preventDefault();
        const $btn = $('#btnSubmitRoadData');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving…');

        post('authority_submit_road_reading', {
            segment_id: $('#authorityRoadSelect').val(),
            reading_time: $('#authorityReadingTime').val(),
            vehicle_count: $('#authorityVehicles').val(),
            avg_speed_kmh: $('#authoritySpeed').val(),
            density_veh_per_km: $('#authorityDensity').val(),
            weather: $('#authorityWeather').val(),
            status_note: $('#authorityStatusNote').val()
        }).always(function () {
            $btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save Road Status');
        }).done(function (res) {
            const cls = res.success ? 'success' : 'danger';
            $('#authorityRoadFormMsg').html('<div class="alert alert-' + cls + ' py-2 mb-0">' + esc(res.message || (res.success ? 'Saved' : 'Failed')) + '</div>');
            if (res.success) {
                loadRoadStatus();
            }
        }).fail(function () {
            $('#authorityRoadFormMsg').html('<div class="alert alert-danger py-2 mb-0">Could not save road data. Please try again.</div>');
        });
    }

    function startRoadStatusRefresh() {
        stopRoadStatusRefresh();
        roadStatusRefreshTimer = setInterval(function () {
            if ($('#road-status-section').is(':visible') || $('.road-status-page:visible').length) {
                loadRoadStatus();
            }
        }, 45000);
        startLiveMetaTicker();
    }

    function stopRoadStatusRefresh() {
        if (roadStatusRefreshTimer) {
            clearInterval(roadStatusRefreshTimer);
            roadStatusRefreshTimer = null;
        }
        stopLiveMetaTicker();
    }

    function bindRoadStatusEvents() {
        $(document).off('click.roadstatus', '#btnRefreshRoadStatus').on('click.roadstatus', '#btnRefreshRoadStatus', function (e) {
            e.preventDefault();
            loadRoadStatus(true);
        });

        $(document).off('click.roadstatus', '#driverRoadFilters [data-filter]')
            .on('click.roadstatus', '#driverRoadFilters [data-filter]', function () {
                currentRoadFilter = $(this).data('filter');
                $('#driverRoadFilters [data-filter]').removeClass('active');
                $(this).addClass('active');
                renderRoadTable(currentRoadFilter);
            });

        $(document).off('click.roadstatus', '.btn-edit-road')
            .on('click.roadstatus', '.btn-edit-road', function () {
                prefillAuthorityForm($(this).data('segment-id'));
            });

        if (IS_AUTHORITY) {
            $('#btnToggleRoadEntry').off('click.roadstatus').on('click.roadstatus', function () {
                $('#roadEntryCard').slideToggle(200);
            });
            $('#btnClearRoadForm').off('click.roadstatus').on('click.roadstatus', function () {
                $('#authorityRoadForm')[0].reset();
                setDefaultReadingTime();
                $('#authorityRoadFormMsg').empty();
            });
            $('#authorityRoadForm').off('submit.roadstatus').on('submit.roadstatus', submitAuthorityRoadForm);
            setDefaultReadingTime();
        }
    }

    window.STRGSRoadStatus = {
        load: function (force) { return loadRoadStatus(!!force); },
        startRefresh: startRoadStatusRefresh,
        stopRefresh: stopRoadStatusRefresh,
        renderTable: function (filter) { renderRoadTable(filter || currentRoadFilter); },
        getData: function () { return latestRoadStatus; }
    };

    $(document).ready(function () {
        if (!$('#road-status-section').length && !$('.road-status-page').length) return;
        bindRoadStatusEvents();
        loadRoadStatus();
        startRoadStatusRefresh();
    });
})(jQuery);
