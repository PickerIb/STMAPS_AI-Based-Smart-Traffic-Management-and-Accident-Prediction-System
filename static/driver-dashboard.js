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
            setTimeout(function () {
                if (routeMap) {
                    routeMap.invalidateSize();
                    fitRemeraSector(routeMap);
                }
            }, 250);
        } else if (section === 'road-status') {
            loadRoadStatus();
        } else if (section === 'notifications') {
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
            $('#driverDestination').val(selectedDest.name);
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

    function drawSegments(map, segments, fit, showLabels, layerStore) {
        const store = layerStore || segmentLayers;
        store.forEach(function (l) {
            if (map.hasLayer(l)) {
                map.removeLayer(l);
            }
        });
        store.length = 0;
        const bounds = [];

        segments.forEach(function (seg) {
            const color = seg.status === 'blocked' ? '#1f2937'
                : seg.congestion_level === 'low' ? '#22c55e'
                : seg.congestion_level === 'moderate' ? '#f59e0b'
                : seg.congestion_level === 'high' ? '#f97316' : '#dc2626';
            const line = L.polyline(
                [[seg.start_lat, seg.start_lng], [seg.end_lat, seg.end_lng]],
                { color: color, weight: 7, opacity: 0.9 }
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

            renderFeed('#driverHomeNotifications', res.notifications, function (a) {
                const typeLabel = a.type === 'road_blockage' ? 'Road closure' : a.type === 'incident' ? 'Accident' : 'Congestion';
                return '<div class="alert-item ' + esc(a.severity || 'info') + '"><div class="d-flex justify-content-between">' +
                    '<strong>' + typeLabel + '</strong><small>' + esc(formatRoadUpdateTime(a.alert_time)) + '</small></div>' +
                    '<div>' + esc(a.message) + '</div>' +
                    (a.segment_name ? '<small class="text-muted">' + esc(a.segment_name) + '</small>' : '') + '</div>';
            });
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

    function loadRouteMap() {
        post('driver_segments_map').done(function (res) {
            if (!res.success || !routeMap) return;
            if (res.map_config) {
                remeraMapConfig = res.map_config;
                $('#driverRouteMapBadge').text(res.map_config.label || 'Remera Sector, Kigali');
                applyRemeraMapSettings(routeMap, res.map_config);
            }
            drawSegments(routeMap, res.segments, true, true, routeSegmentLayers);
            routeZoneLayer = drawRemeraZoneOverlay(routeMap, res.map_config || remeraMapConfig, routeZoneLayer);
            drawRouteLandmarks(routeMap, res.landmarks || []);
            setRouteBaseLayer(activeRouteBaseLayer);
        });
        if (gps.lat) {
            updateUserMarkers(gps.lat, gps.lng);
        }
    }

    function loadDestinations(q) {
        post('driver_search_destinations', { query: q }).done(function (res) {
            if (!res.success) return;
            const $dl = $('#driverDestList');
            $dl.empty();
            res.results.forEach(function (r) {
                $dl.append('<option value="' + esc(r.name) + '">');
            });
            window._driverDestCache = res.results;
        });
    }

    function resolveDestination() {
        const val = $('#driverDestination').val().trim();
        const cache = window._driverDestCache || [];
        const match = cache.find(function (r) { return r.name === val; });
        if (match) return match;
        if (selectedDest && selectedDest.name === val) return selectedDest;
        return null;
    }

    function requestRoute() {
        if (!gps.lat || !gps.lng) {
            alert('Please enable GPS first to use your current location as the starting point.');
            enableGps();
            return;
        }
        const dest = resolveDestination();
        if (!dest) {
            alert('Please select a valid destination from the search list or click on the map.');
            return;
        }

        $('#btnRequestRoute').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Calculating…');

        post('driver_request_route', {
            origin_lat: gps.lat,
            origin_lng: gps.lng,
            origin_label: gps.label,
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
            lastRoutes = res.guidance.routes || [];
            renderRouteOptions(lastRoutes);
            if (lastRoutes[0]) showRouteOnMap(lastRoutes[0]);
        });
    }

    function renderRouteOptions(routes) {
        const $wrap = $('#driverRouteResults').show();
        const $list = $('#driverRouteList').empty();
        routes.forEach(function (r, i) {
            $list.append(
                '<div class="route-option' + (i === 0 ? ' active' : '') + '" data-idx="' + i + '">' +
                '<div class="fw-semibold">' + esc(r.name) + '</div>' +
                '<div class="small text-muted">' + r.distance_km + ' km · ~' + r.estimated_minutes + ' min</div>' +
                '<span class="status-pill ' + statusClass(r.congestion_level) + '">' + esc(r.congestion_level) + ' traffic</span></div>'
            );
        });
        $('.route-option').on('click', function () {
            $('.route-option').removeClass('active');
            $(this).addClass('active');
            showRouteOnMap(lastRoutes[$(this).data('idx')]);
        });
    }

    function showRouteOnMap(route) {
        routeLayers.forEach(function (l) { routeMap.removeLayer(l); });
        routeLayers = [];
        if (!route || !route.polyline) return;
        const latlngs = route.polyline.map(function (p) { return [p.lat, p.lng]; });
        const line = L.polyline(latlngs, { color: '#2563eb', weight: 5, opacity: 0.9 }).addTo(routeMap);
        routeLayers.push(line);
        routeMap.fitBounds(line.getBounds(), { padding: [40, 40] });
    }

    function loadRoadStatus() {
        post('driver_road_status').done(function (res) {
            if (!res.success) return;
            latestRoadStatus = res;
            const s = res.summary;
            $('#driverRoadSummary').html(
                '<div class="road-summary-grid">' +
                '<div class="road-summary-item normal">' +
                '<div class="road-summary-icon"><i class="fas fa-road-circle-check"></i></div>' +
                '<div class="road-summary-count">' + s.normal + '</div>' +
                '<div class="road-summary-label">Normal</div></div>' +
                '<div class="road-summary-item congested">' +
                '<div class="road-summary-icon"><i class="fas fa-traffic-light"></i></div>' +
                '<div class="road-summary-count">' + s.congested + '</div>' +
                '<div class="road-summary-label">Congested</div></div>' +
                '<div class="road-summary-item blocked">' +
                '<div class="road-summary-icon"><i class="fas fa-ban"></i></div>' +
                '<div class="road-summary-count">' + s.blocked + '</div>' +
                '<div class="road-summary-label">Blocked</div></div>' +
                '<div class="road-summary-item issues">' +
                '<div class="road-summary-icon"><i class="fas fa-screwdriver-wrench"></i></div>' +
                '<div class="road-summary-count">' + s.road_issues + '</div>' +
                '<div class="road-summary-label">Not Working</div></div></div>'
            );

            renderRoadTable(currentRoadFilter);
            renderFeed('#driverRoadIssueList', res.issue_roads, function (seg) {
                return '<div class="driver-feed-item"><strong>' + esc(seg.name) + '</strong> — <span class="status-pill ' +
                    statusClass(seg.congestion_level) + '">' + esc(seg.status_label) + '</span><br><small class="text-muted">' +
                    esc(seg.reason || 'No reason provided') + '</small></div>';
            });

            renderFeed('#driverAccidentList', res.accidents, function (a) {
                return '<div class="driver-feed-item"><strong>' + esc(a.segment_name || 'Unknown road') + '</strong> — ' +
                    esc(a.severity) + '<br><small>' + esc(a.cause) + ' · ' + esc(a.accident_time) + '</small></div>';
            });
        });
    }

    function getFilteredRoads(filter) {
        if (!latestRoadStatus) return [];
        if (filter === 'normal') return latestRoadStatus.normal_roads || [];
        if (filter === 'congested') return latestRoadStatus.congested_roads || [];
        if (filter === 'blocked') return latestRoadStatus.blocked_roads || [];
        if (filter === 'issues') return latestRoadStatus.issue_roads || [];
        return latestRoadStatus.segments || [];
    }

    function renderRoadTable(filter) {
        const roads = getFilteredRoads(filter);
        const $tb = $('#driverRoadTable tbody').empty();
        if (!roads.length) {
            $tb.append('<tr><td colspan="6" class="text-center text-muted">No roads found for this filter.</td></tr>');
            return;
        }
        roads.forEach(function (seg) {
            $tb.append('<tr><td>' + esc(seg.name) + '</td><td><span class="status-pill ' +
                statusClass(seg.congestion_level) + '">' + esc(seg.status_label) + '</span></td><td>' +
                (seg.avg_speed_kmh != null ? seg.avg_speed_kmh + ' km/h' : '—') + '</td><td>' +
                (seg.vehicle_count != null ? seg.vehicle_count : '—') + '</td><td>' +
                esc(seg.weather || '—') + '</td><td>' + esc(seg.reason || '—') + '</td></tr>');
        });
    }

    function loadAlerts(target) {
        post('driver_alerts').done(function (res) {
            if (!res.success) return;
            renderFeed(target || '#driverAlertsList', res.alerts, function (a) {
                const typeLabel = a.type === 'road_blockage' ? 'Road closure' : a.type === 'incident' ? 'Accident' : 'Congestion';
                return '<div class="alert-item ' + esc(a.severity || 'info') + '"><div class="d-flex justify-content-between">' +
                    '<strong>' + typeLabel + '</strong><small>' + esc(a.alert_time) + '</small></div>' +
                    '<div>' + esc(a.message) + '</div>' +
                    (a.segment_name ? '<small class="text-muted">' + esc(a.segment_name) + '</small>' : '') + '</div>';
            });
        });
    }

    function loadTravelHistory() {
        post('driver_travel_history').done(function (res) {
            if (!res.success) return;
            if (!res.history.length) {
                $('#driverHistoryList').html('<p class="text-muted">No route requests yet. Use Route Guidance to plan a trip.</p>');
                return;
            }
            let html = '<div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>From</th><th>To</th><th>Route</th><th>Distance</th><th>ETA</th></tr></thead><tbody>';
            res.history.forEach(function (h) {
                html += '<tr><td>' + esc(h.created_at) + '</td><td>' + esc(h.origin_label || 'GPS') + '</td><td>' +
                    esc(h.destination_label) + '</td><td>' + esc(h.route_name) + '</td><td>' + h.distance_km + ' km</td><td>' +
                    h.estimated_minutes + ' min</td></tr>';
            });
            html += '</tbody></table></div>';
            $('#driverHistoryList').html(html);
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
        $('#btnRefreshAlerts').on('click', function () { loadAlerts(); });
        $('#driverDestination').on('input', function () {
            loadDestinations($(this).val());
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
            renderRoadTable(currentRoadFilter);
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
