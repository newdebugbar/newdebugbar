<?php

use Livewire\Livewire;
use NewDebugBar\Livewire\DebugBar;
use NewDebugBar\Storage\ProfileStore;

it('stores and serves bounded local previews with downloadable attachments', function () {
    $response = $this->get('/profiled-messages', ['Accept' => 'text/html'])->assertOk();
    $profileId = $response->headers->get('X-NewDebugBar-Profile');
    $profile = app(ProfileStore::class)->get($profileId);
    $preview = $profile['inspectors']['mail']['payload']['items'][0]['preview'];

    expect($preview)
        ->subject->toBe('private subject')
        ->from->toBe(['private-sender@example.test'])
        ->to->toBe(['private-recipient@example.test'])
        ->cc->toBe(['private-copy@example.test'])
        ->text->toBe('private body')
        ->attachments_omitted->toBe(0)
        ->attachments->toBe([[
            'name' => 'private.txt',
            'content_type' => 'application/octet-stream',
            'disposition' => 'attachment',
            'content_id' => null,
            'size_bytes' => 18,
            'body_base64' => base64_encode('private attachment'),
        ]])
        ->and($preview['eml'])->toContain('private.txt', base64_encode('private attachment'));

    $profile['inspectors']['mail']['payload']['items'][0]['preview']['html'] = '<script>window.top.location="https://example.test"</script><h1>Safe preview</h1>';
    app(ProfileStore::class)->put($profile);

    $htmlResponse = $this->get(route('newdebugbar.mail-preview', [
        'profile' => $profileId,
        'index' => 0,
        'format' => 'html',
    ]));
    $htmlResponse
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertSee('Safe preview')
        ->assertSee('newdebugbar:mail-preview-height', false);
    expect($htmlResponse->headers->get('Content-Security-Policy'))
        ->toStartWith("sandbox allow-scripts; default-src 'none'; img-src data:; style-src 'unsafe-inline'; script-src 'nonce-")
        ->toContain("'; script-src-attr 'none'; form-action 'none'; base-uri 'none'; frame-ancestors 'self'");

    $textResponse = $this->get(route('newdebugbar.mail-preview', [
        'profile' => $profileId,
        'index' => 0,
        'format' => 'text',
    ]));
    $textResponse
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSeeText('private body')
        ->assertSee('newdebugbar:mail-preview-scroll', false);
    expect($textResponse->headers->get('Cache-Control'))->toContain('no-store', 'private');

    $attachmentResponse = $this->get(route('newdebugbar.mail-attachment', [
        'profile' => $profileId,
        'index' => 0,
        'attachment' => 0,
    ]));
    $attachmentResponse
        ->assertOk()
        ->assertHeader('Content-Type', 'application/octet-stream')
        ->assertContent('private attachment');
    expect($attachmentResponse->headers->get('Cache-Control'))->toContain('no-store', 'private');
    expect($attachmentResponse->headers->get('Content-Disposition'))
        ->toContain('attachment', 'filename=private.txt');

    $emlResponse = $this->get(route('newdebugbar.mail-preview', [
        'profile' => $profileId,
        'index' => 0,
        'format' => 'eml',
    ]));
    $emlResponse
        ->assertOk()
        ->assertHeader('Content-Type', 'message/rfc822')
        ->assertHeader('Content-Disposition', 'attachment; filename="message-1.eml"');
    expect($emlResponse->getContent())->toContain('private.txt', base64_encode('private attachment'));

    Livewire::test(DebugBar::class, ['profileId' => $profileId])
        ->call('loadInspector', 'mail')
        ->assertSee('Download .EML')
        ->assertSee('Download')
        ->assertSee('Open preview');

    $this->get(route('newdebugbar.mail-attachment', [
        'profile' => $profileId,
        'index' => 0,
        'attachment' => 1,
    ]))->assertNotFound();
});

it('rejects profile identifiers that storage cannot read', function () {
    $this->get('/__newdebugbar/mail/550e8400-e29b-11d4-a716-446655440000/0/text')
        ->assertNotFound();
});
