<?php

declare(strict_types=1);

namespace Sylius\StoreAssemblerBundle\Command\Plugin;

use Sylius\StoreAssemblerBundle\Command\ConfigTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'sylius:store-assembler:plugin:prepare',
    description: 'Require Sylius plugins in one go'
)]
class PluginPrepare extends Command
{
    use ConfigTrait;

    private SymfonyStyle $io;

    public function __construct(private readonly string $projectDir)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $store = $this->getStoreName();
        $this->validateStore($store);
        $plugins = $this->getPluginsByStore($store);

        // Validate manifest file exists for each declared plugin, trimming semver operators
        foreach ($plugins as $package => $version) {
            [$vendor, $name] = explode('/', $package, 2);

            // Strip leading semver operators (e.g. ^, ~, >=) to get directory name
            $dirVersion = preg_replace('/^[^0-9]*/', '', $version);
            $manifestDir = rtrim($this->projectDir, '/\\') . "/vendor/sylius/store-assembler/config/plugins/{$vendor}/{$name}/{$dirVersion}";
            $manifestFile = $manifestDir . '/manifest.json';

            if (!is_file($manifestFile)) {
                $this->io->error(sprintf(
                    'Plugin "%s"@"%s" is configured but not supported: missing manifest at "%s".',
                    $package,
                    $version,
                    $manifestDir
                ));

                return Command::FAILURE;
            }
        }

        // Configure composer for Sylius plugins
        Process::fromShellCommandline('composer config extra.symfony.allow-contrib true')
            ->setTimeout(0)
            ->run();
        Process::fromShellCommandline('composer config repositories.sylius composer https://sylius.repo.packagist.com/sylius/')
            ->setTimeout(0)
            ->run();

        $this->io->section('[Plugin Preparer] Installing plugins');
        foreach ($plugins as $package => $version) {
            Process::fromShellCommandline(
                sprintf('composer require %s:%s --no-scripts --no-interaction', $package, $version)
            )
                ->setTimeout(0)
                ->mustRun(fn(string $type, string $buffer) => $output->write($buffer));
        }

        $this->io->info('Require intervention/image for image processing');
        $this->runCommand(['composer', 'require', 'intervention/image:^3.1', '--no-interaction']);

        $this->io->title('[Plugin Preparer] Running Rector');
        $rectorConfigPath = $this->projectDir . '/vendor/sylius/store-assembler/config/rector.php';

        $exitCode = $this->runCommand([
            'vendor/bin/rector',
            'process',
            'src',
            '--config=' . $rectorConfigPath,
        ]);

        if ($exitCode !== 0) {
            $this->io->error(sprintf('Rector zakończył się kodem %d', $exitCode));
            return Command::FAILURE;
        }

        $this->io->success('Rector zakończony pomyślnie.');

        $this->io->success('[Plugin Preparer] Plugins installed successfully.');

        return Command::SUCCESS;
    }

    private function runCommand(array $command): int
    {
        $process = new Process($command, $this->projectDir);
        $process
            ->setTty(Process::isTtySupported())
            ->setTimeout(0)
            ->mustRun(fn(string $type, string $buffer) => $this->io->write($buffer));

        return $process->getExitCode();
    }
}
