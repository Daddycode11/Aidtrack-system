<?php
// client/edit_application.php — Edit a pending application
require_once __DIR__ . '/../helpers.php';
require_client();

$uid = $_SESSION['user']['id'];
$app_id = (int)($_GET['id'] ?? 0);
if (!$app_id) redirect('aid_history.php');

// Fetch application (must be pending and belong to user)
$stmt = $mysqli->prepare("SELECT * FROM applications WHERE id=? AND user_id=? AND status='pending'");
$stmt->bind_param('ii', $app_id, $uid);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app) redirect('aid_history.php');

$errors = [];
$success = false;

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type   = trim($_POST['type'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $notes  = trim($_POST['notes'] ?? '');

    if (!$type || !$amount) {
        $errors[] = 'Type and Amount are required.';
    } elseif (!is_numeric(str_replace(',', '', $amount))) {
        $errors[] = 'Amount must be a valid number.';
    }

    if (!$errors) {
        $clean_amount = floatval(str_replace(',', '', $amount));
        $stmt = $mysqli->prepare("UPDATE applications SET type=?, amount_requested=?, notes=? WHERE id=? AND user_id=? AND status='pending'");
        $stmt->bind_param('sdsii', $type, $clean_amount, $notes, $app_id, $uid);

        if ($stmt->execute() && $stmt->affected_rows >= 0) {
            // Handle new document uploads (optional replacements)
            $doc_fields = ['valid_id', 'birth_certificate', 'barangay_clearance', 'other_doc'];
            $allowed = ['image/jpeg','image/png','application/pdf'];
            $max_size = 5 * 1024 * 1024;
            $upload_dir = __DIR__ . '/../uploads/';

            foreach ($doc_fields as $doc) {
                if (!empty($_FILES[$doc]['name']) && $_FILES[$doc]['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES[$doc];
                    if (in_array($file['type'], $allowed) && $file['size'] <= $max_size) {
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $safe_name = $doc . '_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
                        if (move_uploaded_file($file['tmp_name'], $upload_dir . $safe_name)) {
                            $orig = $file['name'];
                            $ins = $mysqli->prepare("INSERT INTO documents (application_id, filename, original_name) VALUES (?, ?, ?)");
                            $ins->bind_param('iss', $app_id, $safe_name, $orig);
                            $ins->execute();
                            $ins->close();
                        }
                    }
                }
            }
            $success = true;
            // Refresh app data
            $stmt2 = $mysqli->prepare("SELECT * FROM applications WHERE id=?");
            $stmt2->bind_param('i', $app_id);
            $stmt2->execute();
            $app = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
        } else {
            $errors[] = 'Update failed. Please try again.';
        }
        $stmt->close();
    }
}

// Fetch existing documents
$docs = [];
$stmt = $mysqli->prepare("SELECT * FROM documents WHERE application_id=? ORDER BY uploaded_at DESC");
$stmt->bind_param('i', $app_id);
$stmt->execute();
$docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$active_page   = 'aid_history';
$page_title    = 'Edit Application';
$page_subtitle = 'Edit Application';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Application — AIDTRACK</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/client.css">
<script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<?php if ($success): ?>
<div class="toast toast-success"><i data-lucide="check-circle" style="width:16px;height:16px"></i> Application updated successfully.</div>
<?php endif; ?>

<main class="main">

    <div class="page-header">
        <div class="page-header-top">
            <div>
                <div class="page-title">Edit Application #<?= $app_id ?></div>
                <div class="page-sub">Update your pending application details.</div>
            </div>
            <div class="page-actions">
                <a href="view_application.php?id=<?= $app_id ?>" class="btn btn-outline btn-sm">
                    <i data-lucide="arrow-left" style="width:13px;height:13px"></i> Back to View
                </a>
            </div>
        </div>
    </div>

    <?php if ($errors): ?>
    <div style="background:var(--red-lt);border:1px solid rgba(220,38,38,.15);border-radius:10px;padding:12px 16px;margin-bottom:16px;">
        <?php foreach ($errors as $e): ?>
        <div style="font-size:.8rem;color:var(--red);font-weight:600;display:flex;align-items:center;gap:6px;margin-bottom:3px;">
            <i data-lucide="alert-circle" style="width:13px;height:13px;flex-shrink:0"></i> <?= htmlspecialchars($e) ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-orange"><i data-lucide="pencil" style="width:14px;height:14px"></i></div>
                Edit Request Details
            </div>
            <span class="badge b-pending">Pending</span>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label>Assistance Type <span style="color:var(--red)">*</span></label>
                        <select name="type" required>
                            <option value="medical" <?= $app['type']==='medical'?'selected':'' ?>>Medical</option>
                            <option value="burial"  <?= $app['type']==='burial'?'selected':'' ?>>Burial</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Requested Amount (₱) <span style="color:var(--red)">*</span></label>
                        <input type="text" name="amount" value="<?= number_format($app['amount_requested'], 2) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Notes / Justification</label>
                    <textarea name="notes" rows="4"><?= htmlspecialchars($app['notes'] ?? '') ?></textarea>
                </div>

                <?php if ($docs): ?>
                <div style="margin-bottom:16px;">
                    <div style="font-size:.76rem;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:8px;">Existing Documents</div>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        <?php foreach ($docs as $d): ?>
                        <a href="../uploads/<?= urlencode($d['filename']) ?>" target="_blank"
                           style="display:flex;align-items:center;gap:6px;padding:8px 12px;background:var(--bg);border:1px solid var(--border);border-radius:8px;font-size:.76rem;font-weight:600;color:var(--navy);text-decoration:none;">
                            <i data-lucide="file" style="width:12px;height:12px;color:var(--brand)"></i>
                            <?= htmlspecialchars($d['original_name'] ?? $d['filename']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div style="font-size:.76rem;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:8px;">Upload Additional Documents</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Valid ID</label>
                        <input type="file" name="valid_id" accept=".jpg,.jpeg,.png,.pdf">
                    </div>
                    <div class="form-group">
                        <label>Supporting Document</label>
                        <input type="file" name="birth_certificate" accept=".jpg,.jpeg,.png,.pdf">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Barangay Clearance</label>
                        <input type="file" name="barangay_clearance" accept=".jpg,.jpeg,.png,.pdf">
                    </div>
                    <div class="form-group">
                        <label>Other Document</label>
                        <input type="file" name="other_doc" accept=".jpg,.jpeg,.png,.pdf">
                    </div>
                </div>

                <div style="display:flex;gap:10px;margin-top:12px;padding-top:12px;border-top:1px solid var(--border);">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save" style="width:14px;height:14px"></i> Save Changes
                    </button>
                    <a href="view_application.php?id=<?= $app_id ?>" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>

</main>
<script src="partials/client.js"></script>
</body>
</html>
