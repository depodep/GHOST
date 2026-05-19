<?php
$pageTitle = 'My Batches';
$pageSubtitle = 'Track and manage your egg batches';
$activePage = 'batches';
require_once 'header.php';
$pdo = getDB();
$uid = $_SESSION['user_id'];
$batches = $pdo->prepare("SELECT b.*, i.name as incubator_name FROM batches b JOIN incubators i ON b.incubator_id=i.id WHERE b.user_id=? ORDER BY b.created_at DESC"); $batches->execute([$uid]); $batches = $batches->fetchAll();
$incubators = $pdo->query("SELECT id,name FROM incubators WHERE status='active'")->fetchAll();
?>
<div class="d-flex justify-content-end mb-4">
  <a href="schedule.php" class="btn btn-primary">
    Schedule New Batch
  </a>
</div>
<div class="ghost-panel">
  <div class="ghost-panel-header"><span class="ghost-panel-title">🥚 All My Batches</span></div>
  <div class="ghost-panel-body p-0">
    <table class="ghost-table">
      <thead><tr><th>Batch Name</th><th>Incubator</th><th>Eggs</th><th>Start</th><th>Hatch Date</th><th>Status</th><th>Status Time</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($batches as $b): $days=max(0,round((strtotime($b['expected_hatch_date'])-time())/86400));
        $statusTime = null;
        if ($b['status'] === 'completed') { $statusTime = $b['completed_at'] ?? null; }
        if ($b['status'] === 'terminated') { $statusTime = $b['terminated_at'] ?? null; }
      ?>
        <tr>
          <td><div style="font-weight:600;color:white;"><?= htmlspecialchars($b['batch_name']) ?></div><?php if($b['notes']): ?><div style="font-size:.72rem;color:var(--ghost-muted);"><?= substr(htmlspecialchars($b['notes']),0,40) ?>...</div><?php endif; ?></td>
          <td style="font-size:.85rem;"><?= htmlspecialchars($b['incubator_name']) ?></td>
          <td style="font-weight:600;"><?= $b['egg_count'] ?></td>
          <td style="font-size:.82rem;color:var(--ghost-muted);"><?= date('M j, Y',strtotime($b['start_date'])) ?></td>
          <td><div style="font-size:.85rem;"><?= date('M j, Y',strtotime($b['expected_hatch_date'])) ?></div><div style="font-size:.72rem;color:<?= $days<=3&&$b['status']=='incubating'?'#f87171':'var(--ghost-muted)' ?>;"><?= $b['status']=='incubating'?($days>0?$days.' days left':'Hatch day!'):'' ?></div></td>
          <td><span class="badge-<?= $b['status'] ?>"><?= $b['status'] ?></span></td>
          <td style="font-size:.82rem;color:var(--ghost-muted);">
            <?= $statusTime ? date('M j, Y H:i', strtotime($statusTime)) : '—' ?>
          </td>
          <td><div class="d-flex gap-2">
            <button class="btn-edit-ghost" onclick="viewBatchSummary(<?= $b['id'] ?>)"><i class="fas fa-eye"></i></button>
            <?php if ($b['status'] === 'scheduled'): ?>
              <button class="btn-danger-ghost" onclick="cancelBatch(<?= $b['id'] ?>)"><i class="fas fa-ban"></i></button>
            <?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- BATCH SUMMARY MODAL -->
