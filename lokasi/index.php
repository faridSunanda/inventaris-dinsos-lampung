<?php
/**
 * Lokasi - List All
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(['admin', 'petugas']);

$user = currentUser();
$pageTitle = 'Lokasi';

// Handle actions
$error = $success = '';
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("DELETE FROM lokasi WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $success = 'Lokasi berhasil dihapus!';
    } catch (Exception $e) {
        $error = 'Gagal menghapus lokasi!';
    }
}

// Handle add/edit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $nama = trim($_POST['nama_lokasi'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    if (empty($nama)) {
        $error = 'Nama lokasi wajib diisi!';
    } else {
        try {
            $pdo = db();
            if ($id) {
                $stmt = $pdo->prepare("UPDATE lokasi SET nama_lokasi = ?, deskripsi = ? WHERE id = ?");
                $stmt->execute([$nama, $deskripsi, $id]);
                $success = 'Lokasi berhasil diupdate!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO lokasi (nama_lokasi, deskripsi) VALUES (?, ?)");
                $stmt->execute([$nama, $deskripsi]);
                $success = 'Lokasi berhasil ditambahkan!';
            }
        } catch (Exception $e) {
            $error = 'Gagal menyimpan lokasi!';
        }
    }
}

// Get data
try {
    $pdo = db();
    $pendingPeminjaman = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'pending'")->fetchColumn();
    $lokasiList = $pdo->query("SELECT l.*, COUNT(b.id) as jumlah_barang FROM lokasi l LEFT JOIN barang b ON l.id = b.lokasi_id GROUP BY l.id ORDER BY l.nama_lokasi")->fetchAll();
} catch (Exception $e) {
    $lokasiList = [];
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
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all bg-catalina-50 text-catalina-700 font-medium hover:bg-gray-100 hover:text-catalina-700 transition-all">
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

        <main class="flex-1 lg:ml-64 min-w-0">
            <header class="bg-white shadow-sm sticky top-0 z-10 border-b border-gray-200">
                <div class="flex items-center justify-between px-4 lg:px-6 py-4">
                    <div class="flex items-center gap-3">
                        <button onclick="toggleSidebar()"
                            class="lg:hidden p-2 -ml-2 text-gray-600 hover:bg-gray-100 rounded-lg"><i
                                class="fas fa-bars text-xl"></i></button>
                        <div>
                            <h1 class="text-lg lg:text-xl font-bold text-gray-800"><?= $pageTitle ?></h1>
                            <p class="text-xs text-gray-500">Kelola lokasi penyimpanan barang</p>
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
                            <h3 class="font-semibold text-gray-800">Daftar Lokasi</h3>
                            <p class="text-sm text-gray-500">Total <?= count($lokasiList) ?> lokasi</p>
                        </div>
                        <button onclick="openModal()"
                            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-catalina-600 hover:bg-catalina-700 text-white rounded-xl font-medium transition-all">
                            <i class="fas fa-plus"></i>Tambah Lokasi
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Nama
                                        Lokasi</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">
                                        Deskripsi</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">
                                        Jumlah</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($lokasiList)): ?>
                                    <tr>
                                        <td colspan="4" class="px-4 py-12 text-center text-gray-400"><i
                                                class="fas fa-map-marker-alt text-4xl mb-3"></i>
                                            <p>Belum ada lokasi</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($lokasiList as $l): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-3">
                                                    <div
                                                        class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
                                                        <i class="fas fa-map-marker-alt text-purple-600"></i>
                                                    </div>
                                                    <span
                                                        class="font-medium text-gray-800"><?= htmlspecialchars($l['nama_lokasi']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-gray-600 hidden md:table-cell">
                                                <?= htmlspecialchars($l['deskripsi'] ?? '-') ?>
                                            </td>
                                            <td class="px-4 py-3 text-center"><span
                                                    class="px-2 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-medium"><?= $l['jumlah_barang'] ?>
                                                    barang</span></td>
                                            <td class="px-4 py-3 text-center">
                                                <button
                                                    onclick="editLokasi(<?= $l['id'] ?>, '<?= htmlspecialchars($l['nama_lokasi']) ?>', '<?= htmlspecialchars($l['deskripsi'] ?? '') ?>')"
                                                    class="p-2 text-catalina-600 hover:bg-catalina-50 rounded-lg"
                                                    title="Edit"><i class="fas fa-edit"></i></button>
                                                <button
                                                    onclick="confirmDelete(<?= $l['id'] ?>, '<?= htmlspecialchars($l['nama_lokasi']) ?>')"
                                                    class="p-2 text-red-500 hover:bg-red-50 rounded-lg" title="Hapus"><i
                                                        class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 text-sm text-gray-500">
                        Total: <strong><?= count($lokasiList) ?></strong> lokasi
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
                    <i class="fas fa-map-marker-alt text-purple-500 mr-2"></i>Tambah Lokasi
                </h3>
                <button onclick="closeModal()"
                    class="p-2 hover:bg-gray-100 rounded-lg text-gray-400 hover:text-gray-600 transition-all">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="lokasiForm">
                <input type="hidden" name="id" id="formId">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nama Lokasi <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="nama_lokasi" id="formNama" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                            placeholder="Masukkan nama lokasi">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
                        <textarea name="deskripsi" id="formDeskripsi" rows="3"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                            placeholder="Deskripsi lokasi (opsional)"></textarea>
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
            <h3 class="text-lg font-bold text-gray-800 mb-2">Hapus Lokasi?</h3>
            <p class="text-gray-500 mb-6">Anda yakin ingin menghapus <strong id="deleteItemName"></strong>?</p>
            <div class="flex gap-3">
                <button onclick="closeDeleteModal()"
                    class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition-all">Batal</button>
                <a id="deleteLink" href="#"
                    class="flex-1 px-4 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-xl font-medium transition-all">Hapus</a>
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
            document.getElementById('formTitle').innerHTML = '<i class="fas fa-map-marker-alt text-purple-500 mr-2"></i>Tambah Lokasi';
            document.getElementById('lokasiForm').reset();
            document.getElementById('formId').value = '';
            document.getElementById('formModal').classList.remove('hidden');
            document.getElementById('formModal').classList.add('flex');
            setTimeout(() => document.getElementById('formNama').focus(), 100);
        }

        function closeModal() {
            document.getElementById('formModal').classList.add('hidden');
            document.getElementById('formModal').classList.remove('flex');
        }

        function editLokasi(id, nama, deskripsi) {
            document.getElementById('formTitle').innerHTML = '<i class="fas fa-edit text-purple-500 mr-2"></i>Edit Lokasi';
            document.getElementById('formId').value = id;
            document.getElementById('formNama').value = nama;
            document.getElementById('formDeskripsi').value = deskripsi;
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

        // Close modals when clicking outside
        document.getElementById('formModal').addEventListener('click', function (e) {
            if (e.target === this) closeModal();
        });
        document.getElementById('deleteModal').addEventListener('click', function (e) {
            if (e.target === this) closeDeleteModal();
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal();
                closeDeleteModal();
            }
        });
    </script>
</body>

</html>