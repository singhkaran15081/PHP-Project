<?php
require_once __DIR__ . '/functions.php';

if (is_logged_in()) {
    header('Location: ' . (is_customer() ? 'customer_dashboard.php' : 'dashboard.php'));
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
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND role IN ('admin','staff') LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            flash('Welcome back, ' . $user['username'] . '! Logged in successfully.');
            header('Location: dashboard.php');
            exit;
        }

        $error = 'Invalid username or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Staff Login · Gas Inventory System</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo"><div class="logo-icon">🔥</div> Gas Inventory System</div>
    <div class="admin-pill">👤 Staff Portal</div>
    <div class="auth-subtitle">Sign in to your staff account</div>

    <?php if ($error): ?>
      <div class="alert error">⚠️ <?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus
               value="<?php echo sanitize($_POST['username'] ?? ''); ?>"
               placeholder="Enter your username" />
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required placeholder="••••••••" />
      </div>
      <button class="button" type="submit" style="width:100%; justify-content:center; padding:11px;">Sign In</button>
    </form>

    <div class="divider"></div>
    <p class="text-muted text-center">
      Admin? <a href="admin.php">Admin Login →</a>
    </p>
    <div style="margin-top:16px; padding:14px; background:rgba(34,197,94,0.07); border:1px solid rgba(34,197,94,0.25); border-radius:10px; text-align:center;">
      <div style="font-size:0.82rem; color:var(--text-muted); margin-bottom:8px;">Are you a customer?</div>
      <a href="customer_login.php" class="button sm" style="background:linear-gradient(135deg,#22c55e,#16a34a);">🛒 Customer Login</a>
      &nbsp;
      <a href="customer_register.php" class="button secondary sm">Register</a>
    </div>
  </div>
</div>
</body>
</html>
