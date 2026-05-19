<?php
$pageTitle = 'Incubators';
$pageSubtitle = 'Manage all incubator units';
$activePage = 'incubators';
require_once 'header.php';
$pdo = getDB();
$incubators = $pdo->query(
  "SELECT i.*,
    (SELECT COUNT(*) FROM batches WHERE incubator_id=i.id AND status='incubating') as active_batches,
    (SELECT COUNT(*) FROM batches WHERE incubator_id=i.id) as total_batches,
    (SELECT COALESCE(SUM(egg_count),0) FROM batches WHERE incubator_id=i.id AND status='incubating') as eggs_in,
    ts.target_temp, ts.target_humidity, ts.min_temp, ts.max_temp, ts.turning_interval,
    hs.temperature as live_temp, hs.humidity as live_humidity,
    hs.heater_on, hs.swing_on,
    TIMESTAMPDIFF(SECOND, hs.last_seen, NOW()) as secs_ago
   FROM incubators i
   LEFT JOIN temperature_settings ts ON ts.incubator_id = i.id
   LEFT JOIN hardware_state hs ON hs.incubator_id = i.id
   ORDER BY i.name"
)->fetchAll();
$users = $pdo->query("SELECT id,full_name FROM users WHERE status='active'")->fetchAll();
?>

<div class="d-flex justify-content-end mb-4">
  <button class="btn-ghost" data-bs-toggle="modal" data-bs-target="#addIncubModal">
    <i class="fas fa-plus me-2"></i>Add Incubator
  </button>
</div>

<div class="row g-3 mb-4" id="incubatorCards">
<?php foreach($incubators as $inc):
  $online  = ($inc['secs_ago'] !== null && $inc['secs_ago'] < 120);
  $liveTemp = ($inc['live_temp']     !== null) ? number_format((float)$inc['live_temp'],1).'°C' : '—';
  $liveHum  = ($inc['live_humidity'] !== null) ? number_format((float)$inc['live_humidity'],1).'%' : '—';
  $usedPct  = $inc['capacity'] > 0 ? min(100, round($inc['eggs_in'] / $inc['capacity'] * 100)) : 0;
