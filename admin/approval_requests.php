<?php
// admin/approval_requests.php — Admin view of their submitted requests
require_once __DIR__ . '/../helpers.php';
require_admin();

$user_id = $_SESSION['user']['id'];
$is_sa   = is_super_admin();

// SA sees all; admin sees only their own
$where_sql = $is_sa ? '' : 'WHERE ar.requested_by = ' . $user_id;

$stmt = $mysqli->prepare("
    SELECT ar.*, u.name AS requester_name,
           sa.name AS reviewer_name
    FROM approval_requests ar
    LEFT JOIN users u  ON ar.requested_by = u.id
    LEFT JOIN users sa ON ar.reviewed_by  = sa.id
    $where_sql
    ORDER BY ar.created_at DESC
");
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pending_aids         = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0] ?? 0);
$pending_sa_approvals = get_pending_approvals_count($mysqli);

$active_page   = 'approval_requests';
$page_title    = 'Approval Requests';
$page_subtitle = 'Approval Requests';

$type_labels = [
    'approve_application'  => 'Approve Application',
    'reject_application'   => 'Reject Application',
    'release_funds'        => 'Release Funds',
    'delete_user'          => 'Delete User',
    'update_budget'        => 'Update Budget',
    'suspend_account'      => 'Suspend Account',
    'bulk_approve'         => 'Bulk Approve',
    'record_modification'  => 'Record Modification',
];

