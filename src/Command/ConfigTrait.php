<?php

declare(strict_types=1);

namespace Sylius\StoreAssemblerBundle\Command;

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

    public function getStoreName(): string
    {
        return $this->getConfig()['name'];
    }

    public function getPlugins(): array
    {
        $config = $this->getConfig();

        if (!isset($config['plugins']) || !is_array($config['plugins'])) {
            throw new \RuntimeException(sprintf('Invalid or missing "plugins" section in configuration: %s', json_encode($config)));
        }

        return $config['plugins'];
    }

    public function getFixturesPath(): string
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
