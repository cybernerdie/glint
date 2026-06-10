<?php

declare(strict_types=1);

use Cybernerdie\Glint\Instrumentation\Http\ResponseParser;

beforeEach(function () {
    $this->parser = new ResponseParser;
});

it('parses openai prompt tokens', function () {
    $body = ['usage' => ['prompt_tokens' => 42, 'completion_tokens' => 10]];
    expect($this->parser->promptTokens('openai', $body))->toBe(42);
});

it('parses openai completion tokens', function () {
    $body = ['usage' => ['prompt_tokens' => 42, 'completion_tokens' => 10]];
    expect($this->parser->completionTokens('openai', $body))->toBe(10);
});

it('parses openai completion text', function () {
    $body = ['choices' => [['message' => ['content' => 'Hello!']]]];
    expect($this->parser->completion('openai', $body))->toBe('Hello!');
});

it('parses openai finish reason', function () {
    $body = ['choices' => [['finish_reason' => 'stop']]];
    expect($this->parser->finishReason('openai', $body))->toBe('stop');
});

it('parses anthropic prompt tokens', function () {
    $body = ['usage' => ['input_tokens' => 100, 'output_tokens' => 50]];
    expect($this->parser->promptTokens('anthropic', $body))->toBe(100);
});

it('parses anthropic completion tokens', function () {
    $body = ['usage' => ['input_tokens' => 100, 'output_tokens' => 50]];
    expect($this->parser->completionTokens('anthropic', $body))->toBe(50);
});

it('parses anthropic completion text', function () {
    $body = ['content' => [['text' => 'Hi there']]];
    expect($this->parser->completion('anthropic', $body))->toBe('Hi there');
});

it('parses anthropic finish reason', function () {
    $body = ['stop_reason' => 'end_turn'];
    expect($this->parser->finishReason('anthropic', $body))->toBe('end_turn');
});

it('parses gemini prompt tokens', function () {
    $body = ['usageMetadata' => ['promptTokenCount' => 30, 'candidatesTokenCount' => 20]];
    expect($this->parser->promptTokens('gemini', $body))->toBe(30);
});

it('parses gemini completion tokens', function () {
    $body = ['usageMetadata' => ['promptTokenCount' => 30, 'candidatesTokenCount' => 20]];
    expect($this->parser->completionTokens('gemini', $body))->toBe(20);
});

it('parses ollama prompt tokens', function () {
    $body = ['prompt_eval_count' => 15];
    expect($this->parser->promptTokens('ollama', $body))->toBe(15);
});

it('parses ollama completion tokens', function () {
    $body = ['eval_count' => 25];
    expect($this->parser->completionTokens('ollama', $body))->toBe(25);
});

it('parses ollama completion text', function () {
    $body = ['message' => ['content' => 'Ollama response']];
    expect($this->parser->completion('ollama', $body))->toBe('Ollama response');
});

it('parses ollama finish reason', function () {
    $body = ['done_reason' => 'stop'];
    expect($this->parser->finishReason('ollama', $body))->toBe('stop');
});

it('returns 0 for missing openai prompt tokens', function () {
    expect($this->parser->promptTokens('openai', []))->toBe(0);
});

it('returns 0 for missing openai completion tokens', function () {
    expect($this->parser->completionTokens('openai', []))->toBe(0);
});

it('returns 0 for missing anthropic prompt tokens', function () {
    expect($this->parser->promptTokens('anthropic', []))->toBe(0);
});

it('returns 0 for missing anthropic completion tokens', function () {
    expect($this->parser->completionTokens('anthropic', []))->toBe(0);
});

it('returns 0 for missing gemini prompt tokens', function () {
    expect($this->parser->promptTokens('gemini', []))->toBe(0);
});

it('returns 0 for missing gemini completion tokens', function () {
    expect($this->parser->completionTokens('gemini', []))->toBe(0);
});

it('returns 0 for missing ollama prompt tokens', function () {
    expect($this->parser->promptTokens('ollama', []))->toBe(0);
});

it('returns 0 for missing ollama completion tokens', function () {
    expect($this->parser->completionTokens('ollama', []))->toBe(0);
});

it('returns null for missing openai completion text', function () {
    expect($this->parser->completion('openai', []))->toBeNull();
});

it('returns null for missing anthropic completion text', function () {
    expect($this->parser->completion('anthropic', []))->toBeNull();
});

it('returns stop for missing openai finish reason', function () {
    expect($this->parser->finishReason('openai', []))->toBe('stop');
});

it('returns stop for missing anthropic finish reason', function () {
    expect($this->parser->finishReason('anthropic', []))->toBe('stop');
});

it('parses gemini completion text', function () {
    $body = [
        'candidates' => [
            ['content' => ['parts' => [['text' => 'Gemini answer']]]],
        ],
    ];
    expect($this->parser->completion('gemini', $body))->toBe('Gemini answer');
});

it('returns null for missing gemini completion text', function () {
    expect($this->parser->completion('gemini', []))->toBeNull();
});

it('parses gemini finish reason', function () {
    $body = [
        'candidates' => [['finishReason' => 'STOP']],
    ];
    expect($this->parser->finishReason('gemini', $body))->toBe('stop');
});

it('returns stop for missing gemini finish reason', function () {
    expect($this->parser->finishReason('gemini', []))->toBe('stop');
});

it('returns stop for missing ollama finish reason', function () {
    expect($this->parser->finishReason('ollama', []))->toBe('stop');
});

it('returns null for missing ollama completion text', function () {
    expect($this->parser->completion('ollama', []))->toBeNull();
});

it('parses openai model from response', function () {
    $body = ['model' => 'gpt-4o'];
    expect($this->parser->model('openai', $body))->toBe('gpt-4o');
});

it('returns unknown for missing openai model', function () {
    expect($this->parser->model('openai', []))->toBe('unknown');
});

it('parses anthropic model from response', function () {
    $body = ['model' => 'claude-3-5-sonnet-20241022'];
    expect($this->parser->model('anthropic', $body))->toBe('claude-3-5-sonnet-20241022');
});

it('returns unknown for missing anthropic model', function () {
    expect($this->parser->model('anthropic', []))->toBe('unknown');
});

it('parses gemini model version from response', function () {
    $body = ['modelVersion' => 'gemini-1.5-pro'];
    expect($this->parser->model('gemini', $body))->toBe('gemini-1.5-pro');
});

it('returns unknown for missing gemini model version', function () {
    expect($this->parser->model('gemini', []))->toBe('unknown');
});
