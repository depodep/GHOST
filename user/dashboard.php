<?php
$pageTitle    = 'My Dashboard';
$pageSubtitle = 'Your incubation overview';
$activePage   = 'dashboard';
require_once 'header.php';
$pdo = getDB();
$uid = $_SESSION['user_id'];

// ── Stats ──────────────────────────────────────────────────
$s = $pdo->prepare("SELECT COUNT(*) FROM batches WHERE user_id=? AND status='incubating'");
$s->execute([$uid]); $myBatches = $s->fetchColumn();

$s = $pdo->prepare("SELECT COUNT(*) FROM batches WHERE user_id=? AND status='completed'");
$s->execute([$uid]); $completedBatches = $s->fetchColumn();

$s = $pdo->prepare("SELECT COUNT(*) FROM batches WHERE user_id=? AND status='terminated'");
$s->execute([$uid]); $terminatedBatches = $s->fetchColumn();

$s = $pdo->prepare("SELECT COALESCE(SUM(egg_count),0) FROM batches WHERE user_id=? AND status='incubating'");
$s->execute([$uid]); $myEggs = $s->fetchColumn();

$s = $pdo->prepare("SELECT COUNT(*) FROM schedules s JOIN batches b ON s.batch_id=b.id WHERE b.user_id=? AND s.scheduled_date=CURDATE() AND s.status='pending'");
$s->execute([$uid]); $todaySched = $s->fetchColumn();

$s = $pdo->prepare("SELECT COUNT(*) FROM batches WHERE user_id=? AND status='incubating' AND expected_hatch_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)");
$s->execute([$uid]); $hatchSoon = $s->fetchColumn();

// ── Data ───────────────────────────────────────────────────
$batchStmt = $pdo->prepare(
    "SELECT b.*, i.name AS incubator_name, i.id AS incubator_id,
            DATE_FORMAT(b.created_at, '%Y-%m-%d %H:%i') as session_start_time
     FROM batches b JOIN incubators i ON b.incubator_id=i.id
     WHERE b.user_id=? ORDER BY b.created_at DESC LIMIT 5");
$batchStmt->execute([$uid]); $batches = $batchStmt->fetchAll();

$schedStmt = $pdo->prepare(
    "SELECT s.*, i.name AS incubator_name
     FROM schedules s
     JOIN incubators i ON s.incubator_id=i.id
     LEFT JOIN batches b ON s.batch_id=b.id
     WHERE (b.user_id=? OR s.created_by_id=?)
       AND s.scheduled_date >= CURDATE()
       AND NOT (s.scheduled_date = CURDATE() AND s.scheduled_time < CURTIME())
       AND s.status = 'pending'
     ORDER BY s.scheduled_date, s.scheduled_time LIMIT 6");
$schedStmt->execute([$uid, $uid]); $schedules = $schedStmt->fetchAll();

// All active incubators with settings — same as admin
$userIncubators = $pdo->query(
    "SELECT i.*, ts.target_temp, ts.min_temp, ts.max_temp,
            ts.target_humidity, ts.min_humidity, ts.max_humidity, ts.turning_interval
     FROM incubators i
     LEFT JOIN temperature_settings ts ON ts.incubator_id=i.id
     WHERE i.status='active'
     ORDER BY i.name")->fetchAll();


$tempLogs = array_reverse($pdo->query(
    "SELECT temperature, humidity, DATE_FORMAT(recorded_at,'%H:%i') AS lbl
     FROM temperature_logs WHERE incubator_id=1
     ORDER BY recorded_at DESC LIMIT 10")->fetchAll());
?>

<!-- ── STAT CARDS ──────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-2">
        <div class="stat-card">
            <div class="stat-icon">📦</div>
            <div class="stat-label">Active Batches</div>
            <div class="stat-val blue"><?= $myBatches ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-label">Completed</div>
            <div class="stat-val blue"><?= $completedBatches ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="stat-card">
            <div class="stat-icon">🛑</div>
            <div class="stat-label">Terminated</div>
            <div class="stat-val blue"><?= $terminatedBatches ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="stat-card">
            <div class="stat-icon">🥚</div>
            <div class="stat-label">Total Eggs</div>
            <div class="stat-val blue"><?= number_format($myEggs) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-label">Today's Tasks</div>
            <div class="stat-val blue"><?= $todaySched ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="stat-card">
            <div class="stat-icon">🐣</div>
            <div class="stat-label">Hatching Soon</div>
            <div class="stat-val blue"><?= $hatchSoon ?></div>
        </div>
    </div>
</div>

<div class="ghost-panel mb-4" id="sessionParamsCard">
    <div class="ghost-panel-header d-flex justify-content-between align-items-center">
        <span class="ghost-panel-title">🧪 Current Session Parameters</span>
        <button id="btnStopTop" class="btn-danger-ghost" onclick="stopSessionQuick()" disabled>Stop Session</button>
    </div>
    <div class="ghost-panel-body">
        <div class="monitor-grid" style="padding: 6px 0;">
            <div class="monitor-item">
                <div class="monitor-label">Start Time</div>
                <div class="monitor-value" id="paramStartTime">—</div>
            </div>
            <div class="monitor-item">
                <div class="monitor-label">End Time</div>
                <div class="monitor-value" id="paramEndTime">—</div>
            </div>
            <div class="monitor-item">
                <div class="monitor-label">Egg Type</div>
                <div class="monitor-value" id="paramEggType">—</div>
            </div>
            <div class="monitor-item">
                <div class="monitor-label">Egg Count</div>
                <div class="monitor-value" id="paramEggCount">—</div>
            </div>
            <div class="monitor-item">
                <div class="monitor-label">Target Temp</div>
                <div class="monitor-value" id="paramTargetTemp">—</div>
            </div>
            <div class="monitor-item">
                <div class="monitor-label">Min Temp</div>
                <div class="monitor-value" id="paramMinTemp">—</div>
            </div>
            <div class="monitor-item">
                <div class="monitor-label">Max Temp</div>
                <div class="monitor-value" id="paramMaxTemp">—</div>
            </div>
            <div class="monitor-item">
                <div class="monitor-label">Target Humidity</div>
                <div class="monitor-value" id="paramTargetHum">—</div>
            </div>
            <div class="monitor-item">
                <div class="monitor-label">Min Humidity</div>
                <div class="monitor-value" id="paramMinHum">—</div>
            </div>
            <div class="monitor-item">
                <div class="monitor-label">Max Humidity</div>
                <div class="monitor-value" id="paramMaxHum">—</div>
            </div>
            <div class="monitor-item">
                <div class="monitor-label">Turn Interval</div>
                <div class="monitor-value" id="paramTurnInterval">—</div>
            </div>
            <div class="monitor-item">
                <div class="monitor-label">Swing Duration</div>
                <div class="monitor-value" id="paramSwingDuration">—</div>
            </div>
            <div class="monitor-item">
                <div class="monitor-label">Next Swing In</div>
                <div class="monitor-value" id="paramNextSwing">—</div>
            </div>
            <div class="monitor-item">
                <div class="monitor-label">Last Swing</div>
                <div class="monitor-value" id="paramLastSwing">—</div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MONITORING
     ══════════════════════════════════════════════════════════ -->
<style>
.monitor-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px 32px;
    padding: 20px 0;
}

@media(max-width:1400px) {
    .monitor-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 20px 24px;
    }
}

@media(max-width:1000px) {
    .monitor-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px 20px;
    }
}

@media(max-width:768px) {
    .monitor-grid {
        grid-template-columns: 1fr;
        gap: 14px 0;
    }
}

.monitor-item {
    display: flex;
    flex-direction: column;
}

.monitor-label {
    font-size: .75rem;
    color: var(--ghost-muted);
    text-transform: uppercase;
    letter-spacing: .08em;
    margin-bottom: 6px;
    font-weight: 600;
}

.monitor-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: white;
    display: flex;
    align-items: center;
    gap: 6px;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .03em;
    background: rgba(34, 197, 94, .12);
    color: #22c55e;
    border: 1px solid rgba(34, 197, 94, .28);
}

.status-pill.offline {
    background: rgba(239, 68, 68, .12);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, .28);
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
    background: #64748b;
}

.status-dot.on {
    background: #22c55e;
    box-shadow: 0 0 0 0 rgba(34, 197, 94, .45);
    animation: ghostPulse 1.8s infinite;
}

@keyframes ghostPulse {
    0% {
        box-shadow: 0 0 0 0 rgba(34, 197, 94, .45);
    }

    70% {
        box-shadow: 0 0 0 10px rgba(34, 197, 94, 0);
    }

    100% {
        box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
    }
}

.control-buttons {
    display: flex;
    gap: 10px;
    margin-top: 14px;
}

.control-buttons button {
    flex: 1;
    padding: 10px;
    border: 1px solid var(--ghost-border);
    background: rgba(255, 255, 255, .03);
    color: white;
    border-radius: 8px;
    font-size: .85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .3s ease;
}

.control-buttons button.btn-success-ghost {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    border-color: transparent;
    color: white;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), inset 0 -1px 0 rgba(0, 0, 0, 0.2);
}

.control-buttons button.btn-ghost {
    background: var(--ghost-gradient);
    border-color: transparent;
    color: white;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), inset 0 -1px 0 rgba(0, 0, 0, 0.2);
}

.control-buttons button.btn-danger-ghost {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.25);
    color: #f87171;
}

