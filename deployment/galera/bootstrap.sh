#!/usr/bin/env bash
# deployment/galera/bootstrap.sh
#
# Bootstrap a fresh MariaDB Galera cluster.
#
# Run this script ONCE on the designated "first node" host before starting
# the other two nodes.  Once the cluster is healthy, this script must NOT
# be run again — doing so would create a new, empty cluster and cause
# data loss on the remaining nodes.
#
# Usage:
#   chmod +x deployment/galera/bootstrap.sh
#   BOOTSTRAP_CLUSTER=yes docker compose -f deployment/docker-compose-galera.yml up -d galera-node-1
#   # Wait until healthy, then:
#   docker compose -f deployment/docker-compose-galera.yml up -d galera-node-2 galera-node-3
#
# To restart an existing cluster after all nodes have been shut down,
# identify the node with the highest seqno in /var/lib/mysql/grastate.dat
# and start that node first with BOOTSTRAP_CLUSTER=yes, then bring up
# the others normally.

set -euo pipefail

COMPOSE_FILE="${COMPOSE_FILE:-$(dirname "$0")/../docker-compose-galera.yml}"

if [[ "${BOOTSTRAP_CLUSTER:-}" != "yes" ]]; then
    echo "ERROR: Set BOOTSTRAP_CLUSTER=yes to confirm you want to bootstrap a new cluster."
    echo "       This is destructive if the cluster already has data on other nodes."
    exit 1
fi

echo "==> Bootstrapping Galera cluster (starting galera-node-1) ..."
BOOTSTRAP_CLUSTER=yes docker compose -f "$COMPOSE_FILE" up -d galera-node-1

echo "==> Waiting for galera-node-1 to become healthy ..."
for i in $(seq 1 30); do
    if docker compose -f "$COMPOSE_FILE" exec -T galera-node-1 \
           mariadb -u root -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT 1" &>/dev/null; then
        echo "    galera-node-1 is up."
        break
    fi
    echo "    Attempt $i/30 – waiting 5 s ..."
    sleep 5
done

echo "==> Bringing up galera-node-2 and galera-node-3 ..."
docker compose -f "$COMPOSE_FILE" up -d galera-node-2 galera-node-3

echo "==> Waiting for all nodes to join the cluster ..."
sleep 15

echo "==> Cluster status:"
docker compose -f "$COMPOSE_FILE" exec -T galera-node-1 \
    mariadb -u root -p"${MYSQL_ROOT_PASSWORD}" \
    -e "SHOW STATUS LIKE 'wsrep_cluster_size'; SHOW STATUS LIKE 'wsrep_cluster_status'; SHOW STATUS LIKE 'wsrep_ready';"

echo ""
echo "Bootstrap complete.  Point your application at:"
echo "  ProxySQL (load-balanced):  host=<host>  port=\${PROXY_MYSQL_PORT:-3305}"
echo "  Or directly to any node:   port=3306/3307/3308"
echo ""
echo "IMPORTANT: Do NOT set BOOTSTRAP_CLUSTER=yes on future restarts."
