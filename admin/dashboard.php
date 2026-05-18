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
$totalEggs        = $pdo->query("SELECT COALESCE(SUM(egg_count),0) FROM batches WHERE status='incubating'")->fetchColumn();
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
      <div class="stat-badge up">In incubation</div>
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
  .control-buttons { display:flex; gap:10px; margin-top:14px; }
  .control-buttons button { flex:1; padding:10px; border:1px solid var(--ghost-border); background:rgba(255,255,255,.03); color:white; border-radius:8px; font-size:.85rem; font-weight:600; cursor:pointer; transition:all .3s ease; }
  .control-buttons button:hover:not(:disabled) { background:var(--ghost-amber); border-color:var(--ghost-amber); color:white; transform:translateY(-2px); }
  .control-buttons button:disabled { opacity:.35; cursor:not-allowed; background:rgba(255,255,255,.01); border-color:rgba(255,255,255,.04); color:var(--ghost-muted); }
</style>
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
          <div style="font-size:.72rem;color:var(--ghost-muted);letter-spacing:.05em;text-transform:uppercase;margin-bottom:8px;">Device Status</div>
          <div id="deviceStatusBadge" class="status-pill offline"><span id="deviceStatusDot" class="status-dot off"></span><span id="deviceStatusText">OFFLINE</span></div>
          <div style="font-size:.72rem;color:var(--ghost-muted);margin-top:10px;line-height:1.5;">Monitoring is view only. Hardware changes are driven automatically by the controller and downloaded parameters.</div>
        </div>

        <div class="control-buttons">
          <button id="btnStart" onclick="startSessionQuick()" disabled title="Start incubation session">
            <i class="fas fa-play"></i> Start
          </button>
          <button id="btnSetParams" onclick="openIncubateModal()" disabled title="Set incubation parameters">
            <i class="fas fa-sliders-h"></i> Set Params
          </button>
        </div>
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
        <a href="batches.php" style="font-size:.8rem;color:var(--ghost-amber);text-decoration:none;">View all →</a>
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

        <!-- Species / egg type -->
        <div class="mb-3">
          <label class="form-label-ghost">🐣 Egg Species / Type</label>
          <select class="form-select-ghost" id="inc_species" onchange="applySpeciesDefaults()">
            <option value="chicken"  data-target="37.5" data-min="37.0" data-max="38.0" data-hum="55" data-days="21">🐔 Chicken (21 days)</option>
            <option value="duck"     data-target="37.5" data-min="37.2" data-max="38.0" data-hum="60" data-days="28">🦆 Duck (28 days)</option>
            <option value="quail"    data-target="37.5" data-min="37.0" data-max="38.0" data-hum="50" data-days="18">🐦 Quail (18 days)</option>
            <option value="turkey"   data-target="37.5" data-min="37.0" data-max="38.2" data-hum="55" data-days="28">🦃 Turkey (28 days)</option>
            <option value="goose"    data-target="37.4" data-min="37.0" data-max="38.0" data-hum="60" data-days="30">🪿 Goose (30 days)</option>
            <option value="custom"   data-target=""     data-min=""     data-max=""     data-hum=""   data-days="">⚙️ Custom</option>
          </select>
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
          Set Parameters saves the values to the database and pushes them to the ESP8266. Start Incubation begins the session timer and then turns on the PTC heater via Relay 1.
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
              <input type="number" min="5" max="300" class="form-control-ghost" id="sw_duration" value="30"
                style="font-size:1.4rem;font-family:'Bebas Neue',sans-serif;color:#22c55e;text-align:center;">
              <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">Recommended: 30–60 seconds per cycle. Max 300 s.</div>
            </div>
          </div>
        </div>

        <!-- Turning interval -->
        <div style="background:rgba(255,255,255,.03);border:1px solid var(--ghost-border);border-radius:12px;padding:18px;margin-bottom:14px;">
          <div style="font-size:.72rem;font-weight:700;color:var(--ghost-muted);letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">🔁 Auto-Turn Interval</div>
          <label class="form-label-ghost">Turn eggs every (hours)</label>
          <input type="number" min="1" max="24" class="form-control-ghost" id="sw_interval" value="8"
            style="font-size:1.4rem;font-family:'Bebas Neue',sans-serif;color:white;text-align:center;">
          <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">Recommended: 3–8 hours. Common default: 4 hours.</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
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
// Store chart instance globally for updates
let tempChartInstance = null;
let liveSessionState = {
  startedAt: null,
  endsAt: null,
  status: 'idle',
  currentMode: 'idle',
  activeSessionName: null,
  incubationDay: null,
  runningOps: 'idle',
  wifiStatus: 'disconnected',
  lastEggTurnAt: null,
  lastServerSync: null
};

