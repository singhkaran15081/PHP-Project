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
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND role = 'customer' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            flash('Welcome back, ' . $user['username'] . '!');
            header('Location: customer_dashboard.php');
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
  <title>Customer Login · Gas Inventory System</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo"><div class="logo-icon">🔥</div> Gas Inventory System</div>
    <div class="admin-pill" style="background:rgba(34,197,94,0.12);border-color:rgba(34,197,94,0.4);color:#86efac;">🛒 Customer Portal</div>
    <div class="auth-subtitle" style="margin-bottom:24px;">Sign in to manage your orders</div>

    <?php if ($error): ?>
      <div class="alert error">⚠️ <?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" required autofocus
               value="<?php echo sanitize($_POST['username'] ?? ''); ?>"
               placeholder="Your username" />
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required placeholder="••••••••" />
      </div>
      <button class="button" type="submit" style="width:100%; justify-content:center; padding:11px; background:linear-gradient(135deg,#22c55e,#16a34a);">
        🛒 Sign In
      </button>
    </form>

    <div class="divider"></div>
    <p class="text-muted text-center">
      New customer? <a href="customer_register.php">Create Account</a>
      &nbsp;·&nbsp; <a href="login.php">Staff Login</a>
    </p>
  </div>
</div>
</body>
</html>
