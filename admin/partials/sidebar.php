<?php
// admin/partials/sidebar.php
$admin_name = $_SESSION['user']['name'] ?? 'Admin';
$admin_role = $_SESSION['user']['role'] ?? 'admin';
$is_sa_nav  = ($admin_role === 'super_admin');

$pending_badge = isset($pending_aids)
    ? (int)$pending_aids
    : (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0] ?? 0);

$sa_approval_badge = $is_sa_nav ? get_pending_approvals_count($mysqli) : 0;

// Admin's own pending requests
$my_pending_badge = 0;
if (!$is_sa_nav) {
    $uid = $_SESSION['user']['id'] ?? 0;
    $res = $mysqli->query("SELECT COUNT(*) FROM approval_requests WHERE requested_by=$uid AND status='pending'");
    $my_pending_badge = $res ? (int)$res->fetch_row()[0] : 0;
}

// ── Navigation structure ──────────────────────────────────────
$nav_items = [
    [
        'section' => 'Main',
        'links' => [
            ['key'=>'dashboard',    'href'=>'dashboard.php',    'icon'=>'layout-dashboard', 'label'=>'Dashboard'],
            ['key'=>'applications', 'href'=>'applications.php', 'icon'=>'clipboard-list',   'label'=>'Applications', 'badge'=>$pending_badge],
            ['key'=>'messages',     'href'=>'messages.php',     'icon'=>'message-square',   'label'=>'Messages'],
        ]
    ],
    [
        'section' => 'Records',
        'links' => [
            ['key'=>'beneficiaries', 'href'=>'beneficiaries.php', 'icon'=>'users',    'label'=>'Beneficiaries'],
            ['key'=>'aid_history',   'href'=>'aid_history.php',   'icon'=>'history',  'label'=>'Aid History'],
            $is_sa_nav ? ['key'=>'users', 'href'=>'user.php', 'icon'=>'user-cog', 'label'=>'Users'] : null,
        ]
    ],
    [
        'section' => 'Workflow',
        'links' => array_filter([
            // Admin: see own requests
            !$is_sa_nav ? ['key'=>'approval_requests', 'href'=>'approval_requests.php', 'icon'=>'clock',         'label'=>'My Requests',    'badge'=>$my_pending_badge] : null,
            // SA: see the full review queue
            $is_sa_nav  ? ['key'=>'super_admin_approvals', 'href'=>'super_admin_approvals.php', 'icon'=>'shield-alert', 'label'=>'Approval Queue', 'badge'=>$sa_approval_badge] : null,
            $is_sa_nav  ? ['key'=>'approval_requests', 'href'=>'approval_requests.php', 'icon'=>'list', 'label'=>'All Requests'] : null,
        ]),
    ],
    [
        'section' => 'Management',
        'links' => [
            $is_sa_nav ? ['key'=>'audit_trail', 'href'=>'audit_trail.php', 'icon'=>'shield-check', 'label'=>'Audit Trail'] : null,
            ['key'=>'analytics',   'href'=>'analytics.php',   'icon'=>'bar-chart-3',  'label'=>'Analytics'],
            ['key'=>'export',      'href'=>'export_reports.php', 'icon'=>'file-down', 'label'=>'Export Reports'],
        ]
    ],
];

// Budget visible to SA only
if ($is_sa_nav) {
    $nav_items[3]['links'][] = ['key'=>'budget', 'href'=>'budget.php', 'icon'=>'wallet', 'label'=>'Budget'];
}

// System section
$system_links = [
    ['key'=>'settings', 'href'=>'settings.php', 'icon'=>'settings', 'label'=>'Settings'],
];
if ($is_sa_nav) {
    $system_links[] = ['key'=>'super_admin', 'href'=>'super_admin.php', 'icon'=>'shield', 'label'=>'Super Admin'];
}
$nav_items[] = ['section'=>'System', 'links'=>$system_links];
?>

<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img src="../assets/images/AIDTRACK-logo.png" alt="AIDTRACK">
        <div class="sidebar-logo-text">
            <?= $is_sa_nav ? 'Super Admin' : 'Admin Panel' ?>
        </div>
    </div>

    <?php if ($is_sa_nav && $sa_approval_badge > 0): ?>
    <a href="super_admin_approvals.php" style="display:flex;align-items:center;gap:8px;margin:10px 14px 4px;padding:9px 12px;background:#f5f3ff;border:1px solid rgba(124,58,237,.2);border-radius:9px;font-size:.78rem;font-weight:700;color:#7c3aed;text-decoration:none;transition:background .15s;">
        <i data-lucide="shield-alert" style="width:14px;height:14px;flex-shrink:0;"></i>
        <?= $sa_approval_badge ?> pending approval<?= $sa_approval_badge > 1 ? 's' : '' ?>
        <i data-lucide="arrow-right" style="width:12px;height:12px;margin-left:auto;"></i>
    </a>
    <?php endif; ?>

    <nav class="sidebar-nav">
        <?php foreach ($nav_items as $group):
            if (empty($group['links'])) continue;
        ?>
            <div class="nav-section-label"><?= $group['section'] ?></div>
            <?php foreach ($group['links'] as $link):
                if (!$link) continue;
                $is_active = ($active_page ?? '') === $link['key'];
            ?>
            <a href="<?= $link['href'] ?>" class="nav-link <?= $is_active ? 'active' : '' ?>">
                <i data-lucide="<?= $link['icon'] ?>" class="nl-icon" style="width:16px;height:16px"></i>
                <?= $link['label'] ?>
                <?php if (!empty($link['badge']) && $link['badge'] > 0): ?>
                    <span class="nav-badge"><?= $link['badge'] ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="su-avatar" style="<?= $is_sa_nav ? 'background:#7c3aed;' : '' ?>"><?= strtoupper(substr($admin_name, 0, 2)) ?></div>
            <div>
                <div class="su-name"><?= htmlspecialchars($admin_name) ?></div>
                <div class="su-role" style="<?= $is_sa_nav ? 'color:#a78bfa;' : '' ?>">
                    <?= $is_sa_nav ? 'Super Admin' : str_replace('_', ' ', $admin_role) ?>
                </div>
            </div>
        </div>
        <a href="../logout.php" class="btn-logout">
            <i data-lucide="log-out" style="width:15px;height:15px"></i>
            Sign Out
        </a>
    </div>
</aside>
