<?php
    $pageTitle = 'My Accounts';
    $pageSubtitle = 'Manage account details and relay testing';
    $activePage = 'accounts';
    require_once 'header.php';
    $pdo = getDB();
    $uid = $_SESSION['user_id'];
    $user = getCurrentUser();
    $incubators = $pdo->prepare("SELECT i.*, ts.target_temp, ts.target_humidity, ts.turning_interval
    FROM incubators i
    LEFT JOIN temperature_settings ts ON i.id = ts.incubator_id
    WHERE i.id IN (SELECT incubator_id FROM batches WHERE user_id = ?)
    ORDER BY i.name ASC");
    $incubators->execute([$uid]);
    $incubators = $incubators->fetchAll();
?>

<div class="row g-3 mb-4">
    <div class="col-lg-12">
        <div class="ghost-panel h-100">
            <div class="ghost-panel-header"><span class="ghost-panel-title">👤 Account Profile</span></div>
            <div class="ghost-panel-body">
                <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
                    <div
                        style="width:54px;height:54px;border-radius:14px;background:linear-gradient(135deg,#3b82f6,#6366f1);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.2rem;color:white;">
                        <?= strtoupper(substr($user['full_name'],0,1)) ?> </div>
                    <div>
                        <div style="font-size:1.1rem;font-weight:700;color:white;">
                            <?= htmlspecialchars($user['full_name']) ?></div>
                        <div style="font-size:.8rem;color:var(--ghost-muted);">Farm User</div>
                    </div>
                </div>
                <div class="mb-2"><span
                        style="color:var(--ghost-muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.1em;">Email</span>
                    <div style="font-weight:600;color:white;"><?= htmlspecialchars($user['email']) ?></div>
                </div>
                <div class="mb-2"><span
                        style="color:var(--ghost-muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.1em;">Phone</span>
                    <div style="font-weight:600;color:white;"><?= htmlspecialchars($user['phone'] ?? '—') ?></div>
                </div>
                <?php $accountStatus = strtolower((string)($user['status'] ?? 'active')); ?>
                <div class="mb-2"><span
                        style="color:var(--ghost-muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.1em;">Account
                        Status</span>
                    <div id="accountStatusText"
                        style="font-weight:600;color:<?= $accountStatus === 'active' ? '#22c55e' : ($accountStatus === 'suspended' ? '#ef4444' : '#94a3b8') ?>;">
                        <?= htmlspecialchars(ucfirst($accountStatus)) ?></div>
                </div>
                <div><span
                        style="color:var(--ghost-muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.1em;">Joined</span>
                    <div style="font-weight:600;color:white;"><?= date('M j, Y', strtotime($user['created_at'])) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- <div class="ghost-panel mb-4">
  <div class="ghost-panel-header d-flex justify-content-between align-items-center">
    <span class="ghost-panel-title">🔌 Relay Testing</span>
    <div style="font-size:.78rem;color:var(--ghost-muted);">Buttons lock while a session is running</div>
  </div>
  <div class="ghost-panel-body">
    <?php if (empty($incubators)): ?>
      <div style="text-align:center;padding:40px;color:var(--ghost-muted);">No incubators assigned to your batches yet.</div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($incubators as $inc): ?>
          <div class="col-lg-6">
            <div class="ghost-panel h-100" style="margin:0;">
              <div class="ghost-panel-header d-flex justify-content-between align-items-center">
                <div>
                  <div class="ghost-panel-title"><?= htmlspecialchars($inc['name']) ?></div>
                </div>
                <span id="sessionBadge-<?= (int)$inc['id'] ?>" class="badge-idle">Loading…</span>
              </div>
              <div class="ghost-panel-body">
                <div class="row g-2 mb-3">
                  <div class="col-3"><div style="font-size:.72rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.08em;">Heater 1 &amp; 2</div><div id="relayState-heater-<?= (int)$inc['id'] ?>" style="font-weight:700;color:white;">—</div></div>
                  <div class="col-3"><div style="font-size:.72rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.08em;">Heater Fan</div><div id="relayState-fan-<?= (int)$inc['id'] ?>" style="font-weight:700;color:white;">—</div></div>
                  <div class="col-3"><div style="font-size:.72rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.08em;">Exhaust Fan</div><div id="relayState-exhaust-<?= (int)$inc['id'] ?>" style="font-weight:700;color:white;">—</div></div>
                  <div class="col-3"><div style="font-size:.72rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.08em;">Swing Motor</div><div id="relayState-swing-<?= (int)$inc['id'] ?>" style="font-weight:700;color:white;">—</div></div>
                </div>

                <div id="relayLockNote-<?= (int)$inc['id'] ?>" style="display:none;margin-bottom:12px;padding:10px 12px;border-radius:10px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.18);color:#fca5a5;font-size:.82rem;">
                  Ongoing session detected. Relay testing is locked until the session ends.
                </div>

                <div id="relayControls-<?= (int)$inc['id'] ?>" class="d-grid gap-2" style="display:none;">
                  <div class="d-flex flex-wrap gap-2">
                    <button class="btn-ghost btn-ghost-sm relay-btn relay-toggle" id="relayToggle-heater-<?= (int)$inc['id'] ?>" data-incubator="<?= (int)$inc['id'] ?>" data-relay="heater">Heater ON</button>
                    <button class="btn-ghost btn-ghost-sm relay-btn relay-toggle" id="relayToggle-heater_fan-<?= (int)$inc['id'] ?>" data-incubator="<?= (int)$inc['id'] ?>" data-relay="heater_fan">Heater Fan ON</button>
                    <button class="btn-ghost btn-ghost-sm relay-btn relay-toggle" id="relayToggle-exhaust-<?= (int)$inc['id'] ?>" data-incubator="<?= (int)$inc['id'] ?>" data-relay="exhaust">Exhaust ON</button>
                    <button class="btn-ghost btn-ghost-sm relay-btn relay-toggle" id="relayToggle-swing-<?= (int)$inc['id'] ?>" data-incubator="<?= (int)$inc['id'] ?>" data-relay="swing">Swing ON</button>
                  </div>
                  <div style="font-size:.75rem;color:var(--ghost-muted);">These are test controls for the assigned incubator only.</div>
                </div>
                <div id="relayIdleNote-<?= (int)$inc['id'] ?>" style="display:block;font-size:.78rem;color:var(--ghost-muted);">Relay testing is hidden while the incubator is idle.</div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div> -->

<script>
const relaySessionState = {};
const relayLiveState = {};

function setRelayButtonsDisabled(incubatorId, disabled) {
    const buttons = document.querySelectorAll(`.relay-btn[data-incubator="${incubatorId}"]`);
    buttons.forEach(btn => btn.disabled = disabled);
    const note = document.getElementById(`relayLockNote-${incubatorId}`);
    if (note) note.style.display = disabled ? 'block' : 'none';
}

function setRelayControlsVisible(incubatorId, visible) {
    const controls = document.getElementById(`relayControls-${incubatorId}`);
    const idleNote = document.getElementById(`relayIdleNote-${incubatorId}`);
    if (controls) controls.style.display = visible ? 'grid' : 'none';
    if (idleNote) idleNote.style.display = visible ? 'none' : 'block';
}

function setRelayStateLabel(incubatorId, relay, state) {
    const el = document.getElementById(`relayState-${relay}-${incubatorId}`);
    if (!el) return;
    const on = !!state;
    el.textContent = on ? 'ON' : 'OFF';
    el.style.color = on ? '#22c55e' : '#94a3b8';
}

function setRelayToggleLabel(incubatorId, relay, state) {
    const button = document.getElementById(`relayToggle-${relay}-${incubatorId}`);
    if (!button) return;
    const labelMap = {
        heater: 'Heater',
        heater_fan: 'Heater Fan',
        exhaust: 'Exhaust',
        swing: 'Swing'
    };
    const label = labelMap[relay] || relay;
    button.textContent = state ? `${label} OFF` : `${label} ON`;
    button.dataset.state = state ? '1' : '0';
    button.classList.toggle('btn-outline-ghost', !!state);
    button.classList.toggle('btn-ghost', !state);
}

function setRelayButtonsFromState(incubatorId, state) {
    relayLiveState[incubatorId] = state;
    setRelayStateLabel(incubatorId, 'heater', state.heater);
    setRelayStateLabel(incubatorId, 'fan', state.fan);
    setRelayStateLabel(incubatorId, 'exhaust', state.exhaust);
    setRelayStateLabel(incubatorId, 'swing', state.swing);
    setRelayToggleLabel(incubatorId, 'heater', state.heater);
    setRelayToggleLabel(incubatorId, 'heater_fan', state.fan);
    setRelayToggleLabel(incubatorId, 'exhaust', state.exhaust);
    setRelayToggleLabel(incubatorId, 'swing', state.swing);
}

function applyRelayCommandState(incubatorId, relay, state) {
    const liveState = relayLiveState[incubatorId] || {
        heater: false,
        fan: false,
        swing: false,
        exhaust: false
    };
    if (relay === 'heater') {
        liveState.heater = state;
        setRelayStateLabel(incubatorId, 'heater', state);
        setRelayToggleLabel(incubatorId, 'heater', state);
    } else if (relay === 'heater_fan') {
        liveState.fan = state;
        setRelayStateLabel(incubatorId, 'fan', state);
        setRelayToggleLabel(incubatorId, 'heater_fan', state);
    } else if (relay === 'exhaust') {
        liveState.exhaust = state;
        setRelayStateLabel(incubatorId, 'exhaust', state);
        setRelayToggleLabel(incubatorId, 'exhaust', state);
    } else if (relay === 'swing') {
        liveState.swing = state;
        setRelayStateLabel(incubatorId, 'swing', state);
        setRelayToggleLabel(incubatorId, 'swing', state);
    }
    relayLiveState[incubatorId] = liveState;
}

function refreshRelayCards() {
    document.querySelectorAll('.relay-btn').forEach(btn => btn.disabled = true);
    const incubatorIds = Array.from(new Set(Array.from(document.querySelectorAll('.relay-btn')).map(btn => btn.dataset
        .incubator)));
    incubatorIds.forEach(function(id) {
        $.post('../ajax/hardware_api.php', {
            action: 'get_live_status',
            incubator_id: id,
            token: 'ghost_hw_secret_2024'
        }, function(res) {
            if (!res || !res.success) {
                console.warn('get_live_status failed for', id, res);
                return;
            }
            const running = res.session_status === 'running';
            relaySessionState[id] = {
                sessionStatus: res.session_status || 'idle',
                running: running
            };
            const badge = document.getElementById(`sessionBadge-${id}`);
            if (badge) {
                badge.className = running ? 'badge-active' : 'badge-idle';
                badge.textContent = running ? 'ONGOING SESSION' : 'READY';
            }
            setRelayControlsVisible(id, running);
            setRelayButtonsFromState(id, {
                heater: !!(res.heater_on || res.heater_1_status || res.heater_2_status),
                fan: !!res.heater_fan_status,
                swing: !!res.swing_status,
                exhaust: !!res.exhaust_status
            });
            setRelayButtonsDisabled(id, running);
        }, 'json').fail(function(xhr, status, err) {
            console.error('refreshRelayCards AJAX error', id, status, err, xhr && xhr.responseText);
            const badge = document.getElementById(`sessionBadge-${id}`);
            if (badge) {
                badge.className = 'badge-idle';
                badge.textContent = 'OFFLINE';
            }
            setRelayControlsVisible(id, false);
            setRelayButtonsFromState(id, {
                heater: false,
                fan: false,
                swing: false,
                exhaust: false
            });
            setRelayButtonsDisabled(id, true);
            try {
                showToast('Unable to contact server for relay state', 'error');
            } catch (e) {}
        });
    });
}

function queueAllOffOnExit(incubatorId) {
    const relays = ['heater', 'heater_fan', 'exhaust', 'swing'];
    relays.forEach(function(relay) {
        const payload = new FormData();
        payload.append('action', 'test_mode_set_relay');
        payload.append('incubator_id', incubatorId);
        payload.append('relay', relay);
        payload.append('state', '0');
        payload.append('source_page', 'accounts');
        navigator.sendBeacon('../ajax/hardware_api.php', payload);
    });
}

function sendRelayTest(incubatorId, relay, state) {
    applyRelayCommandState(incubatorId, relay, state);
    $.post('../ajax/hardware_api.php', {
        action: 'test_mode_set_relay',
        incubator_id: incubatorId,
        relay: relay,
        state: state ? 1 : 0,
        source_page: 'accounts'
    }, function(res) {
        if (res && res.success) {
            showToast('Command sent: ' + relay.replace(/_/g, ' ') + ' ' + (state ? 'ON' : 'OFF'));
            setTimeout(refreshRelayCards, 1500);
        } else {
            const errMsg = (res && (res.error || res.message)) ? (res.error || res.message) : 'Command failed';
            showToast(errMsg, 'error');
            console.warn('test_mode_set_relay failed', res);
            setTimeout(refreshRelayCards, 500);
        }
    }, 'json').fail(function() {
        showToast('Server error.', 'error');
        setTimeout(refreshRelayCards, 500);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    refreshRelayCards();
    $(document).on('click', '.relay-btn', function() {
        if (this.disabled) return;
        const incubatorId = this.dataset.incubator;
        const relay = this.dataset.relay;
        const liveState = relayLiveState[incubatorId] || {
            heater: false,
            fan: false,
            swing: false,
            exhaust: false
        };
        let current = false;
        if (relay === 'heater') current = !!liveState.heater;
        else if (relay === 'heater_fan') current = !!liveState.fan;
        else if (relay === 'exhaust') current = !!liveState.exhaust;
        else if (relay === 'swing') current = !!liveState.swing;
        sendRelayTest(incubatorId, relay, !current);
    });
    setInterval(refreshRelayCards, 8000);
});

window.addEventListener('pagehide', function(event) {
    if (event.persisted) return;
    Object.keys(relaySessionState).forEach(function(id) {
        const state = relaySessionState[id];
        if (!state || state.running) return;
        queueAllOffOnExit(id);
    });
});
</script>

<?php require_once 'footer.php'; ?>