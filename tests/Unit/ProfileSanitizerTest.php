<?php

use NewDebugBar\Support\ProfileSanitizer;
use NewDebugBar\Support\Redactor;

it('keeps baseline credentials hidden when custom rules also hide semantic field names', function () {
    $activity = [['property' => 'apiKey', 'submitted' => 'ACTIVITY-SENTINEL']];
    $profile = sanitizerProfile([
        'livewire' => ['items' => $activity, 'activity' => $activity, 'components' => [['properties' => [['path' => 'apiKey', 'server_value' => 'COMPONENT-SENTINEL']]]]],
        'authorization' => ['items' => [['user' => ['identifier_name' => 'apiKey', 'identifier' => 'AUTH-SENTINEL']]]],
        'models' => ['items' => [['key_name' => 'apiKey', 'key' => 'MODEL-SENTINEL']]],
    ]);
    $redactor = new Redactor(maskedPaths: ['property', 'path', 'identifier_name', 'key_name']);
    $clean = (new ProfileSanitizer($redactor))->clean($redactor->redact($profile));
    expect(json_encode($clean))->not->toContain('ACTIVITY-SENTINEL', 'COMPONENT-SENTINEL', 'AUTH-SENTINEL', 'MODEL-SENTINEL');
});

it('synchronizes a whole masked request query or input container', function (string $path) {
    $profile = sanitizerProfile(['request' => [
        'url' => 'https://example.test/?patient=QUERY-SENTINEL',
        'query' => ['patient' => 'QUERY-SENTINEL'],
        'input' => ['patient' => 'QUERY-SENTINEL', 'body_only' => 'keep body'],
    ]]);
    $clean = (new ProfileSanitizer(new Redactor(maskedPaths: [$path])))->clean($profile);
    expect(json_encode($clean))->not->toContain('QUERY-SENTINEL');
    if ($path === 'request.query') {
        expect($clean['sections']['request']['payload']['input']['body_only'])->toBe('keep body');
    }
})->with(['request.query', 'request.input']);

it('masks credential model keys and their authorization identifier copy', function () {
    $profile = sanitizerProfile([
        'models' => ['items' => [['key_name' => 'apiKey', 'key' => 'MODEL-KEY-SENTINEL']]],
        'authorization' => ['items' => [['arguments' => [['route_key_name' => 'apiKey', 'route_key' => 'AUTH-KEY-SENTINEL', 'identifier' => 'AUTH-KEY-SENTINEL']]]]],
    ]);
    $clean = (new ProfileSanitizer(new Redactor))->clean($profile);
    expect($clean['sections']['models']['payload']['items'][0]['key'])->toBe('[redacted]')
        ->and($clean['sections']['authorization']['payload']['items'][0]['arguments'][0]['identifier'])->toBe('[redacted]')
        ->and(json_encode($clean))->not->toContain('MODEL-KEY-SENTINEL', 'AUTH-KEY-SENTINEL');
});

it('synchronizes query and input masks without changing a different body value', function () {
    $profile = sanitizerProfile(['request' => [
        'url' => 'https://example.test/?patient=PRIVATE&filter[name]=PRIVATE-NAME',
        'query' => ['patient' => 'PRIVATE', 'filter' => ['name' => 'PRIVATE-NAME']],
        'input' => ['patient' => 'body override', 'filter' => ['name' => 'PRIVATE-NAME']],
    ]]);
    $clean = (new ProfileSanitizer(new Redactor(maskedPaths: ['request.query.patient', 'request.input.filter.name'])))->clean($profile);
    expect($clean['sections']['request']['payload']['input']['patient'])->toBe('body override')
        ->and($clean['sections']['request']['payload']['query']['filter']['name'])->toBe('[redacted]')
        ->and(json_encode($clean))->not->toContain('PRIVATE', 'PRIVATE-NAME');
});

