<?php
require_once 'includes/config.php';
// Auto-start schedule runner if not already running (checks last-run timestamp)
// - Uses scripts/run_schedules.last to detect runner activity
// - If last-run is stale (> 45s) will attempt to start the Python runner in background
$runner_last = __DIR__ . '/scripts/run_schedules.last';
$runner_interval = 30; // seconds between runs expected
$runner_buffer = 15; // extra seconds buffer
  // Prefer project's virtualenv Python if present
  $venv_win = __DIR__ . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe';
  $venv_unix = __DIR__ . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python';
  $default_python = 'python';
  if (is_file($venv_win)) {
    $python_bin = $venv_win;
  } elseif (is_file($venv_unix)) {
    $python_bin = $venv_unix;
  } else {
    $python_bin = $default_python;
  }
  $python_bin_escaped = escapeshellarg($python_bin);
  $script_path = __DIR__ . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'run_schedules.py';
  $script_path_escaped = escapeshellarg($script_path);
  $runner_cmd = $python_bin_escaped . ' ' . $script_path_escaped . ' --interval 30 --grace 180';
try {
  $needStart = true;
  if (file_exists($runner_last)) {
    $mtime = filemtime($runner_last);
    if ($mtime !== false && (time() - $mtime) <= ($runner_interval + $runner_buffer)) {
      $needStart = false; // still recently ran
    }
  }
  if ($needStart) {
    // Start background process based on OS
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
      // Windows: use start /B
      $cmd = 'start /B "" ' . $runner_cmd;
      pclose(popen($cmd, 'r'));
    } else {
      // Unix: background exec
      $cmd = $runner_cmd . ' > /dev/null 2>&1 &';
      exec($cmd);
    }
    // touch last-run file to avoid rapid restarts
    @file_put_contents($runner_last, date('c'));
  }
} catch (Exception $e) {
  // non-fatal
}

if (isAdminLoggedIn()) {
  header('Location: ' . BASE_URL . '/admin/dashboard.php');
  exit();
}

