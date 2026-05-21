<?php
$pageTitle    = 'Manage Users';
$pageSubtitle = 'Add, edit and manage farm users';
$activePage   = 'users';
require_once 'header.php';
$pdo   = getDB();

$incubatorColumns = [];
foreach ($pdo->query("SHOW COLUMNS FROM incubators")->fetchAll(PDO::FETCH_ASSOC) as $col) {
  $incubatorColumns[$col['Field']] = true;
}
$assignmentColumn = isset($incubatorColumns['user_id']) ? 'user_id' : (isset($incubatorColumns['owner_id']) ? 'owner_id' : null);

if ($assignmentColumn !== null) {
  $users = $pdo->query(
    "SELECT u.*, GROUP_CONCAT(i.id ORDER BY i.id) AS assigned_incubator_ids,
            GROUP_CONCAT(i.name ORDER BY i.id SEPARATOR ', ') AS assigned_incubator_names,
            COUNT(i.id) AS assigned_incubator_count
     FROM users u
     LEFT JOIN incubators i ON i.{$assignmentColumn} = u.id
     GROUP BY u.id
     ORDER BY u.created_at DESC"
  )->fetchAll();
} else {
  $users = $pdo->query("SELECT *, '' AS assigned_incubator_ids, '' AS assigned_incubator_names, 0 AS assigned_incubator_count FROM users ORDER BY created_at DESC")->fetchAll();
}

