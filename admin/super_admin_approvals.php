<?php
// admin/super_admin_approvals.php — Super Admin reviews and executes pending requests
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../helpers/notify.php';
require_once __DIR__ . '/../helpers/table_filters.php';
require_super_admin();

$sa_id = $_SESSION['user']['id'];

// ─────────────────────────────────────────────────────────────
// Execute action when SA approves/rejects
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { header('Location: super_admin_approvals.php?err=csrf'); exit; }

    $req_id      = (int)($_POST['request_id'] ?? 0);
    $decision    = $_POST['decision'] ?? '';   // 'approve' or 'reject'
    $review_note = trim($_POST['review_notes'] ?? '');

    if (!$req_id || !in_array($decision, ['approve', 'reject'])) {
        header('Location: super_admin_approvals.php?toast=invalid'); exit;
    }

    // Fetch request
    $stmt = $mysqli->prepare("SELECT * FROM approval_requests WHERE id=? AND status='pending'");
    $stmt->bind_param('i', $req_id);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$req) { header('Location: super_admin_approvals.php?toast=not_found'); exit; }

    $data = json_decode($req['request_data'] ?? '{}', true);

    // ── Update request status ──
    $stmt = $mysqli->prepare("
        UPDATE approval_requests
        SET status=?, reviewed_by=?, review_notes=?, reviewed_at=NOW()
        WHERE id=?
    ");
    $decisionStatus = $decision === 'approve' ? 'approved' : 'rejected';
    $stmt->bind_param('sisi', $decisionStatus, $sa_id, $review_note, $req_id);
    $stmt->execute();
    $stmt->close();

    log_audit($mysqli, ($decision === 'approve' ? 'sa_approve_request' : 'sa_reject_request'),
        'approval_request', $req_id,
        "Type: {$req['request_type']} — Notes: $review_note");

    if ($decision === 'approve') {
        // ── Execute the approved action ──
        $type    = $req['request_type'];
        $ref_id  = (int)$req['reference_id'];
        $req_by  = (int)$req['requested_by'];

        if ($type === 'approve_application') {
            $amount_granted = floatval($data['amount_granted'] ?? 0);
            $stmt = $mysqli->prepare("UPDATE applications SET status='approved', rejection_reason=NULL, amount_granted=? WHERE id=?");
            $stmt->bind_param('di', $amount_granted, $ref_id); $stmt->execute(); $stmt->close();

            $details = "Amount granted: ₱" . number_format($amount_granted, 2) . " (SA approved)";
            $stmt = $mysqli->prepare("INSERT INTO admin_actions (application_id, admin_id, action, details) VALUES (?,?,'approve',?)");
            $stmt->bind_param('iis', $ref_id, $sa_id, $details); $stmt->execute(); $stmt->close();

            $user_id = (int)($data['user_id'] ?? 0);
            $app_type = $data['app_type'] ?? '';
            if ($user_id && $app_type) notify_status_change($mysqli, $user_id, $app_type, 'approved', $ref_id);
            log_audit($mysqli, 'approve_application', 'application', $ref_id, $details);

            // Notify the requesting admin
            $notif_msg = "Your approval request for Application #$ref_id has been approved by Super Admin. Amount granted: ₱" . number_format($amount_granted, 2);
            $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $stmt->bind_param('is', $req_by, $notif_msg); $stmt->execute(); $stmt->close();
        }

        elseif ($type === 'release_funds') {
            $amount_released = floatval($data['amount_released'] ?? 0);
            $stmt = $mysqli->prepare("UPDATE applications SET amount_released=? WHERE id=? AND status='approved'");
            $stmt->bind_param('di', $amount_released, $ref_id); $stmt->execute(); $stmt->close();

            $details = "Amount released: ₱" . number_format($amount_released, 2) . " (SA approved)";
            $stmt = $mysqli->prepare("INSERT INTO admin_actions (application_id, admin_id, action, details) VALUES (?,?,'release',?)");
            $stmt->bind_param('iis', $ref_id, $sa_id, $details); $stmt->execute(); $stmt->close();

            $user_id = (int)($data['user_id'] ?? 0);
            $app_type = $data['app_type'] ?? '';
            if ($user_id && $app_type) notify_status_change($mysqli, $user_id, $app_type, 'released', $ref_id);
            log_audit($mysqli, 'release_funds', 'application', $ref_id, $details);

            $notif_msg = "Your release request for Application #$ref_id has been approved. ₱" . number_format($amount_released, 2) . " released.";
            $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $stmt->bind_param('is', $req_by, $notif_msg); $stmt->execute(); $stmt->close();
        }

        elseif ($type === 'delete_user') {
            $target_uid = (int)($data['user_id'] ?? $ref_id);
            $stmt = $mysqli->prepare("DELETE FROM users WHERE id=? AND role != 'super_admin'");
            $stmt->bind_param('i', $target_uid); $stmt->execute(); $stmt->close();
            $details = "Deleted user ID $target_uid: " . ($data['user_name'] ?? '');
            log_audit($mysqli, 'delete_user', 'user', $target_uid, $details . " (SA approved)");

            $notif_msg = "Your request to delete user '{$data['user_name']}' has been approved and executed.";
            $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $stmt->bind_param('is', $req_by, $notif_msg); $stmt->execute(); $stmt->close();
        }

        elseif ($type === 'bulk_approve') {
            $ids = $data['ids'] ?? [];
            foreach ($ids as $bid) {
                $bid = (int)$bid;
                $stmt = $mysqli->prepare("UPDATE applications SET status='approved', rejection_reason=NULL WHERE id=? AND status='pending'");
                $stmt->bind_param('i', $bid); $stmt->execute(); $stmt->close();
                $stmt = $mysqli->prepare("INSERT INTO admin_actions (application_id, admin_id, action, details) VALUES (?,?,'approve','SA bulk approved')");
                $stmt->bind_param('ii', $bid, $sa_id); $stmt->execute(); $stmt->close();
                $info = $mysqli->prepare("SELECT user_id, type FROM applications WHERE id=?");
                $info->bind_param('i', $bid); $info->execute();
                $app_info = $info->get_result()->fetch_assoc(); $info->close();
                if ($app_info) notify_status_change($mysqli, $app_info['user_id'], $app_info['type'], 'approved', $bid);
            }
            $count = count($ids);
            log_audit($mysqli, 'bulk_approve', 'applications', null, "SA approved bulk of $count applications");

            $notif_msg = "Your bulk approve request for $count applications has been approved and executed.";
            $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $stmt->bind_param('is', $req_by, $notif_msg); $stmt->execute(); $stmt->close();
        }

        header('Location: super_admin_approvals.php?toast=executed'); exit;

    } else {
        // Notify admin of rejection
        $notif_msg = "Your request ({$req['request_type']}) was rejected by Super Admin." .
            ($review_note ? " Notes: $review_note" : '');
        $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt->bind_param('is', $req['requested_by'], $notif_msg); $stmt->execute(); $stmt->close();
        header('Location: super_admin_approvals.php?toast=sa_rejected'); exit;
    }
}