function sanitizerProfile(array $payloads): array
{
    $sections = [];

    foreach ($payloads as $key => $payload) {
        $sections[$key] = [
            'label' => ucfirst($key),
            'summary' => ['count' => 1, 'duration_ms' => 1.5],
            'payload' => $payload,
        ];
    }

    return [
        'id' => '00000000-0000-4000-8000-000000000001',
        'profile_type' => 'http',
        'environment' => 'local',
        'metrics' => ['duration_ms' => 12.5],
        'sections' => $sections,
    ];
}

it('masks credential sentinels across every captured section without removing its envelope', function (string $section) {
    $profile = sanitizerProfile([
        $section => ['items' => [['context' => ['accessToken' => 'credential-sentinel', 'status' => 'ready']]]],
    ]);

    $clean = (new ProfileSanitizer(new Redactor))->clean($profile);

    expect($clean['sections'][$section]['label'])->toBe(ucfirst($section))
        ->and($clean['sections'][$section]['summary'])->toBe($profile['sections'][$section]['summary'])
        ->and($clean['sections'][$section]['payload']['items'][0]['context'])->toBe([
            'accessToken' => '[redacted]',
            'status' => 'ready',
        ])
        ->and(json_encode($clean))->not->toContain('credential-sentinel');
})->with([
    'overview', 'request', 'queries', 'http_client', 'queue', 'mail', 'notifications',
    'redis', 'models', 'cache', 'views', 'events', 'authorization', 'validation', 'logs',
    'exceptions', 'livewire',
]);

it('masks credentials in profile metadata and collector summaries too', function () {
    $profile = sanitizerProfile(['authorization' => ['items' => []]]);
    $profile['apiKey'] = 'profile-metadata-sentinel';
    $profile['sections']['authorization']['summary']['clientSecret'] = 'collector-summary-sentinel';

    $clean = (new ProfileSanitizer(new Redactor))->clean($profile);

    expect($clean['apiKey'])->toBe('[redacted]')
        ->and($clean['sections']['authorization']['summary']['clientSecret'])->toBe('[redacted]')
        ->and($clean['sections']['authorization']['summary']['count'])->toBe(1)
        ->and(json_encode($clean))->not->toContain('profile-metadata-sentinel', 'collector-summary-sentinel');
});

it('preserves safe session metadata and deep diagnostic data without applying capture limits twice', function () {
    $session = [
        'present' => true,
        'driver' => 'redis',
        'key_count' => 3,
        'keys' => ['_token', 'checkout', 'errors'],
        'flash_keys' => ['errors'],
        'error_bag_present' => true,
        'error_bags' => ['default'],
    ];
    $deep = ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => [
        'message' => str_repeat('diagnostic ', 300),
        'rows' => range(1, 120),
        'token_count' => 12,
        'tokenizer' => 'default',
        'password_policy' => 'strong',
        'session_present' => true,
        'authorization_result' => 'allowed',
        'accessToken' => 'deep-credential-sentinel',
    ]]]]]]];
    $profile = sanitizerProfile([
        'request' => ['session' => $session, 'input' => ['session' => 'request-session-sentinel']],
        'views' => ['items' => [['data' => $deep]]],
        'authorization' => ['items' => [['ability' => 'update', 'allowed' => true]]],
    ]);

    $clean = (new ProfileSanitizer(new Redactor(maxDepth: 1, maxStringLength: 1, maxArrayItems: 1)))->clean($profile);
    $evidence = $clean['sections']['views']['payload']['items'][0]['data']['a']['b']['c']['d']['e']['f'];

    expect($clean['sections']['request']['payload']['session'])->toBe($session)
        ->and($clean['sections']['request']['payload']['input']['session'])->toBe('[redacted]')
        ->and($evidence)->toBe([
            ...$deep['a']['b']['c']['d']['e']['f'],
            'accessToken' => '[redacted]',
        ])
        ->and($clean['sections']['authorization'])->toBe($profile['sections']['authorization'])
        ->and(json_encode($clean))->not->toContain('deep-credential-sentinel', 'request-session-sentinel', 'maximum depth');
});