$incubators = $assignmentColumn !== null
  ? $pdo->query("SELECT id, name, {$assignmentColumn} AS assigned_user_id FROM incubators ORDER BY name ASC")->fetchAll()
  : $pdo->query("SELECT id, name, NULL AS assigned_user_id FROM incubators ORDER BY name ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div></div>
  <button class="btn-ghost" id="btnOpenAdd"><i class="fas fa-plus me-2"></i>Add User</button>
</div>

<div class="ghost-panel">
  <div class="ghost-panel-header d-flex justify-content-between align-items-center">
    <span class="ghost-panel-title">👥 All Farm Users</span>
    <input type="text" id="searchUsers" class="form-control-ghost"
           placeholder="🔍 Search users…"
           style="width:220px;padding:8px 12px;font-size:0.84rem;">
  </div>
  <div class="ghost-panel-body p-0">
    <table class="ghost-table" id="usersTable">
      <thead>
        <tr>
          <th>#</th><th>Name</th><th>Email</th><th>Phone</th>
          <th>Assigned Incubators</th><th>Status</th><th>Joined</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <?php
          $s   = $u['status'];
          $cls = ($s === 'active') ? 'active' : (($s === 'suspended') ? 'error' : 'idle');
        ?>
        <tr>
          <td style="color:var(--ghost-muted);font-size:0.8rem;"><?= (int)$u['id'] ?></td>
          <td>
            <div style="font-weight:600;color:white;display:flex;align-items:center;gap:10px;">
              <div style="width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,#3b82f6,#6366f1);
                          display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem;color:white;">
                <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
              </div>
              <?= htmlspecialchars($u['full_name']) ?>
            </div>
          </td>
          <td style="color:var(--ghost-muted);font-size:0.85rem;"><?= htmlspecialchars($u['email']) ?></td>
          <td style="color:var(--ghost-muted);font-size:0.85rem;"><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
          <td style="font-size:0.82rem;color:var(--ghost-muted);">
            <?php if ((int)($u['assigned_incubator_count'] ?? 0) > 0): ?>
              <div style="font-weight:600;color:white;"><?= (int)$u['assigned_incubator_count'] ?> unit(s)</div>
              <div><?= htmlspecialchars((string)($u['assigned_incubator_names'] ?? '')) ?></div>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td><span class="badge-<?= $cls ?>"><?= htmlspecialchars($s) ?></span></td>
          <td style="color:var(--ghost-muted);font-size:0.82rem;"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
          <td>
            <div class="d-flex gap-2">
              <button class="btn-edit-ghost btn-edit-user"
                      data-id="<?= (int)$u['id'] ?>"
                      data-name="<?= htmlspecialchars($u['full_name'],    ENT_QUOTES) ?>"
                      data-email="<?= htmlspecialchars($u['email'],       ENT_QUOTES) ?>"
                      data-phone="<?= htmlspecialchars($u['phone'] ?? '', ENT_QUOTES) ?>"
                      data-status="<?= htmlspecialchars($u['status'],     ENT_QUOTES) ?>"
                      data-incubators="<?= htmlspecialchars((string)($u['assigned_incubator_ids'] ?? ''), ENT_QUOTES) ?>">
                <i class="fas fa-pen"></i>
              </button>
              <button class="btn-outline-ghost btn-reset-pass"
                      style="padding:6px 10px;font-size:.78rem;"
                      data-id="<?= (int)$u['id'] ?>"
                      data-status="<?= htmlspecialchars($u['status'], ENT_QUOTES) ?>"
                      data-name="<?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>"
                      title="Reset Password">
                <i class="fas fa-key"></i>
              </button>
              <button class="btn-danger-ghost btn-delete-user"
                      data-id="<?= (int)$u['id'] ?>"
                      data-name="<?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ADD USER MODAL -->
<div class="modal fade modal-ghost" id="addUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user-plus me-2" style="color:var(--ghost-amber)"></i>Add New User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding:24px;">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label-ghost">Full Name</label>
            <input type="text" class="form-control-ghost" id="add_name" placeholder="e.g. John Dela Cruz" autocomplete="off">
          </div>
          <div class="col-12">
            <label class="form-label-ghost">Email</label>
            <input type="email" class="form-control-ghost" id="add_email" placeholder="john@example.com" autocomplete="off">
          </div>
          <div class="col-md-6">
            <label class="form-label-ghost">Phone</label>
            <input type="text" class="form-control-ghost" id="add_phone" placeholder="09171234567" autocomplete="off">
          </div>
          <div class="col-md-6">
            <label class="form-label-ghost">Status</label>
            <select class="form-select-ghost" id="add_status">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="suspended">Suspended</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label-ghost">Password</label>
            <input type="password" class="form-control-ghost" id="add_password" placeholder="Minimum 6 characters" autocomplete="new-password">
          </div>
          <div class="col-12">
            <label class="form-label-ghost">Assign Incubators</label>
            <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:.8rem;color:var(--ghost-muted);">
              <input type="checkbox" id="add_only_unassigned"> Unassigned incubators only
            </label>
            <select class="form-select-ghost" id="add_incubators" multiple style="min-height:120px;">
              <?php foreach ($incubators as $inc): ?>
                <option value="<?= (int)$inc['id'] ?>" data-assigned-user="<?= $inc['assigned_user_id'] !== null ? (int)$inc['assigned_user_id'] : '' ?>"><?= htmlspecialchars($inc['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">Hold Ctrl (or Cmd) to select multiple incubators.</div>
          </div>
        </div>
        <div id="addUserError" style="display:none;margin-top:14px;padding:10px 14px;
             background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);
             border-radius:8px;color:#f87171;font-size:0.84rem;"></div>
      </div>
      <div class="modal-footer">
        <button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-ghost" id="addUserBtn"><i class="fas fa-save me-2"></i>Save User</button>
      </div>
    </div>
  </div>
</div>

<!-- EDIT USER MODAL -->
<div class="modal fade modal-ghost" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user-edit me-2" style="color:var(--ghost-blue)"></i>Edit User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding:24px;">
        <input type="hidden" id="edit_id">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label-ghost">Full Name</label>
            <input type="text" class="form-control-ghost" id="edit_name" autocomplete="off">
          </div>
          <div class="col-12">
            <label class="form-label-ghost">Email</label>
            <input type="email" class="form-control-ghost" id="edit_email" autocomplete="off">
          </div>
          <div class="col-md-6">
            <label class="form-label-ghost">Phone</label>
            <input type="text" class="form-control-ghost" id="edit_phone" autocomplete="off">
          </div>
          <div class="col-md-6">
            <label class="form-label-ghost">Status</label>
            <select class="form-select-ghost" id="edit_status">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="suspended">Suspended</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label-ghost">
              New Password
              <span style="color:var(--ghost-muted);text-transform:none;font-weight:400;">(leave blank to keep)</span>
            </label>
            <input type="password" class="form-control-ghost" id="edit_password"
                   placeholder="Leave blank to keep current" autocomplete="new-password">
          </div>
          <div class="col-12">
            <label class="form-label-ghost">Assign Incubators</label>
            <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:.8rem;color:var(--ghost-muted);">
              <input type="checkbox" id="edit_only_unassigned"> Unassigned incubators only
            </label>
            <select class="form-select-ghost" id="edit_incubators" multiple style="min-height:120px;">
              <?php foreach ($incubators as $inc): ?>
                <option value="<?= (int)$inc['id'] ?>" data-assigned-user="<?= $inc['assigned_user_id'] !== null ? (int)$inc['assigned_user_id'] : '' ?>"><?= htmlspecialchars($inc['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div style="font-size:.75rem;color:var(--ghost-muted);margin-top:6px;">Selected incubators will be assigned to this user.</div>
          </div>
        </div>
        <div id="editUserError" style="display:none;margin-top:14px;padding:10px 14px;
             background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);
             border-radius:8px;color:#f87171;font-size:0.84rem;"></div>
      </div>
      <div class="modal-footer">
        <button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-ghost" id="updateUserBtn"><i class="fas fa-save me-2"></i>Update User</button>
      </div>
    </div>
  </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal fade modal-ghost" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-body text-center" style="padding:32px 24px;">
        <div style="font-size:2.5rem;margin-bottom:16px;">⚠️</div>
        <div style="font-weight:700;color:white;margin-bottom:8px;">Delete User?</div>
        <div id="deleteUserName" style="color:var(--ghost-muted);font-size:0.88rem;margin-bottom:24px;"></div>
        <div style="text-align:left;margin-bottom:14px;">
          <label class="form-label-ghost" style="margin-bottom:6px;">Reassign Active Batches To</label>
          <select class="form-select-ghost" id="delete_reassign_user" style="width:100%;">
            <option value="">None (block delete if active batches exist)</option>
            <?php foreach ($users as $candidate): ?>
              <?php if (strtolower((string)$candidate['status']) === 'active'): ?>
                <option value="<?= (int)$candidate['id'] ?>"><?= htmlspecialchars($candidate['full_name']) ?></option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
          <div style="font-size:.74rem;color:var(--ghost-muted);margin-top:6px;">Quick action: if this user has scheduled/incubating batches, they will be reassigned before delete.</div>
        </div>
        <div id="deleteUserError" style="display:none;margin-bottom:12px;padding:10px 12px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);border-radius:8px;color:#f87171;font-size:0.82rem;"></div>
        <div class="d-flex gap-2 justify-content-center">
          <button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button>
          <button class="btn-danger-ghost" id="confirmDeleteBtn" style="padding:9px 20px;font-size:0.85rem;">
            <i class="fas fa-trash me-1"></i>Delete
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- RESET PASSWORD MODAL -->
<div class="modal fade modal-ghost" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-key me-2" style="color:var(--ghost-amber)"></i>Reset Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding:24px;">
        <input type="hidden" id="rp_id">
        <input type="hidden" id="rp_status">
        <div id="rp_user_name" style="font-size:.9rem;color:var(--ghost-muted);margin-bottom:12px;"></div>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label-ghost">New Password</label>
            <input type="password" class="form-control-ghost" id="rp_password" autocomplete="new-password" placeholder="Minimum 6 characters">
          </div>
          <div class="col-12">
            <label class="form-label-ghost">Confirm Password</label>
            <input type="password" class="form-control-ghost" id="rp_password_confirm" autocomplete="new-password" placeholder="Re-enter password">
          </div>
          <div class="col-12" id="rp_inactive_warning" style="display:none;">
            <label style="display:flex;align-items:center;gap:8px;font-size:.82rem;color:#f59e0b;">
              <input type="checkbox" id="rp_force_inactive_confirm"> I confirm reset for an inactive/suspended user.
            </label>
          </div>
        </div>
        <div id="resetPassError" style="display:none;margin-top:14px;padding:10px 14px;
             background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);
             border-radius:8px;color:#f87171;font-size:0.84rem;"></div>
      </div>
      <div class="modal-footer">
        <button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-ghost" id="resetPassBtn"><i class="fas fa-save me-2"></i>Reset Password</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  var addModal, editModal, deleteModal, resetPasswordModal;

  document.addEventListener('DOMContentLoaded', function () {

    /* Bootstrap modal instances — created once, reused every time */
    addModal    = new bootstrap.Modal(document.getElementById('addUserModal'),  { backdrop: 'static', keyboard: false });
    editModal   = new bootstrap.Modal(document.getElementById('editUserModal'), { backdrop: 'static', keyboard: false });
    deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'),   { backdrop: true,     keyboard: true  });
    resetPasswordModal = new bootstrap.Modal(document.getElementById('resetPasswordModal'), { backdrop: 'static', keyboard: false });

    /* Reset add-form when modal opens */
    document.getElementById('addUserModal').addEventListener('show.bs.modal', function () {
      document.getElementById('add_name').value     = '';
      document.getElementById('add_email').value    = '';
      document.getElementById('add_phone').value    = '';
      document.getElementById('add_status').value   = 'active';
      document.getElementById('add_password').value = '';
      clearMultiSelect('add_incubators');
      var addFilter = document.getElementById('add_only_unassigned');
      if (addFilter) addFilter.checked = false;
      applyIncubatorFilter('add');
      hideError('addUserError');
      resetBtn('addUserBtn', '<i class="fas fa-save me-2"></i>Save User');
    });

    /* Reset edit-error when modal opens */
    document.getElementById('editUserModal').addEventListener('show.bs.modal', function () {
      hideError('editUserError');
      resetBtn('updateUserBtn', '<i class="fas fa-save me-2"></i>Update User');
      applyIncubatorFilter('edit');
    });

    document.getElementById('resetPasswordModal').addEventListener('show.bs.modal', function () {
      document.getElementById('rp_password').value = '';
      document.getElementById('rp_password_confirm').value = '';
      document.getElementById('rp_force_inactive_confirm').checked = false;
      hideError('resetPassError');
      resetBtn('resetPassBtn', '<i class="fas fa-save me-2"></i>Reset Password');
    });

    document.getElementById('add_only_unassigned').addEventListener('change', function () { applyIncubatorFilter('add'); });
    document.getElementById('edit_only_unassigned').addEventListener('change', function () { applyIncubatorFilter('edit'); });

    /* Reset delete button when modal closes */
    document.getElementById('deleteModal').addEventListener('hidden.bs.modal', function () {
      var btn = document.getElementById('confirmDeleteBtn');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-trash me-1"></i>Delete';
      document.getElementById('delete_reassign_user').value = '';
      hideError('deleteUserError');
    });

    /* ── Open Add modal ── */
    document.getElementById('btnOpenAdd').addEventListener('click', function () {
      addModal.show();
    });

    /* ── Table button delegation ── */
    document.getElementById('usersTable').addEventListener('click', function (e) {
      var editBtn = e.target.closest('.btn-edit-user');
      if (editBtn) {
        document.getElementById('edit_id').value       = editBtn.dataset.id;
        document.getElementById('edit_name').value     = editBtn.dataset.name;
        document.getElementById('edit_email').value    = editBtn.dataset.email;
        document.getElementById('edit_phone').value    = editBtn.dataset.phone;
        document.getElementById('edit_status').value   = editBtn.dataset.status;
        document.getElementById('edit_password').value = '';
        setMultiSelectFromCsv('edit_incubators', editBtn.dataset.incubators || '');
        var editFilter = document.getElementById('edit_only_unassigned');
        if (editFilter) editFilter.checked = false;
        applyIncubatorFilter('edit');
        editModal.show();
        return;
      }
      var delBtn = e.target.closest('.btn-delete-user');
      if (delBtn) {
        document.getElementById('deleteUserName').textContent = 'This will remove: ' + delBtn.dataset.name;
        document.getElementById('confirmDeleteBtn').dataset.id = delBtn.dataset.id;
        var reassignSelect = document.getElementById('delete_reassign_user');
        if (reassignSelect) {
          reassignSelect.value = '';
          Array.from(reassignSelect.options).forEach(function(opt){
            opt.hidden = String(opt.value) === String(delBtn.dataset.id);
          });
        }
        deleteModal.show();
        return;
      }

      var resetBtnEl = e.target.closest('.btn-reset-pass');
      if (resetBtnEl) {
        document.getElementById('rp_id').value = resetBtnEl.dataset.id;
        document.getElementById('rp_status').value = resetBtnEl.dataset.status || '';
        document.getElementById('rp_user_name').textContent = 'User: ' + resetBtnEl.dataset.name;
        var st = String(resetBtnEl.dataset.status || '').toLowerCase();
        var warn = document.getElementById('rp_inactive_warning');
        if (warn) warn.style.display = (st === 'inactive' || st === 'suspended') ? 'block' : 'none';
        resetPasswordModal.show();
      }
    });

    /* ── Add User ── */
    document.getElementById('addUserBtn').addEventListener('click', function () {
      var name     = document.getElementById('add_name').value.trim();
      var email    = document.getElementById('add_email').value.trim();
      var phone    = document.getElementById('add_phone').value.trim();
      var status   = document.getElementById('add_status').value;
      var password = document.getElementById('add_password').value;

      hideError('addUserError');
      if (!name || !email || !password) { showError('addUserError', 'Name, email and password are required.'); return; }
      if (password.length < 6)          { showError('addUserError', 'Password must be at least 6 characters.'); return; }

      setLoading('addUserBtn', true);
      showLoader('Adding User…');
      $.ajax({
        url: '../ajax/admin_users.php', method: 'POST', dataType: 'json',
        data: { action: 'add', name: name, email: email, phone: phone, status: status, password: password, incubator_ids: getSelectedValues('add_incubators') },
        success: function (r) {
          hideLoader();
          if (r.success) {
            addModal.hide();
            showToast(r.message || 'User added successfully!');
            setTimeout(function () { location.reload(); }, 800);
          } else {
            showError('addUserError', r.message || 'Failed to add user.');
            setLoading('addUserBtn', false);
          }
        },
        error: function () {
          hideLoader();
          showError('addUserError', 'Server error — please try again.');
          setLoading('addUserBtn', false);
        }
      });
    });

    /* ── Update User ── */
    document.getElementById('updateUserBtn').addEventListener('click', function () {
      var id       = document.getElementById('edit_id').value;
      var name     = document.getElementById('edit_name').value.trim();
      var email    = document.getElementById('edit_email').value.trim();
      var phone    = document.getElementById('edit_phone').value.trim();
      var status   = document.getElementById('edit_status').value;
      var password = document.getElementById('edit_password').value;

      hideError('editUserError');
      if (!name || !email)             { showError('editUserError', 'Name and email are required.'); return; }
      if (password && password.length < 6) { showError('editUserError', 'New password must be at least 6 characters.'); return; }

      setLoading('updateUserBtn', true);
      showLoader('Updating User…');
      $.ajax({
        url: '../ajax/admin_users.php', method: 'POST', dataType: 'json',
        data: { action: 'update', id: id, name: name, email: email, phone: phone, status: status, password: password, incubator_ids: getSelectedValues('edit_incubators') },
        success: function (r) {
          hideLoader();
          if (r.success) {
            editModal.hide();
            showToast(r.message || 'User updated successfully!');
            setTimeout(function () { location.reload(); }, 800);
          } else {
            showError('editUserError', r.message || 'Failed to update user.');
            setLoading('updateUserBtn', false);
          }
        },
        error: function () {
          hideLoader();
          showError('editUserError', 'Server error — please try again.');
          setLoading('updateUserBtn', false);
        }
      });
    });

    /* ── Delete User ── */
    document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
      var id  = this.dataset.id;
      this.disabled = true;
      this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Deleting…';
      showLoader('Deleting User…');
      $.ajax({
        url: '../ajax/admin_users.php', method: 'POST', dataType: 'json',
        data: { action: 'delete', id: id, reassign_to_user_id: document.getElementById('delete_reassign_user').value || '' },
        success: function (r) {
          hideLoader();
          if (r.success) {
            deleteModal.hide();
            showToast(r.message || 'User deleted.');
            setTimeout(function () { location.reload(); }, 600);
          } else {
            showError('deleteUserError', r.message || 'Failed to delete user.');
            var btn = document.getElementById('confirmDeleteBtn');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-trash me-1"></i>Delete';
          }
        },
        error: function () {
          hideLoader();
          showError('deleteUserError', 'Server error — please try again.');
          var btn = document.getElementById('confirmDeleteBtn');
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-trash me-1"></i>Delete';
        }
      });
    });

    document.getElementById('resetPassBtn').addEventListener('click', function () {
      var id = document.getElementById('rp_id').value;
      var status = String(document.getElementById('rp_status').value || '').toLowerCase();
      var password = document.getElementById('rp_password').value;
      var confirm = document.getElementById('rp_password_confirm').value;
      var forceInactiveConfirm = document.getElementById('rp_force_inactive_confirm').checked ? '1' : '0';

      hideError('resetPassError');
      if (!password || password.length < 6) {
        showError('resetPassError', 'Password must be at least 6 characters.');
        return;
      }
      if (password !== confirm) {
        showError('resetPassError', 'Password confirmation does not match.');
        return;
      }
      if ((status === 'inactive' || status === 'suspended') && forceInactiveConfirm !== '1') {
        showError('resetPassError', 'This user is inactive/suspended. Please confirm the checkbox to proceed.');
        return;
      }

      setLoading('resetPassBtn', true);
      showLoader('Resetting Password…');
      $.ajax({
        url: '../ajax/admin_users.php', method: 'POST', dataType: 'json',
        data: { action: 'reset_password', id: id, password: password, force_inactive_confirm: forceInactiveConfirm },
        success: function (r) {
          hideLoader();
          if (r.success) {
            resetPasswordModal.hide();
            showToast(r.message || 'Password reset successfully!');
          } else {
            showError('resetPassError', r.message || 'Failed to reset password.');
            setLoading('resetPassBtn', false);
          }
        },
        error: function () {
          hideLoader();
          showError('resetPassError', 'Server error — please try again.');
          setLoading('resetPassBtn', false);
        }
      });
    });

    /* ── Search ── */
    document.getElementById('searchUsers').addEventListener('input', function () {
      var q = this.value.toLowerCase();
      document.querySelectorAll('#usersTable tbody tr').forEach(function (row) {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });

  }); /* end DOMContentLoaded */

  /* ── Helper functions ── */
  function showError(id, msg) {
    var el = document.getElementById(id);
    el.textContent  = msg;
    el.style.display = 'block';
  }
  function hideError(id) {
    var el = document.getElementById(id);
    el.textContent  = '';
    el.style.display = 'none';
  }
  function setLoading(btnId, on) {
    var btn = document.getElementById(btnId);
    if (on) {
      btn.disabled  = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving…';
    } else {
      if (btnId === 'addUserBtn') {
        resetBtn(btnId, '<i class="fas fa-save me-2"></i>Save User');
      } else if (btnId === 'updateUserBtn') {
        resetBtn(btnId, '<i class="fas fa-save me-2"></i>Update User');
      } else {
        resetBtn(btnId, '<i class="fas fa-save me-2"></i>Reset Password');
      }
    }
  }
  function resetBtn(btnId, html) {
    var btn = document.getElementById(btnId);
    btn.disabled  = false;
    btn.innerHTML = html;
  }

  function getSelectedValues(selectId) {
    var select = document.getElementById(selectId);
    if (!select) return [];
    return Array.from(select.options).filter(function (opt) { return opt.selected; }).map(function (opt) { return opt.value; });
  }

  function clearMultiSelect(selectId) {
    var select = document.getElementById(selectId);
    if (!select) return;
    Array.from(select.options).forEach(function (opt) { opt.selected = false; });
  }

  function setMultiSelectFromCsv(selectId, csv) {
    var select = document.getElementById(selectId);
    if (!select) return;
    var values = String(csv || '').split(',').map(function(v){ return v.trim(); }).filter(Boolean);
    Array.from(select.options).forEach(function (opt) {
      opt.selected = values.indexOf(String(opt.value)) !== -1;
    });
  }

  function applyIncubatorFilter(mode) {
    var selectId = mode === 'edit' ? 'edit_incubators' : 'add_incubators';
    var filterId = mode === 'edit' ? 'edit_only_unassigned' : 'add_only_unassigned';
    var select = document.getElementById(selectId);
    var filter = document.getElementById(filterId);
    if (!select || !filter) return;

    var currentUserId = mode === 'edit' ? String(document.getElementById('edit_id').value || '') : '';
    var onlyUnassigned = !!filter.checked;

    Array.from(select.options).forEach(function (opt) {
      var assignedUser = String(opt.dataset.assignedUser || '').trim();
      var keepVisible = true;
      if (onlyUnassigned) {
        keepVisible = assignedUser === '' || (mode === 'edit' && assignedUser === currentUserId);
      }
      opt.hidden = !keepVisible;
    });
  }

})();
</script>

<?php require_once 'footer.php'; ?>
