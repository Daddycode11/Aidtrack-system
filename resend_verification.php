<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/helpers/mailer.php';

$submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = true;
    $email = trim($_POST['email'] ?? '');
    $now = time();
    $lastRequest = (int)($_SESSION['verification_resend_at'] ?? 0);
    $csrfValid = csrf_verify();

    if ($csrfValid && $now - $lastRequest >= 60) {
        $_SESSION['verification_resend_at'] = $now;
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $mysqli->prepare("SELECT id, email, name FROM users
                WHERE email = ? AND role IN ('admin', 'super_admin') AND email_verified = 0
                LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($user) {
                    send_verification_email($mysqli, (int)$user['id'], $user['email'], $user['name']);
                }
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
<title>Resend Verification — AIDTRACK</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main class="container">
    <h1>Resend admin verification</h1>
    <?php if ($submitted): ?>
    <p>If an eligible admin account exists for that email, a verification message will be sent shortly.</p>
    <?php else: ?>
    <p>Enter the email address for your admin account.</p>
    <form method="post">
        <?= csrf_field() ?>
        <label for="email">Email address</label><br>
        <input type="email" id="email" name="email" autocomplete="email" required><br><br>
        <button type="submit">Request verification link</button>
    </form>
    <?php endif; ?>
    <p><a href="login.php">Back to sign in</a></p>
</main>
</body>
</html>