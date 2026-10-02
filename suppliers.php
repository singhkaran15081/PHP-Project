<?php
require_once __DIR__ . '/functions.php';
require_login();
if (is_customer()) { header('Location: customer_dashboard.php'); exit; }

$db = get_db();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $email   = trim($_POST['email'] ?? '');

    if ($name === '') {
        $error = 'Supplier name is required.';
    } else {
        if (!empty($_POST['id'])) {
            $stmt = $db->prepare('UPDATE suppliers SET name=?, contact=?, address=?, email=? WHERE id=?');
            $stmt->execute([$name, $contact, $address, $email, $_POST['id']]);
            flash('Supplier updated successfully.');
        } else {
            $stmt = $db->prepare('INSERT INTO suppliers (name, contact, address, email, created_at) VALUES (?,?,?,?,?)');
            $stmt->execute([$name, $contact, $address, $email, date('c')]);
            flash('Supplier added successfully.');
        }
        header('Location: suppliers.php'); exit;
    }
}

if (isset($_GET['delete'])) {
    $stmt = $db->prepare('DELETE FROM suppliers WHERE id=?');
    $stmt->execute([(int)$_GET['delete']]);
    flash('Supplier deleted.');
    header('Location: suppliers.php'); exit;
}

$editing = false; $editItem = null;
if (isset($_GET['edit'])) {
    $editing = true;
    $stmt = $db->prepare('SELECT * FROM suppliers WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $editItem = $stmt->fetch();
}

$suppliers = $db->query('SELECT s.*, (SELECT COUNT(*) FROM cylinders c WHERE c.supplier_id=s.id) AS cylinder_count FROM suppliers s ORDER BY name ASC')->fetchAll();

$pageTitle = 'Suppliers';
require __DIR__ . '/header.php';
?>

<div class="card">
  <h2><?php echo $editing ? '✏️ Edit Supplier' : '➕ Add Supplier'; ?></h2>
  <?php if ($error): ?><div class="alert error">⚠️ <?php echo sanitize($error); ?></div><?php endif; ?>
  <form method="post" action="">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?php echo (int)$editItem['id']; ?>" /><?php endif; ?>
    <div class="form-row">
      <div class="form-group">
        <label>Supplier Name</label>
        <input type="text" name="name" required placeholder="Company name" value="<?php echo sanitize($editItem['name'] ?? ''); ?>" />
      </div>
      <div class="form-group">
        <label>Contact Number</label>
        <input type="text" name="contact" placeholder="Phone number" value="<?php echo sanitize($editItem['contact'] ?? ''); ?>" />
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" placeholder="supplier@email.com" value="<?php echo sanitize($editItem['email'] ?? ''); ?>" />
      </div>
      <div class="form-group">
        <label>Address</label>
        <input type="text" name="address" placeholder="Full address" value="<?php echo sanitize($editItem['address'] ?? ''); ?>" />
      </div>
    </div>
    <div style="display:flex; gap:10px;">
      <button class="button" type="submit"><?php echo $editing ? '💾 Save Changes' : '➕ Add Supplier'; ?></button>
      <?php if ($editing): ?><a class="button secondary" href="suppliers.php">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <h2>🏭 Supplier List</h2>
  <div class="search-wrap">
    <input class="search-input" type="text" placeholder="🔍 Search suppliers..." oninput="filterTable(this)" />
    <span class="search-label" id="supCount"><?php echo count($suppliers); ?> suppliers</span>
  </div>
  <div class="table-wrap">
  <table id="supTable">
    <thead>
      <tr><th>ID</th><th>Name</th><th>Contact</th><th>Email</th><th>Address</th><th>Cylinders</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($suppliers as $s): ?>
        <tr>
          <td><code>#<?php echo (int)$s['id']; ?></code></td>
          <td><strong><?php echo sanitize($s['name']); ?></strong></td>
          <td><?php echo sanitize($s['contact']) ?: '<span style="color:var(--text-muted)">—</span>'; ?></td>
          <td><?php echo sanitize($s['email']) ? '<a href="mailto:'.sanitize($s['email']).'">'.sanitize($s['email']).'</a>' : '<span style="color:var(--text-muted)">—</span>'; ?></td>
          <td><?php echo sanitize($s['address']) ?: '<span style="color:var(--text-muted)">—</span>'; ?></td>
          <td><span class="badge badge-info"><?php echo (int)$s['cylinder_count']; ?> types</span></td>
          <td style="display:flex; gap:6px;">
            <a class="button sm" href="suppliers.php?edit=<?php echo (int)$s['id']; ?>">✏️ Edit</a>
            <a class="button danger sm" href="suppliers.php?delete=<?php echo (int)$s['id']; ?>" onclick="return confirm('Delete this supplier?')">🗑️</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($suppliers)): ?>
        <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:24px;">No suppliers yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<script>
function filterTable(input) {
  const q = input.value.toLowerCase();
  const rows = document.querySelectorAll('#supTable tbody tr');
  let v = 0;
  rows.forEach(r => { const m = r.textContent.toLowerCase().includes(q); r.classList.toggle('hidden', !m); if(m) v++; });
  document.getElementById('supCount').textContent = v + ' suppliers';
}
</script>

<?php require __DIR__ . '/footer.php'; ?>