$counts = [
    'all'      => count($requests),
    'pending'  => count(array_filter($requests, fn($r) => $r['status'] === 'pending')),
    'approved' => count(array_filter($requests, fn($r) => $r['status'] === 'approved')),
    'rejected' => count(array_filter($requests, fn($r) => $r['status'] === 'rejected')),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approval Requests — AIDTRACK Admin</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/admin.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
.summary-strip { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:16px; }
.ss-card { background:var(--white); border:1px solid var(--border); border-radius:var(--r); padding:14px 16px; display:flex; align-items:center; gap:12px; }
.ss-icon { width:34px; height:34px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.ss-n { font-size:1.3rem; font-weight:800; line-height:1; }
.ss-l { font-size:.66rem; font-weight:600; color:var(--muted); margin-top:2px; }
.tabs { display:flex; gap:4px; padding:14px 20px 0; border-bottom:1px solid var(--border); }
.tab { padding:8px 16px; font-size:.8rem; font-weight:700; color:var(--muted); border-radius:8px 8px 0 0; border:1px solid transparent; border-bottom:none; cursor:pointer; transition:all .15s; background:transparent; font-family:inherit; position:relative; bottom:-1px; }
.tab.active { color:var(--brand); background:var(--white); border-color:var(--border); border-bottom-color:var(--white); }
.tab:hover:not(.active) { color:var(--navy); }
.req-type { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:99px; font-size:.7rem; font-weight:700; }
.rt-approve_application { background:var(--green-lt); color:var(--green); }
.rt-release_funds { background:rgba(8,145,178,.08); color:#0891b2; }
.rt-reject_application { background:var(--red-lt); color:var(--red); }
.rt-delete_user { background:var(--red-lt); color:var(--red); }
.rt-update_budget { background:#f5f3ff; color:#7c3aed; }
.rt-bulk_approve { background:var(--green-lt); color:var(--green); }
.rt-suspend_account { background:var(--yellow-lt); color:var(--yellow); }
.rt-record_modification { background:var(--brand-lt); color:var(--brand); }
.status-pill { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:99px; font-size:.7rem; font-weight:700; }
.sp-pending  { background:var(--yellow-lt); color:var(--yellow); }
.sp-approved { background:var(--green-lt);  color:var(--green); }
.sp-rejected { background:var(--red-lt);    color:var(--red); }
@media(max-width:768px) { .summary-strip { grid-template-columns:1fr 1fr; } }
</style>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<main class="main">

    <div class="page-header">
        <div class="page-header-top">
            <div>
                <div class="page-title">Approval Requests</div>
                <div class="page-sub">
                    <?= $is_sa ? 'All admin requests requiring Super Admin review.' : 'Your submitted requests awaiting Super Admin approval.' ?>
                </div>
            </div>
            <div class="page-actions">
                <?php if ($is_sa && $pending_sa_approvals > 0): ?>
                <a href="super_admin_approvals.php" class="btn btn-sm" style="background:#f5f3ff;color:#7c3aed;border:1px solid rgba(124,58,237,.2);">
                    <i data-lucide="shield-alert" style="width:13px;height:13px"></i> Review Queue (<?= $pending_sa_approvals ?>)
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Summary -->
    <div class="summary-strip">
        <div class="ss-card">
            <div class="ss-icon" style="background:var(--brand-lt);color:var(--brand);"><i data-lucide="list" style="width:16px;height:16px"></i></div>
            <div><div class="ss-n"><?= $counts['all'] ?></div><div class="ss-l">Total Requests</div></div>
        </div>
        <div class="ss-card">
            <div class="ss-icon" style="background:var(--yellow-lt);color:var(--yellow);"><i data-lucide="clock" style="width:16px;height:16px"></i></div>
            <div><div class="ss-n" style="color:var(--yellow);"><?= $counts['pending'] ?></div><div class="ss-l">Pending</div></div>
        </div>
        <div class="ss-card">
            <div class="ss-icon" style="background:var(--green-lt);color:var(--green);"><i data-lucide="check-circle" style="width:16px;height:16px"></i></div>
            <div><div class="ss-n" style="color:var(--green);"><?= $counts['approved'] ?></div><div class="ss-l">Approved</div></div>
        </div>
        <div class="ss-card">
            <div class="ss-icon" style="background:var(--red-lt);color:var(--red);"><i data-lucide="x-circle" style="width:16px;height:16px"></i></div>
            <div><div class="ss-n" style="color:var(--red);"><?= $counts['rejected'] ?></div><div class="ss-l">Rejected</div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon" style="background:#f5f3ff;color:#7c3aed;"><i data-lucide="shield-check" style="width:14px;height:14px"></i></div>
                Request Log
                <span class="count-pill"><?= $counts['all'] ?></span>
            </div>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="filterTable('all', this)">All (<?= $counts['all'] ?>)</button>
            <button class="tab" onclick="filterTable('pending', this)">
                Pending <?php if ($counts['pending'] > 0): ?><span class="nav-badge" style="position:static;margin-left:6px;"><?= $counts['pending'] ?></span><?php endif; ?>
            </button>
            <button class="tab" onclick="filterTable('approved', this)">Approved (<?= $counts['approved'] ?>)</button>
            <button class="tab" onclick="filterTable('rejected', this)">Rejected (<?= $counts['rejected'] ?>)</button>
        </div>

        <div class="card-body no-pad">
            <?php if (empty($requests)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="inbox" style="width:20px;height:20px"></i></div>
                <div class="empty-text">No approval requests yet.</div>
            </div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table data-paginate="12">
                    <thead>
                        <tr>
                            <th>#</th>
                            <?php if ($is_sa): ?><th>Requested By</th><?php endif; ?>
                            <th>Type</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Reviewer</th>
                            <th>Review Notes</th>
                            <th>Submitted</th>
                            <th>Reviewed</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($requests as $req):
                        $rt  = $req['request_type'];
                        $rtc = 'rt-' . $rt;
                        $sc  = 'sp-' . $req['status'];
                        $data = json_decode($req['request_data'] ?? '{}', true);
                    ?>
                    <tr data-status="<?= $req['status'] ?>">
                        <td style="color:var(--muted);font-size:.74rem;"><?= $req['id'] ?></td>
                        <?php if ($is_sa): ?>
                        <td style="font-weight:700;font-size:.82rem;"><?= htmlspecialchars($req['requester_name'] ?? '—') ?></td>
                        <?php endif; ?>
                        <td><span class="req-type <?= $rtc ?>"><?= htmlspecialchars($type_labels[$rt] ?? ucwords(str_replace('_', ' ', $rt))) ?></span></td>
                        <td style="font-size:.8rem;max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($req['reason'] ?? '') ?>">
                            <?= htmlspecialchars(substr($req['reason'] ?? '—', 0, 60)) ?><?= strlen($req['reason'] ?? '') > 60 ? '…' : '' ?>
                        </td>
                        <td><span class="status-pill <?= $sc ?>">
                            <?= ucfirst($req['status']) ?>
                        </span></td>
                        <td style="font-size:.8rem;color:var(--muted);"><?= htmlspecialchars($req['reviewer_name'] ?? '—') ?></td>
                        <td style="font-size:.78rem;color:var(--muted);max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($req['review_notes'] ?? '') ?>">
                            <?= htmlspecialchars($req['review_notes'] ? substr($req['review_notes'], 0, 50) . (strlen($req['review_notes']) > 50 ? '…' : '') : '—') ?>
                        </td>
                        <td style="color:var(--muted);white-space:nowrap;font-size:.76rem;"><?= date('M d, Y g:i A', strtotime($req['created_at'])) ?></td>
                        <td style="color:var(--muted);white-space:nowrap;font-size:.76rem;">
                            <?= $req['reviewed_at'] ? date('M d, Y g:i A', strtotime($req['reviewed_at'])) : '—' ?>
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
function filterTable(status, btn) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
    });
}
</script>
</body>
</html>
