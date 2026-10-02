<?php
require_once __DIR__ . '/functions.php';

// If already logged in as admin, redirect to dashboard
if (is_logged_in() && is_admin()) {
    header('Location: dashboard.php');
    exit;
}

// If logged in but not admin, deny
if (is_logged_in() && !is_admin()) {
    flash('Access denied. Admin privileges required.');
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            flash('Welcome, Administrator ' . $user['username'] . '! You have full access.');
            header('Location: dashboard.php');
            exit;
        }

        $error = 'Invalid admin credentials. Only admin accounts are permitted here.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Login · Gas Inventory System</title>
  <link rel="stylesheet" href="styles.css" />
  <style>
    .admin-glow {
      box-shadow: 0 0 0 1px rgba(124,58,237,0.4), 0 20px 60px rgba(124,58,237,0.15);
      border-color: rgba(124,58,237,0.4) !important;
    }
    .admin-glow .form-group input:focus {
      border-color: #7c3aed;
      box-shadow: 0 0 0 3px rgba(124,58,237,0.2);
    }
    .admin-btn {
      background: linear-gradient(135deg, #7c3aed, #4f6ef7) !important;
    }
    .admin-btn:hover {
      box-shadow: 0 4px 16px rgba(124,58,237,0.5) !important;
    }
  </style>
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card admin-glow">
    <div class="auth-logo"><div class="logo-icon">🔥</div> Gas Inventory System</div>
    <div class="admin-pill">🔐 Administrator Portal</div>
    <div class="auth-subtitle" style="margin-bottom:24px;">Restricted access — Admin credentials required</div>

    <?php if ($error): ?>
      <div class="alert error">🚫 <?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <div class="form-group">
        <label for="username">Admin Username</label>
        <input type="text" id="username" name="username" required autofocus
               value="<?php echo sanitize($_POST['username'] ?? ''); ?>"
               placeholder="Admin username" />
      </div>
      <div class="form-group">
        <label for="password">Admin Password</label>
        <input type="password" id="password" name="password" required
               placeholder="••••••••" />
      </div>
      <button class="button admin-btn" type="submit" style="width:100%; justify-content:center; padding:11px;">
        🔐 Sign In as Admin
      </button>
    </form>

    <div class="divider"></div>
    <p class="text-muted text-center">
      Not an admin? <a href="login.php">Regular Login</a>
    </p>

    <div style="margin-top:18px; background:rgba(124,58,237,0.08); border:1px solid rgba(124,58,237,0.25); border-radius:10px; padding:12px 14px; font-size:0.78rem; color:var(--text-muted);">
      <strong style="color:#c4b5fd;">Default Admin Credentials</strong><br/>
      Username: <code style="color:var(--text);">admin</code> &nbsp;|&nbsp; Password: <code style="color:var(--text);">admin123</code><br/>
      <span style="color:rgba(139,144,184,0.6); font-size:0.72rem;">Change these after first login for security.</span>
    </div>
  </div>
</div>
</body>
</html>