window.addEventListener('load', function() {
  Chart.defaults.color='#64748b'; Chart.defaults.font.family='Space Grotesk';
  tempChartInstance = new Chart(document.getElementById('tempChart'),{
    type:'line',
    data:{
      labels:<?= json_encode(array_column($tempLogs,'lbl')) ?>,
      datasets:[
        {label:'Temperature (°C)',yAxisID:'y',data:<?= json_encode(array_column($tempLogs,'temperature')) ?>,borderColor:'#f5a623',backgroundColor:'rgba(245,166,35,.08)',tension:.4,pointRadius:4,pointBackgroundColor:'#f5a623',borderWidth:2,fill:true},
        {label:'Humidity (%)',yAxisID:'y2',data:<?= json_encode(array_column($tempLogs,'humidity')) ?>,borderColor:'#3b82f6',backgroundColor:'rgba(59,130,246,.06)',tension:.4,pointRadius:4,pointBackgroundColor:'#3b82f6',borderWidth:2,fill:true}
      ]
    },
    options:{responsive:true,interaction:{mode:'index',intersect:false},plugins:{legend:{labels:{boxWidth:10,padding:16,font:{size:11}}}},
      scales:{
        x:{grid:{color:'rgba(255,255,255,.04)'},ticks:{font:{size:10}}},
        y:{grid:{color:'rgba(255,255,255,.04)'},min:36,max:39,ticks:{font:{size:10},callback:v=>v+'°C'}},
        y2:{position:'right',grid:{display:false},min:45,max:70,ticks:{font:{size:10},callback:v=>v+'%'}}
      }
    }
  });
  
  // Start auto-refresh of temperature chart every 30 seconds
  setInterval(refreshTemperatureChart, 30000);
});

// Function to refresh temperature chart with latest data
function refreshTemperatureChart() {
  const incId = document.getElementById('qc_incubator_id').value;
  if (!incId || !tempChartInstance) return;
  
  $.get('../ajax/admin_temperature_logs.php', {
    incubator_id: incId,
    limit: 10
  }, function(data) {
    if (data && data.length > 0) {
      tempChartInstance.data.labels = data.map(d => d.lbl);
      tempChartInstance.data.datasets[0].data = data.map(d => d.temperature);
      tempChartInstance.data.datasets[1].data = data.map(d => d.humidity);
      tempChartInstance.update();
    }
  }, 'json');
}

// ══════════════════════════════════════════════════════════
//  HELPER: get selected incubator <option> element
// ══════════════════════════════════════════════════════════
function selectedOpt() {
  const sel = document.getElementById('qc_incubator_id');
  return sel.options[sel.selectedIndex];
}

function onIncubatorChange() {
  fetchLiveStatus();
}

function getIncubationFormValues() {
  const species = document.getElementById('inc_species');
  const speciesOption = species.options[species.selectedIndex];
  return {
    incId: document.getElementById('qc_incubator_id').value,
    eggCount: parseInt(document.getElementById('inc_egg_count').value) || 0,
    targetTemp: parseFloat(document.getElementById('inc_target_temp').value),
    minTemp: parseFloat(document.getElementById('inc_min_temp').value),
    maxTemp: parseFloat(document.getElementById('inc_max_temp').value),
    targetHum: parseFloat(document.getElementById('inc_target_hum').value),
    minHum: parseFloat(document.getElementById('inc_min_hum').value),
    maxHum: parseFloat(document.getElementById('inc_max_hum').value),
    duration: parseInt(document.getElementById('sw_duration').value) || 30,
    interval: parseInt(document.getElementById('sw_interval').value) || 8,
    sessionDays: parseInt(speciesOption.dataset.days || '21') || 21
  };
}

function setTurningIntervalPreset(hours) {
  const input = document.getElementById('sw_interval');
  if (input) input.value = hours;
}

