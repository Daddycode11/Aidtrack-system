<?php
// client/dashboard.php
require_once __DIR__ . '/../helpers.php';
require_client();

$uid = $_SESSION['user']['id'];
$user_name = $_SESSION['user']['name'] ?? 'Client';

// --- Date Range Filter ---
$date_from = $_GET['from'] ?? '';
$date_to   = $_GET['to']   ?? '';
$has_date_filter = $date_from || $date_to;

$date_where = '';
$date_params = [$uid];
$date_types  = 'i';
if ($date_from) { $date_where .= " AND date_of_request >= ?"; $date_params[] = $date_from; $date_types .= 's'; }
if ($date_to)   { $date_where .= " AND date_of_request <= ?"; $date_params[] = $date_to;   $date_types .= 's'; }

// --- Fetch applications ---
$apps = [];
$stmt = $mysqli->prepare("SELECT * FROM applications WHERE user_id=?" . $date_where . " ORDER BY created_at DESC");
if ($stmt) { $stmt->bind_param($date_types, ...$date_params); $stmt->execute(); $apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); }

// --- Fetch recent messages ---
$msgs = [];
$stmt = $mysqli->prepare("SELECT * FROM messages WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
if ($stmt) { $stmt->bind_param('i', $uid); $stmt->execute(); $msgs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); }

// --- Stats ---
$total_apps = count($apps);
$approved   = count(array_filter($apps, fn($a) => $a['status'] === 'approved'));
$pending    = count(array_filter($apps, fn($a) => $a['status'] === 'pending'));
$rejected   = count(array_filter($apps, fn($a) => $a['status'] === 'rejected'));

// Beneficiaries no longer specify an amount when applying — this now reflects
// what the admin has actually granted across approved applications.
$total_granted = array_sum(array_column(array_filter($apps, fn($a) => $a['status'] === 'approved'), 'amount_granted'));

// --- Chart: status counts ---
$chart_labels = json_encode(['Approved','Pending','Rejected']);
$chart_data   = json_encode([$approved, $pending, $rejected]);

