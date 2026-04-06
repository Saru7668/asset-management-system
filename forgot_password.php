<?php
session_start();
require_once('db.php');
require_once('header.php');

ini_set('display_errors', 1);
error_reporting(E_ALL);

$error = "";
$success = "";
$secret_salt = "scl_ams_secure_salt_2026";
$mail_sent = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $captcha_input = trim($_POST['captcha'] ?? '');
    $captcha_hash_check = $_POST['captcha_hash'] ?? '';
    $user_answer_hash = md5($captcha_input . $secret_salt);

    if (empty($captcha_input) || $user_answer_hash !== $captcha_hash_check) {
        $error = "Incorrect Security Answer!";
    } elseif (empty($email)) {
        $error = "Please enter your email address.";
    } else {
        $sql = "SELECT id, username, email FROM users WHERE email = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            $username = $row['username'];
            $token = bin2hex(random_bytes(32));
            $expiry_time = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $update_sql = "UPDATE users SET confirm_token = ?, confirm_token_expiry = ? WHERE email = ?";
            $up_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($up_stmt, "sss", $token, $expiry_time, $email);

            if (mysqli_stmt_execute($up_stmt)) {
                $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
                          . "://" . $_SERVER['HTTP_HOST']
                          . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

                $reset_link = $base_url . "/reset_password.php?token=" . urlencode($token);

                $subject = "Password Reset Request - SCL AMS";

                $html_message = '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Password Reset</title>
                </head>
                <body style="margin:0;padding:0;background-color:#f4f7fb;font-family:Segoe UI,Tahoma,Arial,sans-serif;">
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f7fb;padding:30px 0;">
                        <tr>
                            <td align="center">
                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.08);">
                                    
                                    <tr>
                                        <td style="background:linear-gradient(135deg,#0b2545 0%,#1e3a8a 100%);padding:24px 30px;text-align:center;">
                                            <h1 style="margin:0;font-size:24px;color:#ffffff;">SCL AMS</h1>
                                            <p style="margin:8px 0 0 0;font-size:14px;color:#dbeafe;">Password Reset Request</p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="padding:30px;">
                                            <p style="margin:0 0 15px 0;font-size:15px;color:#334155;">Dear <strong>' . htmlspecialchars($username) . '</strong>,</p>

                                            <p style="margin:0 0 15px 0;font-size:15px;color:#475569;line-height:1.7;">
                                                We received a request to reset your password for your <strong>SCL AMS</strong> account.
                                            </p>

                                            <p style="margin:0 0 20px 0;font-size:15px;color:#475569;line-height:1.7;">
                                                Click the button below to set a new password. This reset link will remain active for <strong>15 minutes only</strong>.
                                            </p>

                                            <div style="text-align:center;margin:30px 0;">
                                                <a href="' . htmlspecialchars($reset_link) . '" 
                                                   style="display:inline-block;padding:14px 28px;background:linear-gradient(135deg,#facc15 0%,#eab308 50%,#d97706 100%);color:#1f2937;text-decoration:none;font-size:15px;font-weight:700;border-radius:999px;">
                                                   Reset Password
                                                </a>
                                            </div>

                                            <p style="margin:0 0 10px 0;font-size:14px;color:#64748b;line-height:1.7;">
                                                If the button above does not work, copy and paste the link below into your browser:
                                            </p>

                                            <p style="margin:0 0 18px 0;word-break:break-all;font-size:13px;color:#2563eb;">
                                                ' . htmlspecialchars($reset_link) . '
                                            </p>

                                            <div style="background:#fff7ed;border:1px solid #fdba74;color:#9a3412;padding:14px 16px;border-radius:10px;font-size:13px;line-height:1.6;margin-top:20px;">
                                                For your security, this link will expire after 15 minutes. If you did not request a password reset, you can safely ignore this email.
                                            </div>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="background:#f8fafc;padding:18px 30px;text-align:center;border-top:1px solid #e2e8f0;">
                                            <p style="margin:0;font-size:12px;color:#64748b;">SCL AMS | Sheltech Ceramics Ltd.</p>
                                        </td>
                                    </tr>

                                </table>
                            </td>
                        </tr>
                    </table>
                </body>
                </html>';

                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: SCL AMS <no-reply@sheltechceramics.com>\r\n";
                $headers .= "Reply-To: no-reply@sheltechceramics.com\r\n";

                if (@mail($email, $subject, $html_message, $headers)) {
                    $success = "A password reset link has been sent to your email address. The link will expire in 15 minutes.";
                    $mail_sent = true;
                } else {
                    $error = "Email sending failed. Please contact ICT Admin.";
                }
            } else {
                $error = "Something went wrong, please try again later.";
            }
        } else {
            $error = "No account found with this email address.";
        }
    }
}

