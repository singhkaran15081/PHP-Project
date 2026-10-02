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
    $confirm  = $_POST['confirm_password'] ?? '';
    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $email    = trim($_POST['email'] ?? '');

    if ($username === '' || $password === '' || $confirm === '' || $name === '') {
        $error = 'Username, password and full name are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = get_db();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $db->prepare('INSERT INTO users (username, password, role, created_at) VALUES (?,?,?,?)');
            $stmt->execute([$username, $hash, 'customer', date('c')]);
            $userId = $db->lastInsertId();

            // Create linked customer record
            $stmt = $db->prepare('INSERT INTO customers (name, phone, address, email, user_id, created_at) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$name, $phone, $address, $email, $userId, date('c')]);

            flash('Account created! Welcome, ' . $name . '. You can now log in to place orders.');
            header('Location: customer_login.php'); exit;
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
  <title>Customer Register · Gas Inventory System</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card" style="max-width:460px;">
    <div class="auth-logo"><div class="logo-icon">🔥</div> Gas Inventory System</div>
    <div class="admin-pill" style="background:rgba(34,197,94,0.12);border-color:rgba(34,197,94,0.4);color:#86efac;">🛒 Customer Portal</div>
    <div class="auth-subtitle" style="margin-bottom:20px;">Create your customer account to place orders</div>

    <?php if ($error): ?><div class="alert error">⚠️ <?php echo sanitize($error); ?></div><?php endif; ?>

    <form method="post" action="">
      <div class="form-row">
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" name="name" required placeholder="Your full name" value="<?php echo sanitize($_POST['name'] ?? ''); ?>" />
        </div>
        <div class="form-group">
          <label>Username *</label>
          <input type="text" name="username" required placeholder="Choose a username" value="<?php echo sanitize($_POST['username'] ?? ''); ?>" />
        </div>
      </div>
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" placeholder="your@email.com" value="<?php echo sanitize($_POST['email'] ?? ''); ?>" />
      </div>
      <div class="form-group">
        <label>Phone Number</label>
        <input type="text" name="phone" placeholder="Phone number" value="<?php echo sanitize($_POST['phone'] ?? ''); ?>" />
      </div>
      <div class="form-group">
        <label>Delivery Address</label>
        <textarea name="address" placeholder="Your delivery address..."><?php echo sanitize($_POST['address'] ?? ''); ?></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Password *</label>
          <input type="password" name="password" required placeholder="Min. 6 characters" />
        </div>
        <div class="form-group">
          <label>Confirm Password *</label>
          <input type="password" name="confirm_password" required placeholder="Repeat password" />
        </div>
      </div>
      <button class="button" type="submit" style="width:100%; justify-content:center; padding:11px; background:linear-gradient(135deg,#22c55e,#16a34a);">
        🛒 Create Customer Account
      </button>
    </form>

    <div class="divider"></div>
    <p class="text-muted text-center">Already have an account? <a href="customer_login.php">Sign in</a></p>
    <p class="text-muted text-center" style="margin-top:8px;">Staff/Admin? <a href="login.php">Staff Login →</a></p>
  </div>
</div>
</body>
</html>
