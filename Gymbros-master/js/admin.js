/* Admin Panel Interactive Script - GymBros */

function getAdminApiUrl() {
    return window.location.pathname.includes('/php/') ? 'admin_actions.php' : '../php/admin_actions.php';
}

document.addEventListener('DOMContentLoaded', () => {
    initAdminTabs();
    initFilterAndSearch();
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
        btn.addEventListener('click', () => {
            const targetTab = btn.getAttribute('data-tab');

            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanes.forEach(p => p.classList.add('hidden'));

            btn.classList.add('active');
            const pane = document.getElementById(`tab-${targetTab}`);
            if (pane) pane.classList.remove('hidden');
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
            document.getElementById('priv-target-user-id').value = u.id_number;
            document.getElementById('priv-target-name').innerText = `${u.first_name} ${u.last_name} (@${u.username})`;

            let privs = {};
            try {
                privs = u.privileges ? (typeof u.privileges === 'object' ? u.privileges : JSON.parse(u.privileges)) : {};
            } catch (e) {
                privs = {};
            }

            document.getElementById('priv_can_approve').checked = !!privs.can_approve_users;
            document.getElementById('priv_can_update').checked = !!privs.can_update_info;
            document.getElementById('priv_can_manage_roles').checked = !!privs.can_manage_roles;
            document.getElementById('priv_can_view_reports').checked = !!privs.can_view_reports;

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

    const privileges = {
        can_approve_users: document.getElementById('priv_can_approve').checked,
        can_update_info: document.getElementById('priv_can_update').checked,
        can_manage_roles: document.getElementById('priv_can_manage_roles').checked,
        can_view_reports: document.getElementById('priv_can_view_reports').checked
    };

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
        status: document.getElementById('create-status').value
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
