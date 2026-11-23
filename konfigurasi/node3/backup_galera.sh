#!/bin/bash
TUJUAN="/var/backup/mariadb/$(date +%Y%m%d_%H%M%S)"
mkdir -p "$TUJUAN"
mariabackup --backup --target-dir="$TUJUAN" --user=backup_user --password='GANTI_DENGAN_SANDI' 2>> /var/log/backup_galera.log
mariabackup --prepare --target-dir="$TUJUAN" 2>> /var/log/backup_galera.log
find /var/backup/mariadb -maxdepth 1 -type d -mtime +7 -exec rm -rf {} \;
