<?php
/**
 * Riwayat Peminjaman - Untuk Pegawai
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(['pegawai']);

$user = currentUser();
$pageTitle = 'Riwayat Peminjaman';

// Get peminjaman history
try {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT p.*, b.nama_barang, b.kode_barang 
        FROM peminjaman p 
        JOIN barang b ON p.barang_id = b.id 
        WHERE p.user_id = ? 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $peminjamanList = $stmt->fetchAll();
} catch (Exception $e) {
    $peminjamanList = [];
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
        #sidebar { transition: transform 0.3s ease-in-out; }
        #sidebar.sidebar-hidden { transform: translateX(-100%); }
        @media (min-width: 1024px) { #sidebar { transform: translateX(0) !important; } }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex min-h-screen">
        <div id="overlay" class="fixed inset-0 bg-black/50 z-30 lg:hidden hidden" onclick="toggleSidebar()"></div>

        <aside id="sidebar" class="w-64 bg-white text-gray-800 fixed h-full z-40 shadow-lg sidebar-hidden lg:translate-x-0 border-r border-gray-200">
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
                <button onclick="toggleSidebar()" class="lg:hidden p-2 hover:bg-gray-100 rounded-lg text-gray-500"><i class="fas fa-times"></i></button>
            </div>
            <nav class="p-4 space-y-1">
                <a href="../dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600"><i class="fas fa-home w-5"></i><span>Dashboard</span></a>
                <div class="pt-4"><p class="px-4 text-xs font-semibold text-gray-400 uppercase">Peminjaman</p></div>
                <a href="ajukan.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600"><i class="fas fa-plus-circle w-5"></i><span>Ajukan Peminjaman</span></a>
                <a href="riwayat.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-catalina-50 text-catalina-700 font-medium"><i class="fas fa-history w-5"></i><span>Riwayat Saya</span></a>
                
                <div class="pt-4"><p class="px-4 text-xs font-semibold text-gray-400 uppercase">Akun</p></div>
                <a href="../profil/index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 text-gray-600"><i class="fas fa-user-cog w-5"></i><span>Profil Saya</span></a>
            </nav>
        </aside>

        <main class="flex-1 lg:ml-64 min-w-0">
            <header class="bg-white shadow-sm sticky top-0 z-10 border-b border-gray-200">
                <div class="flex items-center justify-between px-4 lg:px-6 py-4">
                    <div class="flex items-center gap-3">
                        <button onclick="toggleSidebar()" class="lg:hidden p-2 -ml-2 text-gray-600 hover:bg-gray-100 rounded-lg"><i class="fas fa-bars text-xl"></i></button>
                        <div><h1 class="text-lg lg:text-xl font-bold text-gray-800"><?= $pageTitle ?></h1><p class="text-xs text-gray-500">Daftar peminjaman Anda</p></div>
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
                            <a href="../logout.php" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all" title="Logout">
                                <i class="fas fa-sign-out-alt"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="p-4 lg:p-6">
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Barang</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">Tanggal Pinjam</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">Rencana Kembali</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden lg:table-cell">Keperluan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($peminjamanList)): ?>
                                <tr><td colspan="5" class="px-4 py-12 text-center text-gray-400">
                                    <i class="fas fa-inbox text-4xl mb-3"></i>
                                    <p>Belum ada riwayat peminjaman</p>
                                    <a href="ajukan.php" class="inline-block mt-4 px-4 py-2 bg-catalina-600 text-white rounded-xl text-sm">Ajukan Peminjaman</a>
                                </td></tr>
                                <?php else: ?>
                                <?php foreach ($peminjamanList as $p): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-gray-800"><?= htmlspecialchars($p['nama_barang']) ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($p['kode_barang']) ?></p>
                                        <p class="text-xs text-gray-500 md:hidden"><?= date('d/m/Y', strtotime($p['tanggal_pinjam'])) ?></p>
                                    </td>
                                    <td class="px-4 py-3 hidden md:table-cell text-gray-600"><?= date('d M Y', strtotime($p['tanggal_pinjam'])) ?></td>
                                    <td class="px-4 py-3 hidden md:table-cell text-gray-600"><?= date('d M Y', strtotime($p['tanggal_kembali_rencana'])) ?></td>
                                    <td class="px-4 py-3 text-center">
                                        <?php
                                        $statusClass = match($p['status']) {
                                            'pending' => 'bg-amber-100 text-amber-700',
                                            'disetujui' => 'bg-blue-100 text-blue-700',
                                            'ditolak' => 'bg-red-100 text-red-700',
                                            'dipinjam' => 'bg-purple-100 text-purple-700',
                                            'dikembalikan' => 'bg-green-100 text-green-700',
                                            default => 'bg-gray-100 text-gray-700'
                                        };
                                        $statusText = ucfirst($p['status']);
                                        ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $statusClass ?>"><?= $statusText ?></span>
                                    </td>
                                    <td class="px-4 py-3 hidden lg:table-cell text-gray-600 text-sm"><?= htmlspecialchars($p['keperluan'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!empty($peminjamanList)): ?>
                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 text-sm text-gray-500">
                        Total: <strong><?= count($peminjamanList) ?></strong> peminjaman
                    </div>
                    <?php endif; ?>
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