if (isUserLoggedIn()) {
  header('Location: ' . BASE_URL . '/user/dashboard.php');
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GHOST — Intelligent Egg Incubation System</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --ghost-dark: #0a0c10;
  --ghost-darker: #060709;
  --ghost-card: #111318;
  --ghost-border: rgba(255,255,255,0.07);
  --ghost-amber: #f5a623;
  --ghost-amber-dim: rgba(245,166,35,0.15);
  --ghost-amber-glow: rgba(245,166,35,0.4);
  --ghost-green: #22c55e;
  --ghost-red: #ef4444;
  --ghost-blue: #3b82f6;
  --ghost-text: #e2e8f0;
  --ghost-muted: #64748b;
  --ghost-gradient: linear-gradient(135deg, #f5a623 0%, #f97316 50%, #ef4444 100%);
}
* { margin:0; padding:0; box-sizing:border-box; }
html { scroll-behavior: smooth; }
body {
  font-family: 'Space Grotesk', sans-serif;
  background: var(--ghost-dark);
  color: var(--ghost-text);
  overflow-x: hidden;
}

/* ── NAVBAR ── */
.ghost-nav {
  position: fixed; top:0; left:0; right:0; z-index:1000;
  background: rgba(10,12,16,0.92);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--ghost-border);
  padding: 14px 0;
}
.ghost-nav .brand {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 2rem;
  letter-spacing: 0.15em;
  background: var(--ghost-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.ghost-nav .brand span { -webkit-text-fill-color: rgba(255,255,255,0.3); }

/* ── HERO ── */
.hero-section {
  min-height: 100vh;
  display: flex; align-items: center;
  position: relative;
  overflow: hidden;
  padding: 120px 0 80px;
}
.hero-bg {
  position: absolute; inset:0;
  background: radial-gradient(ellipse 80% 70% at 60% 50%, rgba(245,166,35,0.07) 0%, transparent 70%),
              radial-gradient(ellipse 40% 40% at 15% 80%, rgba(239,68,68,0.05) 0%, transparent 60%);
}
.hero-grid {
  position: absolute; inset:0; opacity:0.04;
  background-image: 
    linear-gradient(var(--ghost-border) 1px, transparent 1px),
    linear-gradient(90deg, var(--ghost-border) 1px, transparent 1px);
  background-size: 60px 60px;
}
.hero-badge {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--ghost-amber-dim);
  border: 1px solid var(--ghost-amber);
  color: var(--ghost-amber);
  padding: 6px 18px;
  border-radius: 100px;
  font-size: 0.78rem;
  font-weight: 600;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  margin-bottom: 28px;
}
.hero-badge .dot {
  width: 7px; height: 7px;
  background: var(--ghost-amber);
  border-radius: 50%;
  animation: pulse 2s ease infinite;
}
@keyframes pulse {
  0%,100%{opacity:1;transform:scale(1)}
  50%{opacity:0.4;transform:scale(1.5)}
}
.hero-title {
  font-family: 'Bebas Neue', sans-serif;
  font-size: clamp(4rem, 10vw, 9rem);
  line-height: 0.9;
  letter-spacing: 0.03em;
  margin-bottom: 28px;
}
.hero-title .line-1 { color: white; display:block; }
.hero-title .line-2 {
  display:block;
  background: var(--ghost-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.hero-sub {
  font-size: 1.15rem;
  color: var(--ghost-muted);
  max-width: 500px;
  line-height: 1.8;
  margin-bottom: 44px;
}

/* ── BUTTONS ── */
.btn-ghost-primary {
  background: var(--ghost-gradient);
  border: none;
  color: white;
  font-family: 'Space Grotesk', sans-serif;
  font-weight: 700;
  font-size: 0.9rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  padding: 16px 36px;
  border-radius: 8px;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  text-decoration: none;
  display: inline-block;
  transition: all 0.3s ease;
  box-shadow: 0 4px 24px var(--ghost-amber-glow), 
              inset 0 1px 0 rgba(255,255,255,0.25),
              inset 0 -1px 0 rgba(0,0,0,0.3);
}
.btn-ghost-primary::before {
  content:'';
  position:absolute; inset:0;
  background: linear-gradient(180deg, rgba(255,255,255,0.15) 0%, transparent 50%);
}
.btn-ghost-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 36px var(--ghost-amber-glow), 
              inset 0 1px 0 rgba(255,255,255,0.25);
  color:white;
}
.btn-ghost-outline {
  background: transparent;
  border: 1px solid var(--ghost-border);
  color: var(--ghost-text);
  font-family: 'Space Grotesk', sans-serif;
  font-weight: 600;
  font-size: 0.9rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  padding: 15px 36px;
  border-radius: 8px;
  cursor: pointer;
  text-decoration: none;
  display: inline-block;
  transition: all 0.3s ease;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.07);
}
.btn-ghost-outline:hover {
  border-color: var(--ghost-amber);
  color: var(--ghost-amber);
  background: var(--ghost-amber-dim);
}

/* ── HERO EGG VISUAL ── */
.hero-visual {
  display: flex; align-items: center; justify-content: center;
  position: relative;
}
.egg-container {
  position: relative; width: 340px; height: 420px;
}
.egg-shell {
  position: absolute; left:50%; top:50%;
  transform: translate(-50%, -50%);
  width: 200px; height: 260px;
  border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
  background: linear-gradient(145deg, #1e2028 0%, #111318 50%, #0a0c10 100%);
  border: 1px solid rgba(245,166,35,0.2);
  box-shadow: 0 0 60px rgba(245,166,35,0.12), 0 0 120px rgba(245,166,35,0.06);
  display: flex; align-items: center; justify-content: center;
  flex-direction: column; gap: 8px;
}
.egg-ring {
  position: absolute;
  border-radius: 50%;
  border: 1px solid rgba(245,166,35,0.1);
  animation: rotate-slow linear infinite;
}
.egg-ring:nth-child(1) { width:280px;height:350px; animation-duration:20s; }
.egg-ring:nth-child(2) { width:320px;height:400px; animation-duration:30s; animation-direction: reverse; }
.egg-ring:nth-child(3) { width:360px;height:450px; animation-duration:45s; }
@keyframes rotate-slow { from{transform:translate(-50%,-50%) rotate(0)} to{transform:translate(-50%,-50%) rotate(360deg)} }
.egg-ring { position:absolute; left:50%; top:50%; }
.egg-temp {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 2.8rem;
  color: var(--ghost-amber);
  line-height:1;
}
.egg-label { font-size: 0.7rem; color: var(--ghost-muted); letter-spacing:0.2em; text-transform:uppercase; }
.egg-glow-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--ghost-green);
  animation: pulse 2s ease infinite;
  margin-bottom: 4px;
}
.floating-chip {
  position: absolute;
  background: var(--ghost-card);
  border: 1px solid var(--ghost-border);
  border-radius: 12px;
  padding: 10px 16px;
  display: flex; align-items: center; gap: 10px;
  font-size: 0.8rem;
  box-shadow: 0 8px 32px rgba(0,0,0,0.4);
  white-space: nowrap;
  animation: float linear infinite alternate;
}
.chip-1 { top:30px; left:-20px; animation-duration:3s; }
.chip-2 { bottom:60px; right:-30px; animation-duration:4s; }
.chip-3 { top:150px; right:-50px; animation-duration:3.5s; }
@keyframes float { from{transform:translateY(0)} to{transform:translateY(-12px)} }
.chip-icon { font-size: 1.2rem; }

