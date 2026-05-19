<?php
$pageTitle = 'Batches';
$pageSubtitle = 'All egg batches across all incubators';
$activePage = 'batches';
require_once 'header.php';
$pdo = getDB();
$batches = $pdo->query("SELECT b.*, i.name as incubator_name, u.full_name as user_name FROM batches b JOIN incubators i ON b.incubator_id=i.id JOIN users u ON b.user_id=u.id ORDER BY b.created_at DESC")->fetchAll();
$incubators = $pdo->query("SELECT id,name FROM incubators WHERE status='active'")->fetchAll();
$users = $pdo->query("SELECT id,full_name FROM users WHERE status='active'")->fetchAll();

function formatBatchDateTime(?string $date, ?string $time = null, ?string $notes = null): string {
  $scheduledStart = null;
  if (!empty($notes) && preg_match('/SCHEDULED_START:\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $notes, $matches)) {
    $scheduledStart = $matches[1];
  }

  $value = $scheduledStart ?: trim((string)$date . ' ' . trim((string)($time ?? '00:00:00')));
  $timestamp = strtotime($value);
  if (!$timestamp) {
    return '—';
  }

  return date('F j, Y H:i', $timestamp);
}

function displayBatchEggCount(array $batch): string {
  $eggCount = isset($batch['egg_count']) ? (int)$batch['egg_count'] : 0;
  if ($eggCount <= 0 && !empty($batch['batch_name']) && preg_match('/(\d+)\s*eggs/i', $batch['batch_name'], $matches)) {
    $eggCount = (int)$matches[1];
  }

  return (string)$eggCount;
}

// Compatibility wrappers used by dashboard and other pages
function extractBatchStartTimestamp($b) {
  if (!empty($b['notes']) && preg_match('/SCHEDULED_START:\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $b['notes'], $m)) {
    $ts = strtotime($m[1]);
    if ($ts !== false) return $ts;
  }
  if (!empty($b['session_start_time'])) {
    $ts = strtotime($b['session_start_time']);
    if ($ts !== false) return $ts;
  }
  if (!empty($b['start_date'])) {
    $ts = strtotime($b['start_date'] . ' 00:00:00');
    if ($ts !== false) return $ts;
  }
  return null;
}

function displayEggCountForRow($b) {
  $val = displayBatchEggCount($b);
  $n = is_numeric($val) ? (int)$val : 0;
  return $n > 0 ? number_format($n) : '—';
}

function batchStatusTimeLabel(array $batch): string {
  $status = isset($batch['status']) ? strtolower(trim((string)$batch['status'])) : '';

  if ($status === 'scheduled') {
    return formatBatchDateTime($batch['start_date'] ?? null, null, $batch['notes'] ?? null);
  }

  if ($status === 'completed' && !empty($batch['completed_at'])) {
    return date('F j, Y H:i', strtotime($batch['completed_at']));
  }

  if (in_array($status, ['terminated', 'cancelled', 'failed'], true)) {
    if (!empty($batch['terminated_at'])) {
      return date('F j, Y H:i', strtotime($batch['terminated_at']));
    }
    if (!empty($batch['completed_at'])) {
      return date('F j, Y H:i', strtotime($batch['completed_at']));
    }
    if (!empty($batch['notes'])) {
      $scheduled = extractBatchStartTimestamp($batch);
      if ($scheduled) {
        return date('F j, Y H:i', $scheduled);
      }
    }
  }

  return '—';
}
?>
<div class="d-flex justify-content-end mb-4">
  <button class="btn-ghost" data-bs-toggle="modal" data-bs-target="#addBatchModal"><i class="fas fa-plus me-2"></i>Add Batch</button>
</div>
<div class="ghost-panel">
  <div class="ghost-panel-header"><span class="ghost-panel-title">📦 All Batches</span></div>
  <div class="ghost-panel-body p-0">
    <table class="ghost-table">
      <thead><tr><th>Batch</th><th>User</th><th>Incubator</th><th>Eggs</th><th>Start</th><th>Hatch Day</th><th>Status</th><th>Status Time</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($batches as $b): $days=max(0,round((strtotime($b['expected_hatch_date'])-time())/86400));
        $startTime = formatBatchDateTime($b['start_date'] ?? null, null, $b['notes'] ?? null);
        $statusTimeDisplay = batchStatusTimeLabel($b);
      ?>
        <tr>
          <td><div style="font-weight:600;color:white;"><?= htmlspecialchars($b['batch_name']) ?></div></td>
          <td style="font-size:.85rem;color:var(--ghost-muted);"><?= htmlspecialchars($b['user_name']) ?></td>
          <td style="font-size:.85rem;"><?= htmlspecialchars($b['incubator_name']) ?></td>
          <td style="font-weight:600;"><?= htmlspecialchars(displayBatchEggCount($b)) ?></td>
          <td style="font-size:.82rem;color:var(--ghost-muted);"><?= htmlspecialchars($startTime) ?></td>
          <td><div style="font-size:.85rem;"><?= date('M j, Y',strtotime($b['expected_hatch_date'])) ?></div><?php if($b['status']=='incubating'): ?><div style="font-size:.72rem;color:<?= $days<=3?'#f87171':'var(--ghost-muted)' ?>;"><?= $days>0?$days.' days':'Today!' ?></div><?php endif; ?></td>
          <td><span class="badge-<?= $b['status'] ?>"><?= $b['status'] ?></span></td>
          <td style="font-size:.82rem;color:var(--ghost-muted);">
            <?= htmlspecialchars($statusTimeDisplay) ?>
          </td>
          <td><div class="d-flex gap-2">
            <?php if ($b['status'] === 'scheduled'): ?>
              <button class="btn-danger-ghost" onclick="cancelBatch2(<?= $b['id'] ?>)"><i class="fas fa-ban"></i></button>
            <?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ADD -->
<div class="modal fade modal-ghost" id="addBatchModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus-circle me-2" style="color:var(--ghost-amber)"></i>Add Batch</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" style="padding:24px;"><div class="row g-3">
        <div class="col-12"><label class="form-label-ghost">Batch Name</label><input type="text" class="form-control-ghost" id="ab2_name" placeholder="e.g. Batch Delta-01"></div>
        <div class="col-md-6"><label class="form-label-ghost">Assign to User</label><select class="form-select-ghost" id="ab2_user"><?php foreach($users as $u): ?><option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label-ghost">Incubator</label><select class="form-select-ghost" id="ab2_incubator"><?php foreach($incubators as $i): ?><option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label-ghost">Egg Count</label><input type="number" class="form-control-ghost" id="ab2_count" placeholder="50"></div>
        <div class="col-md-4"><label class="form-label-ghost">Start Date</label><input type="date" class="form-control-ghost" id="ab2_start" value="<?= date('Y-m-d') ?>"></div>
        <div class="col-md-6"><label class="form-label-ghost">Hatch Date</label><input type="date" class="form-control-ghost" id="ab2_hatch" value="<?= date('Y-m-d',strtotime('+21 days')) ?>"></div>
        <div class="col-md-6"><label class="form-label-ghost">Notes</label><input type="text" class="form-control-ghost" id="ab2_notes" placeholder="Optional notes"></div>
      </div></div>
      <div class="modal-footer"><button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn-ghost" onclick="addBatch2()"><i class="fas fa-save me-2"></i>Save</button></div>
    </div>
  </div>
</div>

<!-- EDIT -->
<div class="modal fade modal-ghost" id="editBatchModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit Batch</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" style="padding:24px;"><input type="hidden" id="eb2_id">
        <div class="row g-3">
          <div class="col-12"><label class="form-label-ghost">Batch Name</label><input type="text" class="form-control-ghost" id="eb2_name"></div>
          <div class="col-md-6"><label class="form-label-ghost">Egg Count</label><input type="number" class="form-control-ghost" id="eb2_count"></div>
          <div class="col-md-6"><label class="form-label-ghost">Expected Hatch</label><input type="date" class="form-control-ghost" id="eb2_hatch"></div>
          <div class="col-md-6"><label class="form-label-ghost">Status</label><select class="form-select-ghost" id="eb2_status"><option value="incubating">Incubating</option><option value="completed">Completed</option><option value="terminated">Terminated</option><option value="hatched">Hatched</option><option value="failed">Failed</option><option value="cancelled">Cancelled</option></select></div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn-ghost" onclick="updateBatch2()"><i class="fas fa-save me-2"></i>Update</button></div>
    </div>
  </div>
</div>

<script>
function addBatch2(){
  showLoader('Adding Batch…');
  $.ajax({url:'../ajax/admin_batches.php',method:'POST',data:{action:'add',name:$('#ab2_name').val(),user_id:$('#ab2_user').val(),incubator_id:$('#ab2_incubator').val(),egg_count:$('#ab2_count').val(),start_date:$('#ab2_start').val(),hatch_date:$('#ab2_hatch').val(),notes:$('#ab2_notes').val()},dataType:'json',
    success:r=>{hideLoader();if(r.success){showToast('Batch added!');setTimeout(()=>location.reload(),700);}else showToast(r.message,'error');},
    error:()=>{hideLoader();showToast('Server error.','error');}
  });
}
function editBatch(b){$('#eb2_id').val(b.id);$('#eb2_name').val(b.batch_name);$('#eb2_count').val(b.egg_count);$('#eb2_hatch').val(b.expected_hatch_date);$('#eb2_status').val(b.status);new bootstrap.Modal(document.getElementById('editBatchModal')).show();}
function updateBatch2(){
  showLoader('Updating Batch…');
  $.ajax({url:'../ajax/admin_batches.php',method:'POST',data:{action:'update',id:$('#eb2_id').val(),name:$('#eb2_name').val(),egg_count:$('#eb2_count').val(),hatch_date:$('#eb2_hatch').val(),status:$('#eb2_status').val()},dataType:'json',
    success:r=>{hideLoader();if(r.success){showToast('Updated!');setTimeout(()=>location.reload(),700);}else showToast(r.message,'error');},
    error:()=>{hideLoader();showToast('Server error.','error');}
  });
}
function cancelBatch2(id){
  showConfirm('Cancel batch', 'Mark this batch as cancelled? This will not delete the record.', function(){
    showLoader('Cancelling…');
    $.post('../ajax/admin_batches.php',{action:'update',id:id,status:'cancelled'},function(r){
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
</script>
<?php require_once 'footer.php'; ?>