it('applies both full and section-relative custom paths without replacing built-in masking', function (string $path) {
    $profile = sanitizerProfile(['logs' => ['items' => [['context' => [
        'patient' => ['email' => 'custom-email-sentinel', 'region' => 'FR'],
        'password' => 'built-in-password-sentinel',
    ]]]]]);

    $clean = (new ProfileSanitizer(new Redactor(maskedPaths: [$path])))->clean($profile);

    expect($clean['sections']['logs']['payload']['items'][0]['context'])->toBe([
        'patient' => ['email' => '[redacted]', 'region' => 'FR'],
        'password' => '[redacted]',
    ])->and(json_encode($clean))->not->toContain('custom-email-sentinel', 'built-in-password-sentinel');
})->with([
    'full profile path' => 'sections.logs.payload.items.*.context.patient.email',
    'section path' => 'logs.items.*.context.patient.email',
]);

it('removes request query and route-parameter copies from the retained URL', function (string $prefix) {
    $profile = sanitizerProfile(['request' => [
        'method' => 'GET',
        'path' => '/patients/route-patient-sentinel',
        'url' => 'https://user:password@clinic.test/patients/route-patient-sentinel?email=query-email-sentinel&accessToken=query-token-sentinel&page=2#fragment',
        'query' => ['email' => 'query-email-sentinel', 'accessToken' => 'query-token-sentinel', 'page' => '2'],
        'parameters' => ['patient' => 'route-patient-sentinel'],
    ]]);
    $sanitizer = new ProfileSanitizer(new Redactor(maskedPaths: [$prefix.'.query.email', $prefix.'.parameters.patient']));
    $clean = $sanitizer->clean($profile);
    $request = $clean['sections']['request']['payload'];

    expect($request['query'])->toBe(['email' => '[redacted]', 'accessToken' => '[redacted]', 'page' => '2'])
        ->and($request['parameters']['patient'])->toBe('[redacted]')
        ->and($request['path'])->toBe('[redacted]')
        ->and($request['url'])->toBe('https://clinic.test/[redacted]?email=%5Bredacted%5D&accessToken=%5Bredacted%5D&page=2')
        ->and(json_encode($clean))->not->toContain('route-patient-sentinel', 'query-email-sentinel', 'query-token-sentinel', 'user:password', 'fragment')
        ->and($sanitizer->clean($clean))->toBe($clean);
})->with(['request', 'sections.request.payload']);

it('hides route path copies when the parameter was already redacted during capture', function () {
    $profile = sanitizerProfile(['request' => [
        'url' => 'https://clinic.test/reset/captured-token-sentinel',
        'path' => '/reset/captured-token-sentinel',
        'query' => [],
        'parameters' => ['token' => '[redacted]'],
    ]]);

    $clean = (new ProfileSanitizer(new Redactor))->clean($profile);

    expect($clean['sections']['request']['payload']['url'])->toBe('https://clinic.test/[redacted]')
        ->and($clean['sections']['request']['payload']['path'])->toBe('[redacted]')
        ->and(json_encode($clean))->not->toContain('captured-token-sentinel');
});

it('masks outbound URL query credentials using both supported custom path forms', function (string $path) {
    $profile = sanitizerProfile(['http_client' => ['items' => [[
        'method' => 'GET',
        'url' => 'https://user:password@api.example.test/search?email=http-email-sentinel&apiKey=http-key-sentinel&page=2',
    ]]]]);

    $clean = (new ProfileSanitizer(new Redactor(maskedPaths: [$path])))->clean($profile);

    expect($clean['sections']['http_client']['payload']['items'][0]['url'])
        ->toBe('https://api.example.test/search?email=%5Bredacted%5D&apiKey=%5Bredacted%5D&page=2')
        ->and(json_encode($clean))->not->toContain('http-email-sentinel', 'http-key-sentinel', 'user:password');
})->with(['http_client.items.*.query.email', 'sections.http_client.payload.items.*.query.email']);

