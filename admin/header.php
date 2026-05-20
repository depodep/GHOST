<?php require_once '../includes/config.php'; requireAdmin(); $admin = getCurrentAdmin(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?? 'Dashboard' ?> — GHOST Admin</title>
<link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
<link href="../assets/vendor/fontawesome/all.min.css" rel="stylesheet">
<script src="../assets/vendor/jquery/jquery-3.6.0.min.js"></script>
<script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<style>
/* ── SIDEBAR COLLAPSE ── */
:root{
  --ghost-dark:#0a0c10;--ghost-darker:#060709;--ghost-card:#111318;--ghost-card2:#161920;
  --ghost-border:rgba(255,255,255,0.07);--ghost-amber:#f5a623;--ghost-amber-dim:rgba(245,166,35,0.12);
  --ghost-amber-glow:rgba(245,166,35,0.35);--ghost-green:#22c55e;--ghost-red:#ef4444;--ghost-blue:#3b82f6;
  --ghost-text:#e2e8f0;--ghost-muted:#64748b;--ghost-gradient:linear-gradient(135deg,#f5a623 0%,#f97316 50%,#ef4444 100%);
  --sidebar-w:260px;--sidebar-collapsed-w:0px;
  --transition-page: cubic-bezier(0.22,1,0.36,1);
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Space Grotesk',sans-serif;background:var(--ghost-dark);color:var(--ghost-text);display:flex;min-height:100vh;}

/* ── PAGE TRANSITIONS ── */
@keyframes pageEnter{from{opacity:0;}to{opacity:1;}}
@keyframes pageOut{to{opacity:0;}}
@keyframes ripple{from{transform:scale(0);opacity:0.4}to{transform:scale(4);opacity:0}}
@keyframes slideIn{from{transform:translateX(60px);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}

.page-content{animation:pageEnter 0.35s ease both;}
.page-out .page-content{animation:pageOut 0.25s ease forwards;}

/* Ripple on nav links */
.nav-link-ghost{
  display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:10px;
  color:var(--ghost-muted);text-decoration:none;font-size:0.88rem;font-weight:500;
  transition:all 0.3s var(--transition-page);position:relative;
}
.nav-link-ghost::after{
  content:'';position:absolute;inset:50% 50%;width:10px;height:10px;border-radius:50%;
  background:rgba(245,166,35,0.25);transform:scale(0);opacity:0;pointer-events:none;
}
.nav-link-ghost:active::after{animation:ripple 0.5s ease-out forwards;inset:auto;}
.nav-link-ghost:hover,.nav-link-ghost.active{
  background:var(--ghost-amber-dim);color:var(--ghost-amber);transform:translateX(3px);
}
.nav-link-ghost.active::before{
  content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);
  width:3px;height:60%;background:var(--ghost-amber);border-radius:0 3px 3px 0;
  transition:height 0.3s var(--transition-page);pointer-events:none;
}
.nav-link-ghost .nav-icon{width:20px;text-align:center;font-size:0.9rem;transition:transform 0.3s var(--transition-page);}
.nav-link-ghost:hover .nav-icon{transform:scale(1.15);}
.badge-count{margin-left:auto;background:var(--ghost-amber);color:#000;font-size:0.65rem;font-weight:700;padding:2px 7px;border-radius:100px;}

/* ── SIDEBAR PULL ARROW (collapse toggle) ── */
#sidebarPull{
  position:fixed;left:var(--sidebar-w);top:50%;transform:translateY(-50%);
  z-index:201;width:18px;height:56px;
  background:var(--ghost-darker);border:1px solid var(--ghost-border);
  border-left:none;border-radius:0 8px 8px 0;
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;transition:all 0.35s var(--transition-page);
  color:var(--ghost-muted);font-size:0.6rem;
}
#sidebarPull:hover{background:var(--ghost-amber-dim);color:var(--ghost-amber);width:22px;}
#sidebarPull i{transition:transform 0.35s var(--transition-page);}
body.sidebar-collapsed #sidebarPull{left:0;}
body.sidebar-collapsed #sidebarPull i{transform:rotate(180deg);}

/* ── SIDEBAR COLLAPSED STATE ── */
#sidebar{
  width:var(--sidebar-w);min-width:var(--sidebar-w);height:100vh;position:fixed;top:0;left:0;z-index:200;
  background:var(--ghost-darker);border-right:1px solid var(--ghost-border);
  display:flex;flex-direction:column;
  transition:width 0.35s var(--transition-page), min-width 0.35s var(--transition-page), transform 0.35s var(--transition-page);
  overflow:hidden;
}
#sidebar::-webkit-scrollbar{width:4px;}
#sidebar::-webkit-scrollbar-track{background:transparent;}
#sidebar::-webkit-scrollbar-thumb{background:var(--ghost-border);border-radius:2px;}

body.sidebar-collapsed #sidebar{width:0;min-width:0;}
body.sidebar-collapsed #main{margin-left:0;}

/* Sidebar inner content fade when collapsing */
#sidebar .sidebar-inner{
  width:var(--sidebar-w);min-width:var(--sidebar-w);
  display:flex;flex-direction:column;height:100%;overflow-y:auto;
  transition:opacity 0.2s ease, transform 0.35s var(--transition-page);
}
body.sidebar-collapsed #sidebar .sidebar-inner{opacity:0;pointer-events:none;transform:translateX(-20px);}

