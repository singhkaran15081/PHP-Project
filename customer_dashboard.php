<?php
require_once __DIR__ . '/functions.php';
require_login();

// Redirect staff/admin to their dashboard
if (!is_customer()) {
    header('Location: dashboard.php');
    exit;
}

$db = get_db();
$customer = current_customer();

if (!$customer) {
    flash('Customer profile not found. Please contact support.');
    header('Location: logout.php'); exit;
}

$error = null;
$success = null;

// Cancel order
if (isset($_GET['cancel']) && (int)$_GET['cancel'] > 0) {
    $id = (int)$_GET['cancel'];
    $stmt = $db->prepare("SELECT * FROM bookings WHERE id=? AND customer_id=? AND status='Pending'");
    $stmt->execute([$id, $customer['id']]);
    $booking = $stmt->fetch();
    if ($booking) {
        // Restore stock
        $stmt2 = $db->prepare('UPDATE cylinders SET quantity=quantity+? WHERE gas_type=?');
        $stmt2->execute([$booking['quantity'], $booking['gas_type']]);
        $stmt2 = $db->prepare("UPDATE bookings SET status='Cancelled' WHERE id=?");
        $stmt2->execute([$id]);
        flash('Order #' . $id . ' has been cancelled.');
    }
    header('Location: customer_dashboard.php'); exit;
}

// Place new order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gas_type = trim($_POST['gas_type'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $date     = trim($_POST['date'] ?? date('Y-m-d'));
    $notes    = trim($_POST['notes'] ?? '');

    if ($gas_type === '' || $quantity <= 0 || $date === '') {
        $error = 'Please select a gas type and enter a valid quantity and date.';
    } else {
        $stmt = $db->prepare('SELECT * FROM cylinders WHERE gas_type=? LIMIT 1');
        $stmt->execute([$gas_type]);
        $cylinder = $stmt->fetch();

        if (!$cylinder) {
            $error = 'Selected gas type is not available.';
        } elseif ($cylinder['quantity'] < $quantity) {
            $error = 'Insufficient stock. Only ' . $cylinder['quantity'] . ' units available.';
        } else {
            $stmt = $db->prepare('INSERT INTO bookings (customer_id, gas_type, quantity, date, status, notes, created_at) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$customer['id'], $gas_type, $quantity, $date, 'Pending', $notes, date('c')]);
            $bookingId = $db->lastInsertId();

            $stmt = $db->prepare('UPDATE cylinders SET quantity=quantity-? WHERE id=?');
            $stmt->execute([$quantity, $cylinder['id']]);

            $totalPrice = $quantity * $cylinder['price'];
            $stmt = $db->prepare('INSERT INTO invoices (booking_id, total_price, date, created_at) VALUES (?,?,?,?)');
            $stmt->execute([$bookingId, $totalPrice, $date, date('c')]);

            flash('Order #' . $bookingId . ' placed successfully! We\'ll process it shortly.');
            header('Location: customer_dashboard.php'); exit;
        }
    }
}

// Load available cylinders
$cylinders = $db->query('SELECT gas_type, quantity, price FROM cylinders WHERE quantity > 0 ORDER BY gas_type ASC')->fetchAll();

// Load this customer's orders
$orders = $db->query(
    "SELECT b.*, i.total_price FROM bookings b
     LEFT JOIN invoices i ON i.booking_id=b.id
     WHERE b.customer_id=" . (int)$customer['id'] . "
     ORDER BY b.created_at DESC"
)->fetchAll();

