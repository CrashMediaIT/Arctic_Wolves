<?php
/**
 * Dependency Security Checker
 * Audits project dependencies for known vulnerabilities and outdated packages.
 * Checks both npm (Node.js) and Composer (PHP) dependencies.
 *
 * Usage:
 *   CLI:  php admin/dependency_checker.php
 *   Web:  Include via admin dashboard (requires admin role)
 */

class DependencyChecker {

    private $base_path;
    private $results = [];

    public function __construct($base_path = null) {
        $this->base_path = $base_path ?? dirname(__DIR__);
    }

    /**
     * Run all dependency checks and return results.
     *
     * @return array Structured results with vulnerabilities, outdated packages, and summary
     */
    public function runAllChecks() {
        $this->results = [
            'npm' => $this->checkNpmDependencies(),
            'composer' => $this->checkComposerDependencies(),
            'summary' => []
        ];

        $this->results['summary'] = $this->generateSummary();
        return $this->results;
    }

    /**
     * Check npm dependencies for vulnerabilities using `npm audit`.
     */
    private function checkNpmDependencies() {
        $result = [
            'available' => false,
            'vulnerabilities' => [],
            'outdated' => [],
            'errors' => []
        ];

        $package_json = $this->base_path . '/package.json';
        if (!file_exists($package_json)) {
            $result['errors'][] = 'No package.json found';
            return $result;
        }

        $result['available'] = true;

        // Parse package.json for dependency list
        $pkg = json_decode(file_get_contents($package_json), true);
        $deps = array_merge(
            $pkg['dependencies'] ?? [],
            $pkg['devDependencies'] ?? []
        );
        $result['declared_packages'] = array_keys($deps);

        // Run npm audit (JSON output) if npm is available
        $npm_path = trim(shell_exec('which npm 2>/dev/null') ?? '');
        if (!empty($npm_path)) {
            $lock_file = $this->base_path . '/package-lock.json';
            if (file_exists($lock_file)) {
                $audit_cmd = sprintf(
                    'cd %s && npm audit --json 2>/dev/null',
                    escapeshellarg($this->base_path)
                );
                $audit_output = shell_exec($audit_cmd);
                if ($audit_output) {
                    $audit_data = json_decode($audit_output, true);
                    if (isset($audit_data['vulnerabilities'])) {
                        foreach ($audit_data['vulnerabilities'] as $pkg_name => $vuln) {
                            $result['vulnerabilities'][] = [
                                'package' => $pkg_name,
                                'severity' => $vuln['severity'] ?? 'unknown',
                                'title' => $vuln['title'] ?? ($vuln['via'][0]['title'] ?? 'Unknown vulnerability'),
                                'range' => $vuln['range'] ?? '',
                                'fix_available' => $vuln['fixAvailable'] ?? false,
                            ];
                        }
                    }
                    if (isset($audit_data['metadata'])) {
                        $result['audit_metadata'] = $audit_data['metadata'];
                    }
                }

                // Check for outdated packages
                $outdated_cmd = sprintf(
                    'cd %s && npm outdated --json 2>/dev/null',
                    escapeshellarg($this->base_path)
                );
                $outdated_output = shell_exec($outdated_cmd);
                if ($outdated_output) {
                    $outdated_data = json_decode($outdated_output, true);
                    if (is_array($outdated_data)) {
                        foreach ($outdated_data as $pkg_name => $info) {
                            $result['outdated'][] = [
                                'package' => $pkg_name,
                                'current' => $info['current'] ?? 'unknown',
                                'wanted' => $info['wanted'] ?? 'unknown',
                                'latest' => $info['latest'] ?? 'unknown',
                            ];
                        }
                    }
                }
            } else {
                $result['errors'][] = 'No package-lock.json found. Run npm install first.';
            }
        } else {
            $result['errors'][] = 'npm not found in PATH. Install Node.js to audit npm dependencies.';
        }

        return $result;
    }

