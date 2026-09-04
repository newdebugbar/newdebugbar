<?php

use NewDebugBar\Support\MailPreview;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

it('builds bounded html text eml and attachment previews', function () {
    $message = (new Email)
        ->from('sender@example.test')
        ->to('recipient@example.test')
        ->subject('Preview subject')
        ->text('Plain preview')
        ->html('<h1>HTML preview</h1>')
        ->attach('private attachment', 'private.txt', 'text/plain');

    $preview = (new MailPreview(maxBodyBytes: 1_000, maxRecipients: 10))->capture($message);

    expect($preview)
        ->subject->toBe('Preview subject')
        ->to->toBe(['recipient@example.test'])
        ->text->toBe('Plain preview')
        ->html->toBe('<h1>HTML preview</h1>')
        ->attachments_omitted->toBe(0)
        ->attachment_metadata_omitted->toBe(0)
        ->attachments->toBe([[
            'name' => 'private.txt',
            'content_type' => 'text/plain',
            'disposition' => 'attachment',
            'content_id' => null,
            'size_bytes' => 18,
            'body_base64' => base64_encode('private attachment'),
            'body_omitted_reason' => null,
        ]])
        ->and($preview['eml'])
        ->toContain('Plain preview', 'HTML preview', 'private.txt', base64_encode('private attachment'))
        ->not->toContain('X-NewDebugBar-Attachments-Omitted');

    expect($preview['eml'])->toEndWith("\r\n");
});

it('keeps attachment metadata when the message exceeds its attachment budget', function () {
    $message = (new Email)
        ->from('sender@example.test')
        ->to('recipient@example.test')
        ->subject('Budgeted attachments')
        ->attach('first body', 'first.txt', 'text/plain')
        ->attach('second body', 'second.txt', 'text/plain');

    $preview = (new MailPreview(
        maxBodyBytes: 1_000,
        maxRecipients: 10,
        maxAttachmentBytes: strlen('first body'),
    ))->capture($message);

    expect($preview)
        ->attachments_omitted->toBe(1)
        ->attachment_metadata_omitted->toBe(0)
        ->attachments->toHaveCount(2)
        ->and($preview['attachments'][0])
        ->size_bytes->toBe(strlen('first body'))
        ->body_base64->toBe(base64_encode('first body'))
        ->and($preview['attachments'][1])
        ->size_bytes->toBe(strlen('second body'))
        ->body_base64->toBeNull()
        ->body_omitted_reason->toBe('attachment_budget')
        ->and($preview['eml'])
        ->toContain('first.txt', base64_encode('first body'), 'X-NewDebugBar-Attachments-Omitted: 1')
        ->not->toContain('second.txt', base64_encode('second body'));
});

it('bounds the inputs without cutting the serialized mime message', function () {
    $message = (new Email)
        ->from('sender@example.test')
        ->to('first@example.test', 'second@example.test')
        ->subject(str_repeat('s', 200))
        ->text(str_repeat('t', 200))
        ->html(str_repeat('h', 200));

    $preview = (new MailPreview(maxBodyBytes: 64, maxRecipients: 1))->capture($message);

    expect($preview)
        ->truncated->toBeTrue()
        ->to->toBe(['first@example.test'])
        ->addresses_omitted->toBe(1)
        ->attachment_metadata_omitted->toBe(0)
        ->and(strlen($preview['subject']))->toBeLessThanOrEqual(85)
        ->and($preview['text'])->toContain("\n[preview truncated]")
        ->not->toContain('\\n[preview truncated]')
        ->and($preview['eml'])->toEndWith("\r\n")
        ->not->toEndWith('[preview truncated]');
});

it('ignores unsupported mail message types', function () {
    expect((new MailPreview(maxBodyBytes: 1_000, maxRecipients: 10))->capture(new stdClass))->toBeNull();
});

