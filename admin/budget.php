<?php
// admin/budget.php — Budget Management (Super Admin only)
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../helpers/table_filters.php';
require_super_admin();

// --- Handle Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_budget') {
    if (!csrf_verify()) { header('Location: budget.php?err=csrf'); exit; }
    $budget_id = (int)($_POST['budget_id'] ?? 0);
    $allocated = floatval($_POST['allocated_budget'] ?? 0);
    if ($budget_id && $allocated >= 0) {
        $stmt = $mysqli->prepare("UPDATE budgets SET allocated_budget=? WHERE id=?");
        $stmt->bind_param('di', $allocated, $budget_id);
        $stmt->execute();
        $stmt->close();
        log_audit($mysqli, 'update_budget', 'budget', $budget_id,
            "Set allocated budget to ₱" . number_format($allocated, 2));
        header('Location: budget.php?toast=updated'); exit;
    }
}

// --- Calculate used budget from applications ---
$used_by_type = [];
$res = $mysqli->query("SELECT type, COALESCE(SUM(amount_released),0) AS used FROM applications WHERE status='approved' GROUP BY type");
if ($res) while ($r = $res->fetch_assoc()) $used_by_type[$r['type']] = floatval($r['used']);

$budgetTypes = array_column($mysqli->query('SELECT DISTINCT type FROM budgets ORDER BY type')->fetch_all(MYSQLI_ASSOC), 'type');
$typeOptions = array_combine($budgetTypes, array_map('ucfirst', $budgetTypes)) ?: [];
$yearRows = $mysqli->query('SELECT DISTINCT YEAR(updated_at) AS year FROM budgets ORDER BY year DESC')->fetch_all(MYSQLI_ASSOC);
$yearOptions = [];
foreach ($yearRows as $yearRow) if ($yearRow['year']) $yearOptions[(string)$yearRow['year']] = (string)$yearRow['year'];
$monthOptions = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
$table = table_filter_query($mysqli, [
    'from_sql'=>'FROM budgets',
    'select_sql'=>'id, type, allocated_budget, used_budget, max_amount, reapply_interval_months, updated_at',
    'filters'=>[
        'year'=>['kind'=>'select','label'=>'Fiscal year (last updated)','options'=>$yearOptions,'sql'=>'YEAR(updated_at)'],
        'month'=>['kind'=>'select','label'=>'Month (last updated)','options'=>$monthOptions,'sql'=>'MONTH(updated_at)'],
        'category'=>['kind'=>'select','label'=>'Category','options'=>$typeOptions,'sql'=>'type'],
        'status'=>['kind'=>'select','label'=>'Budget status','options'=>['available'=>'Available','depleted'=>'Depleted'],'expressions'=>['available'=>'allocated_budget > used_budget','depleted'=>'allocated_budget <= used_budget']],
    ],
    'sort'=>['category'=>'type','allocated'=>'allocated_budget','used'=>'used_budget','updated'=>'updated_at'],
    'default_sort'=>'category','default_dir'=>'ASC','per_page'=>25,
]);
$budgets = $table['rows'];

// Update used_budget column to reflect actuals
foreach ($budgets as &$b) {
    $actual_used = $used_by_type[$b['type']] ?? 0;
    if (floatval($b['used_budget']) != $actual_used) {
        $stmt = $mysqli->prepare("UPDATE budgets SET used_budget=? WHERE id=?");
        $stmt->bind_param('di', $actual_used, $b['id']);
        $stmt->execute();
        $stmt->close();
        $b['used_budget'] = $actual_used;
    }
}
unset($b);

// --- Totals ---
$total_allocated = array_sum(array_column($budgets, 'allocated_budget'));
$total_used      = array_sum(array_column($budgets, 'used_budget'));
$total_remaining = $total_allocated - $total_used;

// Page config
$active_page   = 'budget';
$page_title    = 'Budget Management';
$page_subtitle = 'Budget';
$pending_aids  = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0] ?? 0);

