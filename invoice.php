<?php
require_once __DIR__ . '/functions.php';
require_login();
if (is_customer()) { header('Location: customer_dashboard.php'); exit; }

$db = get_db();

$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($invoiceId) {
    $stmt = $db->prepare(
        'SELECT i.*, b.gas_type, b.quantity, b.date AS booking_date, b.status, c.name AS customer_name, c.phone, c.address
         FROM invoices i
         JOIN bookings b ON i.booking_id = b.id
         JOIN customers c ON b.customer_id = c.id
         WHERE i.id = ?'
    );
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();
}

$invoices = $db->query(
    'SELECT i.*, b.gas_type, b.quantity, b.date AS booking_date, b.status, c.name AS customer_name
     FROM invoices i
     JOIN bookings b ON i.booking_id = b.id
     JOIN customers c ON b.customer_id = c.id
     ORDER BY i.created_at DESC'
)->fetchAll();

$pageTitle = 'Invoices';
require __DIR__ . '/header.php';
?>

<div class="card">
  <h2>Invoice History</h2>
  <table>
    <thead>
      <tr>
        <th>Invoice ID</th>
        <th>Order (Booking)</th>
        <th>Customer</th>
        <th>Gas</th>
        <th>Qty</th>
        <th>Total</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($invoices as $inv): ?>
        <tr>
          <td><?php echo (int)$inv['id']; ?></td>
          <td><?php echo (int)$inv['booking_id']; ?></td>
          <td><?php echo sanitize($inv['customer_name']); ?></td>
          <td><?php echo sanitize($inv['gas_type']); ?></td>
          <td><?php echo (int)$inv['quantity']; ?></td>
          <td><?php echo format_currency($inv['total_price']); ?></td>
          <td><?php echo sanitize($inv['date']); ?></td>
          <td><a class="button" href="invoice.php?id=<?php echo (int)$inv['id']; ?>">View</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($invoices)): ?>
        <tr><td colspan="8">No invoices found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if (!empty($invoice)): ?>
  <div class="card">
    <h2>Invoice #<?php echo (int)$invoice['id']; ?></h2>
    <p><strong>Customer:</strong> <?php echo sanitize($invoice['customer_name']); ?></p>
    <p><strong>Phone:</strong> <?php echo sanitize($invoice['phone']); ?></p>
    <p><strong>Address:</strong> <?php echo sanitize($invoice['address']); ?></p>
    <p><strong>Booking Date:</strong> <?php echo sanitize($invoice['booking_date']); ?></p>
    <p><strong>Status:</strong> <?php echo sanitize($invoice['status']); ?></p>

    <table>
      <thead>
        <tr>
          <th>Gas Type</th>
          <th>Quantity</th>
          <th>Unit Price</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><?php echo sanitize($invoice['gas_type']); ?></td>
          <td><?php echo (int)$invoice['quantity']; ?></td>
          <td><?php
              $unitPrice = $invoice['quantity'] > 0 ? $invoice['total_price'] / $invoice['quantity'] : 0;
              echo format_currency($unitPrice);
            ?></td>
          <td><?php echo format_currency($invoice['total_price']); ?></td>
        </tr>
      </tbody>
    </table>

    <p><strong>Invoice Date:</strong> <?php echo sanitize($invoice['date']); ?></p>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/footer.php';
