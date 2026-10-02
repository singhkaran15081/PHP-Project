<?php
require_once __DIR__ . '/functions.php';
require_login();
if (is_customer()) { header('Location: customer_dashboard.php'); exit; }

$db = get_db();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gas_type   = trim($_POST['gas_type'] ?? '');
    $quantity   = (int)($_POST['quantity'] ?? 0);
    $price      = (float)($_POST['price'] ?? 0);
    $supplier_id = $_POST['supplier_id'] ? (int)$_POST['supplier_id'] : null;

    if ($gas_type === '' || $quantity < 0) {
        $error = 'Gas type and quantity are required.';
    } else {
        if (!empty($_POST['id'])) {
            $stmt = $db->prepare('UPDATE cylinders SET gas_type=?, quantity=?, price=?, supplier_id=? WHERE id=?');
            $stmt->execute([$gas_type, $quantity, $price, $supplier_id, $_POST['id']]);
            flash('Cylinder updated successfully.');
        } else {
            $stmt = $db->prepare('INSERT INTO cylinders (gas_type, quantity, price, supplier_id, created_at) VALUES (?,?,?,?,?)');
            $stmt->execute([$gas_type, $quantity, $price, $supplier_id, date('c')]);
            flash('Cylinder added successfully.');
        }
        header('Location: cylinders.php'); exit;
    }
}

if (isset($_GET['delete'])) {
    $stmt = $db->prepare('DELETE FROM cylinders WHERE id=?');
    $stmt->execute([(int)$_GET['delete']]);
    flash('Cylinder deleted.');
    header('Location: cylinders.php'); exit;
}

$editing = false; $editItem = null;
if (isset($_GET['edit'])) {
    $editing = true;
    $stmt = $db->prepare('SELECT * FROM cylinders WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $editItem = $stmt->fetch();
}

$cylinders = $db->query('SELECT c.*, s.name AS supplier_name FROM cylinders c LEFT JOIN suppliers s ON c.supplier_id=s.id ORDER BY c.id DESC')->fetchAll();
$suppliers = $db->query('SELECT * FROM suppliers ORDER BY name ASC')->fetchAll();

$pageTitle = 'Cylinders';
require __DIR__ . '/header.php';
?>

<div class="card">
  <h2><?php echo $editing ? '✏️ Edit Cylinder' : '➕ Add New Cylinder'; ?></h2>
  <?php if ($error): ?><div class="alert error">⚠️ <?php echo sanitize($error); ?></div><?php endif; ?>
  <form method="post" action="">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?php echo (int)$editItem['id']; ?>" /><?php endif; ?>
    <div class="form-row">
      <div class="form-group">
        <label>Gas Type</label>
        <input type="text" name="gas_type" required placeholder="e.g. LPG, Oxygen..." value="<?php echo sanitize($editItem['gas_type'] ?? ''); ?>" />
      </div>
      <div class="form-group">
        <label>Quantity</label>
        <input type="number" name="quantity" min="0" required value="<?php echo (int)($editItem['quantity'] ?? 0); ?>" />
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Price (per cylinder)</label>
        <input type="number" step="0.01" name="price" required value="<?php echo (float)($editItem['price'] ?? 0); ?>" />
      </div>
      <div class="form-group">
        <label>Supplier</label>
        <select name="supplier_id">
          <option value="">-- None --</option>
          <?php foreach ($suppliers as $s): ?>
            <option value="<?php echo (int)$s['id']; ?>" <?php echo isset($editItem['supplier_id']) && $editItem['supplier_id'] == $s['id'] ? 'selected' : ''; ?>><?php echo sanitize($s['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div style="display:flex; gap:10px;">
      <button class="button" type="submit"><?php echo $editing ? '💾 Save Changes' : '➕ Add Cylinder'; ?></button>
      <?php if ($editing): ?><a class="button secondary" href="cylinders.php">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <h2>🛢️ Cylinder Inventory</h2>
  <div class="search-wrap">
    <input class="search-input" type="text" id="cylinderSearch" placeholder="🔍 Search cylinders..." oninput="filterTable(this, 'cylinderTable')" />
    <span class="search-label" id="cylinderCount"><?php echo count($cylinders); ?> records</span>
  </div>
  <div class="table-wrap">
  <table id="cylinderTable">
    <thead>
      <tr><th>ID</th><th>Gas Type</th><th>Quantity</th><th>Price</th><th>Supplier</th><th>Stock</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($cylinders as $c): ?>
        <tr>
          <td><code>#<?php echo (int)$c['id']; ?></code></td>
          <td><strong><?php echo sanitize($c['gas_type']); ?></strong></td>
          <td><?php echo (int)$c['quantity']; ?></td>
          <td><?php echo format_currency($c['price']); ?></td>
          <td><?php echo sanitize($c['supplier_name'] ?? '—'); ?></td>
          <td>
            <?php
              $qty = (int)$c['quantity'];
              if ($qty == 0) echo '<span class="badge badge-danger">Out of Stock</span>';
              elseif ($qty < 10) echo '<span class="badge badge-warning">Low Stock</span>';
              else echo '<span class="badge badge-success">In Stock</span>';
            ?>
          </td>
          <td style="display:flex; gap:6px; flex-wrap:wrap;">
            <a class="button sm" href="cylinders.php?edit=<?php echo (int)$c['id']; ?>">✏️ Edit</a>
            <a class="button danger sm" href="cylinders.php?delete=<?php echo (int)$c['id']; ?>" onclick="return confirm('Delete this cylinder record?')">🗑️</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($cylinders)): ?>
        <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:24px;">No cylinders found. Add one above.</td></tr>
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
  rows.forEach(r => {
    const match = r.textContent.toLowerCase().includes(q);
    r.classList.toggle('hidden', !match);
    if (match) visible++;
  });
  const countEl = document.getElementById(tableId.replace('Table','Count'));
  if (countEl) countEl.textContent = visible + ' records';
}
</script>

<?php require __DIR__ . '/footer.php'; ?>
