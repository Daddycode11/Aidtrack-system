<?php
// forgot_password.php — Password reset via phone number
require_once __DIR__ . '/config.php';
session_start();

$step    = 'phone'; // phone -> verify -> reset
$message = '';
$error   = '';

// Step 1: Enter phone
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step'])) {

    if ($_POST['step'] === 'phone') {
        $phone = trim($_POST['phone'] ?? '');
        if (!$phone) { $error = 'Please enter your phone number.'; }
        else {
            $stmt = $mysqli->prepare("SELECT id, name FROM users WHERE phone=? LIMIT 1");
            $stmt->bind_param('s', $phone);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user) {
                $error = 'No account found with that phone number.';
            } else {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $stmt = $mysqli->prepare("UPDATE users SET reset_token=?, reset_expires=? WHERE id=?");
                $stmt->bind_param('ssi', $token, $expires, $user['id']);
                $stmt->execute();
                $stmt->close();

                // Store in session for verification
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_token']   = $token;
                $_SESSION['reset_name']    = $user['name'];
                $step = 'verify';
            }
        }
    }

    elseif ($_POST['step'] === 'verify') {
        $code = trim($_POST['code'] ?? '');
        // Simple verification: last 6 chars of token
        $expected = substr($_SESSION['reset_token'] ?? '', -6);
        if (strtolower($code) === strtolower($expected)) {
            $step = 'reset';
        } else {
            $error = 'Invalid verification code. Hint: use the last 6 characters of your reset token.';
            $step = 'verify';
        }
    }

    elseif ($_POST['step'] === 'reset') {
        $pass    = $_POST['password'] ?? '';
        $confirm = $_POST['confirm'] ?? '';
        $uid     = $_SESSION['reset_user_id'] ?? 0;

        if (strlen($pass) < 8) { $error = 'Password must be at least 8 characters.'; $step = 'reset'; }
        elseif ($pass !== $confirm) { $error = 'Passwords do not match.'; $step = 'reset'; }
        elseif (!$uid) { $error = 'Session expired. Please start over.'; }
        else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("UPDATE users SET password_hash=?, reset_token=NULL, reset_expires=NULL WHERE id=?");
            $stmt->bind_param('si', $hash, $uid);
            $stmt->execute();
            $stmt->close();

            unset($_SESSION['reset_user_id'], $_SESSION['reset_token'], $_SESSION['reset_name']);
            $message = 'Password reset successful! You can now log in.';
            $step = 'done';
        }
    }
}

