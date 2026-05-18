<?php
$pageTitle = 'Temperature Control';
$pageSubtitle = 'Adjust temperature & humidity settings per incubator';
$activePage = 'temperature';
require_once 'header.php';
$pdo = getDB();
$incubators = $pdo->query("SELECT i.*, ts.target_temp, ts.min_temp, ts.max_temp, ts.target_humidity, ts.min_humidity, ts.max_humidity, ts.turning_interval FROM incubators i LEFT JOIN temperature_settings ts ON i.id=ts.incubator_id ORDER BY i.name")->fetchAll();
?>

<div class="row g-3 mb-4">
  <div class="col-12">
    <div style="background:var(--ghost-amber-dim);border:1px solid rgba(245,166,35,0.25);border-radius:12px;padding:14px 18px;display:flex;align-items:center;gap:12px;font-size:0.88rem;color:var(--ghost-amber);">
      <i class="fas fa-info-circle"></i>
      <span>Changes to temperature settings take effect immediately. Monitor logs closely after adjustments.</span>
    </div>
  </div>
</div>

<?php foreach($incubators as $inc): ?>
<div class="ghost-panel mb-3">
  <div class="ghost-panel-header d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-12">
      <span style="font-size:1.5rem;margin-right:8px;">🌡️</span>
      <div>
        <span class="ghost-panel-title"><?= htmlspecialchars($inc['name']) ?></span>
        <span class="ms-2" style="font-size:0.75rem;color:var(--ghost-muted);"><?= $inc['model'] ?> · <?= $inc['capacity'] ?> eggs · <?= $inc['location'] ?></span>
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="badge-<?= $inc['status'] ?>"><?= $inc['status'] ?></span>
      <button class="btn-ghost btn-ghost-sm" onclick="saveSettings(<?= $inc['id'] ?>)"><i class="fas fa-save me-1"></i>Save</button>
    </div>
  </div>
  <div class="ghost-panel-body">
    <div class="row g-4" id="settings_<?= $inc['id'] ?>">
      <div class="col-md-4">
        <div style="background:rgba(245,166,35,0.05);border:1px solid rgba(245,166,35,0.15);border-radius:12px;padding:20px;">
          <div style="font-size:0.72rem;font-weight:700;color:var(--ghost-amber);letter-spacing:0.12em;text-transform:uppercase;margin-bottom:16px;">🌡️ Temperature (°C)</div>
          <div class="row g-2">
            <div class="col-12"><label class="form-label-ghost">Target</label>
              <input type="number" step="0.1" class="form-control-ghost" name="target_temp" value="<?= $inc['target_temp'] ?? 37.5 ?>" style="font-size:1.3rem;font-family:'Bebas Neue',sans-serif;color:var(--ghost-amber);text-align:center;">
            </div>
            <div class="col-6"><label class="form-label-ghost">Min</label><input type="number" step="0.1" class="form-control-ghost" name="min_temp" value="<?= $inc['min_temp'] ?? 37.0 ?>"></div>
            <div class="col-6"><label class="form-label-ghost">Max</label><input type="number" step="0.1" class="form-control-ghost" name="max_temp" value="<?= $inc['max_temp'] ?? 38.0 ?>"></div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div style="background:rgba(59,130,246,0.05);border:1px solid rgba(59,130,246,0.15);border-radius:12px;padding:20px;">
          <div style="font-size:0.72rem;font-weight:700;color:var(--ghost-blue);letter-spacing:0.12em;text-transform:uppercase;margin-bottom:16px;">💧 Humidity (%)</div>
          <div class="row g-2">
            <div class="col-12"><label class="form-label-ghost">Target</label>
              <input type="number" step="0.1" class="form-control-ghost" name="target_humidity" value="<?= $inc['target_humidity'] ?? 55.0 ?>" style="font-size:1.3rem;font-family:'Bebas Neue',sans-serif;color:var(--ghost-blue);text-align:center;">
            </div>
            <div class="col-6"><label class="form-label-ghost">Min</label><input type="number" step="0.1" class="form-control-ghost" name="min_humidity" value="<?= $inc['min_humidity'] ?? 50.0 ?>"></div>
            <div class="col-6"><label class="form-label-ghost">Max</label><input type="number" step="0.1" class="form-control-ghost" name="max_humidity" value="<?= $inc['max_humidity'] ?? 60.0 ?>"></div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div style="background:rgba(34,197,94,0.05);border:1px solid rgba(34,197,94,0.15);border-radius:12px;padding:20px;">
          <div style="font-size:0.72rem;font-weight:700;color:var(--ghost-green);letter-spacing:0.12em;text-transform:uppercase;margin-bottom:16px;">⚙️ Turning Interval</div>
          <label class="form-label-ghost">Every (hours)</label>
          <input type="number" min="1" max="24" class="form-control-ghost" name="turning_interval" value="<?= $inc['turning_interval'] ?? 8 ?>" style="font-size:1.3rem;font-family:'Bebas Neue',sans-serif;color:var(--ghost-green);text-align:center;">
          <div style="margin-top:12px;font-size:0.8rem;color:var(--ghost-muted);line-height:1.6;">Eggs should be turned every <strong style="color:var(--ghost-green);"><?= $inc['turning_interval'] ?? 8 ?> hours</strong> to prevent embryo sticking.</div>
        </div>
      </div>
    </div>
    <!-- TEMP LOG MINI -->
    <div class="mt-3">
      <div style="font-size:0.78rem;color:var(--ghost-muted);margin-bottom:8px;">Recent readings:</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php
        $logs = $pdo->prepare("SELECT temperature, humidity, DATE_FORMAT(recorded_at,'%H:%i') as t FROM temperature_logs WHERE incubator_id=? ORDER BY recorded_at DESC LIMIT 6");
        $logs->execute([$inc['id']]);
        foreach($logs->fetchAll() as $log): ?>
        <div style="background:rgba(255,255,255,0.03);border:1px solid var(--ghost-border);border-radius:8px;padding:6px 12px;font-size:0.78rem;">
          <span style="color:var(--ghost-amber);font-weight:600;"><?= $log['temperature'] ?>°</span>
          <span style="color:var(--ghost-muted);margin:0 4px;">·</span>
          <span style="color:var(--ghost-blue);"><?= $log['humidity'] ?>%</span>
          <span style="color:var(--ghost-muted);font-size:0.7rem;margin-left:4px;"><?= $log['t'] ?></span>
        </div>
        <?php endforeach; ?>
        <button class="btn-outline-ghost" style="font-size:0.75rem;padding:5px 12px;" onclick="logTemp(<?= $inc['id'] ?>)">+ Log Reading</button>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<!-- LOG TEMP MODAL -->
