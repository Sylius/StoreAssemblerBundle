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

namespace Sylius\StoreAssemblerBundle\Util;

use Composer\InstalledVersions;

final class ComposerPackageHelper
{
    public static function getOwnComposerName(): string
    {
        // workaround: check installed packages first to determine package name quickly
        if (InstalledVersions::isInstalled('sylius/store-assembler-bundle')) {
            return 'sylius/store-assembler-bundle';
        }

        if (InstalledVersions::isInstalled('sylius/store-assembler')) {
            return 'sylius/store-assembler';
        }

        $composerJsonPath = __DIR__ . '/../../composer.json';
        if (!is_file($composerJsonPath)) {
            throw new \RuntimeException('Cannot find composer.json to determine package name.');
        }

        $content = file_get_contents($composerJsonPath);
        if ($content === false) {
            throw new \RuntimeException('Failed to read composer.json file.');
        }

        $composerData = json_decode($content, true);
        if (!is_array($composerData)) {
            throw new \RuntimeException('Invalid composer.json format.');
        }

        if (!isset($composerData['name']) || !is_string($composerData['name'])) {
            throw new \RuntimeException('Package name not set or invalid in composer.json');
        }

        return $composerData['name'];
    }
}
