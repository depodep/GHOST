<?php
require_once '../includes/config.php';
requireAdmin();
$pageTitle = 'Batches';
$pageSubtitle = 'Incubator batches and scheduled runs';
$activePage = 'batches';
$pdo = getDB();

$incubatorFilter = (int)($_GET['incubator_id'] ?? 0);
$statusFilter = trim((string)($_GET['status'] ?? ''));

require_once 'header.php';

$batches = $pdo->query(
  "SELECT b.*, COALESCE(i.incubator_name, i.name, CONCAT('Incubator #', b.incubator_id)) as incubator_name,
          COALESCE(u.full_name, CONCAT('User #', b.user_id)) as user_name,
          hs.device_status,
          hs.last_seen
   FROM batches b
   LEFT JOIN incubators i ON b.incubator_id=i.id
   LEFT JOIN users u ON b.user_id=u.id
   LEFT JOIN hardware_state hs ON hs.incubator_id = b.incubator_id
   ORDER BY b.created_at DESC"
)->fetchAll();
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
<div class="ghost-panel mb-3">
  <div class="ghost-panel-body" style="padding:16px 20px;">
    <div class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label-ghost">Filter By Incubator</label>
        <select class="form-select-ghost" id="batchIncubatorFilter">
          <option value="">All incubators</option>
          <?php foreach($incubators as $i): ?>
            <option value="<?= (int)$i['id'] ?>" <?= $incubatorFilter === (int)$i['id'] ? 'selected' : '' ?>><?= htmlspecialchars($i['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label-ghost">Filter By Status</label>
        <select class="form-select-ghost" id="batchStatusFilter">
          <option value="">All status</option>
          <?php foreach(['scheduled','incubating','completed','terminated','hatched','failed','cancelled'] as $status): ?>
            <option value="<?= $status ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 d-flex gap-2">
        <button class="btn-ghost" type="button" onclick="applyBatchFilters()"><i class="fas fa-filter me-2"></i>Apply</button>
        <button class="btn-outline-ghost" type="button" onclick="clearBatchFilters()">Reset</button>
      </div>
    </div>
  </div>
</div>
<div class="ghost-panel">
  <div class="ghost-panel-header"><span class="ghost-panel-title">📦 All Batches</span></div>
  <div class="ghost-panel-body p-0">
    <table class="ghost-table">
      <thead><tr><th>Batch</th><th>User</th><th>Incubator</th><th>Eggs</th><th>Start</th><th>Hatch Day</th><th>Status</th><th>Status Time</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($batches as $b):
        if ($incubatorFilter > 0 && (int)$b['incubator_id'] !== $incubatorFilter) { continue; }
        if ($statusFilter !== '' && (string)$b['status'] !== $statusFilter) { continue; }
        $days=max(0,round((strtotime($b['expected_hatch_date'])-time())/86400));
        $startTime = formatBatchDateTime($b['start_date'] ?? null, null, $b['notes'] ?? null);
        $statusTimeDisplay = batchStatusTimeLabel($b);
      ?>
        <tr data-incubator-id="<?= (int)$b['incubator_id'] ?>" data-status="<?= htmlspecialchars((string)$b['status']) ?>">
          <td><div style="font-weight:600;color:white;"><?= htmlspecialchars($b['batch_name']) ?></div></td>
          <td style="font-size:.85rem;color:var(--ghost-muted);"><?= htmlspecialchars($b['user_name']) ?></td>
          <td style="font-size:.85rem;"><?= htmlspecialchars($b['incubator_name']) ?></td>
          <td style="font-weight:600;"><?= htmlspecialchars(displayBatchEggCount($b)) ?></td>
          <td style="font-size:.82rem;color:var(--ghost-muted);"><?= htmlspecialchars($startTime) ?></td>
          <td><div style="font-size:.85rem;"><?= date('M j, Y',strtotime($b['expected_hatch_date'])) ?></div><?php if($b['status']=='incubating'): ?><div style="font-size:.72rem;color:<?= $days<=3?'#f87171':'var(--ghost-muted)' ?>;"><?= $days>0?$days.' days':'Today!' ?></div><?php endif; ?></td>
          <td>
            <?php
              $waitingForDevice = !empty($b['waiting_for_device_until']) && strtotime($b['waiting_for_device_until']) > time();
              if ($waitingForDevice):
            ?>
              <span class="badge-running">waiting_for_device</span>
            <?php else: ?>
              <span class="badge-<?= $b['status'] ?>"><?= $b['status'] ?></span>
            <?php endif; ?>
          </td>
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

function applyBatchFilters(){
  const inc = document.getElementById('batchIncubatorFilter').value;
  const status = document.getElementById('batchStatusFilter').value;
  const params = new URLSearchParams(window.location.search);
  if (inc) params.set('incubator_id', inc); else params.delete('incubator_id');
  if (status) params.set('status', status); else params.delete('status');
  const q = params.toString();
  window.location.href = 'batches.php' + (q ? ('?' + q) : '');
}

function clearBatchFilters(){
  window.location.href = 'batches.php';
}
</script>
<?php require_once 'footer.php'; ?>
