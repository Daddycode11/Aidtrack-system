<?php
// admin/export_reports.php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../helpers/table_filters.php';
require_admin();

function export_csv_row($output, array $values): void
{
    $values = array_map(static function ($value): string {
        $cell = (string)($value ?? '');
        return preg_match('/\A[=+\-@]/', $cell) ? "'" . $cell : $cell;
    }, $values);
    fputcsv($output, $values);
}

// --- Handle CSV Export ---
if (isset($_GET['export'])) {
    $export_type = is_string($_GET['export']) ? $_GET['export'] : '';
    if (!in_array($export_type, ['applications','beneficiaries','summary'], true)) {
        http_response_code(400);
        exit('Invalid export type.');
    }

    $barangayRows = $mysqli->query("SELECT DISTINCT barangay FROM users WHERE barangay IS NOT NULL AND barangay <> '' ORDER BY barangay")->fetch_all(MYSQLI_ASSOC);
    $barangayValues = array_column($barangayRows, 'barangay');
    $barangayOptions = array_combine($barangayValues, $barangayValues) ?: [];
    $typeOptions = ['medical'=>'Medical','burial'=>'Burial','educational'=>'Educational','livelihood'=>'Livelihood','emergency'=>'Emergency'];
    $statusOptions = ['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','cancelled'=>'Cancelled','released'=>'Released'];
    $commonFilters = [
        'search'=>['kind'=>'search','label'=>'Keyword','columns'=>['u.name','u.phone','u.email','CAST(a.id AS CHAR)']],
        'status'=>['kind'=>'select','label'=>'Status','options'=>$statusOptions,'sql'=>'a.status','expressions'=>['released'=>'a.amount_released > 0']],
        'type'=>['kind'=>'select','label'=>'Aid type','options'=>$typeOptions,'sql'=>'a.type'],
        'barangay'=>['kind'=>'select','label'=>'Barangay','options'=>$barangayOptions,'sql'=>'u.barangay'],
        'date_from'=>['kind'=>'date','label'=>'Date from','sql'=>'a.created_at','operator'=>'>='],
        'date_to'=>['kind'=>'date','label'=>'Date to','sql'=>'a.created_at','operator'=>'<','inclusive_end'=>true],
        'amount_min'=>['kind'=>'number','label'=>'Minimum amount','sql'=>'a.amount_requested','operator'=>'>='],
        'amount_max'=>['kind'=>'number','label'=>'Maximum amount','sql'=>'a.amount_requested','operator'=>'<='],
    ];
    $commonSort = ['id'=>'a.id','applicant'=>'u.name','client'=>'u.name','barangay'=>'u.barangay','type'=>'a.type','amount'=>'a.amount_requested','date'=>'a.created_at','status'=>'a.status'];

    header('Content-Type: text/csv; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    $output = fopen('php://output', 'w');

    if ($export_type === 'applications') {
        header('Content-Disposition: attachment; filename="applications_report_' . date('Y-m-d') . '.csv"');
        $table = table_filter_query($mysqli, [
            'from_sql'=>'FROM applications a JOIN users u ON a.user_id=u.id',
            'select_sql'=>'a.id, u.name, u.phone, u.email, u.barangay, a.type, a.amount_requested, a.amount_granted, a.amount_released, a.status, a.date_of_request, a.created_at',
            'filters'=>$commonFilters,'sort'=>$commonSort,'default_sort'=>'date','paginate'=>false,
        ]);
        export_csv_row($output, ['ID','Applicant','Phone','Email','Barangay','Type','Amount Requested','Amount Granted','Amount Released','Status','Date of Request','Submitted At']);
        foreach ($table['rows'] as $row) {
            export_csv_row($output, [$row['id'],$row['name'],$row['phone'],$row['email'],$row['barangay'],ucfirst($row['type']),$row['amount_requested'],$row['amount_granted'],$row['amount_released'],ucfirst($row['status']),$row['date_of_request'],$row['created_at']]);
        }

    } elseif ($export_type === 'beneficiaries') {
        header('Content-Disposition: attachment; filename="beneficiaries_report_' . date('Y-m-d') . '.csv"');
        $beneficiaryFilters = $commonFilters;
        $beneficiaryFilters['date_from'] = ['kind'=>'date','label'=>'Registered from','sql'=>'u.created_at','operator'=>'>='];
        $beneficiaryFilters['date_to'] = ['kind'=>'date','label'=>'Registered to','sql'=>'u.created_at','operator'=>'<','inclusive_end'=>true];
        $beneficiaryFilters['account_status'] = ['kind'=>'select','label'=>'Account status','options'=>['active'=>'Active','suspended'=>'Suspended'],'sql'=>'u.status'];
        $beneficiaryFilters['applications'] = ['kind'=>'select','label'=>'Applications','options'=>['has'=>'Has applications','none'=>'Has no applications'],'expressions'=>['has'=>'EXISTS (SELECT 1 FROM applications ax WHERE ax.user_id=u.id)','none'=>'NOT EXISTS (SELECT 1 FROM applications ax WHERE ax.user_id=u.id)']];
        $table = table_filter_query($mysqli, [
            'from_sql'=>'FROM users u LEFT JOIN applications a ON a.user_id=u.id',
            'base_where'=>"u.role='client'",'count_expression'=>'COUNT(DISTINCT u.id)',
            'select_sql'=>"u.id, u.name, u.phone, u.email, u.barangay, u.created_at, COUNT(a.id) AS total_apps, COALESCE(SUM(a.status='approved'),0) AS approved, COALESCE(SUM(a.status='pending'),0) AS pending, COALESCE(SUM(a.status='rejected'),0) AS rejected",
            'group_by'=>'GROUP BY u.id','filters'=>$beneficiaryFilters,
            'sort'=>['name'=>'u.name','phone'=>'u.phone','barangay'=>'u.barangay','status'=>'u.status','total'=>'total_apps','applications'=>'total_apps','registered'=>'u.created_at','approved'=>'approved'],
            'default_sort'=>'name','default_dir'=>'ASC','paginate'=>false,
        ]);
        export_csv_row($output, ['ID','Name','Phone','Email','Barangay','Total Applications','Approved','Pending','Rejected','Registered']);
        foreach ($table['rows'] as $row) {
            export_csv_row($output, [$row['id'],$row['name'],$row['phone'],$row['email'],$row['barangay'],$row['total_apps'],$row['approved'],$row['pending'],$row['rejected'],$row['created_at']]);
        }

    } elseif ($export_type === 'summary') {
        header('Content-Disposition: attachment; filename="summary_report_' . date('Y-m-d') . '.csv"');

        export_csv_row($output, ['Metric', 'Value']);

        $table = table_filter_query($mysqli, [
            'from_sql'=>'FROM applications a JOIN users u ON a.user_id=u.id',
            'select_sql'=>'a.id, a.type, a.status, a.amount_requested, a.amount_released, u.id AS beneficiary_id, u.barangay',
            'filters'=>$commonFilters,'sort'=>$commonSort,'default_sort'=>'date','paginate'=>false,
        ]);
        $total = count($table['rows']);
        $approved = $pending = $rejected = 0;
        $total_requested = $total_approved_amt = 0.0;
        $beneficiaries = $byType = $byBarangay = [];
        foreach ($table['rows'] as $item) {
            if ($item['status'] === 'approved') {
                $approved++;
                $total_approved_amt += (float)$item['amount_requested'];
            }
            if ($item['status'] === 'pending') $pending++;
            if ($item['status'] === 'rejected') $rejected++;
            $total_requested += (float)$item['amount_requested'];
            $beneficiaries[(int)$item['beneficiary_id']] = true;
            $byType[$item['type']] = ($byType[$item['type']] ?? 0) + 1;
            $byBarangay[$item['barangay'] ?? ''] = ($byBarangay[$item['barangay'] ?? ''] ?? 0) + 1;
        }

        export_csv_row($output, ['Total Applications', $total]);
        export_csv_row($output, ['Approved', $approved]);
        export_csv_row($output, ['Pending', $pending]);
        export_csv_row($output, ['Rejected', $rejected]);
        export_csv_row($output, ['Approval Rate', $total ? round($approved/$total*100,1).'%' : '0%']);
        export_csv_row($output, ['Total Amount Requested', number_format($total_requested, 2)]);
        export_csv_row($output, ['Total Amount Approved', number_format($total_approved_amt, 2)]);
        export_csv_row($output, ['Total Beneficiaries', count($beneficiaries)]);
        export_csv_row($output, ['Report Date', date('Y-m-d H:i:s')]);

        // By type
        export_csv_row($output, []);
        export_csv_row($output, ['--- Applications by Type ---']);
        foreach ($byType as $type => $count) export_csv_row($output, [ucfirst($type), $count]);

        // By barangay
        export_csv_row($output, []);
        export_csv_row($output, ['--- Applications by Barangay ---']);
        foreach ($byBarangay as $barangay => $count) if ($barangay !== '') export_csv_row($output, [$barangay, $count]);
    }

    fclose($output);
    exit;
}

$samarica_barangays = array_column($mysqli->query("SELECT DISTINCT barangay FROM users WHERE barangay IS NOT NULL AND barangay <> '' ORDER BY barangay")->fetch_all(MYSQLI_ASSOC), 'barangay');

// --- Page Data ---
$pending_aids = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0] ?? 0);
$total_apps   = get_count('applications');
$total_users  = get_count('users', "role='client'");
$type_list    = ['medical','burial','educational','livelihood','emergency'];
$status_list  = ['pending','approved','rejected','cancelled','released'];

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
                <label>Keyword</label>
                <input type="search" id="filterSearch" placeholder="Name, phone, email or ID">
            </div>
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
            <div class="filter-field">
                <label>Minimum Amount</label>
                <input type="number" id="filterAmountMin" min="0" step="0.01">
            </div>
            <div class="filter-field">
                <label>Maximum Amount</label>
                <input type="number" id="filterAmountMax" min="0" step="0.01">
            </div>
            <div class="filter-field">
                <label>Sort By</label>
                <select id="filterSort">
                    <option value="date">Date submitted</option><option value="applicant">Applicant</option><option value="amount">Amount</option><option value="status">Status</option><option value="type">Aid type</option>
                </select>
            </div>
            <div class="filter-field">
                <label>Direction</label>
                <select id="filterDirection"><option value="DESC">Newest / descending</option><option value="ASC">Oldest / ascending</option></select>
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
    document.getElementById('filterCard').style.display = 'block';
    lucide.createIcons();
}

