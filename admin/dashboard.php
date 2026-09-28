<?php
// admin/dashboard.php
require_once __DIR__ . '/../helpers.php';
require_admin();

// --- Date Range Filter ---
$date_from = $_GET['from'] ?? '';
$date_to   = $_GET['to']   ?? '';
$date_where = '';
$date_params = [];
$date_types  = '';
if ($date_from && $date_to) {
    $date_where = " AND date_of_request BETWEEN ? AND ?";
    $date_params = [$date_from, $date_to];
    $date_types  = 'ss';
} elseif ($date_from) {
    $date_where = " AND date_of_request >= ?";
    $date_params = [$date_from];
    $date_types  = 's';
} elseif ($date_to) {
    $date_where = " AND date_of_request <= ?";
    $date_params = [$date_to];
    $date_types  = 's';
}
$has_date_filter = $date_from || $date_to;

// --- Stats ---
$total_users        = get_count('users');

// Helper for filtered counts
function filtered_count($mysqli, $status, $date_where, $date_params, $date_types) {
    $sql = "SELECT COUNT(*) FROM applications WHERE status=?" . $date_where;
    $stmt = $mysqli->prepare($sql);
    if ($date_params) {
        $stmt->bind_param('s' . $date_types, $status, ...$date_params);
    } else {
        $stmt->bind_param('s', $status);
    }
    $stmt->execute();
    $c = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
    return $c;
}

$sql_total = "SELECT COUNT(*) FROM applications WHERE 1=1" . $date_where;
if ($date_params) {
    $stmt = $mysqli->prepare($sql_total);
    $stmt->bind_param($date_types, ...$date_params);
    $stmt->execute();
    $total_applications = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
} else {
    $total_applications = get_count('applications');
}

$approved_aids = filtered_count($mysqli, 'approved', $date_where, $date_params, $date_types);
$pending_aids  = filtered_count($mysqli, 'pending', $date_where, $date_params, $date_types);
$rejected_aids = filtered_count($mysqli, 'rejected', $date_where, $date_params, $date_types);

// Applications by type
$applications_by_type = [];
$sql_type = "SELECT type, COUNT(*) AS count FROM applications WHERE 1=1" . $date_where . " GROUP BY type";
if ($date_params) {
    $stmt = $mysqli->prepare($sql_type);
    $stmt->bind_param($date_types, ...$date_params);
    $stmt->execute();
    $applications_by_type = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $result = $mysqli->query("SELECT type, COUNT(*) AS count FROM applications GROUP BY type");
    if ($result) $applications_by_type = $result->fetch_all(MYSQLI_ASSOC);
}

// Recent messages
$recent_msgs = [];
$stmt = $mysqli->prepare("SELECT sender, message, created_at FROM messages ORDER BY created_at DESC LIMIT 8");
if ($stmt) { $stmt->execute(); $recent_msgs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); }

// Recent aids
$recent_aids = [];
$stmt = $mysqli->prepare("
    SELECT u.id AS user_id, u.name AS full_name, u.barangay,
           a.status, a.type AS assistance, a.date_of_request AS date_request, a.id AS application_id
    FROM applications a JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC LIMIT 8
");
if ($stmt) { $stmt->execute(); $recent_aids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); }

// Documents
$documents = [];
$stmt = $mysqli->prepare("
    SELECT d.id, d.filename, d.original_name, a.id AS application_id, u.name AS applicant, a.status
    FROM documents d
    JOIN applications a ON d.application_id = a.id
    JOIN users u ON a.user_id = u.id
    ORDER BY d.uploaded_at DESC LIMIT 8
");
if ($stmt) { $stmt->execute(); $documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); }

// Chart data
$chart_labels       = json_encode(['Approved', 'Rejected', 'Pending']);
$chart_data         = json_encode([$approved_aids, $rejected_aids, $pending_aids]);
$application_types  = json_encode(array_column($applications_by_type, 'type'));
$applications_count = json_encode(array_column($applications_by_type, 'count'));

