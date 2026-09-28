<?php
require_once 'helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$phone || !$password) {
        $errors[] = 'Phone number and password are required.';
    } else {
        $mysqli->select_db(DB_NAME);

        $stmt = $mysqli->prepare("
            SELECT id, phone, name, password_hash, role
            FROM users WHERE phone = ? LIMIT 1
        ");

        if ($stmt) {
            $stmt->bind_param('s', $phone);
            $stmt->execute();

            $res = $stmt->get_result();
            $u   = $res->fetch_assoc();

            $stmt->close();

            if (!$u || !password_verify($password, $u['password_hash'])) {
                $errors[] = 'Invalid phone number or password. Please try again.';
            } else {

                unset($u['password_hash']);
                $_SESSION['user'] = $u;

                switch ($u['role']) {
                    case 'super_admin':
                    case 'admin':
                        header('Location: admin/dashboard.php');
                        exit;

                    case 'client':
                    default:
                        header('Location: client/dashboard.php');
                        exit;
                }
            }
        } else {
            $errors[] = 'A system error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — AIDTRACK</title>
<link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
:root {
    --brand:     #1a56db;
    --brand-dk:  #1245b8;
    --brand-lt:  #eff4ff;
    --navy:      #0f172a;
    --slate:     #334155;
    --muted:     #64748b;
    --border:    #e2e8f0;
    --bg:        #f1f5f9;
    --white:     #ffffff;
    --red:       #dc2626;
    --red-lt:    #fef2f2;
    --green:     #16a34a;
    --radius:    10px;
    --shadow:    0 4px 24px rgba(0,0,0,.08);
    --shadow-lg: 0 16px 48px rgba(0,0,0,.14);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { height: 100%; }
body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    min-height: 100vh;
    display: grid;
    grid-template-columns: 1fr 480px;
    background: var(--bg);
    color: var(--navy);
    overflow: hidden;
}

/* ── LEFT PANEL ───────────────────────────────── */
.left-panel {
    position: relative;
    background: var(--navy);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 40px 52px;
    overflow: hidden;
}

/* subtle grid overlay */
.left-panel::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
    background-size: 48px 48px;
    pointer-events: none;
}
/* brand glow */
.left-panel::after {
    content: '';
    position: absolute;
    width: 480px; height: 480px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(26,86,219,.22) 0%, transparent 70%);
    top: -80px; left: -80px;
    pointer-events: none;
}

.lp-top { position: relative; z-index: 1; }
.lp-logo { display: flex; align-items: center; gap: 10px; margin-bottom: 64px; }
.lp-logo img { height: 34px; filter: brightness(0) invert(1); opacity: .9; }

.lp-headline {
    font-size: clamp(1.7rem, 2.8vw, 2.4rem);
    font-weight: 800;
    color: #fff;
    line-height: 1.2;
    margin-bottom: 16px;
}
.lp-headline span { color: rgba(255,255,255,.35); }
.lp-sub { font-size: .9rem; color: rgba(255,255,255,.45); line-height: 1.7; max-width: 360px; }

/* feature list */
.lp-features { position: relative; z-index: 1; display: flex; flex-direction: column; gap: 14px; margin-top: 48px; }
.lp-feat {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 14px 16px;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 10px;
}
.lp-feat-icon {
    width: 36px; height: 36px; border-radius: 9px;
    background: rgba(26,86,219,.4);
    display: flex; align-items: center; justify-content: center;
    color: #93c5fd;
    flex-shrink: 0;
}
.lp-feat-title { font-size: .82rem; font-weight: 700; color: rgba(255,255,255,.85); margin-bottom: 2px; }
.lp-feat-desc  { font-size: .75rem; color: rgba(255,255,255,.35); line-height: 1.5; }

/* stats row */
.lp-stats { position: relative; z-index: 1; display: flex; gap: 28px; padding-top: 24px; border-top: 1px solid rgba(255,255,255,.07); }
.lp-stat-n { font-size: 1.5rem; font-weight: 800; color: #fff; }
.lp-stat-l { font-size: .72rem; color: rgba(255,255,255,.35); margin-top: 2px; }

/* ── RIGHT PANEL ──────────────────────────────── */
.right-panel {
    background: var(--white);
    border-left: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    position: relative;
    overflow-y: auto;
}
.rp-top-bar {
    padding: 16px 40px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
}
.rp-top-bar a { font-size: .8rem; font-weight: 600; color: var(--muted); text-decoration: none; display: flex; align-items: center; gap: 5px; transition: color .15s; }
.rp-top-bar a:hover { color: var(--brand); }

.rp-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 40px 40px 32px;
}

.form-logo {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 32px;
}
.form-logo-mark {
    width: 42px; height: 42px; border-radius: 11px;
    background: var(--brand);
    display: flex; align-items: center; justify-content: center;
}
.form-logo-text h1 { font-size: 1rem; font-weight: 800; color: var(--navy); line-height: 1.1; }
.form-logo-text p  { font-size: .72rem; color: var(--muted); }

.form-heading { font-size: 1.35rem; font-weight: 800; color: var(--navy); margin-bottom: 6px; }
.form-subheading { font-size: .85rem; color: var(--muted); margin-bottom: 28px; }

/* ── ALERT ─────────────────────────────────────── */
.alert {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px 14px;
    border-radius: var(--radius);
    font-size: .82rem;
    margin-bottom: 20px;
    animation: slideDown .25s ease;
}
.alert-error { background: var(--red-lt); border: 1px solid rgba(220,38,38,.2); color: var(--red); }
@keyframes slideDown { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:none; } }

