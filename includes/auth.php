<?php
/**
 * Authentication Functions
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Validate login credentials
 */
function login($username, $password) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nama'] = $user['nama'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        return true;
    }
    return false;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Get current user data
 */
function currentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'nama' => $_SESSION['nama'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role']
    ];
}

/**
 * Check if user has specific role
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    if (is_string($roles)) {
        $roles = [$roles];
    }
    return in_array($_SESSION['role'], $roles);
}

/**
 * Require login - redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

/**
 * Require specific role(s)
 */
function requireRole($roles) {
    requireLogin();
    if (!hasRole($roles)) {
        header('Location: ' . BASE_URL . 'dashboard.php?error=unauthorized');
        exit;
    }
}

/**
 * Logout user
 */
function logout() {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

/**
 * Get role display name
 */
function getRoleDisplayName($role) {
    $roles = [
        'admin' => 'Administrator',
        'petugas' => 'Petugas Inventaris',
        'pegawai' => 'Pegawai',
        'pimpinan' => 'Pimpinan'
    ];
    return $roles[$role] ?? $role;
}
