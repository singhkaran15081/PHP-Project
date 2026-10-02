<?php
require_once __DIR__ . '/functions.php';
require_login();
if (is_customer()) { header('Location: customer_dashboard.php'); exit; }

$db = get_db();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '') {
        $error = 'Customer name is required.';
    } else {
        if (!empty($_POST['id'])) {
            $stmt = $db->prepare('UPDATE customers SET name=?, phone=?, address=? WHERE id=?');
            $stmt->execute([$name, $phone, $address, $_POST['id']]);
            flash('Customer updated successfully.');
        } else {
            $stmt = $db->prepare('INSERT INTO customers (name, phone, address, created_at) VALUES (?,?,?,?)');
            $stmt->execute([$name, $phone, $address, date('c')]);
            flash('Customer added successfully.');
        }
        header('Location: customers.php'); exit;
    }
}

if (isset($_GET['delete'])) {
    $stmt = $db->prepare('DELETE FROM customers WHERE id=?');
    $stmt->execute([(int)$_GET['delete']]);
    flash('Customer deleted.');
    header('Location: customers.php'); exit;
}

$editing = false; $editItem = null;
if (isset($_GET['edit'])) {
    $editing = true;
    $stmt = $db->prepare('SELECT * FROM customers WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $editItem = $stmt->fetch();
}

$customers = $db->query('SELECT c.*, (SELECT COUNT(*) FROM bookings b WHERE b.customer_id=c.id) AS booking_count FROM customers c ORDER BY name ASC')->fetchAll();

$pageTitle = 'Customers';
require __DIR__ . '/header.php';
?>

<div class="card">
  <h2><?php echo $editing ? '✏️ Edit Customer' : '➕ Add Customer'; ?></h2>
  <?php if ($error): ?><div class="alert error">⚠️ <?php echo sanitize($error); ?></div><?php endif; ?>
  <form method="post" action="">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?php echo (int)$editItem['id']; ?>" /><?php endif; ?>
    <div class="form-row">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" required placeholder="Customer name" value="<?php echo sanitize($editItem['name'] ?? ''); ?>" />
      </div>
      <div class="form-group">
        <label>Phone</label>
        <input type="text" name="phone" placeholder="Phone number" value="<?php echo sanitize($editItem['phone'] ?? ''); ?>" />
      </div>
    </div>
    <div class="form-group">
      <label>Address</label>
      <textarea name="address" placeholder="Full address..."><?php echo sanitize($editItem['address'] ?? ''); ?></textarea>
    </div>
    <div style="display:flex; gap:10px;">
      <button class="button" type="submit"><?php echo $editing ? '💾 Save Changes' : '➕ Add Customer'; ?></button>
      <?php if ($editing): ?><a class="button secondary" href="customers.php">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <h2>👥 Customer List</h2>
  <div class="search-wrap">
    <input class="search-input" type="text" placeholder="🔍 Search customers..." oninput="filterTable(this, 'custTable')" />
    <span class="search-label" id="custCount"><?php echo count($customers); ?> customers</span>
  </div>
  <div class="table-wrap">
  <table id="custTable">
    <thead>
      <tr><th>ID</th><th>Name</th><th>Phone</th><th>Address</th><th>Bookings</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($customers as $c): ?>
        <tr>
          <td><code>#<?php echo (int)$c['id']; ?></code></td>
          <td><strong><?php echo sanitize($c['name']); ?></strong></td>
          <td><?php echo sanitize($c['phone']) ?: '<span style="color:var(--text-muted)">—</span>'; ?></td>
          <td><?php echo sanitize($c['address']) ?: '<span style="color:var(--text-muted)">—</span>'; ?></td>
          <td><span class="badge badge-info"><?php echo (int)$c['booking_count']; ?> orders</span></td>
          <td style="display:flex; gap:6px;">
            <a class="button sm" href="customers.php?edit=<?php echo (int)$c['id']; ?>">✏️ Edit</a>
            <a class="button danger sm" href="customers.php?delete=<?php echo (int)$c['id']; ?>" onclick="return confirm('Delete this customer?')">🗑️</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($customers)): ?>
        <tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:24px;">No customers yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<script>
function filterTable(input, tableId) {
  const q = input.value.toLowerCase();
  const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
  let visible = 0;
  rows.forEach(r => { const m = r.textContent.toLowerCase().includes(q); r.classList.toggle('hidden', !m); if(m) visible++; });
  document.getElementById('custCount').textContent = visible + ' customers';
}
</script>

<?php require __DIR__ . '/footer.php'; ?>
