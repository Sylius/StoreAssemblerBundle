<?php

namespace Sylius\StoreAssemblerBundle\Util;

use Composer\Semver\VersionParser;

final class ManifestLocator
{
    /**
     * Locate the best manifest.json for an installed plugin version.
     *
     * @param string $projectDir Absolute path to project root
     * @param string $package    Package name, e.g. 'sylius/cms-plugin'
     *
     * @return string Absolute path to manifest.json
     * @throws \RuntimeException if no suitable manifest found or plugin unsupported
     */
    public static function locate(string $projectDir, string $package): string
    {
        $parts = explode('/', $package, 2);
        $vendor = $parts[0] ?? 'sylius';
        $name = $parts[1] ?? $parts[0];
        $baseDir = rtrim($projectDir, '/\\') . "/vendor/sylius/store-assembler/config/plugins/{$vendor}/{$name}/";

        // Fail fast if plugin directory is missing
        if (!is_dir($baseDir)) {
            throw new \RuntimeException(
                sprintf(
                    'Plugin "%s" is configured but not supported: missing directory "%s".',
                    $package,
                    $baseDir
                )
            );
        }

        // Read installed version from composer.lock
        $lockPath = $projectDir . '/composer.lock';
        if (!is_file($lockPath) || !is_readable($lockPath)) {
            throw new \RuntimeException("composer.lock not found or unreadable at {$lockPath}");
        }

        $lockData = json_decode((string) file_get_contents($lockPath), true);
        $installed = null;
        foreach (array_merge($lockData['packages'] ?? [], $lockData['packages-dev'] ?? []) as $pkg) {
            if (($pkg['name'] ?? '') === "{$vendor}/{$name}") {
                $installed = $pkg['version'];
                break;
            }
        }

        if (!$installed) {
            throw new \RuntimeException("Package {$vendor}/{$name} not found in composer.lock");
        }

        // Normalize to major.minor
        $parser = new VersionParser();
        $normalized = $parser->normalize($installed); // e.g. 1.1.3 -> 1.1.3.0
        $parts = explode('.', $normalized);
        $target = $parts[0] . '.' . $parts[1];

        // Collect available version directories
        $dirs = array_filter(scandir($baseDir), function ($d) use ($baseDir) {
            return is_dir($baseDir . $d) && preg_match('/^\d+\.\d+$/', $d);
        });

        // Sort descending: highest versions first
        usort($dirs, fn($a, $b) => version_compare($b, $a));

        // Find first dir <= target
        foreach ($dirs as $ver) {
            if (version_compare($ver, $target, '<=')) {
                $path = $baseDir . $ver . '/manifest.json';
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        throw new \RuntimeException(
            sprintf(
                'No manifest found <= version %s for plugin "%s" in %s',
                $target,
                $package,
                $baseDir
            )
        );
    }
}
