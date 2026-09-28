<?php
// admin/applications.php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../helpers/notify.php';
require_once __DIR__ . '/../helpers/table_filters.php';
require_admin();

$admin_id   = $_SESSION['user']['id'] ?? 0;
$is_sa      = is_super_admin();
$toast_msg  = '';
$toast_type = 'approved';

// ─────────────────────────────────────────────────────────────
// Batch action POST
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'batch') {
    if (!csrf_verify()) { header('Location: applications.php?err=csrf'); exit; }

    $batch_action = $_POST['batch_action'] ?? '';
    $ids          = array_map('intval', $_POST['batch_ids'] ?? []);
    $count        = count($ids);

    if ($batch_action === 'approve' && !empty($ids)) {
        // Bulk approve above threshold requires SA approval
        if (!$is_sa && $count > SA_APPROVAL_BULK_THRESHOLD) {
            create_approval_request($mysqli, $admin_id, 'bulk_approve', null,
                ['ids' => $ids, 'count' => $count],
                "Batch approve $count applications"
            );
            log_audit($mysqli, 'request_bulk_approve', 'applications', null, "Requested bulk approval for $count apps");
            header("Location: applications.php?toast=sa_pending"); exit;
        }
        $processed = 0;
        foreach ($ids as $bid) {
            $stmt = $mysqli->prepare("UPDATE applications SET status='approved', rejection_reason=NULL WHERE id=? AND status='pending'");
            $stmt->bind_param('i', $bid); $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $processed++;
                $stmt2 = $mysqli->prepare("INSERT INTO admin_actions (application_id, admin_id, action) VALUES (?,?,'approve')");
                $stmt2->bind_param('ii', $bid, $admin_id); $stmt2->execute(); $stmt2->close();
                $info = $mysqli->prepare("SELECT user_id, type FROM applications WHERE id=?");
                $info->bind_param('i', $bid); $info->execute();
                $app_info = $info->get_result()->fetch_assoc(); $info->close();
                if ($app_info) notify_status_change($mysqli, $app_info['user_id'], $app_info['type'], 'approved', $bid);
            }
            $stmt->close();
        }
        log_audit($mysqli, 'batch_approve', 'applications', null, "Batch approved $processed applications");
        header("Location: applications.php?toast=batch_approved&count=$processed"); exit;
    }

    if ($batch_action === 'reject' && !empty($ids)) {
        $reason = trim($_POST['batch_reason'] ?? 'Batch rejected by admin.');
        $processed = 0;
        foreach ($ids as $bid) {
            $stmt = $mysqli->prepare("UPDATE applications SET status='rejected', rejection_reason=? WHERE id=? AND status='pending'");
            $stmt->bind_param('si', $reason, $bid); $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $processed++;
                $stmt2 = $mysqli->prepare("INSERT INTO admin_actions (application_id, admin_id, action, details) VALUES (?,?,'reject',?)");
                $stmt2->bind_param('iis', $bid, $admin_id, $reason); $stmt2->execute(); $stmt2->close();
                $info = $mysqli->prepare("SELECT user_id, type FROM applications WHERE id=?");
                $info->bind_param('i', $bid); $info->execute();
                $app_info = $info->get_result()->fetch_assoc(); $info->close();
                if ($app_info) notify_status_change($mysqli, $app_info['user_id'], $app_info['type'], 'rejected', $bid);
            }
            $stmt->close();
        }
        log_audit($mysqli, 'batch_reject', 'applications', null, "Batch rejected $processed applications. Reason: $reason");
        header("Location: applications.php?toast=batch_rejected&count=$processed"); exit;
    }
}