.control-buttons button.btn-outline-ghost {
    background: transparent;
    border-color: var(--ghost-border);
    color: var(--ghost-muted);
}

.control-buttons button:hover:not(:disabled) {
    background: var(--ghost-blue);
    border-color: var(--ghost-blue);
    color: white;
    transform: translateY(-2px);
}

.control-buttons button:disabled {
    opacity: .35;
    cursor: not-allowed;
    background: rgba(255, 255, 255, .01);
    border-color: rgba(255, 255, 255, .04);
    color: var(--ghost-muted);
}
</style>
<div class="ghost-panel mb-4">
    <div class="ghost-panel-header d-flex justify-content-between align-items-center">
        <span class="ghost-panel-title">🔧 Monitoring</span>
        <div style="display:flex;align-items:center;gap:8px;">
            <span id="hwOnlineDot"
                style="width:9px;height:9px;border-radius:50%;background:var(--ghost-muted);display:inline-block;transition:background .4s;"></span>
            <span id="hwOnlineLabel" style="font-size:0.75rem;color:var(--ghost-muted);">Offline</span>
        </div>
    </div>
    <div class="ghost-panel-body">
        <div class="row g-3 align-items-start">
            <div class="col-md-3">
                <label class="form-label-ghost">My Incubator</label>
                <select class="form-select-ghost" id="qc_incubator_id" onchange="onIncubatorChange()">
                    <?php foreach($userIncubators as $inc): ?>
                    <option value="<?= $inc['id'] ?>" data-target="<?= $inc['target_temp']    ?? 37.5 ?>"
                        data-min="<?=    $inc['min_temp']        ?? 37.0 ?>"
                        data-max="<?=    $inc['max_temp']        ?? 38.0 ?>"
                        data-th="<?=     $inc['target_humidity'] ?? 55.0 ?>"
                        data-thmin="<?=  $inc['min_humidity']    ?? 50.0 ?>"
                        data-thmax="<?=  $inc['max_humidity']    ?? 60.0 ?>"
                        data-cap="<?=    $inc['capacity']        ?? 0    ?>"
                        data-loc="<?=    htmlspecialchars($inc['location'] ?? '') ?>"
                        data-interval="<?= $inc['turning_interval'] ?? 8 ?>">
                        <?= htmlspecialchars($inc['name']) ?>
                    </option>
                    <?php endforeach; ?>
                    <?php if(empty($userIncubators)): ?>
                    <option value="">No active incubators</option>
                    <?php endif; ?>
                </select>

                <div
                    style="margin-top:14px;background:rgba(255,255,255,.03);border:1px solid var(--ghost-border);border-radius:12px;padding:12px 14px;">
                    <div
                        style="font-size:.72rem;color:var(--ghost-muted);letter-spacing:.05em;text-transform:uppercase;margin-bottom:8px;">
                        Device Status</div>
                    <div id="deviceStatusBadge" class="status-pill offline"><span id="deviceStatusDot"
                            class="status-dot off"></span><span id="deviceStatusText">OFFLINE</span></div>
                    <div style="font-size:.72rem;color:var(--ghost-muted);margin-top:10px;line-height:1.5;">Monitoring
                        is view only. Hardware decisions are driven automatically by the controller and downloaded
                        parameters.</div>
                </div>

                <div class="control-buttons">
                    <button id="btnStart" class="btn-success-ghost" onclick="startSessionQuick()"
                        title="Start incubation session">
                        <i class="fas fa-play"></i> Start
                    </button>
                    <button id="btnStop" class="btn-danger-ghost" onclick="stopSessionQuick()" disabled
                        title="Stop incubation session">
                        <i class="fas fa-stop"></i> Stop
                    </button>
                    <button id="btnSetParams" class="btn-ghost" onclick="openIncubateModal()" disabled
                        title="Set incubation parameters">
                        <i class="fas fa-sliders-h"></i> Set Params
                    </button>
                </div>
            </div>

            <div class="col-md-9">
                <div class="monitor-grid">
                    <div class="monitor-item">
                        <div class="monitor-label">Live Temperature</div>
                        <div class="monitor-value" id="monitorTemp">—</div>
                    </div>
                    <div class="monitor-item">
                        <div class="monitor-label">Live Humidity</div>
                        <div class="monitor-value" id="monitorHumidity">—</div>
                    </div>
                    <div class="monitor-item">
                        <div class="monitor-label">Heater 1 Status</div>
                        <div class="monitor-value"><span id="heater1Dot" class="status-dot"></span><span
                                id="monitorHeater1">—</span></div>
                    </div>
                    <div class="monitor-item">
                        <div class="monitor-label">Heater 2 Status</div>
                        <div class="monitor-value"><span id="heater2Dot" class="status-dot"></span><span
                                id="monitorHeater2">—</span></div>
                    </div>
                    <div class="monitor-item">
                        <div class="monitor-label">Heater Fan Status</div>
                        <div class="monitor-value"><span id="heaterFanDot" class="status-dot"></span><span
                                id="monitorHeaterFan">—</span></div>
                    </div>
                    <div class="monitor-item">
                        <div class="monitor-label">Exhaust Fan Status</div>
                        <div class="monitor-value"><span id="exhaustDot" class="status-dot"></span><span
                                id="monitorExhaust">—</span></div>
                    </div>
                    <div class="monitor-item">
                        <div class="monitor-label">Swing Motor Status</div>
                        <div class="monitor-value"><span id="swingDot" class="status-dot"></span><span
                                id="monitorSwing">—</span></div>
                    </div>
                    <div class="monitor-item">
                        <div class="monitor-label">Current Incubation Day</div>
                        <div class="monitor-value" id="monitorIncubationDay">—</div>
                    </div>
                    <div class="monitor-item">
                        <div class="monitor-label">Active Session Name</div>
                        <div class="monitor-value" id="monitorSessionName">—</div>
                    </div>
                    <div class="monitor-item">
                        <div class="monitor-label">Current Running Mode</div>
                        <div class="monitor-value" id="monitorMode">—</div>
                    </div>
                    <div class="monitor-item">
                        <div class="monitor-label">Last Server Update</div>
                        <div class="monitor-value" id="monitorLastSync">—</div>
                    </div>
                    <div class="monitor-item">
                        <div class="monitor-label">Session Status</div>
                        <div class="monitor-value" id="monitorSessionStatus">—</div>
                    </div>
                    <div class="monitor-item">
                        <div class="monitor-label">Session Params ID</div>
                        <div class="monitor-value" id="monitorParamsId">—</div>
                    </div>
                    <div class="monitor-item">
                        <div class="monitor-label">Session Ends At</div>
                        <div class="monitor-value" id="monitorSessionEnds" style="font-size:.9rem;">—</div>
                    </div>
                </div>
            </div>
        </div>
        <div id="hwFeedback" style="display:none;margin-top:12px;padding:10px 16px;border-radius:8px;font-size:.83rem;">
        </div>
    </div>
</div>

<!-- ── CHART + SCHEDULE ─────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="ghost-panel">
            <div class="ghost-panel-header"><span class="ghost-panel-title">🌡️ Temperature Trend</span></div>
            <div class="ghost-panel-body"><canvas id="tempChart" height="130"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="ghost-panel h-100">
            <div class="ghost-panel-header"><span class="ghost-panel-title">📅 Upcoming Tasks</span></div>
            <div class="ghost-panel-body p-0">
                <?php if(empty($schedules)): ?>
                <div class="p-4 text-center" style="color:var(--ghost-muted);font-size:.88rem;">No upcoming tasks</div>
                <?php else: foreach($schedules as $s): ?>
                <div
                    style="padding:11px 18px;border-bottom:1px solid rgba(255,255,255,.04);display:flex;align-items:center;gap:10px;">
                    <div style="font-size:1.1rem;"><?= actionIcon($s['action_type']) ?></div>
                    <div style="flex:1;min-width:0;">
                        <div
                            style="font-size:.83rem;font-weight:600;color:white;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= htmlspecialchars($s['title']) ?></div>
                        <div style="font-size:.72rem;color:var(--ghost-muted);">
                            <?= date('M j',strtotime($s['scheduled_date'])) ?> · <?= substr($s['scheduled_time'],0,5) ?>
                        </div>
                    </div>
                    <span class="badge-<?= $s['status'] ?>"><?= $s['status'] ?></span>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── MY BATCHES ──────────────────────────────────────────── -->