// Restore step from session if needed
if (isset($_SESSION['reset_token']) && $step === 'phone' && !$error) {
    $step = 'verify';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — AIDTRACK</title>
<link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
:root { --brand:#e87400; --brand-dk:#cc6600; --brand-lt:#fff7ed; --navy:#1e293b; --slate:#334155; --muted:#64748b; --border:#e2e8f0; --bg:#f8fafc; --white:#fff; --green:#16a34a; --red:#dc2626; }
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
.reset-card { background:var(--white); border:1px solid var(--border); border-radius:14px; box-shadow:0 8px 32px rgba(0,0,0,.08); width:100%; max-width:420px; padding:36px 32px; }
.reset-logo { text-align:center; margin-bottom:24px; }
.reset-logo img { height:36px; }
.reset-logo h2 { font-size:1.1rem; font-weight:800; color:var(--navy); margin-top:10px; }
.reset-logo p { font-size:.8rem; color:var(--muted); margin-top:4px; }
.step-indicator { display:flex; gap:6px; justify-content:center; margin-bottom:24px; }
.step-dot { width:8px; height:8px; border-radius:50%; background:var(--border); }
.step-dot.active { background:var(--brand); width:24px; border-radius:99px; }
.step-dot.done { background:var(--green); }
.form-group { margin-bottom:16px; }
.form-group label { display:block; font-size:.76rem; font-weight:700; color:var(--slate); margin-bottom:5px; }
.form-group input { width:100%; font-family:inherit; font-size:.88rem; color:var(--navy); background:var(--bg); border:1.5px solid var(--border); border-radius:8px; padding:10px 14px; outline:none; transition:all .15s; }
.form-group input:focus { border-color:var(--brand); background:var(--white); box-shadow:0 0 0 3px rgba(232,116,0,.08); }
.btn { width:100%; display:flex; align-items:center; justify-content:center; gap:8px; font-family:inherit; font-weight:700; font-size:.86rem; border-radius:8px; padding:11px; border:none; cursor:pointer; transition:all .15s; }
.btn-primary { background:var(--brand); color:#fff; }
.btn-primary:hover { background:var(--brand-dk); }
.btn-outline { background:var(--white); color:var(--slate); border:1.5px solid var(--border); margin-top:8px; }
.btn-outline:hover { border-color:var(--brand); color:var(--brand); }
.error-msg { background:#fef2f2; border:1px solid rgba(220,38,38,.15); border-radius:8px; padding:10px 14px; font-size:.8rem; color:var(--red); font-weight:600; margin-bottom:16px; display:flex; align-items:center; gap:6px; }
.success-msg { background:#f0fdf4; border:1px solid rgba(22,163,74,.15); border-radius:8px; padding:14px; font-size:.84rem; color:var(--green); font-weight:600; text-align:center; margin-bottom:16px; }
.hint-box { background:var(--brand-lt); border:1px solid rgba(232,116,0,.12); border-radius:8px; padding:12px 14px; font-size:.76rem; color:var(--brand); margin-bottom:16px; line-height:1.5; }
.back-link { display:block; text-align:center; margin-top:16px; font-size:.8rem; color:var(--muted); }
.back-link:hover { color:var(--brand); }
</style>
</head>
<body>

<div class="reset-card">
    <div class="reset-logo">
        <img src="assets/images/AIDTRACK-logo.png" alt="AIDTRACK">
        <h2>Reset Password</h2>
        <p><?= match($step) { 'phone'=>'Enter your registered phone number', 'verify'=>'Verify your identity', 'reset'=>'Create a new password', 'done'=>'All done!', default=>'' } ?></p>
    </div>

    <div class="step-indicator">
        <div class="step-dot <?= $step==='phone'?'active':($step!=='phone'?'done':'') ?>"></div>
        <div class="step-dot <?= $step==='verify'?'active':($step==='reset'||$step==='done'?'done':'') ?>"></div>
        <div class="step-dot <?= $step==='reset'?'active':($step==='done'?'done':'') ?>"></div>
    </div>

    <?php if ($error): ?>
    <div class="error-msg"><i data-lucide="alert-circle" style="width:14px;height:14px;flex-shrink:0"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($step === 'phone'): ?>
    <form method="POST">
        <input type="hidden" name="step" value="phone">
        <div class="form-group">
            <label>Phone Number</label>
            <input type="text" name="phone" placeholder="09XX XXX XXXX" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="arrow-right" style="width:15px;height:15px"></i> Continue
        </button>
    </form>

    <?php elseif ($step === 'verify'): ?>
    <div class="hint-box">
        Hello <strong><?= htmlspecialchars($_SESSION['reset_name'] ?? '') ?></strong>! For demo purposes, your verification code is: <strong><?= substr($_SESSION['reset_token'] ?? '', -6) ?></strong>
    </div>
    <form method="POST">
        <input type="hidden" name="step" value="verify">
        <div class="form-group">
            <label>Verification Code</label>
            <input type="text" name="code" placeholder="Enter 6-digit code" maxlength="6" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="shield-check" style="width:15px;height:15px"></i> Verify
        </button>
    </form>

    <?php elseif ($step === 'reset'): ?>
    <form method="POST">
        <input type="hidden" name="step" value="reset">
        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="password" class="password-meter" placeholder="At least 8 characters" minlength="8" required>
        </div>
        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm" placeholder="Repeat password" required>
        </div>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="lock" style="width:15px;height:15px"></i> Reset Password
        </button>
    </form>

    <?php elseif ($step === 'done'): ?>
    <div class="success-msg">
        <i data-lucide="check-circle" style="width:16px;height:16px;display:inline;vertical-align:middle"></i>
        <?= htmlspecialchars($message) ?>
    </div>
    <a href="login.php" class="btn btn-primary">
        <i data-lucide="log-in" style="width:15px;height:15px"></i> Go to Login
    </a>
    <?php endif; ?>

    <a href="login.php" class="back-link">Back to Login</a>
</div>

<script>lucide.createIcons();</script>
<script src="assets/js/password-strength.js"></script>
</body>
</html>