$type_colors = [
    'medical' => ['#dc2626','var(--red-lt)'],
    'burial'  => ['#7c3aed','#f5f3ff'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Budget Management — AIDTRACK Admin</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/admin.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
.budget-summary { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:18px; }
.bs-card { background:var(--white); border:1px solid var(--border); border-radius:var(--r); padding:18px; display:flex; align-items:center; gap:14px; }
.bs-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.bs-n { font-size:1.6rem; font-weight:800; line-height:1; }
.bs-l { font-size:.68rem; font-weight:600; color:var(--muted); margin-top:4px; }

.budget-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:14px; margin-bottom:18px; }
.bg-card { background:var(--white); border:1px solid var(--border); border-radius:var(--r); padding:18px 20px; transition:box-shadow .18s; }
.bg-card:hover { box-shadow:var(--sh-md); }
.bg-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
.bg-type { display:flex; align-items:center; gap:10px; }
.bg-icon { width:36px; height:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.bg-name { font-size:.88rem; font-weight:800; color:var(--navy); }
.bg-edit { padding:4px 10px; border:1px solid var(--border); border-radius:6px; font-family:inherit; font-size:.72rem; font-weight:700; color:var(--brand); background:transparent; cursor:pointer; }
.bg-edit:hover { background:var(--brand-lt); }
.bg-row { display:flex; justify-content:space-between; font-size:.76rem; margin-bottom:6px; }
.bg-row-label { color:var(--muted); font-weight:600; }
.bg-row-value { font-weight:700; color:var(--navy); font-family:monospace; }
.bg-bar { height:8px; background:var(--border); border-radius:99px; overflow:hidden; margin-top:10px; }
.bg-bar-fill { height:100%; border-radius:99px; transition:width .3s ease; }
.bg-pct { font-size:.68rem; font-weight:700; margin-top:6px; text-align:right; }

.toast { position:fixed; bottom:24px; right:24px; z-index:999; display:flex; align-items:center; gap:10px; padding:12px 18px; border-radius:10px; font-size:.83rem; font-weight:600; box-shadow:0 8px 28px rgba(0,0,0,.15); background:var(--green-lt); color:var(--green); border:1px solid rgba(22,163,74,.2); animation:toastIn .3s ease,toastOut .4s ease 3s forwards; }
@keyframes toastIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
@keyframes toastOut{from{opacity:1}to{opacity:0;pointer-events:none}}

@media(max-width:768px) { .budget-summary { grid-template-columns:1fr; } }
</style>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<?php if (!empty($_GET['toast'])): ?>
<div class="toast"><i data-lucide="check-circle" style="width:16px;height:16px"></i> Budget updated successfully.</div>
<?php endif; ?>

<main class="main">

    <div class="page-header">
        <div class="page-header-top">
            <div>
                <div class="page-title">Budget Management</div>
                <div class="page-sub">Manage and monitor allocated budgets for each assistance type.</div>
            </div>
        </div>
    </div>

    <!-- Summary -->
    <div class="budget-summary">
        <div class="bs-card">
            <div class="bs-icon" style="background:var(--brand-lt);color:var(--brand);"><i data-lucide="wallet" style="width:20px;height:20px"></i></div>
            <div><div class="bs-n">₱<?= number_format($total_allocated, 2) ?></div><div class="bs-l">Total Allocated</div></div>
        </div>
        <div class="bs-card">
            <div class="bs-icon" style="background:var(--red-lt);color:var(--red);"><i data-lucide="trending-down" style="width:20px;height:20px"></i></div>
            <div><div class="bs-n" style="color:var(--red);">₱<?= number_format($total_used, 2) ?></div><div class="bs-l">Total Used</div></div>
        </div>
        <div class="bs-card">
            <div class="bs-icon" style="background:var(--green-lt);color:var(--green);"><i data-lucide="piggy-bank" style="width:20px;height:20px"></i></div>
            <div><div class="bs-n" style="color:var(--green);">₱<?= number_format($total_remaining, 2) ?></div><div class="bs-l">Remaining</div></div>
        </div>
    </div>

    <!-- Budget Cards -->
    <?php include 'partials/filter_bar.php'; ?>
    <div class="budget-grid">
        <?php foreach ($budgets as $b):
            $alloc = floatval($b['allocated_budget']);
            $used  = floatval($b['used_budget']);
            $rem   = $alloc - $used;
            $pct   = $alloc > 0 ? round(($used / $alloc) * 100) : 0;
            $tc    = $type_colors[$b['type']] ?? ['#64748b','var(--bg)'];
            $bar_color = $pct >= 90 ? 'var(--red)' : ($pct >= 70 ? 'var(--yellow)' : $tc[0]);
        ?>
        <div class="bg-card">
            <div class="bg-top">
                <div class="bg-type">
                    <div class="bg-icon" style="background:<?= $tc[1] ?>;color:<?= $tc[0] ?>;">
                        <i data-lucide="<?= match($b['type']) { 'medical'=>'heart-pulse', 'burial'=>'flower-2', default=>'circle' } ?>" style="width:16px;height:16px"></i>
                    </div>
                    <div class="bg-name"><?= ucfirst($b['type']) ?></div>
                </div>
                <button class="bg-edit" onclick="openBudgetModal(<?= $b['id'] ?>, '<?= ucfirst($b['type']) ?>', <?= $alloc ?>)">
                    <i data-lucide="pencil" style="width:10px;height:10px"></i> Edit
                </button>
            </div>
            <div class="bg-row"><span class="bg-row-label">Allocated</span><span class="bg-row-value">₱<?= number_format($alloc, 2) ?></span></div>
            <div class="bg-row"><span class="bg-row-label">Used</span><span class="bg-row-value" style="color:var(--red);">₱<?= number_format($used, 2) ?></span></div>
            <div class="bg-row"><span class="bg-row-label">Remaining</span><span class="bg-row-value" style="color:var(--green);">₱<?= number_format($rem, 2) ?></span></div>
            <div class="bg-bar"><div class="bg-bar-fill" style="width:<?= min($pct, 100) ?>%;background:<?= $bar_color ?>;"></div></div>
            <div class="bg-pct" style="color:<?= $bar_color ?>;"><?= $pct ?>% utilized</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php include 'partials/table_pager.php'; ?>

    <?php if (empty($budgets)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="wallet" style="width:20px;height:20px"></i></div>
                <div class="empty-text">No budget entries found. Run the SQL to insert default budget rows.</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</main>

<!-- Edit Budget Modal -->
<div class="modal-overlay" id="budgetModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">
                <div class="card-title-icon cti-blue" style="width:28px;height:28px;"><i data-lucide="wallet" style="width:14px;height:14px"></i></div>
                Edit Budget
            </div>
            <button class="modal-close" onclick="closeBudgetModal()"><i data-lucide="x" style="width:14px;height:14px"></i></button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <div class="modal-body">
                <p class="modal-desc" id="budgetDesc">Update the allocated budget.</p>
                <input type="hidden" name="action" value="update_budget">
                <input type="hidden" name="budget_id" id="budgetId">
                <div class="form-group">
                    <label>Allocated Budget (₱)</label>
                    <input type="number" name="allocated_budget" id="budgetAmount" step="0.01" min="0" required
                        style="width:100%;font-family:inherit;font-size:.95rem;font-weight:700;color:var(--navy);background:var(--bg);border:1.5px solid var(--border);border-radius:8px;padding:10px 14px;outline:none;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeBudgetModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="save" style="width:13px;height:13px"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<script src="partials/admin.js"></script>
<script>
function openBudgetModal(id, typeName, currentAmount) {
    document.getElementById('budgetId').value = id;
    document.getElementById('budgetAmount').value = currentAmount.toFixed(2);
    document.getElementById('budgetDesc').textContent = `Update the allocated budget for ${typeName} assistance.`;
    document.getElementById('budgetModal').classList.add('open');
}
function closeBudgetModal() { document.getElementById('budgetModal').classList.remove('open'); }
document.getElementById('budgetModal').addEventListener('click', function(e) { if (e.target === this) closeBudgetModal(); });
</script>
</body>
</html>
