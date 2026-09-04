<?php

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

it('recognizes credential names across casing and separators', function (string $key) {
    $redactor = new Redactor;

    expect($redactor->clean(['nested' => [$key => 'whole-credential-value']]))
        ->toBe(['nested' => [$key => '[redacted]']])
        ->and($redactor->isSensitive($key))->toBeTrue();
})->with([
    'accessToken',
    'APIKey',
    'stripeAPIKey',
    'stripe.apikey',
    'ClientSecret',
    'X-CSRF-Token',
    'PHP_AUTH_PW',
    'sessionID',
    'privateKey',
    'passwordConfirmation',
    'Proxy Authorization',
    'cookies',
    'session',
    'csrf',
    'jwt',
]);

it('does not hide names that merely resemble credentials', function (string $key) {
    $redactor = new Redactor;

    expect($redactor->clean(['nested' => [$key => 'useful diagnostic']]))
        ->toBe(['nested' => [$key => 'useful diagnostic']])
        ->and($redactor->isSensitive($key))->toBeFalse();
})->with([
    'token_count',
    'tokenCount',
    'secretary',
    'public_key',
    'publicKey',
    'keyboard',
    'authorization_status',
    'session_driver',
]);

it('adds normalized dotted path and wildcard rules without replacing the baseline', function () {
    $redactor = new Redactor(maskedPaths: ['customer.emailAddress', 'records.*.personal_id', 'billing.*Code', '', '   ']);
    $input = [
        'context' => [
            'customer' => ['email_address' => 'person@example.test', 'name' => 'Visible'],
            'records' => [['personalId' => '123', 'status' => 'active']],
            'billing' => ['accountCode' => '456'],
            'password' => 'still-secret',
        ],
        'email_address' => 'not-selected@example.test',
        'notcustomer' => ['emailAddress' => 'not-selected@example.test'],
    ];
    $expected = [
        'context' => [
            'customer' => ['email_address' => '[redacted]', 'name' => 'Visible'],
            'records' => [['personalId' => '[redacted]', 'status' => 'active']],
            'billing' => ['accountCode' => '[redacted]'],
            'password' => '[redacted]',
        ],
        'email_address' => 'not-selected@example.test',
        'notcustomer' => ['emailAddress' => 'not-selected@example.test'],
    ];

    expect($redactor->clean($input))->toBe($expected)
        ->and($redactor->redact($input))->toBe($expected)
        ->and($redactor->isSensitivePath('collectors.request.context.customer.emailAddress'))->toBeTrue()
        ->and($redactor->isSensitivePath('collectors.request.context.customer.name'))->toBeFalse();
});

it('accepts the full collector path when applying custom rules', function () {
    $redactor = new Redactor(maskedPaths: ['collectors.queries.0.bindings.0']);
    $input = ['bindings' => ['private value', 'visible value']];

    expect($redactor->redact($input, 'collectors.queries.0'))
        ->toBe(['bindings' => ['[redacted]', 'visible value']])
        ->and($redactor->clean($input, key: 'collectors.queries.0'))
        ->toBe(['bindings' => ['[redacted]', 'visible value']]);
});

it('redacts captured arrays without applying capture limits again', function () {
    $redactor = new Redactor(maxDepth: 1, maxStringLength: 2, maxArrayItems: 1);
    $input = [
        'rows' => [
            ['body' => str_repeat('a', 3_000), 'accessToken' => 'secret'],
            ['body' => 'another complete row', 'privateKey' => 'secret'],
        ],
        'count' => 2,
    ];
    $expected = $input;
    $expected['rows'][0]['accessToken'] = '[redacted]';
    $expected['rows'][1]['privateKey'] = '[redacted]';

    expect($redactor->redact($input))->toBe($expected);
});

it('preserves the root and non JSON values in the final redaction pass', function () {
    $resource = fopen('php://memory', 'rb');
    $object = new stdClass;
    $redactor = new Redactor;

    try {
        expect($redactor->redact(['driver' => 'file', 'csrf' => 'secret'], 'session'))
            ->toBe(['driver' => 'file', 'csrf' => '[redacted]'])
            ->and($redactor->redact(['value' => $resource]))->toBe(['value' => $resource])
            ->and($redactor->redact(['value' => $object]))->toBe(['value' => $object])
            ->and($redactor->redact('unchanged', 'password'))->toBe('unchanged');
    } finally {
        fclose($resource);
    }
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