it('invalidates runnable SQL when a binding or source SQL is masked', function (string $path, bool $sourcePreserved) {
    $profile = sanitizerProfile(['queries' => ['items' => [[
        'sql' => 'select * from patients where email = ?',
        'bindings' => ['query-binding-sentinel'],
        'runnable_sql' => "select * from patients where email = 'query-binding-sentinel'",
        'runnable_available' => true,
        'bindings_complete' => true,
        'source_preserved' => true,
    ]]]]);

    $clean = (new ProfileSanitizer(new Redactor(maskedPaths: [$path])))->clean($profile);
    $query = $clean['sections']['queries']['payload']['items'][0];

    expect($query)->not->toHaveKey('runnable_sql')
        ->and($query['runnable_available'])->toBeFalse()
        ->and($query['bindings_complete'])->toBeFalse()
        ->and($query['source_preserved'])->toBe($sourcePreserved);

    if ($sourcePreserved) {
        expect(json_encode($clean))->not->toContain('query-binding-sentinel');
    }
})->with([
    'binding' => ['queries.items.*.bindings.0', true],
    'full binding path' => ['sections.queries.payload.items.*.bindings.0', true],
    'source' => ['queries.items.*.sql', false],
]);

it('preserves ordinary exact query bindings and runnable SQL', function () {
    $profile = sanitizerProfile(['queries' => ['items' => [[
        'sql' => 'select * from patients where email = ?',
        'bindings' => ['patient@example.test', 42, true, null],
        'runnable_sql' => "select * from patients where email = 'patient@example.test'",
        'runnable_available' => true,
        'bindings_complete' => true,
        'source_preserved' => true,
        'binding_policy' => 'full',
    ]]]]);

    expect((new ProfileSanitizer(new Redactor))->clean($profile))->toBe($profile);
});

it('removes EML when a retained mail field is masked', function (string $path) {
    $profile = sanitizerProfile(['mail' => ['items' => [[
        'subject' => 'subject-sentinel',
        'preview' => [
            'subject' => 'subject-sentinel',
            'to' => ['recipient-sentinel@example.test'],
            'text' => 'body-sentinel',
            'html' => '<p>html-sentinel</p>',
            'eml' => 'Subject: subject-sentinel\r\nTo: recipient-sentinel@example.test\r\nbody-sentinel html-sentinel',
            'attachments' => [],
            'attachments_omitted' => 0,
        ],
    ]]]]);
    $sanitizer = new ProfileSanitizer(new Redactor(maskedPaths: [$path]));
    $clean = $sanitizer->clean($profile);
    $item = $clean['sections']['mail']['payload']['items'][0];

    expect($item['preview']['eml'])->toBeNull()
        ->and($item['preview']['eml_omitted_reason'])->toBe('redacted_fields')
        ->and($sanitizer->clean($clean))->toBe($clean);

    if (str_ends_with($path, '.subject')) {
        expect($item['subject'])->toBe('[redacted]')
            ->and($item['preview']['subject'])->toBe('[redacted]')
            ->and(json_encode($clean))->not->toContain('subject-sentinel');
    }
})->with([
    'mail.items.*.subject',
    'mail.items.*.preview.subject',
    'mail.items.*.preview.to.*',
    'mail.items.*.preview.text',
    'mail.items.*.preview.html',
    'mail.items.*.preview.eml',
]);

