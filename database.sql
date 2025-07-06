CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('mahasiswa','asisten') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabel Mata Praktikum
CREATE TABLE mata_praktikum (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_praktikum VARCHAR(10) UNIQUE NOT NULL,
    nama_praktikum VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    semester INT NOT NULL,
    sks INT DEFAULT 1,
    asisten_id INT,
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asisten_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 3. Tabel Modul/Pertemuan
CREATE TABLE modul (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mata_praktikum_id INT NOT NULL,
    judul_modul VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    pertemuan_ke INT NOT NULL,
    file_materi VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mata_praktikum_id) REFERENCES mata_praktikum(id) ON DELETE CASCADE
);

-- 4. Tabel Pendaftaran Mahasiswa ke Praktikum
CREATE TABLE pendaftaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mahasiswa_id INT NOT NULL,
    mata_praktikum_id INT NOT NULL,
    tanggal_daftar TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('aktif', 'lulus', 'mengulang') DEFAULT 'aktif',
    UNIQUE KEY unique_pendaftaran (mahasiswa_id, mata_praktikum_id),
    FOREIGN KEY (mahasiswa_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mata_praktikum_id) REFERENCES mata_praktikum(id) ON DELETE CASCADE
);

-- 5. Tabel Laporan/Tugas
CREATE TABLE laporan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mahasiswa_id INT NOT NULL,
    modul_id INT NOT NULL,
    file_laporan VARCHAR(255) NOT NULL,
    tanggal_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('belum_dinilai', 'sudah_dinilai') DEFAULT 'belum_dinilai',
    UNIQUE KEY unique_laporan (mahasiswa_id, modul_id),
    FOREIGN KEY (mahasiswa_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (modul_id) REFERENCES modul(id) ON DELETE CASCADE
);

-- 6. Tabel Nilai
CREATE TABLE nilai (
    id INT AUTO_INCREMENT PRIMARY KEY,
    laporan_id INT NOT NULL,
    nilai DECIMAL(5,2) NOT NULL,
    feedback TEXT,
    dinilai_oleh INT NOT NULL,
    tanggal_nilai TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (laporan_id) REFERENCES laporan(id) ON DELETE CASCADE,
    FOREIGN KEY (dinilai_oleh) REFERENCES users(id) ON DELETE CASCADE
);