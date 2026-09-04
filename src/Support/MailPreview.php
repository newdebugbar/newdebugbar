<?php

namespace NewDebugBar\Support;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Throwable;

/** Builds bounded local mail previews with optional raw MIME and attachment body capture. */
final class MailPreview
{
    public function __construct(
        private readonly int $maxBodyBytes,
        private readonly int $maxRecipients,
        private readonly int $maxAttachmentBytes = 2_000_000,
        private readonly bool $captureEml = true,
        private readonly bool $captureAttachmentBodies = true,
    ) {}

    /** @return array<string, mixed>|null */
    public function capture(mixed $message): ?array
    {
        if (! $message instanceof Email) {
            return null;
        }

        [$subject, $subjectTruncated] = $this->bounded($message->getSubject(), min(2_000, $this->maxBodyBytes));
        [$html, $htmlTruncated] = $this->bounded($message->getHtmlBody());
        [$text, $textTruncated] = $this->bounded($message->getTextBody());
        $addressesOmitted = $this->addressesOmitted($message);
        [$attachments, $attachmentsOmitted] = $this->attachments($message->getAttachments());

        return [
            'subject' => $subject,
            'from' => $this->addresses($message->getFrom()),
            'to' => $this->addresses($message->getTo()),
            'cc' => $this->addresses($message->getCc()),
            'bcc' => $this->addresses($message->getBcc()),
            'reply_to' => $this->addresses($message->getReplyTo()),
            'sender' => $message->getSender()?->toString(),
            'return_path' => $message->getReturnPath()?->toString(),
            'date' => $message->getDate()?->format(DATE_ATOM),
            'priority' => $message->getPriority(),
            'attachments' => $attachments,
            'html' => $html,
            'text' => $text,
            // The inputs are bounded before serialization so the MIME document
            // stays valid instead of being cut through a header or body part.
            'eml' => $this->captureEml
                ? $this->boundedCopy($message, $subject, $html, $text, $attachments, $attachmentsOmitted)->toString()
                : null,
            'eml_omitted_reason' => $this->captureEml ? null : 'capture_disabled',
            'truncated' => $subjectTruncated || $htmlTruncated || $textTruncated || $addressesOmitted > 0,
            'attachments_omitted' => $attachmentsOmitted,
            'attachment_metadata_omitted' => max(0, count($message->getAttachments()) - count($attachments)),
            'addresses_omitted' => $addressesOmitted,
        ];
    }

    /**
     * @param  list<DataPart>  $attachments
     * @return array{0: list<array{name: string, content_type: string, disposition: string, content_id: ?string, size_bytes: ?int, body_base64: ?string, body_omitted_reason: ?string}>, 1: int}
     */
    private function attachments(array $attachments): array
    {
        $remainingBytes = max(0, $this->maxAttachmentBytes);
        $capturedBodies = 0;
        $capturedAttachments = [];

        foreach (array_slice($attachments, 0, $this->maxRecipients) as $attachment) {
            $body = null;
            $sizeBytes = null;
            $omittedReason = 'capture_disabled';

            if ($this->captureAttachmentBodies) {
                try {
                    $candidate = $attachment->getBody();
                    $sizeBytes = strlen($candidate);
                    $omittedReason = 'attachment_budget';

                    if ($sizeBytes <= $remainingBytes) {
                        $body = $candidate;
                        $remainingBytes -= $sizeBytes;
                        $capturedBodies++;
                        $omittedReason = null;
                    }
                } catch (Throwable) {
                    // Keep useful metadata when Symfony cannot read the attachment body.
                    $omittedReason = 'unreadable';
                }
            }

            $capturedAttachments[] = [
                'name' => $attachment->getFilename() ?? $attachment->getName() ?? 'Attachment',
                'content_type' => $attachment->getContentType(),
                'disposition' => $attachment->getDisposition() ?? 'attachment',
                'content_id' => $attachment->hasContentId() ? $attachment->getContentId() : null,
                'size_bytes' => $sizeBytes,
                'body_base64' => $body === null ? null : base64_encode($body),
                'body_omitted_reason' => $omittedReason,
            ];
        }

        return [$capturedAttachments, count($attachments) - $capturedBodies];
    }

    /**
     * @param  list<array{name: string, content_type: string, disposition: string, content_id: ?string, size_bytes: ?int, body_base64: ?string, body_omitted_reason: ?string}>  $attachments
     */
    private function boundedCopy(
        Email $message,
        ?string $subject,
        ?string $html,
        ?string $text,
        array $attachments,
        int $attachmentsOmitted,
    ): Email {
        $copy = (new Email)->subject($subject ?? '');

        foreach (array_slice($message->getFrom(), 0, $this->maxRecipients) as $address) {
            $copy->addFrom($address);
        }

        foreach (array_slice($message->getTo(), 0, $this->maxRecipients) as $address) {
            $copy->addTo($address);
        }

        foreach (array_slice($message->getCc(), 0, $this->maxRecipients) as $address) {
            $copy->addCc($address);
        }

        foreach (array_slice($message->getBcc(), 0, $this->maxRecipients) as $address) {
            $copy->addBcc($address);
        }

        foreach (array_slice($message->getReplyTo(), 0, $this->maxRecipients) as $address) {
            $copy->addReplyTo($address);
        }

        if ($text !== null) {
            $copy->text($text);
        }

        if ($html !== null) {
            $copy->html($html);
        }

        foreach ($attachments as $attachment) {
            if (! is_string($attachment['body_base64'])) {
                continue;
            }

            $body = base64_decode($attachment['body_base64'], true);

            if (! is_string($body)) {
                continue;
            }

            $part = new DataPart($body, $attachment['name'], $attachment['content_type']);

            if ($attachment['disposition'] === 'inline') {
                $part->asInline();
            }

            if (is_string($attachment['content_id'])) {
                $part->setContentId($attachment['content_id']);
            }

            $copy->addPart($part);
        }

        if ($attachmentsOmitted > 0) {
            $copy->getHeaders()->addTextHeader(
                'X-NewDebugBar-Attachments-Omitted',
                (string) $attachmentsOmitted,
            );
        }

        if ($text === null && $html === null && $copy->getAttachments() === []) {
            $copy->text('');
        }

        return $copy;
    }

    /** @param list<Address> $addresses @return list<string> */
    private function addresses(array $addresses): array
    {
        return array_map(
            static fn (Address $address): string => $address->toString(),
            array_slice($addresses, 0, $this->maxRecipients),
        );
    }

    /** @return array{0: ?string, 1: bool} */
    private function bounded(?string $value, ?int $limit = null): array
    {
        if ($value === null) {
            return [null, false];
        }

        $limit ??= $this->maxBodyBytes;

        if (strlen($value) <= $limit) {
            return [$value, false];
        }

        return [mb_strcut($value, 0, $limit, 'UTF-8')."\n[preview truncated]", true];
    }

    private function addressesOmitted(Email $message): int
    {
        return array_sum(array_map(
            fn (array $addresses): int => max(0, count($addresses) - $this->maxRecipients),
            [
                $message->getFrom(),
                $message->getTo(),
                $message->getCc(),
                $message->getBcc(),
                $message->getReplyTo(),
            ],
        ));
    }
}
