<?php
// admin/user.php
require_once __DIR__ . '/../helpers.php';
require_admin();

$admin_id = $_SESSION['user']['id'] ?? 0;
$is_sa    = is_super_admin();

// --- Handle Suspend / Activate (Super Admin only, CSRF protected) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['suspend_user','activate_user'])) {
    if (!csrf_verify() || !$is_sa) { header('Location: user.php?err=forbidden'); exit; }
    $target_id = (int)($_POST['user_id'] ?? 0);
    $action    = $_POST['action'];
    if ($target_id && $target_id !== $admin_id) {
        $new_status = $action === 'suspend_user' ? 'suspended' : 'active';
        $upd = $mysqli->prepare("UPDATE users SET status=? WHERE id=? AND role='client'");
        $upd->bind_param('si', $new_status, $target_id);
        $upd->execute();
        $upd->close();
        $audit_action = $action === 'suspend_user' ? 'suspend_account' : 'activate_account';
        log_audit($mysqli, $audit_action, 'user', $target_id, "Account status set to: $new_status");
        // Destroy active session for this user if suspended
        if ($new_status === 'suspended') {
            $mysqli->query("UPDATE users SET status='suspended' WHERE id=$target_id");
        }
        header('Location: user.php?toast=' . ($new_status === 'suspended' ? 'suspended' : 'activated')); exit;
    }
    header('Location: user.php'); exit;
}

// --- Handle Delete (POST only, CSRF protected) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    if (!csrf_verify()) { header('Location: user.php?err=csrf'); exit; }
    $del_id = (int)($_POST['user_id'] ?? 0);

    if ($del_id && $del_id !== $admin_id) {
        // Fetch target user info for logging/approval
        $uinfo = $mysqli->prepare("SELECT name, role FROM users WHERE id=?");
        $uinfo->bind_param('i', $del_id); $uinfo->execute();
        $target = $uinfo->get_result()->fetch_assoc(); $uinfo->close();

        if ($target && $target['role'] === 'super_admin') {
            header('Location: user.php?toast=cannot_delete_sa'); exit;
        }

        if ($is_sa) {
            // Super admin can delete directly
            $stmt = $mysqli->prepare("DELETE FROM users WHERE id=? AND role != 'super_admin'");
            $stmt->bind_param('i', $del_id); $stmt->execute(); $stmt->close();
            log_audit($mysqli, 'delete_user', 'user', $del_id,
                "Deleted user: " . ($target['name'] ?? "ID $del_id") . " (role: " . ($target['role'] ?? '?') . ")");
            header('Location: user.php?toast=deleted'); exit;
        } else {
            // Regular admin → route through SA approval
            create_approval_request($mysqli, $admin_id, 'delete_user', $del_id,
                ['user_id' => $del_id, 'user_name' => $target['name'] ?? '', 'user_role' => $target['role'] ?? ''],
                "Request to delete user: " . ($target['name'] ?? "ID $del_id")
            );
            log_audit($mysqli, 'request_delete_user', 'user', $del_id,
                "Requested deletion of: " . ($target['name'] ?? "ID $del_id"));
            header('Location: user.php?toast=delete_requested'); exit;
        }
    }
    header('Location: user.php'); exit;
}

// --- Filter ---
$search  = trim($_GET['search'] ?? '');
$f_role  = $_GET['role'] ?? '';

$where  = []; $params = []; $types = '';
if ($search) { $where[] = '(name LIKE ? OR phone LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $types .= 'ss'; }
if ($f_role) { $where[] = 'role = ?'; $params[] = $f_role; $types .= 's'; }
$where_sql = $where ? 'WHERE '.implode(' AND ', $where) : '';

$stmt = $mysqli->prepare("
    SELECT id, name, phone, barangay, role, status, created_at
    FROM users $where_sql ORDER BY created_at DESC
");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Role counts
$role_counts = [];
$res = $mysqli->query("SELECT role, COUNT(*) AS cnt FROM users GROUP BY role");
while ($r = $res->fetch_assoc()) $role_counts[$r['role']] = $r['cnt'];

// Partials
$active_page   = 'users';
$page_title    = 'User Management';
$page_subtitle = 'Users';
$pending_aids  = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0] ?? 0);
$colors        = ['#1a56db','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'];

