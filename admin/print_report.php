<?php
// admin/print_report.php — Printable report view
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../helpers/table_filters.php';
require_admin();

$report_type = $_GET['report'] ?? $_GET['type'] ?? 'applications';
if (!in_array($report_type, ['applications','beneficiaries','summary'], true)) $report_type = 'applications';
$_GET['type'] = $_GET['type_filter'] ?? '';
$barangayRows = $mysqli->query("SELECT DISTINCT barangay FROM users WHERE barangay IS NOT NULL AND barangay <> '' ORDER BY barangay")->fetch_all(MYSQLI_ASSOC);
$barangayValues = array_column($barangayRows, 'barangay');
$barangayOptions = array_combine($barangayValues, $barangayValues) ?: [];
$typeOptions = ['medical'=>'Medical','burial'=>'Burial','educational'=>'Educational','livelihood'=>'Livelihood','emergency'=>'Emergency'];
$statusOptions = ['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','cancelled'=>'Cancelled','released'=>'Released'];
$filters = [
    'search'=>['kind'=>'search','label'=>'Keyword','columns'=>['u.name','u.phone','u.email','CAST(a.id AS CHAR)']],
    'status'=>['kind'=>'select','label'=>'Status','options'=>$statusOptions,'sql'=>'a.status','expressions'=>['released'=>'a.amount_released > 0']],
    'type'=>['kind'=>'select','label'=>'Aid type','options'=>$typeOptions,'sql'=>'a.type'],
    'barangay'=>['kind'=>'select','label'=>'Barangay','options'=>$barangayOptions,'sql'=>'u.barangay'],
    'date_from'=>['kind'=>'date','label'=>'Date from','sql'=>'a.created_at','operator'=>'>='],
    'date_to'=>['kind'=>'date','label'=>'Date to','sql'=>'a.created_at','operator'=>'<','inclusive_end'=>true],
    'amount_min'=>['kind'=>'number','label'=>'Minimum amount','sql'=>'a.amount_requested','operator'=>'>='],
    'amount_max'=>['kind'=>'number','label'=>'Maximum amount','sql'=>'a.amount_requested','operator'=>'<='],
];
$sortMap = ['id'=>'a.id','applicant'=>'u.name','client'=>'u.name','barangay'=>'u.barangay','type'=>'a.type','amount'=>'a.amount_requested','date'=>'a.created_at','status'=>'a.status'];

$report_title = match($report_type) {
    'beneficiaries' => 'Beneficiaries Report',
    'summary'       => 'Summary Report',
    default         => 'Applications Report'
};
$rows = [];
$columns = [];

