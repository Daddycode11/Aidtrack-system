<?php
require_once __DIR__ . '/helpers.php';

$verified = false;
$token = $_GET['token'] ?? '';
if (is_string($token) && preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
    $tokenHash = hash('sha256', strtolower($token));
    $stmt = $mysqli->prepare("SELECT id FROM users
        WHERE verification_token_hash = ?
          AND verification_expires > NOW()
          AND email_verified = 0
          AND role IN ('admin', 'super_admin')
        LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            $update = $mysqli->prepare("UPDATE users
                SET email_verified = 1, verification_token_hash = NULL, verification_expires = NULL
                WHERE id = ? AND verification_token_hash = ? AND verification_expires > NOW()");
            if ($update) {
                $update->bind_param('is', $user['id'], $tokenHash);
                $verified = $update->execute() && $update->affected_rows === 1;
                $update->close();
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
<title>Email Verification — AIDTRACK</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main class="container">
    <h1><?= $verified ? 'Email verified' : 'Verification link unavailable' ?></h1>
    <p><?= $verified
        ? 'Your admin email is verified. You can now sign in using your email address.'
        : 'This verification link is invalid, expired, or has already been used. Request a new link and try again.' ?></p>
    <p><a href="login.php">Back to sign in</a></p>
    <?php if (!$verified): ?><p><a href="resend_verification.php">Request a new verification link</a></p><?php endif; ?>
</main>
</body>
</html>