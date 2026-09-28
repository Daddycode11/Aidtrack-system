<?php
// client/messages.php
require_once __DIR__ . '/../helpers.php';
require_client();

$uid = $_SESSION['user']['id'];
$toast = '';

// --- Handle Send Message ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    $msg = trim($_POST['message'] ?? '');
    if ($msg) {
        $stmt = $mysqli->prepare("INSERT INTO messages (user_id, sender, message, is_read) VALUES (?, 'client', ?, 0)");
        $stmt->bind_param('is', $uid, $msg);
        $stmt->execute();
        $stmt->close();
        header('Location: messages.php?toast=sent'); exit;
    }
}

// --- Fetch messages ---
$messages = [];
$stmt = $mysqli->prepare("
    SELECT id, sender, message, is_read, created_at
    FROM messages
    WHERE user_id = ?
    ORDER BY created_at DESC
");
if ($stmt) {
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// --- Also fetch notifications ---
$notifications = [];
$stmt = $mysqli->prepare("
    SELECT id, message, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");
if ($stmt) {
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Mark messages as read
$mysqli->query("UPDATE messages SET is_read=1 WHERE user_id=$uid AND is_read=0");
$mysqli->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid AND is_read=0");

// Combine into unified list
$all_items = [];
foreach ($messages as $m) {
    $all_items[] = [
        'type'    => 'message',
        'sender'  => ucfirst($m['sender']),
        'message' => $m['message'],
        'is_read' => $m['is_read'],
        'time'    => $m['created_at'],
        'icon'    => 'message-square',
        'color'   => 'var(--blue)',
        'bg'      => 'var(--blue-lt)',
    ];
}
foreach ($notifications as $n) {
    $all_items[] = [
        'type'    => 'notification',
        'sender'  => 'System',
        'message' => $n['message'],
        'is_read' => $n['is_read'],
        'time'    => $n['created_at'],
        'icon'    => 'bell',
        'color'   => 'var(--brand)',
        'bg'      => 'var(--brand-lt)',
    ];
}

// Sort by time descending
usort($all_items, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));

$total   = count($all_items);
$unread  = count(array_filter($all_items, fn($i) => !$i['is_read']));

// Partials
$active_page   = 'messages';
$page_title    = 'Notifications';
$page_subtitle = 'Notifications';
$colors = ['#e87400','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications — AIDTRACK</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/client.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
.notif-card {
    display:flex; align-items:flex-start; gap:14px; padding:16px 20px;
    border-bottom:1px solid var(--border); transition:background .12s;
}
.notif-card:last-child { border-bottom:none; }
.notif-card:hover { background:var(--bg); }
.notif-card.was-unread { background:var(--brand-lt); }
.notif-card.was-unread:hover { background:#fff1e0; }
.notif-icon {
    width:36px; height:36px; border-radius:9px;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.notif-body { flex:1; min-width:0; }
.notif-sender { font-size:.78rem; font-weight:700; color:var(--navy); }
.notif-msg { font-size:.82rem; color:var(--slate); margin-top:3px; line-height:1.5; }
.notif-time { font-size:.66rem; color:var(--muted); margin-top:4px; display:flex; align-items:center; gap:4px; }
.notif-badge-type {
    font-size:.58rem; font-weight:700; padding:2px 7px; border-radius:99px; margin-left:8px;
}

.tabs { display:flex; gap:4px; padding:12px 20px 0; border-bottom:1px solid var(--border); }
.tab {
    padding:8px 16px; font-size:.8rem; font-weight:700; color:var(--muted);
    border-radius:8px 8px 0 0; border:1px solid transparent; border-bottom:none;
    cursor:pointer; transition:all .15s; background:transparent; font-family:inherit;
    position:relative; bottom:-1px;
}
.tab.active { color:var(--brand); background:var(--white); border-color:var(--border); border-bottom-color:var(--white); }
.tab:hover:not(.active) { color:var(--navy); }
</style>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<?php if (($_GET['toast'] ?? '') === 'sent'): ?>
<div class="toast toast-success"><i data-lucide="check-circle" style="width:16px;height:16px"></i> Message sent to admin.</div>
<?php endif; ?>

<main class="main">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-top">
            <div>
                <div class="page-title">Notifications</div>
                <div class="page-sub">Messages and updates about your aid applications.</div>
            </div>
            <div class="page-actions">
                <span class="count-pill"><?= $total ?> total</span>
                <?php if ($unread > 0): ?>
                <span class="count-pill" style="background:var(--brand-lt);color:var(--brand);border-color:rgba(232,116,0,.2);"><?= $unread ?> unread</span>
                <?php endif; ?>
                <button class="btn btn-primary btn-sm" onclick="document.getElementById('composeModal').classList.add('open')">
                    <i data-lucide="pen-square" style="width:13px;height:13px"></i> Compose
                </button>
            </div>
        </div>
    </div>

    <!-- Notifications Card -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-orange"><i data-lucide="bell" style="width:14px;height:14px"></i></div>
                All Notifications
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="filterNotifs('all', this)">All</button>
            <button class="tab" onclick="filterNotifs('message', this)">Messages</button>
            <button class="tab" onclick="filterNotifs('notification', this)">System</button>
        </div>

        <div class="card-body no-pad" id="notifList">
            <?php if (empty($all_items)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="bell-off" style="width:20px;height:20px"></i></div>
                <div class="empty-text">You have no notifications yet.</div>
            </div>
            <?php else: ?>
            <?php foreach ($all_items as $i => $item): ?>
            <div class="notif-card <?= !$item['is_read'] ? 'was-unread' : '' ?>" data-type="<?= $item['type'] ?>">
                <div class="notif-icon" style="background:<?= $item['bg'] ?>;color:<?= $item['color'] ?>;">
                    <i data-lucide="<?= $item['icon'] ?>" style="width:16px;height:16px"></i>
                </div>
                <div class="notif-body">
                    <div class="notif-sender">
                        <?= htmlspecialchars($item['sender']) ?>
                        <span class="notif-badge-type" style="background:<?= $item['bg'] ?>;color:<?= $item['color'] ?>;">
                            <?= $item['type'] === 'message' ? 'Message' : 'System' ?>
                        </span>
                    </div>
                    <div class="notif-msg"><?= htmlspecialchars($item['message']) ?></div>
                    <div class="notif-time">
                        <i data-lucide="clock" style="width:10px;height:10px"></i>
                        <?= date('M d, Y — h:i A', strtotime($item['time'])) ?>
                    </div>
                </div>
                <?php if (!$item['is_read']): ?>
                <div style="width:7px;height:7px;border-radius:50%;background:var(--brand);flex-shrink:0;margin-top:14px;"></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</main>

<!-- COMPOSE MODAL -->
<div class="modal-overlay" id="composeModal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.4);align-items:center;justify-content:center;">
    <div style="background:var(--white);border-radius:14px;max-width:480px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border);">
            <div style="display:flex;align-items:center;gap:10px;font-weight:800;font-size:.92rem;color:var(--navy);">
                <div style="width:28px;height:28px;border-radius:8px;background:var(--brand-lt);color:var(--brand);display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="pen-square" style="width:14px;height:14px"></i>
                </div>
                Send Message to Admin
            </div>
            <button onclick="document.getElementById('composeModal').classList.remove('open')" style="background:none;border:none;cursor:pointer;color:var(--muted);padding:4px;">
                <i data-lucide="x" style="width:16px;height:16px"></i>
            </button>
        </div>
        <form method="POST" style="padding:22px;">
            <input type="hidden" name="action" value="send">
            <div class="form-group">
                <label style="font-size:.76rem;font-weight:700;color:var(--slate);">Your Message</label>
                <textarea name="message" rows="5" required placeholder="Type your message to the admin..." style="width:100%;font-family:inherit;font-size:.84rem;color:var(--navy);background:var(--bg);border:1.5px solid var(--border);border-radius:8px;padding:10px 14px;outline:none;resize:vertical;"></textarea>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('composeModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="send" style="width:13px;height:13px"></i> Send</button>
            </div>
        </form>
    </div>
</div>
<style>
.modal-overlay.open { display:flex !important; }
</style>

<script src="partials/client.js"></script>
<script>
document.getElementById('composeModal').addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });

function filterNotifs(type, btn) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.notif-card').forEach(card => {
        card.style.display = (type === 'all' || card.dataset.type === type) ? '' : 'none';
    });
}
</script>
</body>
</html>
