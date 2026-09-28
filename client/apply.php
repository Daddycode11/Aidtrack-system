<?php
// client/apply.php
require_once __DIR__ . '/../helpers.php';
require_client();

$message = '';
$errors  = [];
$success = false;
$duplicate_warning = false;

$allowed_types = ['medical', 'burial'];

$document_fields = [
    'valid_id',
    'birth_certificate',
    'barangay_clearance',
    'other_doc'
];

$document_labels = [
    'valid_id'           => 'Valid ID',
    'birth_certificate'  => 'Medical/Death Certificate',
    'barangay_clearance' => 'Barangay Indigency',
    'other_doc'          => 'Additional document'
];

$required_documents = [
    'valid_id',
    'birth_certificate',
    'barangay_clearance'
];

$allowed_mimes = [
    'image/jpeg',
    'image/png',
    'application/pdf'
];

$max_file_size = 5 * 1024 * 1024;
$upload_dir = __DIR__ . '/../uploads/';

/**
 * Validate one uploaded document using the server-detected MIME type.
 */
function aidtrack_validate_upload(array $file, array $allowed_mimes, int $max_file_size): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return 'No file selected.';
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'Upload failed. Please try again.';
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return 'Invalid uploaded file.';
    }

    if ($file['size'] <= 0 || $file['size'] > $max_file_size) {
        return 'File must be greater than 0 bytes and not exceed 5 MB.';
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detected_mime = $finfo->file($file['tmp_name']);

    if (!in_array($detected_mime, $allowed_mimes, true)) {
        return 'Only JPEG, PNG, or PDF files are allowed.';
    }

    return null;
}

/**
 * Create a safe filename while retaining the original extension.
 */
function aidtrack_safe_filename(string $field, string $original_name): string
{
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $extension = preg_replace('/[^a-z0-9]/', '', $extension);

    return $field . '_' . date('YmdHis') . '_' .
        bin2hex(random_bytes(8)) . '.' . $extension;
}

/**
 * Remove files saved during the current submission.
 */
