<?php
// client/aid_history.php
require_once __DIR__ . '/../helpers.php';
require_client();

$uid = $_SESSION['user']['id'];

// --- Filters ---
$f_status = $_GET['status'] ?? '';
$f_type   = $_GET['type']   ?? '';

$where = ['a.user_id = ?']; $params = [$uid]; $types = 'i';
if ($f_status) { $where[] = 'a.status = ?';  $params[] = $f_status; $types .= 's'; }
if ($f_type)   { $where[] = 'a.type = ?';    $params[] = $f_type;   $types .= 's'; }
$where_sql = implode(' AND ', $where);

// --- Fetch applications ---
$stmt = $mysqli->prepare("
    SELECT a.id, a.type, a.amount_requested, a.status, a.date_of_request,
           a.rejection_reason, a.notes, a.created_at
    FROM applications a
    WHERE $where_sql
    ORDER BY a.created_at DESC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Stats ---
$total    = count($applications);
$approved = count(array_filter($applications, fn($a) => $a['status'] === 'approved'));
$pending  = count(array_filter($applications, fn($a) => $a['status'] === 'pending'));
$rejected = count(array_filter($applications, fn($a) => $a['status'] === 'rejected'));

$has_filters = $f_status || $f_type;

// Partials
$active_page   = 'aid_history';
$page_title    = 'Aid History';
$page_subtitle = 'Aid History';
$colors = ['#e87400','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aid History — AIDTRACK</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/client.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
.summary-strip { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:16px; }
.ss-card { background:var(--white); border:1px solid var(--border); border-radius:var(--r); padding:14px 16px; display:flex; align-items:center; gap:12px; }
.ss-icon { width:34px; height:34px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.ss-n { font-size:1.3rem; font-weight:800; line-height:1; }
.ss-l { font-size:.66rem; font-weight:600; color:var(--muted); margin-top:2px; }

.filter-strip {
    display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    padding:12px 20px; border-bottom:1px solid var(--border); background:var(--bg);
}
.filter-strip select {
    font-family:inherit; font-size:.8rem; color:var(--navy);
    background:var(--white); border:1.5px solid var(--border); border-radius:8px;
    padding:7px 12px; outline:none; transition:border-color .15s;
}
.filter-strip select:focus { border-color:var(--brand); }

@media(max-width:768px) { .summary-strip { grid-template-columns:1fr 1fr; } }
@media(max-width:480px) { .summary-strip { grid-template-columns:1fr; } }
</style>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<main class="main">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-top">
            <div>
                <div class="page-title">Aid History</div>
                <div class="page-sub">Track all your submitted assistance requests and their status.</div>
            </div>
            <div class="page-actions">
                <span class="count-pill"><?= $total ?> records</span>
                <a href="apply.php" class="btn btn-primary btn-sm">
                    <i data-lucide="file-plus" style="width:13px;height:13px"></i> New Request
                </a>
            </div>
        </div>
    </div>

    <!-- Summary Strip -->
    <div class="summary-strip">
        <div class="ss-card">
            <div class="ss-icon" style="background:var(--brand-lt);color:var(--brand);">
                <i data-lucide="file-text" style="width:16px;height:16px"></i>
            </div>
            <div>
                <div class="ss-n"><?= $total ?></div>
                <div class="ss-l">Total</div>
            </div>
        </div>
        <div class="ss-card">
            <div class="ss-icon" style="background:var(--green-lt);color:var(--green);">
                <i data-lucide="check-circle" style="width:16px;height:16px"></i>
            </div>
            <div>
                <div class="ss-n" style="color:var(--green);"><?= $approved ?></div>
                <div class="ss-l">Approved</div>
            </div>
        </div>
        <div class="ss-card">
            <div class="ss-icon" style="background:var(--yellow-lt);color:var(--yellow);">
                <i data-lucide="hourglass" style="width:16px;height:16px"></i>
            </div>
            <div>
                <div class="ss-n" style="color:var(--yellow);"><?= $pending ?></div>
                <div class="ss-l">Pending</div>
            </div>
        </div>
        <div class="ss-card">
            <div class="ss-icon" style="background:var(--red-lt);color:var(--red);">
                <i data-lucide="x-circle" style="width:16px;height:16px"></i>
            </div>
            <div>
                <div class="ss-n" style="color:var(--red);"><?= $rejected ?></div>
                <div class="ss-l">Rejected</div>
            </div>
        </div>
    </div>

    <!-- Applications Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-orange"><i data-lucide="history" style="width:14px;height:14px"></i></div>
                Application Records
            </div>
        </div>

        <!-- Filter -->
        <form method="get" class="filter-strip">
            <i data-lucide="filter" style="width:14px;height:14px;color:var(--muted)"></i>
            <select name="type">
                <option value="">All Types</option>
                <option value="medical" <?= $f_type==='medical'?'selected':'' ?>>Medical</option>
                <option value="burial"  <?= $f_type==='burial'?'selected':'' ?>>Burial</option>
            </select>
            <select name="status">
                <option value="">All Status</option>
                <option value="pending"  <?= $f_status==='pending'?'selected':''  ?>>Pending</option>
                <option value="approved" <?= $f_status==='approved'?'selected':'' ?>>Approved</option>
                <option value="rejected" <?= $f_status==='rejected'?'selected':'' ?>>Rejected</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">
                <i data-lucide="search" style="width:12px;height:12px"></i> Filter
            </button>
            <?php if ($has_filters): ?>
            <a href="aid_history.php" class="btn btn-outline btn-sm">
                <i data-lucide="x" style="width:12px;height:12px"></i> Clear
            </a>
            <?php endif; ?>
        </form>

        <div class="card-body no-pad">
            <?php if (empty($applications)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="search-x" style="width:20px;height:20px"></i></div>
                <div class="empty-text">No applications found. <?php if ($has_filters): ?><a href="aid_history.php" style="color:var(--brand);font-weight:600;">Clear filters</a><?php else: ?><a href="apply.php" style="color:var(--brand);font-weight:600;">Submit your first request.</a><?php endif; ?></div>
            </div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table data-paginate="10">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Date Requested</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($applications as $i => $a):
                        $s  = strtolower($a['status']);
                        $bc = $s === 'approved' ? 'b-approved' : ($s === 'pending' ? 'b-pending' : ($s === 'rejected' ? 'b-rejected' : 'b-cancelled'));
                    ?>
                    <tr>
                        <td style="color:var(--muted);font-size:.74rem;"><?= $a['id'] ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="row-avatar" style="background:<?= $colors[$i % count($colors)] ?>;width:28px;height:28px;font-size:.58rem;">
                                    <?= strtoupper(substr($a['type'], 0, 2)) ?>
                                </div>
                                <span style="font-weight:700;font-size:.82rem;"><?= ucfirst($a['type']) ?></span>
                            </div>
                        </td>
                        <td style="font-family:monospace;font-weight:700;font-size:.8rem;">₱<?= number_format($a['amount_requested'] ?? 0, 2) ?></td>
                        <td style="color:var(--muted);white-space:nowrap;"><?= date('M d, Y', strtotime($a['date_of_request'])) ?></td>
                        <td><span class="badge <?= $bc ?>"><?= ucfirst($s) ?></span></td>
                        <td>
                            <a href="view_application.php?id=<?= $a['id'] ?>" style="font-size:.75rem;font-weight:700;color:var(--brand);">View</a>
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

<script src="partials/client.js"></script>
</body>
</html>
