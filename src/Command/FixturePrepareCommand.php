<?php

declare(strict_types=1);

namespace Sylius\StoreAssemblerBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

#[AsCommand(
    name: 'sylius:store-assembler:fixture:prepare',
    description: 'Prepare fixtures for the store',
    hidden: true,
)]
class FixturePrepareCommand extends Command
{
    use ConfigTrait;

    public function __construct(private readonly string $projectDir)
    {
        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $fixturesPath = $this->getFixturesPath();

        $io->section('[Fixture Loader] Preparing fixtures suite');

        $filesystem = new Filesystem();
        $target = $this->projectDir . '/config/packages/fixtures.yaml';
        try {
            $filesystem->copy($fixturesPath, $target, true);
        } catch (IOExceptionInterface $exception) {
            $io->error(sprintf('Failed to copy fixtures file: %s', $exception->getMessage()));
            return Command::FAILURE;
        }

        $imagesDir = sprintf('%s/store-preset/fixtures/images', $this->projectDir);
        if (is_dir($imagesDir)) {
            $io->section('Copying images');
            $destinationDir = $this->projectDir . '/var/fixture_img';
            try {
                $filesystem->mkdir($destinationDir);
                $filesystem->mirror($imagesDir, $destinationDir);
            } catch (IOExceptionInterface $exception) {
                $io->error(sprintf('Failed to copy images: %s', $exception->getMessage()));
                return Command::FAILURE;
            }
        } else {
            $io->warning('No images directory found, skipping image copy.');
        }

        $io->success('Fixtures prepared successfully.');
        return Command::SUCCESS;
    }
}
