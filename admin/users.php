<?php
$pageTitle    = 'Manage Users';
$pageSubtitle = 'Add, edit and manage farm users';
$activePage   = 'users';
require_once 'header.php';
$pdo   = getDB();
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
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
          <th>Status</th><th>Joined</th><th>Actions</th>
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
          <td><span class="badge-<?= $cls ?>"><?= htmlspecialchars($s) ?></span></td>
          <td style="color:var(--ghost-muted);font-size:0.82rem;"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
          <td>
            <div class="d-flex gap-2">
              <button class="btn-edit-ghost btn-edit-user"
                      data-id="<?= (int)$u['id'] ?>"
                      data-name="<?= htmlspecialchars($u['full_name'],    ENT_QUOTES) ?>"
                      data-email="<?= htmlspecialchars($u['email'],       ENT_QUOTES) ?>"
                      data-phone="<?= htmlspecialchars($u['phone'] ?? '', ENT_QUOTES) ?>"
                      data-status="<?= htmlspecialchars($u['status'],     ENT_QUOTES) ?>">
                <i class="fas fa-pen"></i>
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

<script>
(function () {
  'use strict';

  var addModal, editModal, deleteModal;

  document.addEventListener('DOMContentLoaded', function () {

    /* Bootstrap modal instances — created once, reused every time */
    addModal    = new bootstrap.Modal(document.getElementById('addUserModal'),  { backdrop: 'static', keyboard: false });
    editModal   = new bootstrap.Modal(document.getElementById('editUserModal'), { backdrop: 'static', keyboard: false });
    deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'),   { backdrop: true,     keyboard: true  });

    /* Reset add-form when modal opens */
    document.getElementById('addUserModal').addEventListener('show.bs.modal', function () {
      document.getElementById('add_name').value     = '';
      document.getElementById('add_email').value    = '';
      document.getElementById('add_phone').value    = '';
      document.getElementById('add_status').value   = 'active';
      document.getElementById('add_password').value = '';
      hideError('addUserError');
      resetBtn('addUserBtn', '<i class="fas fa-save me-2"></i>Save User');
    });

    /* Reset edit-error when modal opens */
    document.getElementById('editUserModal').addEventListener('show.bs.modal', function () {
      hideError('editUserError');
      resetBtn('updateUserBtn', '<i class="fas fa-save me-2"></i>Update User');
    });

    /* Reset delete button when modal closes */
    document.getElementById('deleteModal').addEventListener('hidden.bs.modal', function () {
      var btn = document.getElementById('confirmDeleteBtn');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-trash me-1"></i>Delete';
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
        editModal.show();
        return;
      }
      var delBtn = e.target.closest('.btn-delete-user');
      if (delBtn) {
        document.getElementById('deleteUserName').textContent = 'This will remove: ' + delBtn.dataset.name;
        document.getElementById('confirmDeleteBtn').dataset.id = delBtn.dataset.id;
        deleteModal.show();
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
        data: { action: 'add', name: name, email: email, phone: phone, status: status, password: password },
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
        data: { action: 'update', id: id, name: name, email: email, phone: phone, status: status, password: password },
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
        data: { action: 'delete', id: id },
        success: function (r) {
          hideLoader();
          deleteModal.hide();
          if (r.success) {
            showToast(r.message || 'User deleted.');
            setTimeout(function () { location.reload(); }, 600);
          } else {
            showToast(r.message || 'Failed to delete user.', 'error');
          }
        },
        error: function () {
          deleteModal.hide();
          showToast('Server error — please try again.', 'error');
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
      resetBtn(btnId, btnId === 'addUserBtn'
        ? '<i class="fas fa-save me-2"></i>Save User'
        : '<i class="fas fa-save me-2"></i>Update User');
    }
  }
  function resetBtn(btnId, html) {
    var btn = document.getElementById(btnId);
    btn.disabled  = false;
    btn.innerHTML = html;
  }

})();
</script>

<?php require_once 'footer.php'; ?>
