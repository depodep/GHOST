<?php
$pageTitle    = 'Dashboard';
$pageSubtitle = 'System Overview';
$activePage   = 'dashboard';
require_once 'header.php';
$pdo      = getDB();
$admin_id = $_SESSION['admin_id'] ?? 0;

// ── Stats ─────────────────────────────────────────────────
$totalIncubators  = $pdo->query("SELECT COUNT(*) FROM incubators")->fetchColumn();
$activeIncubators = $pdo->query("SELECT COUNT(*) FROM incubators WHERE status='active'")->fetchColumn();
$totalBatches     = $pdo->query("SELECT COUNT(*) FROM batches WHERE status='incubating'")->fetchColumn();
$totalUsers       = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalEggs        = $pdo->query("SELECT COALESCE(SUM(egg_count),0) FROM batches")->fetchColumn();
$incubatingEggs   = $pdo->query("SELECT COALESCE(SUM(egg_count),0) FROM batches WHERE status='incubating'")->fetchColumn();
$unreadAlerts     = $pdo->query("SELECT COUNT(*) FROM alerts WHERE is_read=0")->fetchColumn();

// ── Data ──────────────────────────────────────────────────
$recentBatches = $pdo->query(
    "SELECT b.*, i.name AS incubator_name, u.full_name AS user_name
     FROM batches b JOIN incubators i ON b.incubator_id=i.id JOIN users u ON b.user_id=u.id
     ORDER BY b.created_at DESC LIMIT 5")->fetchAll();

$todaySchedules = $pdo->query(
    "SELECT s.*, i.name AS incubator_name FROM schedules s
     JOIN incubators i ON s.incubator_id=i.id
     WHERE s.scheduled_date=CURDATE() ORDER BY s.scheduled_time ASC LIMIT 6")->fetchAll();

$recentAlerts = $pdo->query(
    "SELECT a.*, i.name AS incubator_name FROM alerts a
     LEFT JOIN incubators i ON a.incubator_id=i.id
     ORDER BY a.created_at DESC LIMIT 5")->fetchAll();

// Active incubators with full settings for modals
$incubators = $pdo->query(
    "SELECT i.*, ts.target_temp, ts.min_temp, ts.max_temp,
            ts.target_humidity, ts.min_humidity, ts.max_humidity, ts.turning_interval
     FROM incubators i
     LEFT JOIN temperature_settings ts ON i.id=ts.incubator_id
     WHERE i.status='active' ORDER BY i.name")->fetchAll();

$tempLogs = array_reverse($pdo->query(
    "SELECT temperature, humidity, DATE_FORMAT(recorded_at,'%H:%i') AS lbl
     FROM temperature_logs WHERE incubator_id=1
     ORDER BY recorded_at DESC LIMIT 12")->fetchAll());
?>

<!-- ── STAT CARDS ──────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon">🥚</div><div class="stat-label">Active Incubators</div>
      <div class="stat-val amber"><?= $activeIncubators ?></div>
      <div class="stat-badge up"><i class="fas fa-check-circle"></i> of <?= $totalIncubators ?> total</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon">📦</div><div class="stat-label">Active Batches</div>
      <div class="stat-val amber"><?= $totalBatches ?></div>
      <div class="stat-badge up">Incubating</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon">🫧</div><div class="stat-label">Total Eggs</div>
      <div class="stat-val amber"><?= number_format($totalEggs) ?></div>
      <div class="stat-badge up">Current eggs incubating: <?= number_format($incubatingEggs) ?></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon">👥</div><div class="stat-label">Farm Users</div>
      <div class="stat-val amber"><?= $totalUsers ?></div>
      <div class="stat-badge <?= $unreadAlerts>0?'down':'up' ?>">
        <i class="fas fa-bell"></i> <?= $unreadAlerts ?> alerts
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MONITORING
     ══════════════════════════════════════════════════════════ -->
<style>
  .monitor-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:24px 32px; padding:20px 0; }
  @media(max-width:1400px) { .monitor-grid { grid-template-columns:repeat(3,1fr); gap:20px 24px; } }
  @media(max-width:1000px) { .monitor-grid { grid-template-columns:repeat(2,1fr); gap:16px 20px; } }
  @media(max-width:768px) { .monitor-grid { grid-template-columns:1fr; gap:14px 0; } }
  .monitor-item { display:flex; flex-direction:column; }
  .monitor-label { font-size:.75rem; color:var(--ghost-muted); text-transform:uppercase; letter-spacing:.08em; margin-bottom:6px; font-weight:600; }
  .monitor-value { font-size:1.1rem; font-weight:700; color:white; display:flex; align-items:center; gap:6px; }
  .status-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 8px; border-radius:999px; font-size:.7rem; font-weight:700; letter-spacing:.03em; background:rgba(34,197,94,.12); color:#22c55e; border:1px solid rgba(34,197,94,.28); }
  .status-pill.offline { background:rgba(239,68,68,.12); color:#ef4444; border:1px solid rgba(239,68,68,.28); }
  .status-dot { width:6px; height:6px; border-radius:50%; display:inline-block; background:#64748b; }
  .status-dot.on { background:#22c55e; box-shadow:0 0 0 0 rgba(34,197,94,.45); animation:ghostPulse 1.8s infinite; }
  @keyframes ghostPulse { 0% { box-shadow:0 0 0 0 rgba(34,197,94,.45); } 70% { box-shadow:0 0 0 10px rgba(34,197,94,0); } 100% { box-shadow:0 0 0 0 rgba(34,197,94,0); } }
  .session-queue-banner { display:none; margin:0 0 18px 0; padding:18px 20px; border:1px solid rgba(245,166,35,.22); border-radius:18px; background:linear-gradient(135deg, rgba(245,166,35,.12), rgba(15,23,42,.92)); box-shadow:0 18px 40px rgba(0,0,0,.22); }
  .session-queue-banner.active { display:block; }
  .session-queue-top { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; }
  .session-queue-kicker { font-size:.7rem; font-weight:700; letter-spacing:.16em; text-transform:uppercase; color:#f5a623; margin-bottom:6px; }
  .session-queue-title { font-size:1.05rem; font-weight:800; color:#fff; line-height:1.2; }
  .session-queue-subtitle { margin-top:4px; font-size:.82rem; color:var(--ghost-muted); }
  .session-queue-status { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; font-size:.78rem; font-weight:700; letter-spacing:.03em; background:rgba(245,166,35,.12); color:#f5a623; border:1px solid rgba(245,166,35,.22); white-space:nowrap; }
  .session-queue-status.ready { background:rgba(34,197,94,.12); color:#22c55e; border-color:rgba(34,197,94,.22); }
  .session-queue-status.failed { background:rgba(239,68,68,.12); color:#ef4444; border-color:rgba(239,68,68,.22); }
  .session-queue-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-top:14px; }
  .session-queue-box { padding:12px 14px; border-radius:14px; background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.06); }
  .session-queue-label { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.12em; color:var(--ghost-muted); }
  .session-queue-value { margin-top:5px; color:#fff; font-size:.96rem; font-weight:700; line-height:1.35; }
  @media(max-width:768px) {
    .session-queue-top { flex-direction:column; }
    .session-queue-grid { grid-template-columns:1fr; }
  }
  .control-buttons { display:flex; gap:10px; margin-top:14px; }
  .control-buttons button { flex:1; padding:10px; border:1px solid var(--ghost-border); background:rgba(255,255,255,.03); color:white; border-radius:8px; font-size:.85rem; font-weight:600; cursor:pointer; transition:all .3s ease; }
  .control-buttons button:hover:not(:disabled) { background:var(--ghost-amber); border-color:var(--ghost-amber); color:white; transform:translateY(-2px); }
  .control-buttons button:disabled { opacity:.35; cursor:not-allowed; background:rgba(255,255,255,.01); border-color:rgba(255,255,255,.04); color:var(--ghost-muted); }
</style>
<div id="sessionQueueBanner" class="session-queue-banner">
  <div class="session-queue-top">
    <div>
      <div class="session-queue-kicker">Session queued</div>
      <div class="session-queue-title" id="sessionQueueTitle">About to start</div>
      <div class="session-queue-subtitle" id="sessionQueueSubtitle">Waiting for device heartbeat</div>
    </div>
    <div id="sessionQueueState" class="session-queue-status">
      <span class="status-dot" style="background:#f5a623;"></span>
      <span id="sessionQueueStateText">Queued</span>
    </div>
  </div>
  <div class="session-queue-grid">
    <div class="session-queue-box">
      <div class="session-queue-label">Batch</div>
      <div class="session-queue-value" id="sessionQueueBatch">—</div>
    </div>
    <div class="session-queue-box">
      <div class="session-queue-label">Start / End</div>
      <div class="session-queue-value" id="sessionQueueWindow">—</div>
    </div>
    <div class="session-queue-box">
      <div class="session-queue-label">Countdown</div>
      <div class="session-queue-value" id="sessionQueueCountdown">—</div>
    </div>
  </div>
</div>
<div class="ghost-panel mb-4">
  <div class="ghost-panel-header d-flex justify-content-between align-items-center">
    <span class="ghost-panel-title">🔧 Monitoring</span>
    <div style="display:flex;align-items:center;gap:8px;">
      <span id="hwOnlineDot" style="width:9px;height:9px;border-radius:50%;background:var(--ghost-muted);display:inline-block;transition:background .4s;"></span>
      <span id="hwOnlineLabel" style="font-size:0.75rem;color:var(--ghost-muted);">Offline</span>
    </div>
  </div>
  <div class="ghost-panel-body">
    <div class="row g-3 align-items-start">
      <div class="col-md-3">
        <label class="form-label-ghost">Select Incubator</label>
        <select class="form-select-ghost" id="qc_incubator_id" onchange="onIncubatorChange()">
          <?php foreach($incubators as $inc): ?>
            <option value="<?= $inc['id'] ?>"
              data-target="<?= $inc['target_temp']    ?? 37.5 ?>"
              data-min="<?=    $inc['min_temp']        ?? 37.0 ?>"
              data-max="<?=    $inc['max_temp']        ?? 38.0 ?>"
              data-thmin="<?=  $inc['min_humidity']    ?? 50.0 ?>"
              data-thmax="<?=  $inc['max_humidity']    ?? 60.0 ?>"
              data-th="<?=     $inc['target_humidity'] ?? 55.0 ?>"
              data-cap="<?=    $inc['capacity']        ?? 0    ?>"
              data-loc="<?=    htmlspecialchars($inc['location'] ?? '') ?>"
              data-interval="<?= $inc['turning_interval'] ?? 8 ?>">
              <?= htmlspecialchars($inc['name']) ?>
            </option>
          <?php endforeach; ?>
          <?php if(empty($incubators)): ?><option value="">No active incubators</option><?php endif; ?>
        </select>

        <div style="margin-top:14px;background:rgba(255,255,255,.03);border:1px solid var(--ghost-border);border-radius:12px;padding:12px 14px;">
          <div style="font-size:.72rem;color:var(--ghost-muted);letter-spacing:.05em;text-transform:uppercase;margin-bottom:8px;">Device Mode</div>
          <div id="deviceStatusBadge" class="status-pill offline"><span id="deviceStatusDot" class="status-dot off"></span><span id="deviceStatusText">IDLE</span></div>
          <div style="font-size:.72rem;color:var(--ghost-muted);margin-top:10px;line-height:1.5;">Monitoring is view only. Hardware changes are driven automatically by the controller and downloaded parameters.</div>
        </div>

        <div style="font-size:.72rem;color:var(--ghost-muted);margin-top:10px;line-height:1.5;">Admin monitoring is read-only. Session control and parameter changes are handled through the user flow.</div>
      </div>

      <div class="col-md-9">
        <div class="monitor-grid">
          <div class="monitor-item"><div class="monitor-label">Live Temperature</div><div class="monitor-value" id="monitorTemp">—</div></div>
          <div class="monitor-item"><div class="monitor-label">Live Humidity</div><div class="monitor-value" id="monitorHumidity">—</div></div>
          <div class="monitor-item"><div class="monitor-label">WiFi Connection</div><div class="monitor-value"><span id="monitorWifi" class="status-pill offline">Disconnected</span></div></div>
          <div class="monitor-item"><div class="monitor-label">Heater 1 Status</div><div class="monitor-value"><span id="heater1Dot" class="status-dot"></span><span id="monitorHeater1">—</span></div></div>
          <div class="monitor-item"><div class="monitor-label">Heater 2 Status</div><div class="monitor-value"><span id="heater2Dot" class="status-dot"></span><span id="monitorHeater2">—</span></div></div>
          <div class="monitor-item"><div class="monitor-label">Heater Fan Status</div><div class="monitor-value"><span id="heaterFanDot" class="status-dot"></span><span id="monitorHeaterFan">—</span></div></div>
          <div class="monitor-item"><div class="monitor-label">Exhaust Fan Status</div><div class="monitor-value"><span id="exhaustDot" class="status-dot"></span><span id="monitorExhaust">—</span></div></div>
          <div class="monitor-item"><div class="monitor-label">Swing Motor Status</div><div class="monitor-value"><span id="swingDot" class="status-dot"></span><span id="monitorSwing">—</span></div></div>
          <div class="monitor-item"><div class="monitor-label">Current Incubation Day</div><div class="monitor-value" id="monitorIncubationDay">—</div></div>
          <div class="monitor-item"><div class="monitor-label">Active Session Name</div><div class="monitor-value" id="monitorSessionName">—</div></div>
          <div class="monitor-item"><div class="monitor-label">Current Running Mode</div><div class="monitor-value" id="monitorMode">—</div></div>
          <div class="monitor-item"><div class="monitor-label">Last Server Update</div><div class="monitor-value" id="monitorLastSync">—</div></div>
          <div class="monitor-item"><div class="monitor-label">Device Mode</div><div class="monitor-value" id="monitorSessionStatus">—</div></div>
          <div class="monitor-item"><div class="monitor-label">Session</div><div class="monitor-value" id="monitorParamsId">—</div></div>
          <div class="monitor-item"><div class="monitor-label">Session Ends At</div><div class="monitor-value" id="monitorSessionEnds" style="font-size:.9rem;">—</div></div>
        </div>
      </div>
    </div>
    <div id="hwFeedback" style="display:none;margin-top:12px;padding:10px 16px;border-radius:8px;font-size:.83rem;"></div>
  </div>
</div>

<!-- ── CHART + SCHEDULE ─────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="ghost-panel">
      <div class="ghost-panel-header d-flex justify-content-between align-items-center">
        <span class="ghost-panel-title">🌡️ Temperature &amp; Humidity — Alpha Unit</span>
        <span style="font-size:.75rem;color:var(--ghost-muted);">Last 12 readings</span>
      </div>
      <div class="ghost-panel-body"><canvas id="tempChart" height="130"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="ghost-panel h-100">
      <div class="ghost-panel-header"><span class="ghost-panel-title">📅 Today's Schedule</span></div>
      <div class="ghost-panel-body p-0">
        <?php if(empty($todaySchedules)): ?>
          <div class="p-4 text-center" style="color:var(--ghost-muted);font-size:.88rem;">No schedules for today</div>
        <?php else: foreach($todaySchedules as $s): ?>
          <div style="padding:12px 18px;border-bottom:1px solid rgba(255,255,255,.04);display:flex;align-items:center;gap:12px;">
            <div style="font-size:1.2rem;"><?= actionIcon($s['action_type']) ?></div>
            <div style="flex:1;min-width:0;">
              <div style="font-size:.85rem;font-weight:600;color:white;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($s['title']) ?></div>
              <div style="font-size:.75rem;color:var(--ghost-muted);"><?= $s['incubator_name'] ?> · <?= substr($s['scheduled_time'],0,5) ?></div>
            </div>
            <span class="badge-<?= $s['status'] ?>"><?= $s['status'] ?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ── BATCHES + ALERTS ─────────────────────────────────────── -->
<div class="row g-3">
  <div class="col-lg-7">
    <div class="ghost-panel">
      <div class="ghost-panel-header d-flex justify-content-between align-items-center">
        <span class="ghost-panel-title">📦 Recent Batches</span>
        <a href="incubators.php" style="font-size:.8rem;color:var(--ghost-amber);text-decoration:none;">View incubators →</a>
      </div>
      <div class="ghost-panel-body p-0">
        <table class="ghost-table">
          <thead><tr><th>Batch</th><th>Incubator</th><th>Eggs</th><th>Started</th><th>Hatch Day</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach($recentBatches as $b): ?>
            <tr>
              <td>
                <div style="font-weight:600;color:white;"><?= htmlspecialchars($b['batch_name']) ?></div>
                <div style="font-size:.75rem;color:var(--ghost-muted);"><?= $b['egg_type'] ?> · <?= $b['user_name'] ?></div>
              </td>
              <td style="color:var(--ghost-muted);font-size:.85rem;"><?= htmlspecialchars($b['incubator_name']) ?></td>
              <td style="font-weight:600;"><?= $b['egg_count'] ?></td>
              <td style="font-size:.82rem;color:var(--ghost-muted);"><?= date('M j g:i a', strtotime($b['created_at'])) ?></td>
              <td style="font-size:.82rem;color:var(--ghost-muted);"><?= date('M j', strtotime($b['expected_hatch_date'])) ?></td>
              <td><span class="badge-<?= $b['status'] ?>"><?= $b['status'] ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="ghost-panel">
      <div class="ghost-panel-header d-flex justify-content-between align-items-center">
        <span class="ghost-panel-title">🔔 Recent Alerts</span>
        <a href="alerts.php" style="font-size:.8rem;color:var(--ghost-amber);text-decoration:none;">All →</a>
      </div>
      <div class="ghost-panel-body p-0">
        <?php foreach($recentAlerts as $a): ?>
          <div style="padding:12px 18px;border-bottom:1px solid rgba(255,255,255,.04);display:flex;align-items:flex-start;gap:12px;">
            <div style="font-size:1.1rem;margin-top:1px;"><?= severityIcon($a['severity']) ?></div>
            <div style="flex:1;">
              <div style="font-size:.83rem;color:white;line-height:1.5;"><?= htmlspecialchars($a['message']) ?></div>
              <div style="font-size:.72rem;color:var(--ghost-muted);margin-top:2px;"><?= date('M j, g:i a',strtotime($a['created_at'])) ?></div>
            </div>
            <?php if(!$a['is_read']): ?>
              <div style="width:7px;height:7px;background:var(--ghost-amber);border-radius:50%;margin-top:6px;flex-shrink:0;"></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
    MODAL: SET PARAMETERS
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade modal-ghost" id="incubateModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:540px;">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title">⚙️ Set Parameters</h5>
          <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:2px;">Review the incubation values before starting the session</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:24px;">

        <!-- Incubator info bar -->
        <div id="inc_info_bar" style="background:rgba(245,166,35,.06);border:1px solid rgba(245,166,35,.2);border-radius:10px;padding:12px 16px;margin-bottom:20px;display:flex;gap:20px;flex-wrap:wrap;">
          <div><div style="font-size:.7rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.06em;">Incubator</div><div style="font-weight:700;color:white;font-size:.9rem;" id="inc_info_name">—</div></div>
          <div><div style="font-size:.7rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.06em;">Capacity</div><div style="font-weight:700;color:var(--ghost-amber);font-size:.9rem;" id="inc_info_cap">—</div></div>
          <div><div style="font-size:.7rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.06em;">Location</div><div style="font-weight:700;color:white;font-size:.9rem;" id="inc_info_loc">—</div></div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:20px;">
          <div style="padding:12px 14px;border-radius:12px;background:rgba(255,255,255,.03);border:1px solid var(--ghost-border);">
            <div style="font-size:.68rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--ghost-muted);">Total Eggs</div>
            <div style="margin-top:6px;font-size:1.15rem;font-weight:800;color:white;"><?= number_format($totalEggs) ?></div>
          </div>
          <div style="padding:12px 14px;border-radius:12px;background:rgba(255,255,255,.03);border:1px solid var(--ghost-border);">
            <div style="font-size:.68rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--ghost-muted);">Current Eggs Incubating</div>
            <div style="margin-top:6px;font-size:1.15rem;font-weight:800;color:#22c55e;"><?= number_format($incubatingEggs) ?></div>
          </div>
        </div>


        <div class="mb-3">
          <label class="form-label-ghost">🥚 Egg Count</label>
          <input type="number" min="1" step="1" class="form-control-ghost" id="inc_egg_count" placeholder="How many eggs were placed?">
          <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">This number is saved with the session and shown in the batch list.</div>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <!-- Temperature settings -->
            <div style="background:rgba(245,166,35,.05);border:1px solid rgba(245,166,35,.15);border-radius:12px;padding:18px;margin-bottom:0;">
              <div style="font-size:.72rem;font-weight:700;color:var(--ghost-amber);letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">🌡️ Temperature (°C)</div>
              <div class="row g-2">
                <div class="col-12">
                  <label class="form-label-ghost">Target °C</label>
                  <input type="number" step="0.1" class="form-control-ghost" id="inc_target_temp"
                    style="font-size:1.4rem;font-family:'Bebas Neue',sans-serif;color:var(--ghost-amber);text-align:center;">
                </div>
                <div class="col-6">
                  <label class="form-label-ghost">Min °C</label>
                  <input type="number" step="0.1" class="form-control-ghost" id="inc_min_temp">
                </div>
                <div class="col-6">
                  <label class="form-label-ghost">Max °C</label>
                  <input type="number" step="0.1" class="form-control-ghost" id="inc_max_temp">
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <!-- Humidity settings -->
            <div style="background:rgba(59,130,246,.05);border:1px solid rgba(59,130,246,.15);border-radius:12px;padding:18px;margin-bottom:0;">
              <div style="font-size:.72rem;font-weight:700;color:#3b82f6;letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">💧 Humidity (%)</div>
              <div class="row g-2">
                <div class="col-12">
                  <label class="form-label-ghost">Target %</label>
                  <input type="number" step="0.1" class="form-control-ghost" id="inc_target_hum"
                    style="font-size:1.4rem;font-family:'Bebas Neue',sans-serif;color:#3b82f6;text-align:center;">
                </div>
                <div class="col-6">
                  <label class="form-label-ghost">Min %</label>
                  <input type="number" step="0.1" class="form-control-ghost" id="inc_min_hum">
                </div>
                <div class="col-6">
                  <label class="form-label-ghost">Max %</label>
                  <input type="number" step="0.1" class="form-control-ghost" id="inc_max_hum">
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Note -->
        <div style="font-size:.78rem;color:var(--ghost-muted);padding:10px 14px;background:rgba(255,255,255,.02);border-radius:8px;border:1px solid var(--ghost-border);">
          <i class="fas fa-info-circle me-1" style="color:var(--ghost-amber);"></i>
          Set Parameters saves the values to the database and pushes them to the ESP8266. Session starts are handled from the user scheduler flow.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-ghost" onclick="saveIncubationSettings(false)">
          <i class="fas fa-save me-1"></i> Set Parameters
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: START NOW (Egg Swing)
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade modal-ghost" id="swingModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title">🔄 Start Egg Swing</h5>
          <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:2px;">Configure swing cycle before activating motors</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:24px;">

        <!-- Swing info bar -->
        <div style="background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.2);border-radius:10px;padding:12px 16px;margin-bottom:20px;">
          <div style="font-size:.75rem;color:var(--ghost-muted);margin-bottom:4px;"><i class="fas fa-circle-info me-1"></i> Hardware: 3× swing motors wired in parallel on Relay 2</div>
          <div style="font-size:.75rem;color:#22c55e;font-weight:600;">All 3 motors activate simultaneously when relay triggers.</div>
        </div>

        <!-- Swing duration -->
        <div style="background:rgba(34,197,94,.05);border:1px solid rgba(34,197,94,.15);border-radius:12px;padding:18px;margin-bottom:14px;">
          <div style="font-size:.72rem;font-weight:700;color:#22c55e;letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">⏱️ Swing Duration</div>
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label-ghost">Run for (seconds)</label>
              <input type="number" min="5" max="300" step="1" class="form-control-ghost" id="sw_duration" value="30"
                style="font-size:1.4rem;font-family:'Bebas Neue',sans-serif;color:#22c55e;text-align:center;">
              <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">Recommended: 30–60 seconds per cycle. Max 300 s.</div>
            </div>
          </div>
        </div>

        <!-- Turning interval -->
        <div style="background:rgba(255,255,255,.03);border:1px solid var(--ghost-border);border-radius:12px;padding:18px;margin-bottom:14px;">
          <div style="font-size:.72rem;font-weight:700;color:var(--ghost-muted);letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">🔁 Auto-Turn Interval</div>
          <label class="form-label-ghost">Turn eggs every (hours)</label>
          <input type="number" min="0.01" max="24" step="0.01" class="form-control-ghost" id="sw_interval" value="8"
            style="font-size:1.4rem;font-family:'Bebas Neue',sans-serif;color:white;text-align:center;">
          <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">Recommended: 3–8 hours. Common default: 4 hours. Fractional values are allowed.</div>
          <div id="sw_interval_preview" style="font-size:.75rem;color:var(--ghost-muted);margin-top:4px;"></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
            <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;" onclick="setTurningIntervalPreset(0.05)">0.05 h</button>
            <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;" onclick="setTurningIntervalPreset(0.15)">0.15 h</button>
            <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;" onclick="setTurningIntervalPreset(3)">3 h</button>
            <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;" onclick="setTurningIntervalPreset(4)">4 h</button>
            <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;" onclick="setTurningIntervalPreset(6)">6 h</button>
            <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;" onclick="setTurningIntervalPreset(8)">8 h</button>
          </div>
          <div style="font-size:.72rem;color:var(--ghost-muted);margin-top:8px;">Egg turning pauses automatically during the final 3 days before hatch.</div>
        </div>

        <!-- Note -->
        <div style="font-size:.78rem;color:var(--ghost-muted);padding:10px 14px;background:rgba(255,255,255,.02);border-radius:8px;border:1px solid var(--ghost-border);">
          <i class="fas fa-info-circle me-1" style="color:#22c55e;"></i>
          This manual trigger starts the swing motors now. The ESP8266 will auto-stop after the set duration. Scheduled turning continues independently.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-ghost" style="background:linear-gradient(135deg,#22c55e,#16a34a);box-shadow:0 0 16px rgba(34,197,94,.3);" onclick="confirmSwing()">
          <i class="fas fa-rotate me-1"></i> Start Swing Now
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── CHART ────────────────────────────────────────────────── -->
<script>
// Store chart data globally for updates
let tempChartCanvas = null;
let tempChartData = {
  labels: [],
  temperatures: [],
  humidity: []
};
let liveSessionState = {
  startedAt: null,
  endsAt: null,
  status: 'idle',
  currentMode: 'idle',
  activeBatchId: null,
  activeSessionName: null,
  incubationDay: null,
  runningOps: 'idle',
  wifiStatus: 'disconnected',
  lastEggTurnAt: null,
  lastServerSync: null,
  nextStartAt: null,
  nextStartEpoch: null,
  nextEndsAt: null,
  nextSessionName: null,
  nextSessionStatus: 'idle',
  scheduledBatchCount: 0
};

function computeSeriesBounds(values, preferredMin, preferredMax) {
  const nums = (values || []).map(v => Number(v)).filter(v => Number.isFinite(v));
  if (!nums.length) {
    return { min: preferredMin, max: preferredMax };
  }

  let min = Math.min(...nums);
  let max = Math.max(...nums);
  if (min === max) {
    min -= 0.5;
    max += 0.5;
  } else {
    const range = max - min;
    const pad = Math.max(range * 0.08, 0.4);
    min -= pad;
    max += pad;
  }

  if (Number.isFinite(preferredMin)) min = Math.min(min, preferredMin);
  if (Number.isFinite(preferredMax)) max = Math.max(max, preferredMax);
  return { min, max };
}

function drawTrendChart(labels, temperatures, humidity) {
  if (!tempChartCanvas) tempChartCanvas = document.getElementById('tempChart');
  const canvas = tempChartCanvas;
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  if (!ctx) return;

  const parent = canvas.parentElement;
  const cssWidth = Math.max(320, Math.floor((parent && parent.clientWidth) || canvas.clientWidth || 0));
  const cssHeight = Math.max(220, Math.floor((parent && parent.clientHeight) || canvas.clientHeight || 260));
  const pixelRatio = window.devicePixelRatio || 1;

  canvas.width = Math.floor(cssWidth * pixelRatio);
  canvas.height = Math.floor(cssHeight * pixelRatio);
  canvas.style.width = cssWidth + 'px';
  canvas.style.height = cssHeight + 'px';
  canvas.style.display = 'block';
  canvas.style.maxWidth = '100%';
  ctx.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
  ctx.clearRect(0, 0, cssWidth, cssHeight);

  ctx.fillStyle = 'rgba(255,255,255,0.02)';
  ctx.fillRect(0, 0, cssWidth, cssHeight);

  if (!labels.length) {
    ctx.fillStyle = '#64748b';
    ctx.font = '600 13px "Space Grotesk", sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('No temperature data yet', cssWidth / 2, cssHeight / 2);
    return;
  }

  const padding = { top: 22, right: 76, bottom: 54, left: 60 };
  const plotWidth = cssWidth - padding.left - padding.right;
  const plotHeight = cssHeight - padding.top - padding.bottom;
  if (plotWidth <= 0 || plotHeight <= 0) return;

  const tempBounds = computeSeriesBounds(temperatures, 36, 39);
  const humBounds = computeSeriesBounds(humidity, 45, 70);
  const tickCount = labels.length > 1 ? Math.min(5, labels.length) : 2;

  const toPoint = function(index, value, bounds) {
    const numericValue = Number(value);
    if (!Number.isFinite(numericValue)) return null;
    const x = padding.left + (labels.length === 1 ? plotWidth / 2 : (index / (labels.length - 1)) * plotWidth);
    const ratio = (numericValue - bounds.min) / ((bounds.max - bounds.min) || 1);
    const y = padding.top + plotHeight - (ratio * plotHeight);
    return { x, y };
  };

  const drawSeries = function(points, strokeColor, fillColor) {
    const finitePoints = points.filter(Boolean);
    if (!finitePoints.length) return;

    ctx.beginPath();
    let started = false;
    points.forEach(function(point) {
      if (!point) { started = false; return; }
      if (!started) { ctx.moveTo(point.x, point.y); started = true; }
      else { ctx.lineTo(point.x, point.y); }
    });

    ctx.strokeStyle = strokeColor;
    ctx.lineWidth = 3;
    ctx.stroke();

    if (fillColor) {
      ctx.lineTo(finitePoints[finitePoints.length - 1].x, padding.top + plotHeight);
      ctx.lineTo(finitePoints[0].x, padding.top + plotHeight);
      ctx.closePath();
      ctx.fillStyle = fillColor;
      ctx.fill();
    }

    finitePoints.forEach(function(point) {
      ctx.beginPath();
      ctx.arc(point.x, point.y, 4, 0, Math.PI * 2);
      ctx.fillStyle = strokeColor;
      ctx.fill();
    });
  };

  ctx.font = '11px "Space Grotesk", sans-serif';
  ctx.textBaseline = 'middle';
  ctx.strokeStyle = 'rgba(255,255,255,0.08)';
  ctx.lineWidth = 1;

  for (let i = 0; i < tickCount; i++) {
    const ratio = tickCount === 1 ? 0 : i / (tickCount - 1);
    const y = padding.top + plotHeight - (ratio * plotHeight);
    const tempValue = tempBounds.min + ((tempBounds.max - tempBounds.min) * ratio);
    const humValue = humBounds.min + ((humBounds.max - humBounds.min) * ratio);

    ctx.beginPath();
    ctx.moveTo(padding.left, y);
    ctx.lineTo(cssWidth - padding.right, y);
    ctx.stroke();

    ctx.fillStyle = '#f5a623';
    ctx.textAlign = 'right';
    ctx.fillText(tempValue.toFixed(1) + '°C', padding.left - 10, y);

    ctx.fillStyle = '#3b82f6';
    ctx.textAlign = 'left';
    ctx.fillText(humValue.toFixed(0) + '%', cssWidth - padding.right + 10, y);
  }

  ctx.beginPath();
  ctx.moveTo(padding.left, padding.top + plotHeight);
  ctx.lineTo(cssWidth - padding.right, padding.top + plotHeight);
  ctx.strokeStyle = 'rgba(255,255,255,0.12)';
  ctx.stroke();

  const tempPoints = temperatures.map((v, idx) => toPoint(idx, v, tempBounds));
  const humPoints = humidity.map((v, idx) => toPoint(idx, v, humBounds));
  drawSeries(tempPoints, '#f5a623', 'rgba(245,166,35,0.08)');
  drawSeries(humPoints, '#3b82f6', null);

  const tickStep = labels.length > 8 ? Math.ceil(labels.length / 6) : 1;
  ctx.fillStyle = '#94a3b8';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'top';

  for (let i = 0; i < labels.length; i += tickStep) {
    const x = padding.left + (labels.length === 1 ? plotWidth / 2 : (i / (labels.length - 1)) * plotWidth);
    ctx.fillText(labels[i], x, padding.top + plotHeight + 12);
  }
}

function renderTemperatureTrendChart() {
  drawTrendChart(tempChartData.labels, tempChartData.temperatures, tempChartData.humidity);
}

window.addEventListener('load', function() {
  tempChartData.labels = <?= json_encode(array_column($tempLogs,'lbl')) ?>;
  tempChartData.temperatures = (<?= json_encode(array_column($tempLogs,'temperature')) ?> || []).map(v => {
    const n = Number(v);
    return Number.isFinite(n) ? n : null;
  });
  tempChartData.humidity = (<?= json_encode(array_column($tempLogs,'humidity')) ?> || []).map(v => {
    const n = Number(v);
    return Number.isFinite(n) ? n : null;
  });
  tempChartCanvas = document.getElementById('tempChart');
  renderTemperatureTrendChart();
  window.addEventListener('resize', renderTemperatureTrendChart);
  refreshTemperatureChart();

  // Start auto-refresh of temperature chart every 30 seconds
  setInterval(refreshTemperatureChart, 30000);

  const intervalInput = document.getElementById('sw_interval');
  if (intervalInput) {
    intervalInput.addEventListener('input', function() {
      updateIntervalPreview('sw_interval', 'sw_interval_preview');
    });
    updateIntervalPreview('sw_interval', 'sw_interval_preview');
  }
});

// Function to refresh temperature chart with latest data
function refreshTemperatureChart() {
  const incId = document.getElementById('qc_incubator_id').value;
  if (!incId || !tempChartCanvas) return;
  
  $.get('../ajax/admin_temperature_logs.php', {
    incubator_id: incId,
    limit: 10
  }, function(data) {
    const logs = Array.isArray(data) ? data : [];
    tempChartData.labels = logs.map(d => d.lbl);
    tempChartData.temperatures = logs.map(d => {
      const n = Number(d.temperature);
      return Number.isFinite(n) ? n : null;
    });
    tempChartData.humidity = logs.map(d => {
      const n = Number(d.humidity);
      return Number.isFinite(n) ? n : null;
    });
    renderTemperatureTrendChart();
  }, 'json');
}

// ══════════════════════════════════════════════════════════
//  HELPER: get selected incubator <option> element
// ══════════════════════════════════════════════════════════
function selectedOpt() {
  const sel = document.getElementById('qc_incubator_id');
  if (!sel || sel.selectedIndex < 0) return null;
  return sel.options[sel.selectedIndex] || null;
}

function syncSelectedOptSettings(settings) {
  const opt = selectedOpt();
  if (!opt || !settings) return;

  const pairs = {
    target: settings.target_temp ?? settings.targetT,
    min: settings.min_temp ?? settings.minT,
    max: settings.max_temp ?? settings.maxT,
    th: settings.target_humidity ?? settings.targetH,
    thmin: settings.min_humidity ?? settings.minH,
    thmax: settings.max_humidity ?? settings.maxH,
    interval: settings.turning_interval ?? settings.interval,
    swingDuration: settings.swing_duration_sec ?? settings.duration
  };

  Object.entries(pairs).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '') {
      opt.dataset[key] = value;
    }
  });
}

function onIncubatorChange() {
  fetchLiveStatus();
  refreshTemperatureChart();
}

function getIncubationFormValues() {
  return {
    incId: document.getElementById('qc_incubator_id').value,
    eggCount: parseInt(document.getElementById('inc_egg_count').value, 10) || 0,
    targetTemp: parseFloat(document.getElementById('inc_target_temp').value),
    minTemp: parseFloat(document.getElementById('inc_min_temp').value),
    maxTemp: parseFloat(document.getElementById('inc_max_temp').value),
    targetHum: parseFloat(document.getElementById('inc_target_hum').value),
    minHum: parseFloat(document.getElementById('inc_min_hum').value),
    maxHum: parseFloat(document.getElementById('inc_max_hum').value),
    duration: parseInt(document.getElementById('sw_duration').value, 10) || 30,
    interval: parseFloat(document.getElementById('sw_interval').value) || 8
  };
}

function setTurningIntervalPreset(hours) {
  const input = document.getElementById('sw_interval');
  if (!input) return;
  input.value = hours;
  updateIntervalPreview('sw_interval', 'sw_interval_preview');
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

function sanitizeFloatValue(value, fallback, minValue, maxValue) {
  const parsed = parseFloat(value);
  if (!Number.isFinite(parsed) || parsed < minValue || parsed > maxValue) {
    return fallback;
  }
  return parsed;
}

function readFloatInput(id, fallback) {
  const el = document.getElementById(id);
  if (!el) return fallback;
  const parsed = parseFloat(el.value);
  return Number.isFinite(parsed) ? parsed : fallback;
}

function readIntInput(id, fallback, minValue, maxValue) {
  const el = document.getElementById(id);
  if (!el) return fallback;
  const parsed = parseWholeNumberInput(el.value);
  if (!Number.isFinite(parsed)) return fallback;
  if (parsed < minValue || parsed > maxValue) return fallback;
  return parsed;
}

function parseWholeNumberInput(value) {
  const raw = String(value ?? '').trim();
  if (raw === '') return NaN;

  const direct = Number(raw);
  if (Number.isFinite(direct) && Number.isInteger(direct)) {
    return direct;
  }

  if (/^0\.\d+$/.test(raw)) {
    const digits = raw.slice(2).replace(/[^0-9]/g, '');
    if (digits) {
      return parseInt(digits, 10);
    }
  }

  const integerPart = parseInt(raw, 10);
  return Number.isFinite(integerPart) ? integerPart : NaN;
}

function saveIncubationSettings(startSession) {
  const values = getIncubationFormValues();
  if (!values.incId) return;
  if (isNaN(values.eggCount) || values.eggCount < 1) { alert('Please enter how many eggs were placed.'); return; }
  if (isNaN(values.targetTemp) || isNaN(values.minTemp) || isNaN(values.maxTemp)) { alert('Please fill in all temperature fields.'); return; }
  if (values.minTemp >= values.maxTemp) { alert('Min temp must be less than Max temp.'); return; }
  if (isNaN(values.duration) || values.duration < 5 || values.duration > 300) { alert('Swing duration must be between 5 and 300 seconds.'); return; }
  if (!Number.isFinite(values.interval) || values.interval < 0.01) { alert('Turning interval too short.'); return; }
  if (values.interval > 24) { alert('Turning interval must be 24 hours or less.'); return; }

  showFb('Saving parameters…', 'info');
  $.post('../ajax/admin_temperature.php', {
    action: 'update_settings',
    incubator_id: values.incId,
    target_temp: values.targetTemp,
    min_temp: values.minTemp,
    max_temp: values.maxTemp,
    target_humidity: values.targetHum,
    min_humidity: values.minHum,
    max_humidity: values.maxHum,
    turning_interval: values.interval
  }, function(r) {
    if (!r.success) {
      showFb('❌ Failed to save settings: ' + (r.message || ''), 'danger');
      return;
    }

    syncSelectedOptSettings(values);

    $.post('../ajax/hardware_api.php', {
      action: 'set_swing_duration',
      incubator_id: values.incId,
      duration_sec: values.duration,
      token: 'ghost_hw_secret_2024'
    }, function() {
      showFb('✅ Parameters saved.', 'success');
      fetchLiveStatus();
    }, 'json');
  }, 'json');
}

// ══════════════════════════════════════════════════════════
//  INCUBATE NOW MODAL
// ══════════════════════════════════════════════════════════
function openIncubateModal() {
  const opt = selectedOpt();
  if (!opt || !opt.value) { showFb('No active incubator selected.','warning'); return; }

  // Populate info bar
  document.getElementById('inc_info_name').textContent = opt.text;
  document.getElementById('inc_info_cap').textContent  = opt.dataset.cap ? opt.dataset.cap + ' eggs' : '—';
  document.getElementById('inc_info_loc').textContent  = opt.dataset.loc || '—';

  // Populate fields from saved settings
  document.getElementById('inc_target_temp').value = opt.dataset.target || 37.5;
  document.getElementById('inc_min_temp').value    = opt.dataset.min    || 37.0;
  document.getElementById('inc_max_temp').value    = opt.dataset.max    || 38.0;
  document.getElementById('inc_target_hum').value  = opt.dataset.th     || 55.0;
  document.getElementById('inc_min_hum').value     = opt.dataset.thmin  || 50.0;
  document.getElementById('inc_max_hum').value     = opt.dataset.thmax  || 60.0;
  document.getElementById('sw_duration').value     = sanitizeFloatValue(opt.dataset.swingDuration, 30, 5, 300);
  document.getElementById('sw_interval').value     = sanitizeFloatValue(opt.dataset.interval, 8, 0.01, 24);
  updateIntervalPreview('sw_interval', 'sw_interval_preview');

  new bootstrap.Modal(document.getElementById('incubateModal')).show();
}

// Auto-adjust min/max to ±3 when target temperature/humidity inputs change
;(function(){
  const tIn = document.getElementById('inc_target_temp');
  const hIn = document.getElementById('inc_target_hum');
  if (tIn) tIn.addEventListener('input', function(){ const v = parseFloat(this.value); if (!isNaN(v)) { const min = (v - 3).toFixed(1); const max = (v + 3).toFixed(1); const elMin = document.getElementById('inc_min_temp'); const elMax = document.getElementById('inc_max_temp'); if(elMin) elMin.value = min; if(elMax) elMax.value = max; } });
  if (hIn) hIn.addEventListener('input', function(){ const v = parseFloat(this.value); if (!isNaN(v)) { const min = (v - 3).toFixed(1); const max = (v + 3).toFixed(1); const elMin = document.getElementById('inc_min_hum'); const elMax = document.getElementById('inc_max_hum'); if(elMin) elMin.value = min; if(elMax) elMax.value = max; } });
})();

// ══════════════════════════════════════════════════════════
//  START NOW MODAL (Swing)
// ══════════════════════════════════════════════════════════
function confirmSwing() {
  const incId    = document.getElementById('qc_incubator_id').value;
  const duration = parseInt(document.getElementById('sw_duration').value) || 30;
  const interval = parseFloat(document.getElementById('sw_interval').value) || 8;

  if (!incId) return;
  if (duration < 5 || duration > 300) { alert('Duration must be between 5 and 300 seconds.'); return; }
  if (interval < 0.01 || interval > 24) { alert('Invalid turning interval.'); return; }

  // 1. Save turning interval to DB
  $.post('../ajax/admin_temperature.php', {
    action:           'update_settings',
    incubator_id:     incId,
    turning_interval: interval,
    // Keep existing values (send them unchanged)
    target_temp:      selectedOpt().dataset.target || 37.5,
    min_temp:         selectedOpt().dataset.min    || 37.0,
    max_temp:         selectedOpt().dataset.max    || 38.0,
    target_humidity:  selectedOpt().dataset.th     || 55.0,
    min_humidity:     selectedOpt().dataset.thmin  || 50.0,
    max_humidity:     selectedOpt().dataset.thmax  || 60.0
  }, function(r) {
    if (!r.success) { showFb('❌ Failed to save interval: ' + (r.message||''), 'danger'); return; }

    // 2. Also update swing duration via hardware_api
    $.post('../ajax/hardware_api.php', {
      action:       'set_swing_duration',
      incubator_id: incId,
      duration_sec: duration,
      token:        'ghost_hw_secret_2024'
    }, function() {
      // 3. Send swing_on command
      sendCmd('swing_on', function() {
        bootstrap.Modal.getInstance(document.getElementById('swingModal')).hide();
        showFb('✅ Swing motors will start within 5 seconds and run for ' + duration + ' s.', 'success');
      });
    }, 'json');
  }, 'json');
}

// ══════════════════════════════════════════════════════════
//  SEND COMMAND (direct stop buttons use this too)
// ══════════════════════════════════════════════════════════
const BTNS = ['#btnHeatOn','#btnHeatOff','#btnSwingOn','#btnSwingOff'];

function sendCmd(cmd, callback) {
  const incId = document.getElementById('qc_incubator_id').value;
  if (!incId) { showFb('No active incubator selected.','warning'); return; }

  BTNS.forEach(b => $(b).prop('disabled', true));

  $.post('../ajax/hardware_api.php', {
    action:       'manual_command',
    command:      cmd,
    incubator_id: incId,
    token:        'ghost_hw_secret_2024'
  }, function(res) {
    BTNS.forEach(b => $(b).prop('disabled', false));
    if (res.success) {
      if (typeof callback === 'function') { callback(); return; }
      const ok = {
        heater_on:  '✅ Heaters will turn ON within 5 seconds.',
        heater_off: '✅ Heaters turned OFF.',
        swing_on:   '✅ Swing motors will start within 5 seconds.',
        swing_off:  '✅ Swing motors stopped.'
      };
      showFb(ok[cmd] || '✅ Command sent.', 'success');
      setTimeout(fetchLiveStatus, 6000);
    } else {
      showFb('❌ ' + (res.message || 'Command failed.'), 'danger');
    }
  }, 'json').fail(function(xhr, textStatus, errorThrown) {
    BTNS.forEach(b => $(b).prop('disabled', false));
    const status = xhr && xhr.status ? xhr.status : '0';
    const msg = `❌ Could not reach server (status ${status}). ${textStatus || errorThrown || ''}`;
    showFb(msg, 'danger');
    // If parsererror, include response preview to help debugging
    if (textStatus === 'parsererror') {
      console.error('[AJAX] parsererror response:', xhr.responseText);
      const ct = xhr.getResponseHeader('Content-Type') || '';
      const preview = (xhr.responseText || '').substring(0,2000);
      showSessionModal('Server Error', msg + '\nContent-Type: ' + ct + '\nResponse preview: ' + preview);
    } else {
      showSessionModal('Server Error', msg);
    }
  });
}

function showFb(msg, type) {
  const el = document.getElementById('hwFeedback');
  const c = {
    success:{bg:'rgba(34,197,94,.12)', bd:'rgba(34,197,94,.3)',  cl:'#22c55e'},
    danger: {bg:'rgba(239,68,68,.12)',  bd:'rgba(239,68,68,.3)',  cl:'#ef4444'},
    warning:{bg:'rgba(245,166,35,.12)', bd:'rgba(245,166,35,.3)', cl:'#f5a623'},
    info:   {bg:'rgba(59,130,246,.10)', bd:'rgba(59,130,246,.25)',cl:'#3b82f6'}
  }[type] || {};
  el.style.cssText = `display:block;background:${c.bg};border:1px solid ${c.bd};color:${c.cl};margin-top:12px;padding:10px 16px;border-radius:8px;font-size:.83rem;`;
  el.textContent = msg;
}

function formatDisplayTime(value) {
  if (!value) return '—';
  return String(value).replace('T', ' ').replace('Z', '');
}

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

function setStatusPill(id, dotId, valueId, state, onText, offText, onClass, offClass) {
  const pill = document.getElementById(id);
  const dot = document.getElementById(dotId);
  const value = document.getElementById(valueId);
  if (!pill || !dot || !value) return;
  const isOn = !!state;
  pill.className = `status-pill ${isOn ? onClass : offClass}`;
  dot.className = `status-dot ${isOn ? 'on' : 'off'}`;
  value.textContent = isOn ? onText : offText;
}

function setDeviceModeBadge(mode, isOnline) {
  const normalized = String(mode || 'idle').toLowerCase();
  const active = isOnline && (normalized === 'incubating' || normalized === 'hatching');
  const label = isOnline ? normalized.toUpperCase() : 'OFFLINE';
  setStatusPill('deviceStatusBadge', 'deviceStatusDot', 'deviceStatusText', active, label, label, 'online', 'offline');
}

function setRelayState(dotId, valueId, state, onText, offText) {
  const dot = document.getElementById(dotId);
  const value = document.getElementById(valueId);
  if (dot) dot.className = `status-dot ${state ? 'on' : 'off'}`;
  if (value) value.textContent = state ? onText : offText;
}

function setModePill(state) {
  const pill = document.getElementById('monitorMode');
  if (!pill) return;
  const mode = (state || 'idle').toLowerCase();
  const label = mode === 'incubating' ? 'Incubating' : (mode === 'hatching' ? 'Hatching' : 'Idle');
  pill.className = `status-pill ${mode === 'idle' ? 'idle' : 'running'}`;
  pill.textContent = label;
}

function formatModeLabel(mode) {
  const normalized = String(mode || 'idle').toLowerCase();
  if (normalized === 'incubating') return 'Incubating';
  if (normalized === 'hatching') return 'Hatching';
  if (normalized === 'completed') return 'Completed';
  return 'Idle';
}

function setConnectionPill(state) {
  const pill = document.getElementById('monitorWifi');
  if (!pill) return;
  const online = state === 'connected';
  pill.className = `status-pill ${online ? 'online' : 'offline'}`;
  pill.textContent = online ? 'Connected' : 'Disconnected';
}

function formatDateTimeLabel(value) {
  if (!value) return '—';
  const normalized = String(value).replace(' ', 'T');
  const parsed = new Date(normalized);
  if (Number.isNaN(parsed.getTime())) return String(value).replace('T', ' ');
  return parsed.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function updatePendingSessionBanner() {
  const banner = document.getElementById('sessionQueueBanner');
  if (!banner) return;

  const hasUpcomingSession = !!liveSessionState.nextStartAt && liveSessionState.nextSessionStatus !== 'idle' && liveSessionState.status !== 'running';
  if (!hasUpcomingSession) {
    banner.classList.remove('active');
    return;
  }

  banner.classList.add('active');

  const titleEl = document.getElementById('sessionQueueTitle');
  const subtitleEl = document.getElementById('sessionQueueSubtitle');
  const stateEl = document.getElementById('sessionQueueState');
  const stateTextEl = document.getElementById('sessionQueueStateText');
  const batchEl = document.getElementById('sessionQueueBatch');
  const windowEl = document.getElementById('sessionQueueWindow');
  const countdownEl = document.getElementById('sessionQueueCountdown');

  const deviceOnline = liveSessionState.wifiStatus === 'connected';
  if (titleEl) titleEl.textContent = 'About to start';
  if (subtitleEl) subtitleEl.textContent = deviceOnline ? 'Device online, ready to start' : 'Waiting for device heartbeat';
  if (stateEl) stateEl.className = `session-queue-status ${deviceOnline ? 'ready' : ''}`.trim();
  if (stateTextEl) stateTextEl.textContent = deviceOnline ? 'Ready' : 'Waiting';

  const batchLabel = liveSessionState.nextSessionName
    ? liveSessionState.nextSessionName
    : (liveSessionState.activeSessionName || (liveSessionState.activeBatchId ? `Batch #${liveSessionState.activeBatchId}` : 'Scheduled session'));
  if (batchEl) batchEl.textContent = batchLabel;

  const startLabel = formatDateTimeLabel(liveSessionState.nextStartAt);
  const endLabel = formatDateTimeLabel(liveSessionState.nextEndsAt);
  if (windowEl) windowEl.textContent = `${startLabel} → ${endLabel}`;

  const startEpoch = liveSessionState.nextStartEpoch ? Number(liveSessionState.nextStartEpoch) : Date.parse(String(liveSessionState.nextStartAt).replace(' ', 'T'));
  if (countdownEl) {
    if (Number.isFinite(startEpoch)) {
      const diffSeconds = Math.max(0, Math.floor((startEpoch - Date.now()) / 1000));
      countdownEl.textContent = diffSeconds > 0 ? `Starts in ${formatCountdown(diffSeconds)}` : 'Waiting for device';
    } else {
      countdownEl.textContent = 'Waiting for device';
    }
  }
}

// ══════════════════════════════════════════════════════════
//  LIVE STATUS POLLING
// ══════════════════════════════════════════════════════════
function fetchLiveStatus() {
  const incId = document.getElementById('qc_incubator_id').value;
  if (!incId) return;
  $.post('../ajax/hardware_api.php', {
    action:'get_live_status', incubator_id:incId, token:'ghost_hw_secret_2024'
  }, function(res) {
    if (!res.success) return;
    const dot = document.getElementById('hwOnlineDot');
    const lbl = document.getElementById('hwOnlineLabel');
    const tempValue = parseFloat(res.current_temp);
    const humidityValue = parseFloat(res.current_humidity);
    const hasTemp = Number.isFinite(tempValue) && tempValue > 0;
    const hasHumidity = Number.isFinite(humidityValue) && humidityValue > 0;
    const isOnline = res.online && hasTemp && hasHumidity;
    
    if (isOnline) { 
      dot.style.background='#22c55e'; 
      lbl.style.color='#22c55e'; 
      lbl.textContent='Online'; 
    } else { 
      dot.style.background='#ef4444'; 
      lbl.style.color='#ef4444'; 
      lbl.textContent='Offline'; 
    }
    
    setDeviceModeBadge(res.current_mode || 'idle', isOnline);
    syncSelectedOptSettings(res);
    
    // Only display data if device is online
    if (isOnline) {
      setText('monitorTemp', res.current_temp ? parseFloat(res.current_temp).toFixed(1)+'°C' : '—');
      setText('monitorHumidity', res.current_humidity ? parseFloat(res.current_humidity).toFixed(1)+'%' : '—');
      setRelayState('heater1Dot', 'monitorHeater1', res.heater_1_status, 'ON', 'OFF');
      setRelayState('heater2Dot', 'monitorHeater2', res.heater_2_status, 'ON', 'OFF');
      setRelayState('heaterFanDot', 'monitorHeaterFan', res.heater_fan_status, 'ON', 'OFF');
      setRelayState('exhaustDot', 'monitorExhaust', res.exhaust_status, 'ON', 'OFF');
      setRelayState('swingDot', 'monitorSwing', res.swing_status, 'RUNNING', 'OFF');
      setText('monitorIncubationDay', res.incubation_day ? `Day ${res.incubation_day}` : '—');
      setText('monitorSessionName', res.active_session_name || '—');
      setText('monitorMode', res.current_mode || 'Idle');
      setText('monitorLastSync', formatDisplayTime(res.last_server_sync || res.last_seen));
      setText('monitorSessionStatus', formatModeLabel(res.current_mode || (res.session_status === 'running' ? 'incubating' : 'idle')));
      setText('monitorSessionEnds', formatDisplayTime(res.session_ends_at));

      if (res.active_batch_id) {
        setText('monitorParamsId', `Batch #${res.active_batch_id}`);
      } else if (res.session_number) {
        setText('monitorParamsId', `Session #${res.session_number}`);
      } else {
        setText('monitorParamsId', '—');
      }
    } else {
      // Show "--" for all values when offline
      setText('monitorTemp', '—');
      setText('monitorHumidity', '—');
      setText('monitorHeater1', '—');
      setText('monitorHeater2', '—');
      setText('monitorHeaterFan', '—');
      setText('monitorExhaust', '—');
      setText('monitorSwing', '—');
      setText('monitorIncubationDay', '—');
      setText('monitorSessionName', '—');
      setText('monitorMode', '—');
      setText('monitorLastSync', '—');
      setText('monitorSessionStatus', 'Offline');
      setText('monitorParamsId', '—');
      setText('monitorSessionEnds', '—');
      document.getElementById('heater1Dot').className = 'status-dot';
      document.getElementById('heater2Dot').className = 'status-dot';
      document.getElementById('heaterFanDot').className = 'status-dot';
      document.getElementById('exhaustDot').className = 'status-dot';
      document.getElementById('swingDot').className = 'status-dot';
    }
    
    setConnectionPill(res.wifi_status);
    liveSessionState.startedAt = res.session_started_at || null;
    liveSessionState.endsAt = res.session_ends_at || null;
    liveSessionState.status = res.session_status || 'idle';
    liveSessionState.currentMode = res.current_mode || 'idle';
    liveSessionState.activeBatchId = res.active_batch_id || null;
    liveSessionState.activeSessionName = res.active_session_name || null;
    liveSessionState.incubationDay = res.incubation_day || null;
    liveSessionState.runningOps = res.running_ops || 'idle';
    liveSessionState.deviceStatus = isOnline ? 'online' : 'offline';
    liveSessionState.wifiStatus = res.wifi_status || 'disconnected';
    liveSessionState.lastEggTurnAt = res.last_egg_turn_at || null;
    liveSessionState.lastServerSync = res.last_server_sync || res.last_seen || null;
    liveSessionState.nextStartAt = res.next_session_start_at || null;
    liveSessionState.nextStartEpoch = res.next_session_start_epoch || null;
    liveSessionState.nextEndsAt = res.next_session_ends_at || null;
    liveSessionState.nextSessionName = res.next_session_name || null;
    liveSessionState.nextSessionStatus = res.next_session_status || 'idle';
    liveSessionState.scheduledBatchCount = res.scheduled_batch_count || 0;
    updatePendingSessionBanner();
    updateSessionCountdown();
  }, 'json');
}

function formatCountdown(totalSeconds) {
  const days = Math.floor(totalSeconds / 86400);
  const hours = Math.floor((totalSeconds % 86400) / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;
  return `${days}d ${hours}h ${minutes}m ${seconds}s`;
}

function updateSessionCountdown() {
  const statusEl = document.getElementById('liveSessionStatus');
  const countdownEl = document.getElementById('liveSessionCountdown');
  const startedEl = document.getElementById('liveSessionStarted');
  const endsEl = document.getElementById('liveSessionEnds');
  if (!statusEl || !countdownEl || !startedEl || !endsEl) return;

  if (!liveSessionState.startedAt || liveSessionState.status === 'idle') {
    statusEl.textContent = 'Idle';
    countdownEl.textContent = '—';
    startedEl.textContent = 'Started: —';
    endsEl.textContent = 'Ends: —';
    return;
  }

  const startedText = liveSessionState.startedAt.replace('T', ' ').replace('Z', '');
  const endsText = liveSessionState.endsAt ? liveSessionState.endsAt.replace('T', ' ').replace('Z', '') : '—';
  startedEl.textContent = 'Started: ' + startedText;
  endsEl.textContent = 'Ends: ' + endsText;

  const endTime = liveSessionState.endsAt ? new Date(liveSessionState.endsAt.replace(' ', 'T')) : null;
  if (!endTime) {
    statusEl.textContent = liveSessionState.status === 'completed' ? 'Completed' : 'Running';
    countdownEl.textContent = '—';
    return;
  }

  const remaining = Math.max(0, Math.floor((endTime.getTime() - Date.now()) / 1000));
  if (liveSessionState.status === 'completed' || remaining === 0) {
    statusEl.textContent = 'Completed';
    countdownEl.textContent = 'Finished';
    return;
  }

  statusEl.textContent = 'Running';
  countdownEl.textContent = formatCountdown(remaining);
}

// Start polling live status on page load
document.addEventListener('DOMContentLoaded', function() {
  // Initial fetch
  setTimeout(fetchLiveStatus, 500);
  // Auto-refresh every 4 seconds for near real-time updates
  setInterval(fetchLiveStatus, 4000);
  setInterval(updateSessionCountdown, 1000);
});

// Modal to show session start/stop success or errors
const sessionModalHtml = `
<div class="modal fade" id="sessionStatusModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="sessionStatusTitle">Status</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="sessionStatusBody">Message</div>
      <div class="modal-footer"><button class="btn-outline-ghost" data-bs-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>`;
(function(){ if (!document.getElementById('sessionStatusModal')) document.body.insertAdjacentHTML('beforeend', sessionModalHtml); })();

function showSessionModal(title, message) {
  const t = document.getElementById('sessionStatusTitle');
  const b = document.getElementById('sessionStatusBody');
  if (t) t.textContent = title;
  if (b) b.textContent = message;
  const el = document.getElementById('sessionStatusModal');
  if (el) {
    // Hide any other open bootstrap modals first to avoid aria-hidden/focus issues
    document.querySelectorAll('.modal.show').forEach(m => {
      try { const instance = bootstrap.Modal.getInstance(m); if (instance) instance.hide(); } catch(e){}
    });
    const modal = new bootstrap.Modal(el);
    modal.show();
    // Move focus to close button for accessibility
    setTimeout(() => {
      const closeBtn = el.querySelector('.btn-outline-ghost');
      if (closeBtn) closeBtn.focus();
    }, 120);
  }
}

// Reusable confirm modal HTML + helper
const confirmModalHtml = `
<div class="modal fade" id="confirmActionModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="confirmActionTitle">Please confirm</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="confirmActionBody">Are you sure?</div>
      <div class="modal-footer">
        <button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-ghost" id="confirmActionOk">Confirm</button>
      </div>
    </div>
  </div>
</div>`;
(function(){ if (!document.getElementById('confirmActionModal')) document.body.insertAdjacentHTML('beforeend', confirmModalHtml); })();

function showConfirm(title, message, onConfirm) {
  const el = document.getElementById('confirmActionModal');
  if (!el) return onConfirm();
  const t = document.getElementById('confirmActionTitle');
  const b = document.getElementById('confirmActionBody');
  const ok = document.getElementById('confirmActionOk');
  if (t) t.textContent = title;
  if (b) b.textContent = message;
  // ensure any other modals are hidden
  document.querySelectorAll('.modal.show').forEach(m => {
    try { const instance = bootstrap.Modal.getInstance(m); if (instance) instance.hide(); } catch(e){}
  });
  const modal = new bootstrap.Modal(el);
  modal.show();
  ok.focus();
  function cleanup() {
    ok.removeEventListener('click', okHandler);
  }
  function okHandler() {
    cleanup();
    modal.hide();
    if (typeof onConfirm === 'function') onConfirm();
  }
  ok.addEventListener('click', okHandler);
}

// ══════════════════════════════════════════════════════════
//  Quick session controls and animation
// ══════════════════════════════════════════════════════════
let _pulseTimer = null;
function startPulse() {
  const pulse = document.getElementById('sessionPulse');
  if (!pulse) return;
  pulse.style.display = 'inline-block';
  pulse.style.background = '#22c55e';
  let scaleUp = true;
  _pulseTimer = setInterval(() => {
    pulse.style.transform = scaleUp ? 'scale(1.35)' : 'scale(1)';
    pulse.style.opacity = scaleUp ? '0.9' : '0.6';
    scaleUp = !scaleUp;
  }, 700);
}
function stopPulse() {
  const pulse = document.getElementById('sessionPulse');
  if (!pulse) return;
  pulse.style.display = 'none';
  pulse.style.transform = 'scale(1)';
  pulse.style.opacity = '1';
  if (_pulseTimer) { clearInterval(_pulseTimer); _pulseTimer = null; }
}

function updateControlButtons() {
  const status = liveSessionState.status || 'idle';
  const deviceOnline = liveSessionState.deviceStatus === 'online';
  
  // New monitoring panel buttons
  const btnStart = document.getElementById('btnStart');
  const btnSetParams = document.getElementById('btnSetParams');
  
  if (btnStart) {
    btnStart.disabled = !deviceOnline;
  }
  if (btnSetParams) {
    btnSetParams.disabled = !deviceOnline;
  }
  
  // Old session modal buttons (if they exist)
  const startBtn = document.getElementById('btnStartSession');
  const stopBtn = document.getElementById('btnStopSession');
  const setBtn = document.getElementById('btnSetParams');
  const label = document.getElementById('sessionLabel');
  if (status === 'running') {
    if (startBtn) startBtn.style.display = 'none';
    if (stopBtn) stopBtn.style.display = 'inline-block';
    if (label) label.textContent = 'Session: Running';
    startPulse();
    if (setBtn) setBtn.style.display = 'none';
  } else {
    if (startBtn) startBtn.style.display = 'inline-block';
    if (stopBtn) stopBtn.style.display = 'none';
    if (label) label.textContent = 'Session: Idle';
    stopPulse();
    if (setBtn) setBtn.style.display = 'inline-block';
  }
}

function startSessionQuick() {
  showFb('Admin session start is disabled. Use the user schedule flow.', 'warning');
}

function stopSessionQuick() {
  const incId = document.getElementById('qc_incubator_id').value;
  if (!incId) { showFb('No incubator selected','warning'); return; }
  showConfirm('Stop session', 'Stop the current incubation session early? This will mark it completed and turn hardware off.', function() {
    showFb('Stopping session…', 'info');
    $.post('../ajax/hardware_api.php', { action:'stop_session', incubator_id: incId, token: 'ghost_hw_secret_2024' }, function(res) {
      if (!res.success) { showFb('❌ Could not stop: ' + (res.message||''), 'danger'); return; }
      // Send all_off to hardware as well
      sendCmd('all_off', function() {
        showFb('✅ Session stopped.', 'success');
        fetchLiveStatus();
      });
    }, 'json').fail(function(xhr, textStatus, errorThrown){
      const status = xhr && xhr.status ? xhr.status : '0';
      const msg = `❌ Could not reach server (status ${status}). ${textStatus || errorThrown || ''}`;
      showFb(msg, 'danger');
      if (textStatus === 'parsererror') {
        console.error('[AJAX] parsererror response:', xhr.responseText);
        const ct = xhr.getResponseHeader('Content-Type') || '';
        const preview = (xhr.responseText || '').substring(0,2000);
        showSessionModal('Server Error', msg + '\nContent-Type: ' + ct + '\nResponse preview: ' + preview);
      } else {
        showSessionModal('Server Error', msg);
      }
    });
  });
}

// Ensure control buttons reflect state after every live status fetch
const _origFetchLiveStatus = fetchLiveStatus;
fetchLiveStatus = function() { _origFetchLiveStatus(); setTimeout(updateControlButtons, 250); };

</script>

<?php require_once 'footer.php'; ?>
