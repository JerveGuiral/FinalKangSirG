/* Admin Panel Interactive Script - GymBros */

function getAdminApiUrl() {
    return window.location.pathname.includes('/php/') ? 'admin_actions.php' : '../php/admin_actions.php';
}

document.addEventListener('DOMContentLoaded', () => {
    initAdminTabs();
    initFilterAndSearch();
    initDeleteRequestsFilter();
    initModals();
    initSidebarToggle();
});

function initSidebarToggle() {
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebar = document.getElementById('adminSidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }
}

// Toast Notification System
function showToast(message, type = 'success') {
    let container = document.getElementById('admin-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'admin-toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `admin-toast ${type}`;
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    toast.innerHTML = `<i class="fas ${icon}"></i> <span>${escapeHtml(message)}</span>`;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/[&<>"']/g, (m) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[m]);
}

// Tab Switching Logic
function initAdminTabs() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.admin-tab-pane');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const targetTab = btn.getAttribute('data-tab');
            if (!targetTab) return;

            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanes.forEach(p => p.classList.add('hidden'));

            btn.classList.add('active');
            const pane = document.getElementById(`tab-${targetTab}`);
            if (pane) {
                pane.classList.remove('hidden');
            }

            // Sync URL query parameter without full reload
            const url = new URL(window.location.href);
            url.searchParams.set('tab', targetTab);
            window.history.replaceState({}, '', url.toString());
        });
    });
}

// Search & Filter Logic (Client-side real-time filtering by Employee ID / Name / Username / Role / Status)
function initFilterAndSearch() {
    const searchInput = document.getElementById('search-employee-id');
    const roleFilter = document.getElementById('filter-role');
    const statusFilter = document.getElementById('filter-status');

    function filterTable() {
        const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
        const role = (roleFilter ? roleFilter.value : '').toLowerCase();
        const status = (statusFilter ? statusFilter.value : '').toLowerCase();

        const rows = document.querySelectorAll('#all-users-table-body tr');

        rows.forEach(row => {
            const empId = row.getAttribute('data-empid') || '';
            const username = row.getAttribute('data-username') || '';
            const fullname = row.getAttribute('data-fullname') || '';
            const userRole = row.getAttribute('data-role') || '';
            const userStatus = row.getAttribute('data-status') || '';

            const matchesSearch = !query || 
                empId.toLowerCase().includes(query) || 
                username.toLowerCase().includes(query) || 
                fullname.toLowerCase().includes(query);

            const matchesRole = !role || userRole.toLowerCase() === role;
            const matchesStatus = !status || userStatus.toLowerCase() === status;

            if (matchesSearch && matchesRole && matchesStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    if (searchInput) searchInput.addEventListener('input', filterTable);
    if (roleFilter) roleFilter.addEventListener('change', filterTable);
    if (statusFilter) statusFilter.addEventListener('change', filterTable);
}

// Deletion Requests Real-time Live Filter
function initDeleteRequestsFilter() {
    const searchInput = document.getElementById('search-deletion-requests');
    const statusFilter = document.getElementById('filter-deletion-status');

    function filterDeleteTable() {
        const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
        const status = (statusFilter ? statusFilter.value : '').toLowerCase();

        const rows = document.querySelectorAll('#deletion-requests-table-body tr');

        rows.forEach(row => {
            if (!row.getAttribute('data-reqid')) return;
            const target = (row.getAttribute('data-target-user') || '').toLowerCase();
            const requester = (row.getAttribute('data-requester') || '').toLowerCase();
            const reason = (row.getAttribute('data-reason') || '').toLowerCase();
            const reqStatus = (row.getAttribute('data-status') || '').toLowerCase();
            const reqId = (row.getAttribute('data-reqid') || '').toLowerCase();

            const matchesSearch = !query || 
                target.includes(query) || 
                requester.includes(query) || 
                reason.includes(query) ||
                reqId.includes(query) ||
                `#req-${reqId}`.includes(query);

            const matchesStatus = !status || reqStatus === status;

            if (matchesSearch && matchesStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    if (searchInput) searchInput.addEventListener('input', filterDeleteTable);
    if (statusFilter) statusFilter.addEventListener('change', filterDeleteTable);
}

// Modal Controllers
function initModals() {
    // Close modal when clicking background overlay or cancel buttons
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeModal(overlay.id);
        });
    });

    document.querySelectorAll('.modal-close, .btn-modal-close').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-overlay');
            if (modal) closeModal(modal.id);
        });
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Account Status Update (Approve / Block / Pending)
function updateUserStatus(userId, status) {
    const csrfToken = document.getElementById('csrf_token_val').value;

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'update_status',
            user_id: userId,
            status: status,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(err => {
        showToast('Network request failed: ' + err, 'error');
    });
}

// Edit User Info Modal Loader & Handler
function openEditUserModal(userId) {
    const csrfToken = document.getElementById('csrf_token_val').value;

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'get_user_details',
            user_id: userId,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.user) {
            const u = data.user;
            document.getElementById('edit-id-number').value = u.id_number;
            document.getElementById('edit-username').value = u.username;
            document.getElementById('edit-firstname').value = u.first_name;
            document.getElementById('edit-middlename').value = u.middle_name || '';
            document.getElementById('edit-lastname').value = u.last_name;
            document.getElementById('edit-email').value = u.email;
            document.getElementById('edit-birthdate').value = u.birthdate;
            document.getElementById('edit-sex').value = u.sex;
            document.getElementById('edit-purok').value = u.purok_street;
            document.getElementById('edit-barangay').value = u.barangay;
            document.getElementById('edit-city').value = u.city_municipality;
            document.getElementById('edit-province').value = u.province;
            document.getElementById('edit-zip').value = u.zip_code;
            
            const roleSelect = document.getElementById('edit-role');
            if (roleSelect) roleSelect.value = u.role;
            const statusSelect = document.getElementById('edit-status');
            if (statusSelect) statusSelect.value = u.status;

            openModal('modal-edit-user');
        } else {
            showToast(data.message || 'Failed to load user details', 'error');
        }
    });
}

