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
$currentUserRole = $user['role'] ?? 'user';

// Access Control: Only Super Admin and Admin have access to system logs.
// Regular users are forbidden and redirected to their dashboard.
if (!$isAdmin) {
  header("Location: dashboard.php");
  exit();
}

$db = new Database();
$conn = $db->getConnection();

// --- Determine Active Tab ---
$activeTab = isset($_GET['tab']) && $_GET['tab'] === 'login' ? 'login' : 'activity';

// --- Role-Based Visibility Rules ---
// Super Admin: Full visibility across all roles (superadmin, admin, user)
// Administrator: ONLY activity and logs of its own role (admin) and normal users (user). SUPERADMIN IS STRICTLY EXCLUDED.
if ($isSuperAdmin) {
  $allowedRoles = ["'superadmin'", "'admin'", "'user'"];
} else {
  $allowedRoles = ["'admin'", "'user'"];
}

$roleCondition = "role IN (" . implode(',', $allowedRoles) . ")";

// --- Filter Parameters ---
$filterMonth    = isset($_GET['month']) && $_GET['month'] !== '' ? (int)$_GET['month'] : '';
$filterDate     = isset($_GET['date']) && !empty($_GET['date']) ? $db->sanitize($_GET['date']) : '';
$filterYear     = isset($_GET['year']) && $_GET['year'] !== '' ? (int)$_GET['year'] : '';
$filterRole     = isset($_GET['role']) && !empty($_GET['role']) ? $db->sanitize($_GET['role']) : '';
$filterCategory = isset($_GET['category']) && !empty($_GET['category']) ? $db->sanitize($_GET['category']) : '';
$searchQuery    = isset($_GET['search']) && !empty($_GET['search']) ? $db->sanitize($_GET['search']) : '';

// Sanitize requested role according to current user's privileges
if (!empty($filterRole)) {
  if (!$isSuperAdmin && $filterRole === 'superadmin') {
    // Regular admin is forbidden from filtering by or viewing superadmin logs
    $filterRole = '';
  } elseif (!$isAdmin && $filterRole !== 'user') {
    $filterRole = 'user';
  }
}

// --- Pagination Setup ---
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;

// --- Total Counts for Stats Header ---
$statActRes = $conn->query("SELECT COUNT(*) as total FROM activity_logs WHERE $roleCondition");
$totalActivityStats = $statActRes ? (int)$statActRes->fetch_assoc()['total'] : 0;

$statLogRes = $conn->query("SELECT COUNT(*) as total FROM login_logs WHERE $roleCondition");
$totalLoginStats = $statLogRes ? (int)$statLogRes->fetch_assoc()['total'] : 0;

$todayStr = date('Y-m-d');
$statTodayRes = $conn->query("SELECT COUNT(*) as total FROM activity_logs WHERE $roleCondition AND DATE(created_at) = '$todayStr'");
$todayActivityCount = $statTodayRes ? (int)$statTodayRes->fetch_assoc()['total'] : 0;

// Available Categories for Activity Logs Filter
$categoriesList = ['Authentication', 'User Management', 'Fitness Tracking', 'Class Booking', 'Security', 'Profile', 'Account'];

