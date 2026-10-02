<?php
require_once __DIR__ . '/functions.php';
require_login();
if (is_customer()) { header('Location: customer_dashboard.php'); exit; }

$db = get_db();

$inventory = $db->query('SELECT * FROM cylinders ORDER BY gas_type ASC')->fetchAll();

$dailyDate = $_GET['daily_date'] ?? date('Y-m-d');
$stmt = $db->prepare('SELECT COUNT(*) AS orders, COALESCE(SUM(total_price),0) AS total FROM invoices WHERE date=?');
$stmt->execute([$dailyDate]);
$daily = $stmt->fetch();

$month = $_GET['month'] ?? date('Y-m');
$stmt = $db->prepare("SELECT COUNT(*) AS orders, COALESCE(SUM(total_price),0) AS total FROM invoices WHERE substr(date,1,7)=?");
$stmt->execute([$month]);
$monthly = $stmt->fetch();

$topCustomers = $db->query(
    'SELECT c.name, COUNT(b.id) AS orders, COALESCE(SUM(i.total_price),0) AS revenue
     FROM customers c
     LEFT JOIN bookings b ON b.customer_id=c.id
     LEFT JOIN invoices i ON i.booking_id=b.id
     GROUP BY c.id ORDER BY revenue DESC LIMIT 5'
)->fetchAll();

$pageTitle = 'Reports';
require __DIR__ . '/header.php';
?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">📦</div>
    <div class="stat-value"><?php echo count($inventory); ?></div>
    <div class="stat-label">Cylinder Types</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📅</div>
    <div class="stat-value"><?php echo (int)$daily['orders']; ?></div>
    <div class="stat-label">Today's Orders</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">💵</div>
    <div class="stat-value" style="font-size:1.5rem;"><?php echo number_format((float)$daily['total'], 0); ?></div>
    <div class="stat-label">Today's Revenue</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📈</div>
    <div class="stat-value" style="font-size:1.5rem;"><?php echo number_format((float)$monthly['total'], 0); ?></div>
    <div class="stat-label">Monthly Revenue</div>
  </div>
</div>

<div class="card">
  <h2>🛢️ Inventory Report</h2>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Gas Type</th><th>Quantity</th><th>Price / Unit</th><th>Est. Value</th><th>Stock Status</th></tr></thead>
    <tbody>
      <?php foreach ($inventory as $item): $val = $item['quantity'] * $item['price']; ?>
        <tr>
          <td><strong><?php echo sanitize($item['gas_type']); ?></strong></td>
          <td><?php echo (int)$item['quantity']; ?></td>
          <td><?php echo format_currency($item['price']); ?></td>
          <td><?php echo format_currency($val); ?></td>
          <td>
            <?php $q = (int)$item['quantity'];
              if ($q == 0) echo '<span class="badge badge-danger">Out of Stock</span>';
              elseif ($q < 10) echo '<span class="badge badge-warning">Low Stock</span>';
              else echo '<span class="badge badge-success">In Stock</span>';
            ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; flex-wrap:wrap;">
  <div class="card">
    <h2>📅 Daily Sales Report</h2>
    <form method="get" action="" style="display:flex; gap:10px; align-items:flex-end; margin-bottom:16px;">
      <div class="form-group" style="flex:1; margin-bottom:0;">
        <label>Select Date</label>
        <input type="date" name="daily_date" value="<?php echo sanitize($dailyDate); ?>" />
      </div>
      <button class="button" type="submit">Show</button>
    </form>
    <div style="display:flex; flex-direction:column; gap:10px;">
      <div style="background:var(--surface2); border-radius:10px; padding:14px; display:flex; justify-content:space-between; align-items:center;">
        <span style="color:var(--text-muted); font-size:0.82rem;">Total Orders</span>
        <strong style="font-size:1.3rem; font-family:JetBrains Mono,monospace;"><?php echo (int)$daily['orders']; ?></strong>
      </div>
      <div style="background:var(--surface2); border-radius:10px; padding:14px; display:flex; justify-content:space-between; align-items:center;">
        <span style="color:var(--text-muted); font-size:0.82rem;">Total Sales</span>
        <strong style="font-size:1.3rem; color:var(--success);"><?php echo format_currency((float)$daily['total']); ?></strong>
      </div>
    </div>
  </div>

  <div class="card">
    <h2>📆 Monthly Sales Report</h2>
    <form method="get" action="" style="display:flex; gap:10px; align-items:flex-end; margin-bottom:16px;">
      <div class="form-group" style="flex:1; margin-bottom:0;">
        <label>Select Month</label>
        <input type="month" name="month" value="<?php echo sanitize($month); ?>" />
      </div>
      <button class="button" type="submit">Show</button>
    </form>
    <div style="display:flex; flex-direction:column; gap:10px;">
      <div style="background:var(--surface2); border-radius:10px; padding:14px; display:flex; justify-content:space-between; align-items:center;">
        <span style="color:var(--text-muted); font-size:0.82rem;">Total Orders</span>
        <strong style="font-size:1.3rem; font-family:JetBrains Mono,monospace;"><?php echo (int)$monthly['orders']; ?></strong>
      </div>
      <div style="background:var(--surface2); border-radius:10px; padding:14px; display:flex; justify-content:space-between; align-items:center;">
        <span style="color:var(--text-muted); font-size:0.82rem;">Total Revenue</span>
        <strong style="font-size:1.3rem; color:var(--accent);"><?php echo format_currency((float)$monthly['total']); ?></strong>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($topCustomers)): ?>
<div class="card">
  <h2>🏆 Top Customers</h2>
  <div class="table-wrap">
  <table>
    <thead><tr><th>#</th><th>Customer</th><th>Orders</th><th>Revenue</th></tr></thead>
    <tbody>
      <?php foreach ($topCustomers as $i => $tc): ?>
        <tr>
          <td><?php echo ['🥇','🥈','🥉','4.','5.'][$i] ?? ($i+1).'.'; ?></td>
          <td><strong><?php echo sanitize($tc['name']); ?></strong></td>
          <td><span class="badge badge-info"><?php echo (int)$tc['orders']; ?></span></td>
          <td style="color:var(--success);"><?php echo format_currency((float)$tc['revenue']); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
