<?php
$pageTitle = 'My Schedules';
$pageSubtitle = 'View and manage your incubation schedule';
$activePage = 'schedules';
require_once 'header.php';
$pdo = getDB();
$uid = $_SESSION['user_id'];
$schedules = $pdo->prepare("SELECT s.*, i.name as incubator_name, b.batch_name FROM schedules s JOIN incubators i ON s.incubator_id=i.id LEFT JOIN batches b ON s.batch_id=b.id WHERE b.user_id=? OR (s.created_by_role='user' AND s.created_by_id=?) ORDER BY s.scheduled_date DESC, s.scheduled_time DESC"); $schedules->execute([$uid,$uid]); $schedules = $schedules->fetchAll();
$incubators = $pdo->query("SELECT id,name FROM incubators WHERE status='active'")->fetchAll();
$batches_q = $pdo->prepare("SELECT id,batch_name,incubator_id FROM batches WHERE user_id=? AND status='incubating'"); $batches_q->execute([$uid]); $myBatches = $batches_q->fetchAll();
?>
<div class="d-flex justify-content-end mb-4">
  <button class="btn-ghost" data-bs-toggle="modal" data-bs-target="#addSchedModal"><i class="fas fa-plus me-2"></i>Add Schedule</button>
</div>
<div class="ghost-panel">
  <div class="ghost-panel-header"><span class="ghost-panel-title">📅 My Schedules</span></div>
  <div class="ghost-panel-body p-0">
    <table class="ghost-table">
      <thead><tr><th>Title</th><th>Incubator</th><th>Date & Time</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($schedules as $s): ?>
        <tr>
          <td><div style="font-weight:600;color:white;"><?= htmlspecialchars($s['title']) ?></div><?php if($s['batch_name']): ?><div style="font-size:.72rem;color:var(--ghost-muted);"><?= htmlspecialchars($s['batch_name']) ?></div><?php endif; ?></td>
          <td style="font-size:.85rem;"><?= htmlspecialchars($s['incubator_name']) ?></td>
          <td><div style="font-size:.85rem;font-weight:600;"><?= date('M j, Y',strtotime($s['scheduled_date'])) ?></div><div style="font-size:.72rem;color:var(--ghost-muted);"><?= substr($s['scheduled_time'],0,5) ?></div></td>
          <td style="font-size:.8rem;"><?= actionIcon($s['action_type'], true) ?></td>
          <td><span class="badge-<?= $s['status'] ?>"><?= $s['status'] ?></span></td>
          <td>
            <div class="d-flex gap-2">
              <?php if($s['status']=='pending'): ?><button class="btn-edit-ghost" style="font-size:.72rem;padding:5px 10px;" onclick="markDone(<?= $s['id'] ?>)">✓</button><?php endif; ?>
              <?php if($s['created_by_role']=='user'&&$s['created_by_id']==$uid): ?>
              <button class="btn-edit-ghost" onclick="editSched(<?= htmlspecialchars(json_encode($s)) ?>)"><i class="fas fa-pen"></i></button>
              <button class="btn-danger-ghost" onclick="deleteSched(<?= $s['id'] ?>)"><i class="fas fa-trash"></i></button>
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
          <h5 class="modal-title"><i class="fas fa-calendar-plus me-2" style="color:var(--ghost-blue)"></i>Add Schedule</h5>
          <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:2px;">Use the same incubation defaults as the parameter modal when creating a task</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:24px;">
        <div class="row g-4 align-items-start">
          <div class="col-lg-7">
            <div id="as_inc_info_bar" style="background:rgba(245,166,35,.06);border:1px solid rgba(245,166,35,.2);border-radius:10px;padding:12px 16px;margin-bottom:20px;display:flex;gap:20px;flex-wrap:wrap;">
              <div>
                <div style="font-size:.7rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.06em;">Batch</div>
                <div style="font-weight:700;color:white;font-size:.9rem;" id="as_info_batch">General</div>
              </div>
              <div>
                <div style="font-size:.7rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.06em;">Incubator</div>
                <div style="font-weight:700;color:white;font-size:.9rem;" id="as_info_incubator">—</div>
              </div>
              <div>
                <div style="font-size:.7rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.06em;">Location</div>
                <div style="font-weight:700;color:white;font-size:.9rem;" id="as_info_loc">—</div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label-ghost">🐣 Egg Species / Type</label>
              <select class="form-select-ghost" id="as_species" onchange="applyASSpeciesDefaults()">
                <option value="chicken" data-target="37.5" data-min="37.0" data-max="38.0" data-hum="55" data-days="21">🐔 Chicken (21 days)</option>
                <option value="duck" data-target="37.5" data-min="37.2" data-max="38.0" data-hum="60" data-days="28">🦆 Duck (28 days)</option>
                <option value="quail" data-target="37.5" data-min="37.0" data-max="38.0" data-hum="50" data-days="18">🐦 Quail (18 days)</option>
                <option value="custom" data-target="" data-min="" data-max="" data-hum="" data-days="">⚙️ Custom</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label-ghost">🥚 Egg Count</label>
              <input type="number" min="1" step="1" class="form-control-ghost" id="as_egg_count" placeholder="How many eggs?">
              <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">This will be saved with the scheduled session.</div>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <div style="background:rgba(245,166,35,.05);border:1px solid rgba(245,166,35,.15);border-radius:12px;padding:18px;margin-bottom:0;">
                  <div style="font-size:.72rem;font-weight:700;color:var(--ghost-amber);letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">🌡️ Temperature (°C)</div>
                  <div class="row g-2">
                    <div class="col-12"><label class="form-label-ghost">Target °C</label><input type="number" step="0.1" class="form-control-ghost" id="as_target_temp" style="font-size:1.2rem;font-family:'Bebas Neue',sans-serif;color:var(--ghost-amber);text-align:center;"></div>
                    <div class="col-6"><label class="form-label-ghost">Min °C</label><input type="number" step="0.1" class="form-control-ghost" id="as_min_temp"></div>
                    <div class="col-6"><label class="form-label-ghost">Max °C</label><input type="number" step="0.1" class="form-control-ghost" id="as_max_temp"></div>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div style="background:rgba(59,130,246,.05);border:1px solid rgba(59,130,246,.15);border-radius:12px;padding:18px;margin-bottom:0;">
                  <div style="font-size:.72rem;font-weight:700;color:#3b82f6;letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">💧 Humidity (%)</div>
                  <div class="row g-2">
                    <div class="col-12"><label class="form-label-ghost">Target %</label><input type="number" step="0.1" class="form-control-ghost" id="as_target_hum" style="font-size:1.2rem;font-family:'Bebas Neue',sans-serif;color:#3b82f6;text-align:center;"></div>
                    <div class="col-6"><label class="form-label-ghost">Min %</label><input type="number" step="0.1" class="form-control-ghost" id="as_min_hum"></div>
                    <div class="col-6"><label class="form-label-ghost">Max %</label><input type="number" step="0.1" class="form-control-ghost" id="as_max_hum"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Session scheduling block (existing) -->
            <div style="background:rgba(59,130,246,.05);border:1px solid rgba(59,130,246,.18);border-radius:12px;padding:18px;margin-top:12px;">
              <div style="font-size:.72rem;font-weight:700;color:#60a5fa;letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">🗓 Session Scheduling</div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label-ghost">Start Date</label>
                  <input type="date" class="form-control-ghost" id="as_start_date" value="<?= date('Y-m-d') ?>" oninput="updateSchedulePreview()">
                </div>
                <div class="col-md-6">
                  <label class="form-label-ghost">Start Time</label>
                  <input type="time" class="form-control-ghost" id="as_start_time" value="<?= date('H:i') ?>" oninput="updateSchedulePreview()">
                </div>
                <div class="col-12">
                  <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;">
                    <div><label class="form-label-ghost">DD</label><input type="number" min="0" step="1" class="form-control-ghost" id="as_duration_days" value="21" oninput="updateSchedulePreview()"></div>
                    <div><label class="form-label-ghost">HH</label><input type="number" min="0" max="23" step="1" class="form-control-ghost" id="as_duration_hours" value="0" oninput="updateSchedulePreview()"></div>
                    <div><label class="form-label-ghost">MM</label><input type="number" min="0" max="59" step="1" class="form-control-ghost" id="as_duration_minutes" value="0" oninput="updateSchedulePreview()"></div>
                    <div><label class="form-label-ghost">SS</label><input type="number" min="0" max="59" step="1" class="form-control-ghost" id="as_duration_seconds" value="0" oninput="updateSchedulePreview()"></div>
                  </div>
                  <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:8px;">Duration until session ends.</div>
                </div>
                <div class="col-md-6">
                  <div style="background:rgba(245,166,35,.06);border:1px solid rgba(245,166,35,.16);border-radius:10px;padding:12px 14px;height:100%;">
                    <div style="font-size:.72rem;font-weight:700;color:#f59e0b;letter-spacing:.12em;text-transform:uppercase;">🕒 Session Ends</div>
                    <div style="font-size:1.05rem;font-weight:700;color:white;margin-top:8px;" id="as_end_text">—</div>
                    <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:4px;" id="as_end_subtext">Calculated from the start date and duration.</div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div style="background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.16);border-radius:10px;padding:12px 14px;height:100%;">
                    <div style="font-size:.72rem;font-weight:700;color:#22c55e;letter-spacing:.12em;text-transform:uppercase;">⏳ Active Session Timer</div>
                    <div style="font-size:1.05rem;font-weight:700;color:white;margin-top:8px;" id="as_timer_text">—</div>
                    <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:4px;">Remaining time for this session.</div>
                  </div>
                </div>
                <div class="col-12">
                  <div style="background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.18);border-radius:10px;padding:12px 14px;" id="as_conflict_box">
                    <div style="font-size:.72rem;font-weight:700;color:#f87171;letter-spacing:.12em;text-transform:uppercase;">Conflict Status</div>
                    <div style="font-size:.9rem;font-weight:600;color:white;margin-top:6px;" id="as_conflict_text">No conflict detected.</div>
                    <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:4px;" id="as_conflict_subtext">The selected time range is available.</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-5">
            <div style="background:rgba(34,197,94,.05);border:1px solid rgba(34,197,94,.15);border-radius:12px;padding:18px;margin-bottom:14px;">
              <div style="font-size:.72rem;font-weight:700;color:#22c55e;letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">🔄 Egg Swing Settings</div>
              <div class="row g-2">
                <div class="col-12">
                  <label class="form-label-ghost">Run for (seconds)</label>
                  <input type="number" min="5" max="300" class="form-control-ghost" id="as_sw_duration" value="30" style="font-size:1.2rem;font-family:'Bebas Neue',sans-serif;color:#22c55e;text-align:center;">
                  <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">Recommended: 30-60 seconds per cycle. Max 300 s.</div>
                </div>
                <div class="col-12 mt-2">
                  <label class="form-label-ghost">Turn eggs every (hours)</label>
                  <input type="number" min="1" max="24" class="form-control-ghost" id="as_sw_interval" value="8" style="font-size:1.2rem;font-family:'Bebas Neue',sans-serif;color:white;text-align:center;">
                  <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">Eggs should be turned every <strong style="color:white;">3-8 hours</strong>.</div>
                </div>
              </div>
            </div>

            <div style="font-size:.78rem;color:var(--ghost-muted);padding:10px 14px;background:rgba(255,255,255,.02);border-radius:8px;border:1px solid var(--ghost-border);">
              <i class="fas fa-info-circle me-1" style="color:var(--ghost-amber);"></i>
              These settings mirror the incubation parameter modal so each scheduled task can carry the same defaults.
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn-ghost" onclick="addSched()"><i class="fas fa-save me-2"></i>Save</button></div>
    </div>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade modal-ghost" id="editSchedModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit Schedule</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" style="padding:24px;">
        <input type="hidden" id="es2_id">
        <div class="row g-3">
          <div class="col-12"><label class="form-label-ghost">Title</label><input type="text" class="form-control-ghost" id="es2_title"></div>
          <div class="col-md-6"><label class="form-label-ghost">Date</label><input type="date" class="form-control-ghost" id="es2_date"></div>
          <div class="col-md-6"><label class="form-label-ghost">Time</label><input type="time" class="form-control-ghost" id="es2_time"></div>
          <div class="col-12"><label class="form-label-ghost">Action Type</label><select class="form-select-ghost" id="es2_action"><option value="turning">🔄 Egg Turning</option><option value="temperature_check">🌡️ Temperature Check</option><option value="humidity_check">💧 Humidity Check</option><option value="candling">🕯️ Candling</option><option value="hatch">🐣 Hatch Day</option></select></div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn-ghost" onclick="updateSched()"><i class="fas fa-save me-2"></i>Update</button></div>
    </div>
  </div>
