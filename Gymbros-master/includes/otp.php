<?php
require_once __DIR__ . '/config.php';

class OtpService
{
    /**
     * Generate a new 6-digit OTP code, invalidate previous active ones, and save to DB
     */
    public static function generateOTP($userId, $email, $purpose = 'forgot_password', $expiryMinutes = 10)
    {
        $db = new Database();
        $conn = $db->getConnection();

        $email = trim(strtolower($email));
        $purpose = trim($purpose);

        // Invalidate previous unused codes for this email and purpose
        $stmtInv = $conn->prepare("UPDATE otp_codes SET is_used = 1 WHERE email = ? AND purpose = ? AND is_used = 0");
        if ($stmtInv) {
            $stmtInv->bind_param("ss", $email, $purpose);
            $stmtInv->execute();
            $stmtInv->close();
        }

        // Generate cryptographically secure 6-digit numeric OTP
        $otpCode = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiryMinutes * 60));

        $stmt = $conn->prepare("INSERT INTO otp_codes (user_id, email, otp_code, purpose, expires_at, is_used, attempts) VALUES (?, ?, ?, ?, ?, 0, 0)");
        if ($stmt) {
            $stmt->bind_param("sssss", $userId, $email, $otpCode, $purpose, $expiresAt);
            $success = $stmt->execute();
            $stmt->close();
            return $success ? $otpCode : false;
        }

        return false;
    }

    /**
     * Verify an entered OTP code
     */
    public static function verifyOTP($email, $code, $purpose = 'forgot_password')
    {
        $db = new Database();
        $conn = $db->getConnection();

        $email = trim(strtolower($email));
        $code = trim($code);
        $purpose = trim($purpose);

        // Lookup latest code for this email and purpose
        $stmt = $conn->prepare("SELECT id, user_id, otp_code, expires_at, is_used, attempts FROM otp_codes WHERE email = ? AND purpose = ? ORDER BY id DESC LIMIT 1");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database query failed'];
        }

        $stmt->bind_param("ss", $email, $purpose);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return ['success' => false, 'message' => 'No active OTP request found for this email. Please request a new code.'];
        }

        $row = $result->fetch_assoc();
        $stmt->close();

        // Check if already used
        if ($row['is_used'] == 1) {
            return ['success' => false, 'message' => 'This One-Time PIN (OTP) has already been used. Please request a new code.'];
        }

        // Check expiration
        if (strtotime($row['expires_at']) < time()) {
            return ['success' => false, 'message' => 'This One-Time PIN (OTP) has expired. Please request a new one.'];
        }

        // Check max attempts limit
        if ($row['attempts'] >= 5) {
            return ['success' => false, 'message' => 'Too many failed verification attempts. Please generate a new OTP.'];
        }

        // Check code match
        if ($row['otp_code'] !== $code) {
            // Increment failed attempt counter
            $logId = (int)$row['id'];
            $conn->query("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = $logId");
            $remaining = 4 - (int)$row['attempts'];
            return [
                'success' => false,
                'message' => 'Invalid OTP code entered. ' . ($remaining > 0 ? "You have $remaining attempts remaining." : 'Please request a new code.')
            ];
        }

        // OTP is valid! Mark as used
        $logId = (int)$row['id'];
        $conn->query("UPDATE otp_codes SET is_used = 1 WHERE id = $logId");

        return [
            'success' => true,
            'user_id' => $row['user_id'],
            'email' => $email,
            'message' => 'Account identity successfully verified.'
        ];
    }

    /**
     * Send email containing OTP via GymBrosMailer (SMTP / PHP mail fallback)
     */
    public static function sendEmailOTP($email, $otpCode, $recipientName = 'GymBros Member', $purpose = 'forgot_password')
    {
        require_once __DIR__ . '/mailer.php';
        return GymBrosMailer::sendOtpEmail($email, $otpCode, $recipientName, $purpose);
    }
}
?>
