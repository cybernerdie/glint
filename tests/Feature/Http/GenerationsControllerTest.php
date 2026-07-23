<?php

declare(strict_types=1);

use Cybernerdie\Glint\Models\GlintGeneration;

beforeEach(function () {
    config()->set('glint.enabled', true);
});

it('returns 200 on generations index', function () {
    $this->get(route('glint.generations.index'))
        ->assertStatus(200);
});

it('returns 404 for unknown generation', function () {
    $this->get(route('glint.generations.show', 'non-existent-generation-id'))
        ->assertStatus(404);
});

it('shows generation detail', function () {
    $generation = GlintGeneration::factory()->create([
        'name' => 'My Generation',
        'provider' => 'openai',
        'model' => 'gpt-4o',
    ]);

    $this->get(route('glint.generations.show', $generation->id))
        ->assertStatus(200)
        ->assertSee('openai')
        ->assertSee('gpt-4o');
});

it('filters generations by provider', function () {
    GlintGeneration::factory()->create(['name' => 'OpenAI gen', 'provider' => 'openai']);
    GlintGeneration::factory()->create(['name' => 'Anthropic gen', 'provider' => 'anthropic', 'model' => 'claude-3-5-sonnet-20241022']);

    $this->get(route('glint.generations.index', ['provider' => 'openai']))
        ->assertStatus(200)
        ->assertSee('openai');
});

it('filters generations by model', function () {
    GlintGeneration::factory()->create(['name' => 'GPT4 gen', 'model' => 'gpt-4o']);

    $this->get(route('glint.generations.index', ['model' => 'gpt-4o']))
        ->assertStatus(200)
        ->assertSee('gpt-4o');
});
