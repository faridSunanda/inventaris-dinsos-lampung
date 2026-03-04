<?php
/**
 * Kelola User - Admin Only
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(['admin']);

$user = currentUser();
$pageTitle = 'Kelola User';

// Handle actions
$error = $success = '';

// Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if ($_GET['delete'] == $user['id']) {
        $error = 'Tidak bisa menghapus akun sendiri!';
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$_GET['delete']]);
            $success = 'User berhasil dihapus!';
        } catch (Exception $e) {
            $error = 'Gagal menghapus user!';
        }
    }
}

// Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'import') {
        // Handle CSV Import
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['csv_file']['tmp_name'];
            $defaultPassword = password_hash('user123', PASSWORD_DEFAULT);

            try {
                $pdo = db();
                $handle = fopen($file, 'r');
                $header = fgetcsv($handle); // Skip header row

                $imported = 0;
                $skipped = 0;
                $errors = [];
                $lineNum = 1;

                while (($data = fgetcsv($handle)) !== false) {
                    $lineNum++;

                    if (count($data) < 3) {
                        $errors[] = "Baris $lineNum: Format tidak valid";
                        $skipped++;
                        continue;
                    }

                    $nama = trim($data[0]);
                    $username = trim($data[1]);
                    $role = trim(strtolower($data[2]));

                    // Validate
                    if (empty($nama) || empty($username)) {
                        $errors[] = "Baris $lineNum: Nama atau username kosong";
                        $skipped++;
                        continue;
                    }

                    // Validate role
                    if (!in_array($role, ['admin', 'petugas', 'pegawai', 'pimpinan'])) {
                        $role = 'pegawai'; // Default role
                    }

                    // Check duplicate username
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    if ($stmt->fetchColumn() > 0) {
                        $errors[] = "Baris $lineNum: Username '$username' sudah ada";
                        $skipped++;
                        continue;
                    }

                    // Insert user
                    $stmt = $pdo->prepare("INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$nama, $username, $defaultPassword, $role]);
                    $imported++;
                }

                fclose($handle);

                if ($imported > 0) {
                    $success = "Berhasil import $imported user!" . ($skipped > 0 ? " ($skipped dilewati)" : "");
                } else {
                    $error = "Tidak ada user yang berhasil diimport. $skipped data dilewati.";
                }

                if (!empty($errors) && count($errors) <= 5) {
                    $error .= " Detail: " . implode("; ", $errors);
                }

            } catch (Exception $e) {
                $error = 'Gagal import file CSV!';
            }
        } else {
            $error = 'File CSV tidak valid atau tidak ada!';
        }
    } else {
        // Normal Add/Edit
        $id = $_POST['id'] ?? null;
        $nama = trim($_POST['nama'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'pegawai';

        if (empty($nama) || empty($username)) {
            $error = 'Nama dan username wajib diisi!';
        } else {
            try {
                $pdo = db();

                // Check duplicate username
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$username, $id ?: 0]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Username sudah digunakan!';
                } else {
                    if ($id) {
                        // Update
                        if ($password) {
                            $stmt = $pdo->prepare("UPDATE users SET nama = ?, username = ?, password = ?, role = ? WHERE id = ?");
                            $stmt->execute([$nama, $username, password_hash($password, PASSWORD_DEFAULT), $role, $id]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET nama = ?, username = ?, role = ? WHERE id = ?");
                            $stmt->execute([$nama, $username, $role, $id]);
                        }
                        $success = 'User berhasil diupdate!';
                    } else {
                        // Insert
                        if (empty($password)) {
                            $error = 'Password wajib diisi untuk user baru!';
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$nama, $username, password_hash($password, PASSWORD_DEFAULT), $role]);
                            $success = 'User berhasil ditambahkan!';
                        }
                    }
                }
            } catch (Exception $e) {
                $error = 'Gagal menyimpan user!';
            }
        }
    }
}

// Handle template download
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=template_import_user.csv');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM for Excel
    fputcsv($output, ['nama', 'username', 'role']);
    fputcsv($output, ['Contoh User', 'contoh_user', 'pegawai']);
    fputcsv($output, ['Admin Contoh', 'admin_contoh', 'admin']);
    fputcsv($output, ['Petugas Contoh', 'petugas_contoh', 'petugas']);
    fclose($output);
    exit;
}

// Get data
try {
    $pdo = db();
    $pendingPeminjaman = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'pending'")->fetchColumn();
    $userList = $pdo->query("SELECT * FROM users ORDER BY nama")->fetchAll();
} catch (Exception $e) {
    $userList = [];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: { extend: { colors: { 'catalina': { '50': '#eaf8ff', '100': '#d0f0ff', '200': '#abe7ff', '300': '#71daff', '400': '#2ec2ff', '500': '#009cff', '600': '#0074ff', '700': '#005aff', '800': '#004bde', '900': '#0045ad', '950': '#04337c' } } } }
        }
    </script>
    <style>
        #sidebar {
            transition: transform 0.3s ease-in-out;
        }

        #sidebar.sidebar-hidden {
            transform: translateX(-100%);
        }

        @media (min-width: 1024px) {
            #sidebar {
                transform: translateX(0) !important;
            }
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex min-h-screen">
        <div id="overlay" class="fixed inset-0 bg-black/50 z-30 lg:hidden hidden" onclick="toggleSidebar()"></div>

        <!-- Sidebar -->
        <aside id="sidebar"
            class="w-64 bg-white text-gray-800 fixed h-full z-40 shadow-lg sidebar-hidden lg:translate-x-0 border-r border-gray-200">
            <!-- Logo -->
            <div class="p-4 lg:p-6 border-b border-gray-200 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 border-2 border-catalina-600 rounded-xl flex items-center justify-center">
                        <img src="../assets/image/logo.png" alt="Logo">
                    </div>
                    <div>
                        <h1 class="font-bold text-sm text-catalina-900"> Sistem Inventaris</h1>
                        <p class="text-xs text-gray-500">Dinsos Lampung</p>
                    </div>
                </div>
                <button onclick="toggleSidebar()"
                    class="lg:hidden p-2 hover:bg-gray-100 rounded-lg transition-all text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="p-4 space-y-1 overflow-y-auto" style="max-height: calc(100vh - 80px);">
                <!-- Dashboard -->
                <a href="../dashboard.php"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                    <i class="fas fa-home w-5"></i>
                    <span>Dashboard</span>
                </a>

                <?php if (hasRole(['admin', 'petugas'])): ?>
                    <div class="pt-4">
                        <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Inventaris</p>
                    </div>
                    <a href="../barang/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl  hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-box w-5"></i>
                        <span>Data Barang</span>
                    </a>
                    <a href="../kategori/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-tags w-5"></i>
                        <span>Kategori</span>
                    </a>
                    <a href="../lokasi/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-map-marker-alt w-5"></i>
                        <span>Lokasi</span>
                    </a>
                    <a href="../kondisi/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-clipboard-check w-5"></i>
                        <span>Kondisi</span>
                    </a>
                    <a href="../habis_pakai/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-boxes-packing w-5"></i>
                        <span>Barang Habis Pakai</span>
                    </a>
                <?php endif; ?>

                <div class="pt-4">
                    <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Peminjaman</p>
                </div>
                <?php if (hasRole(['pegawai'])): ?>
                    <a href="../peminjaman/ajukan.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-plus-circle w-5"></i>
                        <span>Ajukan Peminjaman</span>
                    </a>
                    <a href="../peminjaman/riwayat.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-history w-5"></i>
                        <span>Riwayat Saya</span>
                    </a>
                <?php endif; ?>

                <?php if (hasRole(['admin', 'petugas'])): ?>
                    <a href="../peminjaman/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-hand-holding w-5"></i>
                        <span class="flex-1">Kelola Peminjaman</span>
                        <?php if (isset($pendingPeminjaman) && $pendingPeminjaman > 0): ?>
                            <span
                                class="px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded-full animate-pulse"><?= $pendingPeminjaman ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="../pengembalian/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-undo w-5"></i>
                        <span>Pengembalian</span>
                    </a>
                <?php endif; ?>

                <?php if (hasRole(['admin', 'pimpinan'])): ?>
                    <div class="pt-4">
                        <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Laporan</p>
                    </div>
                    <a href="../laporan/barang.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-file-alt w-5"></i>
                        <span>Laporan Barang</span>
                    </a>
                    <a href="../laporan/peminjaman.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-file-invoice w-5"></i>
                        <span>Laporan Peminjaman</span>
                    </a>
                <?php endif; ?>

                <?php if (hasRole(['admin'])): ?>
                    <div class="pt-4">
                        <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Admin</p>
                    </div>
                    <a href="../users/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all bg-catalina-50 text-catalina-700 font-medium hover:bg-gray-100 hover:text-catalina-700 transition-all">
                        <i class="fas fa-users w-5"></i>
                        <span>Kelola User</span>
                    </a>
                <?php endif; ?>

                <div class="pt-4">
                    <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Akun</p>
                </div>
                <a href="../profil/index.php"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                    <i class="fas fa-user-cog w-5"></i>
                    <span>Profil Saya</span>
                </a>
            </nav>
        </aside>

        <main class="flex-1 lg:ml-64 min-w-0">
            <header class="bg-white shadow-sm sticky top-0 z-10 border-b border-gray-200">
                <div class="flex items-center justify-between px-4 lg:px-6 py-4">
                    <div class="flex items-center gap-3">
                        <button onclick="toggleSidebar()"
                            class="lg:hidden p-2 -ml-2 text-gray-600 hover:bg-gray-100 rounded-lg"><i
                                class="fas fa-bars text-xl"></i></button>
                        <div>
                            <h1 class="text-lg lg:text-xl font-bold text-gray-800"><?= $pageTitle ?></h1>
                            <p class="text-xs text-gray-500">Kelola akun pengguna sistem</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 lg:gap-4">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 bg-catalina-600 rounded-full flex items-center justify-center text-white font-semibold">
                                <?= strtoupper(substr($user['nama'], 0, 1)) ?>
                            </div>
                            <div class="hidden md:block">
                                <p class="text-sm font-medium text-gray-700"><?= htmlspecialchars($user['nama']) ?></p>
                                <p class="text-xs text-gray-500"><?= getRoleDisplayName($user['role']) ?></p>
                            </div>
                            <a href="../logout.php"
                                class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all"
                                title="Logout">
                                <i class="fas fa-sign-out-alt"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="p-4 lg:p-6">
                <?php if ($success): ?>
                    <div
                        class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-4 flex items-center gap-2">
                        <i class="fas fa-check-circle"></i><?= $success ?>
                    </div><?php endif; ?>
                <?php if ($error): ?>
                    <div
                        class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 flex items-center gap-2">
                        <i class="fas fa-exclamation-circle"></i><?= $error ?>
                    </div><?php endif; ?>

                <!-- Header Actions -->
                <div class="bg-white rounded-2xl p-4 lg:p-6 border border-gray-200 mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h3 class="font-semibold text-gray-800">Daftar User</h3>
                            <p class="text-sm text-gray-500">Total <?= count($userList) ?> pengguna</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="?download_template=1"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition-all">
                                <i class="fas fa-download"></i>Template CSV
                            </a>
                            <button onclick="openImportModal()"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-xl font-medium transition-all">
                                <i class="fas fa-file-import"></i>Import CSV
                            </button>
                            <button onclick="openModal()"
                                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-catalina-600 hover:bg-catalina-700 text-white rounded-xl font-medium transition-all">
                                <i class="fas fa-plus"></i>Tambah User
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">User
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">
                                        Username</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Role
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($userList)): ?>
                                    <tr>
                                        <td colspan="4" class="px-4 py-12 text-center text-gray-400"><i
                                                class="fas fa-users text-4xl mb-3"></i>
                                            <p>Belum ada user</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($userList as $u): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-3">
                                                    <div
                                                        class="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold
                                                <?= match ($u['role']) { 'admin' => 'bg-red-500', 'petugas' => 'bg-blue-500', 'pimpinan' => 'bg-purple-500', default => 'bg-gray-500'} ?>">
                                                        <?= strtoupper(substr($u['nama'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <p class="font-medium text-gray-800"><?= htmlspecialchars($u['nama']) ?>
                                                        </p>
                                                        <p class="text-xs text-gray-500 md:hidden">
                                                            <?= htmlspecialchars($u['username']) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-gray-600 hidden md:table-cell">
                                                <?= htmlspecialchars($u['username']) ?>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <?php
                                                $roleClass = match ($u['role']) {
                                                    'admin' => 'bg-red-100 text-red-700',
                                                    'petugas' => 'bg-blue-100 text-blue-700',
                                                    'pimpinan' => 'bg-purple-100 text-purple-700',
                                                    default => 'bg-gray-100 text-gray-700'
                                                };
                                                ?>
                                                <span
                                                    class="px-2 py-1 rounded-full text-xs font-medium <?= $roleClass ?>"><?= ucfirst($u['role']) ?></span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <button
                                                    onclick="editUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nama']) ?>', '<?= htmlspecialchars($u['username']) ?>', '<?= $u['role'] ?>')"
                                                    class="p-2 text-catalina-600 hover:bg-catalina-50 rounded-lg"
                                                    title="Edit"><i class="fas fa-edit"></i></button>
                                                <?php if ($u['id'] != $user['id']): ?>
                                                    <button
                                                        onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nama']) ?>')"
                                                        class="p-2 text-red-500 hover:bg-red-50 rounded-lg" title="Hapus"><i
                                                            class="fas fa-trash"></i></button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 text-sm text-gray-500">
                        Total: <strong><?= count($userList) ?></strong> user
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Form Modal -->
    <div id="formModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full transform transition-all">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-bold text-gray-800" id="formTitle">
                    <i class="fas fa-user-plus text-catalina-500 mr-2"></i>Tambah User
                </h3>
                <button onclick="closeModal()"
                    class="p-2 hover:bg-gray-100 rounded-lg text-gray-400 hover:text-gray-600 transition-all">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="userForm">
                <input type="hidden" name="id" id="formId">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="nama" id="formNama" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                            placeholder="Masukkan nama lengkap">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Username <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="username" id="formUsername" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                            placeholder="Masukkan username">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Password <span class="text-red-500"
                                id="pwdRequired">*</span></label>
                        <input type="password" name="password" id="formPassword"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                            placeholder="Masukkan password">
                        <p class="text-xs text-gray-500 mt-1" id="pwdHint">Wajib diisi untuk user baru</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                        <select name="role" id="formRole"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100">
                            <option value="admin">Admin</option>
                            <option value="petugas">Petugas Inventaris</option>
                            <option value="pegawai" selected>Pegawai</option>
                            <option value="pimpinan">Pimpinan</option>
                        </select>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="closeModal()"
                            class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition-all">Batal</button>
                        <button type="submit"
                            class="flex-1 px-4 py-2.5 bg-catalina-600 hover:bg-catalina-700 text-white rounded-xl font-medium transition-all"><i
                                class="fas fa-save mr-2"></i>Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4"><i
                    class="fas fa-trash text-2xl text-red-500"></i></div>
            <h3 class="text-lg font-bold text-gray-800 mb-2">Hapus User?</h3>
            <p class="text-gray-500 mb-6">Anda yakin ingin menghapus <strong id="deleteItemName"></strong>?</p>
            <div class="flex gap-3">
                <button onclick="closeDeleteModal()"
                    class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition-all">Batal</button>
                <a id="deleteLink" href="#"
                    class="flex-1 px-4 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-xl font-medium transition-all">Hapus</a>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="importModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-6 max-w-lg w-full">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-file-import text-green-500 mr-2"></i>Import User dari CSV
                </h3>
                <button onclick="closeImportModal()"
                    class="p-2 hover:bg-gray-100 rounded-lg text-gray-400 hover:text-gray-600 transition-all">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
                <h4 class="font-semibold text-blue-800 text-sm mb-2"><i class="fas fa-info-circle mr-1"></i>Petunjuk
                    Import</h4>
                <ul class="text-xs text-blue-700 space-y-1">
                    <li>• Download template CSV terlebih dahulu</li>
                    <li>• Isi file dengan data user sesuai format</li>
                    <li>• Kolom: <strong>nama, username, role</strong></li>
                    <li>• Role yang valid: <code class="bg-blue-100 px-1 rounded">admin</code>, <code
                            class="bg-blue-100 px-1 rounded">petugas</code>, <code
                            class="bg-blue-100 px-1 rounded">pegawai</code>, <code
                            class="bg-blue-100 px-1 rounded">pimpinan</code></li>
                    <li>• Password default: <code class="bg-blue-100 px-1 rounded">user123</code></li>
                </ul>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">File CSV <span
                            class="text-red-500">*</span></label>
                    <div
                        class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-green-400 transition-all">
                        <input type="file" name="csv_file" id="csvFileInput" accept=".csv" required class="hidden"
                            onchange="updateFileName(this)">
                        <label for="csvFileInput" class="cursor-pointer">
                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                            <p class="text-gray-600 font-medium" id="fileName">Klik atau drag file CSV ke sini</p>
                            <p class="text-xs text-gray-400 mt-1">Format: .csv</p>
                        </label>
                    </div>
                </div>

                <div class="flex gap-3">
                    <a href="?download_template=1"
                        class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition-all text-center">
                        <i class="fas fa-download mr-2"></i>Download Template
                    </a>
                    <button type="submit"
                        class="flex-1 px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-xl font-medium transition-all">
                        <i class="fas fa-upload mr-2"></i>Import
                    </button>
                </div>
            </form>

            <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-xl">
                <p class="text-xs text-amber-700">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Catatan:</strong> User yang diimport akan memiliki password default
                    <strong>"user123"</strong>. Anjurkan user untuk segera mengganti password setelah login pertama
                    kali.
                </p>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('sidebar-hidden');
            document.getElementById('overlay').classList.toggle('hidden');
        }

        // Form Modal Functions
        function openModal() {
            document.getElementById('formTitle').innerHTML = '<i class="fas fa-user-plus text-catalina-500 mr-2"></i>Tambah User';
            document.getElementById('userForm').reset();
            document.getElementById('formId').value = '';
            document.getElementById('pwdRequired').style.display = 'inline';
            document.getElementById('pwdHint').textContent = 'Wajib diisi untuk user baru';
            document.getElementById('formModal').classList.remove('hidden');
            document.getElementById('formModal').classList.add('flex');
            setTimeout(() => document.getElementById('formNama').focus(), 100);
        }

        function closeModal() {
            document.getElementById('formModal').classList.add('hidden');
            document.getElementById('formModal').classList.remove('flex');
        }

        function editUser(id, nama, username, role) {
            document.getElementById('formTitle').innerHTML = '<i class="fas fa-user-edit text-catalina-500 mr-2"></i>Edit User';
            document.getElementById('formId').value = id;
            document.getElementById('formNama').value = nama;
            document.getElementById('formUsername').value = username;
            document.getElementById('formRole').value = role;
            document.getElementById('formPassword').value = '';
            document.getElementById('pwdRequired').style.display = 'none';
            document.getElementById('pwdHint').textContent = 'Kosongkan jika tidak ingin mengubah password';
            document.getElementById('formModal').classList.remove('hidden');
            document.getElementById('formModal').classList.add('flex');
            setTimeout(() => document.getElementById('formNama').focus(), 100);
        }

        // Delete Modal Functions
        function confirmDelete(id, name) {
            document.getElementById('deleteItemName').textContent = name;
            document.getElementById('deleteLink').href = '?delete=' + id;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('deleteModal').classList.remove('flex');
        }

        // Import Modal Functions
        function openImportModal() {
            document.getElementById('csvFileInput').value = '';
            document.getElementById('fileName').textContent = 'Klik atau drag file CSV ke sini';
            document.getElementById('importModal').classList.remove('hidden');
            document.getElementById('importModal').classList.add('flex');
        }

        function closeImportModal() {
            document.getElementById('importModal').classList.add('hidden');
            document.getElementById('importModal').classList.remove('flex');
        }

        function updateFileName(input) {
            if (input.files.length > 0) {
                document.getElementById('fileName').textContent = input.files[0].name;
            }
        }

        // Close modals when clicking outside
        document.getElementById('formModal').addEventListener('click', function (e) {
            if (e.target === this) closeModal();
        });
        document.getElementById('deleteModal').addEventListener('click', function (e) {
            if (e.target === this) closeDeleteModal();
        });
        document.getElementById('importModal').addEventListener('click', function (e) {
            if (e.target === this) closeImportModal();
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal();
                closeDeleteModal();
                closeImportModal();
            }
        });
    </script>
</body>

</html>