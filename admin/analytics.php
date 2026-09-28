<?php
// admin/analytics.php
require_once __DIR__ . '/../helpers.php';
require_admin();

// --- Overall Stats ---
$total_users        = get_count('users');
$total_applications = get_count('applications');
$approved_aids      = (int)$mysqli->query("SELECT COUNT(*) FROM applications WHERE status='approved'")->fetch_row()[0];
$pending_aids       = (int)$mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0];
$rejected_aids      = (int)$mysqli->query("SELECT COUNT(*) FROM applications WHERE status='rejected'")->fetch_row()[0];
$total_nz           = $approved_aids + $pending_aids + $rejected_aids;

// --- Applications by Type ---
$by_type = [];
$res = $mysqli->query("SELECT type, COUNT(*) AS count FROM applications GROUP BY type ORDER BY count DESC");
if ($res) $by_type = $res->fetch_all(MYSQLI_ASSOC);

// --- Applications by Barangay ---
$by_barangay = [];
$res = $mysqli->query("
    SELECT u.barangay, COUNT(*) AS count
    FROM applications a JOIN users u ON a.user_id = u.id
    WHERE u.barangay IS NOT NULL AND u.barangay != ''
    GROUP BY u.barangay ORDER BY count DESC LIMIT 10
");
if ($res) $by_barangay = $res->fetch_all(MYSQLI_ASSOC);

// --- Monthly Trend (last 12 months) ---
$monthly = [];
$res = $mysqli->query("
    SELECT DATE_FORMAT(date_of_request, '%Y-%m') AS month,
           SUM(status='approved') AS approved,
           SUM(status='pending') AS pending,
           SUM(status='rejected') AS rejected
    FROM applications
    WHERE date_of_request >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY month ORDER BY month ASC
");
if ($res) $monthly = $res->fetch_all(MYSQLI_ASSOC);

// --- Top Beneficiaries ---
$top_beneficiaries = [];
$res = $mysqli->query("
    SELECT u.name, u.barangay, COUNT(a.id) AS total_apps,
           SUM(a.status='approved') AS approved_count,
           COALESCE(SUM(a.amount_requested), 0) AS total_requested
    FROM applications a JOIN users u ON a.user_id = u.id
    GROUP BY u.id ORDER BY total_apps DESC LIMIT 10
");
if ($res) $top_beneficiaries = $res->fetch_all(MYSQLI_ASSOC);

// --- Amount Stats ---
$amount_stats = $mysqli->query("
    SELECT COALESCE(SUM(amount_requested), 0) AS total_requested,
           COALESCE(SUM(CASE WHEN status='approved' THEN amount_requested ELSE 0 END), 0) AS total_approved_amount
    FROM applications
")->fetch_assoc();

// Chart JSON
$type_labels = json_encode(array_map('ucfirst', array_column($by_type, 'type')));
$type_counts = json_encode(array_map('intval', array_column($by_type, 'count')));
$barangay_labels = json_encode(array_column($by_barangay, 'barangay'));
$barangay_counts = json_encode(array_map('intval', array_column($by_barangay, 'count')));
$month_labels    = json_encode(array_map(fn($m) => date('M Y', strtotime($m['month'].'-01')), $monthly));
$month_approved  = json_encode(array_map(fn($m) => (int)$m['approved'], $monthly));
$month_pending   = json_encode(array_map(fn($m) => (int)$m['pending'], $monthly));
$month_rejected  = json_encode(array_map(fn($m) => (int)$m['rejected'], $monthly));

// Partials
$active_page   = 'analytics';
$page_title    = 'Analytics';
$page_subtitle = 'Analytics';
$colors = ['#1a56db','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analytics — AIDTRACK Admin</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/admin.css">
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
.stats-grid {
    display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px; margin-bottom: 18px;
}
.stat-card {
    background: var(--white); border: 1px solid var(--border); border-radius: var(--r);
    padding: 18px 18px 16px; display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;
    transition: box-shadow .18s;
}
.stat-card:hover { box-shadow: var(--sh-md); }
.sc-label { font-size:.7rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:8px; }
.sc-value { font-size:1.85rem; font-weight:800; color:var(--navy); line-height:1; }
.sc-sub   { font-size:.69rem; font-weight:600; color:var(--muted); margin-top:6px; display:flex; align-items:center; gap:4px; }
.sc-icon  { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.sci-blue   { background:var(--brand-lt); color:var(--brand); }
.sci-green  { background:var(--green-lt); color:var(--green); }
.sci-yellow { background:var(--yellow-lt); color:var(--yellow); }
.sci-red    { background:var(--red-lt);   color:var(--red); }
.sci-purple { background:#f5f3ff; color:#7c3aed; }

.grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
.chart-wrap { padding:16px 20px; }
.chart-wrap canvas { max-height:260px; }

.rate-bar { margin-bottom:13px; }
.rate-bar-head { display:flex; justify-content:space-between; font-size:.74rem; font-weight:700; color:var(--slate); margin-bottom:5px; }
.rate-bar-track { height:6px; background:var(--border); border-radius:99px; overflow:hidden; }
.rate-bar-fill { height:100%; border-radius:99px; }

@media(max-width:1280px){ .stats-grid { grid-template-columns:repeat(3,1fr); } }
@media(max-width:860px) { .stats-grid { grid-template-columns:repeat(2,1fr); } .grid-2 { grid-template-columns:1fr; } }
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
                <div class="page-title">Analytics</div>
                <div class="page-sub">Comprehensive data insights on aid distribution and application trends.</div>
            </div>
            <div class="page-actions">
                <a href="export_reports.php" class="btn btn-primary btn-sm">
                    <i data-lucide="file-down" style="width:13px;height:13px"></i> Export Reports
                </a>
            </div>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div>
                <div class="sc-label">Total Applications</div>
                <div class="sc-value"><?= number_format($total_applications) ?></div>
                <div class="sc-sub"><i data-lucide="file-text" style="width:11px;height:11px"></i> All time</div>
            </div>
            <div class="sc-icon sci-purple"><i data-lucide="file-text" style="width:18px;height:18px"></i></div>
        </div>
        <div class="stat-card">
            <div>
                <div class="sc-label">Approved</div>
                <div class="sc-value" style="color:var(--green)"><?= number_format($approved_aids) ?></div>
                <div class="sc-sub"><?= $total_nz ? round($approved_aids/$total_nz*100) : 0 ?>% approval rate</div>
            </div>
            <div class="sc-icon sci-green"><i data-lucide="check-circle" style="width:18px;height:18px"></i></div>
        </div>
        <div class="stat-card">
            <div>
                <div class="sc-label">Pending</div>
                <div class="sc-value" style="color:var(--yellow)"><?= number_format($pending_aids) ?></div>
                <div class="sc-sub"><i data-lucide="hourglass" style="width:11px;height:11px"></i> Awaiting review</div>
            </div>
            <div class="sc-icon sci-yellow"><i data-lucide="hourglass" style="width:18px;height:18px"></i></div>
        </div>
        <div class="stat-card">
            <div>
                <div class="sc-label">Rejected</div>
                <div class="sc-value" style="color:var(--red)"><?= number_format($rejected_aids) ?></div>
                <div class="sc-sub"><?= $total_nz ? round($rejected_aids/$total_nz*100) : 0 ?>% rejection rate</div>
            </div>
            <div class="sc-icon sci-red"><i data-lucide="x-circle" style="width:18px;height:18px"></i></div>
        </div>
        <div class="stat-card">
            <div>
                <div class="sc-label">Total Requested</div>
                <div class="sc-value" style="font-size:1.4rem;">₱<?= number_format($amount_stats['total_requested'], 2) ?></div>
                <div class="sc-sub">₱<?= number_format($amount_stats['total_approved_amount'], 2) ?> approved</div>
            </div>
            <div class="sc-icon sci-blue"><i data-lucide="banknote" style="width:18px;height:18px"></i></div>
        </div>
    </div>

    <!-- Monthly Trend + Status Breakdown -->
    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon cti-blue"><i data-lucide="trending-up" style="width:14px;height:14px"></i></div>
                    Monthly Trend (Last 12 Months)
                </div>
                <span style="font-size:.75rem;font-weight:600;color:var(--brand);cursor:pointer;" onclick="exportChart('monthlyChart')">
                    <i data-lucide="download" style="width:12px;height:12px;vertical-align:middle"></i> Export
                </span>
            </div>
            <div class="chart-wrap"><canvas id="monthlyChart"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon cti-purple"><i data-lucide="pie-chart" style="width:14px;height:14px"></i></div>
                    Applications by Type
                </div>
                <span style="font-size:.75rem;font-weight:600;color:var(--brand);cursor:pointer;" onclick="exportChart('typeChart')">
                    <i data-lucide="download" style="width:12px;height:12px;vertical-align:middle"></i> Export
                </span>
            </div>
            <div class="chart-wrap"><canvas id="typeChart"></canvas></div>
        </div>
    </div>

    <!-- Barangay Distribution + Rate Summary -->
    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon cti-green"><i data-lucide="map-pin" style="width:14px;height:14px"></i></div>
                    Top Barangays by Applications
                </div>
                <span style="font-size:.75rem;font-weight:600;color:var(--brand);cursor:pointer;" onclick="exportChart('barangayChart')">
                    <i data-lucide="download" style="width:12px;height:12px;vertical-align:middle"></i> Export
                </span>
            </div>
            <div class="chart-wrap"><canvas id="barangayChart"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon cti-yellow"><i data-lucide="activity" style="width:14px;height:14px"></i></div>
                    Rate Summary
                </div>
            </div>
            <div class="card-body">
                <?php
                $rates = [
                    ['label'=>'Approval Rate',  'pct'=>$total_nz ? round($approved_aids/$total_nz*100) : 0, 'color'=>'var(--green)'],
                    ['label'=>'Pending Rate',   'pct'=>$total_nz ? round($pending_aids/$total_nz*100)  : 0, 'color'=>'var(--yellow)'],
                    ['label'=>'Rejection Rate', 'pct'=>$total_nz ? round($rejected_aids/$total_nz*100) : 0, 'color'=>'var(--red)'],
                ];
                foreach ($rates as $r): ?>
                <div class="rate-bar">
                    <div class="rate-bar-head">
                        <span><?= $r['label'] ?></span>
                        <span style="color:<?= $r['color'] ?>;"><?= $r['pct'] ?>%</span>
                    </div>
                    <div class="rate-bar-track">
                        <div class="rate-bar-fill" style="width:<?= $r['pct'] ?>%;background:<?= $r['color'] ?>;"></div>
                    </div>
                </div>
                <?php endforeach; ?>

                <div style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border);">
                    <div style="font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px;">By Type Breakdown</div>
                    <?php foreach ($by_type as $t):
                        $tpct = $total_nz ? round((int)$t['count']/$total_nz*100) : 0;
                    ?>
                    <div class="rate-bar">
                        <div class="rate-bar-head">
                            <span><?= ucfirst($t['type']) ?></span>
                            <span style="color:var(--brand);"><?= $t['count'] ?> (<?= $tpct ?>%)</span>
                        </div>
                        <div class="rate-bar-track">
                            <div class="rate-bar-fill" style="width:<?= $tpct ?>%;background:var(--brand);"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Beneficiaries Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-blue"><i data-lucide="award" style="width:14px;height:14px"></i></div>
                Top Beneficiaries
                <span class="count-pill"><?= count($top_beneficiaries) ?></span>
            </div>
        </div>
        <div class="card-body no-pad">
            <?php if (empty($top_beneficiaries)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="users" style="width:20px;height:20px"></i></div>
                <div class="empty-text">No data available.</div>
            </div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Beneficiary</th>
                            <th>Barangay</th>
                            <th>Applications</th>
                            <th>Approved</th>
                            <th>Total Requested</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($top_beneficiaries as $i => $b):
                        $col  = $colors[$i % count($colors)];
                        $init = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $b['name']), 0, 2)));
                    ?>
                    <tr>
                        <td style="color:var(--muted);font-size:.74rem;"><?= $i + 1 ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:9px;">
                                <div class="row-avatar" style="background:<?= $col ?>;"><?= $init ?></div>
                                <span style="font-weight:700;font-size:.82rem;color:var(--navy);"><?= htmlspecialchars($b['name']) ?></span>
                            </div>
                        </td>
                        <td style="color:var(--muted);"><?= htmlspecialchars($b['barangay'] ?? '-') ?></td>
                        <td style="font-weight:700;"><?= $b['total_apps'] ?></td>
                        <td><span class="badge b-approved"><?= $b['approved_count'] ?></span></td>
                        <td style="font-family:monospace;font-weight:700;font-size:.8rem;">₱<?= number_format($b['total_requested'], 2) ?></td>
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
<script>
Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
Chart.defaults.font.size = 12;

// Monthly Trend - Line Chart
new Chart(document.getElementById('monthlyChart'), {
    type: 'line',
    data: {
        labels: <?= $month_labels ?>,
        datasets: [
            { label:'Approved', data:<?= $month_approved ?>, borderColor:'#16a34a', backgroundColor:'rgba(22,163,74,.08)', fill:true, tension:.3, borderWidth:2, pointRadius:3 },
            { label:'Pending',  data:<?= $month_pending ?>,  borderColor:'#ca8a04', backgroundColor:'rgba(202,138,4,.08)', fill:true, tension:.3, borderWidth:2, pointRadius:3 },
            { label:'Rejected', data:<?= $month_rejected ?>, borderColor:'#dc2626', backgroundColor:'rgba(220,38,38,.08)', fill:true, tension:.3, borderWidth:2, pointRadius:3 }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend:{ position:'bottom', labels:{ boxWidth:10, padding:12, font:{size:11}, color:'#64748b' } } },
        scales: {
            y: { beginAtZero:true, ticks:{ stepSize:1, color:'#94a3b8' }, grid:{ color:'rgba(0,0,0,.04)' }, border:{ display:false } },
            x: { grid:{ display:false }, border:{ display:false }, ticks:{ color:'#94a3b8', maxRotation:45 } }
        }
    }
});

// Applications by Type - Doughnut
new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: {
        labels: <?= $type_labels ?>,
        datasets: [{
            data: <?= $type_counts ?>,
            backgroundColor: ['#1a56db','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'],
            borderWidth: 3, borderColor: '#fff', hoverOffset: 8
        }]
    },
    options: {
        responsive: true, cutout: '66%',
        plugins: { legend:{ position:'bottom', labels:{ boxWidth:11, padding:12, font:{size:11}, color:'#64748b' } } }
    }
});

// Barangay Distribution - Horizontal Bar
new Chart(document.getElementById('barangayChart'), {
    type: 'bar',
    data: {
        labels: <?= $barangay_labels ?>,
        datasets: [{
            label: 'Applications',
            data: <?= $barangay_counts ?>,
            backgroundColor: 'rgba(26,86,219,.12)',
            borderColor: '#1a56db',
            borderWidth: 2, borderRadius: 7, borderSkipped: false
        }]
    },
    options: {
        indexAxis: 'y', responsive: true,
        plugins: { legend:{ display:false } },
        scales: {
            x: { beginAtZero:true, ticks:{ stepSize:1, color:'#94a3b8' }, grid:{ color:'rgba(0,0,0,.04)' }, border:{ display:false } },
            y: { grid:{ display:false }, border:{ display:false }, ticks:{ color:'#94a3b8' } }
        }
    }
});

function exportChart(id) {
    const url = document.getElementById(id).toDataURL();
    const a = document.createElement('a');
    a.href = url; a.download = id + '.png'; a.click();
}
</script>
</body>
</html>
