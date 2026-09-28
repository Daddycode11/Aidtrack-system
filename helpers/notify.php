<?php
// helpers/notify.php — SMS & Email notification helper
// Usage: require_once __DIR__ . '/../helpers/notify.php';
//        send_notification($mysqli, $user_id, $subject, $message);

/**
 * Send notification to a user via SMS and/or Email based on system settings.
 * Also inserts an in-app notification.
 *
 * @param mysqli $mysqli   DB connection
 * @param int    $user_id  Target user ID
 * @param string $subject  Notification subject (used for email subject line)
 * @param string $message  Notification body text
 * @return array           ['sms' => bool, 'email' => bool, 'in_app' => bool]
 */
function send_notification($mysqli, $user_id, $subject, $message) {
    $result = ['sms' => false, 'email' => false, 'in_app' => false];

    // Always create in-app notification
    $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
    $notif_msg = $subject . ': ' . $message;
    $stmt->bind_param('is', $user_id, $notif_msg);
    $result['in_app'] = $stmt->execute();
    $stmt->close();

    // Fetch settings
    $settings = [];
    $res = $mysqli->query("SELECT setting_key, setting_value FROM settings");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    // Fetch user phone
    $user = null;
    $stmt = $mysqli->prepare("SELECT phone, name FROM users WHERE id=?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) return $result;

    // --- SMS via Semaphore ---
    if (($settings['sms_enabled'] ?? '0') === '1' && !empty($settings['sms_api_key']) && !empty($user['phone'])) {
        $result['sms'] = send_sms_semaphore(
            $settings['sms_api_key'],
            $user['phone'],
            $message,
            $settings['sms_sender'] ?? 'AIDTRACK'
        );
    }

    // --- Email via PHP mail() ---
    if (($settings['email_enabled'] ?? '0') === '1' && !empty($settings['email_from'])) {
        // Check if user has an email (use phone@aidtrack.local as fallback — skip if no real email column)
        // For now, email is sent to contact_email as a notification log, or user email if the column exists
        $user_email = get_user_email($mysqli, $user_id);
        if ($user_email) {
            $result['email'] = send_email(
                $settings['email_from'],
                $settings['email_from_name'] ?? 'AIDTRACK',
                $user_email,
                $user['name'],
                $subject,
                $message
            );
        }
    }

    return $result;
}

/**
 * Send SMS via Semaphore (Philippines SMS gateway)
 * API docs: https://semaphore.co/docs
 */
function send_sms_semaphore($api_key, $phone, $message, $sender = 'AIDTRACK') {
    $params = [
        'apikey'     => $api_key,
        'number'     => $phone,
        'message'    => $message,
        'sendername' => $sender,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://api.semaphore.co/api/v4/messages',
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log the attempt
    error_log("SMS to $phone: HTTP $http_code — " . substr($response, 0, 200));

    return $http_code >= 200 && $http_code < 300;
}

/**
 * Send email via PHP mail()
 */
function send_email($from_email, $from_name, $to_email, $to_name, $subject, $body) {
    $headers  = "From: $from_name <$from_email>\r\n";
    $headers .= "Reply-To: $from_email\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $html_body = "
    <div style='font-family:Arial,sans-serif;max-width:500px;margin:0 auto;padding:20px;'>
        <div style='background:#e87400;color:#fff;padding:16px 20px;border-radius:8px 8px 0 0;'>
            <strong>AIDTRACK</strong> — Notification
        </div>
        <div style='background:#fff;border:1px solid #e2e8f0;border-top:none;padding:20px;border-radius:0 0 8px 8px;'>
            <p>Hello <strong>" . htmlspecialchars($to_name) . "</strong>,</p>
            <p>" . nl2br(htmlspecialchars($body)) . "</p>
            <hr style='border:none;border-top:1px solid #e2e8f0;margin:16px 0;'>
            <p style='font-size:12px;color:#64748b;'>This is an automated notification from AIDTRACK. Do not reply to this email.</p>
        </div>
    </div>";

    $sent = @mail($to_email, $subject, $html_body, $headers);
    error_log("Email to $to_email: " . ($sent ? 'sent' : 'failed'));
    return $sent;
}

/**
 * Get user email if email column exists
 */
function get_user_email($mysqli, $user_id) {
    // Check if email column exists
    $result = $mysqli->query("SHOW COLUMNS FROM users LIKE 'email'");
    if ($result && $result->num_rows > 0) {
        $stmt = $mysqli->prepare("SELECT email FROM users WHERE id=?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row['email'] ?? null;
    }
    return null;
}

/**
 * Convenience: notify user about application status change
 */
function notify_status_change($mysqli, $user_id, $app_type, $new_status, $app_id = null) {
    $type_label = ucfirst($app_type);
    $status_label = ucfirst($new_status);

    $subjects = [
        'approved'  => "Application Approved",
        'rejected'  => "Application Update",
        'released'  => "Aid Released",
        'pending'   => "Application Received",
    ];

    $messages = [
        'approved'  => "Your $type_label assistance application has been APPROVED. Please wait for the release schedule.",
        'rejected'  => "Your $type_label assistance application has been reviewed. Unfortunately, it was not approved at this time. Please contact MSWDO for details.",
        'released'  => "The aid for your $type_label assistance application has been RELEASED. Please claim it at the designated office.",
        'pending'   => "Your $type_label assistance application has been received and is now under review.",
    ];

    $subject = $subjects[$new_status] ?? "Application Update";
    $message = $messages[$new_status] ?? "Your $type_label application status has been updated to: $status_label.";

    if ($app_id) {
        $message .= " (Reference: APP-$app_id)";
    }

    return send_notification($mysqli, $user_id, $subject, $message);
}
