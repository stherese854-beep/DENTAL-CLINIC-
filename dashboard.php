<?php
// ============================================================
//  DASHBOARD  (dashboard.php)
// ============================================================
require_once 'config/auth.php';
require_login(['admin','dentist','staff']);   // any logged-in staff/dentist/admin can view

// ---------- Pull live numbers from the database ----------
// Dentists should only see their OWN patients/appointments; admin & staff see all.
$role = current_role();
$isDentist = ($role === 'dentist');
$myName = $_SESSION['name'] ?? '';

// A reusable WHERE fragment so a dentist's numbers are scoped to their patients.
$apptScope = $isDentist ? " AND dentist = " . $pdo->quote($myName) : "";

$totalPatients = $isDentist
    ? $pdo->query("SELECT COUNT(*) FROM patients WHERE primary_dentist = " . $pdo->quote($myName))->fetchColumn()
    : $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();

// "Today's Appointments" must count TODAY only (was counting every date before).
$todaysAppts = $pdo->query(
    "SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()$apptScope"
)->fetchColumn();

$pendingRequests = $pdo->query(
    "SELECT COUNT(*) FROM appointments WHERE status='Pending'$apptScope"
)->fetchColumn();

$treatmentsDone = $pdo->query("SELECT COUNT(*) FROM treatments WHERE status='Completed'")->fetchColumn();

// Today's appointment list (today only, earliest time first).
$appts = $pdo->query(
    "SELECT * FROM appointments
     WHERE appointment_date = CURDATE()$apptScope
     ORDER BY appointment_time ASC, id ASC
     LIMIT 6"
)->fetchAll();

// Recent patients
$recent = $isDentist
    ? $pdo->query("SELECT * FROM patients WHERE primary_dentist = " . $pdo->quote($myName) . " ORDER BY id DESC LIMIT 4")->fetchAll()
    : $pdo->query("SELECT * FROM patients ORDER BY id DESC LIMIT 4")->fetchAll();

// Treatment breakdown (count per treatment type)
$breakdown = $pdo->query("SELECT treatment, COUNT(*) AS cnt FROM appointments WHERE 1=1$apptScope GROUP BY treatment ORDER BY cnt DESC")->fetchAll();