function submitEditUserForm(e) {
    e.preventDefault();
    const csrfToken = document.getElementById('csrf_token_val').value;

    const payload = {
        action: 'update_user_info',
        csrf_token: csrfToken,
        id_number: document.getElementById('edit-id-number').value,
        username: document.getElementById('edit-username').value,
        first_name: document.getElementById('edit-firstname').value,
        middle_name: document.getElementById('edit-middlename').value,
        last_name: document.getElementById('edit-lastname').value,
        email: document.getElementById('edit-email').value,
        birthdate: document.getElementById('edit-birthdate').value,
        sex: document.getElementById('edit-sex').value,
        purok_street: document.getElementById('edit-purok').value,
        barangay: document.getElementById('edit-barangay').value,
        city_municipality: document.getElementById('edit-city').value,
        province: document.getElementById('edit-province').value,
        zip_code: document.getElementById('edit-zip').value
    };

    const roleSelect = document.getElementById('edit-role');
    if (roleSelect) payload.role = roleSelect.value;
    const statusSelect = document.getElementById('edit-status');
    if (statusSelect) payload.status = statusSelect.value;

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('modal-edit-user');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(data.message, 'error');
        }
    });
}

// Privileges Modal Loader & Handler (Super Admin Only)
function openPrivilegesModal(userId) {
    const csrfToken = document.getElementById('csrf_token_val').value;

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'get_user_details',
            user_id: userId,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.user) {
            const u = data.user;
            const targetIdEl = document.getElementById('priv-target-user-id');
            const targetNameEl = document.getElementById('priv-target-name');
            if (targetIdEl) targetIdEl.value = u.id_number;
            if (targetNameEl) targetNameEl.innerText = `${u.first_name} ${u.last_name} (@${u.username})`;

            let privs = {};
            try {
                privs = u.privileges ? (typeof u.privileges === 'object' ? u.privileges : JSON.parse(u.privileges)) : {};
            } catch (e) {
                privs = {};
            }

            const privKeys = [
                'can_approve_users', 'can_block_users', 'can_update_info', 'can_manage_roles', 'can_create_accounts',
                'can_delete_users', 'can_manage_requests', 'can_give_privileges',
                'can_view_reports', 'can_export_logs',
                'can_manage_classes', 'can_manage_bookings', 'can_manage_metrics'
            ];

            privKeys.forEach(key => {
                const el = document.getElementById('priv_' + key);
                if (el) {
                    el.checked = !!privs[key];
                }
            });

            openModal('modal-privileges');
        } else {
            showToast(data.message || 'Failed to load user info', 'error');
        }
    });
}

