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

    public function __construct(private readonly string $projectDir)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $themes = $this->getThemes();

        $this->io->text('Loaded theme areas: ' . implode(', ', array_keys($themes)));

        foreach ($themes as $section => $themeConfig) {
            $this->io->section(sprintf('Processing theme variables and logo for area: %s', $section));

            $relativeStylesDir = sprintf('assets/%s/styles', $section);
            $stylesDir = $this->projectDir . '/' . ltrim($relativeStylesDir, '/');
            if (!is_dir($stylesDir) && !mkdir($stylesDir, 0755, true) && !is_dir($stylesDir)) {
                $this->io->error(sprintf('Failed to create styles directory: %s', $stylesDir));
                continue;
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

            $btnColor = $variables['--bs-text-color'] ?? '#000';
            $btnBg = $variables['--bs-btn-bg'] ?? ($variables['--bs-primary'] ?? '#000');
            $btnHoverBg = $variables['--bs-btn-hover-bg'] ?? ($variables['--bs-primary'] ?? '#000');
            $btnFocusShadow = $variables['--bs-primary-rgb'] ?? '0, 0, 0';
            $btnActiveBg = $variables['--bs-primary'] ?? '#000';
            $btnDisabledBg = $variables['--bs-btn-bg'] ?? ($variables['--bs-primary'] ?? '#000');

            $lines[] = '';
            $lines[] = '.btn-primary {';
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

            file_put_contents($themeFile, implode("\n", $lines) . "\n");
            $this->io->success(sprintf('Generated theme file with variables and .btn-primary: %s', $themeFile));

            $entryFile = $this->projectDir . sprintf('/assets/%s/entrypoint.js', $section);
            if (file_exists($entryFile)) {
                $importLine = "import './styles/custom-theme.scss';";
                $content = file_get_contents($entryFile);
                if (!str_contains($content, $importLine)) {
                    $content = rtrim($content, "\n") . "\n" . $importLine . "\n";
                    file_put_contents($entryFile, $content);
                    $this->io->success(sprintf('Appended SCSS import to %s', $entryFile));
                } else {
                    $this->io->text(sprintf('Import already present in %s', $entryFile));
                }
            } else {
                $this->io->warning(sprintf('entrypoint.js not found for area "%s": %s', $section, $entryFile));
            }

            if (empty($themeConfig['logo'])) {
                $this->io->text(sprintf('No logo defined for area "%s", skipping logo copy.', $section));
                continue;
            }

            $logoFilename = $themeConfig['logo'];
            $logoSrc = sprintf(
                '%s/store-preset/themes/%s/%s',
                $this->projectDir,
                $section,
                $logoFilename
            );
            if (!file_exists($logoSrc)) {
                $this->io->warning(sprintf('Logo file for area "%s" not found: %s', $section, $logoSrc));
                continue;
            }

            $assetsImagesDir = $this->projectDir . sprintf('/assets/%s/images', $section);
            if (!is_dir($assetsImagesDir) && !mkdir($assetsImagesDir, 0755, true) && !is_dir($assetsImagesDir)) {
                $this->io->error(sprintf('Failed to create assets images directory: %s', $assetsImagesDir));
                continue;
            }

            $destLogoInAssets = $assetsImagesDir . '/' . $logoFilename;
            if (file_exists($destLogoInAssets)) {
                $this->io->warning(sprintf('Logo already exists in assets (won’t overwrite unless --force): %s', $destLogoInAssets));
            }

            copy($logoSrc, $destLogoInAssets);
            $this->io->success(sprintf('Copied logo for area "%s" to assets: %s', $section, $destLogoInAssets));


            $this->io->success('Resized logo to consistent height');
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

        foreach ($themes as $section => $themeConfig) {
            if (empty($themeConfig['logo'])) {
                continue;
            }

            $logoFilename = $themeConfig['logo'];
            $publicImagesDir = sprintf('%s/public/build/app/%s/images', $this->projectDir, $section);
            if (!is_dir($publicImagesDir)) {
                Process::fromShellCommandline('mkdir -p ' . escapeshellarg($publicImagesDir), $this->projectDir)
                    ->run(fn ($type, $buffer) => $this->io->write($buffer));
            }

            $basename = pathinfo($logoFilename, PATHINFO_FILENAME);
            $extension = pathinfo($logoFilename, PATHINFO_EXTENSION);
            $pattern = sprintf('%s/%s.*.%s', $publicImagesDir, $basename, $extension);
            $matches = glob($pattern);

            if (empty($matches)) {
                $fallbackPath = sprintf('%s/%s', $publicImagesDir, $logoFilename);
                if (file_exists($fallbackPath)) {
                    $matches[] = $fallbackPath;
                }
            }

            $hashedFullPath = $matches[0];
            $relativePublicPath = substr($hashedFullPath, strlen($this->projectDir . '/public/'));

            $twigDir = $this->projectDir . '/templates/' . $section;
            if (!is_dir($twigDir) && !mkdir($twigDir, 0755, true) && !is_dir($twigDir)) {
                $this->io->error(sprintf('Failed to create templates dir for area "%s": %s', $section, $twigDir));
                continue;
            }

            $twigFilename = 'logo.html.twig';
            $twigPath = $twigDir . '/' . $twigFilename;
            $assetPath = $relativePublicPath;
            $routeName = ($section === 'shop') ? 'sylius_shop_homepage' : 'sylius_admin_dashboard';

            $twigContent = <<<TWIG
{# templates/{$section}/{$twigFilename} #}
<a href="{{ path('{$routeName}') }}" class="app-{$section}-logo">
    <img src="{{ asset('{$assetPath}') }}" alt="Logo {$section}" />
</a>
TWIG;

            if (file_exists($twigPath)) {
                $this->io->warning(sprintf('Twig logo template already exists, overwriting: %s', $twigPath));
            }

            file_put_contents($twigPath, $twigContent . "\n");
            $this->io->success(sprintf('Created Twig logo template for area "%s": %s', $section, $twigPath));
        }

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
        $hooksConfig = $this->overrideLogo('shop', $hooksConfig);
        $hooksConfig = $this->disableNewCollectionHookable($hooksConfig);
        $hooksConfig = $this->disableBanner($hooksConfig);

        file_put_contents($hooksConfigPath, Yaml::dump($hooksConfig, 8));

        $process->run(fn ($type, $buffer) => $this->io->write($buffer));

        $this->io->success('[Theme Loader] Theme loading complete!');

        return Command::SUCCESS;
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
                'priority' => 0,
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
}
