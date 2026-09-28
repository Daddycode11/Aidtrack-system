<?php
// admin/print_report.php — Printable report view
require_once __DIR__ . '/../helpers.php';
require_admin();

$report_type = $_GET['type'] ?? 'applications';
$f_status    = $_GET['status']      ?? '';
$f_type      = $_GET['type_filter'] ?? ($_GET['type'] ?? '');
$f_from      = $_GET['date_from']   ?? '';
$f_to        = $_GET['date_to']     ?? '';
$f_barangay  = $_GET['barangay']    ?? '';

// Override: if type is a report type, don't use it as aid filter
if (in_array($f_type, ['applications','beneficiaries','summary'])) $f_type = '';

$report_title = match($report_type) {
    'beneficiaries' => 'Beneficiaries Report',
    'summary'       => 'Summary Report',
    default         => 'Applications Report'
};

$rows = [];
$columns = [];

if ($report_type === 'applications') {
    $columns = ['ID','Applicant','Phone','Barangay','Type','Amount Requested','Amount Granted','Amount Released','Status','Date'];

    $where = ['1=1']; $params = []; $types = '';
    if ($f_status && !in_array($f_status, ['applications','beneficiaries','summary'])) {
        $where[] = 'a.status = ?'; $params[] = $f_status; $types .= 's';
    }
    if ($f_type)     { $where[] = 'a.type = ?';           $params[] = $f_type;     $types .= 's'; }
    if ($f_barangay) { $where[] = 'u.barangay = ?';       $params[] = $f_barangay; $types .= 's'; }
    if ($f_from)     { $where[] = 'a.date_of_request >= ?'; $params[] = $f_from;   $types .= 's'; }
    if ($f_to)       { $where[] = 'a.date_of_request <= ?'; $params[] = $f_to;     $types .= 's'; }
    $where_sql = implode(' AND ', $where);

    $stmt = $mysqli->prepare("
        SELECT a.id, u.name, u.phone, u.barangay, a.type, a.amount_requested, a.amount_granted, a.amount_released, a.status, a.date_of_request
        FROM applications a JOIN users u ON a.user_id = u.id
        WHERE $where_sql ORDER BY a.date_of_request DESC
    ");
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            $row['id'], $row['name'], $row['phone'], $row['barangay'],
            ucfirst($row['type']),
            '₱' . number_format($row['amount_requested'], 2),
            '₱' . number_format($row['amount_granted'] ?? 0, 2),
            '₱' . number_format($row['amount_released'] ?? 0, 2),
            ucfirst($row['status']),
            $row['date_of_request']
        ];
    }
    $stmt->close();

} elseif ($report_type === 'beneficiaries') {
    $columns = ['ID','Name','Phone','Barangay','Total Apps','Approved','Pending','Rejected','Registered'];
    $b_where = []; $b_params = []; $b_types = '';
    if ($f_barangay) { $b_where[] = 'u.barangay = ?'; $b_params[] = $f_barangay; $b_types .= 's'; }
    $b_where_sql = $b_where ? 'AND ' . implode(' AND ', $b_where) : '';
    $stmt = $mysqli->prepare("
        SELECT u.id, u.name, u.phone, u.barangay, u.created_at,
               COUNT(a.id) AS total_apps,
               SUM(a.status='approved') AS approved,
               SUM(a.status='pending') AS pending,
               SUM(a.status='rejected') AS rejected
        FROM users u
        INNER JOIN applications a ON u.id = a.user_id
        WHERE u.role = 'client' $b_where_sql
        GROUP BY u.id
        HAVING SUM(a.status='approved') > 0
        ORDER BY u.name ASC
    ");
    if ($b_params) $stmt->bind_param($b_types, ...$b_params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = [$row['id'], $row['name'], $row['phone'], $row['barangay'], $row['total_apps'], $row['approved'], $row['pending'], $row['rejected'], date('M d, Y', strtotime($row['created_at']))];
    }
    $stmt->close();

} elseif ($report_type === 'summary') {
    $columns = ['Metric','Value'];
    $total = get_count('applications');
    $approved = (int)$mysqli->query("SELECT COUNT(*) FROM applications WHERE status='approved'")->fetch_row()[0];
    $pending  = (int)$mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0];
    $rejected = (int)$mysqli->query("SELECT COUNT(*) FROM applications WHERE status='rejected'")->fetch_row()[0];
    $total_req = $mysqli->query("SELECT COALESCE(SUM(amount_requested),0) FROM applications")->fetch_row()[0];
    $total_rel = $mysqli->query("SELECT COALESCE(SUM(amount_released),0) FROM applications WHERE status='approved'")->fetch_row()[0];

    $rows = [
        ['Total Applications', $total],
        ['Approved', $approved],
        ['Pending', $pending],
        ['Rejected', $rejected],
        ['Approval Rate', $total ? round($approved/$total*100,1).'%' : '0%'],
        ['Total Amount Requested', '₱' . number_format($total_req, 2)],
        ['Total Amount Released', '₱' . number_format($total_rel, 2)],
        ['Total Beneficiaries', get_count('users', "role='client'")],
    ];
}
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
        <div>Total Records: <?= count($rows) ?></div>
    </div>
</div>

<?php if ($f_status || $f_type || $f_barangay || $f_from || $f_to): ?>
<div class="filters">
    Filters applied:
    <?php if ($f_status): ?><strong>Status:</strong> <?= ucfirst(htmlspecialchars($f_status)) ?> &nbsp;<?php endif; ?>
    <?php if ($f_type): ?><strong>Type:</strong> <?= ucfirst(htmlspecialchars($f_type)) ?> &nbsp;<?php endif; ?>
    <?php if ($f_barangay): ?><strong>Barangay:</strong> <?= htmlspecialchars($f_barangay) ?> &nbsp;<?php endif; ?>
    <?php if ($f_from): ?><strong>From:</strong> <?= htmlspecialchars($f_from) ?> &nbsp;<?php endif; ?>
    <?php if ($f_to): ?><strong>To:</strong> <?= htmlspecialchars($f_to) ?><?php endif; ?>
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
