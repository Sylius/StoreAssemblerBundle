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

namespace Sylius\DXBundle\Configurator;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

final class YamlNodeConfigurator implements ConfiguratorInterface
{
    public function configure(SymfonyStyle $io, string $projectDir, array $options): void
    {
        $io->title('Applying YAML key configurator');

        if (!isset($options['file'], $options['key'], $options['value'])) {
            throw new \InvalidArgumentException('YamlKeyConfigurator requires file, key and value options.');
        }

        $path = $projectDir . '/' . ltrim($options['file'], '/');
        $raw = file_exists($path) ? file_get_contents($path) : '';
        $config = $raw !== '' ? Yaml::parse($raw) : [];

        // Rozbijamy klucz "a.b.c" na ['a','b','c']
        $keys = explode('.', $options['key']);
        $ref = &$config;
        foreach ($keys as $segment) {
            if (!\is_array($ref)) {
                $ref = [];
            }
            if (!array_key_exists($segment, $ref)) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        // Teraz $ref wskazuje na najgłębsze miejsce – ustawiamy wartość
        $ref = $options['value'];

        // Dump z wcięciami 4
        file_put_contents($path, Yaml::dump($config, 4, 2));
        $io->success(sprintf('Set "%s" to "%s" in %s', $options['key'], json_encode($options['value']), $options['file']));
    }
}
