<?php

declare(strict_types=1);

namespace Sylius\DXBundle\Command\Plugin;

use Sylius\DXBundle\Command\ConfigTrait;
use Sylius\DXBundle\Configurator\ConfiguratorInterface;
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

        $this->io->title('[Plugin Installer] Installing plugins');

        foreach (array_keys($plugins) as $pluginName) {
            $manifestPath = ManifestLocator::locate($this->projectDir, $pluginName);
            $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

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

    private function runShellCommand(string $command): int
    {
        // Używamy Process::fromShellCommandline, żeby łatwo przekazać ciąg z parametrami
        $process = Process::fromShellCommandline($command, $this->projectDir);
        $process
            ->setTty(Process::isTtySupported())
            ->setTimeout(0)
            ->mustRun(function (string $type, string $buffer) {
                $this->io->write($buffer);
            });

        return $process->getExitCode();
    }
}
