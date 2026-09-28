<?php
// admin/audit_trail.php — Audit Trail / Activity Log
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../helpers/table_filters.php';
require_super_admin();

$actors = $mysqli->query("SELECT id, name FROM users WHERE role IN ('admin','super_admin') ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$actorOptions = [];
foreach ($actors as $actor) $actorOptions[(string)$actor['id']] = $actor['name'];
$actionRows = $mysqli->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetch_all(MYSQLI_ASSOC);
$actionOptions = [];
foreach ($actionRows as $actionRow) $actionOptions[$actionRow['action']] = ucwords(str_replace('_', ' ', $actionRow['action']));
$table = table_filter_query($mysqli, [
    'from_sql' => 'FROM audit_logs al LEFT JOIN users actor ON actor.id = al.user_id',
    'select_sql' => 'al.id, al.user_id, al.role, al.action, al.target_type, al.target_id, al.details, al.ip_address, al.created_at, actor.name AS admin_name',
    'summary_sql' => "COUNT(*) AS total, SUM(al.action LIKE '%approve%') AS approvals, SUM(al.action LIKE '%reject%') AS rejections, SUM(al.action LIKE '%release%') AS releases",
    'filters' => [
        'search' => ['kind'=>'search','label'=>'Keyword','placeholder'=>'Action, details, target or IP','columns'=>['al.action','al.details','al.target_type','al.ip_address','actor.name','actor.email']],
        'actor' => ['kind'=>'select','label'=>'Actor','options'=>$actorOptions,'sql'=>'al.user_id'],
        'action' => ['kind'=>'select','label'=>'Action type','options'=>$actionOptions,'sql'=>'al.action'],
        'date_from' => ['kind'=>'date','label'=>'From','sql'=>'al.created_at','operator'=>'>='],
        'date_to' => ['kind'=>'date','label'=>'To','sql'=>'al.created_at','operator'=>'<','inclusive_end'=>true],
        'ip' => ['kind'=>'search','label'=>'IP address','placeholder'=>'IPv4 or IPv6','columns'=>['al.ip_address']],
    ],
    'sort' => ['id'=>'al.id','date'=>'al.created_at','actor'=>'actor.name','action'=>'al.action','target'=>'al.target_type','ip'=>'al.ip_address'],
    'default_sort'=>'date','per_page'=>25,
]);
$actions = $table['rows'];
$f_action = $table['filters']['action'];
$f_from = $table['filters']['date_from'];
$f_to = $table['filters']['date_to'];
$has_filters = $table['has_filters'];

// --- Stats ---
$total     = $table['total'];
$approvals = (int)($table['summary']['approvals'] ?? 0);
$rejections= (int)($table['summary']['rejections'] ?? 0);
$releases  = (int)($table['summary']['releases'] ?? 0);

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

        <?php include 'partials/filter_bar.php'; ?>

        <div class="card-body no-pad">
            <?php if (empty($actions)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="shield" style="width:20px;height:20px"></i></div>
                <div class="empty-text">No actions recorded yet.</div>
            </div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?= table_sort_link($table, 'date', 'Date & Time') ?></th>
                            <th><?= table_sort_link($table, 'actor', 'Actor') ?></th>
                            <th><?= table_sort_link($table, 'action', 'Action') ?></th>
                            <th><?= table_sort_link($table, 'target', 'Target') ?></th>
                            <th><?= table_sort_link($table, 'ip', 'IP Address') ?></th>
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
                        <td style="color:var(--muted);white-space:nowrap;font-size:.78rem;"><?= date('M d, Y — h:i A', strtotime($act['created_at'])) ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="row-avatar" style="background:<?= $col ?>;width:26px;height:26px;font-size:.58rem;"><?= $init ?></div>
                                <span style="font-weight:700;font-size:.8rem;"><?= htmlspecialchars($act['admin_name'] ?? 'Unknown') ?></span>
                            </div>
                        </td>
                        <td><span class="action-badge <?= $ac ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $act['action'])), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td style="font-size:.82rem;"><?= htmlspecialchars(($act['target_type'] ?? '—') . ($act['target_id'] ? ' #' . $act['target_id'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td style="font-family:monospace;font-size:.76rem;"><?= htmlspecialchars($act['ip_address'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td style="color:var(--muted);font-size:.78rem;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($act['details'] ?? '') ?>">
                            <?= htmlspecialchars($act['details'] ?? '—') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php include 'partials/table_pager.php'; ?>
            <?php endif; ?>
        </div>
    </div>

</main>
<script src="partials/admin.js"></script>
</body>
</html>
