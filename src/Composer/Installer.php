<?php
// DX/src/Composer/Installer.php

namespace Sylius\DXBundle\Composer;

use Composer\Script\Event;

final class Installer
{
    public static function createStoreLoaderSymlink(Event $event): void
    {
        $io        = $event->getIO();
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir'); // np. /path/to/project/vendor
        $bundleDir = $vendorDir . '/sylius/dx'; // katalog Twojego bundle’a DX

        $source = $bundleDir . '/bin/store-loader.sh';
        if (!is_file($source)) {
            $io->writeError('  [SyliusDX] Warning: could not find "' . $source . '", skipping symlink.');
            return;
        }

        // Kalkulujemy katalog root (Sylius-Standard)
        $projectDir = dirname($vendorDir); // o poziom wyżej niż vendor/
        $binDir     = $projectDir . '/bin';
        $target     = $binDir . '/store-loader.sh';

        // 1) Upewnijmy się, że katalog <project>/bin istnieje:
        if (!is_dir($binDir)) {
            if (!mkdir($binDir, 0755, true) && !is_dir($binDir)) {
                $io->writeError('  [SyliusDX] Warning: failed to create directory "' . $binDir . '".');
                return;
            }
        }

        // 2) Jeżeli w miejscu docelowym już coś jest (plik lub symlink), usuńmy to:
        if (file_exists($target) || is_link($target)) {
            @unlink($target);
        }

        // 3) Stwórzmy dowiązanie symboliczne na ścieżce absolutnej:
        //    Dzięki temu unikniemy nieoczekiwanych problemów z interpretacją względnych ścieżek.
        $success = @symlink($source, $target);

        if ($success && file_exists($target)) {
            // Nadajmy prawa do wykonania, ale tylko jeśli link faktycznie istnieje:
            @chmod($target, 0755);
            $io->write('  [SyliusDX] Symlink created: ' . $target . ' → ' . $source);
        } else {
            $io->writeError('  [SyliusDX] Warning: failed to create symlink "' . $target . '".');
        }

        // 4) Na koniec sprawdźmy jeszcze, czy plik/link faktycznie istnieje:
        if (!file_exists($target)) {
            $io->writeError('  [SyliusDX] Warning: after hook did not find "' . $target . '". ' .
                'The store-loader script may not be available.');
        }
    }
}