// Partials config
$active_page   = 'dashboard';
$page_title    = 'Dashboard';
$page_subtitle = 'Dashboard';

$admin_name = $_SESSION['user']['name'] ?? 'Admin';
$hour       = (int)date('H');
$greeting   = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$first_name = explode(' ', $admin_name)[0];
$colors     = ['#1a56db','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — AIDTRACK Admin</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/admin.css">
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* ── DASHBOARD-SPECIFIC ── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 14px;
    margin-bottom: 18px;
}
.stat-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--r);
    padding: 18px 18px 16px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    transition: box-shadow .18s;
}
.stat-card:hover { box-shadow: var(--sh-md); }
.sc-label { font-size:.7rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:8px; }
.sc-value { font-size:1.85rem; font-weight:800; color:var(--navy); line-height:1; }
.sc-sub   { font-size:.69rem; font-weight:600; color:var(--muted); margin-top:6px; display:flex; align-items:center; gap:4px; }
.sc-up    { color:var(--green); }
.sc-dn    { color:var(--red); }
.sc-icon  { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.sci-blue   { background:var(--brand-lt); color:var(--brand); }
.sci-green  { background:var(--green-lt); color:var(--green); }
.sci-yellow { background:var(--yellow-lt); color:var(--yellow); }
.sci-red    { background:var(--red-lt);   color:var(--red); }
.sci-purple { background:#f5f3ff; color:#7c3aed; }

.grid-2 { display:grid; grid-template-columns:1fr 1fr;     gap:16px; margin-bottom:16px; }
.grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; margin-bottom:16px; }

.chart-wrap { padding:16px 20px; }
.chart-wrap canvas { max-height:230px; }

.msg-list { display:flex; flex-direction:column; }
.msg-item { display:flex; gap:11px; padding:11px 18px; border-bottom:1px solid var(--border); transition:background .12s; }
.msg-item:last-child { border-bottom:none; }
.msg-item:hover { background:var(--bg); }
.msg-av     { width:33px; height:33px; border-radius:9px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:.68rem; font-weight:800; color:#fff; }
.msg-sender { font-size:.79rem; font-weight:700; color:var(--navy); }
.msg-text   { font-size:.75rem; color:var(--muted); margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.msg-time   { font-size:.65rem; color:var(--muted); margin-top:2px; }

.qa-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.qa-btn {
    display:flex; align-items:center; gap:9px;
    padding:10px 12px; border-radius:9px;
    border:1.5px solid var(--border); background:var(--white);
    font-family:inherit; font-size:.78rem; font-weight:700; color:var(--slate);
    cursor:pointer; transition:all .15s; text-decoration:none;
}
.qa-btn:hover { border-color:var(--brand); color:var(--brand); background:var(--brand-lt); }
.qa-icon { width:28px; height:28px; border-radius:7px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }

@media(max-width:1280px){ .stats-grid { grid-template-columns:repeat(3,1fr); } }
@media(max-width:1060px){ .grid-3 { grid-template-columns:1fr 1fr; } }
@media(max-width:860px) { .stats-grid { grid-template-columns:repeat(2,1fr); } .grid-2,.grid-3 { grid-template-columns:1fr; } }
@media(max-width:480px) { .stats-grid { grid-template-columns:1fr 1fr; } }
@media(max-width:360px) { .stats-grid { grid-template-columns:1fr; } }
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
                <div class="page-title"><?= $greeting ?>, <?= htmlspecialchars($first_name) ?>.</div>
                <div class="page-sub">AIDTRACK overview — <?= date('l, F j, Y') ?></div>
            </div>
            <div class="page-actions">
                <form method="get" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <input type="date" name="from" value="<?= htmlspecialchars($date_from) ?>" style="font-family:inherit;font-size:.78rem;padding:6px 10px;border:1.5px solid var(--border);border-radius:8px;outline:none;color:var(--navy);" title="From date">
                    <span style="font-size:.75rem;color:var(--muted);">to</span>
                    <input type="date" name="to" value="<?= htmlspecialchars($date_to) ?>" style="font-family:inherit;font-size:.78rem;padding:6px 10px;border:1.5px solid var(--border);border-radius:8px;outline:none;color:var(--navy);" title="To date">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i data-lucide="filter" style="width:13px;height:13px"></i> Filter
                    </button>
                    <?php if ($has_date_filter): ?>
                    <a href="dashboard.php" class="btn btn-outline btn-sm">
                        <i data-lucide="x" style="width:13px;height:13px"></i> Clear
                    </a>
                    <?php endif; ?>
                </form>
                <a href="applications.php?status=pending" class="btn btn-outline btn-sm">
                    <i data-lucide="clock" style="width:13px;height:13px"></i>
                    Pending (<?= $pending_aids ?>)
                </a>
            </div>
        </div>
    </div>

    <!-- STAT CARDS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div>
                <div class="sc-label">Total Users</div>
                <div class="sc-value"><?= number_format($total_users) ?></div>
                <div class="sc-sub sc-up"><i data-lucide="users" style="width:11px;height:11px"></i> Registered beneficiaries</div>
            </div>
            <div class="sc-icon sci-blue"><i data-lucide="users" style="width:18px;height:18px"></i></div>
        </div>
        <div class="stat-card">
            <div>
                <div class="sc-label">Applications</div>
                <div class="sc-value"><?= number_format($total_applications) ?></div>
                <div class="sc-sub"><i data-lucide="file-text" style="width:11px;height:11px"></i> All time submissions</div>
            </div>
            <div class="sc-icon sci-purple"><i data-lucide="file-text" style="width:18px;height:18px"></i></div>
        </div>
        <div class="stat-card">
            <div>
                <div class="sc-label">Approved</div>
                <div class="sc-value" style="color:var(--green)"><?= number_format($approved_aids) ?></div>
                <div class="sc-sub sc-up"><i data-lucide="check-circle" style="width:11px;height:11px"></i> Successfully released</div>
            </div>
            <div class="sc-icon sci-green"><i data-lucide="shield-check" style="width:18px;height:18px"></i></div>
        </div>
        <div class="stat-card">
            <div>
                <div class="sc-label">Pending</div>
                <div class="sc-value" style="color:var(--yellow)"><?= number_format($pending_aids) ?></div>
                <div class="sc-sub sc-dn"><i data-lucide="hourglass" style="width:11px;height:11px"></i> Awaiting action</div>
            </div>
            <div class="sc-icon sci-yellow"><i data-lucide="hourglass" style="width:18px;height:18px"></i></div>
        </div>
        <div class="stat-card">
            <div>
                <div class="sc-label">Rejected</div>
                <div class="sc-value" style="color:var(--red)"><?= number_format($rejected_aids) ?></div>
                <div class="sc-sub sc-dn"><i data-lucide="x-circle" style="width:11px;height:11px"></i> Did not qualify</div>
            </div>
            <div class="sc-icon sci-red"><i data-lucide="ban" style="width:18px;height:18px"></i></div>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon cti-blue"><i data-lucide="bar-chart-2" style="width:14px;height:14px"></i></div>
                    Applications Overview
                </div>
                <span style="font-size:.75rem;font-weight:600;color:var(--brand);cursor:pointer;" onclick="exportChart('applicationsChart')">
                    <i data-lucide="download" style="width:12px;height:12px;vertical-align:middle"></i> Export
                </span>
            </div>
            <div class="chart-wrap"><canvas id="applicationsChart"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon cti-purple"><i data-lucide="pie-chart" style="width:14px;height:14px"></i></div>
                    Applications by Type
                </div>
            </div>
            <div class="chart-wrap"><canvas id="applicationsTypeChart"></canvas></div>
        </div>
    </div>

    <!-- RECENT APPLICATIONS TABLE -->
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-green"><i data-lucide="clipboard-check" style="width:14px;height:14px"></i></div>
                Recent Applications
            </div>
            <a href="applications.php" style="font-size:.75rem;font-weight:600;color:var(--brand);display:flex;align-items:center;gap:4px;">
                View all <i data-lucide="arrow-right" style="width:12px;height:12px"></i>
            </a>
        </div>
        <div class="card-body no-pad">
            <?php if (empty($recent_aids)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="inbox" style="width:20px;height:20px"></i></div>
                <div class="empty-text">No applications yet.</div>
            </div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Barangay</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent_aids as $i => $aid):
                        $s    = strtolower($aid['status']);
                        $bc   = $s === 'approved' ? 'b-approved' : ($s === 'pending' ? 'b-pending' : 'b-rejected');
                        $col  = $colors[$i % count($colors)];
                        $init = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $aid['full_name']), 0, 2)));
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:9px;">
                                <div class="row-avatar" style="background:<?= $col ?>;"><?= $init ?></div>
                                <span style="font-weight:700;font-size:.82rem;color:var(--navy);"><?= htmlspecialchars($aid['full_name']) ?></span>
                            </div>
                        </td>
                        <td style="color:var(--muted);"><?= htmlspecialchars($aid['barangay'] ?? '—') ?></td>
                        <td style="font-weight:600;"><?= htmlspecialchars(ucwords(strtolower($aid['assistance']))) ?></td>
                        <td style="color:var(--muted);white-space:nowrap;"><?= htmlspecialchars($aid['date_request']) ?></td>
                        <td><span class="badge <?= $bc ?>"><?= ucfirst($s) ?></span></td>
                        <td>
                            <?php if ($s === 'pending'): ?>
                            <div class="tbl-actions">
                                <a href="approve_reject.php?id=<?= $aid['application_id'] ?>&action=approve" class="tbl-action ta-approve">
                                    <i data-lucide="check" style="width:11px;height:11px"></i> Approve
                                </a>
                                <a href="applications.php" class="tbl-action ta-view">
                                    <i data-lucide="eye" style="width:11px;height:11px"></i> View
                                </a>
                            </div>
                            <?php else: ?>
                                <span style="font-size:.75rem;color:var(--muted);font-style:italic;">No action</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- BOTTOM: Messages + Documents + Quick Actions & Summary -->
    <div class="grid-3">

        <!-- Messages -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon cti-blue"><i data-lucide="message-square" style="width:14px;height:14px"></i></div>
                    Recent Messages
                </div>
                <a href="messages.php" style="font-size:.75rem;font-weight:600;color:var(--brand);">View all</a>
            </div>
            <div class="card-body no-pad scroll">
                <?php if (empty($recent_msgs)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i data-lucide="message-circle" style="width:20px;height:20px"></i></div>
                    <div class="empty-text">No messages yet.</div>
                </div>
                <?php else: ?>
                <div class="msg-list">
                    <?php foreach ($recent_msgs as $i => $m):
                        $mc = $colors[$i % count($colors)];
                        $mi = strtoupper(substr(trim($m['sender']), 0, 2));
                    ?>
                    <div class="msg-item">
                        <div class="msg-av" style="background:<?= $mc ?>"><?= $mi ?></div>
                        <div style="flex:1;min-width:0;">
                            <div class="msg-sender"><?= htmlspecialchars($m['sender']) ?></div>
                            <div class="msg-text"><?= htmlspecialchars(substr($m['message'],0,72)) ?><?= strlen($m['message'])>72?'…':'' ?></div>
                            <div class="msg-time"><?= htmlspecialchars($m['created_at']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Documents -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon cti-yellow"><i data-lucide="folder-open" style="width:14px;height:14px"></i></div>
                    Uploaded Documents
                </div>
                <a href="applications.php" style="font-size:.75rem;font-weight:600;color:var(--brand);">View all</a>
            </div>
            <div class="card-body no-pad scroll">
                <?php if (empty($documents)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i data-lucide="folder" style="width:20px;height:20px"></i></div>
                    <div class="empty-text">No documents uploaded yet.</div>
                </div>
                <?php else: ?>
                <div class="tbl-wrap">
                    <table>
                        <thead>
                            <tr><th>Applicant</th><th>Document</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($documents as $i => $doc):
                            $ds  = strtolower($doc['status'] ?? '');
                            $dbc = $ds === 'approved' ? 'b-approved' : ($ds === 'pending' ? 'b-pending' : 'b-rejected');
                            $col = $colors[$i % count($colors)];
                            $din = strtoupper(substr(trim($doc['applicant']), 0, 2));
                        ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:7px;">
                                    <div class="row-avatar" style="background:<?= $col ?>;width:26px;height:26px;font-size:.6rem;"><?= $din ?></div>
                                    <span style="font-weight:700;font-size:.78rem;color:var(--navy);"><?= htmlspecialchars($doc['applicant']) ?></span>
                                </div>
                            </td>
                            <td style="font-size:.75rem;"><?= htmlspecialchars($doc['original_name'] ?? $doc['filename']) ?></td>
                            <td><span class="badge <?= $dbc ?>"><?= ucfirst($ds) ?></span></td>
                            <td>
                                <div class="tbl-actions">
                                    <a href="../uploads/<?= urlencode($doc['filename']) ?>" target="_blank" class="tbl-action ta-view">
                                        <i data-lucide="eye" style="width:10px;height:10px"></i>
                                    </a>
                                    <a href="delete_document.php?id=<?= $doc['id'] ?>" class="tbl-action ta-delete" onclick="return confirm('Delete?')">
                                        <i data-lucide="trash-2" style="width:10px;height:10px"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions + Summary -->
        <div style="display:flex;flex-direction:column;gap:16px;">

            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div class="card-title-icon cti-purple"><i data-lucide="zap" style="width:14px;height:14px"></i></div>
                        Quick Actions
                    </div>
                </div>
                <div class="card-body">
                    <div class="qa-grid">
                        <a href="applications.php?status=pending" class="qa-btn">
                            <div class="qa-icon" style="background:var(--yellow-lt);color:var(--yellow);"><i data-lucide="hourglass" style="width:14px;height:14px"></i></div>
                            Pending
                        </a>
                        <a href="beneficiaries.php" class="qa-btn">
                            <div class="qa-icon" style="background:var(--brand-lt);color:var(--brand);"><i data-lucide="users" style="width:14px;height:14px"></i></div>
                            Beneficiaries
                        </a>
                        <a href="messages.php" class="qa-btn">
                            <div class="qa-icon" style="background:var(--green-lt);color:var(--green);"><i data-lucide="message-square" style="width:14px;height:14px"></i></div>
                            Messages
                        </a>
                        <a href="#" class="qa-btn" onclick="exportDashboardCSV()">
                            <div class="qa-icon" style="background:#f5f3ff;color:#7c3aed;"><i data-lucide="file-down" style="width:14px;height:14px"></i></div>
                            Export CSV
                        </a>
                        <a href="aid_history.php" class="qa-btn">
                            <div class="qa-icon" style="background:var(--red-lt);color:var(--red);"><i data-lucide="history" style="width:14px;height:14px"></i></div>
                            Aid History
                        </a>
                        <a href="user.php" class="qa-btn">
                            <div class="qa-icon" style="background:#fff7ed;color:#c2410c;"><i data-lucide="user-cog" style="width:14px;height:14px"></i></div>
                            Users
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div class="card-title-icon cti-green"><i data-lucide="activity" style="width:14px;height:14px"></i></div>
                        Rate Summary
                    </div>
                </div>
                <div class="card-body">
                    <?php
                    $total_nz = $approved_aids + $pending_aids + $rejected_aids;
                    $rows = [
                        ['label'=>'Approval Rate',  'pct'=>$total_nz ? round($approved_aids/$total_nz*100) : 0, 'color'=>'var(--green)'],
                        ['label'=>'Pending Rate',   'pct'=>$total_nz ? round($pending_aids/$total_nz*100)  : 0, 'color'=>'var(--yellow)'],
                        ['label'=>'Rejection Rate', 'pct'=>$total_nz ? round($rejected_aids/$total_nz*100) : 0, 'color'=>'var(--red)'],
                    ];
                    foreach ($rows as $r): ?>
                    <div style="margin-bottom:13px;">
                        <div style="display:flex;justify-content:space-between;font-size:.74rem;font-weight:700;color:var(--slate);margin-bottom:5px;">
                            <span><?= $r['label'] ?></span>
                            <span style="color:<?= $r['color'] ?>;"><?= $r['pct'] ?>%</span>
                        </div>
                        <div style="height:6px;background:var(--border);border-radius:99px;overflow:hidden;">
                            <div style="width:<?= $r['pct'] ?>%;height:100%;background:<?= $r['color'] ?>;border-radius:99px;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>

</main>

<script src="partials/admin.js"></script>
<script>
Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
Chart.defaults.font.size   = 12;

new Chart(document.getElementById('applicationsChart'), {
    type: 'bar',
    data: {
        labels: <?= $chart_labels ?>,
        datasets: [{
            label: 'Applications',
            data: <?= $chart_data ?>,
            backgroundColor: ['rgba(22,163,74,.12)','rgba(220,38,38,.12)','rgba(202,138,4,.12)'],
            borderColor:     ['#16a34a','#dc2626','#ca8a04'],
            borderWidth: 2,
            borderRadius: 7,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display:false } },
        scales: {
            y: { beginAtZero:true, ticks:{ stepSize:1, color:'#94a3b8' }, grid:{ color:'rgba(0,0,0,.04)' }, border:{ display:false } },
            x: { grid:{ display:false }, border:{ display:false }, ticks:{ color:'#94a3b8' } }
        }
    }
});

new Chart(document.getElementById('applicationsTypeChart'), {
    type: 'doughnut',
    data: {
        labels: <?= $application_types ?>,
        datasets: [{
            data: <?= $applications_count ?>,
            backgroundColor: ['#1a56db','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'],
            borderWidth: 3,
            borderColor: '#fff',
            hoverOffset: 8
        }]
    },
    options: {
        responsive: true,
        cutout: '66%',
        plugins: {
            legend: { position:'bottom', labels:{ boxWidth:11, padding:12, font:{ size:11 }, color:'#64748b' } }
        }
    }
});

function exportChart(id) {
    const url = document.getElementById(id).toDataURL();
    const a = document.createElement('a');
    a.href = url; a.download = id + '.png'; a.click();
}

// Export dashboard data as CSV
function exportDashboardCSV() {
    let csv = 'Metric,Value\n';
    csv += `Total Users,<?= $total_users ?>\n`;
    csv += `Total Applications,<?= $total_applications ?>\n`;
    csv += `Approved,<?= $approved_aids ?>\n`;
    csv += `Pending,<?= $pending_aids ?>\n`;
    csv += `Rejected,<?= $rejected_aids ?>\n`;
    csv += `\nType,Count\n`;
    <?php foreach ($applications_by_type as $t): ?>
    csv += `<?= ucfirst($t['type']) ?>,<?= $t['count'] ?>\n`;
    <?php endforeach; ?>
    csv += `\nReport Date,${new Date().toISOString()}\n`;

    const blob = new Blob([csv], {type: 'text/csv'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'dashboard_export_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
}

// Export all charts as images
function exportAllCharts() {
    ['applicationsChart','applicationsTypeChart'].forEach(id => {
        const el = document.getElementById(id);
        if (el) exportChart(id);
    });
}
</script>
</body>
</html>