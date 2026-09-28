<?php
// admin/messages.php
require_once __DIR__ . '/../helpers.php';
require_admin();

$toast = '';

// --- Handle Reply POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply') {
    $reply_to   = (int)($_POST['user_id'] ?? 0);
    $reply_msg  = trim($_POST['message'] ?? '');
    $admin_id   = $_SESSION['user']['id'] ?? 0;

    if ($reply_to && $reply_msg) {
        $stmt = $mysqli->prepare("INSERT INTO messages (user_id, sender, message, admin_id, is_read) VALUES (?, 'admin', ?, ?, 0)");
        $stmt->bind_param('isi', $reply_to, $reply_msg, $admin_id);
        $stmt->execute();
        $stmt->close();
        header('Location: messages.php?toast=sent'); exit;
    }
}

// --- Handle Delete POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $del_id = (int)($_POST['id'] ?? 0);
    if ($del_id) {
        $stmt = $mysqli->prepare("DELETE FROM messages WHERE id=?");
        $stmt->bind_param('i', $del_id);
        $stmt->execute();
        $stmt->close();
        header('Location: messages.php?toast=deleted'); exit;
    }
}

// --- Mark as read ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_read') {
    $mr_id = (int)($_POST['id'] ?? 0);
    if ($mr_id) {
        $stmt = $mysqli->prepare("UPDATE messages SET is_read=1 WHERE id=?");
        $stmt->bind_param('i', $mr_id);
        $stmt->execute();
        $stmt->close();
    }
}

// -----------------------------
// Fetch messages safely
// -----------------------------
$messages = [];
$stmt = $mysqli->prepare("
    SELECT m.id, m.user_id, m.sender, m.message, m.created_at, m.is_read, u.name AS sender_name,
           COALESCE(a.name, 'Admin') AS admin_name
    FROM messages m
    LEFT JOIN users u ON m.user_id = u.id
    LEFT JOIN users a ON m.admin_id = a.id
    ORDER BY m.created_at DESC
");

if ($stmt) {
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Count unread messages safely
$unread_count = count(array_filter($messages, function($m) { return empty($m['is_read']); }));

// -----------------------------
// Pending aids count (safe query)
// -----------------------------
$pending_aids = 0;
$res = $mysqli->query("SELECT COUNT(*) AS total FROM applications WHERE status='pending'");
if ($res) {
    $row = $res->fetch_assoc();
    $pending_aids = (int)($row['total'] ?? 0);
}

// Page config
$active_page   = 'messages';
$page_title    = 'Messages';
$page_subtitle = 'Messages';
$colors = ['#1a56db','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages — AIDTRACK Admin</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/admin.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
/* Messages-specific styles omitted for brevity (use your existing CSS) */
</style>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<?php if (!empty($_GET['toast'])): ?>
<div class="toast toast-success" style="position:fixed;bottom:24px;right:24px;z-index:999;display:flex;align-items:center;gap:10px;padding:12px 18px;border-radius:10px;font-size:.83rem;font-weight:600;box-shadow:0 8px 28px rgba(0,0,0,.15);background:var(--green-lt,#f0fdf4);color:var(--green,#16a34a);border:1px solid rgba(22,163,74,.2);animation:toastIn .3s ease,toastOut .4s ease 3s forwards;">
    <i data-lucide="check-circle" style="width:16px;height:16px"></i>
    <?= $_GET['toast'] === 'sent' ? 'Reply sent successfully.' : 'Message deleted.' ?>
</div>
<style>@keyframes toastIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}@keyframes toastOut{from{opacity:1}to{opacity:0;pointer-events:none}}</style>
<?php endif; ?>

<main class="main">
<div class="page-header">
    <div class="page-header-top">
        <div>
            <div class="page-title">
                Messages Inbox
                <?php if ($unread_count > 0): ?>
                <span class="unread-pill"><?= $unread_count ?> unread</span>
                <?php endif; ?>
            </div>
            <div class="page-sub">Manage incoming messages from beneficiaries and applicants.</div>
        </div>
        <div class="page-actions">
            <div class="inbox-tabs">
                <button class="itab active" onclick="filterMsgs('all',this)">All</button>
                <button class="itab" onclick="filterMsgs('unread',this)">Unread</button>
                <button class="itab" onclick="filterMsgs('read',this)">Read</button>
            </div>
            <span class="count-pill"><?= count($messages) ?> total</span>
        </div>
    </div>
</div>

<div class="inbox-layout">
    <!-- Message List -->
    <div class="card msg-panel">
        <div class="msg-panel-filter">
            <i data-lucide="search" style="width:14px;height:14px;color:var(--muted);flex-shrink:0;"></i>
            <input type="text" id="msgSearch" placeholder="Search messages…" oninput="searchMsgs(this.value)">
        </div>
        <div class="msg-list" id="msgList">
            <?php if (empty($messages)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i data-lucide="inbox" style="width:20px;height:20px"></i></div>
                    <div class="empty-text">No messages found.</div>
                </div>
            <?php else:
                foreach ($messages as $i => $msg):
                    $col   = $colors[$i % count($colors)];
                    $name  = $msg['sender_name'] ?? ucfirst($msg['sender']);
                    $init  = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $name), 0, 2)));
                    $preview = mb_substr($msg['message'] ?? '', 0, 60);
                    $unread= empty($msg['is_read']);
            ?>
            <div class="msg-row <?= $unread ? 'unread' : '' ?>"
                 data-read="<?= $unread ? 'unread' : 'read' ?>"
                 data-id="<?= (int)$msg['id'] ?>"
                 onclick='selectMsg(
                     this,
                     <?= (int)$msg["id"] ?>,
                     <?= json_encode($name) ?>,
                     <?= json_encode(date("M d, Y H:i", strtotime($msg["created_at"]))) ?>,
                     <?= json_encode($msg["message"] ?? "No message content.") ?>,
                     <?= json_encode($col) ?>,
                     <?= json_encode($init) ?>,
                     <?= (int)($msg["user_id"] ?? 0) ?>,
                     <?= json_encode($msg["sender"] ?? "client") ?>
                 )'>
                <div class="msg-av" style="background:<?= $col ?>"><?= $init ?></div>
                <div class="msg-row-body">
                    <div class="msg-row-top">
                        <span class="msg-row-sender"><?= htmlspecialchars($name) ?></span>
                        <span class="msg-row-time"><?= date('M d', strtotime($msg['created_at'])) ?></span>
                    </div>
                    <div class="msg-row-subject"><?= htmlspecialchars($preview ?: '(No content)') ?></div>
                </div>
                <?php if ($unread): ?><div class="unread-dot"></div><?php endif; ?>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Message Viewer -->
    <div class="card msg-viewer" id="msgViewer">
        <div class="mv-empty" id="mvEmpty">
            <div class="mv-empty-icon"><i data-lucide="mail-open" style="width:24px;height:24px"></i></div>
            <div class="mv-empty-text">Select a message to read it</div>
        </div>
        <div id="mvContent" style="display:none;">
            <div class="mv-header">
                <div class="mv-subject" id="mvSubject"></div>
                <div class="mv-meta">
                    <div class="mv-av" id="mvAv"></div>
                    <div>
                        <div class="mv-sender-name" id="mvSenderName"></div>
                        <div class="mv-sender-email" id="mvSenderEmail"></div>
                    </div>
                    <div class="mv-date" id="mvDate"></div>
                </div>
            </div>
            <div class="mv-body" id="mvBody"></div>

            <!-- Reply Form -->
            <div id="mvReplySection" style="border-top:1px solid var(--border);padding:16px 20px;margin-top:12px;">
                <div style="font-size:.76rem;font-weight:700;color:var(--slate);margin-bottom:8px;">
                    <i data-lucide="reply" style="width:12px;height:12px;vertical-align:middle"></i> Reply
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="user_id" id="mvReplyUserId">
                    <textarea name="message" placeholder="Type your reply…" required
                        style="width:100%;min-height:80px;font-family:inherit;font-size:.84rem;color:var(--navy);background:var(--bg);border:1.5px solid var(--border);border-radius:8px;padding:10px 14px;outline:none;resize:vertical;margin-bottom:8px;"></textarea>
                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                        <form method="post" style="display:inline;" id="mvDeleteForm">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" id="mvDeleteId">
                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--red);border-color:rgba(220,38,38,.15);"
                                    onclick="return confirm('Delete this message?')">
                                <i data-lucide="trash-2" style="width:13px;height:13px"></i> Delete
                            </button>
                        </form>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i data-lucide="send" style="width:13px;height:13px"></i> Send Reply
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</main>

