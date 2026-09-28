<?php
/**
 * Local Development Database Connection (XAMPP)
 * - Handles DB connection errors gracefully
 * - Automatically creates 'uploads/' folder if missing
 */

// Show errors for local development
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Database configuration (XAMPP defaults)
define('DB_HOST', 'localhost');
define('DB_USER', 'u882394654_Aidtrack_123');
define('DB_PASS', 'Aidtrack_123');
define('DB_NAME', 'u882394654_Aidtrack_123');

// Upload directory
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// -----------------------------
// Database Connection
// -----------------------------
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check for connection errors
if ($mysqli->connect_errno) {
    error_log("Database Connection Failed: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
    die("Database connection failed: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
}

// -----------------------------
// Ensure uploads folder exists
// -----------------------------
if (!file_exists(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0755, true)) {
        error_log("Failed to create uploads folder at: " . UPLOAD_DIR);
        die("Server error: Unable to create required folders.");
    }
}

// Verify uploads folder is writable
if (!is_writable(UPLOAD_DIR)) {
    error_log("Uploads folder is not writable: " . UPLOAD_DIR);
    die("Server error: Upload directory is not writable.");
}

// Connection ready
