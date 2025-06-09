<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    // 1) Ustal, w których ścieżkach Rector ma działać (kod hostującej aplikacji)
    //    Zakładamy, że główny katalog ze źródłami to <projectRoot>/src
    $projectDir = getcwd(); // katalog, w którym wywoływany jest Rector
    $rectorConfig->paths([ $projectDir . '/src' ]);

    // 2) Wczytaj store-preset, żeby poznać listę pluginów
    $storePresetPath = $projectDir . '/store-preset/store-preset.json';
    if (!file_exists($storePresetPath)) {
        // Jeśli nie ma store-preset, nic nie robimy dalej
        return;
    }

    try {
        $storeData = json_decode(file_get_contents($storePresetPath), true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        // Jeśli JSON niepoprawny, przerwijmy ładowanie Rectora
        echo 'Invalid JSON in ' . $storePresetPath . ': ' . $e->getMessage() . PHP_EOL;
        return;
    }

    $plugins = $storeData['plugins'] ?? [];
    if (!is_array($plugins)) {
        // Jeżeli nie ma klucza "plugins" lub nie jest to tablica, nic nie robimy
        $plugins = [];
    }

    // 3) Dla każdego pluginu spróbuj wczytać manifest.json z config/plugins/{vendor}/{package}/manifest.json
    foreach (array_keys($plugins) as $pluginName) {
        // pluginName w formacie "vendor/package"
        [$vendor, $package] = explode('/', $pluginName, 2) + [1 => null];
        if ($package === null) {
            // Niepoprawny format – pomiń
            continue;
        }

        $manifestPath = sprintf('%s/vendor/sylius/dx/config/plugins/%s/%s/manifest.json', $projectDir, $vendor, $package);
        if (!file_exists($manifestPath)) {
            // Jeżeli manifest.json nie istnieje, pomijamy
            continue;
        }

        try {
            $manifestData = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // Jeśli manifest jest błędny JSON, wyświetlamy informację i pomijamy ten plugin
            echo sprintf(
                "Invalid JSON in plugin manifest %s: %s%s",
                $manifestPath,
                $e->getMessage(),
                PHP_EOL
            );
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

            if (!defined($class . '::' . $const)) {
                echo sprintf(
                    "Warning: Rector set %s::%s not defined (plugin %s).%s",
                    $class,
                    $const,
                    $pluginName,
                    PHP_EOL
                );
                continue;
            }

            $rectorSetConstant = constant($class . '::' . $const);
            $rectorConfig->sets([ $rectorSetConstant ]);
        }
    }

    // 4) Zawsze na końcu:
    $rectorConfig->importNames();
    $rectorConfig->removeUnusedImports();
};
