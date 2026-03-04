<?php
/**
 * Konfigurasi Utama Sistem Inventaris Dinas Sosial Lampung
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'inventaris_dinsos_lampung');
define('DB_USER', 'root');
define('DB_PASS', '');

// Base URL
define('BASE_URL', 'http://localhost/inventaris_dinsos_lampung/');

// App Info
define('APP_NAME', 'Sistem Inventaris Dinas Sosial Lampung');
define('APP_VERSION', '1.0.0');
