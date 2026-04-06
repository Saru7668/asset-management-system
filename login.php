<?php
session_start();
require_once('db.php');
require_once('header.php'); // <--- Header.php ???? ??? ??? ??????? ??????? ????

// Already logged in?
if (isset($_SESSION['UserName']) && $_SESSION['UserName'] != "") {
    $role = $_SESSION['UserRole'] ?? 'user';

    if (!empty($_SESSION['force_profile_update'])) {
        // mandatory profile update incomplete
        header("Location: profile.php");
        exit;
    } else {
        // profile complete, redirect based on role
        if ($role === 'admin' || $role === 'SuperAdmin') {
            header("Location: admin_dashboard.php");
        } else {
            header("Location: profile.php");
        }
        exit;
    }
}

$error = "";
$returl = isset($_REQUEST['returl']) ? $_REQUEST['returl'] : "";
$secret_salt = "mrbs_secure_salt_2026";

// ?? Login Success Flag
$login_success = false;
$redirect_url = "";

// ========================================
// ?? HELPER FUNCTION: GET REAL IP ADDRESS
// ========================================
function getUserIP() {
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]; 
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return trim($ip);
}

// ========================================
// ??? HELPER FUNCTION: GET OS, BROWSER & DEVICE TYPE
// ========================================
function getDeviceInfo() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $os_platform  = "Unknown OS";
    $browser_name = "Unknown Browser";
    $device_type  = "Desktop / Laptop"; 

    $tablet_browser = 0;
    $mobile_browser = 0;

    if (preg_match('/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', strtolower($user_agent))) {
        $tablet_browser++;
    }
    if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', strtolower($user_agent))) {
        $mobile_browser++;
    }

    if ($tablet_browser > 0) {
        $device_type = 'Tablet';
    } else if ($mobile_browser > 0) {
        $device_type = 'Mobile Device';
    }

    $os_array = array(
        '/windows nt 11/i'      =>  'Windows 11',
        '/windows nt 10/i'      =>  'Windows 10',
        '/windows nt 6.3/i'     =>  'Windows 8.1',
        '/windows nt 6.2/i'     =>  'Windows 8',
        '/windows nt 6.1/i'     =>  'Windows 7',
        '/macintosh|mac os x/i' =>  'Mac OS X',
        '/mac_powerpc/i'        =>  'Mac OS 9',
        '/linux/i'              =>  'Linux',
        '/ubuntu/i'             =>  'Ubuntu',
        '/iphone/i'             =>  'iPhone',
        '/ipod/i'               =>  'iPod',
        '/ipad/i'               =>  'iPad',
        '/android/i'            =>  'Android',
        '/blackberry/i'         =>  'BlackBerry',
        '/webos/i'              =>  'Mobile OS'
    );

    foreach ($os_array as $regex => $value) {
        if (preg_match($regex, $user_agent)) {
            $os_platform = $value;
            break;
        }
    }

    $browser_array = array(
        '/edge/i'       => 'Edge',
        '/edg/i'        => 'Edge',
        '/chrome/i'     => 'Chrome',
        '/safari/i'     => 'Safari',
        '/firefox/i'    => 'Firefox',
        '/opera/i'      => 'Opera',
        '/netscape/i'   => 'Netscape',
        '/maxthon/i'    => 'Maxthon',
        '/konqueror/i'  => 'Konqueror',
        '/mobile/i'     => 'Mobile Browser'
    );

    foreach ($browser_array as $regex => $value) {
        if (preg_match($regex, $user_agent)) {
            $browser_name = $value;
            break;
        }
    }

    return array(
        'os' => $os_platform, 
        'browser' => $browser_name,
        'device' => $device_type
    );
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_user = mysqli_real_escape_string($conn, trim($_POST['username']));
    $input_pass = $_POST['password'];
    $captcha_input = trim($_POST['captcha']);
    $captcha_hash_check = $_POST['captcha_hash'];

    $user_answer_hash = md5($captcha_input . $secret_salt);

    if (empty($captcha_input) || $user_answer_hash !== $captcha_hash_check) {
        $error = "? Incorrect Security Answer! Please check your math.";
    }
    elseif (empty($input_user) || empty($input_pass)) {
        $error = "?? Please enter username/email and password.";
    }
    else {
        $user_exists_sql = "SELECT COUNT(*) as count FROM users WHERE username = ? OR email = ?";
        $user_exists_stmt = mysqli_prepare($conn, $user_exists_sql);
        mysqli_stmt_bind_param($user_exists_stmt, "ss", $input_user, $input_user);
        mysqli_stmt_execute($user_exists_stmt);
        $exists_result = mysqli_stmt_get_result($user_exists_stmt);
        $exists_row = mysqli_fetch_assoc($exists_result);
        
        if ($exists_row['count'] == 0) {
            $error = "? This Username/Email does not exist!";
        } else {
              // ?? Updated to match database columns
              $sql = "SELECT username, password, department, user_role, confirm_token,
                             title, full_name, phone, email, designation, nid_company_id, last_login_ip
                      FROM users 
                      WHERE (username = ? OR email = ?) AND confirm_token IS NULL 
                      LIMIT 1";
              $stmt = mysqli_prepare($conn, $sql);
      
              if ($stmt) {
                  mysqli_stmt_bind_param($stmt, "ss", $input_user, $input_user);
                  mysqli_stmt_execute($stmt);
                  $result = mysqli_stmt_get_result($stmt);
      
                  if ($row = mysqli_fetch_assoc($result)) {
      
                      if (password_verify($input_pass, $row['password'])) {
      
                          $user_title  = trim((string)($row['title']       ?? '')); 
                          $full_name   = trim((string)($row['full_name']   ?? ''));
                          $phone       = trim((string)($row['phone']       ?? ''));
                          $email_db    = trim((string)($row['email']       ?? ''));
                          $designation = trim((string)($row['designation'] ?? ''));
                          $user_dept   = trim((string)($row['department']  ?? ''));
                          // ?? Fix: using correct nid_company_id column
                          $nid_id      = trim((string)($row['nid_company_id'] ?? ''));
                          $last_ip     = trim((string)($row['last_login_ip'] ?? '')); 
      
                          $profile_incomplete =
                              ($full_name   === '') ||
                              ($phone       === '') ||
                              ($email_db    === '') ||
                              ($nid_id      === '');
      
                          $_SESSION['UserName']   = $row['username'];
                          $_SESSION['Department'] = $user_dept;
                          $_SESSION['UserRole']   = $row['user_role'];
                          $_SESSION['login_time'] = time();
                          $_SESSION['force_profile_update'] = $profile_incomplete;
      
                          session_regenerate_id(true);
      
                          $login_success = true;
                          
                          if ($profile_incomplete) {
                              $_SESSION['msg'] = "
                                  <div class='alert alert-warning alert-dismissible fade show' role='alert'>
                                      <strong>Profile Incomplete!</strong> Please complete your profile before using the system.
                                      <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                                  </div>
                              ";
                              $redirect_url = "profile.php";
                          } else {
                              if ($row['user_role'] == 'admin' || $row['user_role'] == 'SuperAdmin') {
                                  $redirect_url = "admin_dashboard.php";
                              } else {
                                  $redirect_url = "index.php";
                              }
                          }

                          // ========================================
                          // ?? SEND LOGIN SECURITY ALERT EMAIL (Updated)
                          // ========================================
                          date_default_timezone_set('Asia/Dhaka');
                          $current_user_ip = getUserIP();
                          
                          if (!empty($email_db) && $current_user_ip !== $last_ip) {
                              
                              $device_data = getDeviceInfo();
                              $os_name = $device_data['os'];
                              $browser_name = $device_data['browser'];
                              $device_type = $device_data['device'];
                              $login_time = date('d M Y, h:i A');
                              
                              $display_name = !empty($full_name) ? $full_name : $row['username'];
                              if (!empty($user_title)) {
                                  $display_name = $user_title . " " . $display_name;
                              }

                              $subject = "Security Alert: New Device/Location Login Detected";
                              
                              $headers  = "MIME-Version: 1.0\r\n";
                              $headers .= "Content-type:text/html;charset=UTF-8\r\n";
                              // ?? Updated From Address exactly as requested
                              $headers .= "From: SCL Asset Management <noreply@asset-management.com>\r\n";

                              $mail_body = "
                              <!DOCTYPE html>
                              <html>
                              <head>
                                  <style>
                                      body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
                                      .email-container { max-width: 600px; margin: 30px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; }
                                      .email-header { background-color: #2f6b96; color: #ffffff; padding: 20px; text-align: center; }
                                      .email-body { padding: 30px; color: #333333; line-height: 1.6; }
                                      .info-box { background-color: #f8f9fa; border-left: 4px solid #f0ad4e; padding: 20px; margin: 25px 0; border-radius: 4px; }
                                      .footer { border-top: 1px solid #eeeeee; margin-top: 30px; padding-top: 20px; font-size: 12px; color: #999999; text-align: center; }
                                  </style>
                              </head>
                              <body>
                                  <div class='email-container'>
                                      <div class='email-header'><h2>New Login Detected</h2></div>
                                      <div class='email-body'>
                                          <p>Dear <strong>{$display_name}</strong>,</p>
                                          <p>We noticed a successful login to your SCL Asset Management account from a new IP Address.</p>
                                          
                                          <div class='info-box'>
                                              <p><strong>Time:</strong> {$login_time}</p>
                                              <p><strong>IP Address:</strong> {$current_user_ip}</p>
                                              <p><strong>Device:</strong> {$device_type}</p>
                                              <p><strong>OS:</strong> {$os_name}</p>
                                              <p><strong>Browser:</strong> {$browser_name}</p>
                                          </div>
                                          
                                          <p>If this was you, you can safely ignore this email.</p>
                                          <div class='footer'>Automated security email from SCL Asset Management.</div>
                                      </div>
                                  </div>
                              </body>
                              </html>";

                              @mail($email_db, $subject, $mail_body, $headers);
                              
                              $update_ip_sql = "UPDATE users SET last_login_ip = ? WHERE username = ?";
                              $ip_stmt = mysqli_prepare($conn, $update_ip_sql);
                              if($ip_stmt){
                                  mysqli_stmt_bind_param($ip_stmt, "ss", $current_user_ip, $row['username']);
                                  mysqli_stmt_execute($ip_stmt);
                                  mysqli_stmt_close($ip_stmt);
                              }
                          }
                          // ========================================
      
                      } else {
                          $error = "? Invalid password.";
                      }
      
                  } else {
                      $error = "?? Account not activated or mismatch.";
                  }
      
                  mysqli_stmt_close($stmt);
      
              } else {
                  $error = "?? Database error.";
              }
        }
        mysqli_stmt_close($user_exists_stmt);
    }
}

$num1 = rand(1, 9);
$num2 = rand(1, 9);
$sum = $num1 + $num2;
$correct_hash = md5($sum . $secret_salt);
?>

<!-- 
   ?????? header.php ? HTML <html>, <head> ? <body> ????? ???? ??? ???, 
   ??? ????? ???? ??? ???? ????? ????? ???, ???? ?????? ?????????? ??????
-->
<style>
    html, body {
        height: 100%; margin: 0; padding: 0; width: 100%;
        font-family: 'Segoe UI', sans-serif;
        background-image: url('background.jpg');
        background-size: cover; background-position: center;
        display: flex;
        flex-direction: column; 
    }
    
    body::before { content: ""; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: -1; }

    .main-wrapper {
        flex: 1; 
        display: flex;
        justify-content: center; 
        align-items: center;     
        width: 100%;
    }

    .login-container { 
        background: rgba(255, 255, 255, 0.95); 
        padding: 20px 25px; 
        border-radius: 10px; 
        box-shadow: 0 8px 20px rgba(0,0,0,0.3); 
        width: 100%; max-width: 400px; 
        text-align: center; 
        transition: opacity 0.5s ease; 
    }

    .fade-in { animation: fadeIn 0.8s ease-out; }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .logo_login-img { width: 120px; height: 120px; object-fit: contain; margin-bottom: 20px; }
    .login-container h2 { color: #2f6b96; margin-bottom: 18px; font-size: 20px; margin-top: 0; font-weight: bold; }
    .form-group { margin-bottom: 15px; text-align: left; position: relative; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
    .form-group input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 16px; }
    .toggle-password { position: absolute; right: 12px; top: 38px; cursor: pointer; color: #999; font-size: 16px; }
    
    .btn-login { width: 100%; padding: 12px; background: #2f6b96; color: white; border: none; border-radius: 5px; font-size: 18px; font-weight: bold; cursor: pointer; transition: all 0.3s; }
    .btn-login:hover { background: #1a4f75; transform: scale(1.02); }
    .btn-login:disabled { background-color: #cccccc !important; cursor: not-allowed; opacity: 0.6; transform: none; }
    
    .error-msg { background: #ffdddd; color: darkred; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; border: 1px solid red; font-weight: 500; }

    .scl-footer {
        background: transparent !important;
        color: yellow !important;
        font-weight: 600;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.9);
        text-align: center;
        padding: 15px 0;
        width: 100%;
    }

    #success-overlay {
        position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
        background-color: #ffffff; 
        z-index: 999999; display: flex; flex-direction: column; justify-content: center; align-items: center;
        opacity: 0; visibility: hidden; transition: opacity 0.5s ease-in-out;
    }
    #success-overlay.active { opacity: 1; visibility: visible; }
    .success-icon { font-size: 70px; color: #28a745; margin-bottom: 20px; animation: popIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
    .success-text { font-size: 24px; font-weight: bold; color: #1a2a3a; margin-bottom: 10px; }
    .success-subtext { font-size: 15px; color: #6c757d; margin-bottom: 30px; }
    .loading-spinner { width: 40px; height: 40px; border: 4px solid rgba(47, 107, 150, 0.2); border-top: 4px solid #2f6b96; border-radius: 50%; animation: spin 1s linear infinite; }
    @keyframes popIn { 0% { transform: scale(0); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>

<div id="success-overlay">
    <i class="fas fa-check-circle success-icon"></i>
    <div class="success-text">Login Successful!</div>
    <div class="success-subtext">Setting up your workspace...</div>
    <div class="loading-spinner"></div>
</div>

<div class="main-wrapper" id="login-wrapper">
    <div class="login-container fade-in">
        <img src="logo_login.png" alt="Logo" class="logo_login-img">
        <h2>SCL ASSET MANAGEMENT SYSTEM</h2>
        
        <?php if ($error): ?> 
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div> 
        <?php endif; ?>

        <form method="post" action="login.php">
            <input type="hidden" name="returl" value="<?php echo htmlspecialchars($returl); ?>">
            
            <div class="form-group">
                <label>Username / Email</label>
                <input type="text" name="username" id="username" placeholder="Enter username or email" required autofocus>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" id="password" placeholder="Enter password" required>
                <i class="fas fa-eye toggle-password" onclick="toggleVisibility('password', this)"></i>
                <div style="text-align: right; margin-top: 5px;">
                    <a href="forgot_password.php" style="font-size: 13px; color: #2f6b96; text-decoration: none; font-weight: bold;">Forgot Password?</a>
                </div>
            </div>

            <div class="form-group">
                <label>Security Question: 
                    <span style="color:red; font-weight:bold;"><?php echo "$num1 + $num2"; ?> = ?</span>
                </label>
                <input type="number" name="captcha" id="captcha" placeholder="Enter result" required autocomplete="off">
                <input type="hidden" id="realAnswer" value="<?php echo $sum; ?>">
                <input type="hidden" name="captcha_hash" value="<?php echo $correct_hash; ?>">
                <small style="font-size: 10.5px; color: #555;">Provide correct answer to enable Login button</small>
            </div>

            <button type="submit" class="btn-login" id="submitBtn" disabled>
                <i class="fas fa-sign-in-alt me-2"></i> Login
            </button>
        </form>

        <div class="footer-links" style="margin-top: 20px;">
            <p>Don't have an account? 
                <a href="register.php" style="color: #2f6b96; font-weight: bold; text-decoration: none;">Register Here</a>
            </p>
        </div>
    </div>
</div>

<!-- Footer -->
<div class="scl-footer">
    © <?php echo date("Y"); ?> All Rights Reserved by SCL ICT Team. | A Product of Sheltech Ceramics ICT Team.
</div>

<!-- JS Scripts -->
<script>
    function toggleVisibility(fieldId, iconElement) {
        const field = document.getElementById(fieldId);
        if (field.type === "password") {
            field.type = "text";
            iconElement.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            field.type = "password";
            iconElement.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const captchaInput = document.getElementById('captcha');
    const realAnswer = document.getElementById('realAnswer').value;
    const submitBtn = document.getElementById('submitBtn');

    function checkForm() {
        if (usernameInput.value.trim() !== "" &&
            passwordInput.value.trim() !== "" &&
            captchaInput.value.trim() === realAnswer) {
            submitBtn.disabled = false;
            submitBtn.style.opacity = "1";
        } else {
            submitBtn.disabled = true;
            submitBtn.style.opacity = "0.6";
        }
    }

    [usernameInput, passwordInput, captchaInput].forEach(input => {
        input.addEventListener('input', checkForm);
    });
</script>

<?php if ($login_success): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById('login-wrapper').style.opacity = "0";
        const overlay = document.getElementById('success-overlay');
        overlay.classList.add('active');

        const btn = document.getElementById('submitBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Authenticating...';
        btn.disabled = true;

        setTimeout(function() {
            window.location.href = "<?php echo $redirect_url; ?>";
        }, 2000);
    });
</script>
<?php endif; ?>

</body>
</html>
