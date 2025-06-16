<?php

declare(strict_types=1);

namespace Sylius\StoreAssemblerBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'sylius:store-assembler:plugin:prepare',
    description: 'Configure and install Sylius plugins according to their manifest.json',
    hidden: true,
)]
/** @experimental */
class PluginPrepareCommand extends Command
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

        $plugins = $this->getPlugins();

        foreach ($plugins as $package => $version) {
            [$vendor, $name] = explode('/', $package, 2);

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

        (new Process(
            ['composer', 'config', 'extra.symfony.allow-contrib', 'true'],
            $this->projectDir
        ))->run();

        $this->io->section('[Plugin Preparer] Installing plugins');
        foreach ($plugins as $package => $version) {
            $process = new Process(
                ['composer', 'require', sprintf('%s:%s', $package, $version), '--no-scripts', '--no-interaction'],
                $this->projectDir
            );
            $process
                ->setTimeout(0)
                ->mustRun(fn (string $type, string $buffer) => $output->write($buffer));
        }

        $this->io->title('[Plugin Preparer] Running Rector');
        $rectorConfigPath = $this->projectDir . '/vendor/sylius/store-assembler/config/rector.php';

        $exitCode = $this->runCommand([
            'vendor/bin/rector',
            'process',
            'src',
            '--config=' . $rectorConfigPath,
        ]);

        if ($exitCode !== 0) {
            $this->io->error(sprintf('Rector exited with code %d', $exitCode));
            return Command::FAILURE;
        }

        $this->io->success('Rector completed successfully.');

        $this->io->success('[Plugin Preparer] Plugins installed successfully.');

        return Command::SUCCESS;
    }

    private function runCommand(array $command): int
    {
        $process = new Process($command, $this->projectDir);
        $process
            ->setTty(Process::isTtySupported())
            ->setTimeout(0)
            ->mustRun(fn (string $type, string $buffer) => $this->io->write($buffer));

        return $process->getExitCode();
    }
}