// Real sub-figures for the stat cards (replaces the old hard-coded numbers).
$newPatientsMonth = $isDentist
    ? $pdo->query("SELECT COUNT(*) FROM patients WHERE primary_dentist = " . $pdo->quote($myName) . " AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetchColumn()
    : (int)$pdo->query("SELECT COUNT(*) FROM patients WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetchColumn();
$confirmedToday = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE() AND status='Confirmed'$apptScope")->fetchColumn();
$completedMonth = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status='Completed' AND MONTH(appointment_date)=MONTH(CURDATE()) AND YEAR(appointment_date)=YEAR(CURDATE())$apptScope")->fetchColumn();
$maxCnt = 1;
foreach ($breakdown as $b) { if ($b['cnt'] > $maxCnt) $maxCnt = $b['cnt']; }

// colors used for avatars / bars
$avatarColors = ['#f5a3a3','#f5d6a3','#a3d8f5','#c3a3f5','#a3f5c3'];

// ============================================================
//  STAT-CARD TREND SPARKLINES
// ============================================================
// The stat cards used to show a decorative bar at a fixed, made-up width.
// This replaces that with a real 7-day trend (daily counts) for each metric,
// drawn as a tiny bar-sparkline — the right form for "a headline number plus
// its recent trend" (a full chart would be overkill for 4 numbers).

// Counts rows in $table, grouped by day, for the last 7 days (today included).
// Missing days come back as 0 so every sparkline always has exactly 7 bars.
function seven_day_counts($pdo, $table, $dateCol, $extraWhere = '', $params = []) {
    $days = [];
    for ($i = 6; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i day"))] = 0;

    $stmt = $pdo->prepare(
        "SELECT DATE($dateCol) AS d, COUNT(*) AS c FROM $table
         WHERE DATE($dateCol) >= CURDATE() - INTERVAL 6 DAY $extraWhere
         GROUP BY DATE($dateCol)"
    );
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) {
        if (isset($days[$row['d']])) $days[$row['d']] = (int)$row['c'];
    }
    return array_values($days);
}

// Draws a small bar-sparkline as inline SVG. Thin rounded bars, one hue —
// the value under each bar is available on hover (native tooltip) so nothing
// important is color-only.
function render_sparkline($values, $color, $height = 30, $barW = 8, $gap = 4) {
    $max = max(1, max($values));
    $width = count($values) * $barW + (count($values) - 1) * $gap;
    $bars = '';
    foreach ($values as $i => $v) {
        $h = $v > 0 ? max(3, (int)round(($v / $max) * ($height - 4))) : 2;
        $x = $i * ($barW + $gap);
        $y = $height - $h;
        $bars .= "<rect x='$x' y='$y' width='$barW' height='$h' rx='2' fill='$color'><title>$v</title></rect>";
    }
    return "<svg width='$width' height='$height' viewBox='0 0 $width $height' role='img' aria-label='7-day trend'>$bars</svg>";
}

$patientsTrend = $isDentist
    ? seven_day_counts($pdo, 'patients', 'created_at', ' AND primary_dentist = ?', [$myName])
    : seven_day_counts($pdo, 'patients', 'created_at');

$apptsTrend = $isDentist
    ? seven_day_counts($pdo, 'appointments', 'appointment_date', ' AND dentist = ?', [$myName])
    : seven_day_counts($pdo, 'appointments', 'appointment_date');

$pendingTrend = $isDentist
    ? seven_day_counts($pdo, 'appointments', 'appointment_date', " AND status='Pending' AND dentist = ?", [$myName])
    : seven_day_counts($pdo, 'appointments', 'appointment_date', " AND status='Pending'");

$treatTrend = seven_day_counts($pdo, 'treatments', 'treatment_date', " AND status='Completed'");

$page_title = "Dashboard";
include 'includes/head.php';
$active = 'dashboard';
?>
<div class="app-wrap">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main">
        <!-- Page header with live clock -->
        <div class="page-head">
            <div>
                <h1>Dashboard</h1>
                <div class="sub">Overview of clinic activity</div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="clock"><span class="time" id="clock">--:--</span><br><span id="clock-date"></span></div>
                <a href="patients.php" class="btn btn-dark-navy">+ New Patient</a>
            </div>
        </div>

        <!-- ===== Clinic announcements (visible to all staff) ===== -->
        <?php
        $dashAnns = $pdo->query(
            "SELECT title, content, created_at FROM announcements
             WHERE status='Published' ORDER BY created_at DESC LIMIT 3"
        )->fetchAll();
        ?>
        <?php if ($dashAnns): ?>
        <div class="card-box mb-3" style="border-left:4px solid var(--gold);">
            <div class="flex-between mb-2">
                <h6 class="mb-0">📣 Clinic Announcements</h6>
                <?php if (current_role() === 'admin'): ?>
                    <a href="announcements.php" class="btn btn-sm btn-light">Manage</a>
                <?php endif; ?>
            </div>
            <?php foreach ($dashAnns as $an): ?>
                <div class="py-2 border-bottom">
                    <div style="font-weight:600;font-size:.95rem;"><?= e($an['title']) ?></div>
                    <div class="text-muted2" style="font-size:.86rem;"><?= e($an['content']) ?></div>
                    <div class="text-muted2" style="font-size:.75rem;margin-top:2px;"><?= date('M j, Y', strtotime($an['created_at'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ===== Stat cards ===== -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="flex-between"><span class="label">Total Patients</span> 👥</div>
                <div class="value"><?= number_format($totalPatients) ?></div>
                <div class="change" style="color:#138a4e;"><?= $newPatientsMonth ?> new this month</div>
                <div class="spark" title="New patients, last 7 days"><?= render_sparkline($patientsTrend, '#0d9488') ?></div>
            </div>
            <div class="stat-card">
                <div class="flex-between"><span class="label">Today's Appointments</span> 📅</div>
                <div class="value"><?= $todaysAppts ?></div>
                <div class="change"><?= $confirmedToday ?> confirmed</div>
                <div class="spark" title="Appointments, last 7 days"><?= render_sparkline($apptsTrend, '#e879b9') ?></div>
            </div>
            <div class="stat-card">
                <div class="flex-between"><span class="label">Pending Requests</span> ⏳</div>
                <div class="value"><?= $pendingRequests ?></div>
                <div class="change">awaiting approval</div>
                <div class="spark" title="Pending requests, last 7 days"><?= render_sparkline($pendingTrend, '#60a5fa') ?></div>
            </div>
            <div class="stat-card">
                <div class="flex-between"><span class="label">Treatments Done</span> 🦷</div>
                <div class="value"><?= number_format($treatmentsDone) ?></div>
                <div class="change" style="color:#138a4e;"><?= $completedMonth ?> completed this month</div>
                <div class="spark" title="Completed treatments, last 7 days"><?= render_sparkline($treatTrend, '#10b981') ?></div>
            </div>
        </div>

        <div class="row">
            <!-- ===== Today's appointments table ===== -->
            <div class="col-lg-8">
                <div class="card-box">
                    <div class="flex-between mb-2">
                        <h5 class="mb-0">Today's Appointments</h5>
                        <a href="appointments.php" class="btn btn-sm btn-outline-teal">View All →</a>
                    </div>
                    <div class="table-responsive">
                        <table class="data">
                            <thead><tr><th>Patient</th><th>Date</th><th>Time</th><th>Treatment</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php if (empty($appts)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center;padding:34px 12px;color:#8aa0a0;">
                                        <div style="font-size:2.2rem;margin-bottom:6px;">📭</div>
                                        No appointments scheduled for today.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($appts as $a): ?>
                                    <tr>
                                        <td><strong><?= e($a['patient_name']) ?></strong></td>
                                        <td><?= e($a['appointment_date']) ?></td>
                                        <td class="date-blue"><?= e($a['appointment_time']) ?></td>
                                        <td><?= e($a['treatment']) ?></td>
                                        <td><span class="badge-pill b-<?= strtolower($a['status']) ?>"><?= e($a['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ===== Mini calendar ===== -->
            <div class="col-lg-4">
                <div class="card-box">
                    <h5>Schedule</h5>
                    <div id="calendar"></div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- ===== Recent patients ===== -->
            <div class="col-lg-6">
                <div class="card-box">
                    <h5>Recent Patients</h5>
                    <?php foreach ($recent as $i => $p): ?>
                        <div class="flex-between py-2 border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar" style="background:<?= $avatarColors[$i % 5] ?>"><?= strtoupper(substr($p['name'],0,1)) ?></span>
                                <div>
                                    <strong><?= e($p['name']) ?></strong><br>
                                    <small class="text-muted2">Age <?= e($p['age']) ?> • <?= e($p['last_visit']) ?></small>
                                </div>
                            </div>
                            <span class="badge-pill b-<?= strtolower($p['status']) ?>"><?= e($p['status']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ===== Treatment breakdown ===== -->
            <div class="col-lg-6">
                <div class="card-box">
                    <h5>Treatment Breakdown</h5>
                    <?php
                    $barColors = ['#3b82f6','#22c55e','#ec4899','#14b8a6','#f59e0b'];
                    foreach ($breakdown as $i => $b):
                        $pct = round(($b['cnt'] / $maxCnt) * 100);
                    ?>
                        <div class="mb-3">
                            <div class="flex-between"><span><?= e($b['treatment']) ?></span><small class="text-muted2"><?= $b['cnt'] ?> cases</small></div>
                            <div class="bar" style="height:8px;background:#eef2f5;border-radius:5px;margin-top:4px;">
                                <span style="display:block;height:100%;border-radius:5px;width:<?= $pct ?>%;background:<?= $barColors[$i % 5] ?>"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="js/app.js"></script>
<script>
    startClock();          // live clock (top right)
    buildCalendar();       // simple month calendar
</script>
</body>
</html>