<div class="ghost-panel">
    <div class="ghost-panel-header d-flex justify-content-between align-items-center">
        <span class="ghost-panel-title">📦 My Batches</span>
        <a href="batches.php" style="font-size:.8rem;color:var(--ghost-blue);text-decoration:none;">View all →</a>
    </div>
    <div class="ghost-panel-body p-0">
        <table class="ghost-table">
            <thead>
                <tr>
                    <th>Batch</th>
                    <th>Incubator</th>
                    <th>Eggs</th>
                    <th>Start Date</th>
                    <th>Start Time</th>
                    <th>Expected Hatch</th>
                    <th>Status</th>
                    <th> Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($batches as $b):
        $days = max(0, round((strtotime($b['expected_hatch_date']) - time()) / 86400));
        $statusTime = null;
        if ($b['status'] === 'completed') { $statusTime = $b['completed_at'] ?? null; }
        if ($b['status'] === 'terminated') { $statusTime = $b['terminated_at'] ?? null; }
        ?>
                <tr>
                    <td>
                        <div style="font-weight:600;color:white;"><?= htmlspecialchars($b['batch_name']) ?></div>
                        <div style="font-size:.75rem;color:var(--ghost-muted);"><?= $b['egg_type'] ?></div>
                    </td>
                    <td style="font-size:.85rem;color:var(--ghost-muted);"><?= htmlspecialchars($b['incubator_name']) ?>
                    </td>
                    <td style="font-weight:600;"><?= $b['egg_count'] ?></td>
                    <td style="font-size:.82rem;color:var(--ghost-muted);">
                        <?= date('M j, Y',strtotime($b['start_date'])) ?></td>
                    <td style="font-size:.82rem;color:var(--ghost-muted);">
                        <?= !empty($b['session_start_time']) ? date('H:i', strtotime($b['session_start_time'])) : '—' ?>
                    </td>
                    <td>
                        <div style="font-size:.85rem;"><?= date('M j, Y',strtotime($b['expected_hatch_date'])) ?></div>
                        <div style="font-size:.72rem;color:<?= $days<=3?'#f87171':'var(--ghost-muted)' ?>;">
                            <?= $days > 0 ? $days.' days left' : 'Today!' ?>
                        </div>
                    </td>
                    <td><span class="badge-<?= $b['status'] ?>"><?= $b['status'] ?></span></td>
                    <td style="font-size:.82rem;color:var(--ghost-muted);">
                        <?= $statusTime ? date('M j, Y H:i', strtotime($statusTime)) : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
    MODAL: SET PARAMETERS  (editable — same as admin)
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade modal-ghost" id="incubateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width:980px;">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">⚙️ Set Parameters</h5>
                    <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:2px;">Review the incubation values
                        before starting the session</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:24px;">
                <div class="row g-4 align-items-start">
                    <div class="col-lg-7">
                        <!-- Incubator info bar -->
                        <div id="inc_info_bar"
                            style="background:rgba(245,166,35,.06);border:1px solid rgba(245,166,35,.2);border-radius:10px;padding:12px 16px;margin-bottom:20px;display:flex;gap:20px;flex-wrap:wrap;">
                            <div>
                                <div
                                    style="font-size:.7rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.06em;">
                                    Incubator</div>
                                <div style="font-weight:700;color:white;font-size:.9rem;" id="inc_info_name">—</div>
                            </div>
                            <div>
                                <div
                                    style="font-size:.7rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.06em;">
                                    Capacity</div>
                                <div style="font-weight:700;color:var(--ghost-amber);font-size:.9rem;"
                                    id="inc_info_cap">—</div>
                            </div>
                            <div>
                                <div
                                    style="font-size:.7rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.06em;">
                                    Location</div>
                                <div style="font-weight:700;color:white;font-size:.9rem;" id="inc_info_loc">—</div>
                            </div>
                        </div>

                        <!-- Session duration -->
                        <div class="mb-3">
                            <label class="form-label-ghost">⏳ Session Duration</label>
                            <div class="row g-2">
                                <div class="col-3">
                                    <label class="form-label-ghost">Day</label>
                                    <input type="number" min="0" step="1" class="form-control-ghost"
                                        id="inc_duration_days" value="21" style="text-align:center;">
                                </div>
                                <div class="col-3">
                                    <label class="form-label-ghost">HH</label>
                                    <input type="number" min="0" max="23" step="1" class="form-control-ghost"
                                        id="inc_duration_hours" value="0" style="text-align:center;">
                                </div>
                                <div class="col-3">
                                    <label class="form-label-ghost">MM</label>
                                    <input type="number" min="0" max="59" step="1" class="form-control-ghost"
                                        id="inc_duration_minutes" value="0" style="text-align:center;">
                                </div>
                                <div class="col-3">
                                    <label class="form-label-ghost">SS</label>
                                    <input type="number" min="0" max="59" step="1" class="form-control-ghost"
                                        id="inc_duration_seconds" value="0" style="text-align:center;">
                                </div>
                            </div>
                            <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">Use this instead of
                                egg type presets. Example: 21 days 00:00:00 for chicken.</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <!-- Temperature settings -->
                                <div
                                    style="background:rgba(245,166,35,.05);border:1px solid rgba(245,166,35,.15);border-radius:12px;padding:18px;margin-bottom:0;">
                                    <div
                                        style="font-size:.72rem;font-weight:700;color:var(--ghost-amber);letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">
                                        🌡️ Temperature (°C)</div>
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <label class="form-label-ghost">Target °C</label>
                                            <input type="number" step="0.1" class="form-control-ghost"
                                                id="inc_target_temp"
                                                style="font-size:1.4rem;font-family:'Bebas Neue',sans-serif;color:var(--ghost-amber);text-align:center;">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label-ghost">Min °C</label>
                                            <input type="number" step="0.1" class="form-control-ghost"
                                                id="inc_min_temp">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label-ghost">Max °C</label>
                                            <input type="number" step="0.1" class="form-control-ghost"
                                                id="inc_max_temp">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <!-- Humidity settings -->
                                <div
                                    style="background:rgba(59,130,246,.05);border:1px solid rgba(59,130,246,.15);border-radius:12px;padding:18px;margin-bottom:0;">
                                    <div
                                        style="font-size:.72rem;font-weight:700;color:#3b82f6;letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">
                                        💧 Humidity (%)</div>
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <label class="form-label-ghost">Target %</label>
                                            <input type="number" step="0.1" class="form-control-ghost"
                                                id="inc_target_hum"
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
                    </div>

                    <div class="col-lg-5">
                        <!-- Swing settings -->
                        <div
                            style="background:rgba(34,197,94,.05);border:1px solid rgba(34,197,94,.15);border-radius:12px;padding:18px;margin-bottom:14px;">
                            <div
                                style="font-size:.72rem;font-weight:700;color:#22c55e;letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">
                                🔄 Egg Swing Settings</div>

                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label-ghost">Run for (seconds)</label>
                                    <input type="number" min="5" max="300" step="1" class="form-control-ghost"
                                        id="sw_duration" value="30"
                                        style="font-size:1.4rem;font-family:'Bebas Neue',sans-serif;color:#22c55e;text-align:center;">
                                    <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">Recommended:
                                        30–60 seconds per cycle. Max 300 s.</div>
                                </div>

                                <div class="col-12 mt-2">
                                    <div style="font-size:.75rem;color:var(--ghost-muted);margin-bottom:8px;">Quick
                                        presets:</div>
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="document.getElementById('sw_duration').value=30">30 s</button>
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="document.getElementById('sw_duration').value=45">45 s</button>
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="document.getElementById('sw_duration').value=60">60 s</button>
                                        <button class="btn-outline-ghost" style="font-size:.78rem;padding:5px 14px;"
                                            onclick="document.getElementById('sw_duration').value=120">2 min</button>
                                    </div>
                                </div>

                                <div class="col-12 mt-2">
                                    <label class="form-label-ghost">Turn eggs every (hours)</label>
                                    <input type="number" min="0.01" max="24" step="0.01" class="form-control-ghost"
                                        id="sw_interval" value="8"
                                        style="font-size:1.4rem;font-family:'Bebas Neue',sans-serif;color:white;text-align:center;">
                                    <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">Recommended:
                                        3–8 hours. Common default: 4 hours.</div>
                                    <div id="sw_interval_preview"
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

                        <!-- Note -->
                        <div
                            style="font-size:.78rem;color:var(--ghost-muted);padding:10px 14px;background:rgba(255,255,255,.02);border-radius:8px;border:1px solid var(--ghost-border);">
                            <i class="fas fa-info-circle me-1" style="color:var(--ghost-amber);"></i>

                        </div>
                    </div>
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

<script>
console.log('[SCRIPT] Dashboard script loaded');
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
    deviceOnline: false,
    lastEggTurnAt: null,
    lastServerSync: null
};

window.addEventListener('load', function() {
    if (!window.Chart) return;
    Chart.defaults.color = '#64748b';
    Chart.defaults.font.family = 'Space Grotesk';
    tempChartInstance = new Chart(document.getElementById('tempChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($tempLogs,'lbl')) ?>,
            datasets: [{
                    label: 'Temperature (°C)',
                    yAxisID: 'y',
                    data: <?= json_encode(array_column($tempLogs,'temperature')) ?>,
                    borderColor: '#f5a623',
                    backgroundColor: 'rgba(245,166,35,.08)',
                    tension: .4,
                    pointRadius: 4,
                    pointBackgroundColor: '#f5a623',
                    borderWidth: 2,
                    fill: true
                },
                {
                    label: 'Humidity (%)',
                    yAxisID: 'y2',
                    data: <?= json_encode(array_column($tempLogs,'humidity')) ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,.06)',
                    tension: .4,
                    pointRadius: 4,
                    pointBackgroundColor: '#3b82f6',
                    borderWidth: 2,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    labels: {
                        boxWidth: 10,
                        padding: 16,
                        font: {
                            size: 11
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(255,255,255,.04)'
                    },
                    ticks: {
                        font: {
                            size: 10
                        }
                    }
                },
                y: {
                    grid: {
                        color: 'rgba(255,255,255,.04)'
                    },
                    min: 36,
                    max: 39,
                    ticks: {
                        font: {
                            size: 10
                        },
                        callback: v => v + '°C'
                    }
                },
                y2: {
                    position: 'right',
                    grid: {
                        display: false
                    },
                    min: 45,
                    max: 70,
                    ticks: {
                        font: {
                            size: 10
                        },
                        callback: v => v + '%'
                    }
                }
            }
        }
    });

    // Start auto-refresh of temperature chart every 30 seconds
    setInterval(refreshTemperatureChart, 30000);
});

