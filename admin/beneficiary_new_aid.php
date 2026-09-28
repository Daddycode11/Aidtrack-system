<?php
// admin/beneficiary_new_aid.php
require_once __DIR__ . '/../helpers.php';
require_admin();

$admin_id = $_SESSION['user']['id'] ?? 0;

// --- Get beneficiary ---
$id = (int)($_GET['id'] ?? $_POST['user_id'] ?? 0);
if (!$id) { header('Location: beneficiaries.php'); exit; }

$stmt = $mysqli->prepare("SELECT id, name, phone, barangay, role FROM users WHERE id = ? AND role = 'client' LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$beneficiary = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$beneficiary) { header('Location: beneficiaries.php?err=notfound'); exit; }

$errors = [];
$old = [
    'type'             => '',
    'amount_requested' => '',
    'date_of_request'  => date('Y-m-d'),
    'status'           => 'pending',
    'rejection_reason' => '',
    'notes'            => '',
];

// Matches the 'type' enum in the applications table
$aid_types = ['burial', 'medical', 'educational', 'livelihood', 'emergency'];
$type_labels = [
    'burial'      => 'Burial Assistance',
    'medical'     => 'Medical Assistance',
    'educational' => 'Educational Assistance',
    'livelihood'  => 'Livelihood Assistance',
    'emergency'   => 'Emergency Assistance',
];

// Matches the 'status' enum in the applications table
$statuses = ['pending', 'approved', 'rejected', 'cancelled'];

// --- Handle Submit ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_aid') {
    if (!csrf_verify()) { header('Location: beneficiary_new_aid.php?id=' . $id . '&err=csrf'); exit; }

    $type             = $_POST['type'] ?? '';
    $amount_requested = trim($_POST['amount_requested'] ?? '');
    $date_of_request  = trim($_POST['date_of_request'] ?? '');
    $status           = $_POST['status'] ?? 'pending';
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    $notes            = trim($_POST['notes'] ?? '');

    $old = compact('type', 'amount_requested', 'date_of_request', 'status', 'rejection_reason', 'notes');

    if (!in_array($type, $aid_types, true))                     $errors[] = 'Please select a valid aid type.';
    if ($amount_requested === '' || !is_numeric($amount_requested)) $errors[] = 'Amount requested must be a valid number.';
    if (is_numeric($amount_requested) && $amount_requested < 0)  $errors[] = 'Amount cannot be negative.';
    if ($date_of_request === '' || !strtotime($date_of_request)) $errors[] = 'Date of request is required.';
    if (!in_array($status, $statuses, true))                     $errors[] = 'Invalid status selected.';
    if ($status === 'rejected' && $rejection_reason === '')      $errors[] = 'Rejection reason is required when status is Rejected.';

    if (!$errors) {
        $amount_val = (float)$amount_requested;
        // 'amount' is NOT NULL in schema — mirror the requested amount into it at creation time.
        // amount_granted/amount_released stay untouched here; those get set later when the aid is actually approved/released.
        $rejection_reason_val = $status === 'rejected' ? $rejection_reason : null;

        $ins = $mysqli->prepare("
            INSERT INTO applications
                (user_id, type, amount, amount_requested, date_of_request, status, rejection_reason, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $ins->bind_param(
            'isddssss',
            $id, $type, $amount_val, $amount_val, $date_of_request, $status, $rejection_reason_val, $notes
        );
        $ins->execute();
        $new_aid_id = $ins->insert_id;
        $ins->close();

        log_audit($mysqli, 'create_aid', 'application', $new_aid_id,
            "New aid record for {$beneficiary['name']} — type: {$type}, amount requested: ₱" . number_format($amount_val, 2) . ", status: {$status}");

        header('Location: beneficiary_profile.php?id=' . $id . '&toast=aid_added');
        exit;
    }
}

// Partials
$active_page   = 'beneficiaries';
$page_title    = 'New Aid';
$page_subtitle = 'Beneficiaries';
$pending_aids  = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0] ?? 0);
$colors        = ['#1a56db','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'];
$avatar_col    = $colors[$beneficiary['id'] % count($colors)];
$initials      = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $beneficiary['name']), 0, 2)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Aid — <?= htmlspecialchars($beneficiary['name']) ?> — AIDTRACK Admin</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/admin.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
.back-link { display:inline-flex; align-items:center; gap:6px; font-size:.78rem; font-weight:600; color:var(--muted); margin-bottom:14px; }

