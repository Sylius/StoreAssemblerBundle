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

namespace Sylius\StoreAssemblerBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/** @experimental */
final class StoreAssemblerController
{
    public function healthCheck(): JsonResponse
    {
        return new JsonResponse(
            [
                'status' => 'ok',
                'message' => 'Store Assembler is running',
            ],
            Response::HTTP_OK
        );
    }

    public function processStorePreset(): JsonResponse
    {
        return new JsonResponse(
            [
                'status' => 'ok',
                'message' => 'Store preset processed successfully',
            ],
            Response::HTTP_OK
        );
    }
}