it('independently controls raw mime and attachment body capture', function (bool $captureEml, bool $captureAttachmentBodies) {
    $message = (new Email)
        ->from('sender@example.test')
        ->to('recipient@example.test')
        ->subject('Preview subject')
        ->text('Plain preview')
        ->html('<h1>HTML preview</h1>')
        ->attach('private attachment', 'private.txt', 'text/plain');

    $preview = (new MailPreview(
        maxBodyBytes: 1_000,
        maxRecipients: 10,
        captureEml: $captureEml,
        captureAttachmentBodies: $captureAttachmentBodies,
    ))->capture($message);

    expect($preview)
        ->subject->toBe('Preview subject')
        ->from->toBe(['sender@example.test'])
        ->to->toBe(['recipient@example.test'])
        ->text->toBe('Plain preview')
        ->html->toBe('<h1>HTML preview</h1>')
        ->truncated->toBeFalse()
        ->eml_omitted_reason->toBe($captureEml ? null : 'capture_disabled')
        ->attachments_omitted->toBe($captureAttachmentBodies ? 0 : 1)
        ->attachment_metadata_omitted->toBe(0)
        ->and($preview['attachments'][0])
        ->name->toBe('private.txt')
        ->content_type->toBe('text/plain')
        ->body_base64->toBe($captureAttachmentBodies ? base64_encode('private attachment') : null)
        ->size_bytes->toBe($captureAttachmentBodies ? strlen('private attachment') : null)
        ->body_omitted_reason->toBe($captureAttachmentBodies ? null : 'capture_disabled');

    if (! $captureAttachmentBodies) {
        expect(json_encode($preview, JSON_THROW_ON_ERROR))
            ->not->toContain('private attachment', base64_encode('private attachment'));
    }

    if (! $captureEml) {
        expect($preview['eml'])->toBeNull();

        return;
    }

    expect($preview['eml'])
        ->toContain('Plain preview', 'HTML preview')
        ->toEndWith("\r\n");

    if (! $captureAttachmentBodies) {
        expect($preview['eml'])
            ->toContain('X-NewDebugBar-Attachments-Omitted: 1')
            ->not->toContain('Content-Disposition: attachment', 'private.txt');

        return;
    }

    preg_match('/Content-Type: multipart\/mixed; boundary="?([^"\r\n]+)"?/', $preview['eml'], $matches);
    $attachmentParts = array_values(array_filter(
        explode('--'.$matches[1], $preview['eml']),
        static fn (string $part): bool => str_contains($part, 'Content-Disposition: attachment'),
    ));
    expect($attachmentParts)->toHaveCount(1);

    [, $encodedBody] = explode("\r\n\r\n", $attachmentParts[0], 2);
    expect(base64_decode(trim($encodedBody), true))->toBe('private attachment');
})->with([
    'all content retained' => [true, true],
    'raw mime omitted' => [false, true],
    'attachment bodies omitted everywhere' => [true, false],
    'both opaque representations omitted' => [false, false],
]);

it('does not read attachment bodies when their capture is disabled', function () {
    $attachment = new class('private attachment', 'private.txt', 'text/plain') extends DataPart
    {
        public int $bodyReads = 0;

        public function getBody(): string
        {
            $this->bodyReads++;

            return parent::getBody();
        }
    };
    $message = (new Email)
        ->from('sender@example.test')
        ->to('recipient@example.test')
        ->addPart($attachment);

    $preview = (new MailPreview(
        maxBodyBytes: 1_000,
        maxRecipients: 10,
        captureAttachmentBodies: false,
    ))->capture($message);

    expect($attachment->bodyReads)->toBe(0)
        ->and($preview['attachments'][0])
        ->size_bytes->toBeNull()
        ->body_base64->toBeNull()
        ->body_omitted_reason->toBe('capture_disabled')
        ->and($preview['eml'])
        ->toContain('Content-Type: text/plain', 'X-NewDebugBar-Attachments-Omitted: 1')
        ->not->toContain(base64_encode('private attachment'))
        ->toEndWith("\r\n");
});

it('skips mime construction when raw mime capture is disabled', function () {
    // Metadata is useful even before an email has the body and sender required by MIME serialization.
    $preview = (new MailPreview(
        maxBodyBytes: 1_000,
        maxRecipients: 10,
        captureEml: false,
    ))->capture((new Email)->subject('Unfinished message'));

    expect($preview)
        ->subject->toBe('Unfinished message')
        ->eml->toBeNull()
        ->eml_omitted_reason->toBe('capture_disabled');
});

it('distinguishes unreadable attachment bodies from deliberate capture omissions', function () {
    $attachment = new class('private attachment', 'private.txt', 'text/plain') extends DataPart
    {
        public function getBody(): string
        {
            throw new RuntimeException('Attachment is not readable');
        }
    };
    $message = (new Email)
        ->from('sender@example.test')
        ->to('recipient@example.test')
        ->text('Plain preview')
        ->addPart($attachment);

    $preview = (new MailPreview(maxBodyBytes: 1_000, maxRecipients: 10))->capture($message);

    expect($preview)
        ->attachments_omitted->toBe(1)
        ->and($preview['attachments'][0])
        ->name->toBe('private.txt')
        ->body_base64->toBeNull()
        ->body_omitted_reason->toBe('unreadable');
});
