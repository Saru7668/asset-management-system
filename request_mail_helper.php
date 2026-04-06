<?php

function build_mail_template($title, $headline, $message, $button_text = '', $button_url = '') {
    $button_html = '';

    if ($button_text !== '' && $button_url !== '') {
        $button_html = '
            <div style="margin-top:20px;">
                <a href="' . htmlspecialchars($button_url) . '" 
                   style="display:inline-block;padding:12px 22px;background:#0b2545;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;">
                    ' . htmlspecialchars($button_text) . '
                </a>
            </div>';
    }

    return '
    <div style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;">
        <div style="max-width:680px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;">
            <div style="background:linear-gradient(135deg,#0b2545 0%, #1e3a8a 100%);padding:24px 28px;color:#ffffff;">
                <h2 style="margin:0;font-size:24px;">' . htmlspecialchars($title) . '</h2>
            </div>
            <div style="padding:28px;">
                <h3 style="margin-top:0;color:#0f172a;">' . htmlspecialchars($headline) . '</h3>
                <div style="font-size:15px;line-height:1.7;color:#334155;">' . $message . '</div>
                ' . $button_html . '
            </div>
            <div style="padding:18px 28px;background:#f8fafc;border-top:1px solid #e5e7eb;color:#64748b;font-size:13px;">
                This is an automated mail from SCL AMS. Please do not reply directly.
            </div>
        </div>
    </div>';
}

function send_html_mail_basic($to, $subject, $html_body) {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: SCL AMS <no-reply@sheltechceramics.com>\r\n";

    return mail($to, $subject, $html_body, $headers);
}

function send_role_change_mail($to, $full_name, $new_role, $login_url) {
    $subject = "Your SCL AMS Role Has Been Updated";

    $message = '
        <p>Dear <strong>' . htmlspecialchars($full_name) . '</strong>,</p>
        <p>Your role in <strong>SCL AMS</strong> has been updated.</p>
        <p><strong>New Role:</strong> ' . htmlspecialchars($new_role) . '</p>
        <p>You can now login and access the features assigned to this role.</p>
    ';

    $html = build_mail_template(
        'SCL AMS Notification',
        'Role Updated Successfully',
        $message,
        'Login Now',
        $login_url
    );

    return send_html_mail_basic($to, $subject, $html);
}

function send_request_forward_mail($to, $approver_name, $ref_no, $requester_name, $stage_name, $view_url) {
    $subject = "New Request Pending Your Approval - $ref_no";

    $message = '
        <p>Dear <strong>' . htmlspecialchars($approver_name) . '</strong>,</p>
        <p>A new request is waiting for your action.</p>
        <p><strong>Reference No:</strong> ' . htmlspecialchars($ref_no) . '</p>
        <p><strong>Requester:</strong> ' . htmlspecialchars($requester_name) . '</p>
        <p><strong>Current Stage:</strong> ' . htmlspecialchars($stage_name) . '</p>
        <p>Please review the request and take the necessary action.</p>
    ';

    $html = build_mail_template(
        'Approval Required',
        'A Request Needs Your Review',
        $message,
        'Open Request',
        $view_url
    );

    return send_html_mail_basic($to, $subject, $html);
}

function send_return_mail($to, $recipient_name, $ref_no, $from_stage, $remarks, $view_url) {
    $subject = "Request Returned for Re-check - $ref_no";

    $message = '
        <p>Dear <strong>' . htmlspecialchars($recipient_name) . '</strong>,</p>
        <p>The following request has been returned for further review.</p>
        <p><strong>Reference No:</strong> ' . htmlspecialchars($ref_no) . '</p>
        <p><strong>Returned From:</strong> ' . htmlspecialchars($from_stage) . '</p>
        <p><strong>Remarks:</strong><br>' . nl2br(htmlspecialchars($remarks)) . '</p>
    ';

    $html = build_mail_template(
        'Request Returned',
        'Re-assessment / Re-check Required',
        $message,
        'View Request',
        $view_url
    );

    return send_html_mail_basic($to, $subject, $html);
}
?>