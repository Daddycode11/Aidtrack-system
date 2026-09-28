<?php
// client/help.php
require_once __DIR__ . '/../helpers.php';
require_client();

$active_page   = 'help';
$page_title    = 'Help & FAQ';
$page_subtitle = 'Help';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Help & FAQ — AIDTRACK</title>
<link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="partials/client.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
/* ── HELP PAGE SPECIFIC ── */
.faq-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.faq-item {
    border: 1.5px solid var(--border);
    border-radius: 10px;
    background: var(--white);
    overflow: hidden;
    transition: border-color .2s, box-shadow .2s;
}
.faq-item.active {
    border-color: var(--brand);
    box-shadow: 0 2px 12px rgba(232, 116, 0, .08);
}
.faq-question {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 15px 18px;
    cursor: pointer;
    user-select: none;
    transition: background .15s;
}
.faq-question:hover {
    background: #fffbf5;
}
.faq-q-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.faq-num {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: rgba(232, 116, 0, .08);
    color: var(--brand);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .72rem;
    font-weight: 800;
    flex-shrink: 0;
}
.faq-q-text {
    font-size: .85rem;
    font-weight: 700;
    color: var(--navy);
}
.faq-chevron {
    color: var(--muted);
    transition: transform .25s ease;
    flex-shrink: 0;
}
.faq-item.active .faq-chevron {
    transform: rotate(180deg);
    color: var(--brand);
}
.faq-answer {
    display: none;
    padding: 0 18px 16px 58px;
    font-size: .82rem;
    line-height: 1.7;
    color: var(--muted);
}
.faq-item.active .faq-answer {
    display: block;
}