// Stats
$totalOrders = count($orders);
$pendingOrders = count(array_filter($orders, fn($o) => $o['status'] === 'Pending'));
$deliveredOrders = count(array_filter($orders, fn($o) => $o['status'] === 'Delivered'));
$totalSpent = array_sum(array_column($orders, 'total_price'));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Dashboard · Gas Inventory System</title>
  <link rel="stylesheet" href="styles.css" />
  <style>
    .cust-header {
      background: linear-gradient(135deg, rgba(34,197,94,0.12), rgba(16,163,74,0.06));
      border: 1px solid rgba(34,197,94,0.3);
      border-radius: 16px;
      padding: 24px 28px;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 20px;
    }
    .cust-avatar {
      width: 56px; height: 56px;
      background: linear-gradient(135deg, #22c55e, #16a34a);
      border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.6rem;
      flex-shrink: 0;
    }
    .cust-header h1 { font-size: 1.3rem; font-weight: 700; margin-bottom: 4px; }
    .cust-header p { color: var(--text-muted); font-size: 0.85rem; }
    .order-status-pending { color: #fcd34d; }
    .order-status-delivered { color: #86efac; }
    .order-status-cancelled { color: #fca5a5; }
    .price-tag {
      font-family: 'JetBrains Mono', monospace;
      color: var(--success);
      font-weight: 600;
    }
    .gas-cards {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 12px;
      margin-bottom: 20px;
    }
    .gas-card {
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 14px;
      cursor: pointer;
      transition: all 0.2s;
      text-align: center;
    }
    .gas-card:hover, .gas-card.selected {
      border-color: var(--success);
      background: rgba(34,197,94,0.08);
      transform: translateY(-2px);
    }
    .gas-card .gas-name { font-weight: 600; font-size: 0.9rem; margin-bottom: 4px; }
    .gas-card .gas-price { color: var(--success); font-size: 0.8rem; font-family: 'JetBrains Mono', monospace; }
    .gas-card .gas-stock { color: var(--text-muted); font-size: 0.72rem; margin-top: 2px; }
  </style>
</head>
<body>
<!-- Customer Navigation -->
<header>
  <div class="header-inner">
    <a href="customer_dashboard.php" class="logo">
      <div class="logo-icon">🔥</div>
      Gas Inventory System
    </a>
    <nav>
      <a href="customer_dashboard.php" class="active">🏠 My Dashboard</a>
      <a href="customer_profile.php">👤 My Profile</a>
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
    <div class="alert <?php echo strpos($msg,'successfully') !== false || strpos($msg,'placed') !== false ? 'success' : (strpos($msg,'cancelled') !== false ? 'warning' : 'error'); ?>">
      <?php echo sanitize($msg); ?>
    </div>
  <?php endif; ?>

  <!-- Customer Header -->
  <div class="cust-header">
    <div class="cust-avatar">👤</div>
    <div>
      <h1>Welcome back, <?php echo sanitize($customer['name']); ?>!</h1>
      <p>
        <?php if ($customer['phone']): ?>📞 <?php echo sanitize($customer['phone']); ?> &nbsp;·&nbsp;<?php endif; ?>
        <?php if ($customer['email']): ?>✉️ <?php echo sanitize($customer['email']); ?> &nbsp;·&nbsp;<?php endif; ?>
        📍 <?php echo $customer['address'] ? sanitize($customer['address']) : 'No address set'; ?>
      </p>
    </div>
    <a href="customer_profile.php" class="button secondary sm" style="margin-left:auto;">✏️ Edit Profile</a>
  </div>

  <!-- Stats -->
  <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin-bottom:24px;">
    <div class="stat-card">
      <div class="stat-icon">📦</div>
      <div class="stat-value"><?php echo $totalOrders; ?></div>
      <div class="stat-label">Total Orders</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">⏳</div>
      <div class="stat-value" style="color:var(--warning);"><?php echo $pendingOrders; ?></div>
      <div class="stat-label">Pending</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">✅</div>
      <div class="stat-value" style="color:var(--success);"><?php echo $deliveredOrders; ?></div>
      <div class="stat-label">Delivered</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">💰</div>
      <div class="stat-value" style="color:var(--success); font-size:1.3rem;"><?php echo format_currency($totalSpent); ?></div>
      <div class="stat-label">Total Spent</div>
    </div>
  </div>

  <!-- Place Order -->
  <div class="card">
    <h2>🛒 Place New Order</h2>
    <?php if ($error): ?><div class="alert error">⚠️ <?php echo sanitize($error); ?></div><?php endif; ?>

    <?php if (empty($cylinders)): ?>
      <div style="text-align:center; padding:30px; color:var(--text-muted);">
        <div style="font-size:2rem; margin-bottom:10px;">😔</div>
        No gas cylinders are currently in stock. Please check back later.
      </div>
    <?php else: ?>

    <!-- Available Gas Types -->
    <p style="font-size:0.82rem; color:var(--text-muted); margin-bottom:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Available Gas Types</p>
    <div class="gas-cards" id="gasCards">
      <?php foreach ($cylinders as $c): ?>
        <div class="gas-card" onclick="selectGas('<?php echo sanitize($c['gas_type']); ?>', <?php echo $c['price']; ?>)" id="gas-<?php echo sanitize(str_replace(' ', '-', $c['gas_type'])); ?>">
          <div class="gas-name">⛽ <?php echo sanitize($c['gas_type']); ?></div>
          <div class="gas-price"><?php echo format_currency($c['price']); ?> / unit</div>
          <div class="gas-stock"><?php echo (int)$c['quantity']; ?> in stock</div>
        </div>
      <?php endforeach; ?>
    </div>

    <form method="post" action="" id="orderForm">
      <input type="hidden" name="gas_type" id="selectedGasType" value="<?php echo sanitize($_POST['gas_type'] ?? ''); ?>" />
      <div class="form-row">
        <div class="form-group">
          <label>Selected Gas Type</label>
          <input type="text" id="gasDisplay" readonly placeholder="Click a gas type above..."
                 style="cursor:pointer; background:var(--surface);"
                 value="<?php echo sanitize($_POST['gas_type'] ?? ''); ?>" />
        </div>
        <div class="form-group">
          <label>Unit Price</label>
          <input type="text" id="unitPrice" readonly placeholder="—"
                 style="background:var(--surface); color:var(--success); font-family:'JetBrains Mono',monospace;" />
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Quantity *</label>
          <input type="number" name="quantity" id="qtyInput" min="1" required
                 value="<?php echo (int)($_POST['quantity'] ?? 1); ?>"
                 oninput="updateTotal()" />
        </div>
        <div class="form-group">
          <label>Delivery Date *</label>
          <input type="date" name="date" required value="<?php echo sanitize($_POST['date'] ?? date('Y-m-d')); ?>" />
        </div>
      </div>
      <div class="form-group">
        <label>Order Notes (Optional)</label>
        <textarea name="notes" placeholder="Any special instructions or delivery notes..."><?php echo sanitize($_POST['notes'] ?? ''); ?></textarea>
      </div>
      <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
        <button class="button" type="submit" style="background:linear-gradient(135deg,#22c55e,#16a34a);">
          🛒 Place Order
        </button>
        <div style="font-size:0.9rem; color:var(--text-muted);">
          Estimated Total: <span id="totalDisplay" class="price-tag">—</span>
        </div>
      </div>
    </form>
    <?php endif; ?>
  </div>

  <!-- My Orders -->
  <div class="card">
    <h2>📦 My Order History</h2>
    <div class="search-wrap">
      <input class="search-input" type="text" placeholder="🔍 Search my orders..." oninput="filterOrders(this)" />
      <span class="search-label"><?php echo count($orders); ?> orders</span>
    </div>
    <div class="table-wrap">
      <table id="ordersTable">
        <thead>
          <tr><th>Order #</th><th>Gas Type</th><th>Qty</th><th>Delivery Date</th><th>Status</th><th>Total</th><th>Notes</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:30px;">
              No orders yet. Place your first order above! 🛒
            </td></tr>
          <?php else: ?>
            <?php foreach ($orders as $o): ?>
              <tr>
                <td><code>#<?php echo (int)$o['id']; ?></code></td>
                <td><strong><?php echo sanitize($o['gas_type']); ?></strong></td>
                <td><?php echo (int)$o['quantity']; ?></td>
                <td><?php echo sanitize($o['date']); ?></td>
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
                <td style="color:var(--text-muted); font-size:0.82rem; max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                  <?php echo $o['notes'] ? sanitize($o['notes']) : '<em>—</em>'; ?>
                </td>
                <td>
                  <?php if ($o['status'] === 'Pending'): ?>
                    <a class="button danger sm" href="customer_dashboard.php?cancel=<?php echo (int)$o['id']; ?>"
                       onclick="return confirm('Cancel this order?')">✖ Cancel</a>
                  <?php else: ?>
                    <span style="color:var(--text-muted); font-size:0.8rem;">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<?php require __DIR__ . '/footer.php'; ?>

<script>
const gasPrices = {};
<?php foreach ($cylinders as $c): ?>
gasPrices["<?php echo sanitize($c['gas_type']); ?>"] = <?php echo (float)$c['price']; ?>;
<?php endforeach; ?>

function selectGas(gasType, price) {
  document.getElementById('selectedGasType').value = gasType;
  document.getElementById('gasDisplay').value = gasType;
  document.getElementById('unitPrice').value = price.toFixed(2);
  document.querySelectorAll('.gas-card').forEach(c => c.classList.remove('selected'));
  document.getElementById('gas-' + gasType.replace(/ /g,'-')).classList.add('selected');
  updateTotal();
}

function updateTotal() {
  const gasType = document.getElementById('selectedGasType').value;
  const qty = parseInt(document.getElementById('qtyInput').value) || 0;
  const price = gasPrices[gasType] || 0;
  const total = qty * price;
  document.getElementById('totalDisplay').textContent = total > 0 ? total.toFixed(2) : '—';
}

// Re-select if form was submitted with errors
const preselected = document.getElementById('selectedGasType').value;
if (preselected && gasPrices[preselected]) {
  selectGas(preselected, gasPrices[preselected]);
}

function filterOrders(input) {
  const q = input.value.toLowerCase();
  document.querySelectorAll('#ordersTable tbody tr').forEach(r => {
    r.classList.toggle('hidden', !r.textContent.toLowerCase().includes(q));
  });
}
</script>
</body>
</html>