/* ── FORM FIELDS ────────────────────────────────── */
.field-group { display: flex; flex-direction: column; gap: 16px; margin-bottom: 20px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field label { font-size: .78rem; font-weight: 700; color: var(--slate); letter-spacing: .01em; }

.input-wrap { position: relative; }
.input-wrap .input-icon {
    position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
    color: var(--muted); pointer-events: none;
}
.input-wrap input {
    width: 100%;
    padding: 11px 14px 11px 40px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    font-family: inherit; font-size: .9rem; color: var(--navy);
    background: var(--bg);
    transition: border-color .2s, box-shadow .2s, background .2s;
    outline: none;
}
.input-wrap input:focus {
    border-color: var(--brand);
    background: var(--white);
    box-shadow: 0 0 0 3px rgba(26,86,219,.1);
}
.input-wrap input.error-field { border-color: var(--red); }
.input-wrap input.error-field:focus { box-shadow: 0 0 0 3px rgba(220,38,38,.1); }

.pass-toggle {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; color: var(--muted);
    padding: 2px; transition: color .15s;
}
.pass-toggle:hover { color: var(--brand); }

/* ── OPTIONS ROW ─────────────────────────────────── */
.options-row {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px; flex-wrap: wrap; gap: 10px;
}
.checkbox-label {
    display: flex; align-items: center; gap: 8px;
    font-size: .82rem; color: var(--muted); cursor: pointer; user-select: none;
}
.checkbox-label input[type=checkbox] {
    width: 16px; height: 16px; border-radius: 4px;
    accent-color: var(--brand); cursor: pointer;
}
.forgot-link { font-size: .82rem; font-weight: 600; color: var(--brand); text-decoration: none; }
.forgot-link:hover { text-decoration: underline; }

/* ── SUBMIT BTN ──────────────────────────────────── */
.btn-submit {
    width: 100%;
    padding: 12px;
    background: var(--brand);
    color: #fff;
    border: none; border-radius: var(--radius);
    font-family: inherit; font-size: .95rem; font-weight: 700;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    transition: background .2s, box-shadow .2s, transform .15s;
    box-shadow: 0 3px 10px rgba(26,86,219,.3);
    margin-bottom: 24px;
}
.btn-submit:hover { background: var(--brand-dk); box-shadow: 0 6px 16px rgba(26,86,219,.35); transform: translateY(-1px); }
.btn-submit:active { transform: none; }
.btn-submit:disabled { opacity: .65; cursor: not-allowed; transform: none; }

/* spinner */
.spinner { width: 18px; height: 18px; border: 2.5px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── DIVIDER ─────────────────────────────────────── */
.divider { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
.divider-line { flex: 1; height: 1px; background: var(--border); }
.divider-text { font-size: .75rem; color: var(--muted); white-space: nowrap; }

/* ── REGISTER LINK ───────────────────────────────── */
.register-block {
    background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 14px 18px;
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; flex-wrap: wrap;
}
.register-block p { font-size: .82rem; color: var(--muted); }
.register-block a {
    font-size: .82rem; font-weight: 700; color: var(--brand);
    text-decoration: none; display: flex; align-items: center; gap: 5px;
    white-space: nowrap;
}
.register-block a:hover { text-decoration: underline; }

/* ── FOOTER ──────────────────────────────────────── */
.rp-footer {
    padding: 14px 40px;
    border-top: 1px solid var(--border);
    text-align: center;
    font-size: .72rem; color: var(--muted);
    flex-shrink: 0;
}

/* ── RESPONSIVE ──────────────────────────────────── */
@media (max-width: 900px) {
    body { grid-template-columns: 1fr; }
    .left-panel { display: none; }
    .right-panel { border-left: none; min-height: 100vh; }
}
@media (max-width: 480px) {
    .rp-body { padding: 32px 24px; }
    .rp-top-bar { padding: 14px 24px; }
    .rp-footer { padding: 14px 24px; }
}
</style>
</head>
<body>

<!-- LEFT PANEL -->
<div class="left-panel">
    <div class="lp-top">
        <div class="lp-logo">
            <img src="assets/images/AIDTRACK-logo.png" alt="AIDTRACK">
        </div>
        <h2 class="lp-headline">
            Financial Assistance.<br>
            <span>Monitored.</span><br>
            Delivered.
        </h2>
        <p class="lp-sub">
            A secure portal for Congressman offices to manage, process, and track financial assistance from submission to release.
        </p>

        <div class="lp-features">
            <div class="lp-feat">
                <div class="lp-feat-icon">
                    <i data-lucide="shield-check" style="width:17px;height:17px"></i>
                </div>
                <div>
                    <div class="lp-feat-title">Secure &amp; Role-Based Access</div>
                    <div class="lp-feat-desc">Separate portals for Admins, Staff, and Beneficiaries with strict access controls.</div>
                </div>
            </div>
            <div class="lp-feat">
                <div class="lp-feat-icon">
                    <i data-lucide="clipboard-check" style="width:17px;height:17px"></i>
                </div>
                <div>
                    <div class="lp-feat-title">End-to-End Request Tracking</div>
                    <div class="lp-feat-desc">Monitor every request from submission through verification, approval, and fund release.</div>
                </div>
            </div>
            <div class="lp-feat">
                <div class="lp-feat-icon">
                    <i data-lucide="bar-chart-2" style="width:17px;height:17px"></i>
                </div>
                <div>
                    <div class="lp-feat-title">Reports &amp; Analytics</div>
                    <div class="lp-feat-desc">Generate detailed reports per barangay, category, or date range instantly.</div>
                </div>
            </div>
        </div>
    </div>

    <div>
        <div class="lp-stats">
            <div>
                <div class="lp-stat-n">1,200+</div>
                <div class="lp-stat-l">Beneficiaries Served</div>
            </div>
            <div>
                <div class="lp-stat-n">350+</div>
                <div class="lp-stat-l">Requests Approved</div>
            </div>
            <div>
                <div class="lp-stat-n">98%</div>
                <div class="lp-stat-l">Satisfaction Rate</div>
            </div>
        </div>
    </div>
</div>

<!-- RIGHT PANEL -->
<div class="right-panel">

    <!-- Top bar -->
    <div class="rp-top-bar">
        <a href="index.php">
            <i data-lucide="arrow-left" style="width:15px;height:15px"></i>
            Back to Home
        </a>
        <span style="font-size:.75rem;color:var(--muted)">AIDTRACK Portal</span>
    </div>

    <!-- Form body -->
    <div class="rp-body">

        <div class="form-logo">
            <div class="form-logo-mark">
                <i data-lucide="landmark" style="width:20px;height:20px;color:white"></i>
            </div>
            <div class="form-logo-text">
                <h1>AIDTRACK</h1>
                <p>Assistance Monitoring System</p>
            </div>
        </div>

        <h2 class="form-heading">Sign in to your account</h2>
        <p class="form-subheading">Enter your registered phone number and password to continue.</p>

        <!-- Error alerts -->
        <?php if ($errors): foreach ($errors as $e): ?>
        <div class="alert alert-error">
            <i data-lucide="alert-circle" style="width:16px;height:16px;flex-shrink:0;margin-top:1px"></i>
            <span><?= htmlspecialchars($e) ?></span>
        </div>
        <?php endforeach; endif; ?>

        <form method="post" id="loginForm" onsubmit="handleSubmit(event)">

            <div class="field-group">
                <div class="field">
                    <label for="phone">Phone Number</label>
                    <div class="input-wrap">
                        <i data-lucide="smartphone" class="input-icon" style="width:16px;height:16px"></i>
                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            placeholder="e.g. 09171234567"
                            value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                            autocomplete="username"
                            class="<?= $errors ? 'error-field' : '' ?>"
                            required
                            autofocus>
                    </div>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <i data-lucide="lock" class="input-icon" style="width:16px;height:16px"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            class="<?= $errors ? 'error-field' : '' ?>"
                            required>
                        <button type="button" class="pass-toggle" id="passToggle" onclick="togglePass()" title="Show/Hide password">
                            <i data-lucide="eye" style="width:16px;height:16px" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="options-row">
                <label class="checkbox-label">
                    <input type="checkbox" name="remember" id="remember">
                    Remember me for 30 days
                </label>
                <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <span id="btnText">Sign In</span>
                <i data-lucide="arrow-right" style="width:16px;height:16px" id="btnArrow"></i>
                <span class="spinner" id="btnSpinner" style="display:none"></span>
            </button>

        </form>

        <div class="divider">
            <div class="divider-line"></div>
            <span class="divider-text">Don't have an account?</span>
            <div class="divider-line"></div>
        </div>

        <div class="register-block">
            <p>New beneficiary or staff member? Register to get started.</p>
            <a href="register.php">
                Create Account
                <i data-lucide="arrow-right" style="width:14px;height:14px"></i>
            </a>
        </div>

    </div>

    <div class="rp-footer">
        © <?= date('Y') ?> AIDTRACK — Financial Assistance Monitoring System. All rights reserved.
    </div>

</div>

<script>
lucide.createIcons();

function togglePass() {
    const inp = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.setAttribute('data-lucide', 'eye-off');
    } else {
        inp.type = 'password';
        icon.setAttribute('data-lucide', 'eye');
    }
    lucide.createIcons();
}

function handleSubmit(e) {
    const btn    = document.getElementById('submitBtn');
    const text   = document.getElementById('btnText');
    const arrow  = document.getElementById('btnArrow');
    const spin   = document.getElementById('btnSpinner');
    btn.disabled = true;
    text.textContent = 'Signing in…';
    arrow.style.display = 'none';
    spin.style.display  = 'block';
}
</script>

</body>
</html>