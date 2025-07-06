<?php
session_start();
require_once '../config.php'; // Atau path ke koneksi database

if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    die('Anda belum login!');
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$praktikum_id = $_GET['id'] ?? null;
if (!$praktikum_id) {
    die('ID praktikum tidak ditemukan.');
}

// Ambil data praktikum
$stmt = $conn->prepare("SELECT * FROM mata_praktikum WHERE id = ?");
$stmt->bind_param("i", $praktikum_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    die('Praktikum tidak ditemukan.');
}
$praktikum = $result->fetch_assoc();

// Ambil daftar tugas untuk praktikum ini (REVISI PENTING DI SINI)
$stmt = $conn->prepare("
    SELECT t.* 
    FROM tugas_praktikum t
    JOIN modul m ON t.modul_id = m.id
    WHERE m.mata_praktikum_id = ?
    ORDER BY t.deadline ASC
");
$stmt->bind_param("i", $praktikum_id);
$stmt->execute();
$tugas_result = $stmt->get_result();

// Ambil daftar materi/modul
$stmt = $conn->prepare("SELECT * FROM modul WHERE mata_praktikum_id = ?");
$stmt->bind_param("i", $praktikum_id);
$stmt->execute();
$materi_result = $stmt->get_result();

// Proses Upload Laporan oleh Mahasiswa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_laporan'])) {
    $tugas_id = intval($_POST['tugas_id']);
    $file = $_FILES['file_laporan'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowed_ext = ['pdf', 'docx', 'doc'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_ext)) {
            $upload_dir = __DIR__ . '/uploads/tugas/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $file_name = uniqid('tugas_') . '.' . $ext;
            $file_path = $upload_dir . $file_name;

            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Cek apakah mahasiswa sudah pernah upload tugas ini
                $cek = $conn->prepare("SELECT id FROM pengumpulan_tugas WHERE tugas_id = ? AND mahasiswa_id = ?");
                $cek->bind_param("ii", $tugas_id, $user_id);
                $cek->execute();
                $res = $cek->get_result();

                if ($res->num_rows > 0) {
                    // Update file pengumpulan lama
                    $row = $res->fetch_assoc();
                    $stmt = $conn->prepare("UPDATE pengumpulan_tugas SET file_pengumpulan = ?, status = 'Selesai', tanggal_pengumpulan = NOW() WHERE id = ?");
                    $stmt->bind_param("si", $file_name, $row['id']);
                } else {
                    // Insert baru
                    $stmt = $conn->prepare("INSERT INTO pengumpulan_tugas (tugas_id, mahasiswa_id, file_pengumpulan, status, tanggal_pengumpulan) VALUES (?, ?, ?, 'Selesai', NOW())");
                    $stmt->bind_param("iis", $tugas_id, $user_id, $file_name);
                }

                if ($stmt->execute()) {
                    $success_upload = "Laporan berhasil diunggah.";
                } else {
                    $error_upload = "Gagal menyimpan ke database.";
                }
            } else {
                $error_upload = "Gagal mengunggah file.";
            }
        } else {
            $error_upload = "Ekstensi file tidak diizinkan.";
        }
    } else {
        $error_upload = "Terjadi kesalahan saat mengunggah file.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Detail Praktikum - <?= htmlspecialchars($praktikum['nama_praktikum'] ?? 'Praktikum') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans text-gray-800">

  <div class="max-w-6xl mx-auto p-6">
    
    <?php if (!empty($error_upload)): ?>
    <div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-6 border border-red-300">
        <?= htmlspecialchars($error_upload) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($success_upload)): ?>
    <div class="bg-green-100 text-green-700 px-4 py-2 rounded mb-6 border border-green-300">
        <?= htmlspecialchars($success_upload) ?>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
      <h1 class="text-3xl font-bold text-indigo-700 mb-2">
        Detail Praktikum: <?= htmlspecialchars($praktikum['nama_praktikum']) ?>
      </h1>
      <p class="text-gray-700"><?= nl2br(htmlspecialchars($praktikum['deskripsi'] ?? '-')) ?></p>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
      <h2 class="text-2xl font-semibold text-indigo-600 mb-4">Materi Praktikum</h2>
      <?php if ($materi_result->num_rows > 0): ?>
        <ul class="space-y-2 list-disc list-inside text-gray-800">
          <?php while($materi = $materi_result->fetch_assoc()): ?>
            <li>
              <a href="uploads/materi/<?= htmlspecialchars($materi['file_materi']) ?>" target="_blank" class="text-blue-600 hover:underline" download>
                <?= htmlspecialchars($materi['judul_modul']) ?>
              </a>
            </li>
          <?php endwhile; ?>
        </ul>
      <?php else: ?>
        <p class="text-gray-500">Tidak ada materi praktikum.</p>
      <?php endif; ?>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl font-semibold text-indigo-600">Daftar Tugas Praktikum</h2>
        <?php if ($role === 'asisten'): ?>
          <a href="tugas_praktikum_form.php?praktikum_id=<?= $praktikum_id ?>" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
            Tambah Tugas
          </a>
        <?php endif; ?>
      </div>

      <?php if ($role === 'mahasiswa'): ?>
  <div class="bg-white rounded-lg shadow-md p-6 mt-8">
    <h2 class="text-xl font-semibold text-indigo-600 mb-4">Upload Laporan Tugas</h2>

    <?php
    // Ambil daftar tugas berdasarkan praktikum
    $stmt = $conn->prepare("SELECT t.id, t.judul_tugas 
                            FROM tugas_praktikum t 
                            JOIN modul m ON t.modul_id = m.id 
                            WHERE m.mata_praktikum_id = ?");
    $stmt->bind_param("i", $praktikum_id);
    $stmt->execute();
    $tugas_result = $stmt->get_result();
    ?>

    <?php if ($tugas_result->num_rows > 0): ?>
      <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
        <div>
          <label for="tugas_id" class="block font-medium text-gray-700 mb-1">Pilih Tugas:</label>
          <select name="tugas_id" id="tugas_id" required class="block w-full p-2 border rounded">
            <option value="">-- Pilih Tugas --</option>
            <?php while ($row = $tugas_result->fetch_assoc()): ?>
              <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['judul_tugas']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <div>
          <label for="file_laporan" class="block font-medium text-gray-700 mb-1">File Laporan (PDF/DOC/DOCX):</label>
          <input type="file" name="file_laporan" id="file_laporan" accept=".pdf,.doc,.docx" required class="block w-full border p-2 rounded">
        </div>

        <button type="submit" name="upload_laporan" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
          Upload
        </button>
      </form>
    <?php else: ?>
        <p class="text-gray-500 italic">Belum ada tugas yang tersedia untuk diunggah.</p>
    <?php endif; ?>
        </div>
    <?php endif; ?>


      <?php if ($role === 'mahasiswa'): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mt-8">
          <h2 class="text-xl font-semibold text-indigo-600 mb-4">Nilai Tugas Praktikum</h2>
            <div class="overflow-x-auto">
              <table class="min-w-full table-auto border border-gray-300 text-sm">
                <thead class="bg-gray-100">
                    <tr>
                    <th class="py-2 px-4 border-b">Tugas</th>
                    <th class="py-2 px-4 border-b">Status</th>
                    <th class="py-2 px-4 border-b">Nilai</th>
                    <th class="py-2 px-4 border-b">Komentar</th>
                    <th class="py-2 px-4 border-b">File</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $stmt = $conn->prepare("
                            SELECT t.judul_tugas, p.status, p.keterangan, p.file_pengumpulan
                            FROM tugas_praktikum t
                            LEFT JOIN pengumpulan_tugas p ON t.id = p.tugas_id AND p.mahasiswa_id = ?
                            WHERE t.praktikum_id = ?
                            ORDER BY t.deadline ASC
                        ");
                        $stmt->bind_param("ii", $user_id, $praktikum_id);
                        $stmt->execute();
                        $nilai_result = $stmt->get_result();
                        while ($row = $nilai_result->fetch_assoc()):
                    ?>
                    <tr>
                    <td class="py-2 px-4 border-b"><?= htmlspecialchars($row['judul_tugas']) ?></td>
                    <td class="py-2 px-4 border-b"><?= htmlspecialchars($row['status'] ?? 'Belum Dikerjakan') ?></td>
                    <td class="py-2 px-4 border-b"><?= htmlspecialchars($row['nilai'] ?? '-') ?></td>
                    <td class="py-2 px-4 border-b"><?= htmlspecialchars($row['keterangan'] ?? '-') ?></td>
                    <td class="py-2 px-4 border-b">
                        <?php if (!empty($row['file_pengumpulan'])): ?>
                        <a href="uploads/tugas/<?= htmlspecialchars($row['file_pengumpulan']) ?>" class="text-blue-600 hover:underline" download>Lihat</a>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>


      <?php if ($tugas_result->num_rows > 0): ?>
        <div class="overflow-x-auto">
          <table class="min-w-full table-auto border border-gray-300">
            <thead class="bg-gray-100">
              <tr class="text-left text-sm text-gray-700 font-semibold">
                <th class="py-3 px-4 border-b">Judul Tugas</th>
                <th class="py-3 px-4 border-b">Deskripsi</th>
                <th class="py-3 px-4 border-b">Deadline</th>
                <?php if ($role === 'mahasiswa'): ?>
                  <th class="py-3 px-4 border-b">Status</th>
                <?php endif; ?>
                <th class="py-3 px-4 border-b">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php while($tugas = $tugas_result->fetch_assoc()): ?>
                <?php
                  $stmt = $conn->prepare("SELECT * FROM pengumpulan_tugas WHERE tugas_id = ? AND mahasiswa_id = ?");
                  $stmt->bind_param("ii", $tugas['id'], $user_id);
                  $stmt->execute();
                  $hasil_pengumpulan = $stmt->get_result()->fetch_assoc();
                  $status_pengumpulan = $hasil_pengumpulan['status'] ?? 'Belum Dikerjakan';
                  $file_pengumpulan = $hasil_pengumpulan['file_pengumpulan'] ?? null;
                ?>
                <tr class="text-sm text-gray-800 hover:bg-gray-50">
                  <td class="py-3 px-4 border-b"><?= htmlspecialchars($tugas['judul_tugas']) ?></td>
                  <td class="py-3 px-4 border-b"><?= nl2br(htmlspecialchars($tugas['deskripsi'])) ?></td>
                  <td class="py-3 px-4 border-b"><?= htmlspecialchars($tugas['deadline']) ?></td>
                  <?php if ($role === 'mahasiswa'): ?>
                    <td class="py-3 px-4 border-b"><?= htmlspecialchars($status_pengumpulan) ?></td>
                  <?php endif; ?>
                  <td class="py-3 px-4 border-b">
                    <?php if ($role === 'asisten'): ?>
                      <a href="tugas_praktikum_form.php?id=<?= $tugas['id'] ?>" class="text-blue-600 hover:underline">Edit</a> |
                      <a href="tugas_praktikum_delete.php?id=<?= $tugas['id'] ?>" onclick="return confirm('Yakin hapus tugas ini?');" class="text-red-600 hover:underline">Hapus</a>
                    <?php elseif ($role === 'mahasiswa'): ?>
                      <?php if ($status_pengumpulan === 'Belum Dikerjakan'): ?>
                        <form method="post" enctype="multipart/form-data" class="flex flex-col sm:flex-row items-start sm:items-center gap-2">
                          <input type="hidden" name="tugas_id" value="<?= $tugas['id'] ?>">
                          <input type="file" name="file_pengumpulan" class="text-sm" required>
                          <button type="submit" name="upload_tugas" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700">
                            Upload
                          </button>
                        </form>
                      <?php else: ?>
                        <a href="uploads/tugas/<?= htmlspecialchars($file_pengumpulan) ?>" class="text-blue-600 hover:underline" download>Lihat File</a>
                      <?php endif; ?>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-gray-500 mt-2">Belum ada tugas praktikum.</p>
      <?php endif; ?>
    </div>

  </div>

</body>
</html>
