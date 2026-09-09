<?php

namespace NewDebugBar\Presentation;

use InvalidArgumentException;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Support\Redactor;

/** Builds the bounded, versioned contract exposed through local MCP tools. */
final class McpProfilePresenter
{
    public const RESPONSE_VERSION = 1;

    /** @var list<string> */
    private const SECTION_NAMES = [
        'overview',
        'request',
        'timeline',
        'queries',
        'http_client',
        'queue',
        'mail',
        'notifications',
        'redis',
        'models',
        'cache',
        'views',
        'events',
        'authorization',
        'validation',
        'logs',
        'exceptions',
        'livewire',
    ];

    public function __construct(
        private readonly ProfileStore $store,
        private readonly ProfilePresenter $profiles,
        private readonly ProfileSummaryPresenter $summaries,
        private readonly Redactor $redactor,
        private readonly string $projectPath,
        private readonly int $maxItems = 50,
        private readonly int $maxBytes = 100_000,
    ) {}

    /**
     * @param  array{method?: string|null, path?: string|null, status?: int|null, warning?: bool|null}  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters, int $limit): array
    {
        $matching = [];

        foreach ($this->store->recent($this->store->maxProfiles()) as $profile) {
            $presented = $this->profiles->present($profile);
            $summary = $this->summaries->present($presented);
            $summary['available_sections'] = array_values(array_keys($presented['sections'] ?? []));
            $summary['data_path'] = '';

            if (! $this->matchesFilters($summary, $filters)) {
                continue;
            }

            $matching[] = $summary;
        }

        $total = count($matching);
        $summaries = array_slice($matching, 0, max(1, min($limit, $this->store->maxProfiles())));
        $response = $this->profileListResponse($summaries, $total, count($summaries) < $total);

        while ($this->byteLength($response) > $this->maxBytes && $summaries !== []) {
            array_pop($summaries);
            $response = $this->profileListResponse($summaries, $total, true);
        }

        return $response;
    }

    /** @return array<string, mixed> */
    public function section(string $profileId, string $section, int $cursor, int $limit): array
    {
        $profile = $this->find($profileId);

        if ($profile === null) {
            return $this->notFound($profileId);
        }

        $context = $this->backgroundContext($profile);

        if (! in_array($section, self::SECTION_NAMES, true) || ! isset($profile['sections'][$section])) {
            return $this->response([
                'profile_id' => $profileId,
                ...$context,
                'section' => $section,
                'available_sections' => array_values(array_keys($profile['sections'] ?? [])),
            ], 'not_found');
        }

        $sectionData = $profile['sections'][$section];

        $items = $sectionData['payload']['items'] ?? [];
        $payload = $this->safeSectionPayload($section, $sectionData['payload'] ?? []);
        unset($payload['items']);

        return $this->paginatedResponse(
            is_array($items) ? $items : [],
            $cursor,
            $limit,
            fn (array $page, array $pagination): array => [
                'profile_id' => $profileId,
                ...$context,
                'section' => $section,
                'label' => $sectionData['label'] ?? ucfirst($section),
                'summary' => $this->clean($sectionData['summary'] ?? []),
                'payload' => [
                    ...$payload,
                    'items' => array_map(fn (mixed $item): mixed => $this->safeItem($section, $item), $page),
                ],
                'pagination' => $pagination,
            ],
        );
    }

