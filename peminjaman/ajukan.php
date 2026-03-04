<?php
/**
 * Ajukan Peminjaman - Form untuk Pegawai
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(['pegawai']);

$user = currentUser();
$pageTitle = 'Ajukan Peminjaman';

// Get available barang
try {
    $pdo = db();
    $barangList = $pdo->query("SELECT b.*, k.nama_kategori FROM barang b LEFT JOIN kategori k ON b.kategori_id = k.id WHERE b.kondisi_id = 1 AND b.jumlah > 0 ORDER BY b.nama_barang")->fetchAll();
} catch (Exception $e) {
    $barangList = [];
}

// Handle form submit
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barang_id = $_POST['barang_id'] ?? null;
    $tanggal_pinjam = $_POST['tanggal_pinjam'] ?? '';
    $tanggal_kembali = $_POST['tanggal_kembali_rencana'] ?? '';
    $keperluan = trim($_POST['keperluan'] ?? '');

    if (empty($barang_id) || empty($tanggal_pinjam) || empty($tanggal_kembali)) {
        $error = 'Semua field wajib diisi!';
    } elseif (strtotime($tanggal_kembali) < strtotime($tanggal_pinjam)) {
        $error = 'Tanggal kembali tidak boleh sebelum tanggal pinjam!';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO peminjaman (user_id, barang_id, tanggal_pinjam, tanggal_kembali_rencana, keperluan, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$user['id'], $barang_id, $tanggal_pinjam, $tanggal_kembali, $keperluan]);
            $success = 'Peminjaman berhasil diajukan! Menunggu persetujuan.';
        } catch (Exception $e) {
            $error = 'Gagal mengajukan peminjaman!';
        }
    }
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

        <aside id="sidebar"
            class="w-64 bg-white text-gray-800 fixed h-full z-40 shadow-lg sidebar-hidden lg:translate-x-0 border-r border-gray-200">
            <div class="p-4 lg:p-6 border-b border-gray-200 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 border-2 border-catalina-600 rounded-xl flex items-center justify-center">
                        <img src="../assets/image/logo.png" alt="Logo">
                    </div>
                    <div>
                        <h1 class="font-bold text-sm text-catalina-900">Sistem Inventaris</h1>
                        <p class="text-xs text-gray-500">Dinsos Lampung</p>
                    </div>
                </div>
                <button onclick="toggleSidebar()" class="lg:hidden p-2 hover:bg-gray-100 rounded-lg text-gray-500"><i
                        class="fas fa-times"></i></button>
            </div>
            <nav class="p-4 space-y-1">
                <a href="../dashboard.php"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600"><i
                        class="fas fa-home w-5"></i><span>Dashboard</span></a>
                <div class="pt-4">
                    <p class="px-4 text-xs font-semibold text-gray-400 uppercase">Peminjaman</p>
                </div>
                <a href="ajukan.php"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl bg-catalina-50 text-catalina-700 font-medium"><i
                        class="fas fa-plus-circle w-5"></i><span>Ajukan Peminjaman</span></a>
                <a href="riwayat.php"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600"><i
                        class="fas fa-history w-5"></i><span>Riwayat Saya</span></a>

                <div class="pt-4">
                    <p class="px-4 text-xs font-semibold text-gray-400 uppercase">Akun</p>
                </div>
                <a href="../profil/index.php"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600"><i
                        class="fas fa-user-cog w-5"></i><span>Profil Saya</span></a>
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
                            <p class="text-xs text-gray-500">Ajukan peminjaman barang inventaris</p>
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
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div
                        class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 flex items-center gap-2">
                        <i class="fas fa-exclamation-circle"></i><?= $error ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl p-6 border border-gray-200">
                    <form method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Pilih Barang -->
                            <div class="lg:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Barang <span
                                        class="text-red-500">*</span></label>
                                <select name="barang_id" required
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500">
                                    <option value="">-- Pilih Barang --</option>
                                    <?php foreach ($barangList as $b): ?>
                                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nama_barang']) ?>
                                            (<?= htmlspecialchars($b['nama_kategori'] ?? 'Tanpa Kategori') ?>) - Stok:
                                            <?= $b['jumlah'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Tanggal Pinjam -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Pinjam <span
                                        class="text-red-500">*</span></label>
                                <input type="date" name="tanggal_pinjam" required min="<?= date('Y-m-d') ?>"
                                    value="<?= date('Y-m-d') ?>"
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500">
                            </div>

                            <!-- Tanggal Kembali -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Rencana Tanggal Kembali
                                    <span class="text-red-500">*</span></label>
                                <input type="date" name="tanggal_kembali_rencana" required min="<?= date('Y-m-d') ?>"
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500">
                            </div>

                            <!-- Keperluan -->
                            <div class="lg:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Keperluan</label>
                                <textarea name="keperluan" rows="3"
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500"
                                    placeholder="Jelaskan keperluan peminjaman..."></textarea>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
                            <button type="submit"
                                class="px-6 py-2.5 bg-catalina-600 hover:bg-catalina-700 text-white rounded-xl font-medium transition-all">
                                <i class="fas fa-paper-plane mr-2"></i>Ajukan Peminjaman
                            </button>
                            <a href="riwayat.php"
                                class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition-all">Lihat
                                Riwayat</a>
                        </div>
                    </form>
                </div>

                <!-- Info Box -->
                <div class="mt-6 bg-blue-50 border border-blue-200 rounded-2xl p-4">
                    <h4 class="font-semibold text-blue-800 mb-2"><i class="fas fa-info-circle mr-2"></i>Informasi</h4>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>• Peminjaman akan diproses oleh petugas inventaris</li>
                        <li>• Anda akan mendapat notifikasi setelah peminjaman disetujui/ditolak</li>
                        <li>• Pastikan mengembalikan barang sesuai tanggal yang ditentukan</li>
                    </ul>
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