/* ── STATS ── */
.stats-section {
  padding: 60px 0;
  border-top: 1px solid var(--ghost-border);
  border-bottom: 1px solid var(--ghost-border);
  background: rgba(255,255,255,0.01);
}
.stat-item { text-align:center; }
.stat-num {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 3.5rem;
  background: var(--ghost-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  line-height:1;
}
.stat-label { color: var(--ghost-muted); font-size:0.85rem; margin-top:4px; letter-spacing:0.05em; }

/* ── FEATURES ── */
.features-section { padding: 120px 0; }
.section-tag {
  display: inline-block;
  color: var(--ghost-amber);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.25em;
  text-transform: uppercase;
  margin-bottom: 16px;
}
.section-title {
  font-family: 'Bebas Neue', sans-serif;
  font-size: clamp(2.5rem, 5vw, 4.5rem);
  line-height: 1;
  letter-spacing: 0.03em;
  color: white;
  margin-bottom: 20px;
}
.feature-card {
  background: var(--ghost-card);
  border: 1px solid var(--ghost-border);
  border-radius: 16px;
  padding: 32px;
  height: 100%;
  position: relative;
  overflow: hidden;
  transition: all 0.4s ease;
  cursor: default;
}
.feature-card::before {
  content:'';
  position:absolute; top:0; left:0; right:0; height:2px;
  background: var(--ghost-gradient);
  opacity:0;
  transition: opacity 0.3s ease;
}
.feature-card:hover { border-color: rgba(245,166,35,0.3); transform:translateY(-4px); }
.feature-card:hover::before { opacity:1; }
.feature-icon {
  width: 52px; height: 52px;
  background: var(--ghost-amber-dim);
  border: 1px solid rgba(245,166,35,0.3);
  border-radius: 12px;
  display: flex; align-items:center; justify-content:center;
  font-size: 1.4rem;
  margin-bottom: 20px;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.1), 0 2px 8px rgba(0,0,0,0.3);
}
.feature-title { font-weight:700; font-size:1.05rem; margin-bottom:10px; color:white; }
.feature-desc { color: var(--ghost-muted); font-size:0.9rem; line-height:1.7; }

