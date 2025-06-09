<?php

namespace Sylius\DXBundle\Util;

use Composer\Semver\VersionParser;

final class ManifestLocator
{
    /**
     * Locate the best manifest.json for an installed plugin version.
     *
     * @param string $projectDir Absolute path to project root
     * @param string $vendor     Package vendor, e.g. 'sylius'
     * @param string $package    Package name, e.g. 'cms-plugin'
     *
     * @return string Absolute path to manifest.json
     * @throws \RuntimeException if no suitable manifest found
     */
    public static function locate(string $projectDir, string $package): string
    {
        $vendor = explode('/', $package)[0] ?? 'sylius'; // Default to 'sylius' if no vendor provided
        $package = explode('/', $package)[1] ?? $package; // Extract package name after vendor
        $baseDir = rtrim($projectDir, '/\\') . "/vendor/sylius/dx/config/plugins/{$vendor}/{$package}/";

        // Read installed version from composer.lock
        $lockData = json_decode((string) file_get_contents($projectDir . '/composer.lock'), true);
        $installed = null;
        foreach (array_merge($lockData['packages'], $lockData['packages-dev'] ?? []) as $pkg) {
        if (($pkg['name'] ?? '') === "{$vendor}/{$package}") {
            $installed = $pkg['version'];
                break;
            }
        }

        if (!$installed) {
        throw new \RuntimeException("Package {$vendor}/{$package} not found in composer.lock");
    }

        // Normalize to major.minor
        $parser = new VersionParser();
        $normalized = $parser->normalize($installed); // e.g. 1.1.3 -> 1.1.3.0
        $parts = explode('.', $normalized);
        $target = $parts[0] . '.' . $parts[1];

        // Collect available version directories
        $dirs = array_filter(scandir($baseDir) ?: [], function ($d) use ($baseDir) {
        return is_dir($baseDir . $d) && preg_match('/^\d+\.\d+$/', $d);
        });

        // Sort descending: highest minor versions first
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

        throw new \RuntimeException("No manifest <= {$target} for {$vendor}/{$package}");
    }
}


// src/Configurator/YamlPathConfigurator.php

namespace Sylius\DXBundle\Configurator;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

final class YamlPathConfigurator implements ConfiguratorInterface
{
    public function configure(SymfonyStyle $io, string $projectDir, array $options): void
    {
        $io->section('Applying YAML path configurator');

        if (!isset($options['file'], $options['key'], $options['value'])) {
            throw new \InvalidArgumentException('YamlPathConfigurator requires "file", "key" and "value" options.');
        }

$file    = $projectDir . '/' . ltrim($options['file'], '/\\');
        $keyPath = explode('.', $options['key']);
        $value   = $options['value'];

        // Parse existing or start empty
        $raw    = file_exists($file) ? file_get_contents($file) : '';
        $config = $raw !== '' ? Yaml::parse($raw) : [];

        // Drill down into nested arrays
        $ref = &$config;
        foreach ($keyPath as $segment) {
    if (!is_array($ref)) {
        $ref = [];
            }
            if (!array_key_exists($segment, $ref)) {
        $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        // Set value at target node
        $ref = $value;

        // Dump with 4-space indent, 2-space block
        file_put_contents($file, Yaml::dump($config, 4, 2));
        $io->success(sprintf('Set "%s" to "%s" in %s', $options['key'], json_encode($value), $options['file']));
    }
}


// src/Command/PluginPrepareCommand.php (snippet)

// ... inside your execute() or prepare() method:

use Sylius\DXBundle\Util\ManifestLocator;
use Sylius\DXBundle\Configurator\ConfiguratorInterface;

// 1) Locate manifest for sylius/cms-plugin