.contact-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
}
.contact-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    background: var(--white);
    transition: border-color .2s, box-shadow .2s;
}
.contact-item:hover {
    border-color: var(--brand);
    box-shadow: 0 2px 12px rgba(232, 116, 0, .08);
}
.contact-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.ci-orange { background: rgba(232, 116, 0, .08); color: var(--brand); }
.ci-blue   { background: rgba(26, 86, 219, .08); color: #1a56db; }
.ci-green  { background: rgba(22, 163, 74, .08); color: #16a34a; }
.contact-label {
    font-size: .7rem;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: 3px;
}
.contact-value {
    font-size: .84rem;
    font-weight: 700;
    color: var(--navy);
}
.contact-sub {
    font-size: .74rem;
    color: var(--muted);
    margin-top: 2px;
}

.help-intro {
    font-size: .84rem;
    color: var(--muted);
    line-height: 1.7;
    margin-bottom: 4px;
}

@media(max-width:860px) { .contact-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>
<?php include 'partials/topbar.php'; ?>

<main class="main">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-top">
            <div>
                <div class="page-title">Help & FAQ</div>
                <div class="page-sub">Find answers to common questions about using the AIDTRACK system.</div>
            </div>
            <div class="page-actions">
                <a href="apply.php" class="btn btn-primary btn-sm">
                    <i data-lucide="file-plus" style="width:13px;height:13px"></i> New Request
                </a>
            </div>
        </div>
    </div>

    <!-- FAQ Card -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-orange"><i data-lucide="help-circle" style="width:14px;height:14px"></i></div>
                Frequently Asked Questions
            </div>
        </div>
        <div class="card-body">
            <p class="help-intro">Below are the most commonly asked questions. Click on any question to see the answer.</p>

            <div class="faq-list">

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <div class="faq-q-left">
                            <div class="faq-num">1</div>
                            <div class="faq-q-text">How do I apply for assistance?</div>
                        </div>
                        <i data-lucide="chevron-down" class="faq-chevron" style="width:16px;height:16px"></i>
                    </div>
                    <div class="faq-answer">
                        To apply for assistance, navigate to the <strong>"New Request"</strong> page from the sidebar menu. Select the type of assistance you need (Medical or Burial), fill in the required details, upload the necessary documents, and click <strong>"Submit Request"</strong>. Your application will be reviewed by an administrator.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <div class="faq-q-left">
                            <div class="faq-num">2</div>
                            <div class="faq-q-text">What documents are required when applying?</div>
                        </div>
                        <i data-lucide="chevron-down" class="faq-chevron" style="width:16px;height:16px"></i>
                    </div>
                    <div class="faq-answer">
                        The required documents depend on the type of assistance:<br><br>
                        <strong>Medical:</strong> Valid ID, Medical Certificate/Abstract, Barangay Indigency/Attestation<br>
                        <strong>Burial:</strong> Valid ID, Death Certificate, Barangay Indigency<br><br>
                        All documents must be in JPEG, PNG, or PDF format and must not exceed 5MB per file.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <div class="faq-q-left">
                            <div class="faq-num">3</div>
                            <div class="faq-q-text">How long does it take to process my application?</div>
                        </div>
                        <i data-lucide="chevron-down" class="faq-chevron" style="width:16px;height:16px"></i>
                    </div>
                    <div class="faq-answer">
                        Processing times vary depending on the type of assistance and the volume of applications. Typically, applications are reviewed within <strong>3 to 7 working days</strong>. You will be notified once your application status changes.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <div class="faq-q-left">
                            <div class="faq-num">4</div>
                            <div class="faq-q-text">How can I check the status of my application?</div>
                        </div>
                        <i data-lucide="chevron-down" class="faq-chevron" style="width:16px;height:16px"></i>
                    </div>
                    <div class="faq-answer">
                        You can check your application status in two ways:<br><br>
                        <strong>1.</strong> Go to the <strong>"New Request"</strong> page — your previous applications and their statuses are listed at the bottom of the page.<br>
                        <strong>2.</strong> Visit the <strong>"Aid History"</strong> page from the sidebar to see a complete list of all your past and current applications along with their current status (Pending, Approved, or Rejected).
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <div class="faq-q-left">
                            <div class="faq-num">5</div>
                            <div class="faq-q-text">What happens if my application is rejected?</div>
                        </div>
                        <i data-lucide="chevron-down" class="faq-chevron" style="width:16px;height:16px"></i>
                    </div>
                    <div class="faq-answer">
                        If your application is rejected, it may be due to incomplete documents, ineligibility, or insufficient information. You can review the reason (if provided) in your application history. You are welcome to <strong>submit a new application</strong> after addressing the issues. You may also contact the administrator through the <strong>Messages</strong> page for more details.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <div class="faq-q-left">
                            <div class="faq-num">6</div>
                            <div class="faq-q-text">Can I cancel a pending application?</div>
                        </div>
                        <i data-lucide="chevron-down" class="faq-chevron" style="width:16px;height:16px"></i>
                    </div>
                    <div class="faq-answer">
                        Currently, you cannot cancel an application directly from the system. If you need to cancel or withdraw a pending request, please contact the administrator through the <strong>Messages</strong> page and provide your application details. The admin will process the cancellation on your behalf.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <div class="faq-q-left">
                            <div class="faq-num">7</div>
                            <div class="faq-q-text">Can I edit a pending application?</div>
                        </div>
                        <i data-lucide="chevron-down" class="faq-chevron" style="width:16px;height:16px"></i>
                    </div>
                    <div class="faq-answer">
                        Once an application has been submitted, it cannot be directly edited. If you need to make changes to a pending application, please send a message to the administrator through the <strong>Messages</strong> page explaining what needs to be updated. Alternatively, you can request cancellation and submit a new application with the corrected information.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <div class="faq-q-left">
                            <div class="faq-num">8</div>
                            <div class="faq-q-text">What types of aid are available?</div>
                        </div>
                        <i data-lucide="chevron-down" class="faq-chevron" style="width:16px;height:16px"></i>
                    </div>
                    <div class="faq-answer">
                        AIDTRACK currently supports the following types of assistance:<br><br>
                        <strong>Medical Assistance</strong> — For hospitalization, medication, and medical procedures.<br>
                        <strong>Burial Assistance</strong> — For funeral and burial expenses of a deceased family member.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <div class="faq-q-left">
                            <div class="faq-num">9</div>
                            <div class="faq-q-text">How do I contact the administrator?</div>
                        </div>
                        <i data-lucide="chevron-down" class="faq-chevron" style="width:16px;height:16px"></i>
                    </div>
                    <div class="faq-answer">
                        You can contact the administrator directly through the <strong>"Messages"</strong> page available in the sidebar. Simply type your message and send it. The admin team will respond as soon as possible. For urgent concerns, you may also reach out using the contact information provided at the bottom of this page.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <div class="faq-q-left">
                            <div class="faq-num">10</div>
                            <div class="faq-q-text">How do I change my password?</div>
                        </div>
                        <i data-lucide="chevron-down" class="faq-chevron" style="width:16px;height:16px"></i>
                    </div>
                    <div class="faq-answer">
                        To change your password, go to your <strong>Profile</strong> page by clicking on your name or avatar in the top-right corner of the page. From there, you can update your password by entering your current password followed by your new password. Make sure to use a strong password that includes letters, numbers, and special characters.
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Contact Info Card -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon cti-blue"><i data-lucide="phone" style="width:14px;height:14px"></i></div>
                Contact Information
            </div>
        </div>
        <div class="card-body">
            <p class="help-intro">Need further assistance? Reach out to us through any of the following channels.</p>

            <div class="contact-grid">
                <div class="contact-item">
                    <div class="contact-icon ci-orange">
                        <i data-lucide="building-2" style="width:18px;height:18px"></i>
                    </div>
                    <div>
                        <div class="contact-label">Office</div>
                        <div class="contact-value">Municipal Social Welfare and Development Office</div>
                        <div class="contact-sub">Municipal Hall, Ground Floor</div>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon ci-blue">
                        <i data-lucide="mail" style="width:18px;height:18px"></i>
                    </div>
                    <div>
                        <div class="contact-label">Email</div>
                        <div class="contact-value">mswdo@municipality.gov.ph</div>
                        <div class="contact-sub">We respond within 1-2 business days</div>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon ci-green">
                        <i data-lucide="phone" style="width:18px;height:18px"></i>
                    </div>
                    <div>
                        <div class="contact-label">Phone</div>
                        <div class="contact-value">(02) 8123-4567</div>
                        <div class="contact-sub">Mon — Fri, 8:00 AM to 5:00 PM</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</main>

<script src="partials/client.js"></script>
<script>
function toggleFaq(el) {
    const item = el.closest('.faq-item');
    const wasActive = item.classList.contains('active');

    // Close all other items
    document.querySelectorAll('.faq-item.active').forEach(function(openItem) {
        openItem.classList.remove('active');
    });

    // Toggle clicked item
    if (!wasActive) {
        item.classList.add('active');
    }
}
</script>
</body>
</html>
