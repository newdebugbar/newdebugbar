<?php

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Illuminate\View\ComponentSlot;
use Illuminate\View\InvokableComponentVariable;
use NewDebugBar\Support\Redactor;

enum RedactorBackedValue: string
{
    case Ready = 'ready';
}

enum RedactorNamedValue
{
    case Waiting;
}

it('redacts sensitive values recursively', function () {
    $redactor = new Redactor;

    expect($redactor->clean([
        'authorization' => 'Bearer secret',
        'proxy-authorization' => 'Basic secret',
        'set-cookie' => 'session=secret',
        'x-api-key' => 'secret',
        'nested' => [
            'password' => 'secret',
            'clinic_name' => 'Example Clinic',
        ],
    ]))->toBe([
        'authorization' => '[redacted]',
        'proxy-authorization' => '[redacted]',
        'set-cookie' => '[redacted]',
        'x-api-key' => '[redacted]',
        'nested' => [
            'password' => '[redacted]',
            'clinic_name' => 'Example Clinic',
        ],
    ]);
});

it('bounds nested and long values', function () {
    $redactor = new Redactor(maxDepth: 2, maxStringLength: 4, maxArrayItems: 2);

    expect($redactor->clean([
        'long' => 'abcdef',
        'nested' => ['too_deep' => ['value']],
        'extra' => true,
    ]))->toBe([
        'long' => 'abcd…',
        'nested' => ['too_deep' => '[maximum depth reached]'],
        '__truncated__' => 1,
    ]);
});

it('normalizes common debug values without leaking object internals', function () {
    $resource = fopen('php://memory', 'rb');
    $stringable = new class implements Stringable
    {
        public function __toString(): string
        {
            return 'visible';
        }
    };

    try {
        expect((new Redactor)->clean([
            'date' => new DateTimeImmutable('2026-08-01T10:00:00+00:00'),
            'backed' => RedactorBackedValue::Ready,
            'named' => RedactorNamedValue::Waiting,
            'stringable' => $stringable,
            'object' => new stdClass,
            'resource' => $resource,
        ]))->toBe([
            'date' => '2026-08-01T10:00:00+00:00',
            'backed' => 'ready',
            'named' => 'Waiting',
            'stringable' => 'visible',
            'object' => '[stdClass]',
            'resource' => '[resource]',
        ]);
    } finally {
        fclose($resource);
    }
});

it('describes lazy view values without invoking or rendering them', function () {
    $invocations = 0;
    $variable = new InvokableComponentVariable(function () use (&$invocations): string {
        $invocations++;

        return 'unexpected invocation';
    });
    $view = new class implements Renderable, Stringable
    {
        public int $renders = 0;

        public function render(): string
        {
            $this->renders++;

            return 'unexpected render';
        }

        public function __toString(): string
        {
            return $this->render();
        }
    };
    $component = new class extends Component
    {
        public function render(): string
        {
            throw new RuntimeException('Captured components must not render.');
        }
    };

    expect((new Redactor)->clean([
        'nested' => ['blade' => $variable, 'view' => $view, 'component' => $component],
    ]))->toBe([
        'nested' => [
            'blade' => '['.InvokableComponentVariable::class.']',
            'view' => '['.$view::class.']',
            'component' => '['.$component::class.']',
        ],
    ])->and($invocations)->toBe(0)
        ->and($view->renders)->toBe(0);
});

it('keeps already rendered slots and inert stringables bounded and redacted', function () {
    $redactor = new Redactor(maxStringLength: 4);

    expect($redactor->clean([
        'slot' => new ComponentSlot('ready'),
        'html' => new HtmlString('safe'),
        'label' => new Illuminate\Support\Stringable('visible'),
        'token' => new HtmlString('secret'),
    ]))->toBe([
        'slot' => 'read…',
        'html' => 'safe',
        'label' => 'visi…',
        'token' => '[redacted]',
    ]);
});

it('uses an explicit safety policy for positional query bindings', function () {
    $redactor = new Redactor;
    $date = new DateTimeImmutable('2026-08-02T10:00:00+00:00');

    expect($redactor->cleanBindings([
        'private string',
        42,
        true,
        null,
        $date,
        ['nested' => 'private'],
        'token' => 'named secret',
    ]))->toBe([
        '[string]',
        42,
        true,
        null,
        '[datetime]',
        '[array]',
        'token' => '[redacted]',
    ])->and($redactor->cleanBindings(['private string'], 'none'))->toBe([])
        ->and($redactor->cleanBindings(['private string'], 'full'))->toBe(['private string']);
});

it('hides literal strings and comments inside captured sql', function () {
    $redactor = new Redactor;
    $sql = <<<'SQL'
        select * from patients
        where email = 'patient@example.com'
        and note = $$private note$$
        -- private comment
        /* another private comment */
        SQL;

    expect($redactor->cleanSql($sql))
        ->not->toContain('patient@example.com', 'private note', 'private comment', 'another private comment')
        ->toContain("email = '[string]'", "note = '[string]'", '-- comment hidden', '/* comment hidden */');
});