// ─────────────────────────────────────────────────────────────
// Single approve / reject / release POST
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    if (!csrf_verify()) { header('Location: applications.php?err=csrf'); exit; }

    $action  = strtolower($_POST['action']);
    $app_id  = intval($_POST['id']);

    // ── Reject ──
    if ($action === 'rejected' && !empty($_POST['reason'])) {
        $reason = trim($_POST['reason']);
        $stmt = $mysqli->prepare("UPDATE applications SET status='rejected', rejection_reason=? WHERE id=?");
        $stmt->bind_param('si', $reason, $app_id); $stmt->execute(); $stmt->close();

        $stmt = $mysqli->prepare("INSERT INTO admin_actions (application_id, admin_id, action, details) VALUES (?,?,'reject',?)");
        $stmt->bind_param('iis', $app_id, $admin_id, $reason); $stmt->execute(); $stmt->close();

        $info = $mysqli->prepare("SELECT user_id, type FROM applications WHERE id=?");
        $info->bind_param('i', $app_id); $info->execute();
        $app_info = $info->get_result()->fetch_assoc(); $info->close();
        if ($app_info) notify_status_change($mysqli, $app_info['user_id'], $app_info['type'], 'rejected', $app_id);

        log_audit($mysqli, 'reject_application', 'application', $app_id, "Reason: $reason");
        header('Location: applications.php?toast=rejected'); exit;
    }

    // ── Approve ──
    if ($action === 'approved') {
        $amount_granted = floatval($_POST['amount_granted'] ?? 0);

        if (needs_sa_approval('approve_application', ['amount_granted' => $amount_granted])) {
            // Route to SA approval queue
            $info = $mysqli->prepare("SELECT user_id, type, amount_requested FROM applications WHERE id=?");
            $info->bind_param('i', $app_id); $info->execute();
            $app_info = $info->get_result()->fetch_assoc(); $info->close();

            create_approval_request($mysqli, $admin_id, 'approve_application', $app_id,
                ['app_id' => $app_id, 'amount_granted' => $amount_granted,
                 'app_type' => $app_info['type'] ?? '', 'user_id' => $app_info['user_id'] ?? 0],
                "Approve application #$app_id — Amount: ₱" . number_format($amount_granted, 2)
            );
            log_audit($mysqli, 'request_approve_application', 'application', $app_id,
                "Requested SA approval for ₱" . number_format($amount_granted, 2));
            header('Location: applications.php?toast=sa_pending'); exit;
        }

        $stmt = $mysqli->prepare("UPDATE applications SET status='approved', rejection_reason=NULL, amount_granted=? WHERE id=?");
        $stmt->bind_param('di', $amount_granted, $app_id); $stmt->execute(); $stmt->close();

        $details = "Amount granted: ₱" . number_format($amount_granted, 2);
        $stmt = $mysqli->prepare("INSERT INTO admin_actions (application_id, admin_id, action, details) VALUES (?,?,'approve',?)");
        $stmt->bind_param('iis', $app_id, $admin_id, $details); $stmt->execute(); $stmt->close();

        $info = $mysqli->prepare("SELECT user_id, type FROM applications WHERE id=?");
        $info->bind_param('i', $app_id); $info->execute();
        $app_info = $info->get_result()->fetch_assoc(); $info->close();
        if ($app_info) notify_status_change($mysqli, $app_info['user_id'], $app_info['type'], 'approved', $app_id);

        log_audit($mysqli, 'approve_application', 'application', $app_id, $details);
        header('Location: applications.php?toast=approved'); exit;
    }

    // ── Release ──
    if ($action === 'release') {
        $amount_released = floatval($_POST['amount_released'] ?? 0);
        if ($amount_released > 0) {
            if (needs_sa_approval('release_funds', ['amount_released' => $amount_released])) {
                $info = $mysqli->prepare("SELECT user_id, type FROM applications WHERE id=?");
                $info->bind_param('i', $app_id); $info->execute();
                $app_info = $info->get_result()->fetch_assoc(); $info->close();

                create_approval_request($mysqli, $admin_id, 'release_funds', $app_id,
                    ['app_id' => $app_id, 'amount_released' => $amount_released,
                     'app_type' => $app_info['type'] ?? '', 'user_id' => $app_info['user_id'] ?? 0],
                    "Release ₱" . number_format($amount_released, 2) . " for application #$app_id"
                );
                log_audit($mysqli, 'request_release_funds', 'application', $app_id,
                    "Requested SA approval to release ₱" . number_format($amount_released, 2));
                header('Location: applications.php?toast=sa_pending'); exit;
            }

            $stmt = $mysqli->prepare("UPDATE applications SET amount_released=? WHERE id=? AND status='approved'");
            $stmt->bind_param('di', $amount_released, $app_id); $stmt->execute(); $stmt->close();

            $details = "Amount released: ₱" . number_format($amount_released, 2);
            $stmt = $mysqli->prepare("INSERT INTO admin_actions (application_id, admin_id, action, details) VALUES (?,?,'release',?)");
            $stmt->bind_param('iis', $app_id, $admin_id, $details); $stmt->execute(); $stmt->close();

            $info = $mysqli->prepare("SELECT user_id, type FROM applications WHERE id=?");
            $info->bind_param('i', $app_id); $info->execute();
            $app_info = $info->get_result()->fetch_assoc(); $info->close();
            if ($app_info) notify_status_change($mysqli, $app_info['user_id'], $app_info['type'], 'released', $app_id);

            log_audit($mysqli, 'release_funds', 'application', $app_id, $details);
            header('Location: applications.php?toast=released'); exit;
        }
    }
}