// ─────────────────────────────────────────────────────────────
// Fetch pending requests
// ─────────────────────────────────────────────────────────────
$filter = $_GET['status'] ?? 'pending';
$categoryOptions = ['medical'=>'Medical','burial'=>'Burial','educational'=>'Educational','livelihood'=>'Livelihood','emergency'=>'Emergency'];
$requestTypeOptions = ['approve_application'=>'Approve Application','reject_application'=>'Reject Application','release_funds'=>'Release Funds','delete_user'=>'Delete User','update_budget'=>'Update Budget','suspend_account'=>'Suspend Account','bulk_approve'=>'Bulk Approve','record_modification'=>'Record Modification'];
$barangayRows = $mysqli->query("SELECT DISTINCT barangay FROM users WHERE barangay IS NOT NULL AND barangay <> '' ORDER BY barangay")->fetch_all(MYSQLI_ASSOC);
$barangayValues = array_column($barangayRows, 'barangay');
$barangayOptions = array_combine($barangayValues, $barangayValues) ?: [];
$table = table_filter_query($mysqli, [
    'from_sql'=>"FROM approval_requests ar LEFT JOIN users u ON ar.requested_by=u.id LEFT JOIN users sa ON ar.reviewed_by=sa.id LEFT JOIN applications app ON app.id=COALESCE(CASE WHEN ar.request_type IN ('approve_application','reject_application','release_funds') THEN ar.reference_id END, CAST(JSON_UNQUOTE(JSON_EXTRACT(ar.request_data,'$.app_id')) AS UNSIGNED)) LEFT JOIN users beneficiary ON beneficiary.id=app.user_id LEFT JOIN users target_user ON target_user.id=CAST(JSON_UNQUOTE(JSON_EXTRACT(ar.request_data,'$.user_id')) AS UNSIGNED)",
    'select_sql'=>'ar.*, u.name AS requester_name, u.role AS requester_role, u.phone AS requester_phone, u.email AS requester_email, sa.name AS reviewer_name, app.type AS app_type, beneficiary.name AS beneficiary_name, beneficiary.barangay',
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
$filter = $table['filters']['status'] ?: 'all';

$pending_count  = get_pending_approvals_count($mysqli);
$pending_aids   = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0] ?? 0);