?>
  <div class="col-md-4">
    <div class="ghost-panel h-100">

      <!-- Header -->
      <div class="ghost-panel-header d-flex justify-content-between align-items-start">
        <div>
          <div class="ghost-panel-title"><?= htmlspecialchars($inc['name']) ?></div>
          <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:2px;">
            <?= htmlspecialchars($inc['model']) ?> &middot; <?= htmlspecialchars($inc['location']) ?>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
          <span class="badge-<?= $inc['status'] ?>"><?= $inc['status'] ?></span>
          <span style="display:flex;align-items:center;gap:4px;font-size:.7rem;color:<?= $online?'#22c55e':'var(--ghost-muted)' ?>;">
            <span style="width:7px;height:7px;border-radius:50%;background:<?= $online?'#22c55e':'#64748b' ?>;display:inline-block;"></span>
            <?= $online ? 'Online' : 'Offline' ?>
          </span>
        </div>
      </div>

      <div class="ghost-panel-body">

        <!-- Stats Row -->
        <div class="row g-2 text-center mb-3">
          <div class="col-4">
            <div style="font-family:'Bebas Neue',sans-serif;font-size:2rem;color:var(--ghost-amber);"><?= $inc['capacity'] ?></div>
            <div style="font-size:.7rem;color:var(--ghost-muted);">Capacity</div>
          </div>
          <div class="col-4">
            <div style="font-family:'Bebas Neue',sans-serif;font-size:2rem;color:var(--ghost-blue);"><?= $inc['active_batches'] ?></div>
            <div style="font-size:.7rem;color:var(--ghost-muted);">Active</div>
          </div>
          <div class="col-4">
            <div style="font-family:'Bebas Neue',sans-serif;font-size:2rem;color:white;"><?= $inc['total_batches'] ?></div>
            <div style="font-size:.7rem;color:var(--ghost-muted);">Total</div>
          </div>
        </div>

        <!-- Capacity Bar -->
        <div style="margin-bottom:12px;">
          <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--ghost-muted);margin-bottom:4px;">
            <span>Eggs in incubation</span>
            <span><?= $inc['eggs_in'] ?> / <?= $inc['capacity'] ?> (<?= $usedPct ?>%)</span>
          </div>
          <div style="height:5px;background:rgba(255,255,255,.07);border-radius:99px;overflow:hidden;">
            <div style="height:100%;width:<?= $usedPct ?>%;background:linear-gradient(90deg,var(--ghost-amber),#f87171);border-radius:99px;"></div>
          </div>
        </div>

        <!-- Live Sensor Boxes -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;">
          <div style="background:rgba(245,166,35,.07);border:1px solid rgba(245,166,35,.18);border-radius:9px;padding:9px 12px;text-align:center;">
            <div style="font-size:.66rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">🌡 Temperature</div>
            <div id="liveT-<?= $inc['id'] ?>" style="font-family:'Bebas Neue',sans-serif;font-size:1.55rem;color:var(--ghost-amber);line-height:1;"><?= $liveTemp ?></div>
            <div style="font-size:.65rem;color:var(--ghost-muted);margin-top:2px;">Target: <?= $inc['target_temp'] ? number_format($inc['target_temp'],1) : '—' ?>°C</div>
          </div>
          <div style="background:rgba(59,130,246,.07);border:1px solid rgba(59,130,246,.18);border-radius:9px;padding:9px 12px;text-align:center;">
            <div style="font-size:.66rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">💧 Humidity</div>
            <div id="liveH-<?= $inc['id'] ?>" style="font-family:'Bebas Neue',sans-serif;font-size:1.55rem;color:#3b82f6;line-height:1;"><?= $liveHum ?></div>
            <div style="font-size:.65rem;color:var(--ghost-muted);margin-top:2px;">Target: <?= $inc['target_humidity'] ? number_format($inc['target_humidity'],1) : '—' ?>%</div>
          </div>
        </div>

        <!-- Heater & Swing State Badges -->
        <div style="display:flex;gap:6px;justify-content:center;margin-bottom:12px;">
          <span id="htrBadge-<?= $inc['id'] ?>" style="font-size:.72rem;padding:3px 10px;border-radius:99px;font-weight:600;background:<?= $inc['heater_on']?'rgba(245,166,35,.18)':'rgba(255,255,255,.05)' ?>;color:<?= $inc['heater_on']?'#f5a623':'#64748b' ?>;border:1px solid <?= $inc['heater_on']?'rgba(245,166,35,.35)':'rgba(255,255,255,.08)' ?>;">
            🔥 Heater <?= $inc['heater_on'] ? 'ON' : 'OFF' ?>
          </span>
          <span id="swgBadge-<?= $inc['id'] ?>" style="font-size:.72rem;padding:3px 10px;border-radius:99px;font-weight:600;background:<?= $inc['swing_on']?'rgba(34,197,94,.15)':'rgba(255,255,255,.05)' ?>;color:<?= $inc['swing_on']?'#22c55e':'#64748b' ?>;border:1px solid <?= $inc['swing_on']?'rgba(34,197,94,.3)':'rgba(255,255,255,.08)' ?>;">
            🔄 Swing <?= $inc['swing_on'] ? 'Running' : 'OFF' ?>
          </span>
        </div>

        <!-- Feedback line per card -->
        <div id="fb-<?= $inc['id'] ?>" style="display:none;font-size:.72rem;padding:6px 10px;border-radius:7px;text-align:center;margin-bottom:8px;"></div>

        <!-- Hardware Buttons: Incubate Now / Start Now -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-bottom:10px;">
          <button class="btn-ghost" style="font-size:.75rem;padding:8px 4px;"
            onclick="sendHWCmd(<?= $inc['id'] ?>,'heater_on')">
            <i class="fas fa-fire me-1"></i> Incubate Now
          </button>
          <button class="btn-outline-ghost" style="font-size:.75rem;padding:8px 4px;"
            onclick="sendHWCmd(<?= $inc['id'] ?>,'heater_off')">
            <i class="fas fa-stop me-1"></i> Heater Off
          </button>
          <button style="background:linear-gradient(135deg,#22c55e,#16a34a);box-shadow:0 0 14px rgba(34,197,94,.2);color:white;border:none;border-radius:8px;font-size:.75rem;padding:8px 4px;cursor:pointer;font-weight:600;width:100%;"
            onclick="sendHWCmd(<?= $inc['id'] ?>,'swing_on')">
            <i class="fas fa-rotate me-1"></i> Start Now
          </button>
          <button class="btn-outline-ghost" style="font-size:.75rem;padding:8px 4px;"
            onclick="sendHWCmd(<?= $inc['id'] ?>,'swing_off')">
            <i class="fas fa-stop me-1"></i> Swing Off
          </button>
        </div>

        <!-- Edit / Delete -->
        <div class="d-flex gap-2">
          <button class="btn-edit-ghost flex-fill" style="font-size:.78rem;padding:8px;"
            onclick="editIncub(<?= htmlspecialchars(json_encode($inc)) ?>)">
            <i class="fas fa-pen me-1"></i>Edit
          </button>
          <button class="btn-danger-ghost flex-fill" style="font-size:.78rem;padding:8px;"
            onclick="deleteIncub(<?= $inc['id'] ?>)">
            <i class="fas fa-trash me-1"></i>Delete
          </button>
        </div>

      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<!-- ADD MODAL -->
