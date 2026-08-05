<?php

declare(strict_types=1);

namespace Prism\Prism\Providers;

use Generator;
use Prism\Prism\Audio\AudioResponse as TextToSpeechResponse;
use Prism\Prism\Audio\SpeechToTextRequest;
use Prism\Prism\Audio\TextResponse as SpeechToTextResponse;
use Prism\Prism\Audio\TextToSpeechRequest;
use Prism\Prism\Embeddings\Request as EmbeddingsRequest;
use Prism\Prism\Embeddings\Response as EmbeddingsResponse;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Images\Request as ImagesRequest;
use Prism\Prism\Images\Response as ImagesResponse;
use Prism\Prism\Moderation\Request as ModerationRequest;
use Prism\Prism\Moderation\Response as ModerationResponse;
use Prism\Prism\Structured\Request as StructuredRequest;
use Prism\Prism\Structured\Response as StructuredResponse;
use Prism\Prism\Text\Request as TextRequest;
use Prism\Prism\Text\Response as TextResponse;

/**
 * Mirrors the real Prism\Prism\Providers\Provider abstract class: every
 * action has a default implementation that throws for unsupported
 * providers, using the concrete provider's class name in the message.
 *
 * @see https://github.com/prism-php/prism/blob/main/src/Providers/Provider.php
 */
abstract class Provider
{
    public function text(TextRequest $request): TextResponse
    {
        throw PrismException::unsupportedProviderAction('text', class_basename($this));
    }

    public function structured(StructuredRequest $request): StructuredResponse
    {
        throw PrismException::unsupportedProviderAction('structured', class_basename($this));
    }

    public function embeddings(EmbeddingsRequest $request): EmbeddingsResponse
    {
        throw PrismException::unsupportedProviderAction('embeddings', class_basename($this));
    }

    public function images(ImagesRequest $request): ImagesResponse
    {
        throw PrismException::unsupportedProviderAction('images', class_basename($this));
    }

    public function moderation(ModerationRequest $request): ModerationResponse
    {
        throw PrismException::unsupportedProviderAction('moderation', class_basename($this));
    }

    public function textToSpeech(TextToSpeechRequest $request): TextToSpeechResponse
    {
        throw PrismException::unsupportedProviderAction('textToSpeech', class_basename($this));
    }

    public function speechToText(SpeechToTextRequest $request): SpeechToTextResponse
    {
        throw PrismException::unsupportedProviderAction('speechToText', class_basename($this));
    }

    /**
     * @return Generator<int, mixed>
     */
    public function stream(TextRequest $request): Generator
    {
        throw PrismException::unsupportedProviderAction('stream', class_basename($this));
    }
}
