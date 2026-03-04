<?php
/**
 * Laporan Barang - Untuk Admin/Pimpinan
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(['admin', 'pimpinan']);

$user = currentUser();
$pageTitle = 'Laporan Barang';

// Filter
$filterKategori = $_GET['kategori'] ?? '';
$filterKondisi = $_GET['kondisi_id'] ?? '';
$filterLokasi = $_GET['lokasi'] ?? '';

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    try {
        $pdo = db();

        $where = [];
        $params = [];
        if ($filterKategori) {
            $where[] = "b.kategori_id = ?";
            $params[] = $filterKategori;
        }
        if ($filterKondisi) {
            $where[] = "b.kondisi_id = ?";
            $params[] = $filterKondisi;
        }
        if ($filterLokasi) {
            $where[] = "b.lokasi_id = ?";
            $params[] = $filterLokasi;
        }
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT b.kode_barang, b.nama_barang, k.nama_kategori, l.nama_lokasi, b.jumlah, kd.nama_kondisi, b.keterangan
                FROM barang b 
                LEFT JOIN kategori k ON b.kategori_id = k.id 
                LEFT JOIN lokasi l ON b.lokasi_id = l.id 
                LEFT JOIN kondisi kd ON b.kondisi_id = kd.id 
                $whereClause ORDER BY b.nama_barang";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=laporan_barang_' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM for Excel
        fputcsv($output, ['Kode Barang', 'Nama Barang', 'Kategori', 'Lokasi', 'Jumlah', 'Kondisi', 'Keterangan']);

        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    } catch (Exception $e) {
        $error = 'Gagal export data!';
    }
}

// Get data
try {
    $pdo = db();

    // Get pending peminjaman count for notification badge
    $pendingPeminjaman = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'pending'")->fetchColumn();

    $kategoriList = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();
    $lokasiList = $pdo->query("SELECT * FROM lokasi ORDER BY nama_lokasi")->fetchAll();
    $kondisiList = $pdo->query("SELECT * FROM kondisi ORDER BY nama_kondisi")->fetchAll();

    $where = [];
    $params = [];
    if ($filterKategori) {
        $where[] = "b.kategori_id = ?";
        $params[] = $filterKategori;
    }
    if ($filterKondisi) {
        $where[] = "b.kondisi_id = ?";
        $params[] = $filterKondisi;
    }
    if ($filterLokasi) {
        $where[] = "b.lokasi_id = ?";
        $params[] = $filterLokasi;
    }
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT b.*, k.nama_kategori, l.nama_lokasi, kd.nama_kondisi 
            FROM barang b 
            LEFT JOIN kategori k ON b.kategori_id = k.id 
            LEFT JOIN lokasi l ON b.lokasi_id = l.id 
            LEFT JOIN kondisi kd ON b.kondisi_id = kd.id 
            $whereClause ORDER BY b.nama_barang";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $barangList = $stmt->fetchAll();

    // Stats
    $totalBarang = count($barangList);
    $totalUnit = array_sum(array_column($barangList, 'jumlah'));
    $kondisiStats = [];
    foreach ($barangList as $b) {
        $namaKondisi = $b['nama_kondisi'] ?? 'Tidak Diketahui';
        if (!isset($kondisiStats[$namaKondisi])) {
            $kondisiStats[$namaKondisi] = 0;
        }
        $kondisiStats[$namaKondisi]++;
    }

} catch (Exception $e) {
    $barangList = [];
    $kategoriList = [];
    $lokasiList = [];
    $totalBarang = $totalUnit = 0;
    $kondisiStats = [];
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

        @media print {

            #sidebar,
            header,
            .no-print {
                display: none !important;
            }

            main {
                margin-left: 0 !important;
            }

            .print-full {
                width: 100% !important;
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
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all bg-catalina-50 text-catalina-700 font-medium hover:bg-gray-100 hover:text-catalina-700 transition-all">
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
                    class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                    <i class="fas fa-user-cog w-5"></i>
                    <span>Profil Saya</span>
                </a>
            </nav>
        </aside>

        <main class="flex-1 lg:ml-64 min-w-0 print-full">
            <header class="bg-white shadow-sm sticky top-0 z-10 border-b border-gray-200 no-print">
                <div class="flex items-center justify-between px-4 lg:px-6 py-4">
                    <div class="flex items-center gap-3">
                        <button onclick="toggleSidebar()"
                            class="lg:hidden p-2 -ml-2 text-gray-600 hover:bg-gray-100 rounded-lg"><i
                                class="fas fa-bars text-xl"></i></button>
                        <div>
                            <h1 class="text-lg lg:text-xl font-bold text-gray-800"><?= $pageTitle ?></h1>
                            <p class="text-xs text-gray-500">Laporan data inventaris barang</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>"
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-xl text-sm font-medium">
                            <i class="fas fa-file-excel mr-2"></i>Export Excel
                        </a>
                        <button onclick="window.print()"
                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-sm font-medium">
                            <i class="fas fa-print mr-2"></i>Cetak
                        </button>
                    </div>
                </div>
            </header>

            <div class="p-4 lg:p-6">
                <!-- Filter -->
                <div class="bg-white rounded-2xl p-4 border border-gray-200 mb-6 no-print">
                    <form method="GET" class="flex flex-wrap gap-3">
                        <select name="kategori" class="px-4 py-2 border border-gray-200 rounded-xl text-sm">
                            <option value="">Semua Kategori</option>
                            <?php foreach ($kategoriList as $k): ?>
                                <option value="<?= $k['id'] ?>" <?= $filterKategori == $k['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($k['nama_kategori']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="kondisi_id" class="px-4 py-2 border border-gray-200 rounded-xl text-sm">
                            <option value="">Semua Kondisi</option>
                            <?php foreach ($kondisiList as $kd): ?>
                                <option value="<?= $kd['id'] ?>" <?= $filterKondisi == $kd['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kd['nama_kondisi']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="lokasi" class="px-4 py-2 border border-gray-200 rounded-xl text-sm">
                            <option value="">Semua Lokasi</option>
                            <?php foreach ($lokasiList as $l): ?>
                                <option value="<?= $l['id'] ?>" <?= $filterLokasi == $l['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($l['nama_lokasi']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit"
                            class="px-4 py-2 bg-catalina-600 hover:bg-catalina-700 text-white rounded-xl text-sm font-medium">Filter</button>
                        <?php if ($filterKategori || $filterKondisi || $filterLokasi): ?>
                            <a href="barang.php"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-sm font-medium">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-2 lg:grid-cols-<?= 2 + count($kondisiStats) ?> gap-4 mb-6">
                    <div class="bg-white rounded-xl p-4 border border-gray-200 text-center">
                        <p class="text-2xl font-bold text-gray-800"><?= $totalBarang ?></p>
                        <p class="text-xs text-gray-500">Total Jenis</p>
                    </div>
                    <div class="bg-white rounded-xl p-4 border border-gray-200 text-center">
                        <p class="text-2xl font-bold text-catalina-600"><?= $totalUnit ?></p>
                        <p class="text-xs text-gray-500">Total Unit</p>
                    </div>
                    <?php foreach ($kondisiStats as $namaKondisi => $jumlah): ?>
                        <div class="bg-white rounded-xl p-4 border border-gray-200 text-center">
                            <p class="text-2xl font-bold text-gray-700"><?= $jumlah ?></p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($namaKondisi) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Print Header -->
                <div class="hidden print:block mb-6 text-center">
                    <h2 class="text-xl font-bold">LAPORAN DATA BARANG INVENTARIS</h2>
                    <p class="text-sm">Dinas Sosial Provinsi Lampung</p>
                    <p class="text-xs text-gray-500">Dicetak: <?= date('d F Y H:i') ?></p>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">No
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Kode
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Nama
                                        Barang</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                                        Kategori</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Lokasi
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">
                                        Jumlah</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">
                                        Kondisi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($barangList)): ?>
                                    <tr>
                                        <td colspan="7" class="px-4 py-12 text-center text-gray-400">Tidak ada data</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($barangList as $i => $b): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 text-gray-500"><?= $i + 1 ?></td>
                                            <td class="px-4 py-3 font-mono text-sm text-catalina-600">
                                                <?= htmlspecialchars($b['kode_barang']) ?>
                                            </td>
                                            <td class="px-4 py-3 font-medium text-gray-800">
                                                <?= htmlspecialchars($b['nama_barang']) ?>
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                <?= htmlspecialchars($b['nama_kategori'] ?? '-') ?>
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                <?= htmlspecialchars($b['nama_lokasi'] ?? '-') ?>
                                            </td>
                                            <td class="px-4 py-3 text-center font-medium"><?= $b['jumlah'] ?></td>
                                            <td class="px-4 py-3 text-center">
                                                <?= htmlspecialchars($b['nama_kondisi'] ?? '-') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>function toggleSidebar() { document.getElementById('sidebar').classList.toggle('sidebar-hidden'); document.getElementById('overlay').classList.toggle('hidden'); }</script>
</body>

</html>