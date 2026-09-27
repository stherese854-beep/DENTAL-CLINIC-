<?php
// ============================================================
//  ARCHIVE  (admin_archive.php) -- admin only
// ============================================================
//  Accounts land here when they're "deleted" from User Management.
//  Nothing is actually removed at that point — the account (and any
//  patient records attached to it) is just hidden until an admin
//  either Restores it or permanently Deletes it from here.
// ============================================================
require_once 'config/auth.php';
require_login(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'restore' && $id > 0) {
        $info = $pdo->prepare("SELECT name FROM users WHERE id = ? AND status = 'archived'");
        $info->execute([$id]);
        $u = $info->fetch();

        if ($u) {
            restore_user($pdo, $id);
            set_flash(($u['name'] ?: 'User') . ' restored.');
        } else {
            set_flash('That account is no longer in the Archive.', 'error');
        }
        header("Location: admin_archive.php"); exit;
    }

    if ($action === 'permadelete' && $id > 0) {
        $info = $pdo->prepare("SELECT name, role FROM users WHERE id = ? AND status = 'archived'");
        $info->execute([$id]);
        $u = $info->fetch();

        if ($u) {
            // This is permanent: the account, and (if it's a patient) their
            // patient record, dental chart and appointments, are removed for
            // good. A trace of the email is kept in deleted_accounts_log so
            // the login page can still recognize it later.
            delete_user_completely($pdo, $id, $_SESSION['name'] ?? null);

            $extra = ($u['role'] === 'patient')
                   ? ' Their patient record, dental chart and appointments were removed too.'
                   : '';
            set_flash(($u['name'] ?: 'User') . ' permanently deleted.' . $extra, 'info');
        } else {
            set_flash('That account is no longer in the Archive.', 'error');
        }
        header("Location: admin_archive.php"); exit;
    }
}

$archived = $pdo->query("SELECT * FROM users WHERE status = 'archived' ORDER BY archived_at DESC")->fetchAll();

$page_title = "Archive";
include 'includes/head.php';
$active = 'archive';
?>
<div class="app-wrap">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main">
        <div class="page-head">
            <div>
                <h1 style="color:var(--teal-light)">Archive</h1>
                <div class="sub">Accounts moved here before being deleted for good. Restore them, or delete them permanently.</div>
            </div>
        </div>

        <!-- Top tabs shared across admin pages -->
        <?php include 'includes/admin_tabs.php'; ?>

        <div class="card-box">
            <div class="flex-between mb-3">
                <h5 class="mb-0">Archived Accounts <small class="text-muted2 d-block" style="font-size:.75rem;">Showing <?= count($archived) ?> archived account<?= count($archived) === 1 ? '' : 's' ?></small></h5>
                <a href="admin_users.php" class="btn btn-sm btn-outline-secondary">← Back to User Management</a>
            </div>

            <?php if (empty($archived)): ?>
                <div class="text-center text-muted2 py-5">
                    <div style="font-size:2.2rem;">🗄</div>
                    The Archive is empty.
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data">
                    <thead><tr><th>User</th><th>Role</th><th>Archived By</th><th>Archived On</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($archived as $u):
                        $initials = strtoupper(substr($u['name'],0,1) . (strpos($u['name'],' ') ? substr(strstr($u['name'],' '),1,1) : ''));
                        $roleBadge = ['admin'=>'b-progress','staff'=>'b-confirmed','dentist'=>'b-progress','patient'=>'b-pending'][$u['role']] ?? 'b-pending';
                    ?>
                        <tr>
                            <td><div class="d-flex align-items-center gap-2">
                                <span class="avatar" style="background:#9a9a9a;"><?= e($initials) ?></span>
                                <div><strong><?= e($u['name']) ?></strong><br><small class="text-muted2"><?= e($u['email']) ?></small></div>
                            </div></td>
                            <td><span class="badge-pill <?= $roleBadge ?>"><?= ucfirst($u['role']) ?></span></td>
                            <td><?= e($u['archived_by'] ?: '-') ?></td>
                            <td><small class="text-muted2"><?= $u['archived_at'] ? date('M j, Y g:i A', strtotime($u['archived_at'])) : '-' ?></small></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <form method="POST" onsubmit="return confirmDelete('Restore ' + <?= json_encode($u['name']) ?> + '’s account?')">
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="icon-btn icon-btn-restore" title="Restore">↩</button>
                                    </form>
                                    <form method="POST" onsubmit="return confirmDelete('Permanently delete ' + <?= json_encode($u['name']) ?> + '’s account? This cannot be undone.')">
                                        <input type="hidden" name="action" value="permadelete">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="icon-btn icon-btn-delete" title="Delete Permanently">🗑</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<style>
.icon-btn {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    cursor: pointer;
    transition: filter .15s;
}
.icon-btn:hover { filter: brightness(.95); }
.icon-btn-restore { background: #d7f5e3; color: #138a4e; }
.icon-btn-delete  { background: #fbdcdc; color: #c0392b; }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/app.js"></script>
</body></html>
