<?php
/**
 * Kelola Peminjaman - Untuk Admin/Petugas
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(['admin', 'petugas']);

$user = currentUser();
$pageTitle = 'Kelola Peminjaman';

// Handle actions
$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $action = $_POST['action'] ?? '';

    if ($id && $action) {
        try {
            $pdo = db();
            if ($action === 'approve') {
                $stmt = $pdo->prepare("UPDATE peminjaman SET status = 'disetujui', approved_by = ?, approved_at = NOW() WHERE id = ?");
                $stmt->execute([$user['id'], $id]);
                $success = 'Peminjaman berhasil disetujui!';
            } elseif ($action === 'reject') {
                $stmt = $pdo->prepare("UPDATE peminjaman SET status = 'ditolak', approved_by = ?, approved_at = NOW() WHERE id = ?");
                $stmt->execute([$user['id'], $id]);
                $success = 'Peminjaman berhasil ditolak!';
            } elseif ($action === 'handover') {
                $pdo->beginTransaction();
                try {
                    // Update status peminjaman
                    $stmt = $pdo->prepare("UPDATE peminjaman SET status = 'dipinjam' WHERE id = ?");
                    $stmt->execute([$id]);

                    // Kurangi jumlah barang yang tersedia
                    $stmt = $pdo->prepare("UPDATE barang b 
                                          JOIN peminjaman p ON p.barang_id = b.id 
                                          SET b.jumlah = b.jumlah - 1 
                                          WHERE p.id = ?");
                    $stmt->execute([$id]);

                    $pdo->commit();
                    $success = 'Barang berhasil diserahkan!';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
        } catch (Exception $e) {
            $error = 'Gagal memproses: ' . $e->getMessage();
        }
    }
}

// Filter
$filterStatus = $_GET['status'] ?? '';

// Get data
try {
    $pdo = db();

    // Get pending count for notification
    $pendingPeminjaman = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'pending'")->fetchColumn();

    $where = $filterStatus ? "WHERE p.status = ?" : "";
    $params = $filterStatus ? [$filterStatus] : [];

    $sql = "SELECT p.*, u.nama as nama_peminjam, b.nama_barang, b.kode_barang, 
                   a.nama as nama_approver
            FROM peminjaman p 
            JOIN users u ON p.user_id = u.id 
            JOIN barang b ON p.barang_id = b.id 
            LEFT JOIN users a ON p.approved_by = a.id
            $where
            ORDER BY 
                CASE p.status 
                    WHEN 'pending' THEN 1 
                    WHEN 'disetujui' THEN 2 
                    WHEN 'dipinjam' THEN 3 
                    ELSE 4 
                END, 
                p.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $peminjamanList = $stmt->fetchAll();

    // Count by status
    $countPending = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'pending'")->fetchColumn();
    $countDisetujui = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'disetujui'")->fetchColumn();
    $countDipinjam = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam'")->fetchColumn();

} catch (Exception $e) {
    $peminjamanList = [];
    $countPending = $countDisetujui = $countDipinjam = 0;
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
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all bg-catalina-50 text-catalina-700 font-medium hover:bg-gray-100 hover:text-catalina-700 transition-all">
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
                            <p class="text-xs text-gray-500">Kelola permintaan peminjaman</p>
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

                <!-- Stats -->
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <a href="?status=pending"
                        class="bg-amber-50 border-2 <?= $filterStatus == 'pending' ? 'border-amber-500' : 'border-transparent' ?> rounded-xl p-4 text-center hover:border-amber-300 transition-all">
                        <p class="text-2xl font-bold text-amber-600"><?= $countPending ?></p>
                        <p class="text-xs text-amber-700">Menunggu</p>
                    </a>
                    <a href="?status=disetujui"
                        class="bg-blue-50 border-2 <?= $filterStatus == 'disetujui' ? 'border-blue-500' : 'border-transparent' ?> rounded-xl p-4 text-center hover:border-blue-300 transition-all">
                        <p class="text-2xl font-bold text-blue-600"><?= $countDisetujui ?></p>
                        <p class="text-xs text-blue-700">Disetujui</p>
                    </a>
                    <a href="?status=dipinjam"
                        class="bg-purple-50 border-2 <?= $filterStatus == 'dipinjam' ? 'border-purple-500' : 'border-transparent' ?> rounded-xl p-4 text-center hover:border-purple-300 transition-all">
                        <p class="text-2xl font-bold text-purple-600"><?= $countDipinjam ?></p>
                        <p class="text-xs text-purple-700">Dipinjam</p>
                    </a>
                </div>

                <?php if ($filterStatus): ?>
                    <div class="mb-4">
                        <a href="index.php"
                            class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times"></i>Reset Filter
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Table -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                                        Peminjam</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Barang
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">
                                        Tanggal</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">
                                        Status</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($peminjamanList)): ?>
                                    <tr>
                                        <td colspan="5" class="px-4 py-12 text-center text-gray-400">
                                            <i class="fas fa-inbox text-4xl mb-3"></i>
                                            <p>Tidak ada data peminjaman</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($peminjamanList as $p): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <p class="font-medium text-gray-800">
                                                    <?= htmlspecialchars($p['nama_peminjam']) ?>
                                                </p>
                                                <p class="text-xs text-gray-500"><?= htmlspecialchars($p['keperluan'] ?? '-') ?>
                                                </p>
                                            </td>
                                            <td class="px-4 py-3">
                                                <p class="font-medium text-gray-800"><?= htmlspecialchars($p['nama_barang']) ?>
                                                </p>
                                                <p class="text-xs text-gray-500"><?= htmlspecialchars($p['kode_barang']) ?></p>
                                            </td>
                                            <td class="px-4 py-3 hidden md:table-cell text-sm text-gray-600">
                                                <p><i
                                                        class="fas fa-calendar-alt mr-1 text-gray-400"></i><?= date('d M Y', strtotime($p['tanggal_pinjam'])) ?>
                                                </p>
                                                <p class="text-xs text-gray-500">s/d
                                                    <?= date('d M Y', strtotime($p['tanggal_kembali_rencana'])) ?>
                                                </p>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <?php
                                                $statusClass = match ($p['status']) {
                                                    'pending' => 'bg-amber-100 text-amber-700',
                                                    'disetujui' => 'bg-blue-100 text-blue-700',
                                                    'ditolak' => 'bg-red-100 text-red-700',
                                                    'dipinjam' => 'bg-purple-100 text-purple-700',
                                                    'dikembalikan' => 'bg-green-100 text-green-700',
                                                    default => 'bg-gray-100 text-gray-700'
                                                };
                                                ?>
                                                <span
                                                    class="px-2 py-1 rounded-full text-xs font-medium <?= $statusClass ?>"><?= ucfirst($p['status']) ?></span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <?php if ($p['status'] === 'pending'): ?>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                        <button type="submit" name="action" value="approve"
                                                            class="p-2 text-green-600 hover:bg-green-50 rounded-lg"
                                                            title="Setujui"><i class="fas fa-check"></i></button>
                                                        <button type="submit" name="action" value="reject"
                                                            class="p-2 text-red-500 hover:bg-red-50 rounded-lg" title="Tolak"><i
                                                                class="fas fa-times"></i></button>
                                                    </form>
                                                <?php elseif ($p['status'] === 'disetujui'): ?>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                        <button type="submit" name="action" value="handover"
                                                            class="px-3 py-1 bg-purple-100 text-purple-700 hover:bg-purple-200 rounded-lg text-xs font-medium"
                                                            title="Serahkan Barang">Serahkan</button>
                                                    </form>
                                                <?php elseif ($p['status'] === 'dipinjam'): ?>
                                                    <a href="../pengembalian/index.php"
                                                        class="px-3 py-1 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg text-xs font-medium">Proses
                                                        Kembali</a>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-xs">-</span>
                                                <?php endif; ?>
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

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('sidebar-hidden');
            document.getElementById('overlay').classList.toggle('hidden');
        }
    </script>
</body>

</html>