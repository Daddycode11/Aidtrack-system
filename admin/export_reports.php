<?php
// admin/export_reports.php
require_once __DIR__ . '/../helpers.php';
require_admin();

// --- Handle CSV Export ---
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $f_status    = $_GET['status']   ?? '';
    $f_type      = $_GET['type']     ?? '';
    $f_from      = $_GET['date_from'] ?? '';
    $f_to        = $_GET['date_to']   ?? '';
    $f_barangay  = $_GET['barangay']  ?? '';

    header('Content-Type: text/csv; charset=utf-8');
    $output = fopen('php://output', 'w');

    if ($export_type === 'applications') {
        header('Content-Disposition: attachment; filename="applications_report_' . date('Y-m-d') . '.csv"');

        $where = ['1=1']; $params = []; $types = '';
        if ($f_status)   { $where[] = 'a.status = ?';            $params[] = $f_status;   $types .= 's'; }
        if ($f_type)     { $where[] = 'a.type = ?';              $params[] = $f_type;     $types .= 's'; }
        if ($f_barangay) { $where[] = 'u.barangay = ?';          $params[] = $f_barangay; $types .= 's'; }
        if ($f_from)     { $where[] = 'a.date_of_request >= ?';  $params[] = $f_from;     $types .= 's'; }
        if ($f_to)       { $where[] = 'a.date_of_request <= ?';  $params[] = $f_to;       $types .= 's'; }
        $where_sql = implode(' AND ', $where);

        fputcsv($output, ['ID', 'Applicant', 'Phone', 'Barangay', 'Type', 'Amount Requested', 'Status', 'Date of Request', 'Created At']);

        $stmt = $mysqli->prepare("
            SELECT a.id, u.name, u.phone, u.barangay, a.type, a.amount_requested, a.status, a.date_of_request, a.created_at
            FROM applications a JOIN users u ON a.user_id = u.id
            WHERE $where_sql ORDER BY a.date_of_request DESC
        ");
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'], $row['name'], $row['phone'], $row['barangay'],
                ucfirst($row['type']), $row['amount_requested'], ucfirst($row['status']),
                $row['date_of_request'], $row['created_at']
            ]);
        }
        $stmt->close();

    } elseif ($export_type === 'beneficiaries') {
        header('Content-Disposition: attachment; filename="beneficiaries_report_' . date('Y-m-d') . '.csv"');

        fputcsv($output, ['ID', 'Name', 'Phone', 'Barangay', 'Total Applications', 'Approved', 'Pending', 'Rejected', 'Registered']);

        // Only include beneficiaries who have at least one approved application
        $res = $mysqli->query("
            SELECT u.id, u.name, u.phone, u.barangay, u.created_at,
                   COUNT(a.id) AS total_apps,
                   SUM(a.status='approved') AS approved,
                   SUM(a.status='pending') AS pending,
                   SUM(a.status='rejected') AS rejected
            FROM users u
            INNER JOIN applications a ON u.id = a.user_id
            WHERE u.role = 'client'
            GROUP BY u.id
            HAVING SUM(a.status='approved') > 0
            ORDER BY u.name ASC
        ");
        while ($row = $res->fetch_assoc()) {
            fputcsv($output, [
                $row['id'], $row['name'], $row['phone'], $row['barangay'],
                $row['total_apps'], $row['approved'], $row['pending'], $row['rejected'], $row['created_at']
            ]);
        }

    } elseif ($export_type === 'summary') {
        header('Content-Disposition: attachment; filename="summary_report_' . date('Y-m-d') . '.csv"');

        fputcsv($output, ['Metric', 'Value']);

        $total = get_count('applications');
        $approved = (int)$mysqli->query("SELECT COUNT(*) FROM applications WHERE status='approved'")->fetch_row()[0];
        $pending  = (int)$mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0];
        $rejected = (int)$mysqli->query("SELECT COUNT(*) FROM applications WHERE status='rejected'")->fetch_row()[0];
        $total_requested = $mysqli->query("SELECT COALESCE(SUM(amount_requested),0) FROM applications")->fetch_row()[0];
        $total_approved_amt = $mysqli->query("SELECT COALESCE(SUM(amount_requested),0) FROM applications WHERE status='approved'")->fetch_row()[0];

        fputcsv($output, ['Total Applications', $total]);
        fputcsv($output, ['Approved', $approved]);
        fputcsv($output, ['Pending', $pending]);
        fputcsv($output, ['Rejected', $rejected]);
        fputcsv($output, ['Approval Rate', $total ? round($approved/$total*100,1).'%' : '0%']);
        fputcsv($output, ['Total Amount Requested', number_format($total_requested, 2)]);
        fputcsv($output, ['Total Amount Approved', number_format($total_approved_amt, 2)]);
        fputcsv($output, ['Total Beneficiaries', get_count('users', "role='client'")]);
        fputcsv($output, ['Report Date', date('Y-m-d H:i:s')]);

        // By type
        fputcsv($output, []);
        fputcsv($output, ['--- Applications by Type ---']);
        $res = $mysqli->query("SELECT type, COUNT(*) AS count FROM applications GROUP BY type ORDER BY count DESC");
        while ($row = $res->fetch_assoc()) {
            fputcsv($output, [ucfirst($row['type']), $row['count']]);
        }

        // By barangay
        fputcsv($output, []);
        fputcsv($output, ['--- Applications by Barangay ---']);
        $res = $mysqli->query("
            SELECT u.barangay, COUNT(*) AS count
            FROM applications a JOIN users u ON a.user_id = u.id
            WHERE u.barangay IS NOT NULL AND u.barangay != ''
            GROUP BY u.barangay ORDER BY count DESC
        ");
        while ($row = $res->fetch_assoc()) {
            fputcsv($output, [$row['barangay'], $row['count']]);
        }
    }

    fclose($output);
    exit;
}

