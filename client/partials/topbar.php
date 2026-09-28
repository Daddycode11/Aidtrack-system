<?php
// client/partials/topbar.php
$page_title    = $page_title    ?? 'Dashboard';
$page_subtitle = $page_subtitle ?? '';
$user_name     = $_SESSION['user']['name'] ?? 'Client';
?>

<header class="topbar">
    <div class="topbar-left">
        <button class="hamburger" onclick="toggleSidebar()">
            <i data-lucide="menu" style="width:18px;height:18px"></i>
        </button>
        <div>
            <div class="topbar-title"><?= htmlspecialchars($page_title) ?></div>
            <?php if ($page_subtitle): ?>
            <div class="topbar-breadcrumb">
                <span>AIDTRACK</span>
                <i data-lucide="chevron-right" style="width:13px;height:13px"></i>
                <span style="color:var(--brand);font-weight:600;"><?= htmlspecialchars($page_subtitle) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="topbar-right">
        <a href="messages.php" class="tb-icon-btn" title="Notifications">
            <i data-lucide="bell" style="width:15px;height:15px"></i>
            <?php if (($unread_count ?? 0) > 0): ?><div class="notif-dot"></div><?php endif; ?>
        </a>
        <div class="tb-icon-btn" style="background:var(--brand);border-color:var(--brand);color:#fff;border-radius:8px;width:auto;padding:0 12px;gap:6px;font-size:.76rem;font-weight:700;">
            <i data-lucide="user" style="width:13px;height:13px"></i>
            <?= htmlspecialchars(explode(' ', $user_name)[0]) ?>
        </div>
    </div>
</header>
