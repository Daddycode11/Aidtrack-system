<?php
// admin/approval_requests.php — Admin view of their submitted requests
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../helpers/table_filters.php';
require_admin();

$user_id = $_SESSION['user']['id'];
$is_sa   = is_super_admin();

$categoryOptions = ['medical'=>'Medical','burial'=>'Burial','educational'=>'Educational','livelihood'=>'Livelihood','emergency'=>'Emergency'];
$requestTypeOptions = [
    'approve_application'=>'Approve Application','reject_application'=>'Reject Application','release_funds'=>'Release Funds',
    'delete_user'=>'Delete User','update_budget'=>'Update Budget','suspend_account'=>'Suspend Account',
    'bulk_approve'=>'Bulk Approve','record_modification'=>'Record Modification',
];
$barangayRows = $mysqli->query("SELECT DISTINCT barangay FROM users WHERE barangay IS NOT NULL AND barangay <> '' ORDER BY barangay")->fetch_all(MYSQLI_ASSOC);
$barangayOptions = array_column($barangayRows, 'barangay');
$barangayOptions = array_combine($barangayOptions, $barangayOptions) ?: [];
$requestFrom = "FROM approval_requests ar LEFT JOIN users u ON ar.requested_by=u.id LEFT JOIN users sa ON ar.reviewed_by=sa.id LEFT JOIN applications app ON app.id=COALESCE(CASE WHEN ar.request_type IN ('approve_application','reject_application','release_funds') THEN ar.reference_id END, CAST(JSON_UNQUOTE(JSON_EXTRACT(ar.request_data,'$.app_id')) AS UNSIGNED)) LEFT JOIN users beneficiary ON beneficiary.id=app.user_id LEFT JOIN users target_user ON target_user.id=CAST(JSON_UNQUOTE(JSON_EXTRACT(ar.request_data,'$.user_id')) AS UNSIGNED)";
$table = table_filter_query($mysqli, [
    'from_sql'=>$requestFrom,
    'base_where'=>$is_sa ? '' : 'ar.requested_by = ?',
    'base_params'=>$is_sa ? [] : [$user_id],
    'base_types'=>$is_sa ? '' : 'i',
    'select_sql'=>"ar.*, u.name AS requester_name, u.phone AS requester_phone, u.email AS requester_email, sa.name AS reviewer_name, app.type AS app_type, app.amount_requested, beneficiary.name AS beneficiary_name, beneficiary.barangay",
    'filters'=>[
        'search'=>['kind'=>'search','label'=>'Keyword','placeholder'=>'Requester, ID or reason','columns'=>['u.name','u.phone','u.email','ar.reason','CAST(ar.id AS CHAR)','CAST(ar.reference_id AS CHAR)','beneficiary.name','target_user.name']],
        'status'=>['kind'=>'select','label'=>'Status','options'=>['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','released'=>'Linked application released'],'sql'=>'ar.status','expressions'=>['released'=>'app.amount_released > 0']],
        'type'=>['kind'=>'select','label'=>'Request type','options'=>$requestTypeOptions,'sql'=>'ar.request_type'],
        'aid_type'=>['kind'=>'select','label'=>'Aid category','options'=>$categoryOptions,'sql'=>'COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ar.request_data, \'$.app_type\')), app.type)'],
        'barangay'=>['kind'=>'select','label'=>'Barangay','options'=>$barangayOptions,'sql'=>'COALESCE(beneficiary.barangay,target_user.barangay)'],
        'date_from'=>['kind'=>'date','label'=>'Submitted from','sql'=>'ar.created_at','operator'=>'>='],
        'date_to'=>['kind'=>'date','label'=>'Submitted to','sql'=>'ar.created_at','operator'=>'<','inclusive_end'=>true],
        'amount_min'=>['kind'=>'number','label'=>'Amount from','sql'=>"CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ar.request_data, '$.amount_granted')), JSON_UNQUOTE(JSON_EXTRACT(ar.request_data, '$.amount_released'))) AS DECIMAL(12,2))",'operator'=>'>='],
        'amount_max'=>['kind'=>'number','label'=>'Amount to','sql'=>"CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ar.request_data, '$.amount_granted')), JSON_UNQUOTE(JSON_EXTRACT(ar.request_data, '$.amount_released'))) AS DECIMAL(12,2))",'operator'=>'<='],
    ],
    'sort'=>['id'=>'ar.id','requester'=>'u.name','type'=>'ar.request_type','status'=>'ar.status','submitted'=>'ar.created_at','reviewed'=>'ar.reviewed_at'],
    'default_sort'=>'submitted','per_page'=>25,
]);
$requests = $table['rows'];

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

$counts = ['all'=>0,'pending'=>0,'approved'=>0,'rejected'=>0];
$countSql = $is_sa ? "SELECT status, COUNT(*) AS total FROM approval_requests GROUP BY status" : "SELECT status, COUNT(*) AS total FROM approval_requests WHERE requested_by=? GROUP BY status";
$countStmt = $mysqli->prepare($countSql);
if (!$is_sa) $countStmt->bind_param('i', $user_id);
$countStmt->execute();
foreach ($countStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $countRow) {
    $counts[$countRow['status']] = (int)$countRow['total'];
    $counts['all'] += (int)$countRow['total'];
}
$countStmt->close();
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

        <?php include 'partials/filter_bar.php'; ?>

        <div class="card-body no-pad">
            <?php if (empty($requests)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="inbox" style="width:20px;height:20px"></i></div>
                <div class="empty-text">No approval requests yet.</div>
            </div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?= table_sort_link($table, 'id', '#') ?></th>
                            <?php if ($is_sa): ?><th><?= table_sort_link($table, 'requester', 'Requested By') ?></th><?php endif; ?>
                            <th><?= table_sort_link($table, 'type', 'Type') ?></th>
                            <th>Reason</th>
                            <th><?= table_sort_link($table, 'status', 'Status') ?></th>
                            <th>Reviewer</th>
                            <th>Review Notes</th>
                            <th><?= table_sort_link($table, 'submitted', 'Submitted') ?></th>
                            <th><?= table_sort_link($table, 'reviewed', 'Reviewed') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($requests as $req):
                        $rt  = $req['request_type'];
                        $rtc = 'rt-' . $rt;
                        $sc  = 'sp-' . $req['status'];
                        $data = json_decode($req['request_data'] ?? '{}', true);
                    ?>
                    <tr>
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
            <?php include 'partials/table_pager.php'; ?>
            <?php endif; ?>
        </div>
    </div>

</main>

<script src="partials/admin.js"></script>
</body>
</html>