// --- Query Construction by Active Tab ---
if ($activeTab === 'activity') {
  // Activity Logs Table
  $whereClauses = [$roleCondition];
  $types = '';
  $params = [];

  if ($filterMonth >= 1 && $filterMonth <= 12) {
    $whereClauses[] = "MONTH(created_at) = ?";
    $types .= 'i';
    $params[] = $filterMonth;
  }

  if (!empty($filterDate)) {
    $whereClauses[] = "DATE(created_at) = ?";
    $types .= 's';
    $params[] = $filterDate;
  }

  if ($filterYear > 2000) {
    $whereClauses[] = "YEAR(created_at) = ?";
    $types .= 'i';
    $params[] = $filterYear;
  }

  if (!empty($filterRole)) {
    $whereClauses[] = "role = ?";
    $types .= 's';
    $params[] = $filterRole;
  }

  if (!empty($filterCategory)) {
    $whereClauses[] = "action_category = ?";
    $types .= 's';
    $params[] = $filterCategory;
  }

  if (!empty($searchQuery)) {
    $whereClauses[] = "(id_number LIKE ? OR username LIKE ? OR full_name LIKE ? OR action LIKE ? OR details LIKE ?)";
    $types .= 'sssss';
    $searchLike = '%' . $searchQuery . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
  }

  $whereSql = implode(' AND ', $whereClauses);

  // Count total matching activity records
  $countSql = "SELECT COUNT(*) as total FROM activity_logs WHERE $whereSql";
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

  // Fetch paginated activity logs
  $fetchSql = "SELECT * FROM activity_logs WHERE $whereSql ORDER BY created_at DESC LIMIT ?, ?";
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
  $activityLogs = [];
  while ($row = $logsResult->fetch_assoc()) {
    $activityLogs[] = $row;
  }
  $stmtFetch->close();

  // Available years from activity_logs
  $yearsRes = $conn->query("SELECT DISTINCT YEAR(created_at) as yr FROM activity_logs WHERE created_at IS NOT NULL ORDER BY yr DESC");

} else {
  // Login Logs Table
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
    $whereClauses[] = "role = ?";
    $types .= 's';
    $params[] = $filterRole;
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

  // Count total matching login records
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

  // Fetch paginated login logs
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
  $loginLogs = [];
  while ($row = $logsResult->fetch_assoc()) {
    $loginLogs[] = $row;
  }
  $stmtFetch->close();

  // Available years from login_logs
  $yearsRes = $conn->query("SELECT DISTINCT YEAR(time_in) as yr FROM login_logs WHERE time_in IS NOT NULL ORDER BY yr DESC");
}

$availableYears = [];
if ($yearsRes) {
  while ($y = $yearsRes->fetch_assoc()) {
    if (!empty($y['yr'])) $availableYears[] = (int)$y['yr'];
  }
}
if (empty($availableYears)) $availableYears[] = (int)date('Y');

// Pending requests badge count for admin
$pendingRequestsTotal = 0;
if ($isAdmin) {
  $resP = $conn->query("SELECT (SELECT COUNT(*) FROM users WHERE status = 'pending') + (SELECT COUNT(*) FROM delete_requests WHERE status = 'pending') as total");
  if ($resP) $pendingRequestsTotal = (int)$resP->fetch_assoc()['total'];
}

// Helper to preserve query strings in links
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

