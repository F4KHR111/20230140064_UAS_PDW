<?php
// manajemen_modul.php
session_start();

// Koneksi database
$host = 'localhost';
$user = 'root';
$pass = ''; // sesuaikan
$dbname = 'db_simprak'; // sesuaikan nama database

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tugas_id']) && isset($_POST['judul_tugas'])) {
        $tugas_id = intval($_POST['tugas_id']);
        $judul_tugas = $conn->real_escape_string($_POST['judul_tugas']);

        $conn->query("INSERT INTO penilaian (tugas_id, judul_tugas) VALUES ('$tugas_id', '$judul_tugas')");
    } else {
        echo "<script>alert('Isi semua kolom terlebih dahulu.');</script>";
    }
}

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Folder upload materi
$uploadDir = 'upload/';

// Fungsi upload file materi
function uploadFile($fileInput, $uploadDir)
{
    if (!isset($_FILES[$fileInput]) || $_FILES[$fileInput]['error'] == UPLOAD_ERR_NO_FILE) {
        return null; // Tidak ada file diupload
    }

    $file = $_FILES[$fileInput];
    $allowedExt = ['pdf', 'doc', 'docx'];
    $fileName = basename($file['name']);
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($fileExt, $allowedExt)) {
        return false; // ekstensi tidak diperbolehkan
    }

    // Buat nama file unik untuk menghindari overwrite
    $newFileName = uniqid('materi_') . '.' . $fileExt;
    $targetPath = $uploadDir . $newFileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $newFileName;
    } else {
        return false;
    }
}

// Proses CREATE (tambah modul)
if (isset($_POST['action']) && $_POST['action'] === 'create') {
    $judul = $conn->real_escape_string($_POST['judul_modul']);
    $deskripsi = $conn->real_escape_string($_POST['deskripsi']);
    $pertemuan = intval($_POST['pertemuan_ke']);

    // Ambil juga mata_praktikum_id dari form
    $mata_praktikum_id = intval($_POST['mata_praktikum_id']);

    $fileMateri = uploadFile('file_materi', $uploadDir);
    if ($fileMateri === false) {
        die("Upload file tidak valid. Pastikan file PDF/DOC/DOCX.");
    }

    // Tambahkan mata_praktikum_id ke query INSERT
    $sql = "INSERT INTO modul (mata_praktikum_id, judul_modul, deskripsi, pertemuan_ke, file_materi)
            VALUES ($mata_praktikum_id, '$judul', '$deskripsi', $pertemuan, " . ($fileMateri ? "'$fileMateri'" : "NULL") . ")";

    if ($conn->query($sql)) {
        header('Location: manajemen_modul.php');
        exit;
    } else {
        die("Error saat tambah modul: " . $conn->error);
    }
}


// Proses UPDATE (edit modul)
if (isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = intval($_POST['id']);
    $judul = $conn->real_escape_string($_POST['judul_modul']);
    $deskripsi = $conn->real_escape_string($_POST['deskripsi']);
    $pertemuan = intval($_POST['pertemuan_ke']);

    // Cek apakah ada file materi baru diupload
    $fileMateri = uploadFile('file_materi', $uploadDir);
    $fileMateriSql = '';
    if ($fileMateri === false) {
        die("Upload file tidak valid. Pastikan file PDF/DOC/DOCX.");
    } elseif ($fileMateri !== null) {
        $fileMateriSql = ", file_materi='$fileMateri'";
    }

    $sql = "UPDATE modul SET judul_modul='$judul', deskripsi='$deskripsi', pertemuan_ke=$pertemuan $fileMateriSql WHERE id=$id";
    if ($conn->query($sql)) {
        header('Location: manajemen_modul.php');
        exit;
    } else {
        die("Error saat update modul: " . $conn->error);
    }
}

// Proses DELETE (hapus modul)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Hapus file materi dari folder jika ada
    $result = $conn->query("SELECT file_materi FROM modul WHERE id=$id");
    if ($result && $row = $result->fetch_assoc()) {
        if ($row['file_materi']) {
            $filePath = $uploadDir . $row['file_materi'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }

    $conn->query("DELETE FROM modul WHERE id=$id");
    header('Location: manajemen_modul.php');
    exit;
}

// Folder upload tugas mahasiswa
$tugasUploadDir = 'upload/tugas/';

// Fungsi upload tugas
function uploadTugas($fileInput, $uploadDir)
{
    if (!isset($_FILES[$fileInput]) || $_FILES[$fileInput]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$fileInput];
    $allowedExt = ['pdf', 'doc', 'docx'];
    $fileName = basename($file['name']);
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($fileExt, $allowedExt)) {
        return false;
    }

    $newFileName = uniqid('tugas_') . '.' . $fileExt;
    $targetPath = $uploadDir . $newFileName;

    // ✅ Tambahan ini penting:
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $newFileName;
    }

    return false;
}

