<?php
require_once '../includes/config.php';
requireAdmin();
header('Location: incubators.php');
exit();

$schedules = $pdo->query(
  "SELECT s.*, 
          COALESCE(i.incubator_name, i.name, CONCAT('Incubator #', s.incubator_id)) AS incubator_name,
          b.batch_name,
          b.egg_count AS batch_egg_count,
          hs.device_status,
          hs.last_seen,
          hs.session_status,
          CASE
            WHEN b.waiting_for_device_until IS NOT NULL AND b.waiting_for_device_until > NOW() THEN 1
            ELSE 0
          END AS waiting_for_device
   FROM schedules s
   LEFT JOIN incubators i ON s.incubator_id = i.id
   LEFT JOIN batches b ON s.batch_id = b.id
   LEFT JOIN hardware_state hs ON hs.incubator_id = s.incubator_id
   ORDER BY s.scheduled_date DESC, s.scheduled_time DESC"
)->fetchAll();

$incubators = $pdo->query(
  "SELECT i.id,
          COALESCE(i.incubator_name, i.name, CONCAT('Incubator #', i.id)) AS name,
          COALESCE(i.location, '-') AS location,
          COALESCE(i.capacity, 0) AS capacity,
          COALESCE(hs.device_status, 'offline') AS device_status,
          hs.last_seen,
          COALESCE(hs.session_status, 'idle') AS session_status,
          (SELECT COUNT(*) FROM schedules s WHERE s.incubator_id = i.id AND s.status = 'pending') AS pending_count,
          (SELECT COUNT(*) FROM schedules s WHERE s.incubator_id = i.id AND s.status = 'running') AS running_count,
          (SELECT COUNT(*) FROM batches b WHERE b.incubator_id = i.id AND b.waiting_for_device_until IS NOT NULL AND b.waiting_for_device_until > NOW()) AS waiting_count
   FROM incubators i
   LEFT JOIN hardware_state hs ON hs.incubator_id = i.id
   ORDER BY i.id ASC"
)->fetchAll();

$settingsRows = $pdo->query(
  "SELECT incubator_id, target_temp, min_temp, max_temp, target_humidity, min_humidity, max_humidity
   FROM temperature_settings"
)->fetchAll();
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

$batches = $pdo->query(
  "SELECT id, batch_name, incubator_id
   FROM batches
   WHERE status IN ('scheduled','incubating')
   ORDER BY id DESC"
)->fetchAll();

function scheduleDurationSeconds(array $schedule): int {
  if (!empty($schedule['description'])) {
    $decoded = json_decode((string)$schedule['description'], true);
    if (is_array($decoded) && isset($decoded['session_duration_sec'])) {
      return max(1, (int)$decoded['session_duration_sec']);
    }
  }

  if (isset($schedule['duration_hours']) && $schedule['duration_hours'] !== null && $schedule['duration_hours'] !== '') {
    return max(1, (int)round(((float)$schedule['duration_hours']) * 3600));
  }

  return 21 * 86400;
}

function scheduleDateLabel(array $schedule): string {
  $ts = strtotime(trim((string)($schedule['scheduled_date'] ?? '') . ' ' . trim((string)($schedule['scheduled_time'] ?? '00:00:00'))));
  return $ts ? date('M j, Y H:i', $ts) : '—';
}

