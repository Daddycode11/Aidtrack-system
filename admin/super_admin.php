<?php
// admin/super_admin.php — Super Admin Panel
require_once __DIR__ . '/../helpers.php';
require_super_admin();

$admin_id = $_SESSION['user']['id'];

// --- Handle Actions (CSRF protected) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { header('Location: super_admin.php?err=csrf'); exit; }
    $action = $_POST['action'] ?? '';

    // Add Admin / Super Admin
    if ($action === 'add_admin') {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $role  = $_POST['role'] ?? 'admin';
        if (!in_array($role, ['admin', 'super_admin'])) $role = 'admin';

        if ($name && $phone && strlen($pass) >= 8) {
            $stmt = $mysqli->prepare("SELECT id FROM users WHERE phone=?");
            $stmt->bind_param('s', $phone);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                $stmt->close();
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $mysqli->prepare("INSERT INTO users (name, phone, password_hash, role) VALUES (?,?,?,?)");
                $stmt->bind_param('ssss', $name, $phone, $hash, $role);
                $stmt->execute();
                $new_id = (int)$mysqli->insert_id;
                $stmt->close();
                log_audit($mysqli, 'create_user', 'user', $new_id, "Created $role account: $name ($phone)");
                header('Location: super_admin.php?toast=added'); exit;
            } else {
                $stmt->close();
                header('Location: super_admin.php?toast=phone_taken'); exit;
            }
        }
    }

    // Change Role
    if ($action === 'change_role') {
        $uid      = (int)($_POST['user_id'] ?? 0);
        $new_role = $_POST['new_role'] ?? '';
        if ($uid && $uid !== $admin_id && in_array($new_role, ['client', 'admin', 'super_admin'])) {
            $old_stmt = $mysqli->prepare("SELECT role, name FROM users WHERE id=?");
            $old_stmt->bind_param('i', $uid); $old_stmt->execute();
            $old = $old_stmt->get_result()->fetch_assoc(); $old_stmt->close();

            $stmt = $mysqli->prepare("UPDATE users SET role=? WHERE id=?");
            $stmt->bind_param('si', $new_role, $uid);
            $stmt->execute();
            $stmt->close();
            log_audit($mysqli, 'change_role', 'user', $uid,
                "Changed role of {$old['name']} from {$old['role']} to $new_role");
            header('Location: super_admin.php?toast=role_changed'); exit;
        }
    }

    // Delete User (SA can delete directly)
    if ($action === 'delete_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid && $uid !== $admin_id) {
            $uinfo = $mysqli->prepare("SELECT name, role FROM users WHERE id=?");
            $uinfo->bind_param('i', $uid); $uinfo->execute();
            $target = $uinfo->get_result()->fetch_assoc(); $uinfo->close();
            if ($target && $target['role'] !== 'super_admin') {
                $stmt = $mysqli->prepare("DELETE FROM users WHERE id=? AND role != 'super_admin'");
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $stmt->close();
                log_audit($mysqli, 'delete_user', 'user', $uid,
                    "Deleted user: {$target['name']} (role: {$target['role']})");
                header('Location: super_admin.php?toast=deleted'); exit;
            }
        }
    }
}

// --- Fetch Users ---
$users = $mysqli->query("SELECT * FROM users ORDER BY role DESC, name ASC")->fetch_all(MYSQLI_ASSOC);

// --- Stats ---
$total_users  = count($users);
$admins       = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
$super_admins = count(array_filter($users, fn($u) => $u['role'] === 'super_admin'));
$clients      = count(array_filter($users, fn($u) => $u['role'] === 'client'));
$total_apps   = get_count('applications');
$total_msgs   = get_count('messages');

// --- Recent Actions ---
$recent_actions = [];
$stmt = $mysqli->prepare("
    SELECT aa.*, adm.name AS admin_name, app.type AS app_type
    FROM admin_actions aa
    LEFT JOIN users adm ON aa.admin_id = adm.id
    LEFT JOIN applications app ON aa.application_id = app.id
    ORDER BY aa.created_at DESC LIMIT 15
");
if ($stmt) { $stmt->execute(); $recent_actions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); }