<div class="modal fade modal-ghost" id="batchSummaryModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title"><i class="fas fa-eye me-2" style="color:var(--ghost-blue)"></i>Batch Summary</h5>
          <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:2px;" id="batchSummarySubtitle">Summary of session metrics</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:24px;">
        <div class="row g-3">
          <div class="col-md-6"><div class="ghost-panel" style="height:100%;margin:0;"><div class="ghost-panel-body"><div style="font-size:.72rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.12em;">Batch</div><div id="bs_name" style="font-size:1.15rem;font-weight:700;color:white;">—</div><div id="bs_meta" style="margin-top:8px;color:var(--ghost-muted);font-size:.85rem;">—</div></div></div></div>
          <div class="col-md-6"><div class="ghost-panel" style="height:100%;margin:0;"><div class="ghost-panel-body"><div style="font-size:.72rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.12em;">Session Timer</div><div id="bs_duration" style="font-size:1.15rem;font-weight:700;color:#22c55e;">—</div><div id="bs_window" style="margin-top:8px;color:var(--ghost-muted);font-size:.85rem;">—</div></div></div></div>
          <div class="col-md-4"><div class="ghost-panel" style="height:100%;margin:0;"><div class="ghost-panel-body"><div style="font-size:.72rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.12em;">Temperature</div><div id="bs_temp" style="font-size:1.15rem;font-weight:700;color:#f59e0b;">—</div><div id="bs_temp_meta" style="margin-top:8px;color:var(--ghost-muted);font-size:.85rem;">—</div></div></div></div>
          <div class="col-md-4"><div class="ghost-panel" style="height:100%;margin:0;"><div class="ghost-panel-body"><div style="font-size:.72rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.12em;">Humidity</div><div id="bs_humidity" style="font-size:1.15rem;font-weight:700;color:#60a5fa;">—</div><div id="bs_humidity_meta" style="margin-top:8px;color:var(--ghost-muted);font-size:.85rem;">—</div></div></div></div>
          <div class="col-md-4"><div class="ghost-panel" style="height:100%;margin:0;"><div class="ghost-panel-body"><div style="font-size:.72rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.12em;">Egg Swings</div><div id="bs_swings" style="font-size:1.15rem;font-weight:700;color:#22c55e;">—</div><div id="bs_logs" style="margin-top:8px;color:var(--ghost-muted);font-size:.85rem;">—</div></div></div></div>
          <div class="col-12">
            <div style="background:rgba(255,255,255,.02);border:1px solid var(--ghost-border);border-radius:12px;padding:16px;">
              <div style="font-size:.72rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.12em;">Summary</div>
              <div id="bs_summary" style="margin-top:8px;color:white;font-weight:600;line-height:1.6;">—</div>
            </div>
          </div>
          <div class="col-12">
            <div style="background:rgba(255,255,255,.02);border:1px solid var(--ghost-border);border-radius:12px;padding:16px;">
              <div style="font-size:.72rem;color:var(--ghost-muted);text-transform:uppercase;letter-spacing:.12em;margin-bottom:10px;">Session Trend</div>
              <div style="position:relative;height:260px;">
                <canvas id="bsChart" height="260"></canvas>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn-outline-ghost" data-bs-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>

<!-- ADD BATCH MODAL -->
<div class="modal fade modal-ghost" id="addBatchModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus-circle me-2" style="color:var(--ghost-blue)"></i>Start New Batch</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" style="padding:24px;">
        <div class="row g-3">
          <div class="col-12"><label class="form-label-ghost">Batch Name</label><input type="text" class="form-control-ghost" id="ab_name" placeholder="e.g. Batch Alpha-03"></div>
          <div class="col-md-6"><label class="form-label-ghost">Incubator</label><select class="form-select-ghost" id="ab_incubator"><option value="">Select Incubator</option><?php foreach($incubators as $i): ?><option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-4"><label class="form-label-ghost">Egg Count</label><input type="number" class="form-control-ghost" id="ab_count" placeholder="e.g. 50"></div>
          <div class="col-md-4"><label class="form-label-ghost">Start Date</label><input type="date" class="form-control-ghost" id="ab_start" value="<?= date('Y-m-d') ?>"></div>
          <div class="col-md-4"><label class="form-label-ghost">Expected Hatch</label><input type="date" class="form-control-ghost" id="ab_hatch" value="<?= date('Y-m-d',strtotime('+21 days')) ?>"></div>
          <div class="col-12"><label class="form-label-ghost">Notes</label><textarea class="form-control-ghost" id="ab_notes" rows="2" placeholder="Optional notes..."></textarea></div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn-ghost" onclick="addBatch()"><i class="fas fa-save me-2"></i>Start Batch</button></div>
    </div>
  </div>
</div>

