<?php
require_once __DIR__ . '/functions.php';
require_login();
if (!is_customer()) { header('Location: dashboard.php'); exit; }

$db = get_db();
$customer = current_customer();
if (!$customer) { header('Location: logout.php'); exit; }

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $newpass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($name === '') {
        $error = 'Name is required.';
    } else {
        $stmt = $db->prepare('UPDATE customers SET name=?, phone=?, address=?, email=? WHERE id=?');
        $stmt->execute([$name, $phone, $address, $email, $customer['id']]);

        if ($newpass !== '') {
            if (strlen($newpass) < 6) {
                $error = 'New password must be at least 6 characters.';
            } elseif ($newpass !== $confirm) {
                $error = 'Passwords do not match.';
            } else {
                $hash = password_hash($newpass, PASSWORD_DEFAULT);
                $stmt2 = $db->prepare('UPDATE users SET password=? WHERE id=?');
                $stmt2->execute([$hash, $_SESSION['user_id']]);
            }
        }

        if (!$error) {
            flash('Profile updated successfully.');
            header('Location: customer_profile.php'); exit;
        }
    }
    // Reload customer after update
    $stmt = $db->prepare('SELECT * FROM customers WHERE id=?');
    $stmt->execute([$customer['id']]);
    $customer = $stmt->fetch();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Profile · Gas Inventory System</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
<header>
  <div class="header-inner">
    <a href="customer_dashboard.php" class="logo"><div class="logo-icon">🔥</div> Gas Inventory System</a>
    <nav>
      <a href="customer_dashboard.php">🏠 My Dashboard</a>
      <a href="customer_profile.php" class="active">👤 My Profile</a>
    </nav>
    <div class="nav-end">
      <span class="badge" style="background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.3);color:#86efac;margin-right:6px;">
        🛒 <?php echo sanitize($_SESSION['username']); ?>
      </span>
      <a href="logout.php" class="button secondary sm">Logout</a>
    </div>
  </div>
</header>
<main class="container">
  <?php if ($msg = flash()): ?>
    <div class="alert success"><?php echo sanitize($msg); ?></div>
  <?php endif; ?>

  <div class="card" style="max-width:580px; margin:0 auto;">
    <h2>👤 My Profile</h2>
    <?php if ($error): ?><div class="alert error">⚠️ <?php echo sanitize($error); ?></div><?php endif; ?>
    <form method="post" action="">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" required value="<?php echo sanitize($customer['name']); ?>" />
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" placeholder="Phone number" value="<?php echo sanitize($customer['phone'] ?? ''); ?>" />
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" placeholder="your@email.com" value="<?php echo sanitize($customer['email'] ?? ''); ?>" />
        </div>
      </div>
      <div class="form-group">
        <label>Delivery Address</label>
        <textarea name="address"><?php echo sanitize($customer['address'] ?? ''); ?></textarea>
      </div>
      <div style="height:1px; background:var(--border); margin:20px 0;"></div>
      <p style="font-size:0.82rem; color:var(--text-muted); margin-bottom:14px;">Leave password fields blank to keep your current password.</p>
      <div class="form-row">
        <div class="form-group">
          <label>New Password</label>
          <input type="password" name="new_password" placeholder="Min. 6 characters" />
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" placeholder="Repeat new password" />
        </div>
      </div>
      <button class="button" type="submit" style="background:linear-gradient(135deg,#22c55e,#16a34a);">💾 Save Changes</button>
      <a href="customer_dashboard.php" class="button secondary" style="margin-left:10px;">Cancel</a>
    </form>
  </div>
</main>
<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
