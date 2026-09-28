<?php
// admin/partials/topbar.php
$page_title    = $page_title    ?? 'Dashboard';
$page_subtitle = $page_subtitle ?? '';

// --- Dynamic notification data ---
$pending_apps = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0] ?? 0);
$unread_msgs  = (int)($mysqli->query("SELECT COUNT(*) FROM messages WHERE is_read=0")->fetch_row()[0] ?? 0);

// Recent notifications
$notif_items = [];

// Recent pending applications (last 5)
$res = $mysqli->query("
    SELECT a.id, u.name, a.type, a.created_at
    FROM applications a JOIN users u ON a.user_id = u.id
    WHERE a.status='pending'
    ORDER BY a.created_at DESC LIMIT 5
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $notif_items[] = [
            'icon'  => 'clipboard-list',
            'color' => 'var(--yellow)',
            'bg'    => 'var(--yellow-lt)',
            'title' => htmlspecialchars($row['name']) . ' — ' . ucfirst($row['type']) . ' aid request',
            'time'  => $row['created_at'],
            'href'  => 'applications.php?status=pending',
            'type'  => 'request',
        ];
    }
}

// Recent unread messages (last 5)
$res = $mysqli->query("
    SELECT m.id, m.message, m.created_at, u.name
    FROM messages m LEFT JOIN users u ON m.user_id = u.id
    WHERE m.is_read=0
    ORDER BY m.created_at DESC LIMIT 5
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $notif_items[] = [
            'icon'  => 'message-square',
            'color' => 'var(--brand)',
            'bg'    => 'var(--brand-lt)',
            'title' => 'Message from ' . htmlspecialchars($row['name'] ?? ucfirst('system')),
            'time'  => $row['created_at'],
            'href'  => 'messages.php',
            'type'  => 'message',
        ];
    }
}

// Sort all by time descending
usort($notif_items, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));
$notif_items = array_slice($notif_items, 0, 8);

$total_notifs = $pending_apps + $unread_msgs;

// Time ago helper
function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('M d', strtotime($datetime));
}
?>

<!-- TOPBAR -->
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
                <span>Admin</span>
                <i data-lucide="chevron-right" style="width:13px;height:13px"></i>
                <span style="color:var(--brand);font-weight:600;"><?= htmlspecialchars($page_subtitle) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="topbar-right">
        <div class="search-box" id="topSearch" style="display:none;">
            <i data-lucide="search" style="width:14px;height:14px;color:var(--muted)"></i>
            <input type="text" placeholder="Search...">
        </div>
        <div class="tb-icon-btn" title="Search" onclick="toggleSearch()">
            <i data-lucide="search" style="width:15px;height:15px"></i>
        </div>

        <!-- NOTIFICATION BELL -->
        <div class="notif-wrapper" id="notifWrapper">
            <div class="tb-icon-btn" title="Notifications" onclick="toggleNotifPanel(event)">
                <i data-lucide="bell" style="width:15px;height:15px"></i>
                <?php if ($total_notifs > 0): ?>
                <div class="notif-dot"></div>
                <span class="notif-count"><?= $total_notifs > 99 ? '99+' : $total_notifs ?></span>
                <?php endif; ?>
            </div>

            <!-- Dropdown Panel -->
            <div class="notif-panel" id="notifPanel">
                <div class="notif-panel-header">
                    <span class="notif-panel-title">Notifications</span>
                    <?php if ($total_notifs > 0): ?>
                    <span class="notif-panel-badge"><?= $total_notifs ?> new</span>
                    <?php endif; ?>
                </div>

                <!-- Summary Strip -->
                <div class="notif-summary">
                    <a href="applications.php?status=pending" class="notif-sum-item">
                        <div class="notif-sum-icon" style="background:var(--yellow-lt);color:var(--yellow);">
                            <i data-lucide="clipboard-list" style="width:13px;height:13px"></i>
                        </div>
                        <div>
                            <div class="notif-sum-n"><?= $pending_apps ?></div>
                            <div class="notif-sum-l">Pending</div>
                        </div>
                    </a>
                    <a href="messages.php" class="notif-sum-item">
                        <div class="notif-sum-icon" style="background:var(--brand-lt);color:var(--brand);">
                            <i data-lucide="message-square" style="width:13px;height:13px"></i>
                        </div>
                        <div>
                            <div class="notif-sum-n"><?= $unread_msgs ?></div>
                            <div class="notif-sum-l">Messages</div>
                        </div>
                    </a>
                </div>

                <!-- Notification List -->
                <div class="notif-list">
                    <?php if (empty($notif_items)): ?>
                    <div class="notif-empty">
                        <i data-lucide="bell-off" style="width:18px;height:18px;color:var(--muted)"></i>
                        <span>No new notifications</span>
                    </div>
                    <?php else: ?>
                    <?php foreach ($notif_items as $n): ?>
                    <a href="<?= $n['href'] ?>" class="notif-item">
                        <div class="notif-item-icon" style="background:<?= $n['bg'] ?>;color:<?= $n['color'] ?>;">
                            <i data-lucide="<?= $n['icon'] ?>" style="width:14px;height:14px"></i>
                        </div>
                        <div class="notif-item-body">
                            <div class="notif-item-text"><?= $n['title'] ?></div>
                            <div class="notif-item-time"><?= time_ago($n['time']) ?></div>
                        </div>
                        <div class="notif-item-dot"></div>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Footer -->
                <div class="notif-panel-footer">
                    <a href="applications.php?status=pending">View all requests</a>
                    <a href="messages.php">All messages</a>
                </div>
            </div>
        </div>

        <a href="user.php" class="tb-icon-btn" title="User Management">
            <i data-lucide="users" style="width:15px;height:15px"></i>
        </a>
    </div>