<div class="modal fade modal-ghost" id="addIncubModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="fas fa-egg me-2" style="color:var(--ghost-amber)"></i>Add Incubator</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" style="padding:24px;">
        <div class="row g-3">
          <div class="col-12"><label class="form-label-ghost">Name</label><input type="text" class="form-control-ghost" id="ai_name" placeholder="e.g. Delta Unit"></div>
          <div class="col-md-6"><label class="form-label-ghost">Model</label><input type="text" class="form-control-ghost" id="ai_model" placeholder="GH-Pro 1000"></div>
          <div class="col-md-6"><label class="form-label-ghost">Capacity (eggs)</label><input type="number" class="form-control-ghost" id="ai_capacity" placeholder="100"></div>
          <div class="col-12"><label class="form-label-ghost">Location</label><input type="text" class="form-control-ghost" id="ai_location" placeholder="Farm House A"></div>
          <div class="col-md-6"><label class="form-label-ghost">Status</label>
            <select class="form-select-ghost" id="ai_status">
              <option value="idle">Idle</option><option value="active">Active</option><option value="maintenance">Maintenance</option>
            </select>
          </div>
          <div class="col-md-6"><label class="form-label-ghost">Target Temp (°C)</label><input type="number" step="0.1" class="form-control-ghost" id="ai_target_temp" value="37.5"></div>
          <div class="col-md-6"><label class="form-label-ghost">Target Humidity (%)</label><input type="number" step="0.1" class="form-control-ghost" id="ai_target_humidity" value="55"></div>
          <div class="col-md-6"><label class="form-label-ghost">Turning Interval (hrs)</label><input type="number" min="0.01" step="0.01" class="form-control-ghost" id="ai_turning_interval" value="8"></div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn-ghost" onclick="addIncub()"><i class="fas fa-save me-2"></i>Save</button></div>
    </div>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade modal-ghost" id="editIncubModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit me-2" style="color:var(--ghost-blue)"></i>Edit Incubator</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" style="padding:24px;">
        <input type="hidden" id="ei_id">
        <div class="row g-3">
          <div class="col-12"><label class="form-label-ghost">Name</label><input type="text" class="form-control-ghost" id="ei_name"></div>
          <div class="col-md-6"><label class="form-label-ghost">Model</label><input type="text" class="form-control-ghost" id="ei_model"></div>
          <div class="col-md-6"><label class="form-label-ghost">Capacity</label><input type="number" class="form-control-ghost" id="ei_capacity"></div>
          <div class="col-12"><label class="form-label-ghost">Location</label><input type="text" class="form-control-ghost" id="ei_location"></div>
          <div class="col-md-6"><label class="form-label-ghost">Status</label>
            <select class="form-select-ghost" id="ei_status">
              <option value="idle">Idle</option><option value="active">Active</option><option value="maintenance">Maintenance</option><option value="error">Error</option>
            </select>
          </div>
          <div class="col-md-6"><label class="form-label-ghost">Target Temp (°C)</label><input type="number" step="0.1" class="form-control-ghost" id="ei_target_temp"></div>
          <div class="col-md-6"><label class="form-label-ghost">Target Humidity (%)</label><input type="number" step="0.1" class="form-control-ghost" id="ei_target_humidity"></div>
          <div class="col-md-6"><label class="form-label-ghost">Turning Interval (hrs)</label><input type="number" min="0.01" step="0.01" class="form-control-ghost" id="ei_turning_interval"></div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn-ghost" onclick="updateIncub()"><i class="fas fa-save me-2"></i>Update</button></div>
    </div>
  </div>