    /**
     * Check Composer (PHP) dependencies for vulnerabilities.
     */
    private function checkComposerDependencies() {
        $result = [
            'available' => false,
            'vulnerabilities' => [],
            'outdated' => [],
            'errors' => []
        ];

        // Check for composer.json in root or known subdirectories
        $composer_paths = [
            $this->base_path . '/composer.json',
            $this->base_path . '/stripe-php/composer.json',
        ];

        $found_composer = false;
        foreach ($composer_paths as $path) {
            if (file_exists($path)) {
                $found_composer = true;
                $dir = dirname($path);
                $pkg = json_decode(file_get_contents($path), true);
                $result['declared_packages'][] = [
                    'path' => str_replace($this->base_path . '/', '', $path),
                    'name' => $pkg['name'] ?? 'unknown',
                    'require' => $pkg['require'] ?? [],
                ];
            }
        }

        if (!$found_composer) {
            $result['errors'][] = 'No composer.json found';
            return $result;
        }

        $result['available'] = true;

        // Run composer audit if available
        $composer_path = trim(shell_exec('which composer 2>/dev/null') ?? '');
        if (!empty($composer_path)) {
            foreach ($composer_paths as $path) {
                if (!file_exists($path)) continue;
                $dir = dirname($path);
                $lock_file = $dir . '/composer.lock';

                if (file_exists($lock_file)) {
                    $audit_cmd = sprintf(
                        'cd %s && composer audit --format=json 2>/dev/null',
                        escapeshellarg($dir)
                    );
                    $audit_output = shell_exec($audit_cmd);
                    if ($audit_output) {
                        $audit_data = json_decode($audit_output, true);
                        if (isset($audit_data['advisories']) && is_array($audit_data['advisories'])) {
                            foreach ($audit_data['advisories'] as $pkg_name => $advisories) {
                                foreach ($advisories as $advisory) {
                                    $result['vulnerabilities'][] = [
                                        'package' => $pkg_name,
                                        'title' => $advisory['title'] ?? 'Unknown',
                                        'cve' => $advisory['cve'] ?? '',
                                        'link' => $advisory['link'] ?? '',
                                        'affected_versions' => $advisory['affectedVersions'] ?? '',
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        } else {
            $result['errors'][] = 'Composer not found in PATH. Install Composer to audit PHP dependencies.';
        }

        return $result;
    }

    /**
     * Generate summary across all dependency ecosystems.
     */
    private function generateSummary() {
        $total_vulns = 0;
        $total_outdated = 0;
        $critical_vulns = 0;

        foreach (['npm', 'composer'] as $ecosystem) {
            $data = $this->results[$ecosystem] ?? [];
            $vulns = $data['vulnerabilities'] ?? [];
            $total_vulns += count($vulns);
            $total_outdated += count($data['outdated'] ?? []);

            foreach ($vulns as $v) {
                $severity = strtolower($v['severity'] ?? '');
                if (in_array($severity, ['critical', 'high'])) {
                    $critical_vulns++;
                }
            }
        }

        return [
            'total_vulnerabilities' => $total_vulns,
            'critical_high_vulnerabilities' => $critical_vulns,
            'total_outdated' => $total_outdated,
            'status' => $critical_vulns > 0 ? 'critical' : ($total_vulns > 0 ? 'warning' : 'healthy'),
            'checked_at' => date('Y-m-d H:i:s'),
        ];
    }
}

// CLI execution
if (php_sapi_name() === 'cli' && basename($argv[0] ?? '') === basename(__FILE__)) {
    $checker = new DependencyChecker(dirname(__DIR__));
    $results = $checker->runAllChecks();

    echo "=== Dependency Security Report ===\n";
    echo "Checked at: " . $results['summary']['checked_at'] . "\n\n";

    // NPM
    echo "--- NPM Dependencies ---\n";
    if (!empty($results['npm']['vulnerabilities'])) {
        echo "Vulnerabilities found: " . count($results['npm']['vulnerabilities']) . "\n";
        foreach ($results['npm']['vulnerabilities'] as $v) {
            echo "  [{$v['severity']}] {$v['package']}: {$v['title']}\n";
        }
    } else {
        echo "No known vulnerabilities.\n";
    }
    if (!empty($results['npm']['outdated'])) {
        echo "Outdated packages: " . count($results['npm']['outdated']) . "\n";
        foreach ($results['npm']['outdated'] as $o) {
            echo "  {$o['package']}: {$o['current']} -> {$o['latest']}\n";
        }
    }
    if (!empty($results['npm']['errors'])) {
        foreach ($results['npm']['errors'] as $err) {
            echo "  Warning: $err\n";
        }
    }

    echo "\n--- Composer Dependencies ---\n";
    if (!empty($results['composer']['vulnerabilities'])) {
        echo "Vulnerabilities found: " . count($results['composer']['vulnerabilities']) . "\n";
        foreach ($results['composer']['vulnerabilities'] as $v) {
            echo "  {$v['package']}: {$v['title']} ({$v['cve']})\n";
        }
    } else {
        echo "No known vulnerabilities.\n";
    }
    if (!empty($results['composer']['errors'])) {
        foreach ($results['composer']['errors'] as $err) {
            echo "  Warning: $err\n";
        }
    }

    echo "\n--- Summary ---\n";
    echo "Total Vulnerabilities: {$results['summary']['total_vulnerabilities']}\n";
    echo "Critical/High: {$results['summary']['critical_high_vulnerabilities']}\n";
    echo "Outdated Packages: {$results['summary']['total_outdated']}\n";
    echo "Status: {$results['summary']['status']}\n";
}