</div>

<script>
function addSched(){
  const durationDays = parseInt($('#as_duration_days').val(), 10) || 0;
  const durationHours = parseInt($('#as_duration_hours').val(), 10) || 0;
  const durationMinutes = parseInt($('#as_duration_minutes').val(), 10) || 0;
  const durationSeconds = parseInt($('#as_duration_seconds').val(), 10) || 0;
  const startDate = $('#as_start_date').val();
  const startTime = $('#as_start_time').val();
  showLoader('Saving Schedule…');
  $.ajax({url:'../ajax/user_schedules.php',method:'POST',data:{
    action:'add',
    title:$('#as_title').val(),
    batch_id:$('#as_batch').val(),
    incubator_id:$('#as_incubator').val(),
    start_date:startDate,
    start_time:startTime,
    date:startDate,
    time:startTime,
    action_type:$('#as_action').val(),
    target_temp:$('#as_target_temp').val(),
    min_temp:$('#as_min_temp').val(),
    max_temp:$('#as_max_temp').val(),
    target_humidity:$('#as_target_hum').val(),
    min_humidity:$('#as_min_hum').val(),
    max_humidity:$('#as_max_hum').val(),
    swing_duration_sec:$('#as_sw_duration').val(),
    turning_interval:$('#as_sw_interval').val(),
    duration_days:durationDays,
    duration_hours:durationHours,
    duration_minutes:durationMinutes,
    duration_seconds:durationSeconds
  },dataType:'json',
    success:r=>{hideLoader();if(r.success){showToast('Schedule added!');setTimeout(()=>location.reload(),700);}else showToast(r.message,'error');},
    error:()=>{hideLoader();showToast('Server error.','error');}
  });
}
function syncScheduleIncubator(){
  const batchSelect = document.getElementById('as_batch');
  const incubatorSelect = document.getElementById('as_incubator');
  const batchInfo = document.getElementById('as_info_batch');
  const incubatorInfo = document.getElementById('as_info_incubator');
  const batchOption = batchSelect.options[batchSelect.selectedIndex];
  if (batchOption && batchOption.dataset && batchOption.dataset.inc) {
    incubatorSelect.value = batchOption.dataset.inc;
  }
  if (batchInfo) {
    batchInfo.textContent = batchSelect.value ? batchSelect.options[batchSelect.selectedIndex].textContent : 'General';
  }
  if (incubatorInfo) {
    incubatorInfo.textContent = incubatorSelect.value ? incubatorSelect.options[incubatorSelect.selectedIndex].textContent : '—';
  }
  updateSchedulePreview();
}
function resetScheduleDurationDefaults(){
  document.getElementById('as_start_date').value = new Date().toISOString().slice(0, 10);
  document.getElementById('as_start_time').value = new Date().toTimeString().slice(0, 5);
  document.getElementById('as_duration_days').value = 21;
  document.getElementById('as_duration_hours').value = 0;
  document.getElementById('as_duration_minutes').value = 0;
  document.getElementById('as_duration_seconds').value = 0;
  document.getElementById('as_sw_duration').value = 30;
  document.getElementById('as_sw_interval').value = 8;
  updateSchedulePreview();
}
function updateSchedulePreview(){
  const startDate = document.getElementById('as_start_date')?.value;
  const startTime = document.getElementById('as_start_time')?.value || '00:00';
  const days = parseInt(document.getElementById('as_duration_days')?.value, 10) || 0;
  const hours = parseInt(document.getElementById('as_duration_hours')?.value, 10) || 0;
  const minutes = parseInt(document.getElementById('as_duration_minutes')?.value, 10) || 0;
  const seconds = parseInt(document.getElementById('as_duration_seconds')?.value, 10) || 0;
  const endText = document.getElementById('as_end_text');
  const endSubtext = document.getElementById('as_end_subtext');
  const timerText = document.getElementById('as_timer_text');
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
  const endDateText = end.toLocaleString('en-US', { month:'long', day:'numeric', year:'numeric' });
  const endTimeText = end.toLocaleString('en-US', { hour:'2-digit', minute:'2-digit' });
  endText.textContent = `${endDateText} - ${endTimeText}`;
  endSubtext.textContent = `Session starts ${start.toLocaleString('en-US', { month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' })}`;

  const remaining = Math.max(0, Math.floor((end.getTime() - Date.now()) / 1000));
  const remainingDays = Math.floor(remaining / 86400);
  const remainingHours = Math.floor((remaining % 86400) / 3600);
  const remainingMinutes = Math.floor((remaining % 3600) / 60);
  timerText.textContent = `${remainingDays} Days ${String(remainingHours).padStart(2, '0')} Hours ${String(remainingMinutes).padStart(2, '0')} Minutes`;

  const conflictBox = document.getElementById('as_conflict_box');
  const conflictText = document.getElementById('as_conflict_text');
  const conflictSubtext = document.getElementById('as_conflict_subtext');
  if (conflictBox && conflictText && conflictSubtext) {
    conflictBox.style.borderColor = 'rgba(34,197,94,.18)';
    conflictText.textContent = 'No conflict detected.';
    conflictSubtext.textContent = 'The selected time range is available.';
  }
}
document.getElementById('addSchedModal').addEventListener('show.bs.modal', function() {
  syncScheduleIncubator();
  resetScheduleDurationDefaults();
});
function editSched(s){
  $('#es2_id').val(s.id);$('#es2_title').val(s.title);$('#es2_date').val(s.scheduled_date);
  $('#es2_time').val(s.scheduled_time.substring(0,5));$('#es2_action').val(s.action_type);
  new bootstrap.Modal(document.getElementById('editSchedModal')).show();
}
function updateSched(){
  showLoader('Updating Schedule…');
  $.ajax({url:'../ajax/user_schedules.php',method:'POST',data:{action:'update',id:$('#es2_id').val(),title:$('#es2_title').val(),date:$('#es2_date').val(),time:$('#es2_time').val(),action_type:$('#es2_action').val()},dataType:'json',
    success:r=>{hideLoader();if(r.success){showToast('Updated!');setTimeout(()=>location.reload(),700);}else showToast(r.message,'error');},
    error:()=>{hideLoader();showToast('Server error.','error');}
  });
}
function deleteSched(id){
  showConfirm('Delete schedule', 'Delete this schedule?', function(){
    showLoader('Deleting…');
    $.post('../ajax/user_schedules.php',{action:'delete',id},r=>{hideLoader();if(r.success){showToast('Deleted');setTimeout(()=>location.reload(),600);}else showToast(r.message,'error');},'json');
  });
}
function markDone(id){
  showLoader('Marking Done…');
  $.post('../ajax/user_schedules.php',{action:'mark_done',id},r=>{hideLoader();if(r.success){showToast('Marked done!');setTimeout(()=>location.reload(),600);}else showToast(r.message,'error');},'json');
}

// Helpers for Add Schedule modal to match parameter modal behavior
function applyASSpeciesDefaults(){
  const sel = document.getElementById('as_species');
  const opt = sel.options[sel.selectedIndex];
  if(!opt) return;
  const target = opt.dataset.target || '';
  const min = opt.dataset.min || '';
  const max = opt.dataset.max || '';
  const hum = opt.dataset.hum || '';
  const days = opt.dataset.days || '';
  if(target) document.getElementById('as_target_temp').value = target;
  if(min) document.getElementById('as_min_temp').value = min;
  if(max) document.getElementById('as_max_temp').value = max;
  if(hum) document.getElementById('as_target_hum').value = hum;
  if(days) document.getElementById('as_duration_days').value = days;
  updateSchedulePreview();
}

function setTurningIntervalPreset(h){
  const el = document.getElementById('as_sw_interval') || document.getElementById('sw_interval');
  if(el) el.value = h;
}
</script>
<?php require_once 'footer.php'; ?>