$active_page    = 'super_admin_approvals';
$page_title     = 'SA Approval Queue';
$page_subtitle  = 'Approval Queue';

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

$type_icons = [
    'approve_application' => 'check-circle',
    'release_funds'       => 'banknote',
    'reject_application'  => 'x-circle',
    'delete_user'         => 'trash-2',
    'update_budget'       => 'wallet',
    'bulk_approve'        => 'layers',
    'suspend_account'     => 'user-x',
    'record_modification' => 'file-edit',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SA Approval Queue — AIDTRACK</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/admin.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
.filter-tabs { display:flex; gap:6px; margin-bottom:18px; flex-wrap:wrap; }
.ftab { padding:7px 16px; border-radius:8px; font-family:inherit; font-size:.8rem; font-weight:700; cursor:pointer; border:1.5px solid var(--border); background:var(--white); color:var(--muted); text-decoration:none; transition:all .15s; }
.ftab.active, .ftab:hover { border-color:var(--brand); color:var(--brand); background:var(--brand-lt); }

.req-card { background:var(--white); border:1px solid var(--border); border-radius:var(--r); margin-bottom:12px; overflow:hidden; transition:box-shadow .18s; }
.req-card:hover { box-shadow:var(--sh-md); }
.req-card-header { display:flex; align-items:flex-start; gap:14px; padding:16px 20px; border-bottom:1px solid var(--border); }
.req-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.req-title { font-size:.9rem; font-weight:800; color:var(--navy); }
.req-meta  { font-size:.74rem; color:var(--muted); margin-top:3px; display:flex; flex-wrap:wrap; gap:10px; }
.req-meta span { display:flex; align-items:center; gap:4px; }
.req-card-body { padding:14px 20px; display:flex; align-items:flex-start; gap:16px; flex-wrap:wrap; }
.req-detail { flex:1; min-width:180px; }
.rd-label { font-size:.68rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px; }
.rd-value { font-size:.85rem; font-weight:700; color:var(--navy); }
.req-card-footer { padding:12px 20px; border-top:1px solid var(--border); background:var(--bg); display:flex; gap:10px; align-items:center; flex-wrap:wrap; }

.status-pill { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:99px; font-size:.7rem; font-weight:700; }
.sp-pending  { background:var(--yellow-lt); color:var(--yellow); }
.sp-approved { background:var(--green-lt);  color:var(--green); }
.sp-rejected { background:var(--red-lt);    color:var(--red); }

.toast { position:fixed; bottom:24px; right:24px; z-index:999; display:flex; align-items:center; gap:10px; padding:12px 18px; border-radius:10px; font-size:.83rem; font-weight:600; box-shadow:0 8px 28px rgba(0,0,0,.15); animation:toastIn .3s ease,toastOut .4s ease 3s forwards; }
.toast-executed    { background:var(--green-lt); color:var(--green); border:1px solid rgba(22,163,74,.2); }
.toast-sa_rejected { background:var(--red-lt);   color:var(--red);   border:1px solid rgba(220,38,38,.2); }
.toast-invalid,.toast-not_found { background:var(--red-lt); color:var(--red); border:1px solid rgba(220,38,38,.2); }
@keyframes toastIn  { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:none} }
@keyframes toastOut { from{opacity:1} to{opacity:0;pointer-events:none} }

@media(max-width:600px) { .req-card-body { flex-direction:column; } }
</style>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<?php if (!empty($_GET['toast'])): ?>
<?php $t = $_GET['toast']; ?>
<div class="toast toast-<?= $t ?>">
    <i data-lucide="<?= $t === 'executed' ? 'check-circle' : 'x-circle' ?>" style="width:16px;height:16px"></i>
    <?= match($t) {
        'executed'    => 'Request approved and action executed successfully.',
        'sa_rejected' => 'Request rejected. Admin has been notified.',
        'not_found'   => 'Request not found or already processed.',
        default       => 'Action completed.'
    } ?>
</div>
<?php endif; ?>