.sidebar-brand{
  padding:24px 20px;border-bottom:1px solid var(--ghost-border);
  font-family:'Bebas Neue',sans-serif;font-size:1.8rem;letter-spacing:0.12em;
  background:var(--ghost-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
  display:flex;align-items:center;gap:10px;flex-shrink:0;
}
.sidebar-brand-sub{font-family:'Space Grotesk',sans-serif;font-size:0.65rem;font-weight:600;color:var(--ghost-muted);letter-spacing:0.1em;text-transform:uppercase;margin-top:-4px;display:block;-webkit-text-fill-color:var(--ghost-muted);}
.sidebar-section{padding:20px 16px 8px;font-size:0.68rem;font-weight:700;color:var(--ghost-muted);letter-spacing:0.18em;text-transform:uppercase;flex-shrink:0;}
.nav-item{margin:2px 10px;flex-shrink:0;}

.sidebar-user{
  padding:16px;border-top:1px solid var(--ghost-border);margin-top:auto;
  display:flex;align-items:center;gap:12px;flex-shrink:0;
}
.user-avatar{
  width:38px;height:38px;border-radius:10px;background:var(--ghost-gradient);
  display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.9rem;color:#000;flex-shrink:0;
  box-shadow:inset 0 1px 0 rgba(255,255,255,0.2);transition:transform 0.25s var(--transition-page);
}
.user-avatar:hover{transform:scale(1.08);}
.user-name{font-size:0.85rem;font-weight:600;color:var(--ghost-text);line-height:1.2;}
.user-role{font-size:0.72rem;color:var(--ghost-amber);}
.logout-btn{
  margin-left:auto;width:32px;height:32px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);
  border-radius:8px;display:flex;align-items:center;justify-content:center;color:#ef4444;cursor:pointer;
  transition:all 0.25s var(--transition-page);text-decoration:none;flex-shrink:0;
}
.logout-btn:hover{background:rgba(239,68,68,0.2);color:#ef4444;transform:scale(1.1);}

/* ── MAIN ── */
#main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left 0.35s var(--transition-page);}
.topbar{
  padding:16px 28px;background:var(--ghost-darker);border-bottom:1px solid var(--ghost-border);
  display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:100;
}
.topbar-title{font-weight:700;font-size:1.1rem;color:white;}
.topbar-sub{font-size:0.8rem;color:var(--ghost-muted);margin-top:2px;}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:12px;}
.alert-bell{
  position:relative;width:38px;height:38px;background:var(--ghost-card);border:1px solid var(--ghost-border);
  border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;
  transition:all 0.3s var(--transition-page);color:var(--ghost-muted);
}
.alert-bell:hover{border-color:var(--ghost-amber);color:var(--ghost-amber);transform:scale(1.08);}
.bell-dot{position:absolute;top:6px;right:6px;width:8px;height:8px;background:var(--ghost-red);border-radius:50%;border:2px solid var(--ghost-darker);}

/* Live clock */
.topbar-clock{
  display:flex;flex-direction:column;align-items:flex-end;gap:1px;
  background:var(--ghost-card);border:1px solid var(--ghost-border);border-radius:10px;
  padding:6px 12px;min-width:110px;
}
.clock-time{font-family:'Bebas Neue',sans-serif;font-size:1.1rem;letter-spacing:0.08em;color:var(--ghost-amber);line-height:1;}
.clock-date{font-size:0.67rem;font-weight:600;color:var(--ghost-muted);letter-spacing:0.06em;text-transform:uppercase;}

.page-content{padding:28px;flex:1;}