function scheduleEndLabel(array $schedule): string {
  $start = strtotime(trim((string)($schedule['scheduled_date'] ?? '') . ' ' . trim((string)($schedule['scheduled_time'] ?? '00:00:00'))));
  if (!$start) {
    return '—';
  }
  return date('M j, Y H:i', $start + scheduleDurationSeconds($schedule));
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

function durationLabelFromSeconds(int $seconds): string {
  $seconds = max(0, $seconds);
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
foreach ($schedules as $s) {
  $status = trim((string)($s['status'] ?? ''));
  if (!in_array($status, ['pending', 'running'], true)) {
    continue;
  }
  $start = strtotime(trim((string)($s['scheduled_date'] ?? '') . ' ' . trim((string)($s['scheduled_time'] ?? '00:00:00'))));
  if (!$start) {
    continue;
  }
  $scheduleConflictWindows[] = [
    'id' => (int)$s['id'],
    'title' => (string)$s['title'],
    'batch_name' => !empty($s['batch_name']) ? (string)$s['batch_name'] : 'General',
    'egg_count' => isset($s['batch_egg_count']) ? (int)$s['batch_egg_count'] : null,
    'incubator_id' => (int)($s['incubator_id'] ?? 0),
    'start' => $start,
    'end' => $start + scheduleDurationSeconds($s),
    'duration_label' => scheduleDurationLabel($s),
    'source' => 'schedule',
    'status' => $status
  ];
}

foreach ($batches as $b) {
  $batchStmt = $pdo->prepare("SELECT start_date, expected_hatch_date, notes, status, egg_count FROM batches WHERE id=? LIMIT 1");
  $batchStmt->execute([(int)$b['id']]);
  $row = $batchStmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    continue;
  }

  $start = null;
  if (!empty($row['notes']) && preg_match('/SCHEDULED_START:\\s*(\\d{4}-\\d{2}-\\d{2}\\s+\\d{2}:\\d{2}:\\d{2})/', $row['notes'], $m)) {
    $start = strtotime($m[1]);
  }
  if (!$start && !empty($row['start_date'])) {
    $start = strtotime($row['start_date'] . ' 00:00:00');
  }
  $end = !empty($row['expected_hatch_date']) ? strtotime($row['expected_hatch_date'] . ' 23:59:59') : false;
  if ($start && $end) {
    $scheduleConflictWindows[] = [
      'id' => (int)$b['id'],
      'title' => (string)$b['batch_name'],
      'batch_name' => (string)$b['batch_name'],
      'egg_count' => isset($row['egg_count']) ? (int)$row['egg_count'] : null,
      'incubator_id' => (int)($b['incubator_id'] ?? 0),
      'start' => $start,
      'end' => $end,
      'duration_label' => durationLabelFromSeconds($end - $start),
      'source' => 'batch',
      'status' => (string)($row['status'] ?? '')
    ];
  }
}
?>

<div class="ghost-panel mb-4">
  <div class="ghost-panel-header"><span class="ghost-panel-title">Scheduler Status</span></div>
  <div class="ghost-panel-body">
    <div class="row g-3">
      <?php foreach($incubators as $inc): ?>
      <div class="col-md-6 col-xl-4">
        <div style="background:rgba(255,255,255,.02);border:1px solid var(--ghost-border);border-radius:12px;padding:14px;">
          <div class="d-flex justify-content-between align-items-center">
            <div style="font-weight:700;color:white;"><?= htmlspecialchars($inc['name']) ?></div>
            <?php if (($inc['device_status'] ?? 'offline') === 'online'): ?>
              <span class="badge-running">online</span>
            <?php else: ?>
              <span class="badge-failed">offline</span>
            <?php endif; ?>
          </div>
          <div style="font-size:.78rem;color:var(--ghost-muted);margin-top:4px;">Location: <?= htmlspecialchars($inc['location']) ?></div>
          <div style="font-size:.78rem;color:var(--ghost-muted);">Session: <?= htmlspecialchars($inc['session_status']) ?></div>
          <div style="font-size:.78rem;color:var(--ghost-muted);">Last seen: <?= !empty($inc['last_seen']) ? date('M j, H:i:s', strtotime($inc['last_seen'])) : '—' ?></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
            <span class="badge-pending">pending: <?= (int)$inc['pending_count'] ?></span>
            <span class="badge-running">running: <?= (int)$inc['running_count'] ?></span>
            <?php if ((int)$inc['waiting_count'] > 0): ?>
              <span class="badge-missed">waiting: <?= (int)$inc['waiting_count'] ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <button class="btn-outline-ghost" data-filter="all" onclick="filterSchedules('all', this)">All</button>
    <button class="btn-outline-ghost" data-filter="pending" onclick="filterSchedules('pending', this)">Pending</button>
    <button class="btn-outline-ghost" data-filter="running" onclick="filterSchedules('running', this)">Running</button>
    <button class="btn-outline-ghost" data-filter="failed" onclick="filterSchedules('failed', this)">Failed</button>
  </div>
  <button class="btn-ghost" data-bs-toggle="modal" data-bs-target="#addSchedModal"><i class="fas fa-plus me-2"></i>Add Schedule</button>
</div>

<div class="ghost-panel">
  <div class="ghost-panel-header"><span class="ghost-panel-title">All Schedules</span></div>
  <div class="ghost-panel-body p-0">
    <table class="ghost-table" id="scheduleTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Title</th>
          <th>Incubator</th>
          <th>Batch</th>
          <th>Start</th>
          <th>End</th>
          <th>Duration</th>
          <th>Schedule Status</th>
          <th>Device</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($schedules as $idx => $s): ?>
          <?php
            $status = trim((string)($s['status'] ?? 'pending'));
            $badgeClass = 'badge-' . $status;
            $waitingForDevice = (int)($s['waiting_for_device'] ?? 0) === 1;
          ?>
          <tr data-status="<?= htmlspecialchars($status) ?>">
            <td style="font-size:.85rem;color:var(--ghost-muted);font-weight:700;">#<?= $idx + 1 ?></td>
            <td style="font-weight:600;color:white;"><?= htmlspecialchars($s['title']) ?></td>
            <td><?= htmlspecialchars($s['incubator_name'] ?? ('Incubator #' . (int)$s['incubator_id'])) ?></td>
            <td style="font-size:.82rem;color:var(--ghost-muted);"><?= htmlspecialchars($s['batch_name'] ?? 'General') ?></td>
            <td><?= htmlspecialchars(scheduleDateLabel($s)) ?></td>
            <td><?= htmlspecialchars(scheduleEndLabel($s)) ?></td>
            <td><?= htmlspecialchars(scheduleDurationLabel($s)) ?></td>
            <td>
              <?php if ($waitingForDevice): ?>
                <span class="badge-missed">waiting_for_device</span>
              <?php else: ?>
                <span class="<?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (($s['device_status'] ?? 'offline') === 'online'): ?>
                <span class="badge-running">online</span>
              <?php else: ?>
                <span class="badge-failed">offline</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="d-flex gap-2">
                <?php if ($status === 'pending'): ?>
                  <button class="btn-edit-ghost" onclick="markDone(<?= (int)$s['id'] ?>)">✓</button>
                <?php endif; ?>
                <?php if (in_array($status, ['pending', 'running'], true)): ?>
                  <button class="btn-danger-ghost" onclick="cancelSched(<?= (int)$s['id'] ?>)"><i class="fas fa-ban"></i></button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade modal-ghost" id="addSchedModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width:980px;">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title"><i class="fas fa-calendar-plus me-2" style="color:var(--ghost-blue)"></i>Add Schedule</h5>
          <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:2px;">Uses the same scheduling modal flow as user schedules.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:24px;">
        <div class="row g-4 align-items-start">
          <div class="col-lg-7">
            <div class="row g-3 mb-3">
              <div class="col-12">
                <label class="form-label-ghost">Schedule Title</label>
                <input type="text" class="form-control-ghost" id="as_title" placeholder="Enter schedule title">
              </div>
              <div class="col-md-4">
                <label class="form-label-ghost">Incubator</label>
                <select class="form-select-ghost" id="as_incubator" onchange="syncScheduleIncubator()">
                  <?php foreach($incubators as $inc): ?>
                    <option value="<?= (int)$inc['id'] ?>" data-loc="<?= htmlspecialchars($inc['location'] ?? '') ?>" data-cap="<?= htmlspecialchars($inc['capacity'] ?? '') ?>"><?= htmlspecialchars($inc['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label-ghost">Batch</label>
                <select class="form-select-ghost" id="as_batch" onchange="syncScheduleIncubator()">
                  <option value="">General</option>
                  <?php foreach($batches as $batch): ?>
                    <option value="<?= (int)$batch['id'] ?>" data-inc="<?= (int)$batch['incubator_id'] ?>"><?= htmlspecialchars($batch['batch_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div id="as_inc_info_bar" style="background:rgba(245,166,35,.06);border:1px solid rgba(245,166,35,.2);border-radius:10px;padding:12px 16px;margin-bottom:20px;display:flex;gap:20px;flex-wrap:wrap;">
              <div>
                <div style="font-size:.7rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.06em;">Incubator</div>
                <div style="font-weight:700;color:white;font-size:.9rem;" id="as_info_incubator">—</div>
              </div>
              <div>
                <div style="font-size:.7rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.06em;">Capacity</div>
                <div style="font-weight:700;color:var(--ghost-amber);font-size:.9rem;" id="as_info_cap">—</div>
              </div>
              <div>
                <div style="font-size:.7rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.06em;">Location</div>
                <div style="font-weight:700;color:white;font-size:.9rem;" id="as_info_loc">—</div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label-ghost">Session Duration</label>
              <div class="row g-2">
                <div class="col-3"><label class="form-label-ghost">Day</label><input type="number" min="0" step="1" class="form-control-ghost" id="as_duration_days" value="21" oninput="updateSchedulePreview()"></div>
                <div class="col-3"><label class="form-label-ghost">HH</label><input type="number" min="0" max="23" step="1" class="form-control-ghost" id="as_duration_hours" value="0" oninput="updateSchedulePreview()"></div>
                <div class="col-3"><label class="form-label-ghost">MM</label><input type="number" min="0" max="59" step="1" class="form-control-ghost" id="as_duration_minutes" value="0" oninput="updateSchedulePreview()"></div>
                <div class="col-3"><label class="form-label-ghost">SS</label><input type="number" min="0" max="59" step="1" class="form-control-ghost" id="as_duration_seconds" value="0" oninput="updateSchedulePreview()"></div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label-ghost">Egg Count</label>
              <input type="number" min="1" max="100" step="1" class="form-control-ghost" id="as_egg_count" placeholder="How many eggs?">
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label-ghost">Target Temp</label>
                <input type="number" step="0.1" class="form-control-ghost" id="as_target_temp">
              </div>
              <div class="col-md-6">
                <label class="form-label-ghost">Target Humidity</label>
                <input type="number" step="0.1" class="form-control-ghost" id="as_target_hum">
              </div>
              <div class="col-md-6">
                <label class="form-label-ghost">Min Temp</label>
                <input type="number" step="0.1" class="form-control-ghost" id="as_min_temp">
              </div>
              <div class="col-md-6">
                <label class="form-label-ghost">Max Temp</label>
                <input type="number" step="0.1" class="form-control-ghost" id="as_max_temp">
              </div>
              <div class="col-md-6">
                <label class="form-label-ghost">Min Humidity</label>
                <input type="number" step="0.1" class="form-control-ghost" id="as_min_hum">
              </div>
              <div class="col-md-6">
                <label class="form-label-ghost">Max Humidity</label>
                <input type="number" step="0.1" class="form-control-ghost" id="as_max_hum">
              </div>
            </div>

            <div style="background:rgba(59,130,246,.05);border:1px solid rgba(59,130,246,.18);border-radius:12px;padding:18px;margin-top:12px;">
              <div style="font-size:.72rem;font-weight:700;color:#60a5fa;letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">Session Scheduling</div>
              <div class="row g-3">
                <div class="col-md-6"><label class="form-label-ghost">Start Date</label><input type="date" class="form-control-ghost" id="as_start_date" value="<?= date('Y-m-d') ?>" oninput="updateSchedulePreview()"></div>
                <div class="col-md-6"><label class="form-label-ghost">Start Time</label><input type="time" class="form-control-ghost" id="as_start_time" value="<?= date('H:i', strtotime('+1 minute')) ?>" oninput="updateSchedulePreview()"></div>
                <div class="col-md-6"><div style="background:rgba(245,166,35,.06);border:1px solid rgba(245,166,35,.16);border-radius:10px;padding:12px 14px;height:100%;"><div style="font-size:.72rem;font-weight:700;color:#f59e0b;letter-spacing:.12em;text-transform:uppercase;">Session Ends</div><div style="font-size:1.05rem;font-weight:700;color:white;margin-top:8px;" id="as_end_text">—</div><div style="font-size:.75rem;color:var(--ghost-muted);margin-top:4px;" id="as_end_subtext">Calculated from start and duration.</div></div></div>
                <div class="col-md-6"><div style="background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.16);border-radius:10px;padding:12px 14px;height:100%;"><div style="font-size:.72rem;font-weight:700;color:#22c55e;letter-spacing:.12em;text-transform:uppercase;">Active Session Timer</div><div style="font-size:1.05rem;font-weight:700;color:white;margin-top:8px;" id="as_timer_text">—</div></div></div>
                <div class="col-12"><div style="background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.18);border-radius:10px;padding:12px 14px;" id="as_conflict_box"><div style="font-size:.72rem;font-weight:700;color:#f87171;letter-spacing:.12em;text-transform:uppercase;">Conflict Status</div><div style="font-size:.9rem;font-weight:600;color:white;margin-top:6px;" id="as_conflict_text">No conflict detected.</div><div style="font-size:.75rem;color:var(--ghost-muted);margin-top:4px;" id="as_conflict_subtext">The selected time range is available.</div><div id="as_conflict_details" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.08);"><div class="row g-2"><div class="col-6"><div style="font-size:.68rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.08em;">Batch</div><div id="as_conflict_batch" style="font-size:.9rem;font-weight:700;color:white;">—</div></div><div class="col-6"><div style="font-size:.68rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.08em;">Egg Count</div><div id="as_conflict_eggs" style="font-size:.9rem;font-weight:700;color:white;">—</div></div><div class="col-6"><div style="font-size:.68rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.08em;">Duration</div><div id="as_conflict_duration" style="font-size:.9rem;font-weight:700;color:white;">—</div></div><div class="col-6"><div style="font-size:.68rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.08em;">Type</div><div id="as_conflict_type" style="font-size:.9rem;font-weight:700;color:white;">—</div></div><div class="col-12"><div style="font-size:.68rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.08em;">Start</div><div id="as_conflict_start" style="font-size:.9rem;font-weight:700;color:white;">—</div></div><div class="col-12"><div style="font-size:.68rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.08em;">End</div><div id="as_conflict_end" style="font-size:.9rem;font-weight:700;color:white;">—</div></div></div></div></div></div>
              </div>
            </div>
          </div>

          <div class="col-lg-5">
            <div style="background:rgba(34,197,94,.05);border:1px solid rgba(34,197,94,.15);border-radius:12px;padding:18px;margin-bottom:14px;">
              <div style="font-size:.72rem;font-weight:700;color:#22c55e;letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">Egg Swing Settings</div>
              <div class="row g-2">
                <div class="col-12">
                  <label class="form-label-ghost">Run for (seconds)</label>
                  <input type="number" min="5" max="300" step="1" class="form-control-ghost" id="as_sw_duration" value="30">
                </div>
                <div class="col-12 mt-2">
                  <label class="form-label-ghost">Turn eggs every (hours)</label>
                  <input type="number" min="0.01" max="24" step="0.01" class="form-control-ghost" id="as_sw_interval" value="8">
                </div>
              </div>
            </div>
            <div style="font-size:.78rem;color:var(--ghost-muted);padding:10px 14px;background:rgba(255,255,255,.02);border-radius:8px;border:1px solid var(--ghost-border);">
              <i class="fas fa-info-circle me-1" style="color:var(--ghost-amber);"></i>
              This modal intentionally matches the user scheduler modal field flow (`as_*`) so both sides behave consistently.
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button>
        <button id="as_save_btn" class="btn-ghost" onclick="addSched()"><i class="fas fa-save me-2"></i>Save</button>
      </div>
    </div>
  </div>
</div>

<script>
const incubatorSettingPresets = <?= json_encode($settingsByIncubator, JSON_UNESCAPED_SLASHES) ?>;
const scheduleConflictWindows = <?= json_encode($scheduleConflictWindows, JSON_UNESCAPED_SLASHES) ?>;
const scheduleConflictNoticeState = { as: null };

function filterSchedules(status, btn){
  document.querySelectorAll('[data-filter]').forEach(b => b.style.borderColor = '');
  if (btn) btn.style.borderColor = 'var(--ghost-amber)';
  $('#scheduleTable tbody tr').each(function(){
    $(this).toggle(status === 'all' || $(this).data('status') === status);
  });
}

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

  const selectedIncubator = incubatorSelect && incubatorSelect.options[incubatorSelect.selectedIndex] ? incubatorSelect.options[incubatorSelect.selectedIndex] : null;

  if (incubatorInfo) incubatorInfo.textContent = selectedIncubator ? selectedIncubator.textContent : '—';
  if (incubatorCap) incubatorCap.textContent = selectedIncubator && selectedIncubator.dataset && selectedIncubator.dataset.cap ? `${selectedIncubator.dataset.cap} eggs` : '—';
  if (incubatorLoc) incubatorLoc.textContent = selectedIncubator && selectedIncubator.dataset && selectedIncubator.dataset.loc ? selectedIncubator.dataset.loc : '—';

  applyIncubatorPresets(prefix, false);
  updateSchedulePreview(prefix);
}

function setScheduleSaveButtonState(prefix = 'as', enabled = true, titleText = '') {
  const btn = document.getElementById('as_save_btn');
  if (!btn) return;
  if (!btn.dataset.defaultHtml) btn.dataset.defaultHtml = btn.innerHTML;

  btn.disabled = !enabled;
  btn.title = enabled ? '' : (titleText || 'Schedule conflict detected');
  if (enabled) {
    btn.innerHTML = btn.dataset.defaultHtml;
  } else {
    btn.innerHTML = '<i class="fas fa-ban me-2"></i>Time Conflict';
  }
}

function formatPreviewDateTime(epochSeconds) {
  const dt = new Date(epochSeconds * 1000);
  if (Number.isNaN(dt.getTime())) return '—';
  return dt.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatPreviewDuration(startEpochSeconds, endEpochSeconds) {
  const totalSeconds = Math.max(0, endEpochSeconds - startEpochSeconds);
  const days = Math.floor(totalSeconds / 86400);
  const hours = Math.floor((totalSeconds % 86400) / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const parts = [];
  if (days > 0) parts.push(`${days}d`);
  if (hours > 0 || days > 0) parts.push(`${String(hours).padStart(2, '0')}h`);
  parts.push(`${String(minutes).padStart(2, '0')}m`);
  return parts.join(' ');
}

function updateConflictSummary(prefix, conflict) {
  const details = document.getElementById(`${prefix}_conflict_details`);
  const batch = document.getElementById(`${prefix}_conflict_batch`);
  const eggs = document.getElementById(`${prefix}_conflict_eggs`);
  const duration = document.getElementById(`${prefix}_conflict_duration`);
  const type = document.getElementById(`${prefix}_conflict_type`);
  const start = document.getElementById(`${prefix}_conflict_start`);
  const end = document.getElementById(`${prefix}_conflict_end`);
  if (!details || !batch || !eggs || !duration || !type || !start || !end) return;

  if (!conflict) {
    details.style.display = 'none';
    batch.textContent = '—';
    eggs.textContent = '—';
    duration.textContent = '—';
    type.textContent = '—';
    start.textContent = '—';
    end.textContent = '—';
    return;
  }

  const sourceLabel = conflict.source === 'batch' ? 'Batch' : conflict.source === 'schedule' ? 'Schedule' : 'Active session';
  details.style.display = 'block';
  batch.textContent = conflict.batch_name || conflict.title || '—';
  eggs.textContent = typeof conflict.egg_count === 'number' && conflict.egg_count >= 0 ? `${conflict.egg_count}` : '—';
  duration.textContent = conflict.duration_label || formatPreviewDuration(conflict.start, conflict.end);
  type.textContent = sourceLabel;
  start.textContent = formatPreviewDateTime(conflict.start);
  end.textContent = formatPreviewDateTime(conflict.end);
}

function detectScheduleConflict(newStartMs, newEndMs, incubatorId = '', excludeScheduleId = null, excludeBatchId = null) {
  if (!Array.isArray(scheduleConflictWindows)) return null;
  const newStart = Math.floor(newStartMs / 1000);
  const newEnd = Math.floor(newEndMs / 1000);
  const excludedId = excludeScheduleId !== null && excludeScheduleId !== undefined && String(excludeScheduleId).trim() !== '' ? String(excludeScheduleId) : null;
  const excludedBatchId = excludeBatchId !== null && excludeBatchId !== undefined && String(excludeBatchId).trim() !== '' ? String(excludeBatchId) : null;
  const selectedIncubatorId = incubatorId !== null && incubatorId !== undefined && String(incubatorId).trim() !== '' ? String(incubatorId) : null;

  return scheduleConflictWindows.find(window => {
    if (selectedIncubatorId !== null && String(window.incubator_id || '') !== selectedIncubatorId) return false;
    if (excludedId !== null && String(window.id) === excludedId) return false;
    if (excludedBatchId !== null && window.source === 'batch' && String(window.id) === excludedBatchId) return false;
    return newStart < window.end && newEnd > window.start;
  }) || null;
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

  endText.textContent = end.toLocaleString('en-US', { month:'long', day:'numeric', year:'numeric', hour:'2-digit', minute:'2-digit' });
  endSubtext.textContent = `Session starts ${start.toLocaleString('en-US', { month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' })}`;

  const remaining = Math.max(0, Math.floor((end.getTime() - Date.now()) / 1000));
  const remainingDays = Math.floor(remaining / 86400);
  const remainingHours = Math.floor((remaining % 86400) / 3600);
  const remainingMinutes = Math.floor((remaining % 3600) / 60);
  timerText.textContent = `${remainingDays} Days ${String(remainingHours).padStart(2, '0')} Hours ${String(remainingMinutes).padStart(2, '0')} Minutes`;

  const conflictBox = document.getElementById(`${prefix}_conflict_box`);
  const conflictText = document.getElementById(`${prefix}_conflict_text`);
  const conflictSubtext = document.getElementById(`${prefix}_conflict_subtext`);
  if (conflictBox && conflictText && conflictSubtext) {
    const conflict = detectScheduleConflict(start.getTime(), end.getTime(), incubatorId, null, batchId);
    if (conflict) {
      conflictBox.style.borderColor = 'rgba(239,68,68,.22)';
      const conflictPrefix = conflict.source === 'batch' ? 'Batch' : conflict.source === 'schedule' ? 'Schedule' : 'Active session';
      conflictText.textContent = `Conflicts with ${conflictPrefix}: ${conflict.batch_name || conflict.title}`;
      conflictSubtext.textContent = `Overlaps ${formatPreviewDateTime(conflict.start)} → ${formatPreviewDateTime(conflict.end)}`;
      updateConflictSummary(prefix, conflict);
      const conflictKey = `${conflict.source}:${conflict.id}:${conflict.start}:${conflict.end}`;
      if (scheduleConflictNoticeState[prefix] !== conflictKey) {
        scheduleConflictNoticeState[prefix] = conflictKey;
        showToast('Schedule conflict detected. Choose a different time range.', 'error');
      }
      setScheduleSaveButtonState(prefix, false, 'Resolve schedule conflict to continue');
    } else {
      conflictBox.style.borderColor = 'rgba(34,197,94,.18)';
      conflictText.textContent = 'No conflict detected.';
      conflictSubtext.textContent = 'The selected time range is available.';
      updateConflictSummary(prefix, null);
      scheduleConflictNoticeState[prefix] = null;
      setScheduleSaveButtonState(prefix, true);
    }
  }
}

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

  const eggCount = parseInt(($('#as_egg_count').val() || '').toString().trim(), 10);
  if (!Number.isInteger(eggCount) || eggCount < 1 || eggCount > 100) {
    showToast('Egg count must be between 1 and 100.', 'error');
    return;
  }

  const newStart = new Date(startDate + ' ' + (startTime || '00:00'));
  if (isNaN(newStart.getTime()) || newStart.getTime() <= Date.now()) {
    showToast('Please choose a future start date and time.', 'error');
    return;
  }

  const newEnd = new Date(newStart.getTime() + (((durationDays * 24 + durationHours) * 60 + durationMinutes) * 60 + durationSeconds) * 1000);
  const conflict = detectScheduleConflict(newStart.getTime(), newEnd.getTime(), $('#as_incubator').val() || '', null, $('#as_batch').val() || '');
  if (conflict) {
    setScheduleSaveButtonState('as', false, 'Resolve schedule conflict to continue');
    showToast('Schedule conflict detected. Choose a different time range.', 'error');
    return;
  }

  showLoader('Saving Schedule…');
  $.ajax({
    url: '../ajax/admin_schedules.php',
    method: 'POST',
    dataType: 'json',
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
      duration_seconds: durationSeconds,
      action_type: 'turning'
    },
    success: function(r){
      hideLoader();
      if (r.success) {
        showToast('Schedule added!');
        setTimeout(() => location.reload(), 700);
      } else {
        showToast(r.message || 'Could not add schedule', 'error');
      }
    },
    error: function(){
      hideLoader();
      showToast('Server error.', 'error');
    }
  });
}

function cancelSched(id) {
  showConfirm('Cancel schedule', 'Cancel this schedule? It will not be deleted.', function(){
    showLoader('Cancelling…');
    $.post('../ajax/admin_schedules.php', { action: 'delete', id: id }, function(r){
      hideLoader();
      if (r && r.success) {
        showToast('Cancelled');
        setTimeout(() => location.reload(), 600);
      } else {
        showToast((r && r.message) ? r.message : 'Could not cancel schedule', 'error');
      }
    }, 'json').fail(function(){
      hideLoader();
      showToast('Server error.', 'error');
    });
  });
}

function markDone(id) {
  showLoader('Marking Done…');
  $.post('../ajax/admin_schedules.php', { action: 'mark_done', id: id }, function(r){
    hideLoader();
    if (r && r.success) {
      showToast('Marked as done!');
      setTimeout(() => location.reload(), 600);
    } else {
      showToast((r && r.message) ? r.message : 'Could not update schedule', 'error');
    }
  }, 'json').fail(function(){
    hideLoader();
    showToast('Server error.', 'error');
  });
}

document.getElementById('addSchedModal').addEventListener('show.bs.modal', function() {
  scheduleConflictNoticeState.as = null;
  syncScheduleIncubator('as');
  applyIncubatorPresets('as', true);
  updateSchedulePreview('as');
});

(function(){
  const tIn = document.getElementById('as_target_temp');
  const hIn = document.getElementById('as_target_hum');
  if (tIn) {
    tIn.addEventListener('input', function(){
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
  }
  if (hIn) {
    hIn.addEventListener('input', function(){
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
  }
})();
</script>

<?php require_once 'footer.php'; ?>