// Function to refresh temperature chart with latest data
function refreshTemperatureChart() {
    const incId = document.getElementById('qc_incubator_id').value;
    console.log('[startSessionQuick] START - incubator_id:', incId);
    if (!incId || !tempChartInstance) return;

    $.get('../ajax/get_temperature_logs.php', {
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
//  HELPER
// ══════════════════════════════════════════════════════════
function selectedOpt() {
    const sel = document.getElementById('qc_incubator_id');
    return sel.options[sel.selectedIndex];
}

function getIncubationDraftKey(incId) {
    return incId ? `ghost:incubation-modal-draft:${incId}` : null;
}

function readIncubationDraft(incId) {
    const key = getIncubationDraftKey(incId);
    if (!key || !window.localStorage) return null;

    try {
        return JSON.parse(localStorage.getItem(key) || 'null');
    } catch (err) {
        return null;
    }
}

function writeIncubationDraft(incId, values) {
    const key = getIncubationDraftKey(incId);
    if (!key || !window.localStorage || !values) return;

    try {
        localStorage.setItem(key, JSON.stringify(values));
    } catch (err) {
        // Ignore storage quota or privacy-mode failures.
    }
}

function collectIncubationDraftValues() {
    const incId = document.getElementById('qc_incubator_id').value;
    if (!incId) return null;

    return {
        targetT: document.getElementById('inc_target_temp').value,
        minT: document.getElementById('inc_min_temp').value,
        maxT: document.getElementById('inc_max_temp').value,
        targetH: document.getElementById('inc_target_hum').value,
        minH: document.getElementById('inc_min_hum').value,
        maxH: document.getElementById('inc_max_hum').value,
        durationDays: document.getElementById('inc_duration_days').value,
        durationHours: document.getElementById('inc_duration_hours').value,
        durationMinutes: document.getElementById('inc_duration_minutes').value,
        durationSeconds: document.getElementById('inc_duration_seconds').value,
        swingDuration: document.getElementById('sw_duration').value,
        interval: document.getElementById('sw_interval').value
    };
}

function persistIncubationDraft() {
    const incId = document.getElementById('qc_incubator_id').value;
    if (!incId) return;
    writeIncubationDraft(incId, collectIncubationDraftValues());
}

function applyIncubationDraft(incId) {
    const draft = readIncubationDraft(incId);
    if (!draft) return;

    const assignments = {
        inc_target_temp: draft.targetT,
        inc_min_temp: draft.minT,
        inc_max_temp: draft.maxT,
        inc_target_hum: draft.targetH,
        inc_min_hum: draft.minH,
        inc_max_hum: draft.maxH,
        inc_duration_days: draft.durationDays,
        inc_duration_hours: draft.durationHours,
        inc_duration_minutes: draft.durationMinutes,
        inc_duration_seconds: draft.durationSeconds,
        sw_duration: draft.swingDuration,
        sw_interval: draft.interval
    };

    Object.entries(assignments).forEach(([id, value]) => {
        const el = document.getElementById(id);
        if (el && value !== null && value !== undefined && value !== '') {
            el.value = value;
        }
    });

    const days = parseInt(draft.durationDays, 10) || 0;
    const hours = parseInt(draft.durationHours, 10) || 0;
    const minutes = parseInt(draft.durationMinutes, 10) || 0;
    const seconds = parseInt(draft.durationSeconds, 10) || 0;
    window.lastSessionDurationSec = (days * 86400) + (hours * 3600) + (minutes * 60) + seconds;
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
        interval: settings.turning_interval ?? settings.interval
    };

    Object.entries(pairs).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            opt.dataset[key] = value;
        }
    });
}

function onIncubatorChange() {
    fetchLiveStatus();
}

