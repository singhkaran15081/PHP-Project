# Gas Inventory Management System (GIMS) — Enhanced

A PHP + SQLite gas cylinder inventory management system with **separate customer portals** and a full **admin oversight dashboard**.

---

## 🚀 Quick Start

1. Place the folder on a PHP 7.4+ web server (Apache/Nginx with PHP-SQLite support)
2. Visit `index.php` — the DB initializes automatically on first run
3. Run `init_db.php` once via browser or CLI to seed default accounts

### Default Accounts

| Role     | Username    | Password     | Login URL              |
|----------|-------------|--------------|------------------------|
| Admin    | `admin`     | `admin123`   | `admin.php`            |
| Staff    | `admin`     | `admin123`   | `login.php`            |
| Customer | `customer1` | `customer123`| `customer_login.php`   |

> ⚠️ Change all default passwords immediately after first login.

---

## 👥 User Roles & Portals

### 🛒 Customer Portal (`customer_login.php`)
Each customer has their own private dashboard where they can:
- **Place orders** — browse available gas types, select quantity & delivery date
- **Track orders** — view real-time status (Pending / Delivered / Cancelled)
- **Cancel pending orders** — self-service cancellation restores stock
- **View order history** — complete order log with totals
- **Manage profile** — update name, phone, address, email, and password
- **Register** — new customers sign up at `customer_register.php`

### 🏢 Staff Portal (`login.php`)
Standard staff members manage:
- Cylinders, Suppliers, Customers, Bookings, Invoices, Reports

### 👑 Admin Portal (`admin.php`)
Admins have everything staff has, plus:
- **Customer Portals Dashboard** (`admin_customers.php`) — view ALL customer dashboards
  - Grid view of every registered customer with order stats
  - Click any customer → see their full dashboard, profile, and order history
  - Mark pending orders as delivered from within the customer view
  - Search/filter across all customers

---

## 📁 New Files Added

| File                    | Purpose                                          |
|-------------------------|--------------------------------------------------|
| `customer_login.php`    | Customer sign-in page                            |
| `customer_register.php` | Customer self-registration                       |
| `customer_dashboard.php`| Customer's personal order management dashboard   |
| `customer_profile.php`  | Customer profile editing                         |
| `admin_customers.php`   | Admin view of all customer portals & dashboards  |

---

## 🗄️ Database Schema Changes

- `users.role` — now supports `'admin'`, `'staff'`, `'customer'`
- `customers.email` — new column for customer contact
- `customers.user_id` — foreign key linking customer record to login account
- `bookings.notes` — customer order notes/instructions
- `bookings.status` — now supports `'Pending'`, `'Delivered'`, `'Cancelled'`

---

## 🔒 Security Notes

- Customer accounts can **only** access their own data
- Customers cannot access staff/admin pages (auto-redirect enforced)
- Staff/admin cannot access customer portal pages (auto-redirect enforced)
- Admin oversight is read+action via `admin_customers.php`

