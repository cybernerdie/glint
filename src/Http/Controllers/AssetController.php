<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AssetController
{
    /**
     * Serve the Apple touch icon from the package so consumers never
     * need a vendor:publish step for Glint assets.
     */
    public function touchIcon(): BinaryFileResponse
    {
        return response()->file(__DIR__.'/../../../resources/img/apple-touch-icon.png', [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