function formatDuration(totalSeconds) {
    const days = Math.floor(totalSeconds / 86400);
    totalSeconds %= 86400;
    const hours = Math.floor(totalSeconds / 3600);
    totalSeconds %= 3600;
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${days}d ${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

function getIncubationFormValues() {
    const durationDays = parseInt(document.getElementById('inc_duration_days').value, 10) || 0;
    const durationHours = parseInt(document.getElementById('inc_duration_hours').value, 10) || 0;
    const durationMinutes = parseInt(document.getElementById('inc_duration_minutes').value, 10) || 0;
    const durationSeconds = parseInt(document.getElementById('inc_duration_seconds').value, 10) || 0;
    const sessionDurationSec = (durationDays * 86400) + (durationHours * 3600) + (durationMinutes * 60) +
        durationSeconds;
    return {
        incId: document.getElementById('qc_incubator_id').value,
        targetT: parseFloat(document.getElementById('inc_target_temp').value),
        minT: parseFloat(document.getElementById('inc_min_temp').value),
        maxT: parseFloat(document.getElementById('inc_max_temp').value),
        targetH: parseFloat(document.getElementById('inc_target_hum').value),
        minH: parseFloat(document.getElementById('inc_min_hum').value),
        maxH: parseFloat(document.getElementById('inc_max_hum').value),
        duration: parseInt(document.getElementById('sw_duration').value) || 30,
        interval: parseFloat(document.getElementById('sw_interval').value) || 8,
        sessionDurationSec: sessionDurationSec,
        sessionDurationDays: durationDays,
        sessionDurationHours: durationHours,
        sessionDurationMinutes: durationMinutes,
        sessionDurationSeconds: durationSeconds
    };
}

function setTurningIntervalPreset(hours) {
    const input = document.getElementById('sw_interval');
    if (input) {
        input.value = hours;
        updateIntervalPreview('sw_interval', 'sw_interval_preview');
    }
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

function saveIncubationSettings(startSession) {
    const values = getIncubationFormValues();
    if (!values.incId) return;
    if (isNaN(values.targetT) || isNaN(values.minT) || isNaN(values.maxT)) {
        alert('Please fill in all temperature fields.');
        return;
    }
    if (values.minT >= values.maxT) {
        alert('Min temp must be less than Max temp.');
        return;
    }
    if (isNaN(values.duration) || values.duration < 5 || values.duration > 300) {
        alert('Swing duration must be between 5 and 300 seconds.');
        return;
    }
    if (!Number.isFinite(values.interval) || values.interval < 0.01) {
        alert('Turning interval too short.');
        return;
    }
    if (values.interval > 24) {
        alert('Turning interval must be 24 hours or less.');
        return;
    }
    if (!values.sessionDurationSec || values.sessionDurationSec < 1) {
        alert('Session duration must be greater than zero.');
        return;
    }

    showLoader('Saving parameters…');
    $.post('../ajax/user_hw_settings.php', {
        action: 'update_settings',
        incubator_id: values.incId,
        target_temp: values.targetT,
        min_temp: values.minT,
        max_temp: values.maxT,
        target_humidity: values.targetH,
        min_humidity: values.minH,
        max_humidity: values.maxH,
        turning_interval: values.interval
    }, function(r) {
        if (!r.success) {
            hideLoader();
            showFb('❌ Failed to save settings: ' + (r.message || ''), 'danger');
            return;
        }

        persistIncubationDraft();
        syncSelectedOptSettings(values);
        window.lastSessionDurationSec = values.sessionDurationSec;

        $.post('../ajax/hardware_api.php', {
            action: 'set_swing_duration',
            incubator_id: values.incId,
            duration_sec: values.duration,
            token: 'ghost_hw_secret_2024'
        }, function() {
            if (!startSession) {
                hideLoader();
                showFb('✅ Parameters saved.', 'success');
                fetchLiveStatus();
                return;
            }

            $.post('../ajax/hardware_api.php', {
                action: 'start_session',
                incubator_id: values.incId,
                session_duration_sec: values.sessionDurationSec,
                duration_days: values.sessionDurationDays,
                duration_hours: values.sessionDurationHours,
                duration_minutes: values.sessionDurationMinutes,
                duration_seconds: values.sessionDurationSeconds,
                token: 'ghost_hw_secret_2024'
            }, function(startRes) {
                if (!startRes.success) {
                    hideLoader();
                    showFb('❌ Could not start session: ' + (startRes.message || ''), 'danger');
                    return;
                }

                sendCmd('heater_on', function() {
                    hideLoader();
                    bootstrap.Modal.getInstance(document.getElementById(
                        'incubateModal')).hide();
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
    if (!opt || !opt.value) {
        showFb('No active incubator found.', 'warning');
        return;
    }

    // Populate info bar
    document.getElementById('inc_info_name').textContent = opt.text;
    document.getElementById('inc_info_cap').textContent = opt.dataset.cap ? opt.dataset.cap + ' eggs' : '—';
    document.getElementById('inc_info_loc').textContent = opt.dataset.loc || '—';

    // Populate editable fields from saved settings
    document.getElementById('inc_target_temp').value = opt.dataset.target || 37.5;
    document.getElementById('inc_min_temp').value = opt.dataset.min || 37.0;
    document.getElementById('inc_max_temp').value = opt.dataset.max || 38.0;
    document.getElementById('inc_target_hum').value = opt.dataset.th || 55.0;
    document.getElementById('inc_min_hum').value = opt.dataset.thmin || 50.0;
    document.getElementById('inc_max_hum').value = opt.dataset.thmax || 60.0;
    document.getElementById('inc_duration_days').value = 21;
    document.getElementById('inc_duration_hours').value = 0;
    document.getElementById('inc_duration_minutes').value = 0;
    document.getElementById('inc_duration_seconds').value = 0;
    document.getElementById('sw_duration').value = 30;
    document.getElementById('sw_interval').value = opt.dataset.interval || 8;
    updateIntervalPreview('sw_interval', 'sw_interval_preview');
    window.lastSessionDurationSec = (21 * 86400);

    applyIncubationDraft(opt.value);

    new bootstrap.Modal(document.getElementById('incubateModal')).show();
}

// Auto-adjust min/max to ±3 when target temperature/humidity inputs change
;
(function() {
    const tIn = document.getElementById('inc_target_temp');
    const hIn = document.getElementById('inc_target_hum');
    if (tIn) tIn.addEventListener('input', function() {
        const v = parseFloat(this.value);
        if (!isNaN(v)) {
            const min = (v - 3).toFixed(1);
            const max = (v + 3).toFixed(1);
            const elMin = document.getElementById('inc_min_temp');
            const elMax = document.getElementById('inc_max_temp');
            if (elMin) elMin.value = min;
            if (elMax) elMax.value = max;
            persistIncubationDraft();
        }
    });
    if (hIn) hIn.addEventListener('input', function() {
        const v = parseFloat(this.value);
        if (!isNaN(v)) {
            const min = (v - 3).toFixed(1);
            const max = (v + 3).toFixed(1);
            const elMin = document.getElementById('inc_min_hum');
            const elMax = document.getElementById('inc_max_hum');
            if (elMin) elMin.value = min;
            if (elMax) elMax.value = max;
            persistIncubationDraft();
        }
    });
    ['inc_min_temp', 'inc_max_temp', 'inc_min_hum', 'inc_max_hum', 'inc_duration_days', 'inc_duration_hours',
        'inc_duration_minutes', 'inc_duration_seconds', 'sw_duration', 'sw_interval'
    ].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', persistIncubationDraft);
            el.addEventListener('change', persistIncubationDraft);
        }
    });
})();

// ══════════════════════════════════════════════════════════
//  SEND COMMAND  (direct stop buttons use this too)
// ══════════════════════════════════════════════════════════
function getControlButtons() {
    return ['#btnStopAll'];
}

function sendCmd(cmd, callback) {
    const incId = document.getElementById('qc_incubator_id').value;
    if (!incId) {
        showFb('No active incubator found.', 'warning');
        return;
    }

    getControlButtons().forEach(b => $(b).prop('disabled', true));

    $.post('../ajax/hardware_api.php', {
        action: 'manual_command',
        command: cmd,
        incubator_id: incId,
        token: 'ghost_hw_secret_2024'
    }, function(res) {
        getControlButtons().forEach(b => $(b).prop('disabled', false));
        if (res.success) {
            if (typeof callback === 'function') {
                callback();
                return;
            }
            const ok = {
                heater_on: '✅ Heaters will turn ON within 5 seconds.',
                heater_off: '✅ Heaters turned OFF.',
                swing_on: '✅ Swing motors will start within 5 seconds.',
                swing_off: '✅ Swing motors stopped.'
            };
            showFb(ok[cmd] || '✅ Command sent.', 'success');
            setTimeout(fetchLiveStatus, 6000);
        } else {
            showFb('❌ ' + (res.message || 'Command failed. Is the device online?'), 'danger');
        }
    }, 'json').fail(function(xhr, textStatus, errorThrown) {
        getControlButtons().forEach(b => $(b).prop('disabled', false));
        const status = xhr && xhr.status ? xhr.status : '0';
        const msg = `❌ Could not reach server (status ${status}). ${textStatus || errorThrown || ''}`;
        showFb(msg, 'danger');
        if (textStatus === 'parsererror') {
            console.error('[AJAX] parsererror response:', xhr.responseText);
            const ct = xhr.getResponseHeader('Content-Type') || '';
            const preview = (xhr.responseText || '').substring(0, 2000);
            showSessionModal('Server Error', msg + '\nContent-Type: ' + ct + '\nResponse preview: ' + preview);
        } else {
            showSessionModal('Server Error', msg);
        }
    });
}

function showFb(msg, type) {
    const el = document.getElementById('hwFeedback');
    const c = {
        success: {
            bg: 'rgba(34,197,94,.12)',
            bd: 'rgba(34,197,94,.3)',
            cl: '#22c55e'
        },
        danger: {
            bg: 'rgba(239,68,68,.12)',
            bd: 'rgba(239,68,68,.3)',
            cl: '#ef4444'
        },
        warning: {
            bg: 'rgba(245,166,35,.12)',
            bd: 'rgba(245,166,35,.3)',
            cl: '#f5a623'
        },
        info: {
            bg: 'rgba(59,130,246,.10)',
            bd: 'rgba(59,130,246,.25)',
            cl: '#3b82f6'
        }
    } [type] || {};
    el.style.cssText =
        `display:block;background:${c.bg};border:1px solid ${c.bd};color:${c.cl};margin-top:12px;padding:10px 16px;border-radius:8px;font-size:.83rem;`;
    el.textContent = msg;
}

function formatDisplayTime(value) {
    if (!value) return '—';
    return String(value).replace('T', ' ').replace('Z', '');
}

function parseServerDate(value) {
    if (!value) return null;
    const raw = String(value).trim();
    if (!raw) return null;

    const normalized = raw
        .replace(' ', 'T')
        .replace(/\.\d+$/, '');
    const parsed = new Date(normalized);
    if (!isNaN(parsed.getTime())) return parsed;

    const dmy = raw.match(/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$/);
    if (!dmy) return null;

    const day = parseInt(dmy[1], 10);
    const month = parseInt(dmy[2], 10) - 1;
    const year = parseInt(dmy[3], 10);
    const hour = parseInt(dmy[4] || '0', 10);
    const minute = parseInt(dmy[5] || '0', 10);
    const second = parseInt(dmy[6] || '0', 10);
    const fallback = new Date(year, month, day, hour, minute, second);
    return isNaN(fallback.getTime()) ? null : fallback;
}

function formatReadableDate(value) {
    if (!value) return '—';
    const d = parseServerDate(value);
    if (!d) return formatDisplayTime(value);
    return d.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function escapeHtml(unsafe) {
    if (!unsafe && unsafe !== 0) return '';
    return String(unsafe).replace(/[&<>"'`]/g, function(s) {
        return ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
            '`': '&#96;'
        })[s];
    });
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

function formatCountdownShort(totalSeconds) {
    if (totalSeconds <= 0 || !isFinite(totalSeconds)) return '—';
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    if (hours > 0) return `${hours}h ${minutes}m`;
    if (minutes > 0) return `${minutes}m ${seconds}s`;
    return `${seconds}s`;
}

function formatRelativeSeconds(secondsAgo) {
    if (secondsAgo < 0 || !isFinite(secondsAgo)) return '—';
    if (secondsAgo < 60) return `${secondsAgo}s ago`;
    const minutes = Math.floor(secondsAgo / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    return `${days}d ago`;
}

// ══════════════════════════════════════════════════════════
//  LIVE STATUS POLLING
// ══════════════════════════════════════════════════════════
function fetchLiveStatus() {
    const incId = document.getElementById('qc_incubator_id').value;
    if (!incId) return;
    $.post('../ajax/hardware_api.php', {
        action: 'get_live_status',
        incubator_id: incId,
        token: 'ghost_hw_secret_2024'
    }, function(res) {
        if (!res.success) {
            console.warn('[fetchLiveStatus] API error:', res.message || 'unknown');
            updateControlButtons();
            return;
        }
        const dot = document.getElementById('hwOnlineDot');
        const lbl = document.getElementById('hwOnlineLabel');
        const isOnline = res.online;

        // Session parameter card values (show even if offline)
        setText('paramStartTime', res.session_started_at ? formatReadableDate(res.session_started_at) : '—');
        setText('paramEndTime', res.session_ends_at ? formatReadableDate(res.session_ends_at) : '—');
        setText('paramEggType', res.egg_type || '—');
        setText('paramEggCount', (res.egg_count || res.egg_count === 0) ? String(res.egg_count) : '—');
        setText('paramTargetTemp', res.target_temp ? `${parseFloat(res.target_temp).toFixed(2)}°C` : '—');
        setText('paramMinTemp', res.min_temp ? `${parseFloat(res.min_temp).toFixed(2)}°C` : '—');
        setText('paramMaxTemp', res.max_temp ? `${parseFloat(res.max_temp).toFixed(2)}°C` : '—');
        setText('paramTargetHum', res.target_humidity ? `${parseFloat(res.target_humidity).toFixed(2)}%` : '—');
        setText('paramMinHum', res.min_humidity ? `${parseFloat(res.min_humidity).toFixed(2)}%` : '—');
        setText('paramMaxHum', res.max_humidity ? `${parseFloat(res.max_humidity).toFixed(2)}%` : '—');
        setText('paramTurnInterval', res.turning_interval ? `${parseFloat(res.turning_interval).toFixed(2)}h` :
            '—');
        setText('paramSwingDuration', res.swing_duration_sec ? `${res.swing_duration_sec}s` : '—');

        syncSelectedOptSettings(res);

        const lastTurn = parseServerDate(res.last_egg_turn_at);
        const startedAt = parseServerDate(res.session_started_at);
        const intervalHours = parseFloat(res.turning_interval);
        if (lastTurn && !isNaN(lastTurn.getTime())) {
            const secondsAgo = Math.max(0, Math.floor((Date.now() - lastTurn.getTime()) / 1000));
            setText('paramLastSwing', formatRelativeSeconds(secondsAgo));
            if (intervalHours > 0) {
                const intervalMs = intervalHours * 3600 * 1000;
                const elapsed = Date.now() - lastTurn.getTime();
                const steps = Math.max(1, Math.ceil(elapsed / intervalMs));
                const nextMs = lastTurn.getTime() + steps * intervalMs;
                const nextSeconds = Math.max(0, Math.floor((nextMs - Date.now()) / 1000));
                setText('paramNextSwing', formatCountdownShort(nextSeconds));
            } else {
                setText('paramNextSwing', '—');
            }
        } else if (startedAt && !isNaN(startedAt.getTime()) && intervalHours > 0) {
            const intervalMs = intervalHours * 3600 * 1000;
            const elapsed = Date.now() - startedAt.getTime();
            const steps = Math.max(1, Math.ceil(elapsed / intervalMs));
            const nextMs = startedAt.getTime() + steps * intervalMs;
            const nextSeconds = Math.max(0, Math.floor((nextMs - Date.now()) / 1000));
            setText('paramLastSwing', 'Not yet');
            setText('paramNextSwing', formatCountdownShort(nextSeconds));
        } else {
            setText('paramLastSwing', '—');
            setText('paramNextSwing', '—');
        }

        const lockdownDays = parseInt(res.turning_lockdown_days_remaining, 10);
        if (res.turning_lockdown_active) {
            setText('paramNextSwing', lockdownDays > 0 ? `Disabled (${lockdownDays} days left)` : 'Disabled');
        }

        if (isOnline) {
            dot.style.background = '#22c55e';
            lbl.style.color = '#22c55e';
            lbl.textContent = 'Online';
        } else {
            dot.style.background = '#ef4444';
            lbl.style.color = '#ef4444';
            lbl.textContent = 'Offline';
        }

        setStatusPill('deviceStatusBadge', 'deviceStatusDot', 'deviceStatusText', isOnline, 'ONLINE', 'OFFLINE',
            'online', 'offline');

        // Only display data if device is online
        if (isOnline) {
            setText('monitorTemp', res.current_temp ? parseFloat(res.current_temp).toFixed(1) + '°C' : '—');
            setText('monitorHumidity', res.current_humidity ? parseFloat(res.current_humidity).toFixed(1) +
                '%' : '—');
            setRelayState('heater1Dot', 'monitorHeater1', res.heater_1_status, 'ON', 'OFF');
            setRelayState('heater2Dot', 'monitorHeater2', res.heater_2_status, 'ON', 'OFF');
            setRelayState('heaterFanDot', 'monitorHeaterFan', res.heater_fan_status, 'ON', 'OFF');
            setRelayState('exhaustDot', 'monitorExhaust', res.exhaust_status, 'ON', 'OFF');
            setRelayState('swingDot', 'monitorSwing', res.swing_status, 'RUNNING', 'OFF');
            setText('monitorIncubationDay', res.incubation_day ? `Day ${res.incubation_day}` : '—');
            setText('monitorSessionName', res.active_session_name || '—');
            setText('monitorMode', res.current_mode || 'Idle');
            setText('monitorLastSync', formatDisplayTime(res.last_server_sync || res.last_seen));

            // Update session status and end time
            const modeLabel = (res.session_status === 'running') ? 'INCUBATING' : (res.current_mode || 'idle')
                .toUpperCase();
            const onlineLabel = res.online ? 'ONLINE' : 'OFFLINE';
            setText('monitorSessionStatus', `${onlineLabel} | ${modeLabel}`);
            setText('monitorParamsId', res.temp_settings_id ? `#${res.temp_settings_id}` : '—');
            setText('paramSwingDuration', res.swing_duration_sec ? `${res.swing_duration_sec}s` : '—');

            if (res.session_ends_at && res.session_status === 'running') {
                const endTime = parseServerDate(res.session_ends_at);
                if (endTime) {
                    const timeStr = endTime.toLocaleString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    setText('monitorSessionEnds', timeStr);
                } else {
                    setText('monitorSessionEnds', formatDisplayTime(res.session_ends_at));
                }
            } else {
                setText('monitorSessionEnds', '—');
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
            setText('monitorSessionStatus', '—');
            setText('monitorParamsId', '—');
            setText('monitorSessionEnds', '—');
            document.getElementById('heater1Dot').className = 'status-dot';
            document.getElementById('heater2Dot').className = 'status-dot';
            document.getElementById('heaterFanDot').className = 'status-dot';
            document.getElementById('exhaustDot').className = 'status-dot';
            document.getElementById('swingDot').className = 'status-dot';
        }

        liveSessionState.startedAt = res.session_started_at || null;
        liveSessionState.endsAt = res.session_ends_at || null;
        liveSessionState.status = res.session_status || 'idle';
        liveSessionState.currentMode = res.current_mode || 'idle';
        liveSessionState.activeSessionName = res.active_session_name || null;
        liveSessionState.incubationDay = res.incubation_day || null;
        liveSessionState.runningOps = res.running_ops || 'idle';
        liveSessionState.deviceOnline = !!res.online;
        liveSessionState.lastEggTurnAt = res.last_egg_turn_at || null;
        liveSessionState.lastServerSync = res.last_server_sync || res.last_seen || null;
        updateSessionCountdown();
        updateControlButtons();
    }, 'json').fail(function(xhr, textStatus, errorThrown) {
        console.error('[fetchLiveStatus] AJAX failed:', textStatus, errorThrown, xhr.responseText);
        updateControlButtons();
    });
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

    const endTime = parseServerDate(liveSessionState.endsAt);
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
(function() {
    if (!document.getElementById('sessionStatusModal')) document.body.insertAdjacentHTML('beforeend',
        sessionModalHtml);
})();

function showSessionModal(title, message) {
    const t = document.getElementById('sessionStatusTitle');
    const b = document.getElementById('sessionStatusBody');
    if (t) t.textContent = title;
    if (b) b.textContent = message;
    const el = document.getElementById('sessionStatusModal');
    if (el) {
        // Hide any other open bootstrap modals first to avoid aria-hidden/focus issues
        document.querySelectorAll('.modal.show').forEach(m => {
            try {
                const instance = bootstrap.Modal.getInstance(m);
                if (instance) instance.hide();
            } catch (e) {}
        });
        const modal = new bootstrap.Modal(el);
        modal.show();
        setTimeout(() => {
            const closeBtn = el.querySelector('.btn-outline-ghost');
            if (closeBtn) closeBtn.focus();
        }, 120);
    }
}

// Reusable confirm modal HTML + helper (user)
const confirmModalHtml = `
<div class="modal fade" id="confirmActionModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:720px;">
    <div class="modal-content modal-ghost">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="confirmActionTitle">Please confirm</h5>
          <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:2px;" id="confirmActionSubtitle">Confirm this action</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="confirmActionBody" style="padding:24px;white-space:pre-line;">Are you sure?</div>
      <div class="modal-footer">
        <button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-ghost" id="confirmActionOk">Confirm</button>
      </div>
    </div>
  </div>
</div>`;
(function() {
    if (!document.getElementById('confirmActionModal')) document.body.insertAdjacentHTML('beforeend',
        confirmModalHtml);
})();

const startConfirmModalHtml = `
<div class="modal fade" id="startConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:720px;">
        <div class="modal-content modal-ghost">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="startConfirmTitle">Start Session</h5>
                    <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:2px;" id="startConfirmSubtitle">Configure session details</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:24px;">
                <div id="startConfirmBody" style="white-space:pre-line;margin-bottom:16px;">Are you sure?</div>
                <div style="margin-bottom:16px;">
                    <label class="form-label-ghost" for="startEggCount">Egg Count</label>
                    <input type="number" id="startEggCount" class="form-control-ghost" placeholder="e.g. 50" min="1" />
                    <div id="startEggError" style="display:none;color:#f87171;font-size:.78rem;margin-top:6px;">Egg count is required.</div>
                </div>
                <div id="scheduleDatetimeSection" style="display:none;">
                    <label class="form-label-ghost" for="scheduledStartDateTime">Scheduled Start Time</label>
                    <input type="datetime-local" id="scheduledStartDateTime" class="form-control-ghost" />
                    <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:4px;">Select when you want the session to begin</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button>
                <button class="btn-success-ghost" id="startNowBtn">▶ Start Now</button>
                <button class="btn-ghost" id="startScheduleBtn">⏰ Schedule for Later</button>
            </div>
        </div>
    </div>
</div>`;
(function() {
    if (!document.getElementById('startConfirmModal')) document.body.insertAdjacentHTML('beforeend',
        startConfirmModalHtml);
})();

function showConfirm(title, message, onConfirm) {
    const el = document.getElementById('confirmActionModal');
    if (!el) return onConfirm();
    const t = document.getElementById('confirmActionTitle');
    const b = document.getElementById('confirmActionBody');
    const ok = document.getElementById('confirmActionOk');
    if (t) t.textContent = title;
    if (b) {
        b.style.whiteSpace = 'pre-line';
        b.textContent = message;
    }
    document.querySelectorAll('.modal.show').forEach(m => {
        try {
            const instance = bootstrap.Modal.getInstance(m);
            if (instance) instance.hide();
        } catch (e) {}
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

function showStartConfirm(message, defaultEggCount, onConfirm) {
    const el = document.getElementById('startConfirmModal');
    if (!el) return onConfirm(defaultEggCount || 0, null);
    const body = document.getElementById('startConfirmBody');
    const input = document.getElementById('startEggCount');
    const schedInput = document.getElementById('scheduledStartDateTime');
    const schedSection = document.getElementById('scheduleDatetimeSection');
    const subtitle = document.getElementById('startConfirmSubtitle');
    const err = document.getElementById('startEggError');
    const startNowBtn = document.getElementById('startNowBtn');
    const startScheduleBtn = document.getElementById('startScheduleBtn');

    let baseMessage = message;
    if (body) body.textContent = baseMessage;
    if (input) input.value = defaultEggCount ? String(defaultEggCount) : '';
    if (err) err.style.display = 'none';

    // Show clear instruction if egg count is not pre-filled
    if (!defaultEggCount && subtitle) {
        subtitle.textContent = '⚠️ Enter the number of eggs to continue';
        subtitle.style.color = 'var(--ghost-warning, #fbbf24)';
    }

    // Reset button states
    if (schedSection) schedSection.style.display = 'none';
    if (startNowBtn) startNowBtn.classList.remove('disabled');
    if (startScheduleBtn) startScheduleBtn.classList.remove('active');

    // Set default scheduled start time to NOW (current date/time)
    if (schedInput) {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hour = String(now.getHours()).padStart(2, '0');
        const minute = String(now.getMinutes()).padStart(2, '0');
        schedInput.value = `${year}-${month}-${day}T${hour}:${minute}`;
    }

    function syncMessage() {
        if (!body) return;
        const val = input ? parseInt(input.value, 10) : 0;
        const count = val && val > 0 ? val : (defaultEggCount || 0);
        body.textContent = baseMessage.replace(/Egg Count:\s*\d+\s*eggs/, `Egg Count: ${count} eggs`);
        if (err && val && val > 0) {
            err.style.display = 'none';
        }
    }
    if (input) {
        input.addEventListener('input', syncMessage);
        syncMessage();
    }

    document.querySelectorAll('.modal.show').forEach(m => {
        try {
            const instance = bootstrap.Modal.getInstance(m);
            if (instance) instance.hide();
        } catch (e) {}
    });
    const modal = new bootstrap.Modal(el);
    modal.show();
    if (input) input.focus();

    function cleanup() {
        startNowBtn.removeEventListener('click', startNowHandler);
        if (startScheduleBtn) startScheduleBtn.removeEventListener('click', goToSchedulePageHandler);
        if (input) input.removeEventListener('input', syncMessage);
    }

    function getEggCount() {
        const val = input ? parseInt(input.value, 10) : 0;
        if (!val || val < 1) {
            if (err) err.style.display = 'block';
            if (input) input.focus();
            return null;
        }
        if (err) err.style.display = 'none';
        return val;
    }

    function startNowHandler() {
        const eggCount = getEggCount();
        if (!eggCount) return;

        cleanup();
        modal.hide();
        // Start immediately - set datetime to now
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hour = String(now.getHours()).padStart(2, '0');
        const minute = String(now.getMinutes()).padStart(2, '0');
        const scheduledDateTime = `${year}-${month}-${day} ${hour}:${minute}`;
        if (typeof onConfirm === 'function') onConfirm(eggCount, scheduledDateTime);
    }

    startNowBtn.addEventListener('click', startNowHandler);

    function goToSchedulePageHandler() {
        const eggCount = getEggCount();
        if (!eggCount) return;

        cleanup();
        modal.hide();

        const url = new URL('schedule.php', window.location.href);
        if (window.currentIncubatorId) {
            url.searchParams.set('incubator_id', String(window.currentIncubatorId));
        }
        url.searchParams.set('egg_count', String(eggCount));
        window.location.href = url.toString();
    }

    startScheduleBtn.addEventListener('click', goToSchedulePageHandler);
}

// Quick session controls and simple pulse animation (user)
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
    if (_pulseTimer) {
        clearInterval(_pulseTimer);
        _pulseTimer = null;
    }
}

function updateControlButtons() {
    const status = liveSessionState.status || 'idle';
    const deviceOnline = !!liveSessionState.deviceOnline;
    const isSessionRunning = status === 'running';

    // New monitoring panel buttons
    const btnStart = document.getElementById('btnStart');
    const btnStop = document.getElementById('btnStop');
    const btnSetParams = document.getElementById('btnSetParams');
    const btnStopTop = document.getElementById('btnStopTop');
    const sessionParamsCard = document.getElementById('sessionParamsCard');

    if (btnStart) {
        // Enable Start button if no session is currently running
        // (device may be offline but we still allow trying to start)
        btnStart.disabled = isSessionRunning;
    }
    if (btnStop) {
        btnStop.disabled = !isSessionRunning;
    }
    if (btnSetParams) {
        btnSetParams.disabled = false;
    }
    if (btnStopTop) {
        btnStopTop.disabled = !isSessionRunning;
    }
    if (sessionParamsCard) {
        sessionParamsCard.style.display = isSessionRunning ? 'block' : 'none';
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
    if (!incId) {
        showFb('No incubator selected', 'warning');
        console.error('[startSessionQuick] ERROR: No incubator selected');
        return;
    }

    // Check if a session is already running
    if (liveSessionState.status === 'running') {
        showFb('❌ A session is already running on this incubator. Stop it first before starting a new one.', 'danger');
        console.error('[startSessionQuick] ERROR: Session already running');
        return;
    }

    const durationSec = parseInt(window.lastSessionDurationSec || (21 * 86400), 10) || (21 * 86400);
    console.log('[startSessionQuick] Duration (sec):', durationSec);

    function numOrFallback() {
        for (let i = 0; i < arguments.length; i++) {
            const parsed = parseFloat(arguments[i]);
            if (!isNaN(parsed)) return parsed;
        }
        return null;
    }

    function formatLocalDateTime(value) {
        if (!value) return '—';
        const d = value instanceof Date ? value : new Date(value);
        if (isNaN(d.getTime())) return '—';
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const hour = String(d.getHours()).padStart(2, '0');
        const minute = String(d.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day} ${hour}:${minute}`;
    }

    function buildConfirmMessage(source, eggCount) {
        const opt = selectedOpt();
        const dataset = opt && opt.dataset ? opt.dataset : {};

        const localTargetT = numOrFallback(dataset.target, 37.5);
        const localMinT = numOrFallback(dataset.min, 37.0);
        const localMaxT = numOrFallback(dataset.max, 38.0);
        const localTargetH = numOrFallback(dataset.th, 55.0);
        const localMinH = numOrFallback(dataset.thmin, 50.0);
        const localMaxH = numOrFallback(dataset.thmax, 60.0);
        const localInterval = parseInt(dataset.interval || '8', 10) || 8;

        const targetT = numOrFallback(dataset.target, source ? source.target_temp : null, localTargetT) ?? localTargetT;
        const minT = numOrFallback(dataset.min, source ? source.min_temp : null, localMinT) ?? localMinT;
        const maxT = numOrFallback(dataset.max, source ? source.max_temp : null, localMaxT) ?? localMaxT;

        // Conflict modal HTML: shows conflicting scheduled items and offers Adjust or Proceed
        const conflictModalHtml = `
            <div class="modal fade" id="conflictModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered" style="max-width:720px;">
                    <div class="modal-content modal-ghost">
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title">Schedule Conflict</h5>
                                <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:2px;">A scheduled task conflicts with this session</div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" style="padding:24px;">
                            <div id="conflictList" style="margin-bottom:12px;"></div>
                            <div style="font-size:.78rem;color:var(--ghost-muted);">Choose to adjust the session duration or proceed and cancel the conflicting schedules.</div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn-outline-ghost" data-bs-dismiss="modal">Adjust Duration</button>
                            <button class="btn-ghost" id="conflictProceedBtn">Proceed and Cancel Scheduled Tasks</button>
                        </div>
                    </div>
                </div>
            </div>`;
        (function() {
            if (!document.getElementById('conflictModal')) document.body.insertAdjacentHTML('beforeend',
                conflictModalHtml);
        })();
        const targetH = numOrFallback(dataset.th, source ? source.target_humidity : null, localTargetH) ?? localTargetH;
        const minH = numOrFallback(dataset.thmin, source ? source.min_humidity : null, localMinH) ?? localMinH;
        const maxH = numOrFallback(dataset.thmax, source ? source.max_humidity : null, localMaxH) ?? localMaxH;
        const interval = parseFloat(dataset.interval || (source && source.turning_interval ? source.turning_interval :
            localInterval)) || localInterval;
        const duration = parseInt(source && source.swing_duration_sec ? source.swing_duration_sec : 30, 10) || 30;

        const now = new Date();
        const startPreview = liveSessionState.startedAt ? formatDisplayTime(liveSessionState.startedAt) :
            formatLocalDateTime(now);
        const endPreview = liveSessionState.endsAt ? formatDisplayTime(liveSessionState.endsAt) : formatLocalDateTime(
            new Date(now.getTime() + (durationSec * 1000)));

        return (
            'Start incubation now with these settings?\n\n' +
            'Temp: ' + targetT.toFixed(2) + '°C (min ' + minT.toFixed(2) + ', max ' + maxT.toFixed(2) + ')\n' +
            'Humidity: ' + targetH.toFixed(2) + '% (min ' + minH.toFixed(2) + ', max ' + maxH.toFixed(2) + ')\n' +
            'Egg Count: ' + (eggCount || 0) + ' eggs\n' +
            'Session duration: ' + formatDuration(durationSec) + '\n' +
            'Swing duration: ' + duration + 's\n' +
            'Turn interval: ' + interval + 'h\n' +
            'Start time: ' + startPreview + '\n' +
            'End time: ' + endPreview + '\n\n' +
            'This will begin the session timer and turn on heaters.'
        );
    }

    function startNow(eggCount, scheduledDateTime) {
        const totalSec = parseInt(durationSec, 10) || 0;
        const durationDays = Math.floor(totalSec / 86400);
        const durationHours = Math.floor((totalSec % 86400) / 3600);
        const durationMinutes = Math.floor((totalSec % 3600) / 60);
        const durationSeconds = totalSec % 60;

        console.log('[startNow] Starting session with egg_count:', eggCount, 'incubator:', incId);

        showFb('Starting session…', 'info');
        $.post('../ajax/hardware_api.php', {
            action: 'start_session',
            incubator_id: incId,
            egg_count: eggCount,
            session_duration_sec: durationSec,
            duration_days: durationDays,
            duration_hours: durationHours,
            duration_minutes: durationMinutes,
            duration_seconds: durationSeconds,
            scheduled_start_datetime: '',
            token: 'ghost_hw_secret_2024'
        }, function(res) {
            console.log('[startNow] API response:', res);
            if (!res.success) {
                showFb('❌ Could not start session: ' + (res.message || ''), 'danger');
                return;
            }
            showFb('✅ Session started.', 'success');
            setTimeout(function() {
                fetchLiveStatus();
            }, 700);
        }, 'json').fail(function(xhr, textStatus, errorThrown) {
            console.error('[startNow] API call failed:', {
                textStatus,
                errorThrown,
                response: xhr.responseText
            });
            const status = xhr && xhr.status ? xhr.status : '0';
            const msg = `❌ Could not reach server (status ${status}). ${textStatus || errorThrown || ''}`;
            showFb(msg, 'danger');
            if (textStatus === 'parsererror') {
                console.error('[AJAX] parsererror response:', xhr.responseText);
                const ct = xhr.getResponseHeader('Content-Type') || '';
                const preview = (xhr.responseText || '').substring(0, 2000);
                showSessionModal('Server Error', msg + '\nContent-Type: ' + ct + '\nResponse preview: ' +
                    preview);
            } else {
                showSessionModal('Server Error', msg);
            }
        });
    }

    // Fetch settings and egg count
    console.log('[startSessionQuick] Making AJAX calls - get_settings, get_active_egg_count, check_conflicts');
    $.post('../ajax/hardware_api.php', {
        action: 'get_settings',
        incubator_id: incId,
        token: 'ghost_hw_secret_2024'
    }, function(res) {
        console.log('[startSessionQuick] get_settings response:', res);
        // Also fetch egg count from active batch
        $.post('../ajax/user_batches.php', {
            action: 'get_active_egg_count',
            incubator_id: incId
        }, function(batchRes) {
            const eggCount = (batchRes && batchRes.success) ? batchRes.egg_count : 0;
            // Before showing final confirmation, check for schedule conflicts for this session window
            const nowTs = Math.floor(Date.now() / 1000);
            const endTs = nowTs + durationSec;
            $.post('../ajax/user_schedules.php', {
                action: 'check_conflicts',
                incubator_id: incId,
                start_ts: nowTs,
                end_ts: endTs
            }, function(confRes) {
                if (!confRes || !confRes.success || !confRes.conflicts || confRes.conflicts
                    .length === 0) {
                    const msg = buildConfirmMessage((res && res.success) ? res : null,
                        eggCount);
                    showStartConfirm(msg, eggCount, startNow);
                    return;
                }
                // List conflicts and prompt user
                const conflicts = confRes.conflicts;
                const listEl = document.getElementById('conflictList');
                if (listEl) listEl.innerHTML = '';
                const scheduleIds = [];
                conflicts.forEach(c => {
                    if (!listEl) return;
                    const el = document.createElement('div');
                    el.style.padding = '8px 0';
                    el.style.borderBottom = '1px solid rgba(255,255,255,.04)';
                    const startD = new Date(c.start * 1000).toLocaleString();
                    const endD = new Date(c.end * 1000).toLocaleString();
                    el.innerHTML =
                        `<div style="font-weight:700;color:white;">${escapeHtml(c.title || (c.source==='batch'?'Batch':'Schedule'))}</div><div style="font-size:.78rem;color:var(--ghost-muted);">${escapeHtml(c.source)} · ${startD} → ${endD}</div>`;
                    listEl.appendChild(el);
                    if (c.source === 'schedule') scheduleIds.push(c.id);
                });
                const conflictModalEl = document.getElementById('conflictModal');
                const conflictModal = conflictModalEl ? new bootstrap.Modal(conflictModalEl) :
                    null;
                if (conflictModal) conflictModal.show();
                const proceedBtn = document.getElementById('conflictProceedBtn');
                const adjustBtn = document.querySelector('#conflictModal .btn-outline-ghost');
                if (adjustBtn) adjustBtn.onclick = function() {
                    if (conflictModal) conflictModal.hide();
                    openIncubateModal();
                };
                if (proceedBtn) {
                    proceedBtn.onclick = function() {
                        if (scheduleIds.length > 0) {
                            showFb('Cancelling conflicting scheduled tasks…', 'info');
                            $.post('../ajax/user_schedules.php', {
                                action: 'cancel_schedules',
                                schedule_ids: scheduleIds
                            }, function(cancelRes) {
                                if (conflictModal) conflictModal.hide();
                                if (!cancelRes || !cancelRes.success) {
                                    showFb('Could not cancel schedules: ' + (
                                        cancelRes && cancelRes.message ?
                                        cancelRes.message : ''), 'danger');
                                    return;
                                }
                                startNow(eggCount);
                            }, 'json').fail(function() {
                                if (conflictModal) conflictModal.hide();
                                showFb('Server error cancelling schedules',
                                    'danger');
                            });
                        } else {
                            if (conflictModal) conflictModal.hide();
                            startNow(eggCount);
                        }
                    };
                }
            }, 'json').fail(function(xhr, status, error) {
                console.error('[startSessionQuick] check_conflicts failed:', {
                    status,
                    error,
                    response: xhr.responseText
                });
                const msg = buildConfirmMessage((res && res.success) ? res : null, eggCount);
                showStartConfirm(msg, eggCount, startNow);
            });
        }, 'json').fail(function(xhr, status, error) {
            console.error('[startSessionQuick] get_active_egg_count failed:', {
                status,
                error,
                response: xhr.responseText
            });
            const msg = buildConfirmMessage((res && res.success) ? res : null, 0);
            showStartConfirm(msg, 0, startNow);
        });
    }, 'json').fail(function(xhr, status, error) {
        console.error('[startSessionQuick] get_settings failed:', {
            status,
            error,
            response: xhr.responseText
        });
        // On failure to fetch settings, continue as before
        showStartConfirm(buildConfirmMessage(null, 0), 0, startNow);
    });
}

function stopSessionQuick() {
    const incId = document.getElementById('qc_incubator_id').value;
    if (!incId) {
        showFb('No incubator selected', 'warning');
        return;
    }
    showConfirm('Terminate session',
        'Terminate the current incubation session? This will stop hardware and reset egg count to 0.',
        function() {
            showFb('Stopping session…', 'info');
            $.post('../ajax/hardware_api.php', {
                action: 'stop_session',
                incubator_id: incId,
                token: 'ghost_hw_secret_2024'
            }, function(res) {
                if (!res.success) {
                    showFb('❌ Could not stop: ' + (res.message || ''), 'danger');
                    return;
                }
                sendCmd('all_off', function() {
                    showFb('✅ Session stopped.', 'success');
                    fetchLiveStatus();
                });
            }, 'json').fail(function(xhr, textStatus, errorThrown) {
                const status = xhr && xhr.status ? xhr.status : '0';
                const msg =
                    `❌ Could not reach server (status ${status}). ${textStatus || errorThrown || ''}`;
                showFb(msg, 'danger');
                if (textStatus === 'parsererror') {
                    console.error('[AJAX] parsererror response:', xhr.responseText);
                    const ct = xhr.getResponseHeader('Content-Type') || '';
                    const preview = (xhr.responseText || '').substring(0, 2000);
                    showSessionModal('Server Error', msg + '\nContent-Type: ' + ct +
                        '\nResponse preview: ' + preview);
                } else {
                    showSessionModal('Server Error', msg);
                }
            });

            const intervalInput = document.getElementById('sw_interval');
            if (intervalInput) {
                intervalInput.addEventListener('input', function() {
                    updateIntervalPreview('sw_interval', 'sw_interval_preview');
                });
                updateIntervalPreview('sw_interval', 'sw_interval_preview');
            }
        });
}
</script>

<?php require_once 'footer.php'; ?>