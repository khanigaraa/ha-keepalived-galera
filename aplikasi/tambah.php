<?php
/**
 * Formulir tambah judul.
 */

require __DIR__ . '/koneksi.php';

$pesan = null;
$jenis = 'sukses';
$isi = ['nim' => '', 'tahun' => date('Y'), 'semester' => 'GANJIL',
        'nama_mahasiswa' => '', 'judul_skripsi' => '', 'nama_pembimbing' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($isi as $k => $_) {
        $isi[$k] = trim($_POST[$k] ?? '');
    }

    $kosong = array_filter($isi, static fn($v) => $v === '');

    if ($kosong) {
        $pesan = 'Seluruh isian wajib diisi.';
        $jenis = 'galat';
    } else {
        try {
            $pdo = koneksiDB();
            $stmt = $pdo->prepare(
                'INSERT INTO judul_ta
                   (nim, tahun, semester, nama_mahasiswa, judul_skripsi, nama_pembimbing)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $isi['nim'],
                (int) $isi['tahun'],
                $isi['semester'],
                strtoupper($isi['nama_mahasiswa']),
                $isi['judul_skripsi'],
                $isi['nama_pembimbing'],
            ]);

            $d = ambilDiagnostik();
            $pesan = 'Data berhasil disimpan melalui node basis data '
                   . htmlspecialchars($d['node_basis'])
                   . '. Periksa halaman penelusuran pada node lain untuk '
                   . 'memastikan data telah direplikasi.';
            $isi = ['nim' => '', 'tahun' => date('Y'), 'semester' => 'GANJIL',
                    'nama_mahasiswa' => '', 'judul_skripsi' => '', 'nama_pembimbing' => ''];

        } catch (PDOException $e) {
            $pesan = 'Gagal menyimpan: ' . $e->getMessage();
            $jenis = 'galat';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tambah Judul — Replika Simulasi</title>
<link rel="stylesheet" href="gaya.css">
</head>
<body>

<header class="utama">
  <h1>Tambah Judul Tugas Akhir</h1>
  <p>Operasi tulis untuk pengujian replikasi sinkron antar-node</p>
</header>

<nav class="tautan">
  <a href="index.php">Penelusuran Judul</a>
  <a href="tambah.php">Tambah Judul</a>
</nav>

<main>
  <div class="kartu" style="max-width:760px">

    <?php if ($pesan): ?>
      <div class="pesan <?= $jenis ?>"><?= $pesan ?></div>
    <?php endif; ?>

    <form method="post">
      <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px">
        <div>
          <label for="nim">NIM</label>
          <input type="text" name="nim" id="nim" maxlength="15"
                 value="<?= htmlspecialchars($isi['nim']) ?>">
        </div>
        <div>
          <label for="tahun">Tahun</label>
          <input type="number" name="tahun" id="tahun" min="2000" max="2100"
                 value="<?= htmlspecialchars((string) $isi['tahun']) ?>">
        </div>
        <div>
          <label for="semester">Semester</label>
          <select name="semester" id="semester">
            <option value="GANJIL" <?= $isi['semester'] === 'GANJIL' ? 'selected' : '' ?>>GANJIL</option>
            <option value="GENAP"  <?= $isi['semester'] === 'GENAP'  ? 'selected' : '' ?>>GENAP</option>
          </select>
        </div>
      </div>

      <div style="margin-top:14px">
        <label for="nama_mahasiswa">Nama Mahasiswa</label>
        <input type="text" name="nama_mahasiswa" id="nama_mahasiswa" maxlength="100"
               value="<?= htmlspecialchars($isi['nama_mahasiswa']) ?>">
      </div>

      <div style="margin-top:14px">
        <label for="judul_skripsi">Judul Skripsi</label>
        <textarea name="judul_skripsi" id="judul_skripsi"><?= htmlspecialchars($isi['judul_skripsi']) ?></textarea>
      </div>

      <div style="margin-top:14px">
        <label for="nama_pembimbing">Nama Pembimbing</label>
        <input type="text" name="nama_pembimbing" id="nama_pembimbing" maxlength="100"
               value="<?= htmlspecialchars($isi['nama_pembimbing']) ?>">
      </div>

      <div style="margin-top:20px">
        <button type="submit">Simpan</button>
        <a href="index.php" class="tombol" style="background:#6b7280">Kembali</a>
      </div>
    </form>

  </div>
</main>

<?php require __DIR__ . '/diagnostik.php'; ?>
</body>
</html>
