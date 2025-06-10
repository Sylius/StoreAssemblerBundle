<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Sylius\StoreAssemblerBundle\Util\ManifestLocator;

return static function (RectorConfig $rectorConfig): void {
    $projectDir = getcwd();
    $rectorConfig->paths([$projectDir . '/src']);

    $storePresetPath = $projectDir . '/store-preset/store-preset.json';
    if (!file_exists($storePresetPath)) {
        return;
    }

    $storeData = json_decode((string) file_get_contents($storePresetPath), true, 512, JSON_THROW_ON_ERROR);
    $plugins = $storeData['plugins'] ?? [];
    if (!is_array($plugins)) {
        return;
    }

    foreach (array_keys($plugins) as $pluginName) {
        try {
            $manifestPath = ManifestLocator::locate($projectDir, $pluginName);
        } catch (\RuntimeException) {
            continue;
        }

        try {
            $manifestData = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            echo sprintf("Invalid JSON in plugin manifest %s: %s%s", $manifestPath, $e->getMessage(), PHP_EOL);
            continue;
        }

        $sets = $manifestData['rector-sets'] ?? [];
        if (!is_array($sets)) {
            continue;
        }

        foreach ($sets as $setReference) {
            if (!is_string($setReference) || !str_contains($setReference, '::')) {
                continue;
            }

            [$class, $const] = explode('::', $setReference, 2);
            $constantName = $class . '::' . $const;
            if (!defined($constantName)) {
                echo sprintf("Warning: Rector set %s not defined (plugin %s).%s", $constantName, $pluginName, PHP_EOL);
                continue;
            }

            $rectorConfig->sets([constant($constantName)]);
        }
    }

    $rectorConfig->importNames();
    $rectorConfig->removeUnusedImports();
};