// ─────────────────────────────────────────────────────────────
// Filters & data
// ─────────────────────────────────────────────────────────────
$barangay_options = array_column($mysqli->query("SELECT DISTINCT barangay FROM users WHERE barangay IS NOT NULL AND barangay <> '' ORDER BY barangay")->fetch_all(MYSQLI_ASSOC), 'barangay');
$table = table_filter_query($mysqli, [
    'from_sql' => 'FROM applications a JOIN users u ON a.user_id = u.id',
    'select_sql' => 'a.id, a.type, a.amount_requested, a.amount_granted, a.amount_released, a.notes, a.status, a.date_of_request, a.created_at, u.name AS client_name, u.phone, u.email, u.barangay',
    'filters' => [
        'search' => ['kind' => 'search', 'label' => 'Keyword', 'placeholder' => 'Name, phone, email or ID', 'columns' => ['u.name', 'u.phone', 'u.email', 'CAST(a.id AS CHAR)']],
        'status' => ['kind' => 'select', 'label' => 'Status', 'options' => ['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','cancelled'=>'Cancelled','released'=>'Released'], 'sql' => 'a.status', 'expressions' => ['released' => 'a.amount_released > 0']],
        'type' => ['kind' => 'select', 'label' => 'Aid type', 'options' => ['medical'=>'Medical','burial'=>'Burial','educational'=>'Educational','livelihood'=>'Livelihood','emergency'=>'Emergency'], 'sql' => 'a.type'],
        'barangay' => ['kind' => 'select', 'label' => 'Barangay', 'options' => array_combine($barangay_options, $barangay_options) ?: [], 'sql' => 'u.barangay'],
        'date_from' => ['kind' => 'date', 'label' => 'Submitted from', 'sql' => 'a.created_at', 'operator' => '>='],
        'date_to' => ['kind' => 'date', 'label' => 'Submitted to', 'sql' => 'a.created_at', 'operator' => '<', 'inclusive_end' => true],
        'amount_min' => ['kind' => 'number', 'label' => 'Amount from', 'sql' => 'a.amount_requested', 'operator' => '>='],
        'amount_max' => ['kind' => 'number', 'label' => 'Amount to', 'sql' => 'a.amount_requested', 'operator' => '<='],
    ],
    'sort' => ['id'=>'a.id', 'client'=>'u.name', 'barangay'=>'u.barangay', 'type'=>'a.type', 'amount'=>'a.amount_requested', 'date'=>'a.created_at', 'status'=>'a.status'],
    'default_sort' => 'date',
    'per_page' => 25,
]);
$applications = $table['rows'];
$filter_status = $table['filters']['status'];
$filter_type = $table['filters']['type'];
$filter_client = $table['filters']['search'];
$reportParams = $_GET;
unset($reportParams['page'], $reportParams['per_page']);
$applicationExportUrl = 'export_reports.php?' . http_build_query(array_merge($reportParams, ['export'=>'applications']));
$printParams = $reportParams;
if (isset($printParams['type'])) {
    $printParams['type_filter'] = $printParams['type'];
    unset($printParams['type']);
}
$applicationPrintUrl = 'print_report.php?' . http_build_query(array_merge(['type'=>'applications'], $printParams));

$documentsTable = table_filter_query($mysqli, [
    'from_sql'=>'FROM documents d JOIN applications a ON d.application_id=a.id JOIN users u ON a.user_id=u.id',
    'select_sql'=>'d.id, d.application_id, d.original_name, d.filename, d.uploaded_at, u.name AS client_name, a.status AS application_status',
    'filters'=>[],
    'sort'=>['applicant'=>'u.name','application'=>'d.application_id','document'=>'d.original_name','uploaded'=>'d.uploaded_at','status'=>'a.status'],
    'default_sort'=>'uploaded','per_page'=>10,
    'param_names'=>['page'=>'documents_page','per_page'=>'documents_per_page','sort'=>'documents_sort','dir'=>'documents_dir'],
]);
$documents = $documentsTable['rows'];

$pending_aids         = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0] ?? 0);
$pending_sa_approvals = get_pending_approvals_count($mysqli);