<main class="main">

    <div class="page-header">
        <div class="page-header-top">
            <div>
                <div class="page-title">SA Approval Queue</div>
                <div class="page-sub">Review and action admin requests requiring Super Admin authority.</div>
            </div>
            <div class="page-actions">
                <?php if ($pending_count > 0): ?>
                <span class="count-pill" style="background:#f5f3ff;color:#7c3aed;"><?= $pending_count ?> pending</span>
                <?php else: ?>
                <span class="count-pill" style="background:var(--green-lt);color:var(--green);">All clear</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'partials/filter_bar.php'; ?>

    <?php if (empty($requests)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="shield-check" style="width:20px;height:20px"></i></div>
                <div class="empty-text"><?= $filter === 'pending' ? 'No pending requests — queue is clear.' : 'No requests found.' ?></div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <?php foreach ($requests as $req):
        $rt    = $req['request_type'];
        $data  = json_decode($req['request_data'] ?? '{}', true);
        $sc    = 'sp-' . $req['status'];
        $icon  = $type_icons[$rt] ?? 'activity';
        $label = $type_labels[$rt] ?? ucwords(str_replace('_', ' ', $rt));

        $icon_bg = match(true) {
            in_array($rt, ['approve_application','bulk_approve']) => ['background:var(--green-lt)', 'color:var(--green)'],
            in_array($rt, ['release_funds'])                      => ['background:rgba(8,145,178,.08)', 'color:#0891b2'],
            in_array($rt, ['delete_user','reject_application'])   => ['background:var(--red-lt)', 'color:var(--red)'],
            in_array($rt, ['update_budget'])                      => ['background:#f5f3ff', 'color:#7c3aed'],
            default                                               => ['background:var(--brand-lt)', 'color:var(--brand)']
        };
    ?>
    <div class="req-card">
        <div class="req-card-header">
            <div class="req-icon" style="<?= $icon_bg[0] ?>;<?= $icon_bg[1] ?>">
                <i data-lucide="<?= $icon ?>" style="width:18px;height:18px"></i>
            </div>
            <div style="flex:1;">
                <div class="req-title"><?= htmlspecialchars($label) ?></div>
                <div class="req-meta">
                    <span><i data-lucide="user" style="width:11px;height:11px"></i><?= htmlspecialchars($req['requester_name'] ?? '—') ?> (<?= $req['requester_role'] ?? '' ?>)</span>
                    <span><i data-lucide="calendar" style="width:11px;height:11px"></i><?= date('M d, Y g:i A', strtotime($req['created_at'])) ?></span>
                    <span><i data-lucide="hash" style="width:11px;height:11px"></i>Request #<?= $req['id'] ?></span>
                </div>
            </div>
            <span class="status-pill <?= $sc ?>">
                <?= ucfirst($req['status']) ?>
            </span>
        </div>

        <div class="req-card-body">
            <div class="req-detail">
                <div class="rd-label">Reason / Description</div>
                <div class="rd-value" style="font-weight:500;line-height:1.5;"><?= nl2br(htmlspecialchars($req['reason'] ?? '—')) ?></div>
            </div>

            <?php if (!empty($data)): ?>
            <div class="req-detail">
                <div class="rd-label">Request Details</div>
                <?php if (isset($data['amount_granted'])): ?>
                <div class="rd-value">Amount to Grant: <span style="color:var(--green);font-family:monospace;">₱<?= number_format($data['amount_granted'], 2) ?></span></div>
                <?php endif; ?>
                <?php if (isset($data['amount_released'])): ?>
                <div class="rd-value">Amount to Release: <span style="color:#0891b2;font-family:monospace;">₱<?= number_format($data['amount_released'], 2) ?></span></div>
                <?php endif; ?>
                <?php if (isset($data['app_id'])): ?>
                <div class="rd-value" style="margin-top:4px;">
                    Application: <a href="view_application.php?id=<?= $data['app_id'] ?>" style="color:var(--brand);font-weight:700;">#<?= $data['app_id'] ?></a>
                    <?php if (!empty($data['app_type'])): ?><span style="color:var(--muted);font-weight:500;">(<?= ucfirst($data['app_type']) ?>)</span><?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (isset($data['user_name'])): ?>
                <div class="rd-value">Target User: <?= htmlspecialchars($data['user_name']) ?> (<?= htmlspecialchars($data['user_role'] ?? '') ?>)</div>
                <?php endif; ?>
                <?php if (isset($data['count'])): ?>
                <div class="rd-value"><?= $data['count'] ?> applications in bulk</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($req['status'] !== 'pending' && $req['reviewer_name']): ?>
            <div class="req-detail">
                <div class="rd-label">Reviewed By</div>
                <div class="rd-value"><?= htmlspecialchars($req['reviewer_name']) ?></div>
                <?php if ($req['review_notes']): ?>
                <div style="font-size:.78rem;color:var(--muted);margin-top:4px;line-height:1.5;"><?= nl2br(htmlspecialchars($req['review_notes'])) ?></div>
                <?php endif; ?>
                <div style="font-size:.72rem;color:var(--muted);margin-top:4px;"><?= $req['reviewed_at'] ? date('M d, Y g:i A', strtotime($req['reviewed_at'])) : '' ?></div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($req['status'] === 'pending'): ?>
        <div class="req-card-footer">
            <form method="post" id="form_approve_<?= $req['id'] ?>" style="display:contents;">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                <input type="hidden" name="decision" value="approve">
                <input type="hidden" name="review_notes" id="notes_approve_<?= $req['id'] ?>">
            </form>
            <form method="post" id="form_reject_<?= $req['id'] ?>" style="display:contents;">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                <input type="hidden" name="decision" value="reject">
                <input type="hidden" name="review_notes" id="notes_reject_<?= $req['id'] ?>">
            </form>

            <button class="btn btn-sm" style="background:var(--green);color:#fff;"
                    onclick="submitDecision(<?= $req['id'] ?>, 'approve')">
                <i data-lucide="check-circle" style="width:13px;height:13px"></i> Approve & Execute
            </button>
            <button class="btn btn-danger btn-sm"
                    onclick="submitDecision(<?= $req['id'] ?>, 'reject')">
                <i data-lucide="x-circle" style="width:13px;height:13px"></i> Reject
            </button>
            <span style="font-size:.74rem;color:var(--muted);flex:1;text-align:right;">
                Submitted <?= date('M d, Y', strtotime($req['created_at'])) ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php include 'partials/table_pager.php'; ?>
    <?php endif; ?>

</main>

<!-- Review Notes Modal -->
<div class="modal-overlay" id="reviewModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">
                <div class="card-title-icon" style="width:28px;height:28px;background:#f5f3ff;color:#7c3aed;"><i data-lucide="shield" style="width:14px;height:14px"></i></div>
                <span id="reviewModalTitle">Approve Request</span>
            </div>
            <button class="modal-close" onclick="closeReviewModal()"><i data-lucide="x" style="width:14px;height:14px"></i></button>
        </div>
        <div class="modal-body">
            <p class="modal-desc" id="reviewModalDesc"></p>
            <label style="font-size:.76rem;font-weight:700;color:var(--slate);display:block;margin-bottom:6px;">Review Notes (optional)</label>
            <textarea id="reviewNotes" placeholder="Add a note for the requesting admin…" style="width:100%;min-height:80px;font-family:inherit;font-size:.85rem;border:1.5px solid var(--border);border-radius:8px;padding:10px 12px;outline:none;resize:vertical;"></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline btn-sm" onclick="closeReviewModal()">Cancel</button>
            <button class="btn btn-sm" id="reviewConfirmBtn" style="background:var(--green);color:#fff;" onclick="confirmDecision()">
                <i data-lucide="check-circle" style="width:13px;height:13px"></i>
                <span id="reviewConfirmLabel">Approve & Execute</span>
            </button>
        </div>
    </div>
</div>

<script src="partials/admin.js"></script>
<script>
let _pendingReqId   = null;
let _pendingDecision = null;

function submitDecision(reqId, decision) {
    _pendingReqId    = reqId;
    _pendingDecision = decision;

    const isApprove = decision === 'approve';
    document.getElementById('reviewModalTitle').textContent   = isApprove ? 'Approve & Execute Request' : 'Reject Request';
    document.getElementById('reviewModalDesc').textContent    = isApprove
        ? 'This action will immediately execute the requested operation. Add an optional note for the admin.'
        : 'Provide a reason for rejecting this request. The admin will be notified.';
    document.getElementById('reviewConfirmLabel').textContent = isApprove ? 'Approve & Execute' : 'Reject';
    document.getElementById('reviewConfirmBtn').style.background = isApprove ? 'var(--green)' : 'var(--red)';
    document.getElementById('reviewNotes').value = '';
    document.getElementById('reviewModal').classList.add('open');
    lucide.createIcons();
}

function confirmDecision() {
    const notes = document.getElementById('reviewNotes').value.trim();
    const form  = document.getElementById('form_' + _pendingDecision + '_' + _pendingReqId);
    document.getElementById('notes_' + _pendingDecision + '_' + _pendingReqId).value = notes;
    form.submit();
}

function closeReviewModal() { document.getElementById('reviewModal').classList.remove('open'); }
document.getElementById('reviewModal').addEventListener('click', function(e) { if (e.target === this) closeReviewModal(); });

setTimeout(() => { const t = document.querySelector('.toast'); if (t) t.style.display = 'none'; }, 4500);
</script>
</body>
</html>
