# High Availability Multi Node: Keepalived + Galera Cluster

Implementasi sistem **high availability** tiga node untuk aplikasi web PHP. Kalau satu server mati, layanan pindah ke server lain **otomatis dalam ±3 detik**, dan data tetap sama di semua node.

*English: A three-node high-availability setup (Keepalived VRRP failover, MariaDB Galera synchronous replication, Nginx, Prometheus) with a VXLAN overlay to make the Virtual IP work on a cloud network that filters ARP. Measured availability: 99.95%.*

> **Proyek skripsi S1 Teknik Informatika (2026).** Repo ini berisi berkas konfigurasi, skrip, kode aplikasi replika, dan log pengujian dari lampiran skripsi. Aplikasinya adalah **replika simulasi** dengan data buatan, dan semua kata sandi sudah diganti placeholder.

## Hasil pengujian

| Pengujian | Hasil |
|---|---|
| Failover saat **node mati mendadak** (5 percobaan) | rata-rata **3,25 detik** |
| Failover saat **layanan Nginx berhenti** (5 percobaan) | rata-rata **5,06 detik**, pulih kembali 0,55 detik |
| Beban 100 pengguna simultan | waktu tanggap rata-rata **21 ms**, 0% gagal |
| Konsistensi data | replikasi sinkron di 3 node; node yang pulih bergabung lagi lewat IST |
| **Ketersediaan terukur** | **99,9519%** (sebelumnya satu server: 93,75% pada periode insiden) |

Log mentahnya ada di [`hasil-uji/log-pengujian.txt`](hasil-uji/log-pengujian.txt).

## Arsitektur

```text
                     Pengguna
                        │
              Virtual IP 10.200.0.100   ← dipegang node MASTER (Keepalived/VRRP)
                        │
   ┌────────── jaringan overlay VXLAN 10.200.0.0/24 (vni 100) ──────────┐
   │                        │                          │
 node1 (MASTER)          node2 (BACKUP)              node3
 Nginx + PHP-FPM         Nginx + PHP-FPM             Prometheus + Grafana
 Keepalived prio 200     Keepalived prio 100         skrip backup harian
 MariaDB Galera ◄──────► MariaDB Galera ◄──────────► MariaDB Galera
```

- **Keepalived** memindahkan Virtual IP ke node2 kalau node1 mati, atau kalau Nginx di node1 gagal menjawab `/sehat` dua kali berturut-turut.
- **MariaDB Galera Cluster** mereplikasi data secara sinkron ke tiga node. Node ketiga menjaga kuorum supaya tidak terjadi *split-brain*.
- **Prometheus + node_exporter** memantau ketiga node.

### Masalah menarik: Virtual IP tidak terjangkau

Di jaringan penyedia cloud, paket ARP untuk alamat yang tidak terdaftar (termasuk Virtual IP) **disaring**, jadi node lain tidak bisa menjangkau Virtual IP. Solusinya adalah membangun **jaringan overlay VXLAN** di atas jaringan privat. VRRP dan Virtual IP berjalan di dalam overlay, sehingga tidak lagi disaring. Bukti pengamatan `tcpdump` ada di bagian C.5 log pengujian.

## Isi repo

```text
konfigurasi/
├── node1/   vxlan0.service, 70-galera-ifjudul.cnf, nginx-ifjudul.conf, keepalived.conf, cek_nginx.sh
├── node2/   (sama seperti node1, sebagai BACKUP)
└── node3/   vxlan0.service, 70-galera-ifjudul.cnf, prometheus.yml, backup_galera.sh, crontab.txt
skrip/
└── galera_autorecover.sh   # pulihkan klaster setelah ketiga node mati bersamaan
aplikasi/                   # aplikasi replika (PHP 8.3 + PDO)
├── 01_skema.sql
├── koneksi.php, index.php, tambah.php, diagnostik.php
└── gaya.css
hasil-uji/
└── log-pengujian.txt       # log failover, verifikasi klaster, backup, tcpdump, penyetelan parameter
```

| Berkas | Lokasi di server |
|---|---|
| `vxlan0.service` | `/etc/systemd/system/vxlan0.service` |
| `70-galera-ifjudul.cnf` | `/etc/mysql/mariadb.conf.d/70-galera-ifjudul.cnf` |
| `nginx-ifjudul.conf` | `/etc/nginx/sites-available/ifjudul` |
| `keepalived.conf`, `cek_nginx.sh` | `/etc/keepalived/` |
| `prometheus.yml` | `/etc/prometheus/prometheus.yml` |
| `backup_galera.sh`, `galera_autorecover.sh` | `/usr/local/bin/` |

## Teknologi

Ubuntu 24.04 LTS · MariaDB 10.11 Galera Cluster · Keepalived (VRRP, unicast) · Nginx · PHP 8.3-FPM · VXLAN · Prometheus · Grafana · mariabackup · Bash

## Catatan kalau mau mencoba

- Ganti semua `GANTI_DENGAN_SANDI` dan `GANTI123` dengan sandi sendiri. Sandi VRRP (`auth_pass`) maksimal 8 karakter dan harus sama di node1 dan node2.
- Sesuaikan IP privat (`10.10.x.x`) dan nama interface (`eth1`) dengan jaringan kamu.
- Klaster Galera pertama kali dinyalakan dengan `galera_new_cluster` di node1, lalu `systemctl start mariadb` di node2 dan node3.
- `galera_autorecover.sh` memakai SSH root tanpa sandi antar-node dan `StrictHostKeyChecking=no`. Ini cocok untuk lab, tapi untuk produksi sebaiknya pakai user khusus dan `known_hosts` yang sudah diverifikasi.

## Kontak

Terbuka untuk pekerjaan di bidang **infrastruktur / DevOps / web**.

- LinkedIn: [kantakhanigara](https://www.linkedin.com/in/kantakhanigara)

## Lisensi

[MIT](LICENSE) © 2026 Kanta Khanigara
