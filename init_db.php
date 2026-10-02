<?php

require_once __DIR__ . '/config.php';

$db = get_db();

// Create tables if they don't exist
$db->exec(
    "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ('admin','staff','customer')),
        created_at TEXT NOT NULL
    );"
);

$db->exec(
    "CREATE TABLE IF NOT EXISTS suppliers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        contact TEXT,
        address TEXT,
        email TEXT,
        created_at TEXT NOT NULL
    );"
);

$db->exec(
    "CREATE TABLE IF NOT EXISTS cylinders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        gas_type TEXT NOT NULL,
        quantity INTEGER NOT NULL DEFAULT 0,
        price REAL NOT NULL DEFAULT 0,
        supplier_id INTEGER,
        created_at TEXT NOT NULL,
        FOREIGN KEY(supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
    );"
);

$db->exec(
    "CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        phone TEXT,
        address TEXT,
        email TEXT,
        user_id INTEGER,
        created_at TEXT NOT NULL,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
    );"
);

$db->exec(
    "CREATE TABLE IF NOT EXISTS bookings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_id INTEGER NOT NULL,
        gas_type TEXT NOT NULL,
        quantity INTEGER NOT NULL,
        date TEXT NOT NULL,
        status TEXT NOT NULL CHECK(status IN ('Pending','Delivered','Cancelled')),
        notes TEXT,
        created_at TEXT NOT NULL,
        FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE
    );"
);

$db->exec(
    "CREATE TABLE IF NOT EXISTS invoices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        booking_id INTEGER NOT NULL,
        total_price REAL NOT NULL,
        date TEXT NOT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE
    );"
);

// Add email column to customers if not exists (migration)
try { $db->exec("ALTER TABLE customers ADD COLUMN email TEXT"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE customers ADD COLUMN user_id INTEGER"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE users ADD COLUMN role TEXT"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE bookings ADD COLUMN notes TEXT"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE bookings ADD COLUMN status TEXT"); } catch (Exception $e) {}

// Seed admin user if none exists
$stmt = $db->query("SELECT COUNT(*) AS cnt FROM users WHERE role='admin'");
$count = $stmt->fetch()['cnt'];
if ($count == 0) {
    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO users (username, password, role, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute(['admin', $passwordHash, 'admin', date('c')]);
    echo "Created default admin user: admin / admin123\n";
}

echo "Database initialized at: " . DB_PATH . "\n";

// Seed demo customer user if none exist
$stmt = $db->query("SELECT COUNT(*) AS cnt FROM users WHERE role='customer'");
$custCount = $stmt->fetch()['cnt'];
if ($custCount == 0) {
    $custHash = password_hash('customer123', PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO users (username, password, role, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute(['customer1', $custHash, 'customer', date('c')]);
    $custUserId = $db->lastInsertId();

    // Link to first existing customer or create one
    $existing = $db->query('SELECT id FROM customers WHERE user_id IS NULL LIMIT 1')->fetch();
    if ($existing) {
        $db->prepare('UPDATE customers SET user_id=? WHERE id=?')->execute([$custUserId, $existing['id']]);
        echo "Linked demo customer user to existing customer record.\n";
    } else {
        $db->prepare('INSERT INTO customers (name, phone, address, email, user_id, created_at) VALUES (?,?,?,?,?,?)')->execute([
            'Demo Customer', '0000000000', '123 Demo Street', 'demo@example.com', $custUserId, date('c')
        ]);
        echo "Created demo customer: customer1 / customer123\n";
    }
}