function buildFilterParams(forPrint = false) {
    const search   = document.getElementById('filterSearch').value;
    const status   = document.getElementById('filterStatus').value;
    const type     = document.getElementById('filterType').value;
    const barangay = document.getElementById('filterBarangay').value;
    const from     = document.getElementById('filterFrom').value;
    const to       = document.getElementById('filterTo').value;
    const amountMin = document.getElementById('filterAmountMin').value;
    const amountMax = document.getElementById('filterAmountMax').value;
    const sort = document.getElementById('filterSort').value;
    const direction = document.getElementById('filterDirection').value;
    let p = '';
    if (search)   p += '&search=' + encodeURIComponent(search);
    if (status)   p += '&status='   + encodeURIComponent(status);
    if (type)     p += (forPrint ? '&type_filter=' : '&type=') + encodeURIComponent(type);
    if (barangay) p += '&barangay=' + encodeURIComponent(barangay);
    if (from)     p += '&date_from=' + encodeURIComponent(from);
    if (to)       p += '&date_to='   + encodeURIComponent(to);
    if (amountMin) p += '&amount_min=' + encodeURIComponent(amountMin);
    if (amountMax) p += '&amount_max=' + encodeURIComponent(amountMax);
    if (sort) p += '&sort=' + encodeURIComponent(sort);
    if (direction) p += '&dir=' + encodeURIComponent(direction);
    return p;
}

function openPrintReport() {
    let url = 'print_report.php?type=' + selectedReport;
    if (selectedReport === 'applications' || selectedReport === 'beneficiaries' || selectedReport === 'summary') {
        url += buildFilterParams(true);
    }
    window.open(url, '_blank');
}

function downloadReport() {
    let url = '?export=' + selectedReport;
    if (selectedReport === 'applications' || selectedReport === 'beneficiaries' || selectedReport === 'summary') {
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
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterAmountMin').value = '';
    document.getElementById('filterAmountMax').value = '';
    document.getElementById('filterSort').value = 'date';
    document.getElementById('filterDirection').value = 'DESC';
}

// Auto-select first report type
selectReport('applications');
</script>
</body>
</html>
