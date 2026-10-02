<?php
require_once __DIR__ . '/functions.php';
require_login();
if (!is_admin()) {
    flash('Access denied. Admin privileges required.');
    header('Location: dashboard.php'); exit;
}

$db = get_db();

// Admin viewing a specific customer's dashboard
$viewingCustomerId = (int)($_GET['customer_id'] ?? 0);-
$viewingCustomer   = null;
$customerOrders    = [];
$customerStats     = [];

if ($viewingCustomerId > 0) {
    $stmt = $db->prepare('SELECT c.*, u.username, u.created_at as user_created FROM customers c LEFT JOIN users u ON u.id=c.user_id WHERE c.id=?');
    $stmt->execute([$viewingCustomerId]);
    $viewingCustomer = $stmt->fetch();

    if ($viewingCustomer) {
        $customerOrders = $db->query(
            "SELECT b.*, i.total_price FROM bookings b
             LEFT JOIN invoices i ON i.booking_id=b.id
             WHERE b.customer_id=" . $viewingCustomerId . "
             ORDER BY b.created_at DESC"
        )->fetchAll();

        $customerStats['total']     = count($customerOrders);
        $customerStats['pending']   = count(array_filter($customerOrders, fn($o) => $o['status'] === 'Pending'));
        $customerStats['delivered'] = count(array_filter($customerOrders, fn($o) => $o['status'] === 'Delivered'));
        $customerStats['cancelled'] = count(array_filter($customerOrders, fn($o) => $o['status'] === 'Cancelled'));
        $customerStats['spent']     = array_sum(array_column($customerOrders, 'total_price'));
    }
}

// All registered customers (with user accounts)
$allCustomers = $db->query(
    "SELECT c.*, u.username, u.created_at as user_created,
     (SELECT COUNT(*) FROM bookings b WHERE b.customer_id=c.id) AS total_orders,
     (SELECT COUNT(*) FROM bookings b WHERE b.customer_id=c.id AND b.status='Pending') AS pending_orders,
     (SELECT COALESCE(SUM(i.total_price),0) FROM bookings b LEFT JOIN invoices i ON i.booking_id=b.id WHERE b.customer_id=c.id) AS total_spent,
     (SELECT b.created_at FROM bookings b WHERE b.customer_id=c.id ORDER BY b.created_at DESC LIMIT 1) AS last_order_at
     FROM customers c
     INNER JOIN users u ON u.id=c.user_id
     WHERE u.role='customer'
     ORDER BY c.name ASC"
)->fetchAll();