/* ── CARDS ── */
.stat-card{
  background:var(--ghost-card);border:1px solid var(--ghost-border);border-radius:14px;padding:22px;
  position:relative;overflow:hidden;transition:all 0.35s var(--transition-page);
}
.stat-card:hover{border-color:rgba(245,166,35,0.2);transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,0,0,0.3);}
.stat-card::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,0.02) 0%,transparent 60%);pointer-events:none;}
.stat-label{font-size:0.75rem;font-weight:600;color:var(--ghost-muted);letter-spacing:0.1em;text-transform:uppercase;margin-bottom:8px;}
.stat-val{font-family:'Bebas Neue',sans-serif;font-size:2.6rem;line-height:1;color:white;}
.stat-val.amber{background:var(--ghost-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.stat-badge{display:inline-flex;align-items:center;gap:4px;font-size:0.75rem;font-weight:600;padding:3px 8px;border-radius:6px;margin-top:8px;}
.stat-badge.up{background:rgba(34,197,94,0.1);color:var(--ghost-green);}
.stat-badge.down{background:rgba(239,68,68,0.1);color:var(--ghost-red);}
.stat-icon{position:absolute;right:18px;top:18px;font-size:1.8rem;opacity:0.12;}

/* ── DATA TABLE ── */
.ghost-table{width:100%;border-collapse:collapse;}
.ghost-table th{font-size:0.72rem;font-weight:700;color:var(--ghost-muted);letter-spacing:0.1em;text-transform:uppercase;padding:12px 16px;border-bottom:1px solid var(--ghost-border);white-space:nowrap;}
.ghost-table td{padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.88rem;vertical-align:middle;transition:background 0.2s ease;}
.ghost-table tr:hover td{background:rgba(255,255,255,0.03);}
.ghost-table tr:last-child td{border-bottom:none;}

/* ── BUTTONS ── */
.btn-ghost{
  background:var(--ghost-gradient);border:none;color:white;font-family:'Space Grotesk',sans-serif;
  font-weight:700;font-size:0.82rem;letter-spacing:0.06em;text-transform:uppercase;padding:10px 20px;
  border-radius:8px;cursor:pointer;transition:all 0.3s var(--transition-page);
  box-shadow:0 3px 16px var(--ghost-amber-glow),inset 0 1px 0 rgba(255,255,255,0.2),inset 0 -1px 0 rgba(0,0,0,0.2);
  position:relative;overflow:hidden;
}
.btn-ghost::before{content:'';position:absolute;inset:0;background:linear-gradient(180deg,rgba(255,255,255,0.12) 0%,transparent 50%);pointer-events:none;}
.btn-ghost:hover{transform:translateY(-2px);box-shadow:0 8px 28px var(--ghost-amber-glow);}
.btn-ghost:active{transform:translateY(0px);}
.btn-ghost-sm{padding:7px 14px;font-size:0.75rem;}
.btn-outline-ghost{
  background:transparent;border:1px solid var(--ghost-border);color:var(--ghost-muted);
  font-family:'Space Grotesk',sans-serif;font-weight:600;font-size:0.82rem;letter-spacing:0.05em;
  padding:9px 18px;border-radius:8px;cursor:pointer;transition:all 0.3s var(--transition-page);
  box-shadow:inset 0 1px 0 rgba(255,255,255,0.05);
}
.btn-outline-ghost:hover{border-color:var(--ghost-amber);color:var(--ghost-amber);background:var(--ghost-amber-dim);transform:translateY(-1px);}
.btn-danger-ghost{
  background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);color:#f87171;
  font-size:0.78rem;padding:6px 12px;border-radius:7px;cursor:pointer;transition:all 0.25s var(--transition-page);
  box-shadow:inset 0 1px 0 rgba(255,255,255,0.05);
}
.btn-danger-ghost:hover{background:rgba(239,68,68,0.2);transform:translateY(-1px);}
.btn-edit-ghost{
  background:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.25);color:#93c5fd;
  font-size:0.78rem;padding:6px 12px;border-radius:7px;cursor:pointer;transition:all 0.25s var(--transition-page);
  box-shadow:inset 0 1px 0 rgba(255,255,255,0.05);
}
.btn-edit-ghost:hover{background:rgba(59,130,246,0.2);transform:translateY(-1px);}