it('makes masked attachment bodies unavailable and removes the duplicate MIME copy', function () {
    $body = base64_encode('attachment-body-sentinel');
    $profile = sanitizerProfile(['mail' => ['items' => [['preview' => [
        'eml' => 'MIME attachment '.$body,
        'attachments' => [[
            'name' => 'report.pdf',
            'content_type' => 'application/pdf',
            'size_bytes' => 24,
            'body_base64' => $body,
        ]],
        'attachments_omitted' => 0,
    ]]]]]);
    $sanitizer = new ProfileSanitizer(new Redactor(maskedPaths: ['mail.items.*.preview.attachments.*.body_base64']));
    $clean = $sanitizer->clean($profile);
    $preview = $clean['sections']['mail']['payload']['items'][0]['preview'];

    expect($preview['attachments'][0])->toBe([
        'name' => 'report.pdf',
        'content_type' => 'application/pdf',
        'size_bytes' => 24,
        'body_base64' => null,
        'body_omitted_reason' => 'redacted',
    ])->and($preview['attachments_omitted'])->toBe(1)
        ->and($preview['eml'])->toBeNull()
        ->and(json_encode($clean))->not->toContain($body, 'attachment-body-sentinel')
        ->and($sanitizer->clean($clean))->toBe($clean);
});

it('preserves untouched mail bodies attachments and EML', function () {
    $profile = sanitizerProfile(['mail' => ['items' => [['preview' => [
        'subject' => 'An ordinary message',
        'to' => ['patient@example.test'],
        'html' => str_repeat('<p>Large but bounded HTML.</p>', 100),
        'text' => 'Local diagnostics remain useful.',
        'eml' => 'Original bounded MIME document',
        'attachments' => [['name' => 'example.txt', 'body_base64' => base64_encode('ordinary file')]],
        'attachments_omitted' => 0,
    ]]]]]);

    expect((new ProfileSanitizer(new Redactor))->clean($profile))->toBe($profile);
});

it('masks named Livewire property values in descriptors and both activity aliases', function () {
    $activity = [[
        'type' => 'update',
        'property' => 'form.apiKey',
        'before' => 'before-secret-sentinel',
        'submitted' => 'submitted-secret-sentinel',
        'server' => 'server-secret-sentinel',
    ]];
    $profile = sanitizerProfile(['livewire' => [
        'components' => [['id' => 'component-one', 'properties' => [[
            'path' => 'form.apiKey',
            'server_value' => 'descriptor-secret-sentinel',
            'writable' => true,
            'array_leaf_writable' => true,
            'write_allowed' => true,
            'write_reason' => null,
        ]]]],
        'items' => $activity,
        'activity' => $activity,
    ]]);

    $clean = (new ProfileSanitizer(new Redactor))->clean($profile);
    $payload = $clean['sections']['livewire']['payload'];

    expect($payload['components'][0]['properties'][0])->toBe([
        'path' => 'form.apiKey',
        'server_value' => '[redacted]',
        'writable' => false,
        'array_leaf_writable' => false,
        'write_allowed' => false,
        'write_reason' => 'redacted',
    ])->and($payload['items'])->toBe($payload['activity'])
        ->and($payload['items'][0])->toBe([
            'type' => 'update',
            'property' => 'form.apiKey',
            'before' => '[redacted]',
            'submitted' => '[redacted]',
            'server' => '[redacted]',
        ])->and(json_encode($clean))->not->toContain('secret-sentinel');
});

it('applies custom Livewire activity masks to both aliases', function (string $path) {
    $activity = [['type' => 'update', 'property' => 'search', 'submitted' => 'activity-custom-sentinel', 'server' => 'retained']];
    $profile = sanitizerProfile(['livewire' => ['items' => $activity, 'activity' => $activity]]);

    $clean = (new ProfileSanitizer(new Redactor(maskedPaths: [$path])))->clean($profile);
    $payload = $clean['sections']['livewire']['payload'];

    expect($payload['items'])->toBe($payload['activity'])
        ->and($payload['activity'][0]['submitted'])->toBe('[redacted]')
        ->and($payload['activity'][0]['server'])->toBe('retained')
        ->and(json_encode($clean))->not->toContain('activity-custom-sentinel');
})->with([
    'livewire.items.*.submitted', 'livewire.activity.*.submitted',
    'sections.livewire.payload.items.*.submitted', 'sections.livewire.payload.activity.*.submitted',
]);