// Page config
$active_page   = 'super_admin';
$page_title    = 'Super Admin';
$page_subtitle = 'System Management';
$pending_aids  = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0] ?? 0);
$colors = ['#1a56db','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Super Admin — AIDTRACK</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/admin.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
.sys-stats { display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-bottom:18px; }
.sys-card { background:var(--white); border:1px solid var(--border); border-radius:var(--r); padding:14px 16px; text-align:center; }
.sys-n { font-size:1.4rem; font-weight:800; line-height:1; }
.sys-l { font-size:.66rem; font-weight:600; color:var(--muted); margin-top:4px; }
.role-badge { display:inline-flex; padding:3px 10px; border-radius:99px; font-size:.7rem; font-weight:700; }
.rb-client { background:var(--brand-lt); color:var(--brand); }
.rb-admin { background:var(--green-lt); color:var(--green); }
.rb-super_admin { background:#f5f3ff; color:#7c3aed; }
.role-select { font-family:inherit; font-size:.74rem; font-weight:700; padding:3px 8px; border:1.5px solid var(--border); border-radius:6px; background:var(--white); color:var(--navy); outline:none; }
.role-select:focus { border-color:var(--brand); }
.toast { position:fixed; bottom:24px; right:24px; z-index:999; display:flex; align-items:center; gap:10px; padding:12px 18px; border-radius:10px; font-size:.83rem; font-weight:600; box-shadow:0 8px 28px rgba(0,0,0,.15); animation:toastIn .3s ease,toastOut .4s ease 3s forwards; }
.toast-ok { background:var(--green-lt); color:var(--green); border:1px solid rgba(22,163,74,.2); }
.toast-err { background:var(--red-lt); color:var(--red); border:1px solid rgba(220,38,38,.2); }
@keyframes toastIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
@keyframes toastOut{from{opacity:1}to{opacity:0;pointer-events:none}}
@media(max-width:960px) { .sys-stats { grid-template-columns:repeat(3,1fr); } }
@media(max-width:480px) { .sys-stats { grid-template-columns:repeat(2,1fr); } }
</style>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<?php if (!empty($_GET['toast'])):
    $t = $_GET['toast'];
    $is_err = $t === 'phone_taken';
    $tmsg = match($t) {
        'added' => 'Admin user created successfully.',
        'role_changed' => 'User role updated.',
        'deleted' => 'User deleted.',
        'phone_taken' => 'Phone number already in use.',
        default => ''
    };
?>
<div class="toast <?= $is_err ? 'toast-err' : 'toast-ok' ?>">
    <i data-lucide="<?= $is_err ? 'alert-circle' : 'check-circle' ?>" style="width:16px;height:16px"></i> <?= $tmsg ?>
</div>
<?php endif; ?>

<main class="main">

    <div class="page-header">
        <div class="page-header-top">
            <div>
                <div class="page-title">Super Admin Panel</div>
                <div class="page-sub">Manage users, roles, and system settings.</div>
            </div>
            <div class="page-actions">
                <button class="btn btn-primary btn-sm" onclick="document.getElementById('addModal').classList.add('open')">
                    <i data-lucide="user-plus" style="width:13px;height:13px"></i> Add Admin
                </button>
            </div>
        </div>
    </div>

    <!-- System Stats -->
    <div class="sys-stats">
        <div class="sys-card"><div class="sys-n"><?= $total_users ?></div><div class="sys-l">Total Users</div></div>
        <div class="sys-card"><div class="sys-n" style="color:var(--brand);"><?= $clients ?></div><div class="sys-l">Clients</div></div>
        <div class="sys-card"><div class="sys-n" style="color:var(--green);"><?= $admins ?></div><div class="sys-l">Admins</div></div>
        <div class="sys-card"><div class="sys-n" style="color:#7c3aed;"><?= $super_admins ?></div><div class="sys-l">Super Admins</div></div>
        <div class="sys-card"><div class="sys-n"><?= $total_apps ?></div><div class="sys-l">Applications</div></div>
        <div class="sys-card"><div class="sys-n"><?= $total_msgs ?></div><div class="sys-l">Messages</div></div>
    </div>

    <div class="grid-2">
        <!-- Users Table -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon cti-blue"><i data-lucide="users" style="width:14px;height:14px"></i></div>
                    User Management
                    <span class="count-pill"><?= $total_users ?></span>
                </div>
            </div>
            <div class="card-body no-pad">
                <div class="tbl-wrap">
                    <table data-paginate="10">
                        <thead>
                            <tr><th>User</th><th>Phone</th><th>Role</th><th>Joined</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $i => $u):
                            $col  = $colors[$i % count($colors)];
                            $init = strtoupper(substr($u['name'], 0, 2));
                            $is_self = $u['id'] == $admin_id;
                        ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <div class="row-avatar" style="background:<?= $col ?>;width:28px;height:28px;font-size:.6rem;"><?= $init ?></div>
                                    <div>
                                        <div style="font-weight:700;font-size:.82rem;"><?= htmlspecialchars($u['name']) ?></div>
                                        <div style="font-size:.66rem;color:var(--muted);"><?= htmlspecialchars($u['barangay'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:.8rem;color:var(--muted);"><?= htmlspecialchars($u['phone']) ?></td>
                            <td><span class="role-badge rb-<?= $u['role'] ?>"><?= str_replace('_',' ',ucfirst($u['role'])) ?></span></td>
                            <td style="font-size:.76rem;color:var(--muted);white-space:nowrap;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <?php if (!$is_self): ?>
                                <div style="display:flex;gap:4px;align-items:center;">
                                    <form method="post" style="display:flex;gap:4px;align-items:center;" onsubmit="return confirm('Change role for <?= htmlspecialchars(addslashes($u['name'])) ?>?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <select name="new_role" class="role-select" onchange="this.form.submit()">
                                            <option value="client"      <?= $u['role']==='client'     ?'selected':'' ?>>Client</option>
                                            <option value="admin"       <?= $u['role']==='admin'      ?'selected':'' ?>>Admin</option>
                                            <option value="super_admin" <?= $u['role']==='super_admin'?'selected':'' ?>>Super Admin</option>
                                        </select>
                                    </form>
                                    <?php if ($u['role'] !== 'super_admin'): ?>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($u['name'])) ?> permanently?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="tbl-action ta-delete" style="padding:4px 6px;">
                                            <i data-lucide="trash-2" style="width:11px;height:11px"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <span style="font-size:.74rem;color:var(--muted);font-style:italic;">You</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon cti-green"><i data-lucide="activity" style="width:14px;height:14px"></i></div>
                    Recent Activity
                </div>
                <a href="audit_trail.php" style="font-size:.75rem;font-weight:600;color:var(--brand);">View all</a>
            </div>
            <div class="card-body no-pad">
                <?php if (empty($recent_actions)): ?>
                <div class="empty-state"><div class="empty-icon"><i data-lucide="activity" style="width:20px;height:20px"></i></div><div class="empty-text">No activity yet.</div></div>
                <?php else: ?>
                <div class="tbl-wrap">
                    <table data-paginate="8">
                        <thead><tr><th>Admin</th><th>Action</th><th>Type</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent_actions as $act): ?>
                        <tr>
                            <td style="font-weight:700;font-size:.8rem;"><?= htmlspecialchars($act['admin_name'] ?? 'Unknown') ?></td>
                            <td>
                                <span class="role-badge <?= match($act['action']) { 'approve'=>'rb-admin', 'reject'=>'rb-client', default=>'rb-super_admin' } ?>">
                                    <?= ucfirst($act['action']) ?>
                                </span>
                            </td>
                            <td style="font-size:.8rem;"><?= ucfirst($act['app_type'] ?? '—') ?></td>
                            <td style="font-size:.74rem;color:var(--muted);white-space:nowrap;"><?= date('M d h:i A', strtotime($act['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</main>

<!-- Add Admin Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">
                <div class="card-title-icon cti-green" style="width:28px;height:28px;"><i data-lucide="user-plus" style="width:14px;height:14px"></i></div>
                Add Admin User
            </div>
            <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')"><i data-lucide="x" style="width:14px;height:14px"></i></button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <div class="modal-body">
                <input type="hidden" name="action" value="add_admin">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" placeholder="09XX XXX XXXX" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" minlength="8" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="user-plus" style="width:13px;height:13px"></i> Create</button>
            </div>
        </form>
    </div>
</div>

<script src="partials/admin.js"></script>
<script>
document.getElementById('addModal').addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
</script>
</body>
</html>