    /** @return array<string, mixed> */
    public function queries(
        string $profileId,
        string $filter,
        string $search,
        string $sort,
        int $cursor,
        int $limit,
    ): array {
        $profile = $this->find($profileId);

        if ($profile === null) {
            return $this->notFound($profileId);
        }

        $context = $this->backgroundContext($profile);

        $section = $profile['sections']['queries'] ?? ['summary' => [], 'payload' => []];
        $items = $filter === 'repeated'
            ? ($section['payload']['repeated_groups'] ?? [])
            : array_values(array_filter(
                $section['payload']['items'] ?? [],
                fn (array $query): bool => $filter === 'all'
                    || ($filter === 'slow' && ($query['slow'] ?? false))
                    || in_array($filter, ['read', 'write'], true) && ($query['query_type'] ?? null) === $filter,
            ));
        $needle = mb_strtolower(trim($search));

        if ($needle !== '') {
            $items = array_values(array_filter($items, function (array $query) use ($needle): bool {
                $haystack = ($query['sql'] ?? '').' '.($query['driver'] ?? '').' '.json_encode(
                    $query['bindings'] ?? array_column($query['executions'] ?? [], 'bindings'),
                    JSON_UNESCAPED_SLASHES,
                );

                return str_contains(mb_strtolower($haystack), $needle);
            }));
        }

        usort($items, fn (array $left, array $right): int => $sort === 'duration'
            ? ($right['duration_ms'] ?? 0) <=> ($left['duration_ms'] ?? 0)
            : $this->executionNumber($left) <=> $this->executionNumber($right));

        return $this->paginatedResponse(
            $items,
            $cursor,
            $limit,
            fn (array $page, array $pagination): array => [
                'profile_id' => $profileId,
                ...$context,
                'filter' => $filter,
                'search' => $search,
                'sort' => $sort,
                'summary' => $this->clean($section['summary'] ?? []),
                $filter === 'repeated' ? 'repeated_groups' : 'items' => array_map(
                    fn (mixed $item): mixed => $this->safeItem('queries', $item),
                    $page,
                ),
                'pagination' => $pagination,
            ],
        );
    }

    /** @return array<string, mixed> */
    public function findings(string $profileId, int $cursor, int $limit): array
    {
        $profile = $this->find($profileId);

        if ($profile === null) {
            return $this->notFound($profileId);
        }

        $context = $this->backgroundContext($profile);

        return $this->paginatedResponse(
            is_array($profile['findings'] ?? null) ? $profile['findings'] : [],
            $cursor,
            $limit,
            fn (array $page, array $pagination): array => [
                'profile_id' => $profileId,
                ...$context,
                'findings' => $this->clean($page),
                'pagination' => $pagination,
            ],
        );
    }

    /** @return array<string, mixed> */
    public function data(string $profileId, string $path, int $cursor, int $limit): array
    {
        $profile = $this->find($profileId);

        if ($profile === null) {
            return $this->response([
                'profile_id' => $profileId,
                'path' => $path,
            ], 'not_found');
        }

        $context = $this->backgroundContext($profile);

        [$found, $value] = $this->valueAtPointer($profile, $path);

        if (! $found) {
            return $this->response([
                'profile_id' => $profileId,
                ...$context,
                'path' => $path,
            ], 'not_found');
        }

        $type = $this->nodeType($value);

        if (! is_array($value)) {
            $response = $this->response([
                'profile_id' => $profileId,
                ...$context,
                'path' => $path,
                'type' => $type,
                'value' => $value,
            ]);

            if ($type !== 'string' || $this->byteLength($response) <= $this->maxBytes) {
                return $response;
            }

            return $this->chunkedStringResponse($profileId, $path, $value, $cursor, $limit, $context);
        }

        $entries = [];

        foreach ($value as $key => $item) {
            $entries[] = $this->dataEntry($profileId, $path, (string) $key, $item, $context);
        }

        return $this->paginatedResponse(
            $entries,
            $cursor,
            $limit,
            fn (array $page, array $pagination): array => [
                'profile_id' => $profileId,
                ...$context,
                'path' => $path,
                'type' => $type,
                'count' => count($value),
                'entries' => $page,
                'pagination' => $pagination,
            ],
        );
    }

    /** @return list<string> */
    public function sectionNames(): array
    {
        return self::SECTION_NAMES;
    }

    public function maxItems(): int
    {
        return $this->maxItems;
    }

    /** @return array<string, mixed>|null */
    private function find(string $profileId): ?array
    {
        if (! ProfileStore::validId($profileId)) {
            return null;
        }

        $profile = $this->store->get($profileId);

        if ($profile === null) {
            return null;
        }

        return $this->profiles->present($profile);
    }

