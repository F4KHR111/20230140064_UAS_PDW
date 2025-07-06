<?php
// ======  Autentikasi & Header  ======
$pageTitle  = 'Cari Praktikum';
$activePage = 'courses';
require_once 'templates/header_mahasiswa.php';
require_once '../config.php';        // $conn

// ---------- 1.  Variabel umum ----------
$mahasiswaId = $_SESSION['user_id'];

// ---------- 2.  Handle Aksi CRUD ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ===== CREATE (DAFTAR) =====
    if ($_POST['action'] === 'daftar' && isset($_POST['praktikum_id'])) {
        $pid = (int)$_POST['praktikum_id'];

        // Cegah dobel daftar
        $cek = $conn->prepare("SELECT id FROM pendaftaran WHERE mahasiswa_id=? AND mata_praktikum_id=?");
        $cek->bind_param('ii', $mahasiswaId, $pid);
        $cek->execute();
        $cek->store_result();

        if ($cek->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO pendaftaran (mahasiswa_id, mata_praktikum_id, status) VALUES (?, ?, 'aktif')");
            $stmt->bind_param('ii', $mahasiswaId, $pid);
            $stmt->execute();
            $stmt->close();
        }
        $cek->close();
    }

    // ===== DELETE (BATALKAN) =====
    if ($_POST['action'] === 'batal' && isset($_POST['pendaftaran_id'])) {
        $rid = (int)$_POST['pendaftaran_id'];
        // Pastikan pendaftaran milik mahasiswa ini
        $stmt = $conn->prepare("DELETE FROM pendaftaran WHERE id=? AND mahasiswa_id=?");
        $stmt->bind_param('ii', $rid, $mahasiswaId);
        $stmt->execute();
        $stmt->close();
    }

    // ===== UPDATE STATUS (opsional) =====
    if ($_POST['action'] === 'update' && isset($_POST['pendaftaran_id'], $_POST['status'])) {
        $rid = (int)$_POST['pendaftaran_id'];
        $status = $_POST['status']; // aktif / mengulang / lulus
        $stmt = $conn->prepare("UPDATE pendaftaran SET status=? WHERE id=? AND mahasiswa_id=?");
        $stmt->bind_param('sii', $status, $rid, $mahasiswaId);
        $stmt->execute();
        $stmt->close();
    }
}

// ---------- 3.  Pencarian ----------
$keyword = $_GET['q'] ?? '';
$sql = "
    SELECT mp.*,
           COALESCE(p.id, 0) AS pendaftaran_id,
           p.status          AS status_pendaftaran
    FROM mata_praktikum mp
    LEFT JOIN pendaftaran p
      ON p.mata_praktikum_id = mp.id AND p.mahasiswa_id = ?
    WHERE mp.kode_praktikum LIKE ? OR mp.nama_praktikum LIKE ?
    ORDER BY mp.nama_praktikum ASC
";
$like = '%'.$keyword.'%';
$stmt = $conn->prepare($sql);
$stmt->bind_param('sss', $mahasiswaId, $like, $like);
$stmt->execute();
$result = $stmt->get_result();
$rows   = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!-- ======  Konten Utama  ====== -->
<h1 class="text-2xl font-bold mb-6">Cari Praktikum</h1>

<form method="get" class="flex mb-6">
  <input type="text" name="q" value="<?= htmlspecialchars($keyword) ?>"
         placeholder="Ketik nama / kode praktikum..."
         class="w-full p-2 border border-gray-300 rounded-l-md focus:outline-none focus:ring-2 focus:ring-blue-500">
  <button class="px-4 bg-blue-600 text-white rounded-r-md">Cari</button>
</form>

<div class="overflow-x-auto">
  <table class="min-w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 uppercase text-sm">
      <tr>
        <th class="py-3 px-6 text-left">Kode</th>
        <th class="py-3 px-6 text-left">Nama</th>
        <th class="py-3 px-6">Kuota</th>
        <th class="py-3 px-6 text-center">Aksi</th>
      </tr>
    </thead>
    <tbody class="text-gray-700 dark:text-gray-200 text-sm">
    <?php if (!$rows): ?>
      <tr><td colspan="4" class="py-6 text-center">Tidak ada praktikum ditemukan.</td></tr>
    <?php endif; ?>

    <?php foreach ($rows as $row): ?>
      <?php
        $terdaftar = $row['pendaftaran_id'] != 0;
        $kuota = ($row['kuota_max'] ?? 0) ? "{$row['kuota_terisi']}/{$row['kuota_max']}" : '-';
      ?>
      <tr class="border-b border-gray-200 dark:border-gray-700">
        <td class="py-3 px-6"><?= htmlspecialchars($row['kode_praktikum']) ?></td>
        <td class="py-3 px-6"><?= htmlspecialchars($row['nama_praktikum']) ?></td>
        <td class="py-3 px-6 text-center"><?= $kuota ?></td>
        <td class="py-3 px-6 text-center">
          <?php if (!$terdaftar): ?>
            <!-- Tombol DAFTAR (Create) -->
            <form method="post" class="inline">
              <input type="hidden" name="action" value="daftar">
              <input type="hidden" name="praktikum_id" value="<?= $row['id'] ?>">
              <button class="px-3 py-1 bg-green-500 hover:bg-green-600 text-white rounded-md text-xs"
                      onclick="return confirm('Daftar ke praktikum ini?')">
                Daftar
              </button>
            </form>
          <?php else: ?>
            <!-- Tombol BATAL (Delete) -->
            <form method="post" class="inline">
              <input type="hidden" name="action" value="batal">
              <input type="hidden" name="pendaftaran_id" value="<?= $row['pendaftaran_id'] ?>">
              <button class="px-3 py-1 bg-red-500 hover:bg-red-600 text-white rounded-md text-xs"
                      onclick="return confirm('Batalkan pendaftaran ini?')">
                Batal
              </button>
            </form>
            <!-- Tombol UPDATE status (opsional) -->
            <form method="post" class="inline">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="pendaftaran_id" value="<?= $row['pendaftaran_id'] ?>">
              <select name="status" onchange="this.form.submit()"
                      class="text-xs bg-gray-100 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-md px-2 py-1 ml-2">
                <?php
                  $statuses = ['aktif','mengulang','lulus'];
                  foreach ($statuses as $s) {
                    $sel = $s == $row['status_pendaftaran'] ? 'selected' : '';
                    echo "<option value='$s' $sel>$s</option>";
                  }
                ?>
              </select>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php
// ====== Footer ======
require_once 'templates/footer_mahasiswa.php';
?>