/* ── HOW IT WORKS ── */
.how-section { padding: 100px 0; background: rgba(255,255,255,0.01); }
.step-num {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 5rem;
  color: rgba(245,166,35,0.1);
  line-height:1;
  margin-bottom: -10px;
}
.step-title { font-weight:700; font-size:1.1rem; margin-bottom:8px; color:white; }
.step-desc { color: var(--ghost-muted); font-size:0.9rem; line-height:1.7; }

/* ── LOGIN CARDS ── */
.login-section { padding: 120px 0; }
.login-card {
  background: var(--ghost-card);
  border: 1px solid var(--ghost-border);
  border-radius: 20px;
  padding: 44px 40px;
  text-align: center;
  position: relative;
  overflow: hidden;
  transition: all 0.4s ease;
}
.login-card::after {
  content:'';
  position:absolute; inset:0;
  background: var(--ghost-gradient);
  opacity:0; transition: opacity 0.3s ease;
  z-index:0;
}
.login-card:hover { transform:translateY(-6px); }
.login-card:hover.admin-card { box-shadow: 0 20px 60px rgba(245,166,35,0.2); border-color: var(--ghost-amber); }
.login-card:hover.user-card { box-shadow: 0 20px 60px rgba(59,130,246,0.2); border-color: var(--ghost-blue); }
.login-card > * { position:relative; z-index:1; }
.login-card .card-icon {
  width: 80px; height:80px;
  border-radius: 20px;
  display:flex; align-items:center; justify-content:center;
  font-size:2rem;
  margin: 0 auto 24px;
}
.admin-card .card-icon { background: rgba(245,166,35,0.1); border: 1px solid rgba(245,166,35,0.3); color: var(--ghost-amber); }
.user-card .card-icon { background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.3); color: var(--ghost-blue); }
.login-card .card-title { font-weight:700; font-size:1.4rem; margin-bottom:10px; color:white; }
.login-card .card-desc { color: var(--ghost-muted); font-size:0.9rem; line-height:1.7; margin-bottom:28px; }
.btn-admin {
  background: var(--ghost-gradient);
  border:none; color:white; font-weight:700; font-size:0.9rem;
  letter-spacing:0.08em; text-transform:uppercase; padding:14px 32px;
  border-radius:8px; width:100%; cursor:pointer; transition:all 0.3s;
  text-decoration:none; display:block;
  box-shadow: 0 4px 20px var(--ghost-amber-glow), inset 0 1px 0 rgba(255,255,255,0.2), inset 0 -1px 0 rgba(0,0,0,0.25);
}
.btn-admin:hover { color:white; transform:translateY(-2px); box-shadow: 0 8px 32px var(--ghost-amber-glow); }
.btn-user {
  background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
  border:none; color:white; font-weight:700; font-size:0.9rem;
  letter-spacing:0.08em; text-transform:uppercase; padding:14px 32px;
  border-radius:8px; width:100%; cursor:pointer; transition:all 0.3s;
  text-decoration:none; display:block;
  box-shadow: 0 4px 20px rgba(59,130,246,0.4), inset 0 1px 0 rgba(255,255,255,0.2), inset 0 -1px 0 rgba(0,0,0,0.25);
}
.btn-user:hover { color:white; transform:translateY(-2px); box-shadow: 0 8px 32px rgba(59,130,246,0.5); }

