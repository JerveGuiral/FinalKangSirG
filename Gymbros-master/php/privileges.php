<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect to login if not logged in
if (!Auth::isLoggedIn()) {
  header("Location: login.php");
  exit();
}

if (Auth::needsFirstLoginSetup()) {
  header("Location: first-login-setup.php");
  exit();
}

$user = $_SESSION['user'];
$isSuperAdmin = Auth::isSuperAdmin();
$isAdmin = Auth::isAdmin();

// Only Super Administrator or Administrator has access to this Privileges page
if (!$isAdmin) {
  header("Location: dashboard.php");
  exit();
}

$db = new Database();
$conn = $db->getConnection();

// Fresh sync user from DB
$stmtFresh = $conn->prepare("SELECT * FROM users WHERE id_number = ? LIMIT 1");
$stmtFresh->bind_param("s", $user['id_number']);
$stmtFresh->execute();
$rFresh = $stmtFresh->get_result();
if ($rFresh && $rFresh->num_rows === 1) {
  $user = $rFresh->fetch_assoc();
  $_SESSION['user'] = $user;
}
$stmtFresh->close();

// Calculate pending requests count for sidebar badge
$pendingRequestsTotal = 0;
$resP = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE status = 'pending'");
if ($resP) $pendingRequestsTotal += (int)$resP->fetch_assoc()['cnt'];

$resD = $conn->query("SELECT COUNT(*) as cnt FROM delete_requests WHERE status = 'pending'");
if ($resD) $pendingRequestsTotal += (int)$resD->fetch_assoc()['cnt'];

$privilegeCategories = Auth::getAllPrivileges();
$totalAvailablePrivileges = 0;
foreach ($privilegeCategories as $cat) {
  $totalAvailablePrivileges += count($cat['items']);
}

// Current user privileges
$myPrivileges = [];
if ($isSuperAdmin) {
  $myPrivileges = array_fill_keys(Auth::getAllPrivilegeKeys(), true);
} elseif (!empty($user['privileges'])) {
  $myPrivileges = is_array($user['privileges']) ? $user['privileges'] : json_decode($user['privileges'], true);
  if (!is_array($myPrivileges)) $myPrivileges = [];
}
$myActiveCount = count(array_filter($myPrivileges));

// Fetch all users for privilege management (Needed for Super Admin Studio)
$allUsersList = [];
$stats = [
  'total_admins' => 0,
  'total_users' => 0,
  'total_superadmins' => 0,
  'custom_privileges_count' => 0,
  'full_delegates_count' => 0
];

if ($isSuperAdmin) {
  $resUsers = $conn->query("SELECT id_number, username, first_name, middle_name, last_name, email, role, status, privileges, created_at FROM users ORDER BY FIELD(role, 'superadmin', 'admin', 'user'), created_at DESC");
} else {
  $resUsers = null;
}

if ($resUsers) {
  while ($r = $resUsers->fetch_assoc()) {
    $allUsersList[] = $r;
    if ($r['role'] === 'superadmin') {
      $stats['total_superadmins']++;
    } elseif ($r['role'] === 'admin') {
      $stats['total_admins']++;
    } else {
      $stats['total_users']++;
    }

    $parsedPrivs = [];
    if (!empty($r['privileges'])) {
      $parsedPrivs = is_array($r['privileges']) ? $r['privileges'] : json_decode($r['privileges'], true);
      if (is_array($parsedPrivs)) {
        $activeCount = count(array_filter($parsedPrivs));
        if ($activeCount > 0) {
          $stats['custom_privileges_count']++;
          if ($activeCount >= $totalAvailablePrivileges) {
            $stats['full_delegates_count']++;
          }
        }
      }
    }
  }
}

