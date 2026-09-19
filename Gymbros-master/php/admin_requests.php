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
$isAdmin = Auth::isAdmin();

if (!$isAdmin) {
  header("Location: dashboard.php");
  exit();
}

$db = new Database();
$conn = $db->getConnection();

// --- Pending Registrations Filtering & Pagination ---
$searchPending = isset($_GET['search_pending']) ? $db->sanitize(trim($_GET['search_pending'])) : '';
$monthPending  = isset($_GET['month_pending']) && $_GET['month_pending'] !== '' ? (int)$_GET['month_pending'] : '';
$datePending   = isset($_GET['date_pending']) ? $db->sanitize(trim($_GET['date_pending'])) : '';
$pagePending   = isset($_GET['page_pending']) && is_numeric($_GET['page_pending']) ? max(1, (int)$_GET['page_pending']) : 1;
$limitPending  = 8;

$wherePending = ["status = 'pending'"];
if (!$isSuperAdmin) {
  $wherePending[] = "role != 'superadmin'";
}
$pendingTypes = '';
$pendingParams = [];

if (!empty($searchPending)) {
  $wherePending[] = "(id_number LIKE ? OR username LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
  $pendingTypes .= 'sssss';
  $searchLike = '%' . $searchPending . '%';
  $pendingParams = array_merge($pendingParams, [$searchLike, $searchLike, $searchLike, $searchLike, $searchLike]);
}

if ($monthPending >= 1 && $monthPending <= 12) {
  $wherePending[] = "MONTH(created_at) = ?";
  $pendingTypes .= 'i';
  $pendingParams[] = $monthPending;
}

if (!empty($datePending)) {
  $wherePending[] = "DATE(created_at) = ?";
  $pendingTypes .= 's';
  $pendingParams[] = $datePending;
}

$wherePendingSql = implode(' AND ', $wherePending);

// Count total pending registrations matching filter
$countPendingSql = "SELECT COUNT(*) as total FROM users WHERE $wherePendingSql";
if (!empty($pendingParams)) {
  $stmtCount = $conn->prepare($countPendingSql);
  $stmtCount->bind_param($pendingTypes, ...$pendingParams);
  $stmtCount->execute();
  $totalPendingRecords = (int)$stmtCount->get_result()->fetch_assoc()['total'];
  $stmtCount->close();
} else {
  $resCount = $conn->query($countPendingSql);
  $totalPendingRecords = $resCount ? (int)$resCount->fetch_assoc()['total'] : 0;
}

$totalPendingPages = max(1, ceil($totalPendingRecords / $limitPending));
if ($pagePending > $totalPendingPages) $pagePending = $totalPendingPages;
$offsetPending = ($pagePending - 1) * $limitPending;

// Fetch paginated pending registrations
$pendingSql = "SELECT * FROM users WHERE $wherePendingSql ORDER BY created_at DESC LIMIT ?, ?";
$stmtPending = $conn->prepare($pendingSql);
if (!empty($pendingParams)) {
  $fetchPendingTypes = $pendingTypes . 'ii';
  $fetchPendingParams = array_merge($pendingParams, [$offsetPending, $limitPending]);
  $stmtPending->bind_param($fetchPendingTypes, ...$fetchPendingParams);
} else {
  $stmtPending->bind_param('ii', $offsetPending, $limitPending);
}
$stmtPending->execute();
$resPending = $stmtPending->get_result();
$pendingUsers = [];
while ($r = $resPending->fetch_assoc()) {
  $pendingUsers[] = $r;
}
$stmtPending->close();

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

// Get absolute total pending registrations count for badges
$totalAllPending = 0;
$resAllP = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE status = 'pending'");
if ($resAllP) $totalAllPending = (int)$resAllP->fetch_assoc()['cnt'];

$pendingRequestsTotal = $totalAllPending + $pendingDeleteCount;
$csrfToken = Security::generateCSRFToken();

