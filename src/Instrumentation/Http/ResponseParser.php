<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Instrumentation\Http;

final class ResponseParser
{
    /** @param array<array-key, mixed> $body */
    public function promptTokens(string $provider, array $body): int
    {
        $usage = is_array($body['usage'] ?? null) ? $body['usage'] : [];
        $usageMeta = is_array($body['usageMetadata'] ?? null) ? $body['usageMetadata'] : [];

        return match ($provider) {
            'anthropic' => $this->toInt($usage['input_tokens'] ?? null),
            'gemini' => $this->toInt($usageMeta['promptTokenCount'] ?? null),
            'ollama' => $this->toInt($body['prompt_eval_count'] ?? null),
            default => $this->toInt($usage['prompt_tokens'] ?? null),
        };
    }

    /** @param array<array-key, mixed> $body */
    public function completionTokens(string $provider, array $body): int
    {
        $usage = is_array($body['usage'] ?? null) ? $body['usage'] : [];
        $usageMeta = is_array($body['usageMetadata'] ?? null) ? $body['usageMetadata'] : [];

        return match ($provider) {
            'anthropic' => $this->toInt($usage['output_tokens'] ?? null),
            'gemini' => $this->toInt($usageMeta['candidatesTokenCount'] ?? null),
            'ollama' => $this->toInt($body['eval_count'] ?? null),
            default => $this->toInt($usage['completion_tokens'] ?? null),
        };
    }

    /** @param array<array-key, mixed> $body */
    public function completion(string $provider, array $body): ?string
    {
        return match ($provider) {
            'anthropic' => $this->extractAnthropicCompletion($body),
            'gemini' => $this->extractGeminiCompletion($body),
            'ollama' => $this->extractOllamaCompletion($body),
            default => $this->extractOpenAiCompletion($body),
        };
    }

    /** @param array<array-key, mixed> $body */
    public function finishReason(string $provider, array $body): string
    {
        return match ($provider) {
            'anthropic' => is_string($body['stop_reason'] ?? null) ? $body['stop_reason'] : 'stop',
            'gemini' => $this->extractGeminiFinishReason($body),
            'ollama' => is_string($body['done_reason'] ?? null) ? $body['done_reason'] : 'stop',
            default => $this->extractOpenAiFinishReason($body),
        };
    }

    /** @param array<array-key, mixed> $body */
    public function model(string $provider, array $body): string
    {
        return match ($provider) {
            'anthropic' => is_string($body['model'] ?? null) ? $body['model'] : 'unknown',
            'gemini' => is_string($body['modelVersion'] ?? null) ? $body['modelVersion'] : 'unknown',
            default => is_string($body['model'] ?? null) ? $body['model'] : 'unknown',
        };
    }

    /** @param array<array-key, mixed> $body */
    private function extractAnthropicCompletion(array $body): ?string
    {
        $content = is_array($body['content'] ?? null) ? $body['content'] : [];
        $first = is_array($content[0] ?? null) ? $content[0] : [];
        $text = $first['text'] ?? null;

        return is_string($text) ? $text : null;
    }

    /** @param array<array-key, mixed> $body */
    private function extractGeminiCompletion(array $body): ?string
    {
        $candidates = is_array($body['candidates'] ?? null) ? $body['candidates'] : [];
        $first = is_array($candidates[0] ?? null) ? $candidates[0] : [];
        $content = is_array($first['content'] ?? null) ? $first['content'] : [];
        $parts = is_array($content['parts'] ?? null) ? $content['parts'] : [];
        $part = is_array($parts[0] ?? null) ? $parts[0] : [];
        $text = $part['text'] ?? null;

        return is_string($text) ? $text : null;
    }

    /** @param array<array-key, mixed> $body */
    private function extractOllamaCompletion(array $body): ?string
    {
        $response = $body['response'] ?? null;

        if (is_string($response)) {
            return $response;
        }

        $message = is_array($body['message'] ?? null) ? $body['message'] : [];
        $content = $message['content'] ?? null;

        return is_string($content) ? $content : null;
    }

    /** @param array<array-key, mixed> $body */
    private function extractOpenAiCompletion(array $body): ?string
    {
        $choices = is_array($body['choices'] ?? null) ? $body['choices'] : [];
        $first = is_array($choices[0] ?? null) ? $choices[0] : [];
        $message = is_array($first['message'] ?? null) ? $first['message'] : [];
        $content = $message['content'] ?? null;

        return is_string($content) ? $content : null;
    }

    /** @param array<array-key, mixed> $body */
    private function extractGeminiFinishReason(array $body): string
    {
        $candidates = is_array($body['candidates'] ?? null) ? $body['candidates'] : [];
        $first = is_array($candidates[0] ?? null) ? $candidates[0] : [];
        $reason = $first['finishReason'] ?? 'STOP';

        return strtolower(is_string($reason) ? $reason : 'STOP');
    }

    /** @param array<array-key, mixed> $body */
    private function extractOpenAiFinishReason(array $body): string
    {
        $choices = is_array($body['choices'] ?? null) ? $body['choices'] : [];
        $first = is_array($choices[0] ?? null) ? $choices[0] : [];
        $reason = $first['finish_reason'] ?? 'stop';

        return is_string($reason) ? $reason : 'stop';
    }

    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
