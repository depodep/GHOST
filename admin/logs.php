<?php
$pageTitle = 'Activity Logs';
$pageSubtitle = 'System activity history';
$activePage = 'logs';
require_once 'header.php';
$pdo = getDB();
$logs = $pdo->query("SELECT l.*, CASE WHEN l.role='admin' THEN a.full_name ELSE u.full_name END as actor_name FROM activity_logs l LEFT JOIN admins a ON l.role='admin' AND l.user_id=a.id LEFT JOIN users u ON l.role='user' AND l.user_id=u.id ORDER BY l.logged_at DESC LIMIT 100")->fetchAll();
?>
<div class="ghost-panel">
  <div class="ghost-panel-header"><span class="ghost-panel-title">📋 Recent Activity (Last 100)</span></div>
  <div class="ghost-panel-body p-0">
    <table class="ghost-table">
      <thead><tr><th>Time</th><th>Actor</th><th>Role</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach($logs as $l): ?>
        <tr>
          <td style="font-size:.78rem;color:var(--ghost-muted);white-space:nowrap;"><?= date('M j H:i',strtotime($l['logged_at'])) ?></td>
          <td style="font-weight:600;font-size:.85rem;"><?= htmlspecialchars($l['actor_name']??'Unknown') ?></td>
          <td><span class="<?= $l['role']=='admin'?'badge-maintenance':'badge-incubating' ?>"><?= $l['role'] ?></span></td>
          <td style="font-size:.85rem;color:white;"><?= htmlspecialchars($l['action']) ?></td>
          <td style="font-size:.8rem;color:var(--ghost-muted);"><?= htmlspecialchars($l['details']??'') ?></td>
          <td style="font-size:.75rem;color:var(--ghost-muted);"><?= $l['ip_address'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once 'footer.php'; ?>
