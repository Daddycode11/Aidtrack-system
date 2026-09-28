<?php require_once 'helpers.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AIDTRACK — Financial Assistance Monitoring System</title>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">

    <!-- Fonts: DM Sans + Fraunces -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,700;0,900;1,700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Alpine.js -->
    <script src="//unpkg.com/alpinejs" defer></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --orange: #FF8A00;
            --orange-dark: #d97200;
            --orange-light: #fff4e6;
            --navy: #0f172a;
            --navy-mid: #1e293b;
            --slate: #334155;
            --muted: #64748b;
            --border: #e2e8f0;
            --bg: #f8fafc;
            --white: #ffffff;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'DM Sans', sans-serif; }
        html { scroll-behavior: smooth; }

        body { background: var(--bg); color: var(--navy); overflow-x: hidden; }

        /* ===== LOADER ===== */
        #loader {
            position: fixed; inset: 0;
            background: var(--navy);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            z-index: 9999;
            transition: opacity 0.6s ease;
        }
        .loader-logo {
            font-family: 'Fraunces', serif;
            font-size: 2rem;
            color: var(--orange);
            letter-spacing: 0.1em;
            animation: pulse 1.2s ease infinite;
        }
        .loader-bar {
            margin-top: 24px;
            width: 200px; height: 3px;
            background: rgba(255,255,255,0.1);
            border-radius: 99px;
            overflow: hidden;
        }
        .loader-bar-fill {
            height: 100%;
            background: var(--orange);
            border-radius: 99px;
            animation: load 1.5s ease forwards;
        }
        @keyframes load { from { width: 0% } to { width: 100% } }
        @keyframes pulse { 0%,100% { opacity: 1 } 50% { opacity: 0.6 } }

        /* ===== NAV ===== */
        header {
            position: fixed; top: 0; left: 0; right: 0;
            z-index: 100;
            padding: 0 2rem;
            height: 72px;
            display: flex; align-items: center;
            transition: background 0.3s, box-shadow 0.3s;
        }
        header.scrolled {
            background: rgba(255,255,255,0.97);
            box-shadow: 0 1px 20px rgba(0,0,0,0.08);
        }
        .nav-inner {
            max-width: 1200px; margin: 0 auto;
            width: 100%;
            display: flex; align-items: center; justify-content: space-between;
        }
        .nav-logo img { height: 40px; }
        .nav-links { display: flex; align-items: center; gap: 2rem; }
        .nav-links a { color: var(--slate); text-decoration: none; font-weight: 500; font-size: 0.95rem; transition: color 0.2s; }
        .nav-links a:hover { color: var(--orange); }
        .btn-login {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--orange); color: white;
            padding: 10px 22px; border-radius: 10px;
            font-weight: 600; font-size: 0.9rem;
            text-decoration: none;
            transition: background 0.2s, transform 0.2s;
            box-shadow: 0 4px 14px rgba(255,138,0,0.35);
        }
        .btn-login:hover { background: var(--orange-dark); transform: translateY(-1px); }
        .btn-outline {
            display: inline-flex; align-items: center; gap: 6px;
            border: 1.5px solid var(--orange); color: var(--orange);
            padding: 9px 20px; border-radius: 10px;
            font-weight: 600; font-size: 0.9rem;
            text-decoration: none;
            transition: background 0.2s, transform 0.2s;
        }
        .btn-outline:hover { background: var(--orange-light); transform: translateY(-1px); }

        /* Mobile nav */
        .hamburger { display: none; background: none; border: 1.5px solid var(--border); border-radius: 8px; padding: 8px; cursor: pointer; }
        .mobile-nav {
            position: fixed; top: 72px; left: 0; right: 0;
            background: white;
            border-top: 1px solid var(--border);
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            z-index: 99;
            padding: 1.5rem;
            display: flex; flex-direction: column; gap: 1rem;
        }
        .mobile-nav a { color: var(--slate); text-decoration: none; font-weight: 500; padding: 0.5rem 0; border-bottom: 1px solid var(--border); }

        /* ===== HERO ===== */
        .hero {
            min-height: 100vh;
            background: var(--navy);
            display: flex; align-items: center;
            position: relative; overflow: hidden;
            padding-top: 72px;
        }
        .hero-grid {
            position: absolute; inset: 0; opacity: 0.06;
            background-image:
                linear-gradient(var(--orange) 1px, transparent 1px),
                linear-gradient(90deg, var(--orange) 1px, transparent 1px);
            background-size: 60px 60px;
        }
        .hero-glow {
            position: absolute;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(255,138,0,0.2) 0%, transparent 70%);
            top: -100px; right: -100px;
            pointer-events: none;
        }
        .hero-glow2 {
            position: absolute;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(255,138,0,0.1) 0%, transparent 70%);
            bottom: 0; left: 10%;
            pointer-events: none;
        }
        .hero-inner {
            max-width: 1200px; margin: 0 auto;
            padding: 5rem 2rem;
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 4rem; align-items: center;
            position: relative; z-index: 1;
        }
        .hero-tag {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,138,0,0.15);
            border: 1px solid rgba(255,138,0,0.3);
            color: var(--orange);
            padding: 6px 16px; border-radius: 99px;
            font-size: 0.8rem; font-weight: 600; letter-spacing: 0.05em;
            text-transform: uppercase; margin-bottom: 1.5rem;
        }
        .hero-title {
            font-family: 'Fraunces', serif;
            font-size: clamp(2.2rem, 5vw, 3.8rem);
            font-weight: 900;
            color: white;
            line-height: 1.1;
            margin-bottom: 1.5rem;
        }
        .hero-title span { color: var(--orange); }
        .hero-desc { color: #94a3b8; font-size: 1.1rem; line-height: 1.7; margin-bottom: 2.5rem; font-weight: 400; }
        .hero-btns { display: flex; gap: 1rem; flex-wrap: wrap; }
        .hero-btn-primary {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--orange); color: white;
            padding: 14px 28px; border-radius: 12px;
            font-weight: 700; font-size: 1rem;
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 6px 20px rgba(255,138,0,0.4);
        }
        .hero-btn-primary:hover { background: var(--orange-dark); transform: translateY(-2px); }
        .hero-btn-ghost {
            display: inline-flex; align-items: center; gap: 8px;
            border: 1.5px solid rgba(255,255,255,0.2); color: white;
            padding: 13px 26px; border-radius: 12px;
            font-weight: 600; font-size: 1rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        .hero-btn-ghost:hover { background: rgba(255,255,255,0.08); }

        .hero-stats {
            display: flex; gap: 2rem; margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .hero-stat-num {
            font-family: 'Fraunces', serif;
            font-size: 2rem; font-weight: 700; color: var(--orange);
        }
        .hero-stat-label { font-size: 0.8rem; color: #64748b; margin-top: 2px; }

        /* Dashboard preview */
        .hero-visual {
            position: relative;
            animation: floatY 4s ease-in-out infinite;
        }
        @keyframes floatY { 0%,100% { transform: translateY(0) } 50% { transform: translateY(-12px) } }
        .dashboard-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 1.5rem;
            backdrop-filter: blur(20px);
        }
        .dash-header {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 1.2rem;
        }
        .dash-dot { width: 10px; height: 10px; border-radius: 50%; }
        .dash-title { color: #94a3b8; font-size: 0.8rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; }
        .dash-metric-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.8rem; margin-bottom: 1.2rem; }
        .dash-metric {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 0.8rem;
        }
        .dash-metric-num { font-family: 'Fraunces', serif; font-size: 1.4rem; font-weight: 700; color: white; }
        .dash-metric-label { font-size: 0.65rem; color: #64748b; margin-top: 2px; }
        .dash-metric-change { font-size: 0.65rem; margin-top: 4px; }
        .up { color: #22c55e; } .down { color: #ef4444; }

        .dash-bar-label { font-size: 0.7rem; color: #64748b; margin-bottom: 0.4rem; }
        .dash-bar-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
        .dash-bar-name { font-size: 0.65rem; color: #94a3b8; width: 70px; flex-shrink: 0; }
        .dash-bar-track { flex: 1; height: 6px; background: rgba(255,255,255,0.06); border-radius: 99px; overflow: hidden; }
        .dash-bar-fill { height: 100%; background: var(--orange); border-radius: 99px; transition: width 1s ease; }
        .dash-bar-val { font-size: 0.65rem; color: #64748b; width: 28px; text-align: right; }

        .dash-list-item {
            display: flex; align-items: center; gap: 10px;
            padding: 0.6rem 0.8rem;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 8px; margin-bottom: 6px;
        }
        .dash-avatar {
            width: 28px; height: 28px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.6rem; font-weight: 700;
        }
        .dash-item-name { font-size: 0.7rem; color: #e2e8f0; flex: 1; }
        .dash-item-status {
            font-size: 0.6rem; font-weight: 600;
            padding: 2px 8px; border-radius: 99px;
        }
        .status-approved { background: rgba(34,197,94,0.15); color: #22c55e; }
        .status-pending { background: rgba(234,179,8,0.15); color: #eab308; }
        .status-review { background: rgba(59,130,246,0.15); color: #60a5fa; }

        /* Floating badge */
        .float-badge {
            position: absolute;
            bottom: -20px; left: -24px;
            background: white;
            border-radius: 14px;
            padding: 0.8rem 1rem;
            box-shadow: 0 12px 40px rgba(0,0,0,0.2);
            display: flex; align-items: center; gap: 10px;
            font-size: 0.8rem;
        }
        .float-badge-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: var(--orange-light);
            display: flex; align-items: center; justify-content: center;
        }
        .float-badge-num { font-family: 'Fraunces', serif; font-size: 1.2rem; font-weight: 700; color: var(--navy); }
        .float-badge-label { font-size: 0.7rem; color: var(--muted); }

        .float-badge2 {
            position: absolute;
            top: -20px; right: -16px;
            background: var(--navy-mid);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            padding: 0.8rem 1rem;
            display: flex; align-items: center; gap: 8px;
        }
        .float-badge2 span { font-size: 0.75rem; color: #94a3b8; }
        .live-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 0 3px rgba(34,197,94,0.2);
            animation: blink 1.5s ease-in-out infinite;
        }
        @keyframes blink { 0%,100% { opacity: 1 } 50% { opacity: 0.4 } }

        /* ===== SECTION SHARED ===== */
        .section-inner { max-width: 1200px; margin: 0 auto; padding: 5rem 2rem; }
        .section-tag {
            display: inline-flex; align-items: center; gap: 6px;
            color: var(--orange); font-size: 0.8rem; font-weight: 700;
            letter-spacing: 0.08em; text-transform: uppercase;
            margin-bottom: 0.8rem;
        }
        .section-title {
            font-family: 'Fraunces', serif;
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            font-weight: 900; color: var(--navy);
            line-height: 1.2; margin-bottom: 1rem;
        }
        .section-desc { color: var(--muted); font-size: 1.05rem; max-width: 540px; line-height: 1.7; }

        /* ===== TRUSTED BY ===== */
        .trusted { background: white; padding: 2rem 0; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
        .trusted-inner { max-width: 1200px; margin: 0 auto; padding: 0 2rem; display: flex; align-items: center; gap: 3rem; flex-wrap: wrap; justify-content: center; }
        .trusted-label { font-size: 0.8rem; font-weight: 600; color: var(--muted); letter-spacing: 0.06em; text-transform: uppercase; white-space: nowrap; }
        .trusted-logos { display: flex; gap: 3rem; align-items: center; flex-wrap: wrap; }
        .trusted-logo { font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 700; color: #cbd5e1; letter-spacing: 0.05em; }

        /* ===== FEATURES ===== */
        .features { background: var(--bg); }
        .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-top: 3rem; }
        .feature-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2rem;
            transition: all 0.3s;
            position: relative; overflow: hidden;
        }
        .feature-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--orange), #ffb74d);
            transform: scaleX(0); transform-origin: left;
            transition: transform 0.3s;
        }
        .feature-card:hover { border-color: transparent; box-shadow: 0 12px 40px rgba(0,0,0,0.1); transform: translateY(-4px); }
        .feature-card:hover::before { transform: scaleX(1); }
        .feature-icon {
            width: 52px; height: 52px; border-radius: 14px;
            background: var(--orange-light);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1.2rem;
            color: var(--orange);
        }
        .feature-title { font-weight: 700; font-size: 1.05rem; color: var(--navy); margin-bottom: 0.5rem; }
        .feature-desc { color: var(--muted); font-size: 0.9rem; line-height: 1.6; }
        .feature-link { display: inline-flex; align-items: center; gap: 4px; color: var(--orange); font-size: 0.85rem; font-weight: 600; margin-top: 1rem; text-decoration: none; }

        /* ===== HOW IT WORKS ===== */
        .how { background: var(--navy); }
        .steps-wrap { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-top: 3rem; position: relative; }
        .steps-wrap::before {
            content: '';
            position: absolute;
            top: 36px; left: 10%; right: 10%;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255,138,0,0.4), transparent);
        }
        .step-card { text-align: center; padding: 2rem 1rem; }
        .step-num {
            width: 60px; height: 60px; border-radius: 50%;
            background: rgba(255,138,0,0.15);
            border: 2px solid rgba(255,138,0,0.4);
            color: var(--orange);
            font-family: 'Fraunces', serif; font-size: 1.4rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.2rem;
            position: relative; z-index: 1;
        }
        .step-icon-wrap {
            width: 56px; height: 56px; border-radius: 16px;
            background: rgba(255,138,0,0.12);
            display: flex; align-items: center; justify-content: center;
            margin: 0.8rem auto 1rem; color: var(--orange);
        }
        .step-title { font-weight: 700; color: white; margin-bottom: 0.5rem; }
        .step-desc { font-size: 0.85rem; color: #64748b; line-height: 1.6; }

        /* ===== STATS STRIP ===== */
        .stats-strip { background: var(--orange); padding: 3.5rem 0; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); max-width: 1200px; margin: 0 auto; padding: 0 2rem; text-align: center; }
        .stat-divider { border-left: 1px solid rgba(255,255,255,0.2); }
        .stat-num { font-family: 'Fraunces', serif; font-size: 3rem; font-weight: 900; color: white; }
        .stat-label { color: rgba(255,255,255,0.75); font-size: 0.9rem; margin-top: 4px; }

        /* ===== ROLES SECTION ===== */
        .roles { background: white; }
        .roles-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-top: 3rem; }
        .role-card {
            border-radius: 20px; padding: 2rem;
            position: relative; overflow: hidden;
        }
        .role-card-admin { background: var(--navy); }
        .role-card-staff { background: linear-gradient(135deg, #1e3a5f, #1e293b); }
        .role-card-bene { background: var(--orange-light); border: 1px solid rgba(255,138,0,0.2); }
        .role-badge {
            display: inline-block;
            padding: 4px 12px; border-radius: 99px;
            font-size: 0.7rem; font-weight: 700; letter-spacing: 0.06em;
            text-transform: uppercase; margin-bottom: 1.2rem;
        }
        .role-badge-admin { background: rgba(255,138,0,0.2); color: var(--orange); }
        .role-badge-staff { background: rgba(96,165,250,0.2); color: #60a5fa; }
        .role-badge-bene { background: rgba(255,138,0,0.15); color: var(--orange-dark); }
        .role-title { font-family: 'Fraunces', serif; font-size: 1.4rem; font-weight: 700; margin-bottom: 0.8rem; }
        .role-title-light { color: white; }
        .role-title-dark { color: var(--navy); }
        .role-list { list-style: none; }
        .role-list li {
            display: flex; align-items: flex-start; gap: 8px;
            font-size: 0.88rem; padding: 0.4rem 0;
        }
        .role-list li span { flex: 1; }
        .role-check-light { color: #22c55e; flex-shrink: 0; margin-top: 2px; }
        .role-check-dark { color: var(--orange); flex-shrink: 0; margin-top: 2px; }
        .role-list-light span { color: #94a3b8; }
        .role-list-dark span { color: var(--slate); }

        /* ===== DOCUMENT STATUS ===== */
        .docstatus { background: var(--bg); }
        .docstatus-inner { display: grid; grid-template-columns: 1fr 1fr; gap: 5rem; align-items: center; }
        .doc-mockup {
            background: white;
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
        }
        .doc-mockup-header { display: flex; align-items: center; gap: 10px; margin-bottom: 1.2rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
        .doc-mockup-title { font-weight: 700; color: var(--navy); font-size: 0.9rem; }
        .doc-mockup-sub { font-size: 0.75rem; color: var(--muted); }
        .doc-status-row {
            display: flex; align-items: center; gap: 12px;
            padding: 0.8rem; border-radius: 12px;
            border: 1px solid var(--border); margin-bottom: 8px;
            transition: background 0.2s;
        }
        .doc-status-row:hover { background: var(--bg); }
        .doc-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .doc-name { font-size: 0.8rem; font-weight: 600; color: var(--navy); }
        .doc-date { font-size: 0.7rem; color: var(--muted); }
        .doc-badge { font-size: 0.65rem; font-weight: 700; padding: 3px 10px; border-radius: 99px; margin-left: auto; white-space: nowrap; }

        .doc-verified { background: rgba(34,197,94,0.1); color: #16a34a; }
        .doc-pending2 { background: rgba(234,179,8,0.1); color: #ca8a04; }
        .doc-missing { background: rgba(239,68,68,0.1); color: #dc2626; }
        .doc-review2 { background: rgba(59,130,246,0.1); color: #2563eb; }

        .progress-row { margin-top: 1.2rem; padding-top: 1rem; border-top: 1px solid var(--border); }
        .progress-label { display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--muted); margin-bottom: 6px; }
        .progress-track { height: 8px; background: var(--bg); border-radius: 99px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--orange), #ffb74d); border-radius: 99px; }

        /* ===== TESTIMONIALS ===== */
        .testimonials { background: white; }
        .testi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-top: 3rem; }
        .testi-card {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 20px; padding: 1.8rem;
            position: relative;
        }
        .testi-stars { color: var(--orange); font-size: 0.9rem; margin-bottom: 1rem; }
        .testi-quote { font-size: 0.95rem; color: var(--slate); line-height: 1.7; font-style: italic; margin-bottom: 1.5rem; }
        .testi-author { display: flex; align-items: center; gap: 10px; }
        .testi-avatar {
            width: 40px; height: 40px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.85rem; color: white;
        }
        .testi-name { font-weight: 700; font-size: 0.88rem; color: var(--navy); }
        .testi-role { font-size: 0.75rem; color: var(--muted); }
        .testi-quote-mark {
            position: absolute; top: 1.5rem; right: 1.5rem;
            font-family: 'Fraunces', serif; font-size: 4rem;
            color: var(--orange); opacity: 0.1; line-height: 1;
        }

        /* ===== FAQ ===== */
        .faq { background: var(--bg); }
        .faq-list { max-width: 780px; margin: 3rem auto 0; }
        .faq-item {
            background: white; border: 1px solid var(--border);
            border-radius: 14px; margin-bottom: 10px;
            overflow: hidden;
        }
        .faq-question {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.2rem 1.5rem;
            cursor: pointer;
            font-weight: 600; color: var(--navy); font-size: 0.95rem;
            transition: background 0.2s;
        }
        .faq-question:hover { background: var(--bg); }
        .faq-answer {
            padding: 0 1.5rem 1.2rem;
            color: var(--muted); font-size: 0.9rem; line-height: 1.7;
        }
        .faq-icon { color: var(--orange); transition: transform 0.3s; flex-shrink: 0; }
        .faq-icon.open { transform: rotate(45deg); }

        /* ===== VIDEO ===== */
        .video-section { background: var(--navy); }
        .video-wrap { max-width: 900px; margin: 3rem auto 0; border-radius: 24px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 30px 80px rgba(0,0,0,0.4); }
        .video-ratio { position: relative; padding-bottom: 56.25%; height: 0; }
        .video-ratio iframe { position: absolute; inset: 0; width: 100%; height: 100%; }

        /* ===== CTA ===== */
        .cta { background: linear-gradient(135deg, var(--orange) 0%, #ffb74d 100%); }
        .cta-inner { text-align: center; max-width: 700px; margin: 0 auto; padding: 6rem 2rem; }
        .cta-title { font-family: 'Fraunces', serif; font-size: clamp(2rem, 5vw, 3.2rem); font-weight: 900; color: white; margin-bottom: 1rem; }
        .cta-desc { color: rgba(255,255,255,0.85); font-size: 1.05rem; line-height: 1.7; margin-bottom: 2.5rem; }
        .cta-btns { display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap; }
        .btn-white { background: white; color: var(--orange); font-weight: 700; padding: 14px 30px; border-radius: 12px; text-decoration: none; font-size: 1rem; transition: all 0.2s; box-shadow: 0 6px 20px rgba(0,0,0,0.15); }
        .btn-white:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .btn-ghost-white { border: 2px solid rgba(255,255,255,0.5); color: white; font-weight: 600; padding: 12px 28px; border-radius: 12px; text-decoration: none; font-size: 1rem; transition: all 0.2s; }
        .btn-ghost-white:hover { background: rgba(255,255,255,0.15); }

        /* ===== FOOTER ===== */
        footer { background: var(--navy); }
        .footer-inner { max-width: 1200px; margin: 0 auto; padding: 4rem 2rem 2rem; }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 3rem; margin-bottom: 3rem; }
        .footer-brand-desc { color: #64748b; font-size: 0.88rem; line-height: 1.7; margin-top: 1rem; max-width: 260px; }
        .footer-col-title { font-weight: 700; color: white; font-size: 0.85rem; letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 1.2rem; }
        .footer-links { list-style: none; }
        .footer-links li { margin-bottom: 0.6rem; }
        .footer-links a { color: #64748b; text-decoration: none; font-size: 0.88rem; transition: color 0.2s; }
        .footer-links a:hover { color: var(--orange); }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,0.06); padding-top: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
        .footer-copy { color: #475569; font-size: 0.82rem; }
        .footer-socials { display: flex; gap: 0.8rem; }
        .social-btn { width: 36px; height: 36px; border-radius: 10px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: center; color: #64748b; transition: all 0.2s; text-decoration: none; }
        .social-btn:hover { background: rgba(255,138,0,0.15); border-color: rgba(255,138,0,0.3); color: var(--orange); }

        /* ===== ANIMATIONS ===== */
        .fade-in { opacity: 0; transform: translateY(24px); transition: opacity 0.7s ease, transform 0.7s ease; }
        .fade-in.visible { opacity: 1; transform: none; }
        .fade-in-delay-1 { transition-delay: 0.15s; }
        .fade-in-delay-2 { transition-delay: 0.3s; }
        .fade-in-delay-3 { transition-delay: 0.45s; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .features-grid { grid-template-columns: repeat(2, 1fr); }
            .roles-grid { grid-template-columns: 1fr 1fr; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .hamburger { display: flex; }
            .hero-inner { grid-template-columns: 1fr; }
            .hero-visual { display: none; }
            .features-grid { grid-template-columns: 1fr; }
            .steps-wrap { grid-template-columns: 1fr 1fr; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .roles-grid { grid-template-columns: 1fr; }
            .docstatus-inner { grid-template-columns: 1fr; }
            .testi-grid { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 480px) {
            .steps-wrap { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body x-data="{ mobileNav: false, faq: null }">

    <!-- LOADER -->
    <div id="loader">
        <div class="loader-logo">AIDTRACK</div>
        <div class="loader-bar"><div class="loader-bar-fill"></div></div>
    </div>
    <script>
        window.addEventListener('load', () => {
            const l = document.getElementById('loader');
            setTimeout(() => { l.style.opacity = '0'; setTimeout(() => l.style.display = 'none', 600); }, 800);
        });
    </script>

    <!-- HEADER -->
    <header id="header">
        <div class="nav-inner">
            <a href="index.php" class="nav-logo">
                <img src="assets/images/AIDTRACK-logo.png" alt="AIDTRACK">
            </a>

            <nav class="nav-links">
                <a href="#features">Features</a>
                <a href="#how-it-works">How It Works</a>
                <a href="#roles">For Whom</a>
                <a href="#testimonials">Testimonials</a>
                <a href="#faq">FAQ</a>
                <a href="login.php" class="btn-login">
                    <i data-lucide="log-in" style="width:16px;height:16px"></i> Login
                </a>
                <a href="register.php" class="btn-outline">
                    <i data-lucide="user-plus" style="width:16px;height:16px"></i> Register
                </a>
            </nav>

            <button class="hamburger" @click="mobileNav = !mobileNav">
                <i data-lucide="menu" style="width:20px;height:20px;color:#334155"></i>
            </button>
        </div>
    </header>

    <!-- MOBILE NAV -->
    <div class="mobile-nav" x-show="mobileNav" x-transition @click.away="mobileNav=false">
        <a href="#features" @click="mobileNav=false">Features</a>
        <a href="#how-it-works" @click="mobileNav=false">How It Works</a>
        <a href="#roles" @click="mobileNav=false">For Whom</a>
        <a href="#testimonials" @click="mobileNav=false">Testimonials</a>
        <a href="#faq" @click="mobileNav=false">FAQ</a>
        <div style="display:flex;gap:0.8rem;margin-top:0.5rem;">
            <a href="login.php" class="btn-login" style="flex:1;justify-content:center">Login</a>
            <a href="register.php" class="btn-outline" style="flex:1;justify-content:center">Register</a>
        </div>
    </div>

    <!-- HERO -->
    <section class="hero">
        <div class="hero-grid"></div>
        <div class="hero-glow"></div>
        <div class="hero-glow2"></div>

        <div class="hero-inner">
            <!-- Left -->
            <div>
                <div class="hero-tag">
                    <i data-lucide="shield-check" style="width:13px;height:13px"></i>
                    Official System of Congressman's Office
                </div>
                <h1 class="hero-title">
                    Financial Aid.<br><span>Tracked.</span><br>Delivered.
                </h1>
                <p class="hero-desc">
                    AIDTRACK streamlines every step of financial assistance — from request submission and document verification to approval and fund release — all in one secure platform.
                </p>
                <div class="hero-btns">
                    <a href="register.php" class="hero-btn-primary">
                        <i data-lucide="arrow-right-circle" style="width:18px;height:18px"></i>
                        Get Started Free
                    </a>
                    <a href="#how-it-works" class="hero-btn-ghost">
                        <i data-lucide="play-circle" style="width:18px;height:18px"></i>
                        See How It Works
                    </a>
                </div>
                <div class="hero-stats">
                    <div>
                        <div class="hero-stat-num counter" data-target="1200">0</div>
                        <div class="hero-stat-label">Beneficiaries Served</div>
                    </div>
                    <div>
                        <div class="hero-stat-num counter" data-target="350">0</div>
                        <div class="hero-stat-label">Requests Approved</div>
                    </div>
                    <div>
                        <div class="hero-stat-num counter" data-target="98">0</div>
                        <div class="hero-stat-label">% Satisfaction</div>
                    </div>
                </div>
            </div>

            <!-- Right: Dashboard Preview -->
            <div class="hero-visual">
                <div class="float-badge2">
                    <div class="live-dot"></div>
                    <span>System Live</span>
                </div>

                <div class="dashboard-card">
                    <div class="dash-header">
                        <div class="dash-dot" style="background:#ff5f57"></div>
                        <div class="dash-dot" style="background:#ffbd2e"></div>
                        <div class="dash-dot" style="background:#28c840"></div>
                        <span class="dash-title" style="margin-left:8px">AIDTRACK Dashboard</span>
                    </div>

                    <div class="dash-metric-row">
                        <div class="dash-metric">
                            <div class="dash-metric-num">1,248</div>
                            <div class="dash-metric-label">Total Requests</div>
                            <div class="dash-metric-change up">↑ 12% this month</div>
                        </div>
                        <div class="dash-metric">
                            <div class="dash-metric-num">867</div>
                            <div class="dash-metric-label">Approved</div>
                            <div class="dash-metric-change up">↑ 8%</div>
                        </div>
                        <div class="dash-metric">
                            <div class="dash-metric-num">142</div>
                            <div class="dash-metric-label">Pending</div>
                            <div class="dash-metric-change down">↓ 3%</div>
                        </div>
                    </div>

                    <div style="margin-bottom:1rem">
                        <div class="dash-bar-label">Assistance by Category</div>
                        <div class="dash-bar-row">
                            <div class="dash-bar-name">Medical</div>
                            <div class="dash-bar-track"><div class="dash-bar-fill" style="width:78%"></div></div>
                            <div class="dash-bar-val">78%</div>
                        </div>
                        <div class="dash-bar-row">
                            <div class="dash-bar-name">Burial</div>
                            <div class="dash-bar-track"><div class="dash-bar-fill" style="width:45%;background:#a78bfa"></div></div>
                            <div class="dash-bar-val">45%</div>
                        </div>
                    </div>

                    <div class="dash-bar-label">Recent Requests</div>
                    <div class="dash-list-item">
                        <div class="dash-avatar" style="background:rgba(255,138,0,0.2);color:#FF8A00">MR</div>
                        <div class="dash-item-name">Maria Reyes — Medical Aid</div>
                        <span class="dash-item-status status-approved">Approved</span>
                    </div>
                    <div class="dash-list-item">
                        <div class="dash-avatar" style="background:rgba(234,179,8,0.2);color:#eab308">JS</div>
                        <div class="dash-item-name">Juan Santos — Education</div>
                        <span class="dash-item-status status-pending">Pending</span>
                    </div>
                    <div class="dash-list-item">
                        <div class="dash-avatar" style="background:rgba(96,165,250,0.2);color:#60a5fa">AL</div>
                        <div class="dash-item-name">Ana Lim — Medical</div>
                        <span class="dash-item-status status-review">In Review</span>
                    </div>
                </div>

                <div class="float-badge">
                    <div class="float-badge-icon">
                        <i data-lucide="trending-up" style="width:18px;height:18px;color:#FF8A00"></i>
                    </div>
                    <div>
                        <div class="float-badge-num">₱4.2M</div>
                        <div class="float-badge-label">Total Disbursed</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- TRUSTED BY -->
    <div class="trusted">
        <div class="trusted-inner">
            <span class="trusted-label">Trusted by offices in</span>
            <div class="trusted-logos">
                <span class="trusted-logo">Calintaan</span>
                <span class="trusted-logo">Rizal</span>
                <span class="trusted-logo">San Jose</span>
                <span class="trusted-logo">Paluan</span>
                <span class="trusted-logo">Sta. Cruz</span>
            </div>
        </div>
    </div>

    <!-- FEATURES -->
    <section id="features" class="features">
        <div class="section-inner">
            <div>
                <div class="section-tag fade-in"><i data-lucide="sparkles" style="width:14px;height:14px"></i> Features</div>
                <h2 class="section-title fade-in">Everything you need to<br>manage aid effectively</h2>
                <p class="section-desc fade-in">Built for Congressman offices and local government units to modernize financial assistance operations.</p>
            </div>

            <div class="features-grid">
                <div class="feature-card fade-in">
                    <div class="feature-icon"><i data-lucide="layout-dashboard" style="width:24px;height:24px"></i></div>
                    <div class="feature-title">Admin Dashboard</div>
                    <div class="feature-desc">Real-time overview of all requests, beneficiaries, approvals, and fund disbursements at a glance.</div>
                    <a href="login.php" class="feature-link">Explore <i data-lucide="arrow-right" style="width:14px;height:14px"></i></a>
                </div>
                <div class="feature-card fade-in fade-in-delay-1">
                    <div class="feature-icon"><i data-lucide="clipboard-list" style="width:24px;height:24px"></i></div>
                    <div class="feature-title">Request Tracking</div>
                    <div class="feature-desc">Monitor every assistance request from submission to release with a detailed audit trail.</div>
                    <a href="login.php" class="feature-link">Explore <i data-lucide="arrow-right" style="width:14px;height:14px"></i></a>
                </div>
                <div class="feature-card fade-in fade-in-delay-2">
                    <div class="feature-icon"><i data-lucide="folder-lock" style="width:24px;height:24px"></i></div>
                    <div class="feature-title">Document Management</div>
                    <div class="feature-desc">Secure upload, storage, and verification of supporting documents with status tracking per file.</div>
                    <a href="login.php" class="feature-link">Explore <i data-lucide="arrow-right" style="width:14px;height:14px"></i></a>
                </div>
                <div class="feature-card fade-in">
                    <div class="feature-icon"><i data-lucide="users" style="width:24px;height:24px"></i></div>
                    <div class="feature-title">Beneficiary Registry</div>
                    <div class="feature-desc">Centralized database of all registered beneficiaries with history, eligibility, and assistance records.</div>
                    <a href="login.php" class="feature-link">Explore <i data-lucide="arrow-right" style="width:14px;height:14px"></i></a>
                </div>
                <div class="feature-card fade-in fade-in-delay-1">
                    <div class="feature-icon"><i data-lucide="bar-chart-3" style="width:24px;height:24px"></i></div>
                    <div class="feature-title">Reports & Analytics</div>
                    <div class="feature-desc">Generate detailed reports by barangay, category, date, or status. Export to PDF and Excel formats.</div>
                    <a href="login.php" class="feature-link">Explore <i data-lucide="arrow-right" style="width:14px;height:14px"></i></a>
                </div>
                <div class="feature-card fade-in fade-in-delay-2">
                    <div class="feature-icon"><i data-lucide="bell-ring" style="width:24px;height:24px"></i></div>
                    <div class="feature-title">Notifications</div>
                    <div class="feature-desc">Automated status updates notify beneficiaries and staff whenever a request is updated or approved.</div>
                    <a href="login.php" class="feature-link">Explore <i data-lucide="arrow-right" style="width:14px;height:14px"></i></a>
                </div>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section id="how-it-works" class="how">
        <div class="section-inner" style="padding-bottom:5rem">
            <div style="text-align:center">
                <div class="section-tag fade-in" style="justify-content:center;color:rgba(255,138,0,0.9)"><i data-lucide="git-branch" style="width:14px;height:14px"></i> Process</div>
                <h2 class="section-title fade-in" style="color:white">Simple 4-step process</h2>
                <p class="section-desc fade-in" style="color:#64748b;margin:0 auto">From request to release, every step is logged and traceable.</p>
            </div>
            <div class="steps-wrap">
                <div class="step-card fade-in">
                    <div class="step-num">01</div>
                    <div class="step-icon-wrap"><i data-lucide="file-plus-2" style="width:26px;height:26px"></i></div>
                    <div class="step-title">Submit Request</div>
                    <div class="step-desc">Beneficiaries fill out the online assistance form and upload required documents through their portal.</div>
                </div>
                <div class="step-card fade-in fade-in-delay-1">
                    <div class="step-num">02</div>
                    <div class="step-icon-wrap"><i data-lucide="search-check" style="width:26px;height:26px"></i></div>
                    <div class="step-title">Validate & Verify</div>
                    <div class="step-desc">Staff reviews documents, checks eligibility, and flags any incomplete or invalid submissions.</div>
                </div>
                <div class="step-card fade-in fade-in-delay-2">
                    <div class="step-num">03</div>
                    <div class="step-icon-wrap"><i data-lucide="shield-check" style="width:26px;height:26px"></i></div>
                    <div class="step-title">Admin Approval</div>
                    <div class="step-desc">Qualified requests are reviewed and approved by the administrator. Notifications are sent instantly.</div>
                </div>
                <div class="step-card fade-in fade-in-delay-3">
                    <div class="step-num">04</div>
                    <div class="step-icon-wrap"><i data-lucide="hand-coins" style="width:26px;height:26px"></i></div>
                    <div class="step-title">Release & Log</div>
                    <div class="step-desc">Assistance is disbursed and the transaction is automatically recorded in the audit log for accountability.</div>
                </div>
            </div>
        </div>
    </section>

    <!-- STATS STRIP -->
    <div class="stats-strip">
        <div class="stats-grid">
            <div class="fade-in">
                <div class="stat-num counter" data-target="1200">0</div>
                <div class="stat-label">Beneficiaries Served</div>
            </div>
            <div class="stat-divider fade-in fade-in-delay-1">
                <div class="stat-num counter" data-target="350">0</div>
                <div class="stat-label">Requests Approved</div>
            </div>
            <div class="stat-divider fade-in fade-in-delay-2">
                <div class="stat-num counter" data-target="42">0</div>
                <div class="stat-label">Barangays Covered</div>
            </div>
            <div class="stat-divider fade-in fade-in-delay-3">
                <div class="stat-num counter" data-target="98">0</div>
                <div class="stat-label">% Satisfaction Rate</div>
            </div>
        </div>
    </div>

    <!-- ROLES SECTION -->
    <section id="roles" class="roles">
        <div class="section-inner">
            <div style="text-align:center">
                <div class="section-tag fade-in" style="justify-content:center"><i data-lucide="users-round" style="width:14px;height:14px"></i> Who It's For</div>
                <h2 class="section-title fade-in" style="text-align:center">Designed for every role</h2>
                <p class="section-desc fade-in" style="margin:0 auto">AIDTRACK serves three key user types — each with a tailored experience.</p>
            </div>
            <div class="roles-grid">
                <!-- Admin -->
                <div class="role-card role-card-admin fade-in">
                    <div class="role-badge role-badge-admin">Admin</div>
                    <div class="role-title role-title-light">Congressman<br>Office Admin</div>
                    <ul class="role-list role-list-light">
                        <li><i data-lucide="check" class="role-check-light" style="width:15px;height:15px"></i><span>Full dashboard with analytics</span></li>
                        <li><i data-lucide="check" class="role-check-light" style="width:15px;height:15px"></i><span>Approve or reject requests</span></li>
                        <li><i data-lucide="check" class="role-check-light" style="width:15px;height:15px"></i><span>Manage staff accounts</span></li>
                        <li><i data-lucide="check" class="role-check-light" style="width:15px;height:15px"></i><span>Generate and export reports</span></li>
                        <li><i data-lucide="check" class="role-check-light" style="width:15px;height:15px"></i><span>View complete audit trail</span></li>
                    </ul>
                </div>
                <!-- Staff -->
                <div class="role-card role-card-staff fade-in fade-in-delay-1">
                    <div class="role-badge role-badge-staff">Staff</div>
                    <div class="role-title role-title-light">Office Staff<br>& Validators</div>
                    <ul class="role-list role-list-light">
                        <li><i data-lucide="check" class="role-check-light" style="width:15px;height:15px"></i><span>View and process requests</span></li>
                        <li><i data-lucide="check" class="role-check-light" style="width:15px;height:15px"></i><span>Verify uploaded documents</span></li>
                        <li><i data-lucide="check" class="role-check-light" style="width:15px;height:15px"></i><span>Update request status</span></li>
                        <li><i data-lucide="check" class="role-check-light" style="width:15px;height:15px"></i><span>Communicate with beneficiaries</span></li>
                        <li><i data-lucide="check" class="role-check-light" style="width:15px;height:15px"></i><span>Flag incomplete submissions</span></li>
                    </ul>
                </div>
                <!-- Beneficiary -->
                <div class="role-card role-card-bene fade-in fade-in-delay-2">
                    <div class="role-badge role-badge-bene">Beneficiary</div>
                    <div class="role-title role-title-dark">Residents &<br>Constituents</div>
                    <ul class="role-list role-list-dark">
                        <li><i data-lucide="check" class="role-check-dark" style="width:15px;height:15px"></i><span>Submit assistance requests</span></li>
                        <li><i data-lucide="check" class="role-check-dark" style="width:15px;height:15px"></i><span>Upload supporting documents</span></li>
                        <li><i data-lucide="check" class="role-check-dark" style="width:15px;height:15px"></i><span>Track request status live</span></li>
                        <li><i data-lucide="check" class="role-check-dark" style="width:15px;height:15px"></i><span>Receive status notifications</span></li>
                        <li><i data-lucide="check" class="role-check-dark" style="width:15px;height:15px"></i><span>View assistance history</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- DOCUMENT STATUS -->
    <section class="docstatus">
        <div class="section-inner">
            <div class="docstatus-inner">
                <div>
                    <div class="section-tag fade-in"><i data-lucide="file-check-2" style="width:14px;height:14px"></i> Document Tracking</div>
                    <h2 class="section-title fade-in">Know exactly where<br>every document stands</h2>
                    <p class="section-desc fade-in">No more guesswork. AIDTRACK gives real-time visibility into document verification status — so beneficiaries and staff always know what's needed.</p>
                    <div style="margin-top:2rem;display:flex;flex-direction:column;gap:1rem;" class="fade-in">
                        <div style="display:flex;align-items:flex-start;gap:12px">
                            <div style="width:40px;height:40px;border-radius:12px;background:rgba(34,197,94,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#22c55e">
                                <i data-lucide="check-circle-2" style="width:20px;height:20px"></i>
                            </div>
                            <div>
                                <div style="font-weight:700;color:var(--navy);margin-bottom:3px;font-size:0.9rem">Verified Documents</div>
                                <div style="font-size:0.85rem;color:var(--muted)">Confirmed and accepted by the validation team, ready for processing.</div>
                            </div>
                        </div>
                        <div style="display:flex;align-items:flex-start;gap:12px">
                            <div style="width:40px;height:40px;border-radius:12px;background:rgba(234,179,8,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#eab308">
                                <i data-lucide="clock" style="width:20px;height:20px"></i>
                            </div>
                            <div>
                                <div style="font-weight:700;color:var(--navy);margin-bottom:3px;font-size:0.9rem">Awaiting Review</div>
                                <div style="font-size:0.85rem;color:var(--muted)">Submitted and queued for staff verification review.</div>
                            </div>
                        </div>
                        <div style="display:flex;align-items:flex-start;gap:12px">
                            <div style="width:40px;height:40px;border-radius:12px;background:rgba(239,68,68,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#ef4444">
                                <i data-lucide="alert-circle" style="width:20px;height:20px"></i>
                            </div>
                            <div>
                                <div style="font-weight:700;color:var(--navy);margin-bottom:3px;font-size:0.9rem">Missing or Invalid</div>
                                <div style="font-size:0.85rem;color:var(--muted)">Requires re-submission or replacement before the request can proceed.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="doc-mockup fade-in">
                    <div class="doc-mockup-header">
                        <div>
                            <div class="doc-mockup-title">Request #AT-2024-0892</div>
                            <div class="doc-mockup-sub">Maria Santos — Medical Assistance</div>
                        </div>
                    </div>

                    <div class="doc-status-row">
                        <div class="doc-icon" style="background:rgba(34,197,94,0.1)">
                            <i data-lucide="file-text" style="width:18px;height:18px;color:#22c55e"></i>
                        </div>
                        <div>
                            <div class="doc-name">Medical Certificate</div>
                            <div class="doc-date">Uploaded Jan 15, 2025</div>
                        </div>
                        <div class="doc-badge doc-verified">Verified</div>
                    </div>
                    <div class="doc-status-row">
                        <div class="doc-icon" style="background:rgba(34,197,94,0.1)">
                            <i data-lucide="id-card" style="width:18px;height:18px;color:#22c55e"></i>
                        </div>
                        <div>
                            <div class="doc-name">Valid Government ID</div>
                            <div class="doc-date">Uploaded Jan 15, 2025</div>
                        </div>
                        <div class="doc-badge doc-verified">Verified</div>
                    </div>
                    <div class="doc-status-row">
                        <div class="doc-icon" style="background:rgba(234,179,8,0.1)">
                            <i data-lucide="receipt" style="width:18px;height:18px;color:#eab308"></i>
                        </div>
                        <div>
                            <div class="doc-name">Hospital Bill / SOA</div>
                            <div class="doc-date">Uploaded Jan 16, 2025</div>
                        </div>
                        <div class="doc-badge doc-pending2">Pending Review</div>
                    </div>
                    <div class="doc-status-row">
                        <div class="doc-icon" style="background:rgba(239,68,68,0.1)">
                            <i data-lucide="home" style="width:18px;height:18px;color:#ef4444"></i>
                        </div>
                        <div>
                            <div class="doc-name">Certificate of Indigency</div>
                            <div class="doc-date">Not yet uploaded</div>
                        </div>
                        <div class="doc-badge doc-missing">Missing</div>
                    </div>

                    <div class="progress-row">
                        <div class="progress-label">
                            <span>Completion</span>
                            <span style="color:var(--orange);font-weight:700">75%</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" style="width:75%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- TESTIMONIALS -->
    <section id="testimonials" class="testimonials">
        <div class="section-inner">
            <div style="text-align:center">
                <div class="section-tag fade-in" style="justify-content:center"><i data-lucide="message-square-quote" style="width:14px;height:14px"></i> Testimonials</div>
                <h2 class="section-title fade-in" style="text-align:center">What people are saying</h2>
            </div>
            <div class="testi-grid">
                <div class="testi-card fade-in">
                    <div class="testi-quote-mark">"</div>
                    <div class="testi-stars">★★★★★</div>
                    <p class="testi-quote">"Napadali talaga ang proseso ng pagkuha ng tulong. Dati maghintay pa ng matagal bago malaman ang status, ngayon real-time na makikita."</p>
                    <div class="testi-author">
                        <div class="testi-avatar" style="background:linear-gradient(135deg,#FF8A00,#ffb74d)">MR</div>
                        <div>
                            <div class="testi-name">Maria Reyes</div>
                            <div class="testi-role">Resident, Calintaan</div>
                        </div>
                    </div>
                </div>
                <div class="testi-card fade-in fade-in-delay-1">
                    <div class="testi-quote-mark">"</div>
                    <div class="testi-stars">★★★★★</div>
                    <p class="testi-quote">"Mas transparent at mas organized na ang distribution ng ayuda. Lahat ng records nandoon at maayos ang tracking ng bawat request."</p>
                    <div class="testi-author">
                        <div class="testi-avatar" style="background:linear-gradient(135deg,#3b82f6,#60a5fa)">JS</div>
                        <div>
                            <div class="testi-name">Juan Santos</div>
                            <div class="testi-role">Barangay Staff, San Jose</div>
                        </div>
                    </div>
                </div>
                <div class="testi-card fade-in fade-in-delay-2">
                    <div class="testi-quote-mark">"</div>
                    <div class="testi-stars">★★★★★</div>
                    <p class="testi-quote">"Sobrang laking tulong sa aming monitoring at reporting. Nakaka-generate ng reports agad at lahat ay documented. Highly recommended!"</p>
                    <div class="testi-author">
                        <div class="testi-avatar" style="background:linear-gradient(135deg,#22c55e,#4ade80)">AL</div>
                        <div>
                            <div class="testi-name">Ana Lim</div>
                            <div class="testi-role">Office Personnel, Rizal</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="faq">
        <div class="section-inner">
            <div style="text-align:center">
                <div class="section-tag fade-in" style="justify-content:center"><i data-lucide="circle-help" style="width:14px;height:14px"></i> FAQ</div>
                <h2 class="section-title fade-in" style="text-align:center">Frequently asked questions</h2>
            </div>
            <div class="faq-list">
                <div class="faq-item" x-data="{open:false}">
                    <div class="faq-question" @click="open=!open">
                        Who can register on AIDTRACK?
                        <i data-lucide="plus" class="faq-icon" :class="{open}" style="width:18px;height:18px"></i>
                    </div>
                    <div class="faq-answer" x-show="open" x-transition>
                        AIDTRACK is open to residents and constituents under the Congressman's jurisdiction who wish to apply for financial assistance. Office staff and administrators are registered by the system administrator.
                    </div>
                </div>
                <div class="faq-item" x-data="{open:false}">
                    <div class="faq-question" @click="open=!open">
                        What types of financial assistance can I apply for?
                        <i data-lucide="plus" class="faq-icon" :class="{open}" style="width:18px;height:18px"></i>
                    </div>
                    <div class="faq-answer" x-show="open" x-transition>
                        AIDTRACK currently supports Medical and Burial assistance. Available categories may expand depending on current programs of the MSWDO office.
                    </div>
                </div>
                <div class="faq-item" x-data="{open:false}">
                    <div class="faq-question" @click="open=!open">
                        What documents do I need to submit?
                        <i data-lucide="plus" class="faq-icon" :class="{open}" style="width:18px;height:18px"></i>
                    </div>
                    <div class="faq-answer" x-show="open" x-transition>
                        Required documents vary per assistance type. Generally, you'll need a valid government ID, a Certificate of Indigency, and supporting documents (e.g., medical certificate, hospital bill, enrollment form). The system will guide you through each requirement.
                    </div>
                </div>
                <div class="faq-item" x-data="{open:false}">
                    <div class="faq-question" @click="open=!open">
                        How long does the approval process take?
                        <i data-lucide="plus" class="faq-icon" :class="{open}" style="width:18px;height:18px"></i>
                    </div>
                    <div class="faq-answer" x-show="open" x-transition>
                        Processing time varies, but AIDTRACK significantly reduces delays by digitizing and automating the review workflow. Most requests are processed within 3–7 working days after complete document submission.
                    </div>
                </div>
                <div class="faq-item" x-data="{open:false}">
                    <div class="faq-question" @click="open=!open">
                        Can I track my request status after submitting?
                        <i data-lucide="plus" class="faq-icon" :class="{open}" style="width:18px;height:18px"></i>
                    </div>
                    <div class="faq-answer" x-show="open" x-transition>
                        Yes. After logging into your account, you can view the real-time status of your request — including document review status, approval, and release — all from your personal dashboard.
                    </div>
                </div>
                <div class="faq-item" x-data="{open:false}">
                    <div class="faq-question" @click="open=!open">
                        Is my personal data secure?
                        <i data-lucide="plus" class="faq-icon" :class="{open}" style="width:18px;height:18px"></i>
                    </div>
                    <div class="faq-answer" x-show="open" x-transition>
                        Absolutely. AIDTRACK uses encrypted storage and access control to ensure your personal information and documents are protected. Only authorized personnel can view your submitted data.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- VIDEO -->
    <section class="video-section">
        <div class="section-inner" style="text-align:center">
            <div class="section-tag fade-in" style="justify-content:center;color:rgba(255,138,0,0.9)"><i data-lucide="play-circle" style="width:14px;height:14px"></i> Walkthrough</div>
            <h2 class="section-title fade-in" style="color:white;text-align:center">See AIDTRACK in action</h2>
            <p class="section-desc fade-in" style="color:#64748b;margin:0 auto">Watch how we simplify the entire assistance lifecycle from start to finish.</p>
            <div class="video-wrap fade-in">
                <div class="video-ratio">
                    <iframe src="https://www.youtube.com/embed/YOUR_VIDEO_ID"
                            title="AIDTRACK Walkthrough"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen></iframe>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta">
        <div class="cta-inner">
            <h2 class="cta-title fade-in">Ready to transform aid management?</h2>
            <p class="cta-desc fade-in">Join offices across the district already using AIDTRACK to serve constituents faster, transparently, and more efficiently.</p>
            <div class="cta-btns fade-in">
                <a href="register.php" class="btn-white">Create an Account</a>
                <a href="login.php" class="btn-ghost-white">Sign In</a>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <div class="footer-inner">
            <div class="footer-grid">
                <div>
                    <img src="assets/images/AIDTRACK-logo.png" alt="AIDTRACK" style="height:36px;filter:brightness(0) invert(1);opacity:0.8">
                    <p class="footer-brand-desc">Financial Assistance Monitoring System for Congressman offices and local government units. Transparent, efficient, and secure.</p>
                </div>
                <div>
                    <div class="footer-col-title">System</div>
                    <ul class="footer-links">
                        <li><a href="#features">Features</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="#roles">For Whom</a></li>
                        <li><a href="register.php">Register</a></li>
                    </ul>
                </div>
                <div>
                    <div class="footer-col-title">Account</div>
                    <ul class="footer-links">
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Create Account</a></li>
                        <li><a href="#">Forgot Password</a></li>
                    </ul>
                </div>
                <div>
                    <div class="footer-col-title">Support</div>
                    <ul class="footer-links">
                        <li><a href="#faq">FAQ</a></li>
                        <li><a href="#">Contact Office</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Use</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <span class="footer-copy">© <?=date('Y')?> AIDTRACK — Financial Assistance Monitoring System. All rights reserved.</span>
                <div class="footer-socials">
                    <a href="#" class="social-btn"><i data-lucide="facebook" style="width:15px;height:15px"></i></a>
                    <a href="#" class="social-btn"><i data-lucide="twitter" style="width:15px;height:15px"></i></a>
                    <a href="#" class="social-btn"><i data-lucide="mail" style="width:15px;height:15px"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Scroll: header tint
        const header = document.getElementById('header');
        const heroSection = document.querySelector('.hero');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 20) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Scroll animations
        const observer = new IntersectionObserver(entries => {
            entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
        }, { threshold: 0.15 });
        document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));

        // Counter
        let countersRan = false;
        const counterObserver = new IntersectionObserver(entries => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    document.querySelectorAll('.counter').forEach(counter => {
                        const target = +counter.dataset.target;
                        let count = 0;
                        const step = Math.ceil(target / 120);
                        const update = () => {
                            count = Math.min(count + step, target);
                            counter.textContent = count.toLocaleString();
                            if (count < target) requestAnimationFrame(update);
                        };
                        update();
                    });
                    counterObserver.disconnect();
                }
            });
        }, { threshold: 0.3 });
        const firstCounter = document.querySelector('.counter');
        if (firstCounter) counterObserver.observe(firstCounter);

        // Lucide icons
        lucide.createIcons();
    </script>

</body>
</html>