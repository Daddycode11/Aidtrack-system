<?php
// client/view_application.php — Full application detail view
require_once __DIR__ . '/../helpers.php';
require_client();

$uid = $_SESSION['user']['id'];
$app_id = (int)($_GET['id'] ?? 0);

if (!$app_id) { redirect('aid_history.php'); }

// --- Ensure comments table exists ---
$mysqli->query("CREATE TABLE IF NOT EXISTS application_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(application_id)
)");

// --- Handle Add Comment ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'comment') {
    $comment = trim($_POST['comment'] ?? '');
    if ($comment) {
        $stmt = $mysqli->prepare("INSERT INTO application_comments (application_id, user_id, comment) VALUES (?,?,?)");
        $stmt->bind_param('iis', $app_id, $uid, $comment);
        $stmt->execute();
        $stmt->close();
        redirect('view_application.php?id=' . $app_id . '&toast=commented');
    }
}

// --- Handle Cancel ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $stmt = $mysqli->prepare("UPDATE applications SET status='cancelled' WHERE id=? AND user_id=? AND status='pending'");
    $stmt->bind_param('ii', $app_id, $uid);
    $stmt->execute();
    $stmt->close();
    redirect('view_application.php?id=' . $app_id . '&toast=cancelled');
}

// --- Fetch Application ---
$stmt = $mysqli->prepare("
    SELECT a.*, u.name, u.phone, u.barangay
    FROM applications a JOIN users u ON a.user_id = u.id
    WHERE a.id = ? AND a.user_id = ?
");
$stmt->bind_param('ii', $app_id, $uid);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app) { redirect('aid_history.php'); }

