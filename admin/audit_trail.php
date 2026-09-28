<?php
// admin/audit_trail.php — Audit Trail / Activity Log
require_once __DIR__ . '/../helpers.php';
require_admin();

// --- Filters ---
$f_action = $_GET['action_filter'] ?? '';
$f_from   = $_GET['from'] ?? '';
$f_to     = $_GET['to'] ?? '';

$where = ['1=1']; $params = []; $types = '';
if ($f_action) { $where[] = 'aa.action = ?'; $params[] = $f_action; $types .= 's'; }
if ($f_from)   { $where[] = 'DATE(aa.created_at) >= ?'; $params[] = $f_from; $types .= 's'; }
if ($f_to)     { $where[] = 'DATE(aa.created_at) <= ?'; $params[] = $f_to;   $types .= 's'; }
$where_sql = implode(' AND ', $where);
$has_filters = $f_action || $f_from || $f_to;

// --- Fetch Actions ---
$stmt = $mysqli->prepare("
    SELECT aa.*, adm.name AS admin_name, app.type AS app_type, app.amount_requested,
           u.name AS applicant_name
    FROM admin_actions aa
    LEFT JOIN users adm ON aa.admin_id = adm.id
    LEFT JOIN applications app ON aa.application_id = app.id
    LEFT JOIN users u ON app.user_id = u.id
    WHERE $where_sql
    ORDER BY aa.created_at DESC
");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$actions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Stats ---
$total     = count($actions);
$approvals = count(array_filter($actions, fn($a) => $a['action'] === 'approve'));
$rejections= count(array_filter($actions, fn($a) => $a['action'] === 'reject'));
$releases  = count(array_filter($actions, fn($a) => $a['action'] === 'release'));

// Page config
$active_page   = 'audit_trail';
$page_title    = 'Audit Trail';
$page_subtitle = 'Audit Trail';
$pending_aids  = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0] ?? 0);
$colors = ['#1a56db','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Audit Trail — AIDTRACK Admin</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/admin.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
.stats-strip { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:16px; }
.ss-card { background:var(--white); border:1px solid var(--border); border-radius:var(--r); padding:14px 16px; display:flex; align-items:center; gap:12px; }
.ss-icon { width:34px; height:34px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.ss-n { font-size:1.3rem; font-weight:800; line-height:1; }
.ss-l { font-size:.66rem; font-weight:600; color:var(--muted); margin-top:2px; }
.filter-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:12px 20px; border-bottom:1px solid var(--border); background:var(--bg); }
.filter-bar select, .filter-bar input { font-family:inherit; font-size:.8rem; color:var(--navy); background:var(--white); border:1.5px solid var(--border); border-radius:8px; padding:7px 12px; outline:none; }
.filter-bar select:focus, .filter-bar input:focus { border-color:var(--brand); }
.action-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:99px; font-size:.72rem; font-weight:700; }
.ab-approve { background:var(--green-lt); color:var(--green); }
.ab-reject { background:var(--red-lt); color:var(--red); }
.ab-release { background:rgba(8,145,178,.08); color:#0891b2; }
@media(max-width:768px) { .stats-strip { grid-template-columns:1fr 1fr; } }
@media(max-width:480px) { .stats-strip { grid-template-columns:1fr; } }
</style>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<main class="main">

    <div class="page-header">
        <div class="page-header-top">
            <div>
                <div class="page-title">Audit Trail</div>
                <div class="page-sub">Track all administrative actions performed on applications.</div>
            </div>
            <div class="page-actions">
                <span class="count-pill"><?= $total ?> actions</span>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-strip">
        <div class="ss-card">
            <div class="ss-icon" style="background:var(--brand-lt);color:var(--brand);"><i data-lucide="activity" style="width:16px;height:16px"></i></div>
            <div><div class="ss-n"><?= $total ?></div><div class="ss-l">Total Actions</div></div>
        </div>
        <div class="ss-card">
            <div class="ss-icon" style="background:var(--green-lt);color:var(--green);"><i data-lucide="check-circle" style="width:16px;height:16px"></i></div>
            <div><div class="ss-n" style="color:var(--green);"><?= $approvals ?></div><div class="ss-l">Approvals</div></div>
        </div>
        <div class="ss-card">
            <div class="ss-icon" style="background:var(--red-lt);color:var(--red);"><i data-lucide="x-circle" style="width:16px;height:16px"></i></div>
            <div><div class="ss-n" style="color:var(--red);"><?= $rejections ?></div><div class="ss-l">Rejections</div></div>
        </div>
        <div class="ss-card">
            <div class="ss-icon" style="background:rgba(8,145,178,.08);color:#0891b2;"><i data-lucide="banknote" style="width:16px;height:16px"></i></div>
            <div><div class="ss-n" style="color:#0891b2;"><?= $releases ?></div><div class="ss-l">Releases</div></div>
        </div>
    </div>

    <!-- Actions Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-purple"><i data-lucide="shield-check" style="width:14px;height:14px"></i></div>
                Activity Log
            </div>
        </div>

        <form method="get" class="filter-bar">
            <i data-lucide="filter" style="width:14px;height:14px;color:var(--muted)"></i>
            <select name="action_filter">
                <option value="">All Actions</option>
                <option value="approve" <?= $f_action==='approve'?'selected':'' ?>>Approve</option>
                <option value="reject"  <?= $f_action==='reject'?'selected':''  ?>>Reject</option>
                <option value="release" <?= $f_action==='release'?'selected':'' ?>>Release</option>
            </select>
            <input type="date" name="from" value="<?= htmlspecialchars($f_from) ?>" title="From">
            <input type="date" name="to" value="<?= htmlspecialchars($f_to) ?>" title="To">
            <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="search" style="width:12px;height:12px"></i> Filter</button>
            <?php if ($has_filters): ?>
            <a href="audit_trail.php" class="btn btn-outline btn-sm"><i data-lucide="x" style="width:12px;height:12px"></i> Clear</a>
            <?php endif; ?>
        </form>

        <div class="card-body no-pad">
            <?php if (empty($actions)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="shield" style="width:20px;height:20px"></i></div>
                <div class="empty-text">No actions recorded yet.</div>
            </div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table data-paginate="15">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date & Time</th>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>App. ID</th>
                            <th>Applicant</th>
                            <th>Type</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($actions as $i => $act):
                        $ac = match($act['action']) {
                            'approve' => 'ab-approve',
                            'reject'  => 'ab-reject',
                            'release' => 'ab-release',
                            default   => ''
                        };
                        $col  = $colors[$i % count($colors)];
                        $init = strtoupper(substr($act['admin_name'] ?? 'A', 0, 2));
                    ?>
                    <tr>
                        <td style="color:var(--muted);font-size:.74rem;"><?= $act['id'] ?></td>
                        <td style="color:var(--muted);white-space:nowrap;font-size:.78rem;"><?= date('M d, Y — h:i A', strtotime($act['created_at'])) ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="row-avatar" style="background:<?= $col ?>;width:26px;height:26px;font-size:.58rem;"><?= $init ?></div>
                                <span style="font-weight:700;font-size:.8rem;"><?= htmlspecialchars($act['admin_name'] ?? 'Unknown') ?></span>
                            </div>
                        </td>
                        <td><span class="action-badge <?= $ac ?>"><?= ucfirst($act['action']) ?></span></td>
                        <td style="font-weight:600;">#<?= $act['application_id'] ?></td>
                        <td style="font-size:.82rem;"><?= htmlspecialchars($act['applicant_name'] ?? '—') ?></td>
                        <td style="font-size:.82rem;"><?= ucfirst($act['app_type'] ?? '—') ?></td>
                        <td style="color:var(--muted);font-size:.78rem;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($act['details'] ?? '') ?>">
                            <?= htmlspecialchars($act['details'] ?? '—') ?>
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
</body>
</html>