$role_styles = [
    'super_admin' => ['label'=>'Super Admin', 'bg'=>'#f5f3ff', 'color'=>'#7c3aed'],
    'admin'       => ['label'=>'Admin',        'bg'=>'var(--brand-lt)', 'color'=>'var(--brand)'],
    'client'      => ['label'=>'Beneficiary',  'bg'=>'var(--green-lt)', 'color'=>'var(--green)'],
    'staff'       => ['label'=>'Staff',        'bg'=>'#fff7ed', 'color'=>'#c2410c'],
];
function role_badge(string $role): string {
    $map = [
        'super_admin' => ['label'=>'Super Admin', 'bg'=>'#f5f3ff',           'color'=>'#7c3aed'],
        'admin'       => ['label'=>'Admin',        'bg'=>'#eff4ff',           'color'=>'#1a56db'],
        'client'      => ['label'=>'Beneficiary',  'bg'=>'#f0fdf4',           'color'=>'#16a34a'],
        'staff'       => ['label'=>'Staff',        'bg'=>'#fff7ed',           'color'=>'#c2410c'],
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
<title>Users — AIDTRACK Admin</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/admin.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
.role-strip { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:16px; }
.rs-card { background:var(--white); border:1px solid var(--border); border-radius:var(--r); padding:14px 16px; display:flex; align-items:center; gap:11px; }
.rs-icon { width:34px; height:34px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.rs-n { font-size:1.4rem; font-weight:800; line-height:1; }
.rs-l { font-size:.68rem; font-weight:600; color:var(--muted); margin-top:2px; }

.toast { position:fixed; bottom:24px; right:24px; z-index:999; display:flex; align-items:center; gap:10px; padding:12px 18px; border-radius:10px; font-size:.83rem; font-weight:600; box-shadow:0 8px 28px rgba(0,0,0,.15); animation:toastIn .3s ease, toastOut .4s ease 3s forwards; }
.toast-deleted          { background:var(--red-lt);    color:var(--red);    border:1px solid rgba(220,38,38,.2); }
.toast-delete_requested { background:#f5f3ff;          color:#7c3aed;       border:1px solid rgba(124,58,237,.2); }
.toast-cannot_delete_sa { background:var(--red-lt);    color:var(--red);    border:1px solid rgba(220,38,38,.2); }
.toast-suspended        { background:var(--yellow-lt); color:var(--yellow); border:1px solid rgba(202,138,4,.2); }
.toast-activated        { background:var(--green-lt);  color:var(--green);  border:1px solid rgba(22,163,74,.2); }
.status-suspended { display:inline-flex;align-items:center;gap:4px;font-size:.63rem;font-weight:700;padding:2px 9px;border-radius:99px;background:var(--red-lt);color:var(--red); }
.status-active    { display:inline-flex;align-items:center;gap:4px;font-size:.63rem;font-weight:700;padding:2px 9px;border-radius:99px;background:var(--green-lt);color:var(--green); }
@keyframes toastIn  { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:none} }
@keyframes toastOut { from{opacity:1} to{opacity:0;pointer-events:none} }

@media(max-width:860px){ .role-strip { grid-template-columns:1fr 1fr; } }
@media(max-width:480px){ .role-strip { grid-template-columns:1fr 1fr; } }
</style>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<?php if (!empty($_GET['toast'])): ?>
<?php $t = $_GET['toast']; ?>
<div class="toast toast-<?= $t ?>">
    <i data-lucide="<?= $t === 'deleted' ? 'trash-2' : ($t === 'delete_requested' ? 'clock' : 'alert-circle') ?>" style="width:15px;height:15px"></i>
    <?= match($t) {
        'deleted'           => 'User deleted successfully.',
        'delete_requested'  => 'Deletion request submitted for Super Admin approval.',
        'cannot_delete_sa'  => 'Super Admin accounts cannot be deleted.',
        'suspended'         => 'Account suspended successfully.',
        'activated'         => 'Account activated successfully.',
        default             => 'Action completed.'
    } ?>
</div>
<?php endif; ?>

<main class="main">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-top">
            <div>
                <div class="page-title">User Management</div>
                <div class="page-sub">Manage all registered system users, roles, and access levels.</div>
            </div>
            <div class="page-actions">
                <span class="count-pill"><?= count($users) ?> total</span>
                <a href="user_create.php" class="btn btn-primary btn-sm">
                    <i data-lucide="user-plus" style="width:13px;height:13px"></i> Add User
                </a>
            </div>
        </div>
    </div>

    <!-- Role Summary Strip -->
    <div class="role-strip">
        <?php
        $strip = [
            ['role'=>'client',      'label'=>'Beneficiaries', 'bg'=>'var(--green-lt)',  'color'=>'var(--green)',  'icon'=>'users'],
            ['role'=>'admin',       'label'=>'Admins',         'bg'=>'var(--brand-lt)', 'color'=>'var(--brand)',  'icon'=>'shield-check'],
            ['role'=>'staff',       'label'=>'Staff',          'bg'=>'#fff7ed',          'color'=>'#c2410c',       'icon'=>'user-cog'],
            ['role'=>'super_admin', 'label'=>'Super Admins',   'bg'=>'#f5f3ff',          'color'=>'#7c3aed',       'icon'=>'crown'],
        ];
        foreach ($strip as $s): ?>
        <div class="rs-card">
            <div class="rs-icon" style="background:<?= $s['bg'] ?>;color:<?= $s['color'] ?>">
                <i data-lucide="<?= $s['icon'] ?>" style="width:16px;height:16px"></i>
            </div>
            <div>
                <div class="rs-n" style="color:<?= $s['color'] ?>"><?= $role_counts[$s['role']] ?? 0 ?></div>
                <div class="rs-l"><?= $s['label'] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-blue"><i data-lucide="user-cog" style="width:14px;height:14px"></i></div>
                System Users
                <span class="count-pill"><?= count($users) ?></span>
            </div>
        </div>

        <!-- Filter bar -->
        <form method="get" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:13px 18px;border-bottom:1px solid var(--border);background:var(--bg);">
            <i data-lucide="search" style="width:14px;height:14px;color:var(--muted)"></i>
            <input type="text" name="search" placeholder="Search by name or phone…"
                   value="<?= htmlspecialchars($search) ?>"
                   style="flex:1;min-width:160px;font-family:inherit;font-size:.8rem;color:var(--navy);background:var(--white);border:1.5px solid var(--border);border-radius:8px;padding:7px 12px;outline:none;">
            <select name="role" style="font-family:inherit;font-size:.8rem;color:var(--navy);background:var(--white);border:1.5px solid var(--border);border-radius:8px;padding:7px 12px;outline:none;">
                <option value="">All Roles</option>
                <option value="client"      <?= $f_role==='client'     ?'selected':''?>>Beneficiary</option>
                <option value="admin"       <?= $f_role==='admin'      ?'selected':''?>>Admin</option>
                <option value="staff"       <?= $f_role==='staff'      ?'selected':''?>>Staff</option>
                <option value="super_admin" <?= $f_role==='super_admin'?'selected':''?>>Super Admin</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">
                <i data-lucide="filter" style="width:13px;height:13px"></i> Filter
            </button>
            <?php if ($search || $f_role): ?>
            <a href="user.php" style="font-size:.76rem;font-weight:600;color:var(--muted);">
                <i data-lucide="x" style="width:12px;height:12px;vertical-align:middle"></i> Clear
            </a>
            <?php endif; ?>
        </form>

        <div class="card-body no-pad">
            <?php if (empty($users)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="users" style="width:20px;height:20px"></i></div>
                <div class="empty-text">No users match your search.</div>
            </div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table data-paginate="10">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Phone</th>
                            <th>Barangay</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $i => $u):
                        $col  = $colors[$i % count($colors)];
                        $init = implode('', array_map(fn($w)=>strtoupper($w[0]), array_slice(explode(' ',$u['name']),0,2)));
                        $is_me = ($u['id'] === ($_SESSION['user']['id'] ?? 0));
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:9px;">
                                <div class="row-avatar" style="background:<?= $col ?>"><?= $init ?></div>
                                <div>
                                    <div style="font-weight:700;font-size:.82rem;color:var(--navy);">
                                        <?= htmlspecialchars($u['name']) ?>
                                        <?php if ($is_me): ?>
                                        <span style="font-size:.63rem;font-weight:700;background:var(--brand-lt);color:var(--brand);padding:1px 7px;border-radius:99px;margin-left:5px;">You</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size:.68rem;color:var(--muted);">ID #<?= $u['id'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="font-family:monospace;font-size:.8rem;color:var(--muted);"><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
                        <td style="color:var(--muted);"><?= htmlspecialchars($u['barangay'] ?? '—') ?></td>
                        <td><?= role_badge($u['role']) ?></td>
                        <td>
                            <?php $st = $u['status'] ?? 'active'; ?>
                            <span class="status-<?= $st ?>">
                                <i data-lucide="<?= $st === 'suspended' ? 'ban' : 'check-circle' ?>" style="width:10px;height:10px"></i>
                                <?= ucfirst($st) ?>
                            </span>
                        </td>
                        <td style="color:var(--muted);white-space:nowrap;font-size:.76rem;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <div class="tbl-actions">
                                <a href="user_edit.php?id=<?= $u['id'] ?>" class="tbl-action ta-view">
                                    <i data-lucide="pencil" style="width:11px;height:11px"></i> Edit
                                </a>
                                <?php if ($is_sa && !$is_me && $u['role'] === 'client'): ?>
                                <?php if (($u['status'] ?? 'active') === 'active'): ?>
                                <form method="post" style="display:contents;"
                                      onsubmit="return confirm('Suspend account of <?= htmlspecialchars(addslashes($u['name'])) ?>? They will not be able to log in.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="suspend_user">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="tbl-action ta-delete" title="Suspend Account" style="background:var(--yellow-lt);color:var(--yellow);">
                                        <i data-lucide="ban" style="width:11px;height:11px"></i> Suspend
                                    </button>
                                </form>
                                <?php else: ?>
                                <form method="post" style="display:contents;"
                                      onsubmit="return confirm('Reactivate account of <?= htmlspecialchars(addslashes($u['name'])) ?>?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="activate_user">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="tbl-action ta-approve" title="Activate Account">
                                        <i data-lucide="check-circle" style="width:11px;height:11px"></i> Activate
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!$is_me && $u['role'] !== 'super_admin'): ?>
                                <form method="post" style="display:contents;"
                                      onsubmit="return confirm('<?= $is_sa ? 'Delete' : 'Request deletion of' ?> <?= htmlspecialchars(addslashes($u['name'])) ?>?<?= $is_sa ? ' This cannot be undone.' : ' This will require Super Admin approval.' ?>')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="tbl-action ta-delete" title="<?= $is_sa ? 'Delete' : 'Request deletion' ?>">
                                        <i data-lucide="<?= $is_sa ? 'trash-2' : 'clock' ?>" style="width:11px;height:11px"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</main>

<script src="partials/admin.js"></script>
<script>
setTimeout(() => {
    const t = document.querySelector('.toast');
    if (t) t.style.display = 'none';
}, 4000);
</script>
</body>
</html>