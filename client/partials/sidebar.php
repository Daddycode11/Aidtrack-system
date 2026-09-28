<?php
// client/partials/sidebar.php
$user_name = $_SESSION['user']['name'] ?? 'Client';
$user_role = $_SESSION['user']['role'] ?? 'client';
$user_barangay = $_SESSION['user']['barangay'] ?? '';

// Counts for badges
$uid = $_SESSION['user']['id'] ?? 0;
$pending_count = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE user_id=$uid AND status='pending'")->fetch_row()[0] ?? 0);
$unread_count  = (int)($mysqli->query("SELECT COUNT(*) FROM messages WHERE user_id=$uid AND is_read=0")->fetch_row()[0] ?? 0);

$nav_items = [
    [
        'section' => 'Menu',
        'links' => [
            ['key'=>'dashboard', 'href'=>'dashboard.php', 'icon'=>'layout-dashboard', 'label'=>'Dashboard'],
            ['key'=>'apply',     'href'=>'apply.php',     'icon'=>'file-plus',        'label'=>'New Request'],
        ]
    ],
    [
        'section' => 'Records',
        'links' => [
            ['key'=>'aid_history', 'href'=>'aid_history.php', 'icon'=>'history',   'label'=>'Aid History', 'badge'=>$pending_count],
            ['key'=>'messages',    'href'=>'messages.php',    'icon'=>'bell',      'label'=>'Notifications', 'badge'=>$unread_count],
        ]
    ],
    [
        'section' => 'Support',
        'links' => [
            ['key'=>'help', 'href'=>'help.php', 'icon'=>'help-circle', 'label'=>'Help / FAQ'],
        ]
    ],
    [
        'section' => 'Account',
        'links' => [
            ['key'=>'profile', 'href'=>'profile.php', 'icon'=>'user-cog', 'label'=>'My Profile'],
        ]
    ],
];
?>

<!-- SIDEBAR OVERLAY -->
<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img src="../assets/images/AIDTRACK-logo.png" alt="AIDTRACK">
        <div class="sidebar-logo-text">Client Portal</div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($nav_items as $group): ?>
            <div class="nav-section-label"><?= $group['section'] ?></div>
            <?php foreach ($group['links'] as $link):
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
            <div class="su-avatar"><?= strtoupper(substr($user_name, 0, 2)) ?></div>
            <div>
                <div class="su-name"><?= htmlspecialchars($user_name) ?></div>
                <div class="su-role"><?= htmlspecialchars($user_barangay ?: 'Beneficiary') ?></div>
            </div>
        </div>
        <a href="../logout.php" class="btn-logout">
            <i data-lucide="log-out" style="width:15px;height:15px"></i>
            Sign Out
        </a>
    </div>
</aside>
