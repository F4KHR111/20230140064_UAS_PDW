<?php
session_start();

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'mahasiswa') {
    header('Location: /20230140064_UAS_PDW/login.php');
    exit;
}

$pageTitle  = 'Praktikum Saya';
$activePage = 'praktikum_saya';

require_once 'templates/header_mahasiswa.php';
require_once '../config.php';

if ($conn->connect_error) {
    die('Koneksi database gagal: ' . $conn->connect_error);
}

$mahasiswaId = (int)$_SESSION['user_id'];
$praktikumDiikuti = [];

$sql = "
    SELECT mp.id, mp.kode_praktikum, mp.nama_praktikum, mp.deskripsi,
           p.status AS status_pendaftaran
    FROM pendaftaran AS p 
    JOIN mata_praktikum AS mp ON p.mata_praktikum_id = mp.id
    WHERE p.mahasiswa_id = ?
    ORDER BY mp.nama_praktikum ASC
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Error prepare statement: ' . $conn->error);
}
$stmt->bind_param('i', $mahasiswaId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $praktikumDiikuti[] = $row;
}
$stmt->close();

function getStatusClass($status) {
    return match ($status) {
        'aktif' => 'bg-green-200 text-green-800',
        'lulus' => 'bg-blue-200 text-blue-800',
        'mengulang' => 'bg-red-200 text-red-800',
        default => 'bg-gray-200 text-gray-800',
    };
}
?>

<div class="container mx-auto p-6 bg-white rounded-lg shadow-md mt-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Praktikum Saya</h2>

    <?php if (empty($praktikumDiikuti)): ?>
        <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4" role="alert">
            <p class="font-bold">Informasi</p>
            <p>Anda belum terdaftar pada praktikum manapun. Silakan <a href="cari_praktikum.php" class="text-blue-600 underline">cari praktikum</a> untuk mendaftar!</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-300 rounded-lg">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">Kode Praktikum</th>
                        <th class="py-3 px-6 text-left">Nama Praktikum</th>
                        <th class="py-3 px-6 text-left max-w-xs">Deskripsi</th>
                        <th class="py-3 px-6 text-left">Status Pendaftaran</th>
                        <th class="py-3 px-6 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm font-light">
                    <?php foreach ($praktikumDiikuti as $praktikum): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="py-3 px-6 text-left whitespace-nowrap"><?php echo htmlspecialchars($praktikum['kode_praktikum']); ?></td>
                            <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($praktikum['nama_praktikum']); ?></td>
                            <td class="py-3 px-6 text-left max-w-xs overflow-hidden text-ellipsis" title="<?php echo htmlspecialchars($praktikum['deskripsi']); ?>"><?php echo htmlspecialchars($praktikum['deskripsi']); ?></td>
                            <td class="py-3 px-6 text-left">
                                <span class="py-1 px-3 rounded-full text-xs <?php echo getStatusClass($praktikum['status_pendaftaran']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($praktikum['status_pendaftaran'])); ?>
                                </span>
                            </td>
                            <td class="py-3 px-6 text-center">
                                <div class="flex item-center justify-center space-x-4">
                                    <a href="detail_praktikum.php?id=<?php echo $praktikum['id']; ?>" title="Detail Praktikum" class="text-purple-600 hover:text-purple-800">
                                        Detail
                                    </a>
                                    <?php if ($praktikum['status_pendaftaran'] === 'aktif'): ?>
                                        <a href="batalkan_pendaftaran.php?id=<?php echo $praktikum['id']; ?>" title="Batalkan Pendaftaran" class="text-red-600 hover:text-red-800" onclick="return confirm('Apakah Anda yakin ingin membatalkan pendaftaran praktikum ini?');">
                                            Batal
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
require_once 'templates/footer_mahasiswa.php';
$conn->close();
?>
