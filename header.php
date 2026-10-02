<?php
require_once __DIR__ . '/functions.php';

$currentPage = basename($_SERVER['SCRIPT_NAME']);
$isAdmin = is_admin();
$isCustomer = is_customer();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo sanitize(APP_NAME); ?><?php if (!empty($pageTitle)) echo ' · ' . sanitize($pageTitle); ?></title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
<header>
  <div class="header-inner">
    <a href="<?php echo $isCustomer ? 'customer_dashboard.php' : 'dashboard.php'; ?>" class="logo">
      <div class="logo-icon">🔥</div>
      <?php echo sanitize(APP_NAME); ?>
    </a>
    <?php if (is_logged_in()): ?>
    <?php if (!$isCustomer): ?>
    <nav>
      <a href="dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">📊 Dashboard</a>
      <a href="cylinders.php" class="<?php echo $currentPage === 'cylinders.php' ? 'active' : ''; ?>">🛢️ Cylinders</a>
      <a href="suppliers.php" class="<?php echo $currentPage === 'suppliers.php' ? 'active' : ''; ?>">🏭 Suppliers</a>
      <a href="customers.php" class="<?php echo $currentPage === 'customers.php' ? 'active' : ''; ?>">👥 Customers</a>
      <a href="bookings.php" class="<?php echo $currentPage === 'bookings.php' ? 'active' : ''; ?>">📋 Bookings</a>
      <a href="invoice.php" class="<?php echo $currentPage === 'invoice.php' ? 'active' : ''; ?>">🧾 Invoices</a>
      <a href="reports.php" class="<?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>">📈 Reports</a>
      <?php if ($isAdmin): ?>
      <a href="admin_customers.php" class="<?php echo $currentPage === 'admin_customers.php' ? 'active' : ''; ?>" style="color:#c4b5fd;">🧑‍💼 Customer Portals</a>
      <?php endif; ?>
    </nav>
    <?php endif; ?>
    <div class="nav-end">
      <span class="badge <?php echo $isAdmin ? 'badge-admin' : ($isCustomer ? '' : 'badge-info'); ?>" style="<?php echo $isCustomer ? 'background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.3);color:#86efac;' : ''; ?> margin-right:6px;">
        <?php
          if ($isAdmin) echo '👑 Admin';
          elseif ($isCustomer) echo '🛒 ' . sanitize($_SESSION['username'] ?? 'Customer');
          else echo '👤 ' . sanitize($_SESSION['username'] ?? 'Staff');
        ?>
      </span>
      <a href="logout.php" class="button secondary sm">Logout</a>
    </div>
    <?php else: ?>
    <div class="nav-end">
      <a href="customer_login.php" class="button secondary sm">🛒 Customer Login</a>
      <a href="login.php" class="button sm">Staff Login</a>
    </div>
    <?php endif; ?>
  </div>
</header>
<main class="container">
  <?php if ($msg = flash()): ?>
    <div class="alert <?php
      if (strpos($msg, 'success') !== false || strpos($msg, 'successfully') !== false || strpos($msg, 'Welcome') !== false) echo 'success';
      elseif (strpos($msg, 'error') !== false || strpos($msg, 'denied') !== false || strpos($msg, 'Invalid') !== false) echo 'error';
      else echo 'warning';
    ?>">
      <?php echo sanitize($msg); ?>
    </div>
  <?php endif; ?>
