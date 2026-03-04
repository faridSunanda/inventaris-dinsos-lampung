<?php
/**
 * Distribusi Barang Habis Pakai - Form
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(['admin', 'petugas']);

$user = currentUser();
$pageTitle = 'Distribusi Barang Habis Pakai';

// Get data for dropdowns
try {
    $pdo = db();
    $kategoriList = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();
    $barangList = $pdo->query("SELECT b.*, k.nama_kategori FROM barang b LEFT JOIN kategori k ON b.kategori_id = k.id WHERE b.jumlah > 0 ORDER BY b.nama_barang")->fetchAll();
    $pendingPeminjaman = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'pending'")->fetchColumn();
} catch (Exception $e) {
    $kategoriList = [];
    $barangList = [];
}

// Handle form submit
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barang_id = $_POST['barang_id'] ?? null;
    $penerima = trim($_POST['penerima'] ?? '');
    $jumlah = (int) ($_POST['jumlah'] ?? 1);
    $tanggal = $_POST['tanggal_distribusi'] ?? date('Y-m-d');
    $keperluan = trim($_POST['keperluan'] ?? '');

    if (empty($barang_id) || empty($penerima) || $jumlah < 1) {
        $error = 'Barang, penerima, dan jumlah wajib diisi!';
    } else {
        try {
            $pdo->beginTransaction();

            // Check stock
            $stmt = $pdo->prepare("SELECT jumlah, nama_barang FROM barang WHERE id = ?");
            $stmt->execute([$barang_id]);
            $barang = $stmt->fetch();

            if (!$barang) {
                $error = 'Barang tidak ditemukan!';
                $pdo->rollBack();
            } elseif ($barang['jumlah'] < $jumlah) {
                $error = 'Stok tidak cukup! Stok tersedia: ' . $barang['jumlah'] . ' unit.';
                $pdo->rollBack();
            } else {
                // Insert distribusi
                $stmt = $pdo->prepare("INSERT INTO distribusi_habis_pakai (barang_id, user_id, penerima, jumlah, keperluan, tanggal_distribusi) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$barang_id, $user['id'], $penerima, $jumlah, $keperluan, $tanggal]);

                // Decrease stock
                $stmt = $pdo->prepare("UPDATE barang SET jumlah = jumlah - ? WHERE id = ?");
                $stmt->execute([$jumlah, $barang_id]);

                $pdo->commit();
                $success = 'Distribusi ' . htmlspecialchars($barang['nama_barang']) . ' sebanyak ' . $jumlah . ' unit ke ' . htmlspecialchars($penerima) . ' berhasil dicatat!';

                // Refresh barang list
                $barangList = $pdo->query("SELECT b.*, k.nama_kategori FROM barang b LEFT JOIN kategori k ON b.kategori_id = k.id WHERE b.jumlah > 0 ORDER BY b.nama_barang")->fetchAll();
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction())
                $pdo->rollBack();
            $error = 'Gagal mencatat distribusi!';
        }
    }
}

// Prepare barang data as JSON for JS filtering
$barangJson = json_encode(array_map(function ($b) {
    return [
        'id' => $b['id'],
        'nama' => $b['nama_barang'],
        'kode' => $b['kode_barang'],
        'kategori_id' => $b['kategori_id'],
        'kategori' => $b['nama_kategori'] ?? 'Tanpa Kategori',
        'jumlah' => $b['jumlah']
    ];
}, $barangList));
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
                            <p class="text-xs text-gray-500">Distribusikan barang habis pakai ke penerima</p>
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
                <!-- Breadcrumb -->
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
                    <a href="index.php" class="hover:text-catalina-600">Barang Habis Pakai</a>
                    <i class="fas fa-chevron-right text-xs"></i>
                    <span class="text-gray-800">Distribusi Baru</span>
                </div>

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

                <!-- Form -->
                <div class="bg-white rounded-2xl p-6 border border-gray-200">
                    <form method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Pilih Kategori (filter) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Filter Kategori
                                </label>
                                <select id="filterKategori"
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                                    onchange="filterBarang()">
                                    <option value="">-- Semua Kategori --</option>
                                    <?php foreach ($kategoriList as $k): ?>
                                        <option value="<?= $k['id'] ?>">
                                            <?= htmlspecialchars($k['nama_kategori']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-gray-400 mt-1">Pilih kategori untuk memfilter daftar barang</p>
                            </div>

                            <!-- Pilih Barang -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Pilih Barang <span class="text-red-500">*</span>
                                </label>
                                <select name="barang_id" id="selectBarang" required
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                                    onchange="updateMaxJumlah()">
                                    <option value="">-- Pilih Barang --</option>
                                    <?php foreach ($barangList as $b): ?>
                                        <option value="<?= $b['id'] ?>" data-kategori="<?= $b['kategori_id'] ?>"
                                            data-stok="<?= $b['jumlah'] ?>">
                                            <?= htmlspecialchars($b['nama_barang']) ?> (
                                            <?= htmlspecialchars($b['nama_kategori'] ?? 'Tanpa Kategori') ?>) - Stok:
                                            <?= $b['jumlah'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Jumlah -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Jumlah <span class="text-red-500">*</span>
                                </label>
                                <input type="number" name="jumlah" id="inputJumlah" value="1" min="1" required
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100">
                                <p class="text-xs text-gray-400 mt-1" id="stokInfo">Pilih barang untuk melihat stok
                                    tersedia</p>
                            </div>

                            <!-- Penerima -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Penerima <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="penerima" required
                                    value="<?= htmlspecialchars($_POST['penerima'] ?? '') ?>"
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                                    placeholder="Nama penerima barang">
                            </div>

                            <!-- Tanggal -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Tanggal Distribusi <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="tanggal_distribusi" required
                                    value="<?= $_POST['tanggal_distribusi'] ?? date('Y-m-d') ?>"
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100">
                            </div>
                        </div>

                        <!-- Keperluan -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Keperluan</label>
                            <textarea name="keperluan" rows="3"
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-catalina-500 focus:ring-2 focus:ring-catalina-100"
                                placeholder="Jelaskan keperluan distribusi (opsional)"><?= htmlspecialchars($_POST['keperluan'] ?? '') ?></textarea>
                        </div>

                        <!-- Buttons -->
                        <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
                            <button type="submit"
                                class="px-6 py-2.5 bg-catalina-600 hover:bg-catalina-700 text-white rounded-xl font-medium transition-all">
                                <i class="fas fa-paper-plane mr-2"></i>Distribusikan
                            </button>
                            <a href="index.php"
                                class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition-all">
                                Batal
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Info Box -->
                <div class="mt-6 bg-amber-50 border border-amber-200 rounded-2xl p-4">
                    <h4 class="font-semibold text-amber-800 mb-2"><i class="fas fa-info-circle mr-2"></i>Informasi</h4>
                    <ul class="text-sm text-amber-700 space-y-1">
                        <li>• Barang habis pakai adalah barang yang didistribusikan tanpa pengembalian</li>
                        <li>• Stok barang akan otomatis berkurang setelah distribusi</li>
                        <li>• Pilih kategori terlebih dahulu untuk memfilter daftar barang</li>
                        <li>• Jumlah distribusi tidak boleh melebihi stok yang tersedia</li>
                    </ul>
                </div>
            </div>
        </main>
    </div>

    <script>
        const allBarang = <?= $barangJson ?>;

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('sidebar-hidden');
            document.getElementById('overlay').classList.toggle('hidden');
        }

        function filterBarang() {
            const kategoriId = document.getElementById('filterKategori').value;
            const select = document.getElementById('selectBarang');
            const currentValue = select.value;

            // Clear options
            select.innerHTML = '<option value="">-- Pilih Barang --</option>';

            // Filter & rebuild
            const filtered = kategoriId
                ? allBarang.filter(b => b.kategori_id == kategoriId)
                : allBarang;

            filtered.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.id;
                opt.dataset.kategori = b.kategori_id;
                opt.dataset.stok = b.jumlah;
                opt.textContent = `${b.nama} (${b.kategori}) - Stok: ${b.jumlah}`;
                if (b.id == currentValue) opt.selected = true;
                select.appendChild(opt);
            });

            updateMaxJumlah();
        }

        function updateMaxJumlah() {
            const select = document.getElementById('selectBarang');
            const input = document.getElementById('inputJumlah');
            const info = document.getElementById('stokInfo');
            const selected = select.options[select.selectedIndex];

            if (selected && selected.value) {
                const stok = parseInt(selected.dataset.stok);
                input.max = stok;
                if (parseInt(input.value) > stok) input.value = stok;
                info.textContent = `Stok tersedia: ${stok} unit`;
                info.className = 'text-xs text-emerald-600 mt-1 font-medium';
            } else {
                input.removeAttribute('max');
                info.textContent = 'Pilih barang untuk melihat stok tersedia';
                info.className = 'text-xs text-gray-400 mt-1';
            }
        }
    </script>
</body>

</html>