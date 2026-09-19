<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/auth.php';
require_once '../includes/validation.php';
require_once '../includes/otp.php';
require_once '../includes/mailer.php';

header('Content-Type: application/json');

// Check authentication
if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (Auth::needsFirstLoginSetup()) {
    echo json_encode(['success' => false, 'message' => 'Please complete your account setup (password change & security questions) first.']);
    exit();
}

$currentUser = $_SESSION['user'];
$isSuperAdmin = Auth::isSuperAdmin();
$isAdmin = Auth::isAdmin();

if (!$isAdmin) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit();
}

// Read input (JSON or POST)
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;

$action = $input['action'] ?? '';
$csrfToken = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

if (!Security::verifyCSRFToken($csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();

switch ($action) {
    case 'update_status':
        $targetUserId = $db->sanitize($input['user_id'] ?? '');
        $newStatus = $db->sanitize($input['status'] ?? '');

        if (!in_array($newStatus, ['pending', 'approved', 'blocked'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid status option']);
            exit();
        }

        // Fetch target user role to prevent regular admin from altering Super Admin
        $stmt = $conn->prepare("SELECT role FROM users WHERE id_number = ?");
        $stmt->bind_param("s", $targetUserId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }
        $targetRole = $res->fetch_assoc()['role'];
        $stmt->close();

        if ($targetRole === 'superadmin' && !$isSuperAdmin) {
            echo json_encode(['success' => false, 'message' => 'Only Super Admin can alter Super Admin accounts']);
            exit();
        }

        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id_number = ?");
        $stmt->bind_param("ss", $newStatus, $targetUserId);
        if ($stmt->execute()) {
            ActivityLogger::log('UPDATE_STATUS', "Changed status of user '{$targetUserId}' ({$targetRole}) to '{$newStatus}'.", 'User Management');
            $msg = ($newStatus === 'approved') ? 'Account approved / unblocked successfully' : ($newStatus === 'blocked' ? 'Account has been blocked' : 'Account status set to pending');
            echo json_encode(['success' => true, 'message' => $msg]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database update failed: ' . $conn->error]);
        }
        $stmt->close();
        break;

    case 'create_account':
        if (!$isSuperAdmin) {
            echo json_encode(['success' => false, 'message' => 'Only Super Administrator can create Super Admin/Admin accounts']);
            exit();
        }

        $id_number = $db->sanitize($input['id_number'] ?? '');
        $username = $db->sanitize($input['username'] ?? '');
        $first_name = $db->sanitize($input['first_name'] ?? '');
        $middle_name = $db->sanitize($input['middle_name'] ?? '');
        $last_name = $db->sanitize($input['last_name'] ?? '');
        $extension_name = $db->sanitize($input['extension_name'] ?? '');
        $birthdate = $db->sanitize($input['birthdate'] ?? '');
        $email = $db->sanitize($input['email'] ?? '');
        $sex = $db->sanitize($input['sex'] ?? 'other');
        $purok_street = $db->sanitize($input['purok_street'] ?? '');
        $barangay = $db->sanitize($input['barangay'] ?? '');
        $city_municipality = $db->sanitize($input['city_municipality'] ?? '');
        $province = $db->sanitize($input['province'] ?? '');
        $country = $db->sanitize($input['country'] ?? 'Philippines');
        $zip_code = $db->sanitize($input['zip_code'] ?? '');
        $role = $db->sanitize($input['role'] ?? 'admin');
        $status = $db->sanitize($input['status'] ?? 'approved');

        if (!in_array($role, ['superadmin', 'admin', 'user'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid role specified']);
            exit();
        }

        // Handle automated & custom privileges based on role
        $privilegesInput = $input['privileges'] ?? null;
        if ($role === 'superadmin') {
            $privileges = json_encode(Auth::getDefaultPrivilegesForRole('superadmin'));
        } elseif ($role === 'admin') {
            if (is_array($privilegesInput) && count(array_filter($privilegesInput)) > 0) {
                $sanitizedPrivs = [];
                foreach (Auth::getAllPrivilegeKeys() as $k) {
                    $sanitizedPrivs[$k] = !empty($privilegesInput[$k]);
                }
                $privileges = json_encode($sanitizedPrivs);
            } else {
                // Automatically grant default admin privileges
                $privileges = json_encode(Auth::getDefaultAdminPrivileges());
            }
        } else {
            $privileges = NULL;
        }

        if (empty($id_number) || empty($username) || empty($first_name) || empty($last_name) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
            exit();
        }

        $vErrors = [];

        // Validate Username format
        $uErr = Validation::validateUsername($username);
        if (!empty($uErr)) $vErrors = array_merge($vErrors, $uErr);

        // Validate Email format
        $eErr = Validation::validateEmail($email);
        if (!empty($eErr)) $vErrors = array_merge($vErrors, $eErr);

        // Validate Age (min 18)
        if (!empty($birthdate)) {
            $aErr = Validation::validateAge($birthdate);
            if (!empty($aErr)) $vErrors = array_merge($vErrors, $aErr);
        }

        // Check Email uniqueness
        $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $vErrors[] = "Email address '$email' is already registered";
        }
        $stmt->close();

        // Check Employee ID & Username uniqueness
        $stmt = $conn->prepare("SELECT id_number, username FROM users WHERE id_number = ? OR username = ?");
        $stmt->bind_param("ss", $id_number, $username);
        $stmt->execute();
        $resUniq = $stmt->get_result();
        while ($rowU = $resUniq->fetch_assoc()) {
            if ($rowU['id_number'] === $id_number) {
                $vErrors[] = "Employee ID '$id_number' already exists in system";
            }
            if (strtolower($rowU['username']) === strtolower($username)) {
                $vErrors[] = "Username '$username' is already taken";
            }
        }
        $stmt->close();

        if (!empty($vErrors)) {
            echo json_encode(['success' => false, 'message' => implode(' | ', $vErrors)]);
            exit();
        }

        // Calculate age
        $age = 0;
        if (!empty($birthdate)) {
            $bdate = new DateTime($birthdate);
            $today = new DateTime();
            $age = $today->diff($bdate)->y;
        }

        // Generate a random temporary password — the superadmin no longer types one in;
        // it's emailed to the account holder and must be changed on first login.
        $tempPassword = Security::generateTempPassword();
        $password_hash = Security::hashPassword($tempPassword);
        $mustChangePassword = 1;

        $stmt = $conn->prepare("INSERT INTO users (id_number, username, password_hash, first_name, middle_name, last_name, extension_name, birthdate, age, email, sex, purok_street, barangay, city_municipality, province, country, zip_code, role, status, privileges, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssisssssssssssi", $id_number, $username, $password_hash, $first_name, $middle_name, $last_name, $extension_name, $birthdate, $age, $email, $sex, $purok_street, $barangay, $city_municipality, $province, $country, $zip_code, $role, $status, $privileges, $mustChangePassword);

        if ($stmt->execute()) {
            ActivityLogger::log('CREATE_ACCOUNT', "Created new {$role} account: @{$username} ({$first_name} {$last_name}, ID: {$id_number}).", 'User Management');

            $roleLabels = ['superadmin' => 'Super Administrator', 'admin' => 'Administrator', 'user' => 'Member'];
            $roleLabel = $roleLabels[$role] ?? 'Member';
            $recipientName = trim("$first_name $last_name");

            $emailSent = GymBrosMailer::sendAccountCreatedEmail($email, $recipientName, $username, $tempPassword, $roleLabel);

            $message = "Account '$username' ($role) created successfully.";
            $message .= $emailSent
                ? " The temporary password has been emailed to $email."
                : " Warning: the account was created but the welcome email could not be delivered — please share the temporary password with the user manually.";

            echo json_encode(['success' => true, 'message' => $message, 'temp_password' => $emailSent ? null : $tempPassword]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create account: ' . $conn->error]);
        }
        $stmt->close();
        break;

    case 'update_user_info':
        $targetUserId = $db->sanitize($input['id_number'] ?? '');
        $username = $db->sanitize($input['username'] ?? '');
        $first_name = $db->sanitize($input['first_name'] ?? '');
        $middle_name = $db->sanitize($input['middle_name'] ?? '');
        $last_name = $db->sanitize($input['last_name'] ?? '');
        $email = $db->sanitize($input['email'] ?? '');
        $birthdate = $db->sanitize($input['birthdate'] ?? '');
        $sex = $db->sanitize($input['sex'] ?? 'male');
        $purok_street = $db->sanitize($input['purok_street'] ?? '');
        $barangay = $db->sanitize($input['barangay'] ?? '');
        $city_municipality = $db->sanitize($input['city_municipality'] ?? '');
        $province = $db->sanitize($input['province'] ?? '');
        $zip_code = $db->sanitize($input['zip_code'] ?? '');
        $newRole = isset($input['role']) ? $db->sanitize($input['role']) : null;
        $newStatus = isset($input['status']) ? $db->sanitize($input['status']) : null;

        // Check target user role
        $stmt = $conn->prepare("SELECT role FROM users WHERE id_number = ?");
        $stmt->bind_param("s", $targetUserId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Target user not found']);
            exit();
        }
        $targetRole = $res->fetch_assoc()['role'];
        $stmt->close();

        if ($targetRole === 'superadmin' && !$isSuperAdmin) {
            echo json_encode(['success' => false, 'message' => 'Only Super Admin can update Super Admin details']);
            exit();
        }

        // Calculate age
        $age = 0;
        if (!empty($birthdate)) {
            $bdate = new DateTime($birthdate);
            $today = new DateTime();
            $age = $today->diff($bdate)->y;
        }

        // Build UPDATE query
        $query = "UPDATE users SET username=?, first_name=?, middle_name=?, last_name=?, email=?, birthdate=?, age=?, sex=?, purok_street=?, barangay=?, city_municipality=?, province=?, zip_code=?";
        $params = [$username, $first_name, $middle_name, $last_name, $email, $birthdate, $age, $sex, $purok_street, $barangay, $city_municipality, $province, $zip_code];
        $types = "ssssssissssss";

        if ($isSuperAdmin && !empty($newRole)) {
            $query .= ", role=?";
            $params[] = $newRole;
            $types .= "s";

            if ($newRole === 'admin') {
                $stmtCheckPriv = $conn->prepare("SELECT privileges FROM users WHERE id_number = ?");
                $stmtCheckPriv->bind_param("s", $targetUserId);
                $stmtCheckPriv->execute();
                $rPriv = $stmtCheckPriv->get_result()->fetch_assoc();
                $stmtCheckPriv->close();
                $curPrivs = $rPriv['privileges'] ?? '';
                if (empty($curPrivs) || $curPrivs === '{}' || $curPrivs === 'null') {
                    $query .= ", privileges=?";
                    $params[] = json_encode(Auth::getDefaultAdminPrivileges());
                    $types .= "s";
                }
            } elseif ($newRole === 'superadmin') {
                $query .= ", privileges=?";
                $params[] = json_encode(Auth::getDefaultPrivilegesForRole('superadmin'));
                $types .= "s";
            } elseif ($newRole === 'user') {
                $query .= ", privileges=NULL";
            }
        }
        if (!empty($newStatus)) {
            $query .= ", status=?";
            $params[] = $newStatus;
            $types .= "s";
        }

        $query .= " WHERE id_number=?";
        $params[] = $targetUserId;
        $types .= "s";

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            ActivityLogger::log('UPDATE_USER_INFO', "Updated account profile/info for @{$username} (ID: {$targetUserId}).", 'User Management');
            echo json_encode(['success' => true, 'message' => 'Account information updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed: ' . $conn->error]);
        }
        $stmt->close();
        break;

    case 'grant_privileges':
        if (!$isSuperAdmin) {
            echo json_encode(['success' => false, 'message' => 'Only Super Administrator can assign privileges']);
            exit();
        }

        $targetUserId = $db->sanitize($input['user_id'] ?? '');
        $privilegesData = $input['privileges'] ?? [];
        $privilegesJson = is_array($privilegesData) ? json_encode($privilegesData) : $privilegesData;

        $stmt = $conn->prepare("UPDATE users SET privileges = ? WHERE id_number = ?");
        $stmt->bind_param("ss", $privilegesJson, $targetUserId);

        if ($stmt->execute()) {
            ActivityLogger::log('GRANT_PRIVILEGES', "Updated administrative privileges for user ID {$targetUserId}.", 'User Management');
            echo json_encode(['success' => true, 'message' => 'Account privileges updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update privileges']);
        }
        $stmt->close();
        break;

    case 'request_delete':
        $targetUserId = $db->sanitize($input['user_id'] ?? '');
        $reason = trim($db->sanitize($input['reason'] ?? ''));

        if (empty($reason)) {
            echo json_encode(['success' => false, 'message' => 'Reason for deletion is required']);
            exit();
        }

        if ($targetUserId === $currentUser['id_number']) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
            exit();
        }

        // Check target exists
        $stmt = $conn->prepare("SELECT username, role FROM users WHERE id_number = ?");
        $stmt->bind_param("s", $targetUserId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }
        $stmt->close();

        // Check if there is already a pending delete request
        $stmt = $conn->prepare("SELECT id FROM delete_requests WHERE target_user_id = ? AND status = 'pending'");
        $stmt->bind_param("s", $targetUserId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'A deletion request for this user is already pending approval']);
            $stmt->close();
            exit();
        }
        $stmt->close();

        $requestedBy = $currentUser['id_number'];
        $stmt = $conn->prepare("INSERT INTO delete_requests (requested_by, target_user_id, reason) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $requestedBy, $targetUserId, $reason);

        if ($stmt->execute()) {
            ActivityLogger::log('REQUEST_DELETE', "Requested account deletion for user ID {$targetUserId}. Reason: {$reason}", 'User Management');
            echo json_encode(['success' => true, 'message' => 'Deletion request submitted to Super Administrator for approval']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to submit delete request']);
        }
        $stmt->close();
        break;

    case 'delete_account':
        // Direct deletion by Super Admin ONLY
        if (!$isSuperAdmin) {
            echo json_encode(['success' => false, 'message' => 'Only Super Administrator has direct delete privileges']);
            exit();
        }

        $targetUserId = $db->sanitize($input['user_id'] ?? '');

        if ($targetUserId === $currentUser['id_number']) {
            echo json_encode(['success' => false, 'message' => 'Super Administrator cannot delete their own active account']);
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE id_number = ?");
        $stmt->bind_param("s", $targetUserId);

        if ($stmt->execute()) {
            ActivityLogger::log('DELETE_ACCOUNT', "Permanently deleted user account ID {$targetUserId}.", 'User Management');
            // Also clean up any associated delete requests
            $stmt2 = $conn->prepare("DELETE FROM delete_requests WHERE target_user_id = ?");
            $stmt2->bind_param("s", $targetUserId);
            $stmt2->execute();
            $stmt2->close();

            echo json_encode(['success' => true, 'message' => 'User account permanently deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete user account']);
        }
        $stmt->close();
        break;

    case 'review_delete_request':
        if (!$isSuperAdmin) {
            echo json_encode(['success' => false, 'message' => 'Only Super Administrator can review delete requests']);
            exit();
        }

        $requestId = (int)($input['request_id'] ?? 0);
        $decision = $db->sanitize($input['decision'] ?? ''); // 'approve' or 'reject'

        if (!in_array($decision, ['approve', 'reject'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid decision action']);
            exit();
        }

        // Fetch request details
        $stmt = $conn->prepare("SELECT target_user_id FROM delete_requests WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Pending delete request not found']);
            exit();
        }
        $targetUserId = $res->fetch_assoc()['target_user_id'];
        $stmt->close();

        $reviewerId = $currentUser['id_number'];

        if ($decision === 'approve') {
            // Execute deletion of target user
            $stmtDelete = $conn->prepare("DELETE FROM users WHERE id_number = ?");
            $stmtDelete->bind_param("s", $targetUserId);
            $stmtDelete->execute();
            $stmtDelete->close();

            // Update request status
            $stmtUpdate = $conn->prepare("UPDATE delete_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
            $stmtUpdate->bind_param("si", $reviewerId, $requestId);
            $stmtUpdate->execute();
            $stmtUpdate->close();

            ActivityLogger::log('REVIEW_DELETE_REQUEST', "Approved deletion request #{$requestId} and removed user account ID {$targetUserId}.", 'User Management');
            echo json_encode(['success' => true, 'message' => 'Delete request approved and user account has been deleted']);
        } else {
            // Reject request
            $stmtUpdate = $conn->prepare("UPDATE delete_requests SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
            $stmtUpdate->bind_param("si", $reviewerId, $requestId);
            $stmtUpdate->execute();
            $stmtUpdate->close();

            ActivityLogger::log('REVIEW_DELETE_REQUEST', "Rejected deletion request #{$requestId} for user ID {$targetUserId}.", 'User Management');
            echo json_encode(['success' => true, 'message' => 'Delete request rejected']);
        }
        break;

    case 'get_user_details':
        $targetUserId = $db->sanitize($input['user_id'] ?? '');
        $stmt = $conn->prepare("SELECT id_number, username, first_name, middle_name, last_name, extension_name, birthdate, age, email, sex, purok_street, barangay, city_municipality, province, country, zip_code, role, status, privileges, created_at FROM users WHERE id_number = ?");
        $stmt->bind_param("s", $targetUserId);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 1) {
            $userObj = $res->fetch_assoc();
            if ($userObj['role'] === 'superadmin' && !$isSuperAdmin) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                $stmt->close();
                exit();
            }
            echo json_encode(['success' => true, 'user' => $userObj]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        $stmt->close();
        break;

    case 'get_delete_request_details':
        if (!$isSuperAdmin) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        $requestId = (int)($input['request_id'] ?? 0);

        $query = "SELECT dr.id, dr.requested_by, dr.target_user_id, dr.reason, dr.status as request_status, dr.requested_at,
                         u.username, u.first_name, u.middle_name, u.last_name, u.email, u.role, u.status as user_status, u.birthdate, u.sex, u.purok_street, u.barangay, u.city_municipality, u.province, u.created_at,
                         req.username as requester_username, req.first_name as requester_fname, req.last_name as requester_lname
                  FROM delete_requests dr
                  LEFT JOIN users u ON dr.target_user_id = u.id_number
                  LEFT JOIN users req ON dr.requested_by = req.id_number
                  WHERE dr.id = ?";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 1) {
            $data = $res->fetch_assoc();
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
        }
        $stmt->close();
        break;

    case 'send_password_reset_otp':
        $targetUserId = $db->sanitize($input['user_id'] ?? '');
        $stmt = $conn->prepare("SELECT id_number, email, first_name, last_name, username, role FROM users WHERE id_number = ? LIMIT 1");
        $stmt->bind_param("s", $targetUserId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }
        $targetUser = $res->fetch_assoc();
        $stmt->close();

        if ($targetUser['role'] === 'superadmin' && !$isSuperAdmin) {
            echo json_encode(['success' => false, 'message' => 'Only Super Admin can initiate OTP reset for Super Admin accounts']);
            exit();
        }

        $otpCode = OtpService::generateOTP($targetUser['id_number'], $targetUser['email'], 'forgot_password', 15);
        if ($otpCode) {
            $recipientName = trim($targetUser['first_name'] . ' ' . $targetUser['last_name']);
            OtpService::sendEmailOTP($targetUser['email'], $otpCode, $recipientName, 'forgot_password');
            ActivityLogger::log('DISPATCH_OTP', "Admin @{$currentUser['username']} dispatched password reset OTP to @{$targetUser['username']}.", 'Security');
            echo json_encode(['success' => true, 'message' => "Password reset OTP sent to {$targetUser['email']} successfully!"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to generate OTP code.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action request']);
        break;
}

$db->close();
?>
