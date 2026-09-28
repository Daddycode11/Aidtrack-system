<?php
// admin/beneficiary_profile.php
require_once __DIR__ . '/../helpers.php';
require_admin();

// --- Get beneficiary ID ---
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: beneficiaries.php'); exit; }

// --- Fetch beneficiary ---
$stmt = $mysqli->prepare("SELECT id, name, phone, barangay, role, created_at FROM users WHERE id = ? AND role = 'client' LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$beneficiary = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$beneficiary) { header('Location: beneficiaries.php'); exit; }

// --- Fetch this beneficiary's aid applications ---
$stmt = $mysqli->prepare("
    SELECT id, type, amount, amount_requested, amount_granted, amount_released,
           date_of_request, status, rejection_reason, notes, created_at
    FROM applications
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param('i', $id);
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Stats ---
$total_aids    = count($applications);
$approved_aids = count(array_filter($applications, fn($a) => $a['status'] === 'approved'));
$pending_aids2 = count(array_filter($applications, fn($a) => $a['status'] === 'pending'));
$rejected_aids = count(array_filter($applications, fn($a) => $a['status'] === 'rejected'));
$total_granted = array_sum(array_column($applications, 'amount_granted'));

// Partials
$active_page   = 'beneficiaries';
$page_title    = 'Beneficiary Profile';
$page_subtitle = 'Beneficiaries';
$pending_aids  = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0] ?? 0);
$colors        = ['#1a56db','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'];
$avatar_col    = $colors[$beneficiary['id'] % count($colors)];
$initials      = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $beneficiary['name']), 0, 2)));

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
<title><?= htmlspecialchars($beneficiary['name']) ?> — AIDTRACK Admin</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/admin.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
.profile-hero {
    display:flex; align-items:center; gap:16px; flex-wrap:wrap;
    background:var(--white); border:1px solid var(--border); border-radius:14px;
    padding:20px 22px; margin-bottom:18px;
}
.profile-avatar-lg {
    width:56px; height:56px; border-radius:14px; display:flex; align-items:center; justify-content:center;
    color:#fff; font-weight:800; font-size:1.15rem; flex-shrink:0;
}
.profile-meta { display:flex; flex-direction:column; gap:2px; }
.profile-name { font-size:1.05rem; font-weight:800; color:var(--navy); }
.profile-sub { font-size:.78rem; color:var(--muted); display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin-top:4px; }
.profile-sub span { display:flex; align-items:center; gap:5px; }