<div class="modal fade modal-ghost" id="logTempModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">📝 Log Reading</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:24px;">
        <input type="hidden" id="log_incubator_id">
        <div class="mb-3"><label class="form-label-ghost">Temperature (°C)</label><input type="number" step="0.1" class="form-control-ghost" id="log_temp" placeholder="37.5"></div>
        <div class="mb-3"><label class="form-label-ghost">Humidity (%)</label><input type="number" step="0.1" class="form-control-ghost" id="log_humidity" placeholder="55.0"></div>
      </div>
      <div class="modal-footer">
        <button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-ghost" onclick="saveLog()"><i class="fas fa-save me-1"></i>Save</button>
      </div>
    </div>
  </div>
</div>

<script>
function saveSettings(incId){
  showLoader('Saving Settings…');
  const fields = document.querySelectorAll(`#settings_${incId} input`);
  const data = {action:'update_settings', incubator_id:incId};
  fields.forEach(f=>{data[f.name]=f.value;});
  $.ajax({
    url:'../ajax/admin_temperature.php',method:'POST',data,dataType:'json',
    success:r=>{hideLoader();if(r.success)showToast('Settings saved for incubator!');else showToast(r.message,'error');},
    error:()=>{hideLoader();showToast('Server error.','error');}
  });
}

function logTemp(id){
  $('#log_incubator_id').val(id);
  new bootstrap.Modal(document.getElementById('logTempModal')).show();
}

function saveLog(){
  showLoader('Logging Reading…');
  $.ajax({
    url:'../ajax/admin_temperature.php',method:'POST',
    data:{action:'log_temp',incubator_id:$('#log_incubator_id').val(),temperature:$('#log_temp').val(),humidity:$('#log_humidity').val(),role:'admin'},
    dataType:'json',
    success:r=>{hideLoader();if(r.success){showToast('Reading logged!');setTimeout(()=>location.reload(),700);}else showToast(r.message,'error');},
    error:()=>{hideLoader();showToast('Server error.','error');}
  });
}
</script>
<?php require_once 'footer.php'; ?>
