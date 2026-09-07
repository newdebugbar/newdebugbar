<?php

it('composes Notifications from the canonical inspector grammar', function () {
    $views = dirname(__DIR__, 2).'/resources/views';
    $section = file_get_contents($views.'/livewire/sections/notifications.blade.php');
    $detail = file_get_contents($views.'/components/notification-detail.blade.php');
    $header = file_get_contents($views.'/components/notification-header.blade.php');
    $payload = file_get_contents($views.'/components/notification-payload-panel.blade.php');

    expect($section)
        ->toContain('<x-newdebugbar::inspector-workspace')
        ->toContain('frame="top"')
        ->toContain('<x-newdebugbar::inspector-list-panel')
        ->toContain('<x-newdebugbar::inspector-list-controls')
        ->toContain('<x-newdebugbar::search-field')
        ->toContain('<x-newdebugbar::select-field')
        ->toContain('<x-newdebugbar::notification-detail')
        ->not->toContain('<input')
        ->not->toContain('<select')
        ->not->toContain('x-ref="notificationList"');

    expect($detail)
        ->toContain('<x-newdebugbar::inspector-detail-pane')
        ->toContain('<x-newdebugbar::inspector-detail-tabs')
        ->toContain('<x-newdebugbar::filter-tab')
        ->toContain('variant="segmented"')
        ->toContain('<x-newdebugbar::select-field')
        ->toContain('<x-newdebugbar::notification-header')
        ->toContain('<x-newdebugbar::notification-delivery-panel')
        ->toContain('<x-newdebugbar::notification-payload-panel')
        ->toContain('<x-newdebugbar::inspector-source-panel')
        ->not->toContain('<select')
        ->not->toContain('x-ref="notificationDetail"');

    expect($header)
        ->toContain('<x-newdebugbar::inspector-detail-header')
        ->toContain('<x-newdebugbar::inspector-facts', 'columns="4"')
        ->toContain('data-ndb-notification-facts')
        ->toContain('data-ndb-notification-destination')
        ->toContain('label="Channels"')
        ->toContain('label="Duration"')
        ->toContain('label="Execution"')
        ->toContain('label="Source"')
        ->not->toContain('<div data-ndb-notification-fact');

    expect($payload)
        ->toContain('<x-newdebugbar::inspector-evidence label="Application payload"')
        ->toContain('label="Anonymous routes"')
        ->toContain('<x-newdebugbar::inspector-definition-list')
        ->toContain('label="Destination"')
        ->toContain('label="Status"')
        ->toContain('label="Response type"')
        ->toContain('label="Exception"')
        ->toContain('label="Failed at"')
        ->toContain('label="Message ID"');
});