if (isset($_POST['upload_tugas_asisten'])) {
    $judul_tugas = $conn->real_escape_string($_POST['judul_tugas']);
    $modul_id = intval($_POST['modul_id']);
    $deadline = $_POST['deadline'];

    $file_name = null;
    if (!empty($_FILES['file_tugas']['name'])) {
        $allowedExt = ['pdf', 'doc', 'docx'];
        $ext = strtolower(pathinfo($_FILES['file_tugas']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExt)) {
            $file_name = uniqid('tugas_') . '.' . $ext;
            move_uploaded_file($_FILES['file_tugas']['tmp_name'], __DIR__ . "/upload/$file_name");
        }
    }

    $stmt = $conn->prepare("INSERT INTO tugas_praktikum (judul_tugas, modul_id, deadline, file_tugas) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siss", $judul_tugas, $modul_id, $deadline, $file_name);
    if ($stmt->execute()) {
        echo "<script>alert('Tugas berhasil ditambahkan'); window.location='manajemen_modul.php';</script>";
        exit;
    } else {
        echo "<script>alert('Gagal menambahkan tugas: " . $conn->error . "');</script>";
    }
}


// Ambil data modul untuk ditampilkan
$dataModul = [];
$sql = "SELECT * FROM modul ORDER BY pertemuan_ke ASC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dataModul[] = $row;
    }
}

