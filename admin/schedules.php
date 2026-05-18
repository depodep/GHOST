<?php
$pageTitle = 'Schedules';
$pageSubtitle = 'Manage incubation schedules';
$activePage = 'schedules';
require_once 'header.php';
$pdo = getDB();
$schedules = $pdo->query("SELECT s.*, i.name as incubator_name, b.batch_name FROM schedules s JOIN incubators i ON s.incubator_id=i.id LEFT JOIN batches b ON s.batch_id=b.id ORDER BY s.scheduled_date DESC, s.scheduled_time DESC")->fetchAll();
$incubators = $pdo->query("SELECT id, name FROM incubators WHERE status != 'maintenance'")->fetchAll();
$batches = $pdo->query("SELECT id, batch_name, incubator_id FROM batches WHERE status='incubating'")->fetchAll();
?>

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
              <option value="humidity_check">💧 Humidity Check</option>
              <option value="candling">🕯️ Candling</option>
              <option value="hatch">🐣 Hatch Day</option>
              <option value="maintenance">⚙️ Maintenance</option>
            </select>
          </div>
          <div class="col-md-3"><label class="form-label-ghost">Target Temp (°C)</label><input type="number" step="0.1" class="form-control-ghost" id="s_temp" placeholder="37.5"></div>
          <div class="col-md-3"><label class="form-label-ghost">Target Humidity (%)</label><input type="number" step="0.1" class="form-control-ghost" id="s_humidity" placeholder="55"></div>
          <div class="col-12"><label class="form-label-ghost">Notes</label><textarea class="form-control-ghost" id="s_notes" rows="2" placeholder="Optional notes..."></textarea></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-ghost" onclick="addSchedule()"><i class="fas fa-save me-2"></i>Save Schedule</button>
      </div>
    </div>
  </div>
</div>

<!-- EDIT SCHEDULE MODAL -->
<div class="modal fade modal-ghost" id="editScheduleModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-calendar-edit me-2" style="color:var(--ghost-blue)"></i>Edit Schedule</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:24px;">
        <input type="hidden" id="es_id">
        <div class="row g-3">
          <div class="col-12"><label class="form-label-ghost">Title</label><input type="text" class="form-control-ghost" id="es_title"></div>
          <div class="col-md-6"><label class="form-label-ghost">Date</label><input type="date" class="form-control-ghost" id="es_date"></div>
          <div class="col-md-6"><label class="form-label-ghost">Time</label><input type="time" class="form-control-ghost" id="es_time"></div>
          <div class="col-md-6">
            <label class="form-label-ghost">Action Type</label>
            <select class="form-select-ghost" id="es_action">
              <option value="turning">🔄 Egg Turning</option>
              <option value="temperature_check">🌡️ Temperature Check</option>
              <option value="humidity_check">💧 Humidity Check</option>
              <option value="candling">🕯️ Candling</option>
              <option value="hatch">🐣 Hatch Day</option>
              <option value="maintenance">⚙️ Maintenance</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label-ghost">Status</label>
            <select class="form-select-ghost" id="es_status">
              <option value="pending">Pending</option>
              <option value="done">Done</option>
              <option value="missed">Missed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="col-12"><label class="form-label-ghost">Notes</label><textarea class="form-control-ghost" id="es_notes" rows="2"></textarea></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-ghost" onclick="updateSchedule()"><i class="fas fa-save me-2"></i>Update</button>
      </div>
    </div>
  </div>
</div>

<script>
const allBatches = <?= json_encode($batches) ?>;

function openAddSchedule(){ new bootstrap.Modal(document.getElementById('addScheduleModal')).show(); }

function loadBatches(incId){
  const sel = document.getElementById('s_batch');
  sel.innerHTML = '<option value="">All batches</option>';
  allBatches.filter(b=>b.incubator_id==incId).forEach(b=>{
    sel.innerHTML += `<option value="${b.id}">${b.batch_name}</option>`;
  });
}

function addSchedule(){
  showLoader('Saving Schedule…');
  $.ajax({
    url:'../ajax/admin_schedules.php',method:'POST',
    data:{action:'add',title:$('#s_title').val(),incubator_id:$('#s_incubator').val(),batch_id:$('#s_batch').val(),date:$('#s_date').val(),time:$('#s_time').val(),action_type:$('#s_action').val(),target_temp:$('#s_temp').val(),target_humidity:$('#s_humidity').val(),notes:$('#s_notes').val()},
    dataType:'json',
    success:r=>{hideLoader();if(r.success){showToast('Schedule added!');setTimeout(()=>location.reload(),700);}else showToast(r.message,'error');},
    error:()=>{hideLoader();showToast('Server error.','error');}
  });
}

function editSchedule(s){
  $('#es_id').val(s.id);$('#es_title').val(s.title);$('#es_date').val(s.scheduled_date);
  $('#es_time').val(s.scheduled_time.substring(0,5));$('#es_action').val(s.action_type);
  $('#es_status').val(s.status);$('#es_notes').val(s.description||'');
  new bootstrap.Modal(document.getElementById('editScheduleModal')).show();
}

function updateSchedule(){
  showLoader('Updating Schedule…');
  $.ajax({
    url:'../ajax/admin_schedules.php',method:'POST',
    data:{action:'update',id:$('#es_id').val(),title:$('#es_title').val(),date:$('#es_date').val(),time:$('#es_time').val(),action_type:$('#es_action').val(),status:$('#es_status').val(),notes:$('#es_notes').val()},
    dataType:'json',
    success:r=>{hideLoader();if(r.success){showToast('Schedule updated!');setTimeout(()=>location.reload(),700);}else showToast(r.message,'error');},
    error:()=>{hideLoader();showToast('Server error.','error');}
  });
}

function deleteSchedule(id){
  showConfirm('Delete schedule', 'Delete this schedule?', function(){
    showLoader('Deleting…');
    $.post('../ajax/admin_schedules.php',{action:'delete',id},r=>{
      hideLoader();if(r.success){showToast('Deleted!');setTimeout(()=>location.reload(),600);}    },'json');
  });
}

function markDone(id){
  showLoader('Marking Done…');
  $.post('../ajax/admin_schedules.php',{action:'mark_done',id},r=>{
    hideLoader();if(r.success){showToast('Marked as done!');setTimeout(()=>location.reload(),600);}
  },'json');
}

function filterSchedules(status, btn){
  document.querySelectorAll('[data-filter]').forEach(b=>b.style.borderColor='');
  if(btn) btn.style.borderColor='var(--ghost-amber)';
  $('#scheduleTable tbody tr').each(function(){
    $(this).toggle(status==='all' || $(this).data('status')===status);
  });
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
