<?php
require_once __DIR__ . '/functions.php';
require_login();

// Redirect customers to their own dashboard
if (is_customer()) {
    header('Location: customer_dashboard.php');
    exit;
}

$db = get_db();

$totals['cylinders'] = $db->query('SELECT COUNT(*) AS cnt FROM cylinders')->fetch()['cnt'];
$totals['customers'] = $db->query('SELECT COUNT(*) AS cnt FROM customers')->fetch()['cnt'];
$totals['suppliers'] = $db->query('SELECT COUNT(*) AS cnt FROM suppliers')->fetch()['cnt'];
$totals['sales']     = $db->query('SELECT COUNT(*) AS cnt FROM invoices')->fetch()['cnt'];
$totals['pending']   = $db->query("SELECT COUNT(*) AS cnt FROM bookings WHERE status='Pending'")->fetch()['cnt'];
$totals['revenue']   = $db->query('SELECT COALESCE(SUM(total_price),0) AS s FROM invoices')->fetch()['s'];

$lowStock = [];
if (low_stock_warning()) {
    $stmt = $db->prepare('SELECT * FROM cylinders WHERE quantity < 10 ORDER BY quantity ASC');
    $stmt->execute();
    $lowStock = $stmt->fetchAll();
}

$recentBookings = $db->query(
    "SELECT b.*, c.name AS customer_name FROM bookings b
     LEFT JOIN customers c ON b.customer_id = c.id
     ORDER BY b.created_at DESC LIMIT 5"
)->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/header.php';
?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">🛢️</div>
    <div class="stat-value"><?php echo (int)$totals['cylinders']; ?></div>
    <div class="stat-label">Cylinder Types</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">👥</div>
    <div class="stat-value"><?php echo (int)$totals['customers']; ?></div>
    <div class="stat-label">Customers</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🏭</div>
    <div class="stat-value"><?php echo (int)$totals['suppliers']; ?></div>
    <div class="stat-label">Suppliers</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🧾</div>
    <div class="stat-value"><?php echo (int)$totals['sales']; ?></div>
    <div class="stat-label">Total Invoices</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⏳</div>
    <div class="stat-value" style="color:var(--warning);"><?php echo (int)$totals['pending']; ?></div>
    <div class="stat-label">Pending Orders</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">💰</div>
    <div class="stat-value" style="color:var(--success); font-size:1.4rem;"><?php echo number_format((float)$totals['revenue'], 0); ?></div>
    <div class="stat-label">Total Revenue</div>
  </div>
</div>

<?php if (!empty($lowStock)): ?>
<div class="card" style="border-color: rgba(239,68,68,0.4);">
  <h2 style="color:var(--danger);">⚠️ Low Stock Alerts</h2>
  <div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Gas Type</th>
        <th>Quantity</th>
        <th>Price</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($lowStock as $item): ?>
        <tr>
          <td><strong><?php echo sanitize($item['gas_type']); ?></strong></td>
          <td><?php echo (int)$item['quantity']; ?></td>
          <td><?php echo format_currency($item['price']); ?></td>
          <td>
            <?php if ($item['quantity'] == 0): ?>
              <span class="badge badge-danger">● Out of Stock</span>
            <?php else: ?>
              <span class="badge badge-warning">● Low Stock</span>
            <?php endif; ?>
          </td>
          <td><a href="cylinders.php?edit=<?php echo (int)$item['id']; ?>" class="button sm">Restock</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <h2>🕓 Recent Bookings</h2>
  <?php if (empty($recentBookings)): ?>
    <p style="color:var(--text-muted); text-align:center; padding:20px 0;">No bookings yet. <a href="bookings.php">Create one →</a></p>
  <?php else: ?>
  <div class="table-wrap">
  <table>
    <thead>
      <tr><th>ID</th><th>Customer</th><th>Gas Type</th><th>Qty</th><th>Date</th><th>Status</th></tr>
    </thead>
    <tbody>
      <?php foreach ($recentBookings as $b): ?>
        <tr>
          <td><code>#<?php echo (int)$b['id']; ?></code></td>
          <td><?php echo sanitize($b['customer_name']); ?></td>
          <td><?php echo sanitize($b['gas_type']); ?></td>
          <td><?php echo (int)$b['quantity']; ?></td>
          <td><?php echo sanitize($b['date']); ?></td>
          <td>
            <?php if ($b['status'] === 'Delivered'): ?>
              <span class="badge badge-success">✓ Delivered</span>
            <?php else: ?>
              <span class="badge badge-warning">⏳ Pending</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div style="margin-top:14px;"><a href="bookings.php" class="button secondary sm">View All Bookings →</a></div>
  <?php endif; ?>
</div>

<?php if (is_admin()): ?>
<div class="card" style="border-color:rgba(124,58,237,0.35); background:linear-gradient(135deg, rgba(124,58,237,0.06), rgba(79,110,247,0.04));">
  <h2 style="color:#c4b5fd;">🧑‍💼 Customer Portals</h2>
  <?php
    $db2 = get_db();
    $custStats = $db2->query(
        "SELECT COUNT(*) AS total,
         SUM(CASE WHEN u.role='customer' THEN 1 ELSE 0 END) AS registered
         FROM customers c LEFT JOIN users u ON u.id=c.user_id"
    )->fetch();
    $pendingAll = $db2->query("SELECT COUNT(*) AS cnt FROM bookings WHERE status='Pending'")->fetch()['cnt'];
  ?>
  <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
    <div style="flex:1; min-width:200px;">
      <p style="color:var(--text-muted); font-size:0.88rem; margin-bottom:12px;">
        Access individual customer dashboards to view their orders, details, and activity.
      </p>
      <div style="display:flex; gap:10px; flex-wrap:wrap; font-size:0.85rem; color:var(--text-muted); margin-bottom:16px;">
        <span>👥 <strong style="color:var(--text);"><?php echo (int)$custStats['registered']; ?></strong> registered customers</span>
        <span>·</span>
        <span>⏳ <strong style="color:var(--warning);"><?php echo (int)$pendingAll; ?></strong> pending orders</span>
      </div>
      <a href="admin_customers.php" class="button" style="background:linear-gradient(135deg,#7c3aed,#4f6ef7);">
        🧑‍💼 View All Customer Dashboards →
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
