<?php
$pageTitle = 'My Schedules';
$pageSubtitle = 'View and manage your incubation schedule';
$activePage = 'schedules';
require_once 'header.php';
$pdo = getDB();
$uid = $_SESSION['user_id'];
$scheduleGraceSeconds = 300;

// Ensure schedule statuses reflect actual batch state:
// 1) If a schedule is pending but its linked batch has been promoted to incubating, mark schedule as 'running'
$setRunningStmt = $pdo->prepare(
  "UPDATE schedules s JOIN batches b ON s.batch_id = b.id
    SET s.status = 'running'
    WHERE s.status = 'pending' AND b.status = 'incubating' AND s.created_by_role = 'user' AND s.created_by_id = ?"
);
$setRunningStmt->execute([$uid]);

// 2) If a schedule is still pending and its scheduled time has passed, and the linked batch is not incubating, mark it as 'failed'
$setFailedStmt = $pdo->prepare(
  "UPDATE schedules s LEFT JOIN batches b ON s.batch_id = b.id
    SET s.status = 'failed'
    WHERE s.status = 'pending'
      AND s.created_by_role = 'user' AND s.created_by_id = ?
            AND TIMESTAMPDIFF(SECOND, CONCAT(s.scheduled_date, ' ', s.scheduled_time), NOW()) > ?
      AND (s.batch_id IS NULL OR b.status <> 'incubating')"
);
$setFailedStmt->execute([$uid, $scheduleGraceSeconds]);

$schedules = $pdo->prepare("SELECT s.*, i.name as incubator_name, b.batch_name FROM schedules s JOIN incubators i ON s.incubator_id=i.id LEFT JOIN batches b ON s.batch_id=b.id WHERE s.created_by_role='user' AND s.created_by_id=? AND s.status IN ('pending','running') ORDER BY s.scheduled_date DESC, s.scheduled_time DESC");
$schedules->execute([$uid]); $schedules = $schedules->fetchAll();

foreach ($schedules as &$scheduleRow) {
    $status = isset($scheduleRow['status']) ? trim((string)$scheduleRow['status']) : '';
    if ($status !== 'pending') {
        continue;
    }

    $scheduledAt = strtotime(trim((string)($scheduleRow['scheduled_date'] ?? '') . ' ' . trim((string)($scheduleRow['scheduled_time'] ?? '00:00:00'))));
    if (!$scheduledAt || ($scheduledAt + $scheduleGraceSeconds) > time()) {
        continue;
    }

    $isIncubating = false;
    if (!empty($scheduleRow['batch_id'])) {
        $batchCheck = $pdo->prepare("SELECT status FROM batches WHERE id = ? LIMIT 1");
        $batchCheck->execute([(int)$scheduleRow['batch_id']]);
        $isIncubating = (($batchCheck->fetchColumn() ?: '') === 'incubating');
    }

    if ($isIncubating) {
        continue;
    }

    $pdo->prepare("UPDATE schedules SET status = 'failed' WHERE id = ? AND status = 'pending'")
        ->execute([(int)$scheduleRow['id']]);
    if (!empty($scheduleRow['batch_id'])) {
        $pdo->prepare("UPDATE batches SET status = 'failed' WHERE id = ? AND status IN ('scheduled', 'incubating')")
            ->execute([(int)$scheduleRow['batch_id']]);
    }

    $scheduleRow['status'] = 'failed';
}
unset($scheduleRow);
$incubators = $pdo->query("SELECT id,name,location,capacity FROM incubators WHERE status='active'")->fetchAll();
$settingsRows = $pdo->query("SELECT incubator_id, target_temp, min_temp, max_temp, target_humidity, min_humidity, max_humidity FROM temperature_settings")->fetchAll();
$settingsByIncubator = [];
foreach ($settingsRows as $row) {
    $settingsByIncubator[(int)$row['incubator_id']] = [
        'target_temp' => $row['target_temp'],
        'min_temp' => $row['min_temp'],
        'max_temp' => $row['max_temp'],
        'target_humidity' => $row['target_humidity'],
        'min_humidity' => $row['min_humidity'],
        'max_humidity' => $row['max_humidity']
    ];
}
$batches_q = $pdo->prepare("SELECT id,batch_name,incubator_id FROM batches WHERE user_id=? AND status='incubating'"); $batches_q->execute([$uid]); $myBatches = $batches_q->fetchAll();
$batchConflict_q = $pdo->prepare("SELECT id, batch_name, incubator_id, status, start_date, expected_hatch_date, notes FROM batches WHERE user_id=? AND status IN ('scheduled','incubating')");
$batchConflict_q->execute([$uid]);
$batchConflictRows = $batchConflict_q->fetchAll();

$activeSession_q = $pdo->prepare(
    "SELECT incubator_id, active_session_name, session_started_at, session_ends_at
     FROM hardware_state
     WHERE session_status = 'running'"
);
$activeSession_q->execute();
$activeSessionRows = $activeSession_q->fetchAll();

function scheduleDateTimeLabel(array $schedule): string {
    $timestamp = strtotime(trim((string)($schedule['scheduled_date'] ?? '') . ' ' . trim((string)($schedule['scheduled_time'] ?? '00:00:00'))));
    if (!$timestamp) {
        return '—';
    }
    return date('M j, Y H:i', $timestamp);
}

function scheduleDurationSeconds(array $schedule): int {
    $description = [];
    if (!empty($schedule['description'])) {
        $decoded = json_decode((string)$schedule['description'], true);
        if (is_array($decoded)) {
            $description = $decoded;
        }
    }

    if (isset($description['session_duration_sec'])) {
        return max(1, (int)$description['session_duration_sec']);
    }

    if (isset($schedule['duration_hours']) && $schedule['duration_hours'] !== null && $schedule['duration_hours'] !== '') {
        return max(1, (int)round(((float)$schedule['duration_hours']) * 3600));
    }

    return 21 * 86400;
}

function scheduleEndLabel(array $schedule): string {
    $start = strtotime(trim((string)($schedule['scheduled_date'] ?? '') . ' ' . trim((string)($schedule['scheduled_time'] ?? '00:00:00'))));
    if (!$start) {
        return '—';
    }
    $end = $start + scheduleDurationSeconds($schedule);
    return date('M j, Y H:i', $end);
}

function scheduleDurationLabel(array $schedule): string {
    $seconds = scheduleDurationSeconds($schedule);
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $parts = [];
    if ($days > 0) $parts[] = $days . 'd';
    if ($hours > 0 || $days > 0) $parts[] = str_pad((string)$hours, 2, '0', STR_PAD_LEFT) . 'h';
    $parts[] = str_pad((string)$minutes, 2, '0', STR_PAD_LEFT) . 'm';
    return implode(' ', $parts);
}

