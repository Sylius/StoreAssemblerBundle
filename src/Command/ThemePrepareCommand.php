<?php

declare(strict_types=1);

namespace Sylius\StoreAssemblerBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Filesystem\Filesystem;

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

        $themes = $this->getThemes();

        $this->io->text('Loaded theme areas: ' . implode(', ', array_keys($themes)));

        foreach ($themes as $section => $themeConfig) {
            $this->processThemeAssets($section, $themeConfig);
        }

        $this->createDirectory('shop');
        $this->createDirectory('admin');

        $this->io->section('Building assets with Webpack Encore');
        $process = Process::fromShellCommandline('yarn encore dev', $this->projectDir);
        $process->run(fn ($type, $buffer) => $this->io->write($buffer));
        if (!$process->isSuccessful()) {
            $this->io->error('Asset build failed.');
            return Command::FAILURE;
        }
        $this->io->success('Assets built successfully.');

        $this->io->section('Updating Twig hook configuration');
        $hooksConfigPath = $this->projectDir . '/config/packages/sylius_twig_hooks.yaml';

        if (!file_exists($hooksConfigPath)) {
            $baseConfig = [
                'sylius_twig_hooks' => [
                    'hooks' => []
                ]
            ];
            file_put_contents($hooksConfigPath, Yaml::dump($baseConfig, 4));
            $this->io->success(sprintf('Created new hook config: %s', $hooksConfigPath));
        }

        $hooksConfig = Yaml::parseFile($hooksConfigPath);
        foreach ($themes as $section => $themeConfig) {
            $hooksConfig = $this->configureLogo($section, $hooksConfig, $themeConfig);
            if ($section === 'shop') {
                $hooksConfig = $this->disableNewCollectionHookable($hooksConfig);
            }
            $hooksConfig = $this->configureBanner($section, $hooksConfig, $themeConfig);
        }

        file_put_contents($hooksConfigPath, Yaml::dump($hooksConfig, 8));

        $process->run(fn ($type, $buffer) => $this->io->write($buffer));

        $this->io->success('[Theme Loader] Theme loading complete!');

        return Command::SUCCESS;
    }

    private function processThemeAssets(string $section, array $themeConfig): void
    {
        $this->io->section(sprintf('Processing assets for area: %s', $section));

        $relativeStylesDir = sprintf('assets/%s/styles', $section);
        $stylesDir = $this->projectDir . '/' . ltrim($relativeStylesDir, '/');
        try {
            $this->filesystem->mkdir($stylesDir, 0755);
        } catch (\Exception $e) {
            $this->io->error(sprintf('Failed to create styles directory: %s', $stylesDir));
            return;
        }

        $themeFile = $stylesDir . '/custom-theme.scss';
        if (file_exists($themeFile)) {
            $this->io->warning(sprintf('SCSS theme file already exists, overwriting: %s', $themeFile));
        }
        $variables = $themeConfig['cssVariables'] ?? [];
        $lines = [':root {'];
        foreach ($variables as $name => $value) {
            $lines[] = sprintf('    %s: %s;', $name, $value);
        }
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '.btn-primary {';
        $btnColor = $variables['--bs-text-color'] ?? '#000';
        $btnBg = $variables['--bs-btn-bg'] ?? ($variables['--bs-primary'] ?? '#000');
        $btnHoverBg = $variables['--bs-btn-hover-bg'] ?? ($variables['--bs-primary'] ?? '#000');
        $btnFocusShadow = $variables['--bs-primary-rgb'] ?? '0, 0, 0';
        $btnActiveBg = $variables['--bs-primary'] ?? '#000';
        $btnDisabledBg = $variables['--bs-btn-bg'] ?? ($variables['--bs-primary'] ?? '#000');
        $lines[] = sprintf('    --bs-btn-color: %s;', $btnColor);
        $lines[] = sprintf('    --bs-btn-bg: %s;', $btnBg);
        $lines[] = sprintf('    --bs-btn-border-color: %s;', $btnBg);
        $lines[] = sprintf('    --bs-btn-hover-color: %s;', $btnColor);
        $lines[] = sprintf('    --bs-btn-hover-bg: %s;', $btnHoverBg);
        $lines[] = sprintf('    --bs-btn-hover-border-color: %s;', $btnHoverBg);
        $lines[] = sprintf('    --bs-btn-focus-shadow-rgb: %s;', $btnFocusShadow);
        $lines[] = sprintf('    --bs-btn-active-color: %s;', $btnColor);
        $lines[] = sprintf('    --bs-btn-active-bg: %s;', $btnActiveBg);
        $lines[] = sprintf('    --bs-btn-active-border-color: %s;', $btnActiveBg);
        $lines[] = '    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);';
        $lines[] = sprintf('    --bs-btn-disabled-color: %s;', $btnColor);
        $lines[] = sprintf('    --bs-btn-disabled-bg: %s;', $btnDisabledBg);
        $lines[] = sprintf('    --bs-btn-disabled-border-color: %s;', $btnDisabledBg);
        $lines[] = '}';

        try {
            $this->filesystem->dumpFile($themeFile, implode("\n", $lines) . "\n");
            $this->io->success(sprintf('Generated theme file for area "%s": %s', $section, $themeFile));
        } catch (\Exception $e) {
            $this->io->error(sprintf('Failed to write theme file: %s', $themeFile));
        }

        $entryFile = $this->projectDir . sprintf('/assets/%s/entrypoint.js', $section);
        $importLine = "import './styles/custom-theme.scss';";
        if (file_exists($entryFile)) {
            $content = file_get_contents($entryFile) ?: '';
            if (!str_contains($content, $importLine)) {
                file_put_contents($entryFile, rtrim($content, "\n") . "\n" . $importLine . "\n");
                $this->io->success(sprintf('Appended SCSS import to %s', $entryFile));
            } else {
                $this->io->text(sprintf('Import already present in %s', $entryFile));
            }
        } else {
            $this->io->warning(sprintf('entrypoint.js not found for area "%s": %s', $section, $entryFile));
        }
    }

    private function createDirectory(string $section): void
    {
        $publicImagesDir = sprintf('%s/public/build/app/%s/images', $this->projectDir, $section);
        if (!is_dir($publicImagesDir)) {
            Process::fromShellCommandline('mkdir -p ' . escapeshellarg($publicImagesDir), $this->projectDir)
                ->run(fn ($type, $buffer) => $this->io->write($buffer));
        }
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

    private function disableBanner(mixed $hooksConfig): array
    {
        $hooksConfig['sylius_twig_hooks']['hooks']['sylius_shop.homepage.index']['banner']['enabled'] = false;

        return $hooksConfig;
    }

    private function configureBanner(string $section, mixed $hooksConfig, array $themeConfig): array
    {
        if (empty($themeConfig['banner'])) {
            return $this->disableBanner($hooksConfig);
        }

        $bannerFilename = $themeConfig['banner'];
        $bannerSrc = sprintf('%s/store-preset/themes/%s/%s', $this->projectDir, $section, $bannerFilename);
        if (!file_exists($bannerSrc)) {
            $this->io->warning(sprintf('Banner image for area "%s" not found: %s', $section, $bannerSrc));
            return $this->disableBanner($hooksConfig);
        }

        $assetsImagesDir = $this->projectDir . sprintf('/assets/%s/images', $section);
        try {
            $this->filesystem->mkdir($assetsImagesDir, 0755);
        } catch (\Exception $e) {
            $this->io->warning(sprintf('Failed to create assets images directory: %s', $assetsImagesDir));
            return $hooksConfig;
        }
        $destBanner = $assetsImagesDir . '/' . $bannerFilename;
        if (!file_exists($destBanner)) {
            try {
                $this->filesystem->copy($bannerSrc, $destBanner);
                $this->io->success(sprintf('Copied banner image for area "%s" to assets: %s', $section, $destBanner));
            } catch (\Exception $e) {
                $this->io->warning(sprintf('Failed to copy banner image for area "%s": %s', $section, $e->getMessage()));
                return $hooksConfig;
            }
        }

        $publicImagesDir = sprintf('%s/public/build/app/%s/images', $this->projectDir, $section);
        $basename = pathinfo($bannerFilename, PATHINFO_FILENAME);
        $extension = pathinfo($bannerFilename, PATHINFO_EXTENSION);
        $pattern = sprintf('%s/%s.*.%s', $publicImagesDir, $basename, $extension);
        $matches = glob($pattern);
        if (empty($matches)) {
            $relativePath = sprintf('build/app/%s/images/%s', $section, $bannerFilename);
        } else {
            $hashedFull = $matches[0];
            $relativePath = substr($hashedFull, strlen($this->projectDir . '/public/'));
        }

        $twigDir = $this->projectDir . '/templates/' . $section;
        if (!is_dir($twigDir) && !mkdir($twigDir, 0755, true) && !is_dir($twigDir)) {
            $this->io->warning(sprintf('Failed to create templates dir for area "%s": %s', $section, $twigDir));
            return $hooksConfig;
        }
        $twigPath = $twigDir . '/banner.html.twig';
        $twigContent = <<<TWIG
{# templates/{$section}/banner.html.twig #}
<div class="container-fluid p-0 mb-6 overflow-hidden">
    <img src="{{ asset('{$relativePath}', 'app.{$section}') }}" alt="Banner {$section}" class="img-fluid w-100" />
</div>
TWIG;
        try {
            $this->filesystem->dumpFile($twigPath, $twigContent . "\n");
            $this->io->success(sprintf('Generated Twig banner template for area "%s": %s', $section, $twigPath));
        } catch (\Exception $e) {
            $this->io->warning(sprintf('Failed to write Twig banner template for area "%s": %s', $section, $e->getMessage()));
        }

        return $this->overrideBanner($section, $hooksConfig);
    }

    private function overrideBanner(string $section, mixed $hooksConfig): array
    {
        $hookKey = sprintf('sylius_%s.homepage.index', $section);
        $twigTemplate = sprintf('%s/banner.html.twig', $section);

        $hooksConfig['sylius_twig_hooks']['hooks'][$hookKey] = [
            'banner' => [
                'template' => $twigTemplate,
            ],
        ];

        return $hooksConfig;
    }

    private function configureLogo(string $section, array $hooksConfig, array $themeConfig): array
    {
        if (empty($themeConfig['logo'])) {
            return $hooksConfig;
        }
        $logoFilename = $themeConfig['logo'];
        $logoSrc = sprintf('%s/store-preset/themes/%s/%s', $this->projectDir, $section, $logoFilename);
        if (!file_exists($logoSrc)) {
            $this->io->warning(sprintf('Logo image for area "%s" not found: %s', $section, $logoSrc));
            return $hooksConfig;
        }
        $assetsImagesDir = $this->projectDir . sprintf('/assets/%s/images', $section);
        try {
            $this->filesystem->mkdir($assetsImagesDir, 0755);
        } catch (\Exception $e) {
            $this->io->warning(sprintf('Failed to create assets images directory: %s', $assetsImagesDir));
            return $hooksConfig;
        }
        $destLogo = $assetsImagesDir . '/' . $logoFilename;
        if (!file_exists($destLogo)) {
            try {
                $this->filesystem->copy($logoSrc, $destLogo, true);
                $this->io->success(sprintf('Copied logo for area "%s": %s', $section, $destLogo));
            } catch (\Exception $e) {
                $this->io->warning(sprintf('Failed to copy logo for area "%s": %s', $section, $e->getMessage()));
                return $hooksConfig;
            }
        }

        $publicImagesDir = sprintf('%s/public/build/app/%s/images', $this->projectDir, $section);
        $basename = pathinfo($logoFilename, PATHINFO_FILENAME);
        $extension = pathinfo($logoFilename, PATHINFO_EXTENSION);
        $pattern = sprintf('%s/%s.*.%s', $publicImagesDir, $basename, $extension);
        $matches = glob($pattern);
        if (!empty($matches)) {
            $hashedFull = $matches[0];
            $relativePath = substr($hashedFull, strlen($this->projectDir . '/public/'));
        } else {
            $relativePath = sprintf('build/app/%s/images/%s', $section, $logoFilename);
        }

        $twigDir = $this->projectDir . '/templates/' . $section;
        if (!is_dir($twigDir) && !mkdir($twigDir, 0755, true) && !is_dir($twigDir)) {
            $this->io->warning(sprintf('Failed to create templates dir for area "%s": %s', $section, $twigDir));
            return $hooksConfig;
        }
        $twigPath = $twigDir . '/logo.html.twig';
        $twigContent = <<<TWIG
{# templates/{$section}/logo.html.twig #}
<div class="app-shop-logo overflow-hidden w-60 h-70">
    <img src="{{ asset('{$relativePath}', 'app.{$section}') }}" alt="Logo {$section}" class="img-fluid w-60 h-70" />
</div>
TWIG;
        try {
            $this->filesystem->dumpFile($twigPath, $twigContent . "\n");
            $this->io->success(sprintf('Generated Twig logo template for area "%s": %s', $section, $twigPath));
        } catch (\Exception $e) {
            $this->io->warning(sprintf('Failed to write Twig logo template for area "%s": %s', $section, $e->getMessage()));
        }
        return $this->overrideLogo($section, $hooksConfig);
    }
}
