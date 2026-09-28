<?php
// admin/review_application.php
require_once __DIR__ . '/../helpers.php';
require_admin();

$admin_id = $_SESSION['user']['id'] ?? 0;

// --- Get application ---
$app_id = (int)($_GET['id'] ?? $_POST['app_id'] ?? 0);
if (!$app_id) { header('Location: beneficiaries.php'); exit; }

$stmt = $mysqli->prepare("
    SELECT a.*, u.name AS beneficiary_name, u.phone, u.barangay
    FROM applications a
    JOIN users u ON u.id = a.user_id
    WHERE a.id = ?
");
$stmt->bind_param('i', $app_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app) { header('Location: beneficiaries.php?err=notfound'); exit; }

// --- Attached documents ---
$docs = [];
$stmt = $mysqli->prepare("SELECT id, filename, original_name FROM documents WHERE application_id = ?");
$stmt->bind_param('i', $app_id);
$stmt->execute();
$docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$errors = [];
$is_locked = $app['status'] !== 'pending'; // already approved/rejected/cancelled

// --- Handle Decision ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {
    if (!csrf_verify()) { header('Location: review_application.php?id=' . $app_id . '&err=csrf'); exit; }

    $decision = $_POST['decision'] ?? '';

    if ($decision === 'approve') {
        $amount_granted = trim($_POST['amount_granted'] ?? '');

        if ($amount_granted === '' || !is_numeric($amount_granted)) {
            $errors[] = 'Amount granted must be a valid number.';
        } elseif ((float)$amount_granted <= 0) {
            $errors[] = 'Amount granted must be greater than zero.';
        }

        if (!$errors) {
            $amt = (float)$amount_granted;
            $upd = $mysqli->prepare("
                UPDATE applications
                SET status = 'approved', amount = ?, amount_granted = ?, rejection_reason = NULL
                WHERE id = ?
            ");
            $upd->bind_param('ddi', $amt, $amt, $app_id);
            $upd->execute();
            $upd->close();

            log_audit($mysqli, 'approve_application', 'application', $app_id,
                "Approved {$app['type']} application for {$app['beneficiary_name']} — amount granted: ₱" . number_format($amt, 2));

            header('Location: beneficiary_profile.php?id=' . $app['user_id'] . '&toast=aid_approved');
            exit;
        }

    } elseif ($decision === 'reject') {
        $reason = trim($_POST['rejection_reason'] ?? '');

        if ($reason === '') {
            $errors[] = 'Rejection reason is required.';
        }

        if (!$errors) {
            $upd = $mysqli->prepare("
                UPDATE applications
                SET status = 'rejected', rejection_reason = ?
                WHERE id = ?
            ");
            $upd->bind_param('si', $reason, $app_id);
            $upd->execute();
            $upd->close();

            log_audit($mysqli, 'reject_application', 'application', $app_id,
                "Rejected {$app['type']} application for {$app['beneficiary_name']} — reason: {$reason}");

            header('Location: beneficiary_profile.php?id=' . $app['user_id'] . '&toast=aid_rejected');
            exit;
        }

    } elseif ($decision === 'cancel') {
        $upd = $mysqli->prepare("UPDATE applications SET status = 'cancelled' WHERE id = ?");
        $upd->bind_param('i', $app_id);
        $upd->execute();
        $upd->close();

        log_audit($mysqli, 'cancel_application', 'application', $app_id,
            "Cancelled {$app['type']} application for {$app['beneficiary_name']}");

        header('Location: beneficiary_profile.php?id=' . $app['user_id'] . '&toast=aid_cancelled');
        exit;
    } else {
        $errors[] = 'Invalid decision.';
    }
}

// Partials
$active_page   = 'beneficiaries';
$page_title    = 'Review Application';
$page_subtitle = 'Beneficiaries';
$pending_aids  = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0] ?? 0);
$colors        = ['#1a56db','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'];
$avatar_col    = $colors[$app['user_id'] % count($colors)];
$initials      = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $app['beneficiary_name']), 0, 2)));