$active_page   = 'applications';
$page_title    = 'Applications';
$page_subtitle = 'Applications';
$colors        = ['#1a56db','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Applications — AIDTRACK Admin</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/admin.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
.filter-row { display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:14px 20px;border-bottom:1px solid var(--border);background:var(--bg); }
.filter-row input,.filter-row select { font-family:inherit;font-size:.8rem;color:var(--navy);background:var(--white);border:1.5px solid var(--border);border-radius:8px;padding:7px 12px;outline:none;transition:border-color .15s; }
.filter-row input:focus,.filter-row select:focus { border-color:var(--brand); }
.filter-row input { min-width:170px; }
.filter-spacer { flex:1; }
.tabs { display:flex;gap:4px;padding:14px 20px 0;border-bottom:1px solid var(--border); }
.tab { padding:8px 16px;font-size:.8rem;font-weight:700;color:var(--muted);border-radius:8px 8px 0 0;border:1px solid transparent;border-bottom:none;cursor:pointer;transition:all .15s;background:transparent;font-family:inherit;position:relative;bottom:-1px; }
.tab.active { color:var(--brand);background:var(--white);border-color:var(--border);border-bottom-color:var(--white); }
.tab:hover:not(.active) { color:var(--navy); }
.toast { position:fixed;bottom:24px;right:24px;z-index:999;display:flex;align-items:center;gap:10px;padding:12px 18px;border-radius:10px;font-size:.83rem;font-weight:600;box-shadow:0 8px 28px rgba(0,0,0,.15);animation:toastIn .3s ease,toastOut .4s ease 3s forwards; }
.toast-approved  { background:var(--green-lt);color:var(--green);border:1px solid rgba(22,163,74,.2); }
.toast-rejected  { background:var(--red-lt);color:var(--red);border:1px solid rgba(220,38,38,.2); }
.toast-released  { background:var(--brand-lt);color:var(--brand);border:1px solid rgba(26,86,219,.2); }
.toast-sa_pending { background:#f5f3ff;color:#7c3aed;border:1px solid rgba(124,58,237,.2); }
@keyframes toastIn  { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:none} }
@keyframes toastOut { from{opacity:1} to{opacity:0;pointer-events:none} }
.amount { font-family:monospace;font-size:.83rem;font-weight:700; }
.notes-cell { max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--muted);font-size:.78rem; }
.done-label { font-size:.76rem;color:var(--muted);font-style:italic; }
.ta-release { background:rgba(8,145,178,.08);color:#0891b2;border:1px solid rgba(8,145,178,.15);border-radius:6px;padding:4px 10px;font-size:.72rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:4px;font-family:inherit; }
.ta-release:hover { background:rgba(8,145,178,.15); }
.modal-amount { width:100%;font-family:inherit;font-size:.95rem;font-weight:700;color:var(--navy);background:var(--bg);border:1.5px solid var(--border);border-radius:8px;padding:10px 14px;outline:none;margin-top:8px; }
.modal-amount:focus { border-color:var(--brand);background:var(--white);box-shadow:0 0 0 3px rgba(26,86,219,.08); }
.modal-hint { font-size:.72rem;color:var(--muted);margin-top:6px; }
.sa-notice { background:#f5f3ff;border:1px solid rgba(124,58,237,.2);border-radius:8px;padding:8px 12px;font-size:.74rem;color:#7c3aed;font-weight:600;display:flex;align-items:center;gap:6px;margin-top:8px; }
</style>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<!-- TOAST -->
<?php if (!empty($_GET['toast'])): ?>
<?php $t = $_GET['toast']; ?>
<div class="toast toast-<?= $t === 'sa_pending' ? 'sa_pending' : (str_contains($t, 'reject') ? 'rejected' : 'approved') ?>">
    <i data-lucide="<?= $t === 'sa_pending' ? 'clock' : (str_contains($t, 'reject') ? 'x-circle' : 'check-circle') ?>" style="width:16px;height:16px"></i>
    <?= match($t) {
        'approved'       => 'Application approved successfully.',
        'released'       => 'Funds released successfully.',
        'rejected'       => 'Application rejected successfully.',
        'batch_approved' => ($_GET['count'] ?? '') . ' application(s) approved successfully.',
        'batch_rejected' => ($_GET['count'] ?? '') . ' application(s) rejected successfully.',
        'sa_pending'     => 'Action submitted for Super Admin approval.',
        default          => 'Action completed.'
    } ?>
</div>
<?php endif; ?>

<main class="main">

    <div class="page-header">
        <div class="page-header-top">
            <div>
                <div class="page-title">Applications</div>
                <div class="page-sub">Review, approve, and manage all submitted assistance requests.</div>
            </div>
            <div class="page-actions">
                <span class="count-pill"><?= $table['total'] ?> total</span>
                <?php if ($is_sa && $pending_sa_approvals > 0): ?>
                <a href="super_admin_approvals.php" class="btn btn-sm" style="background:#f5f3ff;color:#7c3aed;border:1px solid rgba(124,58,237,.2);">
                    <i data-lucide="shield-alert" style="width:13px;height:13px"></i> SA Queue (<?= $pending_sa_approvals ?>)
                </a>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($applicationExportUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline btn-sm"><i data-lucide="download" style="width:13px;height:13px"></i> Export CSV</a>
                <a href="<?= htmlspecialchars($applicationPrintUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-outline btn-sm"><i data-lucide="printer" style="width:13px;height:13px"></i> Print</a>
                <a href="applications.php" class="btn btn-outline btn-sm">
                    <i data-lucide="refresh-cw" style="width:13px;height:13px"></i> Refresh
                </a>
            </div>
        </div>
    </div>

    <?php if (!$is_sa): ?>
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:10px 16px;font-size:.8rem;color:#1d4ed8;font-weight:600;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
        <i data-lucide="info" style="width:14px;height:14px"></i>
        Applications requesting ₱<?= number_format(SA_APPROVAL_AMOUNT_THRESHOLD, 0) ?>+ or batch operations over <?= SA_APPROVAL_BULK_THRESHOLD ?> will be routed to Super Admin for approval.
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-blue"><i data-lucide="clipboard-list" style="width:14px;height:14px"></i></div>
                Applications Queue
                <span class="count-pill"><?= $table['total'] ?></span>
            </div>
        </div>

        <?php include 'partials/filter_bar.php'; ?>

        <div class="card-body no-pad">
            <?php if (empty($applications)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="inbox" style="width:20px;height:20px"></i></div>
                <div class="empty-text">No applications match your filters.</div>
            </div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table id="applicationsTable">
                    <thead>
                        <tr>
                            <th style="width:30px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>
                            <th><?= table_sort_link($table, 'id', '#') ?></th><th><?= table_sort_link($table, 'client', 'Applicant') ?></th><th><?= table_sort_link($table, 'barangay', 'Barangay') ?></th><th><?= table_sort_link($table, 'type', 'Type') ?></th>
                            <th><?= table_sort_link($table, 'amount', 'Amount') ?></th><th>Notes</th><th><?= table_sort_link($table, 'date', 'Date submitted') ?></th><th><?= table_sort_link($table, 'status', 'Status') ?></th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($applications as $i => $app):
                        $s    = strtolower($app['status']);
                        $bc   = $s === 'approved' ? 'b-approved' : ($s === 'pending' ? 'b-pending' : 'b-rejected');
                        $col  = $colors[$i % count($colors)];
                        $init = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $app['client_name']), 0, 2)));
                        $needs_sa = !$is_sa && floatval($app['amount_requested']) >= SA_APPROVAL_AMOUNT_THRESHOLD;
                    ?>
                    <tr>
                        <td><input type="checkbox" class="row-check" value="<?= $app['id'] ?>" data-status="<?= $s ?>" onchange="updateBatchBar()"></td>
                        <td style="color:var(--muted);font-size:.75rem;"><a href="view_application.php?id=<?= $app['id'] ?>" style="color:var(--brand);font-weight:700;"><?= $app['id'] ?></a></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:9px;">
                                <div class="row-avatar" style="background:<?= $col ?>;"><?= $init ?></div>
                                <div style="font-weight:700;color:var(--navy);font-size:.82rem;"><?= htmlspecialchars($app['client_name']) ?></div>
                            </div>
                        </td>
                        <td style="color:var(--muted);"><?= htmlspecialchars($app['barangay'] ?? '—') ?></td>
                        <td><span style="font-weight:600;"><?= htmlspecialchars(ucwords(strtolower($app['type']))) ?></span></td>
                        <td>
                            <span class="amount" style="<?= $needs_sa ? 'color:#7c3aed;' : '' ?>">
                                ₱<?= number_format($app['amount_requested'], 2) ?>
                            </span>
                            <?php if ($needs_sa && $s === 'pending'): ?>
                            <i data-lucide="shield" style="width:11px;height:11px;color:#7c3aed;margin-left:3px;vertical-align:middle" title="Requires SA approval"></i>
                            <?php endif; ?>
                        </td>
                        <td><div class="notes-cell" title="<?= htmlspecialchars($app['notes'] ?? '') ?>"><?= htmlspecialchars($app['notes'] ?? '—') ?></div></td>
                        <td style="color:var(--muted);white-space:nowrap;"><?= htmlspecialchars($app['date_of_request']) ?></td>
                        <td><span class="badge <?= $bc ?>"><?= ucfirst($s) ?></span></td>
                        <td>
                            <?php if ($s === 'pending'): ?>
                            <div class="tbl-actions">
                                <button class="tbl-action ta-approve"
                                        onclick="openApproveModal(<?= $app['id'] ?>, '<?= htmlspecialchars($app['client_name'], ENT_QUOTES) ?>', <?= floatval($app['amount_requested']) ?>)">
                                    <i data-lucide="check" style="width:11px;height:11px"></i>
                                    <?= $needs_sa ? 'Request' : 'Approve' ?>
                                </button>
                                <button class="tbl-action ta-reject"
                                        onclick="openRejectModal(<?= $app['id'] ?>, '<?= htmlspecialchars($app['client_name'], ENT_QUOTES) ?>')">
                                    <i data-lucide="x" style="width:11px;height:11px"></i> Reject
                                </button>
                            </div>
                            <?php elseif ($s === 'approved' && floatval($app['amount_released'] ?? 0) == 0): ?>
                            <div class="tbl-actions">
                                <button class="tbl-action ta-release"
                                        onclick="openReleaseModal(<?= $app['id'] ?>, '<?= htmlspecialchars($app['client_name'], ENT_QUOTES) ?>', <?= floatval($app['amount_granted'] ?? 0) ?>)">
                                    <i data-lucide="banknote" style="width:11px;height:11px"></i>
                                    <?= !$is_sa && floatval($app['amount_granted'] ?? 0) >= SA_APPROVAL_AMOUNT_THRESHOLD ? 'Request Release' : 'Release' ?>
                                </button>
                            </div>
                            <?php elseif ($s === 'approved' && floatval($app['amount_released'] ?? 0) > 0): ?>
                                <span class="done-label" style="color:var(--green);">Released ₱<?= number_format($app['amount_released'], 2) ?></span>
                            <?php else: ?>
                                <span class="done-label">No action</span>
                            <?php endif; ?>
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

    <!-- Documents -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-yellow"><i data-lucide="folder-open" style="width:14px;height:14px"></i></div>
                Uploaded Documents <span class="count-pill"><?= count($documents) ?></span>
            </div>
        </div>
        <div class="card-body no-pad">
            <?php if (empty($documents)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="folder" style="width:20px;height:20px"></i></div>
                <div class="empty-text">No documents have been uploaded yet.</div>
            </div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table>
                    <thead>
                        <tr><th><?= table_sort_link($documentsTable, 'applicant', 'Applicant') ?></th><th><?= table_sort_link($documentsTable, 'application', 'App. ID') ?></th><th><?= table_sort_link($documentsTable, 'document', 'Document') ?></th><th><?= table_sort_link($documentsTable, 'uploaded', 'Uploaded') ?></th><th><?= table_sort_link($documentsTable, 'status', 'App. Status') ?></th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($documents as $i => $doc):
                        $ds  = strtolower($doc['application_status'] ?? '');
                        $dbc = $ds === 'approved' ? 'b-approved' : ($ds === 'pending' ? 'b-pending' : 'b-rejected');
                        $col = $colors[$i % count($colors)];
                        $din = strtoupper(substr(trim($doc['client_name']), 0, 2));
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:9px;">
                                <div class="row-avatar" style="background:<?= $col ?>;"><?= $din ?></div>
                                <div style="font-weight:700;color:var(--navy);font-size:.82rem;"><?= htmlspecialchars($doc['client_name']) ?></div>
                            </div>
                        </td>
                        <td style="color:var(--muted);">#<?= $doc['application_id'] ?></td>
                        <td style="font-weight:600;"><?= htmlspecialchars($doc['original_name'] ?? $doc['filename']) ?></td>
                        <td style="color:var(--muted);white-space:nowrap;"><?= htmlspecialchars($doc['uploaded_at']) ?></td>
                        <td><span class="badge <?= $dbc ?>"><?= ucfirst($ds) ?></span></td>
                        <td>
                            <div class="tbl-actions">
                                <?php $ext = strtolower(pathinfo($doc['filename'], PATHINFO_EXTENSION)); ?>
                                <button class="tbl-action ta-view" onclick="previewDoc('../uploads/<?= urlencode($doc['filename']) ?>','<?= $ext ?>','<?= htmlspecialchars($doc['original_name'] ?? $doc['filename'], ENT_QUOTES) ?>')">
                                    <i data-lucide="eye" style="width:11px;height:11px"></i> Preview
                                </button>
                                <form method="post" action="delete_document.php" style="display:contents;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $doc['id'] ?>">
                                    <button type="submit" class="tbl-action ta-delete" onclick="return confirm('Delete this document?')">
                                        <i data-lucide="trash-2" style="width:11px;height:11px"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php $table = $documentsTable; include 'partials/table_pager.php'; ?>
            <?php endif; ?>
        </div>
    </div>

</main>

<!-- BATCH BAR -->
<div id="batchBar" style="display:none;position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:998;background:var(--navy);color:#fff;border-radius:12px;padding:10px 20px;align-items:center;gap:14px;box-shadow:0 12px 40px rgba(0,0,0,.25);font-size:.84rem;font-weight:700;">
    <span><span id="batchCount">0</span> selected</span>
    <button onclick="submitBatch('approve')" style="padding:6px 14px;background:var(--green);color:#fff;border:none;border-radius:8px;font-family:inherit;font-weight:700;font-size:.78rem;cursor:pointer;display:flex;align-items:center;gap:5px;">
        <i data-lucide="check" style="width:12px;height:12px"></i> Approve All
    </button>
    <button onclick="submitBatch('reject')" style="padding:6px 14px;background:var(--red);color:#fff;border:none;border-radius:8px;font-family:inherit;font-weight:700;font-size:.78rem;cursor:pointer;display:flex;align-items:center;gap:5px;">
        <i data-lucide="x" style="width:12px;height:12px"></i> Reject All
    </button>
</div>

<!-- APPROVE MODAL -->
<div class="modal-overlay" id="approveModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">
                <div class="card-title-icon cti-green" style="width:28px;height:28px;"><i data-lucide="check-circle" style="width:14px;height:14px"></i></div>
                Approve Application
            </div>
            <button class="modal-close" onclick="closeApproveModal()"><i data-lucide="x" style="width:14px;height:14px"></i></button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <div class="modal-body">
                <p class="modal-desc" id="approveDesc"></p>
                <input type="hidden" name="action" value="approved">
                <input type="hidden" name="id" id="approveAppId">
                <label style="font-size:.76rem;font-weight:700;color:var(--slate);">Amount Granted (₱)</label>
                <input type="number" name="amount_granted" id="approveAmount" class="modal-amount" step="0.01" min="0" placeholder="0.00" required oninput="checkSAThreshold(this.value)">
                <div class="modal-hint">Requested: ₱<span id="approveRequested">0.00</span></div>
                <div id="saNotice" class="sa-notice" style="display:none;">
                    <i data-lucide="shield" style="width:14px;height:14px"></i>
                    Amount ≥ ₱<?= number_format(SA_APPROVAL_AMOUNT_THRESHOLD, 0) ?> — this will be submitted for Super Admin approval before executing.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeApproveModal()">Cancel</button>
                <button type="submit" id="approveSubmitBtn" class="btn btn-sm" style="background:var(--green);color:#fff;">
                    <i data-lucide="check-circle" style="width:13px;height:13px"></i> <span id="approveSubmitLabel">Approve</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- RELEASE MODAL -->
<div class="modal-overlay" id="releaseModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">
                <div class="card-title-icon" style="width:28px;height:28px;background:rgba(8,145,178,.1);color:#0891b2;"><i data-lucide="banknote" style="width:14px;height:14px"></i></div>
                Release Funds
            </div>
            <button class="modal-close" onclick="closeReleaseModal()"><i data-lucide="x" style="width:14px;height:14px"></i></button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <div class="modal-body">
                <p class="modal-desc" id="releaseDesc"></p>
                <input type="hidden" name="action" value="release">
                <input type="hidden" name="id" id="releaseAppId">
                <label style="font-size:.76rem;font-weight:700;color:var(--slate);">Amount to Release (₱)</label>
                <input type="number" name="amount_released" id="releaseAmount" class="modal-amount" step="0.01" min="0.01" placeholder="0.00" required>
                <div class="modal-hint">Granted: ₱<span id="releaseGranted">0.00</span></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeReleaseModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="banknote" style="width:13px;height:13px"></i> Release Funds</button>
            </div>
        </form>
    </div>
</div>

<!-- REJECT MODAL -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">
                <div class="card-title-icon cti-red" style="width:28px;height:28px;"><i data-lucide="x-circle" style="width:14px;height:14px"></i></div>
                Reject Application
            </div>
            <button class="modal-close" onclick="closeRejectModal()"><i data-lucide="x" style="width:14px;height:14px"></i></button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <div class="modal-body">
                <p class="modal-desc" id="rejectDesc"></p>
                <input type="hidden" name="action" value="rejected">
                <input type="hidden" name="id" id="rejectAppId">
                <textarea name="reason" placeholder="Enter rejection reason…" required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm"><i data-lucide="x-circle" style="width:13px;height:13px"></i> Confirm Rejection</button>
            </div>
        </form>
    </div>
</div>

<!-- DOC PREVIEW MODAL -->
<div class="modal-overlay" id="previewModal" style="z-index:1001;">
    <div class="modal" style="max-width:700px;width:95%;">
        <div class="modal-header">
            <div class="modal-title">
                <div class="card-title-icon cti-yellow" style="width:28px;height:28px;"><i data-lucide="file-search" style="width:14px;height:14px"></i></div>
                <span id="previewTitle">Document Preview</span>
            </div>
            <button class="modal-close" onclick="closePreview()"><i data-lucide="x" style="width:14px;height:14px"></i></button>
        </div>
        <div class="modal-body" style="padding:0;text-align:center;min-height:300px;max-height:70vh;overflow:auto;background:var(--bg);" id="previewBody"></div>
        <div class="modal-footer">
            <a id="previewDownload" href="#" target="_blank" class="btn btn-primary btn-sm">
                <i data-lucide="download" style="width:13px;height:13px"></i> Open in New Tab
            </a>
        </div>
    </div>
</div>

<script src="partials/admin.js"></script>
<script>
const SA_THRESHOLD = <?= SA_APPROVAL_AMOUNT_THRESHOLD ?>;
const IS_SA = <?= $is_sa ? 'true' : 'false' ?>;

function checkSAThreshold(val) {
    const notice = document.getElementById('saNotice');
    const label  = document.getElementById('approveSubmitLabel');
    if (!IS_SA && parseFloat(val) >= SA_THRESHOLD) {
        notice.style.display = 'flex';
        label.textContent = 'Submit for Approval';
    } else {
        notice.style.display = 'none';
        label.textContent = 'Approve';
    }
    lucide.createIcons();
}

function previewDoc(url, ext, name) {
    document.getElementById('previewTitle').textContent = name;
    document.getElementById('previewDownload').href = url;
    const body = document.getElementById('previewBody');
    if (['jpg','jpeg','png','gif'].includes(ext)) {
        body.innerHTML = `<img src="${url}" style="max-width:100%;max-height:65vh;object-fit:contain;padding:16px;" alt="${name}">`;
    } else if (ext === 'pdf') {
        body.innerHTML = `<iframe src="${url}" style="width:100%;height:65vh;border:none;"></iframe>`;
    } else {
        body.innerHTML = `<div style="padding:40px;color:var(--muted);font-size:.9rem;">Preview not available. Click "Open in New Tab" to view.</div>`;
    }
    document.getElementById('previewModal').classList.add('open');
}
function closePreview() { document.getElementById('previewModal').classList.remove('open'); }
document.getElementById('previewModal').addEventListener('click', function(e) { if (e.target === this) closePreview(); });

function openApproveModal(appId, clientName, amountRequested) {
    document.getElementById('approveAppId').value = appId;
    document.getElementById('approveAmount').value = amountRequested.toFixed(2);
    document.getElementById('approveRequested').textContent = amountRequested.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
    document.getElementById('approveDesc').textContent = `Approve ${clientName}'s application and set the granted amount.`;
    checkSAThreshold(amountRequested);
    document.getElementById('approveModal').classList.add('open');
}
function closeApproveModal() { document.getElementById('approveModal').classList.remove('open'); }
document.getElementById('approveModal').addEventListener('click', function(e) { if (e.target === this) closeApproveModal(); });

function openReleaseModal(appId, clientName, amountGranted) {
    document.getElementById('releaseAppId').value = appId;
    document.getElementById('releaseAmount').value = amountGranted > 0 ? amountGranted.toFixed(2) : '';
    document.getElementById('releaseGranted').textContent = amountGranted.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
    document.getElementById('releaseDesc').textContent = `Release funds for ${clientName}'s approved application.`;
    document.getElementById('releaseModal').classList.add('open');
}
function closeReleaseModal() { document.getElementById('releaseModal').classList.remove('open'); }
document.getElementById('releaseModal').addEventListener('click', function(e) { if (e.target === this) closeReleaseModal(); });

function openRejectModal(appId, clientName) {
    document.getElementById('rejectAppId').value = appId;
    document.getElementById('rejectDesc').textContent = `Reject ${clientName}'s application. Provide a reason for the applicant.`;
    document.getElementById('rejectModal').classList.add('open');
}
function closeRejectModal() { document.getElementById('rejectModal').classList.remove('open'); }
document.getElementById('rejectModal').addEventListener('click', function(e) { if (e.target === this) closeRejectModal(); });

function switchTab(status, btn) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('#applicationsTable tbody tr').forEach(row => {
        row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
    });
}

