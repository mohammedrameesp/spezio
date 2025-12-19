<?php
/**
 * Spezio Apartments Admin - Authentication
 */

session_start();

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/functions.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Require authentication
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }

    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        logout();
        header('Location: index.php?timeout=1');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

// Login user
function login($username, $password) {
    $user = dbFetchOne(
        "SELECT * FROM admin_users WHERE username = ? AND status = 'active'",
        [$username]
    );

    if (!$user) {
        return ['success' => false, 'error' => 'Invalid username or password'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Invalid username or password'];
    }

    // Set session
    $_SESSION['admin_id'] = $user['id'];
    $_SESSION['admin_username'] = $user['username'];
    $_SESSION['admin_name'] = $user['name'];
    $_SESSION['admin_role'] = $user['role'];
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['last_activity'] = time();

    // Update last login
    dbUpdate('admin_users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', ['id' => $user['id']]);

    // Log activity
    logActivity($user['id'], 'login', 'admin_users', $user['id']);

    return ['success' => true];
}

// Logout user
function logout() {
    if (isset($_SESSION['admin_id'])) {
        logActivity($_SESSION['admin_id'], 'logout', 'admin_users', $_SESSION['admin_id']);
    }

    session_unset();
    session_destroy();
}

// Get current admin user
function getCurrentAdmin() {
    if (!isLoggedIn()) return null;

    return [
        'id' => $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'],
        'name' => $_SESSION['admin_name'],
        'role' => $_SESSION['admin_role']
    ];
}

// Check if user has role
function hasRole($roles) {
    if (!isLoggedIn()) return false;

    if (!is_array($roles)) {
        $roles = [$roles];
    }

    return in_array($_SESSION['admin_role'], $roles);
}

// Change password
function changePassword($userId, $currentPassword, $newPassword) {
    $user = dbFetchOne("SELECT * FROM admin_users WHERE id = ?", [$userId]);

    if (!$user) {
        return ['success' => false, 'error' => 'User not found'];
    }

    if (!password_verify($currentPassword, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Current password is incorrect'];
    }

    if (strlen($newPassword) < 8) {
        return ['success' => false, 'error' => 'New password must be at least 8 characters'];
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    dbUpdate('admin_users', ['password_hash' => $newHash], 'id = ?', ['id' => $userId]);

    logActivity($userId, 'password_changed', 'admin_users', $userId);

    return ['success' => true, 'message' => 'Password changed successfully'];
}
