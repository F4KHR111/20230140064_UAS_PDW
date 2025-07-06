<?php
session_start();
$pageTitle = 'Laporan Masuk';
$activePage = 'laporan_masuk';
require_once 'templates/header.php';

// Koneksi database
$conn = new mysqli('localhost', 'root', '', 'db_simprak');
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$mahasiswa_id = 1; // Simulasi mahasiswa_id, harusnya dari session login user

// Ambil list modul untuk dropdown
$modulList = [];
$qModul = $conn->query("SELECT id, judul_modul FROM modul ORDER BY judul_modul ASC");
while ($r = $qModul->fetch_assoc()) {
    $modulList[] = $r;
}

// ===== HANDLE UPLOAD LAPORAN =====
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_laporan'])) {
    $modul_id = $_POST['modul_id'] ?? '';
    if (!$modul_id) {
        echo "<script>alert('Pilih modul terlebih dahulu.');</script>";
    } elseif (!isset($_FILES['file_laporan'])) {
        echo "<script>alert('Pilih file laporan terlebih dahulu.');</script>";
    } else {
        $file = $_FILES['file_laporan'];
        $allowedExt = ['pdf', 'docx'];
        $fileName = $file['name'];
        $fileTmp = $file['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($fileExt, $allowedExt)) {
            echo "<script>alert('File harus PDF atau DOCX.');</script>";
        } elseif ($file['error'] !== 0) {
            echo "<script>alert('Terjadi kesalahan pada upload file.');</script>";
        } else {
            $newFileName = uniqid() . '.' . $fileExt;
            $uploadDir = __DIR__ . '/upload/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $uploadPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmp, $uploadPath)) {
                $stmt = $conn->prepare("INSERT INTO laporan (mahasiswa_id, modul_id, file_laporan, tanggal_upload, status) VALUES (?, ?, ?, NOW(), 'belum_dinilai')");
                $stmt->bind_param('iis', $mahasiswa_id, $modul_id, $newFileName);
                if ($stmt->execute()) {
                    echo "<script>alert('Laporan berhasil diupload.'); window.location='laporan_masuk.php';</script>";
                    exit;
                } else {
                    unlink($uploadPath);
                    echo "<script>alert('Gagal menyimpan data laporan: " . e($stmt->error) . "');</script>";
                }
            } else {
                echo "<script>alert('Gagal memindahkan file upload.');</script>";
            }
        }
    }
}

// ===== HANDLE DELETE LAPORAN =====
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $q = $conn->prepare("SELECT file_laporan FROM laporan WHERE id = ? AND mahasiswa_id = ?");
    $q->bind_param('ii', $delete_id, $mahasiswa_id);
    $q->execute();
    $res = $q->get_result();
    if ($res->num_rows == 1) {
        $row = $res->fetch_assoc();
        $fileToDelete = __DIR__ . "/upload/" . $row['file_laporan'];

        $del = $conn->prepare("DELETE FROM laporan WHERE id = ? AND mahasiswa_id = ?");
        $del->bind_param('ii', $delete_id, $mahasiswa_id);
        if ($del->execute()) {
            if (file_exists($fileToDelete)) unlink($fileToDelete);
            echo "<script>alert('Laporan berhasil dihapus.'); window.location='laporan_masuk.php';</script>";
            exit;
        } else {
            echo "<script>alert('Gagal menghapus data laporan.'); window.location='laporan_masuk.php';</script>";
            exit;
        }
    } else {
        echo "<script>alert('Data laporan tidak ditemukan.'); window.location='laporan_masuk.php';</script>";
        exit;
    }
}

// ===== HANDLE EDIT LAPORAN =====
$editMode = false;
$editData = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $q = $conn->prepare("SELECT l.*, m.judul_modul, u.nama AS nama_mahasiswa FROM laporan l JOIN modul m ON l.modul_id = m.id JOIN users u ON l.mahasiswa_id = u.id WHERE l.id = ? AND l.mahasiswa_id = ?");
    $q->bind_param('ii', $edit_id, $mahasiswa_id);
    $q->execute();
    $res = $q->get_result();
    if ($res->num_rows == 1) {
        $editData = $res->fetch_assoc();
        $editMode = true;
    } else {
        echo "<script>alert('Data laporan tidak ditemukan.'); window.location='laporan_masuk.php';</script>";
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_laporan'])) {
    $edit_id = intval($_POST['edit_id']);
    $nilai = intval($_POST['nilai']);
    $feedback = $_POST['feedback'];

    $stmt = $conn->prepare("UPDATE laporan SET nilai = ?, feedback = ?, status = 'sudah_dinilai' WHERE id = ? AND mahasiswa_id = ?");
    $stmt->bind_param('isii', $nilai, $feedback, $edit_id, $mahasiswa_id);
    if ($stmt->execute()) {
        echo "<script>alert('Laporan berhasil diperbarui.'); window.location='laporan_masuk.php';</script>";
        exit;
    } else {
        echo "<script>alert('Gagal memperbarui laporan: " . e($stmt->error) . "');</script>";
    }
}

