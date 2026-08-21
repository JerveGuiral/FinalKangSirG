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

if (!$isAdmin) {
  header("Location: dashboard.php");
  exit();
}

$db = new Database();
$conn = $db->getConnection();

// Fetch Pending User Registrations
$pendingUsers = [];
$resPending = $conn->query("SELECT * FROM users WHERE status = 'pending' ORDER BY created_at DESC");
if ($resPending) {
  while ($r = $resPending->fetch_assoc()) {
    $pendingUsers[] = $r;
  }
}

// Fetch Delete Requests
$deleteRequests = [];
$pendingDeleteCount = 0;
if ($isSuperAdmin) {
  $qDel = "SELECT dr.*, u.username as target_username, u.first_name as target_fname, u.last_name as target_lname, u.email as target_email, u.role as target_role, req.username as req_username, req.first_name as req_fname, req.last_name as req_lname
           FROM delete_requests dr
           LEFT JOIN users u ON dr.target_user_id = u.id_number
           LEFT JOIN users req ON dr.requested_by = req.id_number
           ORDER BY dr.requested_at DESC";
  $resDel = $conn->query($qDel);
  if ($resDel) {
    while ($rd = $resDel->fetch_assoc()) {
      $deleteRequests[] = $rd;
      if ($rd['status'] === 'pending') {
        $pendingDeleteCount++;
      }
    }
  }
} else {
  // Regular Admin sees delete requests submitted by themselves
  $adminId = $user['id_number'];
  $qDel = "SELECT dr.*, u.username as target_username, u.first_name as target_fname, u.last_name as target_lname, u.email as target_email, u.role as target_role
           FROM delete_requests dr
           LEFT JOIN users u ON dr.target_user_id = u.id_number
           WHERE dr.requested_by = '$adminId'
           ORDER BY dr.requested_at DESC";
  $resDel = $conn->query($qDel);
  if ($resDel) {
    while ($rd = $resDel->fetch_assoc()) {
      $deleteRequests[] = $rd;
    }
  }
}

