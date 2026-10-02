<?php
require_once __DIR__ . '/functions.php';

if (is_logged_in()) { header('Location: dashboard.php'); exit; }

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($username === '' || $password === '' || $confirm === '') {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = get_db();
        $stmt = $db->query('SELECT COUNT(*) AS cnt FROM users');
        $userCount = (int)$stmt->fetch()['cnt'];
        $role = $userCount === 0 ? 'admin' : 'staff';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $db->prepare('INSERT INTO users (username, password, role, created_at) VALUES (?,?,?,?)');
            $stmt->execute([$username, $hash, $role, date('c')]);
            flash('Registration successful! ' . ($role === 'admin' ? 'You are the first user and have been granted Admin access.' : 'You can now log in.'));
            header('Location: login.php'); exit;
        } catch (Exception $e) {
            $error = 'Username already taken. Please choose another.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register · Gas Inventory System</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo"><div class="logo-icon">🔥</div> Gas Inventory System</div>
    <div class="auth-subtitle">Create a new account</div>

    <?php if ($error): ?><div class="alert error">⚠️ <?php echo sanitize($error); ?></div><?php endif; ?>

    <form method="post" action="">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" required autofocus value="<?php echo sanitize($_POST['username'] ?? ''); ?>" placeholder="Choose a username" />
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required placeholder="Min. 6 characters" />
      </div>
      <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" required placeholder="Repeat password" />
      </div>
      <button class="button" type="submit" style="width:100%; justify-content:center; padding:11px;">Create Account</button>
    </form>

    <div class="divider"></div>
    <p class="text-muted text-center">Already have an account? <a href="login.php">Sign in</a></p>
  </div>
</div>
</body>
</html>
