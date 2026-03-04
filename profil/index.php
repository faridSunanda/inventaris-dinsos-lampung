<?php
/**
 * Profil - Pengaturan Akun
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = currentUser();
$pageTitle = 'Profil Saya';

$error = $success = '';

// Handle update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $nama = trim($_POST['nama'] ?? '');
        $username = trim($_POST['username'] ?? '');

        if (empty($nama) || empty($username)) {
            $error = 'Nama dan username wajib diisi!';
        } else {
            try {
                $pdo = db();

                // Check duplicate username
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$username, $user['id']]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Username sudah digunakan!';
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET nama = ?, username = ? WHERE id = ?");
                    $stmt->execute([$nama, $username, $user['id']]);

                    // Update session
                    $_SESSION['user']['nama'] = $nama;
                    $_SESSION['user']['username'] = $username;
                    $user = currentUser();

                    $success = 'Profil berhasil diupdate!';
                }
            } catch (Exception $e) {
                $error = 'Gagal mengupdate profil!';
            }
        }
    } elseif ($action === 'update_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Semua field password wajib diisi!';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Password baru tidak sama dengan konfirmasi!';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Password baru minimal 6 karakter!';
        } else {
            try {
                $pdo = db();

                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user['id']]);
                $userData = $stmt->fetch();

                if (!password_verify($currentPassword, $userData['password'])) {
                    $error = 'Password saat ini salah!';
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
                    $success = 'Password berhasil diubah!';
                }
            } catch (Exception $e) {
                $error = 'Gagal mengubah password!';
            }
        }
    }
}

// Get pending count for notification badge
try {
    $pdo = db();
    $pendingPeminjaman = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'pending'")->fetchColumn();
} catch (Exception $e) {
    $pendingPeminjaman = 0;
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
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-users w-5"></i>
                        <span>Kelola User</span>
                    </a>
                <?php endif; ?>

                <div class="pt-4">
                    <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Akun</p>
                </div>
                <a href="../profil/index.php"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all bg-catalina-50 text-catalina-700 font-medium">
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
                            <p class="text-xs text-gray-500">Kelola data akun Anda</p>
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

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Profile Card -->
                    <div class="bg-white rounded-2xl p-6 border border-gray-200">
                        <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-100">
                            <div
                                class="w-20 h-20 bg-gradient-to-br from-catalina-500 to-catalina-700 rounded-2xl flex items-center justify-center text-white text-3xl font-bold">
                                <?= strtoupper(substr($user['nama'], 0, 1)) ?>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($user['nama']) ?></h2>
                                <p class="text-gray-500">@<?= htmlspecialchars($user['username']) ?></p>
                                <span
                                    class="inline-block mt-1 px-3 py-1 bg-catalina-100 text-catalina-700 rounded-full text-xs font-medium">
                                    <?= getRoleDisplayName($user['role']) ?>
                                </span>
                            </div>
                        </div>

                        <h3 class="font-semibold text-gray-800 mb-4"><i
                                class="fas fa-user-edit text-catalina-500 mr-2"></i>Ubah Data Diri</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap <span
                                            class="text-red-500">*</span></label>
                                    <input type="text" name="nama" value="<?= htmlspecialchars($user['nama']) ?>"
                                        required
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                                        placeholder="Masukkan nama lengkap">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Username <span
                                            class="text-red-500">*</span></label>
                                    <input type="text" name="username"
                                        value="<?= htmlspecialchars($user['username']) ?>" required
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                                        placeholder="Masukkan username">
                                </div>
                                <button type="submit"
                                    class="w-full px-4 py-2.5 bg-catalina-600 hover:bg-catalina-700 text-white rounded-xl font-medium transition-all">
                                    <i class="fas fa-save mr-2"></i>Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Password Card -->
                    <div class="bg-white rounded-2xl p-6 border border-gray-200 h-fit">
                        <h3 class="font-semibold text-gray-800 mb-4"><i class="fas fa-key text-amber-500 mr-2"></i>Ubah
                            Password</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_password">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Password Saat Ini <span
                                            class="text-red-500">*</span></label>
                                    <input type="password" name="current_password" required
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                                        placeholder="Masukkan password saat ini">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Password Baru <span
                                            class="text-red-500">*</span></label>
                                    <input type="password" name="new_password" required minlength="6"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                                        placeholder="Minimal 6 karakter">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Konfirmasi Password Baru
                                        <span class="text-red-500">*</span></label>
                                    <input type="password" name="confirm_password" required minlength="6"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                                        placeholder="Ulangi password baru">
                                </div>
                                <button type="submit"
                                    class="w-full px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-medium transition-all">
                                    <i class="fas fa-lock mr-2"></i>Ubah Password
                                </button>
                            </div>
                        </form>

                        <div class="mt-6 p-4 bg-amber-50 rounded-xl border border-amber-100">
                            <h4 class="font-medium text-amber-800 text-sm mb-2"><i class="fas fa-info-circle mr-1"></i>
                                Tips Keamanan</h4>
                            <ul class="text-xs text-amber-700 space-y-1">
                                <li>• Gunakan kombinasi huruf, angka, dan simbol</li>
                                <li>• Jangan gunakan password yang mudah ditebak</li>
                                <li>• Ubah password secara berkala</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('sidebar-hidden');
            document.getElementById('overlay').classList.toggle('hidden');
        }
    </script>
</body>

</html>