.hero { display:flex; align-items:center; gap:14px; padding:18px 20px; background:var(--white); border:1px solid var(--border); border-radius:14px; margin-bottom:18px; }
.hero-avatar { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:1rem; flex-shrink:0; }
.hero .name { font-size:1rem; font-weight:800; color:var(--navy); }
.hero .sub  { font-size:.75rem; color:var(--muted); margin-top:2px; display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.hero .sub span { display:flex; align-items:center; gap:5px; }

.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; padding:20px 22px; }
.form-grid.full { grid-template-columns:1fr; }
.f-group { display:flex; flex-direction:column; gap:6px; }
.f-group.span-2 { grid-column:span 2; }
.f-group label { font-size:.76rem; font-weight:700; color:var(--navy); }
.f-group input, .f-group select, .f-group textarea {
    font-family:inherit; font-size:.84rem; color:var(--navy); background:var(--white);
    border:1.5px solid var(--border); border-radius:9px; padding:9px 12px; outline:none;
}
.f-group input:focus, .f-group select:focus, .f-group textarea:focus { border-color:var(--brand); }
.f-group textarea { resize:vertical; min-height:80px; font-family:inherit; }
.amount-wrap { position:relative; }
.amount-wrap .peso { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:.84rem; color:var(--muted); font-weight:700; }
.amount-wrap input { padding-left:26px; }

.form-actions { display:flex; justify-content:flex-end; gap:10px; padding:16px 22px; border-top:1px solid var(--border); background:var(--bg); }

.alert { display:flex; align-items:flex-start; gap:10px; padding:12px 16px; border-radius:10px; font-size:.8rem; font-weight:600; margin-bottom:16px; }
.alert-error { background:var(--red-lt); color:var(--red); border:1px solid rgba(220,38,38,.2); }

.status-choice { display:flex; gap:10px; flex-wrap:wrap; }
.status-choice label {
    display:flex; align-items:center; gap:6px; font-size:.8rem; font-weight:600; color:var(--navy);
    border:1.5px solid var(--border); border-radius:9px; padding:8px 14px; cursor:pointer;
}
.status-choice input { margin:0; }
.status-choice label:has(input:checked) { border-color:var(--brand); background:var(--brand-lt); color:var(--brand); }

#rejection_wrap { display:none; }
#rejection_wrap.show { display:flex; }

