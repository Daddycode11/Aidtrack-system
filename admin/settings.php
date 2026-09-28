<?php
// admin/settings.php
require_once __DIR__ . '/../helpers.php';
require_admin();

// --- Create settings table if not exists ---
$mysqli->query("
    CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

// --- Insert defaults if table is empty ---
$count = (int)$mysqli->query("SELECT COUNT(*) FROM settings")->fetch_row()[0];
if ($count === 0) {
    $defaults = [
        'app_name'      => 'AIDTRACK',
        'org_name'      => 'Municipal Social Welfare and Development Office',
        'contact_phone' => '',
        'contact_email' => '',
        'max_file_size' => '5',
        'allowed_types' => 'medical,burial',
        'sms_enabled'   => '0',
        'sms_api_key'   => '',
        'sms_sender'    => 'AIDTRACK',
        'email_enabled' => '0',
        'email_from'    => 'noreply@aidtrack.local',
        'email_from_name' => 'AIDTRACK System',
    ];
    $ins = $mysqli->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaults as $key => $val) {
        $ins->bind_param('ss', $key, $val);
        $ins->execute();
    }
    $ins->close();
}

// --- Handle POST update ---
$success = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['app_name', 'org_name', 'contact_phone', 'contact_email', 'max_file_size', 'allowed_types',
                'sms_enabled', 'sms_api_key', 'sms_sender', 'email_enabled', 'email_from', 'email_from_name'];
    $upd = $mysqli->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");

    foreach ($fields as $field) {
        $value = trim($_POST[$field] ?? '');
        $upd->bind_param('ss', $value, $field);
        if (!$upd->execute()) {
            $errors[] = "Failed to update: $field";
        }
    }
    $upd->close();

    if (empty($errors)) {
        $success = true;
    }
}

// --- Fetch all settings ---
$settings = [];
$result = $mysqli->query("SELECT setting_key, setting_value FROM settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Partials
$active_page   = 'settings';
$page_title    = 'System Settings';
$page_subtitle = 'Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings — AIDTRACK Admin</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/admin.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
/* ── SETTINGS PAGE SPECIFIC ── */
.settings-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}
.settings-full {
    margin-bottom: 16px;
}
.form-group {
    margin-bottom: 14px;
}
.form-group label {
    display: block;
    font-size: .76rem;
    font-weight: 700;
    color: var(--navy);
    margin-bottom: 6px;
}
.form-group label .label-hint {
    font-weight: 500;
    color: var(--muted);
}
.form-group input,
.form-group textarea {
    width: 100%;
    font-family: inherit;
    font-size: .82rem;
    color: var(--navy);
    background: var(--white);
    border: 1.5px solid var(--border);
    border-radius: 8px;
    padding: 9px 13px;
    outline: none;
    transition: border-color .2s;
    box-sizing: border-box;
}
.form-group input:focus,
.form-group textarea:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(26, 86, 219, .08);
}
.form-group .input-help {
    font-size: .7rem;
    color: var(--muted);
    margin-top: 4px;
}
.form-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-top: 6px;
}

.toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 999;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 18px;
    border-radius: 10px;
    font-size: .83rem;
    font-weight: 600;
    box-shadow: 0 8px 28px rgba(0,0,0,.15);
    animation: toastIn .3s ease, toastOut .4s ease 3s forwards;
}
.toast-success {
    background: var(--green-lt);
    color: var(--green);
    border: 1px solid rgba(22, 163, 74, .2);
}
.toast-error {
    background: var(--red-lt);
    color: var(--red);
    border: 1px solid rgba(220, 38, 38, .2);
}
@keyframes toastIn  { from { opacity:0; transform:translateY(10px) } to { opacity:1; transform:none } }
@keyframes toastOut { from { opacity:1 } to { opacity:0; pointer-events:none } }

