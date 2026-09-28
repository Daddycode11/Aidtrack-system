<?php
// admin/aid_history.php
require_once __DIR__ . '/../helpers.php';
require_admin();

// --- Filters ---
$search_name   = trim($_GET['name'] ?? '');
$f_barangay    = $_GET['barangay'] ?? '';
$f_status      = $_GET['status']   ?? '';
$f_type        = $_GET['type']     ?? '';

// --- Build query ---
$where = ['1=1']; $params = []; $types = '';
if ($search_name)   { $where[] = 'u.name LIKE ?';    $params[] = "%$search_name%";  $types .= 's'; }
if ($f_barangay)    { $where[] = 'u.barangay = ?';    $params[] = $f_barangay;       $types .= 's'; }
if ($f_status)      { $where[] = 'a.status = ?';      $params[] = $f_status;         $types .= 's'; }
if ($f_type)        { $where[] = 'a.type = ?';        $params[] = $f_type;           $types .= 's'; }
$where_sql = implode(' AND ', $where);

$aid_history = [];
$stmt = $mysqli->prepare("
    SELECT a.id, a.type AS assistance, a.status, a.date_of_request,
           a.amount_requested,
           u.name AS full_name,
           u.barangay
    FROM applications a
    JOIN users u ON a.user_id = u.id
    WHERE $where_sql
    ORDER BY a.date_of_request DESC
");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$aid_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Dropdown data
$barangay_list = array_column($mysqli->query("SELECT DISTINCT barangay FROM users WHERE barangay != '' ORDER BY barangay")->fetch_all(MYSQLI_ASSOC), 'barangay');
$type_list     = ['medical','burial'];
$status_list   = ['pending','approved','rejected'];

$has_filters = $search_name || $f_barangay || $f_status || $f_type;

// Stats
$approved_count = count(array_filter($aid_history, fn($r) => strtolower($r['status']) === 'approved'));
$pending_count  = count(array_filter($aid_history, fn($r) => strtolower($r['status']) === 'pending'));
$rejected_count = count(array_filter($aid_history, fn($r) => strtolower($r['status']) === 'rejected'));

// Partials
$active_page   = 'aid_history';
$page_title    = 'Aid History';
$page_subtitle = 'Aid History';
$pending_aids  = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0] ?? 0);
$colors        = ['#1a56db','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aid History — AIDTRACK Admin</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/admin.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
/* ── AID HISTORY SPECIFIC ── */
.summary-strip {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}
.ss-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--r);
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.ss-icon { width: 36px; height: 36px; border-radius: 9px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.ss-n { font-size: 1.4rem; font-weight: 800; line-height: 1; }
.ss-l { font-size: .68rem; font-weight: 600; color: var(--muted); margin-top: 2px; }

.filter-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--r);
    padding: 16px 18px;
    margin-bottom: 16px;
}
.filter-title { font-size: .76rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }
.filter-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; align-items: end; }
.filter-field { display: flex; flex-direction: column; gap: 5px; }
.filter-field label { font-size: .72rem; font-weight: 700; color: var(--slate); }
.filter-field input,
.filter-field select {
    font-family: inherit; font-size: .8rem; color: var(--navy);
    background: var(--bg); border: 1.5px solid var(--border);
    border-radius: 8px; padding: 7px 11px; outline: none;
    transition: border-color .15s;
}
.filter-field input:focus,
.filter-field select:focus { border-color: var(--brand); background: var(--white); }
.filter-actions { display: flex; gap: 8px; align-items: flex-end; }

.amount { font-family: monospace; font-size: .8rem; font-weight: 700; }