@media(max-width:720px){ .form-grid { grid-template-columns:1fr; } .f-group.span-2 { grid-column:span 1; } }
</style>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<main class="main">

    <a href="beneficiary_profile.php?id=<?= $beneficiary['id'] ?>" class="back-link">
        <i data-lucide="arrow-left" style="width:13px;height:13px"></i> Back to Profile
    </a>

    <!-- Beneficiary Hero -->
    <div class="hero">
        <div class="hero-avatar" style="background:<?= $avatar_col ?>"><?= $initials ?></div>
        <div>
            <div class="name"><?= htmlspecialchars($beneficiary['name']) ?></div>
            <div class="sub">
                <span><i data-lucide="hash" style="width:12px;height:12px"></i> ID #<?= $beneficiary['id'] ?></span>
                <span><i data-lucide="phone" style="width:12px;height:12px"></i> <?= htmlspecialchars($beneficiary['phone'] ?? '—') ?></span>
                <span><i data-lucide="map-pin" style="width:12px;height:12px"></i> <?= htmlspecialchars($beneficiary['barangay'] ?? '—') ?></span>
            </div>
        </div>
    </div>

    <?php if (!empty($_GET['err'])): ?>
    <div class="alert alert-error">
        <i data-lucide="alert-circle" style="width:15px;height:15px"></i>
        <?= $_GET['err'] === 'csrf' ? 'Security check failed. Please try again.' : 'Beneficiary not found.' ?>
    </div>
    <?php endif; ?>

    <?php if ($errors): ?>
    <div class="alert alert-error">
        <i data-lucide="alert-circle" style="width:15px;height:15px;flex-shrink:0;"></i>
        <div>
            <?php foreach ($errors as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- New Aid Form -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-blue"><i data-lucide="hand-heart" style="width:14px;height:14px"></i></div>
                Aid Application Details
            </div>
        </div>

        <form method="post" id="newAidForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_aid">
            <input type="hidden" name="user_id" value="<?= $beneficiary['id'] ?>">

            <div class="form-grid">
                <div class="f-group">
                    <label for="type">Aid Type</label>
                    <select id="type" name="type" required>
                        <option value="">Select aid type…</option>
                        <?php foreach ($aid_types as $t): ?>
                        <option value="<?= $t ?>" <?= $old['type']===$t?'selected':''?>><?= htmlspecialchars($type_labels[$t]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="f-group">
                    <label for="amount_requested">Amount Requested</label>
                    <div class="amount-wrap">
                        <span class="peso">₱</span>
                        <input type="number" id="amount_requested" name="amount_requested" step="0.01" min="0"
                               value="<?= htmlspecialchars($old['amount_requested']) ?>" placeholder="0.00" required>
                    </div>
                </div>

                <div class="f-group">
                    <label for="date_of_request">Date of Request</label>
                    <input type="date" id="date_of_request" name="date_of_request"
                           value="<?= htmlspecialchars($old['date_of_request']) ?>" required>
                </div>

                <div class="f-group">
                    <label>&nbsp;</label>
                    <div style="font-size:.72rem;color:var(--muted);padding-top:8px;">
                        Approved/released amounts are set later once the aid is processed.
                    </div>
                </div>

                <div class="f-group span-2">
                    <label>Status</label>
                    <div class="status-choice">
                        <label>
                            <input type="radio" name="status" value="pending" <?= $old['status']==='pending'?'checked':''?> onchange="toggleRejection()">
                            Pending
                        </label>
                        <label>
                            <input type="radio" name="status" value="approved" <?= $old['status']==='approved'?'checked':''?> onchange="toggleRejection()">
                            Approved
                        </label>
                        <label>
                            <input type="radio" name="status" value="rejected" <?= $old['status']==='rejected'?'checked':''?> onchange="toggleRejection()">
                            Rejected
                        </label>
                        <label>
                            <input type="radio" name="status" value="cancelled" <?= $old['status']==='cancelled'?'checked':''?> onchange="toggleRejection()">
                            Cancelled
                        </label>
                    </div>
                </div>

                <div class="f-group span-2" id="rejection_wrap">
                    <label for="rejection_reason">Rejection Reason</label>
                    <textarea id="rejection_reason" name="rejection_reason" placeholder="Reason for rejecting this application…"><?= htmlspecialchars($old['rejection_reason']) ?></textarea>
                </div>

                <div class="f-group span-2">
                    <label for="notes">Notes (optional)</label>
                    <textarea id="notes" name="notes" placeholder="Additional notes about this aid application…"><?= htmlspecialchars($old['notes']) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a href="beneficiary_profile.php?id=<?= $beneficiary['id'] ?>" class="btn btn-outline btn-sm">Cancel</a>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i data-lucide="plus" style="width:13px;height:13px"></i> Save Aid Record
                </button>
            </div>
        </form>
    </div>

</main>

<script src="partials/admin.js"></script>
<script>
function toggleRejection() {
    const status = document.querySelector('input[name="status"]:checked')?.value;
    const wrap = document.getElementById('rejection_wrap');
    if (status === 'rejected') {
        wrap.classList.add('show');
        document.getElementById('rejection_reason').setAttribute('required', 'required');
    } else {
        wrap.classList.remove('show');
        document.getElementById('rejection_reason').removeAttribute('required');
    }
}
toggleRejection();
</script>
</body>
</html>