function saveIncubationSettings(startSession) {
  const values = getIncubationFormValues();
  if (!values.incId) return;
  if (isNaN(values.eggCount) || values.eggCount < 1) { alert('Please enter how many eggs were placed.'); return; }
  if (isNaN(values.targetTemp) || isNaN(values.minTemp) || isNaN(values.maxTemp)) { alert('Please fill in all temperature fields.'); return; }
  if (values.minTemp >= values.maxTemp) { alert('Min temp must be less than Max temp.'); return; }
  if (isNaN(values.duration) || values.duration < 5 || values.duration > 300) { alert('Swing duration must be between 5 and 300 seconds.'); return; }
  if (!Number.isFinite(values.interval) || values.interval < 1) { alert('Turning interval too short.'); return; }
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

    $.post('../ajax/hardware_api.php', {
      action: 'set_swing_duration',
      incubator_id: values.incId,
      duration_sec: values.duration,
      token: 'ghost_hw_secret_2024'
    }, function() {
      if (!startSession) {
        showFb('✅ Parameters saved.', 'success');
        fetchLiveStatus();
        return;
      }

      $.post('../ajax/hardware_api.php', {
        action: 'start_session',
        incubator_id: values.incId,
        egg_count: values.eggCount,
        egg_type: speciesOption.value,
        session_days: values.sessionDays,
        token: 'ghost_hw_secret_2024'
      }, function(startRes) {
        if (!startRes.success) {
          showFb('❌ Could not start session: ' + (startRes.message || ''), 'danger');
          return;
        }

        sendCmd('heater_on', function() {
          bootstrap.Modal.getInstance(document.getElementById('incubateModal')).hide();
          showFb('✅ Session started. Countdown is now running.', 'success');
          fetchLiveStatus();
        });
      }, 'json');
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
  document.getElementById('sw_duration').value     = 30;
  document.getElementById('sw_interval').value     = opt.dataset.interval || 8;

  new bootstrap.Modal(document.getElementById('incubateModal')).show();
}

function applySpeciesDefaults() {
  const sp = document.getElementById('inc_species');
  const o  = sp.options[sp.selectedIndex];
  if (o.value === 'custom') return; // let user type freely
  const t = parseFloat(o.dataset.target);
  const h = parseFloat(o.dataset.hum);
  document.getElementById('inc_target_temp').value = t;
  document.getElementById('inc_min_temp').value    = (t - 0.5).toFixed(1);
  document.getElementById('inc_max_temp').value    = (t + 0.5).toFixed(1);
  document.getElementById('inc_target_hum').value  = h;
  document.getElementById('inc_min_hum').value     = (h - 5).toFixed(1);
  document.getElementById('inc_max_hum').value     = (h + 5).toFixed(1);
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
const startSessionModalHtml = `
<div class="modal fade" id="startSessionModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title">▶ Start Session</h5>
          <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:2px;">Confirm the session before heaters begin</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:24px;">
        <div style="background:rgba(245,166,35,.06);border:1px solid rgba(245,166,35,.2);border-radius:10px;padding:12px 16px;margin-bottom:18px;">
          <div style="font-size:.75rem;color:var(--ghost-muted);margin-bottom:4px;">Selected incubator</div>
          <div style="font-weight:700;color:white;" id="startSessionIncubatorName">—</div>
        </div>
        <div class="mb-3" style="margin-bottom:18px;">
          <label class="form-label-ghost" style="display:block;margin-bottom:8px;font-weight:600;">🥚 How many eggs were placed?</label>
          <input type="number" min="1" step="1" class="form-control-ghost" id="startSessionEggCount" placeholder="Enter egg count" style="padding:10px;font-size:1rem;">
          <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">This will be saved to the batch list and session record.</div>
        </div>
        <div style="font-size:.78rem;color:var(--ghost-muted);padding:10px 14px;background:rgba(255,255,255,.02);border-radius:8px;border:1px solid var(--ghost-border);">
          <i class="fas fa-info-circle me-1" style="color:var(--ghost-amber);"></i>
          Starting a session will create/update the incubating batch, turn on the heaters, and begin the countdown.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-ghost" id="confirmStartSessionBtn">Start Session</button>
      </div>
    </div>
  </div>
</div>`;
(function(){ if (!document.getElementById('startSessionModal')) document.body.insertAdjacentHTML('beforeend', startSessionModalHtml); })();

function openSwingModal() {
  const opt = selectedOpt();
  if (!opt || !opt.value) { showFb('No active incubator selected.','warning'); return; }
  // Pre-fill interval from saved settings
  document.getElementById('sw_interval').value = opt.dataset.interval || 8;
  new bootstrap.Modal(document.getElementById('swingModal')).show();
}

function openStartSessionModal() {
  const opt = selectedOpt();
  if (!opt || !opt.value) { showFb('No active incubator selected.','warning'); return; }
  
  const status = liveSessionState.status || 'idle';
  if (status === 'running') { 
    showFb('❌ A session is already running. Stop it first before starting a new one.', 'warning');
    return;
  }
  
  const incNameEl = document.getElementById('startSessionIncubatorName');
  const eggCountEl = document.getElementById('startSessionEggCount');
  if (incNameEl) incNameEl.textContent = opt.text || '—';
  if (eggCountEl) {
    eggCountEl.value = '';
    eggCountEl.max = opt.dataset.cap || '';
    eggCountEl.placeholder = opt.dataset.cap ? `Enter egg count (max ${opt.dataset.cap})` : 'Enter egg count';
    eggCountEl.style.display = 'block';
    eggCountEl.focus();
  }
  const ok = document.getElementById('confirmStartSessionBtn');
  if (ok && !ok.dataset.bound) {
    ok.dataset.bound = '1';
    ok.removeEventListener('click', confirmStartSession);
    ok.addEventListener('click', confirmStartSession);
  }
  new bootstrap.Modal(document.getElementById('startSessionModal')).show();
}

function confirmStartSession() {
  const incId = document.getElementById('qc_incubator_id').value;
  const eggCount = parseInt(document.getElementById('startSessionEggCount').value) || 0;
  const sel = selectedOpt();
  const days = parseInt((sel && sel.dataset && sel.dataset.days) ? sel.dataset.days : 21) || 21;
  if (!incId) { showFb('No incubator selected','warning'); return; }
  if (eggCount < 1) { showFb('Please enter how many eggs were placed.', 'warning'); return; }

  showFb('Starting session…', 'info');
  $.post('../ajax/hardware_api.php', {
    action:'start_session',
    incubator_id: incId,
    egg_count: eggCount,
    session_days: days,
    token: 'ghost_hw_secret_2024'
  }, function(res) {
    if (!res.success) { showFb('❌ Could not start: ' + (res.message||''), 'danger'); return; }
    sendCmd('heater_on', function() {
      const modal = bootstrap.Modal.getInstance(document.getElementById('startSessionModal'));
      if (modal) modal.hide();
      showFb('✅ Session started.', 'success');
      fetchLiveStatus();
    });
  }, 'json').fail(function(xhr, textStatus, errorThrown){
    const status = xhr && xhr.status ? xhr.status : '0';
    const msg = `❌ Could not reach server (status ${status}). ${textStatus || errorThrown || ''}`;
    showFb(msg, 'danger');
  });
}

function confirmSwing() {
  const incId    = document.getElementById('qc_incubator_id').value;
  const duration = parseInt(document.getElementById('sw_duration').value) || 30;
  const interval = parseInt(document.getElementById('sw_interval').value) || 8;

  if (!incId) return;
  if (duration < 5 || duration > 300) { alert('Duration must be between 5 and 300 seconds.'); return; }

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

function setConnectionPill(state) {
  const pill = document.getElementById('monitorWifi');
  if (!pill) return;
  const online = state === 'connected';
  pill.className = `status-pill ${online ? 'online' : 'offline'}`;
  pill.textContent = online ? 'Connected' : 'Disconnected';
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
    const isOnline = res.online;
    
    if (isOnline) { 
      dot.style.background='#22c55e'; 
      lbl.style.color='#22c55e'; 
      lbl.textContent='Online'; 
    } else { 
      dot.style.background='#ef4444'; 
      lbl.style.color='#ef4444'; 
      lbl.textContent='Offline'; 
    }
    
    setStatusPill('deviceStatusBadge', 'deviceStatusDot', 'deviceStatusText', isOnline, 'ONLINE', 'OFFLINE', 'online', 'offline');
    
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
    liveSessionState.activeSessionName = res.active_session_name || null;
    liveSessionState.incubationDay = res.incubation_day || null;
    liveSessionState.runningOps = res.running_ops || 'idle';
    liveSessionState.wifiStatus = res.wifi_status || 'disconnected';
    liveSessionState.lastEggTurnAt = res.last_egg_turn_at || null;
    liveSessionState.lastServerSync = res.last_server_sync || res.last_seen || null;
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
  const deviceOnline = liveSessionState.wifiStatus === 'connected' || document.getElementById('hwOnlineDot').style.background === '#22c55e';
  
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
  const incId = document.getElementById('qc_incubator_id').value;
  if (!incId) { showFb('No incubator selected','warning'); return; }
  openStartSessionModal();
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