function submitPrivilegesForm(e) {
    e.preventDefault();
    const csrfToken = document.getElementById('csrf_token_val').value;
    const userId = document.getElementById('priv-target-user-id').value;

    const privKeys = [
        'can_approve_users', 'can_block_users', 'can_update_info', 'can_manage_roles', 'can_create_accounts',
        'can_delete_users', 'can_manage_requests', 'can_give_privileges',
        'can_view_reports', 'can_export_logs',
        'can_manage_classes', 'can_manage_bookings', 'can_manage_metrics'
    ];

    const privileges = {};
    privKeys.forEach(key => {
        const el = document.getElementById('priv_' + key);
        if (el) {
            privileges[key] = el.checked;
        }
    });

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'grant_privileges',
            user_id: userId,
            privileges: privileges,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('modal-privileges');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(data.message, 'error');
        }
    });
}

/* ==========================================================================
   SUPERADMIN PRIVILEGES STUDIO (privileges.php)
   ========================================================================== */

const ALL_PRIVILEGE_KEYS = [
    'can_approve_users', 'can_block_users', 'can_update_info', 'can_manage_roles', 'can_create_accounts',
    'can_delete_users', 'can_manage_requests', 'can_give_privileges',
    'can_view_reports', 'can_export_logs',
    'can_manage_classes', 'can_manage_bookings', 'can_manage_metrics'
];

const PRIVILEGE_PRESETS = {
    full_delegate: [
        'can_approve_users', 'can_block_users', 'can_update_info', 'can_manage_roles', 'can_create_accounts',
        'can_delete_users', 'can_manage_requests', 'can_give_privileges',
        'can_view_reports', 'can_export_logs',
        'can_manage_classes', 'can_manage_bookings', 'can_manage_metrics'
    ],
    senior_admin: [
        'can_approve_users', 'can_block_users', 'can_update_info', 'can_manage_requests',
        'can_view_reports', 'can_export_logs', 'can_manage_classes', 'can_manage_bookings'
    ],
    user_manager: [
        'can_approve_users', 'can_block_users', 'can_update_info', 'can_manage_roles', 'can_create_accounts'
    ],
    audit_officer: [
        'can_view_reports', 'can_export_logs', 'can_manage_requests'
    ],
    gym_manager: [
        'can_manage_classes', 'can_manage_bookings', 'can_manage_metrics', 'can_approve_users'
    ],
    standard_staff: [
        'can_approve_users', 'can_view_reports'
    ]
};

