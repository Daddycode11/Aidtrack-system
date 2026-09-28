<?php
// client/profile.php — Account settings
require_once __DIR__ . '/../helpers.php';
require_client();

$uid  = $_SESSION['user']['id'];
$toast = '';
$errors = [];

// Fetch current user
$stmt = $mysqli->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');

    if (!$name || !$phone) { $errors[] = 'Name and phone are required.'; }
    else {
        // Check phone uniqueness
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE phone=? AND id!=?");
        $stmt->bind_param('si', $phone, $uid);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) { $errors[] = 'Phone number is already taken.'; }
        $stmt->close();
    }

    if (!$errors) {
        $stmt = $mysqli->prepare("UPDATE users SET name=?, phone=?, barangay=? WHERE id=?");
        $stmt->bind_param('sssi', $name, $phone, $barangay, $uid);
        $stmt->execute();
        $stmt->close();

        // Update session
        $_SESSION['user']['name']     = $name;
        $_SESSION['user']['phone']    = $phone;
        $_SESSION['user']['barangay'] = $barangay;
        $user['name'] = $name; $user['phone'] = $phone; $user['barangay'] = $barangay;
        $toast = 'profile';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'password') {
    $current = $_POST['current_password'] ?? '';
    $newpass  = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password_hash'])) { $errors[] = 'Current password is incorrect.'; }
    elseif (strlen($newpass) < 8) { $errors[] = 'New password must be at least 8 characters.'; }
    elseif ($newpass !== $confirm) { $errors[] = 'New passwords do not match.'; }
    else {
        $hash = password_hash($newpass, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("UPDATE users SET password_hash=? WHERE id=?");
        $stmt->bind_param('si', $hash, $uid);
        $stmt->execute();
        $stmt->close();
        $toast = 'password';
    }
}

// Barangay list
$barangays = ['Araw ng Bayan','Bagong Silang','Batangas','Bulihan','Calintaan','Concepcion','Dao','Malaking Ilog','Maliit na Ilog','Nag-iba','Paclolo','Poblacion I','Poblacion II','Poblacion III','Poblacion IV','Sabang','San Isidro','Tanauan'];

// Partials
$active_page   = 'profile';
$page_title    = 'My Profile';
$page_subtitle = 'Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile — AIDTRACK</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/client.css">
<script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<?php if ($toast === 'profile'): ?>
<div class="toast toast-success"><i data-lucide="check-circle" style="width:16px;height:16px"></i> Profile updated successfully.</div>
<?php elseif ($toast === 'password'): ?>
<div class="toast toast-success"><i data-lucide="check-circle" style="width:16px;height:16px"></i> Password changed successfully.</div>
<?php endif; ?>

<main class="main">

    <div class="page-header">
        <div class="page-header-top">
            <div>
                <div class="page-title">My Profile</div>
                <div class="page-sub">Manage your account information and security settings.</div>
            </div>
        </div>
    </div>

    <?php if ($errors): ?>
    <div style="background:var(--red-lt);border:1px solid rgba(220,38,38,.15);border-radius:10px;padding:12px 16px;margin-bottom:16px;">
        <?php foreach ($errors as $e): ?>
        <div style="font-size:.8rem;color:var(--red);font-weight:600;display:flex;align-items:center;gap:6px;margin-bottom:3px;">
            <i data-lucide="alert-circle" style="width:13px;height:13px;flex-shrink:0"></i> <?= htmlspecialchars($e) ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="grid-2">
        <!-- Profile Info -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon cti-orange"><i data-lucide="user" style="width:14px;height:14px"></i></div>
                    Personal Information
                </div>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="form" value="profile">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Barangay</label>
                        <select name="barangay">
                            <option value="">-- Select Barangay --</option>
                            <?php foreach ($barangays as $b): ?>
                            <option value="<?= htmlspecialchars($b) ?>" <?= $user['barangay']===$b?'selected':'' ?>><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;padding-top:8px;border-top:1px solid var(--border);margin-top:8px;">
                        <div style="font-size:.7rem;color:var(--muted);">Member since <?= date('M d, Y', strtotime($user['created_at'])) ?></div>
                        <div style="flex:1"></div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i data-lucide="save" style="width:13px;height:13px"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon cti-red"><i data-lucide="lock" style="width:14px;height:14px"></i></div>
                    Change Password
                </div>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="form" value="password">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="password-meter" minlength="8" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i data-lucide="shield-check" style="width:13px;height:13px"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>

</main>
<script src="partials/client.js"></script>
<script src="../assets/js/password-strength.js"></script>
</body>
</html>