.stat-row { display:grid; grid-template-columns:repeat(5,1fr); gap:14px; margin-bottom:18px; }
.stat-card { background:var(--white); border:1px solid var(--border); border-radius:12px; padding:14px 16px; }
.stat-card .stat-label { font-size:.68rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.03em; }
.stat-card .stat-value { font-size:1.3rem; font-weight:800; color:var(--navy); margin-top:4px; }
.stat-card.sc-blue .stat-value   { color:var(--brand); }
.stat-card.sc-green .stat-value  { color:var(--green); }
.stat-card.sc-yellow .stat-value { color:#ca8a04; }
.stat-card.sc-red .stat-value    { color:#dc2626; }

.b-approved  { background:var(--green-lt); color:var(--green); }
.b-pending   { background:#fef9c3; color:#ca8a04; }
.b-rejected  { background:#fee2e2; color:#dc2626; }
.b-cancelled { background:#f1f5f9; color:#64748b; }
.badge { display:inline-flex; align-items:center; gap:4px; font-size:.68rem; font-weight:700; padding:3px 10px; border-radius:99px; }

.back-link { display:inline-flex; align-items:center; gap:6px; font-size:.78rem; font-weight:600; color:var(--muted); margin-bottom:14px; }

.toast { position:fixed; bottom:24px; right:24px; z-index:999; display:flex; align-items:center; gap:10px; padding:12px 18px; border-radius:10px; font-size:.83rem; font-weight:600; box-shadow:0 8px 28px rgba(0,0,0,.15); background:var(--green-lt); color:var(--green); border:1px solid rgba(22,163,74,.2); animation:toastIn .3s ease, toastOut .4s ease 3s forwards; }
@keyframes toastIn  { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:none} }
@keyframes toastOut { from{opacity:1} to{opacity:0;pointer-events:none} }

@media(max-width:900px){ .stat-row { grid-template-columns:repeat(2,1fr); } }
</style>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<?php if (($_GET['toast'] ?? '') === 'aid_added'): ?>
<div class="toast">
    <i data-lucide="check-circle" style="width:15px;height:15px"></i>
    New aid record added successfully.
</div>
<?php endif; ?>

<main class="main">

    <a href="beneficiaries.php" class="back-link">
        <i data-lucide="arrow-left" style="width:13px;height:13px"></i> Back to Beneficiaries
    </a>

    <!-- Profile Hero -->
    <div class="profile-hero">
        <div class="profile-avatar-lg" style="background:<?= $avatar_col ?>"><?= $initials ?></div>
        <div class="profile-meta" style="flex:1;">
            <div class="profile-name"><?= htmlspecialchars($beneficiary['name']) ?></div>
            <div class="profile-sub">
                <span><i data-lucide="hash" style="width:12px;height:12px"></i> ID #<?= $beneficiary['id'] ?></span>
                <span><i data-lucide="phone" style="width:12px;height:12px"></i> <?= htmlspecialchars($beneficiary['phone'] ?? '—') ?></span>
                <span><i data-lucide="map-pin" style="width:12px;height:12px"></i> <?= htmlspecialchars($beneficiary['barangay'] ?? '—') ?></span>
                <span><i data-lucide="calendar" style="width:12px;height:12px"></i> Registered <?= $beneficiary['created_at'] ? date('M d, Y', strtotime($beneficiary['created_at'])) : '—' ?></span>
            </div>
        </div>
        <a href="beneficiary_new_aid.php?id=<?= $beneficiary['id'] ?>" class="btn btn-primary btn-sm">
            <i data-lucide="plus" style="width:13px;height:13px"></i> New Aid
        </a>
    </div>

    <!-- Stats -->
    <div class="stat-row">
        <div class="stat-card sc-blue">
            <div class="stat-label">Total Aids</div>
            <div class="stat-value"><?= $total_aids ?></div>
        </div>
        <div class="stat-card sc-green">
            <div class="stat-label">Approved</div>
            <div class="stat-value"><?= $approved_aids ?></div>
        </div>
        <div class="stat-card sc-yellow">
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?= $pending_aids2 ?></div>
        </div>
        <div class="stat-card sc-red">
            <div class="stat-label">Rejected</div>
            <div class="stat-value"><?= $rejected_aids ?></div>
        </div>
        <div class="stat-card sc-green">
            <div class="stat-label">Total Granted</div>
            <div class="stat-value">₱<?= number_format($total_granted, 2) ?></div>
        </div>
    </div>

    <!-- Aid History -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-blue"><i data-lucide="history" style="width:14px;height:14px"></i></div>
                Aid Application History
                <span class="count-pill"><?= $total_aids ?></span>
            </div>
        </div>

        <div class="card-body no-pad">
            <?php if (empty($applications)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="inbox" style="width:20px;height:20px"></i></div>
                <div class="empty-text">This beneficiary has no aid applications yet.</div>
            </div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table data-paginate="10">
                    <thead>
                        <tr>
                            <th>Aid Type</th>
                            <th>Requested</th>
                            <th>Granted</th>
                            <th>Released</th>
                            <th>Status</th>
                            <th>Date Requested</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($applications as $a): ?>
                    <tr>
                        <td style="font-weight:700;font-size:.82rem;color:var(--navy);"><?= htmlspecialchars($type_labels[$a['type']] ?? ucfirst($a['type'])) ?></td>
                        <td style="font-size:.82rem;color:var(--navy);">₱<?= number_format((float)$a['amount_requested'], 2) ?></td>
                        <td style="font-size:.82rem;color:var(--navy);"><?= $a['amount_granted'] !== null ? '₱' . number_format((float)$a['amount_granted'], 2) : '—' ?></td>
                        <td style="font-size:.82rem;color:var(--navy);"><?= $a['amount_released'] !== null ? '₱' . number_format((float)$a['amount_released'], 2) : '—' ?></td>
                        <td>
                            <span class="badge <?= $status_badge[$a['status']] ?? '' ?>">
                                <?= htmlspecialchars(ucfirst($a['status'])) ?>
                            </span>
                            <?php if ($a['status'] === 'rejected' && $a['rejection_reason']): ?>
                            <div style="font-size:.68rem;color:var(--muted);margin-top:3px;max-width:180px;"><?= htmlspecialchars($a['rejection_reason']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--muted);font-size:.76rem;white-space:nowrap;">
                            <?= $a['date_of_request'] ? date('M d, Y', strtotime($a['date_of_request'])) : '—' ?>
                        </td>
                        <td style="color:var(--muted);font-size:.78rem;"><?= htmlspecialchars($a['notes'] ?? '—') ?></td>
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