function onPrivilegeUserSelected(userId) {
    const selectEl = document.getElementById('privilege-user-select');
    const displayCard = document.getElementById('target-user-profile-display');
    const detailsBox = document.getElementById('active-profile-details');
    const submitBtn = document.getElementById('btn-save-privileges-submit');
    const targetHiddenInput = document.getElementById('studio-target-user-id');
    const superadminNotice = document.getElementById('superadmin-notice-banner');

    if (!userId) {
        if (displayCard) displayCard.classList.add('empty-state');
        if (detailsBox) detailsBox.classList.add('hidden');
        if (submitBtn) submitBtn.disabled = true;
        if (targetHiddenInput) targetHiddenInput.value = '';
        revokeAllPrivileges();
        return;
    }

    const selectedOption = selectEl.options[selectEl.selectedIndex];
    if (!selectedOption) return;

    const role = selectedOption.getAttribute('data-role');
    const status = selectedOption.getAttribute('data-status');
    const name = selectedOption.getAttribute('data-name');
    const username = selectedOption.getAttribute('data-username');
    const email = selectedOption.getAttribute('data-email');
    const privsRaw = selectedOption.getAttribute('data-privileges');

    if (displayCard) displayCard.classList.remove('empty-state');
    if (detailsBox) detailsBox.classList.remove('hidden');

    document.getElementById('profile-fullname').innerText = name;
    document.getElementById('profile-username').innerText = `@${username}`;
    document.getElementById('profile-id-number').innerText = userId;
    document.getElementById('profile-email').innerText = email;

    // Role badge
    const roleBadge = document.getElementById('profile-role-badge');
    if (role === 'superadmin') {
        roleBadge.innerHTML = '<span class="badge badge-superadmin"><i class="fas fa-crown"></i> Super Admin</span>';
        if (superadminNotice) superadminNotice.classList.remove('hidden');
    } else if (role === 'admin') {
        roleBadge.innerHTML = '<span class="badge badge-admin"><i class="fas fa-shield-alt"></i> Admin</span>';
        if (superadminNotice) superadminNotice.classList.add('hidden');
    } else {
        roleBadge.innerHTML = '<span class="badge badge-user"><i class="fas fa-user"></i> Member</span>';
        if (superadminNotice) superadminNotice.classList.add('hidden');
    }

    // Status badge
    const statusBadge = document.getElementById('profile-status-badge');
    if (status === 'approved') {
        statusBadge.innerHTML = '<span class="badge badge-approved"><i class="fas fa-check-circle"></i> Active</span>';
    } else if (status === 'pending') {
        statusBadge.innerHTML = '<span class="badge badge-pending"><i class="fas fa-clock"></i> Pending</span>';
    } else {
        statusBadge.innerHTML = '<span class="badge badge-blocked"><i class="fas fa-ban"></i> Blocked</span>';
    }

    if (targetHiddenInput) targetHiddenInput.value = userId;

    // Parse and apply privileges
    let privs = {};
    if (role === 'superadmin') {
        ALL_PRIVILEGE_KEYS.forEach(k => privs[k] = true);
    } else {
        try {
            privs = privsRaw ? (typeof privsRaw === 'object' ? privsRaw : JSON.parse(privsRaw)) : {};
        } catch (e) {
            privs = {};
        }
    }

    ALL_PRIVILEGE_KEYS.forEach(key => {
        const chk = document.getElementById('check_' + key);
        if (chk) {
            chk.checked = !!privs[key];
            updatePrivilegeUIState(key);
        }
    });

    if (submitBtn) {
        submitBtn.disabled = (role === 'superadmin');
    }

    updatePrivilegesStudioCounters();
}

