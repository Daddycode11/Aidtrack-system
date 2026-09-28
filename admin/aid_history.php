<?php
// admin/aid_history.php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../helpers/table_filters.php';
require_admin();

$barangay_list = array_column($mysqli->query("SELECT DISTINCT barangay FROM users WHERE barangay IS NOT NULL AND barangay <> '' ORDER BY barangay")->fetch_all(MYSQLI_ASSOC), 'barangay');
$type_list = ['medical','burial','educational','livelihood','emergency'];
$status_list = ['pending','approved','rejected','cancelled'];
$table = table_filter_query($mysqli, [
    'from_sql' => "FROM applications a JOIN users u ON a.user_id = u.id LEFT JOIN (SELECT application_id, MAX(created_at) AS released_at, SUBSTRING_INDEX(GROUP_CONCAT(admin_id ORDER BY created_at DESC), ',', 1) AS released_by_id FROM admin_actions WHERE action='release' GROUP BY application_id) rel ON rel.application_id = a.id LEFT JOIN users ru ON ru.id = rel.released_by_id",
    'select_sql' => 'a.id, a.type AS assistance, a.status, a.date_of_request, a.created_at, a.amount_requested, a.amount_released, u.name AS full_name, u.phone, u.email, u.barangay, rel.released_at, ru.name AS released_by',
    'filters' => [
        'search' => ['kind'=>'search','label'=>'Keyword','placeholder'=>'Name, phone, email or ID','columns'=>['u.name','u.phone','u.email','CAST(a.id AS CHAR)']],
        'barangay' => ['kind'=>'select','label'=>'Barangay','options'=>array_combine($barangay_list,$barangay_list) ?: [],'sql'=>'u.barangay'],
        'type' => ['kind'=>'select','label'=>'Aid type','options'=>array_combine($type_list,array_map('ucfirst',$type_list)),'sql'=>'a.type'],
        'status' => ['kind'=>'select','label'=>'Status','options'=>array_combine($status_list,array_map('ucfirst',$status_list)),'sql'=>'a.status'],
        'date_from' => ['kind'=>'date','label'=>'Released from','sql'=>'rel.released_at','operator'=>'>='],
        'date_to' => ['kind'=>'date','label'=>'Released to','sql'=>'rel.released_at','operator'=>'<','inclusive_end'=>true],
        'amount_min' => ['kind'=>'number','label'=>'Amount from','sql'=>'a.amount_released','operator'=>'>='],
        'amount_max' => ['kind'=>'number','label'=>'Amount to','sql'=>'a.amount_released','operator'=>'<='],
        'released_by' => ['kind'=>'search','label'=>'Released by','placeholder'=>'Admin name','columns'=>['ru.name']],
    ],
    'sort' => ['id'=>'a.id','beneficiary'=>'u.name','barangay'=>'u.barangay','type'=>'a.type','amount'=>'a.amount_released','date'=>'rel.released_at','status'=>'a.status','released_by'=>'ru.name'],
    'default_sort'=>'date','per_page'=>25,
]);
$aid_history = $table['rows'];
$search_name = $table['filters']['search'];
$f_barangay = $table['filters']['barangay'];
$f_status = $table['filters']['status'];
$f_type = $table['filters']['type'];

// Dropdown data
$has_filters = $table['has_filters'];

// Stats
$approved_count = (int)$mysqli->query("SELECT COUNT(*) FROM applications WHERE status='approved'")->fetch_row()[0];
$pending_count  = (int)$mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0];
$rejected_count = (int)$mysqli->query("SELECT COUNT(*) FROM applications WHERE status='rejected'")->fetch_row()[0];

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
                <span class="count-pill"><?= $table['total'] ?> results</span>
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
                <div class="ss-n"><?= $table['total'] ?></div>
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
    <?php include 'partials/filter_bar.php'; ?>

    <!-- Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-blue"><i data-lucide="history" style="width:14px;height:14px"></i></div>
                Aid History Records
                <span class="count-pill"><?= $table['total'] ?> results</span>
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
                <table>
                    <thead>
                        <tr>
                            <th><?= table_sort_link($table, 'id', '#') ?></th>
                            <th><?= table_sort_link($table, 'beneficiary', 'Beneficiary') ?></th>
                            <th><?= table_sort_link($table, 'barangay', 'Barangay') ?></th>
                            <th><?= table_sort_link($table, 'type', 'Assistance') ?></th>
                            <th><?= table_sort_link($table, 'amount', 'Amount Released') ?></th>
                            <th><?= table_sort_link($table, 'date', 'Date Released') ?></th>
                            <th><?= table_sort_link($table, 'released_by', 'Released By') ?></th>
                            <th><?= table_sort_link($table, 'status', 'Status') ?></th>
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
                        <td><span class="amount">₱<?= number_format($row['amount_released'] ?? 0, 2) ?></span></td>
                        <td style="color:var(--muted);white-space:nowrap;"><?= htmlspecialchars($row['released_at'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($row['released_by'] ?? '—') ?></td>
                        <td><span class="badge <?= $bc ?>"><?= ucfirst($s) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php include 'partials/table_pager.php'; ?>
            <?php endif; ?>
        </div>
    </div>

</main>

<script src="partials/admin.js"></script>
</body>
</html>