// Cek apakah ada request untuk logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Hapus session
    $_SESSION = array();
    session_destroy();

    // Redirect ke halaman login
    header("Location: /20230140064_UAS_PDW/login.php"); // sesuaikan path login.php sesuai lokasi Anda sebenarnya
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manajemen Modul</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

  <div class="flex h-screen">

    <!-- Sidebar -->
    <aside class="bg-gray-800 w-64 text-gray-200 flex flex-col">
      <div class="p-6 text-center border-b border-gray-700">
        <h1 class="text-2xl font-bold">Panel Asisten</h1>
        <p class="text-sm text-gray-400 mt-1">Fakhri</p>
      </div>
      <nav class="flex flex-col p-4 space-y-2 flex-grow">
        <a href="dashboard.php" class="flex items-center p-2 rounded-md hover:bg-gray-700">
          <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
          Dashboard
        </a>
        <a href="manajemen_modul.php" class="flex items-center p-2 rounded-md bg-gray-700">
          <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>
          Manajemen Modul
        </a>
        <a href="laporan_masuk.php" class="flex items-center p-2 rounded-md hover:bg-gray-700">
          <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75c0-.231-.035-.454-.1-.664M6.75 7.5h1.5M6.75 12h1.5m6.75 0h1.5m-1.5 3h1.5m-1.5 3h1.5M4.5 6.75h1.5v1.5H4.5v-1.5zM4.5 12h1.5v1.5H4.5v-1.5zM4.5 17.25h1.5v1.5H4.5v-1.5z" /></svg>
          Laporan Masuk
        </a>
        <a href="manajemen_mata_praktikum.php" class="flex items-center p-2 rounded-md hover:bg-gray-700">
          <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75c0-.231-.035-.454-.1-.664M6.75 7.5h1.5M6.75 12h1.5m6.75 0h1.5m-1.5 3h1.5m-1.5 3h1.5M4.5 6.75h1.5v1.5H4.5v-1.5zM4.5 12h1.5v1.5H4.5v-1.5zM4.5 17.25h1.5v1.5H4.5v-1.5z" /></svg>
          Manajemen Mata Praktikum
        </a>
        <a href="Kelola_Mahasiswa.php" class="flex items-center p-2 rounded-md hover:bg-gray-700">
          <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75c0-.231-.035-.454-.1-.664M6.75 7.5h1.5M6.75 12h1.5m6.75 0h1.5m-1.5 3h1.5m-1.5 3h1.5M4.5 6.75h1.5v1.5H4.5v-1.5zM4.5 12h1.5v1.5H4.5v-1.5zM4.5 17.25h1.5v1.5H4.5v-1.5z" /></svg>
          Kelola Mahasiswa
        </a>
      </nav>
    </aside>

    <!-- Main content -->
    <main class="flex-grow p-8 overflow-auto">
      <header class="flex justify-between items-center mb-8">
        <h2 class="text-3xl font-bold text-gray-900">Manajemen Modul</h2>
        <a href="?action=logout" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-md">Logout</a>
      </header>

      <!-- Form Section - Dua kolom bersebelahan -->
      <div class="flex flex-col lg:flex-row gap-6 mb-8">
        <!-- Form Tambah/Edit Modul -->
        <section class="bg-white p-6 rounded-md shadow-md w-full lg:w-1/2">
          <h3 class="text-xl font-semibold mb-4">Tambah / Edit Modul</h3>
          <form id="formModul" action="manajemen_modul.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" id="modulId" />
            <div class="mb-4">
              <label for="judul_modul" class="block font-medium mb-1">Judul Modul</label>
              <input type="text" name="judul_modul" id="judul_modul" class="w-full border border-gray-300 p-2 rounded" required />
            </div>
            <div class="mb-4">
              <label for="deskripsi" class="block font-medium mb-1">Deskripsi Modul</label>
              <textarea name="deskripsi" id="deskripsi" rows="3" class="w-full border border-gray-300 p-2 rounded"></textarea>
            </div>
            <div class="mb-4">
              <label for="pertemuan_ke" class="block font-medium mb-1">Pertemuan ke-</label>
              <input type="number" name="pertemuan_ke" id="pertemuan_ke" class="w-24 border border-gray-300 p-2 rounded" min="1" required />
            </div>
            <div class="mb-4">
              <label for="file_materi" class="block font-medium mb-1">File Materi (PDF/DOCX)</label>
              <input type="file" name="file_materi" id="file_materi" accept=".pdf,.doc,.docx" />
            </div>
            <div class="mb-4">
              <label for="mata_praktikum_id" class="block font-medium mb-1">Mata Praktikum</label>
              <select name="mata_praktikum_id" id="mata_praktikum_id" class="w-full border border-gray-300 p-2 rounded" required>
                <option value="">-- Pilih Mata Praktikum --</option>
                <?php
                // Ambil data mata praktikum dari database
                $result = $conn->query("SELECT id, nama_praktikum FROM mata_praktikum");
                while ($row = $result->fetch_assoc()) {
                  echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['nama_praktikum']) . "</option>";
                }
                ?>
              </select>
            </div>
            <div>
              <button type="submit" name="action" value="create" id="btnSubmit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded mr-2">
                Simpan Modul
              </button>
              <button type="button" id="btnReset" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded">
                Reset
              </button>
            </div>
          </form>
        </section>
        
        <!-- Form Upload Tugas Mahasiswa -->
        <section class="bg-white p-6 rounded-md shadow-md w-full lg:w-1/2">
          <h3 class="text-xl font-semibold mb-4">Upload Tugas untuk Mahasiswa</h3>
          <form action="manajemen_modul.php" method="POST" enctype="multipart/form-data">
            
            <div class="mb-4">
              <label for="judul_tugas" class="block font-medium mb-1">Judul Tugas</label>
              <input type="text" name="judul_tugas" id="judul_tugas" class="w-full border border-gray-300 p-2 rounded" required>
            </div>

            <div class="mb-4">
              <label for="modul_id" class="block font-medium mb-1">Modul Terkait</label>
              <select name="modul_id" id="modul_id" class="w-full border border-gray-300 p-2 rounded" required>
                <option value="">-- Pilih Modul --</option>
                <?php
                $result = $conn->query("SELECT m.id, m.judul_modul, p.nama_praktikum FROM modul m JOIN mata_praktikum p ON m.mata_praktikum_id = p.id ORDER BY p.nama_praktikum, m.pertemuan_ke");
                while ($row = $result->fetch_assoc()) {
                  echo "<option value='{$row['id']}'>[{$row['nama_praktikum']}] {$row['judul_modul']}</option>";
                }
                ?>
              </select>
            </div>

            <div class="mb-4">
              <label for="deadline" class="block font-medium mb-1">Deadline</label>
              <input type="datetime-local" name="deadline" id="deadline" class="w-full border border-gray-300 p-2 rounded" required>
            </div>

            <div class="mb-4">
              <label for="file_tugas" class="block font-medium mb-1">File Tugas (PDF/DOCX)</label>
              <input type="file" name="file_tugas" id="file_tugas" accept=".pdf,.doc,.docx" class="w-full">
            </div>

            <button type="submit" name="upload_tugas_asisten" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
              Upload Tugas
            </button>
          </form>
        </section>
      </div>

      <!-- Tabel Daftar Modul -->
      <section class="bg-white p-6 rounded-md shadow-md w-full">
        <h3 class="text-xl font-semibold mb-4">Daftar Modul</h3>
        <div class="overflow-x-auto">
          <table class="min-w-full table-auto border-collapse border border-gray-200">
            <thead>
              <tr class="bg-gray-100 text-left">
                <th class="border border-gray-300 px-4 py-2">Pertemuan Ke-</th>
                <th class="border border-gray-300 px-4 py-2">Judul Modul</th>
                <th class="border border-gray-300 px-4 py-2">Deskripsi</th>
                <th class="border border-gray-300 px-4 py-2">File Materi</th>
                <th class="border border-gray-300 px-4 py-2">Aksi</th>
              </tr>
            </thead>
            <tbody id="modulList">
              <?php foreach ($dataModul as $modul): ?>
                <tr data-id="<?php echo $modul['id']; ?>">
                  <td class="border border-gray-300 px-4 py-2 colPertemuan"><?php echo $modul['pertemuan_ke']; ?></td>
                  <td class="border border-gray-300 px-4 py-2 colJudul"><?php echo htmlspecialchars($modul['judul_modul']); ?></td>
                  <td class="border border-gray-300 px-4 py-2 colDeskripsi"><?php echo htmlspecialchars($modul['deskripsi']); ?></td>
                  <td class="border border-gray-300 px-4 py-2 colFile">
                    <?php if ($modul['file_materi']): ?>
                      <a href="<?php echo $uploadDir . $modul['file_materi']; ?>" target="_blank" class="text-blue-600 hover:underline">Lihat</a>
                    <?php else: ?>
                      -
                    <?php endif; ?>
                  </td>
                  <td class="border border-gray-300 px-4 py-2 space-x-2">
                    <button class="btnEdit bg-yellow-400 hover:bg-yellow-500 px-2 py-1 rounded text-white">Edit</button>
                    <a href="manajemen_modul.php?delete=<?php echo $modul['id']; ?>" onclick="return confirm('Yakin ingin hapus modul ini?');" class="btnDelete bg-red-500 hover:bg-red-600 px-2 py-1 rounded text-white">Hapus</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (count($dataModul) === 0): ?>
                <tr>
                  <td colspan="5" class="border border-gray-300 px-4 py-2 text-center text-gray-500">Belum ada data modul.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>

  <script>
    // Script sederhana untuk handler form edit dan reset
    document.addEventListener('DOMContentLoaded', function () {
      const modulList = document.getElementById('modulList');
      const form = document.getElementById('formModul');
      const modulId = document.getElementById('modulId');
      const judulInput = document.getElementById('judul_modul');
      const deskripsiInput = document.getElementById('deskripsi');
      const pertemuanInput = document.getElementById('pertemuan_ke');
      const fileInput = document.getElementById('file_materi');
      const btnSubmit = document.getElementById('btnSubmit');
      const btnReset = document.getElementById('btnReset');

      modulList.addEventListener('click', function (e) {
        if (e.target.classList.contains('btnEdit')) {
          const tr = e.target.closest('tr');
          modulId.value = tr.dataset.id;
          pertemuanInput.value = tr.querySelector('.colPertemuan').textContent;
          judulInput.value = tr.querySelector('.colJudul').textContent;
          deskripsiInput.value = tr.querySelector('.colDeskripsi').textContent;
          // Untuk file materi, tidak diisi karena file input tidak bisa di-set value
          fileInput.value = '';

          btnSubmit.textContent = 'Update Modul';
          btnSubmit.name = 'action';
          btnSubmit.value = 'update';
        }
      });

      btnReset.addEventListener('click', function () {
        form.reset();
        modulId.value = '';
        btnSubmit.textContent = 'Simpan Modul';
        btnSubmit.name = 'action';
        btnSubmit.value = 'create';
      });
    });
  </script>

</body>
</html>