function loadUserIntoStudio(userId) {
    const selectEl = document.getElementById('privilege-user-select');
    if (selectEl) {
        selectEl.value = userId;
        onPrivilegeUserSelected(userId);
        
        // Scroll to studio smoothly
        const studioEl = document.querySelector('.privilege-studio-layout');
        if (studioEl) {
            studioEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
}

function updatePrivilegeUIState(privKey) {
    const chk = document.getElementById('check_' + privKey);
    const card = document.getElementById('card_' + privKey);
    const pill = document.getElementById('pill_' + privKey);

    if (chk && card) {
        if (chk.checked) {
            card.classList.add('checked');
            if (pill) {
                pill.innerText = 'Active';
                pill.className = 'priv-status-pill';
            }
        } else {
            card.classList.remove('checked');
            if (pill) {
                pill.innerText = 'Inactive';
                pill.className = 'priv-status-pill';
            }
        }
    }
    updatePrivilegesStudioCounters();
}

function updatePrivilegesStudioCounters() {
    let activeCount = 0;
    ALL_PRIVILEGE_KEYS.forEach(key => {
        const chk = document.getElementById('check_' + key);
        if (chk && chk.checked) {
            activeCount++;
        }
    });

    const total = ALL_PRIVILEGE_KEYS.length;
    const counterEl = document.getElementById('active-privileges-counter');
    const barEl = document.getElementById('privilege-progress-bar');

    if (counterEl) {
        counterEl.innerText = `${activeCount} / ${total}`;
    }
    if (barEl) {
        const pct = Math.round((activeCount / total) * 100);
        barEl.style.width = `${pct}%`;
    }
}

function grantAllPrivileges() {
    ALL_PRIVILEGE_KEYS.forEach(key => {
        const chk = document.getElementById('check_' + key);
        if (chk) {
            chk.checked = true;
            updatePrivilegeUIState(key);
        }
    });
    showToast('All Superadmin privileges selected', 'success');
}

function revokeAllPrivileges() {
    ALL_PRIVILEGE_KEYS.forEach(key => {
        const chk = document.getElementById('check_' + key);
        if (chk) {
            chk.checked = false;
            updatePrivilegeUIState(key);
        }
    });
}

function toggleCategoryCheckboxes(catKey) {
    const container = document.querySelector(`.privilege-cards-grid[data-category="${catKey}"]`);
    if (!container) return;

    const checkboxes = container.querySelectorAll('input[type="checkbox"]');
    const allChecked = Array.from(checkboxes).every(c => c.checked);

    checkboxes.forEach(c => {
        c.checked = !allChecked;
        const key = c.getAttribute('data-priv-key');
        if (key) updatePrivilegeUIState(key);
    });
}

function applyPrivilegePreset(presetKey) {
    const selectEl = document.getElementById('privilege-user-select');
    if (!selectEl || !selectEl.value) {
        showToast('Please select a target account first before applying a preset', 'error');
        return;
    }

    const activeKeys = PRIVILEGE_PRESETS[presetKey] || [];
    ALL_PRIVILEGE_KEYS.forEach(key => {
        const chk = document.getElementById('check_' + key);
        if (chk) {
            chk.checked = activeKeys.includes(key);
            updatePrivilegeUIState(key);
        }
    });

    showToast(`Applied preset: ${presetKey.replace('_', ' ').toUpperCase()}`, 'success');
}

function resetCurrentPrivilegesForm() {
    const selectEl = document.getElementById('privilege-user-select');
    if (selectEl && selectEl.value) {
        onPrivilegeUserSelected(selectEl.value);
        showToast('Privilege form reset to current database settings', 'success');
    }
}

function submitPrivilegesStudioForm(e) {
    e.preventDefault();
    const csrfToken = document.getElementById('csrf_token_val').value;
    const userId = document.getElementById('studio-target-user-id').value;
    const submitBtn = document.getElementById('btn-save-privileges-submit');

    if (!userId) {
        showToast('Please choose an account to configure', 'error');
        return;
    }

    const privileges = {};
    ALL_PRIVILEGE_KEYS.forEach(key => {
        const chk = document.getElementById('check_' + key);
        if (chk) {
            privileges[key] = chk.checked;
        }
    });

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    }

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'grant_privileges',
            user_id: userId,
            privileges: privileges,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-shield-alt"></i> Save Privileges';
        }
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 900);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(err => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-shield-alt"></i> Save Privileges';
        }
        showToast('Request failed: ' + err, 'error');
    });
}

function handleCardClick(privKey, event) {
    if (event && (event.target.tagName === 'INPUT' || event.target.closest('.switch'))) {
        return;
    }
    const chk = document.getElementById('check_' + privKey);
    if (chk) {
        chk.checked = !chk.checked;
        updatePrivilegeUIState(privKey);
    }
}

