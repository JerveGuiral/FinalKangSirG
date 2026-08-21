<?php
require_once __DIR__ . '/config.php';

/**
 * GymBros Mailer Utility
 * Provides robust email delivery with SMTP (TLS/SSL/AUTH) and PHP mail() fallback.
 */
class GymBrosMailer
{
    /**
     * Send an OTP authentication email to the user
     * 
     * @param string $toEmail
     * @param string $otpCode
     * @param string $recipientName
     * @param string $purpose
     * @return bool
     */
    public static function sendOtpEmail($toEmail, $otpCode, $recipientName = 'GymBros Member', $purpose = 'forgot_password')
    {
        $subject = "GymBros Security: Your One-Time PIN (OTP) is $otpCode";

        $actionTitle = ($purpose === 'forgot_password')
            ? "Password Recovery Request"
            : "Account Verification Request";

        $actionDescription = ($purpose === 'forgot_password')
            ? "We received a request to reset the password for your GymBros account."
            : "We received a request to verify and secure your GymBros account.";

        $htmlBody = self::buildOtpHtmlTemplate($recipientName, $otpCode, $actionTitle, $actionDescription);
        $plainTextBody = self::buildOtpPlainTextTemplate($recipientName, $otpCode, $actionTitle, $actionDescription);

        return self::sendMail($toEmail, $subject, $htmlBody, $plainTextBody, $recipientName);
    }

    /**
     * Send an email with HTML and plain-text multipart support
     * 
     * @param string $toEmail
     * @param string $subject
     * @param string $htmlContent
     * @param string $plainContent
     * @param string $recipientName
     * @return bool
     */
    public static function sendMail($toEmail, $subject, $htmlContent, $plainContent = '', $recipientName = '')
    {
        $toEmail = trim($toEmail);
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("GymBrosMailer: Invalid recipient email address: $toEmail");
            return false;
        }

        $smtpEnabled = defined('SMTP_ENABLED') ? SMTP_ENABLED : false;
        $smtpHost = defined('SMTP_HOST') ? SMTP_HOST : '';
        $smtpUser = defined('SMTP_USER') ? SMTP_USER : '';
        $smtpPass = defined('SMTP_PASS') ? SMTP_PASS : '';

