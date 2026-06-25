/* global L, STRGS_DRIVER */
(function ($) {
    'use strict';

    const API = 'index.php';
    let gps = { lat: null, lng: null, label: 'Current location' };
    let homeMap = null;
    let routeMap = null;
    let streetLayer = null;
    let satelliteLayer = null;
    let segmentLayers = [];
    let homeSegmentLayers = [];
    let routeSegmentLayers = [];
    let routeLayers = [];
    let destMarker = null;
    let userMarker = null;
    let homeUserMarker = null;
    let routeMapInitialized = false;
    let gpsBusy = false;
    let selectedDest = null;
    let lastRoutes = [];
    let lastGuidanceMeta = null;
    let selectedRouteIndex = null;
    let historyPage = 1;
    const historyPerPage = 10;
    let notificationPollTimer = null;
    let lastNotificationCount = 0;
    let latestRoadStatus = null;
    let currentRoadFilter = 'all';
    let remeraMapConfig = null;
    let homeZoneLayer = null;
    let routeZoneLayer = null;
    let homeLandmarkLayers = [];
    let routeLandmarkLayers = [];
    let homeStreetLayer = null;
    let homeSatelliteLayer = null;
    let homeSatelliteLabels = null;
    let routeStreetLayer = null;
    let routeSatelliteLayer = null;
    let routeSatelliteLabels = null;
    let activeHomeBaseLayer = 'street';
    let activeRouteBaseLayer = 'street';
    let homeRefreshTimer = null;
    let routeRefreshTimer = null;
    let relativeTimeTicker = null;
    let lastRecentUpdates = [];

    const DEFAULT_REMERa_MAP = {
        zone: 'Remera Sector',
        center: { lat: -1.9536, lng: 30.1065 },
        bounds: { south: -1.9655, north: -1.9430, west: 30.0975, east: 30.1195 },
        label: 'Remera Sector, Kigali',
        min_zoom: 14,
        max_zoom: 18
    };

    function getRemeraConfig(config) {
        return config || remeraMapConfig || DEFAULT_REMERa_MAP;
    }

    function getRemeraBoundsLatLng(config) {
        const cfg = getRemeraConfig(config);
        const b = cfg.bounds;
        return [[b.south, b.west], [b.north, b.east]];
    }

    function applyRemeraMapSettings(map, config) {
        if (!map) return;
        const cfg = getRemeraConfig(config);
        remeraMapConfig = cfg;
        map.setMaxBounds(getRemeraBoundsLatLng(cfg));
        map.setMinZoom(cfg.min_zoom || 14);
        if (cfg.max_zoom) {
            map.setMaxZoom(cfg.max_zoom);
        }
        map.setView([cfg.center.lat, cfg.center.lng], cfg.min_zoom || 14);
    }

    function drawRemeraZoneOverlay(map, config, currentLayer) {
        if (!map) return currentLayer;
        const cfg = getRemeraConfig(config);
        if (currentLayer && map.hasLayer(currentLayer)) {
            map.removeLayer(currentLayer);
        }
        const b = cfg.bounds;
        const layer = L.rectangle(
            [[b.south, b.west], [b.north, b.east]],
            {
                color: '#1e3a8a',
                weight: 2,
                dashArray: '10 8',
                fillColor: '#2563eb',
                fillOpacity: 0.05
            }
        ).bindTooltip(cfg.label, { permanent: true, direction: 'top', className: 'remera-zone-label' });
        layer.addTo(map);
        return layer;
    }

    function drawHomeLandmarks(map, landmarks) {
        if (!map) return;
        homeLandmarkLayers.forEach(function (l) { map.removeLayer(l); });
        homeLandmarkLayers = [];
        (landmarks || []).forEach(function (lm) {
            const marker = L.marker([lm.lat, lm.lng], {
                icon: createMapPinIcon('landmark'),
                title: lm.name
            }).bindPopup(
                '<strong>' + esc(lm.name) + '</strong><br><small class="text-muted">Remera Sector landmark</small>'
            ).bindTooltip(esc(lm.name), { direction: 'top', offset: [0, -38], className: 'driver-landmark-tooltip' });
            marker.addTo(map);
            homeLandmarkLayers.push(marker);
        });
    }

    function createStreetTileLayer() {
        return L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19
        });
    }

    function createSatelliteTileLayer() {
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

    function clearHomeBaseLayers() {
        if (!homeMap) return;
        [homeStreetLayer, homeSatelliteLayer, homeSatelliteLabels].forEach(function (layer) {
            if (layer && homeMap.hasLayer(layer)) {
                homeMap.removeLayer(layer);
            }
        });
    }

    function setHomeBaseLayer(layerType) {
        if (!homeMap) return;
        activeHomeBaseLayer = layerType;
        clearHomeBaseLayers();
        if (layerType === 'satellite') {
            homeSatelliteLayer.addTo(homeMap);
            homeSatelliteLabels.addTo(homeMap);
        } else {
            homeStreetLayer.addTo(homeMap);
        }
        $('[data-map-target="home"][data-map-layer]').removeClass('active');
        $('[data-map-target="home"][data-map-layer="' + layerType + '"]').addClass('active');
    }

    function clearRouteBaseLayers() {
        if (!routeMap) return;
        [routeStreetLayer, routeSatelliteLayer, routeSatelliteLabels].forEach(function (layer) {
            if (layer && routeMap.hasLayer(layer)) {
                routeMap.removeLayer(layer);
            }
        });
    }

    function setRouteBaseLayer(layerType) {
        if (!routeMap) return;
        activeRouteBaseLayer = layerType;
        clearRouteBaseLayers();
        if (layerType === 'satellite') {
            routeSatelliteLayer.addTo(routeMap);
            routeSatelliteLabels.addTo(routeMap);
        } else {
            routeStreetLayer.addTo(routeMap);
        }
        $('[data-map-target="route"][data-map-layer]').removeClass('active');
        $('[data-map-target="route"][data-map-layer="' + layerType + '"]').addClass('active');
    }

    function fitRemeraSector(map) {
        if (!map) return;
        map.fitBounds(getRemeraBoundsLatLng(), { padding: [24, 24] });
    }

    function createMapPinIcon(kind) {
        const styles = {
            user: { bg: '#16a34a', icon: 'fa-location-crosshairs', label: 'You' },
            dest: { bg: '#dc2626', icon: 'fa-flag-checkered', label: 'Dest' },
            landmark: { bg: '#1d4ed8', icon: 'fa-location-dot', label: '' }
        };
        const s = styles[kind] || styles.landmark;
        return L.divIcon({
            className: 'driver-map-pin',
            html: '<div class="driver-map-pin-dot" style="background:' + s.bg + '"><i class="fas ' + s.icon + '"></i></div>' +
                (s.label ? '<span class="driver-map-pin-label">' + s.label + '</span>' : ''),
            iconSize: [36, 44],
            iconAnchor: [18, 40]
        });
    }

    function drawRouteLandmarks(map, landmarks) {
        if (!map) return;
        routeLandmarkLayers.forEach(function (l) { map.removeLayer(l); });
        routeLandmarkLayers = [];
        (landmarks || []).forEach(function (lm) {
            const marker = L.marker([lm.lat, lm.lng], {
                icon: createMapPinIcon('landmark'),
                title: lm.name
            }).bindPopup(
                '<strong>' + esc(lm.name) + '</strong><br><small class="text-muted">Remera Sector landmark</small>'
            ).bindTooltip(esc(lm.name), { direction: 'top', offset: [0, -38], className: 'driver-landmark-tooltip' });
            marker.addTo(map);
            routeLandmarkLayers.push(marker);
        });
    }

    function ensureDestMarker(lat, lng) {
        if (!routeMap) return;
        if (destMarker) {
            destMarker.setLatLng([lat, lng]);
            return;
        }
        destMarker = L.marker([lat, lng], { icon: createMapPinIcon('dest'), zIndexOffset: 1000 }).addTo(routeMap);
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

    function secondsAgoFromValue(value, secondsAgo) {
        if (typeof secondsAgo === 'number' && secondsAgo >= 0) {
            return secondsAgo;
        }
        const dt = parseDbDateTime(value);
        if (!dt) return null;
        return Math.max(0, Math.floor((Date.now() - dt.getTime()) / 1000));
    }

    function formatSecondsAgo(seconds) {
        if (seconds == null || seconds < 0) return 'Unknown time';
        if (seconds < 10) return 'Just now';
        if (seconds < 60) {
            return seconds + (seconds === 1 ? ' second ago' : ' seconds ago');
        }
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) {
            return minutes + (minutes === 1 ? ' minute ago' : ' minutes ago');
        }
        const hours = Math.floor(seconds / 3600);
        if (hours < 24) {
            return hours + (hours === 1 ? ' hour ago' : ' hours ago');
        }
        const days = Math.floor(seconds / 86400);
        if (days < 7) {
            return days + (days === 1 ? ' day ago' : ' days ago');
        }
        const weeks = Math.floor(days / 7);
        return weeks + (weeks === 1 ? ' week ago' : ' weeks ago');
    }

    function formatRoadUpdateTime(value, secondsAgo) {
        if (typeof secondsAgo === 'number') {
            return formatSecondsAgo(secondsAgo);
        }
        const secs = secondsAgoFromValue(value, null);
        if (secs == null) return value ? String(value) : 'Unknown time';
        return formatSecondsAgo(secs);
    }

    function getNewestUpdateSeconds(updates) {
        let newest = null;
        (updates || []).forEach(function (u) {
            const secs = secondsAgoFromValue(u.reading_time, u.seconds_ago);
            if (secs == null) return;
            if (newest == null || secs < newest) {
                newest = secs;
            }
        });
        return newest;
    }

    function updateLiveBadge(updates) {
        const newestSecs = getNewestUpdateSeconds(updates);
        if (newestSecs == null) {
            $('#driverUpdatesLive').html('<i class="fas fa-circle text-secondary me-1"></i>No road readings yet');
            return;
        }
        $('#driverUpdatesLive').html(
            '<i class="fas fa-circle text-success me-1 live-pulse"></i>Newest data: ' +
            esc(formatSecondsAgo(newestSecs))
        );
    }

    function renderRecentRoadUpdates(updates) {
        const fetchedAt = Date.now();
        lastRecentUpdates = (updates || []).map(function (u) {
            return Object.assign({}, u, { _fetched_at: fetchedAt });
        });
        renderFeed('#driverRecentUpdates', lastRecentUpdates, function (u) {
            const speed = u.avg_speed_kmh != null ? u.avg_speed_kmh + ' km/h' : '—';
            const vehicles = u.vehicle_count != null ? u.vehicle_count + ' vehicles' : '—';
            const weather = u.weather ? ' · ' + esc(u.weather) : '';
            const timeLabel = u.updated_label || formatRoadUpdateTime(u.reading_time, u.seconds_ago);
            const secs = secondsAgoFromValue(u.reading_time, u.seconds_ago);
            return '<div class="driver-feed-item road-update-item" data-seconds-ago="' + (secs != null ? secs : '') + '" data-reading-time="' + esc(u.reading_time || '') + '">' +
                '<div class="d-flex justify-content-between align-items-start gap-2">' +
                '<strong class="road-update-name">' + esc(u.segment_name) + '</strong>' +
                '<span class="status-pill ' + statusClass(u.congestion_level || 'moderate') + '">' +
                esc(u.status_label || 'Updated') + '</span></div>' +
                '<div class="small text-muted">' + esc(u.zone || 'Remera Sector') + '</div>' +
                '<div class="small mt-1 road-update-meta">' + speed + ' · ' + vehicles + weather + '</div>' +
                '<small class="text-muted road-update-time"><i class="fas fa-clock me-1"></i>' + esc(timeLabel) + '</small></div>';
        });
        updateLiveBadge(lastRecentUpdates);
    }

    function refreshRelativeTimesOnPage() {
        if (!lastRecentUpdates.length) return;

        let newestSecs = null;
        lastRecentUpdates.forEach(function (u, index) {
            const baseSecs = secondsAgoFromValue(u.reading_time, u.seconds_ago);
            if (baseSecs == null) return;
            const elapsed = Math.floor((Date.now() - (u._fetched_at || Date.now())) / 1000);
            const currentSecs = baseSecs + elapsed;
            if (newestSecs == null || currentSecs < newestSecs) {
                newestSecs = currentSecs;
            }
            $('.road-update-item').eq(index).find('.road-update-time').html(
                '<i class="fas fa-clock me-1"></i>' + esc(formatSecondsAgo(currentSecs))
            );
        });

        if (newestSecs != null) {
            $('#driverUpdatesLive').html(
                '<i class="fas fa-circle text-success me-1 live-pulse"></i>Newest data: ' +
                esc(formatSecondsAgo(newestSecs))
            );
        }
    }

    function loadRecentRoadUpdates() {
        return post('driver_recent_updates').done(function (res) {
            if (!res.success) return;
            if (res.map_config) {
                remeraMapConfig = res.map_config;
            }
            renderRecentRoadUpdates(res.recent_updates || []);
        });
    }

    function startRelativeTimeTicker() {
        stopRelativeTimeTicker();
        relativeTimeTicker = setInterval(function () {
            if (!$('#dashboard-section').is(':visible')) return;
            refreshRelativeTimesOnPage();
        }, 60000);
    }

    function stopRelativeTimeTicker() {
        if (relativeTimeTicker) {
            clearInterval(relativeTimeTicker);
            relativeTimeTicker = null;
        }
    }

    function startHomeLiveRefresh() {
        stopHomeLiveRefresh();
        startRelativeTimeTicker();
        homeRefreshTimer = setInterval(function () {
            if (!$('#dashboard-section').is(':visible')) return;
            loadRecentRoadUpdates();
            post('driver_segments_map').done(function (res) {
                if (res.success && homeMap) {
                    drawSegments(homeMap, res.segments, false, true, homeSegmentLayers);
                }
            });
        }, 30000);
    }

    function stopHomeLiveRefresh() {
        if (homeRefreshTimer) {
            clearInterval(homeRefreshTimer);
            homeRefreshTimer = null;
        }
        stopRelativeTimeTicker();
    }

    function startRouteLiveRefresh() {
        stopRouteLiveRefresh();
        routeRefreshTimer = setInterval(function () {
            loadRouteMap(true);
        }, 30000);
    }

    function stopRouteLiveRefresh() {
        if (routeRefreshTimer) {
            clearInterval(routeRefreshTimer);
            routeRefreshTimer = null;
        }
    }

    function shortRoadName(name) {
        if (!name) return '';
        return String(name).split('(')[0].trim();
    }

    function post(action, data, isFormData) {
        const opts = {
            url: API,
            type: 'POST',
            dataType: 'json',
            data: isFormData ? data : Object.assign({ action }, data || {})
        };
        if (isFormData) {
            data.append('action', action);
            opts.processData = false;
            opts.contentType = false;
        }
        return $.ajax(opts);
    }

    function statusClass(level) {
        return level || 'moderate';
    }

    function formatCoords(lat, lng) {
        if (lat == null || lng == null) return '—';
        return lat.toFixed(5) + ', ' + lng.toFixed(5);
    }

    function showGpsError(err) {
        let message = 'GPS unavailable — enable location permission';
        let help = 'Enable location and refresh. On HTTP, use localhost or HTTPS.';
        if (err && err.message && err.code === 0) {
            message = err.message;
            help = err.message;
        } else if (err && err.code === 1) {
            message = 'GPS unavailable — location permission denied';
            help = 'Your browser blocked location access for this site. Allow location in the browser site settings, then press Refresh GPS. You can also use the map-center fallback below.';
        } else if (err && err.code === 2) {
            message = 'GPS unavailable — position could not be determined';
            help = 'Try moving to an open area, turning on device location, then press Refresh GPS.';
        } else if (err && err.code === 3) {
            message = 'GPS unavailable — location request timed out';
            help = 'The location request took too long. Check your internet/device GPS and try Refresh GPS again.';
        }
        $('#driverHomeLocation').text(message);
        $('#driverOriginCoords').text(help);
        $('#driverGpsHelp').text(help);
    }

    function isGeolocationPolicyAllowed() {
        if (document.permissionsPolicy && typeof document.permissionsPolicy.allowsFeature === 'function') {
            return document.permissionsPolicy.allowsFeature('geolocation');
        }
        if (document.featurePolicy && typeof document.featurePolicy.allowsFeature === 'function') {
            return document.featurePolicy.allowsFeature('geolocation');
        }
        return true;
    }

    function isSecureLocationContext() {
        return window.isSecureContext === true
            || location.protocol === 'https:'
            || location.hostname === 'localhost'
            || location.hostname === '127.0.0.1';
    }

    function setGpsButtonsBusy(busy) {
        gpsBusy = busy;
        const $btns = $('#btnEnableGps, #btnRefreshGpsHome, #btnRefreshGpsRoute');
        $btns.prop('disabled', busy);
        if (busy) {
            $btns.each(function () {
                const $btn = $(this);
                if (!$btn.data('gps-label')) {
                    $btn.data('gps-label', $btn.html());
                }
                $btn.html('<i class="fas fa-spinner fa-spin me-1"></i>Locating…');
            });
        } else {
            $btns.each(function () {
                const $btn = $(this);
                const label = $btn.data('gps-label');
                if (label) {
                    $btn.html(label);
                }
            });
        }
    }

    function updateUserMarkers(lat, lng) {
        if (routeMap) {
            if (userMarker) {
                userMarker.setLatLng([lat, lng]);
            } else {
                userMarker = L.marker([lat, lng], { icon: createMapPinIcon('user'), zIndexOffset: 1100 }).addTo(routeMap);
            }
        }
        if (homeMap) {
            if (homeUserMarker) {
                homeUserMarker.setLatLng([lat, lng]);
            } else {
                homeUserMarker = L.marker([lat, lng], { icon: createMapPinIcon('user'), zIndexOffset: 1100 }).addTo(homeMap);
            }
            homeMap.setView([lat, lng], Math.max(homeMap.getZoom(), 15));
        }
    }

    function applyGpsPosition(position) {
        gps.lat = position.coords.latitude;
        gps.lng = position.coords.longitude;
        gps.label = 'Current GPS location';
        $('#driverOrigin').val('__gps__');
        $('#driverOriginLabel').val('My current location');
        $('#driverOriginCoords').text(formatCoords(gps.lat, gps.lng));
        $('#driverHomeLocation').text(formatCoords(gps.lat, gps.lng));
        setGpsStateReady();
        updateUserMarkers(gps.lat, gps.lng);
    }

    function setGpsStateReady() {
        $('#driverGpsHelp').text('Live GPS detected successfully. You can refresh anytime.');
    }

    function inspectLocationPermission() {
        if (!navigator.permissions || !navigator.permissions.query) {
            return;
        }
        navigator.permissions.query({ name: 'geolocation' }).then(function (result) {
            if (result.state === 'denied') {
                $('#driverGpsHelp').text('Location is blocked for this site. Allow it in browser settings, then press Refresh GPS.');
            } else if (result.state === 'prompt') {
                $('#driverGpsHelp').text('Press Refresh GPS and allow the browser location prompt.');
            }
            result.onchange = function () {
                if (result.state === 'granted') {
                    enableGps();
                } else if (result.state === 'denied') {
                    showGpsError({ code: 1 });
                }
            };
        }).catch(function () {
            // Ignore browsers without full Permissions API support.
        });
    }

    function initNavigation() {
        const titles = {
            dashboard: 'Driver Home',
            'route-map': 'Route Guidance',
            'road-status': 'Road Status',
            notifications: 'Notifications',
            'travel-history': 'Travel History',
            profile: 'My Profile'
        };

        function showSection(section) {
            $('.nav-link[data-section]').removeClass('active');
            $('.nav-link[data-section="' + section + '"]').addClass('active');
            $('.driver-section').hide();
            $('#' + section + '-section').show();
            $('#pageTitle').text(titles[section] || 'Driver Dashboard');
            onSectionShown(section);
        }

        $('.nav-link[data-section]').on('click', function (e) {
            e.preventDefault();
            showSection($(this).data('section'));
        });

        $('.driver-nav-jump').on('click', function (e) {
            e.preventDefault();
            showSection($(this).data('section'));
        });

        showSection('dashboard');
    }

    function onSectionShown(section) {
        if (section === 'dashboard') {
            loadDriverHome();
            startHomeLiveRefresh();
            setTimeout(function () {
                if (homeMap) {
                    homeMap.invalidateSize();
                    fitRemeraSector(homeMap);
                }
            }, 250);
        } else {
            stopHomeLiveRefresh();
        }
        if (section === 'route-map') {
            ensureRouteMap();
            loadRouteMap();
            loadDestinations('');
            startRouteLiveRefresh();
            setTimeout(function () {
                if (routeMap) {
                    routeMap.invalidateSize();
                    fitRemeraSector(routeMap);
                }
            }, 250);
        } else {
            stopRouteLiveRefresh();
        }
        if (section === 'road-status') {
            if (window.STRGSRoadStatus) {
                STRGSRoadStatus.load(true);
                STRGSRoadStatus.startRefresh();
            }
        } else if (window.STRGSRoadStatus) {
            STRGSRoadStatus.stopRefresh();
        }
        if (section === 'notifications') {
            loadAlerts('#driverAlertsList');
        } else if (section === 'travel-history') {
            loadTravelHistory();
        } else if (section === 'profile') {
            loadProfile();
        }
    }

    function enableGps(callback) {
        if (gpsBusy) {
            return;
        }

        if (!isSecureLocationContext()) {
            showGpsError({
                code: 0,
                message: 'GPS requires HTTPS or localhost. Open http://localhost/STRGS/dashboard.php instead of an IP address.'
            });
            $('#driverGpsHelp').text('Geolocation is blocked on non-secure pages. Use localhost or HTTPS, then press Refresh GPS.');
            return;
        }

        if (!isGeolocationPolicyAllowed()) {
            showGpsError({
                code: 0,
                message: 'GPS blocked by server Permissions-Policy header'
            });
            $('#driverGpsHelp').text('The site .htaccess was blocking geolocation. Reload the page after the fix (geolocation=(self)).');
            return;
        }

        if (!navigator.geolocation) {
            alert('GPS is not supported on this device.');
            return;
        }

        $('#driverHomeLocation').text('Requesting location…');
        $('#driverGpsHelp').text('Waiting for browser GPS response…');
        setGpsButtonsBusy(true);

        navigator.geolocation.getCurrentPosition(
            function (position) {
                console.log('STRGS GPS latitude:', position.coords.latitude);
                console.log('STRGS GPS longitude:', position.coords.longitude);
                applyGpsPosition(position);
                setGpsButtonsBusy(false);
                if (callback) {
                    callback(position);
                }
            },
            function (err) {
                setGpsButtonsBusy(false);
                showGpsError(err);
            },
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
        );
    }

    function openGoogleTrafficMap() {
        const lat = gps.lat != null ? gps.lat : -1.9536;
        const lng = gps.lng != null ? gps.lng : 30.1065;
        const url = 'https://www.google.com/maps/@' + lat + ',' + lng + ',15z/data=!5m1!1e1';
        window.open(url, '_blank', 'noopener');
    }

    function setManualCurrentLocation() {
        ensureRouteMap();
        if (!routeMap) {
            return;
        }
        const center = routeMap.getCenter();
        gps.lat = center.lat;
        gps.lng = center.lng;
        gps.label = 'Manual map-selected location';
        $('#driverOrigin').val('__gps__');
        $('#driverOriginLabel').val('Manual map-selected location');
        $('#driverOriginCoords').text(formatCoords(gps.lat, gps.lng));
        $('#driverHomeLocation').text(formatCoords(gps.lat, gps.lng) + ' (manual)');
        $('#driverGpsHelp').text('Using manual location from map center. You can still enable browser GPS later.');
        updateUserMarkers(gps.lat, gps.lng);
    }

    function ensureRouteMap() {
        if (routeMapInitialized || !document.getElementById('driverRouteMap') || !homeStreetLayer) {
            return;
        }

        routeStreetLayer = createStreetTileLayer();
        routeSatelliteLayer = createSatelliteTileLayer();
        routeSatelliteLabels = createSatelliteLabelsLayer();

        routeMap = L.map('driverRouteMap', {
            scrollWheelZoom: true,
            zoomControl: false
        });
        L.control.zoom({ position: 'bottomright' }).addTo(routeMap);
        applyRemeraMapSettings(routeMap, remeraMapConfig);
        setRouteBaseLayer('street');
        routeZoneLayer = drawRemeraZoneOverlay(routeMap, remeraMapConfig, routeZoneLayer);

        routeMap.on('click', function (e) {
            selectedDest = { lat: e.latlng.lat, lng: e.latlng.lng, name: 'Map selected point' };
            let $mapOpt = $('#driverDestination option[value="__map_selected__"]');
            if (!$mapOpt.length) {
                $mapOpt = $('<option value="__map_selected__">Map selected point</option>');
                $('#driverDestination').prepend($mapOpt);
            }
            $mapOpt.attr('data-lat', e.latlng.lat).attr('data-lng', e.latlng.lng);
            $('#driverDestination').val('__map_selected__');
            ensureDestMarker(e.latlng.lat, e.latlng.lng);
        });

        routeMapInitialized = true;
        if (gps.lat != null && gps.lng != null) {
            updateUserMarkers(gps.lat, gps.lng);
        }
        fitRemeraSector(routeMap);
    }

    function initMaps() {
        if (!document.getElementById('driverHomeMap')) {
            console.warn('STRGS: driver home map element not found');
            return;
        }

        homeStreetLayer = createStreetTileLayer();
        homeSatelliteLayer = createSatelliteTileLayer();
        homeSatelliteLabels = createSatelliteLabelsLayer();
        streetLayer = homeStreetLayer;
        satelliteLayer = homeSatelliteLayer;

        homeMap = L.map('driverHomeMap', { scrollWheelZoom: true, zoomControl: false });
        L.control.zoom({ position: 'bottomright' }).addTo(homeMap);
        applyRemeraMapSettings(homeMap, remeraMapConfig);
        setHomeBaseLayer('street');
        homeZoneLayer = drawRemeraZoneOverlay(homeMap, remeraMapConfig, homeZoneLayer);
        fitRemeraSector(homeMap);

        $(document).on('click', '[data-map-target="home"][data-map-layer]', function () {
            setHomeBaseLayer($(this).data('map-layer'));
        });
        $(document).on('click', '[data-map-target="route"][data-map-layer]', function () {
            setRouteBaseLayer($(this).data('map-layer'));
        });
    }

    function drawSegments(map, segments, fit, showLabels, layerStore, options) {
        const store = layerStore || segmentLayers;
        const routePlanning = options && options.routePlanning === true;
        store.forEach(function (l) {
            if (map.hasLayer(l)) {
                map.removeLayer(l);
            }
        });
        store.length = 0;
        const bounds = [];

        segments.forEach(function (seg) {
            const color = routePlanning ? '#94a3b8'
                : seg.status === 'blocked' || seg.congestion_level === 'severe' ? '#b91c1c'
                : seg.congestion_level === 'low' ? '#22c55e'
                : seg.congestion_level === 'moderate' ? '#f59e0b'
                : seg.congestion_level === 'high' ? '#f97316' : '#b91c1c';
            const line = L.polyline(
                [[seg.start_lat, seg.start_lng], [seg.end_lat, seg.end_lng]],
                {
                    color: color,
                    weight: routePlanning ? 4 : 7,
                    opacity: routePlanning ? 0.4 : 0.9
                }
            ).bindPopup(
                '<strong>' + esc(seg.name) + '</strong><br>' +
                esc(seg.status_label || 'Updated') + '<br>' +
                '<small>' + esc(seg.zone || 'Remera Sector') + '</small>'
            ).bindTooltip(shortRoadName(seg.name), { sticky: true, opacity: 0.95 });
            line.addTo(map);
            store.push(line);
            bounds.push([seg.start_lat, seg.start_lng], [seg.end_lat, seg.end_lng]);

            if (showLabels) {
                const midLat = (seg.start_lat + seg.end_lat) / 2;
                const midLng = (seg.start_lng + seg.end_lng) / 2;
                const labelClass = map === routeMap ? 'remera-road-label route-road-label' : 'remera-road-label home-road-label';
                const labelMarker = L.marker([midLat, midLng], {
                    icon: L.divIcon({
                        className: labelClass,
                        html: '<span>' + esc(shortRoadName(seg.name)) + '</span>'
                    }),
                    interactive: false
                });
                labelMarker.addTo(map);
                store.push(labelMarker);
            }
        });

        if (fit && bounds.length) {
            const cfg = getRemeraConfig();
            map.fitBounds(getRemeraBoundsLatLng(cfg), { padding: [16, 16] });
        }
    }

    function loadDriverHome() {
        post('driver_home').done(function (res) {
            if (!res.success) return;
            const s = res.traffic_summary || {};
            if (res.map_config) {
                remeraMapConfig = res.map_config;
                $('#driverMapZoneBadge').text(res.map_config.label || 'Remera Sector, Kigali');
                if (homeMap) {
                    applyRemeraMapSettings(homeMap, res.map_config);
                    homeZoneLayer = drawRemeraZoneOverlay(homeMap, res.map_config, homeZoneLayer);
                }
            }
            if (res.profile) {
                syncTopUserCard({
                    name: (res.profile.first_name || '') + ' ' + (res.profile.last_name || ''),
                    email: res.profile.email,
                    profile_picture: res.profile.profile_picture || null
                });
            }
            $('#statNormalRoads').text(s.normal ?? '—');
            $('#statCongested').text(s.congested ?? '—');
            $('#statBlocked').text(s.blocked ?? '—');
            $('#statRoadIssues').text(s.road_issues ?? '—');

            renderRecentRoadUpdates(res.recent_updates || []);

            const homeNotes = res.notifications || [];
            updateNotificationBadge(res.notification_count != null ? res.notification_count : homeNotes.length);
            if (!homeNotes.length) {
                $('#driverHomeNotifications').html('<p class="text-muted mb-0">No recent notifications.</p>');
            } else {
                $('#driverHomeNotifications').html(homeNotes.map(renderNotificationItem).join(''));
            }
        });

        post('driver_segments_map').done(function (res) {
            if (!res.success || !homeMap) return;
            if (res.map_config) {
                remeraMapConfig = res.map_config;
            }
            drawSegments(homeMap, res.segments, true, true, homeSegmentLayers);
            drawHomeLandmarks(homeMap, res.landmarks || []);
            setHomeBaseLayer(activeHomeBaseLayer);
        });
    }

    function loadRouteMap(silent) {
        post('driver_segments_map').done(function (res) {
            if (!res.success || !routeMap) return;
            if (res.map_config) {
                remeraMapConfig = res.map_config;
                $('#driverRouteMapBadge').text(res.map_config.label || 'Remera Sector, Kigali');
                if (!silent) {
                    applyRemeraMapSettings(routeMap, res.map_config);
                } else {
                    routeMap.setMaxBounds(getRemeraBoundsLatLng(res.map_config));
                }
            }
            drawSegments(routeMap, res.segments, !silent, false, routeSegmentLayers, { routePlanning: true });
            routeZoneLayer = drawRemeraZoneOverlay(routeMap, res.map_config || remeraMapConfig, routeZoneLayer);
            drawRouteLandmarks(routeMap, res.landmarks || []);
            setRouteBaseLayer(activeRouteBaseLayer);

            if (selectedRouteIndex !== null && lastRoutes[selectedRouteIndex]) {
                showAllRoutesPreview(lastRoutes, selectedRouteIndex);
            } else if (lastRoutes.length) {
                showAllRoutesPreview(lastRoutes, null);
            }
        });
        if (gps.lat) {
            updateUserMarkers(gps.lat, gps.lng);
        }
    }

    function populateLocationSelect($sel, results, keepFirstOptions) {
        const current = $sel.val();
        const preserved = [];
        if (keepFirstOptions) {
            $sel.find('option').each(function () {
                const val = $(this).val();
                if (val === '__gps__' || val === '__map_selected__') {
                    preserved.push($(this).clone());
                }
            });
        }
        $sel.empty();
        preserved.forEach(function ($opt) { $sel.append($opt); });
        results.forEach(function (r) {
            $sel.append(
                $('<option></option>')
                    .attr('value', r.name)
                    .attr('data-lat', r.lat)
                    .attr('data-lng', r.lng)
                    .text(r.name)
            );
        });
        if (current) {
            $sel.val(current);
        }
    }

    function populateDestinationSelect(results) {
        const $sel = $('#driverDestination');
        const current = $sel.val();
        const mapOpt = $sel.find('option[value="__map_selected__"]').detach();
        $sel.find('option:not(:first)').remove();
        window._driverDestCache = results;
        results.forEach(function (r) {
            $sel.append(
                $('<option></option>')
                    .attr('value', r.name)
                    .attr('data-lat', r.lat)
                    .attr('data-lng', r.lng)
                    .text(r.name)
            );
        });
        if (mapOpt.length) {
            $sel.prepend(mapOpt);
        }
        if (current) {
            $sel.val(current);
        }
        populateLocationSelect($('#driverOrigin'), results, true);
    }

    function loadDestinations() {
        post('driver_search_destinations', { query: '' }).done(function (res) {
            if (!res.success) return;
            populateDestinationSelect(res.results);
        });
    }

    function resolveOrigin() {
        const $opt = $('#driverOrigin option:selected');
        const val = ($opt.val() || '').trim();
        if (val === '__gps__' || val === '') {
            if (gps.lat != null && gps.lng != null) {
                return { lat: gps.lat, lng: gps.lng, label: gps.label || 'Current GPS location' };
            }
            return null;
        }
        const lat = parseFloat($opt.attr('data-lat'));
        const lng = parseFloat($opt.attr('data-lng'));
        if (!isNaN(lat) && !isNaN(lng)) {
            return { lat: lat, lng: lng, label: $opt.text() };
        }
        return null;
    }

    function resolveDestination() {
        const $opt = $('#driverDestination option:selected');
        const val = ($opt.val() || '').trim();
        if (!val) return null;
        const lat = parseFloat($opt.attr('data-lat'));
        const lng = parseFloat($opt.attr('data-lng'));
        if (!isNaN(lat) && !isNaN(lng)) {
            return { name: val === '__map_selected__' ? 'Map selected point' : val, lat: lat, lng: lng };
        }
        const cache = window._driverDestCache || [];
        const match = cache.find(function (r) { return r.name === val; });
        if (match) return match;
        if (selectedDest && (selectedDest.name === val || val === '__map_selected__')) return selectedDest;
        return null;
    }

    function fetchOsrmGeometry(origin, dest) {
        const coords = origin.lng + ',' + origin.lat + ';' + dest.lng + ',' + dest.lat;
        return $.ajax({
            url: 'https://router.project-osrm.org/route/v1/driving/' + coords,
            data: {
                overview: 'full',
                geometries: 'geojson',
                steps: false,
                alternatives: true
            },
            dataType: 'json',
            timeout: 12000
        });
    }

    function applyOsrmGeometryToRoutes(routes, osrmData) {
        if (!osrmData || osrmData.code !== 'Ok' || !osrmData.routes) {
            return routes;
        }
        let osrmIdx = 0;
        routes.forEach(function (route) {
            if (route.road_geometry && route.road_geometry.length >= 2) {
                return;
            }
            const osrmRoute = osrmData.routes[osrmIdx++];
            if (!osrmRoute) {
                return;
            }
            route.road_geometry = (osrmRoute.geometry.coordinates || []).map(function (c) {
                return { lat: c[1], lng: c[0] };
            });
            route.polyline = route.road_geometry;
            route.distance_km = Math.round((osrmRoute.distance / 1000) * 100) / 100;
            route.estimated_minutes = Math.ceil(osrmRoute.duration / 60);
        });
        return routes;
    }

    function routesNeedOsrmGeometry(routes) {
        return !routes.length || routes.some(function (r) {
            return !r.road_geometry || r.road_geometry.length < 2;
        });
    }

    function finishRouteGuidance(guidance) {
        lastRoutes = guidance.routes || [];
        lastGuidanceMeta = {
            origin: guidance.origin || null,
            destination: guidance.destination || null,
            traffic_updated_at: guidance.traffic_updated_at || null
        };
        selectedRouteIndex = null;

        function displayRoutes() {
            renderRouteOptions(lastRoutes, guidance.traffic_updated_at);
            showAllRoutesPreview(lastRoutes, null);
        }
        if (!routesNeedOsrmGeometry(lastRoutes)) {
            displayRoutes();
            return;
        }
        const origin = guidance.origin;
        const destination = guidance.destination;
        if (!origin || !destination) {
            displayRoutes();
            return;
        }
        fetchOsrmGeometry(origin, destination).done(function (osrmData) {
            lastRoutes = applyOsrmGeometryToRoutes(lastRoutes, osrmData);
            displayRoutes();
        }).fail(function () {
            displayRoutes();
        });
    }

    function requestRoute() {
        const origin = resolveOrigin();
        if (!origin) {
            alert('Please set your starting location using GPS, the list above, or the map center button.');
            enableGps();
            return;
        }
        const dest = resolveDestination();
        if (!dest) {
            alert('Please select a destination from the list or click on the map.');
            return;
        }

        updateUserMarkers(origin.lat, origin.lng);
        ensureDestMarker(dest.lat, dest.lng);

        $('#btnRequestRoute').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Calculating…');

        post('driver_request_route', {
            origin_lat: origin.lat,
            origin_lng: origin.lng,
            origin_label: origin.label,
            dest_lat: dest.lat,
            dest_lng: dest.lng,
            destination_label: dest.name
        }).always(function () {
            $('#btnRequestRoute').prop('disabled', false).html('<i class="fas fa-route me-2"></i>Request Route Guidance');
        }).done(function (res) {
            if (!res.success) {
                alert(res.message || 'Route request failed');
                return;
            }
            finishRouteGuidance(res.guidance || {});
        });
    }

    function renderRouteOptions(routes, trafficUpdatedAt) {
        const $wrap = $('#driverRouteResults').show();
        const $list = $('#driverRouteList').empty();
        if (!routes.length) {
            $list.append('<p class="text-muted p-3 mb-0">No route options available.</p>');
            return;
        }

        const countNote = routes.length < 3
            ? routes.length + ' route option' + (routes.length === 1 ? '' : 's') + ' found for this trip.'
            : 'Three route options — preview each one, then choose the best for you.';
        const liveNote = '<p class="text-muted small px-3 pt-3 mb-2"><i class="fas fa-route me-1"></i>' + countNote +
            (trafficUpdatedAt ? ' Uses live Remera road data.' : '') + '</p>';
        $list.append(liveNote);

        const previewColors = ['#6366f1', '#0ea5e9', '#14b8a6'];
        routes.forEach(function (r, i) {
            const optionNum = r.option_number || (i + 1);
            const summary = r.summary || 'Compare distance, time, and traffic before you go.';
            $list.append(
                '<div class="route-option" data-idx="' + i + '">' +
                '<div class="route-option-head">' +
                '<span class="route-option-badge" style="background:' + previewColors[i % previewColors.length] + '">' + optionNum + '</span>' +
                '<div class="route-option-title">' +
                '<div class="fw-semibold">' + esc(r.name || ('Route ' + optionNum)) + '</div>' +
                '<div class="small text-muted">' + r.distance_km + ' km · ~' + r.estimated_minutes + ' min</div>' +
                '</div></div>' +
                '<p class="route-option-summary small text-muted mb-2">' + esc(summary) + '</p>' +
                '<span class="status-pill ' + statusClass(r.congestion_level) + '">' + esc(r.congestion_level) + ' traffic</span>' +
                '<div class="route-option-actions mt-2">' +
                '<button type="button" class="btn btn-sm btn-outline-secondary btn-preview-route" data-idx="' + i + '">' +
                '<i class="fas fa-eye me-1"></i>Preview on map</button>' +
                '<button type="button" class="btn btn-sm btn-dark btn-choose-route" data-idx="' + i + '">' +
                '<i class="fas fa-check me-1"></i>Choose this route</button>' +
                '</div></div>'
            );
        });

        $list.off('click.routeOptions').on('click.routeOptions', '.btn-preview-route', function (e) {
            e.stopPropagation();
            previewRoute($(this).data('idx'));
        }).on('click.routeOptions', '.btn-choose-route', function (e) {
            e.stopPropagation();
            chooseRoute($(this).data('idx'));
        }).on('click.routeOptions', '.route-option', function () {
            previewRoute($(this).data('idx'));
        });
    }

    function previewRoute(idx) {
        idx = Number(idx);
        if (!lastRoutes[idx]) {
            return;
        }
        selectedRouteIndex = idx;
        $('.route-option').removeClass('active previewing chosen');
        $('.route-option[data-idx="' + idx + '"]').addClass('active previewing');
        showAllRoutesPreview(lastRoutes, idx);
    }

    function chooseRoute(idx) {
        idx = Number(idx);
        const route = lastRoutes[idx];
        const meta = lastGuidanceMeta;
        if (!route || !meta || !meta.origin || !meta.destination) {
            alert('Please request route guidance again before choosing a route.');
            return;
        }

        const origin = resolveOrigin();
        const dest = resolveDestination();
        const $btn = $('.btn-choose-route[data-idx="' + idx + '"]');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Saving…');

        post('driver_confirm_route', {
            origin_lat: meta.origin.lat,
            origin_lng: meta.origin.lng,
            origin_label: origin ? origin.label : 'Current location',
            dest_lat: meta.destination.lat,
            dest_lng: meta.destination.lng,
            destination_label: dest ? dest.name : (meta.destination.label || 'Destination'),
            route_index: idx
        }).always(function () {
            $btn.prop('disabled', false).html('<i class="fas fa-check me-1"></i>Choose this route');
        }).done(function (res) {
            if (!res.success) {
                alert(res.message || 'Could not save your route choice.');
                return;
            }
            selectedRouteIndex = idx;
            $('.route-option').removeClass('active previewing chosen');
            $('.route-option[data-idx="' + idx + '"]').addClass('active chosen');
            showAllRoutesPreview(lastRoutes, idx);
            alert(res.message || 'Route saved. Drive safely.');
        });
    }

    function getRouteLatLngs(route) {
        if (route.road_geometry && route.road_geometry.length >= 2) {
            return route.road_geometry.map(function (p) { return [p.lat, p.lng]; });
        }
        const roadLines = route.road_lines || [];
        if (roadLines.length) {
            return roadLines.reduce(function (acc, segmentLine) {
                return acc.concat(segmentLine.map(function (p) { return [p.lat, p.lng]; }));
            }, []);
        }
        if (route.polyline && route.polyline.length >= 2) {
            return route.polyline.map(function (p) { return [p.lat, p.lng]; });
        }
        if (route.segments && route.segments.length) {
            return route.segments.reduce(function (acc, seg) {
                return acc.concat([[seg.start_lat, seg.start_lng], [seg.end_lat, seg.end_lng]]);
            }, []);
        }
        return [];
    }

    function showAllRoutesPreview(routes, highlightIdx) {
        clearRouteHighlight();
        if (!routeMap || !routes.length) {
            return;
        }

        const previewColors = ['#6366f1', '#0ea5e9', '#14b8a6'];
        const bounds = [];

        routes.forEach(function (route, i) {
            const latlngs = getRouteLatLngs(route);
            if (latlngs.length < 2) {
                return;
            }
            const isActive = highlightIdx === i;
            const line = L.polyline(latlngs, {
                color: isActive ? '#1d4ed8' : (previewColors[i % previewColors.length]),
                weight: isActive ? 8 : 5,
                opacity: isActive ? 0.95 : 0.45,
                lineJoin: 'round',
                lineCap: 'round'
            }).addTo(routeMap);
            routeLayers.push(line);
            latlngs.forEach(function (ll) { bounds.push(ll); });
        });

        if (bounds.length) {
            routeMap.fitBounds(bounds, { padding: [40, 40] });
        }
    }

    function clearRouteHighlight() {
        if (!routeMap) return;
        routeLayers.forEach(function (l) { routeMap.removeLayer(l); });
        routeLayers = [];
    }

    function showRouteOnMap(route) {
        const idx = lastRoutes.indexOf(route);
        showAllRoutesPreview(lastRoutes, idx >= 0 ? idx : null);
    }

    function loadRoadStatus() {
        if (window.STRGSRoadStatus) {
            return STRGSRoadStatus.load();
        }
    }

    function renderRoadTable(filter) {
        if (window.STRGSRoadStatus) {
            STRGSRoadStatus.renderTable(filter);
        }
    }

    function notificationIcon(item) {
        if (item.kind === 'road_update') {
            return 'fa-road';
        }
        if (item.type === 'road_blockage') {
            return 'fa-ban';
        }
        if (item.type === 'incident') {
            return 'fa-car-burst';
        }
        return 'fa-triangle-exclamation';
    }

    function renderNotificationItem(item) {
        const title = item.title || 'Notification';
        const time = item.time_label || formatRoadUpdateTime(item.alert_time);
        const kindClass = item.kind === 'road_update' ? 'notification-road-update' : 'notification-alert';
        const unreadClass = item.is_read ? '' : ' notification-unread';
        return '<div class="notification-item' + unreadClass + ' ' + kindClass + ' alert-item ' + esc(item.severity || 'info') + '" data-notification-id="' + esc(item.id || '') + '">' +
            '<div class="d-flex justify-content-between align-items-start gap-2">' +
            '<div class="notification-title-wrap"><i class="fas ' + notificationIcon(item) + ' me-2"></i><strong>' + esc(title) + '</strong>' +
            (!item.is_read ? '<span class="notification-new-badge">New</span>' : '') +
            '</div>' +
            '<small class="text-muted flex-shrink-0">' + esc(time) + '</small></div>' +
            '<div class="notification-message">' + esc(item.message) + '</div>' +
            (item.segment_name ? '<small class="text-muted">' + esc(item.segment_name) + '</small>' : '') +
            '</div>';
    }

    function updateNotificationBadge(count) {
        lastNotificationCount = count || 0;
        const $badge = $('#headerNotifyBadge');
        if (!$badge.length) {
            return;
        }
        if (lastNotificationCount > 0) {
            $badge.text(lastNotificationCount > 99 ? '99+' : String(lastNotificationCount)).show();
        } else {
            $badge.hide();
        }
    }

    function loadNotificationCount() {
        post('driver_alerts', { limit: 1, offset: 0 }).done(function (res) {
            if (res.success) {
                const unread = res.unread_count != null ? res.unread_count : res.count || 0;
                updateNotificationBadge(unread);
            }
        });
    }

    function startNotificationPolling() {
        stopNotificationPolling();
        loadNotificationCount();
        notificationPollTimer = setInterval(loadNotificationCount, 60000);
    }

    function stopNotificationPolling() {
        if (notificationPollTimer) {
            clearInterval(notificationPollTimer);
            notificationPollTimer = null;
        }
    }

    function markNotificationsRead(items) {
        const ids = (items || []).map(function (item) { return item.id; }).filter(Boolean);
        if (!ids.length) {
            return;
        }
        post('driver_mark_notifications_read', { notification_ids: JSON.stringify(ids) }).done(function (res) {
            if (!res.success) {
                return;
            }
            updateNotificationBadge(res.unread_count != null ? res.unread_count : 0);
            $('#driverAlertsList .notification-item').removeClass('notification-unread').find('.notification-new-badge').remove();
        });
    }

    function loadAlerts(target, forceRefresh) {
        const selector = target || '#driverAlertsList';
        const $el = $(selector);
        const $btn = $('#btnRefreshAlerts');
        if (forceRefresh) {
            $btn.prop('disabled', true);
            $btn.find('i').addClass('fa-spin');
        }
        $el.html('<p class="text-muted mb-0"><i class="fas fa-spinner fa-spin me-2"></i>Loading notifications…</p>');

        post('driver_alerts', { limit: 50, offset: 0 }).done(function (res) {
            if (!res.success) {
                $el.html('<p class="text-muted mb-0">Could not load notifications.</p>');
                return;
            }
            const items = res.notifications || res.alerts || [];
            const unread = res.unread_count != null ? res.unread_count : items.filter(function (i) { return !i.is_read; }).length;
            updateNotificationBadge(unread);
            if (!items.length) {
                $el.html('<p class="text-muted mb-0">No notifications yet. Road updates from traffic police will appear here.</p>');
            } else {
                $el.html(items.map(renderNotificationItem).join(''));
                markNotificationsRead(items);
            }
            $('#alertsLastRefreshed').text('Updated ' + new Date().toLocaleTimeString());
        }).fail(function () {
            $el.html('<p class="text-muted mb-0">Could not load notifications. Check your connection and try Refresh.</p>');
        }).always(function () {
            $btn.prop('disabled', false);
            $btn.find('i').removeClass('fa-spin');
        });
    }

    function renderHistoryPagination(pagination) {
        const $wrap = $('#driverHistoryPagination').empty();
        if (!pagination) {
            $('#historyPageSummary').text('');
            return;
        }

        const totalLabel = pagination.total + ' trip' + (pagination.total === 1 ? '' : 's');
        if (pagination.total_pages <= 1) {
            $('#historyPageSummary').text(totalLabel);
            return;
        }

        $('#historyPageSummary').text('Page ' + pagination.page + ' of ' + pagination.total_pages + ' · ' + totalLabel);

        const $nav = $('<nav aria-label="Travel history pages"><ul class="pagination pagination-sm mb-0 justify-content-center flex-wrap"></ul></nav>');
        const $ul = $nav.find('ul');

        function addItem(label, page, disabled, active) {
            $ul.append(
                '<li class="page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '') + '">' +
                '<button type="button" class="page-link" data-page="' + page + '"' + (disabled ? ' disabled' : '') + '>' + label + '</button></li>'
            );
        }

        addItem('Prev', pagination.page - 1, pagination.page <= 1, false);

        const start = Math.max(1, pagination.page - 2);
        const end = Math.min(pagination.total_pages, pagination.page + 2);
        for (let p = start; p <= end; p++) {
            addItem(String(p), p, false, p === pagination.page);
        }

        addItem('Next', pagination.page + 1, pagination.page >= pagination.total_pages, false);
        $wrap.append($nav);

        $wrap.off('click.historyPage').on('click.historyPage', '.page-link', function () {
            const page = Number($(this).data('page'));
            if (!page || page === historyPage) {
                return;
            }
            loadTravelHistory(page);
        });
    }

    function loadTravelHistory(page) {
        historyPage = Math.max(1, page || historyPage || 1);
        $('#driverHistoryList').html('<p class="text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Loading travel history…</p>');

        post('driver_travel_history', { page: historyPage, per_page: historyPerPage }).done(function (res) {
            if (!res.success) {
                $('#driverHistoryList').html('<p class="text-muted">Could not load travel history.</p>');
                return;
            }
            if (!res.history.length) {
                $('#driverHistoryList').html('<p class="text-muted">No route requests yet. Use Route Guidance to plan a trip.</p>');
                renderHistoryPagination(res.pagination || { total: 0, page: 1, total_pages: 1 });
                return;
            }
            let html = '<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr>' +
                '<th>Date</th><th>From</th><th>To</th><th>Route</th><th>Distance</th><th>ETA</th><th>Traffic</th>' +
                '</tr></thead><tbody>';
            res.history.forEach(function (h) {
                html += '<tr><td>' + esc(h.created_at) + '</td><td>' + esc(h.origin_label || 'GPS') + '</td><td>' +
                    esc(h.destination_label) + '</td><td>' + esc(h.route_name) + '</td><td>' + h.distance_km + ' km</td><td>' +
                    h.estimated_minutes + ' min</td><td><span class="status-pill ' + statusClass(h.congestion_level) + '">' +
                    esc(h.congestion_level || '—') + '</span></td></tr>';
            });
            html += '</tbody></table></div>';
            $('#driverHistoryList').html(html);
            renderHistoryPagination(res.pagination);
        }).fail(function () {
            $('#driverHistoryList').html('<p class="text-muted">Could not load travel history. Please try again.</p>');
        });
    }

    function loadProfile() {
        post('driver_profile_get').done(function (res) {
            if (!res.success) return;
            const p = res.profile;
            $('#profileFirstName').val(p.first_name);
            $('#profileLastName').val(p.last_name);
            $('#profileEmail').val(p.email);
            $('#profilePhone').val(p.phone_number);
            $('#profileLicense').val(p.license_id || '—');
            if (p.profile_picture) {
                $('#profileAvatarPreview').html('<img src="' + esc(p.profile_picture) + '" alt="Profile">');
            }
            syncTopUserCard({
                name: (p.first_name || '') + ' ' + (p.last_name || ''),
                email: p.email,
                profile_picture: p.profile_picture || null
            });
        });
    }

    function syncTopUserCard(data) {
        if (data.name) {
            $('#topUserName').text(data.name.trim());
        }
        if (data.email) {
            $('#topUserEmail').text(data.email);
        }
        if (data.profile_picture) {
            $('#topUserAvatar').html('<img src="' + esc(data.profile_picture) + '" alt="User avatar">');
        } else if (data.name) {
            $('#topUserAvatar').text(data.name.trim().charAt(0).toUpperCase());
        }
    }

    function renderFeed(selector, items, tpl) {
        const $el = $(selector);
        if (!items || !items.length) {
            $el.html('<p class="text-muted mb-0">No items to show.</p>');
            return;
        }
        $el.html(items.map(tpl).join(''));
    }

    function esc(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }

    function bindEvents() {
        $(document).on('click', '#btnEnableGps, #btnRefreshGpsHome, #btnRefreshGpsRoute', function (e) {
            e.preventDefault();
            enableGps();
        });
        $('#btnOpenGoogleTraffic, #btnOpenGoogleTrafficRoute').on('click', openGoogleTrafficMap);
        $('#btnSetManualLocation').on('click', setManualCurrentLocation);
        $('#btnFitRemeraRoute').on('click', function () {
            ensureRouteMap();
            fitRemeraSector(routeMap);
        });
        $('#btnFitRemeraHome').on('click', function () {
            fitRemeraSector(homeMap);
        });
        $('#btnFocusGpsRoute').on('click', function () {
            ensureRouteMap();
            if (gps.lat != null && gps.lng != null) {
                routeMap.setView([gps.lat, gps.lng], Math.max(routeMap.getZoom(), 16));
                updateUserMarkers(gps.lat, gps.lng);
            } else {
                enableGps(function () {
                    if (routeMap && gps.lat != null) {
                        routeMap.setView([gps.lat, gps.lng], 16);
                    }
                });
            }
        });
        $('#btnFocusGpsHomeMap').on('click', function () {
            if (gps.lat != null && gps.lng != null) {
                homeMap.setView([gps.lat, gps.lng], Math.max(homeMap.getZoom(), 16));
                updateUserMarkers(gps.lat, gps.lng);
            } else {
                enableGps(function () {
                    if (homeMap && gps.lat != null) {
                        homeMap.setView([gps.lat, gps.lng], 16);
                    }
                });
            }
        });
        $('#btnRequestRoute').on('click', requestRoute);
        $('#btnRefreshAlerts').on('click', function () {
            loadAlerts('#driverAlertsList', true);
        });
        $('#btnHeaderNotifications').on('click', function () {
            $('.nav-link[data-section="notifications"]').trigger('click');
        });
        $('#driverOrigin').on('change', function () {
            const origin = resolveOrigin();
            if (!origin) {
                return;
            }
            if ($('#driverOrigin').val() !== '__gps__') {
                gps.lat = origin.lat;
                gps.lng = origin.lng;
                gps.label = origin.label;
                $('#driverOriginLabel').val(origin.label);
                $('#driverOriginCoords').text(formatCoords(origin.lat, origin.lng));
            }
            updateUserMarkers(origin.lat, origin.lng);
            if (routeMap) {
                routeMap.setView([origin.lat, origin.lng], Math.max(routeMap.getZoom(), 15));
            }
        });
        $('#driverDestination').on('change', function () {
            const dest = resolveDestination();
            if (dest) {
                selectedDest = dest;
                ensureDestMarker(dest.lat, dest.lng);
            }
        });

        $('#driverProfileForm').on('submit', function (e) {
            e.preventDefault();
            post('driver_profile_update', {
                first_name: $('#profileFirstName').val(),
                last_name: $('#profileLastName').val(),
                email: $('#profileEmail').val(),
                phone_number: $('#profilePhone').val()
            }).done(function (res) {
                $('#profileFormMsg').html('<div class="alert alert-' + (res.success ? 'success' : 'danger') + ' py-2">' + esc(res.message) + '</div>');
                if (res.success) {
                    syncTopUserCard({
                        name: $('#profileFirstName').val() + ' ' + $('#profileLastName').val(),
                        email: $('#profileEmail').val()
                    });
                }
            });
        });

        $('#driverPasswordForm').on('submit', function (e) {
            e.preventDefault();
            const fd = $(this).serialize() + '&action=change_password';
            $.post(API, fd, function (res) {
                $('#passwordFormMsg').html('<div class="alert alert-' + (res.success ? 'success' : 'danger') + ' py-2">' + esc(res.message || (res.success ? 'Password updated' : 'Failed')) + '</div>');
                if (res.success) $('#driverPasswordForm')[0].reset();
            }, 'json');
        });

        $('#btnChooseAvatar').on('click', function () { $('#profilePictureInput').click(); });
        $('#profilePictureInput').on('change', function () {
            const fd = new FormData();
            fd.append('profile_picture', this.files[0]);
            post('driver_upload_avatar', fd, true).done(function (res) {
                if (res.success && res.profile_picture) {
                    $('#profileAvatarPreview').html('<img src="' + esc(res.profile_picture) + '" alt="Profile">');
                    syncTopUserCard({ profile_picture: res.profile_picture });
                } else alert(res.message || 'Upload failed');
            });
        });

        $('#driverRoadFilters [data-filter]').on('click', function () {
            currentRoadFilter = $(this).data('filter');
            $('#driverRoadFilters [data-filter]').removeClass('active');
            $(this).addClass('active');
            if (window.STRGSRoadStatus) {
                STRGSRoadStatus.renderTable(currentRoadFilter);
            }
        });

        $('.stat-clickable[data-road-filter]').on('click', function () {
            const filter = $(this).data('road-filter');
            $('.nav-link[data-section="road-status"]').trigger('click');
            setTimeout(function () {
                $('#driverRoadFilters [data-filter="' + filter + '"]').trigger('click');
            }, 200);
        });

        if (window.innerWidth <= 768) {
            $('#sidebarToggle').on('click', function () {
                $('#sidebar').toggleClass('mobile-open');
            });
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
        try {
            initMaps();
        } catch (err) {
            console.error('STRGS map init failed:', err);
            $('#driverGpsHelp').text('Map failed to load, but Refresh GPS can still work.');
        }
        initNavigation();
        inspectLocationPermission();
        tryInitialGps();
        loadDestinations('');
        if ($('#btnHeaderNotifications').length) {
            startNotificationPolling();
        }
    });

    function tryInitialGps() {
        if (!isSecureLocationContext()) {
            $('#driverHomeLocation').text('Use localhost or HTTPS for GPS');
            $('#driverGpsHelp').text('Open http://localhost/STRGS/dashboard.php — geolocation is blocked on plain HTTP IP addresses.');
            return;
        }
        if (!navigator.permissions || !navigator.permissions.query) {
            enableGps();
            return;
        }
        navigator.permissions.query({ name: 'geolocation' }).then(function (result) {
            if (result.state === 'granted' || result.state === 'prompt') {
                enableGps();
            } else {
                $('#driverHomeLocation').text('Press Refresh GPS after allowing location');
            }
        }).catch(function () {
            enableGps();
        });
    }
})(jQuery);
