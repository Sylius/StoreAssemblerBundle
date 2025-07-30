<?php

declare(strict_types=1);

namespace Sylius\StoreAssemblerBundle\Command;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/** @experimental */
trait ConfigTrait
{
    public function getConfig(): array
    {
        $configPath = sprintf('%s/store-preset/store-preset.json', $this->projectDir);
        if (!file_exists($configPath)) {
            throw new \RuntimeException(sprintf('Configuration file not found: %s', $configPath));
        }

        try {
            return json_decode((string)file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Failed to parse JSON from %s: %s', $configPath, $e->getMessage()));
        }
    }

    public function getFixturesSuiteName(): string
    {
        $filePath = $this->getFixturesFilePath();
        $filesystem = new Filesystem();
        if (!$filesystem->exists($filePath)) {
            throw new \RuntimeException(sprintf('Fixtures file not found: %s', $filePath));
        }
        $config = Yaml::parseFile($filePath);
        $suiteName = array_keys($config['sylius_fixtures']['suites'])[0] ?? null;
        if ($suiteName === null) {
            throw new \RuntimeException(sprintf('No fixtures suite found in file: %s', $filePath));
        }

        return $suiteName;
    }

    public function getPlugins(): array
    {
        $config = $this->getConfig();

        if (!isset($config['plugins']) || !is_array($config['plugins'])) {
            throw new \RuntimeException(sprintf('Invalid or missing "plugins" section in configuration: %s', json_encode($config)));
        }

        return $config['plugins'];
    }

    public function getFixturesFilePath(): string
    {
        $fixturesPath = sprintf('%s/store-preset/fixtures/fixtures.yaml', $this->projectDir);

        if (!file_exists($fixturesPath)) {
            throw new \RuntimeException(sprintf('Fixtures file not found: %s', $fixturesPath));
        }

        return $fixturesPath;
    }

    public function getThemes(): array
    {
        $config = $this->getConfig();

        if (!isset($config['themes']) || !is_array($config['themes'])) {
            throw new \RuntimeException(sprintf('Invalid or missing "themes" section in configuration: %s', json_encode($config)));
        }

        return $config['themes'];
    }
}
