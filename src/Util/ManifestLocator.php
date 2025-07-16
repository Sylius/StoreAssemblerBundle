<?php

namespace Sylius\StoreAssemblerBundle\Util;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;

/** @experimental */
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

        if (!is_dir($baseDir)) {
            throw new \RuntimeException(
                sprintf(
                    'Plugin "%s" is configured but not supported: missing directory "%s".',
                    $package,
                    $baseDir
                )
            );
        }

        if (!InstalledVersions::isInstalled("{$vendor}/{$name}")) {
            throw new \RuntimeException(sprintf(
                'Package "%s/%s" is not installed',
                $vendor,
                $name
            ));
        }
        $installed = InstalledVersions::getVersion("{$vendor}/{$name}");
        if ($installed === null) {
            throw new \RuntimeException(sprintf(
                'Unable to detect installed version for "%s/%s"',
                $vendor,
                $name
            ));
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
        usort($dirs, fn ($a, $b) => version_compare($b, $a));

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
