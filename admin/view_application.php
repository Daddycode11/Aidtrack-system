<?php
// admin/view_application.php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../helpers/notify.php';
require_admin();

$app_id   = intval($_GET['id'] ?? 0);
$admin_id = $_SESSION['user']['id'] ?? 0;
$is_sa    = is_super_admin();

if (!$app_id) { header('Location: applications.php'); exit; }

// --- Handle POST Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify()) { header("Location: view_application.php?id=$app_id&err=csrf"); exit; }
    $action = strtolower($_POST['action']);

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
        header("Location: view_application.php?id=$app_id&toast=rejected"); exit;
    }

    if ($action === 'approved') {
        $amount_granted = floatval($_POST['amount_granted'] ?? 0);

        if (needs_sa_approval('approve_application', ['amount_granted' => $amount_granted])) {
            $info = $mysqli->prepare("SELECT user_id, type FROM applications WHERE id=?");
            $info->bind_param('i', $app_id); $info->execute();
            $app_info = $info->get_result()->fetch_assoc(); $info->close();

            create_approval_request($mysqli, $admin_id, 'approve_application', $app_id,
                ['app_id' => $app_id, 'amount_granted' => $amount_granted,
                 'app_type' => $app_info['type'] ?? '', 'user_id' => $app_info['user_id'] ?? 0],
                "Approve application #$app_id — ₱" . number_format($amount_granted, 2)
            );
            log_audit($mysqli, 'request_approve_application', 'application', $app_id,
                "Requested SA approval for ₱" . number_format($amount_granted, 2));
            header("Location: view_application.php?id=$app_id&toast=sa_pending"); exit;
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
        header("Location: view_application.php?id=$app_id&toast=approved"); exit;
    }

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
                header("Location: view_application.php?id=$app_id&toast=sa_pending"); exit;
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
            header("Location: view_application.php?id=$app_id&toast=released"); exit;
        }
    }
}

// --- Fetch Application + Applicant ---
$stmt = $mysqli->prepare("
    SELECT a.*, u.name AS client_name, u.phone, u.barangay, u.created_at AS member_since
    FROM applications a
    JOIN users u ON a.user_id = u.id
    WHERE a.id = ?
");
$stmt->bind_param('i', $app_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app) { header('Location: applications.php'); exit; }

// --- Fetch Documents ---
$stmt = $mysqli->prepare("SELECT * FROM documents WHERE application_id = ? ORDER BY uploaded_at ASC");
$stmt->bind_param('i', $app_id);
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Fetch Activity Timeline ---
$stmt = $mysqli->prepare("
    SELECT aa.*, u.name AS admin_name
    FROM admin_actions aa
    LEFT JOIN users u ON aa.admin_id = u.id
    WHERE aa.application_id = ?
    ORDER BY aa.created_at DESC
");
$stmt->bind_param('i', $app_id);
$stmt->execute();
$actions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Page vars
$active_page   = 'applications';
$page_title    = 'View Application';
$page_subtitle = 'Application #' . $app_id;
$pending_aids  = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0] ?? 0);
$colors        = ['#1a56db','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'];

$s = strtolower($app['status']);
$init = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $app['client_name']), 0, 2)));
$col  = $colors[($app['user_id'] ?? 0) % count($colors)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Application #<?= $app_id ?> — AIDTRACK Admin</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/admin.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
/* ── PAGE-SPECIFIC ── */

/* Status banner */
.status-banner {
    display: flex; align-items: center; gap: 12px;
    padding: 16px 20px; border-radius: 12px;
    margin-bottom: 20px; font-weight: 700; font-size: .9rem;
}
.status-banner i { flex-shrink: 0; }
.sb-pending  { background: rgba(202,138,4,.08);  color: var(--yellow); border: 1px solid rgba(202,138,4,.18); }
.sb-approved { background: rgba(22,163,74,.08);   color: var(--green);  border: 1px solid rgba(22,163,74,.18); }
.sb-rejected { background: rgba(220,38,38,.08);   color: var(--red);    border: 1px solid rgba(220,38,38,.18); }

/* Two-column grid */
.detail-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 20px;
}
@media (max-width: 768px) { .detail-grid { grid-template-columns: 1fr; } }

/* Detail rows */
.detail-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 0; border-bottom: 1px solid var(--border);
    font-size: .83rem;
}
.detail-row:last-child { border-bottom: none; }
.detail-label { color: var(--muted); font-weight: 600; }
.detail-value { color: var(--navy); font-weight: 700; text-align: right; }
.detail-value.mono { font-family: monospace; }

