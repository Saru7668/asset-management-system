<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function deny_access_and_redirect($message = 'You do not have permission to access this page.', $redirect = 'index.php') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Access Denied',
                text: <?php echo json_encode($message); ?>,
                confirmButtonText: 'OK',
                confirmButtonColor: '#dc3545',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then(() => {
                window.location.href = <?php echo json_encode($redirect); ?>;
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

function require_login($redirect = 'login.php') {
    if (!isset($_SESSION['UserName'])) {
        header("Location: " . $redirect);
        exit;
    }
}

function require_roles(array $allowed_roles, $message = 'You do not have permission to access this page.', $redirect = 'index.php') {
    require_login();

    $current_role = strtolower($_SESSION['UserRole'] ?? '');
    $normalized_roles = array_map('strtolower', $allowed_roles);

    if (!in_array($current_role, $normalized_roles)) {
        deny_access_and_redirect($message, $redirect);
    }
}
?>