function filterStudioUserDropdown(query) {
    const selectEl = document.getElementById('privilege-user-select');
    if (!selectEl) return;

    const q = (query || '').toLowerCase().trim();
    const optgroups = selectEl.querySelectorAll('optgroup');
    let firstMatchValue = null;
    let totalMatches = 0;

    optgroups.forEach(group => {
        let groupHasMatch = false;
        const options = group.querySelectorAll('option');

        options.forEach(opt => {
            const val = (opt.value || '').toLowerCase();
            const text = (opt.textContent || '').toLowerCase();
            const name = (opt.getAttribute('data-name') || '').toLowerCase();
            const username = (opt.getAttribute('data-username') || '').toLowerCase();
            const email = (opt.getAttribute('data-email') || '').toLowerCase();

            const isMatch = !q || val.includes(q) || text.includes(q) || name.includes(q) || username.includes(q) || email.includes(q);

            if (isMatch) {
                opt.hidden = false;
                opt.disabled = false;
                opt.style.display = '';
                groupHasMatch = true;
                totalMatches++;
                if (!firstMatchValue && opt.value) {
                    firstMatchValue = opt.value;
                }
            } else {
                opt.hidden = true;
                opt.disabled = true;
                opt.style.display = 'none';
            }
        });

        group.style.display = groupHasMatch ? '' : 'none';
    });

    // If Enter key is pressed or user wants to auto-pick first match
    return { firstMatchValue, totalMatches };
}

// Add enter key support to studio search input
document.addEventListener('DOMContentLoaded', () => {
    const studioSearch = document.getElementById('studio-user-search');
    if (studioSearch) {
        studioSearch.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const res = filterStudioUserDropdown(studioSearch.value);
                if (res && res.firstMatchValue) {
                    const selectEl = document.getElementById('privilege-user-select');
                    if (selectEl) {
                        selectEl.value = res.firstMatchValue;
                        onPrivilegeUserSelected(res.firstMatchValue);
                        showToast('Loaded account: ' + selectEl.options[selectEl.selectedIndex].text, 'success');
                    }
                }
            }
        });
    }
});

function filterMatrixTable() {
    const searchInput = document.getElementById('matrix-search-input');
    const roleSelect = document.getElementById('matrix-filter-role');
    const tbody = document.getElementById('privileges-matrix-tbody');
    if (!tbody) return;

    const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
    const role = (roleSelect ? roleSelect.value : '').toLowerCase();

    const rows = tbody.querySelectorAll('tr:not(.no-results-row)');
    let visibleCount = 0;

    rows.forEach(row => {
        const empId = (row.getAttribute('data-empid') || '').toLowerCase();
        const username = (row.getAttribute('data-username') || '').toLowerCase();
        const fullname = (row.getAttribute('data-fullname') || '').toLowerCase();
        const email = (row.getAttribute('data-email') || '').toLowerCase();
        const privs = (row.getAttribute('data-privs') || '').toLowerCase();
        const userRole = (row.getAttribute('data-role') || '').toLowerCase();

        const matchesQuery = !query || 
            empId.includes(query) || 
            username.includes(query) || 
            fullname.includes(query) || 
            email.includes(query) || 
            privs.includes(query);

        const matchesRole = !role || userRole === role;

        if (matchesQuery && matchesRole) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    let emptyRow = tbody.querySelector('.no-results-row');
    if (visibleCount === 0) {
        if (!emptyRow) {
            emptyRow = document.createElement('tr');
            emptyRow.className = 'no-results-row';
            emptyRow.innerHTML = `
                <td colspan="6" style="text-align: center; padding: 35px 20px; color: #94a3b8;">
                    <i class="fas fa-search" style="font-size: 26px; margin-bottom: 10px; color: var(--accent, #ff5e00); opacity: 0.7; display: block;"></i>
                    <strong style="font-size: 15px; color: #fff; display: block; margin-bottom: 4px;">No matching accounts found</strong>
                    <span style="font-size: 13px;">Try adjusting your search query or role filter.</span>
                </td>
            `;
            tbody.appendChild(emptyRow);
        } else {
            emptyRow.style.display = '';
        }
    } else if (emptyRow) {
        emptyRow.style.display = 'none';
    }
}


// Administrator Delete Request Flow
function openDeleteRequestModal(userId, username) {
    document.getElementById('delreq-user-id').value = userId;
    document.getElementById('delreq-username').innerText = `${username} (ID: ${userId})`;
    document.getElementById('delreq-reason').value = '';
    openModal('modal-delete-request');
}

function submitDeleteRequestForm(e) {
    e.preventDefault();
    const csrfToken = document.getElementById('csrf_token_val').value;
    const userId = document.getElementById('delreq-user-id').value;
    const reason = document.getElementById('delreq-reason').value;

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'request_delete',
            user_id: userId,
            reason: reason,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('modal-delete-request');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(data.message, 'error');
        }
    });
}

