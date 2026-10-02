<?php
require_once __DIR__ . '/functions.php';

if (is_logged_in()) {
    header('Location: ' . (is_customer() ? 'customer_dashboard.php' : 'dashboard.php'));
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Gas Inventory System</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
<div class="auth-wrap" style="flex-direction:column; gap:32px;">
  <div style="text-align:center;">
    <div style="display:flex; align-items:center; justify-content:center; gap:12px; margin-bottom:10px;">
      <div class="logo-icon" style="width:48px; height:48px; font-size:24px; border-radius:14px;">🔥</div>
      <h1 style="font-size:1.8rem; font-weight:800;">Gas Inventory System</h1>
    </div>
    <p style="color:var(--text-muted); font-size:0.95rem;">Select your portal to continue</p>
  </div>

  <div class="portal-split" style="max-width:640px; width:100%; margin:0 auto;">
    <a href="customer_login.php" class="portal-card customer">
      <div class="portal-icon">🛒</div>
      <div class="portal-title">Customer Portal</div>
      <div class="portal-desc">Place and track your gas cylinder orders, view your order history and invoices.</div>
      <div class="button" style="background:linear-gradient(135deg,#22c55e,#16a34a); display:inline-flex;">Sign In as Customer</div>
    </a>
    <a href="login.php" class="portal-card staff">
      <div class="portal-icon">🏢</div>
      <div class="portal-title">Staff Portal</div>
      <div class="portal-desc">Manage inventory, customers, bookings, suppliers and generate reports.</div>
      <div class="button" style="display:inline-flex;">Staff / Admin Login</div>
    </a>
  </div>

  <p style="color:var(--text-muted); font-size:0.82rem; text-align:center;">
    New customer? <a href="customer_register.php">Create a free account →</a>
  </p>
</div>
</body>
</html>
