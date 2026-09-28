<?php
// Copy to config.php and replace every placeholder with deployment values.
define('DB_HOST', 'localhost');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_NAME', 'your_database_name');

define('MAIL_USER', 'your-gmail@gmail.com');
define('MAIL_APP_PASS', 'your-16-character-gmail-app-password');
define('APP_URL', 'https://your-domain.example');

define('UPLOAD_DIR', __DIR__ . '/uploads/');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    error_log('Database connection failed: ' . $mysqli->connect_error);
    die('Database connection failed.');
}