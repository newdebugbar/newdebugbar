<?php

use NewDebugBar\Support\Redactor;
use NewDebugBar\Support\SafeUrl;

it('removes credentials fragments and private query values from URLs', function () {
    $urls = new SafeUrl(new Redactor);

    expect($urls->clean('https://user:password@example.test:8443/v1/items?token=secret&limit=5#private'))
        ->toBe('https://example.test:8443/v1/items?token=%5Bredacted%5D&limit=5')
        ->and($urls->clean('/relative/private'))->toBe('[invalid-url]');
});

it('redacts normalized query credentials and keeps ordinary query values', function () {
    $urls = new SafeUrl(new Redactor);

    expect($urls->clean('https://example.test/items?APIKey=secret&nested[accessToken]=secret&tokenCount=4&public_key=visible'))
        ->toBe('https://example.test/items?APIKey=%5Bredacted%5D&nested%5BaccessToken%5D=%5Bredacted%5D&tokenCount=4&public_key=visible');
});

it('applies custom query masks with an optional collector context', function () {
    $urls = new SafeUrl(new Redactor(maskedPaths: ['query.customer.email', 'http.0.url.query.account_id']));

    expect($urls->clean('https://example.test/items?customer[email]=person&account_id=123&limit=5', 'http.0.url'))
        ->toBe('https://example.test/items?customer%5Bemail%5D=%5Bredacted%5D&account_id=%5Bredacted%5D&limit=5')
        ->and($urls->clean('https://example.test/items?account_id=123'))
        ->toBe('https://example.test/items?account_id=123');
});
