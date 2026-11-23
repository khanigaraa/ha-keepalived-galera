<?php
/**
 * Halaman penelusuran judul tugas akhir (replika simulasi).
 * Modul ini merupakan bagian yang diamati dalam penelitian.
 */

require __DIR__ . '/koneksi.php';

$waktuMulai = microtime(true);

// --- Parameter permintaan -------------------------------------------------
$cari    = trim($_GET['cari'] ?? '');
$urut    = $_GET['urut'] ?? 'tahun';
$arah    = strtolower($_GET['arah'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$perHal  = (int) ($_GET['per'] ?? 10);
$halaman = max(1, (int) ($_GET['hal'] ?? 1));

$kolomBoleh = ['nim', 'tahun', 'semester', 'nama_mahasiswa', 'judul_skripsi', 'nama_pembimbing'];
if (!in_array($urut, $kolomBoleh, true)) {
    $urut = 'tahun';
}
if (!in_array($perHal, [10, 25, 50, 100], true)) {
    $perHal = 10;
}

$galat = null;
$baris = [];
$total = 0;

try {
    $pdo = koneksiDB();

    $where  = '';
    $params = [];
    if ($cari !== '') {
        $where = 'WHERE nama_mahasiswa LIKE ? OR judul_skripsi LIKE ? OR nim LIKE ?';
        $kunci = '%' . $cari . '%';
        $params = [$kunci, $kunci, $kunci];
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM judul_ta {$where}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    $totalHal = max(1, (int) ceil($total / $perHal));
    $halaman  = min($halaman, $totalHal);
    $offset   = ($halaman - 1) * $perHal;

    $sql = "SELECT nim, tahun, semester, nama_mahasiswa, judul_skripsi, nama_pembimbing
            FROM judul_ta
            {$where}
            ORDER BY {$urut} {$arah}
            LIMIT {$perHal} OFFSET {$offset}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $baris = $stmt->fetchAll();

} catch (PDOException $e) {
    $galat = $e->getMessage();
    $totalHal = 1;
}

function tautanUrut(string $kolom, string $label, string $urutKini, string $arahKini): string
{
    $arahBaru = ($urutKini === $kolom && strtoupper($arahKini) === 'ASC') ? 'desc' : 'asc';
    $q = $_GET;
    $q['urut'] = $kolom;
    $q['arah'] = $arahBaru;
    $q['hal']  = 1;
    $tanda = $urutKini === $kolom ? (strtoupper($arahKini) === 'ASC' ? ' ▲' : ' ▼') : '';
    return '<a href="?' . htmlspecialchars(http_build_query($q)) . '">'
         . htmlspecialchars($label) . $tanda . '</a>';
}

function tautanHalaman(int $h): string
{
    $q = $_GET;
    $q['hal'] = $h;
    return '?' . htmlspecialchars(http_build_query($q));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Penelusuran Judul Tugas Akhir — Replika Simulasi</title>
<link rel="stylesheet" href="gaya.css">
</head>
<body>

<header class="utama">
  <h1>Penelusuran Judul Tugas Akhir</h1>
  <p>Replika simulasi untuk pengujian sistem <em>high availability</em> — data bersifat buatan</p>
</header>

<nav class="tautan">
  <a href="index.php">Penelusuran Judul</a>
  <a href="tambah.php">Tambah Judul</a>
</nav>

<main>
  <div class="kartu">

    <?php if ($galat): ?>
      <div class="pesan galat">
        Gagal mengambil data: <?= htmlspecialchars($galat) ?>
      </div>
    <?php endif; ?>

    <form method="get" class="baris-alat">
      <div style="width:150px">
        <label for="per">Tampilkan baris</label>
        <select name="per" id="per" onchange="this.form.submit()">
          <?php foreach ([10, 25, 50, 100] as $n): ?>
            <option value="<?= $n ?>" <?= $perHal === $n ? 'selected' : '' ?>><?= $n ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="flex:1; min-width:260px">
        <label for="cari">Cari mahasiswa, NIM, atau judul</label>
        <input type="text" name="cari" id="cari"
               value="<?= htmlspecialchars($cari) ?>"
               placeholder="ketik kata kunci lalu tekan Enter">
      </div>

      <input type="hidden" name="urut" value="<?= htmlspecialchars($urut) ?>">
      <input type="hidden" name="arah" value="<?= htmlspecialchars(strtolower($arah)) ?>">
      <div><button type="submit">Cari</button></div>
    </form>

    <table>
      <thead>
        <tr>
          <th style="width:100px"><?= tautanUrut('nim', 'NIM', $urut, $arah) ?></th>
          <th style="width:75px"><?= tautanUrut('tahun', 'Tahun', $urut, $arah) ?></th>
          <th style="width:95px"><?= tautanUrut('semester', 'Semester', $urut, $arah) ?></th>
          <th style="width:190px"><?= tautanUrut('nama_mahasiswa', 'Nama Mahasiswa', $urut, $arah) ?></th>
          <th><?= tautanUrut('judul_skripsi', 'Judul Skripsi', $urut, $arah) ?></th>
          <th style="width:190px"><?= tautanUrut('nama_pembimbing', 'Nama Pembimbing', $urut, $arah) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$baris): ?>
          <tr><td colspan="6" style="text-align:center; padding:26px; color:#6b7280">
            Tidak ada data yang cocok.
          </td></tr>
        <?php else: foreach ($baris as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['nim']) ?></td>
            <td><?= htmlspecialchars((string) $r['tahun']) ?></td>
            <td><?= htmlspecialchars($r['semester']) ?></td>
            <td><?= htmlspecialchars($r['nama_mahasiswa']) ?></td>
            <td><?= htmlspecialchars($r['judul_skripsi']) ?></td>
            <td><?= htmlspecialchars($r['nama_pembimbing']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>

    <?php
      $awal = $total ? (($halaman - 1) * $perHal + 1) : 0;
      $akhir = min($halaman * $perHal, $total);
    ?>
    <div class="halaman">
      <span class="ket-jumlah">
        Menampilkan <?= $awal ?> sampai <?= $akhir ?> dari <?= number_format($total, 0, ',', '.') ?> data
      </span>

      <?php if ($halaman > 1): ?>
        <a href="<?= tautanHalaman($halaman - 1) ?>">Sebelumnya</a>
      <?php endif; ?>

      <?php
        $dari = max(1, $halaman - 2);
        $ke   = min($totalHal, $halaman + 2);
        for ($h = $dari; $h <= $ke; $h++):
      ?>
        <?php if ($h === $halaman): ?>
          <span class="kini"><?= $h ?></span>
        <?php else: ?>
          <a href="<?= tautanHalaman($h) ?>"><?= $h ?></a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($halaman < $totalHal): ?>
        <a href="<?= tautanHalaman($halaman + 1) ?>">Berikutnya</a>
      <?php endif; ?>
    </div>

    <p style="margin-top:14px; font-size:12.5px; color:#6b7280">
      Waktu proses kueri: <?= number_format((microtime(true) - $waktuMulai) * 1000, 2) ?> ms
    </p>

  </div>
</main>

<?php require __DIR__ . '/diagnostik.php'; ?>
</body>
</html>
