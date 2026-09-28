<?php
// admin/beneficiaries.php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../helpers/table_filters.php';
require_admin();

$town_list = array_column($mysqli->query("SELECT DISTINCT barangay FROM users WHERE barangay IS NOT NULL AND barangay <> '' ORDER BY barangay")->fetch_all(MYSQLI_ASSOC), 'barangay');
$table = table_filter_query($mysqli, [
    'from_sql' => 'FROM users u LEFT JOIN applications a ON a.user_id = u.id',
    'base_where' => "u.role = 'client'",
    'count_expression' => 'COUNT(DISTINCT u.id)',
    'select_sql' => 'u.id, u.name, u.phone, u.barangay, u.status, u.created_at, COUNT(a.id) AS total_aids, COALESCE(SUM(a.status = \'approved\'), 0) AS approved_aids, MAX(a.created_at) AS last_request',
    'group_by' => 'GROUP BY u.id',
    'filters' => [
        'search' => ['kind'=>'search', 'label'=>'Keyword', 'placeholder'=>'Name or phone', 'columns'=>['u.name','u.phone']],
        'barangay' => ['kind'=>'select','label'=>'Barangay','options'=>array_combine($town_list, $town_list) ?: [],'sql'=>'u.barangay'],
        'account_status' => ['kind'=>'select','label'=>'Account status','options'=>['active'=>'Active','suspended'=>'Suspended'],'sql'=>'u.status'],
        'date_from' => ['kind'=>'date','label'=>'Registered from','sql'=>'u.created_at','operator'=>'>='],
        'date_to' => ['kind'=>'date','label'=>'Registered to','sql'=>'u.created_at','operator'=>'<','inclusive_end'=>true],
        'applications' => ['kind'=>'select','label'=>'Applications','options'=>['has'=>'Has applications','none'=>'Has no applications'],'expressions'=>['has'=>'EXISTS (SELECT 1 FROM applications ax WHERE ax.user_id = u.id)','none'=>'NOT EXISTS (SELECT 1 FROM applications ax WHERE ax.user_id = u.id)']],
    ],
    'sort' => ['name'=>'u.name','phone'=>'u.phone','barangay'=>'u.barangay','status'=>'u.status','total'=>'total_aids','approved'=>'approved_aids','registered'=>'u.created_at'],
    'default_sort'=>'name','default_dir'=>'ASC','per_page'=>25,
]);
$beneficiaries = $table['rows'];
$search = $table['filters']['search'];
$f_town = $table['filters']['barangay'];
$beneficiaryParams = $_GET;
unset($beneficiaryParams['page'], $beneficiaryParams['per_page']);
$beneficiaryExportUrl = 'export_reports.php?' . http_build_query(array_merge($beneficiaryParams, ['export'=>'beneficiaries']));
$beneficiaryPrintUrl = 'print_report.php?' . http_build_query(array_merge(['type'=>'beneficiaries'], $beneficiaryParams));

// --- Static Town/Bayan list (hindi na barangay ng Calintaan lang, mga karatig-bayan) ---

// Partials
$active_page   = 'beneficiaries';
$page_title    = 'Beneficiaries';
$page_subtitle = 'Beneficiaries';
$pending_aids  = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0] ?? 0);
$colors        = ['#1a56db','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Beneficiaries — AIDTRACK Admin</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/admin.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
.aids-bar { display:flex; gap:3px; align-items:center; }
.aids-bar-fill { height:5px; border-radius:99px; background:var(--green); }
.aids-bar-track { width:60px; height:5px; background:var(--border); border-radius:99px; overflow:hidden; }
.role-pill { display:inline-flex; align-items:center; gap:4px; font-size:.63rem; font-weight:700; padding:2px 9px; border-radius:99px; }
.rp-admin  { background:var(--brand-lt); color:var(--brand); }
.rp-client { background:var(--green-lt); color:var(--green); }
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
                <div class="page-title">Registered Beneficiaries</div>
                <div class="page-sub">All residents registered in the AIDTRACK system as aid applicants.</div>
            </div>
            <div class="page-actions">
                <span class="count-pill"><?= $table['total'] ?> total</span>
                <a href="<?= htmlspecialchars($beneficiaryExportUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary btn-sm">
                    <i data-lucide="file-down" style="width:13px;height:13px"></i> Export
                </a>
                <a href="<?= htmlspecialchars($beneficiaryPrintUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-outline btn-sm"><i data-lucide="printer" style="width:13px;height:13px"></i> Print</a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-blue"><i data-lucide="users" style="width:14px;height:14px"></i></div>
                Beneficiary List
                <span class="count-pill"><?= $table['total'] ?></span>
            </div>
        </div>

        <!-- Filter -->
        <?php include 'partials/filter_bar.php'; ?>

        <div class="card-body no-pad">
            <?php if (empty($beneficiaries)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="users" style="width:20px;height:20px"></i></div>
                <div class="empty-text">No beneficiaries match your search.</div>
            </div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?= table_sort_link($table, 'name', 'Beneficiary') ?></th>
                            <th><?= table_sort_link($table, 'phone', 'Phone') ?></th>
                            <th><?= table_sort_link($table, 'barangay', 'Town') ?></th>
                            <th><?= table_sort_link($table, 'status', 'Status') ?></th>
                            <th><?= table_sort_link($table, 'total', 'Total Aids') ?></th>
                            <th><?= table_sort_link($table, 'approved', 'Approved') ?></th>
                            <th><?= table_sort_link($table, 'registered', 'Registered') ?></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($beneficiaries as $i => $b):
                        $col   = $colors[$i % count($colors)];
                        $init  = implode('', array_map(fn($w)=>strtoupper($w[0]), array_slice(explode(' ',$b['name']),0,2)));
                        $total = (int)$b['total_aids'];
                        $appr  = (int)$b['approved_aids'];
                        $pct   = $total > 0 ? min(100, round($appr/$total*100)) : 0;
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:9px;">
                                <div class="row-avatar" style="background:<?= $col ?>"><?= $init ?></div>
                                <div>
                                    <div style="font-weight:700;font-size:.82rem;color:var(--navy);"><?= htmlspecialchars($b['name']) ?></div>
                                    <div style="font-size:.68rem;color:var(--muted);">ID #<?= $b['id'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="font-family:monospace;font-size:.8rem;color:var(--muted);"><?= htmlspecialchars($b['phone'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($b['barangay'] ?? '—') ?></td>
                        <td><?= htmlspecialchars(ucfirst($b['status'] ?? 'active'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-weight:700;color:var(--navy);font-size:.84rem;"><?= $total ?></span>
                                <?php if ($total > 0): ?>
                                <div class="aids-bar-track">
                                    <div class="aids-bar-fill" style="width:<?= $pct ?>%"></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($appr > 0): ?>
                            <span class="badge b-approved"><?= $appr ?> approved</span>
                            <?php else: ?>
                            <span style="font-size:.75rem;color:var(--muted);">None</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--muted);font-size:.76rem;white-space:nowrap;">
                            <?= $b['last_request'] ? date('M d, Y', strtotime($b['last_request'])) : '—' ?>
                        </td>
                        <td>
                            <div class="tbl-actions">
                                <a href="beneficiary_profile.php?id=<?= $b['id'] ?>" class="tbl-action ta-view">
                                    <i data-lucide="user" style="width:11px;height:11px"></i> Profile
                                </a>
                                <a href="beneficiary_new_aid.php?id=<?= $b['id'] ?>" class="tbl-action ta-approve">
                                    <i data-lucide="plus" style="width:11px;height:11px"></i> New Aid
                                </a>
                            </div>
                        </td>
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