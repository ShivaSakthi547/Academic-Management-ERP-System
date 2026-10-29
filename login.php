<?php
// login.php
session_start();
if (isset($_SESSION['user_id'])) { header("Location: dashboard.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Campus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; font-family: 'Inter', sans-serif; background: #f0f4f8; }
        .auth-wrapper { display: flex; min-height: 100vh; }
        /* LEFT PANEL */
        .auth-left {
            flex: 1.1;
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
            display: flex; flex-direction: column; justify-content: space-between;
            padding: 3rem; position: relative; overflow: hidden; color: white;
        }
        /* Subtle education illustration / vector background */
        .auth-left::before {
            content: "\F19D"; /* mortarboard icon in bootstrap */
            font-family: "bootstrap-icons";
            position: absolute;
            font-size: 35rem;
            opacity: 0.05;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
        }
        .left-content { position:relative; z-index:5; display:flex; flex-direction:column; gap:2.5rem; }
        .brand-logo {
            width: 72px; height: 72px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 20px; display: flex; align-items: center; justify-content: center;
            font-size: 2.2rem; color: white; box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .brand-name { font-size:1.8rem; font-weight:800; color:#fff; line-height:1.3; letter-spacing:-.5px; }
        .brand-tagline { font-size:.95rem; color:rgba(255,255,255,.8); line-height:1.65; max-width:380px; margin-top:.6rem; }
        .feature-list { display:flex; flex-direction:column; gap:1.2rem; }
        .feature-item { display:flex; align-items:center; gap:1rem; }
        .feature-icon {
            width: 44px; height: 44px; border-radius: 12px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(5px);
            display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0;
            color: white;
        }
        .feature-text { color:#fff; font-size:1rem; font-weight:600; }
        .left-footer { position:relative; z-index:5; color:rgba(255,255,255,.7); font-size:.76rem; }

        /* RIGHT PANEL */
        .auth-right {
            flex: 0.9;
            background: #f8fafc;
            display: flex; align-items: center; justify-content: center;
            padding: 3rem 2.5rem; position: relative;
        }
        /* Background pattern for right panel */
        .auth-right::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 30px 30px;
            opacity: 0.4;
            pointer-events: none;
        }
        .form-panel {
            width: 100%; max-width: 440px;
            position: relative; z-index: 5;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 1);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
        }
        .form-heading { font-size:1.8rem; font-weight:800; color:#1e293b; letter-spacing:-.5px; }
        .form-subheading { font-size:.9rem; color:#64748b; margin-top:.35rem; }
        .field-label { display:block; font-size:.8rem; font-weight:600; color:#475569; margin-bottom:.45rem; }
        .field-wrap { position:relative; margin-bottom: 1.2rem; }
        .field-icon { position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:1rem; pointer-events:none; }
        .field-input {
            width: 100%; background: #ffffff;
            border: 1px solid #e2e8f0; border-radius: 12px;
            padding: 0.82rem 1rem 0.82rem 2.8rem; color: #1e293b;
            font-size: 0.95rem; font-family: inherit; transition: all 0.25s ease;
        }
        .field-input:focus { background: #ffffff; border-color: #3b82f6; outline: none; box-shadow: 0 0 0 4px rgba(59,130,246,0.15); }
        .field-input::placeholder { color: #94a3b8; }
        .field-input option { background: #ffffff; color: #1e293b; }
        .eye-btn { position:absolute; right:1rem; top:50%; transform:translateY(-50%); background:none; border:none; color:#94a3b8; cursor:pointer; padding:0; }
        .eye-btn:hover { color:#475569; }
        .btn-signin {
            width:100%; background: #2563eb;
            border: none; border-radius: 12px; color: white; font-weight: 600; font-size: 1rem;
            padding: 0.9rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(37,99,235,0.3); cursor:pointer;
        }
        .btn-signin:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 6px 15px rgba(37,99,235,0.4); }
        .divider { display:flex; align-items:center; gap:1rem; color:#94a3b8; font-size:.8rem; }
        .divider::before,.divider::after { content:""; flex:1; height:1px; background:#e2e8f0; }
        .alert-err { background:#fef2f2; border:1px solid #fecaca; border-left:3px solid #ef4444; border-radius:10px; color:#b91c1c; padding:.9rem 1rem; font-size:.88rem; }
        @media(max-width:900px){.auth-left{display:none} .auth-right{flex:1;}}
    </style>
</head>
<body>
<div class="auth-wrapper">

  <!-- LEFT PANEL -->
  <div class="auth-left">
    <div class="left-content">
      <div>
        <div class="brand-logo mb-3"><i class="bi bi-bank"></i></div>
        <div class="brand-name">Smart Campus Matric<br>Higher Secondary School</div>
        <p class="brand-tagline">Empowering Education Through Smart Technology</p>
      </div>
      <div class="feature-list mt-4">
        <div class="feature-item">
          <div class="feature-icon"><i class="bi bi-award"></i></div>
          <div class="feature-text">Academic Excellence</div>
        </div>
        <div class="feature-item">
          <div class="feature-icon"><i class="bi bi-laptop"></i></div>
          <div class="feature-text">Digital Campus</div>
        </div>
        <div class="feature-item">
          <div class="feature-icon"><i class="bi bi-people"></i></div>
          <div class="feature-text">Student Management</div>
        </div>
        <div class="feature-item">
          <div class="feature-icon"><i class="bi bi-shield-check"></i></div>
          <div class="feature-text">Secure Login</div>
        </div>
      </div>
    </div>
    <div class="left-footer">&copy; <?php echo date('Y'); ?> Smart Campus Matric. All rights reserved</div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="auth-right">
    <div class="form-panel">
      <div class="mb-4">
        <div class="form-heading">Welcome back</div>
        <div class="form-subheading">Sign in to your account to continue</div>
      </div>

      <?php if (isset($_SESSION['error'])): ?>
      <div class="alert-err d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
        <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
      </div>
      <?php endif; ?>

      <form action="action.php?action=login" method="POST" id="loginForm">
        <div class="mb-4">
          <label class="field-label">Username</label>
          <div class="field-wrap">
            <i class="bi bi-person field-icon"></i>
            <input type="text" class="field-input" id="username" name="username" placeholder="Enter your username" required>
          </div>
        </div>
        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="field-label mb-0">Password</label>
            <a href="forgot_password.php" style="font-size:.8rem;color:#2563eb;text-decoration:none;font-weight:600;">Forgot Password?</a>
          </div>
          <div class="field-wrap mb-1">
            <i class="bi bi-lock field-icon"></i>
            <input type="password" class="field-input" id="password" name="password" placeholder="Enter your password" required>
            <button type="button" class="eye-btn" id="togglePassword"><i class="bi bi-eye" id="toggleIcon"></i></button>
          </div>
          <div style="font-size:.75rem;color:#64748b;">Default: DD-MM-YYYY (date of birth)</div>
        </div>
        <div class="mb-4">
          <label class="field-label">Role</label>
          <div class="field-wrap mb-0">
            <i class="bi bi-shield-lock field-icon"></i>
            <select class="field-input" id="role" name="role" required style="appearance:none;padding-right:2.5rem;">
              <option value="" disabled selected>Select your role</option>
              <option value="Admin">Admin</option>
              <option value="Staff">Staff</option>
              <option value="Student">Student</option>
            </select>
            <i class="bi bi-chevron-down" style="position:absolute;right:1rem;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;font-size:.82rem;"></i>
          </div>
        </div>
        <button type="submit" class="btn-signin mb-4 mt-2">
          <span>Sign In</span>
        </button>
      </form>

      <div class="divider mb-4">or</div>
      <div class="text-center">
        <p style="color:#64748b;font-size:.9rem;margin:0;">New to the system?
          <a href="register.php" style="color:#2563eb;font-weight:700;text-decoration:none;margin-left:4px;">Create an account</a>
        </p>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const tp=document.getElementById('togglePassword'),pi=document.getElementById('password'),ti=document.getElementById('toggleIcon');
  if(tp&&pi&&ti){tp.addEventListener('click',()=>{const isP=pi.getAttribute('type')==='password';pi.setAttribute('type',isP?'text':'password');ti.classList.toggle('bi-eye',!isP);ti.classList.toggle('bi-eye-slash',isP);});}
  const te=document.getElementById('liveToast');if(te)new bootstrap.Toast(te).show();
});
</script>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
