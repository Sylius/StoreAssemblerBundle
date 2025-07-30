<?php

declare(strict_types=1);

namespace Sylius\StoreAssemblerBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
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

        $openSource = [];
        $paid = [];

        foreach ($plugins as $package => $version) {
            [$vendor, $name] = explode('/', $package, 2);
            $dirVersion = preg_replace('/^[^0-9]*/', '', $version);

            $manifestDir = rtrim($this->projectDir, '/\\')
                . "/vendor/sylius/store-assembler/config/plugins/{$vendor}/{$name}/{$dirVersion}";
            $manifestFile = $manifestDir . '/manifest.json';

            if (!is_file($manifestFile)) {
                $this->io->error(sprintf(
                    'Plugin "%s"@"%s" is configured but missing manifest.json in "%s".',
                    $package,
                    $version,
                    $manifestDir
                ));
                return Command::FAILURE;
            }

            $manifest = json_decode((string)file_get_contents($manifestFile), true);
            $type = $manifest['type'] ?? 'open-source';

            if (strtolower($type) === 'paid') {
                $paid[$package] = $version;
            } else {
                $openSource[$package] = $version;
            }
        }

        (new Process(
            ['composer', 'config', 'extra.symfony.allow-contrib', 'true'],
            $this->projectDir
        ))->mustRun();

        if (!empty($openSource)) {
            $this->io->section('[Plugin Preparer] Installing open-source plugins');
            foreach ($openSource as $package => $version) {
                $this->io->text(sprintf(' → %s:%s', $package, $version));
                (new Process(
                    ['composer', 'require', "{$package}:{$version}", '--no-scripts', '--no-interaction'],
                    $this->projectDir
                ))
                    ->setTimeout(0)
                    ->mustRun(fn($type, $buffer) => $output->write($buffer));
            }
        }

        if (!empty($paid)) {
            $this->io->section('[Plugin Preparer] Configuring private Sylius repository');
            (new Process(
                ['composer', 'config', 'repositories.sylius', 'composer', 'https://sylius.repo.packagist.com/sylius/'],
                $this->projectDir
            ))->mustRun();

            $this->io->section('[Plugin Preparer] Checking existing credentials');
            $usernameCheck = new Process(
                ['composer', 'config', '--auth', 'http-basic.sylius.repo.packagist.com.username'],
                $this->projectDir
            );
            $passwordCheck = new Process(
                ['composer', 'config', '--auth', 'http-basic.sylius.repo.packagist.com.password'],
                $this->projectDir
            );
            $usernameCheck->run();
            $passwordCheck->run();

            $hasUsername = $usernameCheck->getExitCode() === 0 && trim($usernameCheck->getOutput()) !== '';
            $hasPassword = $passwordCheck->getExitCode() === 0 && trim($passwordCheck->getOutput()) !== '';

            if ($hasUsername && $hasPassword) {
                $this->io->text('✔ Found existing credentials via Composer; skipping prompt.');
            } else {
                $username = $this->io->ask('Sylius repo username');
                $token = $this->io->askHidden('Sylius repo token');

                (new Process(
                    ['composer', 'config', '--auth', 'http-basic.sylius.repo.packagist.com', $username, $token],
                    $this->projectDir
                ))->mustRun();
            }

            $this->io->section('[Plugin Preparer] Verifying access (dry‑run)');
            foreach ($paid as $package => $version) {
                $this->io->text(sprintf(' 🔍 Testing %s:%s', $package, $version));
                try {
                    (new Process(
                        ['composer', 'require', "{$package}:{$version}", '--no-scripts', '--no-interaction', '--dry-run'],
                        $this->projectDir
                    ))
                        ->setTimeout(0)
                        ->mustRun();
                } catch (ProcessFailedException $e) {
                    $this->io->error(sprintf(
                        'Access denied for %s:%s. Please verify your token and repository credentials.',
                        $package,
                        $version
                    ));
                    return Command::FAILURE;
                }
            }

            $this->io->section('[Plugin Preparer] Installing paid plugins');
            foreach ($paid as $package => $version) {
                $this->io->text(sprintf(' → %s:%s', $package, $version));
                (new Process(
                    ['composer', 'require', "{$package}:{$version}", '--no-scripts', '--no-interaction'],
                    $this->projectDir
                ))
                    ->setTimeout(0)
                    ->mustRun(fn($type, $buffer) => $output->write($buffer));
            }
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
        $this->io->success('[Plugin Preparer] All plugins installed successfully.');

        return Command::SUCCESS;
    }

    private function runCommand(array $command): int
    {
        $process = new Process($command, $this->projectDir);
        $process
            ->setTty(Process::isTtySupported())
            ->setTimeout(0)
            ->mustRun(fn($type, $buffer) => $this->io->write($buffer));

        return $process->getExitCode();
    }
}
