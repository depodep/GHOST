<?php
$pageTitle = 'Alerts';
$pageSubtitle = 'System alerts and notifications';
$activePage = 'alerts';
require_once 'header.php';
$pdo = getDB();
$alerts = $pdo->query("SELECT a.*, i.name as incubator_name FROM alerts a LEFT JOIN incubators i ON a.incubator_id=i.id ORDER BY a.created_at DESC")->fetchAll();
?>
<div class="d-flex justify-content-end mb-4">
  <button class="btn-outline-ghost" onclick="markAllRead()"><i class="fas fa-check-double me-2"></i>Mark All Read</button>
</div>
<div class="ghost-panel">
  <div class="ghost-panel-header"><span class="ghost-panel-title">🔔 All Alerts</span></div>
  <div class="ghost-panel-body p-0" id="alertList">
  <?php foreach($alerts as $a): ?>
    <div class="alert-row" data-id="<?= $a['id'] ?>" style="padding:16px 22px;border-bottom:1px solid rgba(255,255,255,.04);display:flex;align-items:flex-start;gap:14px;<?= !$a['is_read']?'background:rgba(245,166,35,.03);':'' ?>">
      <div style="font-size:1.3rem;margin-top:2px;"><?= severityIcon($a['severity']) ?></div>
      <div style="flex:1;">
        <div style="font-size:.9rem;color:white;font-weight:<?= !$a['is_read']?'600':'400' ?>;"><?= htmlspecialchars($a['message']) ?></div>
        <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:4px;"><?= $a['incubator_name']??'System' ?> · <?= date('M j, Y g:i a',strtotime($a['created_at'])) ?></div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge-<?= $a['severity']=='danger'?'error':($a['severity']=='warning'?'pending':'idle') ?>"><?= $a['severity'] ?></span>
        <?php if(!$a['is_read']): ?><button class="btn-outline-ghost" style="font-size:.72rem;padding:4px 10px;" onclick="markRead(<?= $a['id'] ?>)">Mark Read</button><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
</div>
<script>
function markRead(id){
  $.post('../ajax/get_alerts.php',{action:'mark_read',id},function(r){if(r.success)location.reload();},'json');
}
function markAllRead(){
  $.post('../ajax/get_alerts.php',{action:'mark_all_read'},function(r){if(r.success){showToast('All marked read');setTimeout(()=>location.reload(),700);}else showToast('Error','error');},'json');
}
</script>
<?php require_once 'footer.php'; ?>
