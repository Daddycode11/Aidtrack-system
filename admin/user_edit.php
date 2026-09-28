<?php
// admin/user_edit.php
require_once __DIR__ . '/../helpers.php';
require_admin();

$admin_id = $_SESSION['user']['id'] ?? 0;
$is_sa    = is_super_admin();

// --- Get target user ---
$id = (int)($_GET['id'] ?? $_POST['user_id'] ?? 0);
if (!$id) { header('Location: user.php'); exit; }

$stmt = $mysqli->prepare("SELECT id, name, phone, barangay, role, status, created_at FROM users WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$target = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$target) { header('Location: user.php?err=notfound'); exit; }

$is_self          = ($id === $admin_id);
$editing_sa        = ($target['role'] === 'super_admin');
$can_change_role   = $is_sa && !$is_self;              // only SA can change roles, not their own
$locked_no_access  = $editing_sa && !$is_sa;            // regular admin cannot touch a super admin at all

if ($locked_no_access) { header('Location: user.php?err=forbidden'); exit; }

$errors = [];
$old    = $target; // pre-fill values

// --- Handle Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_user') {
    if (!csrf_verify()) { header('Location: user_edit.php?id=' . $id . '&err=csrf'); exit; }

    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $role     = $_POST['role'] ?? $target['role'];

    $old = ['id' => $id, 'name' => $name, 'phone' => $phone, 'barangay' => $barangay, 'role' => $role, 'status' => $target['status'], 'created_at' => $target['created_at']];

    if ($name === '')  $errors[] = 'Full name is required.';
    if ($phone === '') $errors[] = 'Phone number is required.';
    if ($phone && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) $errors[] = 'Phone number format is invalid.';

    $allowed_roles = ['client', 'staff', 'admin', 'super_admin'];
    if (!in_array($role, $allowed_roles, true)) $errors[] = 'Invalid role selected.';

    // Prevent privilege escalation / role tampering by non-SA
    if (!$can_change_role) {
        $role = $target['role'];
    }
    // Prevent demoting/removing the last super admin accidentally via this form
    if ($target['role'] === 'super_admin' && $role !== 'super_admin') {
        $errors[] = 'Super Admin role cannot be changed from this screen.';
    }

    // Phone uniqueness check (excluding self)
    if (!$errors) {
        $chk = $mysqli->prepare("SELECT id FROM users WHERE phone = ? AND id != ? LIMIT 1");
        $chk->bind_param('si', $phone, $id);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) $errors[] = 'Phone number is already used by another account.';
        $chk->close();
    }

    if (!$errors) {
        $upd = $mysqli->prepare("UPDATE users SET name=?, phone=?, barangay=?, role=? WHERE id=?");
        $upd->bind_param('ssssi', $name, $phone, $barangay, $role, $id);
        $upd->execute();
        $upd->close();

        $changes = [];
        if ($name !== $target['name'])         $changes[] = "name: '{$target['name']}' → '{$name}'";
        if ($phone !== $target['phone'])       $changes[] = "phone: '{$target['phone']}' → '{$phone}'";
        if ($barangay !== $target['barangay']) $changes[] = "barangay: '{$target['barangay']}' → '{$barangay}'";
        if ($role !== $target['role'])         $changes[] = "role: '{$target['role']}' → '{$role}'";

        log_audit($mysqli, 'update_user', 'user', $id,
            $changes ? 'Updated fields — ' . implode(', ', $changes) : 'No field changes saved');

        header('Location: user_edit.php?id=' . $id . '&toast=updated');
        exit;
    }
}