<script src="partials/admin.js"></script>
<script>
function selectMsg(el, id, sender, date, body, color, initials, userId, senderType) {
    document.querySelectorAll('.msg-row').forEach(r => r.classList.remove('selected'));
    el.classList.add('selected');
    const dot = el.querySelector('.unread-dot');
    if(dot) dot.remove();
    el.classList.remove('unread');
    el.dataset.read = 'read';

    document.getElementById('mvEmpty').style.display   = 'none';
    document.getElementById('mvContent').style.display = 'block';
    document.getElementById('mvSubject').textContent    = (senderType === 'admin' ? 'Reply to ' : 'Message from ') + sender;
    document.getElementById('mvSenderName').textContent = sender;
    document.getElementById('mvSenderEmail').textContent= senderType === 'admin' ? 'Admin Reply' : 'Client Message';
    document.getElementById('mvDate').textContent       = date;
    document.getElementById('mvBody').textContent       = body || 'No message content.';
    document.getElementById('mvReplyUserId').value      = userId;
    document.getElementById('mvDeleteId').value         = id;
    const av = document.getElementById('mvAv');
    av.textContent = initials;
    av.style.background = color;

    // Show/hide reply section based on sender
    document.getElementById('mvReplySection').style.display = userId > 0 ? 'block' : 'none';

    // Mark as read via AJAX
    fetch('messages.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=mark_read&id=${id}`
    });
}

function filterMsgs(filter, btn) {
    document.querySelectorAll('.itab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.msg-row').forEach(row => {
        row.style.display = (filter==='all' || row.dataset.read===filter) ? '' : 'none';
    });
}

function searchMsgs(val) {
    const q = val.toLowerCase();
    document.querySelectorAll('.msg-row').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>
</body>
</html>