// --- Fetch Documents ---
$docs = [];
$stmt = $mysqli->prepare("SELECT * FROM documents WHERE application_id = ? ORDER BY uploaded_at DESC");
$stmt->bind_param('i', $app_id);
$stmt->execute();
$docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Fetch Admin Actions ---
$actions = [];
$stmt = $mysqli->prepare("
    SELECT aa.*, u.name AS admin_name
    FROM admin_actions aa LEFT JOIN users u ON aa.admin_id = u.id
    WHERE aa.application_id = ?
    ORDER BY aa.created_at DESC
");
$stmt->bind_param('i', $app_id);
$stmt->execute();
$actions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Fetch Comments ---
$comments = [];
$stmt = $mysqli->prepare("
    SELECT c.*, u.name AS author_name, u.role AS author_role
    FROM application_comments c JOIN users u ON c.user_id = u.id
    WHERE c.application_id = ?
    ORDER BY c.created_at ASC
");
$stmt->bind_param('i', $app_id);
$stmt->execute();
$comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$s = strtolower($app['status']);
$bc = match($s) { 'approved'=>'b-approved', 'pending'=>'b-pending', 'rejected'=>'b-rejected', default=>'b-cancelled' };

// Partials
$active_page   = 'aid_history';
$page_title    = 'Application #' . $app_id;
$page_subtitle = 'View Application';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Application #<?= $app_id ?> — AIDTRACK</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/client.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
.detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.detail-item { padding:10px 0; }
.detail-label { font-size:.68rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px; }
.detail-value { font-size:.88rem; font-weight:600; color:var(--navy); }
.detail-value.mono { font-family:monospace; }
.status-banner { padding:16px 20px; border-radius:var(--r); display:flex; align-items:center; gap:12px; margin-bottom:16px; }
.sb-approved  { background:var(--green-lt); border:1px solid rgba(22,163,74,.15); color:var(--green); }
.sb-pending   { background:var(--yellow-lt); border:1px solid rgba(202,138,4,.15); color:var(--yellow); }
.sb-rejected  { background:var(--red-lt); border:1px solid rgba(220,38,38,.15); color:var(--red); }
.sb-cancelled { background:var(--bg); border:1px solid var(--border); color:var(--muted); }
.sb-text { font-size:.84rem; font-weight:700; }
.sb-sub  { font-size:.74rem; font-weight:500; opacity:.8; }
.doc-card { display:flex; align-items:center; gap:12px; padding:12px 14px; background:var(--bg); border:1px solid var(--border); border-radius:8px; transition:all .15s; }
.doc-card:hover { border-color:var(--brand); }
.doc-icon { width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.doc-name { font-size:.8rem; font-weight:700; color:var(--navy); }
.doc-meta { font-size:.66rem; color:var(--muted); margin-top:2px; }
.timeline { display:flex; flex-direction:column; gap:0; }
.tl-item { display:flex; gap:14px; padding:14px 0; position:relative; }
.tl-item:not(:last-child)::before { content:''; position:absolute; left:15px; top:42px; bottom:0; width:2px; background:var(--border); }
.tl-dot { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; z-index:1; }
.tl-text { font-size:.82rem; font-weight:600; color:var(--navy); }
.tl-sub  { font-size:.7rem; color:var(--muted); margin-top:2px; }
@media(max-width:768px) { .detail-grid { grid-template-columns:1fr; } }
</style>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<?php if (($_GET['toast'] ?? '') === 'cancelled'): ?>
<div class="toast toast-success"><i data-lucide="check-circle" style="width:16px;height:16px"></i> Application cancelled successfully.</div>
<?php elseif (($_GET['toast'] ?? '') === 'commented'): ?>
<div class="toast toast-success"><i data-lucide="check-circle" style="width:16px;height:16px"></i> Comment posted.</div>
<?php endif; ?>

<main class="main">

    <div class="page-header">
        <div class="page-header-top">
            <div>
                <div class="page-title">Application #<?= $app_id ?></div>
                <div class="page-sub"><?= ucfirst($app['type']) ?> Assistance — submitted <?= date('M d, Y', strtotime($app['date_of_request'])) ?></div>
            </div>
            <div class="page-actions">
                <a href="aid_history.php" class="btn btn-outline btn-sm">
                    <i data-lucide="arrow-left" style="width:13px;height:13px"></i> Back
                </a>
                <?php if ($s === 'pending'): ?>
                <a href="edit_application.php?id=<?= $app_id ?>" class="btn btn-sm" style="background:var(--brand);color:#fff;">
                    <i data-lucide="pencil" style="width:13px;height:13px"></i> Edit
                </a>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this application?')">
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn btn-sm" style="background:var(--red);color:#fff;">
                        <i data-lucide="x-circle" style="width:13px;height:13px"></i> Cancel
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Status Banner -->
    <div class="status-banner sb-<?= $s ?>">
        <i data-lucide="<?= match($s) { 'approved'=>'check-circle', 'pending'=>'hourglass', 'rejected'=>'x-circle', default=>'ban' } ?>" style="width:22px;height:22px"></i>
        <div>
            <div class="sb-text"><?= ucfirst($s) ?></div>
            <div class="sb-sub">
                <?= match($s) {
                    'approved' => 'Your application has been approved.',
                    'pending'  => 'Your application is awaiting review by the admin.',
                    'rejected' => 'Your application was not approved.',
                    'cancelled'=> 'You cancelled this application.',
                    default    => ''
                } ?>
            </div>
        </div>
    </div>

    <?php if ($s === 'rejected' && $app['rejection_reason']): ?>
    <div style="background:var(--red-lt);border:1px solid rgba(220,38,38,.12);border-radius:var(--r);padding:14px 18px;margin-bottom:16px;">
        <div style="font-size:.72rem;font-weight:700;color:var(--red);text-transform:uppercase;margin-bottom:4px;">Rejection Reason</div>
        <div style="font-size:.84rem;color:var(--slate);line-height:1.6;"><?= htmlspecialchars($app['rejection_reason']) ?></div>
    </div>
    <?php endif; ?>

    <div class="grid-2">
        <!-- Application Details -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon cti-orange"><i data-lucide="file-text" style="width:14px;height:14px"></i></div>
                    Application Details
                </div>
            </div>
            <div class="card-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">Application ID</div>
                        <div class="detail-value">#<?= $app_id ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Assistance Type</div>
                        <div class="detail-value"><?= ucfirst($app['type']) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Amount Requested</div>
                        <div class="detail-value mono">₱<?= number_format($app['amount_requested'] ?? 0, 2) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Amount Granted</div>
                        <div class="detail-value mono" style="color:var(--green);">₱<?= number_format($app['amount_granted'] ?? 0, 2) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Amount Released</div>
                        <div class="detail-value mono" style="color:var(--blue);">₱<?= number_format($app['amount_released'] ?? 0, 2) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Date Requested</div>
                        <div class="detail-value"><?= date('F d, Y', strtotime($app['date_of_request'])) ?></div>
                    </div>
                </div>
                <?php if ($app['notes']): ?>
                <div class="detail-item" style="margin-top:8px;padding-top:12px;border-top:1px solid var(--border);">
                    <div class="detail-label">Notes / Justification</div>
                    <div style="font-size:.84rem;color:var(--slate);line-height:1.6;margin-top:4px;"><?= nl2br(htmlspecialchars($app['notes'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Documents + Timeline -->
        <div style="display:flex;flex-direction:column;gap:16px;">
            <!-- Documents -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div class="card-title-icon cti-yellow"><i data-lucide="folder-open" style="width:14px;height:14px"></i></div>
                        Uploaded Documents
                        <span class="count-pill"><?= count($docs) ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($docs)): ?>
                    <div class="empty-state" style="padding:20px;">
                        <div class="empty-text">No documents uploaded.</div>
                    </div>
                    <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <?php foreach ($docs as $d):
                            $ext = strtolower(pathinfo($d['filename'], PATHINFO_EXTENSION));
                            $is_img = in_array($ext, ['jpg','jpeg','png']);
                        ?>
                        <a href="../uploads/<?= urlencode($d['filename']) ?>" target="_blank" class="doc-card">
                            <div class="doc-icon" style="background:<?= $is_img ? 'var(--blue-lt)' : 'var(--red-lt)' ?>;color:<?= $is_img ? 'var(--blue)' : 'var(--red)' ?>;">
                                <i data-lucide="<?= $is_img ? 'image' : 'file-text' ?>" style="width:16px;height:16px"></i>
                            </div>
                            <div>
                                <div class="doc-name"><?= htmlspecialchars($d['original_name'] ?? $d['filename']) ?></div>
                                <div class="doc-meta">Uploaded <?= date('M d, Y h:i A', strtotime($d['uploaded_at'])) ?></div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Activity Timeline -->
            <?php if (!empty($actions)): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div class="card-title-icon cti-green"><i data-lucide="activity" style="width:14px;height:14px"></i></div>
                        Activity
                    </div>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($actions as $act):
                            $ac = match($act['action']) { 'approve'=>['check','var(--green-lt)','var(--green)'], 'reject'=>['x','var(--red-lt)','var(--red)'], 'release'=>['banknote','var(--blue-lt)','var(--blue)'], default=>['circle','var(--bg)','var(--muted)'] };
                        ?>
                        <div class="tl-item">
                            <div class="tl-dot" style="background:<?= $ac[1] ?>;color:<?= $ac[2] ?>;">
                                <i data-lucide="<?= $ac[0] ?>" style="width:14px;height:14px"></i>
                            </div>
                            <div>
                                <div class="tl-text"><?= ucfirst($act['action']) ?>d by <?= htmlspecialchars($act['admin_name'] ?? 'Admin') ?></div>
                                <?php if ($act['details']): ?><div class="tl-sub"><?= htmlspecialchars($act['details']) ?></div><?php endif; ?>
                                <div class="tl-sub"><?= date('M d, Y — h:i A', strtotime($act['created_at'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Comments Section -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-orange"><i data-lucide="message-circle" style="width:14px;height:14px"></i></div>
                Comments
                <span class="count-pill"><?= count($comments) ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php if ($comments): ?>
            <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:16px;">
                <?php foreach ($comments as $c):
                    $is_admin = in_array($c['author_role'], ['admin','super_admin']);
                ?>
                <div style="display:flex;gap:10px;<?= $is_admin ? 'flex-direction:row-reverse;' : '' ?>">
                    <div style="width:30px;height:30px;border-radius:8px;background:<?= $is_admin ? 'var(--blue-lt,#eff6ff)' : 'var(--brand-lt)' ?>;color:<?= $is_admin ? 'var(--blue,#1a56db)' : 'var(--brand)' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.6rem;font-weight:800;">
                        <?= strtoupper(substr($c['author_name'], 0, 2)) ?>
                    </div>
                    <div style="flex:1;max-width:80%;background:<?= $is_admin ? 'var(--blue-lt,#eff6ff)' : 'var(--bg)' ?>;border:1px solid var(--border);border-radius:10px;padding:10px 14px;">
                        <div style="font-size:.72rem;font-weight:700;color:<?= $is_admin ? 'var(--blue,#1a56db)' : 'var(--brand)' ?>;margin-bottom:4px;">
                            <?= htmlspecialchars($c['author_name']) ?>
                            <span style="font-weight:500;color:var(--muted);margin-left:4px;"><?= $is_admin ? '(Admin)' : '(You)' ?></span>
                        </div>
                        <div style="font-size:.84rem;color:var(--navy);line-height:1.5;"><?= nl2br(htmlspecialchars($c['comment'])) ?></div>
                        <div style="font-size:.64rem;color:var(--muted);margin-top:6px;"><?= date('M d, Y — h:i A', strtotime($c['created_at'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="text-align:center;padding:16px;color:var(--muted);font-size:.82rem;">No comments yet. Start the conversation below.</div>
            <?php endif; ?>

            <form method="POST" style="border-top:1px solid var(--border);padding-top:14px;">
                <input type="hidden" name="action" value="comment">
                <textarea name="comment" rows="3" required placeholder="Add a comment or question..." style="width:100%;font-family:inherit;font-size:.84rem;color:var(--navy);background:var(--bg);border:1.5px solid var(--border);border-radius:8px;padding:10px 14px;outline:none;resize:vertical;margin-bottom:8px;"></textarea>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i data-lucide="send" style="width:13px;height:13px"></i> Post Comment
                </button>
            </form>
        </div>
    </div>

</main>
<script src="partials/client.js"></script>
</body>
</html>
