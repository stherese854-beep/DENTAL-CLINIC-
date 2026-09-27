<?php
// ============================================================
//  MARK NOTIFICATIONS AS SEEN  (notif_seen.php)
// ============================================================
//  The notification bell calls this in the background (fetch) the
//  moment the user opens it. It stamps users.notif_seen_at with the
//  current time, which makes the red badge disappear until something
//  newer happens.
//
//  It returns a tiny JSON reply; no page is shown.
// ============================================================
require_once 'config/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'reason' => 'not logged in']);
    exit;
}

try {
    $pdo->prepare("UPDATE users SET notif_seen_at = NOW() WHERE id = ?")
        ->execute([$_SESSION['user_id']]);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    // The column may not exist yet if update.sql has not been run.
    echo json_encode(['ok' => false, 'reason' => 'db']);
}
