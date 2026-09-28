<?php
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone            = trim($_POST['phone'] ?? '');
    $name             = trim($_POST['name'] ?? '');
    $barangay         = trim($_POST['barangay'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$phone || !$name || !$barangay || !$password || !$confirm_password) {
        $errors[] = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    } else {
        $mysqli->select_db(DB_NAME);

        $stmt = $mysqli->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = 'This phone number is already registered.';
        } else {
            $hash  = password_hash($password, PASSWORD_DEFAULT);
            $stmt2 = $mysqli->prepare("INSERT INTO users (phone, name, barangay, password_hash, role) VALUES (?, ?, ?, ?, 'client')");
            $stmt2->bind_param('ssss', $phone, $name, $barangay, $hash);

            if ($stmt2->execute()) {
                $user_id = $stmt2->insert_id;
                $_SESSION['user'] = [
                    'id'       => $user_id,
                    'phone'    => $phone,
                    'name'     => $name,
                    'barangay' => $barangay,
                    'role'     => 'client'
                ];
                $stmt2->close();
                header('Location: client/dashboard.php');
                exit;
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account — AIDTRACK</title>
<link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
:root {
    --brand:    #1a56db;
    --brand-dk: #1245b8;
    --brand-lt: #eff4ff;
    --navy:     #0f172a;
    --slate:    #334155;
    --muted:    #64748b;
    --border:   #e2e8f0;
    --bg:       #f1f5f9;
    --white:    #ffffff;
    --green:    #16a34a;
    --green-lt: #f0fdf4;
    --red:      #dc2626;
    --red-lt:   #fef2f2;
    --yellow:   #ca8a04;
    --yellow-lt:#fefce8;
    --r:        10px;
    --sh-lg:    0 16px 48px rgba(0,0,0,.14);
}
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html { height:100%; }
body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    min-height: 100vh;
    display: grid;
    grid-template-columns: 1fr 500px;
    background: var(--bg);
    color: var(--navy);
    overflow: hidden;
}

/* ── LEFT PANEL ── */
.lp {
    position: relative;
    background: var(--navy);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 40px 52px;
    overflow: hidden;
}
.lp::before {
    content:'';
    position:absolute;inset:0;
    background-image:
        linear-gradient(rgba(255,255,255,.03) 1px,transparent 1px),
        linear-gradient(90deg,rgba(255,255,255,.03) 1px,transparent 1px);
    background-size:48px 48px;
    pointer-events:none;
}
.lp::after {
    content:'';
    position:absolute;
    width:500px;height:500px;
    border-radius:50%;
    background:radial-gradient(circle,rgba(26,86,219,.2) 0%,transparent 70%);
    top:-100px;left:-80px;
    pointer-events:none;
}
.lp-top { position:relative;z-index:1; }
.lp-logo { display:flex;align-items:center;gap:10px;margin-bottom:56px; }
.lp-logo img { height:34px;filter:brightness(0) invert(1);opacity:.85; }
.lp-headline { font-size:clamp(1.6rem,2.6vw,2.3rem);font-weight:800;color:#fff;line-height:1.2;margin-bottom:14px; }
.lp-headline span { color:rgba(255,255,255,.32); }
.lp-sub { font-size:.88rem;color:rgba(255,255,255,.42);line-height:1.75;max-width:340px; }

/* Steps */
.lp-steps { position:relative;z-index:1;margin-top:44px;display:flex;flex-direction:column;gap:0; }
.lp-step { display:flex;align-items:flex-start;gap:14px;padding:14px 0; }
.lp-step + .lp-step { border-top:1px solid rgba(255,255,255,.06); }
.lp-step-num {
    width:34px;height:34px;border-radius:50%;
    background:rgba(26,86,219,.5);border:1px solid rgba(26,86,219,.6);
    color:#93c5fd;font-weight:800;font-size:.8rem;
    display:flex;align-items:center;justify-content:center;
    flex-shrink:0;
}
.lp-step-title { font-size:.82rem;font-weight:700;color:rgba(255,255,255,.8);margin-bottom:2px; }
.lp-step-desc  { font-size:.75rem;color:rgba(255,255,255,.35);line-height:1.5; }

/* Trust badges */
.lp-trust { position:relative;z-index:1;display:flex;gap:10px;flex-wrap:wrap;margin-top:32px;padding-top:24px;border-top:1px solid rgba(255,255,255,.07); }
.trust-chip { display:flex;align-items:center;gap:6px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:99px;padding:5px 12px;font-size:.72rem;font-weight:600;color:rgba(255,255,255,.45); }

/* ── RIGHT PANEL ── */
.rp {
    background: var(--white);
    border-left: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}
.rp-bar {
    padding:14px 40px;
    border-bottom:1px solid var(--border);
    display:flex;align-items:center;justify-content:space-between;
    flex-shrink:0;
}
.rp-bar a { font-size:.79rem;font-weight:600;color:var(--muted);text-decoration:none;display:flex;align-items:center;gap:5px;transition:color .15s; }
.rp-bar a:hover { color:var(--brand); }
.rp-bar-label { font-size:.74rem;color:var(--muted); }

.rp-body { flex:1;padding:36px 40px 28px;display:flex;flex-direction:column;justify-content:center; }

.form-logo { display:flex;align-items:center;gap:10px;margin-bottom:28px; }
.form-logo-mark { width:40px;height:40px;border-radius:10px;background:var(--brand);display:flex;align-items:center;justify-content:center; }
.form-logo-text h1 { font-size:.97rem;font-weight:800;color:var(--navy);line-height:1.1; }
.form-logo-text p  { font-size:.7rem;color:var(--muted); }

.form-heading    { font-size:1.28rem;font-weight:800;color:var(--navy);margin-bottom:5px; }
.form-subheading { font-size:.83rem;color:var(--muted);margin-bottom:24px; }

/* Progress indicator */
.prog-steps { display:flex;align-items:center;gap:0;margin-bottom:28px; }
.ps { display:flex;align-items:center;gap:7px; }
.ps-dot { width:26px;height:26px;border-radius:50%;border:2px solid var(--border);background:var(--white);color:var(--muted);font-size:.7rem;font-weight:700;display:flex;align-items:center;justify-content:center;transition:all .2s; }
.ps-dot.done { background:var(--green);border-color:var(--green);color:#fff; }
.ps-dot.active { background:var(--brand);border-color:var(--brand);color:#fff; }
.ps-label { font-size:.72rem;font-weight:600; }
.ps-label.active { color:var(--brand); }
.ps-label.done   { color:var(--green); }
.ps-label.muted  { color:var(--muted); }
.ps-line { flex:1;height:2px;background:var(--border);margin:0 8px; }
.ps-line.done { background:var(--green); }

/* Alert */
.alert { display:flex;align-items:flex-start;gap:9px;padding:11px 13px;border-radius:var(--r);font-size:.8rem;margin-bottom:18px;animation:slideD .22s ease; }
.a-err { background:var(--red-lt);border:1px solid rgba(220,38,38,.18);color:var(--red); }
@keyframes slideD { from{opacity:0;transform:translateY(-5px)}to{opacity:1;transform:none} }

/* Fields */
.field-row { display:grid;grid-template-columns:1fr 1fr;gap:12px; }
.field { display:flex;flex-direction:column;gap:5px;margin-bottom:14px; }
.field label { font-size:.76rem;font-weight:700;color:var(--slate);letter-spacing:.01em; }
.iw { position:relative; }
.iw .ico { position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);pointer-events:none; }
.iw input, .iw select {
    width:100%;
    padding:10px 13px 10px 38px;
    border:1.5px solid var(--border);
    border-radius:var(--r);
    font-family:inherit;font-size:.88rem;color:var(--navy);
    background:var(--bg);
    outline:none;
    transition:border-color .18s,box-shadow .18s,background .18s;
    appearance:none;
}
.iw input:focus, .iw select:focus {
    border-color:var(--brand);
    background:var(--white);
    box-shadow:0 0 0 3px rgba(26,86,219,.1);
}
.iw input.err { border-color:var(--red); }
.iw input.err:focus { box-shadow:0 0 0 3px rgba(220,38,38,.1); }
.iw input.ok  { border-color:var(--green); }
.iw input.ok:focus { box-shadow:0 0 0 3px rgba(22,163,74,.1); }
.iw .pt { position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);padding:2px;transition:color .15s; }
.iw .pt:hover { color:var(--brand); }

/* Password strength */
.strength-bar { height:3px;background:var(--border);border-radius:99px;overflow:hidden;margin-top:5px; }
.strength-fill { height:100%;border-radius:99px;transition:width .3s,background .3s; }
.strength-text { font-size:.7rem;margin-top:4px; }
.match-text { font-size:.7rem;margin-top:4px; }

/* Submit */
.btn-submit {
    width:100%;padding:12px;
    background:var(--brand);color:#fff;
    border:none;border-radius:var(--r);
    font-family:inherit;font-size:.93rem;font-weight:700;
    cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;
    transition:background .18s,box-shadow .18s,transform .14s;
    box-shadow:0 3px 10px rgba(26,86,219,.28);
    margin-top:6px;margin-bottom:20px;
}
.btn-submit:hover { background:var(--brand-dk);box-shadow:0 5px 16px rgba(26,86,219,.32);transform:translateY(-1px); }
.btn-submit:disabled { opacity:.62;cursor:not-allowed;transform:none; }
.spinner { width:17px;height:17px;border:2.5px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite; }
@keyframes spin{to{transform:rotate(360deg)}}

/* Terms */
.terms { display:flex;align-items:flex-start;gap:8px;font-size:.78rem;color:var(--muted);margin-bottom:18px; }
.terms input[type=checkbox] { width:15px;height:15px;margin-top:2px;accent-color:var(--brand);cursor:pointer;flex-shrink:0; }
.terms a { color:var(--brand);font-weight:600; }

/* Login link */
.login-block {
    background:var(--bg);border:1px solid var(--border);
    border-radius:var(--r);padding:13px 17px;
    display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
}
.login-block p { font-size:.8rem;color:var(--muted); }
.login-block a { font-size:.8rem;font-weight:700;color:var(--brand);text-decoration:none;display:flex;align-items:center;gap:5px;white-space:nowrap; }
.login-block a:hover { text-decoration:underline; }

.rp-footer { padding:13px 40px;border-top:1px solid var(--border);font-size:.7rem;color:var(--muted);text-align:center;flex-shrink:0; }

/* Responsive */
@media(max-width:960px){
    body { grid-template-columns:1fr; }
    .lp   { display:none; }
    .rp   { border-left:none;min-height:100vh; }
}
@media(max-width:520px){
    .rp-body { padding:28px 22px; }
    .rp-bar  { padding:13px 22px; }
    .rp-footer { padding:13px 22px; }
    .field-row { grid-template-columns:1fr; }
}
</style>
</head>
<body>

<!-- LEFT PANEL -->
<div class="lp">
    <div class="lp-top">
        <div class="lp-logo">
            <img src="assets/images/AIDTRACK-logo.png" alt="AIDTRACK">
        </div>
        <h2 class="lp-headline">
            Apply for Assistance.<br>
            <span>Register to get started.</span>
        </h2>
        <p class="lp-sub">
            Create your AIDTRACK account to submit financial assistance requests and track them in real time from your personal portal.
        </p>

        <div class="lp-steps">
            <div class="lp-step">
                <div class="lp-step-num">1</div>
                <div>
                    <div class="lp-step-title">Create your account</div>
                    <div class="lp-step-desc">Register with your phone number and basic details. Takes less than a minute.</div>
                </div>
            </div>
            <div class="lp-step">
                <div class="lp-step-num">2</div>
                <div>
                    <div class="lp-step-title">Submit your request</div>
                    <div class="lp-step-desc">Fill out the assistance form and upload your supporting documents securely.</div>
                </div>
            </div>
            <div class="lp-step">
                <div class="lp-step-num">3</div>
                <div>
                    <div class="lp-step-title">Track &amp; receive</div>
                    <div class="lp-step-desc">Monitor your request status in real time and receive notifications at every step.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="lp-trust">
        <div class="trust-chip">
            <i data-lucide="shield-check" style="width:12px;height:12px"></i>
            Secure &amp; Encrypted
        </div>
        <div class="trust-chip">
            <i data-lucide="lock" style="width:12px;height:12px"></i>
            Data Protected
        </div>
        <div class="trust-chip">
            <i data-lucide="landmark" style="width:12px;height:12px"></i>
            Official Gov Portal
        </div>
    </div>
</div>

<!-- RIGHT PANEL -->
<div class="rp">

    <div class="rp-bar">
        <a href="index.php">
            <i data-lucide="arrow-left" style="width:15px;height:15px"></i>
            Back to Home
        </a>
        <span class="rp-bar-label">AIDTRACK Portal</span>
    </div>

    <div class="rp-body">

        <div class="form-logo">
            <div class="form-logo-mark">
                <i data-lucide="landmark" style="width:19px;height:19px;color:white"></i>
            </div>
            <div class="form-logo-text">
                <h1>AIDTRACK</h1>
                <p>Assistance Monitoring System</p>
            </div>
        </div>

        <h2 class="form-heading">Create your account</h2>
        <p class="form-subheading">Register as a beneficiary to apply for financial assistance online.</p>

        <!-- Errors -->
        <?php if ($errors): foreach ($errors as $e): ?>
        <div class="alert a-err">
            <i data-lucide="alert-circle" style="width:15px;height:15px;flex-shrink:0;margin-top:1px"></i>
            <span><?= htmlspecialchars($e) ?></span>
        </div>
        <?php endforeach; endif; ?>

        <form method="post" id="regForm" onsubmit="handleSubmit(event)">

            <!-- Name & Phone -->
            <div class="field-row">
                <div class="field">
                    <label for="name">Full Name</label>
                    <div class="iw">
                        <i data-lucide="user" class="ico" style="width:15px;height:15px"></i>
                        <input type="text" id="name" name="name"
                               placeholder="e.g. Maria Santos"
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                               class="<?= $errors ? 'err' : '' ?>"
                               required autofocus>
                    </div>
                </div>
                <div class="field">
                    <label for="phone">Phone Number</label>
                    <div class="iw">
                        <i data-lucide="smartphone" class="ico" style="width:15px;height:15px"></i>
                        <input type="text" id="phone" name="phone"
                               placeholder="09171234567"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                               class="<?= $errors ? 'err' : '' ?>"
                               required>
                    </div>
                </div>
            </div>

            <!-- Barangay -->
            <div class="field">
                <label for="barangay">Barangay</label>
                <div class="iw">
                    <i data-lucide="map-pin" class="ico" style="width:15px;height:15px"></i>
                    <select id="barangay" name="barangay" required>
                        <option value="" disabled <?= empty($_POST['barangay']) ? 'selected' : '' ?>>Select your barangay</option>
                        <?php
$barangays = [
    'San Jose',
    'Magsaysay',
    'Rizal',
    'Calintaan'
];

foreach ($barangays as $b) {
    $sel = (isset($_POST['barangay']) && $_POST['barangay'] === $b) ? 'selected' : '';
    echo "<option value=\"" . htmlspecialchars($b) . "\" $sel>" . htmlspecialchars($b) . "</option>";
}
?>
                    </select>
                </div>
            </div>

            <!-- Password -->
            <div class="field">
                <label for="password">Password</label>
                <div class="iw">
                    <i data-lucide="lock" class="ico" style="width:15px;height:15px"></i>
                    <input type="password" id="password" name="password"
                           placeholder="Create a strong password"
                           oninput="checkStrength()"
                           class="<?= $errors ? 'err' : '' ?>"
                           required>
                    <button type="button" class="pt" onclick="togglePass('password','eye1')" title="Show/Hide">
                        <i data-lucide="eye" style="width:15px;height:15px" id="eye1"></i>
                    </button>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="sfill" style="width:0%"></div></div>
                <div class="strength-text" id="stxt"></div>
            </div>

            <!-- Confirm Password -->
            <div class="field">
                <label for="confirm_password">Confirm Password</label>
                <div class="iw">
                    <i data-lucide="lock-keyhole" class="ico" style="width:15px;height:15px"></i>
                    <input type="password" id="confirm_password" name="confirm_password"
                           placeholder="Re-enter your password"
                           oninput="checkMatch()"
                           class="<?= $errors ? 'err' : '' ?>"
                           required>
                    <button type="button" class="pt" onclick="togglePass('confirm_password','eye2')" title="Show/Hide">
                        <i data-lucide="eye" style="width:15px;height:15px" id="eye2"></i>
                    </button>
                </div>
                <div class="match-text" id="mtxt"></div>
            </div>

            <!-- Terms -->
            <div class="terms">
                <input type="checkbox" id="terms" required>
                <label for="terms">
                    I agree to the <a href="#">Terms of Use</a> and <a href="#">Privacy Policy</a> of the AIDTRACK portal.
                </label>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <span id="btnTxt">Create Account</span>
                <i data-lucide="arrow-right" style="width:15px;height:15px" id="btnIco"></i>
                <span class="spinner" id="spin" style="display:none"></span>
            </button>

        </form>

        <div class="login-block">
            <p>Already have an account?</p>
            <a href="login.php">
                Sign In to Portal
                <i data-lucide="arrow-right" style="width:13px;height:13px"></i>
            </a>
        </div>

    </div>

    <div class="rp-footer">
        © <?= date('Y') ?> AIDTRACK — Financial Assistance Monitoring System. All rights reserved.
    </div>

</div>

<script>
lucide.createIcons();

/* Password visibility toggle */
function togglePass(inputId, iconId) {
    const inp  = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.setAttribute('data-lucide', 'eye-off');
    } else {
        inp.type = 'password';
        icon.setAttribute('data-lucide', 'eye');
    }
    lucide.createIcons();
}

/* Password strength */
function checkStrength() {
    const val   = document.getElementById('password').value;
    const fill  = document.getElementById('sfill');
    const txt   = document.getElementById('stxt');
    const conf  = document.getElementById('confirm_password');

    let score = 0;
    if (val.length >= 8)          score++;
    if (/[A-Z]/.test(val))        score++;
    if (/[0-9]/.test(val))        score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { w: '25%', bg: '#dc2626', label: 'Weak',   color: '#dc2626' },
        { w: '50%', bg: '#f97316', label: 'Fair',   color: '#f97316' },
        { w: '75%', bg: '#ca8a04', label: 'Good',   color: '#ca8a04' },
        { w: '100%',bg: '#16a34a', label: 'Strong', color: '#16a34a' },
    ];

    if (val.length === 0) {
        fill.style.width = '0%';
        txt.textContent = '';
        return;
    }

    const l = levels[score - 1] || levels[0];
    fill.style.width      = l.w;
    fill.style.background = l.bg;
    txt.textContent       = l.label + ' password';
    txt.style.color       = l.color;

    // re-run match if confirm already has content
    if (conf.value) checkMatch();
}

/* Password match */
function checkMatch() {
    const pass = document.getElementById('password').value;
    const conf = document.getElementById('confirm_password');
    const mtxt = document.getElementById('mtxt');

    if (!conf.value) { mtxt.textContent = ''; conf.className = ''; return; }

    if (pass === conf.value) {
        mtxt.textContent  = '✓ Passwords match';
        mtxt.style.color  = '#16a34a';
        conf.classList.remove('err');
        conf.classList.add('ok');
    } else {
        mtxt.textContent  = '✗ Passwords do not match';
        mtxt.style.color  = '#dc2626';
        conf.classList.remove('ok');
        conf.classList.add('err');
    }
}

/* Submit */
function handleSubmit(e) {
    const pass = document.getElementById('password').value;
    const conf = document.getElementById('confirm_password').value;
    if (pass !== conf) {
        e.preventDefault();
        document.getElementById('confirm_password').focus();
        return;
    }
    const btn  = document.getElementById('submitBtn');
    const txt  = document.getElementById('btnTxt');
    const ico  = document.getElementById('btnIco');
    const spin = document.getElementById('spin');
    btn.disabled       = true;
    txt.textContent    = 'Creating account…';
    ico.style.display  = 'none';
    spin.style.display = 'block';
}
</script>

</body>
</html>