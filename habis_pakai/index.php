<?php
/**
 * Barang Habis Pakai - Riwayat Distribusi
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(['admin', 'petugas']);

$user = currentUser();
$pageTitle = 'Barang Habis Pakai';

// Handle delete
$error = $success = '';
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $pdo = db();
        $pdo->beginTransaction();

        // Get distribusi data to restore stock
        $stmt = $pdo->prepare("SELECT barang_id, jumlah FROM distribusi_habis_pakai WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $dist = $stmt->fetch();

        if ($dist) {
            // Restore stock
            $stmt = $pdo->prepare("UPDATE barang SET jumlah = jumlah + ? WHERE id = ?");
            $stmt->execute([$dist['jumlah'], $dist['barang_id']]);

            // Delete record
            $stmt = $pdo->prepare("DELETE FROM distribusi_habis_pakai WHERE id = ?");
            $stmt->execute([$_GET['delete']]);

            $pdo->commit();
            $success = 'Distribusi berhasil dihapus dan stok dikembalikan!';
        } else {
            $pdo->rollBack();
            $error = 'Data distribusi tidak ditemukan!';
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        $error = 'Gagal menghapus distribusi!';
    }
}

// Filter
$search = $_GET['search'] ?? '';
$filterKategori = $_GET['kategori'] ?? '';

// Get data
try {
    $pdo = db();

    $pendingPeminjaman = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'pending'")->fetchColumn();
    $kategoriList = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();

    // Build query
    $where = [];
    $params = [];

    if ($search) {
        $where[] = "(d.penerima LIKE ? OR b.nama_barang LIKE ? OR b.kode_barang LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($filterKategori) {
        $where[] = "b.kategori_id = ?";
        $params[] = $filterKategori;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT d.*, b.nama_barang, b.kode_barang, k.nama_kategori, u.nama as nama_distributor
            FROM distribusi_habis_pakai d
            JOIN barang b ON d.barang_id = b.id
            LEFT JOIN kategori k ON b.kategori_id = k.id
            JOIN users u ON d.user_id = u.id
            $whereClause
            ORDER BY d.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $distribusiList = $stmt->fetchAll();

    // Stats
    $totalDistribusi = count($distribusiList);
    $totalUnitDistribusi = array_sum(array_column($distribusiList, 'jumlah'));

} catch (Exception $e) {
    $distribusiList = [];
    $kategoriList = [];
    $totalDistribusi = $totalUnitDistribusi = 0;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $pageTitle ?> -
        <?= APP_NAME ?>
    </title>
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
                        class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-all">
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
                        class="flex items-center gap-3 px-4 py-3 rounded-xl bg-catalina-50 text-catalina-700 font-medium hover:bg-gray-100 hover:text-catalina-700 transition-all">
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
                            <span class="px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded-full animate-pulse">
                                <?= $pendingPeminjaman ?>
                            </span>
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

        <main class="flex-1 lg:ml-64 min-w-0">
            <header class="bg-white shadow-sm sticky top-0 z-10 border-b border-gray-200">
                <div class="flex items-center justify-between px-4 lg:px-6 py-4">
                    <div class="flex items-center gap-3">
                        <button onclick="toggleSidebar()"
                            class="lg:hidden p-2 -ml-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        <div>
                            <h1 class="text-lg lg:text-xl font-bold text-gray-800">
                                <?= $pageTitle ?>
                            </h1>
                            <p class="text-xs text-gray-500">Kelola distribusi barang habis pakai</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 lg:gap-4">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 bg-catalina-600 rounded-full flex items-center justify-center text-white font-semibold">
                                <?= strtoupper(substr($user['nama'], 0, 1)) ?>
                            </div>
                            <div class="hidden md:block">
                                <p class="text-sm font-medium text-gray-700">
                                    <?= htmlspecialchars($user['nama']) ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?= getRoleDisplayName($user['role']) ?>
                                </p>
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
                        <i class="fas fa-check-circle"></i>
                        <?= $success ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div
                        class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 flex items-center gap-2">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-white rounded-xl p-4 border border-gray-200 text-center">
                        <p class="text-2xl font-bold text-gray-800">
                            <?= $totalDistribusi ?>
                        </p>
                        <p class="text-xs text-gray-500">Total Distribusi</p>
                    </div>
                    <div class="bg-white rounded-xl p-4 border border-gray-200 text-center">
                        <p class="text-2xl font-bold text-catalina-600">
                            <?= $totalUnitDistribusi ?>
                        </p>
                        <p class="text-xs text-gray-500">Total Unit Keluar</p>
                    </div>
                </div>

                <!-- Header Actions -->
                <div class="bg-white rounded-2xl p-4 lg:p-6 border border-gray-200 mb-6">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <a href="distribusi.php"
                            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-catalina-600 hover:bg-catalina-700 text-white rounded-xl font-medium transition-all">
                            <i class="fas fa-plus"></i>Distribusi Baru
                        </a>

                        <!-- Search & Filter -->
                        <form method="GET" class="flex flex-col sm:flex-row gap-3 flex-1 lg:max-w-2xl">
                            <div class="relative flex-1">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                    placeholder="Cari penerima/barang..."
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
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">No
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                                        Tanggal</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Barang
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">
                                        Kategori</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                                        Penerima</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">
                                        Jumlah</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden lg:table-cell">
                                        Keperluan</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($distribusiList)): ?>
                                    <tr>
                                        <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                                            <i class="fas fa-boxes-packing text-4xl mb-3"></i>
                                            <p>Belum ada distribusi barang habis pakai</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; ?>
                                    <?php foreach ($distribusiList as $d): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 text-center text-gray-600">
                                                <?= $no++ ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-600">
                                                <?= date('d M Y', strtotime($d['tanggal_distribusi'])) ?>
                                            </td>
                                            <td class="px-4 py-3">
                                                <p class="font-medium text-gray-800">
                                                    <?= htmlspecialchars($d['nama_barang']) ?>
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    <?= htmlspecialchars($d['kode_barang']) ?>
                                                </p>
                                            </td>
                                            <td class="px-4 py-3 text-gray-600 hidden md:table-cell">
                                                <?= htmlspecialchars($d['nama_kategori'] ?? '-') ?>
                                            </td>
                                            <td class="px-4 py-3">
                                                <p class="font-medium text-gray-800">
                                                    <?= htmlspecialchars($d['penerima']) ?>
                                                </p>
                                                <p class="text-xs text-gray-500">oleh
                                                    <?= htmlspecialchars($d['nama_distributor']) ?>
                                                </p>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span
                                                    class="px-2 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-medium">
                                                    <?= $d['jumlah'] ?> unit
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-600 hidden lg:table-cell">
                                                <?= htmlspecialchars($d['keperluan'] ?? '-') ?>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <button
                                                    onclick="confirmDelete(<?= $d['id'] ?>, '<?= htmlspecialchars($d['nama_barang']) ?>')"
                                                    class="p-2 text-red-500 hover:bg-red-50 rounded-lg" title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 text-sm text-gray-500">
                        Total: <strong>
                            <?= $totalDistribusi ?>
                        </strong> distribusi
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-trash text-2xl text-red-500"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-800 mb-2">Hapus Distribusi?</h3>
            <p class="text-gray-500 mb-2">Anda yakin ingin menghapus distribusi <strong id="deleteItemName"></strong>?
            </p>
            <p class="text-sm text-amber-600 mb-6"><i class="fas fa-info-circle mr-1"></i>Stok barang akan dikembalikan.
            </p>
            <div class="flex gap-3">
                <button onclick="closeDeleteModal()"
                    class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition-all">Batal</button>
                <a id="deleteLink" href="#"
                    class="flex-1 px-4 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-xl font-medium transition-all text-center">Hapus</a>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('sidebar-hidden');
            document.getElementById('overlay').classList.toggle('hidden');
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

        document.getElementById('deleteModal').addEventListener('click', function (e) {
            if (e.target === this) closeDeleteModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeDeleteModal();
        });
    </script>
</body>

</html>