<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AssetController
{
    public function touchIcon(): BinaryFileResponse
    {
        return response()->file(__DIR__.'/../../../resources/img/apple-touch-icon.png', [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