<!-- EDIT BATCH MODAL -->
<div class="modal fade modal-ghost" id="editBatchModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit me-2" style="color:var(--ghost-blue)"></i>Edit Batch</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" style="padding:24px;">
        <input type="hidden" id="eb_id">
        <div class="row g-3">
          <div class="col-12"><label class="form-label-ghost">Batch Name</label><input type="text" class="form-control-ghost" id="eb_name"></div>
          <div class="col-md-6"><label class="form-label-ghost">Egg Count</label><input type="number" class="form-control-ghost" id="eb_count"></div>
          <div class="col-md-6"><label class="form-label-ghost">Expected Hatch</label><input type="date" class="form-control-ghost" id="eb_hatch"></div>
          <div class="col-md-6"><label class="form-label-ghost">Status</label><select class="form-select-ghost" id="eb_status"><option value="incubating">Incubating</option><option value="completed">Completed</option><option value="terminated">Terminated</option><option value="hatched">Hatched</option><option value="failed">Failed</option><option value="cancelled">Cancelled</option></select></div>
          <div class="col-12"><label class="form-label-ghost">Notes</label><textarea class="form-control-ghost" id="eb_notes" rows="2"></textarea></div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn-ghost" onclick="updateBatch()"><i class="fas fa-save me-2"></i>Update</button></div>
    </div>
  </div>
</div>

<script>
function addBatch(){
  showLoader('Starting Batch…');
  $.ajax({url:'../ajax/user_batches.php',method:'POST',data:{action:'add',name:$('#ab_name').val(),incubator_id:$('#ab_incubator').val(),egg_count:$('#ab_count').val(),start_date:$('#ab_start').val(),hatch_date:$('#ab_hatch').val(),notes:$('#ab_notes').val()},dataType:'json',
    success:r=>{hideLoader();if(r.success){showToast('Batch started!');setTimeout(()=>location.reload(),700);}else showToast(r.message,'error');},
    error:()=>{hideLoader();showToast('Server error.','error');}
  });
}
function editBatch(b){
  $('#eb_id').val(b.id);$('#eb_name').val(b.batch_name);
  $('#eb_count').val(b.egg_count);$('#eb_hatch').val(b.expected_hatch_date);
  $('#eb_status').val(b.status);$('#eb_notes').val(b.notes||'');
  new bootstrap.Modal(document.getElementById('editBatchModal')).show();
}
function updateBatch(){
  showLoader('Updating Batch…');
  $.ajax({url:'../ajax/user_batches.php',method:'POST',data:{action:'update',id:$('#eb_id').val(),name:$('#eb_name').val(),egg_count:$('#eb_count').val(),hatch_date:$('#eb_hatch').val(),status:$('#eb_status').val(),notes:$('#eb_notes').val()},dataType:'json',
    success:r=>{hideLoader();if(r.success){showToast('Batch updated!');setTimeout(()=>location.reload(),700);}else showToast(r.message,'error');},
    error:()=>{hideLoader();showToast('Server error.','error');}
  });
}
function deleteBatch(id){
  showConfirm('Delete batch', 'Delete this batch?', function(){
    showLoader('Deleting…');
    $.post('../ajax/user_batches.php',{action:'delete',id},r=>{hideLoader();if(r.success){showToast('Deleted');setTimeout(()=>location.reload(),600);}else showToast(r.message,'error');},'json');
  });
}

function cancelBatch(id){
  showConfirm('Cancel batch', 'Mark this batch as cancelled? This will not delete the record.', function(){
    showLoader('Cancelling…');
    $.post('../ajax/user_batches.php',{action:'update',id:id,status:'cancelled'},function(r){
      hideLoader();
      if(r && r.success){
        showToast('Batch cancelled');
        setTimeout(()=>location.reload(),600);
      } else {
        showToast((r && r.message) ? r.message : 'Could not cancel batch','error');
      }
    },'json').fail(function(){ hideLoader(); showToast('Server error.','error'); });
  });
}

