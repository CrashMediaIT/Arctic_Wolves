/**
 * Tests for Companion App Node Support
 *
 * Verifies:
 * 1. Node configuration in companion app.py (master/slave roles, slave node list)
 * 2. Node management API endpoints (GET/POST/DELETE /api/nodes)
 * 3. Master delegation logic (delegate to slave when busy)
 * 4. Node settings in companion web UI (settings form, node list)
 * 5. Health and config endpoints include node information
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Node configuration in companion app.py
// =====================================================

test.describe('Companion app.py node configuration', () => {
  const content = () => readFile('companion/app.py');

  test('should define NODE_ROLE from persistent config', () => {
    const c = content();
    expect(c).toContain('NODE_ROLE');
    expect(c).toContain('node_role');
    expect(c).toContain('_pcfg("node_role"');
  });

  test('should support master and slave roles', () => {
    const c = content();
    expect(c).toContain('"master"');
    expect(c).toContain('"slave"');
  });

  test('should have SLAVE_NODES list loaded from persistent config', () => {
    const c = content();
    expect(c).toContain('SLAVE_NODES');
    expect(c).toContain('_load_slave_nodes');
    expect(c).toContain('"slave_nodes"');
  });

  test('should have _is_master_busy helper function', () => {
    const c = content();
    expect(c).toContain('def _is_master_busy');
    expect(c).toContain('MAX_CONCURRENT_JOBS');
  });

  test('should have _check_slave_health helper function', () => {
    const c = content();
    expect(c).toContain('def _check_slave_health');
    expect(c).toContain('/api/health');
  });

  test('should have _get_available_slave helper function', () => {
    const c = content();
    expect(c).toContain('def _get_available_slave');
    expect(c).toContain('SLAVE_NODES');
  });

  test('should have _delegate_to_slave helper function', () => {
    const c = content();
    expect(c).toContain('def _delegate_to_slave');
    expect(c).toContain('/api/hls');
  });
});

// =====================================================
// 2. Node management API endpoints
// =====================================================

test.describe('Companion app.py node API endpoints', () => {
  const content = () => readFile('companion/app.py');

  test('should have GET /api/nodes endpoint to list nodes', () => {
    const c = content();
    expect(c).toContain('/api/nodes');
    expect(c).toContain('def list_nodes');
  });

  test('should have POST /api/nodes endpoint to add a node', () => {
    const c = content();
    expect(c).toContain('def add_node');
    // Should validate URL scheme
    expect(c).toContain('urlparse');
  });

  test('should have DELETE /api/nodes/<node_id> endpoint to remove a node', () => {
    const c = content();
    expect(c).toContain('/api/nodes/<node_id>');
    expect(c).toContain('def remove_node');
  });

  test('list_nodes should return node_role and slave_nodes', () => {
    const c = content();
    const listFunc = c.substring(
      c.indexOf('def list_nodes'),
      c.indexOf('def add_node')
    );
    expect(listFunc).toContain('"node_role"');
    expect(listFunc).toContain('"slave_nodes"');
  });

  test('add_node should validate URL and prevent duplicates', () => {
    const c = content();
    const addFunc = c.substring(
      c.indexOf('def add_node'),
      c.indexOf('def remove_node')
    );
    expect(addFunc).toContain('url is required');
    expect(addFunc).toContain('already registered');
  });

  test('add_node should persist slave nodes to config', () => {
    const c = content();
    const addFunc = c.substring(
      c.indexOf('def add_node'),
      c.indexOf('def remove_node')
    );
    expect(addFunc).toContain('_save_persistent_config');
  });

  test('remove_node should persist changes after removal', () => {
    const c = content();
    const removeStart = c.indexOf('def remove_node');
    const removeFunc = c.substring(
      removeStart,
      c.indexOf('\n\n\n', removeStart) > -1
        ? c.indexOf('\n\n\n', removeStart)
        : c.indexOf('# ---', removeStart)
    );
    expect(removeFunc).toContain('_save_persistent_config');
  });

  test('all node endpoints should require API key authentication', () => {
    const c = content();
    // Count _require_api_key calls in the node section
    const nodeSection = c.substring(
      c.indexOf('# Node Management'),
      c.indexOf('# Callback')
    );
    const authCalls = (nodeSection.match(/_require_api_key/g) || []).length;
    expect(authCalls).toBeGreaterThanOrEqual(3);
  });
});

// =====================================================
// 3. Master delegation logic in /api/hls
// =====================================================

test.describe('Companion app.py master delegation in HLS endpoint', () => {
  const content = () => readFile('companion/app.py');

  test('HLS endpoint should check if master is busy before processing', () => {
    const c = content();
    const hlsFunc = c.substring(
      c.indexOf('def hls_transcode'),
      c.indexOf('# ------', c.indexOf('def hls_transcode') + 1)
    );
    expect(hlsFunc).toContain('_is_master_busy');
  });

  test('HLS endpoint should delegate to slave when master is busy', () => {
    const c = content();
    const hlsFunc = c.substring(
      c.indexOf('def hls_transcode'),
      c.indexOf('# ------', c.indexOf('def hls_transcode') + 1)
    );
    expect(hlsFunc).toContain('_delegate_to_slave');
    expect(hlsFunc).toContain('_get_available_slave');
  });

  test('delegation should only happen when node_role is master', () => {
    const c = content();
    const hlsFunc = c.substring(
      c.indexOf('def hls_transcode'),
      c.indexOf('# ------', c.indexOf('def hls_transcode') + 1)
    );
    expect(hlsFunc).toContain('NODE_ROLE');
    expect(hlsFunc).toContain('"master"');
  });

  test('delegation should only happen when slave nodes are configured', () => {
    const c = content();
    const hlsFunc = c.substring(
      c.indexOf('def hls_transcode'),
      c.indexOf('# ------', c.indexOf('def hls_transcode') + 1)
    );
    expect(hlsFunc).toContain('SLAVE_NODES');
  });

  test('should fall back to local processing if no slave is available', () => {
    const c = content();
    // After the delegation block, it should still create a local job
    const hlsFunc = c.substring(
      c.indexOf('def hls_transcode'),
      c.indexOf('# ------', c.indexOf('def hls_transcode') + 1)
    );
    // Job creation still exists after delegation attempt
    expect(hlsFunc).toContain('uuid.uuid4');
    expect(hlsFunc).toContain('"queued"');
  });
});

// =====================================================
// 4. Health and config endpoints include node info
// =====================================================

test.describe('Companion app.py health and config include node info', () => {
  const content = () => readFile('companion/app.py');

  test('health endpoint should include node_role', () => {
    const c = content();
    const healthFunc = c.substring(
      c.indexOf('def health'),
      c.indexOf('def probe')
    );
    expect(healthFunc).toContain('node_role');
  });

  test('health endpoint should include slave_node_count', () => {
    const c = content();
    const healthFunc = c.substring(
      c.indexOf('def health'),
      c.indexOf('def probe')
    );
    expect(healthFunc).toContain('slave_node_count');
  });

  test('config GET endpoint should include node_role', () => {
    const c = content();
    const configFunc = c.substring(
      c.indexOf('def get_config'),
      c.indexOf('def update_config')
    );
    expect(configFunc).toContain('node_role');
  });

  test('config PUT endpoint should accept node_role', () => {
    const c = content();
    const updateFunc = c.substring(
      c.indexOf('def update_config'),
      c.indexOf('def generate_key')
    );
    expect(updateFunc).toContain('"node_role"');
    expect(updateFunc).toContain('NODE_ROLE');
  });

  test('config PUT should persist node_role and slave_nodes', () => {
    const c = content();
    const updateFunc = c.substring(
      c.indexOf('def update_config'),
      c.indexOf('def generate_key')
    );
    expect(updateFunc).toContain('"slave_nodes"');
  });
});

// =====================================================
// 5. Node settings in companion web UI
// =====================================================

test.describe('Companion web UI node settings (templates/index.html)', () => {
  const content = () => readFile('companion/templates/index.html');

  test('should have Node Role selector', () => {
    const c = content();
    expect(c).toContain('cfg-node-role');
    expect(c).toContain('node_role');
  });

  test('should have Master and Slave role options', () => {
    const c = content();
    expect(c).toContain('value="master"');
    expect(c).toContain('value="slave"');
  });

  test('should have slave nodes list section', () => {
    const c = content();
    expect(c).toContain('slave-nodes-list');
    expect(c).toContain('slave-nodes-section');
  });

  test('should have Add Slave Node form', () => {
    const c = content();
    expect(c).toContain('new-node-url');
    expect(c).toContain('new-node-api-key');
    expect(c).toContain('addSlaveNode');
  });

  test('should have Remove Node functionality', () => {
    const c = content();
    expect(c).toContain('removeNode');
    expect(c).toContain('Remove');
  });

  test('should have refreshNodes function', () => {
    const c = content();
    expect(c).toContain('refreshNodes');
    expect(c).toContain('/api/nodes');
  });

  test('should have toggleSlaveNodesSection function', () => {
    const c = content();
    expect(c).toContain('toggleSlaveNodesSection');
  });

  test('should describe node management purpose', () => {
    const c = content();
    expect(c).toContain('distributed transcoding');
  });

  test('should include node_role in saveConfig payload', () => {
    const c = content();
    const saveFunc = c.substring(
      c.indexOf('async function saveConfig'),
      c.indexOf('async function generateApiKey')
    );
    expect(saveFunc).toContain('node_role');
  });

  test('should load node_role in loadConfig', () => {
    const c = content();
    const loadFunc = c.substring(
      c.indexOf('async function loadConfig'),
      c.indexOf('async function saveConfig')
    );
    expect(loadFunc).toContain('cfg-node-role');
    expect(loadFunc).toContain('node_role');
  });

  test('should show node status with online/busy/offline indicators', () => {
    const c = content();
    expect(c).toContain('Available');
    expect(c).toContain('Busy');
    expect(c).toContain('Offline');
  });
});

// =====================================================
// 6. Setup wizard — node role selection
// =====================================================

test.describe('Setup wizard node role selection (app.py)', () => {
  const content = () => readFile('companion/app.py');

  test('setup page should have role selection (master/slave)', () => {
    const c = content();
    expect(c).toContain('step-role');
    expect(c).toContain('role-master');
    expect(c).toContain('role-slave');
    expect(c).toContain('selectRole');
  });

  test('master setup should walk through account creation step', () => {
    const c = content();
    expect(c).toContain('step-master-account');
    expect(c).toContain('saveAccount');
  });

  test('master setup should walk through RustFS step', () => {
    const c = content();
    expect(c).toContain('step-master-rustfs');
    expect(c).toContain('saveMasterRustFS');
    expect(c).toContain('skipRustFS');
    expect(c).toContain('setup-s3-endpoint');
  });

  test('master setup should walk through API key generation step', () => {
    const c = content();
    expect(c).toContain('step-master-apikey');
    expect(c).toContain('setupGenerateApiKey');
  });

  test('slave setup should only have API key generation', () => {
    const c = content();
    expect(c).toContain('step-slave-apikey');
    expect(c).toContain('slaveGenerateApiKey');
    expect(c).toContain('Copy this key and enter it when adding this slave on the master');
  });

  test('/api/setup should accept node_role parameter', () => {
    const c = content();
    const setupFunc = c.substring(
      c.indexOf('def setup_save'),
      c.indexOf('# Import redirect')
    );
    expect(setupFunc).toContain('node_role');
    expect(setupFunc).toContain('"master"');
    expect(setupFunc).toContain('"slave"');
  });

  test('master setup should return config_loaded when key matches existing config', () => {
    const c = content();
    const setupFunc = c.substring(
      c.indexOf('def setup_save'),
      c.indexOf('# Import redirect')
    );
    expect(setupFunc).toContain('config_loaded');
    expect(setupFunc).toContain('s3_configured');
  });

  test('slave setup should auto-generate encryption key if not in env', () => {
    const c = content();
    const setupFunc = c.substring(
      c.indexOf('def setup_save'),
      c.indexOf('# Import redirect')
    );
    expect(setupFunc).toContain('secrets.token_hex');
  });

  test('master setup should reload runtime globals from loaded config', () => {
    const c = content();
    const setupFunc = c.substring(
      c.indexOf('def setup_save'),
      c.indexOf('# Import redirect')
    );
    expect(setupFunc).toContain('S3_ENDPOINT');
    expect(setupFunc).toContain('ADMIN_USERNAME');
  });
});

// =====================================================
// 7. Settings sync — master to slave
// =====================================================

test.describe('Settings sync from master to slave', () => {
  const content = () => readFile('companion/app.py');

  test('should have _get_master_settings_payload helper', () => {
    const c = content();
    expect(c).toContain('def _get_master_settings_payload');
  });

  test('should have _sync_settings_to_slave helper', () => {
    const c = content();
    expect(c).toContain('def _sync_settings_to_slave');
    expect(c).toContain('/api/config');
  });

  test('should have /api/nodes/sync endpoint', () => {
    const c = content();
    expect(c).toContain('/api/nodes/sync');
    expect(c).toContain('def sync_nodes');
  });

  test('should have /api/nodes/pull-settings endpoint', () => {
    const c = content();
    expect(c).toContain('/api/nodes/pull-settings');
    expect(c).toContain('def pull_settings');
  });

  test('should auto-sync settings when a slave node is added', () => {
    const c = content();
    const addFunc = c.substring(
      c.indexOf('def add_node'),
      c.indexOf('def remove_node')
    );
    expect(addFunc).toContain('_sync_settings_to_slave');
    expect(addFunc).toContain('settings_synced');
  });

  test('settings sync should NOT include hw_accel', () => {
    const c = content();
    const payloadFunc = c.substring(
      c.indexOf('def _get_master_settings_payload'),
      c.indexOf('def _sync_settings_to_slave')
    );
    expect(payloadFunc).not.toContain('"hw_accel"');
    expect(payloadFunc).toContain('intentionally excluded');
    expect(payloadFunc).toContain('different GPU hardware');
  });

  test('settings sync should include S3/RustFS credentials', () => {
    const c = content();
    const payloadFunc = c.substring(
      c.indexOf('def _get_master_settings_payload'),
      c.indexOf('def _sync_settings_to_slave')
    );
    expect(payloadFunc).toContain('s3_endpoint');
    expect(payloadFunc).toContain('s3_access_key');
    expect(payloadFunc).toContain('s3_secret_key');
    expect(payloadFunc).toContain('s3_bucket');
  });

  test('sync endpoint should only work on master nodes', () => {
    const c = content();
    const syncFunc = c.substring(
      c.indexOf('def sync_nodes'),
      c.indexOf('def pull_settings')
    );
    expect(syncFunc).toContain('NODE_ROLE');
    expect(syncFunc).toContain('"master"');
    expect(syncFunc).toContain('Only master nodes');
  });
});

// =====================================================
// 8. UI — master/slave settings visibility
// =====================================================

test.describe('Companion web UI master/slave settings visibility', () => {
  const content = () => readFile('companion/templates/index.html');

  test('should have master-only-settings class on S3 settings', () => {
    const c = content();
    expect(c).toContain('master-only-settings');
    // S3 section should be master-only
    const s3Section = c.substring(
      c.indexOf('S3 / RustFS'),
      c.indexOf('Node Management')
    );
    expect(s3Section).toContain('master-only-settings');
  });

  test('hardware acceleration should NOT have master-only-settings class', () => {
    const c = content();
    // Find the form-group div that directly contains cfg-hw-accel
    const hwStart = c.indexOf('cfg-hw-accel');
    // Go backwards to find the opening div for this form-group
    const divStart = c.lastIndexOf('<div class="form-group', hwStart);
    const divTag = c.substring(divStart, c.indexOf('>', divStart) + 1);
    expect(divTag).not.toContain('master-only-settings');
  });

  test('should explain hw_accel is configured per-node', () => {
    const c = content();
    expect(c).toContain('per-node');
    expect(c).toContain('different GPU hardware');
  });

  test('should have slave settings banner', () => {
    const c = content();
    expect(c).toContain('slave-settings-banner');
    expect(c).toContain('Slave Node');
  });

  test('toggleSlaveNodesSection should toggle master-only-settings visibility', () => {
    const c = content();
    const toggleFunc = c.substring(
      c.indexOf('function toggleSlaveNodesSection'),
      c.indexOf('document.getElementById(\'cfg-node-role\').addEventListener')
    );
    expect(toggleFunc).toContain('master-only-settings');
    expect(toggleFunc).toContain('slave-settings-banner');
  });

  test('should have syncAllNodes function and button', () => {
    const c = content();
    expect(c).toContain('syncAllNodes');
    expect(c).toContain('Sync Settings to All Slaves');
    expect(c).toContain('/api/nodes/sync');
  });
});