$activeTab = isset($_GET['tab']) && in_array($_GET['tab'], ['pending-registrations', 'delete-requests']) ? $_GET['tab'] : 'pending-registrations';

function buildAdminReqUrl($paramsToMerge = []) {
  $currentParams = $_GET;
  foreach ($paramsToMerge as $k => $v) {
    if ($v === null || $v === '') {
      unset($currentParams[$k]);
    } else {
      $currentParams[$k] = $v;
    }
  }
  return 'admin_requests.php?' . http_build_query($currentParams);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Requests & Approvals | GymBros</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
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
          <li>
            <a href="privileges.php">
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
            <h2><i class="fas fa-clipboard-check"></i> Requests & Approvals Queue</h2>
            <p>Review and act on pending member registrations and authorized account deletion requests.</p>
          </div>

          <div class="admin-stat-pills">
            <div class="stat-pill pill-pending">
              <i class="fas fa-user-clock"></i>
              <div class="stat-pill-info">
                <div class="num"><?php echo $totalAllPending; ?></div>
                <div class="lbl">Pending Users</div>
              </div>
            </div>

            <div class="stat-pill pill-requests">
              <i class="fas fa-trash-restore"></i>
              <div class="stat-pill-info">
                <div class="num"><?php echo $pendingDeleteCount; ?></div>
                <div class="lbl">Deletion Requests</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="admin-tabs">
          <button type="button" class="tab-btn <?php echo $activeTab === 'pending-registrations' ? 'active' : ''; ?>" data-tab="pending-registrations">
            <i class="fas fa-user-clock"></i> Pending User Registrations
            <?php if ($totalAllPending > 0): ?>
              <span class="tab-badge"><?php echo $totalAllPending; ?></span>
            <?php endif; ?>
          </button>
          <button type="button" class="tab-btn <?php echo $activeTab === 'delete-requests' ? 'active' : ''; ?>" data-tab="delete-requests">
            <i class="fas fa-trash-restore"></i> Account Deletion Requests
            <?php if ($pendingDeleteCount > 0): ?>
              <span class="tab-badge badge-danger-glow"><?php echo $pendingDeleteCount; ?></span>
            <?php endif; ?>
          </button>
        </div>

        <!-- TAB 1: PENDING USER REGISTRATIONS -->
        <div class="admin-tab-pane <?php echo $activeTab === 'pending-registrations' ? '' : 'hidden'; ?>" id="tab-pending-registrations">
          <div class="admin-table-card">
            <div class="admin-table-card-header">
              <div class="admin-table-card-title">
                <div class="title-icon orange">
                  <i class="fas fa-user-clock"></i>
                </div>
                <div>
                  <h3>Pending User Registrations</h3>
                  <p>Review new account signups and grant access or block unauthorized applications.</p>
                </div>
              </div>
            </div>

            <!-- Filter Bar for Approvals -->
            <form method="GET" action="admin_requests.php" class="req-filter-bar">
              <input type="hidden" name="tab" value="pending-registrations">
              
              <div class="req-filter-field" style="flex: 1; min-width: 220px;">
                <label class="req-filter-label"><i class="fas fa-search"></i> Search ID / Name / Username / Email</label>
                <input type="text" name="search_pending" class="req-filter-input" value="<?php echo htmlspecialchars($searchPending); ?>" placeholder="Type keyword to search...">
              </div>

              <div class="req-filter-field" style="min-width: 150px;">
                <label class="req-filter-label"><i class="fas fa-calendar-alt"></i> Registration Month</label>
                <select name="month_pending" class="req-filter-select">
                  <option value="">All Months</option>
                  <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $monthPending === $m ? 'selected' : ''; ?>>
                      <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                    </option>
                  <?php endfor; ?>
                </select>
              </div>

              <div class="req-filter-field" style="min-width: 140px;">
                <label class="req-filter-label"><i class="fas fa-calendar-day"></i> Exact Date</label>
                <input type="date" name="date_pending" class="req-filter-input" value="<?php echo htmlspecialchars($datePending); ?>">
              </div>

              <div style="display: flex; gap: 8px; align-items: flex-end;">
                <button type="submit" class="btn-primary-action" style="padding: 9px 18px; font-size: 13px;"><i class="fas fa-filter"></i> Filter</button>
                <a href="admin_requests.php?tab=pending-registrations" class="btn-secondary-action" style="padding: 9px 14px; font-size: 13px; text-decoration: none;"><i class="fas fa-undo"></i> Reset</a>
              </div>
            </form>

            <div class="table-responsive">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Employee ID</th>
                    <th>Applicant Profile</th>
                    <th>Email Address</th>
                    <th>Applied Date</th>
                    <th>Approval Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($pendingUsers)): ?>
                    <tr>
                      <td colspan="5">
                        <div class="req-empty-state">
                          <div class="req-empty-icon success">
                            <i class="fas fa-check-circle"></i>
                          </div>
                          <div class="req-empty-title">All Caught Up!</div>
                          <div class="req-empty-sub">No pending user registrations found matching your filter criteria.</div>
                        </div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($pendingUsers as $pu): 
                      $initials = strtoupper(substr($pu['first_name'] ?? 'U', 0, 1) . substr($pu['last_name'] ?? '', 0, 1));
                    ?>
                      <tr>
                        <td><span class="chip-id"><?php echo htmlspecialchars($pu['id_number']); ?></span></td>
                        <td>
                          <div class="user-info-cell">
                            <div class="user-avatar-circle"><?php echo $initials ?: 'U'; ?></div>
                            <div class="user-names-wrap">
                              <div class="name-primary"><?php echo htmlspecialchars($pu['first_name'] . ' ' . $pu['last_name']); ?></div>
                              <div class="name-sub">@<?php echo htmlspecialchars($pu['username']); ?></div>
                            </div>
                          </div>
                        </td>
                        <td>
                          <span style="color: #cbd5e1; font-size: 13px;"><i class="fas fa-envelope" style="color: #64748b; margin-right: 6px;"></i><?php echo htmlspecialchars($pu['email']); ?></span>
                        </td>
                        <td>
                          <span style="color: #94a3b8; font-size: 13px;"><i class="fas fa-clock" style="color: #64748b; margin-right: 6px;"></i><?php echo date('M d, Y h:i A', strtotime($pu['created_at'])); ?></span>
                        </td>
                        <td>
                          <div class="action-btns">
                            <button class="btn-approve-pill" onclick="updateUserStatus('<?php echo $pu['id_number']; ?>', 'approved')" title="Approve and activate account">
                              <i class="fas fa-check-circle"></i> Approve
                            </button>
                            <button class="btn-block-pill" onclick="updateUserStatus('<?php echo $pu['id_number']; ?>', 'blocked')" title="Reject and block account">
                              <i class="fas fa-ban"></i> Block
                            </button>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <!-- Pagination for Pending Registrations -->
            <?php if ($totalPendingPages > 1): ?>
              <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding: 12px 16px; background: rgba(15, 23, 42, 0.6); border-radius: 10px;">
                <div style="font-size: 13px; color: #94a3b8;">
                  Showing <strong><?php echo $totalPendingRecords > 0 ? $offsetPending + 1 : 0; ?></strong> to <strong><?php echo min($offsetPending + $limitPending, $totalPendingRecords); ?></strong> of <strong><?php echo number_format($totalPendingRecords); ?></strong> pending registrations
                </div>
                <div style="display: flex; gap: 6px;">
                  <a href="<?php echo buildAdminReqUrl(['page_pending' => $pagePending - 1, 'tab' => 'pending-registrations']); ?>" class="page-link-btn <?php echo $pagePending <= 1 ? 'disabled' : ''; ?>" style="padding: 6px 12px; border-radius: 6px; background: rgba(255,255,255,0.08); color: #fff; text-decoration: none; font-size: 13px;" title="Previous">
                    <i class="fas fa-chevron-left"></i>
                  </a>
                  <?php for ($p = 1; $p <= $totalPendingPages; $p++): ?>
                    <a href="<?php echo buildAdminReqUrl(['page_pending' => $p, 'tab' => 'pending-registrations']); ?>" style="padding: 6px 12px; border-radius: 6px; background: <?php echo $pagePending === $p ? 'var(--accent, #ff5e00)' : 'rgba(255,255,255,0.08)'; ?>; color: #fff; text-decoration: none; font-size: 13px; font-weight: <?php echo $pagePending === $p ? '700' : 'normal'; ?>;">
                      <?php echo $p; ?>
                    </a>
                  <?php endfor; ?>
                  <a href="<?php echo buildAdminReqUrl(['page_pending' => $pagePending + 1, 'tab' => 'pending-registrations']); ?>" class="page-link-btn <?php echo $pagePending >= $totalPendingPages ? 'disabled' : ''; ?>" style="padding: 6px 12px; border-radius: 6px; background: rgba(255,255,255,0.08); color: #fff; text-decoration: none; font-size: 13px;" title="Next">
                    <i class="fas fa-chevron-right"></i>
                  </a>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- TAB 2: ACCOUNT DELETION REQUESTS -->
        <div class="admin-tab-pane <?php echo $activeTab === 'delete-requests' ? '' : 'hidden'; ?>" id="tab-delete-requests">
          <div class="admin-table-card">
            <div class="admin-table-card-header">
              <div class="admin-table-card-title">
                <div class="title-icon danger">
                  <i class="fas fa-trash-restore"></i>
                </div>
                <div>
                  <h3>Account Deletion Requests</h3>
                  <p><?php echo $isSuperAdmin ? 'Review, authorize, or reject account termination requests submitted by administrators.' : 'Track the status of your submitted account deletion requests.'; ?></p>
                </div>
              </div>
            </div>

            <!-- Live Search & Filter Bar for Deletion Requests -->
            <div class="req-filter-bar">
              <div class="req-filter-field" style="flex: 1; min-width: 220px;">
                <label class="req-filter-label"><i class="fas fa-search"></i> Search Target User / Requester / Reason / ID</label>
                <input type="text" id="search-deletion-requests" class="req-filter-input" placeholder="Live search deletion queue...">
              </div>

              <div class="req-filter-field" style="min-width: 160px;">
                <label class="req-filter-label"><i class="fas fa-filter"></i> Request Status</label>
                <select id="filter-deletion-status" class="req-filter-select">
                  <option value="">All Statuses</option>
                  <option value="pending">Pending Review</option>
                  <option value="approved">Approved (Deleted)</option>
                  <option value="rejected">Rejected</option>
                </select>
              </div>
            </div>

            <div class="table-responsive">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Req ID</th>
                    <th>Target User Account</th>
                    <th>Reason for Deletion</th>
                    <th>Submitted By</th>
                    <th>Date Submitted</th>
                    <th>Status</th>
                    <th>Review Decision</th>
                  </tr>
                </thead>
                <tbody id="deletion-requests-table-body">
                  <?php if (empty($deleteRequests)): ?>
                    <tr>
                      <td colspan="7">
                        <div class="req-empty-state">
                          <div class="req-empty-icon neutral">
                            <i class="fas fa-trash-restore"></i>
                          </div>
                          <div class="req-empty-title">No Deletion Requests</div>
                          <div class="req-empty-sub">There are currently no account deletion requests in the queue.</div>
                        </div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($deleteRequests as $dr): 
                      $targetRole = $dr['target_role'] ?? 'user';
                      $targetInitials = strtoupper(substr($dr['target_fname'] ?? 'U', 0, 1) . substr($dr['target_lname'] ?? '', 0, 1));
                    ?>
                      <tr data-reqid="<?php echo $dr['id']; ?>"
                          data-target-user="<?php echo htmlspecialchars(($dr['target_fname'] ?? '') . ' ' . ($dr['target_lname'] ?? '') . ' ' . ($dr['target_username'] ?? '') . ' ' . $dr['target_user_id']); ?>"
                          data-requester="<?php echo htmlspecialchars(($dr['req_fname'] ?? '') . ' ' . ($dr['req_lname'] ?? '') . ' ' . ($dr['req_username'] ?? '') . ' ' . $dr['requested_by']); ?>"
                          data-reason="<?php echo htmlspecialchars($dr['reason']); ?>"
                          data-status="<?php echo htmlspecialchars($dr['status']); ?>">
                        <td><span class="chip-id">#REQ-<?php echo str_pad($dr['id'], 2, '0', STR_PAD_LEFT); ?></span></td>
                        <td>
                          <div class="user-info-cell">
                            <div class="user-avatar-circle danger"><?php echo $targetInitials ?: 'U'; ?></div>
                            <div class="user-names-wrap">
                              <div class="name-primary"><?php echo htmlspecialchars(($dr['target_fname'] ?? 'Unknown') . ' ' . ($dr['target_lname'] ?? '')); ?></div>
                              <div class="name-sub">
                                <span>ID: <?php echo htmlspecialchars($dr['target_user_id']); ?></span>
                                <span>•</span>
                                <span>@<?php echo htmlspecialchars($dr['target_username'] ?? 'deleted'); ?></span>
                              </div>
                            </div>
                          </div>
                        </td>
                        <td>
                          <div class="req-reason-quote">
                            <i class="fas fa-quote-left" style="opacity: 0.5; margin-right: 4px;"></i><?php echo htmlspecialchars($dr['reason']); ?>
                          </div>
                        </td>
                        <td>
                          <div style="font-weight: 600; color: #60a5fa; font-size: 13px;">
                            <i class="fas fa-user-shield" style="margin-right: 4px;"></i><?php echo htmlspecialchars(($dr['req_fname'] ?? '') . ' ' . ($dr['req_lname'] ?? 'Admin')); ?>
                          </div>
                          <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">ID: <?php echo htmlspecialchars($dr['requested_by']); ?></div>
                        </td>
                        <td>
                          <span style="color: #94a3b8; font-size: 13px;"><i class="fas fa-calendar-alt" style="color: #64748b; margin-right: 6px;"></i><?php echo date('M d, Y h:i A', strtotime($dr['requested_at'])); ?></span>
                        </td>
                        <td>
                          <?php if ($dr['status'] === 'pending'): ?>
                            <span class="badge badge-pending"><i class="fas fa-hourglass-half"></i> Pending Review</span>
                          <?php elseif ($dr['status'] === 'approved'): ?>
                            <span class="badge badge-approved"><i class="fas fa-check-circle"></i> Approved (Deleted)</span>
                          <?php else: ?>
                            <span class="badge badge-blocked"><i class="fas fa-times-circle"></i> Rejected</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if ($dr['status'] === 'pending' && $isSuperAdmin): ?>
                            <button class="btn-review-action" onclick="openReviewDeleteRequestModal(<?php echo $dr['id']; ?>)">
                              <i class="fas fa-gavel"></i> Review & Decision
                            </button>
                          <?php elseif ($dr['status'] === 'pending'): ?>
                            <span style="font-size: 12px; color: #fbbf24; font-weight: 600;"><i class="fas fa-hourglass-half"></i> Pending Super Admin</span>
                          <?php else: ?>
                            <span style="font-size: 12px; color: #94a3b8;"><i class="fas fa-check-double"></i> Completed</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
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
          <button type="button" class="btn-secondary-action" onclick="processDeleteRequest('reject')" style="background: #eab308; border-color: #eab308; color: #000; font-weight: 600;">
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