// --- Chart: monthly trend ---
$monthly = array_fill(1, 12, 0);
foreach ($apps as $a) {
    $m = (int)date('n', strtotime($a['created_at']));
    $monthly[$m]++;
}
$month_labels = json_encode(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']);
$month_data   = json_encode(array_values($monthly));

// Partials
$active_page   = 'dashboard';
$page_title    = 'Dashboard';
$page_subtitle = 'Dashboard';
$colors = ['#e87400','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'];

$hour = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$first_name = explode(' ', $user_name)[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — AIDTRACK</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/client.css">
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <div class="page-sub">Here's a summary of your aid applications — <?= date('l, F j, Y') ?></div>
            </div>
            <div class="page-actions" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <form method="get" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                    <input type="date" name="from" value="<?= htmlspecialchars($date_from) ?>" style="font-family:inherit;font-size:.76rem;padding:6px 10px;border:1.5px solid var(--border);border-radius:8px;outline:none;color:var(--navy);">
                    <span style="font-size:.72rem;color:var(--muted);">to</span>
                    <input type="date" name="to" value="<?= htmlspecialchars($date_to) ?>" style="font-family:inherit;font-size:.76rem;padding:6px 10px;border:1.5px solid var(--border);border-radius:8px;outline:none;color:var(--navy);">
                    <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="filter" style="width:12px;height:12px"></i> Filter</button>
                    <?php if ($has_date_filter): ?>
                    <a href="dashboard.php" class="btn btn-outline btn-sm"><i data-lucide="x" style="width:12px;height:12px"></i></a>
                    <?php endif; ?>
                </form>
                <a href="apply.php" class="btn btn-primary btn-sm">
                    <i data-lucide="file-plus" style="width:13px;height:13px"></i> New Request
                </a>
            </div>
        </div>
    </div>

    <!-- Mobile swipe tab labels -->
    <div class="swipe-container">
        <div class="swipe-tabs">
            <button class="swipe-tab active">
                <i data-lucide="bar-chart-2" style="width:13px;height:13px"></i> Overview
            </button>
            <button class="swipe-tab">
                <i data-lucide="clipboard-list" style="width:13px;height:13px"></i> Applications
            </button>
            <button class="swipe-tab">
                <i data-lucide="bell" style="width:13px;height:13px"></i> Notifications
            </button>
        </div>

        <div class="swipe-sections">

            <!-- ─── SLIDE 1: Overview (Stats + Charts) ─── -->
            <div class="swipe-section">

                <!-- Stat Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div>
                            <div class="sc-label">Total Requests</div>
                            <div class="sc-value"><?= $total_apps ?></div>
                            <div class="sc-sub"><i data-lucide="file-text" style="width:11px;height:11px"></i> All time</div>
                        </div>
                        <div class="sc-icon sci-orange"><i data-lucide="file-text" style="width:17px;height:17px"></i></div>
                    </div>
                    <div class="stat-card">
                        <div>
                            <div class="sc-label">Approved</div>
                            <div class="sc-value" style="color:var(--green)"><?= $approved ?></div>
                            <div class="sc-sub"><i data-lucide="check-circle" style="width:11px;height:11px;color:var(--green)"></i> Granted</div>
                        </div>
                        <div class="sc-icon sci-green"><i data-lucide="check-circle" style="width:17px;height:17px"></i></div>
                    </div>
                    <div class="stat-card">
                        <div>
                            <div class="sc-label">Pending</div>
                            <div class="sc-value" style="color:var(--yellow)"><?= $pending ?></div>
                            <div class="sc-sub"><i data-lucide="hourglass" style="width:11px;height:11px;color:var(--yellow)"></i> Awaiting review</div>
                        </div>
                        <div class="sc-icon sci-yellow"><i data-lucide="hourglass" style="width:17px;height:17px"></i></div>
                    </div>
                    <div class="stat-card">
                        <div>
                            <div class="sc-label">Total Granted</div>
                            <div class="sc-value" style="color:var(--green)">₱<?= number_format($total_granted, 2) ?></div>
                            <div class="sc-sub"><i data-lucide="wallet" style="width:11px;height:11px;color:var(--green)"></i> Across approved requests</div>
                        </div>
                        <div class="sc-icon sci-green"><i data-lucide="wallet" style="width:17px;height:17px"></i></div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="grid-2">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <div class="card-title-icon cti-orange"><i data-lucide="pie-chart" style="width:14px;height:14px"></i></div>
                                Application Status
                            </div>
                        </div>
                        <div class="chart-wrap"><canvas id="statusChart"></canvas></div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <div class="card-title-icon cti-blue"><i data-lucide="trending-up" style="width:14px;height:14px"></i></div>
                                Monthly Requests
                            </div>
                        </div>
                        <div class="chart-wrap"><canvas id="monthlyChart"></canvas></div>
                    </div>
                </div>

            </div><!-- /swipe-section 1 -->

            <!-- ─── SLIDE 2: Recent Applications ─── -->
            <div class="swipe-section">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <div class="card-title-icon cti-green"><i data-lucide="clipboard-list" style="width:14px;height:14px"></i></div>
                            Recent Applications
                        </div>
                        <a href="aid_history.php" style="font-size:.75rem;font-weight:600;color:var(--brand);display:flex;align-items:center;gap:4px;">
                            View all <i data-lucide="arrow-right" style="width:12px;height:12px"></i>
                        </a>
                    </div>
                    <div class="card-body no-pad scroll">
                        <?php if (empty($apps)): ?>
                        <div class="empty-state">
                            <div class="empty-icon"><i data-lucide="inbox" style="width:20px;height:20px"></i></div>
                            <div class="empty-text">No applications yet. <a href="apply.php" style="color:var(--brand);font-weight:600;">Submit your first request.</a></div>
                        </div>
                        <?php else: ?>
                        <div class="tbl-wrap">
                            <table>
                                <thead><tr><th>Type</th><th>Amount Granted</th><th>Date</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach (array_slice($apps, 0, 6) as $a):
                                    $s = strtolower($a['status']);
                                    $bc = $s === 'approved' ? 'b-approved' : ($s === 'pending' ? 'b-pending' : ($s === 'rejected' ? 'b-rejected' : 'b-cancelled'));
                                ?>
                                <tr>
                                    <td style="font-weight:700;"><?= ucfirst($a['type']) ?></td>
                                    <td style="font-family:monospace;font-weight:700;font-size:.8rem;">
                                        <?= $s === 'approved' ? '₱' . number_format($a['amount_granted'] ?? 0, 2) : '<span style="color:var(--muted);font-weight:500;">—</span>' ?>
                                    </td>
                                    <td style="color:var(--muted);white-space:nowrap;"><?= date('M d, Y', strtotime($a['date_of_request'])) ?></td>
                                    <td><span class="badge <?= $bc ?>"><?= ucfirst($s) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div><!-- /swipe-section 2 -->

            <!-- ─── SLIDE 3: Recent Notifications ─── -->
            <div class="swipe-section">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <div class="card-title-icon cti-yellow"><i data-lucide="bell" style="width:14px;height:14px"></i></div>
                            Recent Notifications
                        </div>
                        <a href="messages.php" style="font-size:.75rem;font-weight:600;color:var(--brand);">View all</a>
                    </div>
                    <div class="card-body no-pad scroll">
                        <?php if (empty($msgs)): ?>
                        <div class="empty-state">
                            <div class="empty-icon"><i data-lucide="bell-off" style="width:20px;height:20px"></i></div>
                            <div class="empty-text">No notifications yet.</div>
                        </div>
                        <?php else: ?>
                        <div class="msg-list">
                            <?php foreach ($msgs as $i => $m):
                                $mc = $colors[$i % count($colors)];
                                $mi = strtoupper(substr($m['sender'], 0, 2));
                                $unread = empty($m['is_read']);
                            ?>
                            <div class="msg-item <?= $unread ? 'unread' : '' ?>">
                                <div class="msg-av" style="background:<?= $mc ?>"><?= $mi ?></div>
                                <div style="flex:1;min-width:0;">
                                    <div class="msg-sender"><?= htmlspecialchars(ucfirst($m['sender'])) ?></div>
                                    <div class="msg-text"><?= htmlspecialchars(mb_substr($m['message'], 0, 70)) ?><?= mb_strlen($m['message']) > 70 ? '...' : '' ?></div>
                                    <div class="msg-time"><?= date('M d, Y h:i A', strtotime($m['created_at'])) ?></div>
                                </div>
                                <?php if ($unread): ?><div class="msg-dot"></div><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div><!-- /swipe-section 3 -->

        </div><!-- /swipe-sections -->
    </div><!-- /swipe-container -->

</main>

<script src="partials/client.js"></script>
<script>
Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
Chart.defaults.font.size = 12;

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: <?= $chart_labels ?>,
        datasets: [{
            data: <?= $chart_data ?>,
            backgroundColor: ['#16a34a','#ca8a04','#dc2626'],
            borderWidth: 3, borderColor: '#fff', hoverOffset: 8
        }]
    },
    options: { responsive:true, cutout:'66%', plugins:{ legend:{ position:'bottom', labels:{ boxWidth:11, padding:12, font:{size:11}, color:'#64748b' } } } }
});

new Chart(document.getElementById('monthlyChart'), {
    type: 'line',
    data: {
        labels: <?= $month_labels ?>,
        datasets: [{
            label: 'Requests',
            data: <?= $month_data ?>,
            borderColor: '#e87400',
            backgroundColor: 'rgba(232,116,0,.08)',
            fill: true, tension: .3, borderWidth: 2, pointRadius: 3, pointBackgroundColor: '#e87400'
        }]
    },
    options: {
        responsive:true,
        plugins:{ legend:{ display:false } },
        scales:{
            y:{ beginAtZero:true, ticks:{ stepSize:1, color:'#94a3b8' }, grid:{ color:'rgba(0,0,0,.04)' }, border:{ display:false } },
            x:{ grid:{ display:false }, border:{ display:false }, ticks:{ color:'#94a3b8' } }
        }
    }
});
</script>
</body>
</html>