/* Documents grid */
.docs-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px;
    padding: 18px;
}
.doc-item {
    border: 1.5px solid var(--border); border-radius: 10px;
    overflow: hidden; background: var(--white); transition: border-color .15s, box-shadow .15s;
}
.doc-item:hover { border-color: var(--brand); box-shadow: 0 4px 14px rgba(26,86,219,.08); }
.doc-thumb {
    height: 110px; background: var(--bg); display: flex; align-items: center; justify-content: center;
    overflow: hidden;
}
.doc-thumb img { width: 100%; height: 100%; object-fit: cover; }
.doc-thumb .file-icon { color: var(--muted); }
.doc-info {
    padding: 10px 12px; border-top: 1px solid var(--border);
}
.doc-name { font-size: .76rem; font-weight: 700; color: var(--navy); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.doc-date { font-size: .68rem; color: var(--muted); margin-top: 2px; }
.doc-actions { display: flex; gap: 6px; margin-top: 8px; }
.doc-btn {
    font-family: inherit; font-size: .68rem; font-weight: 700;
    padding: 4px 10px; border-radius: 6px; cursor: pointer;
    display: inline-flex; align-items: center; gap: 4px;
    text-decoration: none; border: none;
}
.doc-btn-view { background: rgba(26,86,219,.08); color: var(--brand); }
.doc-btn-view:hover { background: rgba(26,86,219,.14); }

/* Timeline */
.timeline { padding: 18px 20px; }
.tl-item { display: flex; gap: 14px; position: relative; padding-bottom: 20px; }
.tl-item:last-child { padding-bottom: 0; }
.tl-item:not(:last-child)::before {
    content: ''; position: absolute; left: 15px; top: 32px;
    width: 2px; bottom: 0; background: var(--border);
}
.tl-dot {
    width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: .7rem; position: relative; z-index: 1;
}
.tl-dot-approve { background: rgba(22,163,74,.1); color: var(--green); }
.tl-dot-reject  { background: rgba(220,38,38,.1);  color: var(--red); }
.tl-dot-release { background: rgba(8,145,178,.1);  color: #0891b2; }
.tl-dot-default { background: var(--bg); color: var(--muted); border: 1.5px solid var(--border); }
.tl-content { flex: 1; }
.tl-action { font-weight: 700; font-size: .82rem; color: var(--navy); }
.tl-details { font-size: .78rem; color: var(--muted); margin-top: 2px; }
.tl-meta { font-size: .7rem; color: var(--muted); margin-top: 4px; display: flex; align-items: center; gap: 8px; }

/* Rejection box */
.rejection-box {
    background: rgba(220,38,38,.05); border: 1px solid rgba(220,38,38,.15);
    border-radius: 10px; padding: 14px 18px; margin-bottom: 20px;
}
.rejection-box-title { font-size: .78rem; font-weight: 700; color: var(--red); margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
.rejection-box-text { font-size: .82rem; color: var(--navy); line-height: 1.5; }

/* Action buttons row */
.action-btns { display: flex; gap: 10px; flex-wrap: wrap; }

/* Toast */
.toast {
    position: fixed; bottom: 24px; right: 24px; z-index: 999;
    display: flex; align-items: center; gap: 10px;
    padding: 12px 18px; border-radius: 10px;
    font-size: .83rem; font-weight: 600;
    box-shadow: 0 8px 28px rgba(0,0,0,.15);
    animation: toastIn .3s ease, toastOut .4s ease 3s forwards;
}
.toast-approved  { background: var(--green-lt); color: var(--green); border: 1px solid rgba(22,163,74,.2); }
.toast-rejected  { background: var(--red-lt);   color: var(--red);   border: 1px solid rgba(220,38,38,.2); }
.toast-released  { background: var(--brand-lt); color: var(--brand); border: 1px solid rgba(26,86,219,.2); }
.toast-sa_pending { background: #f5f3ff; color: #7c3aed; border: 1px solid rgba(124,58,237,.2); }
.sa-notice { background:#f5f3ff;border:1px solid rgba(124,58,237,.2);border-radius:8px;padding:8px 12px;font-size:.74rem;color:#7c3aed;font-weight:600;display:flex;align-items:center;gap:6px;margin-top:8px; }
@keyframes toastIn  { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:none} }
@keyframes toastOut { from{opacity:1} to{opacity:0;pointer-events:none} }

/* Amount input in modal */
.modal-amount { width:100%; font-family:inherit; font-size:.95rem; font-weight:700; color:var(--navy); background:var(--bg); border:1.5px solid var(--border); border-radius:8px; padding:10px 14px; outline:none; margin-top:8px; }
.modal-amount:focus { border-color:var(--brand); background:var(--white); box-shadow:0 0 0 3px rgba(26,86,219,.08); }
.modal-hint { font-size:.72rem; color:var(--muted); margin-top:6px; }

/* Back link */
.back-link {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: .82rem; font-weight: 700; color: var(--muted);
    text-decoration: none; margin-bottom: 16px; transition: color .15s;
}
.back-link:hover { color: var(--brand); }
</style>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<!-- TOAST -->
<?php if (!empty($_GET['toast'])): ?>
<?php $t = $_GET['toast'];
      $tc = $t === 'rejected' ? 'rejected' : ($t === 'released' ? 'released' : ($t === 'sa_pending' ? 'sa_pending' : 'approved')); ?>
<div class="toast toast-<?= $tc ?>">
    <i data-lucide="<?= $t === 'rejected' ? 'x-circle' : ($t === 'sa_pending' ? 'clock' : 'check-circle') ?>" style="width:16px;height:16px"></i>
    <?= match($t) {
        'approved'   => 'Application approved successfully.',
        'released'   => 'Funds released successfully.',
        'rejected'   => 'Application rejected successfully.',
        'sa_pending' => 'Action submitted for Super Admin approval.',
        default      => 'Action completed.'
    } ?>
</div>
<?php endif; ?>

<!-- MAIN -->
<main class="main">

    <!-- Back button -->
    <a href="applications.php" class="back-link">
        <i data-lucide="arrow-left" style="width:15px;height:15px"></i> Back to Applications
    </a>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-top">
            <div>
                <div class="page-title">Application #<?= $app_id ?></div>
                <div class="page-sub"><?= ucwords($app['type']) ?> assistance request by <?= htmlspecialchars($app['client_name']) ?></div>
            </div>
            <div class="page-actions">
                <span class="badge <?= $s === 'approved' ? 'b-approved' : ($s === 'pending' ? 'b-pending' : 'b-rejected') ?>" style="font-size:.8rem;padding:6px 14px;">
                    <?= ucfirst($s) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Status Banner -->
    <?php
    $sb_class = $s === 'approved' ? 'sb-approved' : ($s === 'pending' ? 'sb-pending' : 'sb-rejected');
    $sb_icon  = $s === 'approved' ? 'check-circle' : ($s === 'pending' ? 'clock' : 'x-circle');
    $sb_text  = $s === 'approved' ? 'This application has been approved.' : ($s === 'pending' ? 'This application is pending review.' : 'This application has been rejected.');
    if ($s === 'approved' && floatval($app['amount_released'] ?? 0) > 0) {
        $sb_text = 'This application has been approved and funds have been released.';
    }
    ?>
    <div class="status-banner <?= $sb_class ?>">
        <i data-lucide="<?= $sb_icon ?>" style="width:20px;height:20px"></i>
        <?= $sb_text ?>
    </div>

    <!-- Rejection Reason Box -->
    <?php if ($s === 'rejected' && !empty($app['rejection_reason'])): ?>
    <div class="rejection-box">
        <div class="rejection-box-title">
            <i data-lucide="alert-triangle" style="width:14px;height:14px"></i> Rejection Reason
        </div>
        <div class="rejection-box-text"><?= nl2br(htmlspecialchars($app['rejection_reason'])) ?></div>
    </div>
    <?php endif; ?>

    <!-- Two-column grid -->
    <div class="detail-grid">

        <!-- Application Details -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon cti-blue">
                        <i data-lucide="clipboard-list" style="width:14px;height:14px"></i>
                    </div>
                    Application Details
                </div>
            </div>
            <div class="card-body">
                <div class="detail-row">
                    <span class="detail-label">Application ID</span>
                    <span class="detail-value">#<?= $app['id'] ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Type</span>
                    <span class="detail-value"><?= htmlspecialchars(ucwords($app['type'])) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Amount Requested</span>
                    <span class="detail-value mono">₱<?= number_format($app['amount_requested'], 2) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Amount Granted</span>
                    <span class="detail-value mono"><?= floatval($app['amount_granted'] ?? 0) > 0 ? '₱' . number_format($app['amount_granted'], 2) : '—' ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Amount Released</span>
                    <span class="detail-value mono"><?= floatval($app['amount_released'] ?? 0) > 0 ? '₱' . number_format($app['amount_released'], 2) : '—' ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="detail-value">
                        <span class="badge <?= $s === 'approved' ? 'b-approved' : ($s === 'pending' ? 'b-pending' : 'b-rejected') ?>"><?= ucfirst($s) ?></span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date of Request</span>
                    <span class="detail-value"><?= $app['date_of_request'] ? date('M d, Y', strtotime($app['date_of_request'])) : '—' ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Submitted</span>
                    <span class="detail-value"><?= $app['created_at'] ? date('M d, Y g:i A', strtotime($app['created_at'])) : '—' ?></span>
                </div>
                <?php if (!empty($app['notes'])): ?>
                <div class="detail-row" style="flex-direction:column;align-items:flex-start;gap:6px;">
                    <span class="detail-label">Notes</span>
                    <span class="detail-value" style="text-align:left;font-weight:500;color:var(--navy);line-height:1.5;"><?= nl2br(htmlspecialchars($app['notes'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Applicant Info -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon cti-green">
                        <i data-lucide="user" style="width:14px;height:14px"></i>
                    </div>
                    Applicant Information
                </div>
            </div>
            <div class="card-body">
                <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px;padding-bottom:16px;border-bottom:1px solid var(--border);">
                    <div class="row-avatar" style="background:<?= $col ?>;width:44px;height:44px;font-size:.9rem;"><?= $init ?></div>
                    <div>
                        <div style="font-weight:800;font-size:.95rem;color:var(--navy);"><?= htmlspecialchars($app['client_name']) ?></div>
                        <div style="font-size:.75rem;color:var(--muted);">Applicant ID #<?= $app['user_id'] ?></div>
                    </div>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Full Name</span>
                    <span class="detail-value"><?= htmlspecialchars($app['client_name']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Phone</span>
                    <span class="detail-value mono"><?= htmlspecialchars($app['phone'] ?? '—') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Barangay</span>
                    <span class="detail-value"><?= htmlspecialchars($app['barangay'] ?? '—') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Member Since</span>
                    <span class="detail-value"><?= $app['member_since'] ? date('M d, Y', strtotime($app['member_since'])) : '—' ?></span>
                </div>
            </div>
        </div>

    </div>

    <!-- Documents Card -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-yellow">
                    <i data-lucide="folder-open" style="width:14px;height:14px"></i>
                </div>
                Documents
                <span class="count-pill"><?= count($documents) ?></span>
            </div>
        </div>
        <?php if (empty($documents)): ?>
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="folder" style="width:20px;height:20px"></i></div>
                <div class="empty-text">No documents attached to this application.</div>
            </div>
        </div>
        <?php else: ?>
        <div class="docs-grid">
            <?php foreach ($documents as $doc):
                $ext = strtolower(pathinfo($doc['filename'], PATHINFO_EXTENSION));
                $is_image = in_array($ext, ['jpg','jpeg','png','gif']);
                $file_url = '../uploads/' . urlencode($doc['filename']);
            ?>
            <div class="doc-item">
                <div class="doc-thumb">
                    <?php if ($is_image): ?>
                        <img src="<?= $file_url ?>" alt="<?= htmlspecialchars($doc['original_name'] ?? $doc['filename']) ?>">
                    <?php else: ?>
                        <div class="file-icon">
                            <i data-lucide="<?= $ext === 'pdf' ? 'file-text' : 'file' ?>" style="width:32px;height:32px"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="doc-info">
                    <div class="doc-name" title="<?= htmlspecialchars($doc['original_name'] ?? $doc['filename']) ?>">
                        <?= htmlspecialchars($doc['original_name'] ?? $doc['filename']) ?>
                    </div>
                    <div class="doc-date"><?= $doc['uploaded_at'] ? date('M d, Y g:i A', strtotime($doc['uploaded_at'])) : '—' ?></div>
                    <div class="doc-actions">
                        <a href="<?= $file_url ?>" target="_blank" class="doc-btn doc-btn-view">
                            <i data-lucide="eye" style="width:11px;height:11px"></i> View
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Activity Timeline -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-blue">
                    <i data-lucide="activity" style="width:14px;height:14px"></i>
                </div>
                Activity Timeline
                <span class="count-pill"><?= count($actions) ?></span>
            </div>
        </div>
        <?php if (empty($actions)): ?>
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="clock" style="width:20px;height:20px"></i></div>
                <div class="empty-text">No activity recorded yet.</div>
            </div>
        </div>
        <?php else: ?>
        <div class="timeline">
            <?php foreach ($actions as $act):
                $a = strtolower($act['action']);
                $dot_class = $a === 'approve' ? 'tl-dot-approve' : ($a === 'reject' ? 'tl-dot-reject' : ($a === 'release' ? 'tl-dot-release' : 'tl-dot-default'));
                $act_icon  = $a === 'approve' ? 'check' : ($a === 'reject' ? 'x' : ($a === 'release' ? 'banknote' : 'activity'));
                $act_label = $a === 'approve' ? 'Approved' : ($a === 'reject' ? 'Rejected' : ($a === 'release' ? 'Funds Released' : ucfirst($a)));
            ?>
            <div class="tl-item">
                <div class="tl-dot <?= $dot_class ?>">
                    <i data-lucide="<?= $act_icon ?>" style="width:14px;height:14px"></i>
                </div>
                <div class="tl-content">
                    <div class="tl-action"><?= $act_label ?></div>
                    <?php if (!empty($act['details'])): ?>
                    <div class="tl-details"><?= htmlspecialchars($act['details']) ?></div>
                    <?php endif; ?>
                    <div class="tl-meta">
                        <span>by <?= htmlspecialchars($act['admin_name'] ?? 'System') ?></span>
                        <span>&middot;</span>
                        <span><?= $act['created_at'] ? date('M d, Y g:i A', strtotime($act['created_at'])) : '—' ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Action Buttons -->
    <?php if ($s === 'pending'): ?>
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-yellow">
                    <i data-lucide="settings" style="width:14px;height:14px"></i>
                </div>
                Actions
            </div>
        </div>
        <div class="card-body">
            <div class="action-btns">
                <button class="btn btn-sm" style="background:var(--green);color:#fff;"
                        onclick="openApproveModal(<?= $app['id'] ?>, '<?= htmlspecialchars($app['client_name'], ENT_QUOTES) ?>', <?= floatval($app['amount_requested']) ?>)">
                    <i data-lucide="check-circle" style="width:14px;height:14px"></i> Approve Application
                </button>
                <button class="btn btn-danger btn-sm"
                        onclick="openRejectModal(<?= $app['id'] ?>, '<?= htmlspecialchars($app['client_name'], ENT_QUOTES) ?>')">
                    <i data-lucide="x-circle" style="width:14px;height:14px"></i> Reject Application
                </button>
            </div>
        </div>
    </div>
    <?php elseif ($s === 'approved' && floatval($app['amount_released'] ?? 0) == 0): ?>
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon" style="background:rgba(8,145,178,.1);color:#0891b2;">
                    <i data-lucide="banknote" style="width:14px;height:14px"></i>
                </div>
                Actions
            </div>
        </div>
        <div class="card-body">
            <div class="action-btns">
                <button class="btn btn-primary btn-sm"
                        onclick="openReleaseModal(<?= $app['id'] ?>, '<?= htmlspecialchars($app['client_name'], ENT_QUOTES) ?>', <?= floatval($app['amount_granted'] ?? 0) ?>)">
                    <i data-lucide="banknote" style="width:14px;height:14px"></i> Release Funds
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

</main>

<!-- APPROVE MODAL -->
<div class="modal-overlay" id="approveModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">
                <div class="card-title-icon cti-green" style="width:28px;height:28px;">
                    <i data-lucide="check-circle" style="width:14px;height:14px"></i>
                </div>
                Approve Application
            </div>
            <button class="modal-close" onclick="closeApproveModal()">
                <i data-lucide="x" style="width:14px;height:14px"></i>
            </button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <div class="modal-body">
                <p class="modal-desc" id="approveDesc">Approve this application and set the granted amount.</p>
                <input type="hidden" name="action" value="approved">
                <label style="font-size:.76rem;font-weight:700;color:var(--slate);">Amount Granted (₱)</label>
                <input type="number" name="amount_granted" id="approveAmount" class="modal-amount" step="0.01" min="0" placeholder="0.00" required oninput="checkSAThreshold(this.value)">
                <div class="modal-hint">Requested: ₱<span id="approveRequested"><?= number_format($app['amount_requested'], 2) ?></span></div>
                <div id="saNotice" class="sa-notice" style="display:none;">
                    <i data-lucide="shield" style="width:14px;height:14px"></i>
                    Amount ≥ ₱<?= number_format(SA_APPROVAL_AMOUNT_THRESHOLD, 0) ?> — will be submitted for Super Admin approval.
                </div>
                <div class="modal-hint">Requested: ₱<span id="approveRequested">0.00</span></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeApproveModal()">Cancel</button>
                <button type="submit" class="btn btn-sm" style="background:var(--green);color:#fff;">
                    <i data-lucide="check-circle" style="width:13px;height:13px"></i> Approve
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
                <div class="card-title-icon" style="width:28px;height:28px;background:rgba(8,145,178,.1);color:#0891b2;">
                    <i data-lucide="banknote" style="width:14px;height:14px"></i>
                </div>
                Release Funds
            </div>
            <button class="modal-close" onclick="closeReleaseModal()">
                <i data-lucide="x" style="width:14px;height:14px"></i>
            </button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <div class="modal-body">
                <p class="modal-desc" id="releaseDesc">Release funds for this approved application.</p>
                <input type="hidden" name="action" value="release">
                <label style="font-size:.76rem;font-weight:700;color:var(--slate);">Amount to Release (₱)</label>
                <input type="number" name="amount_released" id="releaseAmount" class="modal-amount" step="0.01" min="0.01" placeholder="0.00" required>
                <div class="modal-hint">Granted: ₱<span id="releaseGranted">0.00</span></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeReleaseModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i data-lucide="banknote" style="width:13px;height:13px"></i> Release Funds
                </button>
            </div>
        </form>
    </div>
</div>

<!-- REJECT MODAL -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">
                <div class="card-title-icon cti-red" style="width:28px;height:28px;">
                    <i data-lucide="x-circle" style="width:14px;height:14px"></i>
                </div>
                Reject Application
            </div>
            <button class="modal-close" onclick="closeRejectModal()">
                <i data-lucide="x" style="width:14px;height:14px"></i>
            </button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <div class="modal-body">
                <p class="modal-desc" id="rejectDesc">You are about to reject this application. Please provide a reason — this will be sent to the applicant.</p>
                <input type="hidden" name="action" value="rejected">
                <textarea name="reason" placeholder="Enter rejection reason…" required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm">
                    <i data-lucide="x-circle" style="width:13px;height:13px"></i> Confirm Rejection
                </button>
            </div>
        </form>
    </div>
</div>

<script src="partials/admin.js"></script>
<script>
const SA_THRESHOLD = <?= SA_APPROVAL_AMOUNT_THRESHOLD ?>;
const IS_SA = <?= $is_sa ? 'true' : 'false' ?>;

function checkSAThreshold(val) {
    const notice = document.getElementById('saNotice');
    if (notice) {
        notice.style.display = (!IS_SA && parseFloat(val) >= SA_THRESHOLD) ? 'flex' : 'none';
        lucide.createIcons();
    }
}

// Approve modal
function openApproveModal(appId, clientName, amountRequested) {
    document.getElementById('approveAmount').value = amountRequested.toFixed(2);
    document.getElementById('approveDesc').textContent = `Approve ${clientName}'s application and set the granted amount.`;
    checkSAThreshold(amountRequested);
    document.getElementById('approveModal').classList.add('open');
}
function closeApproveModal() { document.getElementById('approveModal').classList.remove('open'); }
document.getElementById('approveModal').addEventListener('click', function(e) { if (e.target === this) closeApproveModal(); });

// Release modal
function openReleaseModal(appId, clientName, amountGranted) {
    document.getElementById('releaseAmount').value = amountGranted > 0 ? amountGranted.toFixed(2) : '';
    document.getElementById('releaseGranted').textContent = amountGranted.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
    document.getElementById('releaseDesc').textContent = `Release funds for ${clientName}'s approved application.`;
    document.getElementById('releaseModal').classList.add('open');
}
function closeReleaseModal() { document.getElementById('releaseModal').classList.remove('open'); }
document.getElementById('releaseModal').addEventListener('click', function(e) { if (e.target === this) closeReleaseModal(); });

// Reject modal
function openRejectModal(appId, clientName) {
    document.getElementById('rejectDesc').textContent =
        `You are about to reject ${clientName}'s application. Please provide a reason — this will be sent to the applicant.`;
    document.getElementById('rejectModal').classList.add('open');
}
function closeRejectModal() { document.getElementById('rejectModal').classList.remove('open'); }
document.getElementById('rejectModal').addEventListener('click', function(e) { if (e.target === this) closeRejectModal(); });

// Auto-dismiss toast
setTimeout(() => {
    const t = document.querySelector('.toast');
    if (t) t.style.display = 'none';
}, 4000);
</script>

</body>
</html>