@media(max-width:1100px){ .filter-grid { grid-template-columns: repeat(3,1fr); } }
@media(max-width:860px) { .summary-strip { grid-template-columns: 1fr 1fr; } .filter-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:480px) { .summary-strip { grid-template-columns: 1fr 1fr; } .filter-grid { grid-template-columns: 1fr; } }
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
                <div class="page-sub">Complete record of all financial assistance requests and their outcomes.</div>
            </div>
            <div class="page-actions">
                <span class="count-pill"><?= count($aid_history) ?> results</span>
                <a href="#" class="btn btn-primary btn-sm">
                    <i data-lucide="file-down" style="width:13px;height:13px"></i> Export
                </a>
            </div>
        </div>
    </div>

    <!-- Summary Strip -->
    <div class="summary-strip">
        <div class="ss-card">
            <div class="ss-icon sci-purple" style="background:#f5f3ff;color:#7c3aed;">
                <i data-lucide="file-text" style="width:17px;height:17px"></i>
            </div>
            <div>
                <div class="ss-n"><?= count($aid_history) ?></div>
                <div class="ss-l">Total Records</div>
            </div>
        </div>
        <div class="ss-card">
            <div class="ss-icon" style="background:var(--green-lt);color:var(--green);">
                <i data-lucide="check-circle" style="width:17px;height:17px"></i>
            </div>
            <div>
                <div class="ss-n" style="color:var(--green);"><?= $approved_count ?></div>
                <div class="ss-l">Approved</div>
            </div>
        </div>
        <div class="ss-card">
            <div class="ss-icon" style="background:var(--yellow-lt);color:var(--yellow);">
                <i data-lucide="hourglass" style="width:17px;height:17px"></i>
            </div>
            <div>
                <div class="ss-n" style="color:var(--yellow);"><?= $pending_count ?></div>
                <div class="ss-l">Pending</div>
            </div>
        </div>
        <div class="ss-card">
            <div class="ss-icon" style="background:var(--red-lt);color:var(--red);">
                <i data-lucide="x-circle" style="width:17px;height:17px"></i>
            </div>
            <div>
                <div class="ss-n" style="color:var(--red);"><?= $rejected_count ?></div>
                <div class="ss-l">Rejected</div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="filter-card">
        <div class="filter-title">
            <i data-lucide="sliders-horizontal" style="width:13px;height:13px"></i>
            Filter Records
        </div>
        <form method="get">
            <div class="filter-grid">
                <div class="filter-field">
                    <label>Name</label>
                    <input type="text" name="name" placeholder="e.g. Santos" value="<?= htmlspecialchars($search_name) ?>">
                </div>
                <div class="filter-field">
                    <label>Barangay</label>
                    <select name="barangay">
                        <option value="">All Barangays</option>
                        <?php foreach ($barangay_list as $b): ?>
                        <option value="<?= htmlspecialchars($b) ?>" <?= $f_barangay===$b?'selected':''?>>
                            <?= htmlspecialchars($b) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label>Aid Type</label>
                    <select name="type">
                        <option value="">All Types</option>
                        <?php foreach ($type_list as $t): ?>
                        <option value="<?= $t ?>" <?= $f_type===$t?'selected':''?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Statuses</option>
                        <?php foreach ($status_list as $s): ?>
                        <option value="<?= $s ?>" <?= $f_status===$s?'selected':''?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="filter-actions" style="margin-top:12px;">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i data-lucide="search" style="width:13px;height:13px"></i> Search
                </button>
                <?php if ($has_filters): ?>
                <a href="aid_history.php" class="btn btn-outline btn-sm">
                    <i data-lucide="x" style="width:13px;height:13px"></i> Clear
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-blue"><i data-lucide="history" style="width:14px;height:14px"></i></div>
                Aid History Records
                <span class="count-pill"><?= count($aid_history) ?> results</span>
            </div>
        </div>
        <div class="card-body no-pad">
            <?php if (empty($aid_history)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="search-x" style="width:20px;height:20px"></i></div>
                <div class="empty-text">No records match your search criteria.</div>
            </div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table data-paginate="10">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Beneficiary</th>
                            <th>Barangay</th>
                            <th>Assistance</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($aid_history as $i => $row):
                        $s    = strtolower($row['status']);
                        $bc   = $s === 'approved' ? 'b-approved' : ($s === 'pending' ? 'b-pending' : 'b-rejected');
                        $col  = $colors[$i % count($colors)];
                        $init = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $row['full_name']), 0, 2)));
                    ?>
                    <tr>
                        <td style="color:var(--muted);font-size:.74rem;"><?= $row['id'] ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:9px;">
                                <div class="row-avatar" style="background:<?= $col ?>;"><?= $init ?></div>
                                <span style="font-weight:700;font-size:.82rem;color:var(--navy);"><?= htmlspecialchars($row['full_name']) ?></span>
                            </div>
                        </td>
                        <td style="color:var(--muted);"><?= htmlspecialchars($row['barangay'] ?? '—') ?></td>
                        <td style="font-weight:600;"><?= htmlspecialchars(ucwords(strtolower($row['assistance']))) ?></td>
                        <td><span class="amount">₱<?= number_format($row['amount_requested'] ?? 0, 2) ?></span></td>
                        <td style="color:var(--muted);white-space:nowrap;"><?= htmlspecialchars($row['date_of_request']) ?></td>
                        <td><span class="badge <?= $bc ?>"><?= ucfirst($s) ?></span></td>
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