$num1 = rand(1, 9);
$num2 = rand(1, 9);
$sum = $num1 + $num2;
$correct_hash = md5($sum . $secret_salt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - SCL AMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            background: radial-gradient(circle at top, #1e3a8a 0%, #020617 55%, #020617 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            color: #0f172a;
        }

        .forgot-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 16px;
        }

        .forgot-container {
            background: rgba(248, 250, 252, 0.98);
            padding: 26px 26px 24px 26px;
            border-radius: 14px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.55);
            border-top: 4px solid #eab308;
            position: relative;
            overflow: hidden;
        }

        .forgot-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        .logo-pill {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, #0b2545 0%, #1e3a8a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fefce8;
            box-shadow: 0 8px 18px rgba(30, 64, 175, 0.55);
            flex-shrink: 0;
        }

        .forgot-header-text h2 {
            margin: 0;
            color: #0b2545;
            font-size: 21px;
            font-weight: 700;
        }

        .forgot-header-text span {
            font-size: 13px;
            color: #64748b;
        }

        .info-text {
            font-size: 13px;
            color: #475569;
            margin-bottom: 18px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 14px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #0f172a;
            font-size: 13px;
        }

        .form-group input[type="email"],
        .form-group input[type="number"] {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #f59e0b;
            box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.35);
        }

        .captcha-label span {
            color: #b45309;
            font-weight: 700;
        }

        .btn-reset,
        .btn-login {
            width: 100%;
            padding: 11px;
            background: linear-gradient(135deg, #facc15 0%, #eab308 50%, #d97706 100%);
            color: #1f2933;
            border: none;
            border-radius: 999px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 6px;
            box-shadow: 0 8px 18px rgba(234, 179, 8, 0.45);
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-reset:hover,
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(234, 179, 8, 0.55);
        }

        .message {
            padding: 10px 11px;
            border-radius: 8px;
            margin-bottom: 14px;
            font-size: 13px;
            text-align: left;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .message i {
            margin-top: 1px;
            font-size: 15px;
        }

        .error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .success-panel {
            text-align: center;
            padding: 10px 4px 4px;
        }

        .success-circle-wrap {
            width: 92px;
            height: 92px;
            margin: 0 auto 18px;
            position: relative;
            animation: popIn 0.45s ease;
        }

        .success-circle {
            width: 92px;
            height: 92px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #86efac 0%, #22c55e 60%, #15803d 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 12px 30px rgba(34, 197, 94, 0.28);
            position: relative;
            z-index: 2;
        }

        .success-circle i {
            color: #fff;
            font-size: 36px;
        }

        .ring {
            position: absolute;
            border-radius: 50%;
            border: 2px solid rgba(34, 197, 94, 0.25);
            animation: ripple 1.8s infinite;
            inset: -8px;
        }

        .ring.r2 {
            animation-delay: 0.4s;
        }

        .success-title {
            font-size: 22px;
            font-weight: 700;
            color: #14532d;
            margin-bottom: 8px;
        }

        .success-text {
            font-size: 14px;
            color: #475569;
            line-height: 1.7;
            margin-bottom: 18px;
        }

        .back-login {
            margin-top: 18px;
            font-size: 13px;
            text-align: center;
        }

        .back-login a {
            color: #0b63ce;
            text-decoration: none;
            font-weight: 600;
        }

        .back-login a:hover {
            text-decoration: underline;
        }

        @keyframes popIn {
            0% { transform: scale(0.7); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        @keyframes ripple {
            0% {
                transform: scale(0.9);
                opacity: 0.7;
            }
            100% {
                transform: scale(1.35);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
<div class="forgot-wrapper">
    <div class="forgot-container">
        <div class="forgot-header">
            <div class="logo-pill">
                <i class="fas fa-key"></i>
            </div>
            <div class="forgot-header-text">
                <h2>Forgot Password</h2>
                <span>SCL AMS - Secure reset</span>
            </div>
        </div>

        <?php if ($mail_sent): ?>
            <div class="success-panel">
                <div class="success-circle-wrap">
                    <div class="ring"></div>
                    <div class="ring r2"></div>
                    <div class="success-circle">
                        <i class="fas fa-check"></i>
                    </div>
                </div>

                <div class="success-title">Reset Link Sent</div>
                <div class="success-text">
                    We have sent your password reset link to your email address.<br>
                    The link will expire in 15 minutes.
                </div>

                <a href="login.php" class="btn-login">Back to Login</a>
            </div>

        <?php else: ?>

            <p class="info-text">
                Enter your registered email address and solve the small security question.
                We will send you a password reset link.
            </p>

            <?php if ($error): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <div class="form-group">
                    <label>Email Address <span style="color:#dc2626;">*</span></label>
                    <input type="email" name="email" placeholder="Enter your company email" required>
                </div>

                <div class="form-group">
                    <label class="captcha-label">
                        Security Check: <span><?php echo "$num1 + $num2"; ?> = ?</span>
                    </label>
                    <input type="number" name="captcha" id="captcha" required>
                    <input type="hidden" name="captcha_hash" value="<?php echo $correct_hash; ?>">
                </div>

                <button type="submit" class="btn-reset">Send Reset Link</button>
            </form>

            <div class="back-login">
                <a href="login.php"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
            </div>

        <?php endif; ?>
    </div>
</div>
</body>
</html>
