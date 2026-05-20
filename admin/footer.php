  </div><!-- /page-content -->
</div><!-- /main -->

<div id="toastContainer"></div>

<!-- ── GLOBAL AJAX SAVE LOADER ── -->
<div id="ajaxLoader" style="display:none;position:fixed;inset:0;z-index:88888;background:rgba(10,12,16,0.75);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);align-items:center;justify-content:center;flex-direction:column;gap:16px;">
  <div style="position:relative;width:60px;height:60px;display:flex;align-items:center;justify-content:center;">
    <div style="position:absolute;inset:0;border-radius:50%;border:3px solid rgba(255,255,255,0.06);border-top-color:var(--ghost-amber);animation:spinRing 0.8s linear infinite;"></div>
    <div style="position:absolute;inset:10px;border-radius:50%;border:3px solid rgba(255,255,255,0.04);border-bottom-color:#f97316;animation:spinRing 1.1s linear infinite reverse;"></div>
    <div style="width:8px;height:8px;border-radius:50%;background:var(--ghost-amber);animation:loaderPulse2 1s ease infinite;"></div>
  </div>
  <div id="ajaxLoaderText" style="font-family:'Space Grotesk',sans-serif;font-size:0.8rem;font-weight:600;color:var(--ghost-muted);letter-spacing:0.1em;text-transform:uppercase;animation:textPulse2 1.4s ease infinite;">Saving…</div>
</div>
<style>
@keyframes spinRing{to{transform:rotate(360deg)}}
@keyframes loaderPulse2{0%,100%{opacity:0.4;transform:scale(0.8)}50%{opacity:1;transform:scale(1.2)}}
@keyframes textPulse2{0%,100%{opacity:0.4}60%{opacity:1}}
/* Ripple effect on save buttons */
.btn-ghost,.btn-edit-ghost,.btn-danger-ghost{position:relative;overflow:hidden;}
.btn-ripple{position:absolute;border-radius:50%;background:rgba(255,255,255,0.25);animation:rippleOut 0.55s ease-out forwards;pointer-events:none;}
@keyframes rippleOut{from{width:0;height:0;opacity:1;transform:translate(-50%,-50%)}to{width:200px;height:200px;opacity:0;transform:translate(-50%,-50%)}}
</style>

<script src="../assets/vendor/chart/chart.js"></script>
<script>
// ── MOVE ALL BOOTSTRAP MODALS TO BODY (escape stacking context) ──
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('.modal').forEach(function(m){
    if(m.parentElement !== document.body){
      document.body.appendChild(m);
    }
  });
});
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('open');
}
function closeSidebar(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('open');
}

// ── DESKTOP SIDEBAR COLLAPSE (pull arrow) ──
function toggleSidebarCollapse(){
  document.body.classList.toggle('sidebar-collapsed');
  const collapsed = document.body.classList.contains('sidebar-collapsed');
  try{ localStorage.setItem('ghost_sidebar_collapsed', collapsed ? '1' : '0'); }catch(e){}
}
// Restore sidebar state
try{
  if(localStorage.getItem('ghost_sidebar_collapsed')==='1'){
    document.body.classList.add('sidebar-collapsed');
  }
}catch(e){}

// ── LIVE CLOCK ──
function updateClock(){
  const now = new Date();
  const h = String(now.getHours()).padStart(2,'0');
  const m = String(now.getMinutes()).padStart(2,'0');
  const s = String(now.getSeconds()).padStart(2,'0');
  const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  const timeEl = document.getElementById('clockTime');
  const dateEl = document.getElementById('clockDate');
  if(timeEl) timeEl.textContent = `${h}:${m}:${s}`;
  if(dateEl) dateEl.textContent = `${days[now.getDay()]}, ${months[now.getMonth()]} ${now.getDate()}`;
}
updateClock();
setInterval(updateClock, 1000);

// ── PAGE TRANSITION (nav links only — skip logout modal, modals, hash links) ──
document.addEventListener('click', function(e){
  const link = e.target.closest('a[href]');
  if(!link) return;
  const href = link.getAttribute('href');
  if(!href || href.startsWith('#') || href.startsWith('javascript') || link.getAttribute('target') || link.getAttribute('data-bs-toggle') || link.getAttribute('data-bs-dismiss')) return;
  // Skip links inside the logout confirmation modal
  if(link.closest('#logoutModal')) return;
  if(e.ctrlKey || e.metaKey || e.shiftKey) return;
  e.preventDefault();
  document.body.classList.add('page-out');
  setTimeout(()=>{ window.location.href = href; }, 280);
});

// ── AJAX SAVE LOADER ──
function showLoader(text){
  const el = document.getElementById('ajaxLoader');
  const lbl = document.getElementById('ajaxLoaderText');
  if(lbl) lbl.textContent = text || 'Saving…';
  el.style.display = 'flex';
  void el.offsetWidth;
  el.style.opacity = '0';
  el.style.transition = 'opacity 0.25s ease';
  requestAnimationFrame(()=>{ el.style.opacity = '1'; });
}
function hideLoader(){
  const el = document.getElementById('ajaxLoader');
  el.style.opacity = '0';
  setTimeout(()=>{ el.style.display = 'none'; }, 250);
}

// ── RIPPLE EFFECT ON BUTTONS ──
document.addEventListener('click', function(e){
  const btn = e.target.closest('.btn-ghost,.btn-edit-ghost,.btn-danger-ghost');
  if(!btn) return;
  const r = document.createElement('span');
  r.className = 'btn-ripple';
  const rect = btn.getBoundingClientRect();
  r.style.left = (e.clientX - rect.left) + 'px';
  r.style.top  = (e.clientY - rect.top)  + 'px';
  btn.appendChild(r);
  setTimeout(()=>r.remove(), 600);
});

// ── TOAST NOTIFICATIONS ──
function showToast(msg, type='success'){
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  const icon = type==='success'?'fa-check-circle':type==='error'?'fa-times-circle':'fa-exclamation-triangle';
  const color = type==='success'?'#4ade80':type==='error'?'#f87171':'#f5a623';
  t.className = `ghost-toast ${type}`;
  t.innerHTML = `<i class="fas ${icon}" style="color:${color};font-size:1rem;"></i><span style="font-size:0.88rem;">${msg}</span>`;
  c.appendChild(t);
  setTimeout(()=>{ t.style.transition='opacity 0.3s,transform 0.3s'; t.style.opacity='0'; t.style.transform='translateX(40px)'; setTimeout(()=>t.remove(),300); }, 3700);
}

// ── ALERT COUNT ──
function loadAlertCount(){
  $.get('../ajax/get_alerts.php', {count:1}, function(res){
    if(res.count > 0){
      $('#alertCount').text(res.count);
      $('#bellDot').show();
    }
  }, 'json');
}
loadAlertCount();
setInterval(loadAlertCount, 30000);
</script>
</body>
</html>
