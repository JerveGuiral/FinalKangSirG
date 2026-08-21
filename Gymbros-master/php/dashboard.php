<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/auth.php';

// Redirect to login if not logged in
if (!Auth::isLoggedIn()) {
  header("Location: login.php");
  exit();
}

$user = $_SESSION['user'];
$isSuperAdmin = Auth::isSuperAdmin();
$isAdmin = Auth::isAdmin();

// Database queries if Admin or Super Admin
$db = new Database();
$conn = $db->getConnection();

$allUsers = [];
$stats = [
  'total_users' => 0,
  'total_admins' => 0,
  'total_superadmins' => 0,
  'pending_count' => 0,
  'blocked_count' => 0,
  'delete_requests_count' => 0
];

if ($isAdmin) {
  // Fetch stats
  $res = $conn->query("SELECT role, status, COUNT(*) as cnt FROM users GROUP BY role, status");
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      if ($row['role'] === 'superadmin') $stats['total_superadmins'] += $row['cnt'];
      if ($row['role'] === 'admin') $stats['total_admins'] += $row['cnt'];
      if ($row['role'] === 'user') $stats['total_users'] += $row['cnt'];
      if ($row['status'] === 'pending') $stats['pending_count'] += $row['cnt'];
      if ($row['status'] === 'blocked') $stats['blocked_count'] += $row['cnt'];
    }
  }

  // Fetch delete requests count
  $resDel = $conn->query("SELECT COUNT(*) as cnt FROM delete_requests WHERE status = 'pending'");
  if ($resDel) {
    $stats['delete_requests_count'] = (int)$resDel->fetch_assoc()['cnt'];
  }

  // Fetch all users
  $resUsers = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
  if ($resUsers) {
    while ($r = $resUsers->fetch_assoc()) {
      $allUsers[] = $r;
    }
  }
}

$pendingRequestsTotal = $stats['pending_count'] + $stats['delete_requests_count'];

// Calculate member since duration
$joinDate = new DateTime($user['created_at'] ?? date('Y-m-d H:i:s'));
$currentDate = new DateTime();
$membershipDuration = $currentDate->diff($joinDate);
$months = ($membershipDuration->y * 12) + $membershipDuration->m;
$memberSinceText = $months > 0 ? "$months month" . ($months > 1 ? 's' : '') : 'Less than a month';

$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $isSuperAdmin ? 'Super Admin Console' : ($isAdmin ? 'Admin Console' : 'Dashboard'); ?> | GymBros</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
  <?php if ($isAdmin): ?>
    <link rel="stylesheet" href="../css/admin.css">
  <?php endif; ?>
</head>

