<?php
// api/notification_count.php — Returns JSON with unread counts
require_once __DIR__ . '/../config.php';
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$uid  = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];

$data = [];

if ($role === 'client') {
    // Unread messages + notifications for client
    $msg_count  = (int)($mysqli->query("SELECT COUNT(*) FROM messages WHERE user_id=$uid AND is_read=0")->fetch_row()[0] ?? 0);
    $notif_count = (int)($mysqli->query("SELECT COUNT(*) FROM notifications WHERE user_id=$uid AND is_read=0")->fetch_row()[0] ?? 0);
    $pending = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE user_id=$uid AND status='pending'")->fetch_row()[0] ?? 0);
    $data = [
        'unread_messages' => $msg_count,
        'unread_notifications' => $notif_count,
        'unread_total' => $msg_count + $notif_count,
        'pending_apps' => $pending,
    ];
} else {
    // Admin: pending apps + unread messages
    $pending = (int)($mysqli->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0] ?? 0);
    $unread  = (int)($mysqli->query("SELECT COUNT(*) FROM messages WHERE is_read=0")->fetch_row()[0] ?? 0);
    $data = [
        'pending_apps' => $pending,
        'unread_messages' => $unread,
    ];
}

echo json_encode($data);
