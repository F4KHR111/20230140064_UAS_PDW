<?php
include("../config.php");
require_once 'templates/header.php';

// Handle Tambah/Edit Mahasiswa
// THIS BLOCK MUST BE BEFORE ANY HTML OUTPUT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = $_POST['id'] ?? '';
  $nama = $_POST['nama'];
  $email = $_POST['email'];
  $password = password_hash('123456', PASSWORD_DEFAULT); // Default password

  if ($id) {
    $stmt = $conn->prepare("UPDATE users SET nama=?, email=? WHERE id=? AND role='mahasiswa'");
    $stmt->bind_param("ssi", $nama, $email, $id);
  } else {
    $stmt = $conn->prepare("INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, 'mahasiswa')");
    $stmt->bind_param("sss", $nama, $email, $password);
  }
  $stmt->execute();
  header("Location: Kelola_Mahasiswa.php"); // THIS IS THE LINE 21
  exit;
}

// Handle Hapus
// THIS BLOCK ALSO MUST BE BEFORE ANY HTML OUTPUT
if (isset($_GET['hapus'])) {
  $hapus_id = $_GET['hapus'];
  $conn->query("DELETE FROM users WHERE id=$hapus_id AND role='mahasiswa'");
  header("Location: Kelola_Mahasiswa.php");
  exit;
}

// Ambil data mahasiswa - This can be here as it's just data retrieval
$result = $conn->query("SELECT * FROM users WHERE role='mahasiswa' ORDER BY created_at DESC");
$dataMahasiswa = $result->fetch_all(MYSQLI_ASSOC);
?>

<main class="flex-grow p-8 overflow-auto">
  <h2 class="text-3xl font-bold text-gray-800 mb-6">Kelola Mahasiswa</h2>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
    <section class="bg-white p-6 rounded shadow">
      <h3 class="text-xl font-semibold mb-4">Tambah / Edit Mahasiswa</h3>
      <form action="" method="POST" class="space-y-4">
        <input type="hidden" name="id" id="id">
        <div>
          <label class="block font-medium">Nama</label>
          <input type="text" name="nama" id="nama" class="w-full border border-gray-300 p-2 rounded" required>
        </div>
        <div>
          <label class="block font-medium">Email</label>
          <input type="email" name="email" id="email" class="w-full border border-gray-300 p-2 rounded" required>
        </div>
        <p class="text-sm text-gray-500">Password default: <strong>123456</strong></p>
        <div>
          <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Simpan</button>
          <button type="reset" onclick="resetForm()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded ml-2">Reset</button>
        </div>
      </form>
    </section>
  </div>

  <section class="bg-white p-6 rounded shadow">
    <h3 class="text-xl font-semibold mb-4">Daftar Mahasiswa</h3>
    <div class="overflow-x-auto">
      <table class="min-w-full border border-gray-300 text-sm">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-4 py-2 border">Nama</th>
            <th class="px-4 py-2 border">Email</th>
            <th class="px-4 py-2 border">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($dataMahasiswa as $mhs): ?>
            <tr>
              <td class="px-4 py-2 border"><?= htmlspecialchars($mhs['nama']) ?></td>
              <td class="px-4 py-2 border"><?= htmlspecialchars($mhs['email']) ?></td>
              <td class="px-4 py-2 border space-x-2">
                <button onclick="editMahasiswa(<?= $mhs['id'] ?>, '<?= htmlspecialchars($mhs['nama']) ?>', '<?= htmlspecialchars($mhs['email']) ?>')" class="bg-yellow-400 text-white px-3 py-1 rounded hover:bg-yellow-500">Edit</button>
                <a href="?hapus=<?= $mhs['id'] ?>" onclick="return confirm('Yakin ingin hapus?')" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">Hapus</a>
              </td>
            </tr>
          <?php endforeach ?>
          <?php if (count($dataMahasiswa) === 0): ?>
            <tr><td colspan="3" class="text-center text-gray-500 py-4">Belum ada mahasiswa.</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<script>
function editMahasiswa(id, nama, email) {
  document.getElementById('id').value = id;
  document.getElementById('nama').value = nama;
  document.getElementById('email').value = email;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function resetForm() {
  document.getElementById('id').value = '';
  document.getElementById('nama').value = '';
  document.getElementById('email').value = '';
}
</script>

<?php include 'templates/footer.php'; ?>