</div>

<!-- HARDWARE ACTION MODAL (Incubators page) -->
<div class="modal fade modal-ghost" id="hwActionModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
    <div class="modal-content" style="border-radius:16px;">
      <div class="modal-body" style="padding:32px 28px;text-align:center;">
        <div id="hwModalIcon" style="font-size:3rem;margin-bottom:12px;">⚡</div>
        <div id="hwModalTitle" style="font-family:'Bebas Neue',sans-serif;font-size:1.6rem;letter-spacing:.04em;margin-bottom:6px;color:white;"></div>
        <div id="hwModalSub" style="font-size:.83rem;color:var(--ghost-muted);margin-bottom:22px;"></div>
        <div id="hwModalSpinner" style="display:none;margin-bottom:18px;">
          <div style="width:44px;height:44px;border:3px solid rgba(255,255,255,.1);border-top-color:var(--ghost-amber);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto;"></div>
          <div style="font-size:.78rem;color:var(--ghost-muted);margin-top:10px;">Sending command…</div>
        </div>
        <div id="hwModalResult" style="display:none;padding:12px 16px;border-radius:10px;font-size:.85rem;font-weight:600;margin-bottom:18px;"></div>
        <div id="hwModalStatus" style="display:none;background:rgba(255,255,255,.03);border:1px solid var(--ghost-border);border-radius:10px;padding:14px;margin-bottom:20px;text-align:left;">
          <div style="font-size:.68rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;">Live Hardware Status</div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px;text-align:center;">
            <div><div style="font-size:.65rem;color:var(--ghost-muted);">TEMP</div><div id="mLiveTemp" style="font-family:'Bebas Neue',sans-serif;font-size:1.3rem;color:var(--ghost-amber);">—</div></div>
            <div><div style="font-size:.65rem;color:var(--ghost-muted);">HUMID</div><div id="mLiveHum" style="font-family:'Bebas Neue',sans-serif;font-size:1.3rem;color:#3b82f6;">—</div></div>
            <div><div style="font-size:.65rem;color:var(--ghost-muted);">HEATER</div><div id="mLiveHeater" style="font-family:'Bebas Neue',sans-serif;font-size:1.3rem;color:var(--ghost-muted);">—</div></div>
            <div><div style="font-size:.65rem;color:var(--ghost-muted);">SWING</div><div id="mLiveSwing" style="font-family:'Bebas Neue',sans-serif;font-size:1.3rem;color:var(--ghost-muted);">—</div></div>
          </div>
        </div>
        <div id="hwModalConfirmBtns" style="display:flex;gap:10px;justify-content:center;">
          <button class="btn-outline-ghost" style="padding:10px 24px;" data-bs-dismiss="modal">Cancel</button>
          <button id="hwModalConfirmBtn" class="btn-ghost" style="padding:10px 28px;">Confirm</button>
        </div>
        <div id="hwModalCloseBtns" style="display:none;">
          <button class="btn-ghost" style="padding:10px 32px;width:100%;" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
</div>
<style>@keyframes spin{to{transform:rotate(360deg)}}</style>

