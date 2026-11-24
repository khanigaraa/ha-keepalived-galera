# Pemulihan setelah ketiga node mati

Kalau **ketiga node mati bersamaan** (misalnya listrik atau penyedia cloud bermasalah), tidak ada node Galera yang berstatus *Primary* saat dinyalakan lagi. Klaster hanya boleh dinyalakan ulang (bootstrap) dari node yang datanya **paling baru**. Kalau salah pilih, transaksi terakhir bisa hilang.

[`skrip/galera_autorecover.sh`](../skrip/galera_autorecover.sh) mengotomatiskan pemilihan itu.

## Cara memilih node

1. Baca `/var/lib/mysql/grastate.dat` di setiap node.
2. Kalau ada node dengan `safe_to_bootstrap: 1`, pilih node itu.
3. Kalau tidak ada, pilih node dengan `seqno` tertinggi. Kalau `seqno` bernilai `-1` (mati tidak tertib), posisi dipulihkan dulu dengan `mariadbd --wsrep-recover`.
4. Tandai node terpilih `safe_to_bootstrap: 1`, jalankan `galera_new_cluster` di sana, lalu nyalakan MariaDB di dua node lain satu per satu.
5. Tampilkan `wsrep_cluster_size`, `wsrep_cluster_status`, dan `wsrep_local_state_comment` sebagai verifikasi.

## Pemakaian

```bash
sudo ./galera_autorecover.sh status    # lihat safe_to_bootstrap dan seqno node ini
sudo ./galera_autorecover.sh recover   # bandingkan ketiga node lalu pulihkan klaster
```

Mode `recover` dijalankan dari **satu node saja** (node koordinator), dan butuh kunci SSH tanpa sandi antar-node.

## Catatan keamanan

Skrip ini memakai SSH sebagai `root` dengan `StrictHostKeyChecking=no`. Untuk lab itu praktis, tapi untuk produksi sebaiknya:

- pakai user khusus dengan `sudo` terbatas, bukan `root`;
- isi `known_hosts` dengan kunci host yang sudah diverifikasi, lalu aktifkan lagi `StrictHostKeyChecking`.
