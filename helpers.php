<?php
// helpers.php
require_once __DIR__ . '/config.php';
session_start();

// Safe redirect
function redirect($url) {
    header("Location: $url");
    exit;
}

// Require any authenticated login
function require_login() {
    if (empty($_SESSION['user'])) redirect('../login.php');
}

// Require admin OR super_admin
function require_admin() {
    if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'super_admin'])) {
        redirect('../login.php');
    }
}

// Require super_admin exclusively
function require_super_admin() {
    if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'super_admin') {
        if (!empty($_SESSION['user']) && in_array($_SESSION['user']['role'], ['admin'])) {
            redirect('dashboard.php?err=superadmin_only');
        }
        redirect('../login.php');
    }
}

// Require client (active accounts only)
function require_client() {
    if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'client') {
        redirect('../login.php');
    }
    if (($_SESSION['user']['status'] ?? 'active') === 'suspended') {
        session_destroy();
        redirect('../login.php?err=suspended');
    }
}

// Role checks
function is_super_admin(): bool {
    return ($_SESSION['user']['role'] ?? '') === 'super_admin';
}

function is_admin(): bool {
    return in_array($_SESSION['user']['role'] ?? '', ['admin', 'super_admin']);
}

// ─────────────────────────────────────────────────────────────
// Approval threshold — actions requiring Super Admin review
// ─────────────────────────────────────────────────────────────
define('SA_APPROVAL_AMOUNT_THRESHOLD', 10000.00); // ₱10,000+
define('SA_APPROVAL_BULK_THRESHOLD',   5);         // > 5 batch items

function needs_sa_approval(string $action, $context = null): bool {
    if (is_super_admin()) return false;
    switch ($action) {
        case 'approve_application':
            return floatval($context['amount_granted'] ?? 0) >= SA_APPROVAL_AMOUNT_THRESHOLD;
        case 'release_funds':
            return floatval($context['amount_released'] ?? 0) >= SA_APPROVAL_AMOUNT_THRESHOLD;
        case 'bulk_approve':
            return intval($context['count'] ?? 0) > SA_APPROVAL_BULK_THRESHOLD;
        case 'delete_user':
        case 'update_budget':
        case 'suspend_account':
        case 'record_modification':
            return true;
        default:
            return false;
    }
}

// ─────────────────────────────────────────────────────────────
// Approval Request — admin submits, super_admin reviews
// ─────────────────────────────────────────────────────────────
function create_approval_request(
    $mysqli,
    int $requested_by,
    string $request_type,
    ?int $reference_id,
    array $request_data,
    string $reason = ''
): int {
    $data_json = json_encode($request_data);
    $stmt = $mysqli->prepare("
        INSERT INTO approval_requests
            (requested_by, request_type, reference_id, request_data, reason, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param('isiss', $requested_by, $request_type, $reference_id, $data_json, $reason);
    $stmt->execute();
    $id = (int)$mysqli->insert_id;
    $stmt->close();
    return $id;
}

function get_pending_approvals_count($mysqli): int {
    $res = $mysqli->query("SELECT COUNT(*) FROM approval_requests WHERE status='pending'");
    return $res ? (int)$res->fetch_row()[0] : 0;
}

// ─────────────────────────────────────────────────────────────
// Audit Logging
// ─────────────────────────────────────────────────────────────
function log_audit(
    $mysqli,
    string $action,
    string $target_type = '',
    ?int $target_id = null,
    string $details = ''
): void {
    $user_id = $_SESSION['user']['id'] ?? null;
    $role    = $_SESSION['user']['role'] ?? 'unknown';
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '';

    $stmt = $mysqli->prepare("
        INSERT INTO audit_logs (user_id, role, action, target_type, target_id, details, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('isssiis', $user_id, $role, $action, $target_type, $target_id, $details, $ip);
    $stmt->execute();
    $stmt->close();
}

// ─────────────────────────────────────────────────────────────
// User helpers
// ─────────────────────────────────────────────────────────────
function get_user_by_phone($phone) {
    global $mysqli;
    $stmt = $mysqli->prepare("SELECT id, phone, name, barangay, role FROM users WHERE phone=? LIMIT 1");
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    $res = $stmt->get_result();
    $u   = $res->fetch_assoc();
    $stmt->close();
    return $u ?: null;
}

function get_count($table, $where = '1=1') {
    global $mysqli;
    $res = $mysqli->query("SELECT COUNT(*) AS c FROM `$table` WHERE $where");
    if (!$res) return 0;
    $r = $res->fetch_assoc();
    return (int)$r['c'];
}

// Flash messages
function flash_set($k, $v) { $_SESSION['flash'][$k] = $v; }
function flash_get($k) { $v = $_SESSION['flash'][$k] ?? null; unset($_SESSION['flash'][$k]); return $v; }

// CSRF helpers
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}
