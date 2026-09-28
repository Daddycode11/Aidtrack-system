<?php
// admin/beneficiaries.php
require_once __DIR__ . '/../helpers.php';
require_admin();

// --- Filter ---
$search  = $_GET['search']   ?? '';
$f_town  = $_GET['barangay'] ?? ''; // note: kept as 'barangay' GET/column name to avoid breaking existing DB schema/links

$where = []; $params = []; $types = '';
if ($search) { $where[] = '(u.name LIKE ? OR u.phone LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $types .= 'ss'; }
if ($f_town) { $where[] = 'u.barangay = ?'; $params[] = $f_town; $types .= 's'; }
$where_sql = $where ? 'WHERE '.implode(' AND ', $where) : '';

// Beneficiaries query
$beneficiaries = [];
$stmt = $mysqli->prepare("
    SELECT u.id, u.name, u.phone, u.barangay,
           COUNT(a.id)                                   AS total_aids,
           SUM(a.status='approved')                      AS approved_aids,
           MAX(a.created_at)                             AS last_request
    FROM users u
    LEFT JOIN applications a ON a.user_id = u.id
    WHERE u.role = 'client'" . ($where ? ' AND ' . implode(' AND ', $where) : '') . "
    GROUP BY u.id ORDER BY u.name ASC
");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$beneficiaries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Static Town/Bayan list (hindi na barangay ng Calintaan lang, mga karatig-bayan) ---
$town_list = [
    'Calintaan',
    'Magsaysay',
    'Rizal',
    'San Jose',
];
sort($town_list);

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
                <span class="count-pill"><?= count($beneficiaries) ?> total</span>
                <a href="#" class="btn btn-primary btn-sm">
                    <i data-lucide="file-down" style="width:13px;height:13px"></i> Export
                </a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-blue"><i data-lucide="users" style="width:14px;height:14px"></i></div>
                Beneficiary List
                <span class="count-pill"><?= count($beneficiaries) ?></span>
            </div>
        </div>

        <!-- Filter -->
        <form method="get" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:13px 18px;border-bottom:1px solid var(--border);background:var(--bg);">
            <i data-lucide="search" style="width:14px;height:14px;color:var(--muted)"></i>
            <input type="text" name="search" placeholder="Search by name or phone…"
                   value="<?= htmlspecialchars($search) ?>"
                   style="flex:1;min-width:160px;font-family:inherit;font-size:.8rem;color:var(--navy);background:var(--white);border:1.5px solid var(--border);border-radius:8px;padding:7px 12px;outline:none;">
            <select name="barangay"
                    style="font-family:inherit;font-size:.8rem;color:var(--navy);background:var(--white);border:1.5px solid var(--border);border-radius:8px;padding:7px 12px;outline:none;">
                <option value="">All Towns</option>
                <?php foreach ($town_list as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>" <?= $f_town===$t?'selected':''?>>
                    <?= htmlspecialchars($t) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">
                <i data-lucide="filter" style="width:13px;height:13px"></i> Filter
            </button>
            <?php if ($search || $f_town): ?>
            <a href="beneficiaries.php" style="font-size:.76rem;font-weight:600;color:var(--muted);">
                <i data-lucide="x" style="width:12px;height:12px;vertical-align:middle"></i> Clear
            </a>
            <?php endif; ?>
        </form>

        <div class="card-body no-pad">
            <?php if (empty($beneficiaries)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="users" style="width:20px;height:20px"></i></div>
                <div class="empty-text">No beneficiaries match your search.</div>
            </div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table data-paginate="10">
                    <thead>
                        <tr>
                            <th>Beneficiary</th>
                            <th>Phone</th>
                            <th>Town</th>
                            <th>Total Aids</th>
                            <th>Approved</th>
                            <th>Last Request</th>
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
            <?php endif; ?>
        </div>
    </div>

</main>

<script src="partials/admin.js"></script>
</body>