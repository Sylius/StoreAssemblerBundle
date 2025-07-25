<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\StoreAssemblerBundle\TwigHooks;

use Symfony\Component\Filesystem\Filesystem;

/** @experimental */
final class SyliusTwigHooksConfigurator
{
    private array $hooksConfig;

    public function __construct(
        private string $hooksConfigPath,
        private Filesystem $filesystem,
    ) {
        if (!file_exists($hooksConfigPath)) {
            $this->hooksConfig = ['sylius_twig_hooks' => ['hooks' => []]];
        } else {
            $this->hooksConfig = Yaml::parseFile($hooksConfigPath);
        }
    }

    public function load(): array
    {
        return $this->hooksConfig;
    }

    public function overrideLogo(string $area, string $template): self
    {
        $key = sprintf('sylius_%s.base.header.content.logo', $area);
        $this->hooksConfig['sylius_twig_hooks']['hooks'][$key] = [
            'content' => ['template' => $template],
        ];

        return $this;
    }

    public function disableBanner(string $area): self
    {
        $key = sprintf('sylius_%s.homepage.index.banner', $area);
        $this->hooksConfig['sylius_twig_hooks']['hooks'][$key]['enabled'] = false;

        return $this;
    }

    public function save(): void
    {
        $yaml = Yaml::dump($this->hooksConfig, 8);
        $this->filesystem->dumpFile($this->hooksConfigPath, $yaml);
    }

    public function configureBanner(string $section, mixed $hooksConfig, array $themeConfig): array
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

    public function overrideBanner(string $section, mixed $hooksConfig): array
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
