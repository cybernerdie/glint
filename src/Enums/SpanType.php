<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Enums;

enum SpanType: string
{
    case Span = 'span';
    case ToolCall = 'tool_call';
    case Retrieval = 'retrieval';
    case Embedding = 'embedding';
    case Custom = 'custom';
    case Generation = 'generation';
}