if ($report_type === 'applications') {
    $columns = ['ID','Applicant','Phone','Email','Barangay','Type','Amount Requested','Amount Granted','Amount Released','Status','Date'];
    $table = table_filter_query($mysqli, [
        'from_sql'=>'FROM applications a JOIN users u ON a.user_id=u.id',
        'select_sql'=>'a.id, u.name, u.phone, u.email, u.barangay, a.type, a.amount_requested, a.amount_granted, a.amount_released, a.status, a.date_of_request, a.created_at',
        'filters'=>$filters,'sort'=>$sortMap,'default_sort'=>'date','paginate'=>false,
    ]);
    foreach ($table['rows'] as $row) {
        $rows[] = [
            $row['id'], $row['name'], $row['phone'], $row['email'], $row['barangay'],
            ucfirst($row['type']),
            '₱' . number_format($row['amount_requested'], 2),
            '₱' . number_format($row['amount_granted'] ?? 0, 2),
            '₱' . number_format($row['amount_released'] ?? 0, 2),
            ucfirst($row['status']),
            $row['date_of_request']
        ];
    }

} elseif ($report_type === 'beneficiaries') {
    $columns = ['ID','Name','Phone','Email','Barangay','Total Apps','Approved','Pending','Rejected','Registered'];
    $beneficiaryFilters = $filters;
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
    foreach ($table['rows'] as $row) {
        $rows[] = [$row['id'], $row['name'], $row['phone'], $row['email'], $row['barangay'], $row['total_apps'], $row['approved'], $row['pending'], $row['rejected'], date('M d, Y', strtotime($row['created_at']))];
    }

} elseif ($report_type === 'summary') {
    $columns = ['Metric','Value'];
    $table = table_filter_query($mysqli, [
        'from_sql'=>'FROM applications a JOIN users u ON a.user_id=u.id',
        'select_sql'=>'a.id, a.type, a.status, a.amount_requested, a.amount_released, u.id AS beneficiary_id, u.barangay',
        'filters'=>$filters,'sort'=>$sortMap,'default_sort'=>'date','paginate'=>false,
    ]);
    $total = count($table['rows']);
    $approved = $pending = $rejected = 0;
    $total_req = $total_rel = 0.0;
    $beneficiaryIds = [];
    $byType = [];
    $byBarangay = [];
    foreach ($table['rows'] as $item) {
        if ($item['status'] === 'approved') $approved++;
        if ($item['status'] === 'pending') $pending++;
        if ($item['status'] === 'rejected') $rejected++;
        $total_req += (float)$item['amount_requested'];
        $total_rel += (float)$item['amount_released'];
        $beneficiaryIds[(int)$item['beneficiary_id']] = true;
        $byType[$item['type']] = ($byType[$item['type']] ?? 0) + 1;
        $byBarangay[$item['barangay'] ?? ''] = ($byBarangay[$item['barangay'] ?? ''] ?? 0) + 1;
    }

    $rows = [
        ['Total Applications', $total],
        ['Approved', $approved],
        ['Pending', $pending],
        ['Rejected', $rejected],
        ['Approval Rate', $total ? round($approved/$total*100,1).'%' : '0%'],
        ['Total Amount Requested', '₱' . number_format($total_req, 2)],
        ['Total Amount Released', '₱' . number_format($total_rel, 2)],
        ['Total Beneficiaries', count($beneficiaryIds)],
    ];
    foreach ($byType as $type => $count) $rows[] = ['Applications by ' . ucfirst($type), $count];
    foreach ($byBarangay as $barangay => $count) if ($barangay !== '') $rows[] = ['Applications in ' . $barangay, $count];
}
$activeFilters = $table['filters'] ?? [];
$recordCount = $table['total'] ?? count($rows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $report_title ?> — AIDTRACK</title>
<style>
@page { margin: 15mm; }
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family: 'Segoe UI', Arial, sans-serif; font-size:11px; color:#1e293b; padding:20px; }
.header { display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #1a56db; padding-bottom:12px; margin-bottom:20px; }
.header h1 { font-size:18px; color:#1a56db; }
.header .meta { text-align:right; font-size:10px; color:#64748b; }
.filters { background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 14px; margin-bottom:16px; font-size:10px; color:#64748b; }
.filters strong { color:#1e293b; }
table { width:100%; border-collapse:collapse; margin-bottom:20px; }
th { background:#f1f5f9; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#64748b; text-align:left; padding:8px 10px; border-bottom:2px solid #e2e8f0; }
td { padding:7px 10px; border-bottom:1px solid #e2e8f0; font-size:10.5px; }
tr:nth-child(even) { background:#f8fafc; }
.footer { border-top:1px solid #e2e8f0; padding-top:10px; margin-top:20px; font-size:9px; color:#94a3b8; display:flex; justify-content:space-between; }
.total-row { font-weight:700; background:#f1f5f9 !important; }
.no-print { margin-bottom:16px; }
@media print {
    .no-print { display:none !important; }
    body { padding:0; }
}
</style>
</head>
<body>
<div class="no-print" style="display:flex;gap:8px;">
    <button onclick="window.print()" style="padding:8px 16px;background:#1a56db;color:#fff;border:none;border-radius:6px;font-weight:700;cursor:pointer;font-size:12px;">Print Report</button>
    <button onclick="window.close()" style="padding:8px 16px;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;border-radius:6px;font-weight:700;cursor:pointer;font-size:12px;">Close</button>
</div>

<div class="header">
    <div>
        <h1>AIDTRACK — <?= $report_title ?></h1>
        <div style="font-size:10px;color:#64748b;margin-top:4px;">Municipal Social Welfare and Development Office</div>
    </div>
    <div class="meta">
        <div>Generated: <?= date('F d, Y — h:i A') ?></div>
        <div>Total Records: <?= (int)$recordCount ?></div>
    </div>
</div>

<?php if (!empty($activeFilters) && array_filter($activeFilters, static fn($value) => $value !== '')): ?>
<div class="filters">
    Filters applied:
    <?php foreach ($activeFilters as $filterKey => $filterValue): if ($filterValue === '') continue; ?>
    <strong><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $filterKey)), ENT_QUOTES, 'UTF-8') ?>:</strong> <?= htmlspecialchars((string)$filterValue, ENT_QUOTES, 'UTF-8') ?> &nbsp;
    <?php endforeach; ?>
    <strong>Sort:</strong> <?= htmlspecialchars($table['sort'] ?? 'default', ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($table['dir'] ?? '', ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <?php foreach ($columns as $col): ?>
            <th><?= $col ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="<?= count($columns) ?>" style="text-align:center;color:#94a3b8;padding:30px;">No records found.</td></tr>
        <?php else: ?>
        <?php foreach ($rows as $row): ?>
        <tr>
            <?php foreach ($row as $cell): ?>
            <td><?= htmlspecialchars($cell) ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<div class="footer">
    <div>AIDTRACK System — Confidential</div>
    <div>Page 1 of 1 — Printed by <?= htmlspecialchars($_SESSION['user']['name'] ?? 'Admin') ?></div>
</div>

<script>
// Auto-print on load
window.onload = function() {
    // Give a moment for the page to render
    setTimeout(() => window.print(), 500);
};
</script>
</body>
</html>
