<?php

declare(strict_types=1);

namespace Sylius\StoreAssemblerBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'sylius:store-assembler:theme:prepare',
    description: 'Prepare and configure themes for the store',
    hidden: true,
)]
/** @experimental */
class ThemePrepareCommand extends Command
{
    use ConfigTrait;

    private SymfonyStyle $io;
    private Filesystem $filesystem;

    public function __construct(private readonly string $projectDir)
    {
        parent::__construct();
        $this->filesystem = new Filesystem();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->copyAssets();

        $process = Process::fromShellCommandline('yarn encore dev', $this->projectDir);
        $process->run(function (string $type, string $buffer) {
            Process::ERR === $type ? $this->io->error($buffer) : $this->io->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $this->io->error('Asset build failed.');
            return Command::FAILURE;
        }

        $this->io->success('Assets built successfully.');

        $this->io->section('Updating Twig hook configuration');
        $hooksConfigPath = $this->projectDir . '/config/packages/sylius_twig_hooks.yaml';

        $filesystem = new Filesystem();
        if (!$filesystem->exists($hooksConfigPath)) {
            $baseConfig = [
                'sylius_twig_hooks' => [
                    'hooks' => []
                ]
            ];
            $filesystem->dumpFile($hooksConfigPath, Yaml::dump($baseConfig, 8));
            $this->io->success(sprintf('Created new hook config: %s', $hooksConfigPath));
        }

        $hooksConfig = Yaml::parseFile($hooksConfigPath);
        $this->createBannerTemplate();
        $hooksConfig = $this->configureBannerHook($hooksConfig);
        $this->createLogoTemplate();
        $hooksConfig = $this->configureLogoHook($hooksConfig);
        $hooksConfig = $this->disableNewCollectionHookable($hooksConfig);
        $hooksConfig = $this->disableLatestDealsHookable($hooksConfig);

        $filesystem->dumpFile($hooksConfigPath, Yaml::dump($hooksConfig, 8));

        $this->io->success('[Theme Loader] Theme loading complete!');

        return Command::SUCCESS;
    }

    public function copyAssets(): void
    {
        $this->filesystem->mirror(
            originDir: $this->projectDir . '/store-preset/assets',
            targetDir: $this->projectDir . '/assets',
            options: ['override' => true]
        );
    }

    private function overrideLogo(string $section, mixed $hooksConfig): array
    {
        $hookKey = sprintf('sylius_%s.base.header.content.logo', $section);
        $twigTemplate = sprintf('%s/logo.html.twig', $section);

        $hooksConfig['sylius_twig_hooks']['hooks'][$hookKey] = [
            'content' => [
                'template' => $twigTemplate,
            ],
        ];

        return $hooksConfig;
    }

    private function disableNewCollectionHookable(mixed $hooksConfig): array
    {
        $hooksConfig['sylius_twig_hooks']['hooks']['sylius_shop.homepage.index']['new_collection']['enabled'] = false;

        return $hooksConfig;
    }
    private function disableLatestDealsHookable(mixed $hooksConfig): array
    {
        $hooksConfig['sylius_twig_hooks']['hooks']['sylius_shop.homepage.index']['latest_deals']['enabled'] = false;

        return $hooksConfig;
    }

    private function disableBanner(mixed $hooksConfig): array
    {
        $hooksConfig['sylius_twig_hooks']['hooks']['sylius_shop.homepage.index']['banner']['enabled'] = false;

        return $hooksConfig;
    }

    private function createBannerTemplate(): void
    {
        $twigPath = $this->projectDir . '/templates/shop/banner.html.twig';
        $twigContent = <<<TWIG
{# templates/shop/banner.html.twig #}
<div class="w-100 d-flex justify-content-center align-items-center position-relative">
    <img src="{{ asset('build/app/shop/images/banner.png', 'app.shop') }}" class="img-fluid" alt="Banner shop" />
</div>
TWIG;
        $this->filesystem->dumpFile($twigPath, $twigContent . "\n");
        $this->io->success(sprintf('Generated Twig banner template for area "shop": %s', $twigPath));
    }

    private function configureBannerHook(array $hooksConfig): array
    {
        $hooksConfig['sylius_twig_hooks']['hooks']['sylius_shop.homepage.index'] = [
            'banner' => [
                'template' => 'shop/banner.html.twig',
            ],
        ];

        return $hooksConfig;
    }

    private function configureLogoHook(array $hooksConfig): array
    {
        $hooksConfig['sylius_twig_hooks']['hooks']['sylius_shop.base.header.content'] = [
            'logo' => [
                'template' => 'shop/logo.html.twig',
            ],
        ];

        return $hooksConfig;
    }

    private function createLogoTemplate(): void
    {
        $twigPath = $this->projectDir . '/templates/shop/logo.html.twig';
        $twigContent = <<<TWIG
{# templates/shop/logo.html.twig #}
<div class="col">
    <img src="{{ asset('build/app/shop/images/logo.png', 'app.shop') }}" alt="Banner shop" style="max-width:150px; height:auto;" />
</div>
TWIG;
        $this->filesystem->dumpFile($twigPath, $twigContent . "\n");
        $this->io->success(sprintf('Generated Twig logo template for area "shop": %s', $twigPath));
    }
}