function aidtrack_cleanup_files(array $saved_paths): void
{
    foreach ($saved_paths as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type  = trim($_POST['type'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($type === '') {
        $errors[] = 'Assistance type is required.';
    } elseif (!in_array($type, $allowed_types, true)) {
        $errors[] = 'Invalid assistance type selected.';
    }

    // Prevent accidental duplicate pending requests.
    if (!$errors && empty($_POST['confirm_duplicate'])) {
        $dup = $mysqli->prepare("
            SELECT id
            FROM applications
            WHERE user_id = ? AND type = ? AND status = 'pending'
            LIMIT 1
        ");
        $dup->bind_param('is', $_SESSION['user']['id'], $type);
        $dup->execute();

        if ($dup->get_result()->num_rows > 0) {
            $errors[] = "You already have a pending {$type} application. Submit again to confirm a duplicate request.";
            $duplicate_warning = true;
        }

        $dup->close();
    }

    // Validate the required and optional documents before creating the request.
    if (!$errors) {
        foreach ($required_documents as $field) {
            if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
                $errors[] = $document_labels[$field] . ' is required.';
                continue;
            }

            $file_error = aidtrack_validate_upload(
                $_FILES[$field],
                $allowed_mimes,
                $max_file_size
            );

            if ($file_error !== null) {
                $errors[] = $document_labels[$field] . ': ' . $file_error;
            }
        }

        if (
            isset($_FILES['other_doc']) &&
            $_FILES['other_doc']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            $file_error = aidtrack_validate_upload(
                $_FILES['other_doc'],
                $allowed_mimes,
                $max_file_size
            );

            if ($file_error !== null) {
                $errors[] = $document_labels['other_doc'] . ': ' . $file_error;
            }
        }
    }

    // Ensure the upload directory exists and can receive files.
    if (!$errors) {
        if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
            $errors[] = 'The uploads folder could not be created.';
        } elseif (!is_writable($upload_dir)) {
            $errors[] = 'The uploads folder is not writable.';
        }
    }

    if (!$errors) {
        $user_id = (int)($_SESSION['user']['id'] ?? 0);
        $application_id = 0;
        $saved_paths = [];

        try {
            // Amount is set by the admin after reviewing the request.
            $stmt = $mysqli->prepare("
                INSERT INTO applications
                    (user_id, type, amount, notes, status, date_of_request, created_at)
                VALUES
                    (?, ?, 0.00, ?, 'pending', CURDATE(), NOW())
            ");
            $stmt->bind_param('iss', $user_id, $type, $notes);
            $stmt->execute();
            $application_id = $stmt->insert_id;
            $stmt->close();

            /*
             * The documents.status column already exists in your database.
             * New uploads are saved as valid so the admin can later flag them.
             */
            $doc_insert = $mysqli->prepare("
                INSERT INTO documents
                    (application_id, filename, original_name, status)
                VALUES
                    (?, ?, ?, 'valid')
            ");

            foreach ($document_fields as $field) {
                if (
                    !isset($_FILES[$field]) ||
                    $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE
                ) {
                    continue;
                }

                $file = $_FILES[$field];
                $safe_name = aidtrack_safe_filename($field, $file['name']);
                $destination = $upload_dir . $safe_name;

                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    throw new RuntimeException(
                        'Failed to save ' . $document_labels[$field] . '.'
                    );
                }

                $saved_paths[] = $destination;
                $original_name = basename($file['name']);

                $doc_insert->bind_param(
                    'iss',
                    $application_id,
                    $safe_name,
                    $original_name
                );
                $doc_insert->execute();
            }

            $doc_insert->close();
            $success = true;
        } catch (Throwable $exception) {
            if ($application_id > 0) {
                $delete_docs = $mysqli->prepare(
                    "DELETE FROM documents WHERE application_id = ?"
                );
                $delete_docs->bind_param('i', $application_id);
                $delete_docs->execute();
                $delete_docs->close();

                $delete_application = $mysqli->prepare(
                    "DELETE FROM applications WHERE id = ?"
                );
                $delete_application->bind_param('i', $application_id);
                $delete_application->execute();
                $delete_application->close();
            }

            aidtrack_cleanup_files($saved_paths);
            $errors[] = 'Submission failed while saving your documents. Please try again.';
        }
    }
}

// Fetch previous applications.
$stmt2 = $mysqli->prepare("
    SELECT id, type, amount_granted, notes, status, date_of_request, created_at
    FROM applications
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt2->bind_param('i', $_SESSION['user']['id']);
$stmt2->execute();
$applications = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

// Partials
$active_page   = 'apply';
$page_title    = 'New Request';
$page_subtitle = 'Submit Request';
$colors = ['#e87400','#16a34a','#ca8a04','#dc2626','#7c3aed','#0891b2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Request — AIDTRACK</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/client.css">
<script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<!-- Toast -->
<?php if ($success): ?>
<div class="toast toast-success">
    <i data-lucide="check-circle" style="width:16px;height:16px"></i>
    Application submitted successfully! It is now pending review.
</div>
<?php elseif ($errors): ?>
<div class="toast toast-error">
    <i data-lucide="alert-circle" style="width:16px;height:16px"></i>
    <?= htmlspecialchars($errors[0]) ?>
</div>
<?php endif; ?>

<main class="main">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-top">
            <div>
                <div class="page-title">Submit New Request</div>
                <div class="page-sub">Fill out the form below to request financial assistance.</div>
            </div>
            <div class="page-actions">
                <a href="aid_history.php" class="btn btn-outline btn-sm">
                    <i data-lucide="history" style="width:13px;height:13px"></i> View History
                </a>
            </div>
        </div>
    </div>

    <!-- Mobile swipe tab labels -->
    <div class="swipe-container">
        <div class="swipe-tabs">
            <button class="swipe-tab active">
                <i data-lucide="file-plus" style="width:13px;height:13px"></i> New Request
            </button>
            <button class="swipe-tab">
                <i data-lucide="history" style="width:13px;height:13px"></i> History
            </button>
        </div>

        <div class="swipe-sections">

        <!-- ─── SLIDE 1: Application Form ─── -->
        <div class="swipe-section">
    <!-- Application Form -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-orange"><i data-lucide="file-plus" style="width:14px;height:14px"></i></div>
                Assistance Request Form
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($errors) && !$success): ?>
            <div style="background:var(--red-lt);border:1px solid rgba(220,38,38,.15);border-radius:8px;padding:12px 16px;margin-bottom:16px;">
                <?php foreach ($errors as $e): ?>
                <div style="font-size:.8rem;color:var(--red);font-weight:600;display:flex;align-items:center;gap:6px;margin-bottom:3px;">
                    <i data-lucide="alert-circle" style="width:13px;height:13px;flex-shrink:0"></i> <?= htmlspecialchars($e) ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div style="background:var(--brand-lt,#eff4ff);border:1px solid rgba(26,86,219,.15);border-radius:8px;padding:12px 16px;margin-bottom:16px;">
                <div style="font-size:.78rem;color:var(--brand,#1a56db);font-weight:600;display:flex;align-items:center;gap:6px;">
                    <i data-lucide="info" style="width:13px;height:13px;flex-shrink:0"></i>
                    You don't need to specify an amount. Our office will assess your request and set the assistance amount upon approval.
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" id="aidForm">
                <?php if (!empty($duplicate_warning)): ?>
                <input type="hidden" name="confirm_duplicate" value="1">
                <?php endif; ?>

                <!-- Duplicate Warning Banner -->
                <div id="dupWarning" style="display:none;background:#fefce8;border:1px solid rgba(202,138,4,.2);border-radius:8px;padding:12px 16px;margin-bottom:16px;">
                    <div style="font-size:.8rem;color:#ca8a04;font-weight:700;display:flex;align-items:center;gap:6px;">
                        <i data-lucide="alert-triangle" style="width:14px;height:14px;flex-shrink:0"></i>
                        <span id="dupWarningText">You already have a pending application of this type.</span>
                    </div>
                </div>

                <!-- Type -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Assistance Type <span style="color:var(--red)">*</span></label>
                        <select name="type" id="type" required onchange="toggleForms(); checkDuplicate()">
                            <option value="">-- Select Type --</option>
                            <option value="medical">Medical Assistance</option>
                            <option value="burial">Burial Assistance</option>
                        </select>
                    </div>
                </div>

                <!-- ─── MEDICAL FIELDS ─── -->
                <div id="medicalFields" style="display:none;">
                    <div class="form-section">Medical Assistance Information</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Patient Name</label>
                            <input type="text" name="patient_name" placeholder="Full name of patient">
                        </div>
                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="text" name="contact" placeholder="09XX XXX XXXX">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" placeholder="Complete address">
                    </div>
                    <div class="form-group">
                        <label>Diagnosis / Nature of Illness</label>
                        <textarea name="diagnosis" rows="3" placeholder="Describe the medical condition..."></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Referred By</label>
                            <input type="text" name="referred" placeholder="Name of referrer">
                        </div>
                        <div class="form-group">
                            <label>Prepared By</label>
                            <input type="text" name="prepared_by" placeholder="Name">
                        </div>
                    </div>

                    <div class="form-section">Required Documents</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Valid ID <span style="color:var(--red)">*</span></label>
                            <input type="file" name="valid_id" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                        <div class="form-group">
                            <label>Medical Certificate / Abstract <span style="color:var(--red)">*</span></label>
                            <input type="file" name="birth_certificate" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Barangay Indigency / Attestation <span style="color:var(--red)">*</span></label>
                            <input type="file" name="barangay_clearance" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                        <div class="form-group">
                            <label>Prescription / Reseta <span style="color:var(--muted);font-weight:500">(optional)</span></label>
                            <input type="file" name="other_doc" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                    </div>
                </div>

                <!-- ─── BURIAL FIELDS ─── -->
                <div id="burialFields" style="display:none;">
                    <div class="form-section">Burial Assistance Information</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Name of Deceased</label>
                            <input type="text" name="name_deceased" placeholder="Full name of deceased">
                        </div>
                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="text" name="burial_contact" placeholder="09XX XXX XXXX">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="burial_address" placeholder="Complete address">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Funeral Service</label>
                            <input type="text" name="funeral" placeholder="Name of funeral service">
                        </div>
                        <div class="form-group">
                            <label>Date of Interment</label>
                            <input type="date" name="date_of_interment">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Prepared By</label>
                        <input type="text" name="burial_prepared_by" placeholder="Name">
                    </div>

                    <div class="form-section">Required Documents</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Valid ID <span style="color:var(--red)">*</span></label>
                            <input type="file" name="valid_id" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                        <div class="form-group">
                            <label>Death Certificate <span style="color:var(--red)">*</span></label>
                            <input type="file" name="birth_certificate" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Barangay Indigency <span style="color:var(--red)">*</span></label>
                            <input type="file" name="barangay_clearance" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                        <div class="form-group">
                            <label>Funeral Contract <span style="color:var(--muted);font-weight:500">(optional)</span></label>
                            <input type="file" name="other_doc" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="form-group" style="margin-top:8px;">
                    <label>Notes / Justification <span style="color:var(--muted);font-weight:500">(optional)</span></label>
                    <textarea name="notes" rows="3" placeholder="Add any additional details to support your request..."></textarea>
                </div>

                <div style="display:flex;gap:10px;margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="send" style="width:14px;height:14px"></i> Submit Request
                    </button>
                    <button type="reset" class="btn btn-outline" onclick="toggleForms()">
                        <i data-lucide="rotate-ccw" style="width:14px;height:14px"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>

        </div><!-- /swipe-section 1 -->

        <!-- ─── SLIDE 2: Previous Applications ─── -->
        <div class="swipe-section">
    <!-- Previous Applications -->
    <?php if ($applications): ?>
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-blue"><i data-lucide="history" style="width:14px;height:14px"></i></div>
                Previous Applications
                <span class="count-pill"><?= count($applications) ?></span>
            </div>
        </div>
        <div class="card-body no-pad">
            <div class="tbl-wrap">
                <table>
                    <thead>
                        <tr><th>Type</th><th>Amount Granted</th><th>Notes</th><th>Status</th><th>Submitted</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($applications as $app):
                        $s = strtolower($app['status']);
                        $bc = $s === 'approved' ? 'b-approved' : ($s === 'pending' ? 'b-pending' : ($s === 'rejected' ? 'b-rejected' : 'b-cancelled'));
                    ?>
                    <tr>
                        <td style="font-weight:700;"><?= ucfirst(htmlspecialchars($app['type'])) ?></td>
                        <td style="font-family:monospace;font-weight:700;font-size:.8rem;">
                            <?= $s === 'approved' ? '₱' . number_format($app['amount_granted'] ?? 0, 2) : '<span style="color:var(--muted);font-weight:500;">—</span>' ?>
                        </td>
                        <td style="color:var(--muted);max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($app['notes'] ?: '-') ?></td>
                        <td><span class="badge <?= $bc ?>"><?= ucfirst($s) ?></span></td>
                        <td style="color:var(--muted);white-space:nowrap;"><?= date('M d, Y', strtotime($app['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-icon"><i data-lucide="inbox" style="width:20px;height:20px"></i></div>
                    <div class="empty-text">No previous applications yet.</div>
                </div>
            </div>
        </div>
    <?php endif; ?>
        </div><!-- /swipe-section 2 -->

        </div><!-- /swipe-sections -->
    </div><!-- /swipe-container -->

</main>

<script src="partials/client.js"></script>
<script>
// Pending application types for duplicate check
const pendingTypes = <?= json_encode(array_values(array_unique(array_column(
    array_filter($applications, fn($application) => strtolower($application['status'] ?? '') === 'pending'),
    'type'
)))) ?>;

function checkDuplicate() {
    const type = document.getElementById('type').value;
    const warn = document.getElementById('dupWarning');
    const txt = document.getElementById('dupWarningText');

    if (type && pendingTypes.includes(type)) {
        txt.textContent =
            'You already have a pending ' + type +
            ' application. You may still submit, but it could be flagged as a duplicate.';
        warn.style.display = 'block';
    } else {
        warn.style.display = 'none';
    }
}

function toggleForms() {
    const type = document.getElementById('type').value;

    const sections = {
        medical: document.getElementById('medicalFields'),
        burial: document.getElementById('burialFields')
    };

    Object.entries(sections).forEach(([key, section]) => {
        const show = type === key;

        section.style.display = show ? 'block' : 'none';

        section.querySelectorAll('input, textarea, select').forEach(input => {
            input.disabled = !show;
        });
    });
}

const aidForm = document.getElementById('aidForm');

aidForm.addEventListener('submit', function (event) {
    const type = document.getElementById('type').value;

    if (!type) {
        event.preventDefault();
        alert('Please select an assistance type.');
        return;
    }

    const requiredDocuments = {
        valid_id: 'Valid ID',
        birth_certificate: type === 'burial'
            ? 'Death Certificate'
            : 'Medical Certificate / Abstract',
        barangay_clearance: type === 'burial'
            ? 'Barangay Indigency'
            : 'Barangay Indigency / Attestation'
    };

    for (const [field, label] of Object.entries(requiredDocuments)) {
        const input = document.querySelector(
            '#' + type + 'Fields input[name="' + field + '"]:not(:disabled)'
        );

        if (!input || !input.files || input.files.length === 0) {
            event.preventDefault();
            alert(label + ' is required. Please upload the document before submitting.');
            if (input) input.focus();
            return;
        }
    }
});

aidForm.addEventListener('reset', function () {
    setTimeout(function () {
        toggleForms();
        checkDuplicate();
    }, 0);
});

// Disable hidden form controls on initial page load.
toggleForms();
checkDuplicate();</script>
</body>
</html>
