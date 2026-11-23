<?php
/**
 * Bilah penanda diagnostik.
 * diamati secara langsung melalui antarmuka aplikasi.
 */
$d = ambilDiagnostik();
?>
<div class="diagnostik">
  <span>Dilayani oleh: <b><?= htmlspecialchars($d['server_web']) ?></b></span>
  <span>Node basis data: <b><?= htmlspecialchars($d['node_basis']) ?></b></span>
  <span>Ukuran klaster: <b><?= htmlspecialchars($d['ukuran_klaster']) ?></b></span>
  <span>Status klaster: <b><?= htmlspecialchars($d['status_klaster']) ?></b></span>
  <span class="cap">Waktu: <?= date('H:i:s') ?></span>
</div>
