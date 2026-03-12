<?php
/**
 * Dashboard - Sistem Inventaris Dinas Sosial Lampung
 */

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = currentUser();
$pageTitle = 'Dashboard';

// Get stats for dashboard
try {
    $pdo = db();

    // Total barang
    $totalBarang = $pdo->query("SELECT COUNT(*) FROM barang")->fetchColumn();

    // Total kategori
    $totalKategori = $pdo->query("SELECT COUNT(*) FROM kategori")->fetchColumn();

    // Peminjaman aktif (pending/disetujui/dipinjam)
    $peminjamanAktif = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status IN ('pending', 'disetujui', 'dipinjam')")->fetchColumn();

    // Menunggu persetujuan
    $menungguPersetujuan = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'pending'")->fetchColumn();

    // Barang kondisi baik
    $barangBaik = $pdo->query("SELECT COUNT(*) FROM barang b JOIN kondisi kd ON b.kondisi_id = kd.id WHERE kd.nama_kondisi = 'Baik'")->fetchColumn();

    // Barang bermasalah (rusak + hilang)
    $barangBermasalah = $pdo->query("SELECT COUNT(*) FROM barang b JOIN kondisi kd ON b.kondisi_id = kd.id WHERE kd.nama_kondisi IN ('Rusak Ringan', 'Rusak Berat', 'Hilang')")->fetchColumn();
    $barangHilang = $pdo->query("SELECT COUNT(*) FROM barang b JOIN kondisi kd ON b.kondisi_id = kd.id WHERE kd.nama_kondisi = 'Hilang'")->fetchColumn();

    // Kondisi barang untuk chart
    $kondisiBaik = $pdo->query("SELECT COUNT(*) FROM barang b JOIN kondisi kd ON b.kondisi_id = kd.id WHERE kd.nama_kondisi = 'Baik'")->fetchColumn();
    $kondisiRusakRingan = $pdo->query("SELECT COUNT(*) FROM barang b JOIN kondisi kd ON b.kondisi_id = kd.id WHERE kd.nama_kondisi = 'Rusak Ringan'")->fetchColumn();
    $kondisiRusakBerat = $pdo->query("SELECT COUNT(*) FROM barang b JOIN kondisi kd ON b.kondisi_id = kd.id WHERE kd.nama_kondisi = 'Rusak Berat'")->fetchColumn();
    $kondisiHilang = $pdo->query("SELECT COUNT(*) FROM barang b JOIN kondisi kd ON b.kondisi_id = kd.id WHERE kd.nama_kondisi = 'Hilang'")->fetchColumn();

    // Barang per kategori
    $barangPerKategori = $pdo->query("
        SELECT k.nama_kategori, COUNT(b.id) as jumlah 
        FROM kategori k 
        LEFT JOIN barang b ON k.id = b.kategori_id 
        GROUP BY k.id, k.nama_kategori
    ")->fetchAll();

    // Total lokasi
    $totalLokasi = $pdo->query("SELECT COUNT(*) FROM lokasi")->fetchColumn();

    // Total user
    $totalUser = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    // Peminjaman terbaru
    $peminjamanTerbaru = $pdo->query("
        SELECT p.*, u.nama as nama_peminjam, b.nama_barang 
        FROM peminjaman p 
        JOIN users u ON p.user_id = u.id 
        JOIN barang b ON p.barang_id = b.id 
        ORDER BY p.created_at DESC 
        LIMIT 5
    ")->fetchAll();

    // Barang terbaru
    $barangTerbaru = $pdo->query("
        SELECT b.*, k.nama_kategori, kd.nama_kondisi 
        FROM barang b 
        LEFT JOIN kategori k ON b.kategori_id = k.id 
        LEFT JOIN kondisi kd ON b.kondisi_id = kd.id 
        ORDER BY b.created_at DESC 
        LIMIT 5
    ")->fetchAll();

} catch (Exception $e) {
    $totalBarang = 0;
    $totalKategori = 0;
    $peminjamanAktif = 0;
    $menungguPersetujuan = 0;
    $barangBaik = 0;
    $barangBermasalah = 0;
    $barangHilang = 0;
    $kondisiBaik = 0;
    $kondisiRusakRingan = 0;
    $kondisiRusakBerat = 0;
    $kondisiHilang = 0;
    $barangPerKategori = [];
    $totalLokasi = 0;
    $totalUser = 0;
    $peminjamanTerbaru = [];
    $barangTerbaru = [];
}

// Prepare chart data
$kategoriLabels = array_column($barangPerKategori, 'nama_kategori');
$kategoriData = array_column($barangPerKategori, 'jumlah');
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'catalina': {
                            '50': '#eaf8ff',
                            '100': '#d0f0ff',
                            '200': '#abe7ff',
                            '300': '#71daff',
                            '400': '#2ec2ff',
                            '500': '#009cff',
                            '600': '#0074ff',
                            '700': '#005aff',
                            '800': '#004bde',
                            '900': '#0045ad',
                            '950': '#04337c',
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
        <!-- Mobile Overlay -->
        <div id="overlay" class="fixed inset-0 bg-black/50 z-30 lg:hidden hidden" onclick="toggleSidebar()"></div>

        <!-- Sidebar -->
        <aside id="sidebar"
            class="w-64 bg-white text-gray-800 fixed h-full z-40 shadow-lg sidebar-hidden lg:translate-x-0 border-r border-gray-200">
            <!-- Logo -->
            <div class="p-4 lg:p-6 border-b border-gray-200 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 border-2 border-catalina-600 rounded-xl flex items-center justify-center">
                        <img src="assets/image/logo.png" alt="Logo">
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
                <a href="dashboard.php"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl bg-catalina-50 text-catalina-700 font-medium transition-all">
                    <i class="fas fa-home w-5"></i>
                    <span>Dashboard</span>
                </a>

                <?php if (hasRole(['admin', 'petugas'])): ?>
                    <div class="pt-4">
                        <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Inventaris</p>
                    </div>
                    <a href="barang/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-box w-5"></i>
                        <span>Data Barang</span>
                    </a>
                    <a href="kategori/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-tags w-5"></i>
                        <span>Kategori</span>
                    </a>
                    <a href="lokasi/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-map-marker-alt w-5"></i>
                        <span>Lokasi</span>
                    </a>
                    <a href="kondisi/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-clipboard-check w-5"></i>
                        <span>Kondisi</span>
                    </a>
                    <a href="habis_pakai/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-boxes-packing w-5"></i>
                        <span>Barang Habis Pakai</span>
                    </a>
                <?php endif; ?>

                <div class="pt-4">
                    <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Peminjaman</p>
                </div>
                <?php if (hasRole(['pegawai'])): ?>
                    <a href="peminjaman/ajukan.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-plus-circle w-5"></i>
                        <span>Ajukan Peminjaman</span>
                    </a>
                    <a href="peminjaman/riwayat.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-history w-5"></i>
                        <span>Riwayat Saya</span>
                    </a>
                <?php endif; ?>

                <?php if (hasRole(['admin', 'petugas'])): ?>
                    <a href="peminjaman/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-hand-holding w-5"></i>
                        <span class="flex-1">Kelola Peminjaman</span>
                        <?php if ($menungguPersetujuan > 0): ?>
                            <span
                                class="px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded-full animate-pulse"><?= $menungguPersetujuan ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="pengembalian/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-undo w-5"></i>
                        <span>Pengembalian</span>
                    </a>
                <?php endif; ?>

                <?php if (hasRole(['admin', 'pimpinan'])): ?>
                    <div class="pt-4">
                        <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Laporan</p>
                    </div>
                    <a href="laporan/barang.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-file-alt w-5"></i>
                        <span>Laporan Barang</span>
                    </a>
                    <a href="laporan/peminjaman.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-file-invoice w-5"></i>
                        <span>Laporan Peminjaman</span>
                    </a>
                <?php endif; ?>

                <?php if (hasRole(['admin'])): ?>
                    <div class="pt-4">
                        <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Admin</p>
                    </div>
                    <a href="users/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                        <i class="fas fa-users w-5"></i>
                        <span>Kelola User</span>
                    </a>
                <?php endif; ?>

                <div class="pt-4">
                    <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Akun</p>
                </div>
                <a href="profil/index.php"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                    <i class="fas fa-user-cog w-5"></i>
                    <span>Profil Saya</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 lg:ml-64 min-w-0">
            <!-- Top Navbar -->
            <header class="bg-white shadow-sm sticky top-0 z-20 border-b border-gray-200">
                <div class="flex items-center justify-between px-4 lg:px-6 py-4">
                    <div class="flex items-center gap-3">
                        <button onclick="toggleSidebar()"
                            class="lg:hidden p-2 -ml-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-all">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        <div>
                            <h1 class="text-lg lg:text-xl font-bold text-gray-800"><?= $pageTitle ?></h1>
                            <p class="text-xs lg:text-sm text-gray-500">Selamat datang di Sistem Inventaris Dinas Sosial
                            </p>
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
                            <a href="logout.php"
                                class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all"
                                title="Logout">
                                <i class="fas fa-sign-out-alt"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="p-4 lg:p-6">
                <!-- Stats Cards Row -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <!-- Total Barang -->
                    <div
                        class="bg-gradient-to-br from-catalina-500 to-catalina-700 rounded-2xl p-5 text-white relative overflow-hidden">
                        <div class="relative z-10">
                            <p class="text-sm opacity-90">Total Barang</p>
                            <p class="text-3xl lg:text-4xl font-bold mt-1"><?= $totalBarang ?></p>
                            <p class="text-xs mt-2 opacity-75"><i class="fas fa-tags mr-1"></i><?= $totalKategori ?>
                                Kategori</p>
                        </div>
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full"></div>
                        <div class="absolute right-4 top-4">
                            <i class="fas fa-boxes text-3xl opacity-30"></i>
                        </div>
                    </div>

                    <!-- Peminjaman Aktif -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-200 relative overflow-hidden">
                        <div class="relative z-10">
                            <p class="text-sm text-gray-500">Peminjaman Aktif</p>
                            <p class="text-3xl lg:text-4xl font-bold text-gray-800 mt-1"><?= $peminjamanAktif ?></p>
                            <p class="text-xs mt-2 text-amber-600"><i
                                    class="fas fa-clock mr-1"></i><?= $menungguPersetujuan ?> Menunggu Persetujuan</p>
                        </div>
                        <div class="absolute right-4 top-4">
                            <i class="fas fa-exchange-alt text-3xl text-gray-200"></i>
                        </div>
                    </div>

                    <!-- Barang Baik -->
                    <div
                        class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl p-5 text-white relative overflow-hidden">
                        <div class="relative z-10">
                            <p class="text-sm opacity-90">Barang Baik</p>
                            <p class="text-3xl lg:text-4xl font-bold mt-1"><?= $barangBaik ?></p>
                            <p class="text-xs mt-2 opacity-75"><i class="fas fa-check-circle mr-1"></i>Kondisi Prima</p>
                        </div>
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full"></div>
                        <div class="absolute right-4 top-4">
                            <i class="fas fa-check-circle text-3xl opacity-30"></i>
                        </div>
                    </div>

                    <!-- Barang Bermasalah -->
                    <div
                        class="bg-gradient-to-br from-red-500 to-red-600 rounded-2xl p-5 text-white relative overflow-hidden">
                        <div class="relative z-10">
                            <p class="text-sm opacity-90">Barang Bermasalah</p>
                            <p class="text-3xl lg:text-4xl font-bold mt-1"><?= $barangBermasalah ?></p>
                            <p class="text-xs mt-2 opacity-75"><i
                                    class="fas fa-exclamation-circle mr-1"></i><?= $barangHilang ?> Hilang</p>
                        </div>
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full"></div>
                        <div class="absolute right-4 top-4">
                            <i class="fas fa-exclamation-triangle text-3xl opacity-30"></i>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                    <!-- Kondisi Barang - Donut Chart -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-200">
                        <h3 class="font-semibold text-gray-800 mb-4">Kondisi Barang</h3>
                        <div class="flex items-center justify-center">
                            <div class="w-48 h-48">
                                <canvas id="kondisiChart"></canvas>
                            </div>
                        </div>
                        <div class="flex flex-wrap justify-center gap-3 mt-4">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 bg-emerald-500 rounded-full"></span>
                                <span class="text-xs text-gray-600">Baik</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 bg-amber-500 rounded-full"></span>
                                <span class="text-xs text-gray-600">Rusak Ringan</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                                <span class="text-xs text-gray-600">Rusak Berat</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 bg-gray-500 rounded-full"></span>
                                <span class="text-xs text-gray-600">Hilang</span>
                            </div>
                        </div>
                    </div>

                    <!-- Barang per Kategori - Bar Chart -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-200">
                        <h3 class="font-semibold text-gray-800 mb-4">Barang per Kategori</h3>
                        <div class="h-48">
                            <canvas id="kategoriChart"></canvas>
                        </div>
                    </div>

                    <!-- Informasi Aset -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-200">
                        <h3 class="font-semibold text-gray-800 mb-4">Informasi Aset</h3>
                        <div class="space-y-4">
                            <div class="flex items-center gap-4 p-3 bg-purple-50 rounded-xl">
                                <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center">
                                    <i class="fas fa-map-marker-alt text-white"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Total Lokasi</p>
                                    <p class="font-bold text-gray-800"><?= $totalLokasi ?> Lokasi</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 p-3 bg-cyan-50 rounded-xl">
                                <div class="w-10 h-10 bg-cyan-500 rounded-full flex items-center justify-center">
                                    <i class="fas fa-users text-white"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Total Pengguna</p>
                                    <p class="font-bold text-gray-800"><?= $totalUser ?> User</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Row: Peminjaman & Barang Terbaru -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <!-- Peminjaman Terbaru -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-gray-800">Peminjaman Terbaru</h3>
                            <a href="peminjaman/index.php" class="text-sm text-catalina-600 hover:underline">Lihat
                                Semua</a>
                        </div>
                        <div class="space-y-3">
                            <?php if (empty($peminjamanTerbaru)): ?>
                                <div class="text-center py-8 text-gray-400">
                                    <i class="fas fa-inbox text-3xl mb-2"></i>
                                    <p class="text-sm">Belum ada peminjaman</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($peminjamanTerbaru as $p): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-10 h-10 bg-catalina-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-exchange-alt text-catalina-600"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-800 text-sm">
                                                    <?= htmlspecialchars($p['nama_barang']) ?>
                                                </p>
                                                <p class="text-xs text-gray-500"><?= htmlspecialchars($p['nama_peminjam']) ?> •
                                                    <?= date('d M Y', strtotime($p['tanggal_pinjam'])) ?>
                                                </p>
                                            </div>
                                        </div>
                                        <?php
                                        $statusClass = match ($p['status']) {
                                            'pending' => 'bg-amber-100 text-amber-700',
                                            'disetujui' => 'bg-blue-100 text-blue-700',
                                            'ditolak' => 'bg-red-100 text-red-700',
                                            'dipinjam' => 'bg-purple-100 text-purple-700',
                                            'dikembalikan' => 'bg-green-100 text-green-700',
                                            default => 'bg-gray-100 text-gray-700'
                                        };
                                        $statusText = ucfirst($p['status']);
                                        ?>
                                        <span
                                            class="px-3 py-1 rounded-full text-xs font-medium <?= $statusClass ?>"><?= $statusText ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Barang Terbaru -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-gray-800">Barang Terbaru</h3>
                            <a href="barang/index.php" class="text-sm text-catalina-600 hover:underline">Lihat Semua</a>
                        </div>
                        <div class="space-y-3">
                            <?php if (empty($barangTerbaru)): ?>
                                <div class="text-center py-8 text-gray-400">
                                    <i class="fas fa-box-open text-3xl mb-2"></i>
                                    <p class="text-sm">Belum ada barang</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($barangTerbaru as $b): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-box text-emerald-600"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-800 text-sm">
                                                    <?= htmlspecialchars($b['nama_barang']) ?>
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    <?= htmlspecialchars($b['nama_kategori'] ?? 'Tanpa Kategori') ?> •
                                                    <?= $b['jumlah'] ?> unit
                                                </p>
                                            </div>
                                        </div>
                                        <?php
                                        $namaKondisi = strtolower($b['nama_kondisi'] ?? '');
                                        $kondisiClass = match (true) {
                                            str_contains($namaKondisi, 'baik') => 'bg-emerald-100 text-emerald-700',
                                            str_contains($namaKondisi, 'rusak ringan') => 'bg-amber-100 text-amber-700',
                                            str_contains($namaKondisi, 'rusak berat') => 'bg-red-100 text-red-700',
                                            str_contains($namaKondisi, 'hilang') => 'bg-gray-100 text-gray-700',
                                            default => 'bg-gray-100 text-gray-700'
                                        };
                                        $kondisiText = $b['nama_kondisi'] ?? 'Tidak Diketahui';
                                        ?>
                                        <span
                                            class="px-3 py-1 rounded-full text-xs font-medium <?= $kondisiClass ?>"><?= $kondisiText ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Sidebar Toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('sidebar-hidden');
            overlay.classList.toggle('hidden');
            document.body.style.overflow = sidebar.classList.contains('sidebar-hidden') ? '' : 'hidden';
        }

        document.addEventListener('keydown', e => { if (e.key === 'Escape') toggleSidebar(); });
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) {
                document.getElementById('overlay').classList.add('hidden');
                document.body.style.overflow = '';
            }
        });

        // Kondisi Chart (Donut)
        new Chart(document.getElementById('kondisiChart'), {
            type: 'doughnut',
            data: {
                labels: ['Baik', 'Rusak Ringan', 'Rusak Berat', 'Hilang'],
                datasets: [{
                    data: [<?= $kondisiBaik ?>, <?= $kondisiRusakRingan ?>, <?= $kondisiRusakBerat ?>, <?= $kondisiHilang ?>],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#6b7280'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '70%',
                plugins: { legend: { display: false } }
            }
        });

        // Kategori Chart (Bar)
        new Chart(document.getElementById('kategoriChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($kategoriLabels) ?>,
                datasets: [{
                    label: 'Jumlah Barang',
                    data: <?= json_encode($kategoriData) ?>,
                    backgroundColor: '#0074ff',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 20 } },
                    x: { grid: { display: false } }
                }
            }
        });
    </script>
</body>

</html>