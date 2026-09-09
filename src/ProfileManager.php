<?php

namespace NewDebugBar;

use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use NewDebugBar\Collectors\QueryCollector;
use NewDebugBar\Collectors\RedisCollector;
use NewDebugBar\Collectors\ValidationCollector;
use NewDebugBar\Contracts\Collector;
use NewDebugBar\Support\CallSiteResolver;
use NewDebugBar\Support\ExceptionNormalizer;
use NewDebugBar\Support\Redactor;
use NewDebugBar\Support\RequestContext;
use NewDebugBar\Support\RuntimeContext;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Coordinates request timing, collectors, and the final debug profile. */
final class ProfileManager
{
    /** @var array<string, Collector> */
    private array $collectors = [];

    private bool $collecting = false;

    private int $startedAt = 0;

    private int $responseHandledAt = 0;

    private ?float $responseDurationMs = null;

    private bool $afterResponse = false;

    private bool $afterResponseActivity = false;

    private string $profileType = 'http';

    private string $primarySectionLabel = 'Request';

    private string $profileId = '';

    /** @var array<string, mixed> */
    private array $request = [];

    /** @var array<int, true> */
    private array $recordedValidationExceptions = [];

    /** @param iterable<Collector> $collectors */
    public function __construct(
        iterable $collectors,
        private readonly Redactor $redactor,
        private readonly ?ExceptionNormalizer $exceptionNormalizer = null,
        private readonly ?RuntimeContext $runtimeContext = null,
        private readonly ?RequestContext $requestContext = null,
        private readonly ?CallSiteResolver $callSites = null,
    ) {
        foreach ($collectors as $collector) {
            $this->collectors[$collector->key()] = $collector;
        }
    }

    public function begin(Request $request): void
    {
        $this->start('http', 'Request');
        $query = $this->redactor->clean($request->query());
        $this->request = [
            'method' => $request->getMethod(),
            'url' => $this->redactedUrl($request, is_array($query) ? $query : []),
            'path' => '/'.ltrim($request->path(), '/'),
            'query' => $query,
            'input' => $this->requestInput($request),
            'headers' => $this->redactor->clean($request->headers->all()),
        ];
        $this->collecting = true;
    }

    /** @param array<string, mixed> $context */
    public function beginRuntime(string $type, string $name, array $context = []): void
    {
        $type = in_array($type, ['artisan', 'queue', 'test'], true) ? $type : 'runtime';
        $this->start($type, 'Runtime');
        $safeContext = $this->redactor->clean($context);
        $this->request = [
            'method' => 'CLI',
            'url' => null,
            'path' => $type.':'.$name,
            'query' => [],
            'input' => [],
            'headers' => [],
            'runtime_type' => $type,
            'name' => $name,
            'context' => is_array($safeContext) ? $safeContext : [],
        ];
        $this->collecting = true;
    }

    public function isCollecting(): bool
    {
        return $this->collecting;
    }