// --- Handle Flag / Unflag Document (client accounts only) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['flag_document', 'unflag_document'])) {
    if (!csrf_verify()) { header('Location: user_edit.php?id=' . $id . '&err=csrf'); exit; }

    $doc_id = (int)($_POST['doc_id'] ?? 0);

    // Make sure this document actually belongs to an application of this user
    $chk = $mysqli->prepare("
        SELECT d.id FROM documents d
        JOIN applications a ON a.id = d.application_id
        WHERE d.id = ? AND a.user_id = ?
    ");
    $chk->bind_param('ii', $doc_id, $id);
    $chk->execute();
    $owns_doc = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($doc_id && $owns_doc) {
        if (($_POST['action'] ?? '') === 'flag_document') {
            $reason = trim($_POST['flag_reason'] ?? '');
            if ($reason === '') {
                header('Location: user_edit.php?id=' . $id . '&err=noreason');
                exit;
            }

            $upd = $mysqli->prepare("
                UPDATE documents
                SET status='invalid', flag_reason=?, flagged_by=?, flagged_at=NOW()
                WHERE id=?
            ");
            $upd->bind_param('sii', $reason, $admin_id, $doc_id);
            $upd->execute();
            $upd->close();

            // Notify the beneficiary so they know to re-upload
            $doc_info = $mysqli->prepare("SELECT original_name FROM documents WHERE id=?");
            $doc_info->bind_param('i', $doc_id);
            $doc_info->execute();
            $doc_name = $doc_info->get_result()->fetch_assoc()['original_name'] ?? 'a document';
            $doc_info->close();

            $notif_msg = "One of your submitted documents (\"{$doc_name}\") was marked invalid: {$reason}. Please re-upload a corrected copy.";
            $notif = $mysqli->prepare("INSERT INTO messages (user_id, sender, message, is_read, created_at) VALUES (?, 'admin', ?, 0, NOW())");
            $notif->bind_param('is', $id, $notif_msg);
            $notif->execute();
            $notif->close();

            log_audit($mysqli, 'flag_document', 'document', $doc_id,
                "Flagged document \"{$doc_name}\" as invalid for {$target['name']} — reason: {$reason}");

            header('Location: user_edit.php?id=' . $id . '&toast=doc_flagged');
            exit;

        } else {
            $upd = $mysqli->prepare("
                UPDATE documents
                SET status='valid', flag_reason=NULL, flagged_by=NULL, flagged_at=NULL
                WHERE id=?
            ");
            $upd->bind_param('i', $doc_id);
            $upd->execute();
            $upd->close();

            log_audit($mysqli, 'unflag_document', 'document', $doc_id,
                "Marked document as valid again for {$target['name']}");

            header('Location: user_edit.php?id=' . $id . '&toast=doc_unflagged');
            exit;
        }
    }

    header('Location: user_edit.php?id=' . $id); exit;
}

// --- Handle Password Reset ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
    if (!csrf_verify()) { header('Location: user_edit.php?id=' . $id . '&err=csrf'); exit; }

    $new_pass  = $_POST['new_password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if (strlen($new_pass) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    } elseif ($new_pass !== $confirm) {
        $errors[] = 'Password confirmation does not match.';
    } else {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $upd = $mysqli->prepare("UPDATE users SET password=? WHERE id=?");
        $upd->bind_param('si', $hash, $id);
        $upd->execute();
        $upd->close();

        log_audit($mysqli, 'reset_password', 'user', $id, "Password reset for: {$target['name']}");
        header('Location: user_edit.php?id=' . $id . '&toast=pw_reset');
        exit;
    }
}

// --- Fetch submitted documents (client accounts only) ---
$documents = [];
if ($target['role'] === 'client') {
    $stmt = $mysqli->prepare("
        SELECT d.id, d.filename, d.original_name, d.status, d.flag_reason, d.flagged_at,
               a.id AS application_id, a.type AS aid_type, a.created_at AS app_created_at
        FROM documents d
        JOIN applications a ON a.id = d.application_id
        WHERE a.user_id = ?
        ORDER BY a.created_at DESC, d.id ASC
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$aid_type_labels = [
    'burial'      => 'Burial Assistance',
    'medical'     => 'Medical Assistance',
    'educational' => 'Educational Assistance',
    'livelihood'  => 'Livelihood Assistance',
    'emergency'   => 'Emergency Assistance',
];

// Partials
$active_page   = 'users';
$page_title    = 'Edit User';
$page_subtitle = 'Users';
$pending_aids  = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0] ?? 0);
$colors        = ['#1a56db','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'];
$avatar_col    = $colors[$target['id'] % count($colors)];
$initials      = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $target['name']), 0, 2)));

