<?php
// ============================================================
//  ACTIVITY LOG  (admin_activity.php) -- admin only
// ============================================================
//  A read-only trail of the meaningful things that happen in the
//  system: sign-ins, account changes, appointment status changes,
//  settings edits, announcements, and review moderation. Nothing
//  here can be edited or removed from the UI — it's a record.
// ============================================================
require_once 'config/auth.php';
require_login(['admin']);

// ---------- Filters ----------
$roleFilter   = $_GET['role']   ?? '';
$actionFilter = $_GET['q']      ?? '';
$limit        = 100;

$where  = [];
$params = [];

if ($roleFilter !== '') {
    $where[] = "actor_role = ?";
    $params[] = $roleFilter;
}
if ($actionFilter !== '') {
    $where[] = "(action LIKE ? OR details LIKE ? OR actor_name LIKE ?)";
    $like = '%' . $actionFilter . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
$sql = "SELECT * FROM activity_log";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY created_at DESC LIMIT $limit";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$totalCount = (int)$pdo->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();

// A small set of roles for the filter dropdown (only ones actually seen).
$roles = $pdo->query("SELECT DISTINCT actor_role FROM activity_log WHERE actor_role IS NOT NULL AND actor_role <> '' ORDER BY actor_role")
             ->fetchAll(PDO::FETCH_COLUMN);

// Give each action a badge color so the table is easy to scan.
function activity_badge($action) {
    $a = strtolower($action);
    if (strpos($a, 'delete') !== false || strpos($a, 'cancel') !== false) return 'b-cancelled';
    if (strpos($a, 'archiv') !== false)  return 'b-archived';
    if (strpos($a, 'restor') !== false || strpos($a, 'confirm') !== false || strpos($a, 'approv') !== false || strpos($a, 'created') !== false) return 'b-confirmed';
    if (strpos($a, 'login') !== false)   return 'b-progress';
    return 'b-pending';
}

$page_title = "Activity Log";
include 'includes/head.php';
$active = 'activity';
?>
<div class="app-wrap">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main">
        <div class="page-head">
            <div>
                <h1 style="color:var(--teal-light)">Activity Log</h1>
                <div class="sub">A record of what happened in the system, and who did it</div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="clock"><span class="time" id="clock">--:--</span><br><span id="clock-date"></span></div>
            </div>
        </div>

        <!-- Top tabs shared across admin pages -->
        <?php include 'includes/admin_tabs.php'; ?>

        <div class="card-box">
            <div class="flex-between mb-3 flex-wrap gap-2">
                <h5 class="mb-0">
                    Recent Activity
                    <small class="text-muted2 d-block" style="font-size:.75rem;">
                        Showing <?= count($logs) ?> of <?= $totalCount ?> total entr<?= $totalCount === 1 ? 'y' : 'ies' ?> (most recent <?= $limit ?>)
                    </small>
                </h5>
                <form method="GET" class="d-flex gap-2 flex-wrap">
                    <select name="role" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                        <option value="">All roles</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= e($r) ?>" <?= $roleFilter === $r ? 'selected' : '' ?>><?= ucfirst(e($r)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="q" class="form-control form-control-sm" style="width:200px;"
                           placeholder="Search action, name, details..." value="<?= e($actionFilter) ?>">
                    <button class="btn btn-sm btn-teal" type="submit">Filter</button>
                    <?php if ($roleFilter !== '' || $actionFilter !== ''): ?>
                        <a href="admin_activity.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($logs)): ?>
                <div class="text-center text-muted2 py-5">
                    <div style="font-size:2.2rem;">📋</div>
                    No activity recorded yet.
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data">
                    <thead><tr><th>When</th><th>Who</th><th>Role</th><th>Action</th><th>Details</th></tr></thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><small class="text-muted2"><?= date('M j, Y g:i A', strtotime($log['created_at'])) ?></small></td>
                            <td><strong><?= e($log['actor_name'] ?: 'System') ?></strong></td>
                            <td><?= $log['actor_role'] ? '<span class="badge-pill b-pending">' . ucfirst(e($log['actor_role'])) . '</span>' : '<span class="text-muted2">-</span>' ?></td>
                            <td><span class="badge-pill <?= activity_badge($log['action']) ?>"><?= e($log['action']) ?></span></td>
                            <td class="text-muted2"><?= e($log['details'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/app.js"></script>
</body></html>
