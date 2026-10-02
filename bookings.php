<?php
require_once __DIR__ . '/functions.php';
require_login();
if (is_customer()) { header('Location: customer_dashboard.php'); exit; }

$db = get_db();
$error = null;

if (isset($_GET['mark_delivered'])) {
    $id = (int)$_GET['mark_delivered'];
    $stmt = $db->prepare('UPDATE bookings SET status=? WHERE id=?');
    $stmt->execute(['Delivered', $id]);
    flash('Booking marked as delivered successfully.');
    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'bookings.php'; header('Location: ' . $redirect); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $gas_type    = trim($_POST['gas_type'] ?? '');
    $quantity    = (int)($_POST['quantity'] ?? 0);
    $date        = trim($_POST['date'] ?? date('Y-m-d'));

    if (!$customer_id || $gas_type === '' || $quantity <= 0 || $date === '') {
        $error = 'All fields are required and quantity must be greater than zero.';
    } else {
        $stmt = $db->prepare('SELECT * FROM cylinders WHERE gas_type=? LIMIT 1');
        $stmt->execute([$gas_type]);
        $cylinder = $stmt->fetch();

        if (!$cylinder) {
            $error = 'No cylinder found for the selected gas type.';
        } elseif ($cylinder['quantity'] < $quantity) {
            $error = 'Insufficient stock. Only ' . $cylinder['quantity'] . ' units available.';
        } else {
            $stmt = $db->prepare('INSERT INTO bookings (customer_id, gas_type, quantity, date, status, created_at) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$customer_id, $gas_type, $quantity, $date, 'Pending', date('c')]);
            $bookingId = $db->lastInsertId();

            $stmt = $db->prepare('UPDATE cylinders SET quantity=quantity-? WHERE id=?');
            $stmt->execute([$quantity, $cylinder['id']]);

            $totalPrice = $quantity * $cylinder['price'];
            $stmt = $db->prepare('INSERT INTO invoices (booking_id, total_price, date, created_at) VALUES (?,?,?,?)');
            $stmt->execute([$bookingId, $totalPrice, $date, date('c')]);

            flash('Booking created successfully. Invoice generated.');
            $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'bookings.php'; header('Location: ' . $redirect); exit;
        }
    }
}

$customers = $db->query('SELECT * FROM customers ORDER BY name ASC')->fetchAll();
$cylinders = $db->query('SELECT gas_type, quantity FROM cylinders GROUP BY gas_type ORDER BY gas_type ASC')->fetchAll();

$filterStatus = $_GET['status'] ?? '';
$sql = 'SELECT b.*, c.name AS customer_name, i.total_price FROM bookings b LEFT JOIN customers c ON b.customer_id=c.id LEFT JOIN invoices i ON i.booking_id=b.id';
if ($filterStatus) { $sql .= " WHERE b.status='" . $db->quote($filterStatus) . "'"; }
$sql .= ' ORDER BY b.created_at DESC';
$bookings = $db->query($sql)->fetchAll();

$pageTitle = 'Bookings';
require __DIR__ . '/header.php';
?>

<div class="card">
  <h2>📋 Create Booking</h2>
  <?php if ($error): ?><div class="alert error">⚠️ <?php echo sanitize($error); ?></div><?php endif; ?>
  <form method="post" action="">
    <div class="form-row">
      <div class="form-group">
        <label>Customer</label>
        <select name="customer_id" required>
          <option value="">— Select Customer —</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?php echo (int)$c['id']; ?>" <?php echo isset($_POST['customer_id']) && $_POST['customer_id'] == $c['id'] ? 'selected' : ''; ?>>
              <?php echo sanitize($c['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Gas Type</label>
        <select name="gas_type" required>
          <option value="">— Select Gas Type —</option>
          <?php foreach ($cylinders as $c): ?>
            <option value="<?php echo sanitize($c['gas_type']); ?>" <?php echo isset($_POST['gas_type']) && $_POST['gas_type'] === $c['gas_type'] ? 'selected' : ''; ?>>
              <?php echo sanitize($c['gas_type']); ?> (<?php echo (int)$c['quantity']; ?> in stock)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Quantity</label>
        <input type="number" name="quantity" min="1" required value="<?php echo (int)($_POST['quantity'] ?? 1); ?>" />
      </div>
      <div class="form-group">
        <label>Date</label>
        <input type="date" name="date" required value="<?php echo sanitize($_POST['date'] ?? date('Y-m-d')); ?>" />
      </div>
    </div>
    <button class="button" type="submit">📋 Create Booking</button>
  </form>
</div>

<div class="card">
  <h2>📦 Booking History</h2>
  <div class="search-wrap" style="flex-wrap:wrap; gap:8px;">
    <input class="search-input" type="text" placeholder="🔍 Search bookings..." oninput="filterTable(this)" />
    <a href="bookings.php" class="button sm <?php echo !$filterStatus ? '' : 'secondary'; ?>">All</a>
    <a href="bookings.php?status=Pending" class="button sm <?php echo $filterStatus==='Pending' ? '' : 'secondary'; ?>">⏳ Pending</a>
    <a href="bookings.php?status=Delivered" class="button sm <?php echo $filterStatus==='Delivered' ? '' : 'secondary'; ?>">✓ Delivered</a>
    <span class="search-label" id="bookingCount"><?php echo count($bookings); ?> records</span>
  </div>
  <div class="table-wrap">
  <table id="bookingTable">
    <thead>
      <tr><th>ID</th><th>Customer</th><th>Gas Type</th><th>Qty</th><th>Date</th><th>Status</th><th>Total</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($bookings as $b): ?>
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
          <td><?php echo isset($b['total_price']) ? format_currency($b['total_price']) : '—'; ?></td>
          <td>
            <?php if ($b['status'] !== 'Delivered'): ?>
              <a class="button success sm" href="bookings.php?mark_delivered=<?php echo (int)$b['id']; ?>" onclick="return confirm('Mark as delivered?')">✓ Deliver</a>
            <?php else: ?>
              <span style="color:var(--success); font-size:0.8rem;">✓ Done</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($bookings)): ?>
        <tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:24px;">No bookings found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<script>
function filterTable(input) {
  const q = input.value.toLowerCase();
  const rows = document.querySelectorAll('#bookingTable tbody tr');
  let v = 0;
  rows.forEach(r => { const m = r.textContent.toLowerCase().includes(q); r.classList.toggle('hidden', !m); if(m) v++; });
  document.getElementById('bookingCount').textContent = v + ' records';
}
</script>

<?php require __DIR__ . '/footer.php'; ?>
