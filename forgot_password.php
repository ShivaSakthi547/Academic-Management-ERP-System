<?php
// forgot_password.php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; font-family: 'Inter', sans-serif; }
        .auth-wrapper { display: flex; min-height: 100vh; }
        /* LEFT PANEL */
        .auth-left {
            flex: 1.1;
            background: linear-gradient(145deg, #0f1a3e 0%, #1a2d6b 40%, #0d3060 70%, #0a1628 100%);
            display: flex; flex-direction: column; justify-content: space-between;
            padding: 3rem; position: relative; overflow: hidden;
        }
        .auth-left::before { content:""; position:absolute; width:420px; height:420px; background:radial-gradient(circle,rgba(99,102,241,.35) 0%,transparent 70%); top:-80px; left:-80px; border-radius:50%; }
        .auth-left::after  { content:""; position:absolute; width:350px; height:350px; background:radial-gradient(circle,rgba(14,165,233,.25) 0%,transparent 70%); bottom:-60px; right:-60px; border-radius:50%; }
        .orb-mid { position:absolute; width:260px; height:260px; background:radial-gradient(circle,rgba(139,92,246,.2) 0%,transparent 70%); top:50%; left:60%; transform:translate(-50%,-50%); border-radius:50%; }
        .grid-overlay { position:absolute; inset:0; background-image:linear-gradient(rgba(255,255,255,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.03) 1px,transparent 1px); background-size:50px 50px; }
        .left-content { position:relative; z-index:5; display:flex; flex-direction:column; gap:2.5rem; }
        .brand-logo { width:72px; height:72px; background:linear-gradient(135deg,#6366f1,#8b5cf6,#0ea5e9); border-radius:20px; display:flex; align-items:center; justify-content:center; font-size:2.2rem; color:white; box-shadow:0 12px 32px rgba(99,102,241,.45); }
        .brand-name { font-size:1.9rem; font-weight:800; color:#fff; line-height:1.2; letter-spacing:-.5px; }
        .brand-name span { color:#7dd3fc; }
        .brand-tagline { font-size:.92rem; color:rgba(255,255,255,.5); line-height:1.65; max-width:340px; margin-top:.6rem; }
        .feature-list { display:flex; flex-direction:column; gap:1rem; }
        .feature-item { display:flex; align-items:center; gap:1rem; }
        .feature-icon { width:44px; height:44px; border-radius:12px; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.1); display:flex; align-items:center; justify-content:center; font-size:1.15rem; flex-shrink:0; }
        .fi-blue{color:#818cf8} .fi-green{color:#34d399} .fi-yellow{color:#fbbf24} .fi-red{color:#f87171}
        .feature-text { color:rgba(255,255,255,.82); font-size:.88rem; font-weight:600; }
        .feature-sub  { color:rgba(255,255,255,.38); font-size:.74rem; margin-top:2px; }
        .stats-row { display:flex; gap:1rem; }
        .stat-box { flex:1; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); border-radius:14px; padding:1rem; text-align:center; }
        .stat-num { font-size:1.55rem; font-weight:800; color:#7dd3fc; }
        .stat-lbl { font-size:.7rem; color:rgba(255,255,255,.4); text-transform:uppercase; letter-spacing:.8px; margin-top:3px; }
        .left-footer { position:relative; z-index:5; color:rgba(255,255,255,.28); font-size:.76rem; }
        /* RIGHT PANEL */
        .auth-right { flex:.9; background:#0f172a; display:flex; align-items:center; justify-content:center; padding:3rem 2.5rem; position:relative; }
        .auth-right::before { content:""; position:absolute; width:280px; height:280px; background:radial-gradient(circle,rgba(99,102,241,.1) 0%,transparent 70%); bottom:0; right:0; border-radius:50%; }
        .form-panel { width:100%; max-width:420px; position:relative; z-index:5; }
        .form-heading { font-size:1.8rem; font-weight:800; color:#f1f5f9; letter-spacing:-.5px; }
        .form-subheading { font-size:.88rem; color:rgba(255,255,255,.4); margin-top:.35rem; }
        .field-label { display:block; font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:rgba(255,255,255,.55); margin-bottom:.45rem; }
        .field-wrap { position:relative; margin-bottom: 1.5rem; }
        .field-icon { position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:rgba(255,255,255,.3); font-size:1rem; pointer-events:none; }
        .field-input { width:100%; background:rgba(255,255,255,.05); border:1.5px solid rgba(255,255,255,.08); border-radius:12px; padding:.82rem 1rem .82rem 2.8rem; color:#f1f5f9; font-size:.95rem; font-family:inherit; transition:all .25s ease; }
        .field-input:focus { background:rgba(99,102,241,.08); border-color:#6366f1; outline:none; box-shadow:0 0 0 4px rgba(99,102,241,.12); }
        .field-input::placeholder { color:rgba(255,255,255,.2); }
        .btn-signin { width:100%; background:linear-gradient(135deg,#6366f1,#4f46e5); border:none; border-radius:12px; color:white; font-weight:700; font-size:1rem; padding:.9rem; display:flex; align-items:center; justify-content:center; gap:.5rem; transition:all .3s ease; box-shadow:0 6px 20px rgba(99,102,241,.35); cursor:pointer; }
        .btn-signin:hover { transform:translateY(-2px); box-shadow:0 10px 30px rgba(99,102,241,.5); }
        .divider { display:flex; align-items:center; gap:1rem; color:rgba(255,255,255,.2); font-size:.8rem; }
        .divider::before,.divider::after { content:""; flex:1; height:1px; background:rgba(255,255,255,.08); }
        .alert-err { background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.25); border-left:3px solid #ef4444; border-radius:10px; color:#fca5a5; padding:.9rem 1rem; font-size:.88rem; margin-bottom:1.5rem; }
        @media(max-width:900px){.auth-left{display:none} .auth-right{flex:1;background:linear-gradient(145deg,#0f1a3e,#0f172a)}}
    </style>
</head>
<body>
<div class="auth-wrapper">

  <!-- LEFT PANEL -->
  <div class="auth-left">
    <div class="grid-overlay"></div>
    <div class="orb-mid"></div>
    <div class="left-content">
      <div>
        <div class="brand-logo mb-3"><i class="bi bi-mortarboard-fill"></i></div>
        <div class="brand-name">Academic<br><span>Management</span> System</div>
        <p class="brand-tagline">A comprehensive platform for managing students, staff, academics, and institutional operations &mdash; all in one place.</p>
      </div>
      <div class="feature-list">
        <div class="feature-item">
          <div class="feature-icon fi-blue"><i class="bi bi-people-fill"></i></div>
          <div><div class="feature-text">Student &amp; Staff Management</div><div class="feature-sub">Manage enrollment, profiles and roles</div></div>
        </div>
        <div class="feature-item">
          <div class="feature-icon fi-green"><i class="bi bi-calendar2-check-fill"></i></div>
          <div><div class="feature-text">Attendance &amp; Exam Tracking</div><div class="feature-sub">Real-time records and reports</div></div>
        </div>
        <div class="feature-item">
          <div class="feature-icon fi-yellow"><i class="bi bi-bar-chart-fill"></i></div>
          <div><div class="feature-text">Performance Analytics</div><div class="feature-sub">Grades, marks and class rankings</div></div>
        </div>
        <div class="feature-item">
          <div class="feature-icon fi-red"><i class="bi bi-shield-fill-check"></i></div>
          <div><div class="feature-text">Secure Role-Based Access</div><div class="feature-sub">Admin, Staff and Student portals</div></div>
        </div>
      </div>
      <div class="stats-row">
        <div class="stat-box"><div class="stat-num">3</div><div class="stat-lbl">User Roles</div></div>
        <div class="stat-box"><div class="stat-num">100%</div><div class="stat-lbl">Secure</div></div>
        <div class="stat-box"><div class="stat-num">24/7</div><div class="stat-lbl">Access</div></div>
      </div>
    </div>
    <div class="left-footer">&copy; <?php echo date('Y'); ?> Academic Management System &middot; All rights reserved</div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="auth-right">
    <div class="form-panel">
      <div class="mb-5">
        <div class="form-heading">Reset Password</div>
        <div class="form-subheading">Enter your registered email to receive a reset link</div>
      </div>

      <?php if (isset($_SESSION['error'])): ?>
      <div class="alert-err d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
        <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
      </div>
      <?php endif; ?>

      <form action="action.php?action=send_reset_link" method="POST" id="forgotForm">
        <div class="mb-4">
          <label class="field-label">Registered Email Address</label>
          <div class="field-wrap">
            <i class="bi bi-envelope field-icon"></i>
            <input type="email" class="field-input" id="email" name="email" placeholder="your@email.com" required>
          </div>
        </div>
        <button type="submit" class="btn-signin mb-4">
          <span>Send Reset Link</span><i class="bi bi-send fs-5"></i>
        </button>
      </form>

      <div class="divider mb-4"></div>
      <div class="text-center">
        <p style="color:rgba(255,255,255,0.38);font-size:.88rem;margin:0;">
          <a href="login.php" style="color:#818cf8;font-weight:700;text-decoration:none;"><i class="bi bi-arrow-left me-1"></i> Back to Sign In</a>
        </p>
      </div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
