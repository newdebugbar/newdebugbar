<?php

namespace NewDebugBar\Support;

use Illuminate\Http\Response as LaravelResponse;
use Livewire\LivewireManager;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Adds the Livewire toolbar and package assets to supported HTML responses. */
final class BarInjector
{
    /**
     * A host page may start its own Alpine instance, and whichever instance
     * initializes the toolbar first owns it. A host instance lacks Livewire's
     * `$wire` support, so this flag makes every Alpine instance skip the toolbar
     * until the bundle releases it to Livewire's own Alpine on `livewire:init`
     * (see resources/js/alpine-handoff.js).
     */
    private const ALPINE_HANDOFF_GUARD = '<script data-navigate-once="true">'
        ."(() => { const el = document.getElementById('newdebugbar'); if (el) el._x_ignore = true; })()"
        .'</script>';

    public function __construct(
        private readonly LivewireManager $livewire,
        private readonly AssetUrl $assets,
    ) {}

    public function inject(Response $response, string $profileId): Response
    {
        if (! $this->supports($response)) {
            return $response;
        }

        $html = (string) $response->getContent();

        $stylesheet = e($this->assets->for('newdebugbar.css'));
        $script = e($this->assets->for('newdebugbar.js'));
        $component = $this->livewire->mount('newdebugbar.toolbar', ['profileId' => $profileId], 'newdebugbar-toolbar');
        $livewireStyles = FrontendAssets::styles();
        $livewireScripts = FrontendAssets::scripts();

        $head = $livewireStyles
            .'<style id="newdebugbar-critical-css" data-navigate-once="true">#newdebugbar [x-cloak]{display:none!important}</style>'
            .'<link rel="stylesheet" href="'.$stylesheet.'" data-navigate-once="true">';
        $body = '<script src="'.$script.'" data-navigate-once="true"></script>'
            .$component
            .self::ALPINE_HANDOFF_GUARD
            .$livewireScripts;
        if (preg_match('/<\/head\s*>/i', $html) === 1) {
            $html = preg_replace('/<\/head\s*>/i', $head.'$0', $html, 1) ?? $html;
        } elseif (preg_match('/<html(?:\s[^>]*)?>/i', $html) === 1) {
            $html = preg_replace('/<html(?:\s[^>]*)?>/i', '$0<head>'.$head.'</head>', $html, 1) ?? $html;
        }

        $html = preg_replace('/<\/body\s*>/i', $body.'$0', $html, 1) ?? $html;

        $original = $response instanceof LaravelResponse ? $response->getOriginalContent() : null;
        $response->setContent($html);

        if ($response instanceof LaravelResponse) {
            $response->original = $original;
        }

        $response->headers->remove('Content-Length');
        $response->headers->set('X-NewDebugBar-Profile', $profileId);

        return $response;
    }

    public function supports(Response $response): bool
    {
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return false;
        }

        if ($response->isRedirection()) {
            return false;
        }

        if (str_contains(strtolower((string) $response->headers->get('Content-Disposition')), 'attachment')) {
            return false;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type'));

        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return false;
        }

        return preg_match('/<\/body\s*>/i', (string) $response->getContent()) === 1;
    }
}