function role_badge(string $role): string {
    $map = [
        'super_admin' => ['label'=>'Super Admin', 'bg'=>'#f5f3ff', 'color'=>'#7c3aed'],
        'admin'       => ['label'=>'Admin',        'bg'=>'#eff4ff', 'color'=>'#1a56db'],
        'client'      => ['label'=>'Beneficiary',  'bg'=>'#f0fdf4', 'color'=>'#16a34a'],
        'staff'       => ['label'=>'Staff',        'bg'=>'#fff7ed', 'color'=>'#c2410c'],
    ];
    $s = $map[$role] ?? ['label'=>ucfirst($role),'bg'=>'#f1f5f9','color'=>'#64748b'];
    return "<span class=\"badge\" style=\"background:{$s['bg']};color:{$s['color']}\">{$s['label']}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit <?= htmlspecialchars($target['name']) ?> — AIDTRACK Admin</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/admin.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
.back-link { display:inline-flex; align-items:center; gap:6px; font-size:.78rem; font-weight:600; color:var(--muted); margin-bottom:14px; }

.edit-hero { display:flex; align-items:center; gap:14px; padding:18px 20px; background:var(--white); border:1px solid var(--border); border-radius:14px; margin-bottom:18px; }
.edit-avatar { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:1rem; flex-shrink:0; }
.edit-hero .name { font-size:1rem; font-weight:800; color:var(--navy); }
.edit-hero .sub  { font-size:.75rem; color:var(--muted); margin-top:2px; display:flex; align-items:center; gap:8px; }

.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; padding:20px 22px; }
.form-grid.full { grid-template-columns:1fr; }
.f-group { display:flex; flex-direction:column; gap:6px; }
.f-group label { font-size:.76rem; font-weight:700; color:var(--navy); }
.f-group input, .f-group select {
    font-family:inherit; font-size:.84rem; color:var(--navy); background:var(--white);
    border:1.5px solid var(--border); border-radius:9px; padding:9px 12px; outline:none;
}
.f-group input:focus, .f-group select:focus { border-color:var(--brand); }
.f-group input:disabled, .f-group select:disabled { background:var(--bg); color:var(--muted); cursor:not-allowed; }
.f-hint { font-size:.7rem; color:var(--muted); }

.form-actions { display:flex; justify-content:flex-end; gap:10px; padding:16px 22px; border-top:1px solid var(--border); background:var(--bg); }

.alert { display:flex; align-items:flex-start; gap:10px; padding:12px 16px; border-radius:10px; font-size:.8rem; font-weight:600; margin-bottom:16px; }
.alert-error   { background:var(--red-lt); color:var(--red); border:1px solid rgba(220,38,38,.2); }
.alert-success { background:var(--green-lt); color:var(--green); border:1px solid rgba(22,163,74,.2); }

@media(max-width:720px){ .form-grid { grid-template-columns:1fr; } }

