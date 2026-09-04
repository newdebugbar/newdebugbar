<?php

namespace NewDebugBar\Support;

/** Reconstructs URLs without credentials and with redacted query values. */
final class SafeUrl
{
    public function __construct(private readonly Redactor $redactor) {}

    public function clean(string $url, string $path = ''): string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return '[invalid-url]';
        }

        $query = [];

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            $query = $this->redactor->clean($query, key: $path === '' ? 'query' : $path.'.query');
        }

        $safe = strtolower((string) $parts['scheme']).'://'.(string) $parts['host'];

        if (isset($parts['port'])) {
            $safe .= ':'.(int) $parts['port'];
        }

        $safe .= $parts['path'] ?? '/';

        if (is_array($query) && $query !== []) {
            $safe .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $safe;
    }
}
