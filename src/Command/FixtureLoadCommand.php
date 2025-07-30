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
    name: 'sylius:store-assembler:fixture:load',
    description: 'Load fixtures for the store',
    hidden: true,
)]
/** @experimental */
class FixtureLoadCommand extends Command
{
    use ConfigTrait;

    public function __construct(private readonly string $projectDir)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('[Fixture Loader] Loading fixtures suite');
        $process = $this->runConsoleCommand([$this->getFixturesSuiteName(), '--no-interaction'], $io);
        if ($process->getExitCode() !== 0) {
            $io->error('Fixtures loading failed.');
            return Command::FAILURE;
        }

        $io->success('Fixtures suite loaded successfully.');

        return Command::SUCCESS;
    }

    private function runConsoleCommand(
        array $arguments,
        SymfonyStyle $io,
    ): Process {
        $parts = array_merge(['bin/console', 'sylius:fixtures:load'], $arguments);
        $process = Process::fromShellCommandline(
            implode(' ', $parts),
            $this->projectDir
        );

        $process
            ->setTty(Process::isTtySupported())
            ->setTimeout(0)
            ->run(function ($type, $buffer) use ($io) {
                $io->write($buffer);
            });

        return $process;
    }
}