it('uses semantic Livewire property paths for custom fields and disables masked writes', function () {
    $profile = sanitizerProfile(['livewire' => ['components' => [['properties' => [[
        'path' => 'patient.email',
        'server_value' => 'custom-property-sentinel',
        'write_allowed' => true,
        'writable' => true,
        'array_leaf_writable' => false,
    ]]]]]]);

    $clean = (new ProfileSanitizer(new Redactor(maskedPaths: ['livewire.properties.patient.email'])))->clean($profile);
    $property = $clean['sections']['livewire']['payload']['components'][0]['properties'][0];

    expect($property['server_value'])->toBe('[redacted]')
        ->and($property['write_allowed'])->toBeFalse()
        ->and($property['writable'])->toBeFalse()
        ->and($property['write_reason'])->toBe('redacted')
        ->and(json_encode($clean))->not->toContain('custom-property-sentinel');
});

it('preserves normal Livewire property values and editing permissions', function () {
    $activity = [['property' => 'search', 'before' => 'old', 'submitted' => 'new', 'server' => 'new']];
    $profile = sanitizerProfile(['livewire' => [
        'components' => [['properties' => [[
            'path' => 'search',
            'server_value' => 'new',
            'writable' => true,
            'array_leaf_writable' => false,
            'write_allowed' => true,
            'write_reason' => null,
        ]]]],
        'items' => $activity,
        'activity' => $activity,
    ]]);

    expect((new ProfileSanitizer(new Redactor))->clean($profile))->toBe($profile);
});

it('masks authorization values based on their captured field names', function () {
    $profile = sanitizerProfile(['authorization' => ['items' => [[
        'ability' => 'update',
        'allowed' => false,
        'user' => ['identifier_name' => 'api_token', 'identifier' => 'user-identifier-sentinel', 'name' => 'Example user'],
        'arguments' => [[
            'kind' => 'model',
            'identifier' => 42,
            'route_key_name' => 'privateKey',
            'route_key' => 'route-key-sentinel',
        ]],
    ]]]]);

    $clean = (new ProfileSanitizer(new Redactor))->clean($profile);
    $item = $clean['sections']['authorization']['payload']['items'][0];

    expect($item['user']['identifier'])->toBe('[redacted]')
        ->and($item['user']['identifier_name'])->toBe('api_token')
        ->and($item['arguments'][0]['route_key'])->toBe('[redacted]')
        ->and($item['arguments'][0]['identifier'])->toBe(42)
        ->and($item['allowed'])->toBeFalse()
        ->and(json_encode($clean))->not->toContain('user-identifier-sentinel', 'route-key-sentinel');
});

it('preserves normal authorization identifiers and route keys', function () {
    $profile = sanitizerProfile(['authorization' => ['items' => [[
        'ability' => 'update',
        'allowed' => true,
        'user' => ['identifier_name' => 'id', 'identifier' => 7, 'name' => 'Example user'],
        'arguments' => [['identifier' => 42, 'route_key_name' => 'slug', 'route_key' => 'example-project']],
    ]]]]);

    expect((new ProfileSanitizer(new Redactor))->clean($profile))->toBe($profile);
});

it('honors a custom rule masking the whole diagnostic session object', function () {
    $profile = sanitizerProfile(['request' => ['session' => [
        'present' => true,
        'driver' => 'redis',
        'keys' => ['session-shape-sentinel'],
    ]]]);

    $clean = (new ProfileSanitizer(new Redactor(maskedPaths: ['request.session'])))->clean($profile);

    expect($clean['sections']['request']['payload']['session'])->toBe('[redacted]')
        ->and(json_encode($clean))->not->toContain('session-shape-sentinel');
});

