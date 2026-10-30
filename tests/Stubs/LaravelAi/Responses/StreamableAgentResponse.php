<?php

declare(strict_types=1);

namespace Laravel\Ai\Responses;

use Closure;
use IteratorAggregate;
use Laravel\Ai\Responses\Data\Meta;
use Traversable;

class StreamableAgentResponse implements IteratorAggregate
{
    private array $callbacks = [];

    public function __construct(
        public string $invocationId,
        private Closure $generator,
        public ?Meta $meta = null,
    ) {}

    public function then(callable $callback): self
    {
        $this->callbacks[] = $callback;

        return $this;
    }

    public function adoptStateFrom(StreamedAgentResponse $response): self
    {
        $this->meta = $response->meta;

        return $this;
    }

    public function getIterator(): Traversable
    {
        yield from ($this->generator)();

        foreach ($this->callbacks as $callback) {
            $callback(new StreamedAgentResponse($this->meta ?? new Meta));
        }
    }
}