function toggleSelectAll(el) {
    document.querySelectorAll('.row-check').forEach(c => { c.checked = el.checked; });
    updateBatchBar();
}
function updateBatchBar() {
    const checked = document.querySelectorAll('.row-check:checked');
    const bar = document.getElementById('batchBar');
    document.getElementById('batchCount').textContent = checked.length;
    bar.style.display = checked.length > 0 ? 'flex' : 'none';
}
function submitBatch(action) {
    const checked = document.querySelectorAll('.row-check:checked');
    if (checked.length === 0) return;
    let reason = '';
    if (action === 'reject') {
        reason = prompt('Enter rejection reason for all selected:');
        if (!reason) return;
    }
    if (!confirm(`${action === 'approve' ? 'Approve' : 'Reject'} ${checked.length} application(s)?`)) return;
    const form = document.createElement('form');
    form.method = 'POST'; form.style.display = 'none';
    form.innerHTML = `<input name="csrf_token" value="<?= csrf_token() ?>"><input name="action" value="batch"><input name="batch_action" value="${action}">`;
    if (reason) form.innerHTML += `<input name="batch_reason" value="${reason.replace(/"/g,'&quot;')}">`;
    checked.forEach(c => { form.innerHTML += `<input name="batch_ids[]" value="${c.value}">`; });
    document.body.appendChild(form);
    form.submit();
}

setTimeout(() => { const t = document.querySelector('.toast'); if (t) t.style.display = 'none'; }, 4500);
</script>
</body>
</html>
