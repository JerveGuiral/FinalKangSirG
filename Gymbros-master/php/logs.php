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
$currentUserRole = $user['role'] ?? 'user';

// Access Control: Super Admin has full access. For Admins & Users, check assigned privilege
if (!$isSuperAdmin && !Auth::hasPrivilege('can_view_reports') && !Auth::hasPrivilege('can_view_logs')) {
  $_SESSION['error_message'] = "Access denied. You do not have privilege to view system logs. Please contact a Super Administrator.";
  header("Location: dashboard.php");
  exit();
}

$db = new Database();
$conn = $db->getConnection();

// Permissions: 
// Super Admin can view all logs (superadmin, admin, user)
// Administrator can view only admin and user logs
// Regular Users can view only user logs
if ($isSuperAdmin) {
  $allowedRoles = ["'superadmin'", "'admin'", "'user'"];
} elseif ($isAdmin) {
  $allowedRoles = ["'admin'", "'user'"];
} else {
  $allowedRoles = ["'user'"];
}
$roleCondition = "role IN (" . implode(',', $allowedRoles) . ")";

// --- Filter Parameters ---
$filterMonth = isset($_GET['month']) && $_GET['month'] !== '' ? (int)$_GET['month'] : '';
$filterDate  = isset($_GET['date']) && !empty($_GET['date']) ? $db->sanitize($_GET['date']) : '';
$filterYear  = isset($_GET['year']) && $_GET['year'] !== '' ? (int)$_GET['year'] : '';
$filterRole  = isset($_GET['role']) && !empty($_GET['role']) ? $db->sanitize($_GET['role']) : '';
$searchQuery = isset($_GET['search']) && !empty($_GET['search']) ? $db->sanitize($_GET['search']) : '';

// Build WHERE clauses
$whereClauses = [$roleCondition];
$types = '';
$params = [];

if ($filterMonth >= 1 && $filterMonth <= 12) {
  $whereClauses[] = "MONTH(time_in) = ?";
  $types .= 'i';
  $params[] = $filterMonth;
}

if (!empty($filterDate)) {
  $whereClauses[] = "DATE(time_in) = ?";
  $types .= 's';
  $params[] = $filterDate;
}

if ($filterYear > 2000) {
  $whereClauses[] = "YEAR(time_in) = ?";
  $types .= 'i';
  $params[] = $filterYear;
}

if (!empty($filterRole)) {
  // Check if requested role is allowed for current user
  if ($isSuperAdmin || ($isAdmin && in_array($filterRole, ['admin', 'user'])) || (!$isAdmin && $filterRole === 'user')) {
    $whereClauses[] = "role = ?";
    $types .= 's';
    $params[] = $filterRole;
  }
}

if (!empty($searchQuery)) {
  $whereClauses[] = "(id_number LIKE ? OR username LIKE ? OR full_name LIKE ?)";
  $types .= 'sss';
  $searchLike = '%' . $searchQuery . '%';
  $params[] = $searchLike;
  $params[] = $searchLike;
  $params[] = $searchLike;
}

$whereSql = implode(' AND ', $whereClauses);

// --- Pagination Setup ---
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;

// Count total records
$countSql = "SELECT COUNT(*) as total FROM login_logs WHERE $whereSql";
if (!empty($params)) {
  $stmtCount = $conn->prepare($countSql);
  $stmtCount->bind_param($types, ...$params);
  $stmtCount->execute();
  $totalRecords = (int)$stmtCount->get_result()->fetch_assoc()['total'];
  $stmtCount->close();
} else {
  $resCount = $conn->query($countSql);
  $totalRecords = $resCount ? (int)$resCount->fetch_assoc()['total'] : 0;
}

$totalPages = max(1, ceil($totalRecords / $limit));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $limit;

// Fetch logs
$fetchSql = "SELECT * FROM login_logs WHERE $whereSql ORDER BY time_in DESC LIMIT ?, ?";
$stmtFetch = $conn->prepare($fetchSql);
if (!empty($params)) {
  $fetchTypes = $types . 'ii';
  $fetchParams = array_merge($params, [$offset, $limit]);
  $stmtFetch->bind_param($fetchTypes, ...$fetchParams);
} else {
  $stmtFetch->bind_param('ii', $offset, $limit);
}
$stmtFetch->execute();
$logsResult = $stmtFetch->get_result();
$logs = [];
while ($row = $logsResult->fetch_assoc()) {
  $logs[] = $row;
}
$stmtFetch->close();

