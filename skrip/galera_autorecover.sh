#!/bin/bash
# =============================================================================
#  galera_autorecover.sh
#  Pemulihan otomatis klaster Galera setelah seluruh node mati bersamaan.
#
#  Dasar kerja : setelah mati total, tidak ada node yang berstatus Primary.
#                Klaster hanya boleh di-bootstrap dari node yang memegang data
#                paling mutakhir, yaitu node dengan safe_to_bootstrap=1 atau,
#                bila seluruhnya 0, node dengan nilai seqno tertinggi.
#
#  Pemakaian   : sudo ./galera_autorecover.sh status    -> tampilkan status lokal
#                sudo ./galera_autorecover.sh recover   -> bandingkan & pulihkan
#
#  Catatan     : mode "recover" dijalankan dari SATU node saja (node koordinator)
#                dan memerlukan kunci SSH tanpa kata sandi antar-node.
# =============================================================================

set -uo pipefail

GRASTATE="/var/lib/mysql/grastate.dat"
NODES=("10.200.0.1" "10.200.0.2" "10.200.0.3")
SSH_USER="root"
SSH_OPTS="-o BatchMode=yes -o ConnectTimeout=5 -o StrictHostKeyChecking=no"

log() { echo "[$(date '+%F %T')] $*"; }

# --- membaca grastate.dat pada node lokal -----------------------------------
baca_status_lokal() {
    if [ ! -f "$GRASTATE" ]; then
        echo "TIDAK_ADA -1"
        return 1
    fi
    local safe seqno
    safe=$(grep -E '^safe_to_bootstrap:' "$GRASTATE" | awk '{print $2}')
    seqno=$(grep -E '^seqno:' "$GRASTATE" | awk '{print $2}')
    [ -z "$safe" ]  && safe=0
    [ -z "$seqno" ] && seqno=-1
    echo "$safe $seqno"
}

# --- memulihkan seqno dari log bila grastate.dat bernilai -1 ----------------
pulihkan_seqno() {
    local hasil
    hasil=$(mariadbd --wsrep-recover 2>&1 | grep -oE 'WSREP: Recovered position:.*:-?[0-9]+' | grep -oE '\-?[0-9]+$')
    [ -z "$hasil" ] && hasil=-1
    echo "$hasil"
}

mode_status() {
    read -r safe seqno <<< "$(baca_status_lokal)"
    if [ "$safe" = "TIDAK_ADA" ]; then
        log "grastate.dat tidak ditemukan pada $(hostname). MariaDB mungkin belum pernah dijalankan."
        exit 1
    fi
    if [ "$seqno" = "-1" ]; then
        log "seqno pada grastate.dat bernilai -1 (penghentian tidak tertib). Memulihkan posisi dari log..."
        seqno=$(pulihkan_seqno)
    fi
    log "Node          : $(hostname)"
    log "safe_to_bootstrap : $safe"
    log "seqno             : $seqno"
    echo "$(hostname) $safe $seqno"
}

mode_recover() {
    log "Mengumpulkan status seluruh node..."
    local terbaik_node="" terbaik_seqno=-2 terbaik_safe=0
    local daftar=()

    for ip in "${NODES[@]}"; do
        local keluaran
        keluaran=$(ssh $SSH_OPTS "${SSH_USER}@${ip}" \
            "bash -s" < "$0" status 2>/dev/null | tail -n 1)
        if [ -z "$keluaran" ]; then
            log "  $ip : TIDAK DAPAT DIHUBUNGI"
            continue
        fi
        read -r nama safe seqno <<< "$keluaran"
        log "  $ip ($nama) : safe_to_bootstrap=$safe seqno=$seqno"
        daftar+=("$ip $safe $seqno")

        if [ "$safe" -eq 1 ] 2>/dev/null; then
            if [ "$terbaik_safe" -ne 1 ] || [ "$seqno" -gt "$terbaik_seqno" ]; then
                terbaik_node="$ip"; terbaik_seqno="$seqno"; terbaik_safe=1
            fi
        elif [ "$terbaik_safe" -ne 1 ] && [ "$seqno" -gt "$terbaik_seqno" ] 2>/dev/null; then
            terbaik_node="$ip"; terbaik_seqno="$seqno"
        fi
    done

    if [ -z "$terbaik_node" ]; then
        log "GAGAL: tidak satu pun node dapat dibaca statusnya. Pemulihan dihentikan."
        exit 1
    fi

    log "Node termutakhir : $terbaik_node (safe_to_bootstrap=$terbaik_safe, seqno=$terbaik_seqno)"

    if [ "$terbaik_safe" -ne 1 ]; then
        log "Tidak ada node bertanda safe_to_bootstrap=1; menandai $terbaik_node secara eksplisit."
        ssh $SSH_OPTS "${SSH_USER}@${terbaik_node}" \
            "sed -i 's/^safe_to_bootstrap:.*/safe_to_bootstrap: 1/' $GRASTATE"
    fi

    log "Menjalankan bootstrap pada $terbaik_node ..."
    ssh $SSH_OPTS "${SSH_USER}@${terbaik_node}" "galera_new_cluster"
    sleep 10

    for ip in "${NODES[@]}"; do
        [ "$ip" = "$terbaik_node" ] && continue
        log "Menggabungkan $ip ke klaster ..."
        ssh $SSH_OPTS "${SSH_USER}@${ip}" "systemctl start mariadb"
        sleep 8
    done

    log "Memverifikasi hasil pemulihan ..."
    ssh $SSH_OPTS "${SSH_USER}@${terbaik_node}" \
        "mariadb -e \"SHOW STATUS WHERE Variable_name IN ('wsrep_cluster_size','wsrep_cluster_status','wsrep_local_state_comment');\""
    log "Selesai."
}

case "${1:-status}" in
    status)  mode_status ;;
    recover) mode_recover ;;
    *) echo "Pemakaian: $0 {status|recover}"; exit 1 ;;
esac
