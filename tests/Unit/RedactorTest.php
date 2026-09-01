<?php

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
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

it('does not stringify view objects that render when cast to string', function () {
    $invoked = false;
    $invokable = new InvokableComponentVariable(function () use (&$invoked) {
        $invoked = true;

        throw new RuntimeException('Blade view methods must not run during redaction.');
    });
    $view = new class implements Htmlable, Renderable, Stringable
    {
        public function toHtml(): string
        {
            throw new RuntimeException('Views must not render during redaction.');
        }

        public function render(): string
        {
            return $this->toHtml();
        }

        public function __toString(): string
        {
            return $this->toHtml();
        }
    };

    expect((new Redactor)->clean([
        'blade' => $invokable,
        'view' => $view,
        'label' => 'Context view',
    ]))->toBe([
        'blade' => '['.InvokableComponentVariable::class.']',
        'view' => '['.$view::class.']',
        'label' => 'Context view',
    ])->and($invoked)->toBeFalse();
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