    /** @param array<string, mixed> $item */
    public function record(string $collector, array $item): void
    {
        if (! $this->collecting) {
            return;
        }

        if (isset($this->collectors[$collector])) {
            $timing = ['at_ms' => $this->elapsedMilliseconds()];

            if ($this->afterResponse) {
                $this->afterResponseActivity = true;
                $timing['lifecycle'] = 'after_response';
                $timing['after_response_ms'] = $this->afterResponseMilliseconds();
            }

            $this->collectors[$collector]->record([
                ...$item,
                ...$timing,
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function checkpoint(Request $request, ?Response $response = null): array
    {
        $this->completeRequest($request, $response);
        $this->responseHandledAt = hrtime(true);
        $this->responseDurationMs = $this->elapsedMilliseconds();
        $this->collecting = false;

        return $this->buildProfile('terminating');
    }

    public function resumeAfterResponse(): void
    {
        if ($this->responseHandledAt === 0 || $this->profileType !== 'http') {
            return;
        }

        $this->afterResponse = true;
        $this->collecting = true;
    }

    /** @return array<string, mixed>|null */
    public function finishAfterResponse(): ?array
    {
        if ($this->responseHandledAt === 0 || $this->profileType !== 'http') {
            return null;
        }

        try {
            return $this->buildProfile('complete');
        } finally {
            $this->collecting = false;
            $this->afterResponse = false;
        }
    }

    /** @return array<string, mixed> */
    public function finishRuntime(int $exitCode = 0): array
    {
        try {
            $this->request = [
                ...$this->request,
                'route' => null,
                'action' => null,
                'parameters' => [],
                'middleware' => [],
                'status' => $exitCode,
                'exit_code' => $exitCode,
                'content_type' => null,
                'request_size_bytes' => 0,
                'response_size_bytes' => 0,
                'session_present' => false,
                'authenticated' => false,
                'response_headers' => [],
            ];

            return $this->buildProfile('complete');
        } finally {
            $this->collecting = false;
        }
    }

    public function discard(): void
    {
        $this->collecting = false;
        $this->afterResponse = false;
    }

    public function currentProfileId(): ?string
    {
        return $this->profileId !== '' ? $this->profileId : null;
    }

    /** @return array<string, mixed> */
    public function runtimeProfileContext(): array
    {
        $context = $this->request['context'] ?? [];

        return $this->profileType === 'queue' && is_array($context) ? $context : [];
    }

    /** @return array<string, mixed> */
    public function recordException(Throwable $exception): array
    {
        $normalized = $this->exceptionNormalizer?->normalize($exception) ?? [
            'class' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine(),
            'frames' => ['application' => [], 'vendor' => []],
            'source' => null,
            'causes' => [],
            'chain_truncated' => false,
        ];
        $this->record('exceptions', $normalized);

        return $normalized;
    }

    public function recordValidationException(ValidationException $exception): void
    {
        $exceptionId = spl_object_id($exception);

        if (isset($this->recordedValidationExceptions[$exceptionId])) {
            return;
        }

        $this->recordedValidationExceptions[$exceptionId] = true;
        $failed = $exception->validator->failed();
        $messages = $exception->validator->errors()->toArray();
        $fields = array_values(array_unique(array_map(
            'strval',
            [...array_keys($failed), ...array_keys($messages)],
        )));
        $rules = [];

        foreach ($fields as $field) {
            $rules[$field] = array_values(array_map('strval', array_keys((array) ($failed[$field] ?? []))));
        }

        $this->record('validation', [
            'source' => 'exception',
            'fields' => $fields,
            'rules' => $rules,
            'messages' => $messages,
            'error_bag' => (string) $exception->errorBag,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'exception_status' => (int) $exception->status,
            'redirect_requested' => is_string($exception->redirectTo) && $exception->redirectTo !== '',
            'callsite' => $this->callSites?->fromThrowable($exception),
        ]);
    }

    /** @param array<string, mixed> $item */
    public function recordTransaction(array $item): void
    {
        $collector = $this->collectors['queries'] ?? null;

        if ($this->collecting && $collector instanceof QueryCollector) {
            $collector->recordTransaction([
                ...$item,
                'at_ms' => $this->elapsedMilliseconds(),
            ]);
        }
    }

    public function excludeRedisCacheOperation(string $operation): void
    {
        $collector = $this->collectors['redis'] ?? null;

        if ($this->collecting && $collector instanceof RedisCollector) {
            $collector->excludeCacheOperation($operation);
        }
    }

    private function responseSize(?Response $response): int
    {
        $contentLength = $response?->headers->get('Content-Length');

        if (is_numeric($contentLength)) {
            return max(0, (int) $contentLength);
        }

        if ($response instanceof BinaryFileResponse) {
            $file = $response->getFile();

            return $file->isFile() ? max(0, $file->getSize()) : 0;
        }

        if ($response instanceof StreamedResponse) {
            return 0;
        }

        $content = $response?->getContent();

        return is_string($content) ? strlen($content) : 0;
    }

    private function start(string $type, string $primarySectionLabel): void
    {
        foreach ($this->collectors as $collector) {
            $collector->reset();
        }

        $this->profileType = $type;
        $this->primarySectionLabel = $primarySectionLabel;
        $this->profileId = (string) Str::uuid();
        $this->startedAt = hrtime(true);
        $this->responseHandledAt = 0;
        $this->responseDurationMs = null;
        $this->afterResponse = false;
        $this->afterResponseActivity = false;
        $this->request = [];
        $this->recordedValidationExceptions = [];
        $this->collecting = true;
    }

    private function recordSessionValidationErrors(Request $request): void
    {
        try {
            if (! $request->hasSession()) {
                return;
            }

            $errors = $request->session()->get('errors');
        } catch (Throwable) {
            return;
        }

        if (! $errors instanceof ViewErrorBag) {
            return;
        }

        foreach ($errors->getBags() as $bag => $messages) {
            $messageArray = $messages->toArray();

            if ($messageArray === []) {
                continue;
            }

            $fields = array_values(array_map('strval', array_keys($messageArray)));
            $this->record('validation', [
                'source' => 'session',
                'from_previous_request' => true,
                'fields' => $fields,
                'rules' => array_fill_keys($fields, []),
                'messages' => $messageArray,
                'error_bag' => (string) $bag,
                'exception_class' => null,
                'exception_message' => null,
                'exception_status' => null,
                'redirect_requested' => false,
                'callsite' => null,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function buildProfile(string $completionState): array
    {
        $duration = $this->responseDurationMs
            ?? (($this->startedAt > 0 ? hrtime(true) - $this->startedAt : 0) / 1_000_000);
        $metrics = [
            'duration_ms' => round($duration, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1_048_576, 2),
        ];

        if ($this->afterResponseActivity && $this->responseHandledAt > 0) {
            $metrics['after_response_duration_ms'] = round((hrtime(true) - $this->responseHandledAt) / 1_000_000, 2);
        }
        $sections = [
            'overview' => [
                'label' => 'Overview',
                'summary' => $metrics,
                'payload' => $this->runtimeContext?->build() ?? [],
            ],
            'request' => [
                'label' => $this->primarySectionLabel,
                'summary' => [
                    'method' => $this->request['method'],
                    'status' => $this->request['status'],
                    'exit_code' => $this->request['exit_code'] ?? null,
                ],
                'payload' => $this->request,
            ],
        ];

        foreach ($this->collectors as $collector) {
            $sections[$collector->key()] = [
                'label' => $collector->label(),
                'summary' => $collector->summary(),
                'payload' => $collector->payload(),
            ];
        }

        return [
            'schema_version' => 1,
            'id' => $this->profileId !== '' ? $this->profileId : (string) Str::uuid(),
            'recorded_at' => now()->toIso8601String(),
            'profile_type' => $this->profileType,
            'completion_state' => $completionState,
            'environment' => app()->environment(),
            'metrics' => $metrics,
            'sections' => $sections,
        ];
    }

    private function requestSize(Request $request): int
    {
        $contentLength = $request->headers->get('Content-Length');

        if (is_numeric($contentLength)) {
            return max(0, (int) $contentLength);
        }

        $content = $request->getContent();

        if ($content !== '') {
            return strlen($content);
        }

        return strlen(http_build_query($request->request->all(), '', '&', PHP_QUERY_RFC3986));
    }

    /** @return array<string, mixed> */
    private function requestInput(Request $request): array
    {
        if (! $request->headers->has('X-Livewire')) {
            $input = $this->redactor->clean($request->input());

            return is_array($input) ? $input : [];
        }

        $messages = $request->input('components');

        return [
            'component_message_count' => is_array($messages) ? count($messages) : 0,
            'snapshot_data_stored' => false,
        ];
    }

    private function hasStartedSession(Request $request): bool
    {
        try {
            return $request->hasSession() && $request->session()->isStarted();
        } catch (Throwable) {
            return false;
        }
    }

    private function isAuthenticated(Request $request): bool
    {
        try {
            return $request->user() !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $parameters */
    private function normalizeRouteParameters(array $parameters): array
    {
        return array_map(function (mixed $parameter): mixed {
            if ($parameter instanceof UrlRoutable) {
                return $parameter->getRouteKey();
            }

            return is_object($parameter) ? '['.$parameter::class.']' : $parameter;
        }, $parameters);
    }

    /** @param array<string, mixed> $query */
    private function redactedUrl(Request $request, array $query): string
    {
        if ($query === []) {
            return $request->url();
        }

        return $request->url().'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function elapsedMilliseconds(): float
    {
        return round(($this->startedAt > 0 ? hrtime(true) - $this->startedAt : 0) / 1_000_000, 3);
    }

    private function afterResponseMilliseconds(): float
    {
        return round(($this->responseHandledAt > 0 ? hrtime(true) - $this->responseHandledAt : 0) / 1_000_000, 3);
    }

    private function completeRequest(Request $request, ?Response $response): void
    {
        $route = $request->route();
        $middleware = is_object($route) ? app('router')->gatherRouteMiddleware($route) : [];
        $authentication = $this->requestContext?->authentication($request, $middleware) ?? [];
        $session = $this->requestContext?->session($request) ?? [];
        $validation = $this->collectors['validation'] ?? null;

        if ($validation instanceof ValidationCollector) {
            if ($validation->hasFailures()) {
                $validation->attachResponseStatus($response?->getStatusCode() ?? 500);
            } else {
                $this->recordSessionValidationErrors($request);
            }
        }

        $this->request = [
            ...$this->request,
            'route' => is_object($route) ? $route->getName() : null,
            'action' => is_object($route) ? $route->getActionName() : null,
            'parameters' => is_object($route)
                ? $this->redactor->clean($this->normalizeRouteParameters($route->parameters()))
                : [],
            'middleware' => $middleware,
            'status' => $response?->getStatusCode() ?? 500,
            'request_type' => $this->requestType($request, $response),
            'content_type' => $response?->headers->get('Content-Type'),
            'request_size_bytes' => $this->requestSize($request),
            'response_size_bytes' => $this->responseSize($response),
            'session_present' => (bool) ($session['present'] ?? $this->hasStartedSession($request)),
            'authenticated' => (bool) ($authentication['authenticated'] ?? $this->isAuthenticated($request)),
            'authentication' => $authentication,
            'session' => $session,
            'response_headers' => $this->redactor->clean($response?->headers->all() ?? []),
        ];
    }

    private function requestType(Request $request, ?Response $response): string
    {
        $status = $response?->getStatusCode() ?? 500;

        if ($status >= 300 && $status < 400) {
            return 'redirect';
        }

        if ($response instanceof BinaryFileResponse) {
            return 'download';
        }

        if (str_contains(strtolower((string) $response?->headers->get('Content-Disposition')), 'attachment')) {
            return 'download';
        }

        if ($response instanceof StreamedResponse) {
            return 'stream';
        }

        if ($request->headers->has('X-Livewire')) {
            return 'livewire';
        }

        if ($request->ajax()) {
            return 'ajax';
        }

        $contentType = strtolower((string) $response?->headers->get('Content-Type'));

        return $request->expectsJson() || str_contains($contentType, 'json')
            ? 'json'
            : 'full_page';
    }
}