$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $isSuperAdmin ? 'Privileges Management | GymBros Super Admin' : 'My Privileges | GymBros Admin'; ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
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
      <p>Loading Privileges...</p>
    </div>
  </div>
  <script>
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
            <span class="badge-admin"><i class="fas fa-shield-alt"></i> ADMINISTRATOR</span>
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
            <a href="dashboard.php">
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
          <li>
            <a href="privileges.php" class="active">
              <i class="fas fa-user-shield"></i> <span>Privileges</span>
            </a>
          </li>
          <?php if ($isSuperAdmin): ?>
            <li>
              <a href="create_account.php">
                <i class="fas fa-user-plus"></i> <span>Create Account</span>
              </a>
            </li>
          <?php endif; ?>
          <li>
            <a href="logs.php">
              <i class="fas fa-history"></i> <span>System Logs</span>
            </a>
          </li>
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

    <!-- MAIN CONTENT -->
    <div class="admin-main-wrapper">
      <header class="admin-topbar">
        <button class="sidebar-toggle-btn" id="sidebarToggleBtn" title="Toggle Navigation"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">
          <h3><i class="fas fa-key"></i> <?php echo $isSuperAdmin ? 'Privileges Management Studio' : 'My Administrative Privileges'; ?></h3>
        </div>
        <div class="topbar-right">
          <div class="topbar-user-chip">
            <?php if ($isSuperAdmin): ?>
              <i class="fas fa-crown" style="color: #fbbf24;"></i>
              <span>@<?php echo htmlspecialchars($user['username']); ?> (Super Admin)</span>
            <?php else: ?>
              <i class="fas fa-user-shield" style="color: var(--accent);"></i>
              <span>@<?php echo htmlspecialchars($user['username']); ?> (Administrator)</span>
            <?php endif; ?>
          </div>
          <a href="logout.php" class="btn-topbar-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
      </header>

      <div class="admin-page-content">
        <?php if ($isSuperAdmin): ?>
          <!-- Clean Page Header -->
        <div class="priv-page-header">
          <div class="priv-header-left">
            <div class="priv-superadmin-tag">
              <i class="fas fa-crown"></i> SUPERADMIN CONTROL CENTER
            </div>
            <h2>Privilege Delegation & Capability Matrix</h2>
            <p>Grant, customize, and manage administrative privileges and security permissions across all accounts.</p>
          </div>
          <div class="priv-header-right">
            <a href="dashboard.php" class="btn-secondary-action">
              <i class="fas fa-users"></i> Accounts Console
            </a>
            <a href="logs.php" class="btn-secondary-action">
              <i class="fas fa-history"></i> System Logs
            </a>
          </div>
        </div>

        <!-- 4 Stats Overview Cards -->
        <div class="admin-stats-grid" style="margin-bottom: 25px;">
          <div class="stat-card">
            <div class="stat-icon" style="background: rgba(168, 85, 247, 0.15); color: #c084fc;">
              <i class="fas fa-user-shield"></i>
            </div>
            <div class="stat-details">
              <h4>System Administrators</h4>
              <div class="stat-number"><?php echo $stats['total_admins']; ?></div>
              <p>Active admin accounts</p>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon" style="background: rgba(255, 94, 0, 0.15); color: var(--accent);">
              <i class="fas fa-sliders-h"></i>
            </div>
            <div class="stat-details">
              <h4>Delegated Accounts</h4>
              <div class="stat-number"><?php echo $stats['custom_privileges_count']; ?></div>
              <p>Custom rights assigned</p>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #4ade80;">
              <i class="fas fa-crown"></i>
            </div>
            <div class="stat-details">
              <h4>Available Capabilities</h4>
              <div class="stat-number"><?php echo $totalAvailablePrivileges; ?></div>
              <p>All Superadmin controllable permissions</p>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
              <i class="fas fa-users"></i>
            </div>
            <div class="stat-details">
              <h4>Member Accounts</h4>
              <div class="stat-number"><?php echo $stats['total_users']; ?></div>
              <p>Standard registered users</p>
            </div>
          </div>
        </div>

        <!-- MAIN TWO-COLUMN STUDIO LAYOUT -->
        <div class="privilege-studio-layout">
          
          <!-- LEFT PANEL: TARGET ACCOUNT, PRESETS & SAVE ACTIONS -->
          <div class="privilege-left-panel">
            
            <!-- Target Selection Box -->
            <div class="priv-card">
              <div class="priv-card-header">
                <h3><i class="fas fa-user-check"></i> Target Account</h3>
                <span class="step-pill">Step 1</span>
              </div>
              <p class="priv-card-sub">Select an account to configure their permissions:</p>

              <div class="search-box" style="margin-bottom: 10px;">
                <i class="fas fa-search"></i>
                <input type="text" id="studio-user-search" class="search-input" placeholder="🔍 Filter accounts by name or ID..." oninput="filterStudioUserDropdown(this.value)">
              </div>

              <div class="priv-select-wrap">
                <select id="privilege-user-select" class="priv-dropdown" onchange="onPrivilegeUserSelected(this.value)">
                  <option value="">-- Select an Account to Configure --</option>
                  <optgroup label="🛡️ Administrators" id="optgroup-admins">
                    <?php foreach ($allUsersList as $u): ?>
                      <?php if ($u['role'] === 'admin'): ?>
                        <option value="<?php echo htmlspecialchars($u['id_number']); ?>" data-role="<?php echo $u['role']; ?>" data-status="<?php echo $u['status']; ?>" data-name="<?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>" data-username="<?php echo htmlspecialchars($u['username']); ?>" data-email="<?php echo htmlspecialchars($u['email']); ?>" data-privileges='<?php echo htmlspecialchars($u['privileges'] ?? '{}', ENT_QUOTES, 'UTF-8'); ?>'>
                          <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?> (@<?php echo htmlspecialchars($u['username']); ?>) [ID: <?php echo htmlspecialchars($u['id_number']); ?>]
                        </option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </optgroup>
                  <optgroup label="👤 Regular Users" id="optgroup-users">
                    <?php foreach ($allUsersList as $u): ?>
                      <?php if ($u['role'] === 'user'): ?>
                        <option value="<?php echo htmlspecialchars($u['id_number']); ?>" data-role="<?php echo $u['role']; ?>" data-status="<?php echo $u['status']; ?>" data-name="<?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>" data-username="<?php echo htmlspecialchars($u['username']); ?>" data-email="<?php echo htmlspecialchars($u['email']); ?>" data-privileges='<?php echo htmlspecialchars($u['privileges'] ?? '{}', ENT_QUOTES, 'UTF-8'); ?>'>
                          <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?> (@<?php echo htmlspecialchars($u['username']); ?>) [ID: <?php echo htmlspecialchars($u['id_number']); ?>]
                        </option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </optgroup>
                  <optgroup label="👑 Super Administrators" id="optgroup-superadmins">
                    <?php foreach ($allUsersList as $u): ?>
                      <?php if ($u['role'] === 'superadmin'): ?>
                        <option value="<?php echo htmlspecialchars($u['id_number']); ?>" data-role="superadmin" data-status="approved" data-name="<?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>" data-username="<?php echo htmlspecialchars($u['username']); ?>" data-email="<?php echo htmlspecialchars($u['email']); ?>" data-privileges='{"all":true}' <?php echo ($u['id_number'] === $user['id_number']) ? 'disabled' : ''; ?>>
                          <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?> (@<?php echo htmlspecialchars($u['username']); ?>) [Super Admin]
                        </option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </optgroup>
                </select>
              </div>

              <!-- Selected Profile Overview Card -->
              <div id="target-user-profile-display" class="target-profile-box empty-state">
                <div class="empty-prompt">
                  <i class="fas fa-user-circle"></i>
                  <h4>No Account Selected</h4>
                  <p>Choose an account above or click "Configure" in the matrix table below.</p>
                </div>

                <div class="active-profile-content hidden" id="active-profile-details">
                  <div class="profile-header-strip">
                    <div class="profile-avatar-circle">
                      <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="profile-titles">
                      <h4 id="profile-fullname">User Full Name</h4>
                      <span id="profile-username" class="profile-username">@username</span>
                    </div>
                  </div>

                  <div class="profile-meta-list">
                    <div class="meta-item">
                      <span class="lbl"><i class="fas fa-id-badge"></i> Account ID:</span>
                      <strong class="val" id="profile-id-number">SA-0000</strong>
                    </div>
                    <div class="meta-item">
                      <span class="lbl"><i class="fas fa-envelope"></i> Email:</span>
                      <span class="val" id="profile-email">email@domain.com</span>
                    </div>
                    <div class="meta-item">
                      <span class="lbl"><i class="fas fa-shield-alt"></i> Role:</span>
                      <span class="val" id="profile-role-badge"><span class="badge badge-admin">Admin</span></span>
                    </div>
                    <div class="meta-item">
                      <span class="lbl"><i class="fas fa-circle-check"></i> Status:</span>
                      <span class="val" id="profile-status-badge"><span class="badge badge-approved">Active</span></span>
                    </div>
                  </div>

                  <div class="priv-gauge-box">
                    <div class="gauge-top">
                      <span><i class="fas fa-key"></i> Active Privileges:</span>
                      <span id="active-privileges-counter" class="gauge-pill">0 / <?php echo $totalAvailablePrivileges; ?></span>
                    </div>
                    <div class="gauge-track">
                      <div id="privilege-progress-bar" class="gauge-fill" style="width: 0%;"></div>
                    </div>
                  </div>

                  <div id="superadmin-notice-banner" class="superadmin-notice hidden">
                    <i class="fas fa-crown"></i>
                    <div>
                      <strong>Superadmin Root Account</strong>
                      <p>Superadmin accounts possess all privileges inherently with root access.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Quick Template Presets Card -->
            <div class="priv-card">
              <div class="priv-card-header">
                <h3><i class="fas fa-magic"></i> Quick Role Presets</h3>
              </div>
              <p class="priv-card-sub">Instantly apply pre-configured capability templates:</p>
              
              <div class="presets-palette">
                <button type="button" class="preset-chip" onclick="applyPrivilegePreset('full_delegate')">
                  <i class="fas fa-crown" style="color: #fbbf24;"></i>
                  <div>
                    <strong>Full Delegate</strong>
                    <span>All 13 privileges</span>
                  </div>
                </button>

                <button type="button" class="preset-chip" onclick="applyPrivilegePreset('senior_admin')">
                  <i class="fas fa-shield-alt" style="color: #60a5fa;"></i>
                  <div>
                    <strong>Senior Admin</strong>
                    <span>Approvals, edits & logs</span>
                  </div>
                </button>

                <button type="button" class="preset-chip" onclick="applyPrivilegePreset('user_manager')">
                  <i class="fas fa-users-cog" style="color: #34d399;"></i>
                  <div>
                    <strong>User Manager</strong>
                    <span>Users, roles & provision</span>
                  </div>
                </button>

                <button type="button" class="preset-chip" onclick="applyPrivilegePreset('audit_officer')">
                  <i class="fas fa-clipboard-check" style="color: #a78bfa;"></i>
                  <div>
                    <strong>Audit Officer</strong>
                    <span>Logs, exports & requests</span>
                  </div>
                </button>

                <button type="button" class="preset-chip" onclick="applyPrivilegePreset('gym_manager')">
                  <i class="fas fa-dumbbell" style="color: #f97316;"></i>
                  <div>
                    <strong>Gym Operations</strong>
                    <span>Classes, bookings & metrics</span>
                  </div>
                </button>

                <button type="button" class="preset-chip" onclick="applyPrivilegePreset('standard_staff')">
                  <i class="fas fa-user-tag" style="color: #cbd5e1;"></i>
                  <div>
                    <strong>Standard Staff</strong>
                    <span>Basic approvals & logs</span>
                  </div>
                </button>
              </div>
            </div>

            <!-- Save & Action Controls Card -->
            <div class="priv-card priv-save-card">
              <div class="save-actions-wrap">
                <button type="button" class="btn-primary-action btn-save-studio" id="btn-save-privileges-submit" onclick="submitPrivilegesStudioForm(event)" disabled>
                  <i class="fas fa-save"></i> Save Account Privileges
                </button>
                <button type="button" class="btn-secondary-action btn-reset-studio" onclick="resetCurrentPrivilegesForm()">
                  <i class="fas fa-undo"></i> Reset to Saved
                </button>
              </div>
              <p class="save-note"><i class="fas fa-bolt"></i> Saved changes take effect immediately on next request.</p>
            </div>

          </div>

          <!-- RIGHT PANEL: CATEGORIZED PRIVILEGE CHECKBOXES MATRIX -->
          <div class="privilege-right-panel">
            <div class="priv-card priv-matrix-studio-card">
              
              <!-- Studio Top Bar -->
              <div class="studio-topbar">
                <div class="studio-topbar-title">
                  <h3><i class="fas fa-sliders-h" style="color: var(--accent);"></i> Superadmin Capability Controls</h3>
                  <p>Check or uncheck the capabilities you wish to grant to the selected account.</p>
                </div>
                <div class="studio-topbar-actions">
                  <button type="button" class="btn-quick-toggle btn-all-on" onclick="grantAllPrivileges()">
                    <i class="fas fa-check-double"></i> Grant All
                  </button>
                  <button type="button" class="btn-quick-toggle btn-all-off" onclick="revokeAllPrivileges()">
                    <i class="fas fa-times"></i> Revoke All
                  </button>
                </div>
              </div>

              <!-- Checkbox Form Area -->
              <form id="form-manage-privileges" onsubmit="submitPrivilegesStudioForm(event)">
                <input type="hidden" id="studio-target-user-id" value="">

                <div class="categories-stack">
                  <?php foreach ($privilegeCategories as $catKey => $category): ?>
                    <div class="category-block">
                      <div class="category-block-header">
                        <div class="category-block-title">
                          <i class="<?php echo htmlspecialchars($category['icon']); ?>"></i>
                          <h4><?php echo htmlspecialchars($category['title']); ?></h4>
                        </div>
                        <button type="button" class="btn-toggle-cat" onclick="toggleCategoryCheckboxes('<?php echo $catKey; ?>')">
                          Toggle Category
                        </button>
                      </div>

                      <div class="priv-items-grid" data-category="<?php echo $catKey; ?>">
                        <?php foreach ($category['items'] as $privKey => $item): ?>
                          <div class="priv-toggle-card" id="card_<?php echo $privKey; ?>" onclick="handleCardClick('<?php echo $privKey; ?>', event)">
                            <div class="priv-toggle-left">
                              <div class="priv-icon-circle">
                                <i class="<?php echo htmlspecialchars($item['icon']); ?>"></i>
                              </div>
                              <div class="priv-text-content">
                                <div class="priv-title-row">
                                  <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                </div>
                                <p class="priv-desc"><?php echo htmlspecialchars($item['desc']); ?></p>
                              </div>
                            </div>
                            
                            <div class="priv-toggle-right">
                              <label class="switch" onclick="event.stopPropagation()">
                                <input type="checkbox" id="check_<?php echo $privKey; ?>" data-priv-key="<?php echo $privKey; ?>" onchange="updatePrivilegeUIState('<?php echo $privKey; ?>')">
                                <span class="slider"></span>
                              </label>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </form>

            </div>
          </div>

        </div>

        <!-- BOTTOM SECTION: COMPLETE PRIVILEGES MATRIX & ROSTER TABLE -->
        <div class="admin-table-card" style="margin-top: 30px;">
          <div class="table-card-header">
            <div>
              <h3><i class="fas fa-table-list"></i> Accounts Capability Roster & Matrix</h3>
              <p style="font-size: 13px; color: #94a3b8; margin-top: 4px;">Complete directory of all user accounts and their active administrative privileges</p>
            </div>

            <div class="table-card-tools">
              <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="matrix-search-input" class="search-input" placeholder="Search by name, ID, or username..." oninput="filterMatrixTable()">
              </div>
              <select id="matrix-filter-role" onchange="filterMatrixTable()" class="filter-select">
                <option value="">All Roles</option>
                <option value="superadmin">Super Admins</option>
                <option value="admin">Administrators</option>
                <option value="user">Users</option>
              </select>
            </div>
          </div>

          <div class="table-responsive">
            <table class="admin-table" id="privileges-matrix-table">
              <thead>
                <tr>
                  <th>Employee / User ID</th>
                  <th>Full Name & Username</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th>Granted Capabilities</th>
                  <th style="text-align: center;">Action</th>
                </tr>
              </thead>
              <tbody id="privileges-matrix-tbody">
                <?php foreach ($allUsersList as $u): ?>
                  <?php
                    $uPrivs = [];
                    if (!empty($u['privileges'])) {
                      $uPrivs = is_array($u['privileges']) ? $u['privileges'] : json_decode($u['privileges'], true);
                      if (!is_array($uPrivs)) $uPrivs = [];
                    }
                    $grantedCount = count(array_filter($uPrivs));
                    $privKeysList = implode(' ', array_keys(array_filter($uPrivs)));
                  ?>
                  <tr data-empid="<?php echo htmlspecialchars($u['id_number']); ?>" data-username="<?php echo htmlspecialchars($u['username']); ?>" data-fullname="<?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>" data-role="<?php echo htmlspecialchars($u['role']); ?>" data-email="<?php echo htmlspecialchars($u['email']); ?>" data-privs="<?php echo htmlspecialchars($privKeysList); ?>">
                    <td>
                      <span class="badge-empid"><?php echo htmlspecialchars($u['id_number']); ?></span>
                    </td>
                    <td>
                      <div class="user-cell">
                        <div class="user-cell-avatar">
                          <?php if ($u['role'] === 'superadmin'): ?>
                            <i class="fas fa-crown" style="color: #fbbf24;"></i>
                          <?php elseif ($u['role'] === 'admin'): ?>
                            <i class="fas fa-shield-alt" style="color: var(--accent);"></i>
                          <?php else: ?>
                            <i class="fas fa-user" style="color: #94a3b8;"></i>
                          <?php endif; ?>
                        </div>
                        <div>
                          <div class="user-cell-name"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></div>
                          <div class="user-cell-sub">@<?php echo htmlspecialchars($u['username']); ?> &bull; <?php echo htmlspecialchars($u['email']); ?></div>
                        </div>
                      </div>
                    </td>
                    <td>
                      <?php if ($u['role'] === 'superadmin'): ?>
                        <span class="badge badge-superadmin"><i class="fas fa-crown"></i> Super Admin</span>
                      <?php elseif ($u['role'] === 'admin'): ?>
                        <span class="badge badge-admin"><i class="fas fa-shield-alt"></i> Admin</span>
                      <?php else: ?>
                        <span class="badge badge-user"><i class="fas fa-user"></i> User</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if (($u['status'] ?? 'approved') === 'approved'): ?>
                        <span class="badge badge-approved"><i class="fas fa-check-circle"></i> Active</span>
                      <?php elseif (($u['status'] ?? 'approved') === 'pending'): ?>
                        <span class="badge badge-pending"><i class="fas fa-clock"></i> Pending</span>
                      <?php else: ?>
                        <span class="badge badge-blocked"><i class="fas fa-ban"></i> Blocked</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($u['role'] === 'superadmin'): ?>
                        <span class="priv-badge-full-access">
                          <i class="fas fa-crown"></i> FULL SUPERADMIN PRIVILEGES (ROOT IMMUNITY)
                        </span>
                      <?php elseif ($grantedCount === 0): ?>
                        <span class="priv-badge-none">No Delegated Privileges</span>
                      <?php else: ?>
                        <div class="priv-badges-wrap">
                          <span class="priv-badge-count"><i class="fas fa-key"></i> <?php echo $grantedCount; ?>/<?php echo $totalAvailablePrivileges; ?>:</span>
                          <?php if (!empty($uPrivs['can_approve_users'])): ?>
                            <span class="priv-pill"><i class="fas fa-user-check"></i> Approvals</span>
                          <?php endif; ?>
                          <?php if (!empty($uPrivs['can_block_users'])): ?>
                            <span class="priv-pill"><i class="fas fa-user-slash"></i> Block Users</span>
                          <?php endif; ?>
                          <?php if (!empty($uPrivs['can_update_info'])): ?>
                            <span class="priv-pill"><i class="fas fa-user-edit"></i> Edit Info</span>
                          <?php endif; ?>
                          <?php if (!empty($uPrivs['can_manage_roles'])): ?>
                            <span class="priv-pill"><i class="fas fa-user-tag"></i> Roles</span>
                          <?php endif; ?>
                          <?php if (!empty($uPrivs['can_create_accounts'])): ?>
                            <span class="priv-pill"><i class="fas fa-user-plus"></i> Create Accs</span>
                          <?php endif; ?>
                          <?php if (!empty($uPrivs['can_delete_users'])): ?>
                            <span class="priv-pill"><i class="fas fa-trash-alt"></i> Direct Delete</span>
                          <?php endif; ?>
                          <?php if (!empty($uPrivs['can_manage_requests'])): ?>
                            <span class="priv-pill"><i class="fas fa-clipboard-check"></i> Requests</span>
                          <?php endif; ?>
                          <?php if (!empty($uPrivs['can_give_privileges'])): ?>
                            <span class="priv-pill"><i class="fas fa-key"></i> Privileges</span>
                          <?php endif; ?>
                          <?php if (!empty($uPrivs['can_view_reports']) || !empty($uPrivs['can_view_logs'])): ?>
                            <span class="priv-pill"><i class="fas fa-history"></i> Logs</span>
                          <?php endif; ?>
                          <?php if (!empty($uPrivs['can_export_logs'])): ?>
                            <span class="priv-pill"><i class="fas fa-file-export"></i> Export</span>
                          <?php endif; ?>
                          <?php if (!empty($uPrivs['can_manage_classes'])): ?>
                            <span class="priv-pill"><i class="fas fa-calendar-alt"></i> Classes</span>
                          <?php endif; ?>
                          <?php if (!empty($uPrivs['can_manage_bookings'])): ?>
                            <span class="priv-pill"><i class="fas fa-clipboard-list"></i> Bookings</span>
                          <?php endif; ?>
                          <?php if (!empty($uPrivs['can_manage_metrics'])): ?>
                            <span class="priv-pill"><i class="fas fa-heartbeat"></i> Metrics</span>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                      <?php if ($u['role'] !== 'superadmin'): ?>
                        <button type="button" class="btn-configure-priv" onclick="loadUserIntoStudio('<?php echo htmlspecialchars($u['id_number']); ?>')" title="Configure Privileges">
                          <i class="fas fa-sliders-h"></i> Configure
                        </button>
                      <?php else: ?>
                        <span class="badge" style="background: rgba(168, 85, 247, 0.2); color: #c084fc; font-size: 11px; padding: 6px 10px;">
                          <i class="fas fa-lock"></i> Root
                        </span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php else: ?>
          <!-- ==========================================================================
               REGULAR ADMIN VIEW: STATIC READ-ONLY PRIVILEGE CHECKBOX LIST
               ========================================================================== -->
          <div class="priv-page-header">
            <div class="priv-header-left">
              <div class="priv-superadmin-tag" style="background: rgba(59, 130, 246, 0.2); border-color: rgba(59, 130, 246, 0.4); color: #60a5fa;">
                <i class="fas fa-shield-alt"></i> ADMINISTRATOR CAPABILITY PROFILE
              </div>
              <h2>My Administrative Privileges & Capabilities</h2>
              <p>Review the active operational capabilities and permissions delegated to your account by the Super Administrator.</p>
            </div>
            <div class="priv-header-right">
              <a href="dashboard.php" class="btn-secondary-action">
                <i class="fas fa-users-cog"></i> Accounts Console
              </a>
              <a href="logs.php" class="btn-secondary-action">
                <i class="fas fa-history"></i> System Logs
              </a>
            </div>
          </div>

          <!-- Read-Only Notice Box -->
          <div style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 14px; padding: 18px 22px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
            <div style="display: flex; align-items: center; gap: 14px;">
              <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.4); display: flex; align-items: center; justify-content: center; font-size: 20px; color: #60a5fa; flex-shrink: 0;">
                <i class="fas fa-info-circle"></i>
              </div>
              <div>
                <h4 style="color: #fff; font-size: 15px; margin: 0 0 3px 0; font-family: 'Montserrat', sans-serif;">Read-Only Privilege Directory</h4>
                <p style="color: #94a3b8; font-size: 13px; margin: 0;">These capabilities are granted and managed by the Super Administrator. Checkboxes below indicate your active permissions.</p>
              </div>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
              <span style="background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.3); color: #4ade80; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 700;">
                <i class="fas fa-key"></i> <?php echo $myActiveCount; ?> / <?php echo $totalAvailablePrivileges; ?> Active Capabilities
              </span>
            </div>
          </div>

          <!-- Admin Stats Cards -->
          <div class="admin-stats-grid" style="margin-bottom: 25px;">
            <div class="stat-card">
              <div class="stat-icon" style="background: rgba(255, 94, 0, 0.15); color: var(--accent);">
                <i class="fas fa-id-badge"></i>
              </div>
              <div class="stat-details">
                <h4>Account ID</h4>
                <div class="stat-number" style="font-size: 18px;"><?php echo htmlspecialchars($user['id_number']); ?></div>
                <p>@<?php echo htmlspecialchars($user['username']); ?></p>
              </div>
            </div>

            <div class="stat-card">
              <div class="stat-icon" style="background: rgba(168, 85, 247, 0.15); color: #c084fc;">
                <i class="fas fa-shield-alt"></i>
              </div>
              <div class="stat-details">
                <h4>System Role</h4>
                <div class="stat-number" style="font-size: 18px; color: #c084fc;">Administrator</div>
                <p>Elevated Staff Account</p>
              </div>
            </div>

            <div class="stat-card">
              <div class="stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #4ade80;">
                <i class="fas fa-check-circle"></i>
              </div>
              <div class="stat-details">
                <h4>Account Status</h4>
                <div class="stat-number" style="font-size: 18px; color: #4ade80; text-transform: uppercase;"><?php echo htmlspecialchars($user['status'] ?? 'approved'); ?></div>
                <p>Full System Access</p>
              </div>
            </div>

            <div class="stat-card">
              <div class="stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
                <i class="fas fa-key"></i>
              </div>
              <div class="stat-details">
                <h4>Active Privileges</h4>
                <div class="stat-number" style="font-size: 18px; color: #60a5fa;"><?php echo $myActiveCount; ?> / <?php echo $totalAvailablePrivileges; ?></div>
                <p><?php echo round(($myActiveCount / max($totalAvailablePrivileges, 1)) * 100); ?>% operational capabilities</p>
              </div>
            </div>
          </div>

          <!-- Static Categorized Privilege Matrix -->
          <div class="categories-stack">
            <?php foreach ($privilegeCategories as $catKey => $category): ?>
              <div class="category-block" style="margin-bottom: 20px;">
                <div class="category-block-header">
                  <div class="category-block-title">
                    <i class="<?php echo htmlspecialchars($category['icon']); ?>"></i>
                    <h4><?php echo htmlspecialchars($category['title']); ?></h4>
                  </div>
                </div>

                <div class="priv-items-grid">
                  <?php foreach ($category['items'] as $privKey => $item): ?>
                    <?php $isGranted = !empty($myPrivileges[$privKey]); ?>
                    <div class="priv-toggle-card <?php echo $isGranted ? 'checked' : ''; ?>" style="cursor: default; pointer-events: none;">
                      <div class="priv-toggle-left">
                        <div class="priv-icon-circle" style="<?php echo $isGranted ? 'background: var(--accent); color: #fff;' : 'background: rgba(255,255,255,0.06); color: #64748b;'; ?>">
                          <i class="<?php echo htmlspecialchars($item['icon']); ?>"></i>
                        </div>
                        <div class="priv-text-content">
                          <div class="priv-title-row" style="display: flex; align-items: center; gap: 8px;">
                            <strong style="<?php echo $isGranted ? 'color: #fff;' : 'color: #94a3b8;'; ?>"><?php echo htmlspecialchars($item['title']); ?></strong>
                          </div>
                          <p class="priv-desc" style="<?php echo $isGranted ? 'color: #cbd5e1;' : 'color: #64748b;'; ?>"><?php echo htmlspecialchars($item['desc']); ?></p>
                        </div>
                      </div>
                      
                      <div class="priv-toggle-right" style="display: flex; align-items: center; gap: 10px;">
                        <?php if ($isGranted): ?>
                          <span style="background: rgba(34, 197, 94, 0.18); border: 1px solid rgba(34, 197, 94, 0.35); color: #4ade80; font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 6px; text-transform: uppercase;">
                            <i class="fas fa-check"></i> Granted
                          </span>
                        <?php else: ?>
                          <span style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.25); color: #f87171; font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 6px; text-transform: uppercase;">
                            <i class="fas fa-lock"></i> Restricted
                          </span>
                        <?php endif; ?>
                        <input type="checkbox" disabled <?php echo $isGranted ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--accent); cursor: not-allowed;">
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

        <?php endif; ?>

        <footer class="admin-footer">
          <div class="admin-footer-brand">
            <div class="logo"><h1>Gym<span>Bros</span></h1></div>
            <p>Administration & Capabilities Center</p>
          </div>
          <p class="admin-footer-copyright">&copy; <?php echo date('Y'); ?> GymBros. All rights reserved.</p>
        </footer>
      </div>
    </div>
  </div>

  <?php if ($isSuperAdmin): ?>
    <script>
      function handleCardClick(privKey, event) {
        // Toggle checkbox when clicking card
        const chk = document.getElementById('check_' + privKey);
        if (chk) {
          chk.checked = !chk.checked;
          updatePrivilegeUIState(privKey);
        }
      }
    </script>
    <script src="../js/admin.js?v=<?php echo time(); ?>"></script>
  <?php endif; ?>
</body>
</html>