<script>
/* ── CRUD ── */
function addIncub(){
  showLoader('Adding Incubator…');
  $.ajax({url:'../ajax/admin_incubators.php',method:'POST',data:{
    action:'add',name:$('#ai_name').val(),model:$('#ai_model').val(),
    capacity:$('#ai_capacity').val(),location:$('#ai_location').val(),
    status:$('#ai_status').val(),target_temp:$('#ai_target_temp').val(),
    target_humidity:$('#ai_target_humidity').val(),turning_interval:$('#ai_turning_interval').val()
  },dataType:'json',
    success:r=>{hideLoader();if(r.success){showToast('Incubator added!');setTimeout(()=>location.reload(),700);}else showToast(r.message,'error');},
    error:()=>{hideLoader();showToast('Server error.','error');}
  });
}
function editIncub(i){
  $('#ei_id').val(i.id);$('#ei_name').val(i.name);$('#ei_model').val(i.model);
  $('#ei_capacity').val(i.capacity);$('#ei_location').val(i.location);$('#ei_status').val(i.status);
  $('#ei_target_temp').val(i.target_temp||'');$('#ei_target_humidity').val(i.target_humidity||'');
  $('#ei_turning_interval').val(i.turning_interval||'');
  new bootstrap.Modal(document.getElementById('editIncubModal')).show();
}
function updateIncub(){
  showLoader('Updating Incubator…');
  $.ajax({url:'../ajax/admin_incubators.php',method:'POST',data:{
    action:'update',id:$('#ei_id').val(),name:$('#ei_name').val(),
    model:$('#ei_model').val(),capacity:$('#ei_capacity').val(),
    location:$('#ei_location').val(),status:$('#ei_status').val(),
    target_temp:$('#ei_target_temp').val(),target_humidity:$('#ei_target_humidity').val(),
    turning_interval:$('#ei_turning_interval').val()
  },dataType:'json',
    success:r=>{hideLoader();if(r.success){showToast('Updated!');setTimeout(()=>location.reload(),700);}else showToast(r.message,'error');},
    error:()=>{hideLoader();showToast('Server error.','error');}
  });
}
function deleteIncub(id){
  showConfirm('Delete incubator', 'Delete this incubator? All associated batches will also be removed.', function(){
    showLoader('Deleting…');
    $.post('../ajax/admin_incubators.php',{action:'delete',id},r=>{
      hideLoader();if(r.success){showToast('Deleted');setTimeout(()=>location.reload(),600);}else showToast(r.message,'error');
    },'json');
  });
}

/* ── HARDWARE per-card — now opens action modal ── */
let _pendingCmd=null, _pendingIncId=null;

const CMD_META={
  heater_on: {icon:'🔥',title:'INCUBATE NOW',   sub:'Turn ON the PTC heater and fan.',   color:'#f5a623',confirmLabel:'🔥 Turn Heater ON'},
  heater_off:{icon:'⏹️',title:'STOP HEATER',    sub:'Turn OFF the heater.',               color:'#ef4444',confirmLabel:'⏹ Turn Heater OFF'},
  swing_on:  {icon:'🔄',title:'START SWING NOW',sub:'Start the egg swing motors.',        color:'#22c55e',confirmLabel:'🔄 Start Swing'},
  swing_off: {icon:'⏹️',title:'STOP SWING',     sub:'Stop the egg swing motors.',        color:'#ef4444',confirmLabel:'⏹ Stop Swing'}
};

function sendHWCmd(incId,cmd){
  const m=CMD_META[cmd]||{icon:'⚡',title:cmd,sub:'',color:'#f5a623',confirmLabel:'Confirm'};
  _pendingCmd=cmd; _pendingIncId=incId;

  $('#hwModalIcon').text(m.icon);
  $('#hwModalTitle').text(m.title).css('color',m.color);
  // Find the card's incubator name from its title
  const cardName=$('#card-'+incId+' .ghost-panel-title').first().text()||'Incubator #'+incId;
  $('#hwModalSub').text(m.sub+' — '+cardName);
  $('#hwModalSpinner').hide();
  $('#hwModalResult').hide();
  $('#hwModalStatus').hide();
  $('#hwModalConfirmBtns').show();
  $('#hwModalCloseBtns').hide();
  $('#hwModalConfirmBtn').text(m.confirmLabel).prop('disabled',false);

  new bootstrap.Modal(document.getElementById('hwActionModal')).show();
}

