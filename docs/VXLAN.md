# Kenapa butuh VXLAN

## Masalahnya

Keepalived memindahkan **Virtual IP** dari node ke node. Node yang sedang memegang Virtual IP mengumumkannya lewat ARP, supaya node lain tahu alamat MAC mana yang harus dituju.

Di jaringan privat penyedia cloud yang dipakai, ARP ke alamat yang **tidak terdaftar** untuk sebuah VPS ternyata disaring. Akibatnya, Virtual IP tidak bisa dijangkau dari node lain, dan failover jadi percuma.

Bukti dari log (C.5): dari node2 dikirim `ping` ke IP asli node1 dan ke Virtual IP, sementara node1 menangkap ARP yang masuk.

```text
ARP, Request who-has 10.10.33.75 tell 10.10.35.92      ← ARP ke IP asli node1: sampai
ARP, Reply 10.10.33.75 is-at 02:00:0a:0a:21:4b
ARP, Request who-has 10.10.33.102 tell 10.10.35.92
                                                       ← ARP ke Virtual IP: tidak pernah muncul
```

## Solusinya

Bangun jaringan **overlay VXLAN** di atas jaringan privat. Setiap node punya interface `vxlan0` (VNI 100, UDP 4789) dengan alamat `10.200.0.0/24`. Paket di dalam overlay dibungkus UDP biasa antar-IP asli, jadi jaringan penyedia hanya melihat lalu lintas UDP antar-VPS yang sah.

| Node | IP asli (`eth1`) | IP overlay (`vxlan0`) |
|---|---|---|
| node1 | 10.10.33.75 | 10.200.0.1 |
| node2 | 10.10.35.92 | 10.200.0.2 |
| node3 | 10.10.33.102 | 10.200.0.3 |
| Virtual IP | — | **10.200.0.100** |

Karena overlay ini tidak memakai multicast, setiap node mendaftarkan node lain secara manual di tabel forwarding (`bridge fdb append … dst <IP asli>`). Lihat [`konfigurasi/node1/vxlan0.service`](../konfigurasi/node1/vxlan0.service).

Keepalived kemudian berjalan **di dalam overlay** (`interface vxlan0`) dengan VRRP **unicast**, dan Virtual IP dipasang di `vxlan0`. Galera Cluster juga memakai alamat overlay (`gcomm://10.200.0.1,10.200.0.2,10.200.0.3`).

Bukti bahwa VRRP benar-benar lewat overlay (C.5):

```text
IP 10.10.33.75.36733 > 10.10.35.92.4789: VXLAN, flags [I] (0x08), vni 100
IP 10.200.0.1 > 10.200.0.2: VRRPv2, Advertisement, vrid 51, prio 200, authtype simple, intvl 1s, length 20
```

`vxlan0.service` diatur jalan **sebelum** `keepalived.service` (`Before=keepalived.service`), supaya interface-nya sudah ada saat Keepalived mulai.
