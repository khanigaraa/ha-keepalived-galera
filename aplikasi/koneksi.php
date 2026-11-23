<?php
/**
 * Koneksi basis data dan fungsi penanda diagnostik.
 *
 * Koneksi diarahkan ke 127.0.0.1 — setiap web server memakai node
 * basis data yang menyertainya. Ini sesuai pola akses aktif-pasif
 * yang ditetapkan pada perancangan arsitektur.
 */

const DB_HOST = '127.0.0.1';
const DB_NAMA = 'ifjudul_simulasi';
const DB_USER = 'ifjudul';
const DB_SANDI = 'GANTI_DENGAN_SANDI';   // samakan dengan 01_skema.sql

function koneksiDB(): PDO
{
static $pdo = null;

if ($pdo === null) {
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAMA . ';charset=utf8mb4';
$pdo = new PDO($dsn, DB_USER, DB_SANDI, [
PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
PDO::ATTR_EMULATE_PREPARES   => false,
PDO::ATTR_TIMEOUT            => 5,
]);
}

return $pdo;
}

function ambilDiagnostik(): array
{
$info = [
'server_web'   => gethostname() ?: 'tidak diketahui',
'node_basis'   => 'tidak tersedia',
'ukuran_klaster' => '-',
'status_klaster' => '-',
];

try {
$pdo = koneksiDB();

$row = $pdo->query("SHOW VARIABLES LIKE 'wsrep_node_name'")->fetch();
if ($row) {
$info['node_basis'] = $row['Value'];
}

$row = $pdo->query("SHOW STATUS LIKE 'wsrep_cluster_size'")->fetch();
if ($row) {
$info['ukuran_klaster'] = $row['Value'];
}

$row = $pdo->query("SHOW STATUS LIKE 'wsrep_cluster_status'")->fetch();
if ($row) {
$info['status_klaster'] = $row['Value'];
}
} catch (PDOException $e) {
$info['node_basis'] = 'gagal terhubung';
}

return $info;
}
