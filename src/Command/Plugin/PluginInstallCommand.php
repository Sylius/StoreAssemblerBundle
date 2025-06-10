<?php

declare(strict_types=1);

namespace Sylius\StoreAssemblerBundle\Command\Plugin;

use Sylius\StoreAssemblerBundle\Command\ConfigTrait;
use Sylius\StoreAssemblerBundle\Util\ManifestLocator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'sylius:store-assembler:plugin:install',
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

        // Check for unsupported plugins (no manifest available)
        $supported = [];
        $unsupported = [];
        foreach ($plugins as $package => $version) {
            try {
                // Validate manifest exists
                ManifestLocator::locate($this->projectDir, $package);
                $supported[$package] = $version;
            } catch (\RuntimeException $e) {
                $unsupported[] = "$package@$version";
            }
        }

        if (!empty($unsupported)) {
            $this->io->warning(
                sprintf(
                    'The following plugins are configured but not supported (missing manifest): %s.\n'.
                    'To support them, add a manifest under config/plugins/{vendor}/{name}/{version} or remove them from store-preset.',
                    implode(', ', $unsupported)
                )
            );
        }

        if (empty($supported)) {
            $this->io->warning('No supported plugins to install after manifest validation.');
            return Command::SUCCESS;
        }

        $this->io->title('[Plugin Installer] Installing plugins');

        foreach (array_keys($supported) as $packageName) {
            $manifestPath = ManifestLocator::locate($this->projectDir, $packageName);
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
                if (!$configurator instanceof \Sylius\StoreAssemblerBundle\Configurator\ConfiguratorInterface) {
                    throw new \RuntimeException("{$class} must implement ConfiguratorInterface");
                }

                $configurator->configure($this->io, $this->projectDir, $entry);
            }
        }

        $this->io->success('[Plugin Installer] All supported plugins have been processed.');

        return Command::SUCCESS;
    }
}
