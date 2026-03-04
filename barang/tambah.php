<?php
/**
 * Tambah Barang Baru
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(['admin', 'petugas']);

$user = currentUser();
$pageTitle = 'Tambah Barang';

// Get kategori & lokasi
try {
    $pdo = db();
    $kategoriList = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();
    $lokasiList = $pdo->query("SELECT * FROM lokasi ORDER BY nama_lokasi")->fetchAll();
    $kondisiList = $pdo->query("SELECT * FROM kondisi ORDER BY nama_kondisi")->fetchAll();
} catch (Exception $e) {
    $kategoriList = [];
    $lokasiList = [];
    $kondisiList = [];
}

// Handle form submit
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode = trim($_POST['kode_barang'] ?? '');
    $nama = trim($_POST['nama_barang'] ?? '');
    $kategori = $_POST['kategori_id'] ?? null;
    $lokasi = $_POST['lokasi_id'] ?? null;
    $jumlah = (int) ($_POST['jumlah'] ?? 1);
    $kondisi_id = $_POST['kondisi_id'] ?? null;
    $keterangan = trim($_POST['keterangan'] ?? '');

    if (empty($kode) || empty($nama)) {
        $error = 'Kode dan nama barang wajib diisi!';
    } else {
        try {
            $pdo = db();

            // Check duplicate kode
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM barang WHERE kode_barang = ?");
            $stmt->execute([$kode]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Kode barang sudah digunakan!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO barang (kode_barang, nama_barang, kategori_id, lokasi_id, jumlah, kondisi_id, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$kode, $nama, $kategori ?: null, $lokasi ?: null, $jumlah, $kondisi_id, $keterangan]);

                header('Location: index.php?success=1');
                exit;
            }
        } catch (Exception $e) {
            $error = 'Gagal menyimpan: ' . $e->getMessage();
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
            theme: {
                extend: {
                    colors: {
                        'catalina': {
                            '50': '#eaf8ff', '100': '#d0f0ff', '200': '#abe7ff', '300': '#71daff',
                            '400': '#2ec2ff', '500': '#009cff', '600': '#0074ff', '700': '#005aff',
                            '800': '#004bde', '900': '#0045ad', '950': '#04337c',
                        }
                    }
                }
            }
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
                        class="flex items-center gap-3 px-4 py-3 rounded-xl bg-catalina-50 text-catalina-700 font-medium hover:bg-gray-100 hover:text-catalina-700 transition-all">
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
                        <span>Kelola Peminjaman</span>
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
            </nav>
        </aside>

        <main class="flex-1 lg:ml-64 min-w-0">
            <header class="bg-white shadow-sm sticky top-0 z-10 border-b border-gray-200">
                <div class="flex items-center justify-between px-4 lg:px-6 py-4">
                    <div class="flex items-center gap-3">
                        <button onclick="toggleSidebar()"
                            class="lg:hidden p-2 -ml-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        <div>
                            <h1 class="text-lg lg:text-xl font-bold text-gray-800"><?= $pageTitle ?></h1>
                            <p class="text-xs text-gray-500">Masukkan data barang baru</p>
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
                <!-- Breadcrumb -->
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
                    <a href="index.php" class="hover:text-catalina-600">Data Barang</a>
                    <i class="fas fa-chevron-right text-xs"></i>
                    <span class="text-gray-800">Tambah Baru</span>
                </div>

                <?php if ($error): ?>
                    <div
                        class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 flex items-center gap-2">
                        <i class="fas fa-exclamation-circle"></i><?= $error ?>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <div class="bg-white rounded-2xl p-6 border border-gray-200">
                    <form method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Kode Barang -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Kode Barang <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="kode_barang"
                                    value="<?= htmlspecialchars($_POST['kode_barang'] ?? '') ?>" required
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                                    placeholder="Contoh: INV-001">
                            </div>

                            <!-- Nama Barang -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Nama Barang <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="nama_barang"
                                    value="<?= htmlspecialchars($_POST['nama_barang'] ?? '') ?>" required
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                                    placeholder="Nama barang">
                            </div>

                            <!-- Kategori -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                                <select name="kategori_id"
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500">
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($kategoriList as $k): ?>
                                        <option value="<?= $k['id'] ?>" <?= ($_POST['kategori_id'] ?? '') == $k['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($k['nama_kategori']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Lokasi -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Lokasi</label>
                                <select name="lokasi_id"
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500">
                                    <option value="">-- Pilih Lokasi --</option>
                                    <?php foreach ($lokasiList as $l): ?>
                                        <option value="<?= $l['id'] ?>" <?= ($_POST['lokasi_id'] ?? '') == $l['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($l['nama_lokasi']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Jumlah -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Jumlah</label>
                                <input type="number" name="jumlah"
                                    value="<?= htmlspecialchars($_POST['jumlah'] ?? '1') ?>" min="1"
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100">
                            </div>

                            <!-- Kondisi -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Kondisi</label>
                                <select name="kondisi_id"
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500">
                                    <option value="">-- Pilih Kondisi --</option>
                                    <?php foreach ($kondisiList as $kd): ?>
                                        <option value="<?= $kd['id'] ?>" <?= ($_POST['kondisi_id'] ?? '') == $kd['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($kd['nama_kondisi']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Keterangan -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Keterangan</label>
                            <textarea name="keterangan" rows="3"
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                                placeholder="Keterangan tambahan (opsional)"><?= htmlspecialchars($_POST['keterangan'] ?? '') ?></textarea>
                        </div>

                        <!-- Buttons -->
                        <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
                            <button type="submit"
                                class="px-6 py-2.5 bg-catalina-600 hover:bg-catalina-700 text-white rounded-xl font-medium transition-all">
                                <i class="fas fa-save mr-2"></i>Simpan
                            </button>
                            <a href="index.php"
                                class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition-all">
                                Batal
                            </a>
                        </div>
                    </form>
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