<body>
  <input type="hidden" id="csrf_token_val" value="<?php echo $csrfToken; ?>">

  <!-- Loading Animation -->
  <div class="page-loader">
    <div class="loader">
      <div class="dumbbell">
        <div class="bar"></div>
        <div class="weight left"></div>
        <div class="weight right"></div>
      </div>
      <p>Loading Console...</p>
    </div>
  </div>

  <?php if ($isAdmin): ?>
    <!-- ADMIN SIDEBAR LAYOUT WRAPPER -->
    <div class="admin-layout-wrapper">
      <!-- SIDEBAR -->
      <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-brand">
          <div class="logo">
            <h1>Gym<span>Bros</span></h1>
          </div>
          <div class="role-badge-pill">
            <?php if ($isSuperAdmin): ?>
              <span class="badge-superadmin"><i class="fas fa-crown"></i> SUPER ADMIN</span>
            <?php else: ?>
              <span class="badge-admin"><i class="fas fa-shield-alt"></i> ADMIN PORTAL</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="sidebar-user">
          <div class="user-avatar">
            <i class="fas fa-user-circle"></i>
          </div>
          <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
            <div class="user-id">ID: <?php echo htmlspecialchars($user['id_number']); ?></div>
          </div>
        </div>

        <nav class="sidebar-nav">
          <ul>
            <li>
              <a href="dashboard.php" class="active">
                <i class="fas fa-users-cog"></i> <span>Accounts Console</span>
              </a>
            </li>
            <li>
              <a href="admin_requests.php">
                <i class="fas fa-clipboard-list"></i> <span>Requests Queue</span>
                <?php if ($pendingRequestsTotal > 0): ?>
                  <span class="nav-badge"><?php echo $pendingRequestsTotal; ?></span>
                <?php endif; ?>
              </a>
            </li>
            <?php if ($isSuperAdmin): ?>
              <li>
                <a href="create_account.php">
                  <i class="fas fa-user-plus"></i> <span>Create Account</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if ($isSuperAdmin || Auth::hasPrivilege('can_view_reports')): ?>
              <li>
                <a href="logs.php">
                  <i class="fas fa-history"></i> <span>System Logs</span>
                </a>
              </li>
            <?php endif; ?>
            <li class="nav-divider"></li>
            <li>
              <a href="change-password.php">
                <i class="fas fa-key"></i> <span>Change Password</span>
              </a>
            </li>
            <li>
              <a href="logout.php" class="nav-logout">
                <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
              </a>
            </li>
          </ul>
        </nav>
      </aside>

      <!-- MAIN CONTENT WRAPPER -->
      <div class="admin-main-wrapper">
        <header class="admin-topbar">
          <button class="sidebar-toggle-btn" id="sidebarToggleBtn" title="Toggle Sidebar Navigation">
            <i class="fas fa-bars"></i>
          </button>
          <div class="topbar-title">
            <h3><i class="fas fa-users-cog"></i> All Accounts Management</h3>
          </div>
          <div class="topbar-right">
            <div class="topbar-user-chip">
              <i class="fas fa-user-shield"></i>
              <span>@<?php echo htmlspecialchars($user['username']); ?></span>
            </div>
            <a href="logout.php" class="btn-topbar-logout" title="Logout">
              <i class="fas fa-sign-out-alt"></i> Logout
            </a>
          </div>
        </header>

        <div class="admin-page-content">
          <!-- Header Banner -->
          <div class="admin-header">
            <div class="admin-header-title">
              <h2><i class="fas fa-shield-alt"></i> <?php echo $isSuperAdmin ? 'Super Administrator Console' : 'Administrator Control Panel'; ?></h2>
              <p>Filter, edit, manage permissions, and maintain system user accounts.</p>
            </div>

            <div class="admin-stat-pills">
              <div class="stat-pill pill-users">
                <i class="fas fa-users"></i>
                <div class="stat-pill-info">
                  <div class="num"><?php echo count($allUsers); ?></div>
                  <div class="lbl">Total Accounts</div>
                </div>
              </div>

              <div class="stat-pill pill-pending">
                <i class="fas fa-user-clock"></i>
                <div class="stat-pill-info">
                  <div class="num"><?php echo $stats['pending_count']; ?></div>
                  <div class="lbl">Pending Users</div>
                </div>
              </div>

              <?php if ($isSuperAdmin): ?>
                <div class="stat-pill pill-requests">
                  <i class="fas fa-trash-restore"></i>
                  <div class="stat-pill-info">
                    <div class="num"><?php echo $stats['delete_requests_count']; ?></div>
                    <div class="lbl">Delete Requests</div>
                  </div>
                </div>
              <?php endif; ?>

              <div class="stat-pill pill-blocked">
                <i class="fas fa-user-slash"></i>
                <div class="stat-pill-info">
                  <div class="num"><?php echo $stats['blocked_count']; ?></div>
                  <div class="lbl">Blocked Accounts</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Filter & Control Bar -->
          <div class="admin-controls-bar">
            <div class="search-box">
              <i class="fas fa-search"></i>
              <input type="text" id="search-employee-id" class="search-input" placeholder="Filter by Employee ID, Username, or Full Name...">
            </div>
            <div class="filter-group">
              <select id="filter-role" class="filter-select">
                <option value="">All Roles</option>
                <option value="superadmin">Super Admin</option>
                <option value="admin">Administrator</option>
                <option value="user">User</option>
              </select>
              <select id="filter-status" class="filter-select">
                <option value="">All Statuses</option>
                <option value="approved">Approved</option>
                <option value="pending">Pending</option>
                <option value="blocked">Blocked</option>
              </select>
            </div>
          </div>

          <!-- All Accounts Data Table -->
          <div class="table-responsive">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Employee ID</th>
                  <th>User Information</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th>Registered Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="all-users-table-body">
                <?php foreach ($allUsers as $u): ?>
                  <tr data-empid="<?php echo htmlspecialchars($u['id_number']); ?>"
                      data-username="<?php echo htmlspecialchars($u['username']); ?>"
                      data-fullname="<?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>"
                      data-role="<?php echo htmlspecialchars($u['role'] ?? 'user'); ?>"
                      data-status="<?php echo htmlspecialchars($u['status'] ?? 'approved'); ?>">
                    <td><strong><?php echo htmlspecialchars($u['id_number']); ?></strong></td>
                    <td>
                      <div style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></div>
                      <div style="font-size: 12px; color: #94a3b8;">@<?php echo htmlspecialchars($u['username']); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td>
                      <?php if ($u['role'] === 'superadmin'): ?>
                        <span class="badge badge-superadmin"><i class="fas fa-crown"></i> Super Admin</span>
                      <?php elseif ($u['role'] === 'admin'): ?>
                        <span class="badge badge-admin"><i class="fas fa-user-shield"></i> Admin</span>
                      <?php else: ?>
                        <span class="badge badge-user"><i class="fas fa-user"></i> User</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if (($u['status'] ?? 'approved') === 'approved'): ?>
                        <span class="badge badge-approved"><i class="fas fa-check-circle"></i> Approved</span>
                      <?php elseif ($u['status'] === 'pending'): ?>
                        <span class="badge badge-pending"><i class="fas fa-clock"></i> Pending</span>
                      <?php else: ?>
                        <span class="badge badge-blocked"><i class="fas fa-ban"></i> Blocked</span>
                      <?php endif; ?>
                    </td>
                    <td style="font-size: 13px; color: #94a3b8;"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                    <td>
                      <div class="action-btns">
                        <!-- Status Toggle -->
                        <?php if (($u['status'] ?? 'approved') === 'pending'): ?>
                          <button class="btn-icon btn-approve" onclick="updateUserStatus('<?php echo $u['id_number']; ?>', 'approved')" title="Approve Registration">
                            <i class="fas fa-check"></i>
                          </button>
                        <?php elseif (($u['status'] ?? 'approved') === 'blocked'): ?>
                          <button class="btn-icon btn-unblock" onclick="updateUserStatus('<?php echo $u['id_number']; ?>', 'approved')" title="Unblock Account">
                            <i class="fas fa-unlock"></i>
                          </button>
                        <?php endif; ?>

                        <?php if (($u['status'] ?? 'approved') !== 'blocked' && $u['id_number'] !== $user['id_number'] && ($u['role'] !== 'superadmin' || $isSuperAdmin)): ?>
                          <button class="btn-icon btn-block" onclick="updateUserStatus('<?php echo $u['id_number']; ?>', 'blocked')" title="Block User">
                            <i class="fas fa-ban"></i>
                          </button>
                        <?php endif; ?>

                        <!-- Edit Info -->
                        <?php if ($isSuperAdmin || $u['role'] !== 'superadmin'): ?>
                          <button class="btn-icon btn-edit" onclick="openEditUserModal('<?php echo $u['id_number']; ?>')" title="Update Account Info">
                            <i class="fas fa-edit"></i>
                          </button>
                        <?php endif; ?>

                        <!-- Super Admin Privileges Modal -->
                        <?php if ($isSuperAdmin): ?>
                          <button class="btn-icon btn-privileges" onclick="openPrivilegesModal('<?php echo $u['id_number']; ?>')" title="Manage Privileges">
                            <i class="fas fa-user-lock"></i>
                          </button>
                        <?php endif; ?>

                        <!-- Delete Action -->
                        <?php if ($isSuperAdmin): ?>
                          <?php if ($u['id_number'] !== $user['id_number']): ?>
                            <button class="btn-icon btn-delete" onclick="openDirectDeleteModal('<?php echo $u['id_number']; ?>', '<?php echo htmlspecialchars($u['username']); ?>')" title="Direct Delete (Super Admin)">
                              <i class="fas fa-trash-alt"></i>
                            </button>
                          <?php endif; ?>
                        <?php else: ?>
                          <!-- Admin Delete Request -->
                          <?php if ($u['id_number'] !== $user['id_number']): ?>
                            <button class="btn-icon btn-delete" onclick="openDeleteRequestModal('<?php echo $u['id_number']; ?>', '<?php echo htmlspecialchars($u['username']); ?>')" title="Request Deletion (Sends to Super Admin)">
                              <i class="fas fa-trash"></i>
                            </button>
                          <?php endif; ?>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <footer class="admin-footer">
          <div class="admin-footer-brand">
            <div class="logo"><h1>Gym<span>Bros</span></h1></div>
            <p>Management & Administration Portal</p>
          </div>
          <p class="admin-footer-copyright">© <?php echo date('Y'); ?> GymBros. All rights reserved.</p>
        </footer>
      </div>
    </div>

    <!-- MODALS FOR ADMIN ACTIONS (Admin / Super Admin Only) -->
    <!-- 1. EDIT USER INFO MODAL -->
    <div class="modal-overlay" id="modal-edit-user">
      <div class="modal-card">
        <div class="modal-header">
          <h3><i class="fas fa-user-edit"></i> Update Account Details</h3>
          <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <form onsubmit="submitEditUserForm(event)">
          <div class="modal-body">
            <input type="hidden" id="edit-id-number">
            <div class="form-grid">
              <div class="form-field"><label>Username</label><input type="text" id="edit-username" required></div>
              <div class="form-field"><label>First Name</label><input type="text" id="edit-firstname" required></div>
              <div class="form-field"><label>Middle Name</label><input type="text" id="edit-middlename"></div>
              <div class="form-field"><label>Last Name</label><input type="text" id="edit-lastname" required></div>
              <div class="form-field"><label>Email</label><input type="email" id="edit-email" required></div>
              <div class="form-field"><label>Birthdate</label><input type="date" id="edit-birthdate"></div>
              <div class="form-field">
                <label>Sex</label>
                <select id="edit-sex">
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <?php if ($isSuperAdmin): ?>
                <div class="form-field">
                  <label>Role</label>
                  <select id="edit-role">
                    <option value="user">User</option>
                    <option value="admin">Administrator</option>
                    <option value="superadmin">Super Administrator</option>
                  </select>
                </div>
              <?php endif; ?>
              <div class="form-field">
                <label>Status</label>
                <select id="edit-status">
                  <option value="approved">Approved</option>
                  <option value="pending">Pending</option>
                  <option value="blocked">Blocked</option>
                </select>
              </div>
              <div class="form-field"><label>Purok / Street</label><input type="text" id="edit-purok"></div>
              <div class="form-field"><label>Barangay</label><input type="text" id="edit-barangay"></div>
              <div class="form-field"><label>City / Municipality</label><input type="text" id="edit-city"></div>
              <div class="form-field"><label>Province</label><input type="text" id="edit-province"></div>
              <div class="form-field"><label>Zip Code</label><input type="text" id="edit-zip"></div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-secondary-action btn-modal-close">Cancel</button>
            <button type="submit" class="btn-primary-action"><i class="fas fa-save"></i> Save Changes</button>
          </div>
        </form>
      </div>
    </div>

    <!-- 2. GIVE PRIVILEGES MODAL (Super Admin Only) -->
    <?php if ($isSuperAdmin): ?>
      <div class="modal-overlay" id="modal-privileges">
        <div class="modal-card">
          <div class="modal-header">
            <h3><i class="fas fa-user-shield"></i> Manage Account Privileges</h3>
            <button class="modal-close"><i class="fas fa-times"></i></button>
          </div>
          <form onsubmit="submitPrivilegesForm(event)">
            <div class="modal-body">
              <input type="hidden" id="priv-target-user-id">
              <p style="margin-bottom: 15px; color: #cbd5e1;">Configuring custom permissions for: <strong id="priv-target-name" style="color: var(--accent);"></strong></p>

              <div class="privilege-checkbox-grid">
                <label class="privilege-item"><input type="checkbox" id="priv_can_approve"><div><strong>Accept / Approve Users</strong><div style="font-size: 11px; color: #94a3b8;">Can approve user registrations</div></div></label>
                <label class="privilege-item"><input type="checkbox" id="priv_can_update"><div><strong>Update Account Info</strong><div style="font-size: 11px; color: #94a3b8;">Can edit details of users & admins</div></div></label>
                <label class="privilege-item"><input type="checkbox" id="priv_can_manage_roles"><div><strong>Manage Roles</strong><div style="font-size: 11px; color: #94a3b8;">Can modify assigned user roles</div></div></label>
                <label class="privilege-item"><input type="checkbox" id="priv_can_view_reports"><div><strong>View System Logs</strong><div style="font-size: 11px; color: #94a3b8;">Access audit logs & system statistics</div></div></label>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn-secondary-action btn-modal-close">Cancel</button>
              <button type="submit" class="btn-primary-action"><i class="fas fa-key"></i> Update Privileges</button>
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <!-- 3. ADMIN DELETE REQUEST MODAL -->
    <div class="modal-overlay" id="modal-delete-request">
      <div class="modal-card" style="max-width: 500px;">
        <div class="modal-header">
          <h3><i class="fas fa-trash-alt"></i> Request Account Deletion</h3>
          <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <form onsubmit="submitDeleteRequestForm(event)">
          <div class="modal-body">
            <input type="hidden" id="delreq-user-id">
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 10px; padding: 12px 15px; margin-bottom: 15px;">
              <p style="font-size: 13px; color: #f87171;">
                <i class="fas fa-info-circle"></i> As an Administrator, deleting an account submits a formal deletion request to the <strong>Super Administrator</strong> for review and final approval.
              </p>
            </div>
            <p style="margin-bottom: 15px; font-size: 14px; color: #fff;">
              Target Account: <strong id="delreq-username" style="color: var(--accent);"></strong>
            </p>
            <div class="form-field">
              <label>Reason for Deletion *</label>
              <textarea id="delreq-reason" placeholder="Please explain why this user should be deleted (required for Super Admin review)..." required></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-secondary-action btn-modal-close">Cancel</button>
            <button type="submit" class="btn-primary-action" style="background: #dc2626;"><i class="fas fa-paper-plane"></i> Submit Request</button>
          </div>
        </form>
      </div>
    </div>

    <!-- 4. SUPER ADMIN DIRECT DELETE CONFIRMATION MODAL -->
    <?php if ($isSuperAdmin): ?>
      <div class="modal-overlay" id="modal-direct-delete">
        <div class="modal-card" style="max-width: 480px;">
          <div class="modal-header">
            <h3 style="color: #ef4444;"><i class="fas fa-exclamation-triangle"></i> Confirm Permanent Deletion</h3>
            <button class="modal-close"><i class="fas fa-times"></i></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="direct-del-user-id">
            <p style="font-size: 14px; color: #cbd5e1; margin-bottom: 10px;">Are you sure you want to permanently delete this account?</p>
            <p style="font-size: 16px; font-weight: 700; color: #ef4444; margin-bottom: 15px;" id="direct-del-username"></p>
            <p style="font-size: 12px; color: #94a3b8;">This action cannot be undone.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-secondary-action btn-modal-close">Cancel</button>
            <button type="button" class="btn-primary-action" style="background: #dc2626;" onclick="confirmDirectDelete()"><i class="fas fa-trash-alt"></i> Delete Permanently</button>
          </div>
        </div>
      </div>
    <?php endif; ?>

  <?php else: ?>

    <!-- REGULAR USER DASHBOARD (Standard Gym Member View) -->
    <header>
      <div class="logo">
        <h1>Gym<span>Bros</span></h1>
      </div>
      <div class="navBar">
        <ul>
          <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
          <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <?php if (Auth::hasPrivilege('can_view_reports')): ?>
            <li><a href="logs.php"><i class="fas fa-history"></i> Logs</a></li>
          <?php endif; ?>
          <li><a href="change-password.php"><i class="fas fa-key"></i> Change Password</a></li>
          <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
      </div>
    </header>

    <main>
      <section class="dashboard">
        <div class="welcome-card">
          <div class="welcome-header">
            <div class="user-greeting">
              <h2>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h2>
              <p>Great to see you again. Ready for your next workout?</p>
            </div>
            <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
          </div>
          <div class="welcome-stats">
            <div class="stat-item"><span class="stat-value"><?php echo htmlspecialchars($user['id_number']); ?></span><span class="stat-label">Member ID</span></div>
            <div class="stat-item"><span class="stat-value"><?php echo $memberSinceText; ?></span><span class="stat-label">Member Since</span></div>
            <div class="stat-item"><span class="stat-value" style="color: #4ade80;">Active</span><span class="stat-label">Status</span></div>
          </div>
        </div>

        <div class="stats-grid">
          <div class="stat-card"><div class="stat-icon"><i class="fas fa-dumbbell"></i></div><div class="stat-content"><h3>24</h3><p>Workouts Completed</p></div></div>
          <div class="stat-card"><div class="stat-icon"><i class="fas fa-fire"></i></div><div class="stat-content"><h3>8,450</h3><p>Calories Burned</p></div></div>
          <div class="stat-card"><div class="stat-icon"><i class="fas fa-calendar-check"></i></div><div class="stat-content"><h3>7 days</h3><p>Current Streak</p></div></div>
          <div class="stat-card"><div class="stat-icon"><i class="fas fa-heartbeat"></i></div><div class="stat-content"><h3>24/7</h3><p>Gym Access</p></div></div>
        </div>

        <div class="dashboard-grid">
          <div class="dashboard-card profile-card">
            <div class="card-header"><i class="fas fa-user-circle"></i><h3>Profile Information</h3></div>
            <div class="card-content">
              <div class="info-item"><span class="info-label">Username:</span><span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span></div>
              <div class="info-item"><span class="info-label">Email:</span><span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span></div>
              <div class="info-item"><span class="info-label">Full Name:</span><span class="info-value"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span></div>
            </div>
          </div>
          <div class="dashboard-card actions-card">
            <div class="card-header"><i class="fas fa-bolt"></i><h3>Quick Actions</h3></div>
            <div class="card-content">
              <a href="change-password.php" class="action-btn"><i class="fas fa-key"></i><span>Change Password</span></a>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer>
      <div class="footer-content">
        <div class="footer-section"><div class="logo"><h1>Gym<span>Bros</span></h1></div><p>Your fitness journey starts here.</p></div>
        <div class="footer-section"><p class="copyright">© <?php echo date('Y'); ?> GymBros. All rights reserved.</p></div>
      </div>
    </footer>
  <?php endif; ?>

  <script src="../js/loader.js"></script>
  <?php if ($isAdmin): ?>
    <script src="../js/admin.js"></script>
  <?php endif; ?>
</body>

</html>