it('does not restore a whole Livewire activity collection masked through either alias', function (string $path) {
    $activity = [['property' => 'search', 'submitted' => 'whole-activity-sentinel']];
    $profile = sanitizerProfile(['livewire' => ['items' => $activity, 'activity' => $activity]]);

    $clean = (new ProfileSanitizer(new Redactor(maskedPaths: [$path])))->clean($profile);

    expect(json_encode($clean))->not->toContain('whole-activity-sentinel')
        ->and($clean['sections']['livewire']['payload']['items'])
        ->toBe($clean['sections']['livewire']['payload']['activity']);
})->with(['livewire.items', 'livewire.activity']);

it('keeps a whole masked attachment collection unavailable without failing sanitization', function () {
    $body = base64_encode('whole-attachment-sentinel');
    $profile = sanitizerProfile(['mail' => ['items' => [['preview' => [
        'eml' => 'MIME document '.$body,
        'attachments' => [['name' => 'report.txt', 'body_base64' => $body]],
        'attachments_omitted' => 0,
    ]]]]]);

    $clean = (new ProfileSanitizer(new Redactor(maskedPaths: ['mail.items.*.preview.attachments'])))->clean($profile);

    expect(json_encode($clean))->not->toContain($body, 'whole-attachment-sentinel')
        ->and($clean['sections']['mail']['payload']['items'][0]['preview']['eml'])->toBeNull();
});

it('masks nested Livewire property updates when an ancestor is a credential field', function () {
    $activity = [['property' => 'form.apiToken.value', 'before' => 'nested-before-sentinel', 'submitted' => 'nested-submit-sentinel', 'server' => 'nested-server-sentinel']];
    $profile = sanitizerProfile(['livewire' => ['items' => $activity, 'activity' => $activity]]);

    $clean = (new ProfileSanitizer(new Redactor))->clean($profile);

    expect($clean['sections']['livewire']['payload']['items'][0])->toBe([
        'property' => 'form.apiToken.value',
        'before' => '[redacted]',
        'submitted' => '[redacted]',
        'server' => '[redacted]',
    ])->and(json_encode($clean))->not->toContain('nested-before-sentinel', 'nested-submit-sentinel', 'nested-server-sentinel');
});

it('removes runnable SQL when bindings already contain a capture-time mask', function () {
    $profile = sanitizerProfile(['queries' => ['items' => [[
        'sql' => 'select * from users where token = ?',
        'bindings' => ['[redacted]'],
        'runnable_sql' => "select * from users where token = 'already-masked-binding-sentinel'",
        'runnable_available' => true,
        'bindings_complete' => true,
        'source_preserved' => true,
    ]]]]);

    $clean = (new ProfileSanitizer(new Redactor))->clean($profile);
    $query = $clean['sections']['queries']['payload']['items'][0];

    expect($query)->not->toHaveKey('runnable_sql')
        ->and($query['runnable_available'])->toBeFalse()
        ->and($query['bindings_complete'])->toBeFalse()
        ->and(json_encode($clean))->not->toContain('already-masked-binding-sentinel');
});

it('masks the request URL path copy when its explicit path field is selected', function () {
    $profile = sanitizerProfile(['request' => [
        'url' => 'https://clinic.test/patients/private-path-sentinel?page=2',
        'path' => '/patients/private-path-sentinel',
        'query' => ['page' => '2'],
        'parameters' => [],
    ]]);

    $clean = (new ProfileSanitizer(new Redactor(maskedPaths: ['request.path'])))->clean($profile);

    expect($clean['sections']['request']['payload']['path'])->toBe('[redacted]')
        ->and($clean['sections']['request']['payload']['url'])->toBe('https://clinic.test/[redacted]?page=2')
        ->and(json_encode($clean))->not->toContain('private-path-sentinel');
});
