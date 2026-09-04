<?php

namespace NewDebugBar\Support;

use BackedEnum;
use DateTimeInterface;
use Stringable;
use UnitEnum;

/**
 * Hides recognized credentials and application-selected paths in captured data.
 * Capture cleanup also bounds values; the final redaction pass preserves those bounds.
 */
final class Redactor
{
    private const REDACTED = '[redacted]';

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'authorization',
        'cookie',
        'cookies',
        'set_cookie',
        'password',
        'password_confirmation',
        'secret',
        'session',
        'session_id',
        'sessionid',
        'token',
        'access_token',
        'api_key',
        'apikey',
        'private_key',
        'client_secret',
        'csrf',
        'xsrf',
        'php_auth_pw',
        'passwd',
        'passphrase',
        'jwt',
        '_token',
    ];

    /** @var list<string> */
    private readonly array $maskedPatterns;

    /** @param list<string> $maskedPaths Additional dotted paths; * matches any characters. */
    public function __construct(
        private readonly int $maxDepth = 5,
        private readonly int $maxStringLength = 2_000,
        private readonly int $maxArrayItems = 100,
        array $maskedPaths = [],
    ) {
        $this->maskedPatterns = array_map(
            fn (string $path): string => '~(?:^|\\.)'.str_replace('\\*', '.*', preg_quote($this->normalizePath($path), '~')).'$~D',
            array_values(array_filter($maskedPaths, fn (mixed $path): bool => is_string($path) && trim($path) !== '')),
        );
    }

    public function clean(mixed $value, int $depth = 0, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitivePath($key)) {
            return self::REDACTED;
        }

        if ($depth >= $this->maxDepth) {
            return '[maximum depth reached]';
        }

        if (is_array($value)) {
            $clean = [];

            foreach (array_slice($value, 0, $this->maxArrayItems, true) as $itemKey => $item) {
                $path = $key === null || $key === '' ? (string) $itemKey : $key.'.'.$itemKey;
                $clean[$itemKey] = $this->clean($item, $depth + 1, $path);
            }

            if (count($value) > $this->maxArrayItems) {
                $clean['__truncated__'] = count($value) - $this->maxArrayItems;
            }

            return $clean;
        }

        if (is_string($value)) {
            if (mb_strlen($value) <= $this->maxStringLength) {
                return $value;
            }

            return mb_substr($value, 0, $this->maxStringLength).'…';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof Stringable) {
            return $this->clean((string) $value, $depth, $key);
        }

        if (is_object($value)) {
            return '['.$value::class.']';
        }

        if (is_resource($value)) {
            return '[resource]';
        }

        return $value;
    }

    /** Redacts array children without converting or truncating already captured values. */
    public function redact(mixed $value, string $path = ''): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $itemPath = $path === '' ? (string) $key : $path.'.'.$key;
            $value[$key] = $this->isSensitivePath($itemPath)
                ? self::REDACTED
                : $this->redact($item, $itemPath);
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $bindings
     * @return array<array-key, mixed>
     */
    public function cleanBindings(array $bindings, string $policy = 'safe'): array
    {
        if ($policy === 'none') {
            return [];
        }

        if ($policy === 'full') {
            /** @var array<array-key, mixed> $clean */
            $clean = $this->clean($bindings);

            return $clean;
        }

        $clean = [];

        foreach (array_slice($bindings, 0, $this->maxArrayItems, true) as $key => $binding) {
            if ($this->isSensitivePath((string) $key)) {
                $clean[$key] = self::REDACTED;

                continue;
            }

            $clean[$key] = match (true) {
                $binding === null, is_bool($binding), is_int($binding), is_float($binding) => $binding,
                $binding instanceof BackedEnum && ! is_string($binding->value) => $binding->value,
                $binding instanceof DateTimeInterface => '[datetime]',
                $binding instanceof UnitEnum => '[enum]',
                is_string($binding), $binding instanceof Stringable => '[string]',
                is_array($binding) => '[array]',
                is_object($binding) => '['.$binding::class.']',
                is_resource($binding) => '[resource]',
                default => '['.get_debug_type($binding).']',
            };
        }

        if (count($bindings) > $this->maxArrayItems) {
            $clean['__truncated__'] = count($bindings) - $this->maxArrayItems;
        }

        return $clean;
    }

    public function cleanSql(string $sql): string
    {
        $sql = preg_replace('/\/\*.*?\*\//s', '/* comment hidden */', $sql) ?? $sql;
        $sql = preg_replace('/(?:--|#)[^\r\n]*/', '-- comment hidden', $sql) ?? $sql;
        $sql = preg_replace('/\$([a-z_][a-z0-9_]*)\$.*?\$\1\$/is', "'[string]'", $sql) ?? $sql;
        $sql = preg_replace('/\$\$.*?\$\$/s', "'[string]'", $sql) ?? $sql;

        return preg_replace("/'(?:''|\\\\.|[^'])*'/s", "'[string]'", $sql) ?? $sql;
    }

    public function cleanKey(mixed $key, string $policy = 'hash'): string
    {
        $value = is_scalar($key) || $key instanceof Stringable ? (string) $key : get_debug_type($key);

        if ($policy !== 'full') {
            return substr(hash('sha256', $value), 0, 16);
        }

        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
        $value = preg_replace(
            '/\b(token|password|secret|authorization|api[_-]?key)([:=_-]?)[^:|\/]+/i',
            '$1$2[redacted]',
            $value,
        ) ?? $value;

        return mb_strlen($value) > 250 ? mb_substr($value, 0, 249).'…' : $value;
    }

    public function isSensitive(string $key): bool
    {
        $normalized = $this->normalizeKey($key);

        return in_array($normalized, self::SENSITIVE_KEYS, true)
            || str_ends_with($normalized, '_api_key')
            || str_ends_with($normalized, '_apikey')
            || str_ends_with($normalized, '_authorization')
            || str_ends_with($normalized, '_password')
            || str_ends_with($normalized, '_password_confirmation')
            || str_ends_with($normalized, '_private_key')
            || str_ends_with($normalized, '_secret')
            || str_ends_with($normalized, '_session_id')
            || str_ends_with($normalized, '_cookie')
            || str_ends_with($normalized, '_cookies')
            || str_ends_with($normalized, '_php_auth_pw')
            || str_ends_with($normalized, '_passphrase')
            || str_ends_with($normalized, '_token');
    }

    public function isSensitivePath(string $path): bool
    {
        $lastSegment = strrchr($path, '.');

        if ($this->isSensitive($path) || ($lastSegment !== false && $this->isSensitive(substr($lastSegment, 1)))) {
            return true;
        }

        return $this->matchesConfiguredPath($path);
    }

    public function matchesConfiguredPath(string $path): bool
    {
        $normalized = $this->normalizePath($path);

        foreach ($this->maskedPatterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    private function normalizePath(string $path): string
    {
        return implode('.', array_map($this->normalizeKey(...), explode('.', trim($path, '. '))));
    }

    private function normalizeKey(string $key): string
    {
        $key = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $key) ?? $key;
        $key = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $key) ?? $key;

        return trim(strtolower(preg_replace('/[^a-zA-Z0-9*]+/', '_', $key) ?? $key), '_');
    }
}