.doc-list { display:flex; flex-direction:column; gap:10px; padding:16px 22px; }
.doc-row {
    display:flex; align-items:flex-start; gap:12px; padding:12px 14px;
    border:1.5px solid var(--border); border-radius:10px; background:var(--white);
}
.doc-row.doc-invalid { border-color:rgba(220,38,38,.3); background:var(--red-lt); }
.doc-icon {
    width:32px; height:32px; border-radius:8px; background:#eff4ff; color:#1a56db;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.doc-row.doc-invalid .doc-icon { background:#fff; color:var(--red); }
.doc-name { font-size:.82rem; font-weight:700; color:var(--navy); word-break:break-word; }
.doc-meta { font-size:.7rem; color:var(--muted); margin-top:2px; }
.doc-flag-reason {
    display:flex; align-items:center; gap:5px; font-size:.72rem; font-weight:600; color:var(--red);
    margin-top:6px;
}
.doc-actions { display:flex; gap:6px; flex-shrink:0; flex-wrap:wrap; }

.modal-overlay {
    display:none; position:fixed; inset:0; background:rgba(15,23,42,.45); z-index:1000;
    align-items:center; justify-content:center; padding:20px;
}
.modal-overlay.show { display:flex; }
.modal-box { background:var(--white); border-radius:14px; padding:22px; width:100%; max-width:420px; }
.modal-title { font-size:.95rem; font-weight:800; color:var(--navy); display:flex; align-items:center; gap:8px; margin-bottom:6px; }
.modal-sub { font-size:.78rem; color:var(--muted); margin-bottom:16px; }
.modal-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:16px; }
</style>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<main class="main">

    <a href="user.php" class="back-link">
        <i data-lucide="arrow-left" style="width:13px;height:13px"></i> Back to Users
    </a>

    <!-- Edit Hero -->
    <div class="edit-hero">
        <div class="edit-avatar" style="background:<?= $avatar_col ?>"><?= $initials ?></div>
        <div style="flex:1;">
            <div class="name"><?= htmlspecialchars($target['name']) ?></div>
            <div class="sub">
                ID #<?= $target['id'] ?> · <?= role_badge($target['role']) ?>
                <?php if ($is_self): ?><span style="font-size:.68rem;font-weight:700;background:var(--brand-lt);color:var(--brand);padding:1px 8px;border-radius:99px;">You</span><?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($_GET['toast'])): ?>
    <div class="alert alert-success">
        <i data-lucide="check-circle" style="width:15px;height:15px"></i>
        <?= match($_GET['toast']) {
            'pw_reset'      => 'Password reset successfully.',
            'doc_flagged'   => 'Document flagged as invalid. The beneficiary has been notified.',
            'doc_unflagged' => 'Document marked as valid again.',
            default         => 'User details updated successfully.'
        } ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($_GET['err'])): ?>
    <div class="alert alert-error">
        <i data-lucide="alert-circle" style="width:15px;height:15px"></i>
        <?= match($_GET['err']) {
            'csrf'     => 'Security check failed. Please try again.',
            'noreason' => 'Please provide a reason before flagging a document.',
            default    => 'You are not authorized to perform this action.'
        } ?>
    </div>
    <?php endif; ?>

    <?php if ($errors): ?>
    <div class="alert alert-error">
        <i data-lucide="alert-circle" style="width:15px;height:15px;flex-shrink:0;"></i>
        <div>
            <?php foreach ($errors as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Details Form -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-blue"><i data-lucide="user-cog" style="width:14px;height:14px"></i></div>
                Account Details
            </div>
        </div>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" value="<?= $target['id'] ?>">

            <div class="form-grid">
                <div class="f-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($old['name']) ?>" required>
                </div>
                <div class="f-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($old['phone']) ?>" required>
                </div>
                <div class="f-group">
                    <label for="barangay">Barangay</label>
                    <input type="text" id="barangay" name="barangay" value="<?= htmlspecialchars($old['barangay'] ?? '') ?>">
                </div>
                <div class="f-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" <?= $can_change_role ? '' : 'disabled' ?>>
                        <option value="client"      <?= $old['role']==='client'     ?'selected':''?>>Beneficiary</option>
                        <option value="staff"       <?= $old['role']==='staff'      ?'selected':''?>>Staff</option>
                        <option value="admin"       <?= $old['role']==='admin'      ?'selected':''?>>Admin</option>
                        <option value="super_admin" <?= $old['role']==='super_admin'?'selected':''?> <?= !$is_sa ? 'disabled' : '' ?>>Super Admin</option>
                    </select>
                    <?php if (!$can_change_role): ?>
                    <div class="f-hint">
                        <?= $is_self ? 'You cannot change your own role.' : 'Only Super Admins can change roles.' ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-actions">
                <a href="user.php" class="btn btn-outline btn-sm">Cancel</a>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i data-lucide="save" style="width:13px;height:13px"></i> Save Changes
                </button>
            </div>
        </form>
    </div>

    <?php if ($target['role'] === 'client'): ?>
    <!-- Submitted Documents -->
    <div class="card" style="margin-top:18px;">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon" style="background:#eff4ff;color:#1a56db;"><i data-lucide="paperclip" style="width:14px;height:14px"></i></div>
                Submitted Documents
                <span class="count-pill"><?= count($documents) ?></span>
            </div>
        </div>

        <?php if (empty($documents)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i data-lucide="file-x" style="width:20px;height:20px"></i></div>
            <div class="empty-text">This beneficiary hasn't submitted any documents yet.</div>
        </div>
        <?php else: ?>
        <div class="doc-list">
            <?php foreach ($documents as $d): ?>
            <div class="doc-row <?= $d['status'] === 'invalid' ? 'doc-invalid' : '' ?>">
                <div class="doc-icon"><i data-lucide="file-text" style="width:16px;height:16px"></i></div>
                <div style="flex:1;min-width:0;">
                    <div class="doc-name"><?= htmlspecialchars($d['original_name']) ?></div>
                    <div class="doc-meta">
                        <?= htmlspecialchars($aid_type_labels[$d['aid_type']] ?? ucfirst($d['aid_type'])) ?>
                        · Application #<?= $d['application_id'] ?>
                        · <?= date('M d, Y', strtotime($d['app_created_at'])) ?>
                    </div>
                    <?php if ($d['status'] === 'invalid'): ?>
                    <div class="doc-flag-reason">
                        <i data-lucide="alert-triangle" style="width:11px;height:11px"></i>
                        Flagged invalid — <?= htmlspecialchars($d['flag_reason']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="doc-actions">
                    <a href="../uploads/<?= htmlspecialchars($d['filename']) ?>" target="_blank" class="tbl-action ta-view">
                        <i data-lucide="eye" style="width:11px;height:11px"></i> View
                    </a>
                    <?php if ($d['status'] === 'invalid'): ?>
                    <form method="post" style="display:contents;" onsubmit="return confirm('Mark this document as valid again?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="unflag_document">
                        <input type="hidden" name="doc_id" value="<?= $d['id'] ?>">
                        <button type="submit" class="tbl-action ta-approve">
                            <i data-lucide="check-circle" style="width:11px;height:11px"></i> Mark Valid
                        </button>
                    </form>
                    <?php else: ?>
                    <button type="button" class="tbl-action ta-delete" onclick="openFlagModal(<?= $d['id'] ?>, '<?= htmlspecialchars(addslashes($d['original_name'])) ?>')">
                        <i data-lucide="flag" style="width:11px;height:11px"></i> Flag Invalid
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Flag Reason Modal -->
    <div id="flagModalOverlay" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-title"><i data-lucide="flag" style="width:15px;height:15px;color:var(--red)"></i> Flag Document as Invalid</div>
            <div class="modal-sub">This will notify the beneficiary that <b id="flagDocName"></b> needs to be re-uploaded.</div>
            <form method="post" id="flagForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="flag_document">
                <input type="hidden" name="doc_id" id="flagDocId" value="">
                <div class="f-group">
                    <label for="flag_reason">Reason</label>
                    <textarea id="flag_reason" name="flag_reason" rows="3" placeholder="e.g. Image is blurry / wrong document type / expired document" required></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeFlagModal()">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background:var(--red);color:#fff;">
                        <i data-lucide="flag" style="width:12px;height:12px"></i> Flag & Notify
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Password Reset -->
    <div class="card" style="margin-top:18px;">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon" style="background:#fff7ed;color:#c2410c;"><i data-lucide="key-round" style="width:14px;height:14px"></i></div>
                Reset Password
            </div>
        </div>

        <form method="post" onsubmit="return confirm('Reset the password for <?= htmlspecialchars(addslashes($target['name'])) ?>?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" value="<?= $target['id'] ?>">

            <div class="form-grid">
                <div class="f-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" minlength="8" required placeholder="At least 8 characters">
                </div>
                <div class="f-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-outline btn-sm">
                    <i data-lucide="key-round" style="width:13px;height:13px"></i> Reset Password
                </button>
            </div>
        </form>
    </div>

</main>

<script src="partials/admin.js"></script>
<script>
function openFlagModal(docId, docName) {
    document.getElementById('flagDocId').value = docId;
    document.getElementById('flagDocName').textContent = docName;
    document.getElementById('flag_reason').value = '';
    document.getElementById('flagModalOverlay').classList.add('show');
}
function closeFlagModal() {
    document.getElementById('flagModalOverlay').classList.remove('show');
}
</script>
</body>
</html>