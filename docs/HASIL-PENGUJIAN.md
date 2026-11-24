# Hasil pengujian

Semua angka di halaman ini berasal dari skripsi. Untuk tiap tabel disebutkan sumbernya: **log di repo** ([`hasil-uji/log-pengujian.txt`](../hasil-uji/log-pengujian.txt), bagian C.x) atau **Bab IV skripsi** (log mentahnya tidak ikut di repo).

## Failover saat node mati mendadak

Node1 (MASTER) dimatikan paksa dengan `echo b > /proc/sysrq-trigger`. Dari node3, Virtual IP di-ping tiap 0,2 detik. Waktu failover dihitung dari jeda balasan ping.

| Percobaan | p1 | p2 | p3 | p4 | p5 | **Rata-rata** |
|---|---|---|---|---|---|---|
| Waktu failover (detik) | 3,32 | 3,32 | 3,17 | 3,32 | 3,11 | **3,25** |

Sumber: log di repo, C.1. Rata-rata dihitung dari lima nilai tersebut.

## Failover saat layanan Nginx berhenti

Di node1 dijalankan `systemctl stop nginx`, lalu 20 detik kemudian `systemctl start nginx`. Dari node3, halaman di Virtual IP diminta lewat HTTP tiap 0,5 detik.

| Percobaan | Permintaan gagal | Waktu failover | Waktu kembali ke node1 |
|---|---|---|---|
| p1 | 7 | 4,27 s | 0,54 s |
| p2 | 7 | 4,20 s | 0,53 s |
| p3 | 11 | 6,29 s | 0,55 s |
| p4 | 9 | 5,25 s | 0,54 s |
| p5 | 9 | 5,27 s | 0,58 s |
| **Rata-rata** | **8,6** | **5,06 s** | **0,55 s** |

Sumber: log di repo, C.2.

## Pengaruh penyetelan Keepalived

Selang pemeriksaan kesehatan (`interval` di `vrrp_script`) dan selang advertisement VRRP (`advert_int`) diubah, lalu skenario Nginx berhenti diulang lima kali.

| Konfigurasi | Selang pemeriksaan | Selang advertisement | Rata-rata failover |
|---|---|---|---|
| 1 | 2 detik | 1 detik | 6,65 s |
| 2 | 1 detik | 1 detik | 4,87 s |
| 3 (dipakai di repo) | 1 detik | 0,5 detik | 5,06 s |

Sumber: log di repo, C.6 (konfigurasi 1 dan 2) dan C.2 (konfigurasi 3). Mempercepat pemeriksaan kesehatan dari 2 ke 1 detik jelas berpengaruh. Menurunkan selang advertisement dari 1 ke 0,5 detik tidak membuat failover lebih cepat pada lima percobaan ini.

## Uji beban

Diuji dengan Apache JMeter dari komputer di luar server: 100 pengguna simultan dengan penambahan bertahap selama 10 detik, masing-masing 10 permintaan (total 1.000 permintaan).

| Parameter | Halaman penelusuran (PHP + basis data) | Titik cek kesehatan `/sehat` (Nginx saja) |
|---|---|---|
| Waktu tanggap rata-rata | 21 ms | 9 ms |
| Waktu tanggap minimum | 13 ms | 7 ms |
| Waktu tanggap maksimum | 66 ms | 40 ms |
| Tingkat kegagalan | 0% | 0% |
| Throughput | 99,3 permintaan/detik | 100,1 permintaan/detik |

Sumber: Bab IV skripsi (Tabel 4.28). Setiap node pelayan memakai 2 inti prosesor.

## Ketersediaan

Selama 24 jam pengujian (86.400 detik), layanan tidak menjawab total **41,55 detik**: 5 × 3,25 detik dari failover node, ditambah 5 × 5,06 detik dari failover layanan.

```text
(86.400 − 41,55) / 86.400 × 100% = 99,9519%
```

Sebagai pembanding, sistem lama (satu server) tercatat mengalami gangguan sekitar 180 menit selama 2 hari pengamatan (8–9 Oktober 2025), atau 93,750% pada periode itu.

> **Catatan penting:** kedua angka diukur pada periode yang berbeda (24 jam vs 2 hari), jadi **tidak bisa dibandingkan langsung** sebagai satu deret. Perbandingan yang sahih adalah waktu pemulihan per kejadian: sekitar 1–2 jam dan manual di sistem lama, menjadi 3,25 detik (node mati) atau 5,06 detik (layanan berhenti) dan otomatis di sistem baru.

Sumber: Bab IV skripsi (Tabel 4.43 dan 4.44).

## Bukti lain di log

| Bagian log | Isi |
|---|---|
| C.3 | `wsrep_cluster_size = 3` dan status `Primary` di ketiga node |
| C.4 | Pencadangan `mariabackup` harian lewat cron, dari 7 sampai 13 Agustus 2026 |
| C.5 | `tcpdump` yang menunjukkan paket VRRP dibungkus VXLAN, dan bahwa ARP ke alamat asli sampai ke node1 sedangkan ARP ke Virtual IP tidak terlihat sama sekali (lihat [VXLAN.md](VXLAN.md)) |