$pageTitle = $viewingCustomer ? 'Customer: ' . $viewingCustomer['name'] : 'Customer Dashboards';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo sanitize($pageTitle); ?> · Gas Inventory System</title>
  <link rel="stylesheet" href="styles.css" />
  <style>
    .customer-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }
    .customer-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 20px;
      transition: all 0.25s;
      cursor: pointer;
      text-decoration: none;
      display: block;
    }
    .customer-card:hover {
      border-color: var(--accent);
      transform: translateY(-3px);
      box-shadow: 0 8px 24px rgba(79,110,247,0.15);
      opacity: 1;
    }
    .customer-card .cust-name { font-weight: 700; font-size: 1rem; margin-bottom: 4px; color: var(--text); }
    .customer-card .cust-meta { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 12px; }
    .customer-card .cust-stats { display: flex; gap: 10px; flex-wrap: wrap; }
    .cust-stat { background: var(--surface2); border-radius: 8px; padding: 6px 10px; font-size: 0.75rem; }
    .cust-stat .val { font-weight: 700; font-size: 1rem; display: block; }
    .admin-viewing-banner {
      background: linear-gradient(135deg, rgba(79,110,247,0.15), rgba(124,58,237,0.1));
      border: 1px solid rgba(79,110,247,0.4);
      border-radius: 12px;
      padding: 14px 20px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .price-tag { color: var(--success); font-family: 'JetBrains Mono', monospace; font-weight: 600; }
    .activity-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--success); display: inline-block; margin-right: 4px; }
    .no-activity .activity-dot { background: var(--text-muted); }
  </style>
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<?php if ($viewingCustomer): ?>
  <!-- Admin Viewing Customer Dashboard -->
  <div class="admin-viewing-banner">
    <div style="font-size:1.5rem;">👁️</div>
    <div>
      <strong>Admin View:</strong> You are viewing <strong><?php echo sanitize($viewingCustomer['name']); ?></strong>'s dashboard
      <span style="font-size:0.8rem; color:var(--text-muted); margin-left:8px;">@<?php echo sanitize($viewingCustomer['username']); ?></span>
    </div>
    <a href="admin_customers.php" class="button secondary sm" style="margin-left:auto;">← Back to All Customers</a>
  </div>

  <!-- Customer Details -->
  <div class="card">
    <h2>👤 Customer Information</h2>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
      <div>
        <div style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.07em; margin-bottom:4px;">Full Name</div>
        <div style="font-weight:600;"><?php echo sanitize($viewingCustomer['name']); ?></div>
      </div>
      <div>
        <div style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.07em; margin-bottom:4px;">Username</div>
        <div><code>@<?php echo sanitize($viewingCustomer['username']); ?></code></div>
      </div>
      <div>
        <div style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.07em; margin-bottom:4px;">Phone</div>
        <div><?php echo $viewingCustomer['phone'] ? sanitize($viewingCustomer['phone']) : '<span style="color:var(--text-muted)">—</span>'; ?></div>
      </div>
      <div>
        <div style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.07em; margin-bottom:4px;">Email</div>
        <div><?php echo $viewingCustomer['email'] ? sanitize($viewingCustomer['email']) : '<span style="color:var(--text-muted)">—</span>'; ?></div>
      </div>
      <div>
        <div style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.07em; margin-bottom:4px;">Address</div>
        <div><?php echo $viewingCustomer['address'] ? sanitize($viewingCustomer['address']) : '<span style="color:var(--text-muted)">—</span>'; ?></div>
      </div>
      <div>
        <div style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.07em; margin-bottom:4px;">Customer Since</div>
        <div><?php echo sanitize(substr($viewingCustomer['created_at'], 0, 10)); ?></div>
      </div>
    </div>
  </div>

  <!-- Customer Stats -->
  <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));">
    <div class="stat-card"><div class="stat-icon">📦</div><div class="stat-value"><?php echo $customerStats['total']; ?></div><div class="stat-label">Total Orders</div></div>
    <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-value" style="color:var(--warning);"><?php echo $customerStats['pending']; ?></div><div class="stat-label">Pending</div></div>
    <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-value" style="color:var(--success);"><?php echo $customerStats['delivered']; ?></div><div class="stat-label">Delivered</div></div>
    <div class="stat-card"><div class="stat-icon">✖</div><div class="stat-value" style="color:var(--danger);"><?php echo $customerStats['cancelled']; ?></div><div class="stat-label">Cancelled</div></div>
    <div class="stat-card"><div class="stat-icon">💰</div><div class="stat-value" style="color:var(--success); font-size:1.2rem;"><?php echo format_currency($customerStats['spent']); ?></div><div class="stat-label">Total Spent</div></div>
  </div>

  <!-- Customer Orders -->
  <div class="card">
    <h2>📦 Order History</h2>
    <?php if (empty($customerOrders)): ?>
      <p style="text-align:center; color:var(--text-muted); padding:24px;">This customer has no orders yet.</p>
    <?php else: ?>
    <div class="search-wrap">
      <input class="search-input" type="text" placeholder="🔍 Search orders..." oninput="filterTable(this)" />
      <span class="search-label" id="orderCount"><?php echo count($customerOrders); ?> orders</span>
    </div>
    <div class="table-wrap">
      <table id="ordersTable">
        <thead>
          <tr><th>Order #</th><th>Gas Type</th><th>Qty</th><th>Delivery Date</th><th>Ordered On</th><th>Status</th><th>Total</th><th>Notes</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php foreach ($customerOrders as $o): ?>
            <tr>
              <td><code>#<?php echo (int)$o['id']; ?></code></td>
              <td><strong><?php echo sanitize($o['gas_type']); ?></strong></td>
              <td><?php echo (int)$o['quantity']; ?></td>
              <td><?php echo sanitize($o['date']); ?></td>
              <td style="color:var(--text-muted); font-size:0.82rem;"><?php echo substr($o['created_at'],0,10); ?></td>
              <td>
                <?php if ($o['status'] === 'Delivered'): ?>
                  <span class="badge badge-success">✅ Delivered</span>
                <?php elseif ($o['status'] === 'Cancelled'): ?>
                  <span class="badge badge-danger">✖ Cancelled</span>
                <?php else: ?>
                  <span class="badge badge-warning">⏳ Pending</span>
                <?php endif; ?>
              </td>
              <td class="price-tag"><?php echo isset($o['total_price']) ? format_currency($o['total_price']) : '—'; ?></td>
              <td style="color:var(--text-muted); font-size:0.82rem; max-width:160px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                <?php echo $o['notes'] ? sanitize($o['notes']) : '—'; ?>
              </td>
              <td>
                <?php if ($o['status'] === 'Pending'): ?>
                  <a class="button success sm" href="bookings.php?mark_delivered=<?php echo (int)$o['id']; ?>&redirect=admin_customers.php?customer_id=<?php echo $viewingCustomerId; ?>"
                     onclick="return confirm('Mark order #<?php echo (int)$o['id']; ?> as delivered?')">✓ Deliver</a>
                <?php else: ?>
                  <span style="color:var(--text-muted); font-size:0.8rem;">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