$type_labels = [
    'burial'      => 'Burial Assistance',
    'medical'     => 'Medical Assistance',
    'educational' => 'Educational Assistance',
    'livelihood'  => 'Livelihood Assistance',
    'emergency'   => 'Emergency Assistance',
];
$status_badge = [
    'approved'  => 'b-approved',
    'pending'   => 'b-pending',
    'rejected'  => 'b-rejected',
    'cancelled' => 'b-cancelled',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Review Application — AIDTRACK Admin</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/admin.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
.back-link { display:inline-flex; align-items:center; gap:6px; font-size:.78rem; font-weight:600; color:var(--muted); margin-bottom:14px; }

.hero { display:flex; align-items:center; gap:14px; padding:18px 20px; background:var(--white); border:1px solid var(--border); border-radius:14px; margin-bottom:18px; flex-wrap:wrap; }
.hero-avatar { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:1rem; flex-shrink:0; }
.hero .name { font-size:1rem; font-weight:800; color:var(--navy); }
.hero .sub  { font-size:.75rem; color:var(--muted); margin-top:2px; display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.hero .sub span { display:flex; align-items:center; gap:5px; }

.detail-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; padding:20px 22px; }
.detail-item .dl { font-size:.68rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.03em; }
.detail-item .dv { font-size:.86rem; font-weight:700; color:var(--navy); margin-top:3px; }

.doc-list { display:flex; flex-direction:column; gap:8px; padding:0 22px 20px; }
.doc-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border:1px solid var(--border); border-radius:9px; background:var(--bg); }
.doc-item i { color:var(--brand); flex-shrink:0; }
.doc-item .dn { font-size:.8rem; font-weight:600; color:var(--navy); flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

.badge { display:inline-flex; align-items:center; gap:4px; font-size:.68rem; font-weight:700; padding:3px 10px; border-radius:99px; }
.b-approved  { background:var(--green-lt); color:var(--green); }
.b-pending   { background:#fef9c3; color:#ca8a04; }
.b-rejected  { background:#fee2e2; color:#dc2626; }
.b-cancelled { background:#f1f5f9; color:#64748b; }

.decision-tabs { display:flex; gap:8px; padding:16px 22px 0; border-top:1px solid var(--border); }
.decision-tab {
    flex:1; text-align:center; padding:10px 14px; border-radius:9px; font-size:.82rem; font-weight:700;
    border:1.5px solid var(--border); cursor:pointer; color:var(--muted); display:flex; align-items:center; justify-content:center; gap:6px;
}
.decision-tab.active-approve { border-color:var(--green); background:var(--green-lt); color:var(--green); }
.decision-tab.active-reject  { border-color:var(--red); background:var(--red-lt); color:var(--red); }
.decision-tab.active-cancel  { border-color:#64748b; background:#f1f5f9; color:#64748b; }

.decision-panel { display:none; padding:18px 22px; }
.decision-panel.show { display:block; }

.f-group { display:flex; flex-direction:column; gap:6px; margin-bottom:14px; }
.f-group label { font-size:.76rem; font-weight:700; color:var(--navy); }
.f-group input, .f-group textarea {
    font-family:inherit; font-size:.84rem; color:var(--navy); background:var(--white);
    border:1.5px solid var(--border); border-radius:9px; padding:9px 12px; outline:none;
}
.f-group textarea { resize:vertical; min-height:80px; font-family:inherit; }
.amount-wrap { position:relative; }
.amount-wrap .peso { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:.84rem; color:var(--muted); font-weight:700; }
.amount-wrap input { padding-left:26px; }

.form-actions { display:flex; justify-content:flex-end; gap:10px; padding:16px 22px; border-top:1px solid var(--border); background:var(--bg); }
.alert { display:flex; align-items:flex-start; gap:10px; padding:12px 16px; border-radius:10px; font-size:.8rem; font-weight:600; margin-bottom:16px; }
.alert-error { background:var(--red-lt); color:var(--red); border:1px solid rgba(220,38,38,.2); }
.alert-info  { background:#eff4ff; color:#1a56db; border:1px solid rgba(26,86,219,.15); }

@media(max-width:720px){ .detail-grid { grid-template-columns:1fr 1fr; } .decision-tabs { flex-wrap:wrap; } }
</style>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<main class="main">

    <a href="beneficiary_profile.php?id=<?= $app['user_id'] ?>" class="back-link">
        <i data-lucide="arrow-left" style="width:13px;height:13px"></i> Back to Profile
    </a>

    <!-- Beneficiary Hero -->
    <div class="hero">
        <div class="hero-avatar" style="background:<?= $avatar_col ?>"><?= $initials ?></div>
        <div style="flex:1;">
            <div class="name"><?= htmlspecialchars($app['beneficiary_name']) ?></div>
            <div class="sub">
                <span><i data-lucide="phone" style="width:12px;height:12px"></i> <?= htmlspecialchars($app['phone'] ?? '—') ?></span>
                <span><i data-lucide="map-pin" style="width:12px;height:12px"></i> <?= htmlspecialchars($app['barangay'] ?? '—') ?></span>
            </div>
        </div>
        <span class="badge <?= $status_badge[$app['status']] ?? '' ?>" style="font-size:.75rem;padding:5px 14px;">
            <?= htmlspecialchars(ucfirst($app['status'])) ?>
        </span>
    </div>

    <?php if (!empty($_GET['err'])): ?>
    <div class="alert alert-error">
        <i data-lucide="alert-circle" style="width:15px;height:15px"></i>
        <?= $_GET['err'] === 'csrf' ? 'Security check failed. Please try again.' : 'Application not found.' ?>
    </div>
    <?php endif; ?>

    <?php if ($errors): ?>
    <div class="alert alert-error">
        <i data-lucide="alert-circle" style="width:15px;height:15px;flex-shrink:0;"></i>
        <div><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
    </div>
    <?php endif; ?>

    <?php if ($is_locked): ?>
    <div class="alert alert-info">
        <i data-lucide="info" style="width:15px;height:15px"></i>
        This application has already been <?= htmlspecialchars($app['status']) ?> and can no longer be modified here.
    </div>
    <?php endif; ?>

    <!-- Application Details -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-blue"><i data-lucide="file-text" style="width:14px;height:14px"></i></div>
                Application Details
            </div>
        </div>

        <div class="detail-grid">
            <div class="detail-item">
                <div class="dl">Aid Type</div>
                <div class="dv"><?= htmlspecialchars($type_labels[$app['type']] ?? ucfirst($app['type'])) ?></div>
            </div>
            <div class="detail-item">
                <div class="dl">Date of Request</div>
                <div class="dv"><?= $app['date_of_request'] ? date('M d, Y', strtotime($app['date_of_request'])) : '—' ?></div>
            </div>
            <div class="detail-item">
                <div class="dl">Submitted</div>
                <div class="dv"><?= date('M d, Y h:i A', strtotime($app['created_at'])) ?></div>
            </div>
            <?php if ($app['status'] === 'approved'): ?>
            <div class="detail-item">
                <div class="dl">Amount Granted</div>
                <div class="dv" style="color:var(--green)">₱<?= number_format((float)$app['amount_granted'], 2) ?></div>
            </div>
            <?php elseif ($app['status'] === 'rejected'): ?>
            <div class="detail-item" style="grid-column:span 2;">
                <div class="dl">Rejection Reason</div>
                <div class="dv" style="color:var(--red);font-weight:600;"><?= htmlspecialchars($app['rejection_reason'] ?? '—') ?></div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($app['notes']): ?>
        <div style="padding:0 22px 20px;">
            <div class="dl" style="font-size:.68rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;margin-bottom:4px;">Notes from Beneficiary</div>
            <div style="font-size:.82rem;color:var(--navy);background:var(--bg);border:1px solid var(--border);border-radius:9px;padding:10px 14px;"><?= nl2br(htmlspecialchars($app['notes'])) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Attached Documents -->
    <div class="card" style="margin-top:18px;">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon" style="background:#fff7ed;color:#c2410c;"><i data-lucide="paperclip" style="width:14px;height:14px"></i></div>
                Attached Documents
                <span class="count-pill"><?= count($docs) ?></span>
            </div>
        </div>
        <?php if (empty($docs)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i data-lucide="file-x" style="width:20px;height:20px"></i></div>
            <div class="empty-text">No documents were attached to this application.</div>
        </div>
        <?php else: ?>
        <div class="doc-list">
            <?php foreach ($docs as $d): ?>
            <a href="../uploads/<?= htmlspecialchars($d['filename']) ?>" target="_blank" class="doc-item" style="text-decoration:none;">
                <i data-lucide="file" style="width:15px;height:15px"></i>
                <span class="dn"><?= htmlspecialchars($d['original_name']) ?></span>
                <i data-lucide="external-link" style="width:13px;height:13px;color:var(--muted);"></i>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!$is_locked): ?>
    <!-- Decision Panel -->
    <div class="card" style="margin-top:18px;">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon" style="background:#f5f3ff;color:#7c3aed;"><i data-lucide="gavel" style="width:14px;height:14px"></i></div>
                Make a Decision
            </div>
        </div>

        <div class="decision-tabs">
            <div class="decision-tab active-approve" data-target="approvePanel" onclick="showPanel('approve')">
                <i data-lucide="check-circle" style="width:14px;height:14px"></i> Approve
            </div>
            <div class="decision-tab" data-target="rejectPanel" onclick="showPanel('reject')">
                <i data-lucide="x-circle" style="width:14px;height:14px"></i> Reject
            </div>
            <div class="decision-tab" data-target="cancelPanel" onclick="showPanel('cancel')">
                <i data-lucide="ban" style="width:14px;height:14px"></i> Cancel
            </div>
        </div>

        <!-- Approve Panel -->
        <form method="post" class="decision-panel show" id="approvePanel">
            <?= csrf_field() ?>
            <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
            <input type="hidden" name="decision" value="approve">
            <div class="f-group">
                <label for="amount_granted">Amount to Grant</label>
                <div class="amount-wrap">
                    <span class="peso">₱</span>
                    <input type="number" id="amount_granted" name="amount_granted" step="0.01" min="0.01" placeholder="0.00">
                </div>
            </div>
            <div class="form-actions" style="padding:0;border-top:none;background:none;">
                <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Approve this application and grant the specified amount?')">
                    <i data-lucide="check-circle" style="width:13px;height:13px"></i> Approve Application
                </button>
            </div>
        </form>

        <!-- Reject Panel -->
        <form method="post" class="decision-panel" id="rejectPanel">
            <?= csrf_field() ?>
            <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
            <input type="hidden" name="decision" value="reject">
            <div class="f-group">
                <label for="rejection_reason">Rejection Reason</label>
                <textarea id="rejection_reason" name="rejection_reason" placeholder="Explain why this application is being rejected…"></textarea>
            </div>
            <div class="form-actions" style="padding:0;border-top:none;background:none;">
                <button type="submit" class="btn btn-sm" style="background:var(--red);color:#fff;" onclick="return confirm('Reject this application?')">
                    <i data-lucide="x-circle" style="width:13px;height:13px"></i> Reject Application
                </button>
            </div>
        </form>

        <!-- Cancel Panel -->
        <form method="post" class="decision-panel" id="cancelPanel">
            <?= csrf_field() ?>
            <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
            <input type="hidden" name="decision" value="cancel">
            <div style="font-size:.8rem;color:var(--muted);margin-bottom:14px;">
                This marks the application as cancelled without approving or rejecting it — use this for withdrawn or duplicate requests.
            </div>
            <div class="form-actions" style="padding:0;border-top:none;background:none;">
                <button type="submit" class="btn btn-outline btn-sm" onclick="return confirm('Cancel this application?')">
                    <i data-lucide="ban" style="width:13px;height:13px"></i> Cancel Application
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

</main>

<script src="partials/admin.js"></script>
<script>
function showPanel(which) {
    document.querySelectorAll('.decision-panel').forEach(p => p.classList.remove('show'));
    document.querySelectorAll('.decision-tab').forEach(t => t.classList.remove('active-approve','active-reject','active-cancel'));

    document.getElementById(which + 'Panel').classList.add('show');
    document.querySelector('.decision-tab[data-target="' + which + 'Panel"]').classList.add('active-' + which);
}
</script>
</body>
</html>