// --- Samarica barangay list ---
$samarica_barangays = [
    'Barangay 1 (Poblacion)','Barangay 2 (Poblacion)','Barangay 3 (Poblacion)',
    'Barangay 4 (Poblacion)','Barangay 5 (Poblacion)','Barangay 6 (Poblacion)',
    'Araw ng Bayan','Bagong Silang','Batangas','Bayanan','Buenavista',
    'Caguray','Calumpang','Guinobatan','Ibaba','Iba','Ilaya',
    'Kaingin','Kalamias','Kasay','Kaylaway','Lalud','Luyahan',
    'Mabini','Magampon','Makina','Manggahan','Marasigan','Matagbak',
    'Palanas','Parang','Pinagsibaan','Putol','Sabang',
    'San Agustin','San Antonio','San Isidro','San Jose','San Pablo',
    'San Pedro','San Rafael','Santol','Santo Niño','Silangan',
    'Sinipian','Tabing Dagat','Tibag','Tulay',
];

// --- Page Data ---
$pending_aids = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0] ?? 0);
$total_apps   = get_count('applications');
$total_users  = get_count('users', "role='client'");
$type_list    = ['medical','burial'];
$status_list  = ['pending','approved','rejected'];

// Recent exports log (just show available reports)
$report_types = [
    ['key'=>'applications',  'icon'=>'clipboard-list', 'color'=>'var(--brand)',  'bg'=>'var(--brand-lt)', 'title'=>'Applications Report',  'desc'=>'All aid applications with applicant details, status, amounts, and dates.'],
    ['key'=>'beneficiaries', 'icon'=>'users',          'color'=>'var(--green)',  'bg'=>'var(--green-lt)', 'title'=>'Beneficiaries Report', 'desc'=>'Complete list of registered beneficiaries with application statistics.'],
    ['key'=>'summary',       'icon'=>'bar-chart-3',    'color'=>'#7c3aed',      'bg'=>'#f5f3ff',         'title'=>'Summary Report',       'desc'=>'Overview statistics including rates, totals, breakdowns by type and barangay.'],
];

// Partials
$active_page   = 'export';
$page_title    = 'Export Reports';
$page_subtitle = 'Export Reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Export Reports — AIDTRACK Admin</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/admin.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
.export-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:20px; }
.export-card {
    background:var(--white); border:1px solid var(--border); border-radius:var(--r);
    padding:22px 20px; transition:all .18s; cursor:pointer; position:relative;
}
.export-card:hover { box-shadow:var(--sh-md); border-color:var(--brand); }
.export-card.active { border-color:var(--brand); box-shadow:0 0 0 3px rgba(26,86,219,.12); }
.ec-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:14px; }
.ec-title { font-size:.88rem; font-weight:800; color:var(--navy); margin-bottom:6px; }
.ec-desc { font-size:.74rem; color:var(--muted); line-height:1.5; }
.ec-check { position:absolute; top:12px; right:12px; width:20px; height:20px; border-radius:50%; background:var(--brand); color:#fff; display:none; align-items:center; justify-content:center; }
.export-card.active .ec-check { display:flex; }

.filter-card {
    background:var(--white); border:1px solid var(--border); border-radius:var(--r);
    padding:18px 20px; margin-bottom:20px;
}
.filter-title { font-size:.76rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; margin-bottom:14px; display:flex; align-items:center; gap:6px; }
.filter-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; align-items:end; }
.filter-field { display:flex; flex-direction:column; gap:5px; }
.filter-field label { font-size:.72rem; font-weight:700; color:var(--slate); }
.filter-field input,
.filter-field select {
    font-family:inherit; font-size:.8rem; color:var(--navy);
    background:var(--bg); border:1.5px solid var(--border);
    border-radius:8px; padding:8px 12px; outline:none; transition:border-color .15s;
}
.filter-field input:focus,
.filter-field select:focus { border-color:var(--brand); background:var(--white); }

