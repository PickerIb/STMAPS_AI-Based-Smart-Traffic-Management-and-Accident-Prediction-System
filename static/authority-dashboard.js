/* global L, Chart */
(function ($) {
    'use strict';

    const API = 'index.php';
    let remeraMapConfig = null;
    let homeMap = null;
    let monitorMap = null;
    let homeStreetLayer = null;
    let homeSatelliteLayer = null;
    let homeSatelliteLabels = null;
    let monitorStreetLayer = null;
    let monitorSatelliteLayer = null;
    let monitorSatelliteLabels = null;
    let activeHomeBaseLayer = 'street';
    let activeMonitorBaseLayer = 'street';
    let homeSegmentLayers = [];
    let monitorSegmentLayers = [];
    let homeLandmarkLayers = [];
    let monitorLandmarkLayers = [];
    let accidentMarkers = [];
    let monitorAccidentMarkers = [];
    let homeZoneLayer = null;
    let monitorZoneLayer = null;
    let lastHomeMapPayload = null;
    let pendingRoadStatusFilter = null;
    let lastMonitorMapPayload = null;
    let segmentOptions = [];
    let activityPage = 1;
    let reportPeriod = 'daily';
    let readingsChart = null;
    let alertsChart = null;
    let activityChart = null;
    let lastReportData = null;
    let notifyPollTimer = null;

    const DEFAULT_MAP = {
        center: { lat: -1.9536, lng: 30.1065 },
        bounds: { south: -1.9655, north: -1.9430, west: 30.0975, east: 30.1195 },
        label: 'Remera Sector, Kigali',
        min_zoom: 14,
        max_zoom: 18
    };

    const SECTION_TITLES = {
        dashboard: 'Traffic Police Dashboard',
        'road-status': 'Road Status',
        incidents: 'Report Incidents',
        'map-monitoring': 'Traffic Monitoring',
        notifications: 'Notifications',
        reports: 'Traffic Reports',
        'activity-history': 'Activity History',
        profile: 'My Profile'
    };

    function post(action, data, isFormData) {
        const opts = { url: API, type: 'POST', dataType: 'json' };
        if (isFormData) {
            data.append('action', action);
            opts.data = data;
            opts.processData = false;
            opts.contentType = false;
        } else {
            opts.data = Object.assign({ action: action, _t: Date.now() }, data || {});
        }
        return $.ajax(opts);
    }

    function esc(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }

    function getCfg(config) {
        return config || remeraMapConfig || DEFAULT_MAP;
    }

    function getBounds(cfg) {
        const c = getCfg(cfg);
        return [[c.bounds.south, c.bounds.west], [c.bounds.north, c.bounds.east]];
    }

    function fitSector(map) {
        if (!map) return;
        map.fitBounds(getBounds(), { padding: [20, 20] });
    }

    function applyMapSettings(map, config) {
        if (!map) return;
        const cfg = getCfg(config);
        remeraMapConfig = cfg;
        map.setMaxBounds(getBounds(cfg));
        map.setMinZoom(cfg.min_zoom || 14);
        if (cfg.max_zoom) map.setMaxZoom(cfg.max_zoom);
    }

    function createStreetLayer() {
        return L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19
        });
    }

    function createSatelliteLayer() {
        return L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            { attribution: '&copy; Esri', maxZoom: 19 }
        );
    }

    function createSatelliteLabelsLayer() {
        return L.tileLayer(
            'https://{s}.basemaps.cartocdn.com/light_only_labels/{z}/{x}/{y}{r}.png',
            {
                attribution: '&copy; OpenStreetMap &copy; CARTO',
                subdomains: 'abcd',
                maxZoom: 20,
                opacity: 0.92,
                pane: 'overlayPane'
            }
        );
    }

    function shortRoadName(name) {
        if (!name) return '';
        return String(name).split('(')[0].trim();
    }

    function createMapPinIcon(kind) {
        const styles = {
            landmark: { bg: '#1d4ed8', icon: 'fa-location-dot' },
            accident: { bg: '#dc2626', icon: 'fa-car-burst' }
        };
        const s = styles[kind] || styles.landmark;
        return L.divIcon({
            className: 'driver-map-pin',
            html: '<div class="driver-map-pin-dot" style="background:' + s.bg + '"><i class="fas ' + s.icon + '"></i></div>',
            iconSize: [36, 44],
            iconAnchor: [18, 40]
        });
    }

    function segmentColor(seg) {
        if (seg.status === 'blocked' || seg.congestion_level === 'severe') return '#b91c1c';
        if (seg.congestion_level === 'low') return '#22c55e';
        if (seg.congestion_level === 'moderate') return '#f59e0b';
        if (seg.congestion_level === 'high') return '#f97316';
        return '#b91c1c';
    }

    function isSatelliteMode(map) {
        if (map === homeMap) return activeHomeBaseLayer === 'satellite';
        if (map === monitorMap) return activeMonitorBaseLayer === 'satellite';
        return false;
    }

    function updateMapShellMode(map) {
        const $shell = map === homeMap ? $('.auth-map-shell').first() : $('.auth-monitor-shell');
        $shell.toggleClass('satellite-mode', isSatelliteMode(map));
    }

    function clearBaseLayers(map) {
        if (!map) return;
        const layers = map === homeMap
            ? [homeStreetLayer, homeSatelliteLayer, homeSatelliteLabels]
            : [monitorStreetLayer, monitorSatelliteLayer, monitorSatelliteLabels];
        layers.forEach(function (layer) {
            if (layer && map.hasLayer(layer)) map.removeLayer(layer);
        });
    }

    function setMapBaseLayer(map, layerType) {
        if (!map) return;
        if (map === homeMap) {
            activeHomeBaseLayer = layerType;
            clearBaseLayers(homeMap);
            if (layerType === 'satellite') {
                homeSatelliteLayer.addTo(homeMap);
                homeSatelliteLabels.addTo(homeMap);
            } else {
                homeStreetLayer.addTo(homeMap);
            }
            $('[data-map-target="home"][data-map-layer]').removeClass('active');
            $('[data-map-target="home"][data-map-layer="' + layerType + '"]').addClass('active');
            updateMapShellMode(homeMap);
            if (lastHomeMapPayload) applyMapPayload(homeMap, lastHomeMapPayload, homeSegmentLayers, homeLandmarkLayers, accidentMarkers);
        } else if (map === monitorMap) {
            activeMonitorBaseLayer = layerType;
            clearBaseLayers(monitorMap);
            if (layerType === 'satellite') {
                monitorSatelliteLayer.addTo(monitorMap);
                monitorSatelliteLabels.addTo(monitorMap);
            } else {
                monitorStreetLayer.addTo(monitorMap);
            }
            $('[data-map-target="monitor"][data-map-layer]').removeClass('active');
            $('[data-map-target="monitor"][data-map-layer="' + layerType + '"]').addClass('active');
            updateMapShellMode(monitorMap);
            if (lastMonitorMapPayload) applyMapPayload(monitorMap, lastMonitorMapPayload, monitorSegmentLayers, monitorLandmarkLayers, monitorAccidentMarkers);
        }
    }

    function drawZone(map, currentLayer) {
        if (!map) return currentLayer;
        const cfg = getCfg();
        const b = cfg.bounds;
        if (currentLayer && map.hasLayer(currentLayer)) map.removeLayer(currentLayer);
        const layer = L.rectangle(
            [[b.south, b.west], [b.north, b.east]],
            {
                color: '#1e3a8a',
                weight: 2,
                dashArray: '10 8',
                fillColor: '#2563eb',
                fillOpacity: isSatelliteMode(map) ? 0.08 : 0.05
            }
        ).bindTooltip(cfg.label || 'Remera Sector', {
            permanent: true,
            direction: 'top',
            className: 'remera-zone-label'
        });
        layer.addTo(map);
        return layer;
    }

    function drawLandmarks(map, landmarks, store) {
        store.forEach(function (l) { if (map.hasLayer(l)) map.removeLayer(l); });
        store.length = 0;
        (landmarks || []).forEach(function (lm) {
            const marker = L.marker([lm.lat, lm.lng], {
                icon: createMapPinIcon('landmark'),
                title: lm.name
            }).bindPopup(
                '<strong>' + esc(lm.name) + '</strong><br><small class="text-muted">Remera Sector landmark</small>'
            ).bindTooltip(esc(lm.name), { direction: 'top', offset: [0, -38], className: 'driver-landmark-tooltip' });
            marker.addTo(map);
            store.push(marker);
        });
    }

    function segmentPopupHtml(seg) {
        const pill = seg.congestion_level || 'low';
        return '<div class="auth-map-popup">' +
            '<strong>' + esc(seg.name) + '</strong><br>' +
            '<span class="status-pill ' + esc(pill) + '">' + esc(seg.status_label || 'Updated') + '</span><br>' +
            '<small class="text-muted">' + esc(seg.zone || 'Remera Sector') + '</small>' +
            (seg.avg_speed_kmh != null ? '<br><small>Avg speed: ' + seg.avg_speed_kmh + ' km/h</small>' : '') +
            '</div>';
    }

    function drawSegments(map, segments, store, accidents, showLabels) {
        store.forEach(function (l) { if (map.hasLayer(l)) map.removeLayer(l); });
        store.length = 0;
        const satellite = isSatelliteMode(map);
        const weight = satellite ? 9 : 8;
        const haloWeight = satellite ? 13 : 0;

        (segments || []).forEach(function (seg) {
            const latlngs = [[seg.start_lat, seg.start_lng], [seg.end_lat, seg.end_lng]];
            const color = segmentColor(seg);

            if (haloWeight) {
                const halo = L.polyline(latlngs, {
                    color: '#ffffff',
                    weight: haloWeight,
                    opacity: 0.85,
                    lineJoin: 'round',
                    lineCap: 'round'
                });
                halo.addTo(map);
                store.push(halo);
            }

            const line = L.polyline(latlngs, {
                color: color,
                weight: weight,
                opacity: 0.95,
                lineJoin: 'round',
                lineCap: 'round'
            }).bindPopup(segmentPopupHtml(seg))
              .bindTooltip(shortRoadName(seg.name), { sticky: true, opacity: 0.95 });
            line.addTo(map);
            store.push(line);

            if (showLabels !== false) {
                const midLat = (seg.start_lat + seg.end_lat) / 2;
                const midLng = (seg.start_lng + seg.end_lng) / 2;
                const labelMarker = L.marker([midLat, midLng], {
                    icon: L.divIcon({
                        className: 'remera-road-label',
                        html: '<span>' + esc(shortRoadName(seg.name)) + '</span>'
                    }),
                    interactive: false
                });
                labelMarker.addTo(map);
                store.push(labelMarker);
            }
        });

        const markerStore = map === homeMap ? accidentMarkers : monitorAccidentMarkers;
        markerStore.forEach(function (m) { if (map.hasLayer(m)) map.removeLayer(m); });
        markerStore.length = 0;

        (accidents || []).forEach(function (acc) {
            let lat = acc.lat;
            let lng = acc.lng;
            if ((lat == null || lng == null) && acc.segment_name) {
                const seg = (segments || []).find(function (s) { return s.name === acc.segment_name; });
                if (seg) {
                    lat = (seg.start_lat + seg.end_lat) / 2;
                    lng = (seg.start_lng + seg.end_lng) / 2;
                }
            }
            if (lat == null || lng == null) return;

            const resolved = acc.is_resolved || (acc.notes && String(acc.notes).toLowerCase().indexOf('resolved') >= 0);
            const marker = L.marker([lat, lng], {
                icon: L.divIcon({
                    className: 'auth-accident-marker',
                    html: '<span><i class="fas fa-car-burst"></i></span>',
                    iconSize: [28, 28],
                    iconAnchor: [14, 14]
                }),
                zIndexOffset: 900
            }).bindPopup(
                '<strong>' + (resolved ? 'Resolved accident' : 'Active accident') + '</strong><br>' +
                esc(acc.segment_name || 'Road') + '<br>' +
                esc(acc.cause || acc.severity || '') +
                (acc.accident_time ? '<br><small>' + esc(acc.accident_time) + '</small>' : '')
            );
            marker.addTo(map);
            markerStore.push(marker);
        });
    }

    function applyMapPayload(map, payload, segStore, landmarkStore, accidentStore) {
        if (!map || !payload) return;
        if (payload.map_config) {
            remeraMapConfig = payload.map_config;
            applyMapSettings(map, payload.map_config);
            $('#authHomeMapBadge, #authMonitorMapBadge').text(payload.map_config.label || 'Remera Sector');
        }
        if (map === homeMap) {
            if (homeZoneLayer && homeMap.hasLayer(homeZoneLayer)) homeMap.removeLayer(homeZoneLayer);
            homeZoneLayer = drawZone(homeMap, homeZoneLayer);
        } else if (map === monitorMap) {
            if (monitorZoneLayer && monitorMap.hasLayer(monitorZoneLayer)) monitorMap.removeLayer(monitorZoneLayer);
            monitorZoneLayer = drawZone(monitorMap, monitorZoneLayer);
        }
        drawSegments(map, payload.segments, segStore, payload.accidents, true);
        drawLandmarks(map, payload.landmarks, landmarkStore);
        updateMapShellMode(map);
    }

    function initMapInstance(elementId, target) {
        const map = L.map(elementId, { scrollWheelZoom: true, zoomControl: false });
        L.control.zoom({ position: 'bottomright' }).addTo(map);
        applyMapSettings(map, remeraMapConfig);
        fitSector(map);

        const street = createStreetLayer();
        const satellite = createSatelliteLayer();
        const labels = createSatelliteLabelsLayer();
        street.addTo(map);

        if (target === 'home') {
            homeMap = map;
            homeStreetLayer = street;
            homeSatelliteLayer = satellite;
            homeSatelliteLabels = labels;
            homeZoneLayer = drawZone(homeMap, homeZoneLayer);
            setMapBaseLayer(homeMap, 'street');
        } else {
            monitorMap = map;
            monitorStreetLayer = street;
            monitorSatelliteLayer = satellite;
            monitorSatelliteLabels = labels;
            monitorZoneLayer = drawZone(monitorMap, monitorZoneLayer);
            setMapBaseLayer(monitorMap, 'street');
        }
        return map;
    }

    function initMaps() {
        if (document.getElementById('authHomeMap')) {
            initMapInstance('authHomeMap', 'home');
        }
    }

    function ensureMonitorMap() {
        if (!monitorMap && document.getElementById('authMonitorMap')) {
            initMapInstance('authMonitorMap', 'monitor');
            loadMonitorMapData(true);
        }
    }

    function loadHomeMapData(force) {
        if (!homeMap) return;
        if (force) {
            $('#btnRefreshAuthHomeMap i').addClass('fa-spin');
        }
        post('driver_segments_map').done(function (res) {
            if (!res.success || !homeMap) return;
            lastHomeMapPayload = res;
            applyMapPayload(homeMap, res, homeSegmentLayers, homeLandmarkLayers, accidentMarkers);
            $('#authHomeMapLive').html('<i class="fas fa-circle text-success me-1 live-pulse"></i>Updated ' + new Date().toLocaleTimeString());
        }).always(function () {
            $('#btnRefreshAuthHomeMap i').removeClass('fa-spin');
        });
    }

    function loadMonitorMapData(force) {
        if (!monitorMap) return;
        if (force) {
            $('#btnRefreshAuthMonitorMap i').addClass('fa-spin');
        }
        post('driver_segments_map').done(function (res) {
            if (!res.success || !monitorMap) return;
            lastMonitorMapPayload = res;
            applyMapPayload(monitorMap, res, monitorSegmentLayers, monitorLandmarkLayers, monitorAccidentMarkers);
        }).always(function () {
            $('#btnRefreshAuthMonitorMap i').removeClass('fa-spin');
        });
    }

    function loadMapData(map) {
        if (map === homeMap) loadHomeMapData(false);
        else if (map === monitorMap) loadMonitorMapData(false);
    }

    function updateNotifyBadge(count) {
        const $b = $('#headerNotifyBadge');
        if (!$b.length) return;
        if (count > 0) {
            $b.text(count > 99 ? '99+' : String(count)).show();
        } else {
            $b.hide();
        }
    }

    function loadNotifyCount() {
        post('authority_alerts', { limit: 1 }).done(function (res) {
            if (res.success) updateNotifyBadge(res.count || 0);
        });
    }

    function populateRoadSelects() {
        const html = '<option value="" disabled selected>Select road…</option>' +
            segmentOptions.map(function (s) {
                return '<option value="' + s.id + '">' + esc(s.name) + '</option>';
            }).join('');
        $('.auth-road-select, #accidentSegment').html(html);
    }

    function loadSegmentOptions() {
        post('driver_segments_map').done(function (res) {
            if (!res.success) return;
            segmentOptions = res.segments || [];
            populateRoadSelects();
        });
    }

    function renderFeed($el, items, emptyMsg, tpl) {
        if (!items || !items.length) {
            $el.html('<p class="text-muted mb-0">' + emptyMsg + '</p>');
            return;
        }
        $el.html(items.map(tpl).join(''));
    }

    function loadAuthorityHome() {
        post('authority_home').done(function (res) {
            if (!res.success) return;
            const st = res.stats || {};
            $('#authStatRoadUpdates').text(st.road_updates ?? '—');
            $('#authStatAccidents').text(st.accident_reports ?? '—');
            $('#authStatCongestion').text(st.congestion_reports ?? '—');
            $('#authStatBlockages').text(st.road_blockages ?? '—');
            updateNotifyBadge(st.unacknowledged_alerts || 0);

            if (res.map_config) {
                remeraMapConfig = res.map_config;
                $('#authHomeMapBadge').text(res.map_config.label || 'Remera Sector');
            }

            renderFeed($('#authRecentUpdates'), res.recent_updates, 'No recent updates.', function (u) {
                return '<div class="alert-item info mb-2"><div class="d-flex justify-content-between"><strong>' +
                    esc(u.segment_name) + '</strong><small>' + esc(u.updated_label || '') + '</small></div>' +
                    '<small>' + esc(u.status_label || '') + '</small></div>';
            });

            renderFeed($('#authRecentActivity'), res.recent_activity, 'No activity yet.', function (a) {
                return '<div class="alert-item info mb-2"><div class="d-flex justify-content-between"><strong>' +
                    esc(a.label) + '</strong><small>' + esc(a.created_at) + '</small></div>' +
                    '<small class="text-muted">' + esc(a.details) + '</small></div>';
            });

            if (homeMap) {
                loadHomeMapData(false);
            }
        });
    }

    function loadAccidents() {
        $('#authAccidentList').html('<p class="text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Loading…</p>');
        post('authority_accidents', { limit: 20 }).done(function (res) {
            if (!res.success) return;
            if (!res.accidents.length) {
                $('#authAccidentList').html('<p class="text-muted">No accident reports yet.</p>');
                return;
            }
            let html = '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr>' +
                '<th>Report #</th><th>Road</th><th>Time</th><th>Severity</th><th>Status</th><th></th></tr></thead><tbody>';
            res.accidents.forEach(function (a) {
                html += '<tr><td>' + (a.report_number || '—') + '</td><td>' + esc(a.segment_name) + '</td><td>' + esc(a.accident_time) +
                    '</td><td>' + esc(a.severity) + '</td><td>' + (a.is_resolved ? '<span class="badge bg-success">Resolved</span>' : '<span class="badge bg-warning text-dark">Active</span>') +
                    '</td><td>' + (a.is_resolved ? '' : '<button type="button" class="btn btn-sm btn-outline-success btn-resolve-accident" data-id="' + a.id + '">Resolve</button>') +
                    '</td></tr>';
            });
            html += '</tbody></table></div>';
            $('#authAccidentList').html(html);
        });
    }

    function loadAuthAlerts(force) {
        const $btn = $('#btnRefreshAuthAlerts');
        if (force) {
            $btn.prop('disabled', true);
            $btn.find('i').addClass('fa-spin');
        }
        $('#authAlertsList').html('<p class="text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Loading…</p>');
        post('authority_alerts', { limit: 50 }).done(function (res) {
            if (!res.success) return;
            updateNotifyBadge(res.count || 0);
            if (!res.alerts.length) {
                $('#authAlertsList').html('<p class="text-muted mb-0">No alerts yet.</p>');
                return;
            }
            $('#authAlertsList').html(res.alerts.map(function (a) {
                const typeLabel = a.type === 'road_blockage' ? 'Blockage' : a.type === 'incident' ? 'Incident' : a.type === 'system' ? 'Maintenance' : 'Congestion';
                return '<div class="notification-item alert-item ' + esc(a.severity || 'info') + ' mb-2">' +
                    '<div class="d-flex justify-content-between align-items-start gap-2">' +
                    '<div><strong>' + esc(typeLabel) + '</strong> — ' + esc(a.segment_name || 'Road') +
                    (a.is_acknowledged ? ' <span class="badge bg-secondary">Ack</span>' : '') + '</div>' +
                    '<small class="text-muted">' + esc(a.alert_time) + '</small></div>' +
                    '<div class="notification-message">' + esc(a.message) + '</div>' +
                    (!a.is_acknowledged ? '<button type="button" class="btn btn-sm btn-outline-dark mt-2 btn-ack-alert" data-id="' + a.id + '">Mark acknowledged</button>' : '') +
                    '</div>';
            }).join(''));
            $('#authAlertsRefreshed').text('Updated ' + new Date().toLocaleTimeString());
        }).always(function () {
            $btn.prop('disabled', false);
            $btn.find('i').removeClass('fa-spin');
        });
    }

    function loadActivity(page) {
        activityPage = page || 1;
        post('authority_activity', { page: activityPage, per_page: 15 }).done(function (res) {
            if (!res.success) return;
            const act = res.activity;
            if (!act.items.length) {
                $('#authActivityList').html('<p class="text-muted">No activity records yet.</p>');
            } else {
                $('#authActivityList').html(act.items.map(function (a) {
                    return '<div class="alert-item info mb-2"><div class="d-flex justify-content-between"><strong>' +
                        esc(a.label) + '</strong><small>' + esc(a.created_at) + '</small></div>' +
                        '<small>' + esc(a.details) + '</small></div>';
                }).join(''));
            }
            renderActivityPagination(act);
        });
    }

    function renderActivityPagination(p) {
        const $w = $('#authActivityPagination').empty();
        $('#authActivitySummary').text(p.total ? (p.total + ' records') : '');
        if (!p || p.total_pages <= 1) return;
        const $ul = $('<ul class="pagination pagination-sm mb-0 justify-content-center"></ul>');
        function add(lbl, pg, dis, act) {
            $ul.append('<li class="page-item' + (dis ? ' disabled' : '') + (act ? ' active' : '') +
                '"><button type="button" class="page-link" data-page="' + pg + '"' + (dis ? ' disabled' : '') + '>' + lbl + '</button></li>');
        }
        add('Prev', p.page - 1, p.page <= 1, false);
        for (let i = Math.max(1, p.page - 2); i <= Math.min(p.total_pages, p.page + 2); i++) {
            add(String(i), i, false, i === p.page);
        }
        add('Next', p.page + 1, p.page >= p.total_pages, false);
        $w.append($('<nav></nav>').append($ul));
        $w.off('click').on('click', '.page-link', function () {
            const pg = Number($(this).data('page'));
            if (pg && pg !== activityPage) loadActivity(pg);
        });
    }

    const ALERT_TYPE_META = {
        congestion: { label: 'Congestion', color: '#f59e0b' },
        incident: { label: 'Accidents', color: '#dc2626' },
        road_blockage: { label: 'Road Blockage', color: '#b91c1c' },
        system: { label: 'Maintenance', color: '#6366f1' }
    };

    const CHART_FONT = "'Segoe UI', system-ui, -apple-system, sans-serif";

    function formatReportDay(dayStr) {
        if (!dayStr) return '';
        const parts = String(dayStr).split('-');
        if (parts.length !== 3) return dayStr;
        const d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        if (Number.isNaN(d.getTime())) return dayStr;
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    }

    function alertTypeLabel(type) {
        return (ALERT_TYPE_META[type] || { label: type }).label;
    }

    function mergeActivityTimeline(readings, alertsTimeline) {
        const map = {};
        (readings || []).forEach(function (r) {
            map[r.day] = { readings: Number(r.cnt) || 0, alerts: 0 };
        });
        (alertsTimeline || []).forEach(function (a) {
            if (!map[a.day]) {
                map[a.day] = { readings: 0, alerts: 0 };
            }
            map[a.day].alerts = Number(a.cnt) || 0;
        });
        const days = Object.keys(map).sort();
        if (!days.length) {
            return { labels: ['No data'], readings: [0], alerts: [0] };
        }
        return {
            labels: days.map(formatReportDay),
            readings: days.map(function (d) { return map[d].readings; }),
            alerts: days.map(function (d) { return map[d].alerts; })
        };
    }

    function destroyReportCharts() {
        [readingsChart, alertsChart, activityChart].forEach(function (c) {
            if (c) c.destroy();
        });
        readingsChart = null;
        alertsChart = null;
        activityChart = null;
    }

    function chartGradient(ctx, colorRgb) {
        const g = ctx.createLinearGradient(0, 0, 0, 280);
        g.addColorStop(0, 'rgba(' + colorRgb + ', 0.35)');
        g.addColorStop(1, 'rgba(' + colorRgb + ', 0.02)');
        return g;
    }

    function baseChartOptions(extra) {
        return Object.assign({
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 600, easing: 'easeOutQuart' },
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        font: { family: CHART_FONT, size: 12 },
                        padding: 16,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.92)',
                    titleFont: { family: CHART_FONT, size: 13, weight: '600' },
                    bodyFont: { family: CHART_FONT, size: 12 },
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: true
                }
            }
        }, extra || {});
    }

    function renderReportKpis(reports) {
        $('#authKpiReadings').text(reports.total_readings ?? 0);
        $('#authKpiAccidents').text(reports.accident_count ?? 0);
        $('#authKpiAlerts').text(reports.total_alerts ?? 0);
        $('#authKpiBlockages').text(reports.blockage_count ?? 0);
        $('#authReportPeriodLabel').text(
            (reports.period_label || 'Report') + ' — Remera Sector traffic police activity'
        );
    }

    function renderAlertTypeLegend(alerts) {
        const $el = $('#authReportAlertLegend').empty();
        (alerts || []).forEach(function (a) {
            const meta = ALERT_TYPE_META[a.type] || { label: a.type, color: '#94a3b8' };
            $el.append(
                '<span class="legend-item"><span class="legend-swatch" style="background:' + meta.color + '"></span>' +
                esc(meta.label) + ' <strong>' + (a.cnt || 0) + '</strong></span>'
            );
        });
        if (!$el.children().length) {
            $el.html('<span class="text-muted small">No alerts in this period</span>');
        }
    }

    function loadReports(period) {
        reportPeriod = period || reportPeriod;
        $('#authReportLoading').removeClass('d-none');
        $('#authReportKpis, .auth-report-chart-card').css('opacity', '0.55');

        post('authority_reports', { period: reportPeriod }).done(function (res) {
            if (!res.success) return;
            lastReportData = res.reports;
            const reports = res.reports || {};
            const readings = reports.road_readings || [];
            const alerts = reports.alerts_by_type || [];
            const timeline = mergeActivityTimeline(readings, reports.alerts_timeline || []);

            renderReportKpis(reports);
            destroyReportCharts();

            const activityCtx = document.getElementById('authReportActivityChart');
            if (activityCtx) {
                activityChart = new Chart(activityCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: timeline.labels,
                        datasets: [
                            {
                                label: 'Road updates',
                                data: timeline.readings,
                                borderColor: '#2563eb',
                                backgroundColor: chartGradient(activityCtx.getContext('2d'), '37, 99, 235'),
                                borderWidth: 3,
                                fill: true,
                                tension: 0.35,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                pointBackgroundColor: '#2563eb'
                            },
                            {
                                label: 'Traffic alerts',
                                data: timeline.alerts,
                                borderColor: '#f59e0b',
                                backgroundColor: chartGradient(activityCtx.getContext('2d'), '245, 158, 11'),
                                borderWidth: 3,
                                fill: true,
                                tension: 0.35,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                pointBackgroundColor: '#f59e0b'
                            }
                        ]
                    },
                    options: baseChartOptions({
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { font: { family: CHART_FONT, size: 11 }, color: '#64748b' }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: { stepSize: 1, font: { family: CHART_FONT, size: 11 }, color: '#64748b' },
                                grid: { color: 'rgba(148, 163, 184, 0.2)' },
                                title: { display: true, text: 'Count', font: { family: CHART_FONT, size: 11 } }
                            }
                        }
                    })
                });
            }

            const alertLabels = alerts.length
                ? alerts.map(function (a) { return alertTypeLabel(a.type); })
                : ['No alerts'];
            const alertData = alerts.length ? alerts.map(function (a) { return Number(a.cnt) || 0; }) : [1];
            const alertColors = alerts.length
                ? alerts.map(function (a) { return (ALERT_TYPE_META[a.type] || { color: '#94a3b8' }).color; })
                : ['#e2e8f0'];

            const alertsCtx = document.getElementById('authReportAlertsChart');
            if (alertsCtx) {
                alertsChart = new Chart(alertsCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: alertLabels,
                        datasets: [{
                            data: alertData,
                            backgroundColor: alertColors,
                            borderColor: '#fff',
                            borderWidth: 3,
                            hoverOffset: 8
                        }]
                    },
                    options: baseChartOptions({
                        cutout: '62%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        const total = ctx.dataset.data.reduce(function (s, v) { return s + v; }, 0);
                                        const pct = total ? Math.round((ctx.parsed / total) * 100) : 0;
                                        return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                                    }
                                }
                            }
                        }
                    })
                });
            }
            renderAlertTypeLegend(alerts);

            const rLabels = readings.length ? readings.map(function (r) { return formatReportDay(r.day); }) : ['No data'];
            const rData = readings.length ? readings.map(function (r) { return Number(r.cnt) || 0; }) : [0];
            const readingsCtx = document.getElementById('authReportReadingsChart');
            if (readingsCtx) {
                const rCtx = readingsCtx.getContext('2d');
                readingsChart = new Chart(rCtx, {
                    type: 'bar',
                    data: {
                        labels: rLabels,
                        datasets: [{
                            label: 'Police road readings',
                            data: rData,
                            backgroundColor: chartGradient(rCtx, '29, 78, 216'),
                            borderColor: '#2563eb',
                            borderWidth: 1,
                            borderRadius: 8,
                            borderSkipped: false,
                            maxBarThickness: 48
                        }]
                    },
                    options: baseChartOptions({
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { font: { family: CHART_FONT, size: 11 }, color: '#64748b' }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: { stepSize: 1, font: { family: CHART_FONT, size: 11 }, color: '#64748b' },
                                grid: { color: 'rgba(148, 163, 184, 0.2)' }
                            }
                        }
                    })
                });
            }

            const congestion = reports.congestion_count ?? 0;
            const maintenance = reports.maintenance_count ?? 0;
            $('#authReportSummary').html(
                '<i class="fas fa-circle-info me-1"></i>' +
                '<strong>' + esc(reports.period_label || reportPeriod) + '</strong> summary: ' +
                (reports.total_readings ?? 0) + ' road update(s), ' +
                (reports.accident_count ?? 0) + ' accident report(s), ' +
                (reports.total_alerts ?? 0) + ' alert(s) ' +
                '(congestion ' + congestion + ', blockages ' + (reports.blockage_count ?? 0) +
                ', maintenance ' + maintenance + ').'
            );
        }).always(function () {
            $('#authReportLoading').addClass('d-none');
            $('#authReportKpis, .auth-report-chart-card').css('opacity', '1');
        });
    }

    function loadProfile() {
        post('authority_profile_get').done(function (res) {
            if (!res.success) return;
            const p = res.profile;
            $('#authFirstName').val(p.first_name);
            $('#authLastName').val(p.last_name);
            $('#authEmail').val(p.email);
            $('#authPhone').val(p.phone_number);
            $('#authPoliceId').val(p.police_id || '—');
            if (p.profile_picture) {
                $('#authProfileAvatar').html('<img src="' + esc(p.profile_picture) + '" alt="Profile">');
            }
            syncTopUser(p);
        });
    }

    function syncTopUser(p) {
        if (p.first_name) $('#topUserName').text((p.first_name + ' ' + (p.last_name || '')).trim());
        if (p.email) $('#topUserEmail').text(p.email);
        if (p.profile_picture) $('#topUserAvatar').html('<img src="' + esc(p.profile_picture) + '" alt="">');
    }

    function showFormMsg($el, res) {
        $el.html('<div class="alert alert-' + (res.success ? 'success' : 'danger') + ' py-2">' + esc(res.message) + '</div>');
    }

    function setIncidentFormBusy($form, busy) {
        const $btn = $form.find('button[type="submit"]');
        if (busy) {
            if (!$btn.data('default-html')) {
                $btn.data('default-html', $btn.html());
            }
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Submitting…');
            $form.addClass('is-submitting');
        } else {
            $btn.prop('disabled', false).html($btn.data('default-html') || 'Submit');
            $form.removeClass('is-submitting');
        }
    }

    function submitIncidentForm($form, action, $msg, onSuccess) {
        if ($form.hasClass('is-submitting')) {
            return;
        }
        setIncidentFormBusy($form, true);
        post(action, serializeForm($form)).always(function () {
            setIncidentFormBusy($form, false);
        }).done(function (res) {
            showFormMsg($msg, res);
            if (res.success) {
                if (typeof onSuccess === 'function') {
                    onSuccess(res);
                }
                $form[0].reset();
            }
        }).fail(function () {
            showFormMsg($msg, { success: false, message: 'Could not reach the server. Please try again.' });
        });
    }

    function serializeForm($form) {
        const o = {};
        $form.serializeArray().forEach(function (f) { o[f.name] = f.value; });
        return o;
    }

    function initNavigation() {
        function showSection(section) {
            $('.nav-link[data-section]').removeClass('active');
            $('.nav-link[data-section="' + section + '"]').addClass('active');
            $('.authority-section').hide();
            $('#' + section + '-section').show();
            $('#pageTitle').text(SECTION_TITLES[section] || 'Traffic Police Dashboard');
            onSectionShown(section);
        }
        $('.nav-link[data-section]').on('click', function (e) {
            e.preventDefault();
            showSection($(this).data('section'));
        });
        $('.authority-nav-jump, [data-section-jump]').on('click', function (e) {
            e.preventDefault();
            const $el = $(this);
            const sec = $el.data('section') || $el.data('section-jump');
            const roadFilter = $el.data('road-filter');
            if (roadFilter && sec === 'road-status') {
                pendingRoadStatusFilter = roadFilter;
            }
            showSection(sec);
        });
        $('#btnHeaderNotifications').on('click', function () {
            showSection('notifications');
        });
        showSection('dashboard');
    }

    function onSectionShown(section) {
        if (section === 'dashboard') {
            loadAuthorityHome();
            setTimeout(function () {
                if (homeMap) {
                    homeMap.invalidateSize();
                    fitSector(homeMap);
                    loadHomeMapData(false);
                }
            }, 250);
        }
        if (section === 'road-status' && window.STRGSRoadStatus) {
            const filter = pendingRoadStatusFilter;
            pendingRoadStatusFilter = null;
            STRGSRoadStatus.load(true).done(function () {
                if (filter) {
                    STRGSRoadStatus.setFilter(filter);
                }
            });
            STRGSRoadStatus.startRefresh();
        } else if (window.STRGSRoadStatus) {
            STRGSRoadStatus.stopRefresh();
        }
        if (section === 'incidents') {
            loadSegmentOptions();
            loadAccidents();
            switchIncidentTab('accident');
            if (!$('#accidentTime').val()) {
                const now = new Date();
                now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                $('#accidentTime').val(now.toISOString().slice(0, 16));
            }
        }
        if (section === 'map-monitoring') {
            ensureMonitorMap();
            setTimeout(function () {
                if (monitorMap) {
                    monitorMap.invalidateSize();
                    fitSector(monitorMap);
                    loadMonitorMapData(false);
                }
            }, 250);
        }
        if (section === 'notifications') loadAuthAlerts(false);
        if (section === 'reports') {
            loadReports(reportPeriod);
            setTimeout(function () {
                [activityChart, alertsChart, readingsChart].forEach(function (c) {
                    if (c) c.resize();
                });
            }, 280);
        }
        if (section === 'activity-history') loadActivity(1);
        if (section === 'profile') loadProfile();
    }

    function switchIncidentTab(tab) {
        const key = tab || 'accident';
        $('.incidents-tab').each(function () {
            const isActive = $(this).data('incident-tab') === key;
            $(this).toggleClass('active', isActive).attr('aria-selected', isActive ? 'true' : 'false');
        });
        $('.incident-form-panel').each(function () {
            const isActive = this.id === 'incidentPanel' + key.charAt(0).toUpperCase() + key.slice(1);
            $(this).toggleClass('active', isActive).prop('hidden', !isActive);
        });
    }

    function bindEvents() {
        $('#btnFitRemeraAuthHome').on('click', function () { fitSector(homeMap); });
        $('#btnFitRemeraAuthMonitor').on('click', function () { fitSector(monitorMap); });
        $('#btnRefreshAuthHomeMap').on('click', function () { loadHomeMapData(true); });
        $('#btnRefreshAuthMonitorMap').on('click', function () { loadMonitorMapData(true); });
        $(document).on('click', '[data-map-target="home"][data-map-layer]', function () {
            setMapBaseLayer(homeMap, $(this).data('map-layer'));
        });
        $(document).on('click', '[data-map-target="monitor"][data-map-layer]', function () {
            setMapBaseLayer(monitorMap, $(this).data('map-layer'));
        });

        $(document).on('click', '.incidents-tab', function () {
            switchIncidentTab($(this).data('incident-tab'));
        });

        $('#authAccidentForm').on('submit', function (e) {
            e.preventDefault();
            const $form = $(this);
            submitIncidentForm($form, 'authority_report_accident', $('#authAccidentMsg'), function () {
                loadAccidents();
                loadNotifyCount();
                if (window.STRGSRoadStatus) {
                    STRGSRoadStatus.load(true);
                }
            });
        });
        $('#authCongestionForm, #authBlockageForm').on('submit', function (e) {
            e.preventDefault();
            const $form = $(this);
            const $msg = $form.attr('id') === 'authCongestionForm' ? $('#authCongestionMsg') : $('#authBlockageMsg');
            submitIncidentForm($form, 'authority_report_alert', $msg, function () {
                loadAuthAlerts(false);
                loadNotifyCount();
                if (window.STRGSRoadStatus) {
                    STRGSRoadStatus.load(true);
                }
            });
        });
        $(document).on('click', '.btn-resolve-accident', function () {
            const id = $(this).data('id');
            const note = prompt('Resolution note (optional):') || '';
            post('authority_resolve_accident', { accident_id: id, resolution_note: note }).done(function (res) {
                alert(res.message || (res.success ? 'Done' : 'Failed'));
                if (res.success) loadAccidents();
            });
        });
        $(document).on('click', '.btn-ack-alert', function () {
            post('authority_ack_alert', { alert_id: $(this).data('id') }).done(function (res) {
                if (res.success) loadAuthAlerts(false);
            });
        });
        $('#btnRefreshAccidents').on('click', loadAccidents);
        $('#btnRefreshAuthAlerts').on('click', function () { loadAuthAlerts(true); });
        $('#authReportPeriod button').on('click', function () {
            $('#authReportPeriod button').removeClass('active');
            $(this).addClass('active');
            loadReports($(this).data('period'));
        });
        $('#btnExportAuthReport').on('click', function () {
            if (!lastReportData) return;
            const rows = [
                ['Metric', 'Value'],
                ['Period', lastReportData.period_label || reportPeriod],
                ['Road updates', lastReportData.total_readings || 0],
                ['Accidents', lastReportData.accident_count || 0],
                ['Total alerts', lastReportData.total_alerts || 0],
                ['Road blockages', lastReportData.blockage_count || 0],
                ['Congestion alerts', lastReportData.congestion_count || 0],
                ['Maintenance alerts', lastReportData.maintenance_count || 0]
            ];
            (lastReportData.alerts_by_type || []).forEach(function (a) {
                rows.push(['Alert: ' + alertTypeLabel(a.type), a.cnt]);
            });
            (lastReportData.road_readings || []).forEach(function (r) {
                rows.push(['Readings ' + r.day, r.cnt]);
            });
            const csv = rows.map(function (r) { return r.join(','); }).join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'strgs_traffic_report_' + reportPeriod + '.csv';
            a.click();
        });
        $('#btnAuthChooseAvatar').on('click', function () { $('#authProfilePictureInput').click(); });
        $('#authProfilePictureInput').on('change', function () {
            const fd = new FormData();
            fd.append('profile_picture', this.files[0]);
            post('authority_upload_avatar', fd, true).done(function (res) {
                if (res.success && res.profile_picture) {
                    $('#authProfileAvatar').html('<img src="' + esc(res.profile_picture) + '" alt="">');
                    $('#topUserAvatar').html('<img src="' + esc(res.profile_picture) + '" alt="">');
                }
                alert(res.message);
            });
        });
        $('#authProfileForm').on('submit', function (e) {
            e.preventDefault();
            post('authority_profile_update', serializeForm($(this))).done(function (res) {
                showFormMsg($('#authProfileMsg'), res);
                if (res.success) syncTopUser({ first_name: $('#authFirstName').val(), last_name: $('#authLastName').val(), email: $('#authEmail').val() });
            });
        });
        $('#authPasswordForm').on('submit', function (e) {
            e.preventDefault();
            const fd = $(this).serialize() + '&action=change_password';
            $.post(API, fd, function (res) {
                showFormMsg($('#authPasswordMsg'), typeof res === 'string' ? JSON.parse(res) : res);
            });
        });
        if (window.innerWidth <= 768) {
            $('#sidebarToggle').on('click', function () { $('#sidebar').toggleClass('mobile-open'); });
        }
    }

    function initSidebar() {
        $('#sidebarToggle').on('click', function () {
            $('#sidebar').toggleClass('collapsed');
            $('#mainContent').toggleClass('sidebar-collapsed');
            const icon = $(this).find('i');
            if ($('#sidebar').hasClass('collapsed')) {
                icon.removeClass('fa-bars').addClass('fa-chevron-right');
            } else {
                icon.removeClass('fa-chevron-right').addClass('fa-bars');
            }
        });
    }

    $(document).ready(function () {
        initSidebar();
        bindEvents();
        try { initMaps(); } catch (err) { console.error('STRGS authority map init:', err); }
        initNavigation();
        loadSegmentOptions();
        loadNotifyCount();
        notifyPollTimer = setInterval(loadNotifyCount, 60000);
        setTimeout(function () {
            if (homeMap) loadHomeMapData(false);
        }, 400);
    });
})(jQuery);
