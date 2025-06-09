<?php

declare(strict_types=1);

namespace Sylius\DXBundle\Command\Plugin;

use Sylius\DXBundle\Command\ConfigTrait;
use Sylius\DXBundle\Util\ManifestLocator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'sylius:dx:plugin:install',
    description: 'Install Sylius plugins according to their manifest.json'
)]
final class PluginInstallCommand extends Command
{
    use ConfigTrait;

    private SymfonyStyle $io;

    public function __construct(
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $store = $this->getStoreName();
        $this->validateStore($store);

        $plugins = $this->getPluginsByStore($store);
        if (empty($plugins)) {
            $this->io->warning('No plugins defined for this store. Nothing to do.');
            return Command::SUCCESS;
        }

        // --- Validation: ensure each plugin has a manifest definition folder ---
        $missing = [];
        foreach ($plugins as $package => $version) {
            [$vendor, $name] = explode('/', $package, 2);
            $path = $this->projectDir . '/config/plugins/' . $vendor . '/' . $name . '/' . $version;
            if (!is_dir($path)) {
                $missing[] = "$package@$version";
            }
        }
        if (!empty($missing)) {
            $this->io->error(
                'Missing plugin definitions for: ' . implode(', ', $missing) . ".\n"
                . 'Please ensure each listed plugin has a corresponding folder under config/plugins/{vendor}/{plugin}/{version}.'
            );
            return Command::FAILURE;
        }
        // ----------------------------------------------------------------------

        $this->io->title('[Plugin Installer] Installing plugins');

        foreach (array_keys($plugins) as $pluginName) {
            $manifestPath = ManifestLocator::locate($this->projectDir, $pluginName);
            $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

            foreach ($manifest['steps'] ?? [] as $cmd) {
                $this->io->section('[PluginPrepare] Running shell step');
                Process::fromShellCommandline($cmd, $this->projectDir)
                    ->run(fn($type, $buffer) => $this->io->write($buffer));
            }

            foreach ($manifest['configurators'] ?? [] as $entry) {
                $class = $entry['class'] ?? null;
                if (!$class || !class_exists($class)) {
                    throw new \RuntimeException("Configurator class {$class} not found");
                }

                $configurator = new $class();
                if (!$configurator instanceof ConfiguratorInterface) {
                    throw new \RuntimeException("{$class} must implement ConfiguratorInterface");
                }

                $configurator->configure($this->io, $this->projectDir, $entry);
            }
        }

        $this->io->success('[Plugin Installer] Wszystkie pluginy zostały przetworzone.');

        return Command::SUCCESS;
    }
}