// Super Admin Direct Delete
function openDirectDeleteModal(userId, username) {
    document.getElementById('direct-del-user-id').value = userId;
    document.getElementById('direct-del-username').innerText = `${username} (ID: ${userId})`;
    openModal('modal-direct-delete');
}

function confirmDirectDelete() {
    const csrfToken = document.getElementById('csrf_token_val').value;
    const userId = document.getElementById('direct-del-user-id').value;

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'delete_account',
            user_id: userId,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('modal-direct-delete');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(data.message, 'error');
        }
    });
}

// Super Admin Review Delete Request
function openReviewDeleteRequestModal(requestId) {
    const csrfToken = document.getElementById('csrf_token_val').value;

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'get_delete_request_details',
            request_id: requestId,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.data) {
            const d = data.data;
            document.getElementById('review-req-id').value = d.id;
            document.getElementById('review-req-reason').innerText = d.reason;
            document.getElementById('review-req-by').innerText = `${d.requester_fname || ''} ${d.requester_lname || ''} (@${d.requester_username || d.requested_by})`;
            document.getElementById('review-req-date').innerText = d.requested_at;

            // Target user full info
            document.getElementById('review-target-name').innerText = `${d.first_name} ${d.middle_name || ''} ${d.last_name}`;
            document.getElementById('review-target-id').innerText = d.target_user_id;
            document.getElementById('review-target-username').innerText = d.username;
            document.getElementById('review-target-email').innerText = d.email;
            document.getElementById('review-target-role').innerText = d.role;
            document.getElementById('review-target-address').innerText = `${d.purok_street}, ${d.barangay}, ${d.city_municipality}, ${d.province}`;

            openModal('modal-review-delete-request');
        } else {
            showToast(data.message || 'Failed to load request details', 'error');
        }
    });
}

function processDeleteRequest(decision) {
    const csrfToken = document.getElementById('csrf_token_val').value;
    const requestId = document.getElementById('review-req-id').value;

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'review_delete_request',
            request_id: requestId,
            decision: decision,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('modal-review-delete-request');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(data.message, 'error');
        }
    });
}

// Super Admin Create Account Form Submit
function submitCreateAccountForm(e) {
    e.preventDefault();
    const csrfToken = document.getElementById('csrf_token_val').value;

    const privKeys = [
        'can_approve_users', 'can_block_users', 'can_update_info', 'can_manage_roles', 'can_create_accounts',
        'can_delete_users', 'can_manage_requests', 'can_give_privileges',
        'can_view_reports', 'can_export_logs',
        'can_manage_classes', 'can_manage_bookings', 'can_manage_metrics'
    ];

    const privileges = {};
    privKeys.forEach(k => {
        const el = document.getElementById('create_priv_' + k);
        if (el) {
            privileges[k] = el.checked;
        }
    });

    const payload = {
        action: 'create_account',
        csrf_token: csrfToken,
        id_number: document.getElementById('create-id-number').value,
        username: document.getElementById('create-username').value,
        password: document.getElementById('create-password').value,
        first_name: document.getElementById('create-firstname').value,
        middle_name: document.getElementById('create-middlename').value,
        last_name: document.getElementById('create-lastname').value,
        extension_name: document.getElementById('create-extension').value,
        birthdate: document.getElementById('create-birthdate').value,
        email: document.getElementById('create-email').value,
        sex: document.getElementById('create-sex').value,
        purok_street: document.getElementById('create-purok').value,
        barangay: document.getElementById('create-barangay').value,
        city_municipality: document.getElementById('create-city').value,
        province: document.getElementById('create-province').value,
        zip_code: document.getElementById('create-zip').value,
        role: document.getElementById('create-role').value,
        status: document.getElementById('create-status').value,
        privileges: privileges
    };

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            document.getElementById('form-create-account').reset();
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(data.message, 'error');
        }
    });
}