.export-actions { display:flex; gap:10px; align-items:center; margin-top:16px; }

.info-strip {
    display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:20px;
}
.is-card {
    background:var(--white); border:1px solid var(--border); border-radius:var(--r);
    padding:14px 16px; display:flex; align-items:center; gap:12px;
}
.is-icon { width:36px; height:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.is-n { font-size:1.3rem; font-weight:800; line-height:1; }
.is-l { font-size:.68rem; font-weight:600; color:var(--muted); margin-top:2px; }

@media(max-width:960px) { .export-grid { grid-template-columns:1fr 1fr; } .info-strip { grid-template-columns:1fr 1fr; } }
@media(max-width:600px) { .export-grid { grid-template-columns:1fr; } .filter-grid { grid-template-columns:1fr 1fr; } .info-strip { grid-template-columns:1fr; } }
@media(max-width:480px) { .filter-grid { grid-template-columns:1fr; } }
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
                <div class="page-title">Export Reports</div>
                <div class="page-sub">Generate and download CSV reports for analysis and record-keeping.</div>
            </div>
            <div class="page-actions">
                <a href="analytics.php" class="btn btn-outline btn-sm">
                    <i data-lucide="bar-chart-3" style="width:13px;height:13px"></i> Analytics
                </a>
            </div>
        </div>
    </div>

    <!-- Info Strip -->
    <div class="info-strip">
        <div class="is-card">
            <div class="is-icon" style="background:var(--brand-lt);color:var(--brand);">
                <i data-lucide="file-text" style="width:17px;height:17px"></i>
            </div>
            <div>
                <div class="is-n"><?= number_format($total_apps) ?></div>
                <div class="is-l">Total Applications</div>
            </div>
        </div>
        <div class="is-card">
            <div class="is-icon" style="background:var(--green-lt);color:var(--green);">
                <i data-lucide="users" style="width:17px;height:17px"></i>
            </div>
            <div>
                <div class="is-n"><?= number_format($total_users) ?></div>
                <div class="is-l">Beneficiaries</div>
            </div>
        </div>
        <div class="is-card">
            <div class="is-icon" style="background:var(--yellow-lt);color:var(--yellow);">
                <i data-lucide="clock" style="width:17px;height:17px"></i>
            </div>
            <div>
                <div class="is-n"><?= number_format($pending_aids) ?></div>
                <div class="is-l">Pending Review</div>
            </div>
        </div>
    </div>

    <!-- Report Type Selection -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-purple"><i data-lucide="file-down" style="width:14px;height:14px"></i></div>
                Select Report Type
            </div>
        </div>
        <div class="card-body">
            <div class="export-grid">
                <?php foreach ($report_types as $rt): ?>
                <div class="export-card" data-type="<?= $rt['key'] ?>" onclick="selectReport('<?= $rt['key'] ?>')">
                    <div class="ec-check"><i data-lucide="check" style="width:12px;height:12px"></i></div>
                    <div class="ec-icon" style="background:<?= $rt['bg'] ?>;color:<?= $rt['color'] ?>;">
                        <i data-lucide="<?= $rt['icon'] ?>" style="width:20px;height:20px"></i>
                    </div>
                    <div class="ec-title"><?= $rt['title'] ?></div>
                    <div class="ec-desc"><?= $rt['desc'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Filters (for Applications export) -->
    <div class="filter-card" id="filterCard">
        <div class="filter-title">
            <i data-lucide="sliders-horizontal" style="width:13px;height:13px"></i>
            Filter Options <span style="font-weight:500;text-transform:none;letter-spacing:0;color:var(--slate);">(applies to Applications &amp; Beneficiaries Reports)</span>
        </div>
        <div class="filter-grid" style="grid-template-columns:repeat(3,1fr);">
            <div class="filter-field">
                <label>Status</label>
                <select id="filterStatus">
                    <option value="">All Statuses</option>
                    <?php foreach ($status_list as $s): ?>
                    <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label>Aid Type</label>
                <select id="filterType">
                    <option value="">All Types</option>
                    <?php foreach ($type_list as $t): ?>
                    <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label>Barangay</label>
                <select id="filterBarangay">
                    <option value="">All Barangays</option>
                    <?php foreach ($samarica_barangays as $b): ?>
                    <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label>Date From</label>
                <input type="date" id="filterFrom">
            </div>
            <div class="filter-field">
                <label>Date To</label>
                <input type="date" id="filterTo">
            </div>
        </div>
        <div class="export-actions">
            <button class="btn btn-primary btn-sm" onclick="downloadReport()">
                <i data-lucide="download" style="width:13px;height:13px"></i> Download CSV
            </button>
            <button class="btn btn-sm" style="background:var(--green);color:#fff;" onclick="openPrintReport()">
                <i data-lucide="printer" style="width:13px;height:13px"></i> Print Report
            </button>
            <button class="btn btn-outline btn-sm" onclick="resetFilters()">
                <i data-lucide="refresh-cw" style="width:13px;height:13px"></i> Reset
            </button>
        </div>
    </div>

    <!-- Quick Export Cards -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-green"><i data-lucide="zap" style="width:14px;height:14px"></i></div>
                Quick Export
            </div>
        </div>
        <div class="card-body no-pad">
            <div class="tbl-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Report</th>
                            <th>Description</th>
                            <th>Format</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="font-weight:700;color:var(--navy);">All Applications</td>
                            <td style="color:var(--muted);font-size:.78rem;">Complete applications data without filters</td>
                            <td><span class="badge b-approved">CSV</span></td>
                            <td>
                                <a href="?export=applications" class="tbl-action ta-approve">
                                    <i data-lucide="download" style="width:11px;height:11px"></i> Download
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:700;color:var(--navy);">Approved Only</td>
                            <td style="color:var(--muted);font-size:.78rem;">Applications with approved status</td>
                            <td><span class="badge b-approved">CSV</span></td>
                            <td>
                                <a href="?export=applications&status=approved" class="tbl-action ta-approve">
                                    <i data-lucide="download" style="width:11px;height:11px"></i> Download
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:700;color:var(--navy);">Pending Only</td>
                            <td style="color:var(--muted);font-size:.78rem;">Applications awaiting review</td>
                            <td><span class="badge b-pending">CSV</span></td>
                            <td>
                                <a href="?export=applications&status=pending" class="tbl-action ta-approve">
                                    <i data-lucide="download" style="width:11px;height:11px"></i> Download
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:700;color:var(--navy);">Beneficiaries</td>
                            <td style="color:var(--muted);font-size:.78rem;">All registered beneficiaries with stats</td>
                            <td><span class="badge b-review">CSV</span></td>
                            <td>
                                <a href="?export=beneficiaries" class="tbl-action ta-approve">
                                    <i data-lucide="download" style="width:11px;height:11px"></i> Download
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:700;color:var(--navy);">Summary Report</td>
                            <td style="color:var(--muted);font-size:.78rem;">Statistics overview with breakdowns</td>
                            <td><span class="badge" style="background:#f5f3ff;color:#7c3aed;">CSV</span></td>
                            <td>
                                <a href="?export=summary" class="tbl-action ta-approve">
                                    <i data-lucide="download" style="width:11px;height:11px"></i> Download
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<script src="partials/admin.js"></script>
<script>
let selectedReport = 'applications';

function selectReport(type) {
    selectedReport = type;
    document.querySelectorAll('.export-card').forEach(c => c.classList.remove('active'));
    document.querySelector('.export-card[data-type="'+type+'"]').classList.add('active');
    // Show/hide filters based on type
    document.getElementById('filterCard').style.display = type === 'applications' ? 'block' : 'none';
    lucide.createIcons();
}

function buildFilterParams() {
    const status   = document.getElementById('filterStatus').value;
    const type     = document.getElementById('filterType').value;
    const barangay = document.getElementById('filterBarangay').value;
    const from     = document.getElementById('filterFrom').value;
    const to       = document.getElementById('filterTo').value;
    let p = '';
    if (status)   p += '&status='   + encodeURIComponent(status);
    if (type)     p += '&type='     + encodeURIComponent(type);
    if (barangay) p += '&barangay=' + encodeURIComponent(barangay);
    if (from)     p += '&date_from=' + encodeURIComponent(from);
    if (to)       p += '&date_to='   + encodeURIComponent(to);
    return p;
}

function openPrintReport() {
    let url = 'print_report.php?type=' + selectedReport;
    if (selectedReport === 'applications' || selectedReport === 'beneficiaries') {
        url += buildFilterParams();
    }
    window.open(url, '_blank');
}

function downloadReport() {
    let url = '?export=' + selectedReport;
    if (selectedReport === 'applications' || selectedReport === 'beneficiaries') {
        url += buildFilterParams();
    }
    window.location.href = url;
}

function resetFilters() {
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterType').value = '';
    document.getElementById('filterBarangay').value = '';
    document.getElementById('filterFrom').value = '';
    document.getElementById('filterTo').value = '';
}

// Auto-select first report type
selectReport('applications');
</script>
</body>
</html>
