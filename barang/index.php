<?php
/**
 * Data Barang - List All
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(['admin', 'petugas']);

$user = currentUser();
$pageTitle = 'Data Barang';

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("DELETE FROM barang WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $success = 'Barang berhasil dihapus!';
    } catch (Exception $e) {
        $error = 'Gagal menghapus barang: ' . $e->getMessage();
    }
}

// Handle Add Stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah_stok') {
    $id = $_POST['barang_id'] ?? null;
    $tambahan = intval($_POST['jumlah_tambahan'] ?? 0);
    if ($id && $tambahan > 0) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("UPDATE barang SET jumlah = jumlah + ? WHERE id = ?");
            $stmt->execute([$tambahan, $id]);
            $success = "Stok berhasil ditambah!";
        } catch (Exception $e) {
            $error = "Gagal menambah stok: " . $e->getMessage();
        }
    }
}

// Get search & filter
$search = $_GET['search'] ?? '';
$filterKategori = $_GET['kategori'] ?? '';
$filterKondisi = $_GET['kondisi_id'] ?? '';

// Get data
try {
    $pdo = db();

    // Get pending peminjaman count for notification badge
    $pendingPeminjaman = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'pending'")->fetchColumn();

    // Get kategori & kondisi for filter
    $kategoriList = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();
    $kondisiList = $pdo->query("SELECT * FROM kondisi ORDER BY nama_kondisi")->fetchAll();

    // Build query
    $where = [];
    $params = [];

    if ($search) {
        $where[] = "(b.nama_barang LIKE ? OR b.kode_barang LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($filterKategori) {
        $where[] = "b.kategori_id = ?";
        $params[] = $filterKategori;
    }
    if ($filterKondisi) {
        $where[] = "b.kondisi_id = ?";
        $params[] = $filterKondisi;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT b.*, k.nama_kategori, l.nama_lokasi, kd.nama_kondisi 
            FROM barang b 
            LEFT JOIN kategori k ON b.kategori_id = k.id 
            LEFT JOIN lokasi l ON b.lokasi_id = l.id 
            LEFT JOIN kondisi kd ON b.kondisi_id = kd.id 
            $whereClause
            ORDER BY b.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $barangList = $stmt->fetchAll();

} catch (Exception $e) {
    $barangList = [];
    $kategoriList = [];
    $error = 'Gagal mengambil data: ' . $e->getMessage();
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
                    class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
                    <i class="fas fa-user-cog w-5"></i>
                    <span>Profil Saya</span>
                </a>
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
                            <p class="text-xs text-gray-500">Kelola data barang inventaris</p>
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
                <!-- Alert Messages -->
                <?php if (isset($success)): ?>
                    <div
                        class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-4 flex items-center gap-2">
                        <i class="fas fa-check-circle"></i><?= $success ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div
                        class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 flex items-center gap-2">
                        <i class="fas fa-exclamation-circle"></i><?= $error ?>
                    </div>
                <?php endif; ?>

                <!-- Header Actions -->
                <div class="bg-white rounded-2xl p-4 lg:p-6 border border-gray-200 mb-6">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <a href="tambah.php"
                            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-catalina-600 hover:bg-catalina-700 text-white rounded-xl font-medium transition-all">
                            <i class="fas fa-plus"></i>Tambah Barang
                        </a>

                        <!-- Search & Filter -->
                        <form method="GET" class="flex flex-col sm:flex-row gap-3 flex-1 lg:max-w-2xl">
                            <div class="relative flex-1">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                    placeholder="Cari barang..."
                                    class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100">
                            </div>
                            <select name="kategori"
                                class="px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500">
                                <option value="">Semua Kategori</option>
                                <?php foreach ($kategoriList as $k): ?>
                                    <option value="<?= $k['id'] ?>" <?= $filterKategori == $k['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($k['nama_kategori']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="kondisi_id"
                                class="px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500">
                                <option value="">Semua Kondisi</option>
                                <?php foreach ($kondisiList as $kd): ?>
                                    <option value="<?= $kd['id'] ?>" <?= $filterKondisi == $kd['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($kd['nama_kondisi']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit"
                                class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition-all">
                                <i class="fas fa-filter mr-2"></i>Filter
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Kode
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Nama
                                        Barang</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">
                                        Kategori</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden lg:table-cell">
                                        Lokasi</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">
                                        Jumlah</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">
                                        Kondisi</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($barangList)): ?>
                                    <tr>
                                        <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                                            <i class="fas fa-box-open text-4xl mb-3"></i>
                                            <p>Belum ada data barang</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($barangList as $b): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <span
                                                    class="font-mono text-sm text-catalina-600"><?= htmlspecialchars($b['kode_barang']) ?></span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <p class="font-medium text-gray-800"><?= htmlspecialchars($b['nama_barang']) ?>
                                                </p>
                                                <p class="text-xs text-gray-500 md:hidden">
                                                    <?= htmlspecialchars($b['nama_kategori'] ?? '-') ?>
                                                </p>
                                            </td>
                                            <td class="px-4 py-3 hidden md:table-cell text-gray-600">
                                                <?= htmlspecialchars($b['nama_kategori'] ?? '-') ?>
                                            </td>
                                            <td class="px-4 py-3 hidden lg:table-cell text-gray-600">
                                                <?= htmlspecialchars($b['nama_lokasi'] ?? '-') ?>
                                            </td>
                                            <td class="px-4 py-3 text-center font-medium"><?= $b['jumlah'] ?></td>
                                            <td class="px-4 py-3 text-center">
                                                <?= htmlspecialchars($b['nama_kondisi'] ?? '-') ?>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <div class="flex items-center justify-center gap-1">
                                                    <button
                                                        onclick="openStockModal(<?= $b['id'] ?>, '<?= htmlspecialchars($b['nama_barang']) ?>')"
                                                        class="p-2 text-green-600 hover:bg-green-50 rounded-lg"
                                                        title="Tambah Stok">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                    <a href="edit.php?id=<?= $b['id'] ?>"
                                                        class="p-2 text-catalina-600 hover:bg-catalina-50 rounded-lg"
                                                        title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button
                                                        onclick="confirmDelete(<?= $b['id'] ?>, '<?= htmlspecialchars($b['nama_barang']) ?>')"
                                                        class="p-2 text-red-500 hover:bg-red-50 rounded-lg" title="Hapus">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 text-sm text-gray-500">
                        Total: <strong><?= count($barangList) ?></strong> barang
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full">
            <div class="text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-trash text-2xl text-red-500"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800 mb-2">Hapus Barang?</h3>
                <p class="text-gray-500 mb-6">Anda yakin ingin menghapus <strong id="deleteItemName"></strong>?</p>
                <div class="flex gap-3">
                    <button onclick="closeDeleteModal()"
                        class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition-all">Batal</button>
                    <a id="deleteLink" href="#"
                        class="flex-1 px-4 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-xl font-medium transition-all text-center">Hapus</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Increase Modal -->
    <div id="stockModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-800"><i class="fas fa-plus text-green-500 mr-2"></i>Tambah Stok
                </h3>
                <button onclick="closeStockModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="tambah_stok">
                <input type="hidden" name="barang_id" id="stockBarangId">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Barang</label>
                        <p class="font-medium text-gray-800 bg-gray-50 p-3 rounded-xl border border-gray-100"
                            id="stockBarangName"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Jumlah Tambahan <span
                                class="text-red-500">*</span></label>
                        <input type="number" name="jumlah_tambahan" min="1" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                            placeholder="Contoh: 10">
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeStockModal()"
                        class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition-all">Batal</button>
                    <button type="submit"
                        class="flex-1 px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-xl font-medium transition-all shadow-lg shadow-green-100">Simpan
                        Stok</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('sidebar-hidden');
            overlay.classList.toggle('hidden');
            document.body.style.overflow = sidebar.classList.contains('sidebar-hidden') ? '' : 'hidden';
        }

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

        function openStockModal(id, name) {
            document.getElementById('stockBarangId').value = id;
            document.getElementById('stockBarangName').textContent = name;
            document.getElementById('stockModal').classList.remove('hidden');
            document.getElementById('stockModal').classList.add('flex');
        }

        function closeStockModal() {
            document.getElementById('stockModal').classList.add('hidden');
            document.getElementById('stockModal').classList.remove('flex');
        }
    </script>
</body>

</html>