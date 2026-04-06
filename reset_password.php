<?php
session_start();
require_once('db.php');
require_once('header.php');

ini_set('display_errors', 1);
error_reporting(E_ALL);

$error = "";
$success = "";
$token_valid = false;
$token = "";

if (isset($_GET['token'])) {
    $token = trim($_GET['token']);

    $sql = "SELECT id FROM users WHERE confirm_token = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_fetch_assoc($result)) {
        $token_valid = true;
    } else {
        $error = "Invalid or expired reset link!";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = trim($_POST['token'] ?? '');

    $check_sql = "SELECT id FROM users WHERE confirm_token = ? LIMIT 1";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $token);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);

    if (!mysqli_fetch_assoc($check_result)) {
        $error = "Invalid or expired reset link!";
        $token_valid = false;
    } else {
        $token_valid = true;

        $new_pass     = $_POST['password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        if (empty($new_pass) || strlen($new_pass) < 8) {
            $error = "Password must be at least 8 characters.";
        } elseif (!preg_match('/[A-Za-z]/', $new_pass)) {
            $error = "Password must contain at least one letter.";
        } elseif (!preg_match('/[0-9]/', $new_pass)) {
            $error = "Password must contain at least one number.";
        } elseif (!preg_match('/[\W_]/', $new_pass)) {
            $error = "Password must contain at least one symbol.";
        } elseif ($new_pass !== $confirm_pass) {
            $error = "Passwords do not match.";
        } else {
            $password_hash = password_hash($new_pass, PASSWORD_DEFAULT);

            $update_sql = "UPDATE users SET password = ?, confirm_token = NULL WHERE confirm_token = ?";
            $up_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($up_stmt, "ss", $password_hash, $token);

            if (mysqli_stmt_execute($up_stmt)) {
                $success = "Password updated successfully. You can now <a href='login.php'>log in</a>.";
                $token_valid = false;
            } else {
                $error = "Failed to update password. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - SCL AMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            box-sizing: border-box;
        }

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

        .reset-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 16px;
        }

        .reset-container {
            background: rgba(248, 250, 252, 0.98);
            padding: 26px 26px 24px 26px;
            border-radius: 14px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.55);
            border-top: 4px solid #eab308;
            position: relative;
            overflow: hidden;
        }

        .reset-header {
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

        .reset-header-text h2 {
            margin: 0;
            color: #0b2545;
            font-size: 21px;
            font-weight: 700;
        }

        .reset-header-text span {
            font-size: 13px;
            color: #64748b;
        }

        .info-text {
            font-size: 13px;
            color: #475569;
            margin-bottom: 18px;
            line-height: 1.6;
        }

        .password-rules {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e3a8a;
            font-size: 12px;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 16px;
            line-height: 1.5;
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

        .input-wrap {
            position: relative;
        }

        .input-wrap input {
            width: 100%;
            height: 44px;
            padding: 11px 48px 11px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            background: #fff;
        }

        .input-wrap input:focus {
            outline: none;
            border-color: #f59e0b;
            box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.35);
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            width: 32px;
            height: 32px;
            border: none;
            background: transparent;
            color: #94a3b8;
            cursor: pointer;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s ease, color 0.2s ease;
            padding: 0;
        }

        .toggle-password:hover {
            background: rgba(148, 163, 184, 0.12);
            color: #475569;
        }

        .toggle-password:focus {
            outline: none;
        }

        .btn-reset {
            width: 100%;
            padding: 11px;
            background: linear-gradient(135deg, #facc15 0%, #eab308 50%, #d97706 100%);
            color: #1f2933;
            border: none;
            border-radius: 999px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 4px;
            box-shadow: 0 8px 18px rgba(234, 179, 8, 0.45);
            transition: all 0.2s ease;
        }

        .btn-reset:hover {
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

        .success {
            background: #ecfdf3;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .success a {
            color: #166534;
            font-weight: 700;
            text-decoration: underline;
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
    </style>
</head>
<body>

<div class="reset-wrapper">
    <div class="reset-container">
        <div class="reset-header">
            <div class="logo-pill">
                <i class="fas fa-lock"></i>
            </div>
            <div class="reset-header-text">
                <h2>Set New Password</h2>
                <span>SCL AMS - Account security</span>
            </div>
        </div>

        <p class="info-text">
            Create a strong password for your SCL AMS account. Do not share this password with anyone.
        </p>

        <?php if ($token_valid): ?>
            <div class="password-rules">
                Password must be at least 8 characters and include a letter, a number, and a symbol.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $success; ?></div>
            </div>
        <?php endif; ?>

        <?php if ($token_valid): ?>
            <form method="post" autocomplete="off">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="form-group">
                    <label>New Password <span style="color:#dc2626;">*</span></label>
                    <div class="input-wrap">
                        <input type="password" name="password" id="password" placeholder="Enter new password" required>
                        <button type="button" class="toggle-password" onclick="toggleVisibility('password', this)" aria-label="Show password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirm Password <span style="color:#dc2626;">*</span></label>
                    <div class="input-wrap">
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-type new password" required>
                        <button type="button" class="toggle-password" onclick="toggleVisibility('confirm_password', this)" aria-label="Show confirm password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-reset">Update Password</button>
            </form>
        <?php endif; ?>

        <div class="back-login">
            <a href="login.php"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
        </div>
    </div>
</div>

<script>
function toggleVisibility(fieldId, buttonElement) {
    const field = document.getElementById(fieldId);
    if (!field) return;

    const icon = buttonElement.querySelector('i');
    if (!icon) return;

    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>
</body>
</html>
