<?php
// init_db.php — full schema initialization
require_once 'config.php';

if (!$mysqli->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    die("Error creating DB: " . $mysqli->error);
}
$mysqli->select_db(DB_NAME);

$queries = [];

// ── Core Tables ────────────────────────────────────────────
$queries[] = "
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  phone         VARCHAR(20)  NOT NULL UNIQUE,
  name          VARCHAR(255) NOT NULL,
  email         VARCHAR(255) NULL,
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  verification_token_hash CHAR(64) NULL,
  verification_expires DATETIME NULL,
  barangay      VARCHAR(100),
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('client','admin','super_admin') DEFAULT 'client',
  status        ENUM('active','suspended') DEFAULT 'active',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_created (role, created_at),
    KEY idx_users_created_at (created_at),
    KEY idx_users_barangay_created (barangay, created_at),
    KEY idx_users_verified_role (email_verified, role)
) ENGINE=InnoDB;
";

$queries[] = "
CREATE TABLE IF NOT EXISTS applications (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  type            ENUM('burial','medical','educational','livelihood','emergency') NOT NULL,
  date_of_request DATE NOT NULL,
  status          ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
  amount_requested  DECIMAL(12,2) DEFAULT 0.00,
  amount_granted    DECIMAL(12,2) DEFAULT 0.00,
  amount_released   DECIMAL(12,2) DEFAULT 0.00,
  rejection_reason  TEXT,
  notes             TEXT,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_applications_status_created (status, created_at),
    KEY idx_applications_created_at (created_at),
    KEY idx_applications_type_created (type, created_at),
    KEY idx_applications_request_date (date_of_request),
    KEY idx_applications_amount_requested (amount_requested),
    KEY idx_applications_amount_released (amount_released),
    KEY idx_applications_user_created (user_id, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
";

$queries[] = "
CREATE TABLE IF NOT EXISTS documents (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  filename       VARCHAR(255) NOT NULL,
  original_name  VARCHAR(255) NOT NULL,
  uploaded_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB;
";

$queries[] = "
CREATE TABLE IF NOT EXISTS messages (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  sender     ENUM('admin','system') DEFAULT 'system',
  message    TEXT,
  is_read    TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
";

// ── Admin Actions (legacy audit for applications) ──────────
$queries[] = "
CREATE TABLE IF NOT EXISTS admin_actions (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT,
  admin_id       INT,
  action         VARCHAR(50) NOT NULL,
  details        TEXT,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_admin_actions_app_action_date (application_id, action, created_at),
    KEY idx_admin_actions_admin_date (admin_id, created_at),
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL,
  FOREIGN KEY (admin_id)       REFERENCES users(id)         ON DELETE SET NULL
) ENGINE=InnoDB;
";

// ── In-App Notifications ────────────────────────────────────
$queries[] = "
CREATE TABLE IF NOT EXISTS notifications (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  message    TEXT,
  is_read    TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
";

// ── Budget Tracking ─────────────────────────────────────────
$queries[] = "
CREATE TABLE IF NOT EXISTS budgets (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  type              VARCHAR(50) NOT NULL UNIQUE,
  allocated_budget  DECIMAL(14,2) DEFAULT 0.00,
  used_budget       DECIMAL(14,2) DEFAULT 0.00,
  max_amount        DECIMAL(12,2) DEFAULT 0.00,
  reapply_interval_months INT DEFAULT 6,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_budgets_updated_at (updated_at)
) ENGINE=InnoDB;
";

// ── System Settings ─────────────────────────────────────────
$queries[] = "
CREATE TABLE IF NOT EXISTS settings (
  setting_key   VARCHAR(100) PRIMARY KEY,
  setting_value TEXT,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
";

// ── Comprehensive Audit Log ─────────────────────────────────
$queries[] = "
CREATE TABLE IF NOT EXISTS audit_logs (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT,
  role        VARCHAR(20),
  action      VARCHAR(100) NOT NULL,
  target_type VARCHAR(50),
  target_id   INT,
  details     TEXT,
  ip_address  VARCHAR(45),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_logs_created (created_at),
    KEY idx_audit_logs_user_created (user_id, created_at),
    KEY idx_audit_logs_action_created (action, created_at),
    KEY idx_audit_logs_ip (ip_address),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
";

// ── Super Admin Approval Requests ───────────────────────────
$queries[] = "
CREATE TABLE IF NOT EXISTS approval_requests (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  requested_by   INT NOT NULL,
  request_type   ENUM(
    'approve_application',
    'reject_application',
    'release_funds',
    'delete_user',
    'update_budget',
    'suspend_account',
    'bulk_approve',
    'record_modification'
  ) NOT NULL,
  reference_id   INT,
  request_data   JSON,
  reason         TEXT,
  status         ENUM('pending','approved','rejected') DEFAULT 'pending',
  reviewed_by    INT,
  review_notes   TEXT,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_at    TIMESTAMP NULL,
    KEY idx_approval_status_created (status, created_at),
    KEY idx_approval_created_at (created_at),
    KEY idx_approval_requester_status_created (requested_by, status, created_at),
    KEY idx_approval_type_created (request_type, created_at),
    KEY idx_approval_reference (reference_id),
  FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
";

foreach ($queries as $q) {
    if (!$mysqli->query($q)) {
        die('Schema error: ' . $mysqli->error . "\n\nSQL: $q");
    }
}

// ── Seed budget rows ────────────────────────────────────────
$budget_types = ['medical', 'burial'];
foreach ($budget_types as $bt) {
    $mysqli->query("INSERT IGNORE INTO budgets (type, allocated_budget, max_amount) VALUES ('$bt', 500000.00, 10000.00)");
}

// ── Seed default settings ───────────────────────────────────
$defaults = [
    'app_name'            => 'AIDTRACK',
    'org_name'            => 'Municipal Social Welfare and Development Office',
    'contact_phone'       => '',
    'contact_email'       => '',
    'max_file_size'       => '5',
    'allowed_types'       => 'medical,burial',
    'sms_enabled'         => '0',
    'sms_api_key'         => '',
    'sms_sender'          => 'AIDTRACK',
    'email_enabled'       => '0',
    'email_from'          => 'noreply@aidtrack.local',
    'email_from_name'     => 'AIDTRACK System',
    'sa_approval_threshold' => '10000',
    'sa_bulk_threshold'   => '5',
];
$ins = $mysqli->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
foreach ($defaults as $key => $val) {
    $ins->bind_param('ss', $key, $val);
    $ins->execute();
}
$ins->close();

// ── Seed accounts ───────────────────────────────────────────
$accounts = [
    ['phone' => '09171234567', 'name' => 'Admin User',  'password' => 'admin123!',    'role' => 'admin'],
    ['phone' => '09170000001', 'name' => 'Super Admin', 'password' => 'superadmin123', 'role' => 'super_admin'],
];
foreach ($accounts as $acc) {
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->bind_param('s', $acc['phone']);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        $hash = password_hash($acc['password'], PASSWORD_DEFAULT);
        $stmt2 = $mysqli->prepare("INSERT INTO users (phone, name, barangay, password_hash, role) VALUES (?, ?, 'Office', ?, ?)");
        $stmt2->bind_param('ssss', $acc['phone'], $acc['name'], $hash, $acc['role']);
        $stmt2->execute();
        echo ucfirst(str_replace('_', ' ', $acc['role'])) . " created — phone: {$acc['phone']} / pass: {$acc['password']}\n";
        $stmt2->close();
    } else {
        echo ucfirst(str_replace('_', ' ', $acc['role'])) . " already exists ({$acc['phone']}).\n";
    }
    $stmt->close();
}

// ── Uploads folder ──────────────────────────────────────────
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

echo "DB initialized successfully.\n";
