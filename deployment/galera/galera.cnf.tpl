# MariaDB Galera Cluster configuration
# This file is mounted into every cluster node unchanged.
# Node-specific values (wsrep_node_address, wsrep_new_cluster) are
# supplied via environment variables set in docker-compose-galera.yml.

[mysqld]
# ---- Galera provider ----
wsrep_on                    = ON
wsrep_provider              = /usr/lib/galera/libgalera_smm.so
wsrep_provider_options      = "gcache.size=512M; gcs.fc_limit=256"

# ---- Cluster identity ----
# The cluster name must be identical on all nodes.
wsrep_cluster_name          = ${GALERA_CLUSTER_NAME}

# Comma-separated list of all cluster member addresses.
wsrep_cluster_address       = gcomm://${GALERA_CLUSTER_MEMBERS}

# The IP/hostname this node advertises to its peers.
wsrep_node_address          = ${GALERA_NODE_ADDRESS}
wsrep_node_name             = ${GALERA_NODE_ADDRESS}

# ---- Replication settings ----
# ROW-based binary logging is required by Galera.
binlog_format               = ROW
log_bin                     = ON

# InnoDB is the only fully supported storage engine with Galera.
default_storage_engine      = InnoDB

# Required for Galera with auto-increment tables (avoids deadlocks).
innodb_autoinc_lock_mode    = 2

# Write-set replication: use a dedicated thread pool.
wsrep_slave_threads         = 4

# ---- Certification-based replication tuning ----
# These eliminate the risk of a primary-key conflict during parallel apply.
innodb_locks_unsafe_for_binlog  = 1

# ---- SST (State Snapshot Transfer) method ----
# mariabackup performs a non-blocking hot backup; it is the preferred SST
# method because it does not lock the donor node during the transfer.
wsrep_sst_method            = mariabackup
wsrep_sst_auth              = root:${MYSQL_ROOT_PASSWORD}

# ---- Performance ----
innodb_buffer_pool_size     = 256M
innodb_log_file_size        = 64M
innodb_flush_log_at_trx_commit = 1
sync_binlog                 = 1
innodb_flush_method         = O_DIRECT

# ---- Character set ----
character_set_server        = utf8mb4
collation_server            = utf8mb4_unicode_ci