    /** @param array<string, mixed> $summary @param array<string, mixed> $filters */
    private function matchesFilters(array $summary, array $filters): bool
    {
        if (($filters['method'] ?? null) !== null && $summary['method'] !== $filters['method']) {
            return false;
        }

        if (($filters['path'] ?? null) !== null && ! str_contains((string) $summary['path'], (string) $filters['path'])) {
            return false;
        }

        if (($filters['status'] ?? null) !== null && $summary['status'] !== $filters['status']) {
            return false;
        }

        return ($filters['warning'] ?? null) === null || $summary['warning'] === $filters['warning'];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function safeSectionPayload(string $section, array $payload): array
    {
        if ($section === 'queries') {
            unset($payload['records']);
        }

        if ($section !== 'request') {
            unset(
                $payload['items'],
                $payload['groups'],
                $payload['model_groups'],
                $payload['model_group_previews'],
                $payload['repeated_groups'],
                $payload['repeated_misses'],
                $payload['activity'],
                $payload['components'],
            );

            return $this->clean($payload);
        }

        $runtimeContext = is_array($payload['context'] ?? null) ? $payload['context'] : [];

        return $this->clean([
            'method' => $payload['method'] ?? null,
            'path' => $payload['path'] ?? null,
            'route' => $payload['route'] ?? null,
            'action' => $payload['action'] ?? null,
            'parameters' => array_keys(is_array($payload['parameters'] ?? null) ? $payload['parameters'] : []),
            'middleware' => $payload['middleware'] ?? [],
            'status' => $payload['status'] ?? null,
            'runtime_type' => $payload['runtime_type'] ?? null,
            'runtime_name' => $payload['name'] ?? null,
            'exit_code' => $payload['exit_code'] ?? null,
            'runtime_context' => [
                'argument_names' => $runtimeContext['argument_names'] ?? [],
                'option_names' => $runtimeContext['option_names'] ?? [],
                'connection' => $runtimeContext['connection'] ?? null,
                'queue' => $runtimeContext['queue'] ?? null,
                'job_id' => $runtimeContext['job_id'] ?? null,
                'attempt' => $runtimeContext['attempt'] ?? null,
                'correlation_key' => $runtimeContext['correlation_key'] ?? null,
                'origin_profile_id' => $runtimeContext['origin_profile_id'] ?? null,
                'communication_type' => $runtimeContext['communication_type'] ?? null,
                'communication_class' => $runtimeContext['communication_class'] ?? null,
                'channels' => $runtimeContext['channels'] ?? [],
                'notifiable_types' => $runtimeContext['notifiable_types'] ?? [],
            ],
            'content_type' => $payload['content_type'] ?? null,
            'request_size_bytes' => $payload['request_size_bytes'] ?? null,
            'response_size_bytes' => $payload['response_size_bytes'] ?? null,
            'session_present' => $payload['session_present'] ?? false,
            'authenticated' => $payload['authenticated'] ?? false,
            'request_header_names' => array_keys(is_array($payload['headers'] ?? null) ? $payload['headers'] : []),
            'response_header_names' => array_keys(is_array($payload['response_headers'] ?? null) ? $payload['response_headers'] : []),
            'query_keys' => array_keys(is_array($payload['query'] ?? null) ? $payload['query'] : []),
            'input_keys' => array_keys(is_array($payload['input'] ?? null) ? $payload['input'] : []),
        ]);
    }

    private function safeItem(string $section, mixed $item): mixed
    {
        if (! is_array($item)) {
            return $this->clean($item);
        }

        if ($section === 'queries') {
            $item = $this->safeQueryItem($item);
        } elseif ($section === 'logs') {
            $item = [
                'at_ms' => $item['at_ms'] ?? null,
                'occurred_at' => $item['occurred_at'] ?? null,
                'level' => $item['level'] ?? null,
                'channel' => $item['channel'] ?? null,
                'message' => '[message hidden]',
                'callsite' => $item['callsite'] ?? null,
                'context_keys' => array_keys(is_array($item['context'] ?? null) ? $item['context'] : []),
                'related_exception' => is_array($item['related_exception'] ?? null) ? [
                    'class' => $item['related_exception']['class'] ?? null,
                    'message' => '[message hidden]',
                    'file' => $item['related_exception']['file'] ?? null,
                    'line' => $item['related_exception']['line'] ?? null,
                ] : null,
            ];
        } elseif ($section === 'exceptions') {
            $causes = array_slice(array_values(array_filter(
                is_array($item['causes'] ?? null) ? $item['causes'] : [],
                'is_array',
            )), 0, 5);
            $item = [
                'at_ms' => $item['at_ms'] ?? null,
                'class' => $item['class'] ?? null,
                'message' => '[message hidden]',
                'file' => $item['file'] ?? null,
                'line' => $item['line'] ?? null,
                'application_frames' => $item['frames']['application'] ?? [],
                'cause_count' => count($causes),
                'causes' => array_map(fn (array $cause): array => [
                    'class' => $cause['class'] ?? null,
                    'file' => $cause['file'] ?? null,
                    'line' => $cause['line'] ?? null,
                ], $causes),
                'chain_truncated' => (bool) ($item['chain_truncated'] ?? false),
            ];
        } elseif ($section === 'models' && array_key_exists('key', $item)) {
            $item['key'] = $item['key'] === null ? null : '[identifier]';
        } elseif ($section === 'views' && is_array($item['data'] ?? null)) {
            $item['data'] = $this->redactor->cleanBindings($item['data'], 'safe');
        } elseif ($section === 'timeline') {
            if (($item['section'] ?? null) === 'logs') {
                $item['label'] = '[log message hidden]';
            } elseif (($item['section'] ?? null) === 'queries' && is_string($item['label'] ?? null)) {
                $item['label'] = $this->redactor->cleanSql($item['label']);
            }
        } elseif ($section === 'mail' && is_array($item['preview'] ?? null)) {
            $preview = $item['preview'];
            $item['preview'] = [
                'available' => true,
                'html_available' => is_string($preview['html'] ?? null),
                'text_available' => is_string($preview['text'] ?? null),
                'eml_available' => is_string($preview['eml'] ?? null),
                'truncated' => (bool) ($preview['truncated'] ?? false),
                'attachments_omitted' => (int) ($preview['attachments_omitted'] ?? 0),
                'addresses_omitted' => (int) ($preview['addresses_omitted'] ?? 0),
            ];
        }

        return $this->clean($item);
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function safeQueryItem(array $item): array
    {
        foreach (['sql', 'normalized_sql'] as $key) {
            if (is_string($item[$key] ?? null)) {
                $item[$key] = $this->redactor->cleanSql($item[$key]);
            }
        }

        $hasBindingMetadata = array_key_exists('bindings', $item)
            || array_key_exists('binding_policy', $item)
            || array_key_exists('bindings_complete', $item)
            || array_key_exists('runnable_available', $item)
            || array_key_exists('runnable_sql', $item);

        if ($hasBindingMetadata) {
            $item['bindings'] = $this->redactor->cleanBindings(
                is_array($item['bindings'] ?? null) ? $item['bindings'] : [],
                'safe',
            );
            $item['binding_policy'] = 'safe';
            $item['bindings_complete'] = false;
            $item['runnable_available'] = false;
        }

        unset($item['runnable_sql']);

        if (is_array($item['executions'] ?? null)) {
            $item['executions'] = array_map(
                fn (mixed $execution): mixed => is_array($execution) ? $this->safeQueryItem($execution) : $execution,
                $item['executions'],
            );
        }

        return $item;
    }

    private function executionNumber(array $item): int
    {
        return (int) ($item['execution'] ?? $item['executions'][0]['execution'] ?? 0);
    }

    /** @return array{0: bool, 1: mixed} */
    private function valueAtPointer(array $profile, string $path): array
    {
        $value = $profile;

        foreach ($this->pointerSegments($path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return [false, null];
            }

            $value = $value[$segment];
        }

        return [true, $value];
    }

    /** @return list<string> */
    private function pointerSegments(string $path): array
    {
        if ($path === '') {
            return [];
        }

        if (! str_starts_with($path, '/')) {
            throw new InvalidArgumentException('The profile data path must be an empty string or a JSON Pointer beginning with /.');
        }

        return array_map(function (string $segment): string {
            if (preg_match('/~(?![01])/', $segment) === 1) {
                throw new InvalidArgumentException('The profile data path contains an invalid JSON Pointer escape.');
            }

            return str_replace(['~1', '~0'], ['/', '~'], $segment);
        }, explode('/', substr($path, 1)));
    }

    /** @return array<string, mixed> */
    private function dataEntry(string $profileId, string $parentPath, string $key, mixed $value, array $context): array
    {
        $path = $parentPath.'/'.$this->escapePointerSegment($key);
        $type = $this->nodeType($value);
        $entry = [
            'key' => $key,
            'path' => $path,
            'type' => $type,
        ];

        if (is_array($value)) {
            $entry['count'] = count($value);

            return $entry;
        }

        if (is_string($value) && $this->byteLength($this->response([
            'profile_id' => $profileId,
            ...$context,
            'path' => $path,
            'type' => 'string',
            'value' => $value,
        ])) > $this->maxBytes) {
            $entry['length_bytes'] = strlen($value);
            $entry['chunked'] = true;

            return $entry;
        }

        $entry['value'] = $value;

        return $entry;
    }

    private function escapePointerSegment(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }

    private function nodeType(mixed $value): string
    {
        return match (true) {
            is_array($value) => array_is_list($value) ? 'list' : 'object',
            is_string($value) => 'string',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_bool($value) => 'boolean',
            $value === null => 'null',
            default => 'unknown',
        };
    }

    /** @return array<string, mixed> */
    private function chunkedStringResponse(
        string $profileId,
        string $path,
        string $value,
        int $cursor,
        int $limit,
        array $context,
    ): array {
        $cursor = max(0, $cursor);
        $limit = max(1, min($limit, $this->maxItems));
        $page = [];
        $offset = 0;
        $index = 0;
        $length = strlen($value);
        $chunkBytes = $this->dataChunkBytes($profileId, $path, $length, $context);

        while ($offset < $length) {
            $chunk = mb_strcut($value, $offset, $chunkBytes, 'UTF-8');

            if ($chunk === '') {
                break;
            }

            if ($index >= $cursor && count($page) < $limit) {
                $page[] = $chunk;
            }

            $offset += strlen($chunk);
            $index++;
        }

        return $this->boundedPaginatedResponse(
            $page,
            $cursor,
            $index,
            fn (array $page, array $pagination): array => [
                'profile_id' => $profileId,
                ...$context,
                'path' => $path,
                'type' => 'string',
                'length_bytes' => $length,
                'chunked' => true,
                'chunks' => $page,
                'pagination' => $pagination,
            ],
        );
    }

    private function dataChunkBytes(string $profileId, string $path, int $length, array $context): int
    {
        $envelope = $this->response([
            'profile_id' => $profileId,
            ...$context,
            'path' => $path,
            'type' => 'string',
            'length_bytes' => $length,
            'chunked' => true,
            'chunks' => [''],
            'pagination' => [
                'cursor' => PHP_INT_MAX,
                'returned' => 1,
                'total' => PHP_INT_MAX,
                'truncated' => true,
                'next_cursor' => PHP_INT_MAX,
            ],
        ]);
        $availableBytes = max(1, $this->maxBytes - $this->byteLength($envelope) - 16);

        return max(4, min(4_000, intdiv($availableBytes, 6)));
    }

    /** @param list<array<string, mixed>> $profiles @return array<string, mixed> */
    private function profileListResponse(array $profiles, int $total, bool $truncated): array
    {
        $context = collect($profiles)->contains(fn (array $profile): bool => isset($profile['background_error']))
            ? ['background_error' => BackgroundActivityPresenter::READ_ERROR]
            : [];

        return $this->response([
            ...$context,
            'profiles' => $profiles,
            'count' => count($profiles),
            'total' => $total,
            'truncated' => $truncated,
        ]);
    }

    /**
     * @param  list<mixed>  $all
     * @param  callable(list<mixed>, array<string, mixed>): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    private function paginatedResponse(array $all, int $cursor, int $limit, callable $build): array
    {
        $cursor = max(0, $cursor);
        $limit = max(1, min($limit, $this->maxItems));
        $page = array_values(array_slice($all, $cursor, $limit));

        return $this->boundedPaginatedResponse($page, $cursor, count($all), $build);
    }

    /**
     * @param  list<mixed>  $page
     * @param  callable(list<mixed>, array<string, mixed>): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    private function boundedPaginatedResponse(array $page, int $cursor, int $total, callable $build): array
    {
        $requestedCount = count($page);
        $truncated = $cursor + count($page) < $total;
        $response = $this->response($build($page, $this->pagination($cursor, count($page), $total, $truncated)));

        while ($this->byteLength($response) > $this->maxBytes && $page !== []) {
            array_pop($page);
            $truncated = true;
            $response = $this->response($build($page, $this->pagination($cursor, count($page), $total, true)));
        }

        if ($requestedCount > 0 && $page === []) {
            $response = $this->response($build([], $this->pagination(
                $cursor,
                0,
                $total,
                true,
                omittedDueToBytes: 1,
            )));
        }

        if ($this->byteLength($response) > $this->maxBytes) {
            $response = $this->minimalPaginatedResponse($response);
        }

        return $response;
    }

    /** @return array<string, mixed> */
    private function pagination(
        int $cursor,
        int $count,
        int $total,
        bool $truncated,
        int $omittedDueToBytes = 0,
    ): array {
        $nextOffset = $cursor + ($omittedDueToBytes > 0 ? $omittedDueToBytes : $count);
        $pagination = [
            'cursor' => $cursor,
            'returned' => $count,
            'total' => $total,
            'truncated' => $truncated,
            'next_cursor' => $nextOffset < $total ? $nextOffset : null,
        ];

        if ($omittedDueToBytes > 0) {
            $pagination['omitted_due_to_bytes'] = $omittedDueToBytes;
        }

        return $pagination;
    }

    /** @param array<string, mixed> $response @return array<string, mixed> */
    private function minimalPaginatedResponse(array $response): array
    {
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $minimal = [];

        foreach (['profile_id', 'section', 'filter', 'search', 'sort', 'path', 'type', 'background_error'] as $key) {
            if (array_key_exists($key, $data)) {
                $minimal[$key] = $data[$key];
            }
        }

        $pagination = is_array($data['pagination'] ?? null) ? $data['pagination'] : [];
        $pagination['truncated'] = true;

        return $this->response([
            ...$minimal,
            'content_omitted' => true,
            'pagination' => $pagination,
        ]);
    }

    /** @return array<string, mixed> */
    private function response(array $data, string $status = 'ok'): array
    {
        if (isset($data['background_error'])) {
            $status = 'partial';
        }

        return [
            'version' => self::RESPONSE_VERSION,
            'status' => $status,
            'data' => $data,
        ];
    }

    /** @param array<string, mixed> $profile @return array<string, string> */
    private function backgroundContext(array $profile): array
    {
        return isset($profile['background_activity']['error'])
            ? ['background_error' => BackgroundActivityPresenter::READ_ERROR]
            : [];
    }

    /** @return array<string, mixed> */
    private function notFound(string $profileId): array
    {
        return $this->response(['profile_id' => $profileId], 'not_found');
    }

    private function clean(mixed $value): mixed
    {
        return $this->relativePaths($this->redactor->clean($value));
    }

    private function relativePaths(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $clean = [];

            foreach ($value as $itemKey => $item) {
                $clean[$itemKey] = $this->relativePaths($item, (string) $itemKey);
            }

            return $clean;
        }

        if ($key === 'file' && is_string($value) && str_starts_with($value, '/')) {
            $project = rtrim(str_replace('\\', '/', $this->projectPath), '/').'/';
            $file = str_replace('\\', '/', $value);

            return str_starts_with($file, $project) ? substr($file, strlen($project)) : basename($file);
        }

        return $value;
    }

    private function byteLength(array $response): int
    {
        return strlen(json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }
}