$laporanList = [];
$sql = "SELECT l.id, l.file_laporan, DATE_FORMAT(l.tanggal_upload, '%Y-%m-%d %H:%i:%s') AS tanggal_upload, 
        l.nilai, l.feedback, m.judul_modul, u.nama AS nama_mahasiswa
        FROM laporan l 
        JOIN modul m ON l.modul_id = m.id 
        JOIN users u ON l.mahasiswa_id = u.id 
        WHERE l.mahasiswa_id = ?
        ORDER BY l.tanggal_upload DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $mahasiswa_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $laporanList[] = $row;
}
?>

<section class="bg-white p-6 rounded shadow max-w-3xl mb-10">
  <h2 class="text-xl font-semibold mb-4">
    <?= $editMode ? 'Edit Laporan: ' . e($editData['judul_modul']) : 'Upload Laporan' ?>
  </h2>

  <?php if ($editMode): ?>
    <form method="POST">
      <input type="hidden" name="edit_id" value="<?= e($editData['id']) ?>">
      <div class="mb-4">
        <label class="block font-medium">Nilai</label>
        <input type="number" name="nilai" min="0" max="100" value="<?= e($editData['nilai'] ?? '') ?>" class="w-full border px-3 py-2 rounded" required>
      </div>
      <div class="mb-4">
        <label class="block font-medium">Feedback</label>
        <textarea name="feedback" rows="4" class="w-full border px-3 py-2 rounded" required><?= e($editData['feedback'] ?? '') ?></textarea>
      </div>
      <button name="edit_laporan" class="bg-blue-600 text-white px-4 py-2 rounded">Simpan</button>
      <a href="laporan_masuk.php" class="ml-4 text-blue-600">Batal</a>
    </form>
  <?php else: ?>
    <form method="POST" enctype="multipart/form-data">
      <div class="mb-4">
        <label class="block font-medium">Pilih Modul</label>
        <select name="modul_id" class="w-full border px-3 py-2 rounded" required>
          <option value="">-- Pilih Modul --</option>
          <?php foreach ($modulList as $modul): ?>
            <option value="<?= e($modul['id']) ?>"><?= e($modul['judul_modul']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-4">
        <label class="block font-medium">File Laporan (PDF/DOCX)</label>
        <input type="file" name="file_laporan" accept=".pdf,.docx" class="w-full border px-3 py-2 rounded" required>
      </div>
      <button name="upload_laporan" class="bg-blue-600 text-white px-4 py-2 rounded">Upload</button>
    </form>
  <?php endif; ?>
</section>

<section class="bg-white p-6 rounded shadow">
  <h2 class="text-xl font-semibold mb-4">Daftar Laporan</h2>
  <?php if (!$laporanList): ?>
    <p class="text-gray-500">Belum ada laporan diupload.</p>
  <?php else: ?>
    <div class="overflow-x-auto">
      <table class="min-w-full border text-left">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-4 py-2 border">No</th>
            <th class="px-4 py-2 border">Modul</th>
            <th class="px-4 py-2 border">Mahasiswa</th>
            <th class="px-4 py-2 border">File</th>
            <th class="px-4 py-2 border">Upload</th>
            <th class="px-4 py-2 border">Nilai</th>
            <th class="px-4 py-2 border">Feedback</th>
            <th class="px-4 py-2 border">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($laporanList as $i => $l): ?>
          <tr class="hover:bg-gray-50">
            <td class="border px-4 py-2 text-center"><?= $i + 1 ?></td>
            <td class="border px-4 py-2"><?= e($l['judul_modul']) ?></td>
            <td class="border px-4 py-2"><?= e($l['nama_mahasiswa']) ?></td>
            <td class="border px-4 py-2"><a href="upload/<?= e($l['file_laporan']) ?>" class="text-blue-600 underline" target="_blank">Lihat</a></td>
            <td class="border px-4 py-2"><?= e($l['tanggal_upload']) ?></td>
            <td class="border px-4 py-2"><?= $l['nilai'] !== null ? e($l['nilai']) : '-' ?></td>
            <td class="border px-4 py-2"><?= $l['feedback'] !== null ? e($l['feedback']) : '-' ?></td>
            <td class="border px-4 py-2">
              <a href="?edit_id=<?= e($l['id']) ?>" class="text-blue-600">Edit</a> |
              <a href="?delete_id=<?= e($l['id']) ?>" class="text-red-600" onclick="return confirm('Yakin ingin menghapus laporan ini?');">Hapus</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require_once 'templates/footer.php'; ?>