function formatBatchDateTime(value) {
  if (!value) return '—';
  const parsed = new Date(String(value).replace(' ', 'T'));
  if (isNaN(parsed.getTime())) return String(value);
  return parsed.toLocaleString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatDuration(seconds) {
  seconds = parseInt(seconds, 10) || 0;
  const days = Math.floor(seconds / 86400);
  const hours = Math.floor((seconds % 86400) / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  if (days > 0) return `${days}d ${String(hours).padStart(2,'0')}h ${String(minutes).padStart(2,'0')}m`;
  if (hours > 0) return `${hours}h ${String(minutes).padStart(2,'0')}m`;
  return `${minutes}m`;
}

let batchSummaryChart = null;

function destroyBatchSummaryChart() {
  if (batchSummaryChart) {
    batchSummaryChart.destroy();
    batchSummaryChart = null;
  }
}

function renderBatchSummaryChart(logs) {
  const canvas = document.getElementById('bsChart');
  if (!canvas || !window.Chart) return;

  destroyBatchSummaryChart();

  const labels = (logs || []).map(row => formatBatchDateTime(row.recorded_at));
  const temperatures = (logs || []).map(row => row.temperature !== null && row.temperature !== undefined ? parseFloat(row.temperature) : null);
  const humidity = (logs || []).map(row => row.humidity !== null && row.humidity !== undefined ? parseFloat(row.humidity) : null);

  batchSummaryChart = new Chart(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Temperature (°C)',
          data: temperatures,
          borderColor: '#f59e0b',
          backgroundColor: 'rgba(245,158,11,.12)',
          pointBackgroundColor: '#f59e0b',
          tension: 0.35,
          fill: true,
          borderWidth: 2,
          spanGaps: true
        },
        {
          label: 'Humidity (%)',
          data: humidity,
          borderColor: '#60a5fa',
          backgroundColor: 'rgba(96,165,250,.10)',
          pointBackgroundColor: '#60a5fa',
          tension: 0.35,
          fill: false,
          borderWidth: 2,
          spanGaps: true
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          labels: {
            color: '#cbd5e1'
          }
        },
        tooltip: {
          mode: 'index',
          intersect: false
        }
      },
      interaction: {
        mode: 'index',
        intersect: false
      },
      scales: {
        x: {
          ticks: { color: '#94a3b8', maxRotation: 0, autoSkip: true },
          grid: { color: 'rgba(255,255,255,.05)' }
        },
        y: {
          ticks: { color: '#94a3b8' },
          grid: { color: 'rgba(255,255,255,.05)' }
        }
      }
    }
  });
}

function viewBatchSummary(batchId){
  showLoader('Loading summary…');
  $.post('../ajax/user_batches.php', { action:'summary', batch_id: batchId }, function(res) {
    hideLoader();
    if (!res.success) {
      showToast(res.message || 'Could not load summary', 'error');
      return;
    }

    $('#bs_name').text(res.batch.batch_name || '—');
    $('#bs_meta').text(`${res.batch.incubator_name || '—'} · ${res.batch.egg_count || 0} eggs · ${res.batch.status || '—'}`);
    $('#bs_duration').text(formatDuration(res.session.duration_seconds));
    $('#bs_window').text(`${formatBatchDateTime(res.session.start_at)} → ${formatBatchDateTime(res.session.end_at)}`);
    $('#bs_temp').text(res.session.temperature.avg !== null ? `${res.session.temperature.avg.toFixed(2)}°C` : '—');
    $('#bs_temp_meta').text(`Min ${res.session.temperature.min ?? '—'}°C · Max ${res.session.temperature.max ?? '—'}°C · Logs ${res.session.temperature.count ?? 0}`);
    $('#bs_humidity').text(res.session.humidity.avg !== null ? `${res.session.humidity.avg.toFixed(2)}%` : '—');
    $('#bs_humidity_meta').text(`Min ${res.session.humidity.min ?? '—'}% · Max ${res.session.humidity.max ?? '—'}%`);
    $('#bs_swings').text(`${res.session.swing_count || 0}`);
    $('#bs_logs').text(`First log: ${formatBatchDateTime(res.session.first_log)} · Last log: ${formatBatchDateTime(res.session.last_log)}`);

    const statusLabel = res.batch.status === 'terminated' ? 'terminated' : (res.batch.status === 'completed' ? 'completed' : 'active');
    $('#bs_summary').text(`This batch is ${statusLabel}. Temperature averaged ${res.session.temperature.avg !== null ? res.session.temperature.avg.toFixed(2) + '°C' : 'no readings'}, humidity averaged ${res.session.humidity.avg !== null ? res.session.humidity.avg.toFixed(2) + '%' : 'no readings'}, and egg swings were recorded ${res.session.swing_count || 0} times.`);
    renderBatchSummaryChart(res.session.logs || []);

    new bootstrap.Modal(document.getElementById('batchSummaryModal')).show();
  }, 'json').fail(function() {
    hideLoader();
    showToast('Server error.', 'error');
  });
}

document.getElementById('batchSummaryModal').addEventListener('hidden.bs.modal', function() {
  destroyBatchSummaryChart();
});
</script>
<?php require_once 'footer.php'; ?>
