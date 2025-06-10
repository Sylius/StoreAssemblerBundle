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

namespace Sylius\StoreAssemblerBundle\Configurator;

use Symfony\Component\Console\Style\SymfonyStyle;

interface ConfiguratorInterface
{
    /**
     * Perform configuration step based on manifest options.
     *
     * @param SymfonyStyle $io
     * @param string       $projectDir
     * @param array        $options    Entry from manifest.json
     */
    public function configure(SymfonyStyle $io, string $projectDir, array $options): void;
}