.setting-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.si-blue   { background: var(--brand-lt); color: var(--brand); }
.si-green  { background: var(--green-lt); color: var(--green); }
.si-purple { background: #f5f3ff; color: #7c3aed; }

@media(max-width:860px) {
    .settings-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<!-- Toast Notifications -->
<?php if ($success): ?>
<div class="toast toast-success">
    <i data-lucide="check-circle" style="width:15px;height:15px"></i>
    Settings saved successfully.
</div>
<?php elseif ($errors): ?>
<div class="toast toast-error">
    <i data-lucide="alert-circle" style="width:15px;height:15px"></i>
    <?= htmlspecialchars($errors[0]) ?>
</div>
<?php endif; ?>

<main class="main">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-top">
            <div>
                <div class="page-title">System Settings</div>
                <div class="page-sub">Configure application name, organization details, and system preferences.</div>
            </div>
            <div class="page-actions">
                <a href="dashboard.php" class="btn btn-outline btn-sm">
                    <i data-lucide="arrow-left" style="width:13px;height:13px"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <form method="POST">

        <!-- General Settings + Contact Info -->
        <div class="settings-grid">

            <!-- General Settings -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div class="card-title-icon cti-blue"><i data-lucide="settings" style="width:14px;height:14px"></i></div>
                        General Settings
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Application Name</label>
                        <input type="text" name="app_name" value="<?= htmlspecialchars($settings['app_name'] ?? '') ?>" placeholder="e.g. AIDTRACK">
                        <div class="input-help">The name displayed across the system header and titles.</div>
                    </div>
                    <div class="form-group">
                        <label>Organization Name</label>
                        <input type="text" name="org_name" value="<?= htmlspecialchars($settings['org_name'] ?? '') ?>" placeholder="e.g. Municipal Social Welfare Office">
                        <div class="input-help">Full name of the organization managing this system.</div>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div class="card-title-icon cti-green"><i data-lucide="phone" style="width:14px;height:14px"></i></div>
                        Contact Information
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Contact Phone</label>
                        <input type="text" name="contact_phone" value="<?= htmlspecialchars($settings['contact_phone'] ?? '') ?>" placeholder="e.g. (02) 8123-4567">
                        <div class="input-help">Primary phone number for client inquiries.</div>
                    </div>
                    <div class="form-group">
                        <label>Contact Email</label>
                        <input type="email" name="contact_email" value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>" placeholder="e.g. mswdo@municipality.gov.ph">
                        <div class="input-help">Primary email address for support and notifications.</div>
                    </div>
                </div>
            </div>

        </div>

        <!-- System Configuration -->
        <div class="settings-full">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div class="card-title-icon cti-purple"><i data-lucide="sliders-horizontal" style="width:14px;height:14px"></i></div>
                        System Configuration
                    </div>
                </div>
                <div class="card-body">
                    <div class="settings-grid" style="margin-bottom:0;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Maximum File Upload Size <span class="label-hint">(MB)</span></label>
                            <input type="number" name="max_file_size" value="<?= htmlspecialchars($settings['max_file_size'] ?? '5') ?>" min="1" max="50" placeholder="5">
                            <div class="input-help">Maximum file size allowed for document uploads, in megabytes.</div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Allowed Aid Types <span class="label-hint">(comma-separated)</span></label>
                            <input type="text" name="allowed_types" value="<?= htmlspecialchars($settings['allowed_types'] ?? '') ?>" placeholder="medical,burial">
                            <div class="input-help">Comma-separated list of assistance types available in the system.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SMS & Email Notification Settings -->
        <div class="settings-grid">

            <!-- SMS Settings -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div class="card-title-icon" style="background:#fef3c7;color:#d97706;"><i data-lucide="smartphone" style="width:14px;height:14px"></i></div>
                        SMS Notifications
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Enable SMS Notifications</label>
                        <select name="sms_enabled" style="width:100%;font-family:inherit;font-size:.82rem;color:var(--navy);background:var(--white);border:1.5px solid var(--border);border-radius:8px;padding:9px 13px;outline:none;">
                            <option value="0" <?= ($settings['sms_enabled'] ?? '0')==='0'?'selected':'' ?>>Disabled</option>
                            <option value="1" <?= ($settings['sms_enabled'] ?? '0')==='1'?'selected':'' ?>>Enabled</option>
                        </select>
                        <div class="input-help">Send SMS via Semaphore API when application status changes.</div>
                    </div>
                    <div class="form-group">
                        <label>Semaphore API Key</label>
                        <input type="text" name="sms_api_key" value="<?= htmlspecialchars($settings['sms_api_key'] ?? '') ?>" placeholder="Your Semaphore API key">
                        <div class="input-help">Get your API key from <strong>semaphore.co</strong>.</div>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Sender Name</label>
                        <input type="text" name="sms_sender" value="<?= htmlspecialchars($settings['sms_sender'] ?? 'AIDTRACK') ?>" placeholder="AIDTRACK" maxlength="11">
                        <div class="input-help">Sender name shown to recipients (max 11 chars).</div>
                    </div>
                </div>
            </div>

            <!-- Email Settings -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div class="card-title-icon" style="background:#ede9fe;color:#7c3aed;"><i data-lucide="mail" style="width:14px;height:14px"></i></div>
                        Email Notifications
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Enable Email Notifications</label>
                        <select name="email_enabled" style="width:100%;font-family:inherit;font-size:.82rem;color:var(--navy);background:var(--white);border:1.5px solid var(--border);border-radius:8px;padding:9px 13px;outline:none;">
                            <option value="0" <?= ($settings['email_enabled'] ?? '0')==='0'?'selected':'' ?>>Disabled</option>
                            <option value="1" <?= ($settings['email_enabled'] ?? '0')==='1'?'selected':'' ?>>Enabled</option>
                        </select>
                        <div class="input-help">Send email notifications when application status changes.</div>
                    </div>
                    <div class="form-group">
                        <label>From Email Address</label>
                        <input type="email" name="email_from" value="<?= htmlspecialchars($settings['email_from'] ?? '') ?>" placeholder="noreply@aidtrack.local">
                        <div class="input-help">The sender email address for outgoing notifications.</div>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>From Name</label>
                        <input type="text" name="email_from_name" value="<?= htmlspecialchars($settings['email_from_name'] ?? '') ?>" placeholder="AIDTRACK System">
                        <div class="input-help">Display name shown alongside the from email.</div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Save Button -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save" style="width:14px;height:14px"></i> Save Settings
            </button>
            <button type="reset" class="btn btn-outline">
                <i data-lucide="rotate-ccw" style="width:14px;height:14px"></i> Reset Changes
            </button>
        </div>

    </form>

</main>

<script src="partials/admin.js"></script>
<script>
// Auto-hide toast after 4 seconds
setTimeout(function() {
    var t = document.querySelector('.toast');
    if (t) t.style.display = 'none';
}, 4000);
</script>
</body>
</html>
