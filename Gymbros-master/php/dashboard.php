<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

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

// Member default variables initialization
$userWorkoutsCount = 0;
$userCaloriesTotal = 0;
$userStreak = 0;
$userWorkoutsList = [];
$userLatestMetric = null;
$availableClasses = [];
$myBookings = [];

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
} else {
  // Sync fresh user row from DB
  $stmtUser = $conn->prepare("SELECT * FROM users WHERE id_number = ? LIMIT 1");
  $stmtUser->bind_param("s", $user['id_number']);
  $stmtUser->execute();
  $uRes = $stmtUser->get_result();
  if ($uRes && $uRes->num_rows === 1) {
    $user = $uRes->fetch_assoc();
    $_SESSION['user'] = $user;
  }
  $stmtUser->close();

  // Workouts and calories
  $stmt = $conn->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(calories_burned), 0) as total_cal FROM user_workouts WHERE user_id = ?");
  $stmt->bind_param("s", $user['id_number']);
  $stmt->execute();
  $wStat = $stmt->get_result()->fetch_assoc();
  $userWorkoutsCount = (int)($wStat['cnt'] ?? 0);
  $userCaloriesTotal = (int)($wStat['total_cal'] ?? 0);
  $stmt->close();

  // Streak calculation
  $userStreak = 0;
  $stmt = $conn->prepare("SELECT DISTINCT workout_date FROM user_workouts WHERE user_id = ? ORDER BY workout_date DESC");
  $stmt->bind_param("s", $user['id_number']);
  $stmt->execute();
  $res = $stmt->get_result();
  $dates = [];
  while ($row = $res->fetch_assoc()) {
    $dates[] = $row['workout_date'];
  }
  $stmt->close();
  if (!empty($dates)) {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $checkDate = in_array($today, $dates) ? $today : (in_array($yesterday, $dates) ? $yesterday : null);
    if ($checkDate) {
      $curr = new DateTime($checkDate);
      $userStreak = 1;
      while (true) {
        $curr->modify('-1 day');
        if (in_array($curr->format('Y-m-d'), $dates)) {
          $userStreak++;
        } else {
          break;
        }
      }
    }
  }

  // Recent Workouts list
  $stmt = $conn->prepare("SELECT * FROM user_workouts WHERE user_id = ? ORDER BY workout_date DESC, created_at DESC LIMIT 15");
  $stmt->bind_param("s", $user['id_number']);
  $stmt->execute();
  $wRes = $stmt->get_result();
  while ($r = $wRes->fetch_assoc()) {
    $userWorkoutsList[] = $r;
  }
  $stmt->close();

  // Latest Body Metric & BMI
  $userLatestMetric = null;
  $stmt = $conn->prepare("SELECT * FROM user_body_metrics WHERE user_id = ? ORDER BY recorded_at DESC LIMIT 1");
  $stmt->bind_param("s", $user['id_number']);
  $stmt->execute();
  $mRes = $stmt->get_result();
  if ($mRes && $mRes->num_rows > 0) {
    $userLatestMetric = $mRes->fetch_assoc();
  }
  $stmt->close();

  // Available Gym Classes with booked counts
  $classRes = $conn->query("SELECT gc.*, 
    (SELECT COUNT(*) FROM class_bookings cb WHERE cb.class_id = gc.id AND cb.status = 'booked' AND cb.booking_date >= CURDATE()) as active_bookings,
    (SELECT COUNT(*) FROM class_bookings cb2 WHERE cb2.class_id = gc.id AND cb2.user_id = '{$user['id_number']}' AND cb2.status = 'booked' AND cb2.booking_date >= CURDATE()) as is_my_booked
    FROM gym_classes gc ORDER BY FIELD(schedule_day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), start_time ASC");
  if ($classRes) {
    while ($cr = $classRes->fetch_assoc()) {
      $availableClasses[] = $cr;
    }
  }

  // User's active Bookings
  $stmt = $conn->prepare("SELECT cb.*, gc.class_name, gc.instructor, gc.category, gc.room, gc.start_time, gc.end_time 
    FROM class_bookings cb 
    JOIN gym_classes gc ON cb.class_id = gc.id 
    WHERE cb.user_id = ? AND cb.status = 'booked' AND cb.booking_date >= CURDATE()
    ORDER BY cb.booking_date ASC, gc.start_time ASC");
  $stmt->bind_param("s", $user['id_number']);
  $stmt->execute();
  $bRes = $stmt->get_result();
  while ($br = $bRes->fetch_assoc()) {
    $myBookings[] = $br;
  }
  $stmt->close();
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
  <link rel="stylesheet" href="../css/style.css?v=<?php echo time(); ?>">
  <?php if ($isAdmin): ?>
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
  <?php else: ?>
    <link rel="stylesheet" href="../css/dashboard.css?v=<?php echo time(); ?>">
  <?php endif; ?>
</head>

<body>
  <input type="hidden" id="csrf_token_val" value="<?php echo $csrfToken; ?>">

  <!-- Loading Animation -->
  <div class="page-loader" id="mainPageLoader">
    <div class="loader">
      <div class="dumbbell">
        <div class="bar"></div>
        <div class="weight left"></div>
        <div class="weight right"></div>
      </div>
      <p><?php echo $isAdmin ? 'Loading Console...' : 'Loading GymBros...'; ?></p>
    </div>
  </div>
  <script>
    // Immediate fallback: Auto-dismiss loader without waiting for heavy external assets
    (function() {
      setTimeout(function() {
        var el = document.getElementById('mainPageLoader') || document.querySelector('.page-loader');
        if (el) {
          el.style.opacity = '0';
          el.style.pointerEvents = 'none';
          setTimeout(function() { el.style.display = 'none'; }, 200);
        }
      }, 300);
    })();
  </script>

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

    <!-- REGULAR USER DASHBOARD (Full-Featured Member Experience) -->
    <header>
      <div class="logo">
        <h1>Gym<span>Bros</span></h1>
      </div>
      <div class="navBar">
        <ul>
          <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
          <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <?php if (Auth::hasPrivilege('can_view_reports')): ?>
            <li><a href="logs.php"><i class="fas fa-history"></i> System Logs</a></li>
          <?php endif; ?>
          <li><a href="change-password.php"><i class="fas fa-key"></i> Change Password</a></li>
          <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
      </div>
    </header>

    <main>
      <div class="user-dashboard-wrapper">
        <!-- Hero Welcome Banner -->
        <div class="member-hero-banner">
          <div class="member-hero-left">
            <div class="member-avatar-lg">
              <i class="fas fa-dumbbell"></i>
            </div>
            <div class="member-greeting">
              <h2>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h2>
              <p>
                <span><i class="fas fa-id-badge" style="color: var(--accent-orange);"></i> ID: <?php echo htmlspecialchars($user['id_number']); ?></span>
                <span>•</span>
                <span><i class="fas fa-bullseye" style="color: #34d399;"></i> Goal: <?php echo htmlspecialchars($user['fitness_goal'] ?? 'Muscle Building & Fitness'); ?></span>
                <span class="member-tier-badge"><i class="fas fa-crown"></i> <?php echo strtoupper(htmlspecialchars($user['membership_tier'] ?? 'GOLD')); ?> VIP</span>
              </p>
            </div>
          </div>
          <div class="member-hero-actions">
            <button class="btn-hero-primary" onclick="openModal('modal-log-workout')">
              <i class="fas fa-plus-circle"></i> Log Workout
            </button>
            <button class="btn-hero-secondary" onclick="switchDashboardTab('tab-pass')">
              <i class="fas fa-qrcode"></i> Digital Pass
            </button>
            <button class="btn-hero-secondary" onclick="openModal('modal-edit-profile')">
              <i class="fas fa-user-edit"></i> Edit Profile
            </button>
          </div>
        </div>

        <!-- Live Fitness Stat Cards -->
        <div class="user-stat-grid">
          <div class="user-stat-card">
            <div class="user-stat-icon icon-workouts">
              <i class="fas fa-dumbbell"></i>
            </div>
            <div class="user-stat-info">
              <h3 id="stat-workout-count"><?php echo $userWorkoutsCount; ?></h3>
              <p>Workouts Completed</p>
            </div>
          </div>

          <div class="user-stat-card">
            <div class="user-stat-icon icon-calories">
              <i class="fas fa-fire"></i>
            </div>
            <div class="user-stat-info">
              <h3 id="stat-calories-total"><?php echo number_format($userCaloriesTotal); ?> kcal</h3>
              <p>Total Calories Burned</p>
            </div>
          </div>

          <div class="user-stat-card">
            <div class="user-stat-icon icon-streak">
              <i class="fas fa-calendar-check"></i>
            </div>
            <div class="user-stat-info">
              <h3 id="stat-streak-days"><?php echo $userStreak; ?> days</h3>
              <p>Active Workout Streak</p>
            </div>
          </div>

          <div class="user-stat-card" style="cursor: pointer;" onclick="switchDashboardTab('tab-metrics')">
            <div class="user-stat-icon icon-bmi">
              <i class="fas fa-heartbeat"></i>
            </div>
            <div class="user-stat-info">
              <h3 id="stat-bmi-num"><?php echo (!empty($userLatestMetric) && !empty($userLatestMetric['bmi'])) ? $userLatestMetric['bmi'] : 'Calculate'; ?></h3>
              <p><?php echo (!empty($userLatestMetric) && !empty($userLatestMetric['bmi'])) ? 'Body Mass Index (BMI)' : 'Track Your BMI'; ?></p>
            </div>
          </div>
        </div>

        <!-- Dashboard Feature Navigation Tabs -->
        <div class="dashboard-nav-tabs">
          <button class="dashboard-tab-btn active" data-tab="tab-workouts">
            <i class="fas fa-running"></i> <span>Workout Activity</span>
          </button>
          <button class="dashboard-tab-btn" data-tab="tab-classes">
            <i class="fas fa-calendar-alt"></i> <span>Class Booking</span>
            <?php if (count($myBookings) > 0): ?>
              <span style="background: var(--accent-orange); color:#fff; font-size:11px; padding:2px 7px; border-radius:10px;"><?php echo count($myBookings); ?></span>
            <?php endif; ?>
          </button>
          <button class="dashboard-tab-btn" data-tab="tab-pass">
            <i class="fas fa-id-card"></i> <span>Digital Member Pass</span>
          </button>
          <button class="dashboard-tab-btn" data-tab="tab-metrics">
            <i class="fas fa-weight"></i> <span>BMI & Body Metrics</span>
          </button>
          <button class="dashboard-tab-btn" data-tab="tab-profile">
            <i class="fas fa-user-circle"></i> <span>My Profile</span>
          </button>
        </div>

        <!-- TAB 1: WORKOUT ACTIVITY & LOGGER -->
        <div class="tab-pane active" id="tab-workouts">
          <div class="dash-card">
            <div class="dash-card-header">
              <h3><i class="fas fa-dumbbell"></i> Workout History & Activity Log</h3>
              <button class="btn-hero-primary" style="padding: 9px 18px; font-size: 13px;" onclick="openModal('modal-log-workout')">
                <i class="fas fa-plus"></i> Record New Workout
              </button>
            </div>

            <div class="workout-table-wrapper">
              <table class="workout-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Exercise / Workout</th>
                    <th>Muscle Group</th>
                    <th>Sets & Reps</th>
                    <th>Duration</th>
                    <th>Calories</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody id="workoutTableBody">
                  <?php if (empty($userWorkoutsList)): ?>
                    <tr>
                      <td colspan="7" style="text-align: center; color: #94a3b8; padding: 35px;">
                        <i class="fas fa-dumbbell" style="font-size: 28px; margin-bottom: 12px; display: block; color: #64748b;"></i>
                        No workouts logged yet! Start tracking your reps and burn calories today.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($userWorkoutsList as $w): ?>
                      <tr id="workout-row-<?php echo $w['id']; ?>">
                        <td><strong><?php echo htmlspecialchars($w['workout_date']); ?></strong></td>
                        <td><strong><?php echo htmlspecialchars($w['workout_name']); ?></strong></td>
                        <td><span class="muscle-badge"><?php echo htmlspecialchars($w['muscle_group']); ?></span></td>
                        <td><?php echo (int)$w['sets_count']; ?> sets × <?php echo (int)$w['reps_count']; ?> reps <?php if (!empty($w['weight_lifted']) && $w['weight_lifted'] > 0): ?>(<?php echo (float)$w['weight_lifted']; ?> kg)<?php endif; ?></td>
                        <td><?php echo (int)$w['duration_minutes']; ?> mins</td>
                        <td><span style="color: #f87171; font-weight: 600;">🔥 <?php echo (int)$w['calories_burned']; ?> kcal</span></td>
                        <td>
                          <button class="btn-del-workout" onclick="deleteWorkout(<?php echo $w['id']; ?>)" title="Delete Workout">
                            <i class="fas fa-trash"></i>
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- TAB 2: GYM CLASS BOOKINGS & SCHEDULE -->
        <div class="tab-pane" id="tab-classes">
          <?php if (!empty($myBookings)): ?>
            <div class="dash-card">
              <div class="dash-card-header">
                <h3><i class="fas fa-clipboard-check" style="color: #10b981;"></i> My Upcoming Class Reservations</h3>
              </div>
              <div class="classes-grid">
                <?php foreach ($myBookings as $mb): ?>
                  <div class="class-card" id="booking-card-<?php echo $mb['id']; ?>" style="border-color: rgba(16, 185, 129, 0.4);">
                    <div>
                      <div class="class-category-tag" style="color: #34d399;"><i class="fas fa-check-circle"></i> Confirmed Booking</div>
                      <h4><?php echo htmlspecialchars($mb['class_name']); ?></h4>
                      <div class="class-meta">
                        <div class="class-meta-item"><i class="fas fa-calendar-day"></i> <strong>Date: <?php echo date('M d, Y (l)', strtotime($mb['booking_date'])); ?></strong></div>
                        <div class="class-meta-item"><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($mb['start_time'])); ?> - <?php echo date('h:i A', strtotime($mb['end_time'])); ?></div>
                        <div class="class-meta-item"><i class="fas fa-user-tie"></i> Coach: <?php echo htmlspecialchars($mb['instructor']); ?></div>
                        <div class="class-meta-item"><i class="fas fa-map-marker-alt"></i> Room: <?php echo htmlspecialchars($mb['room']); ?></div>
                      </div>
                    </div>
                    <button class="btn-del-workout" style="width: 100%; padding: 10px; font-weight: 600;" onclick="cancelBooking(<?php echo $mb['id']; ?>)">
                      <i class="fas fa-times-circle"></i> Cancel Reservation
                    </button>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <div class="dash-card">
            <div class="dash-card-header">
              <h3><i class="fas fa-calendar-alt"></i> Weekly Class Schedule & Spot Booking</h3>
              <p style="color: var(--text-muted); font-size: 13px;">Reserve your spot in high-energy instructor-led group sessions.</p>
            </div>
            <div class="classes-grid">
              <?php foreach ($availableClasses as $gc): 
                $capPercent = round(((int)$gc['active_bookings'] / (int)$gc['max_capacity']) * 100);
                $isFull = (int)$gc['active_bookings'] >= (int)$gc['max_capacity'];
                $isBookedByMe = (int)$gc['is_my_booked'] > 0;
              ?>
                <div class="class-card">
                  <div>
                    <div class="class-category-tag"><?php echo htmlspecialchars($gc['category']); ?> • <?php echo htmlspecialchars($gc['difficulty']); ?></div>
                    <h4><?php echo htmlspecialchars($gc['class_name']); ?></h4>
                    <div class="class-meta">
                      <div class="class-meta-item"><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($gc['schedule_day']); ?></div>
                      <div class="class-meta-item"><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($gc['start_time'])); ?> - <?php echo date('h:i A', strtotime($gc['end_time'])); ?></div>
                      <div class="class-meta-item"><i class="fas fa-user-ninja"></i> Coach: <?php echo htmlspecialchars($gc['instructor']); ?></div>
                      <div class="class-meta-item"><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($gc['room']); ?></div>
                    </div>
                  </div>
                  <div>
                    <div class="class-capacity-bar">
                      <div class="capacity-text">
                        <span>Slots: <?php echo (int)$gc['active_bookings']; ?> / <?php echo (int)$gc['max_capacity']; ?></span>
                        <span><?php echo $capPercent; ?>% Full</span>
                      </div>
                      <div class="capacity-progress">
                        <div class="capacity-fill" style="width: <?php echo min(100, $capPercent); ?>%;"></div>
                      </div>
                    </div>
                    <?php if ($isBookedByMe): ?>
                      <button class="btn-book-class booked" disabled><i class="fas fa-check"></i> Already Reserved</button>
                    <?php elseif ($isFull): ?>
                      <button class="btn-book-class" style="background: rgba(239, 68, 68, 0.2); color:#f87171;" disabled><i class="fas fa-ban"></i> Class Full</button>
                    <?php else: ?>
                      <button class="btn-book-class" onclick="openBookClassModal(<?php echo $gc['id']; ?>, '<?php echo htmlspecialchars(addslashes($gc['class_name'])); ?>', '<?php echo htmlspecialchars(addslashes($gc['instructor'])); ?>', '<?php echo htmlspecialchars($gc['schedule_day']); ?>', '<?php echo date('h:i A', strtotime($gc['start_time'])); ?>', '<?php echo date('h:i A', strtotime($gc['end_time'])); ?>')">
                        <i class="fas fa-ticket-alt"></i> Reserve Spot
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- TAB 3: DIGITAL GYM PASS -->
        <div class="tab-pane" id="tab-pass">
          <div class="dash-card">
            <div class="dash-card-header">
              <h3><i class="fas fa-id-card"></i> Official Digital Membership Pass</h3>
              <p style="color: var(--text-muted); font-size: 13px;">Present this digital badge or QR code at gym turnstiles and front desk check-in.</p>
            </div>

            <div class="pass-container">
              <div class="gym-pass-card">
                <div class="pass-header">
                  <div class="pass-logo">
                    <h2>Gym<span>Bros</span></h2>
                  </div>
                  <div class="pass-chip"></div>
                </div>

                <div class="pass-body">
                  <div>
                    <div class="pass-member-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    <div class="pass-member-id">ID: <?php echo htmlspecialchars($user['id_number']); ?></div>
                    <div class="pass-meta-row">
                      <div>
                        <div style="font-size: 10px; text-transform: uppercase;">Tier</div>
                        <strong style="color: #fbbf24;"><?php echo strtoupper(htmlspecialchars($user['membership_tier'] ?? 'GOLD')); ?> VIP</strong>
                      </div>
                      <div>
                        <div style="font-size: 10px; text-transform: uppercase;">Member Since</div>
                        <strong style="color: #fff;"><?php echo date('M Y', strtotime($user['created_at'])); ?></strong>
                      </div>
                      <div>
                        <div style="font-size: 10px; text-transform: uppercase;">Status</div>
                        <strong style="color: #4ade80;"><i class="fas fa-check-circle"></i> ACTIVE</strong>
                      </div>
                    </div>
                  </div>
                  <div class="pass-qr-box" title="Scan for Entry">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=GYMBROS-MEMBER-<?php echo urlencode($user['id_number']); ?>-<?php echo urlencode($user['username']); ?>" alt="Gym Pass QR Code" loading="lazy" onerror="this.style.display='none';">
                  </div>
                </div>

                <div class="pass-footer">
                  <span><i class="fas fa-shield-alt" style="color: var(--accent-orange);"></i> Authorized Member Pass</span>
                  <span>24/7 All-Branch Access</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 4: BODY METRICS & BMI TRACKER -->
        <div class="tab-pane" id="tab-metrics">
          <div class="dash-card">
            <div class="dash-card-header">
              <h3><i class="fas fa-heartbeat"></i> Health & Body Composition Tracker</h3>
              <button class="btn-hero-primary" style="padding: 9px 18px; font-size: 13px;" onclick="openModal('modal-log-metric')">
                <i class="fas fa-plus"></i> Record Body Metrics
              </button>
            </div>

            <div class="bmi-tracker-layout">
              <div class="bmi-meter-card">
                <div class="bmi-value-circle">
                  <div class="bmi-number" id="bmi-display-num"><?php echo (!empty($userLatestMetric) && !empty($userLatestMetric['bmi'])) ? $userLatestMetric['bmi'] : '--'; ?></div>
                  <div class="bmi-status-lbl">BMI Score</div>
                </div>

                <?php
                  $bmiVal = (!empty($userLatestMetric) && !empty($userLatestMetric['bmi'])) ? (float)$userLatestMetric['bmi'] : 0;
                  $catName = 'Not Calculated';
                  $catColor = '#94a3b8';
                  if ($bmiVal > 0) {
                    if ($bmiVal < 18.5) { $catName = 'Underweight'; $catColor = '#3b82f6'; }
                    elseif ($bmiVal < 25) { $catName = 'Normal Weight'; $catColor = '#10b981'; }
                    elseif ($bmiVal < 30) { $catName = 'Overweight'; $catColor = '#f59e0b'; }
                    else { $catName = 'Obese'; $catColor = '#ef4444'; }
                  }
                ?>
                <div class="bmi-category-tag" id="bmi-category-badge" style="color: <?php echo $catColor; ?>; border-color: <?php echo $catColor; ?>;">
                  <?php echo $catName; ?>
                </div>

                <div class="bmi-scale-bar">
                  <div class="scale-under" title="Underweight (< 18.5)"></div>
                  <div class="scale-normal" title="Normal (18.5 - 24.9)"></div>
                  <div class="scale-over" title="Overweight (25.0 - 29.9)"></div>
                  <div class="scale-obese" title="Obese (>= 30.0)"></div>
                </div>
                <div class="scale-labels">
                  <span>&lt; 18.5</span>
                  <span>18.5 - 24.9</span>
                  <span>25 - 29.9</span>
                  <span>30+</span>
                </div>
              </div>

              <div>
                <div class="dash-card" style="background: rgba(15, 23, 42, 0.6); margin-bottom: 0;">
                  <h4 style="color: #fff; margin-bottom: 15px; font-size: 16px;"><i class="fas fa-chart-line" style="color: var(--accent-orange);"></i> Body Metrics Profile</h4>
                  
                  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div style="background: rgba(255,255,255,0.04); padding: 12px 16px; border-radius: 12px; border: 1px solid var(--card-border);">
                      <div style="font-size: 12px; color: var(--text-muted);">Current Weight</div>
                      <div style="font-size: 20px; font-weight: 700; color: #fff;"><?php echo (!empty($userLatestMetric) && !empty($userLatestMetric['weight_kg'])) ? $userLatestMetric['weight_kg'] . ' kg' : 'Not recorded'; ?></div>
                    </div>
                    <div style="background: rgba(255,255,255,0.04); padding: 12px 16px; border-radius: 12px; border: 1px solid var(--card-border);">
                      <div style="font-size: 12px; color: var(--text-muted);">Height</div>
                      <div style="font-size: 20px; font-weight: 700; color: #fff;"><?php echo (!empty($userLatestMetric) && !empty($userLatestMetric['height_cm'])) ? $userLatestMetric['height_cm'] . ' cm' : 'Not recorded'; ?></div>
                    </div>
                    <div style="background: rgba(255,255,255,0.04); padding: 12px 16px; border-radius: 12px; border: 1px solid var(--card-border);">
                      <div style="font-size: 12px; color: var(--text-muted);">Target Goal Weight</div>
                      <div style="font-size: 20px; font-weight: 700; color: var(--accent-orange);"><?php echo (!empty($userLatestMetric) && !empty($userLatestMetric['target_weight_kg'])) ? $userLatestMetric['target_weight_kg'] . ' kg' : 'Set a target'; ?></div>
                    </div>
                    <div style="background: rgba(255,255,255,0.04); padding: 12px 16px; border-radius: 12px; border: 1px solid var(--card-border);">
                      <div style="font-size: 12px; color: var(--text-muted);">Body Fat %</div>
                      <div style="font-size: 20px; font-weight: 700; color: #34d399;"><?php echo (!empty($userLatestMetric) && !empty($userLatestMetric['body_fat_percentage'])) ? $userLatestMetric['body_fat_percentage'] . '%' : 'Optional'; ?></div>
                    </div>
                  </div>

                  <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 15px;">
                    <?php if (!empty($userLatestMetric) && !empty($userLatestMetric['notes'])): ?>
                      <strong>Coach/User Notes:</strong> <?php echo htmlspecialchars($userLatestMetric['notes']); ?>
                    <?php else: ?>
                      Keeping your metrics updated helps you analyze progress, calibrate calorie targets, and track milestones toward your goal!
                    <?php endif; ?>
                  </p>
                  
                  <button class="btn-hero-secondary" style="width: 100%; justify-content: center;" onclick="openModal('modal-log-metric')">
                    <i class="fas fa-edit"></i> Update Body Composition
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 5: PROFILE & SETTINGS -->
        <div class="tab-pane" id="tab-profile">
          <div class="dash-card">
            <div class="dash-card-header">
              <h3><i class="fas fa-user-circle"></i> Member Profile & Preferences</h3>
              <button class="btn-hero-primary" style="padding: 9px 18px; font-size: 13px;" onclick="openModal('modal-edit-profile')">
                <i class="fas fa-edit"></i> Edit Details
              </button>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
              <div style="background: rgba(15, 23, 42, 0.6); padding: 20px; border-radius: 14px; border: 1px solid var(--card-border);">
                <h4 style="color: #fff; margin-bottom: 15px; font-size: 15px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 8px;">
                  <i class="fas fa-id-badge" style="color: var(--accent-orange);"></i> Personal Information
                </h4>
                <div style="display: flex; flex-direction: column; gap: 10px; font-size: 14px;">
                  <div><span style="color: var(--text-muted);">Full Name:</span> <strong style="color:#fff;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ($user['extension_name'] ? ' ' . $user['extension_name'] : '')); ?></strong></div>
                  <div><span style="color: var(--text-muted);">Username:</span> <strong style="color:#fff;">@<?php echo htmlspecialchars($user['username']); ?></strong></div>
                  <div><span style="color: var(--text-muted);">Member ID:</span> <strong style="color: var(--accent-orange);"><?php echo htmlspecialchars($user['id_number']); ?></strong></div>
                  <div><span style="color: var(--text-muted);">Email:</span> <strong style="color:#fff;"><?php echo htmlspecialchars($user['email']); ?></strong></div>
                  <div><span style="color: var(--text-muted);">Phone Number:</span> <strong style="color:#fff;"><?php echo htmlspecialchars($user['phone_number'] ?? 'Not set'); ?></strong></div>
                </div>
              </div>

              <div style="background: rgba(15, 23, 42, 0.6); padding: 20px; border-radius: 14px; border: 1px solid var(--card-border);">
                <h4 style="color: #fff; margin-bottom: 15px; font-size: 15px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 8px;">
                  <i class="fas fa-map-marker-alt" style="color: var(--accent-orange);"></i> Location & Address
                </h4>
                <div style="display: flex; flex-direction: column; gap: 10px; font-size: 14px;">
                  <div><span style="color: var(--text-muted);">Purok / Street:</span> <strong style="color:#fff;"><?php echo htmlspecialchars($user['purok_street'] ?? 'N/A'); ?></strong></div>
                  <div><span style="color: var(--text-muted);">Barangay:</span> <strong style="color:#fff;"><?php echo htmlspecialchars($user['barangay'] ?? 'N/A'); ?></strong></div>
                  <div><span style="color: var(--text-muted);">City / Municipality:</span> <strong style="color:#fff;"><?php echo htmlspecialchars($user['city_municipality'] ?? 'N/A'); ?></strong></div>
                  <div><span style="color: var(--text-muted);">Province / Zip:</span> <strong style="color:#fff;"><?php echo htmlspecialchars(($user['province'] ?? '') . ' ' . ($user['zip_code'] ?? '')); ?></strong></div>
                  <div><span style="color: var(--text-muted);">Country:</span> <strong style="color:#fff;"><?php echo htmlspecialchars($user['country'] ?? 'Philippines'); ?></strong></div>
                </div>
              </div>

              <div style="background: rgba(15, 23, 42, 0.6); padding: 20px; border-radius: 14px; border: 1px solid var(--card-border);">
                <h4 style="color: #fff; margin-bottom: 15px; font-size: 15px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 8px;">
                  <i class="fas fa-trophy" style="color: var(--accent-orange);"></i> Fitness Goals & Bio
                </h4>
                <div style="display: flex; flex-direction: column; gap: 10px; font-size: 14px;">
                  <div><span style="color: var(--text-muted);">Primary Goal:</span> <strong style="color:#34d399;"><?php echo htmlspecialchars($user['fitness_goal'] ?? 'Muscle Building & Fitness'); ?></strong></div>
                  <div><span style="color: var(--text-muted);">About / Bio:</span> <p style="color:#cbd5e1; font-size:13px; margin-top:4px;"><?php echo htmlspecialchars($user['bio'] ?? 'No bio added yet.'); ?></p></div>
                  <div style="margin-top: 10px;">
                    <a href="change-password.php" class="btn-hero-secondary" style="font-size: 12px; padding: 8px 14px; width: 100%; justify-content: center;">
                      <i class="fas fa-key"></i> Change Security Password
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- MEMBER MODAL 1: LOG WORKOUT -->
    <div class="modal-overlay" id="modal-log-workout">
      <div class="modal-card">
        <div class="modal-header">
          <h3><i class="fas fa-dumbbell" style="color: var(--accent-orange);"></i> Record Daily Workout</h3>
          <button class="modal-close-btn" onclick="closeModal('modal-log-workout')"><i class="fas fa-times"></i></button>
        </div>
        <form onsubmit="submitLogWorkoutForm(event)">
          <div class="modal-body">
            <div class="form-field-dash">
              <label>Exercise / Workout Name *</label>
              <input type="text" id="log-workout-name" placeholder="e.g., Incline Barbell Bench Press" required>
            </div>

            <div class="form-grid-2">
              <div class="form-field-dash">
                <label>Target Muscle Group</label>
                <select id="log-muscle-group">
                  <option value="Chest">Chest</option>
                  <option value="Back">Back</option>
                  <option value="Legs">Legs / Quads / Hamstrings</option>
                  <option value="Shoulders">Shoulders</option>
                  <option value="Arms">Arms (Biceps & Triceps)</option>
                  <option value="Core & Abs">Core & Abs</option>
                  <option value="Cardio & HIIT">Cardio & HIIT</option>
                  <option value="Full Body">Full Body</option>
                </select>
              </div>
              <div class="form-field-dash">
                <label>Workout Date</label>
                <input type="date" id="log-date" value="<?php echo date('Y-m-d'); ?>">
              </div>
            </div>

            <div class="form-grid-2">
              <div class="form-field-dash">
                <label>Sets Completed</label>
                <input type="number" id="log-sets" min="1" max="50" value="3">
              </div>
              <div class="form-field-dash">
                <label>Reps per Set</label>
                <input type="number" id="log-reps" min="1" max="200" value="10">
              </div>
            </div>

            <div class="form-grid-2">
              <div class="form-field-dash">
                <label>Weight Lifted (kg, optional)</label>
                <input type="number" step="0.5" id="log-weight" min="0" placeholder="0">
              </div>
              <div class="form-field-dash">
                <label>Duration (Minutes)</label>
                <input type="number" id="log-duration" min="1" max="300" value="45">
              </div>
            </div>

            <div class="form-field-dash">
              <label>Estimated Calories Burned (kcal)</label>
              <input type="number" id="log-calories" min="1" max="3000" value="250">
            </div>

            <div class="form-field-dash">
              <label>Workout Notes / Observations (optional)</label>
              <textarea id="log-notes" placeholder="e.g., Felt strong today, increased weight on 3rd set!"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-hero-secondary btn-modal-cancel">Cancel</button>
            <button type="submit" class="btn-hero-primary"><i class="fas fa-save"></i> Save Workout</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MEMBER MODAL 2: BOOK GYM CLASS -->
    <div class="modal-overlay" id="modal-book-class">
      <div class="modal-card" style="max-width: 480px;">
        <div class="modal-header">
          <h3><i class="fas fa-ticket-alt" style="color: var(--accent-orange);"></i> Confirm Class Spot</h3>
          <button class="modal-close-btn" onclick="closeModal('modal-book-class')"><i class="fas fa-times"></i></button>
        </div>
        <form onsubmit="submitBookClassForm(event)">
          <div class="modal-body">
            <input type="hidden" id="book-class-id">
            <div style="background: rgba(255, 94, 0, 0.1); border: 1px solid rgba(255, 94, 0, 0.3); border-radius: 12px; padding: 15px; margin-bottom: 20px;">
              <h4 id="book-class-name-display" style="color: #fff; font-size: 16px; margin-bottom: 5px;"></h4>
              <p style="color: var(--text-muted); font-size: 13px;" id="book-class-instructor-display"></p>
              <p style="color: #fbbf24; font-size: 13px; font-weight: 600; margin-top: 5px;" id="book-class-schedule-display"></p>
            </div>

            <div class="form-field-dash">
              <label>Select Session Date *</label>
              <input type="date" id="book-class-date" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-hero-secondary btn-modal-cancel">Cancel</button>
            <button type="submit" class="btn-hero-primary"><i class="fas fa-check"></i> Confirm Reservation</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MEMBER MODAL 3: LOG BODY METRIC -->
    <div class="modal-overlay" id="modal-log-metric">
      <div class="modal-card">
        <div class="modal-header">
          <h3><i class="fas fa-heartbeat" style="color: var(--accent-orange);"></i> Update Body Metrics & BMI</h3>
          <button class="modal-close-btn" onclick="closeModal('modal-log-metric')"><i class="fas fa-times"></i></button>
        </div>
        <form onsubmit="submitBodyMetricsForm(event)">
          <div class="modal-body">
            <div class="form-grid-2">
              <div class="form-field-dash">
                <label>Current Weight (kg) *</label>
                <input type="number" step="0.1" id="metric-weight" min="20" max="300" placeholder="e.g., 75.5" required>
              </div>
              <div class="form-field-dash">
                <label>Height (cm) *</label>
                <input type="number" step="0.5" id="metric-height" min="50" max="260" placeholder="e.g., 175" required>
              </div>
            </div>

            <div id="bmi-live-preview" style="margin-bottom: 15px; font-size: 13px; color: var(--text-muted);">
              Enter weight and height to see calculated BMI in real-time.
            </div>

            <div class="form-grid-2">
              <div class="form-field-dash">
                <label>Target Goal Weight (kg)</label>
                <input type="number" step="0.1" id="metric-target-weight" min="20" max="300" placeholder="e.g., 70.0">
              </div>
              <div class="form-field-dash">
                <label>Body Fat % (optional)</label>
                <input type="number" step="0.1" id="metric-body-fat" min="3" max="60" placeholder="e.g., 15.0">
              </div>
            </div>

            <div class="form-field-dash">
              <label>Primary Fitness Goal</label>
              <select id="metric-fitness-goal">
                <option value="Muscle Building & Hypertrophy">Muscle Building & Hypertrophy</option>
                <option value="Fat Loss & Calorie Burn">Fat Loss & Calorie Burn</option>
                <option value="Strength & Powerlifting">Strength & Powerlifting</option>
                <option value="Athletic Conditioning & Endurance">Athletic Conditioning & Endurance</option>
                <option value="General Health & Wellness">General Health & Wellness</option>
              </select>
            </div>

            <div class="form-field-dash">
              <label>Notes (optional)</label>
              <textarea id="metric-notes" placeholder="e.g., Progress check-in at week 4"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-hero-secondary btn-modal-cancel">Cancel</button>
            <button type="submit" class="btn-hero-primary"><i class="fas fa-calculator"></i> Save Metrics</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MEMBER MODAL 4: EDIT PROFILE -->
    <div class="modal-overlay" id="modal-edit-profile">
      <div class="modal-card">
        <div class="modal-header">
          <h3><i class="fas fa-user-edit" style="color: var(--accent-orange);"></i> Update Profile Information</h3>
          <button class="modal-close-btn" onclick="closeModal('modal-edit-profile')"><i class="fas fa-times"></i></button>
        </div>
        <form onsubmit="submitProfileForm(event)">
          <div class="modal-body">
            <div class="form-grid-2">
              <div class="form-field-dash">
                <label>Contact Phone Number</label>
                <input type="text" id="prof-phone" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" placeholder="+63 912 345 6789">
              </div>
              <div class="form-field-dash">
                <label>Primary Fitness Goal</label>
                <select id="prof-fitness-goal">
                  <option value="Muscle Building & Hypertrophy" <?php echo ($user['fitness_goal'] ?? '') === 'Muscle Building & Hypertrophy' ? 'selected' : ''; ?>>Muscle Building & Hypertrophy</option>
                  <option value="Fat Loss & Calorie Burn" <?php echo ($user['fitness_goal'] ?? '') === 'Fat Loss & Calorie Burn' ? 'selected' : ''; ?>>Fat Loss & Calorie Burn</option>
                  <option value="Strength & Powerlifting" <?php echo ($user['fitness_goal'] ?? '') === 'Strength & Powerlifting' ? 'selected' : ''; ?>>Strength & Powerlifting</option>
                  <option value="Athletic Conditioning & Endurance" <?php echo ($user['fitness_goal'] ?? '') === 'Athletic Conditioning & Endurance' ? 'selected' : ''; ?>>Athletic Conditioning & Endurance</option>
                  <option value="General Health & Wellness" <?php echo ($user['fitness_goal'] ?? '') === 'General Health & Wellness' ? 'selected' : ''; ?>>General Health & Wellness</option>
                </select>
              </div>
            </div>

            <div class="form-field-dash">
              <label>Personal Bio / About Me</label>
              <textarea id="prof-bio" placeholder="Tell us a little bit about your fitness journey..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
            </div>

            <h4 style="color: #fff; font-size: 14px; margin: 15px 0 10px;"><i class="fas fa-map-marker-alt" style="color: var(--accent-orange);"></i> Address Details</h4>

            <div class="form-grid-2">
              <div class="form-field-dash">
                <label>Purok / Street</label>
                <input type="text" id="prof-purok" value="<?php echo htmlspecialchars($user['purok_street'] ?? ''); ?>">
              </div>
              <div class="form-field-dash">
                <label>Barangay</label>
                <input type="text" id="prof-barangay" value="<?php echo htmlspecialchars($user['barangay'] ?? ''); ?>">
              </div>
            </div>

            <div class="form-grid-2">
              <div class="form-field-dash">
                <label>City / Municipality</label>
                <input type="text" id="prof-city" value="<?php echo htmlspecialchars($user['city_municipality'] ?? ''); ?>">
              </div>
              <div class="form-field-dash">
                <label>Province</label>
                <input type="text" id="prof-province" value="<?php echo htmlspecialchars($user['province'] ?? ''); ?>">
              </div>
            </div>

            <div class="form-field-dash">
              <label>Zip Code</label>
              <input type="text" id="prof-zip" value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-hero-secondary btn-modal-cancel">Cancel</button>
            <button type="submit" class="btn-hero-primary"><i class="fas fa-save"></i> Save Profile</button>
          </div>
        </form>
      </div>
    </div>

    <footer>
      <div class="footer-content">
        <div class="footer-section"><div class="logo"><h1>Gym<span>Bros</span></h1></div><p>Your fitness journey starts here.</p></div>
        <div class="footer-section"><p class="copyright">© <?php echo date('Y'); ?> GymBros. All rights reserved.</p></div>
      </div>
    </footer>
  <?php endif; ?>

  <script src="../js/loader.js?v=<?php echo time(); ?>"></script>
  <?php if ($isAdmin): ?>
    <script src="../js/admin.js?v=<?php echo time(); ?>"></script>
  <?php else: ?>
    <script src="../js/user_dashboard.js?v=<?php echo time(); ?>"></script>
  <?php endif; ?>
</body>

</html>