<?php else: ?>
  <!-- All Customer Dashboards Overview -->
  <div class="card" style="border-color:rgba(79,110,247,0.3);">
    <h2>👥 Customer Dashboards</h2>
    <p style="color:var(--text-muted); font-size:0.88rem; margin-top:-12px; margin-bottom:0;">
      Click on any customer to view their full dashboard, order history, and details.
    </p>
  </div>

  <?php if (empty($allCustomers)): ?>
    <div class="card" style="text-align:center; padding:40px;">
      <div style="font-size:2.5rem; margin-bottom:12px;">👤</div>
      <h3 style="margin-bottom:8px;">No Customer Accounts Yet</h3>
      <p style="color:var(--text-muted);">Customers can register via <a href="customer_register.php">customer_register.php</a>.<br>
      Or you can <a href="customers.php">manually add customers</a> and link them to accounts.</p>
    </div>
  <?php else: ?>

  <!-- Search -->
  <div style="margin-bottom:16px;">
    <input class="search-input" type="text" style="max-width:400px;" placeholder="🔍 Search customers..." oninput="filterCards(this)" />
    <span style="margin-left:12px; font-size:0.82rem; color:var(--text-muted);" id="cardCount"><?php echo count($allCustomers); ?> customers</span>
  </div>

  <!-- Summary Stats -->
  <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); margin-bottom:24px;">
    <div class="stat-card">
      <div class="stat-icon">👥</div>
      <div class="stat-value"><?php echo count($allCustomers); ?></div>
      <div class="stat-label">Total Customers</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">📦</div>
      <div class="stat-value"><?php echo array_sum(array_column($allCustomers, 'total_orders')); ?></div>
      <div class="stat-label">Total Orders</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">⏳</div>
      <div class="stat-value" style="color:var(--warning);"><?php echo array_sum(array_column($allCustomers, 'pending_orders')); ?></div>
      <div class="stat-label">Pending Orders</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">💰</div>
      <div class="stat-value" style="color:var(--success); font-size:1.2rem;"><?php echo format_currency(array_sum(array_column($allCustomers, 'total_spent'))); ?></div>
      <div class="stat-label">Total Revenue</div>
    </div>
  </div>

  <div class="customer-grid" id="customerGrid">
    <?php foreach ($allCustomers as $c): ?>
      <a href="admin_customers.php?customer_id=<?php echo (int)$c['id']; ?>" class="customer-card" data-name="<?php echo sanitize(strtolower($c['name'])); ?>">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
          <div style="width:42px; height:42px; background:linear-gradient(135deg,var(--accent),var(--accent2)); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0;">
            <?php echo strtoupper(substr($c['name'],0,1)); ?>
          </div>
          <div>
            <div class="cust-name"><?php echo sanitize($c['name']); ?></div>
            <div class="cust-meta">
              <span class="<?php echo $c['last_order_at'] ? '' : 'no-activity'; ?>">
                <span class="activity-dot"></span>
              </span>
              @<?php echo sanitize($c['username']); ?>
              <?php if ($c['phone']): ?> · <?php echo sanitize($c['phone']); ?><?php endif; ?>
            </div>
          </div>
        </div>
        <div class="cust-stats">
          <div class="cust-stat">
            <span class="val"><?php echo (int)$c['total_orders']; ?></span>
            Orders
          </div>
          <?php if ($c['pending_orders'] > 0): ?>
          <div class="cust-stat" style="border:1px solid rgba(245,158,11,0.3); background:rgba(245,158,11,0.08);">
            <span class="val" style="color:var(--warning);"><?php echo (int)$c['pending_orders']; ?></span>
            Pending
          </div>
          <?php endif; ?>
          <div class="cust-stat" style="border:1px solid rgba(34,197,94,0.3); background:rgba(34,197,94,0.06);">
            <span class="val" style="color:var(--success); font-size:0.9rem;"><?php echo format_currency($c['total_spent']); ?></span>
            Spent
          </div>
        </div>
        <?php if ($c['last_order_at']): ?>
          <div style="margin-top:10px; font-size:0.72rem; color:var(--text-muted);">
            Last order: <?php echo substr($c['last_order_at'],0,10); ?>
          </div>
        <?php else: ?>
          <div style="margin-top:10px; font-size:0.72rem; color:var(--text-muted);">No orders yet</div>
        <?php endif; ?>
        <div style="margin-top:10px; text-align:right;">
          <span style="font-size:0.75rem; color:var(--accent); font-weight:600;">View Dashboard →</span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>

<script>
function filterTable(input) {
  const q = input.value.toLowerCase();
  const rows = document.querySelectorAll('#ordersTable tbody tr');
  let v = 0;
  rows.forEach(r => { const m = r.textContent.toLowerCase().includes(q); r.classList.toggle('hidden', !m); if(m) v++; });
  document.getElementById('orderCount').textContent = v + ' orders';
}
function filterCards(input) {
  const q = input.value.toLowerCase();
  let v = 0;
  document.querySelectorAll('.customer-card').forEach(c => {
    const m = c.textContent.toLowerCase().includes(q);
    c.style.display = m ? '' : 'none';
    if (m) v++;
  });
  document.getElementById('cardCount').textContent = v + ' customers';
}
</script>
</body>
</html>