        // If SMTP is configured and credentials/host are present, use SMTP
        if ($smtpEnabled && !empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass)) {
            $smtpSuccess = self::sendViaSmtp($toEmail, $subject, $htmlContent, $plainContent, $recipientName);
            if ($smtpSuccess) {
                return true;
            }
            error_log("GymBrosMailer: SMTP delivery failed, attempting fallback to mail()");
        }

        // Fallback to standard PHP mail()
        return self::sendViaPhpMail($toEmail, $subject, $htmlContent, $plainContent, $recipientName);
    }

    /**
     * Send email directly via SMTP socket with TLS/SSL encryption
     */
    private static function sendViaSmtp($toEmail, $subject, $htmlContent, $plainContent, $recipientName)
    {
        $host = defined('SMTP_HOST') ? SMTP_HOST : 'localhost';
        $port = defined('SMTP_PORT') ? SMTP_PORT : 587;
        $secure = defined('SMTP_SECURE') ? strtolower(SMTP_SECURE) : 'tls';
        $user = defined('SMTP_USER') ? trim(SMTP_USER) : '';
        $pass = defined('SMTP_PASS') ? str_replace(' ', '', trim(SMTP_PASS)) : '';
        $fromEmail = defined('SMTP_FROM_EMAIL') && !empty(SMTP_FROM_EMAIL) ? trim(SMTP_FROM_EMAIL) : (!empty($user) ? $user : 'no-reply@gymbros.com');
        $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'GymBros Security';

        $timeout = 15;
        $socketHost = ($secure === 'ssl') ? "ssl://$host" : $host;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $socket = @stream_socket_client(
            "$socketHost:$port",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            error_log("GymBrosMailer SMTP Socket Error: $errstr ($errno)");
            return false;
        }

        stream_set_timeout($socket, $timeout);

        $readResponse = function() use ($socket) {
            $data = '';
            while ($str = fgets($socket, 515)) {
                $data .= $str;
                // Multiline SMTP response lines have '-' as 4th char (e.g. 250-SIZE), last line has ' ' (e.g. 250 OK)
                if (strlen($str) >= 4 && substr($str, 3, 1) === ' ') {
                    break;
                }
            }
            return $data;
        };

        $sendCommand = function($cmd) use ($socket, $readResponse) {
            fputs($socket, $cmd . "\r\n");
            return $readResponse();
        };

        $initial = $readResponse();
        if (substr($initial, 0, 3) !== '220') {
            error_log("GymBrosMailer SMTP: Unexpected welcome: $initial");
            fclose($socket);
            return false;
        }

        // Send EHLO with safe client domain
        $clientHost = !empty(gethostname()) ? preg_replace('/[^a-zA-Z0-9\.\-]/', '', gethostname()) : 'localhost';
        if (empty($clientHost)) $clientHost = 'localhost';
        $ehlo = $sendCommand("EHLO $clientHost");

        // STARTTLS if requested and on non-ssl socket
        if ($secure === 'tls') {
            $starttls = $sendCommand("STARTTLS");
            if (substr($starttls, 0, 3) === '220') {
                $crypto = stream_socket_enable_crypto(
                    $socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
                );
                if (!$crypto) {
                    error_log("GymBrosMailer SMTP: TLS handshake failed");
                    fclose($socket);
                    return false;
                }
                // Resend EHLO after TLS negotiation
                $sendCommand("EHLO $clientHost");
            }
        }

        // Authenticate if credentials provided
        if (!empty($user) && !empty($pass)) {
            $authLogin = $sendCommand("AUTH LOGIN");
            if (substr($authLogin, 0, 3) !== '334') {
                error_log("GymBrosMailer SMTP: AUTH LOGIN rejected: $authLogin");
                fclose($socket);
                return false;
            }

            $authUser = $sendCommand(base64_encode($user));
            if (substr($authUser, 0, 3) !== '334') {
                error_log("GymBrosMailer SMTP: Username rejected: $authUser");
                fclose($socket);
                return false;
            }

            $authPass = $sendCommand(base64_encode($pass));
            if (substr($authPass, 0, 3) !== '235') {
                error_log("GymBrosMailer SMTP: Password auth failed: $authPass");
                fclose($socket);
                return false;
            }
        }

        // Set Mail From & RCPT To
        $mailFrom = $sendCommand("MAIL FROM:<$fromEmail>");
        if (substr($mailFrom, 0, 3) !== '250') {
            error_log("GymBrosMailer SMTP: MAIL FROM error: $mailFrom");
            fclose($socket);
            return false;
        }

        $rcptTo = $sendCommand("RCPT TO:<$toEmail>");
        if (substr($rcptTo, 0, 3) !== '250') {
            error_log("GymBrosMailer SMTP: RCPT TO error: $rcptTo");
            fclose($socket);
            return false;
        }

        $dataCmd = $sendCommand("DATA");
        if (substr($dataCmd, 0, 3) !== '354') {
            error_log("GymBrosMailer SMTP: DATA command error: $dataCmd");
            fclose($socket);
            return false;
        }

        // Construct MIME Message
        $boundary = "----=_GymBros_NextPart_" . md5(uniqid(time(), true));
        $toHeader = !empty($recipientName) ? "=?UTF-8?B?" . base64_encode($recipientName) . "?= <$toEmail>" : "<$toEmail>";
        $fromHeader = "=?UTF-8?B?" . base64_encode($fromName) . "?= <$fromEmail>";
        $subjectHeader = "=?UTF-8?B?" . base64_encode($subject) . "?=";

        $headers = [];
        $headers[] = "Date: " . date('r');
        $headers[] = "From: $fromHeader";
        $headers[] = "To: $toHeader";
        $headers[] = "Subject: $subjectHeader";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "X-Mailer: GymBros-Mailer/1.0";
        $headers[] = "Content-Type: multipart/alternative; boundary=\"$boundary\"";

        $body = implode("\r\n", $headers) . "\r\n\r\n";
        
        // Plain text part
        if (!empty($plainContent)) {
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($plainContent)) . "\r\n";
        }

        // HTML part
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlContent)) . "\r\n";
        $body .= "--$boundary--\r\n";

        // Dot termination
        $body .= ".";

        $dataResp = $sendCommand($body);
        $sendCommand("QUIT");
        fclose($socket);

        if (substr($dataResp, 0, 3) === '250') {
            return true;
        }

        error_log("GymBrosMailer SMTP: Data transfer response: $dataResp");
        return false;
    }

    /**
     * Send email via standard PHP mail() with HTML headers
     */
    private static function sendViaPhpMail($toEmail, $subject, $htmlContent, $plainContent, $recipientName)
    {
        $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'security@gymbros.com';
        $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'GymBros Security';

        $boundary = "----=_GymBros_NextPart_" . md5(uniqid(time(), true));
        $toHeader = !empty($recipientName) ? "$recipientName <$toEmail>" : $toEmail;

        $headers = [];
        $headers[] = "From: $fromName <$fromEmail>";
        $headers[] = "Reply-To: $fromEmail";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "X-Mailer: GymBros-PHP/" . phpversion();
        $headers[] = "Content-Type: multipart/alternative; boundary=\"$boundary\"";

        $message = "--$boundary\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= ($plainContent ?: strip_tags($htmlContent)) . "\r\n\r\n";

        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $htmlContent . "\r\n\r\n";
        $message .= "--$boundary--";

        return @mail($toHeader, $subject, $message, implode("\r\n", $headers));
    }

    /**
     * Build rich, responsive, branded HTML email template for GymBros OTP
     */
    private static function buildOtpHtmlTemplate($recipientName, $otpCode, $actionTitle, $actionDescription)
    {
        $year = date('Y');
        $safeName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
        $formattedOtp = htmlspecialchars($otpCode, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GymBros Verification Code</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0b0f19; font-family: 'Segoe UI', Arial, sans-serif; color: #f8fafc;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #0b0f19; padding: 30px 10px;">
    <tr>
      <td align="center">
        <!-- Main Card -->
        <table role="presentation" width="100%" max-width="540" cellspacing="0" cellpadding="0" border="0" style="max-width: 540px; background-color: #111827; border-radius: 16px; border: 1px solid rgba(255, 94, 0, 0.25); box-shadow: 0 10px 25px rgba(0,0,0,0.5); overflow: hidden;">
          
          <!-- Header Banner -->
          <tr>
            <td style="background: linear-gradient(135deg, #1e293b, #0f172a); padding: 25px 30px; text-align: center; border-bottom: 2px solid #ff5e00;">
              <h1 style="margin: 0; font-size: 28px; font-weight: 800; letter-spacing: 1px; color: #ffffff;">
                Gym<span style="color: #ff5e00;">Bros</span>
              </h1>
              <p style="margin: 5px 0 0 0; font-size: 12px; text-transform: uppercase; letter-spacing: 2px; color: #94a3b8;">
                Account Security & Authentication
              </p>
            </td>
          </tr>

          <!-- Content Body -->
          <tr>
            <td style="padding: 35px 30px 25px 30px;">
              <h2 style="margin: 0 0 15px 0; font-size: 20px; color: #f8fafc; font-weight: 700;">
                {$actionTitle}
              </h2>
              <p style="margin: 0 0 15px 0; font-size: 15px; line-height: 1.6; color: #cbd5e1;">
                Hello <strong>{$safeName}</strong>,
              </p>
              <p style="margin: 0 0 25px 0; font-size: 14px; line-height: 1.6; color: #94a3b8;">
                {$actionDescription} Use the One-Time PIN (OTP) code below to securely verify your identity.
              </p>

              <!-- OTP Code Display Box -->
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 25px 0;">
                <tr>
                  <td align="center" style="background: rgba(255, 94, 0, 0.08); border: 2px dashed #ff5e00; border-radius: 12px; padding: 20px;">
                    <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; color: #ff7b00; font-weight: 600; margin-bottom: 8px;">
                      Your One-Time PIN (OTP)
                    </div>
                    <div style="font-family: 'Courier New', Courier, monospace, monospace; font-size: 38px; font-weight: 800; letter-spacing: 10px; color: #ffffff; text-shadow: 0 0 10px rgba(255, 94, 0, 0.5);">
                      {$formattedOtp}
                    </div>
                    <div style="font-size: 12px; color: #94a3b8; margin-top: 8px;">
                      ⏱️ Valid for <strong>10 minutes</strong> only
                    </div>
                  </td>
                </tr>
              </table>

              <!-- Security Notice -->
              <div style="background-color: #1e293b; border-left: 4px solid #ef4444; border-radius: 6px; padding: 12px 16px; margin: 25px 0 15px 0;">
                <p style="margin: 0; font-size: 13px; color: #fca5a5; line-height: 1.5;">
                  <strong>⚠️ Security Alert:</strong> Never share this OTP code with anyone. GymBros staff and administrators will never ask for your PIN.
                </p>
              </div>

              <p style="margin: 20px 0 0 0; font-size: 13px; line-height: 1.5; color: #64748b;">
                If you did not request this verification, please disregard this email or review your account security immediately.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color: #0b0f19; padding: 20px 30px; text-align: center; border-top: 1px solid #1f2937;">
              <p style="margin: 0; font-size: 12px; color: #64748b;">
                &copy; {$year} GymBros Fitness HQ. All rights reserved.
              </p>
              <p style="margin: 5px 0 0 0; font-size: 11px; color: #475569;">
                This is an automated security transmission. Please do not reply directly to this email.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    /**
     * Build Plain Text template fallback for OTP
     */
    private static function buildOtpPlainTextTemplate($recipientName, $otpCode, $actionTitle, $actionDescription)
    {
        return "GymBros Security: $actionTitle\n\n"
             . "Hello $recipientName,\n\n"
             . "$actionDescription\n\n"
             . "Your 6-Digit One-Time PIN (OTP) is: $otpCode\n\n"
             . "This code will expire in 10 minutes.\n\n"
             . "SECURITY NOTICE: Do not share this code with anyone. GymBros staff will never ask for your OTP.\n"
             . "If you did not request this code, please ignore this email or secure your account.\n\n"
             . "Best regards,\n"
             . "GymBros Security Team\n";
    }
}
