<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Login — GHOST</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--ghost-dark:#0a0c10;--ghost-card:#111318;--ghost-border:rgba(255,255,255,0.07);--ghost-blue:#3b82f6;--ghost-blue-dim:rgba(59,130,246,0.12);--ghost-blue-glow:rgba(59,130,246,0.4);--ghost-text:#e2e8f0;--ghost-muted:#64748b;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Space Grotesk',sans-serif;background:var(--ghost-dark);color:var(--ghost-text);min-height:100vh;display:flex;align-items:center;justify-content:center;}
.login-wrap{width:100%;max-width:460px;padding:24px;}
.brand{font-family:'Bebas Neue',sans-serif;font-size:2.2rem;letter-spacing:0.15em;background:linear-gradient(135deg,#3b82f6,#6366f1);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.login-box{background:var(--ghost-card);border:1px solid var(--ghost-border);border-radius:20px;padding:40px;margin-top:24px;}
.form-label{font-size:0.82rem;font-weight:600;color:var(--ghost-muted);letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px;}
.form-control{background:rgba(255,255,255,0.03);border:1px solid var(--ghost-border);color:var(--ghost-text);padding:13px 16px;border-radius:10px;font-family:'Space Grotesk',sans-serif;font-size:0.92rem;transition:all 0.2s;}
.form-control:focus{background:rgba(59,130,246,0.04);border-color:var(--ghost-blue);box-shadow:0 0 0 3px var(--ghost-blue-dim);color:var(--ghost-text);}
.form-control::placeholder{color:var(--ghost-muted);}
.btn-login{background:linear-gradient(135deg,#3b82f6,#6366f1);border:none;color:white;font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:0.92rem;letter-spacing:0.08em;text-transform:uppercase;padding:14px;border-radius:10px;width:100%;transition:all 0.3s;box-shadow:0 4px 20px var(--ghost-blue-glow),inset 0 1px 0 rgba(255,255,255,0.2),inset 0 -1px 0 rgba(0,0,0,0.25);}
.btn-login:hover{transform:translateY(-2px);box-shadow:0 8px 32px var(--ghost-blue-glow);}
.btn-login:disabled{opacity:0.6;transform:none;}
.back-link{color:var(--ghost-muted);font-size:0.85rem;text-decoration:none;display:flex;align-items:center;gap:8px;transition:color 0.2s;}
.back-link:hover{color:var(--ghost-blue);}
.alert-ghost{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#fca5a5;border-radius:10px;padding:12px 16px;font-size:0.88rem;margin-bottom:20px;}
@keyframes fadeUp{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:translateY(0)}}
@keyframes scaleIn{from{opacity:0;transform:scale(0.94)}to{opacity:1;transform:scale(1)}}
@keyframes pulseGlow{0%,100%{box-shadow:0 4px 20px var(--ghost-blue-glow),inset 0 1px 0 rgba(255,255,255,0.2)}50%{box-shadow:0 8px 40px rgba(59,130,246,0.6),inset 0 1px 0 rgba(255,255,255,0.2)}}
.login-wrap{animation:fadeUp 0.55s cubic-bezier(0.22,1,0.36,1) both;}
.login-box{animation:scaleIn 0.55s cubic-bezier(0.22,1,0.36,1) 0.08s both;}
.brand{animation:fadeUp 0.4s cubic-bezier(0.22,1,0.36,1) both;}
.back-link{animation:fadeUp 0.35s cubic-bezier(0.22,1,0.36,1) both;}
.btn-login:not(:disabled):hover{animation:pulseGlow 1.5s ease infinite;}
.form-control{transition:all 0.3s cubic-bezier(0.22,1,0.36,1);}
.form-control:focus{transform:translateY(-1px);}
body{overflow:hidden;}
body.ready{overflow:auto;}
.page-out{animation:pageOut 0.3s ease forwards;}
@keyframes pageOut{to{opacity:0;transform:translateY(-12px)}}

/* ── FULL SCREEN LOADING OVERLAY ── */
#loginLoader{position:fixed;inset:0;z-index:99999;background:var(--ghost-dark);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:20px;opacity:0;pointer-events:none;transition:opacity 0.35s ease;}
#loginLoader.active{opacity:1;pointer-events:all;}
.loader-rings{position:relative;width:72px;height:72px;display:flex;align-items:center;justify-content:center;}
.loader-ring-outer{position:absolute;inset:0;border-radius:50%;border:3px solid rgba(255,255,255,0.06);border-top-color:var(--ghost-blue);animation:spinRing 0.9s linear infinite;}
.loader-ring-inner{position:absolute;inset:12px;border-radius:50%;border:3px solid rgba(255,255,255,0.04);border-bottom-color:#6366f1;animation:spinRing 1.2s linear infinite reverse;}
.loader-dot{width:10px;height:10px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#6366f1);animation:loaderPulse 1.2s ease infinite;}
@keyframes spinRing{to{transform:rotate(360deg)}}
@keyframes loaderPulse{0%,100%{opacity:0.4;transform:scale(0.8)}50%{opacity:1;transform:scale(1.1)}}
.loader-label{font-family:'Space Grotesk',sans-serif;font-size:0.82rem;font-weight:600;color:var(--ghost-muted);letter-spacing:0.12em;text-transform:uppercase;animation:textPulse 1.6s ease infinite;}
.loader-brand-text{font-family:'Bebas Neue',sans-serif;font-size:1.5rem;letter-spacing:0.18em;background:linear-gradient(135deg,#3b82f6,#6366f1);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
@keyframes textPulse{0%,100%{opacity:0.4}60%{opacity:1}}

.user-icon{width:48px;height:48px;background:var(--ghost-blue-dim);border:1px solid rgba(59,130,246,0.3);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:var(--ghost-blue);margin-bottom:20px;box-shadow:inset 0 1px 0 rgba(255,255,255,0.1);}
</style>
</head>
<body>
<div id="loginLoader">
  <div class="loader-brand-text">GHOST</div>
  <div class="loader-rings">
    <div class="loader-ring-outer"></div>
    <div class="loader-ring-inner"></div>
    <div class="loader-dot"></div>
  </div>
  <div class="loader-label">Loading Dashboard…</div>
</div>
<div class="login-wrap">
  <a href="../index.php" class="back-link mb-4 d-inline-flex"><i class="fas fa-arrow-left"></i> Back to Home</a>
  <div class="brand">GHOST</div>
  <div class="login-box">
    <div class="user-icon"><i class="fas fa-user-circle"></i></div>
    <div style="font-weight:700;font-size:1.5rem;color:white;margin-bottom:4px;">Farm User Login</div>
    <div style="color:var(--ghost-muted);font-size:0.88rem;margin-bottom:32px;">Access your incubation dashboard</div>
    <div id="alertBox" class="alert-ghost d-none"></div>
    <form id="loginForm">
      <div class="mb-3"><label class="form-label">Email Address</label><input type="email" class="form-control" id="email" placeholder="user@ghost.com" required></div>
      <div class="mb-4"><label class="form-label">Password</label><input type="password" class="form-control" id="password" placeholder="••••••••" required></div>
      <button type="submit" class="btn-login" id="loginBtn"><i class="fas fa-sign-in-alt me-2"></i>Login to Dashboard</button>
    </form>

  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$('#loginForm').on('submit',function(e){
  e.preventDefault();
  const btn=$('#loginBtn'),box=$('#alertBox');
  btn.prop('disabled',true).html('<i class="fas fa-spinner fa-spin me-2"></i>Signing in...');
  box.addClass('d-none');
  $.ajax({
    url:'../ajax/user_auth.php',method:'POST',
    data:{action:'login',email:$('#email').val(),password:$('#password').val()},
    dataType:'json',
    success:function(res){
      if(res.success){
        $('#loginLoader').addClass('active');
        $('body').addClass('page-out');
        setTimeout(()=>{window.location.href='dashboard.php';},900);
      }
      else{box.removeClass('d-none').text(res.message);btn.prop('disabled',false).html('<i class="fas fa-sign-in-alt me-2"></i>Login to Dashboard');}
    },
    error:function(){box.removeClass('d-none').text('Server error.');btn.prop('disabled',false).html('<i class="fas fa-sign-in-alt me-2"></i>Login to Dashboard');}
  });
});
$(document).ready(function(){$('body').addClass('ready');});
// Intercept nav links for page-out effect
$(document).on('click','a[href]',function(e){
  const href=$(this).attr('href');
  if(!href||href.startsWith('#')||href.startsWith('javascript')||$(this).attr('target'))return;
  e.preventDefault();
  $('body').addClass('page-out');
  setTimeout(()=>{window.location.href=href;},280);
});
</script>
</body>
</html>