// Pending count for badge if admin
$pendingRequestsTotal = 0;
if ($isAdmin) {
  $resP = $conn->query("SELECT (SELECT COUNT(*) FROM users WHERE status = 'pending') + (SELECT COUNT(*) FROM delete_requests WHERE status = 'pending') as total");
  if ($resP) $pendingRequestsTotal = (int)$resP->fetch_assoc()['total'];
}

// Available years from logs for filter dropdown
$yearsRes = $conn->query("SELECT DISTINCT YEAR(time_in) as yr FROM login_logs WHERE time_in IS NOT NULL ORDER BY yr DESC");
$availableYears = [];
if ($yearsRes) {
  while ($y = $yearsRes->fetch_assoc()) {
    if (!empty($y['yr'])) $availableYears[] = (int)$y['yr'];
  }
}
if (empty($availableYears)) $availableYears[] = (int)date('Y');

// Helper to preserve query strings in pagination
function buildQueryUrl($paramsToMerge = []) {
  $currentParams = $_GET;
  foreach ($paramsToMerge as $k => $v) {
    if ($v === null || $v === '') {
      unset($currentParams[$k]);
    } else {
      $currentParams[$k] = $v;
    }
  }
  return 'logs.php?' . http_build_query($currentParams);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login & Activity Logs | GymBros</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
  <?php if ($isAdmin): ?>
    <link rel="stylesheet" href="../css/admin.css">
  <?php endif; ?>
  <style>
    .logs-filter-card {
      background: rgba(26, 31, 59, 0.7);
      backdrop-filter: blur(15px);
      -webkit-backdrop-filter: blur(15px);
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 16px;
      padding: 20px 24px;
      margin-bottom: 25px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
    }
    .logs-filter-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 15px;
      align-items: flex-end;
    }
    .filter-item label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #94a3b8;
      margin-bottom: 6px;
    }
    .filter-item input,
    .filter-item select {
      width: 100%;
      background: rgba(15, 23, 42, 0.75);
      border: 1px solid rgba(255, 255, 255, 0.15);
      color: #fff;
      padding: 10px 14px;
      border-radius: 10px;
      font-size: 13px;
      font-family: inherit;
      outline: none;
      transition: all 0.2s ease;
    }
    .filter-item input:focus,
    .filter-item select:focus {
      border-color: var(--accent, #ff5e00);
      box-shadow: 0 0 0 3px rgba(255, 94, 0, 0.2);
    }
    .filter-btn-group {
      display: flex;
      gap: 10px;
    }
    .btn-filter-apply {
      background: linear-gradient(135deg, var(--accent, #ff5e00), #ff7b00);
      color: #fff;
      border: none;
      padding: 10px 18px;
      border-radius: 10px;
      font-weight: 600;
      font-size: 13px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s ease;
    }
    .btn-filter-apply:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(255, 94, 0, 0.35);
    }
    .btn-filter-reset {
      background: rgba(255, 255, 255, 0.08);
      color: #cbd5e1;
      border: 1px solid rgba(255, 255, 255, 0.15);
      padding: 10px 16px;
      border-radius: 10px;
      font-weight: 600;
      font-size: 13px;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s ease;
    }
    .btn-filter-reset:hover {
      background: rgba(255, 255, 255, 0.15);
      color: #fff;
    }

    /* Active Session Indicator */
    .status-online {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: #4ade80;
      font-size: 12px;
      font-weight: 600;
    }
    .status-online .dot {
      width: 8px;
      height: 8px;
      background: #4ade80;
      border-radius: 50%;
      box-shadow: 0 0 8px #4ade80;
      animation: pulseDot 2s infinite;
    }
    @keyframes pulseDot {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.5; transform: scale(1.2); }
    }

    /* Pagination Styles */
    .logs-pagination-wrapper {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 15px;
      margin-top: 25px;
      padding: 15px 20px;
      background: rgba(26, 31, 59, 0.5);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 14px;
    }
    .pagination-info {
      font-size: 13px;
      color: #94a3b8;
    }
    .pagination-info strong {
      color: #fff;
    }
    .pagination-controls {
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .page-link-btn {
      min-width: 36px;
      height: 36px;
      padding: 0 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 8px;
      color: #cbd5e1;
      font-size: 13px;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.2s ease;
    }
    .page-link-btn:hover:not(.disabled) {
      background: var(--accent, #ff5e00);
      border-color: var(--accent, #ff5e00);
      color: #fff;
    }
    .page-link-btn.active {
      background: linear-gradient(135deg, var(--accent, #ff5e00), #ff7b00);
      border-color: var(--accent, #ff5e00);
      color: #fff;
      box-shadow: 0 4px 12px rgba(255, 94, 0, 0.3);
    }
    .page-link-btn.disabled {
      opacity: 0.35;
      cursor: not-allowed;
      pointer-events: none;
    }

    /* Regular User Layout Enhancement */
    .user-logs-container {
      max-width: 1200px;
      margin: 30px auto;
      padding: 0 20px;
    }
    .user-logs-banner {
      background: linear-gradient(135deg, var(--primary, #1a1f3b), #2a325f);
      border-radius: 16px;
      padding: 2.2rem;
      margin-bottom: 25px;
      border: 1px solid var(--card-border, rgba(255, 255, 255, 0.1));
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 20px;
    }
    .user-logs-banner h2 {
      font-family: 'Oswald', sans-serif;
      font-size: 2rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #fff;
      margin-bottom: 6px;
    }
    .user-logs-banner p {
      color: #cbd5e1;
      font-size: 14px;
    }
  </style>
</head>

<body>
  <!-- Loading Animation -->
  <div class="page-loader">
    <div class="loader">
      <div class="dumbbell">
        <div class="bar"></div>
        <div class="weight left"></div>
        <div class="weight right"></div>
      </div>
      <p>Loading System Logs...</p>
    </div>
  </div>

  <?php if ($isAdmin): ?>
    <!-- ADMIN / SUPER ADMIN SIDEBAR LAYOUT -->
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
            <li>
              <a href="logs.php" class="active">
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
            <h3><i class="fas fa-history"></i> Login & Activity Logs</h3>
          </div>
          <div class="topbar-right">
            <div class="topbar-user-chip">
              <i class="fas fa-user-shield"></i>
              <span>@<?php echo htmlspecialchars($user['username']); ?></span>
            </div>
            <a href="logout.php" class="btn-topbar-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
          </div>
        </header>

        <div class="admin-page-content">
          <!-- Banner -->
          <div class="admin-header">
            <div class="admin-header-title">
              <h2><i class="fas fa-history"></i> System Access & Login Logs</h2>
              <p>
                <?php if ($isSuperAdmin): ?>
                  Full system audit log: Viewing all Super Administrator, Administrator, and Member login records.
                <?php else: ?>
                  Administrator access log: Viewing Administrator and Member login records.
                <?php endif; ?>
              </p>
            </div>
            <div class="admin-stat-pills">
              <div class="stat-pill pill-users">
                <i class="fas fa-clipboard-list"></i>
                <div class="stat-pill-info">
                  <div class="num"><?php echo number_format($totalRecords); ?></div>
                  <div class="lbl">Total Log Entries</div>
                </div>
              </div>
            </div>
          </div>

          <!-- FILTER BAR -->
          <form method="GET" action="logs.php" class="logs-filter-card">
            <div class="logs-filter-grid">
              <!-- Search Keyword -->
              <div class="filter-item">
                <label><i class="fas fa-search"></i> Search ID / Name / Username</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="e.g. EMP-101 or John...">
              </div>

              <!-- Month Filter -->
              <div class="filter-item">
                <label><i class="fas fa-calendar-alt"></i> Filter by Month</label>
                <select name="month">
                  <option value="">All Months</option>
                  <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $filterMonth === $m ? 'selected' : ''; ?>>
                      <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                    </option>
                  <?php endfor; ?>
                </select>
              </div>

              <!-- Specific Date Filter -->
              <div class="filter-item">
                <label><i class="fas fa-calendar-day"></i> Filter by Specific Date</label>
                <input type="date" name="date" value="<?php echo htmlspecialchars($filterDate); ?>">
              </div>

              <!-- Year Filter -->
              <div class="filter-item">
                <label><i class="fas fa-calendar"></i> Year</label>
                <select name="year">
                  <option value="">All Years</option>
                  <?php foreach ($availableYears as $yr): ?>
                    <option value="<?php echo $yr; ?>" <?php echo $filterYear === $yr ? 'selected' : ''; ?>>
                      <?php echo $yr; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <?php if ($isSuperAdmin || $isAdmin): ?>
                <!-- Role Filter -->
                <div class="filter-item">
                  <label><i class="fas fa-user-tag"></i> Role</label>
                  <select name="role">
                    <option value="">All Roles</option>
                    <?php if ($isSuperAdmin): ?>
                      <option value="superadmin" <?php echo $filterRole === 'superadmin' ? 'selected' : ''; ?>>Super Admin</option>
                    <?php endif; ?>
                    <option value="admin" <?php echo $filterRole === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                    <option value="user" <?php echo $filterRole === 'user' ? 'selected' : ''; ?>>User</option>
                  </select>
                </div>
              <?php endif; ?>

              <!-- Filter Buttons -->
              <div class="filter-btn-group">
                <button type="submit" class="btn-filter-apply"><i class="fas fa-filter"></i> Apply Filter</button>
                <a href="logs.php" class="btn-filter-reset" title="Reset Filters"><i class="fas fa-undo"></i> Reset</a>
              </div>
            </div>
          </form>

          <!-- LOGS TABLE -->
          <div class="table-responsive">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Log ID</th>
                  <th>ID Number</th>
                  <th>Full Name</th>
                  <th>Username</th>
                  <th>Role</th>
                  <th>Time In</th>
                  <th>Time Out</th>
                  <th>Duration / Status</th>
                  <th>IP Address</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($logs)): ?>
                  <tr>
                    <td colspan="9" style="text-align: center; padding: 40px; color: #94a3b8;">
                      <i class="fas fa-folder-open" style="font-size: 36px; color: #64748b; margin-bottom: 12px; display: block;"></i>
                      No login records match the specified filters.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($logs as $log): ?>
                    <?php
                      $timeIn = !empty($log['time_in']) ? strtotime($log['time_in']) : null;
                      $timeOut = !empty($log['time_out']) ? strtotime($log['time_out']) : null;
                      $isActive = empty($timeOut);
                      
                      $durationText = 'Active Session';
                      if (!$isActive && $timeIn && $timeOut) {
                        $diffSecs = $timeOut - $timeIn;
                        if ($diffSecs < 60) {
                          $durationText = $diffSecs . 's';
                        } elseif ($diffSecs < 3600) {
                          $durationText = floor($diffSecs / 60) . 'm ' . ($diffSecs % 60) . 's';
                        } else {
                          $hours = floor($diffSecs / 3600);
                          $mins = floor(($diffSecs % 3600) / 60);
                          $durationText = $hours . 'h ' . $mins . 'm';
                        }
                      }
                    ?>
                    <tr>
                      <td><span style="color: #94a3b8; font-size: 13px;">#<?php echo $log['id']; ?></span></td>
                      <td><strong><?php echo htmlspecialchars($log['id_number']); ?></strong></td>
                      <td>
                        <div style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($log['full_name']); ?></div>
                      </td>
                      <td><span style="color: var(--accent); font-weight: 600;">@<?php echo htmlspecialchars($log['username']); ?></span></td>
                      <td>
                        <?php if ($log['role'] === 'superadmin'): ?>
                          <span class="badge badge-superadmin"><i class="fas fa-crown"></i> Super Admin</span>
                        <?php elseif ($log['role'] === 'admin'): ?>
                          <span class="badge badge-admin"><i class="fas fa-user-shield"></i> Admin</span>
                        <?php else: ?>
                          <span class="badge badge-user"><i class="fas fa-user"></i> User</span>
                        <?php endif; ?>
                      </td>
                      <td style="font-size: 13px; color: #4ade80;">
                        <i class="fas fa-sign-in-alt"></i> <?php echo date('M d, Y h:i:s A', $timeIn); ?>
                      </td>
                      <td style="font-size: 13px;">
                        <?php if ($timeOut): ?>
                          <span style="color: #f87171;"><i class="fas fa-sign-out-alt"></i> <?php echo date('M d, Y h:i:s A', $timeOut); ?></span>
                        <?php else: ?>
                          <span class="status-online"><span class="dot"></span> Online</span>
                        <?php endif; ?>
                      </td>
                      <td style="font-size: 13px; color: #cbd5e1;">
                        <?php if ($isActive): ?>
                          <span class="status-online"><span class="dot"></span> Active</span>
                        <?php else: ?>
                          <span style="color: #94a3b8;"><i class="fas fa-hourglass-end"></i> <?php echo $durationText; ?></span>
                        <?php endif; ?>
                      </td>
                      <td style="font-size: 12px; color: #94a3b8;">
                        <code><?php echo htmlspecialchars($log['ip_address'] ?? '127.0.0.1'); ?></code>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- PAGINATION -->
          <?php if ($totalPages > 1): ?>
            <div class="logs-pagination-wrapper">
              <div class="pagination-info">
                Showing <strong><?php echo $totalRecords > 0 ? $offset + 1 : 0; ?></strong> to <strong><?php echo min($offset + $limit, $totalRecords); ?></strong> of <strong><?php echo number_format($totalRecords); ?></strong> logs
              </div>
              <div class="pagination-controls">
                <!-- Previous Button -->
                <a href="<?php echo buildQueryUrl(['page' => $page - 1]); ?>" class="page-link-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" title="Previous Page">
                  <i class="fas fa-chevron-left"></i>
                </a>

                <!-- Numbered Pages -->
                <?php
                  $startPage = max(1, $page - 2);
                  $endPage = min($totalPages, $page + 2);
                  if ($startPage > 1) {
                    echo '<a href="' . buildQueryUrl(['page' => 1]) . '" class="page-link-btn">1</a>';
                    if ($startPage > 2) echo '<span style="color: #64748b; padding: 0 4px;">...</span>';
                  }
                  for ($p = $startPage; $p <= $endPage; $p++):
                ?>
                  <a href="<?php echo buildQueryUrl(['page' => $p]); ?>" class="page-link-btn <?php echo $page === $p ? 'active' : ''; ?>">
                    <?php echo $p; ?>
                  </a>
                <?php
                  endfor;
                  if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) echo '<span style="color: #64748b; padding: 0 4px;">...</span>';
                    echo '<a href="' . buildQueryUrl(['page' => $totalPages]) . '" class="page-link-btn">' . $totalPages . '</a>';
                  }
                ?>

                <!-- Next Button -->
                <a href="<?php echo buildQueryUrl(['page' => $page + 1]); ?>" class="page-link-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" title="Next Page">
                  <i class="fas fa-chevron-right"></i>
                </a>
              </div>
            </div>
          <?php endif; ?>

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

  <?php else: ?>

    <!-- REGULAR USER VIEW -->
    <header>
      <div class="logo">
        <h1>Gym<span>Bros</span></h1>
      </div>
      <div class="navBar">
        <ul>
          <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
          <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <li><a href="logs.php" class="active"><i class="fas fa-history"></i> Logs</a></li>
          <li><a href="change-password.php"><i class="fas fa-key"></i> Change Password</a></li>
          <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
      </div>
    </header>

    <main>
      <div class="user-logs-container" style="max-width: 1200px; margin: 30px auto; padding: 0 20px;">
        <!-- Banner -->
        <div style="background: linear-gradient(135deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.9)); border: 1px solid rgba(255, 94, 0, 0.25); border-radius: 16px; padding: 24px 30px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
          <div>
            <h2 style="margin: 0; font-size: 24px; color: #fff; font-family: 'Oswald', sans-serif; letter-spacing: 0.5px;">
              <i class="fas fa-history" style="color: #ff5e00; margin-right: 8px;"></i> User Activity Logs
            </h2>
            <p style="margin: 6px 0 0 0; color: #94a3b8; font-size: 13px;">
              Session and access records for registered gym members.
            </p>
          </div>
          <div style="text-align: right;">
            <div style="font-size: 32px; font-weight: 700; font-family: 'Oswald', sans-serif; color: #ff7b00;"><?php echo number_format($totalRecords); ?></div>
            <div style="font-size: 11px; text-transform: uppercase; color: #94a3b8; letter-spacing: 1px;">Total Records</div>
          </div>
        </div>

        <!-- FILTER BAR -->
        <form method="GET" action="logs.php" class="logs-filter-card">
          <div class="logs-filter-grid">
            <!-- Search Keyword -->
            <div class="filter-item">
              <label><i class="fas fa-search"></i> Search ID / Name / Username</label>
              <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="e.g. John or EMP-101...">
            </div>

            <!-- Month Filter -->
            <div class="filter-item">
              <label><i class="fas fa-calendar-alt"></i> Filter by Month</label>
              <select name="month">
                <option value="">All Months</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                  <option value="<?php echo $m; ?>" <?php echo $filterMonth === $m ? 'selected' : ''; ?>>
                    <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                  </option>
                <?php endfor; ?>
              </select>
            </div>

            <!-- Specific Date Filter -->
            <div class="filter-item">
              <label><i class="fas fa-calendar-day"></i> Filter by Date</label>
              <input type="date" name="date" value="<?php echo htmlspecialchars($filterDate); ?>">
            </div>

            <!-- Year Filter -->
            <div class="filter-item">
              <label><i class="fas fa-calendar"></i> Year</label>
              <select name="year">
                <option value="">All Years</option>
                <?php foreach ($availableYears as $yr): ?>
                  <option value="<?php echo $yr; ?>" <?php echo $filterYear === $yr ? 'selected' : ''; ?>>
                    <?php echo $yr; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Filter Buttons -->
            <div class="filter-btn-group" style="display: flex; gap: 10px;">
              <button type="submit" class="btn" style="padding: 10px 18px; font-size: 13px; margin: 0; width: auto;"><i class="fas fa-filter"></i> Filter</button>
              <a href="logs.php" class="btn" style="padding: 10px 18px; font-size: 13px; margin: 0; width: auto; background: rgba(255,255,255,0.1); color: #cbd5e1; text-align: center; text-decoration: none;"><i class="fas fa-undo"></i> Reset</a>
            </div>
          </div>
        </form>

        <!-- LOGS TABLE -->
        <div class="table-responsive" style="background: rgba(15, 23, 42, 0.85); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; overflow: hidden; margin-bottom: 25px;">
          <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
            <thead>
              <tr style="background: rgba(30, 41, 59, 0.9); border-bottom: 2px solid rgba(255, 94, 0, 0.3); color: #f8fafc;">
                <th style="padding: 14px 16px;">ID Number</th>
                <th style="padding: 14px 16px;">Full Name</th>
                <th style="padding: 14px 16px;">Username</th>
                <th style="padding: 14px 16px;">Time In</th>
                <th style="padding: 14px 16px;">Time Out</th>
                <th style="padding: 14px 16px;">Session Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($logs)): ?>
                <tr>
                  <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">
                    <i class="fas fa-folder-open" style="font-size: 36px; color: #64748b; margin-bottom: 12px; display: block;"></i>
                    No activity logs found matching the filter criteria.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($logs as $log): ?>
                  <?php
                    $timeIn = !empty($log['time_in']) ? strtotime($log['time_in']) : null;
                    $timeOut = !empty($log['time_out']) ? strtotime($log['time_out']) : null;
                    $isActive = empty($timeOut);
                  ?>
                  <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.06); transition: background 0.2s;">
                    <td style="padding: 14px 16px;"><strong style="color: #cbd5e1;"><?php echo htmlspecialchars($log['id_number']); ?></strong></td>
                    <td style="padding: 14px 16px;">
                      <div style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($log['full_name']); ?></div>
                    </td>
                    <td style="padding: 14px 16px;"><span style="color: #ff7b00; font-weight: 600;">@<?php echo htmlspecialchars($log['username']); ?></span></td>
                    <td style="padding: 14px 16px; font-size: 13px; color: #4ade80;">
                      <i class="fas fa-sign-in-alt"></i> <?php echo date('M d, Y h:i:s A', $timeIn); ?>
                    </td>
                    <td style="padding: 14px 16px; font-size: 13px;">
                      <?php if ($timeOut): ?>
                        <span style="color: #f87171;"><i class="fas fa-sign-out-alt"></i> <?php echo date('M d, Y h:i:s A', $timeOut); ?></span>
                      <?php else: ?>
                        <span style="color: #4ade80; font-weight: 600;"><i class="fas fa-circle" style="font-size: 8px;"></i> Online</span>
                      <?php endif; ?>
                    </td>
                    <td style="padding: 14px 16px; font-size: 13px;">
                      <?php if ($isActive): ?>
                        <span style="background: rgba(74, 222, 128, 0.15); color: #4ade80; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600;">Active Session</span>
                      <?php else: ?>
                        <span style="background: rgba(148, 163, 184, 0.15); color: #94a3b8; padding: 4px 10px; border-radius: 6px; font-size: 12px;"><i class="fas fa-check"></i> Completed</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; flex-wrap: wrap; gap: 15px;">
            <div style="color: #94a3b8; font-size: 13px;">
              Showing <strong><?php echo $totalRecords > 0 ? $offset + 1 : 0; ?></strong> to <strong><?php echo min($offset + $limit, $totalRecords); ?></strong> of <strong><?php echo number_format($totalRecords); ?></strong> logs
            </div>
            <div style="display: flex; gap: 6px;">
              <!-- Previous Button -->
              <a href="<?php echo buildQueryUrl(['page' => $page - 1]); ?>" class="page-link-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" style="padding: 8px 12px; border-radius: 8px; background: rgba(255,255,255,0.08); color: #fff; text-decoration: none; font-size: 13px;" title="Previous Page">
                <i class="fas fa-chevron-left"></i>
              </a>

              <!-- Numbered Pages -->
              <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                if ($startPage > 1) {
                  echo '<a href="' . buildQueryUrl(['page' => 1]) . '" style="padding: 8px 12px; border-radius: 8px; background: rgba(255,255,255,0.08); color: #fff; text-decoration: none; font-size: 13px;">1</a>';
                  if ($startPage > 2) echo '<span style="color: #64748b; padding: 8px 4px;">...</span>';
                }
                for ($p = $startPage; $p <= $endPage; $p++):
              ?>
                <a href="<?php echo buildQueryUrl(['page' => $p]); ?>" style="padding: 8px 12px; border-radius: 8px; background: <?php echo $page === $p ? '#ff5e00' : 'rgba(255,255,255,0.08)'; ?>; color: #fff; text-decoration: none; font-size: 13px; font-weight: <?php echo $page === $p ? '700' : 'normal'; ?>;">
                  <?php echo $p; ?>
                </a>
              <?php
                endfor;
                if ($endPage < $totalPages) {
                  if ($endPage < $totalPages - 1) echo '<span style="color: #64748b; padding: 8px 4px;">...</span>';
                  echo '<a href="' . buildQueryUrl(['page' => $totalPages]) . '" style="padding: 8px 12px; border-radius: 8px; background: rgba(255,255,255,0.08); color: #fff; text-decoration: none; font-size: 13px;">' . $totalPages . '</a>';
                }
              ?>

              <!-- Next Button -->
              <a href="<?php echo buildQueryUrl(['page' => $page + 1]); ?>" class="page-link-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" style="padding: 8px 12px; border-radius: 8px; background: rgba(255,255,255,0.08); color: #fff; text-decoration: none; font-size: 13px;" title="Next Page">
                <i class="fas fa-chevron-right"></i>
              </a>
            </div>
          </div>
        <?php endif; ?>

      </div>
    </main>

    <footer>
      &copy; <?php echo date('Y'); ?> GymBros. All rights reserved.
    </footer>

  <?php endif; ?>

  <script src="../js/loader.js"></script>
  <?php if ($isAdmin): ?>
    <script src="../js/admin.js"></script>
  <?php endif; ?>
</body>

</html>
