<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/auth.php';

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

// Only Super Admin can create Super Admin/Admin accounts directly
if (!$isSuperAdmin) {
  header("Location: dashboard.php");
  exit();
}

$db = new Database();
$conn = $db->getConnection();

// Calculate pending requests for navbar badge count
$pendingRequestsTotal = 0;
$resP = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE status = 'pending'");
if ($resP) $pendingRequestsTotal += (int)$resP->fetch_assoc()['cnt'];

$resD = $conn->query("SELECT COUNT(*) as cnt FROM delete_requests WHERE status = 'pending'");
if ($resD) $pendingRequestsTotal += (int)$resD->fetch_assoc()['cnt'];

$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create New Account | GymBros</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/admin.css">
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
      <p>Loading Form...</p>
    </div>
  </div>

  <div class="admin-layout-wrapper">
    <!-- SIDEBAR -->
    <aside class="admin-sidebar" id="adminSidebar">
      <div class="sidebar-brand">
        <div class="logo">
          <h1>Gym<span>Bros</span></h1>
        </div>
        <div class="role-badge-pill">
          <span class="badge-superadmin"><i class="fas fa-crown"></i> SUPER ADMIN</span>
        </div>
      </div>

      <div class="sidebar-user">
        <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
        <div class="user-info">
          <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
          <div class="user-id">ID: <?php echo htmlspecialchars($user['id_number']); ?></div>
        </div>
      </div>

      <nav class="sidebar-nav">
        <ul>
          <li><a href="dashboard.php"><i class="fas fa-users-cog"></i> <span>Accounts Console</span></a></li>
          <li>
            <a href="admin_requests.php">
              <i class="fas fa-clipboard-list"></i> <span>Requests Queue</span>
              <?php if ($pendingRequestsTotal > 0): ?>
                <span class="nav-badge"><?php echo $pendingRequestsTotal; ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li>
            <a href="privileges.php">
              <i class="fas fa-user-shield"></i> <span>Privileges</span>
            </a>
          </li>
          <li>
            <a href="create_account.php" class="active">
              <i class="fas fa-user-plus"></i> <span>Create Account</span>
            </a>
          </li>
          <li>
            <a href="logs.php">
              <i class="fas fa-history"></i> <span>System Logs</span>
            </a>
          </li>
          <li class="nav-divider"></li>
          <li><a href="change-password.php"><i class="fas fa-key"></i> <span>Change Password</span></a></li>
          <li><a href="logout.php" class="nav-logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
      </nav>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="admin-main-wrapper">
      <header class="admin-topbar">
        <button class="sidebar-toggle-btn" id="sidebarToggleBtn" title="Toggle Navigation"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">
          <h3><i class="fas fa-user-plus"></i> Account Provisioning Portal</h3>
        </div>
        <div class="topbar-right">
          <div class="topbar-user-chip"><i class="fas fa-user-shield"></i> <span>@<?php echo htmlspecialchars($user['username']); ?></span></div>
          <a href="logout.php" class="btn-topbar-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
      </header>

      <div class="admin-page-content">
        <div style="background: rgba(26, 31, 59, 0.7); backdrop-filter: blur(15px); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 20px; padding: 35px; max-width: 900px; margin: 0 auto; box-shadow: 0 15px 40px rgba(0,0,0,0.5);">
          
          <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px; margin-bottom: 25px;">
            <div>
              <h2 style="font-family: 'Oswald', sans-serif; color: var(--accent); font-size: 26px; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-user-plus"></i> Create New Administrator / Account
              </h2>
              <p style="color: #94a3b8; font-size: 14px; margin-top: 4px;">Directly create Super Administrator, Administrator, or User accounts.</p>
            </div>
            <a href="dashboard.php" class="btn-secondary-action" style="font-size: 13px;">
              <i class="fas fa-arrow-left"></i> Dashboard
            </a>
          </div>
          <!-- Essential Rules Box -->
          <div style="background: rgba(255, 94, 0, 0.08); border: 1px solid rgba(255, 94, 0, 0.25); border-radius: 12px; padding: 16px 20px; margin-bottom: 25px;">
            <h4 style="color: var(--accent); font-size: 15px; font-weight: 700; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
              <i class="fas fa-shield-alt"></i> Essential Administrator Account Rules
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 10px; font-size: 12px; color: #cbd5e1;">
              <div><i class="fas fa-id-badge" style="color: #60a5fa;"></i> <strong>Employee ID:</strong> Must be unique in system</div>
              <div><i class="fas fa-user-check" style="color: #60a5fa;"></i> <strong>Username:</strong> 3-20 letters, numbers, or underscores</div>
              <div><i class="fas fa-key" style="color: #60a5fa;"></i> <strong>Password:</strong> Auto-generated & emailed to the account holder</div>
              <div><i class="fas fa-birthday-cake" style="color: #60a5fa;"></i> <strong>Age Requirement:</strong> Minimum 18 years old</div>
              <div><i class="fas fa-envelope" style="color: #60a5fa;"></i> <strong>Email Address:</strong> Valid & unique email address</div>
            </div>
          </div>

          <!-- Auto-Generated Password Notice -->
          <div style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 12px; padding: 14px 20px; margin-bottom: 25px; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-shield-alt" style="color: #60a5fa; font-size: 18px; flex-shrink: 0;"></i>
            <p style="margin: 0; font-size: 12.5px; color: #cbd5e1; line-height: 1.5;">
              A secure temporary password will be generated automatically and emailed to the account holder. They will be required to set their own password and configure 3 security questions the first time they log in.
            </p>
          </div>

          <form id="form-create-account" onsubmit="submitCreateAccountForm(event)">
            <div class="form-grid">
              <div class="form-field">
                <label><i class="fas fa-id-card" style="color: var(--accent);"></i> Employee ID / Account ID *</label>
                <input type="text" id="create-id-number" placeholder="e.g. EMP-2025-001" required>
              </div>

              <div class="form-field">
                <label><i class="fas fa-user" style="color: var(--accent);"></i> Username *</label>
                <input type="text" id="create-username" placeholder="Enter username" required>
              </div>

              <div class="form-field">
                <label><i class="fas fa-user-shield" style="color: var(--accent);"></i> Account Role *</label>
                <select id="create-role" onchange="handleCreateRoleChange(this.value)" required>
                  <option value="admin" selected>Administrator</option>
                  <option value="superadmin">Super Administrator</option>
                  <option value="user">Regular User</option>
                </select>
              </div>

              <div class="form-field">
                <label><i class="fas fa-toggle-on" style="color: var(--accent);"></i> Initial Status *</label>
                <select id="create-status" required>
                  <option value="approved" selected>Approved (Active)</option>
                  <option value="pending">Pending Approval</option>
                  <option value="blocked">Blocked</option>
                </select>
              </div>

              <div class="form-field">
                <label>First Name *</label>
                <input type="text" id="create-firstname" placeholder="First Name" required>
              </div>

              <div class="form-field">
                <label>Middle Name</label>
                <input type="text" id="create-middlename" placeholder="Middle Name">
              </div>

              <div class="form-field">
                <label>Last Name *</label>
                <input type="text" id="create-lastname" placeholder="Last Name" required>
              </div>

              <div class="form-field">
                <label>Extension Name</label>
                <input type="text" id="create-extension" placeholder="e.g. Jr, Sr, III">
              </div>

              <div class="form-field">
                <label><i class="fas fa-envelope" style="color: var(--accent);"></i> Email Address *</label>
                <input type="email" id="create-email" placeholder="email@example.com" required>
              </div>

              <div class="form-field">
                <label><i class="fas fa-calendar" style="color: var(--accent);"></i> Birthdate *</label>
                <input type="date" id="create-birthdate" required>
              </div>

              <div class="form-field">
                <label>Sex</label>
                <select id="create-sex">
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="other">Other</option>
                </select>
              </div>

              <div class="form-field">
                <label>Purok / Street</label>
                <input type="text" id="create-purok" placeholder="Purok / Street">
              </div>

              <div class="form-field">
                <label>Barangay</label>
                <input type="text" id="create-barangay" placeholder="Barangay">
              </div>

              <div class="form-field">
                <label>City / Municipality</label>
                <input type="text" id="create-city" placeholder="City">
              </div>

              <div class="form-field">
                <label>Province</label>
                <input type="text" id="create-province" placeholder="Province">
              </div>

              <div class="form-field">
                <label>Zip Code</label>
                <input type="text" id="create-zip" placeholder="Zip Code">
              </div>
            </div>

            <!-- Initial Administrative Privileges (Automatically Configured) -->
            <div id="create-privileges-section" style="margin-top: 25px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
                <div>
                  <h4 style="color: var(--accent); font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-key"></i> Administrative Privileges & Capabilities
                  </h4>
                  <p style="font-size: 12.5px; color: #94a3b8; margin-top: 3px;">Standard administrator privileges are automatically granted upon creation.</p>
                </div>
                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                  <button type="button" class="btn-category-toggle" onclick="applyCreatePrivilegesPreset('admin_default')" style="background: rgba(255, 94, 0, 0.15); border-color: rgba(255, 94, 0, 0.4); color: #ff7a18;"><i class="fas fa-magic"></i> Default Admin</button>
                  <button type="button" class="btn-category-toggle" onclick="toggleAllCreatePrivileges(true)"><i class="fas fa-check-double"></i> Select All</button>
                  <button type="button" class="btn-category-toggle" onclick="toggleAllCreatePrivileges(false)"><i class="fas fa-times"></i> Clear All</button>
                </div>
              </div>
              
              <div class="privilege-checkbox-grid">
                <label class="privilege-item"><input type="checkbox" id="create_priv_can_approve_users" checked><div><strong>Approve / Unblock Users</strong><div style="font-size: 11px; color: #94a3b8;">Accept registrations & unblock</div></div></label>
                <label class="privilege-item"><input type="checkbox" id="create_priv_can_block_users" checked><div><strong>Block / Suspend Users</strong><div style="font-size: 11px; color: #94a3b8;">Restrict account access</div></div></label>
                <label class="privilege-item"><input type="checkbox" id="create_priv_can_update_info" checked><div><strong>Update Account Info</strong><div style="font-size: 11px; color: #94a3b8;">Edit profiles & credentials</div></div></label>
                <label class="privilege-item"><input type="checkbox" id="create_priv_can_manage_roles" checked><div><strong>Manage Roles</strong><div style="font-size: 11px; color: #94a3b8;">Change user roles</div></div></label>
                <label class="privilege-item"><input type="checkbox" id="create_priv_can_create_accounts" checked><div><strong>Provision Accounts</strong><div style="font-size: 11px; color: #94a3b8;">Create admins & users</div></div></label>
                <label class="privilege-item"><input type="checkbox" id="create_priv_can_delete_users"><div><strong>Direct Deletion</strong><div style="font-size: 11px; color: #94a3b8;">Permanently delete accounts</div></div></label>
                <label class="privilege-item"><input type="checkbox" id="create_priv_can_manage_requests" checked><div><strong>Review Delete Requests</strong><div style="font-size: 11px; color: #94a3b8;">Approve/reject deletion queue</div></div></label>
                <label class="privilege-item"><input type="checkbox" id="create_priv_can_give_privileges"><div><strong>Grant Privileges</strong><div style="font-size: 11px; color: #94a3b8;">Delegate permissions</div></div></label>
                <label class="privilege-item"><input type="checkbox" id="create_priv_can_view_reports" checked><div><strong>View System Logs</strong><div style="font-size: 11px; color: #94a3b8;">Audit trail & login history</div></div></label>
                <label class="privilege-item"><input type="checkbox" id="create_priv_can_export_logs" checked><div><strong>Export Logs</strong><div style="font-size: 11px; color: #94a3b8;">Download reports & trails</div></div></label>
                <label class="privilege-item"><input type="checkbox" id="create_priv_can_manage_classes" checked><div><strong>Manage Classes</strong><div style="font-size: 11px; color: #94a3b8;">Schedules & trainers</div></div></label>
                <label class="privilege-item"><input type="checkbox" id="create_priv_can_manage_bookings" checked><div><strong>Manage Bookings</strong><div style="font-size: 11px; color: #94a3b8;">Class reservations</div></div></label>
                <label class="privilege-item"><input type="checkbox" id="create_priv_can_manage_metrics" checked><div><strong>Fitness Metrics</strong><div style="font-size: 11px; color: #94a3b8;">BMIs & workout logs</div></div></label>
              </div>
            </div>

            <script>
              const DEFAULT_ADMIN_PRIVILEGE_KEYS = [
                'can_approve_users', 'can_block_users', 'can_update_info', 'can_manage_roles', 'can_create_accounts',
                'can_manage_requests',
                'can_view_reports', 'can_export_logs',
                'can_manage_classes', 'can_manage_bookings', 'can_manage_metrics'
              ];

              const ALL_CREATE_PRIVILEGE_KEYS = [
                'can_approve_users', 'can_block_users', 'can_update_info', 'can_manage_roles', 'can_create_accounts',
                'can_delete_users', 'can_manage_requests', 'can_give_privileges',
                'can_view_reports', 'can_export_logs',
                'can_manage_classes', 'can_manage_bookings', 'can_manage_metrics'
              ];

              function handleCreateRoleChange(role) {
                const section = document.getElementById('create-privileges-section');
                if (!section) return;

                if (role === 'user') {
                  section.style.display = 'none';
                  toggleAllCreatePrivileges(false);
                } else if (role === 'superadmin') {
                  section.style.display = 'block';
                  toggleAllCreatePrivileges(true);
                } else if (role === 'admin') {
                  section.style.display = 'block';
                  applyCreatePrivilegesPreset('admin_default');
                }
              }

              function applyCreatePrivilegesPreset(preset) {
                if (preset === 'admin_default') {
                  ALL_CREATE_PRIVILEGE_KEYS.forEach(k => {
                    const el = document.getElementById('create_priv_' + k);
                    if (el) el.checked = DEFAULT_ADMIN_PRIVILEGE_KEYS.includes(k);
                  });
                }
              }

              function toggleAllCreatePrivileges(checked) {
                ALL_CREATE_PRIVILEGE_KEYS.forEach(k => {
                  const el = document.getElementById('create_priv_' + k);
                  if (el) el.checked = checked;
                });
              }

              // Auto initialize on load
              document.addEventListener('DOMContentLoaded', () => {
                const roleSelect = document.getElementById('create-role');
                if (roleSelect) {
                  handleCreateRoleChange(roleSelect.value);
                }
              });
            </script>

            <div style="margin-top: 30px; display: flex; justify-content: flex-end; gap: 15px;">
              <a href="dashboard.php" class="btn-secondary-action">Cancel</a>
              <button type="submit" class="btn-primary-action" style="padding: 12px 25px; font-size: 15px;">
                <i class="fas fa-plus-circle"></i> Create Account Now
              </button>
            </div>
          </form>
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

  <script src="../js/loader.js?v=<?php echo time(); ?>"></script>
  <script src="../js/admin.js?v=<?php echo time(); ?>"></script>
</body>

</html>