</header>

<style>
/* ── NOTIFICATION SYSTEM ── */
.notif-wrapper { position:relative; }
.notif-count {
    position:absolute; top:-4px; right:-6px; min-width:16px; height:16px;
    background:var(--red); color:#fff; font-size:.56rem; font-weight:800;
    border-radius:99px; display:flex; align-items:center; justify-content:center;
    padding:0 4px; border:2px solid var(--white); line-height:1;
}
.notif-wrapper .notif-dot { display:none; }
.notif-wrapper .notif-count ~ .notif-dot { display:none; }

.notif-panel {
    display:none; position:absolute; top:calc(100% + 10px); right:0;
    width:360px; background:var(--white); border:1px solid var(--border);
    border-radius:12px; box-shadow:0 12px 40px rgba(0,0,0,.14);
    z-index:500; overflow:hidden;
    animation:notifIn .18s ease;
}
.notif-panel.open { display:block; }
@keyframes notifIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:none} }

.notif-panel-header {
    padding:14px 16px; border-bottom:1px solid var(--border);
    display:flex; align-items:center; justify-content:space-between;
}
.notif-panel-title { font-size:.88rem; font-weight:800; color:var(--navy); }
.notif-panel-badge {
    font-size:.62rem; font-weight:700; padding:2px 8px; border-radius:99px;
    background:var(--red-lt); color:var(--red);
}

.notif-summary {
    display:grid; grid-template-columns:1fr 1fr; gap:8px;
    padding:12px 16px; border-bottom:1px solid var(--border); background:var(--bg);
}
.notif-sum-item {
    display:flex; align-items:center; gap:10px; padding:8px 10px;
    background:var(--white); border-radius:8px; border:1px solid var(--border);
    transition:all .15s; text-decoration:none;
}
.notif-sum-item:hover { border-color:var(--brand); }
.notif-sum-icon { width:30px; height:30px; border-radius:7px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.notif-sum-n { font-size:1.1rem; font-weight:800; color:var(--navy); line-height:1; }
.notif-sum-l { font-size:.6rem; font-weight:600; color:var(--muted); margin-top:1px; }

.notif-list { max-height:280px; overflow-y:auto; }
.notif-item {
    display:flex; align-items:center; gap:11px; padding:11px 16px;
    border-bottom:1px solid var(--border); transition:background .12s;
    text-decoration:none; color:inherit;
}
.notif-item:last-child { border-bottom:none; }
.notif-item:hover { background:var(--bg); }
.notif-item-icon { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.notif-item-body { flex:1; min-width:0; }
.notif-item-text { font-size:.78rem; font-weight:600; color:var(--navy); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.notif-item-time { font-size:.65rem; color:var(--muted); margin-top:2px; }
.notif-item-dot { width:7px; height:7px; border-radius:50%; background:var(--brand); flex-shrink:0; }

.notif-empty { padding:28px 16px; text-align:center; display:flex; flex-direction:column; align-items:center; gap:6px; font-size:.8rem; color:var(--muted); }

.notif-panel-footer {
    padding:10px 16px; border-top:1px solid var(--border); background:var(--bg);
    display:flex; justify-content:space-between;
}
.notif-panel-footer a { font-size:.72rem; font-weight:700; color:var(--brand); }
.notif-panel-footer a:hover { text-decoration:underline; }

@media(max-width:480px) {
    .notif-panel { width:calc(100vw - 32px); right:-60px; }
}
</style>

<script>
function toggleNotifPanel(e) {
    e.stopPropagation();
    const panel = document.getElementById('notifPanel');
    panel.classList.toggle('open');
    // Re-render icons inside the dropdown
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

// Close when clicking outside
document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('notifWrapper');
    const panel   = document.getElementById('notifPanel');
    if (wrapper && panel && !wrapper.contains(e.target)) {
        panel.classList.remove('open');
    }
});
</script>