/* ── FOOTER ── */
footer {
  padding: 60px 0 30px;
  border-top: 1px solid var(--ghost-border);
}
.footer-brand {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 2.5rem;
  letter-spacing: 0.15em;
  background: var(--ghost-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.footer-text { color: var(--ghost-muted); font-size:0.85rem; margin-top:8px; }
.footer-divider { border-color: var(--ghost-border); margin: 30px 0; }
.footer-copy { color: var(--ghost-muted); font-size:0.8rem; text-align:center; }

/* ── NAV LINKS ── */
.nav-links a {
  color: var(--ghost-muted); text-decoration:none;
  font-size:0.9rem; font-weight:500;
  transition: color 0.2s;
  padding: 0 12px;
}
.nav-links a:hover { color:white; }
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="ghost-nav">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between">
      <div class="brand">GHOST<span>.</span></div>
      <div class="nav-links d-none d-md-flex align-items-center">
        <a href="#features">Features</a>
        <a href="#how-it-works">How It Works</a>
        <a href="#access">Access</a>
      </div>
      <div class="d-flex gap-2">
        <a href="admin/login.php" class="btn-ghost-outline" style="padding:10px 22px; font-size:0.8rem;">Admin</a>
        <a href="user/login.php" class="btn-ghost-primary" style="padding:10px 22px; font-size:0.8rem;">User Login</a>
      </div>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero-section" id="home">
  <div class="hero-bg"></div>
  <div class="hero-grid"></div>
  <div class="container position-relative">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <div class="hero-badge"><span class="dot"></span> System Online & Monitoring</div>
        <h1 class="hero-title">
          <span class="line-1">BUGOK</span>
          <span class="line-2">INCUBATION</span>
        </h1>
        <p class="hero-sub">GHOST delivers intelligent, real-time egg incubation management. Monitor temperature, schedule rotations, and track every batch — all from a single command center.</p>
        <div class="d-flex flex-wrap gap-3">
          <a href="#access" class="btn-ghost-primary"><i class="fas fa-rocket me-2"></i> Get Started</a>
          <a href="#features" class="btn-ghost-outline"><i class="fas fa-play me-2"></i> See Features</a>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="hero-visual">
          <div class="egg-container">
            <div class="egg-ring"></div>
            <div class="egg-ring"></div>
            <div class="egg-ring"></div>
            <div class="egg-shell">
              <div class="egg-glow-dot"></div>
              <div class="egg-temp">37.5°</div>
              <div class="egg-label">Optimal Temp</div>
            </div>
            <div class="floating-chip chip-1">
              <span class="chip-icon">💧</span>
              <div><div style="font-weight:700;color:white">55%</div><div style="color:var(--ghost-muted);font-size:0.7rem">Humidity</div></div>
            </div>
            <div class="floating-chip chip-2">
              <span class="chip-icon">🥚</span>
              <div><div style="font-weight:700;color:white">125 Eggs</div><div style="color:var(--ghost-muted);font-size:0.7rem">Active Batches</div></div>
            </div>
            <div class="floating-chip chip-3">
              <span class="chip-icon">⏰</span>
              <div><div style="font-weight:700;color:white">Day 10</div><div style="color:var(--ghost-muted);font-size:0.7rem">Incubation</div></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- STATS -->
<section class="stats-section">
  <div class="container">
    <div class="row g-4 text-center">
      <div class="col-6 col-md-3"><div class="stat-num">99.8%</div><div class="stat-label">Hatch Success Rate</div></div>
      <div class="col-6 col-md-3"><div class="stat-num">±0.1°</div><div class="stat-label">Temp Precision</div></div>
      <div class="col-6 col-md-3"><div class="stat-num">24/7</div><div class="stat-label">Real-time Monitoring</div></div>
      <div class="col-6 col-md-3"><div class="stat-num">500+</div><div class="stat-label">Egg Capacity</div></div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="features-section" id="features">
  <div class="container">
    <div class="text-center mb-5">
      <span class="section-tag">● Core Features</span>
      <h2 class="section-title">Everything You Need</h2>
      <p style="color:var(--ghost-muted);max-width:520px;margin:0 auto;line-height:1.8">Built for serious incubation — from backyard hobbyists to commercial farms.</p>
    </div>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon">🌡️</div>
          <div class="feature-title">Live Temperature Control</div>
          <div class="feature-desc">Adjust target temperature and humidity remotely. Automated alerts trigger instantly when values drift outside safe ranges.</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon">📅</div>
          <div class="feature-title">Smart Scheduling</div>
          <div class="feature-desc">Automate egg turning, candling checks, humidity monitoring, and hatch day preparations with a built-in schedule engine.</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon">📊</div>
          <div class="feature-title">Batch Tracking</div>
          <div class="feature-desc">Track every batch from day one to hatch day. Log egg count, species type, success rates, and historical performance data.</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon">👥</div>
          <div class="feature-title">Role-Based Access</div>
          <div class="feature-desc">Separate admin and user accounts with different permission levels. Admins manage everything; users manage their own batches.</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon">🔔</div>
          <div class="feature-title">Alert System</div>
          <div class="feature-desc">Real-time notifications for temperature spikes, missed schedules, and hatch day countdowns. Never miss a critical moment.</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon">📈</div>
          <div class="feature-title">Analytics Dashboard</div>
          <div class="feature-desc">Visual charts showing temperature trends over time, humidity patterns, and batch performance metrics at a glance.</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="how-section" id="how-it-works">
  <div class="container">
    <div class="text-center mb-5">
      <span class="section-tag">● Process</span>
      <h2 class="section-title">How It Works</h2>
    </div>
    <div class="row g-5">
      <div class="col-md-3">
        <div class="step-num">01</div>
        <div class="step-title">Add Incubator</div>
        <div class="step-desc">Register your incubator units with model, capacity, and location details.</div>
      </div>
      <div class="col-md-3">
        <div class="step-num">02</div>
        <div class="step-title">Start a Batch</div>
        <div class="step-desc">Create a new egg batch, select species type, egg count and incubation start date.</div>
      </div>
      <div class="col-md-3">
        <div class="step-num">03</div>
        <div class="step-title">Set Schedule</div>
        <div class="step-desc">Configure automated turning schedules, temperature checks, and candling reminders.</div>
      </div>
      <div class="col-md-3">
        <div class="step-num">04</div>
        <div class="step-title">Monitor & Hatch</div>
        <div class="step-desc">Watch real-time data, respond to alerts, and celebrate your hatch day success.</div>
      </div>
    </div>
  </div>
</section>

<!-- ACCESS SECTION -->
<section class="login-section" id="access">
  <div class="container">
    <div class="text-center mb-5">
      <span class="section-tag">● Access Portal</span>
      <h2 class="section-title">Choose Your Role</h2>
      <p style="color:var(--ghost-muted);max-width:440px;margin:0 auto">Login to your dedicated dashboard for full system control.</p>
    </div>
    <div class="row justify-content-center g-4">
      <div class="col-md-5">
        <div class="login-card admin-card">
          <div class="card-icon"><i class="fas fa-shield-halved"></i></div>
          <div class="card-title">Administrator</div>
          <div class="card-desc">Full system control — manage users, incubators, schedules, temperature settings, and view system-wide analytics.</div>
          <a href="admin/login.php" class="btn-admin"><i class="fas fa-lock me-2"></i>Admin Login</a>
        </div>
      </div>
      <div class="col-md-5">
        <div class="login-card user-card">
          <div class="card-icon"><i class="fas fa-user-circle"></i></div>
          <div class="card-title">Farm User</div>
          <div class="card-desc">Manage your assigned incubators, track egg batches, view schedules, and monitor temperature readings.</div>
          <a href="user/login.php" class="btn-user"><i class="fas fa-sign-in-alt me-2"></i>User Login</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="container">
    <div class="row align-items-center">
      <div class="col-md-6">
        <div class="footer-brand">GHOST</div>
        <div class="footer-text">Intelligent Egg Incubation Management System</div>
      </div>
      <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <div style="color:var(--ghost-muted);font-size:0.85rem">Version 1.0.0 · Built for precision farming</div>
      </div>
    </div>
    <hr class="footer-divider">
    <div class="footer-copy">© 2025 GHOST Incubator System. All rights reserved.</div>
  </div>
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
