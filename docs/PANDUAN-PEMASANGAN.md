# Panduan pemasangan

Panduan ini disusun ulang dari berkas konfigurasi di repo, untuk tiga VPS **Ubuntu 24.04 LTS**. Ini bukan skrip otomatis: jalankan langkah demi langkah, dan sesuaikan IP, nama interface, dan sandi dengan jaringanmu.

> Uji failover di langkah 8 mematikan server secara paksa. Lakukan hanya di lingkungan lab.

## 0. Siapkan

| Node | Peran | Paket |
|---|---|---|
| node1 | MASTER: Nginx + PHP + MariaDB Galera + Keepalived | `mariadb-server galera-4 rsync nginx php8.3-fpm php8.3-mysql keepalived prometheus-node-exporter` |
| node2 | BACKUP: sama seperti node1 | sama seperti node1 |
| node3 | MariaDB Galera + Prometheus + Grafana + backup | `mariadb-server galera-4 rsync mariadb-backup prometheus prometheus-node-exporter` (Grafana dari repo resmi Grafana) |

Port yang harus terbuka **antar-node** di jaringan privat: UDP 4789 (VXLAN). Di dalam overlay: 3306, 4567, 4568, 4444 (Galera), protokol 112 (VRRP), 80 (Nginx), dan 9100 (node_exporter).

Ganti dulu semua placeholder di repo: `GANTI_DENGAN_SANDI` dan `GANTI123` (sandi VRRP, maksimal 8 karakter, harus sama di node1 dan node2).

## 1. Jaringan overlay VXLAN (ketiga node)

```bash
sudo cp konfigurasi/nodeN/vxlan0.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now vxlan0
ip addr show vxlan0          # harus muncul 10.200.0.N/24
ping -c 3 10.200.0.1         # dari node2/node3
```

Kenapa langkah ini perlu: [VXLAN.md](VXLAN.md).

## 2. MariaDB Galera Cluster

```bash
sudo cp konfigurasi/nodeN/70-galera-ifjudul.cnf /etc/mysql/mariadb.conf.d/
sudo systemctl stop mariadb

# node1 saja: nyalakan klaster pertama kali
sudo galera_new_cluster

# lalu node2, kemudian node3
sudo systemctl start mariadb

# cek di node mana saja: harus 3 dan Primary
sudo mariadb -e "SHOW STATUS LIKE 'wsrep_cluster_size'; SHOW STATUS LIKE 'wsrep_cluster_status';"
```

## 3. Basis data dan aplikasi (node1, lalu salin ke node2)

```bash
# node1 saja: Galera mereplikasi ke node2 dan node3
sudo mariadb < aplikasi/01_skema.sql

# node1 dan node2
sudo mkdir -p /var/www/ifjudul
sudo cp aplikasi/*.php aplikasi/gaya.css /var/www/ifjudul/
```

Pastikan `DB_SANDI` di `koneksi.php` sama dengan sandi di `01_skema.sql`.

## 4. Nginx (node1 dan node2)

```bash
sudo cp konfigurasi/nodeN/nginx-ifjudul.conf /etc/nginx/sites-available/ifjudul
sudo ln -s /etc/nginx/sites-available/ifjudul /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
curl http://127.0.0.1/sehat  # harus menjawab OK
```

## 5. Keepalived (node1 dan node2)

```bash
sudo cp konfigurasi/nodeN/keepalived.conf konfigurasi/nodeN/cek_nginx.sh /etc/keepalived/
sudo chmod +x /etc/keepalived/cek_nginx.sh
sudo systemctl enable --now keepalived
ip addr show vxlan0          # di node1 harus muncul juga 10.200.0.100
```

## 6. Monitoring (node3, plus node_exporter di semua node)

```bash
sudo cp konfigurasi/node3/prometheus.yml /etc/prometheus/prometheus.yml
sudo systemctl restart prometheus
```

Target `10.200.0.1–3:9100` harus berstatus *UP* di halaman Targets Prometheus (port 9090).

## 7. Pencadangan harian (node3)

```bash
sudo mariadb -e "CREATE USER 'backup_user'@'localhost' IDENTIFIED BY 'GANTI_DENGAN_SANDI';
  GRANT RELOAD, PROCESS, LOCK TABLES, BINLOG MONITOR ON *.* TO 'backup_user'@'localhost';"
sudo install -m 750 konfigurasi/node3/backup_galera.sh /usr/local/bin/
sudo crontab -e              # tambahkan baris dari konfigurasi/node3/crontab.txt
```

Skrip menyimpan cadangan di `/var/backup/mariadb/<tanggal>` dan menghapus yang lebih tua dari 7 hari.

## 8. Uji failover (lab saja)

```bash
# node3: pantau Virtual IP
ping -i 0.2 -D 10.200.0.100

# node1: matikan paksa
echo b | sudo tee /proc/sysrq-trigger
```

Balasan ping harus terhenti sebentar lalu lanjut lagi (dijawab node2). Hasil yang diukur di skripsi ada di [HASIL-PENGUJIAN.md](HASIL-PENGUJIAN.md).

Kalau ketiga node pernah mati bersamaan, lihat [PEMULIHAN-TOTAL.md](PEMULIHAN-TOTAL.md).