/* ── STATUS BADGES ── */
.badge-active{background:rgba(34,197,94,0.12);color:#4ade80;border:1px solid rgba(34,197,94,0.2);padding:3px 10px;border-radius:6px;font-size:0.72rem;font-weight:600;}
.badge-idle{background:rgba(100,116,139,0.15);color:#94a3b8;border:1px solid rgba(100,116,139,0.2);padding:3px 10px;border-radius:6px;font-size:0.72rem;font-weight:600;}
.badge-maintenance{background:rgba(245,166,35,0.12);color:var(--ghost-amber);border:1px solid rgba(245,166,35,0.2);padding:3px 10px;border-radius:6px;font-size:0.72rem;font-weight:600;}
.badge-error{background:rgba(239,68,68,0.12);color:#f87171;border:1px solid rgba(239,68,68,0.2);padding:3px 10px;border-radius:6px;font-size:0.72rem;font-weight:600;}
.badge-completed{background:rgba(34,197,94,0.12);color:#4ade80;border:1px solid rgba(34,197,94,0.2);padding:3px 10px;border-radius:6px;font-size:0.72rem;font-weight:600;}
.badge-terminated{background:rgba(239,68,68,0.12);color:#f87171;border:1px solid rgba(239,68,68,0.2);padding:3px 10px;border-radius:6px;font-size:0.72rem;font-weight:600;}
.badge-pending{background:rgba(245,166,35,0.12);color:var(--ghost-amber);border:1px solid rgba(245,166,35,0.2);padding:3px 10px;border-radius:6px;font-size:0.72rem;font-weight:600;}
.badge-done{background:rgba(34,197,94,0.12);color:#4ade80;border:1px solid rgba(34,197,94,0.2);padding:3px 10px;border-radius:6px;font-size:0.72rem;font-weight:600;}
.badge-incubating{background:rgba(59,130,246,0.12);color:#93c5fd;border:1px solid rgba(59,130,246,0.2);padding:3px 10px;border-radius:6px;font-size:0.72rem;font-weight:600;}

/* ── CARDS / PANELS ── */
.ghost-panel{background:var(--ghost-card);border:1px solid var(--ghost-border);border-radius:14px;overflow:hidden;transition:box-shadow 0.3s ease;}
.ghost-panel:hover{box-shadow:0 8px 32px rgba(0,0,0,0.2);}
.ghost-panel-header{padding:18px 22px;border-bottom:1px solid var(--ghost-border);display:flex;align-items:center;justify-content:between;gap:12px;}
.ghost-panel-title{font-weight:700;font-size:0.95rem;color:white;flex:1;}
.ghost-panel-body{padding:22px;}

/* ── MODAL ── */
.modal .modal-content,
.modal-ghost .modal-content{background:var(--ghost-card);border:1px solid var(--ghost-border);border-radius:16px;color:var(--ghost-text);box-shadow:0 24px 70px rgba(0,0,0,0.55);}
.modal .modal-header,
.modal-ghost .modal-header{border-bottom:1px solid var(--ghost-border);padding:20px 24px;background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0));}
.modal .modal-body{color:var(--ghost-text);}
.modal .modal-footer,
.modal-ghost .modal-footer{border-top:1px solid var(--ghost-border);padding:16px 24px;background:rgba(255,255,255,0.01);}
.modal .modal-title,
.modal-ghost .modal-title{font-weight:700;font-size:1rem;color:white;}
.modal .btn-close,
.modal-ghost .btn-close{filter:invert(1);opacity:1;box-shadow:none;}
/* Ensure Bootstrap modals always sit above sidebar and pull arrow */
.modal{z-index:1060!important;}
.modal-backdrop{z-index:1055!important;}
.modal-backdrop.show{opacity:0.78;}
.form-label-ghost{font-size:0.77rem;font-weight:600;color:var(--ghost-muted);letter-spacing:0.08em;text-transform:uppercase;margin-bottom:7px;}
.form-control-ghost{background:rgba(255,255,255,0.03);border:1px solid var(--ghost-border);color:var(--ghost-text);padding:11px 14px;border-radius:9px;font-family:'Space Grotesk',sans-serif;font-size:0.9rem;width:100%;transition:all 0.25s var(--transition-page);}
.form-control-ghost:focus{background:rgba(245,166,35,0.04);border-color:var(--ghost-amber);outline:none;box-shadow:0 0 0 3px var(--ghost-amber-dim);transform:translateY(-1px);}
.form-select-ghost{background:rgba(255,255,255,0.03) url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") no-repeat right 12px center/14px 10px;border:1px solid var(--ghost-border);color:var(--ghost-text);padding:11px 36px 11px 14px;border-radius:9px;font-family:'Space Grotesk',sans-serif;font-size:0.9rem;width:100%;-webkit-appearance:none;transition:all 0.25s var(--transition-page);}
.form-select-ghost:focus{background-color:rgba(245,166,35,0.04);border-color:var(--ghost-amber);outline:none;box-shadow:0 0 0 3px var(--ghost-amber-dim);}
.form-select-ghost option{background:var(--ghost-card);}

/* ── RESPONSIVE ── */
@media(max-width:991px){
  #sidebar{transform:translateX(-100%);}
  #sidebar.open{transform:translateX(0);}
  #main{margin-left:0;}
  .topbar{padding:12px 16px;}
  .page-content{padding:16px;}
  #sidebarPull{display:none;}
}
.sidebar-toggle{display:none;width:38px;height:38px;background:var(--ghost-card);border:1px solid var(--ghost-border);border-radius:9px;align-items:center;justify-content:center;cursor:pointer;color:var(--ghost-muted);transition:all 0.2s;}
.sidebar-toggle:hover{color:var(--ghost-amber);border-color:var(--ghost-amber);}
@media(max-width:991px){.sidebar-toggle{display:flex;}}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:150;backdrop-filter:blur(2px);}
.sidebar-overlay.open{display:block;}

/* Alerts toast */
#toastContainer{position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;}
.ghost-toast{background:var(--ghost-card);border:1px solid var(--ghost-border);border-radius:12px;padding:14px 18px;min-width:280px;display:flex;align-items:center;gap:12px;box-shadow:0 8px 32px rgba(0,0,0,0.4);animation:slideIn 0.3s var(--transition-page);}
.ghost-toast.success{border-left:3px solid var(--ghost-green);}
.ghost-toast.error{border-left:3px solid var(--ghost-red);}
.ghost-toast.warning{border-left:3px solid var(--ghost-amber);}

</style>
</head>
<body>

<!-- LOGOUT CONFIRMATION MODAL -->
<div id="logoutModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(4px);">
  <div style="background:var(--ghost-card);border:1px solid var(--ghost-border);border-radius:16px;padding:32px;width:100%;max-width:360px;margin:16px;text-align:center;box-shadow:0 24px 64px rgba(0,0,0,0.5);">
    <div style="font-size:2.5rem;margin-bottom:12px;">🚪</div>
    <div style="font-size:1.1rem;font-weight:700;color:white;margin-bottom:8px;">Log Out?</div>
    <div style="font-size:0.88rem;color:var(--ghost-muted);margin-bottom:24px;">Are you sure you want to log out of your admin session?</div>
    <div style="display:flex;gap:12px;justify-content:center;">
      <button onclick="document.getElementById('logoutModal').style.display='none'" style="flex:1;padding:10px 16px;border-radius:10px;border:1px solid var(--ghost-border);background:transparent;color:var(--ghost-muted);cursor:pointer;font-size:0.88rem;transition:all 0.2s;" onmouseover="this.style.borderColor='var(--ghost-amber)';this.style.color='white'" onmouseout="this.style.borderColor='var(--ghost-border)';this.style.color='var(--ghost-muted)'">Cancel</button>
      <a href="logout.php" style="flex:1;padding:10px 16px;border-radius:10px;border:none;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;cursor:pointer;font-size:0.88rem;font-weight:600;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:6px;transition:all 0.2s;" onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'"><i class="fas fa-sign-out-alt"></i> Yes, Log Out</a>
    </div>
  </div>
</div>
<!-- GLOBAL CONFIRMATION MODAL -->
<div class="modal fade" id="confirmActionModalGlobal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content modal-ghost">
      <div class="modal-header"><h5 class="modal-title" id="confirmActionTitleGlobal">Please confirm</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="confirmActionBodyGlobal">Are you sure?</div>
      <div class="modal-footer">
        <button class="btn-outline-ghost" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-ghost" id="confirmActionOkGlobal">Confirm</button>
      </div>
    </div>
  </div>
</div>

<script>
function showConfirm(title, message, onConfirm) {
  const el = document.getElementById('confirmActionModalGlobal');
  if (!el) return onConfirm();
  const t = document.getElementById('confirmActionTitleGlobal');
  const b = document.getElementById('confirmActionBodyGlobal');
  const ok = document.getElementById('confirmActionOkGlobal');
  if (t) t.textContent = title;
  if (b) b.textContent = message;
  document.querySelectorAll('.modal.show').forEach(m => {
    try { const instance = bootstrap.Modal.getInstance(m); if (instance) instance.hide(); } catch(e){}
  });
  const modal = new bootstrap.Modal(el);
  modal.show();
  ok.focus();
  function cleanup() { ok.removeEventListener('click', okHandler); }
  function okHandler() { cleanup(); modal.hide(); if (typeof onConfirm === 'function') onConfirm(); }
  ok.addEventListener('click', okHandler);
}
</script>
<div id="sidebarOverlay" class="sidebar-overlay" onclick="closeSidebar()"></div>

<!-- SIDEBAR PULL ARROW -->
<div id="sidebarPull" onclick="toggleSidebarCollapse()" title="Toggle sidebar">
  <i class="fas fa-chevron-left"></i>
</div>

<!-- SIDEBAR -->
<nav id="sidebar">
  <div class="sidebar-inner">
  <div class="sidebar-brand">
    <i class="fas fa-egg" style="font-size:1.4rem;opacity:0.8;"></i>
    <div>
      GHOST
      <span class="sidebar-brand-sub">Admin Panel</span>
    </div>
  </div>
  <div class="sidebar-section">Overview</div>
  <div class="nav-item"><a href="dashboard.php" class="nav-link-ghost <?= ($activePage??'')=='dashboard'?'active':'' ?>"><span class="nav-icon"><i class="fas fa-chart-pie"></i></span> Dashboard</a></div>
  <div class="sidebar-section">Management</div>
  <div class="nav-item"><a href="incubators.php" class="nav-link-ghost <?= ($activePage??'')=='incubators'?'active':'' ?>"><span class="nav-icon"><i class="fas fa-egg"></i></span> Incubators</a></div>
  <div class="nav-item"><a href="batches.php" class="nav-link-ghost <?= ($activePage??'')=='batches'?'active':'' ?>"><span class="nav-icon"><i class="fas fa-layer-group"></i></span> Batches</a></div>
  <div class="nav-item"><a href="schedules.php" class="nav-link-ghost <?= ($activePage??'')=='schedules'?'active':'' ?>"><span class="nav-icon"><i class="fas fa-calendar-check"></i></span> Schedules</a></div>
  <div class="nav-item"><a href="temperature.php" class="nav-link-ghost <?= ($activePage??'')=='temperature'?'active':'' ?>"><span class="nav-icon"><i class="fas fa-thermometer-half"></i></span> Temperature</a></div>
  <div class="sidebar-section">Users</div>
  <div class="nav-item"><a href="users.php" class="nav-link-ghost <?= ($activePage??'')=='users'?'active':'' ?>"><span class="nav-icon"><i class="fas fa-users"></i></span> Manage Users</a></div>
  <div class="nav-item"><a href="alerts.php" class="nav-link-ghost <?= ($activePage??'')=='alerts'?'active':'' ?>"><span class="nav-icon"><i class="fas fa-bell"></i></span> Alerts <span class="badge-count" id="alertCount">0</span></a></div>
  <div class="nav-item"><a href="logs.php" class="nav-link-ghost <?= ($activePage??'')=='logs'?'active':'' ?>"><span class="nav-icon"><i class="fas fa-list-alt"></i></span> Activity Logs</a></div>
  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($admin['full_name'],0,1)) ?></div>
    <div><div class="user-name"><?= htmlspecialchars($admin['full_name']) ?></div><div class="user-role">Administrator</div></div>
    <button onclick="document.getElementById('logoutModal').style.display='flex'" class="logout-btn" title="Logout" style="border:none;"><i class="fas fa-sign-out-alt" style="font-size:0.85rem;"></i></button>
  </div>
  </div><!-- /sidebar-inner -->
</nav>

<!-- MAIN -->
<div id="main">
  <div class="topbar">
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <div>
      <div class="topbar-title"><?= $pageTitle ?? 'Dashboard' ?></div>
      <div class="topbar-sub"><?= $pageSubtitle ?? 'GHOST Incubator System' ?></div>
    </div>
    <div class="topbar-right">
      <div class="alert-bell" onclick="window.location.href='alerts.php'"><i class="fas fa-bell"></i><span class="bell-dot" id="bellDot" style="display:none"></span></div>
      <div class="topbar-clock">
        <div class="clock-time" id="clockTime">--:--:--</div>
        <div class="clock-date" id="clockDate">--- -- ----</div>
      </div>
    </div>
  </div>
  <div class="page-content">
<!-- PAGE CONTENT BELOW -->
