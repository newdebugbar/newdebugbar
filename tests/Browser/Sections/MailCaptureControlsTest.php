<?php

uses(OmitsMailDownloads::class);

/** Boots populated mail fixtures with downloads disabled before mail listeners resolve. */
trait OmitsMailDownloads
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('newdebugbar.mail_preview.capture_eml', false);
        $app['config']->set('newdebugbar.mail_preview.capture_attachment_bodies', false);
    }
}

it('keeps previews and metadata usable without offering missing downloads', function (int $width, int $height, string $theme) {
    $page = visit('/profiled-mail-rich')->resize($width, $height);
    $preferences = json_encode(['theme' => $theme, 'favorites' => []], JSON_THROW_ON_ERROR);
    $page->script("localStorage.setItem('newdebugbar.preferences.v1', '{$preferences}')");
    $page->refresh();

    if ($width < 640) {
        $page->click('[data-ndb-mobile-toolbar-trigger="actions"]')
            ->click('[data-ndb-mobile-toolbar-action="inspector"]')
            ->click('[data-ndb-header-mobile-trigger="actions"]')
            ->click('[data-ndb-header-mobile-action="sections"]');
    } else {
        $page->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');
    }

    $page->click('[data-ndb-select-section="mail"]')->waitForText('Payment receipt #NS-1042');
    if ($width < 640) {
        $page->click('[data-ndb-mail-item="1"]');
    }

    $page->assertAttribute('#newdebugbar', 'data-ndb-theme', $theme)
        ->assertVisible('[data-ndb-mail-preview-frame]')
        ->click('[data-ndb-mail-actions-trigger]')
        ->assertScript('getComputedStyle(document.querySelector("[data-ndb-mail-download]")).display === "none"')
        ->click('[data-ndb-mail-actions-trigger]')
        ->click('[data-ndb-mail-detail-tab="message"]')
        ->assertSee('receipt-NS-1042.pdf')
        ->assertMissing('[data-ndb-mail-attachment-download]')
        ->assertScript('document.querySelector("[role=dialog]").scrollWidth <= document.querySelector("[role=dialog]").clientWidth + 1')
        ->click('[data-ndb-mail-detail-tab="preview"]')
        ->assertVisible('[data-ndb-mail-preview-frame]')
        ->assertNoJavaScriptErrors();
})->with([
    [1440, 1200, 'light'], [1440, 1200, 'dark'],
    [1280, 720, 'light'], [1280, 720, 'dark'],
    [390, 844, 'light'], [390, 844, 'dark'],
]);