$pendingRequestsTotal = count($pendingUsers) + $pendingDeleteCount;
$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Requests & Approvals | GymBros</title>
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
      <p>Loading Requests Queue...</p>
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
          <?php if ($isSuperAdmin): ?>
            <span class="badge-superadmin"><i class="fas fa-crown"></i> SUPER ADMIN</span>
          <?php else: ?>
            <span class="badge-admin"><i class="fas fa-shield-alt"></i> ADMIN PORTAL</span>
          <?php endif; ?>
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
          <li>
            <a href="dashboard.php">
              <i class="fas fa-users-cog"></i> <span>Accounts Console</span>
            </a>
          </li>
          <li>
            <a href="admin_requests.php" class="active">
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
          <h3><i class="fas fa-clipboard-check"></i> Requests & Approvals Queue</h3>
        </div>
        <div class="topbar-right">
          <div class="topbar-user-chip"><i class="fas fa-user-shield"></i> <span>@<?php echo htmlspecialchars($user['username']); ?></span></div>
          <a href="logout.php" class="btn-topbar-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
      </header>

      <div class="admin-page-content">
        <!-- Header Banner -->
        <div class="admin-header">
          <div class="admin-header-title">
            <h2><i class="fas fa-clipboard-check"></i> Requests & Approvals</h2>
            <p>Review and act on pending user registrations and account deletion requests.</p>
          </div>

          <div class="admin-stat-pills">
            <div class="stat-pill pill-pending">
              <i class="fas fa-user-clock"></i>
              <div class="stat-pill-info">
                <div class="num"><?php echo count($pendingUsers); ?></div>
                <div class="lbl">Pending Registrations</div>
              </div>
            </div>

            <div class="stat-pill pill-requests">
              <i class="fas fa-trash-restore"></i>
              <div class="stat-pill-info">
                <div class="num"><?php echo $pendingDeleteCount; ?></div>
                <div class="lbl">Pending Delete Requests</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="admin-tabs">
          <button class="tab-btn active" data-tab="pending-registrations">
            <i class="fas fa-user-check"></i> Pending User Registrations
            <?php if (count($pendingUsers) > 0): ?>
              <span class="tab-badge"><?php echo count($pendingUsers); ?></span>
            <?php endif; ?>
          </button>
          <button class="tab-btn" data-tab="delete-requests">
            <i class="fas fa-exclamation-triangle"></i> Account Deletion Requests
            <?php if ($pendingDeleteCount > 0): ?>
              <span class="tab-badge"><?php echo $pendingDeleteCount; ?></span>
            <?php endif; ?>
          </button>
        </div>

        <!-- TAB 1: PENDING USER REGISTRATIONS -->
        <div class="admin-tab-pane" id="tab-pending-registrations">
          <div class="table-responsive">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Employee ID</th>
                  <th>Full Name</th>
                  <th>Username</th>
                  <th>Email</th>
                  <th>Registration Date</th>
                  <th>Approval Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($pendingUsers)): ?>
                  <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">
                      <i class="fas fa-check-circle" style="font-size: 36px; color: #4ade80; margin-bottom: 10px; display: block;"></i>
                      All user registrations are up to date. No pending approvals!
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($pendingUsers as $pu): ?>
                    <tr>
                      <td><strong><?php echo htmlspecialchars($pu['id_number']); ?></strong></td>
                      <td>
                        <div style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($pu['first_name'] . ' ' . $pu['last_name']); ?></div>
                      </td>
                      <td>@<?php echo htmlspecialchars($pu['username']); ?></td>
                      <td><?php echo htmlspecialchars($pu['email']); ?></td>
                      <td><?php echo date('M d, Y h:i A', strtotime($pu['created_at'])); ?></td>
                      <td>
                        <div class="action-btns">
                          <button class="btn-primary-action" style="padding: 6px 14px; font-size: 13px; background: #16a34a;" onclick="updateUserStatus('<?php echo $pu['id_number']; ?>', 'approved')">
                            <i class="fas fa-user-check"></i> Accept / Approve
                          </button>
                          <button class="btn-secondary-action" style="padding: 6px 14px; font-size: 13px; background: #dc2626; border-color: #dc2626; color: #fff;" onclick="updateUserStatus('<?php echo $pu['id_number']; ?>', 'blocked')">
                            <i class="fas fa-user-slash"></i> Block
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- TAB 2: ACCOUNT DELETION REQUESTS -->
        <div class="admin-tab-pane hidden" id="tab-delete-requests">
          <div class="table-responsive">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Req ID</th>
                  <th>Target User</th>
                  <th>Reason for Deletion</th>
                  <th>Requested By</th>
                  <th>Request Date</th>
                  <th>Status</th>
                  <th>Review Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($deleteRequests)): ?>
                  <tr>
                    <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                      <i class="fas fa-inbox" style="font-size: 36px; color: #94a3b8; margin-bottom: 10px; display: block;"></i>
                      No account deletion requests found.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($deleteRequests as $dr): ?>
                    <tr>
                      <td>#<?php echo $dr['id']; ?></td>
                      <td>
                        <div style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars(($dr['target_fname'] ?? '') . ' ' . ($dr['target_lname'] ?? '')); ?></div>
                        <div style="font-size: 12px; color: #94a3b8;">ID: <?php echo htmlspecialchars($dr['target_user_id']); ?> (@<?php echo htmlspecialchars($dr['target_username'] ?? 'deleted'); ?>)</div>
                      </td>
                      <td style="max-width: 260px; color: #f87171; font-weight: 500;">
                        "<?php echo htmlspecialchars($dr['reason']); ?>"
                      </td>
                      <td>
                        <div style="font-weight: 600; color: #60a5fa;"><?php echo htmlspecialchars(($dr['req_fname'] ?? '') . ' ' . ($dr['req_lname'] ?? 'Admin')); ?></div>
                        <div style="font-size: 12px; color: #94a3b8;">ID: <?php echo htmlspecialchars($dr['requested_by']); ?></div>
                      </td>
                      <td style="font-size: 13px; color: #94a3b8;"><?php echo date('M d, Y h:i A', strtotime($dr['requested_at'])); ?></td>
                      <td>
                        <?php if ($dr['status'] === 'pending'): ?>
                          <span class="badge badge-pending">Pending Review</span>
                        <?php elseif ($dr['status'] === 'approved'): ?>
                          <span class="badge badge-approved">Approved (Deleted)</span>
                        <?php else: ?>
                          <span class="badge badge-blocked">Rejected</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($dr['status'] === 'pending' && $isSuperAdmin): ?>
                          <button class="btn-primary-action" style="padding: 6px 14px; font-size: 13px;" onclick="openReviewDeleteRequestModal(<?php echo $dr['id']; ?>)">
                            <i class="fas fa-search-plus"></i> Review & Decision
                          </button>
                        <?php elseif ($dr['status'] === 'pending'): ?>
                          <span style="font-size: 12px; color: #fbbf24;"><i class="fas fa-hourglass-half"></i> Pending Super Admin</span>
                        <?php else: ?>
                          <span style="font-size: 12px; color: #94a3b8;">Completed</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
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
      </div>
    </div>

  <!-- SUPER ADMIN REVIEW DELETE REQUEST MODAL -->
  <?php if ($isSuperAdmin): ?>
    <div class="modal-overlay" id="modal-review-delete-request">
      <div class="modal-card" style="max-width: 650px;">
        <div class="modal-header">
          <h3><i class="fas fa-clipboard-check"></i> Review Deletion Request</h3>
          <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="review-req-id">
          <div class="detail-box" style="border-color: rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.08);">
            <h4 style="color: #ef4444;"><i class="fas fa-comment-alt"></i> Reason for Deletion</h4>
            <p id="review-req-reason" style="color: #fff; font-size: 14px; font-style: italic;"></p>
            <div style="margin-top: 10px; font-size: 12px; color: #94a3b8; display: flex; justify-content: space-between;">
              <span>Submitted by: <strong id="review-req-by" style="color: #60a5fa;"></strong></span>
              <span id="review-req-date"></span>
            </div>
          </div>

          <div class="detail-box">
            <h4><i class="fas fa-id-card"></i> Complete User Details</h4>
            <div class="detail-row"><span class="detail-label">Employee ID:</span><span class="detail-val" id="review-target-id"></span></div>
            <div class="detail-row"><span class="detail-label">Full Name:</span><span class="detail-val" id="review-target-name"></span></div>
            <div class="detail-row"><span class="detail-label">Username:</span><span class="detail-val" id="review-target-username"></span></div>
            <div class="detail-row"><span class="detail-label">Email:</span><span class="detail-val" id="review-target-email"></span></div>
            <div class="detail-row"><span class="detail-label">Role:</span><span class="detail-val" id="review-target-role"></span></div>
            <div class="detail-row"><span class="detail-label">Address:</span><span class="detail-val" id="review-target-address"></span></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-secondary-action" onclick="processDeleteRequest('reject')" style="background: #eab308; border-color: #eab308; color: #000;">
            <i class="fas fa-times-circle"></i> Reject Request
          </button>
          <button type="button" class="btn-primary-action" onclick="processDeleteRequest('approve')" style="background: #dc2626;">
            <i class="fas fa-check-circle"></i> Approve & Delete User
          </button>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <script src="../js/loader.js"></script>
  <script src="../js/admin.js"></script>
</body>

</html>
