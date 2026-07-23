<?php

declare(strict_types=1);

arch('strict types')
    ->expect('Cybernerdie\Glint')
    ->toUseStrictTypes();

arch('all concrete classes are final')
    ->expect('Cybernerdie\Glint')
    ->classes()
    ->toBeFinal()
    ->ignoring([
        'Cybernerdie\Glint\GlintApplicationServiceProvider',
        'Cybernerdie\Glint\GlintServiceProvider',
        'Cybernerdie\Glint\Models',
    ]);

arch('no debug calls')
    ->expect('Cybernerdie\Glint')
    ->not->toUse(['dd', 'dump', 'var_dump', 'ray']);

arch('enums are string backed')
    ->expect('Cybernerdie\Glint\Enums')
    ->toBeStringBackedEnums();

arch('events are readonly')
    ->expect('Cybernerdie\Glint\Events')
    ->toBeReadonly();

arch('null objects implement the trace/span/generation contracts')
    ->expect('Cybernerdie\Glint\Null\NullTrace')
    ->toImplement('Cybernerdie\Glint\Contracts\TraceInterface');

arch('instrumentation drivers implement the driver contract')
    ->expect('Cybernerdie\Glint\Instrumentation')
    ->toImplement('Cybernerdie\Glint\Contracts\InstrumentationDriver')
    ->ignoring('Cybernerdie\Glint\Instrumentation\Prism\TracingPrismManager')
    ->ignoring('Cybernerdie\Glint\Instrumentation\Prism\TracingProvider')
    ->ignoring('Cybernerdie\Glint\Instrumentation\Http\ResponseParser')
    ->ignoring('Cybernerdie\Glint\Instrumentation\NeuronAi\GlintNeuronAiObserver');
