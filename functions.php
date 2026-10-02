<?php

require_once __DIR__ . '/config.php';

function flash($message = null) {
    if ($message === null) {
        if (isset($_SESSION['_flash'])) {
            $msg = $_SESSION['_flash'];
            unset($_SESSION['_flash']);
            return $msg;
        }
        return null;
    }
    $_SESSION['_flash'] = $message;
}

function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function is_customer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'customer';
}

function is_staff() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'staff';
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_role($role) {
    require_login();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        flash('Access denied.');
        header('Location: dashboard.php');
        exit;
    }
}

function require_admin_or_staff() {
    require_login();
    if (!is_admin() && !is_staff()) {
        flash('Access denied. Staff or Admin privileges required.');
        if (is_customer()) {
            header('Location: customer_dashboard.php');
        } else {
            header('Location: login.php');
        }
        exit;
    }
}

function current_user() {
    if (!is_logged_in()) return null;
    return [
        'id'       => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? null,
        'role'     => $_SESSION['role'] ?? null,
    ];
}

function sanitize($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function low_stock_warning() {
    $db = get_db();
    $stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM cylinders WHERE quantity < 10');
    $stmt->execute();
    $row = $stmt->fetch();
    return $row['cnt'] > 0;
}

function format_currency($amount) {
    return number_format($amount, 2);
}

// Get customer record linked to current user
function current_customer() {
    if (!is_customer()) return null;
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM customers WHERE user_id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