$scheduleConflictWindows = [];
foreach ($schedules as $scheduleRow) {
    $scheduleStatus = isset($scheduleRow['status']) ? trim((string)$scheduleRow['status']) : '';
    if (!in_array($scheduleStatus, ['pending', 'running'], true)) {
        continue;
    }

    $start = strtotime(trim((string)($scheduleRow['scheduled_date'] ?? '') . ' ' . trim((string)($scheduleRow['scheduled_time'] ?? '00:00:00'))));
    if (!$start) {
        continue;
    }
    $scheduleConflictWindows[] = [
        'id' => (int)$scheduleRow['id'],
        'title' => (string)$scheduleRow['title'],
        'incubator_id' => (int)($scheduleRow['incubator_id'] ?? 0),
        'start' => $start,
        'end' => $start + scheduleDurationSeconds($scheduleRow),
        'source' => 'schedule',
        'status' => $scheduleStatus
    ];
}

foreach ($batchConflictRows as $batchRow) {
    $batchWindowStart = null;
    if (!empty($batchRow['notes']) && preg_match('/SCHEDULED_START:\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $batchRow['notes'], $matches)) {
        $batchWindowStart = strtotime($matches[1]);
    }
    if (!$batchWindowStart && !empty($batchRow['start_date'])) {
        $batchWindowStart = strtotime((string)$batchRow['start_date'] . ' 00:00:00');
    }
    $batchWindowEnd = !empty($batchRow['expected_hatch_date']) ? strtotime((string)$batchRow['expected_hatch_date'] . ' 23:59:59') : false;
    if ($batchWindowStart && $batchWindowEnd) {
        $scheduleConflictWindows[] = [
            'id' => (int)$batchRow['id'],
            'title' => (string)$batchRow['batch_name'],
            'incubator_id' => (int)($batchRow['incubator_id'] ?? 0),
            'start' => $batchWindowStart,
            'end' => $batchWindowEnd,
            'source' => 'batch',
            'status' => (string)($batchRow['status'] ?? '')
        ];
    }
}

foreach ($activeSessionRows as $sessionRow) {
    $sessionStart = !empty($sessionRow['session_started_at']) ? strtotime($sessionRow['session_started_at']) : false;
    $sessionEnd = !empty($sessionRow['session_ends_at']) ? strtotime($sessionRow['session_ends_at']) : false;
    if ($sessionStart && $sessionEnd && $sessionEnd > $sessionStart) {
        $scheduleConflictWindows[] = [
            'id' => 0,
            'title' => !empty($sessionRow['active_session_name']) ? (string)$sessionRow['active_session_name'] : 'Active Session',
            'incubator_id' => (int)($sessionRow['incubator_id'] ?? 0),
            'start' => $sessionStart,
            'end' => $sessionEnd,
            'source' => 'active_session',
            'status' => 'running'
        ];
    }
}
?>
<div class="d-flex justify-content-end mb-4">
    <button class="btn-ghost" data-bs-toggle="modal" data-bs-target="#addSchedModal"><i class="fas fa-plus me-2"></i>Add
        Schedule</button>
</div>
<div class="ghost-panel">
    <div class="ghost-panel-header"><span class="ghost-panel-title">📅 My Schedules</span></div>
    <div class="ghost-panel-body p-0">
        <table class="ghost-table">
            <thead>
                <tr>
                    <th>No#</th>
                    <th>Title</th>
                    <th>Incubator</th>
                    <th>Start (Date & Time)</th>
                    <th>End</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($schedules as $index => $s): ?>
                <?php
                  // Normalize status for display when DB value is empty or null
                  $rawStatus = isset($s['status']) ? trim((string)$s['status']) : '';
                  $displayStatus = $rawStatus !== '' ? $rawStatus : 'pending';
                  $badgeClass = 'badge-' . $displayStatus;
                  $batchLabel = !empty($s['batch_name']) ? htmlspecialchars($s['batch_name']) : '';
                ?>
                <tr>
                    <td style="font-size:.85rem;color:var(--ghost-muted);font-weight:700;">#<?= $index + 1 ?></td>
                    <td>
                        <div style="font-weight:600;color:white;"><?= htmlspecialchars($s['title']) ?></div>
                        <?php if($batchLabel): ?><div style="font-size:.72rem;color:var(--ghost-muted);">
                            <?= $batchLabel ?></div><?php endif; ?>
                    </td>
                    <td style="font-size:.85rem;"><?= htmlspecialchars($s['incubator_name']) ?></td>
                    <td>
                        <div style="font-size:.85rem;font-weight:600;">
                            <?= htmlspecialchars(scheduleDateTimeLabel($s)) ?></div>
                    </td>
                    <td>
                        <div style="font-size:.85rem;font-weight:600;">
                            <?= htmlspecialchars(scheduleEndLabel($s)) ?></div>
                    </td>
                    <td>
                        <div style="font-size:.85rem;font-weight:600;">
                            <?= htmlspecialchars(scheduleDurationLabel($s)) ?></div>
                    </td>
                    <td><span class="<?= $badgeClass ?>"><?= htmlspecialchars($displayStatus) ?></span></td>
                    <td>
                        <div class="d-flex gap-2">
                            <?php if($displayStatus === 'pending'): ?><button class="btn-edit-ghost"
                                style="font-size:.72rem;padding:5px 10px;"
                                onclick="markDone(<?= (int)$s['id'] ?>)">✓</button><?php endif; ?>
                            <?php if(!empty($s['created_by_role']) && $s['created_by_role']=='user' && (int)($s['created_by_id'] ?? 0) === (int)$uid): ?>
                            <button class="btn-edit-ghost"
                                onclick='editSched(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)'><i
                                    class="fas fa-pen"></i></button>
                                <button class="btn-danger-ghost" onclick="cancelSched(<?= (int)$s['id'] ?>)" title="Cancel schedule"><i
                                    class="fas fa-ban"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD SCHEDULE MODAL -->
<div class="modal fade modal-ghost" id="addSchedModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width:980px;">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title"><i class="fas fa-calendar-plus me-2" style="color:var(--ghost-blue)"></i>Add
                        Schedule</h5>
                    <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:2px;">Review the incubation values
                        before scheduling the session</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:24px;">
                <div class="row g-4 align-items-start">
                    <div class="col-lg-7">
                        <div class="row g-3 mb-3">
                            <div class="col-12">
                                <label class="form-label-ghost">Schedule Title</label>
                                <input type="text" class="form-control-ghost" id="as_title"
                                    placeholder="Enter schedule title">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-ghost">Incubator</label>
                                <select class="form-select-ghost" id="as_incubator" onchange="syncScheduleIncubator()">
                                    <?php foreach($incubators as $inc): ?>
                                    <option value="<?= $inc['id'] ?>"
                                        data-loc="<?= htmlspecialchars($inc['location'] ?? '') ?>"
                                        data-cap="<?= htmlspecialchars($inc['capacity'] ?? '') ?>">
                                        <?= htmlspecialchars($inc['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-ghost">Batch</label>
                                <select class="form-select-ghost" id="as_batch" onchange="syncScheduleIncubator()">
                                    <option value="">General</option>
                                    <?php foreach($myBatches as $batch): ?>
                                    <option value="<?= $batch['id'] ?>" data-inc="<?= $batch['incubator_id'] ?>">
                                        <?= htmlspecialchars($batch['batch_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div id="as_inc_info_bar"
                            style="background:rgba(245,166,35,.06);border:1px solid rgba(245,166,35,.2);border-radius:10px;padding:12px 16px;margin-bottom:20px;display:flex;gap:20px;flex-wrap:wrap;">
                            <div>
                                <div
                                    style="font-size:.7rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.06em;">
                                    Incubator</div>
                                <div style="font-weight:700;color:white;font-size:.9rem;" id="as_info_incubator">—</div>
                            </div>
                            <div>
                                <div
                                    style="font-size:.7rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.06em;">
                                    Capacity</div>
                                <div style="font-weight:700;color:var(--ghost-amber);font-size:.9rem;" id="as_info_cap">
                                    —</div>
                            </div>
                            <div>
                                <div
                                    style="font-size:.7rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.06em;">
                                    Location</div>
                                <div style="font-weight:700;color:white;font-size:.9rem;" id="as_info_loc">—</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-ghost">⏳ Session Duration</label>
                            <div class="row g-2">
                                <div class="col-3">
                                    <label class="form-label-ghost">Day</label>
                                    <input type="number" min="0" step="1" class="form-control-ghost"
                                        id="as_duration_days" value="21" oninput="updateSchedulePreview()"
                                        style="text-align:center;">
                                </div>
                                <div class="col-3">
                                    <label class="form-label-ghost">HH</label>
                                    <input type="number" min="0" max="23" step="1" class="form-control-ghost"
                                        id="as_duration_hours" value="0" oninput="updateSchedulePreview()"
                                        style="text-align:center;">
                                </div>
                                <div class="col-3">
                                    <label class="form-label-ghost">MM</label>
                                    <input type="number" min="0" max="59" step="1" class="form-control-ghost"
                                        id="as_duration_minutes" value="0" oninput="updateSchedulePreview()"
                                        style="text-align:center;">
                                </div>
                                <div class="col-3">
                                    <label class="form-label-ghost">SS</label>
                                    <input type="number" min="0" max="59" step="1" class="form-control-ghost"
                                        id="as_duration_seconds" value="0" oninput="updateSchedulePreview()"
                                        style="text-align:center;">
                                </div>
                            </div>
                            <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">Example: 21 days
                                00:00:00.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-ghost">🥚 Egg Count</label>
                            <input type="number" min="1" step="1" class="form-control-ghost" id="as_egg_count"
                                placeholder="How many eggs?">
                            <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">This will be saved
                                with the scheduled session.</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div
                                    style="background:rgba(245,166,35,.05);border:1px solid rgba(245,166,35,.15);border-radius:12px;padding:18px;margin-bottom:0;">
                                    <div
                                        style="font-size:.72rem;font-weight:700;color:var(--ghost-amber);letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">
                                        🌡️ Temperature (°C)</div>
                                    <div class="row g-2">
                                        <div class="col-12"><label class="form-label-ghost">Target °C</label><input
                                                type="number" step="0.1" class="form-control-ghost" id="as_target_temp"
                                                style="font-size:1.4rem;font-family:'Bebas Neue',sans-serif;color:var(--ghost-amber);text-align:center;">
                                        </div>
                                        <div class="col-6"><label class="form-label-ghost">Min °C</label><input
                                                type="number" step="0.1" class="form-control-ghost" id="as_min_temp">
                                        </div>
                                        <div class="col-6"><label class="form-label-ghost">Max °C</label><input
                                                type="number" step="0.1" class="form-control-ghost" id="as_max_temp">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div
                                    style="background:rgba(59,130,246,.05);border:1px solid rgba(59,130,246,.15);border-radius:12px;padding:18px;margin-bottom:0;">
                                    <div
                                        style="font-size:.72rem;font-weight:700;color:#3b82f6;letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">
                                        💧 Humidity (%)</div>
                                    <div class="row g-2">
                                        <div class="col-12"><label class="form-label-ghost">Target %</label><input
                                                type="number" step="0.1" class="form-control-ghost" id="as_target_hum"
                                                style="font-size:1.4rem;font-family:'Bebas Neue',sans-serif;color:#3b82f6;text-align:center;">
                                        </div>
                                        <div class="col-6"><label class="form-label-ghost">Min %</label><input
                                                type="number" step="0.1" class="form-control-ghost" id="as_min_hum">
                                        </div>
                                        <div class="col-6"><label class="form-label-ghost">Max %</label><input
                                                type="number" step="0.1" class="form-control-ghost" id="as_max_hum">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Session scheduling block (existing) -->
                        <div
                            style="background:rgba(59,130,246,.05);border:1px solid rgba(59,130,246,.18);border-radius:12px;padding:18px;margin-top:12px;">
                            <div
                                style="font-size:.72rem;font-weight:700;color:#60a5fa;letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">
                                🗓 Session Scheduling</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label-ghost">Start Date</label>
                                    <input type="date" class="form-control-ghost" id="as_start_date"
                                        value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>"
                                        oninput="updateSchedulePreview()">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-ghost">Start Time</label>
                                    <input type="time" class="form-control-ghost" id="as_start_time"
                                        value="<?= date('H:i', strtotime('+1 minute')) ?>"
                                        oninput="updateSchedulePreview()">
                                </div>
                                <div class="col-12">
                                    <div
                                        style="font-size:.75rem;color:var(--ghost-muted);padding:10px 12px;background:rgba(255,255,255,.02);border:1px solid var(--ghost-border);border-radius:8px;">
                                        Session duration is set in the block above. This section previews when the
                                        scheduled session will start and end.
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div
                                        style="background:rgba(245,166,35,.06);border:1px solid rgba(245,166,35,.16);border-radius:10px;padding:12px 14px;height:100%;">
                                        <div
                                            style="font-size:.72rem;font-weight:700;color:#f59e0b;letter-spacing:.12em;text-transform:uppercase;">
                                            🕒 Session Ends</div>
                                        <div style="font-size:1.05rem;font-weight:700;color:white;margin-top:8px;"
                                            id="as_end_text">—</div>
                                        <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:4px;"
                                            id="as_end_subtext">Calculated from the start date and duration.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div
                                        style="background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.16);border-radius:10px;padding:12px 14px;height:100%;">
                                        <div
                                            style="font-size:.72rem;font-weight:700;color:#22c55e;letter-spacing:.12em;text-transform:uppercase;">
                                            ⏳ Active Session Timer</div>
                                        <div style="font-size:1.05rem;font-weight:700;color:white;margin-top:8px;"
                                            id="as_timer_text">—</div>
                                        <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:4px;">Remaining
                                            time for this session.</div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div style="background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.18);border-radius:10px;padding:12px 14px;"
                                        id="as_conflict_box">
                                        <div
                                            style="font-size:.72rem;font-weight:700;color:#f87171;letter-spacing:.12em;text-transform:uppercase;">
                                            Conflict Status</div>
                                        <div style="font-size:.9rem;font-weight:600;color:white;margin-top:6px;"
                                            id="as_conflict_text">No conflict detected.</div>
                                        <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:4px;"
                                            id="as_conflict_subtext">The selected time range is available.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div
                            style="background:rgba(34,197,94,.05);border:1px solid rgba(34,197,94,.15);border-radius:12px;padding:18px;margin-bottom:14px;">
                            <div
                                style="font-size:.72rem;font-weight:700;color:#22c55e;letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">
                                🔄 Egg Swing Settings</div>
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label-ghost">Run for (seconds)</label>
                                    <input type="number" min="5" max="300" step="1" class="form-control-ghost"
                                        id="as_sw_duration" value="30"
                                        style="font-size:1.4rem;font-family:'Bebas Neue',sans-serif;color:#22c55e;text-align:center;">
                                    <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">Recommended:
                                        30-60 seconds per cycle. Max 300 s.</div>
                                </div>
                                <div class="col-12 mt-2">
                                    <div style="font-size:.75rem;color:var(--ghost-muted);margin-bottom:8px;">Quick
                                        presets:</div>
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="document.getElementById('as_sw_duration').value=30">30 s</button>
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="document.getElementById('as_sw_duration').value=45">45 s</button>
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="document.getElementById('as_sw_duration').value=60">60 s</button>
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="document.getElementById('as_sw_duration').value=120">2 min</button>
                                    </div>
                                </div>
                                <div class="col-12 mt-2">
                                    <label class="form-label-ghost">Turn eggs every (hours)</label>
                                    <input type="number" min="0.01" max="24" step="0.01" class="form-control-ghost"
                                        id="as_sw_interval" value="8"
                                        style="font-size:1.4rem;font-family:'Bebas Neue',sans-serif;color:white;text-align:center;">
                                    <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">Recommended:
                                        3–8 hours. Common default: 4 hours. Fractional values are allowed.</div>
                                    <div id="as_sw_interval_preview"
                                        style="font-size:.75rem;color:var(--ghost-muted);margin-top:4px;"></div>
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="setTurningIntervalPreset(3)">3 h</button>
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="setTurningIntervalPreset(4)">4 h</button>
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="setTurningIntervalPreset(6)">6 h</button>
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="setTurningIntervalPreset(8)">8 h</button>
                                    </div>
                                    <div style="font-size:.72rem;color:var(--ghost-muted);margin-top:8px;">Egg turning
                                        pauses automatically during the final 3 days before hatch.</div>
                                </div>
                            </div>
                        </div>

                        <div
                            style="font-size:.78rem;color:var(--ghost-muted);padding:10px 14px;background:rgba(255,255,255,.02);border-radius:8px;border:1px solid var(--ghost-border);">
                            <i class="fas fa-info-circle me-1" style="color:var(--ghost-amber);"></i>
                            These settings mirror the incubation parameter modal so each scheduled task can carry the
                            same defaults.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button><button
                    class="btn-ghost" onclick="addSched()"><i class="fas fa-save me-2"></i>Save</button></div>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade modal-ghost" id="editSchedModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width:980px;">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title"><i class="fas fa-edit me-2" style="color:var(--ghost-blue)"></i>Edit
                        Schedule</h5>
                    <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:2px;">Update the same incubation
                        settings used when creating the schedule.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:24px;">
                <input type="hidden" id="es2_id">
                <input type="hidden" id="es2_action_type">
                <input type="hidden" id="es2_orig_status">
                <div class="row g-4 align-items-start">
                    <div class="col-lg-7">
                        <div class="row g-3 mb-3">
                            <div class="col-12">
                                <label class="form-label-ghost">Schedule Title</label>
                                <input type="text" class="form-control-ghost" id="es2_title"
                                    placeholder="Enter schedule title">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-ghost">Incubator</label>
                                <select class="form-select-ghost" id="es2_incubator"
                                    onchange="syncScheduleIncubator('es2')">
                                    <?php foreach($incubators as $inc): ?>
                                    <option value="<?= $inc['id'] ?>"
                                        data-loc="<?= htmlspecialchars($inc['location'] ?? '') ?>"
                                        data-cap="<?= htmlspecialchars($inc['capacity'] ?? '') ?>">
                                        <?= htmlspecialchars($inc['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-ghost">Batch</label>
                                <select class="form-select-ghost" id="es2_batch"
                                    onchange="syncScheduleIncubator('es2')">
                                    <option value="">General</option>
                                    <?php foreach($myBatches as $batch): ?>
                                    <option value="<?= $batch['id'] ?>" data-inc="<?= $batch['incubator_id'] ?>">
                                        <?= htmlspecialchars($batch['batch_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div id="es2_inc_info_bar"
                            style="background:rgba(245,166,35,.06);border:1px solid rgba(245,166,35,.2);border-radius:10px;padding:12px 16px;margin-bottom:20px;display:flex;gap:20px;flex-wrap:wrap;">
                            <div>
                                <div
                                    style="font-size:.7rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.06em;">
                                    Incubator</div>
                                <div style="font-weight:700;color:white;font-size:.9rem;" id="es2_info_incubator">—
                                </div>
                            </div>
                            <div>
                                <div
                                    style="font-size:.7rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.06em;">
                                    Capacity</div>
                                <div style="font-weight:700;color:var(--ghost-amber);font-size:.9rem;"
                                    id="es2_info_cap">—</div>
                            </div>
                            <div>
                                <div
                                    style="font-size:.7rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.06em;">
                                    Location</div>
                                <div style="font-weight:700;color:white;font-size:.9rem;" id="es2_info_loc">—</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-ghost">⏳ Session Duration</label>
                            <div class="row g-2">
                                <div class="col-3">
                                    <label class="form-label-ghost">Day</label>
                                    <input type="number" min="0" step="1" class="form-control-ghost"
                                        id="es2_duration_days" value="21" oninput="updateSchedulePreview('es2')"
                                        style="text-align:center;">
                                </div>
                                <div class="col-3">
                                    <label class="form-label-ghost">HH</label>
                                    <input type="number" min="0" max="23" step="1" class="form-control-ghost"
                                        id="es2_duration_hours" value="0" oninput="updateSchedulePreview('es2')"
                                        style="text-align:center;">
                                </div>
                                <div class="col-3">
                                    <label class="form-label-ghost">MM</label>
                                    <input type="number" min="0" max="59" step="1" class="form-control-ghost"
                                        id="es2_duration_minutes" value="0" oninput="updateSchedulePreview('es2')"
                                        style="text-align:center;">
                                </div>
                                <div class="col-3">
                                    <label class="form-label-ghost">SS</label>
                                    <input type="number" min="0" max="59" step="1" class="form-control-ghost"
                                        id="es2_duration_seconds" value="0" oninput="updateSchedulePreview('es2')"
                                        style="text-align:center;">
                                </div>
                            </div>
                            <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">Example: 21 days
                                00:00:00.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-ghost">🥚 Egg Count</label>
                            <input type="number" min="1" step="1" class="form-control-ghost" id="es2_egg_count"
                                placeholder="How many eggs?">
                            <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">This will be saved
                                with the scheduled session.</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div
                                    style="background:rgba(245,166,35,.05);border:1px solid rgba(245,166,35,.15);border-radius:12px;padding:18px;margin-bottom:0;">
                                    <div
                                        style="font-size:.72rem;font-weight:700;color:var(--ghost-amber);letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">
                                        🌡️ Temperature (°C)</div>
                                    <div class="row g-2">
                                        <div class="col-12"><label class="form-label-ghost">Target °C</label><input
                                                type="number" step="0.1" class="form-control-ghost" id="es2_target_temp"
                                                style="font-size:1.4rem;font-family:'Bebas Neue',sans-serif;color:var(--ghost-amber);text-align:center;">
                                        </div>
                                        <div class="col-6"><label class="form-label-ghost">Min °C</label><input
                                                type="number" step="0.1" class="form-control-ghost" id="es2_min_temp">
                                        </div>
                                        <div class="col-6"><label class="form-label-ghost">Max °C</label><input
                                                type="number" step="0.1" class="form-control-ghost" id="es2_max_temp">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div
                                    style="background:rgba(59,130,246,.05);border:1px solid rgba(59,130,246,.15);border-radius:12px;padding:18px;margin-bottom:0;">
                                    <div
                                        style="font-size:.72rem;font-weight:700;color:#3b82f6;letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">
                                        💧 Humidity (%)</div>
                                    <div class="row g-2">
                                        <div class="col-12"><label class="form-label-ghost">Target %</label><input
                                                type="number" step="0.1" class="form-control-ghost" id="es2_target_hum"
                                                style="font-size:1.4rem;font-family:'Bebas Neue',sans-serif;color:#3b82f6;text-align:center;">
                                        </div>
                                        <div class="col-6"><label class="form-label-ghost">Min %</label><input
                                                type="number" step="0.1" class="form-control-ghost" id="es2_min_hum">
                                        </div>
                                        <div class="col-6"><label class="form-label-ghost">Max %</label><input
                                                type="number" step="0.1" class="form-control-ghost" id="es2_max_hum">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            style="background:rgba(59,130,246,.05);border:1px solid rgba(59,130,246,.18);border-radius:12px;padding:18px;margin-top:12px;">
                            <div
                                style="font-size:.72rem;font-weight:700;color:#60a5fa;letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">
                                🗓 Session Scheduling</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label-ghost">Start Date</label>
                                    <input type="date" class="form-control-ghost" id="es2_start_date"
                                        value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>"
                                        oninput="updateSchedulePreview('es2')">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-ghost">Start Time</label>
                                    <input type="time" class="form-control-ghost" id="es2_start_time"
                                        value="<?= date('H:i', strtotime('+1 minute')) ?>"
                                        oninput="updateSchedulePreview('es2')">
                                </div>
                                <div class="col-12">
                                    <div
                                        style="font-size:.75rem;color:var(--ghost-muted);padding:10px 12px;background:rgba(255,255,255,.02);border:1px solid var(--ghost-border);border-radius:8px;">
                                        Session duration is set in the block above. This section previews when the
                                        scheduled session will start and end.
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div
                                        style="background:rgba(245,166,35,.06);border:1px solid rgba(245,166,35,.16);border-radius:10px;padding:12px 14px;height:100%;">
                                        <div
                                            style="font-size:.72rem;font-weight:700;color:#f59e0b;letter-spacing:.12em;text-transform:uppercase;">
                                            🕒 Session Ends</div>
                                        <div style="font-size:1.05rem;font-weight:700;color:white;margin-top:8px;"
                                            id="es2_end_text">—</div>
                                        <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:4px;"
                                            id="es2_end_subtext">Calculated from the start date and duration.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div
                                        style="background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.16);border-radius:10px;padding:12px 14px;height:100%;">
                                        <div
                                            style="font-size:.72rem;font-weight:700;color:#22c55e;letter-spacing:.12em;text-transform:uppercase;">
                                            ⏳ Active Session Timer</div>
                                        <div style="font-size:1.05rem;font-weight:700;color:white;margin-top:8px;"
                                            id="es2_timer_text">—</div>
                                        <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:4px;">Remaining
                                            time for this session.</div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div style="background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.18);border-radius:10px;padding:12px 14px;"
                                        id="es2_conflict_box">
                                        <div
                                            style="font-size:.72rem;font-weight:700;color:#f87171;letter-spacing:.12em;text-transform:uppercase;">
                                            Conflict Status</div>
                                        <div style="font-size:.9rem;font-weight:600;color:white;margin-top:6px;"
                                            id="es2_conflict_text">No conflict detected.</div>
                                        <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:4px;"
                                            id="es2_conflict_subtext">The selected time range is available.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div
                            style="background:rgba(34,197,94,.05);border:1px solid rgba(34,197,94,.15);border-radius:12px;padding:18px;margin-bottom:14px;">
                            <div
                                style="font-size:.72rem;font-weight:700;color:#22c55e;letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">
                                🔄 Egg Swing Settings</div>
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label-ghost">Run for (seconds)</label>
                                    <input type="number" min="5" max="300" step="1" class="form-control-ghost"
                                        id="es2_sw_duration" value="30"
                                        style="font-size:1.4rem;font-family:'Bebas Neue',sans-serif;color:#22c55e;text-align:center;">
                                    <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">Recommended:
                                        30-60 seconds per cycle. Max 300 s.</div>
                                </div>
                                <div class="col-12 mt-2">
                                    <div style="font-size:.75rem;color:var(--ghost-muted);margin-bottom:8px;">Quick
                                        presets:</div>
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="document.getElementById('es2_sw_duration').value=30">30 s</button>
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="document.getElementById('es2_sw_duration').value=45">45 s</button>
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="document.getElementById('es2_sw_duration').value=60">60 s</button>
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="document.getElementById('es2_sw_duration').value=120">2
                                            min</button>
                                    </div>
                                </div>
                                <div class="col-12 mt-2">
                                    <label class="form-label-ghost">Turn eggs every (hours)</label>
                                    <input type="number" min="0.01" max="24" step="0.01" class="form-control-ghost"
                                        id="es2_sw_interval" value="8"
                                        style="font-size:1.4rem;font-family:'Bebas Neue',sans-serif;color:white;text-align:center;"
                                        oninput="updateIntervalPreview('es2_sw_interval', 'es2_sw_interval_preview')">
                                    <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">Recommended:
                                        3–8 hours. Common default: 4 hours. Fractional values are allowed.</div>
                                    <div id="es2_sw_interval_preview"
                                        style="font-size:.75rem;color:var(--ghost-muted);margin-top:4px;"></div>
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="document.getElementById('es2_sw_interval').value=3;updateIntervalPreview('es2_sw_interval','es2_sw_interval_preview')">3
                                            h</button>
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="document.getElementById('es2_sw_interval').value=4;updateIntervalPreview('es2_sw_interval','es2_sw_interval_preview')">4
                                            h</button>
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="document.getElementById('es2_sw_interval').value=6;updateIntervalPreview('es2_sw_interval','es2_sw_interval_preview')">6
                                            h</button>
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="document.getElementById('es2_sw_interval').value=8;updateIntervalPreview('es2_sw_interval','es2_sw_interval_preview')">8
                                            h</button>
                                    </div>
                                    <div style="font-size:.72rem;color:var(--ghost-muted);margin-top:8px;">Egg turning
                                        pauses automatically during the final 3 days before hatch.</div>
                                </div>
                            </div>
                        </div>

                        <div
                            style="font-size:.78rem;color:var(--ghost-muted);padding:10px 14px;background:rgba(255,255,255,.02);border-radius:8px;border:1px solid var(--ghost-border);">
                            <i class="fas fa-info-circle me-1" style="color:var(--ghost-amber);"></i>
                            These settings mirror the incubation parameter modal so each scheduled task can carry the
                            same defaults.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button><button
                    class="btn-ghost" onclick="updateSched()"><i class="fas fa-save me-2"></i>Update</button></div>
        </div>
    </div>
</div>

<script>
const incubatorSettingPresets = <?= json_encode($settingsByIncubator, JSON_UNESCAPED_SLASHES) ?>;
const scheduleConflictWindows = <?= json_encode($scheduleConflictWindows, JSON_UNESCAPED_SLASHES) ?>;

function applyIncubatorPresets(prefix = 'as', force = false) {
    const incubatorSelect = document.getElementById(`${prefix}_incubator`);
    if (!incubatorSelect) return;

    const incubatorId = parseInt(incubatorSelect.value, 10);
    const preset = incubatorSettingPresets[String(incubatorId)] || incubatorSettingPresets[incubatorId];
    if (!preset) return;

    const fieldMap = [
        ['target_temp', `${prefix}_target_temp`],
        ['min_temp', `${prefix}_min_temp`],
        ['max_temp', `${prefix}_max_temp`],
        ['target_humidity', `${prefix}_target_hum`],
        ['min_humidity', `${prefix}_min_hum`],
        ['max_humidity', `${prefix}_max_hum`]
    ];

    fieldMap.forEach(([presetKey, inputId]) => {
        const input = document.getElementById(inputId);
        if (!input) return;
        const hasValue = String(input.value || '').trim() !== '';
        if (!force && hasValue) return;
        const presetValue = preset[presetKey];
        if (presetValue === null || typeof presetValue === 'undefined' || presetValue === '') return;
        input.value = presetValue;
    });
}

function formatIntervalMinutes(hours) {
    if (!Number.isFinite(hours) || hours <= 0) return '';
    const minutes = hours * 60;
    const rounded = (Math.round(minutes * 10) / 10).toFixed(minutes % 1 === 0 ? 0 : 1);
    return `≈ ${rounded} min`;
}

function updateIntervalPreview(inputId, outputId) {
    const input = document.getElementById(inputId);
    const output = document.getElementById(outputId);
    if (!input || !output) return;
    const hours = parseFloat(input.value);
    output.textContent = formatIntervalMinutes(hours);
}

window.addEventListener('load', function() {
    const intervalInput = document.getElementById('as_sw_interval');
    if (intervalInput) {
        intervalInput.addEventListener('input', function() {
            updateIntervalPreview('as_sw_interval', 'as_sw_interval_preview');
        });
        updateIntervalPreview('as_sw_interval', 'as_sw_interval_preview');
    }
});

function addSched() {
    const durationDays = parseInt($('#as_duration_days').val(), 10) || 0;
    const durationHours = parseInt($('#as_duration_hours').val(), 10) || 0;
    const durationMinutes = parseInt($('#as_duration_minutes').val(), 10) || 0;
    const durationSeconds = parseInt($('#as_duration_seconds').val(), 10) || 0;
    const startDate = $('#as_start_date').val();
    const startTime = $('#as_start_time').val();
    const swingDuration = parseInt($('#as_sw_duration').val(), 10);
    const turningInterval = parseFloat($('#as_sw_interval').val());

    if (!Number.isFinite(swingDuration) || swingDuration < 5 || swingDuration > 300) {
        showToast('Swing duration must be between 5 and 300 seconds.', 'error');
        return;
    }
    if (!Number.isFinite(turningInterval) || turningInterval < 0.01 || turningInterval > 24) {
        showToast('Invalid turning interval.', 'error');
        return;
    }
    const newStart = new Date(startDate + ' ' + (startTime || '00:00'));
    if (isNaN(newStart.getTime()) || newStart.getTime() <= Date.now()) {
        showToast('Please choose a future start date and time.', 'error');
        return;
    }
    showLoader('Saving Schedule…');
    $.ajax({
        url: '../ajax/user_schedules.php',
        method: 'POST',
        data: {
            action: 'add',
            title: $('#as_title').val(),
            batch_id: $('#as_batch').val(),
            incubator_id: $('#as_incubator').val(),
            start_date: startDate,
            start_time: startTime,
            date: startDate,
            time: startTime,
            target_temp: $('#as_target_temp').val(),
            min_temp: $('#as_min_temp').val(),
            max_temp: $('#as_max_temp').val(),
            target_humidity: $('#as_target_hum').val(),
            min_humidity: $('#as_min_hum').val(),
            max_humidity: $('#as_max_hum').val(),
            swing_duration_sec: swingDuration,
            turning_interval: turningInterval,
            egg_count: $('#as_egg_count').val(),
            duration_days: durationDays,
            duration_hours: durationHours,
            duration_minutes: durationMinutes,
            duration_seconds: durationSeconds
        },
        dataType: 'json',
        success: r => {
            hideLoader();
            if (r.success) {
                showToast('Schedule added!');
                setTimeout(() => location.reload(), 700);
            } else showToast(r.message, 'error');
        },
        error: () => {
            hideLoader();
            showToast('Server error.', 'error');
        }
    });
}

function syncScheduleIncubator(prefix = 'as') {
    const batchSelect = document.getElementById(`${prefix}_batch`);
    const incubatorSelect = document.getElementById(`${prefix}_incubator`);
    const incubatorInfo = document.getElementById(`${prefix}_info_incubator`);
    const incubatorCap = document.getElementById(`${prefix}_info_cap`);
    const incubatorLoc = document.getElementById(`${prefix}_info_loc`);

    if (batchSelect && incubatorSelect) {
        const batchOption = batchSelect.options[batchSelect.selectedIndex];
        if (batchOption && batchOption.dataset && batchOption.dataset.inc) {
            incubatorSelect.value = batchOption.dataset.inc;
        }
    }

    const selectedIncubator = incubatorSelect && incubatorSelect.options[incubatorSelect.selectedIndex] ?
        incubatorSelect.options[incubatorSelect.selectedIndex] :
        null;

    if (incubatorInfo) {
        incubatorInfo.textContent = selectedIncubator ? selectedIncubator.textContent : '—';
    }
    if (incubatorCap) {
        incubatorCap.textContent = selectedIncubator && selectedIncubator.dataset && selectedIncubator.dataset.cap ?
            `${selectedIncubator.dataset.cap} eggs` :
            '—';
    }
    if (incubatorLoc) {
        incubatorLoc.textContent = selectedIncubator && selectedIncubator.dataset && selectedIncubator.dataset.loc ?
            selectedIncubator.dataset.loc :
            '—';
    }
    // Auto-fill temp/humidity from incubator settings if fields are empty.
    applyIncubatorPresets(prefix, false);
    updateSchedulePreview(prefix);
}

function resetScheduleDurationDefaults(prefix = 'as') {
    document.getElementById(`${prefix}_start_date`).value = new Date().toISOString().slice(0, 10);
    document.getElementById(`${prefix}_start_time`).value = new Date().toTimeString().slice(0, 5);
    document.getElementById(`${prefix}_duration_days`).value = 21;
    document.getElementById(`${prefix}_duration_hours`).value = 0;
    document.getElementById(`${prefix}_duration_minutes`).value = 0;
    document.getElementById(`${prefix}_duration_seconds`).value = 0;
    document.getElementById(`${prefix}_sw_duration`).value = 30;
    document.getElementById(`${prefix}_sw_interval`).value = 8;
    const titleInput = document.getElementById(`${prefix}_title`);
    if (titleInput) titleInput.value = '';
    // On add flow, always refresh to latest incubator presets.
    applyIncubatorPresets(prefix, true);
    updateSchedulePreview(prefix);
}

function updateSchedulePreview(prefix = 'as') {
    const startDate = document.getElementById(`${prefix}_start_date`)?.value;
    const startTime = document.getElementById(`${prefix}_start_time`)?.value || '00:00';
    const incubatorId = document.getElementById(`${prefix}_incubator`)?.value || '';
    const batchId = document.getElementById(`${prefix}_batch`)?.value || '';
    const days = parseInt(document.getElementById(`${prefix}_duration_days`)?.value, 10) || 0;
    const hours = parseInt(document.getElementById(`${prefix}_duration_hours`)?.value, 10) || 0;
    const minutes = parseInt(document.getElementById(`${prefix}_duration_minutes`)?.value, 10) || 0;
    const seconds = parseInt(document.getElementById(`${prefix}_duration_seconds`)?.value, 10) || 0;
    const endText = document.getElementById(`${prefix}_end_text`);
    const endSubtext = document.getElementById(`${prefix}_end_subtext`);
    const timerText = document.getElementById(`${prefix}_timer_text`);
    if (!startDate || !endText || !endSubtext || !timerText) return;

    const start = new Date(`${startDate}T${startTime}`);
    if (isNaN(start.getTime())) {
        endText.textContent = '—';
        endSubtext.textContent = 'Enter a valid start date and time.';
        timerText.textContent = '—';
        return;
    }

    const durationMs = (((days * 24) + hours) * 60 + minutes) * 60 * 1000 + (seconds * 1000);
    const end = new Date(start.getTime() + durationMs);
    const endDateText = end.toLocaleString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    });
    const endTimeText = end.toLocaleString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });
    endText.textContent = `${endDateText} - ${endTimeText}`;
    endSubtext.textContent =
        `Session starts ${start.toLocaleString('en-US', { month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' })}`;

    const remaining = Math.max(0, Math.floor((end.getTime() - Date.now()) / 1000));
    const remainingDays = Math.floor(remaining / 86400);
    const remainingHours = Math.floor((remaining % 86400) / 3600);
    const remainingMinutes = Math.floor((remaining % 3600) / 60);
    timerText.textContent =
        `${remainingDays} Days ${String(remainingHours).padStart(2, '0')} Hours ${String(remainingMinutes).padStart(2, '0')} Minutes`;

    const conflictBox = document.getElementById(`${prefix}_conflict_box`);
    const conflictText = document.getElementById(`${prefix}_conflict_text`);
    const conflictSubtext = document.getElementById(`${prefix}_conflict_subtext`);
    if (conflictBox && conflictText && conflictSubtext) {
        const excludeId = prefix === 'es2' ? document.getElementById('es2_id')?.value : null;
        const conflict = detectScheduleConflict(start.getTime(), end.getTime(), incubatorId, excludeId, batchId);
        if (conflict) {
            conflictBox.style.borderColor = 'rgba(239,68,68,.22)';
            const conflictPrefix = conflict.source === 'active_session'
                ? 'Active session'
                : conflict.source === 'batch'
                    ? 'Batch'
                    : 'Schedule';
            conflictText.textContent = `Conflicts with ${conflictPrefix}: ${conflict.title}`;
            conflictSubtext.textContent = `Overlaps ${formatPreviewDateTime(conflict.start)} → ${formatPreviewDateTime(conflict.end)}`;
        } else {
            conflictBox.style.borderColor = 'rgba(34,197,94,.18)';
            conflictText.textContent = 'No conflict detected.';
            conflictSubtext.textContent = 'The selected time range is available.';
        }
    }
}

function formatPreviewDateTime(epochSeconds) {
    const dt = new Date(epochSeconds * 1000);
    if (Number.isNaN(dt.getTime())) return '—';
    return dt.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function detectScheduleConflict(newStartMs, newEndMs, incubatorId = '', excludeScheduleId = null, excludeBatchId = null) {
    if (!Array.isArray(scheduleConflictWindows)) return null;
    const newStart = Math.floor(newStartMs / 1000);
    const newEnd = Math.floor(newEndMs / 1000);
    const excludedId = excludeScheduleId !== null && excludeScheduleId !== undefined && String(excludeScheduleId).trim() !== ''
        ? String(excludeScheduleId)
        : null;
    const excludedBatchId = excludeBatchId !== null && excludeBatchId !== undefined && String(excludeBatchId).trim() !== ''
        ? String(excludeBatchId)
        : null;
    const selectedIncubatorId = incubatorId !== null && incubatorId !== undefined && String(incubatorId).trim() !== ''
        ? String(incubatorId)
        : null;
    return scheduleConflictWindows.find(window => {
        if (selectedIncubatorId !== null && String(window.incubator_id || '') !== selectedIncubatorId) {
            return false;
        }
        if (excludedId !== null && String(window.id) === excludedId) {
            return false;
        }
        if (excludedBatchId !== null && window.source === 'batch' && String(window.id) === excludedBatchId) {
            return false;
        }
        return newStart < window.end && newEnd > window.start;
    }) || null;
}

document.getElementById('addSchedModal').addEventListener('show.bs.modal', function() {
    syncScheduleIncubator('as');
    resetScheduleDurationDefaults('as');
    syncScheduleIncubator('as');
});

document.getElementById('editSchedModal')?.addEventListener('show.bs.modal', function() {
    syncScheduleIncubator('es2');
    updateSchedulePreview('es2');
});

function editSched(s) {
    let desc = {};
    try {
        desc = s.description ? JSON.parse(s.description) : {};
    } catch (e) {
        desc = {};
    }
    $('#es2_id').val(s.id);
    $('#es2_action_type').val(s.action_type || 'turning');
    $('#es2_title').val(s.title);
    $('#es2_incubator').val(s.incubator_id || '');
    $('#es2_batch').val(s.batch_id || '');
    $('#es2_duration_days').val(desc.duration_days ?? 21);
    $('#es2_duration_hours').val(desc.duration_hours ?? 0);
    $('#es2_duration_minutes').val(desc.duration_minutes ?? 0);
    $('#es2_duration_seconds').val(desc.duration_seconds ?? 0);
    $('#es2_egg_count').val(desc.egg_count ?? '');
    $('#es2_target_temp').val(desc.target_temp ?? '');
    $('#es2_min_temp').val(desc.min_temp ?? '');
    $('#es2_max_temp').val(desc.max_temp ?? '');
    $('#es2_target_hum').val(desc.target_humidity ?? '');
    $('#es2_min_hum').val(desc.min_humidity ?? '');
    $('#es2_max_hum').val(desc.max_humidity ?? '');
    $('#es2_sw_duration').val(desc.swing_duration_sec ?? 30);
    $('#es2_sw_interval').val(desc.turning_interval ?? 8);
    $('#es2_start_date').val(s.scheduled_date);
    $('#es2_start_time').val((s.scheduled_time || '').substring(0, 5));
    $('#es2_orig_status').val(s.status || '');
    syncScheduleIncubator('es2');
    updateIntervalPreview('es2_sw_interval', 'es2_sw_interval_preview');
    updateSchedulePreview('es2');
    new bootstrap.Modal(document.getElementById('editSchedModal')).show();
}

function updateSched() {
    const durationDays = parseInt($('#es2_duration_days').val(), 10) || 0;
    const durationHours = parseInt($('#es2_duration_hours').val(), 10) || 0;
    const durationMinutes = parseInt($('#es2_duration_minutes').val(), 10) || 0;
    const durationSeconds = parseInt($('#es2_duration_seconds').val(), 10) || 0;
    const startDate = $('#es2_start_date').val();
    const startTime = $('#es2_start_time').val();
    const swingDuration = parseInt($('#es2_sw_duration').val(), 10);
    const turningInterval = parseFloat($('#es2_sw_interval').val());

    if (!Number.isFinite(swingDuration) || swingDuration < 5 || swingDuration > 300) {
        showToast('Swing duration must be between 5 and 300 seconds.', 'error');
        return;
    }
    if (!Number.isFinite(turningInterval) || turningInterval < 0.01 || turningInterval > 24) {
        showToast('Invalid turning interval.', 'error');
        return;
    }

    function performUpdate() {
        showLoader('Updating Schedule…');
        $.ajax({
            url: '../ajax/user_schedules.php',
            method: 'POST',
            data: {
                action: 'update',
                id: $('#es2_id').val(),
                title: $('#es2_title').val(),
                batch_id: $('#es2_batch').val(),
                incubator_id: $('#es2_incubator').val(),
                date: startDate,
                time: startTime,
                target_temp: $('#es2_target_temp').val(),
                min_temp: $('#es2_min_temp').val(),
                max_temp: $('#es2_max_temp').val(),
                target_humidity: $('#es2_target_hum').val(),
                min_humidity: $('#es2_min_hum').val(),
                max_humidity: $('#es2_max_hum').val(),
                swing_duration_sec: swingDuration,
                turning_interval: turningInterval,
                egg_count: $('#es2_egg_count').val(),
                duration_days: durationDays,
                duration_hours: durationHours,
                duration_minutes: durationMinutes,
                duration_seconds: durationSeconds,
                action_type: $('#es2_action_type').val()
            },
            dataType: 'json',
            success: r => {
                hideLoader();
                if (r.success) {
                    showToast('Updated!');
                    setTimeout(() => location.reload(), 700);
                } else showToast(r.message, 'error');
            },
            error: () => {
                hideLoader();
                showToast('Server error.', 'error');
            }
        });
    }

    // If original status was 'done' and user is moving the schedule into the future, confirm
    const origStatus = ($('#es2_orig_status').val() || '').toLowerCase();
    const newStart = new Date(startDate + ' ' + (startTime || '00:00'));
    const now = new Date();
    if (newStart.getTime() <= now.getTime()) {
        showToast('Please choose a future start date and time.', 'error');
        return;
    }
    if (origStatus === 'done' && newStart.getTime() > now.getTime()) {
        showConfirm('Reschedule completed task',
            'This schedule is currently marked "done". Re-scheduling it to a future time will set it back to pending/scheduled. Continue?',
            function() {
                performUpdate();
            });
        return;
    }

    // Otherwise proceed directly
    performUpdate();
}

function cancelSched(id) {
    showConfirm('Cancel schedule', 'Cancel this schedule? It will not be deleted.', function() {
        showLoader('Cancelling…');
        $.post('../ajax/user_schedules.php', {
            action: 'delete',
            id
        }, r => {
            hideLoader();
            if (r.success) {
                showToast('Cancelled');
                setTimeout(() => location.reload(), 600);
            } else showToast(r.message, 'error');
        }, 'json');
    });
}

function markDone(id) {
    showLoader('Marking Done…');
    $.post('../ajax/user_schedules.php', {
        action: 'mark_done',
        id
    }, r => {
        hideLoader();
        if (r.success) {
            showToast('Marked done!');
            setTimeout(() => location.reload(), 600);
        } else showToast(r.message, 'error');
    }, 'json');
}

function setTurningIntervalPreset(h) {
    const el = document.getElementById('as_sw_interval') || document.getElementById('sw_interval');
    if (el) {
        el.value = h;
        updateIntervalPreview('as_sw_interval', 'as_sw_interval_preview');
    }
}

// Auto-adjust min/max to ±3 when target temperature/humidity inputs change
;
(function() {
    const tIn = document.getElementById('as_target_temp');
    const hIn = document.getElementById('as_target_hum');
    if (tIn) tIn.addEventListener('input', function() {
        const v = parseFloat(this.value);
        if (!isNaN(v)) {
            const min = (v - 3).toFixed(1);
            const max = (v + 3).toFixed(1);
            const elMin = document.getElementById('as_min_temp');
            const elMax = document.getElementById('as_max_temp');
            if (elMin) elMin.value = min;
            if (elMax) elMax.value = max;
        }
    });
    if (hIn) hIn.addEventListener('input', function() {
        const v = parseFloat(this.value);
        if (!isNaN(v)) {
            const min = (v - 3).toFixed(1);
            const max = (v + 3).toFixed(1);
            const elMin = document.getElementById('as_min_hum');
            const elMax = document.getElementById('as_max_hum');
            if (elMin) elMin.value = min;
            if (elMax) elMax.value = max;
        }
    });
})();
</script>
<?php require_once 'footer.php'; ?>