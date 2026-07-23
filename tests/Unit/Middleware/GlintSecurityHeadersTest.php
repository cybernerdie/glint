<?php

declare(strict_types=1);

use Cybernerdie\Glint\Middleware\GlintSecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

it('adds X-Frame-Options: DENY header', function () {
    $request = Request::create('/glint', 'GET');
    $middleware = new GlintSecurityHeaders;

    $response = $middleware->handle($request, fn () => new Response('ok'));

    expect($response->headers->get('X-Frame-Options'))->toBe('DENY');
});

it('adds X-Content-Type-Options: nosniff header', function () {
    $request = Request::create('/glint', 'GET');
    $middleware = new GlintSecurityHeaders;

    $response = $middleware->handle($request, fn () => new Response('ok'));

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('adds Referrer-Policy: same-origin header', function () {
    $request = Request::create('/glint', 'GET');
    $middleware = new GlintSecurityHeaders;

    $response = $middleware->handle($request, fn () => new Response('ok'));

    expect($response->headers->get('Referrer-Policy'))->toBe('same-origin');
});

it('does not override existing X-Frame-Options when false is passed as replace', function () {
    $request = Request::create('/glint', 'GET');
    $middleware = new GlintSecurityHeaders;

    $existingResponse = new Response('ok');
    $existingResponse->headers->set('X-Frame-Options', 'SAMEORIGIN');

    $response = $middleware->handle($request, fn () => $existingResponse);

    expect($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');
});
