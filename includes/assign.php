<?php
// ============================================================
//  PATIENT → DENTIST AUTO-BALANCER  (includes/assign.php)
// ============================================================
//  Decides which dentist a NEW patient should be assigned to.
//
//  THE RULES (exactly what you asked for):
//    • 0 active dentists  -> return NULL (nobody to assign yet)
//    • 1 active dentist   -> just return that one dentist.
//                            (Auto-balance does NOT kick in — there is
//                             nothing to balance with only one doctor.)
//    • 2+ active dentists -> AUTO-BALANCE turns on: assign the patient
//                            to the dentist who currently has the
//                            FEWEST patients, so the load stays even.
//
//  It returns the dentist's NAME (this matches the text stored in
//  patients.primary_dentist), or NULL if there are no active dentists.
// ============================================================

function pick_dentist_for_new_patient($pdo) {
    // Get every ACTIVE dentist (inactive ones are skipped).
    $dentists = $pdo->query(
        "SELECT name FROM users WHERE role='dentist' AND status='active' ORDER BY name"
    )->fetchAll(PDO::FETCH_COLUMN);

    $howMany = count($dentists);

    // --- Case 1: no dentists at all ---
    if ($howMany === 0) {
        return null;                // leave the patient unassigned for now
    }

    // --- Case 2: only ONE dentist ---
    // Auto-balance is OFF here. Just hand the patient to the lone dentist.
    if ($howMany === 1) {
        return $dentists[0];
    }

    // --- Case 3: TWO OR MORE dentists -> AUTO-BALANCE ---
    // Look at each dentist, count how many patients they already have,
    // and choose the one with the smallest count. If there is a tie,
    // the first one alphabetically wins (because we loop in name order).
    $chosenDentist = null;
    $lowestCount   = null;

    foreach ($dentists as $dentistName) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE primary_dentist = ?");
        $stmt->execute([$dentistName]);
        $patientCount = (int)$stmt->fetchColumn();

        // First loop, or found someone with fewer patients -> remember them.
        if ($lowestCount === null || $patientCount < $lowestCount) {
            $lowestCount   = $patientCount;
            $chosenDentist = $dentistName;
        }
    }

    return $chosenDentist;
}

// ============================================================
//  DENTIST NAME MATCHING
// ============================================================
//  The system stores a dentist's name in two shapes:
//     users.name / patients.primary_dentist -> "Dr. Ana Santos"  (full)
//     appointments.dentist                  -> "Dr. Santos"      (short)
//  This returns every shape a given dentist may appear as, so a
//  filter can match all of that dentist's records reliably.
// ============================================================
function dentist_name_variants($fullName) {
    $fullName = trim($fullName);
    if ($fullName === '') return [''];

    $variants = [$fullName];

    // Build the short form: keep the title (Dr.) + the LAST word (surname).
    $parts = preg_split('/\s+/', $fullName);
    if (count($parts) >= 3) {
        $short = $parts[0] . ' ' . end($parts);      // "Dr." + "Santos"
        if (!in_array($short, $variants)) $variants[] = $short;
    }
    // Also allow the bare surname on its own, just in case.
    $last = end($parts);
    if ($last && !in_array($last, $variants)) $variants[] = $last;

    return $variants;
}

// Builds an SQL fragment like "(col = ? OR col = ? OR col = ?)" plus its params.
function dentist_match_sql($column, $fullName, &$params) {
    $variants = dentist_name_variants($fullName);
    $marks = [];
    foreach ($variants as $v) { $marks[] = "$column = ?"; $params[] = $v; }
    return '(' . implode(' OR ', $marks) . ')';
}

// ============================================================
//  IS THIS SLOT AVAILABLE?
// ============================================================
//  Used when an appointment is booked or moved. Checks three things:
//    1. the clinic is open that day and at that hour
//    2. the dentist has not marked the day off
//    3. nobody else already holds that slot with that dentist
//  $ignoreId lets an appointment ignore itself when being moved.
// ============================================================
function appt_slot_is_open($pdo, $date, $time, $dentist, $ignoreId = 0) {
    // ---- 1. Is the clinic open that day? ----
    try {
        $cfg = [];
        foreach ($pdo->query("SELECT setting_key, setting_value FROM settings
                              WHERE setting_key IN ('clinic_open_days','clinic_open_time','clinic_close_time')") as $r) {
            $cfg[$r['setting_key']] = $r['setting_value'];
        }
        $openDays = array_filter(array_map('trim', explode(',', $cfg['clinic_open_days'] ?? 'Mon,Tue,Wed,Thu,Fri,Sat')));
        if ($openDays && !in_array(date('D', strtotime($date)), $openDays)) return false;

        // ---- within opening hours? ----
        $t = date('H:i', strtotime($time));
        if (!empty($cfg['clinic_open_time'])  && $t < date('H:i', strtotime($cfg['clinic_open_time'])))  return false;
        if (!empty($cfg['clinic_close_time']) && $t > date('H:i', strtotime($cfg['clinic_close_time']))) return false;
    } catch (Throwable $e) { /* settings missing — allow */ }

    // ---- 2. Has the dentist marked that day off? ----
    if ($dentist) {
        try {
            $p = [$date];
            $sql = "SELECT COUNT(*) FROM dentist_daysoff WHERE off_date = ? AND "
                 . dentist_match_sql('dentist_name', $dentist, $p);
            $q = $pdo->prepare($sql); $q->execute($p);
            if ((int)$q->fetchColumn() > 0) return false;
        } catch (Throwable $e) {}
    }

    // ---- 3. Is that slot already taken by this dentist? ----
    try {
        $p = [$date, $time];
        $sql = "SELECT COUNT(*) FROM appointments
                 WHERE appointment_date = ? AND appointment_time = ?
                   AND status IN ('Pending','Confirmed')";
        if ($dentist) { $sql .= " AND " . dentist_match_sql('dentist', $dentist, $p); }
        if ($ignoreId) { $sql .= " AND id <> ?"; $p[] = (int)$ignoreId; }
        $q = $pdo->prepare($sql); $q->execute($p);
        if ((int)$q->fetchColumn() > 0) return false;
    } catch (Throwable $e) {}

    return true;
}
