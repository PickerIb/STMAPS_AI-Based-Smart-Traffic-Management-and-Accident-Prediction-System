/* global Chart */
(function ($) {
    'use strict';

    const API = 'index.php';
    const SECTION_TITLES = {
        dashboard: 'Admin Home',
        users: 'User Management',
        'traffic-monitoring': 'Traffic Monitoring',
        system: 'System Management',
        notifications: 'Notifications',
        'activity-logs': 'Activity Logs',
        profile: 'Profile'
    };

    let activityChart = null;
    let usersPage = 1;
    let logsPage = 1;
    let logsTab = 'all';
    let monitorTab = 'road-status';
    let pendingMonitorTab = null;
    let pendingLogsTab = null;
    let editingUserId = null;
    let logsPollTimer = null;

    function post(action, data, isFormData) {
        const payload = isFormData ? data : Object.assign({ action: action, _t: Date.now() }, data || {});
        if (!isFormData) {
            return $.ajax({ url: API, type: 'POST', dataType: 'json', cache: false, data: payload });
        }
        payload.append('action', action);
        payload.append('_t', Date.now());
        return $.ajax({
            url: API,
            type: 'POST',
            dataType: 'json',
            cache: false,
            data: payload,
            processData: false,
            contentType: false
        });
    }

    function esc(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }

    function showMsg($el, res) {
        $el.html('<div class="alert alert-' + (res.success ? 'success' : 'danger') + ' py-2 mb-0">' + esc(res.message || (res.success ? 'Done' : 'Failed')) + '</div>');
    }

    function userTypeLabel(t) {
        const map = {
            driver: 'Driver',
            traffic_authority: 'Traffic Officer',
            administrator: 'Administrator'
        };
        return map[t] || t;
    }

    function roleBadge(active) {
        return active
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-secondary">Inactive</span>';
    }

    function userDisplayName(u) {
        return ((u.first_name || '') + ' ' + (u.last_name || '')).trim() || u.username || u.email || 'User';
    }

    function userActionButtons(u) {
        const isActive = Number(u.is_active) === 1;
        const isAdministrator = u.user_type === 'administrator';
        const name = userDisplayName(u);
        const toggleLabel = isActive ? 'Deactivate' : 'Activate';
        const toggleTitle = isActive
            ? 'Deactivate account for ' + name + ' — they will not be able to sign in'
            : 'Activate account for ' + name + ' — restore sign-in access';

        let html = '<div class="admin-user-actions">' +
            '<button type="button" class="btn btn-sm btn-outline-dark btn-admin-edit-user" data-id="' + u.id + '" ' +
                'title="Edit account details for ' + esc(name) + ' (name, email, phone, role, password)" ' +
                'aria-label="Edit user ' + esc(name) + '">' +
                '<i class="fas fa-pen" aria-hidden="true"></i><span class="admin-action-text">Edit</span></button>';

        if (!isAdministrator) {
            html += '<button type="button" class="btn btn-sm btn-outline-' + (isActive ? 'warning' : 'success') + ' btn-admin-toggle-user" ' +
                'data-id="' + u.id + '" data-active="' + u.is_active + '" ' +
                'title="' + esc(toggleTitle) + '" aria-label="' + esc(toggleLabel) + ' user ' + esc(name) + '">' +
                '<i class="fas fa-' + (isActive ? 'ban' : 'check') + '" aria-hidden="true"></i>' +
                '<span class="admin-action-text">' + toggleLabel + '</span></button>';
        }

        html += '<button type="button" class="btn btn-sm btn-outline-secondary btn-admin-reset-pw" data-id="' + u.id + '" ' +
                'data-name="' + esc(name) + '" ' +
                'title="Set a new password for ' + esc(name) + '" aria-label="Reset password for ' + esc(name) + '">' +
                '<i class="fas fa-key" aria-hidden="true"></i><span class="admin-action-text">Reset</span></button>';

        if (!isAdministrator) {
            html += '<button type="button" class="btn btn-sm btn-outline-danger btn-admin-delete-user" data-id="' + u.id + '" ' +
                'data-name="' + esc(name) + '" ' +
                'title="Permanently delete ' + esc(name) + '\'s account — requires typing Delete to confirm" ' +
                'aria-label="Delete user ' + esc(name) + '">' +
                '<i class="fas fa-trash" aria-hidden="true"></i><span class="admin-action-text">Delete</span></button>';
        }

        html += '</div>';
        return html;
    }

    let pendingDeleteUserId = null;
    let deleteUserModal = null;
    let pendingResetUserId = null;
    let resetPasswordModal = null;

    function openDeleteUserModal(userId, userName) {
        pendingDeleteUserId = userId;
        $('#adminDeleteUserName').text(userName || 'this user');
        $('#adminDeleteConfirmInput').val('').removeClass('is-invalid is-valid');
        $('#btnAdminDeleteConfirm').prop('disabled', true);
        const el = document.getElementById('adminDeleteUserModal');
        if (!el) return;
        if (!deleteUserModal) {
            deleteUserModal = new bootstrap.Modal(el);
        }
        deleteUserModal.show();
        setTimeout(function () { $('#adminDeleteConfirmInput').trigger('focus'); }, 250);
    }

    function closeDeleteUserModal() {
        pendingDeleteUserId = null;
        $('#adminDeleteConfirmInput').val('').removeClass('is-invalid is-valid');
        $('#btnAdminDeleteConfirm').prop('disabled', true);
        if (deleteUserModal) {
            deleteUserModal.hide();
        }
    }

    function validateResetPasswordForm() {
        const pw = $('#adminResetPasswordNew').val();
        const confirm = $('#adminResetPasswordConfirm').val();
        const match = pw.length >= 8 && pw === confirm;
        $('#btnAdminResetPasswordConfirm').prop('disabled', !match);
        $('#adminResetPasswordConfirm').toggleClass('is-valid', confirm.length > 0 && pw === confirm && pw.length >= 8);
        $('#adminResetPasswordConfirm').toggleClass('is-invalid', confirm.length > 0 && pw !== confirm);
        $('#adminResetPasswordNew').toggleClass('is-invalid', pw.length > 0 && pw.length < 8);
    }

    function openResetPasswordModal(userId, userName) {
        pendingResetUserId = userId;
        $('#adminResetPasswordUserName').text(userName || 'this user');
        $('#adminResetPasswordNew, #adminResetPasswordConfirm').val('').removeClass('is-invalid is-valid');
        $('#adminResetPasswordMsg').empty();
        $('#btnAdminResetPasswordConfirm').prop('disabled', true);

        const el = document.getElementById('adminResetPasswordModal');
        if (!el) return;
        if (!resetPasswordModal) {
            resetPasswordModal = new bootstrap.Modal(el);
        }
        resetPasswordModal.show();
        setTimeout(function () { $('#adminResetPasswordNew').trigger('focus'); }, 250);
    }

    function closeResetPasswordModal() {
        pendingResetUserId = null;
        $('#adminResetPasswordNew, #adminResetPasswordConfirm').val('').removeClass('is-invalid is-valid');
        $('#adminResetPasswordMsg').empty();
        $('#btnAdminResetPasswordConfirm').prop('disabled', true);
        $('#adminResetPasswordModal .admin-password-toggle').each(function () {
            const input = document.getElementById($(this).attr('data-target'));
            if (input) input.type = 'password';
            $(this).find('i').removeClass('fa-eye-slash').addClass('fa-eye');
        });
        if (resetPasswordModal) {
            resetPasswordModal.hide();
        }
    }

    function bindPasswordToggles(root) {
        $(root).find('.admin-password-toggle').off('click.adminPwToggle').on('click.adminPwToggle', function () {
            const input = document.getElementById($(this).attr('data-target'));
            const icon = $(this).find('i');
            if (!input || !icon.length) return;

            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.toggleClass('fa-eye', !show);
            icon.toggleClass('fa-eye-slash', show);
            $(this).attr('aria-label', show ? 'Hide password' : 'Show password');
            $(this).attr('title', show ? 'Hide password' : 'Show password');
        });
    }

    function formatLogDate(value, timeLabel) {
        if (!value) return '—';
        const d = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(d.getTime())) {
            return esc(value);
        }
        const absolute = d.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
        return '<span class="admin-log-time">' + esc(absolute) + '</span>' +
            (timeLabel ? '<small class="d-block text-muted">' + esc(timeLabel) + '</small>' : '');
    }

    function startLogsLiveRefresh() {
        stopLogsLiveRefresh();
        const secs = parseInt((window.STRGS_SYSTEM_SETTINGS || {}).auto_refresh_seconds, 10) || 30;
        const intervalMs = Math.max(10, Math.min(secs, 30)) * 1000;
        logsPollTimer = setInterval(function () {
            if (!$('#activity-logs-section').is(':visible')) return;
            loadActivityLogs(logsPage, true);
            loadLoginHistory(true);
        }, intervalMs);
    }

    function stopLogsLiveRefresh() {
        if (logsPollTimer) {
            clearInterval(logsPollTimer);
            logsPollTimer = null;
        }
    }

    function updateLogsRefreshNote(serverTime) {
        const note = serverTime
            ? 'Last updated: ' + new Date(String(serverTime).replace(' ', 'T')).toLocaleTimeString()
            : 'Updated: ' + new Date().toLocaleTimeString();
        $('#adminLogsUpdatedAt').text(note + ' · refreshes automatically');
    }

    function initNavigation() {
        function showSection(section) {
            $('.nav-link[data-section]').removeClass('active');
            $('.nav-link[data-section="' + section + '"]').addClass('active');
            $('.admin-section').hide();
            $('#' + section + '-section').show();
            $('#pageTitle').text(SECTION_TITLES[section] || 'Administrator Dashboard');
            onSectionShown(section);
        }

        $('.nav-link[data-section]').on('click', function (e) {
            e.preventDefault();
            showSection($(this).data('section'));
        });

        $('[data-section-jump]').on('click', function (e) {
            e.preventDefault();
            const sec = $(this).data('section-jump');
            const tab = $(this).data('monitor-tab');
            if (tab) pendingMonitorTab = tab;
            showSection(sec);
        });

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

        showSection('dashboard');
    }

    function onSectionShown(section) {
        stopLogsLiveRefresh();
        if (section === 'dashboard') loadAdminHome();
        if (section === 'users') loadUsers();
        if (section === 'traffic-monitoring') {
            if (pendingMonitorTab) {
                switchMonitorTab(pendingMonitorTab);
                pendingMonitorTab = null;
            } else {
                loadTrafficMonitoring(monitorTab);
            }
        }
        if (section === 'system') loadSystemSettings();
        if (section === 'notifications') loadAnnouncements();
        if (section === 'activity-logs') {
            if (pendingLogsTab) {
                logsTab = pendingLogsTab;
                pendingLogsTab = null;
                $('[data-logs-tab]').removeClass('active').attr('aria-selected', 'false');
                $('[data-logs-tab="' + logsTab + '"]').addClass('active').attr('aria-selected', 'true');
            }
            loadActivityLogs();
            loadLoginHistory();
            startLogsLiveRefresh();
        }
        if (section === 'profile') loadProfile();

        if (section === 'dashboard' && activityChart) {
            setTimeout(function () { activityChart.resize(); }, 200);
        }
    }

    function loadAdminHome() {
        post('admin_home').done(function (res) {
            if (!res.success) return;
            const st = res.home.stats || {};
            $('#adminStatDrivers').text(st.drivers ?? '—');
            $('#adminStatOfficers').text(st.officers ?? '—');
            $('#adminStatAdmins').text(st.admins ?? '—');
            $('#adminStatActive').text(st.active_users ?? '—');
            $('#adminStatRoadRecords').text(st.road_status_records ?? '—');
            $('#adminStatAccidents').text(st.accident_reports ?? '—');
            $('#adminStatCongestion').text(st.congestion_reports ?? '—');
            $('#adminStatBlockages').text(st.road_blockage_reports ?? '—');

            renderActivityChart(res.home.activity_chart || []);
            renderRecentActivity(res.home.recent_activities || []);
        });
    }

    function renderActivityChart(rows) {
        const labels = rows.length ? rows.map(function (r) { return r.day; }) : ['No data'];
        const data = rows.length ? rows.map(function (r) { return Number(r.cnt) || 0; }) : [0];
        const ctx = document.getElementById('adminActivityChart');
        if (!ctx) return;
        if (activityChart) activityChart.destroy();
        activityChart = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'System events (7 days)',
                    data: data,
                    borderColor: '#111827',
                    backgroundColor: 'rgba(17, 24, 39, 0.08)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    }

    function renderRecentActivity(items) {
        const $el = $('#adminRecentActivity');
        if (!items.length) {
            $el.html('<p class="text-muted mb-0">No recent system activity.</p>');
            return;
        }
        $el.html(items.map(function (a) {
            return '<div class="alert-item info mb-2"><div class="d-flex justify-content-between gap-2">' +
                '<strong>' + esc(a.label) + '</strong>' +
                '<small class="text-muted">' + esc(a.created_at) + '</small></div>' +
                '<small class="text-muted">' + esc(a.user_name) +
                (a.user_type ? ' · ' + esc(userTypeLabel(a.user_type)) : '') + '</small></div>';
        }).join(''));
    }

    function loadUsers(page) {
        usersPage = page || usersPage;
        $('#adminUsersTable').html('<tr><td colspan="8" class="text-center py-4 text-muted">Loading…</td></tr>');
        post('admin_users', {
            search: $('#adminUserSearch').val(),
            role: $('#adminUserRoleFilter').val(),
            status: $('#adminUserStatusFilter').val(),
            page: usersPage,
            per_page: 15
        }).done(function (res) {
            if (!res.success) return;
            const users = res.users || [];
            if (!users.length) {
                $('#adminUsersTable').html('<tr><td colspan="8" class="text-center py-4 text-muted">No users found.</td></tr>');
            } else {
                const pg = res.pagination || {};
                const page = Number(pg.page) || 1;
                const perPage = Number(pg.per_page) || 15;
                const rowOffset = (page - 1) * perPage;

                $('#adminUsersTable').html(users.map(function (u, index) {
                    const name = userDisplayName(u);
                    const email = u.email || '—';
                    const phone = (u.phone_number || '').trim();
                    const username = (u.username || '').trim();
                    const rowNum = rowOffset + index + 1;

                    return '<tr>' +
                        '<td class="admin-user-cell-num"><span class="admin-user-num">' + rowNum + '</span></td>' +
                        '<td class="admin-user-cell-name">' +
                            '<span class="admin-user-name">' + esc(name) + '</span>' +
                            (username ? '<small class="text-muted d-block admin-user-username">@' + esc(username) + '</small>' : '') +
                        '</td>' +
                        '<td class="admin-user-cell-email">' +
                            (email !== '—'
                                ? '<a href="mailto:' + esc(email) + '" class="admin-user-email">' + esc(email) + '</a>'
                                : '<span class="text-muted">—</span>') +
                        '</td>' +
                        '<td class="admin-user-cell-phone">' +
                            (phone
                                ? '<a href="tel:' + esc(phone.replace(/\s+/g, '')) + '" class="admin-user-phone">' + esc(phone) + '</a>'
                                : '<span class="text-muted">—</span>') +
                        '</td>' +
                        '<td class="admin-user-cell-role"><span class="admin-user-role">' + esc(userTypeLabel(u.user_type)) + '</span></td>' +
                        '<td class="admin-user-cell-status">' + roleBadge(Number(u.is_active) === 1) + '</td>' +
                        '<td class="admin-user-cell-login"><small class="text-muted">' + esc(u.last_login || 'Never') + '</small></td>' +
                        '<td class="text-end admin-user-cell-actions">' + userActionButtons(u) + '</td>' +
                        '</tr>';
                }).join(''));
            }
            renderPagination('#adminUsersPagination', res.pagination, loadUsers);
        });
    }

    function setIdFieldsEditable(isNew) {
        const $license = $('#adminUserLicenseId');
        const $police = $('#adminUserPoliceId');

        $license.prop('disabled', !isNew);
        $police.prop('disabled', !isNew);
        $('.admin-id-lock-hint').toggleClass('d-none', isNew);

        if (!isNew) {
            $license.attr('title', 'License ID cannot be changed after registration');
            $police.attr('title', 'Police ID cannot be changed after registration');
        } else {
            $license.removeAttr('title');
            $police.removeAttr('title');
        }
    }

    function setUserTypeEditable(user) {
        const isAdminUser = user && user.user_type === 'administrator';
        const $type = $('#adminUserType');

        if (isAdminUser) {
            $type.val('administrator').prop('disabled', true);
            $('.admin-type-lock-hint').removeClass('d-none');
        } else {
            $type.prop('disabled', false);
            $('.admin-type-lock-hint').addClass('d-none');
        }
    }

    function openUserForm(user) {
        editingUserId = user ? user.id : null;
        const isNew = !user;
        $('#adminUserFormTitle').html('<i class="fas fa-user-edit"></i> ' + (user ? 'Edit User' : 'Register New User'));
        $('#adminUserId').val(user ? user.id : '');
        $('#adminUserFirstName').val(user ? user.first_name : '');
        $('#adminUserLastName').val(user ? user.last_name : '');
        $('#adminUserEmail').val(user ? user.email : '');
        $('#adminUserUsername').val(user ? user.username : '');
        $('#adminUserPhone').val(user ? (user.phone_number || '') : '');
        $('#adminUserType').val(user ? user.user_type : 'driver');
        $('#adminUserLicenseId').val(user ? (user.license_id || '') : '');
        $('#adminUserPoliceId').val(user ? (user.police_id || '') : '');
        $('#adminUserPassword').val('').prop('required', isNew);
        $('#adminUserIsActive').prop('checked', user ? Number(user.is_active) === 1 : true);
        $('#adminUserFormMsg').empty();
        setUserTypeEditable(user);
        setIdFieldsEditable(isNew);
        toggleRoleFields();
        $('#adminUserFormCard').removeClass('d-none')[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function toggleRoleFields() {
        const t = $('#adminUserType').val();
        const isNew = !editingUserId;
        $('#adminUserLicenseId').closest('.col-md-6').toggle(t === 'driver');
        $('#adminUserPoliceId').closest('.col-md-6').toggle(t === 'traffic_authority');
        setIdFieldsEditable(isNew);
    }

    function switchMonitorTab(tab) {
        monitorTab = tab;
        $('[data-monitor-tab]').removeClass('active').attr('aria-selected', 'false');
        $('[data-monitor-tab="' + tab + '"]').addClass('active').attr('aria-selected', 'true');
        loadTrafficMonitoring(tab);
    }

    function loadTrafficMonitoring(tab) {
        monitorTab = tab || monitorTab;
        $('#adminMonitorTable').html('<tr><td colspan="6" class="text-center py-4 text-muted">Loading…</td></tr>');
        post('admin_traffic_monitoring', { tab: monitorTab, limit: 40 }).done(function (res) {
            if (!res.success) return;
            const m = res.monitoring || {};
            const st = m.stats || {};
            $('#adminMonitorStatTotal').text(st.road_readings ?? '—');
            $('#adminMonitorStatRecent').text(st.accidents ?? '—');
            $('#adminMonitorStatUpdated').text(new Date().toLocaleTimeString());

            const titles = {
                'road-status': 'Road Status Updates',
                accidents: 'Accident Reports',
                congestion: 'Congestion Reports',
                blockages: 'Road Blockage Reports'
            };
            $('#adminMonitorTableTitle').html('<i class="fas fa-table"></i> ' + (titles[monitorTab] || 'Monitoring'));

            if (monitorTab === 'road-status') {
                $('#adminMonitorTableHead').html('<tr><th>Road</th><th>Time</th><th>Source</th></tr>');
                $('#adminMonitorTable').html((m.items || []).map(function (r) {
                    return '<tr><td>' + esc(r.segment_name) + '<br><small class="text-muted">' + esc(r.zone) + '</small></td>' +
                        '<td><small>' + esc(r.reading_time) + '</small></td>' +
                        '<td>' + esc(r.source) + '</td></tr>';
                }).join('') || '<tr><td colspan="3" class="text-center text-muted py-4">No road status records.</td></tr>');
            } else if (monitorTab === 'accidents') {
                $('#adminMonitorTableHead').html('<tr><th>#</th><th>Road</th><th>Time</th><th>Severity</th><th>Description</th></tr>');
                $('#adminMonitorTable').html((m.items || []).map(function (r) {
                    return '<tr><td>' + esc(r.report_number || r.id) + '</td><td>' + esc(r.segment_name) + '</td>' +
                        '<td><small>' + esc(r.accident_time) + '</small></td>' +
                        '<td>' + esc(r.severity) + '</td><td>' + esc(r.cause || '—') + '</td></tr>';
                }).join('') || '<tr><td colspan="5" class="text-center text-muted py-4">No accident reports.</td></tr>');
            } else {
                $('#adminMonitorTableHead').html('<tr><th>Road</th><th>Time</th><th>Severity</th><th>Message</th><th>Status</th></tr>');
                $('#adminMonitorTable').html((m.items || []).map(function (r) {
                    return '<tr><td>' + esc(r.segment_name || '—') + '</td><td><small>' + esc(r.alert_time) + '</small></td>' +
                        '<td>' + esc(r.severity) + '</td><td>' + esc(r.message) + '</td>' +
                        '<td>' + (Number(r.is_acknowledged) ? '<span class="badge bg-secondary">Ack</span>' : '<span class="badge bg-warning text-dark">Open</span>') + '</td></tr>';
                }).join('') || '<tr><td colspan="5" class="text-center text-muted py-4">No reports.</td></tr>');
            }
        });
    }

    function applySettingsToForm(settings) {
        const s = settings || {};
        Object.keys(s).forEach(function (key) {
            const $field = $('#adminSystemSettingsForm [name="' + key + '"]');
            if (!$field.length) return;
            if ($field.attr('type') === 'checkbox') {
                $field.prop('checked', !!s[key]);
            } else {
                $field.val(s[key]);
            }
        });
    }

    let adminRefreshTimer = null;

    function applyAdminAutoRefresh() {
        clearInterval(adminRefreshTimer);
        const secs = parseInt((window.STRGS_SYSTEM_SETTINGS || {}).auto_refresh_seconds, 10);
        if (!secs || secs < 10) return;

        adminRefreshTimer = setInterval(function () {
            const sectionId = $('.admin-section:visible').attr('id') || '';
            if (sectionId === 'dashboard-section') loadAdminHome();
            else if (sectionId === 'users-section') loadUsers(usersPage);
            else if (sectionId === 'traffic-monitoring-section') loadTrafficMonitoring(monitorTab);
            else if (sectionId === 'notifications-section') loadAnnouncements();
            else if (sectionId === 'activity-logs-section') loadActivityLogs(logsPage);
        }, secs * 1000);
    }

    function loadSystemSettings() {
        post('admin_get_settings').done(function (res) {
            if (!res.success) return;
            const s = res.settings || {};
            applySettingsToForm(s);
            window.STRGS_SYSTEM_SETTINGS = s;
            applyAdminAutoRefresh();
        });
    }

    function switchSettingsTab(tab) {
        $('[data-settings-tab]').removeClass('active').attr('aria-selected', 'false');
        $('[data-settings-tab="' + tab + '"]').addClass('active').attr('aria-selected', 'true');
        $('.admin-settings-panel').prop('hidden', true).removeClass('active');
        $('.admin-settings-panel[data-settings-panel="' + tab + '"]').prop('hidden', false).addClass('active');
    }

    function loadAnnouncements() {
        $('#adminAnnouncementsList').html('<p class="text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Loading…</p>');
        post('admin_announcements', { limit: 30 }).done(function (res) {
            if (!res.success) return;
            const items = res.announcements || [];
            if (!items.length) {
                $('#adminAnnouncementsList').html('<p class="text-muted mb-0">No announcements yet.</p>');
                return;
            }
            $('#adminAnnouncementsList').html(items.map(function (a) {
                const sev = a.severity === 'critical' ? 'danger' : (a.severity === 'warning' ? 'warning' : 'info');
                return '<div class="notification-item alert-item ' + sev + ' mb-2">' +
                    '<div class="d-flex justify-content-between"><strong>' + esc(a.title) + '</strong>' +
                    '<small class="text-muted">' + esc(a.created_at) + '</small></div>' +
                    '<div class="notification-message">' + esc(a.message) + '</div></div>';
            }).join(''));
        });
    }

    function updateLogsTabHint() {
        const hints = {
            all: 'Showing all system activity.',
            login: 'Showing sign-in and sign-out events only.',
            security: 'Showing administrator actions, logins, and password events.',
            changes: 'Showing records that were created, updated, deactivated, or resolved — not new traffic alerts or incident reports.'
        };
        $('#adminLogsTabHint').text(hints[logsTab] || hints.all);
    }

    function loadActivityLogs(page, silent) {
        logsPage = page || logsPage;
        if (!silent) {
            $('#adminLogsTable').html('<tr><td colspan="4" class="text-center py-4 text-muted">Loading…</td></tr>');
        }
        updateLogsTabHint();
        post('admin_activity_logs', { type: logsTab, page: logsPage, per_page: 20, _t: Date.now() }).done(function (res) {
            if (!res.success) return;
            const logs = res.logs || [];
            $('#adminLogsTable').html(logs.length ? logs.map(function (l) {
                return '<tr>' +
                    '<td class="admin-log-cell-time">' + formatLogDate(l.created_at, l.time_label) + '</td>' +
                    '<td class="admin-log-cell-user">' +
                        '<span class="admin-log-user-name">' + esc(l.user_name) + '</span>' +
                        (l.user_type ? '<small class="d-block text-muted">' + esc(userTypeLabel(l.user_type)) + '</small>' : '') +
                    '</td>' +
                    '<td class="admin-log-cell-action"><span class="admin-log-action-pill">' + esc(l.label) + '</span></td>' +
                    '<td class="admin-log-cell-details"><div class="admin-log-details-text">' + esc(l.details_text || 'No additional details recorded.') + '</div></td>' +
                    '</tr>';
            }).join('') : '<tr><td colspan="4" class="text-center text-muted py-4">' +
                (logsTab === 'changes'
                    ? 'No data changes found. This tab shows updated accounts, settings, road status, and resolved records.'
                    : 'No activity records found for this filter.') +
                '</td></tr>');
            renderPagination('#adminLogsPagination', res.pagination, loadActivityLogs);
            updateLogsRefreshNote(res.server_time);
        });
    }

    function loadLoginHistory(silent) {
        post('admin_login_history', { limit: 15, _t: Date.now() }).done(function (res) {
            if (!res.success) return;
            const $body = $('#adminLoginHistory .driver-feed');
            const items = res.logins || [];
            if (!items.length) {
                $body.html('<p class="text-muted mb-0">No sign-in activity yet. Users will appear here after they log in or out.</p>');
                return;
            }
            $body.html(items.map(function (u) {
                const actionClass = u.action === 'logout' ? 'secondary' : 'success';
                return '<div class="admin-login-feed-item mb-2">' +
                    '<div class="d-flex justify-content-between align-items-start gap-2">' +
                        '<div><strong>' + esc((u.first_name || '') + ' ' + (u.last_name || '')) + '</strong>' +
                        '<small class="d-block text-muted">' + esc(userTypeLabel(u.user_type)) + '</small></div>' +
                        '<span class="badge bg-' + actionClass + '">' + esc(u.action_label || (u.action === 'logout' ? 'Signed out' : 'Signed in')) + '</span>' +
                    '</div>' +
                    '<div class="admin-log-details-text small mt-1">' + esc(u.details_text || '') + '</div>' +
                    '<small class="text-muted">' + esc(u.time_label || u.created_at || u.last_login) + '</small>' +
                '</div>';
            }).join(''));
        });
    }

    function loadProfile() {
        post('admin_profile_get').done(function (res) {
            if (!res.success) return;
            const p = res.profile;
            $('#adminFirstName').val(p.first_name);
            $('#adminLastName').val(p.last_name);
            $('#adminEmail').val(p.email);
            $('#adminPhone').val(p.phone_number);
            $('#adminUsername').val(p.username);
            if (p.profile_picture) {
                $('#adminProfileAvatar').html('<img src="' + esc(p.profile_picture) + '" alt="">');
                $('#topUserAvatar').html('<img src="' + esc(p.profile_picture) + '" alt="">');
            }
            $('#topUserName').text((p.first_name + ' ' + p.last_name).trim());
            $('#topUserEmail').text(p.email);
        });
    }

    function renderPagination(selector, pg, loader) {
        const $el = $(selector);
        if (!pg || pg.pages <= 1) {
            $el.empty();
            return;
        }
        $el.html(
            '<div class="d-flex justify-content-between align-items-center p-2">' +
            '<button type="button" class="btn btn-sm btn-outline-secondary admin-pg-prev"' + (pg.page <= 1 ? ' disabled' : '') + '>Previous</button>' +
            '<small class="text-muted">Page ' + pg.page + ' of ' + pg.pages + '</small>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary admin-pg-next"' + (pg.page >= pg.pages ? ' disabled' : '') + '>Next</button></div>'
        );
        $el.find('.admin-pg-prev').on('click', function () { if (pg.page > 1) loader(pg.page - 1); });
        $el.find('.admin-pg-next').on('click', function () { if (pg.page < pg.pages) loader(pg.page + 1); });
    }

    function bindEvents() {
        $('#btnAdminAddUser').on('click', function () { openUserForm(null); });
        $('#btnAdminCancelUser, #btnAdminUserFormClose').on('click', function () {
            $('#adminUserFormCard').addClass('d-none');
            editingUserId = null;
        });
        $('#adminUserType').on('change', toggleRoleFields);

        $('#adminUserForm').on('submit', function (e) {
            e.preventDefault();
            const data = {};
            $(this).serializeArray().forEach(function (f) { data[f.name] = f.value; });
            data.is_active = $('#adminUserIsActive').is(':checked') ? 1 : 0;
            if ($('#adminUserType').prop('disabled')) {
                data.user_type = 'administrator';
            }
            const action = editingUserId ? 'admin_update_user' : 'admin_create_user';
            if (editingUserId) data.user_id = editingUserId;
            post(action, data).done(function (res) {
                showMsg($('#adminUserFormMsg'), res);
                if (res.success) {
                    const savedPage = usersPage;
                    loadUsers(savedPage);
                    $('#adminUserFormCard').addClass('d-none');
                    editingUserId = null;
                    document.querySelector('.admin-users-table-wrap')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        });

        let searchTimer;
        $('#adminUserSearch').on('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { usersPage = 1; loadUsers(); }, 350);
        });
        $('#adminUserRoleFilter, #adminUserStatusFilter').on('change', function () { usersPage = 1; loadUsers(); });

        $(document).on('click', '.btn-admin-edit-user', function () {
            const id = $(this).data('id');
            post('admin_users', { search: '', page: 1, per_page: 200 }).done(function (res) {
                const user = (res.users || []).find(function (u) { return Number(u.id) === Number(id); });
                if (user) openUserForm(user);
            });
        });

        $(document).on('click', '.btn-admin-toggle-user', function () {
            const id = $(this).data('id');
            const active = Number($(this).data('active')) !== 1;
            const actionText = active ? 'activate' : 'deactivate';
            if (!confirm('Are you sure you want to ' + actionText + ' this user account?')) return;
            post('admin_toggle_user', { user_id: id, is_active: active ? 1 : 0 }).done(function (res) {
                alert(res.message);
                if (res.success) loadUsers();
            });
        });

        $('#adminDeleteConfirmInput').on('input', function () {
            const val = $(this).val();
            const ok = val === 'Delete';
            $('#btnAdminDeleteConfirm').prop('disabled', !ok);
            $(this).toggleClass('is-valid', ok).toggleClass('is-invalid', val.length > 0 && !ok);
        });

        $('#adminDeleteConfirmInput').on('keydown', function (e) {
            if (e.key === 'Enter' && $(this).val() === 'Delete' && pendingDeleteUserId) {
                e.preventDefault();
                $('#btnAdminDeleteConfirm').trigger('click');
            }
        });

        $('#btnAdminDeleteConfirm').on('click', function () {
            if ($('#adminDeleteConfirmInput').val() !== 'Delete' || !pendingDeleteUserId) return;
            const userId = pendingDeleteUserId;
            $(this).prop('disabled', true);
            post('admin_delete_user', { user_id: userId }).done(function (res) {
                alert(res.message);
                closeDeleteUserModal();
                if (res.success) loadUsers();
            }).always(function () {
                $('#btnAdminDeleteConfirm').prop('disabled', $('#adminDeleteConfirmInput').val() !== 'Delete');
            });
        });

        document.getElementById('adminDeleteUserModal')?.addEventListener('hidden.bs.modal', function () {
            pendingDeleteUserId = null;
            $('#adminDeleteConfirmInput').val('').removeClass('is-invalid is-valid');
            $('#btnAdminDeleteConfirm').prop('disabled', true);
        });

        $(document).on('click', '.btn-admin-delete-user', function () {
            openDeleteUserModal($(this).data('id'), $(this).data('name'));
        });

        $(document).on('click', '.btn-admin-reset-pw', function () {
            openResetPasswordModal($(this).data('id'), $(this).data('name'));
        });

        $('#adminResetPasswordNew, #adminResetPasswordConfirm').on('input', validateResetPasswordForm);

        $('#btnAdminResetPasswordConfirm').on('click', function () {
            const pw = $('#adminResetPasswordNew').val();
            const confirm = $('#adminResetPasswordConfirm').val();
            if (!pendingResetUserId || pw.length < 8 || pw !== confirm) return;

            const $btn = $(this).prop('disabled', true);
            post('admin_reset_password', { user_id: pendingResetUserId, new_password: pw }).done(function (res) {
                if (res.success) {
                    closeResetPasswordModal();
                    alert(res.message);
                } else {
                    showMsg($('#adminResetPasswordMsg'), res);
                }
            }).always(function () {
                $btn.prop('disabled', $('#adminResetPasswordNew').val().length < 8 ||
                    $('#adminResetPasswordNew').val() !== $('#adminResetPasswordConfirm').val());
            });
        });

        document.getElementById('adminResetPasswordModal')?.addEventListener('hidden.bs.modal', function () {
            closeResetPasswordModal();
        });

        bindPasswordToggles(document);

        $('[data-monitor-tab]').on('click', function () {
            switchMonitorTab($(this).data('monitor-tab'));
        });
        $('#btnAdminRefreshMonitor').on('click', function () { loadTrafficMonitoring(monitorTab); });

        $('[data-settings-tab]').on('click', function () {
            switchSettingsTab($(this).data('settings-tab'));
        });

        $('#adminSystemSettingsForm').on('submit', function (e) {
            e.preventDefault();
            const data = {};
            $(this).serializeArray().forEach(function (f) { data[f.name] = f.value; });
            $(this).find('input[type="checkbox"]').each(function () {
                data[this.name] = $(this).is(':checked') ? '1' : '0';
            });
            post('admin_save_settings', data).done(function (res) {
                showMsg($('#adminSettingsMsg'), res);
                if (res.success && res.settings) {
                    applySettingsToForm(res.settings);
                    window.STRGS_SYSTEM_SETTINGS = res.settings;
                    applyAdminAutoRefresh();
                }
            });
        });

        $('#adminAnnouncementForm').on('submit', function (e) {
            e.preventDefault();
            const $form = $(this);
            const $btn = $form.find('button[type="submit"]');
            const data = {};
            $form.serializeArray().forEach(function (f) { data[f.name] = f.value; });
            data.emergency = $('#adminAnnouncementEmergency').is(':checked') ? 1 : 0;
            $btn.prop('disabled', true);
            post('admin_send_announcement', data).done(function (res) {
                showMsg($('#adminAnnouncementMsg'), res);
                if (res.success) {
                    $form[0].reset();
                    loadAnnouncements();
                }
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });
        $('#btnAdminRefreshAnnouncements').on('click', loadAnnouncements);

        $('[data-logs-tab]').on('click', function () {
            logsTab = $(this).data('logs-tab');
            $('[data-logs-tab]').removeClass('active').attr('aria-selected', 'false');
            $(this).addClass('active').attr('aria-selected', 'true');
            logsPage = 1;
            loadActivityLogs();
        });

        $('#btnAdminRefreshLogs').on('click', function () {
            loadActivityLogs(logsPage);
            loadLoginHistory();
        });

        $('#btnAdminChooseAvatar').on('click', function () { $('#adminProfilePictureInput').click(); });
        $('#adminProfilePictureInput').on('change', function () {
            const fd = new FormData();
            fd.append('profile_picture', this.files[0]);
            post('admin_upload_avatar', fd, true).done(function (res) {
                showMsg($('#adminAvatarMsg'), res);
                if (res.success && res.profile_picture) {
                    $('#adminProfileAvatar, #topUserAvatar').html('<img src="' + esc(res.profile_picture) + '" alt="">');
                }
            });
        });

        $('#adminProfileForm').on('submit', function (e) {
            e.preventDefault();
            const data = {};
            $(this).serializeArray().forEach(function (f) { data[f.name] = f.value; });
            post('admin_profile_update', data).done(function (res) {
                showMsg($('#adminProfileMsg'), res);
                if (res.success) loadProfile();
            });
        });

        $('#adminPasswordForm').on('submit', function (e) {
            e.preventDefault();
            const data = {};
            $(this).serializeArray().forEach(function (f) { data[f.name] = f.value; });
            post('change_password', {
                current_password: data.current_password,
                new_password: data.new_password
            }).done(function (res) {
                showMsg($('#adminPasswordMsg'), res);
                if (res.success) {
                    $('#adminPasswordForm')[0].reset();
                    $('#adminPasswordForm .admin-password-toggle').each(function () {
                        const input = document.getElementById($(this).attr('data-target'));
                        if (input) input.type = 'password';
                        $(this).find('i').removeClass('fa-eye-slash').addClass('fa-eye');
                        $(this).attr('aria-label', $(this).attr('data-target') === 'adminProfileCurrentPassword'
                            ? 'Show current password' : 'Show new password');
                        $(this).attr('title', 'Show password');
                    });
                }
            });
        });

        $('.admin-nav-jump').on('click', function (e) {
            e.preventDefault();
            const sec = $(this).data('section');
            const lt = $(this).data('logs-tab');
            if (lt) pendingLogsTab = lt;
            $('.nav-link[data-section="' + sec + '"]').trigger('click');
        });
    }

    $(document).ready(function () {
        initNavigation();
        bindEvents();
        if (window.STRGS_SYSTEM_SETTINGS) {
            applyAdminAutoRefresh();
        }
        loadAdminHome();
    });
}(jQuery));
