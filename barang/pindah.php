<?php
/**
 * Pindah Barang - Memindahkan stok barang ke lokasi lain
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(['admin', 'petugas']);

$user = currentUser();
$pageTitle = 'Pindah Barang';

$error = '';
$success = '';
$source_id = $_GET['id'] ?? null;
$source_barang = null;

try {
    $pdo = db();

    // Ambil data untuk notifikasi pending peminjaman
    $pendingPeminjaman = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'pending'")->fetchColumn();

    // Ambil data semua lokasi untuk pilihan tujuan
    $lokasiList = $pdo->query("SELECT * FROM lokasi ORDER BY nama_lokasi")->fetchAll();

    // Ambil data semua barang untuk penggabungan
    $allBarang = $pdo->query("SELECT id, kode_barang, nama_barang, lokasi_id, jumlah FROM barang ORDER BY nama_barang")->fetchAll();

    // Ambil data barang sumber yang memiliki stok > 0
    $sourceList = $pdo->query("SELECT b.id, b.kode_barang, b.nama_barang, b.jumlah, b.lokasi_id, l.nama_lokasi 
                               FROM barang b 
                               LEFT JOIN lokasi l ON b.lokasi_id = l.id 
                               WHERE b.jumlah > 0 
                               ORDER BY b.nama_barang")->fetchAll();

    // Jika parameter ID barang sumber disediakan
    if ($source_id) {
        $stmt = $pdo->prepare("SELECT b.*, l.nama_lokasi FROM barang b LEFT JOIN lokasi l ON b.lokasi_id = l.id WHERE b.id = ?");
        $stmt->execute([$source_id]);
        $source_barang = $stmt->fetch();

        if (!$source_barang) {
            $error = 'Barang sumber tidak ditemukan!';
        } elseif ($source_barang['jumlah'] <= 0) {
            $error = 'Stok barang sumber kosong, tidak dapat dipindahkan!';
        }
    }
} catch (Exception $e) {
    $error = 'Gagal mengambil data awal: ' . $e->getMessage();
}

// Proses form pemindahan barang
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_source_id = $_POST['source_id'] ?? $source_id;
    $jumlah_pindah = intval($_POST['jumlah_pindah'] ?? 0);
    $lokasi_tujuan_id = $_POST['lokasi_tujuan_id'] ?? '';
    $metode = $_POST['metode'] ?? 'baru'; // 'baru' atau 'gabung'
    $target_barang_id = $_POST['target_barang_id'] ?? '';
    $new_kode_barang = trim($_POST['new_kode_barang'] ?? '');

    if (empty($selected_source_id)) {
        $error = 'Silakan pilih barang sumber terlebih dahulu!';
    } elseif ($jumlah_pindah <= 0) {
        $error = 'Jumlah barang yang dipindahkan harus lebih dari 0!';
    } elseif (empty($lokasi_tujuan_id)) {
        $error = 'Silakan pilih lokasi tujuan!';
    } else {
        try {
            $pdo = db();

            // Fetch detail barang sumber untuk verifikasi stok terbaru
            $stmt = $pdo->prepare("SELECT * FROM barang WHERE id = ?");
            $stmt->execute([$selected_source_id]);
            $latest_source = $stmt->fetch();

            if (!$latest_source) {
                $error = 'Barang sumber tidak ditemukan di database!';
            } elseif ($jumlah_pindah > $latest_source['jumlah']) {
                $error = 'Jumlah pemindahan melebihi stok yang tersedia saat ini (Stok: ' . $latest_source['jumlah'] . ')!';
            } elseif ($lokasi_tujuan_id == $latest_source['lokasi_id']) {
                $error = 'Lokasi tujuan tidak boleh sama dengan lokasi barang saat ini!';
            } else {
                if ($metode === 'gabung') {
                    if (empty($target_barang_id)) {
                        $error = 'Silakan pilih barang tujuan penggabungan!';
                    } else {
                        // Verifikasi barang tujuan
                        $stmt = $pdo->prepare("SELECT * FROM barang WHERE id = ?");
                        $stmt->execute([$target_barang_id]);
                        $target_barang = $stmt->fetch();

                        if (!$target_barang) {
                            $error = 'Barang tujuan tidak ditemukan!';
                        } elseif ($target_barang['lokasi_id'] != $lokasi_tujuan_id) {
                            $error = 'Barang tujuan harus berada di lokasi tujuan yang dipilih!';
                        } else {
                            // Jalankan transaksi database
                            $pdo->beginTransaction();

                            // 1. Kurangi stok di barang sumber
                            $stmt = $pdo->prepare("UPDATE barang SET jumlah = jumlah - ? WHERE id = ?");
                            $stmt->execute([$jumlah_pindah, $selected_source_id]);

                            // 2. Tambah stok di barang tujuan
                            $stmt = $pdo->prepare("UPDATE barang SET jumlah = jumlah + ? WHERE id = ?");
                            $stmt->execute([$jumlah_pindah, $target_barang_id]);

                            $pdo->commit();

                            $msg = "Berhasil memindahkan " . $jumlah_pindah . " unit barang ke '" . $target_barang['nama_barang'] . "' di lokasi baru.";
                            header("Location: index.php?success=" . urlencode($msg));
                            exit;
                        }
                    }
                } else {
                    // Metode Buat Baru
                    if (empty($new_kode_barang)) {
                        $error = 'Kode barang baru wajib diisi!';
                    } else {
                        // Cek keunikan kode barang baru
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM barang WHERE kode_barang = ?");
                        $stmt->execute([$new_kode_barang]);
                        if ($stmt->fetchColumn() > 0) {
                            $error = "Kode barang baru '$new_kode_barang' sudah digunakan oleh barang lain!";
                        } else {
                            // Jalankan transaksi database
                            $pdo->beginTransaction();

                            // 1. Kurangi stok di barang sumber
                            $stmt = $pdo->prepare("UPDATE barang SET jumlah = jumlah - ? WHERE id = ?");
                            $stmt->execute([$jumlah_pindah, $selected_source_id]);

                            // 2. Insert barang baru dengan lokasi baru
                            $new_keterangan = "Hasil pemindahan dari " . $latest_source['kode_barang'] . ".";
                            if (!empty($latest_source['keterangan'])) {
                                $new_keterangan .= " Keterangan asal: " . $latest_source['keterangan'];
                            }

                            $stmt = $pdo->prepare("INSERT INTO barang (kode_barang, nama_barang, kategori_id, lokasi_id, jumlah, kondisi_id, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $new_kode_barang,
                                $latest_source['nama_barang'],
                                $latest_source['kategori_id'],
                                $lokasi_tujuan_id,
                                $jumlah_pindah,
                                $latest_source['kondisi_id'],
                                $new_keterangan
                            ]);

                            $pdo->commit();

                            $msg = "Berhasil memindahkan " . $jumlah_pindah . " unit barang ke lokasi baru dengan kode barang " . $new_kode_barang;
                            header("Location: index.php?success=" . urlencode($msg));
                            exit;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
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
        <!-- Overlay Mobiles -->
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
                        <span class="flex-1">Kelola Peminjaman</span>
                        <?php if (isset($pendingPeminjaman) && $pendingPeminjaman > 0): ?>
                            <span class="px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded-full animate-pulse"><?= $pendingPeminjaman ?></span>
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
            </nav>
        </aside>

        <!-- Main Content -->
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
                            <p class="text-xs text-gray-500">Pindahkan sebagian/seluruh stok barang ke lokasi lain</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 lg:gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-catalina-600 rounded-full flex items-center justify-center text-white font-semibold">
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

            <div class="p-4 lg:p-6 text-gray-800">
                <!-- Breadcrumb -->
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
                    <a href="index.php" class="hover:text-catalina-600">Data Barang</a>
                    <i class="fas fa-chevron-right text-xs"></i>
                    <span class="text-gray-800">Pindah Barang</span>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
                        <i class="fas fa-exclamation-circle"></i><?= $error ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm max-w-7xl">
                    <form method="POST" id="transferForm" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <!-- Pilih Barang Sumber -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Barang Sumber <span class="text-red-500">*</span>
                                </label>
                                <?php if ($source_barang): ?>
                                    <input type="hidden" name="source_id" value="<?= $source_barang['id'] ?>">
                                    <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl space-y-2">
                                        <p class="font-bold text-catalina-900"><?= htmlspecialchars($source_barang['nama_barang']) ?></p>
                                        <p class="text-xs text-gray-500">Kode: <span class="font-mono bg-gray-200 px-1.5 py-0.5 rounded text-gray-700"><?= htmlspecialchars($source_barang['kode_barang']) ?></span></p>
                                        <p class="text-xs text-gray-500">Lokasi Saat Ini: <span class="font-medium text-gray-700"><?= htmlspecialchars($source_barang['nama_lokasi'] ?? 'Tanpa Lokasi') ?></span></p>
                                        <p class="text-xs text-gray-500">Stok Tersedia: <span class="font-bold text-green-600" id="currentStockDisplay"><?= $source_barang['jumlah'] ?></span> unit</p>
                                    </div>
                                <?php else: ?>
                                    <select name="source_id" id="sourceSelector" required
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100">
                                        <option value="">-- Pilih Barang Sumber --</option>
                                        <?php foreach ($sourceList as $src): ?>
                                            <option value="<?= $src['id'] ?>" <?= (($_POST['source_id'] ?? '') == $src['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($src['nama_barang']) ?> (<?= htmlspecialchars($src['kode_barang']) ?> - <?= htmlspecialchars($src['nama_lokasi'] ?? 'Tanpa Lokasi') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="sourceDetails" class="hidden mt-3 p-4 bg-gray-50 border border-gray-200 rounded-xl space-y-1 text-sm">
                                        <p class="text-xs text-gray-500">Lokasi Asal: <span id="sourceLocName" class="font-semibold text-gray-700"></span></p>
                                        <p class="text-xs text-gray-500">Stok Asal: <span id="sourceStockVal" class="font-bold text-green-600"></span> unit</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Jumlah & Lokasi Tujuan -->
                            <div class="space-y-4">
                                <!-- Jumlah Pindah -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Jumlah Yang Dipindahkan <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number" name="jumlah_pindah" id="jumlahPindah" required min="1"
                                        value="<?= htmlspecialchars($_POST['jumlah_pindah'] ?? '1') ?>"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                                        placeholder="Masukkan jumlah unit">
                                    <p class="text-xs text-gray-400 mt-1" id="maxLabel">
                                        <?php if ($source_barang): ?>Maksimal: <?= $source_barang['jumlah'] ?> unit<?php endif; ?>
                                    </p>
                                </div>

                                <!-- Lokasi Tujuan -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Lokasi Tujuan <span class="text-red-500">*</span>
                                    </label>
                                    <select name="lokasi_tujuan_id" id="lokasiTujuan" required
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500">
                                        <option value="">-- Pilih Lokasi Tujuan --</option>
                                        <?php foreach ($lokasiList as $l): ?>
                                            <option value="<?= $l['id'] ?>" <?= (($_POST['lokasi_tujuan_id'] ?? '') == $l['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($l['nama_lokasi']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr class="border-gray-100">

                        <!-- Metode & Data Tujuan -->
                        <div class="space-y-4" id="targetSection">
                            <label class="block text-sm font-medium text-gray-700">Metode Pemindahan</label>
                            
                            <div class="flex flex-col sm:flex-row gap-4">
                                <!-- Radio Opsi Baru -->
                                <label class="flex-1 p-4 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-2xl cursor-pointer flex items-start gap-3 transition-all">
                                    <input type="radio" name="metode" value="baru" checked id="radioBaru"
                                        class="mt-1 text-catalina-600 focus:ring-catalina-500 border-gray-300">
                                    <div>
                                        <p class="font-semibold text-gray-800 text-sm">Buat Barang Baru di Lokasi Tujuan</p>
                                        <p class="text-xs text-gray-500 mt-1">Stok akan dipisah dan didaftarkan sebagai baris barang baru dengan kode unik tersendiri.</p>
                                    </div>
                                </label>

                                <!-- Radio Opsi Gabung -->
                                <label id="labelGabung" class="flex-1 p-4 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-2xl cursor-pointer flex items-start gap-3 transition-all">
                                    <input type="radio" name="metode" value="gabung" id="radioGabung"
                                        class="mt-1 text-catalina-600 focus:ring-catalina-500 border-gray-300">
                                    <div>
                                        <p class="font-semibold text-gray-800 text-sm">Gabung Dengan Barang Yang Sudah Ada</p>
                                        <p class="text-xs text-gray-500 mt-1">Stok akan ditambahkan ke data barang sejenis yang sudah terdaftar di lokasi tujuan.</p>
                                    </div>
                                </label>
                            </div>

                            <!-- Bidang Input Khusus Metode Buat Baru -->
                            <div id="fieldMetodeBaru" class="bg-gray-50/50 p-4 border border-gray-200 rounded-2xl space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Kode Barang Baru <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="new_kode_barang" id="newKodeBarang"
                                        value="<?= htmlspecialchars($_POST['new_kode_barang'] ?? '') ?>"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                                        placeholder="Contoh: INV-001-B">
                                    <p class="text-xs text-gray-400 mt-1">Kode barang ini harus unik dan belum terdaftar di sistem.</p>
                                </div>
                            </div>

                            <!-- Bidang Input Khusus Metode Gabung -->
                            <div id="fieldMetodeGabung" class="hidden bg-gray-50/50 p-4 border border-gray-200 rounded-2xl space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Pilih Barang di Lokasi Tujuan <span class="text-red-500">*</span>
                                    </label>
                                    <select name="target_barang_id" id="targetBarangDropdown"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500">
                                        <option value="">-- Pilih Barang Penerima --</option>
                                    </select>
                                    <p class="text-xs text-gray-400 mt-1">Stok barang terpilih di lokasi tujuan akan bertambah setelah pemindahan selesai.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Tombol Aksi -->
                        <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
                            <button type="submit"
                                class="px-6 py-2.5 bg-catalina-600 hover:bg-catalina-700 text-white rounded-xl font-medium transition-all flex items-center gap-2">
                                <i class="fas fa-save"></i>Proses Pemindahan
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

    <!-- Data dari Backend untuk JS -->
    <script>
        const isSourcePreselected = <?= $source_barang ? 'true' : 'false' ?>;
        const preselectedSourceLocId = <?= $source_barang ? intval($source_barang['lokasi_id']) : 'null' ?>;
        const preselectedSourceStock = <?= $source_barang ? intval($source_barang['jumlah']) : '0' ?>;
        const preselectedSourceCode = "<?= $source_barang ? htmlspecialchars($source_barang['kode_barang']) : '' ?>";

        const sourceList = <?= json_encode($sourceList) ?>;
        const allBarang = <?= json_encode($allBarang) ?>;
        const lokasiList = <?= json_encode($lokasiList) ?>;

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('sidebar-hidden');
            document.getElementById('overlay').classList.toggle('hidden');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const sourceSelector = document.getElementById('sourceSelector');
            const sourceDetails = document.getElementById('sourceDetails');
            const sourceLocName = document.getElementById('sourceLocName');
            const sourceStockVal = document.getElementById('sourceStockVal');
            
            const jumlahPindah = document.getElementById('jumlahPindah');
            const maxLabel = document.getElementById('maxLabel');
            const lokasiTujuan = document.getElementById('lokasiTujuan');
            
            const radioBaru = document.getElementById('radioBaru');
            const radioGabung = document.getElementById('radioGabung');
            const labelGabung = document.getElementById('labelGabung');
            
            const fieldMetodeBaru = document.getElementById('fieldMetodeBaru');
            const fieldMetodeGabung = document.getElementById('fieldMetodeGabung');
            
            const newKodeBarang = document.getElementById('newKodeBarang');
            const targetBarangDropdown = document.getElementById('targetBarangDropdown');

            // Set state awal
            let currentSelectedSource = null;

            if (isSourcePreselected) {
                currentSelectedSource = {
                    id: <?= $source_id ? intval($source_id) : 'null' ?>,
                    lokasi_id: preselectedSourceLocId,
                    jumlah: preselectedSourceStock,
                    kode_barang: preselectedSourceCode
                };
                filterTargetLocations(preselectedSourceLocId);
            }

            // Handler ganti barang sumber (jika tidak dipreselect)
            if (sourceSelector) {
                sourceSelector.addEventListener('change', function() {
                    const id = parseInt(this.value);
                    if (!id) {
                        currentSelectedSource = null;
                        sourceDetails.classList.add('hidden');
                        jumlahPindah.max = '';
                        maxLabel.textContent = '';
                        filterTargetLocations(null);
                        return;
                    }

                    // Cari barang terpilih
                    const item = sourceList.find(x => x.id === id);
                    if (item) {
                        currentSelectedSource = item;
                        sourceLocName.textContent = item.nama_lokasi || 'Tanpa Lokasi';
                        sourceStockVal.textContent = item.jumlah;
                        sourceDetails.classList.remove('hidden');
                        
                        jumlahPindah.max = item.jumlah;
                        maxLabel.textContent = `Maksimal: ${item.jumlah} unit`;
                        
                        // Isi default kode barang baru (saran)
                        newKodeBarang.value = `${item.kode_barang}-PND`;

                        // Filter lokasi tujuan (jangan tampilkan lokasi saat ini)
                        filterTargetLocations(item.lokasi_id);
                    }
                });
            } else {
                // Pre-fill default saran kode barang baru
                if (isSourcePreselected) {
                    newKodeBarang.value = `${preselectedSourceCode}-PND`;
                }
            }

            // Membatasi pilihan lokasi tujuan
            function filterTargetLocations(currentLocId) {
                const options = lokasiTujuan.options;
                for (let i = 0; i < options.length; i++) {
                    const optionVal = parseInt(options[i].value);
                    if (!optionVal) continue;
                    
                    if (currentLocId && optionVal === parseInt(currentLocId)) {
                        options[i].disabled = true;
                        options[i].style.display = 'none';
                        if (lokasiTujuan.value == optionVal) {
                            lokasiTujuan.value = '';
                        }
                    } else {
                        options[i].disabled = false;
                        options[i].style.display = '';
                    }
                }
                updateTargetOptions();
            }

            // Handler ganti lokasi tujuan
            lokasiTujuan.addEventListener('change', function() {
                updateTargetOptions();
            });

            // Update pilihan penggabungan barang di lokasi tujuan
            function updateTargetOptions() {
                const destLocId = parseInt(lokasiTujuan.value);
                
                // Bersihkan dropdown barang penerima
                targetBarangDropdown.innerHTML = '<option value="">-- Pilih Barang Penerima --</option>';
                
                if (!destLocId) {
                    radioGabung.disabled = true;
                    labelGabung.classList.add('opacity-50', 'cursor-not-allowed');
                    labelGabung.classList.remove('hover:bg-gray-100');
                    radioBaru.click();
                    return;
                }

                // Cari barang yang sudah ada di lokasi tujuan
                const availableTargets = allBarang.filter(x => x.lokasi_id === destLocId);

                if (availableTargets.length === 0) {
                    // Jika tidak ada barang di lokasi tujuan, matikan pilihan gabung
                    radioGabung.disabled = true;
                    labelGabung.classList.add('opacity-50', 'cursor-not-allowed');
                    labelGabung.classList.remove('hover:bg-gray-100');
                    radioBaru.click();
                } else {
                    // Aktifkan pilihan gabung
                    radioGabung.disabled = false;
                    labelGabung.classList.remove('opacity-50', 'cursor-not-allowed');
                    labelGabung.classList.add('hover:bg-gray-100');

                    // Isi dropdown barang penerima
                    availableTargets.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = `${item.nama_barang} (${item.kode_barang} - Stok: ${item.jumlah})`;
                        targetBarangDropdown.appendChild(option);
                    });
                }
            }

            // Handler perubahan radio button metode
            radioBaru.addEventListener('change', function() {
                if (this.checked) {
                    fieldMetodeBaru.classList.remove('hidden');
                    fieldMetodeGabung.classList.add('hidden');
                    newKodeBarang.required = true;
                    targetBarangDropdown.required = false;
                }
            });

            radioGabung.addEventListener('change', function() {
                if (this.checked) {
                    fieldMetodeBaru.classList.add('hidden');
                    fieldMetodeGabung.classList.remove('hidden');
                    newKodeBarang.required = false;
                    targetBarangDropdown.required = true;
                }
            });

            // Set required defaults
            newKodeBarang.required = true;

            // Validasi submit sisi klien
            document.getElementById('transferForm').addEventListener('submit', function(e) {
                const qty = parseInt(jumlahPindah.value);
                const maxQty = currentSelectedSource ? parseInt(currentSelectedSource.jumlah) : 0;
                
                if (currentSelectedSource && qty > maxQty) {
                    e.preventDefault();
                    alert(`Jumlah pemindahan (${qty}) melebihi stok yang tersedia (${maxQty})!`);
                    return;
                }

                if (currentSelectedSource && parseInt(lokasiTujuan.value) === parseInt(currentSelectedSource.lokasi_id)) {
                    e.preventDefault();
                    alert('Lokasi tujuan tidak boleh sama dengan lokasi asal!');
                    return;
                }
            });
        });
    </script>
</body>

</html>