// Category Badge Helper
function getCategoryBadge($cat) {
  switch ($cat) {
    case 'Authentication':
      return ['class' => 'badge-cat-auth', 'icon' => 'fas fa-sign-in-alt', 'color' => '#38bdf8'];
    case 'User Management':
      return ['class' => 'badge-cat-mgmt', 'icon' => 'fas fa-users-cog', 'color' => '#fb923c'];
    case 'Fitness Tracking':
      return ['class' => 'badge-cat-fitness', 'icon' => 'fas fa-dumbbell', 'color' => '#4ade80'];
    case 'Class Booking':
      return ['class' => 'badge-cat-booking', 'icon' => 'fas fa-calendar-check', 'color' => '#a78bfa'];
    case 'Security':
      return ['class' => 'badge-cat-security', 'icon' => 'fas fa-shield-alt', 'color' => '#f87171'];
    case 'Profile':
      return ['class' => 'badge-cat-profile', 'icon' => 'fas fa-id-card', 'color' => '#34d399'];
    case 'Account':
      return ['class' => 'badge-cat-account', 'icon' => 'fas fa-user-plus', 'color' => '#facc15'];
    default:
      return ['class' => 'badge-cat-general', 'icon' => 'fas fa-stream', 'color' => '#94a3b8'];
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Activity & System Logs | GymBros</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
  <?php if ($isAdmin): ?>
    <link rel="stylesheet" href="../css/admin.css">
  <?php endif; ?>
  <style>
    /* Tabs Navigation */
    .logs-nav-tabs {
      display: flex;
      gap: 12px;
      margin-bottom: 24px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.12);
      padding-bottom: 12px;
      flex-wrap: wrap;
    }
    .logs-tab-btn {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 12px 22px;
      border-radius: 12px;
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
      color: #94a3b8;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.08);
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .logs-tab-btn:hover {
      color: #fff;
      background: rgba(255, 255, 255, 0.1);
      transform: translateY(-1px);
    }
    .logs-tab-btn.active {
      color: #fff;
      background: linear-gradient(135deg, rgba(255, 94, 0, 0.25), rgba(255, 123, 0, 0.15));
      border-color: rgba(255, 94, 0, 0.6);
      box-shadow: 0 4px 16px rgba(255, 94, 0, 0.2);
    }
    .logs-tab-btn .tab-count {
      padding: 2px 8px;
      border-radius: 20px;
      font-size: 11px;
      background: rgba(255, 255, 255, 0.12);
      color: #cbd5e1;
    }
    .logs-tab-btn.active .tab-count {
      background: #ff5e00;
      color: #fff;
    }

    /* Filter Card */
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
      grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
      gap: 14px;
      align-items: flex-end;
    }
    .filter-item label {
      display: block;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      color: #94a3b8;
      margin-bottom: 6px;
    }
    .filter-item input,
    .filter-item select {
      width: 100%;
      background: rgba(15, 23, 42, 0.85);
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
      display: inline-flex;
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
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s ease;
    }
    .btn-filter-reset:hover {
      background: rgba(255, 255, 255, 0.15);
      color: #fff;
    }

    /* Category Badges */
    .cat-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 10px;
      border-radius: 8px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.3px;
      text-transform: uppercase;
    }
    .badge-cat-auth { background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); }
    .badge-cat-mgmt { background: rgba(251, 146, 60, 0.15); color: #fb923c; border: 1px solid rgba(251, 146, 60, 0.3); }
    .badge-cat-fitness { background: rgba(74, 222, 128, 0.15); color: #4ade80; border: 1px solid rgba(74, 222, 128, 0.3); }
    .badge-cat-booking { background: rgba(167, 139, 250, 0.15); color: #a78bfa; border: 1px solid rgba(167, 139, 250, 0.3); }
    .badge-cat-security { background: rgba(248, 113, 113, 0.15); color: #f87171; border: 1px solid rgba(248, 113, 113, 0.3); }
    .badge-cat-profile { background: rgba(52, 211, 153, 0.15); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.3); }
    .badge-cat-account { background: rgba(250, 204, 21, 0.15); color: #facc15; border: 1px solid rgba(250, 204, 21, 0.3); }
    .badge-cat-general { background: rgba(148, 163, 184, 0.15); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.3); }

    .action-code-tag {
      display: inline-block;
      font-family: 'Courier New', Courier, monospace;
      font-size: 11px;
      font-weight: 700;
      color: #cbd5e1;
      background: rgba(15, 23, 42, 0.8);
      padding: 3px 8px;
      border-radius: 6px;
      border: 1px solid rgba(255, 255, 255, 0.1);
    }

    /* Role Pill Badges */
    .role-badge-superadmin {
      background: linear-gradient(135deg, rgba(234, 179, 8, 0.2), rgba(245, 158, 11, 0.1));
      color: #fbbf24;
      border: 1px solid rgba(234, 179, 8, 0.4);
      padding: 3px 8px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .role-badge-admin {
      background: rgba(255, 94, 0, 0.15);
      color: #ff7b00;
      border: 1px solid rgba(255, 94, 0, 0.35);
      padding: 3px 8px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .role-badge-user {
      background: rgba(148, 163, 184, 0.15);
      color: #cbd5e1;
      border: 1px solid rgba(148, 163, 184, 0.25);
      padding: 3px 8px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 4px;
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
      <p>Loading Activity Logs...</p>
    </div>
  </div>

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
            <h3><i class="fas fa-history"></i> Account Activity & System Logs</h3>
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
          <!-- Header Banner -->
          <div class="admin-header">
            <div class="admin-header-title">
              <h2><i class="fas fa-clipboard-list"></i> System & Account Audit Logs</h2>
              <p>
                <?php if ($isSuperAdmin): ?>
                  Full system audit stream: Viewing activities across Super Administrator, Administrator, and Member accounts.
                <?php else: ?>
                  Administrator audit stream: Viewing Administrator and Member account activities.
                <?php endif; ?>
              </p>
            </div>
            <div class="admin-stat-pills">
              <div class="stat-pill pill-users">
                <i class="fas fa-history"></i>
                <div class="stat-pill-info">
                  <div class="num"><?php echo number_format($totalActivityStats); ?></div>
                  <div class="lbl">Total Activity Logs</div>
                </div>
              </div>
              <div class="stat-pill pill-admins">
                <i class="fas fa-calendar-day"></i>
                <div class="stat-pill-info">
                  <div class="num"><?php echo number_format($todayActivityCount); ?></div>
                  <div class="lbl">Today's Activities</div>
                </div>
              </div>
              <div class="stat-pill pill-pending">
                <i class="fas fa-sign-in-alt"></i>
                <div class="stat-pill-info">
                  <div class="num"><?php echo number_format($totalLoginStats); ?></div>
                  <div class="lbl">Total Logins</div>
                </div>
              </div>
            </div>
          </div>

          <!-- TABS -->
          <div class="logs-nav-tabs">
            <a href="<?php echo buildQueryUrl(['tab' => 'activity', 'page' => 1]); ?>" class="logs-tab-btn <?php echo $activeTab === 'activity' ? 'active' : ''; ?>">
              <i class="fas fa-running"></i>
              <span>Account Activity Logs</span>
              <span class="tab-count"><?php echo number_format($totalActivityStats); ?></span>
            </a>
            <a href="<?php echo buildQueryUrl(['tab' => 'login', 'page' => 1]); ?>" class="logs-tab-btn <?php echo $activeTab === 'login' ? 'active' : ''; ?>">
              <i class="fas fa-sign-in-alt"></i>
              <span>Login & Session Logs</span>
              <span class="tab-count"><?php echo number_format($totalLoginStats); ?></span>
            </a>
          </div>

          <!-- FILTER BAR -->
          <form method="GET" action="logs.php" class="logs-filter-card">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
            <div class="logs-filter-grid">
              <!-- Search Keyword -->
              <div class="filter-item">
                <label><i class="fas fa-search"></i> Search ID / Name / Action</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="e.g. EMP-101 or workout...">
              </div>

              <?php if ($activeTab === 'activity'): ?>
                <!-- Category Filter -->
                <div class="filter-item">
                  <label><i class="fas fa-tags"></i> Category</label>
                  <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categoriesList as $cat): ?>
                      <option value="<?php echo $cat; ?>" <?php echo $filterCategory === $cat ? 'selected' : ''; ?>>
                        <?php echo $cat; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>

              <!-- Role Filter (Role-Based Isolation) -->
              <div class="filter-item">
                <label><i class="fas fa-user-tag"></i> Role</label>
                <select name="role">
                  <option value="">
                    <?php if ($isSuperAdmin): ?>All Roles<?php else: ?>All (Admin & User)<?php endif; ?>
                  </option>
                  <?php if ($isSuperAdmin): ?>
                    <option value="superadmin" <?php echo $filterRole === 'superadmin' ? 'selected' : ''; ?>>Super Admin</option>
                  <?php endif; ?>
                  <option value="admin" <?php echo $filterRole === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                  <option value="user" <?php echo $filterRole === 'user' ? 'selected' : ''; ?>>User / Member</option>
                </select>
              </div>

              <!-- Month Filter -->
              <div class="filter-item">
                <label><i class="fas fa-calendar-alt"></i> Month</label>
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
                <label><i class="fas fa-calendar-day"></i> Specific Date</label>
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
              <div class="filter-btn-group">
                <button type="submit" class="btn-filter-apply"><i class="fas fa-filter"></i> Apply</button>
                <a href="logs.php?tab=<?php echo htmlspecialchars($activeTab); ?>" class="btn-filter-reset" title="Reset Filters"><i class="fas fa-undo"></i> Reset</a>
              </div>
            </div>
          </form>

          <?php if ($activeTab === 'activity'): ?>
            <!-- ================= ACTIVITY LOGS TABLE ================= -->
            <div class="table-responsive">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th style="width: 70px;">Log ID</th>
                    <th style="width: 170px;">Account / Actor</th>
                    <th style="width: 110px;">Role</th>
                    <th style="width: 150px;">Category</th>
                    <th style="width: 140px;">Action Tag</th>
                    <th>Activity Details & Changes</th>
                    <th style="width: 110px;">IP Address</th>
                    <th style="width: 160px;">Timestamp</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($activityLogs)): ?>
                    <tr>
                      <td colspan="8" style="text-align: center; padding: 45px; color: #94a3b8;">
                        <i class="fas fa-folder-open" style="font-size: 38px; color: #64748b; margin-bottom: 12px; display: block;"></i>
                        No activity records found matching the specified filters.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($activityLogs as $log): ?>
                      <?php
                        $catInfo = getCategoryBadge($log['action_category'] ?? 'General');
                        $logTime = !empty($log['created_at']) ? strtotime($log['created_at']) : null;
                      ?>
                      <tr>
                        <td><span style="color: #94a3b8; font-size: 12px; font-weight: 600;">#<?php echo $log['id']; ?></span></td>
                        <td>
                          <div style="font-weight: 600; color: #fff; font-size: 13px;"><?php echo htmlspecialchars($log['full_name']); ?></div>
                          <div style="font-size: 12px; color: var(--accent); display: flex; gap: 6px; align-items: center;">
                            <span>@<?php echo htmlspecialchars($log['username']); ?></span>
                            <span style="color: #64748b;">•</span>
                            <span style="color: #94a3b8;"><?php echo htmlspecialchars($log['id_number']); ?></span>
                          </div>
                        </td>
                        <td>
                          <?php if ($log['role'] === 'superadmin'): ?>
                            <span class="role-badge-superadmin"><i class="fas fa-crown"></i> Super Admin</span>
                          <?php elseif ($log['role'] === 'admin'): ?>
                            <span class="role-badge-admin"><i class="fas fa-shield-alt"></i> Admin</span>
                          <?php else: ?>
                            <span class="role-badge-user"><i class="fas fa-user"></i> User</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <span class="cat-pill <?php echo $catInfo['class']; ?>">
                            <i class="<?php echo $catInfo['icon']; ?>"></i> <?php echo htmlspecialchars($log['action_category']); ?>
                          </span>
                        </td>
                        <td>
                          <span class="action-code-tag"><?php echo htmlspecialchars($log['action']); ?></span>
                        </td>
                        <td>
                          <div style="color: #e2e8f0; font-size: 13px; line-height: 1.4;">
                            <?php echo htmlspecialchars($log['details']); ?>
                          </div>
                        </td>
                        <td style="font-size: 12px; color: #94a3b8;">
                          <code><?php echo htmlspecialchars($log['ip_address'] ?? '127.0.0.1'); ?></code>
                        </td>
                        <td style="font-size: 12px; color: #cbd5e1; white-space: nowrap;">
                          <i class="far fa-clock" style="color: #38bdf8; margin-right: 4px;"></i>
                          <?php echo $logTime ? date('M d, Y h:i A', $logTime) : 'N/A'; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

          <?php else: ?>
            <!-- ================= LOGIN & SESSION LOGS TABLE ================= -->
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
                  <?php if (empty($loginLogs)): ?>
                    <tr>
                      <td colspan="9" style="text-align: center; padding: 45px; color: #94a3b8;">
                        <i class="fas fa-folder-open" style="font-size: 38px; color: #64748b; margin-bottom: 12px; display: block;"></i>
                        No login records match the specified filters.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($loginLogs as $log): ?>
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
                            <span class="role-badge-superadmin"><i class="fas fa-crown"></i> Super Admin</span>
                          <?php elseif ($log['role'] === 'admin'): ?>
                            <span class="role-badge-admin"><i class="fas fa-shield-alt"></i> Admin</span>
                          <?php else: ?>
                            <span class="role-badge-user"><i class="fas fa-user"></i> User</span>
                          <?php endif; ?>
                        </td>
                        <td style="font-size: 13px; color: #4ade80;">
                          <i class="fas fa-sign-in-alt"></i> <?php echo $timeIn ? date('M d, Y h:i:s A', $timeIn) : 'N/A'; ?>
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
          <?php endif; ?>

          <!-- PAGINATION -->
          <?php if ($totalPages > 1): ?>
            <div class="logs-pagination-wrapper">
              <div class="pagination-info">
                Showing <strong><?php echo $totalRecords > 0 ? $offset + 1 : 0; ?></strong> to <strong><?php echo min($offset + $limit, $totalRecords); ?></strong> of <strong><?php echo number_format($totalRecords); ?></strong> records
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

  <script src="../js/loader.js?v=<?php echo time(); ?>"></script>
  <script src="../js/admin.js?v=<?php echo time(); ?>"></script>
</body>

</html>