document.getElementById('hwModalConfirmBtn').addEventListener('click',function(){
  if(!_pendingCmd||!_pendingIncId) return;
  const cmd=_pendingCmd, incId=_pendingIncId;

  $('#hwModalConfirmBtns').hide();
  $('#hwModalSpinner').show();

  $.post('../ajax/hardware_api.php',{action:'manual_command',command:cmd,incubator_id:incId,token:'ghost_hw_secret_2024'},function(res){
    $('#hwModalSpinner').hide();
    if(res.success){
      const okMsg={heater_on:'✅ Command sent! Heater will turn ON within 5s.',heater_off:'✅ Heater will turn OFF within 5s.',swing_on:'✅ Swing will start within 5s.',swing_off:'✅ Swing will stop within 5s.'};
      $('#hwModalResult').text(okMsg[cmd]||'✅ Done.').css({display:'block',background:'rgba(34,197,94,.12)',color:'#22c55e',border:'1px solid rgba(34,197,94,.3)',borderRadius:'10px',padding:'12px 16px'});
      setTimeout(function(){
        $.post('../ajax/hardware_api.php',{action:'get_live_status',incubator_id:incId,token:'ghost_hw_secret_2024'},function(r){
          if(!r.success) return;
          $('#mLiveTemp').text(r.temperature?parseFloat(r.temperature).toFixed(1)+'°C':'—');
          $('#mLiveHum').text(r.humidity?parseFloat(r.humidity).toFixed(1)+'%':'—');
          const hEl=$('#mLiveHeater')[0];hEl.textContent=r.heater_on?'ON':'OFF';hEl.style.color=r.heater_on?'#f5a623':'#64748b';
          const sEl=$('#mLiveSwing')[0];sEl.textContent=r.swing_on?'ON':'OFF';sEl.style.color=r.swing_on?'#22c55e':'#64748b';
          $('#hwModalStatus').show();
          refreshCard(incId);
        },'json');
      },3000);
    } else {
      $('#hwModalResult').text('❌ '+(res.message||'Command failed. Is the device online?')).css({display:'block',background:'rgba(239,68,68,.12)',color:'#ef4444',border:'1px solid rgba(239,68,68,.3)',borderRadius:'10px',padding:'12px 16px'});
    }
    $('#hwModalCloseBtns').show();
  },'json').fail(function(){
    $('#hwModalSpinner').hide();
    $('#hwModalResult').text('❌ Server unreachable. Is XAMPP running?').css({display:'block',background:'rgba(239,68,68,.12)',color:'#ef4444',border:'1px solid rgba(239,68,68,.3)',borderRadius:'10px',padding:'12px 16px'});
    $('#hwModalCloseBtns').show();
  });
});

function refreshCard(incId){
  $.post('../ajax/hardware_api.php',{action:'get_live_status',incubator_id:incId,token:'ghost_hw_secret_2024'},function(res){
    if(!res.success) return;
    $('#liveT-'+incId).text(res.temperature?parseFloat(res.temperature).toFixed(1)+'°C':'—');
    $('#liveH-'+incId).text(res.humidity?parseFloat(res.humidity).toFixed(1)+'%':'—');
    const hb=$('#htrBadge-'+incId);
    if(res.heater_on){hb.css({background:'rgba(245,166,35,.18)',color:'#f5a623',border:'1px solid rgba(245,166,35,.35)'}).text('🔥 Heater ON');}
    else{hb.css({background:'rgba(255,255,255,.05)',color:'#64748b',border:'1px solid rgba(255,255,255,.08)'}).text('🔥 Heater OFF');}
    const sb=$('#swgBadge-'+incId);
    if(res.swing_on){sb.css({background:'rgba(34,197,94,.15)',color:'#22c55e',border:'1px solid rgba(34,197,94,.3)'}).text('🔄 Swing Running');}
    else{sb.css({background:'rgba(255,255,255,.05)',color:'#64748b',border:'1px solid rgba(255,255,255,.08)'}).text('🔄 Swing OFF');}
  },'json');
}

function pollAllCards(){
  <?php foreach($incubators as $inc): ?>refreshCard(<?= $inc['id'] ?>);<?php endforeach; ?>
}
setInterval(pollAllCards,15000);
</script>
<?php require_once 'footer.php'; ?>
