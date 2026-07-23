<?php

declare(strict_types=1);

use Cybernerdie\Glint\Support\GenerationFingerprint;

it('creates the same fingerprint for equivalent normalized payloads', function () {
    $messages = [
        ['role' => 'user', 'content' => 'Hello'],
        ['content' => 'World', 'role' => 'assistant'],
    ];

    $objectMessages = [
        (object) ['content' => 'Hello', 'role' => 'user'],
        (object) ['role' => 'assistant', 'content' => 'World'],
    ];

    expect(GenerationFingerprint::make(
        provider: 'openai',
        model: 'gpt-4o',
        messages: $messages,
        temperature: 0.5,
        maxTokens: 200,
        isStreaming: false,
    ))->toBe(GenerationFingerprint::make(
        provider: 'OpenAI',
        model: 'gpt-4o',
        messages: $objectMessages,
        temperature: 0.5,
        maxTokens: 200,
        isStreaming: false,
    ));
});

it('creates different fingerprints for materially different payloads', function () {
    $base = GenerationFingerprint::make(
        provider: 'openai',
        model: 'gpt-4o',
        messages: [['role' => 'user', 'content' => 'Hello']],
        temperature: null,
        maxTokens: null,
        isStreaming: false,
    );

    $different = GenerationFingerprint::make(
        provider: 'openai',
        model: 'gpt-4o',
        messages: [['role' => 'user', 'content' => 'Different']],
        temperature: null,
        maxTokens: null,
        isStreaming: false,
    );

    expect($base)->not->toBe($different);
});
