<?php

namespace NewDebugBar\Http\Controllers;

use Illuminate\Http\Response;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Support\ProfileAccess;
use Symfony\Component\HttpFoundation\HeaderUtils;

/** Checks current HTTP access before serving retained mail previews and attachments. */
final class MailPreviewController
{
    public function __invoke(string $profile, int $index, string $format, ProfileStore $store): Response
    {
        $preview = $this->preview($profile, $index, $store);

        $content = $preview[$format] ?? null;

        if (! is_string($content)) {
            abort(404);
        }

        $headers = $this->privateHeaders();

        if (in_array($format, ['html', 'text'], true)) {
            $nonce = bin2hex(random_bytes(16));
            $document = $format === 'html'
                ? $content
                : $this->textDocument($content);

            return response($this->withHeightReporter($document, $nonce), 200, [
                ...$headers,
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Security-Policy' => "sandbox allow-scripts; default-src 'none'; img-src data:; style-src 'unsafe-inline'; script-src 'nonce-{$nonce}'; script-src-attr 'none'; form-action 'none'; base-uri 'none'; frame-ancestors 'self'",
            ]);
        }

        return response($content, 200, [
            ...$headers,
            'Content-Type' => 'message/rfc822',
            'Content-Disposition' => 'attachment; filename="message-'.($index + 1).'.eml"',
        ]);
    }

    public function attachment(
        string $profile,
        int $index,
        int $attachment,
        ProfileStore $store,
    ): Response {
        $preview = $this->preview($profile, $index, $store);
        $retained = $preview['attachments'][$attachment] ?? null;

        if (! is_array($retained) || ! is_string($retained['body_base64'] ?? null)) {
            abort(404);
        }

        $content = base64_decode($retained['body_base64'], true);

        if (! is_string($content)) {
            abort(404);
        }

        $filename = $this->attachmentFilename($retained['name'] ?? null, $attachment);
        $contentType = $this->attachmentContentType($retained['content_type'] ?? null);

        return response($content, 200, [
            ...$this->privateHeaders(),
            'Content-Type' => $contentType,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $filename,
                $this->attachmentFallbackFilename($filename, $attachment),
            ),
        ]);
    }

    /** @return array<string, mixed> */
    private function preview(string $profile, int $index, ProfileStore $store): array
    {
        request()->attributes->set('newdebugbar.profile-data', true);
        app(ProfileAccess::class)->authorize(request());

        $stored = $store->get($profile);
        $preview = $stored['sections']['mail']['payload']['items'][$index]['preview'] ?? null;

        if (! is_array($preview)) {
            abort(404);
        }

        return $preview;
    }

    /** @return array<string, string> */
    private function privateHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'no-referrer',
        ];
    }

    private function attachmentFilename(mixed $name, int $index): string
    {
        $filename = is_string($name) ? basename(str_replace('\\', '/', trim($name))) : '';
        $filename = preg_replace('/[\x00-\x1F\x7F%]/u', '', $filename) ?? '';

        return $filename === '' ? 'attachment-'.($index + 1) : $filename;
    }

    private function attachmentContentType(mixed $contentType): string
    {
        if (is_string($contentType)
            && preg_match('/\A[a-z0-9][a-z0-9.+-]*\/[a-z0-9][a-z0-9.+-]*\z/i', $contentType) === 1) {
            return $contentType;
        }

        return 'application/octet-stream';
    }

    private function attachmentFallbackFilename(string $filename, int $index): string
    {
        if (preg_match('/\A[\x20-\x7E]+\z/', $filename) === 1) {
            return $filename;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $extension = preg_match('/\A[a-z0-9]+\z/i', $extension) === 1 ? '.'.$extension : '';

        return 'attachment-'.($index + 1).$extension;
    }

    private function withHeightReporter(string $content, string $nonce): string
    {
        $reporter = <<<'HTML'
<script nonce="__NONCE__">
(() => {
    let scheduled = false;
    const report = () => {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => {
            scheduled = false;
            const height = Math.max(
                320,
                document.body?.scrollHeight ?? 0,
                document.documentElement.scrollHeight,
            );
            window.parent.postMessage({
                type: 'newdebugbar:mail-preview-height',
                height,
            }, '*');
        });
    };

    window.addEventListener('message', (event) => {
        if (event.data?.type === 'newdebugbar:measure-mail-preview') report();
    });
    window.addEventListener('wheel', (event) => {
        window.parent.postMessage({
            type: 'newdebugbar:mail-preview-scroll',
            deltaY: event.deltaY,
            deltaMode: event.deltaMode,
        }, '*');
    }, { passive: true });
    window.addEventListener('keydown', (event) => {
        const deltaY = {
            ArrowDown: 48,
            ArrowUp: -48,
            PageDown: window.innerHeight * 0.8,
            PageUp: window.innerHeight * -0.8,
            Home: -100000,
            End: 100000,
        }[event.key];
        if (deltaY === undefined || event.altKey || event.ctrlKey || event.metaKey) return;
        event.preventDefault();
        window.parent.postMessage({
            type: 'newdebugbar:mail-preview-scroll',
            deltaY,
            deltaMode: 0,
        }, '*');
    });
    let lastTouchY = null;
    window.addEventListener('touchstart', (event) => {
        lastTouchY = event.touches[0]?.clientY ?? null;
    }, { passive: true });
    window.addEventListener('touchmove', (event) => {
        const currentY = event.touches[0]?.clientY ?? null;
        if (lastTouchY === null || currentY === null) return;
        window.parent.postMessage({
            type: 'newdebugbar:mail-preview-scroll',
            deltaY: lastTouchY - currentY,
            deltaMode: 0,
        }, '*');
        lastTouchY = currentY;
    }, { passive: true });
    window.addEventListener('touchend', () => {
        lastTouchY = null;
    }, { passive: true });
    window.addEventListener('load', report);
    if (typeof ResizeObserver === 'function') {
        new ResizeObserver(report).observe(document.body);
    }
    report();
})();
</script>
HTML;
        $reporter = str_replace('__NONCE__', $nonce, $reporter);
        $closingBody = strripos($content, '</body>');

        if ($closingBody === false) {
            return $content.$reporter;
        }

        return substr($content, 0, $closingBody).$reporter.substr($content, $closingBody);
    }

    private function textDocument(string $content): string
    {
        $escaped = htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Text mail preview</title>
</head>
<body style="margin: 0; background: #ffffff; color: #27272a;">
    <pre style="box-sizing: border-box; min-height: 100vh; margin: 0; padding: 24px; white-space: pre-wrap; overflow-wrap: anywhere; font: 14px/1.65 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;">{$escaped}</pre>
</body>
</html>
HTML;
    }
}
