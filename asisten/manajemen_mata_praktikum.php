<?php
session_start();

// Cek login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'asisten') {
    header('Location: login.php');
    exit;
}

// Koneksi ke database
$conn = new mysqli('localhost', 'root', '', 'db_simprak');
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// CRUD operasi
if (isset($_POST['action'])) {
    $nama = $conn->real_escape_string($_POST['nama_praktikum']);
    if ($_POST['action'] === 'create') {
        $conn->query("INSERT INTO mata_praktikum (nama_praktikum) VALUES ('$nama')");
    } elseif ($_POST['action'] === 'update') {
        $id = intval($_POST['id']);
        $conn->query("UPDATE mata_praktikum SET nama_praktikum = '$nama' WHERE id = $id");
    }
    header('Location: manajemen_mata_praktikum.php');
    exit;
} elseif (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM mata_praktikum WHERE id = $id");
    header('Location: manajemen_mata_praktikum.php');
    exit;
}

// Ambil data dari DB
$data = $conn->query("SELECT * FROM mata_praktikum");

// Konfigurasi untuk template
$pageTitle = "Manajemen Mata Praktikum";
$activePage = "manajemen_mata_praktikum";

// Include template header
require_once 'templates/header.php';
?>

<!-- Konten utama -->
<div class="bg-white p-6 rounded-lg shadow">
    <h2 class="text-xl font-semibold mb-4">Manajemen Mata Praktikum</h2>

    <form method="POST" class="bg-gray-100 p-4 rounded-lg mb-6">
        <input type="hidden" name="id" id="formId">
        <label class="block mb-2 font-medium">Nama Praktikum</label>
        <input type="text" name="nama_praktikum" id="formNama" class="border p-2 w-full rounded mb-4" required>
        <div class="flex gap-2">
            <button type="submit" name="action" value="create" id="btnCreate" class="bg-blue-600 text-white px-4 py-2 rounded">Tambah</button>
            <button type="submit" name="action" value="update" id="btnUpdate" class="bg-yellow-500 text-white px-4 py-2 rounded hidden">Update</button>
            <button type="button" onclick="resetForm()" class="bg-gray-400 px-4 py-2 rounded">Batal</button>
        </div>
    </form>

    <table class="w-full bg-white rounded shadow overflow-hidden">
        <thead class="bg-gray-200">
            <tr>
                <th class="px-4 py-2 text-left">ID</th>
                <th class="px-4 py-2 text-left">Nama Praktikum</th>
                <th class="px-4 py-2 text-left">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $data->fetch_assoc()): ?>
            <tr class="border-t">
                <td class="px-4 py-2"><?= $row['id'] ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($row['nama_praktikum']) ?></td>
                <td class="px-4 py-2">
                    <button onclick="editData(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nama_praktikum']) ?>')" class="text-yellow-600 hover:underline">Edit</button>
                    <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Hapus data ini?')" class="text-red-600 hover:underline ml-2">Hapus</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
function editData(id, nama) {
    document.getElementById('formId').value = id;
    document.getElementById('formNama').value = nama;
    document.getElementById('btnCreate').classList.add('hidden');
    document.getElementById('btnUpdate').classList.remove('hidden');
}
function resetForm() {
    document.getElementById('formId').value = '';
    document.getElementById('formNama').value = '';
    document.getElementById('btnCreate').classList.remove('hidden');
    document.getElementById('btnUpdate').classList.add('hidden');
}
</script>

<?php require_once 'templates/footer.php'; ?>
