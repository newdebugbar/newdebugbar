<?php

namespace NewDebugBar\Support;

use Closure;
use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Auth\Access\Response as AuthorizationResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Cache\Events\CacheFailedOver;
use Illuminate\Cache\Events\CacheFlushed;
use Illuminate\Cache\Events\CacheFlushFailed;
use Illuminate\Cache\Events\CacheFlushing;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\ForgettingKey;
use Illuminate\Cache\Events\KeyForgetFailed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWriteFailed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Events\RetrievingKey;
use Illuminate\Cache\Events\RetrievingManyKeys;
use Illuminate\Cache\Events\WritingKey;
use Illuminate\Cache\Events\WritingManyKeys;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Redis\Events\CommandFailed;
use Illuminate\Routing\Events\PreparingResponse;
use Illuminate\Routing\Events\ResponsePrepared;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Events\Routing;
use Illuminate\Support\Str;
use NewDebugBar\ProfileManager;
use NewDebugBar\Storage\BackgroundActivityStore;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Throwable;
use UnitEnum;
use WeakMap;

/** Routes Laravel runtime events into matching collectors and keeps bounded dispatch evidence. */
final class EventRegistrar
{
    private const MAX_PENDING_CACHE_OPERATIONS = 500;

    private const MAX_MODEL_CHANGED_ATTRIBUTES = 12;

    private const MAX_PENDING_NOTIFICATION_DESTINATIONS = 500;

    /** @var list<string> */
    private const FRAMEWORK_NOTIFICATION_PROPERTIES = [
        'afterCommit',
        'chainCatchCallbacks',
        'chainConnection',
        'chainQueue',
        'chained',
        'connection',
        'debounceOwner',
        'deduplicator',
        'delay',
        'messageGroup',
        'middleware',
        'queue',
        'uniqueLockOwner',
    ];

    /** @var array<int, array<string, mixed>> */
    private array $activeJobContexts = [];

    /** @var list<int> */
    private array $activeJobOrder = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $pendingCacheOperations = [];

    private int $pendingCacheOperationCount = 0;

    private int $cacheOperationSequence = 0;

    /** @var list<class-string> */
    private const EXCLUDED_GENERAL_EVENTS = [
        QueryExecuted::class,
        CacheHit::class,
        CacheMissed::class,
        RetrievingKey::class,
        RetrievingManyKeys::class,
        WritingKey::class,
        WritingManyKeys::class,
        ForgettingKey::class,
        CacheFlushing::class,
        KeyWritten::class,
        KeyWriteFailed::class,
        KeyForgotten::class,
        KeyForgetFailed::class,
        CacheFlushFailed::class,
        CacheFailedOver::class,
        MessageLogged::class,
        RequestSending::class,
        ResponseReceived::class,
        ConnectionFailed::class,
        JobQueueing::class,
        JobQueued::class,
        JobProcessing::class,
        JobProcessed::class,
        JobExceptionOccurred::class,
        MessageSending::class,
        MessageSent::class,
        NotificationSending::class,
        NotificationSent::class,
        NotificationFailed::class,
        CommandExecuted::class,
        CommandFailed::class,
        CacheFlushed::class,
        CommandStarting::class,
        CommandFinished::class,
        GateEvaluated::class,
        TransactionBeginning::class,
        TransactionCommitted::class,
        TransactionRolledBack::class,
        Routing::class,
        RouteMatched::class,
        PreparingResponse::class,
        ResponsePrepared::class,
    ];

    /** @var array<string, mixed> */
    private array $notificationDestinations = [];

    /** @var WeakMap<Model, array{id: int, type: string}>|null */
    private ?WeakMap $modelOperations = null;

    private int $nextModelOperationId = 0;

    public function __construct(
        private readonly Dispatcher $events,
        private readonly Container $container,
        private readonly CallSiteResolver $callSites,
        private readonly SafeUrl $safeUrl,
        private readonly Redactor $redactor,
        private readonly MailPreview $mailPreview,
        private readonly QueuedCommunicationInspector $communications,
        private readonly BackgroundActivityStore $background,
        private readonly LogChannelTracker $logChannels,
    ) {}

    public function register(): void
    {
        $this->listen(GateEvaluated::class, function (GateEvaluated $event): void {
            $location = $this->callSites->capture();
            $handler = $this->authorizationHandler($event);
            $response = $event->result instanceof AuthorizationResponse ? $event->result : null;
            $arguments = array_values($event->arguments);
            $this->manager()->record('authorization', [
                'ability' => $event->ability,
                'result' => ($response?->allowed() ?? $event->result === true) ? 'allowed' : 'denied',
                'result_message' => $response?->message(),
                'result_code' => $response === null ? null : $this->redactor->clean($response->code()),
                'result_status' => $response?->status(),
                'handler' => $handler['legacy'],
                'handler_kind' => $handler['kind'],
                'handler_name' => $handler['name'],
                'handler_source' => $handler['source'],
                'user' => $this->authorizationUser($event->user),
                'arguments' => array_values(array_map(
                    fn (mixed $argument, int $index): array => $this->authorizationArgument($argument, $index + 1),
                    $arguments,
                    array_keys($arguments),
                )),
                'argument_types' => array_values(array_map(
                    fn (mixed $argument): string => $this->authorizationArgumentType($argument),
                    $arguments,
                )),
                'callsite' => $location['callsite'],
                'stack' => $location['stack'],
            ]);
        });

        $this->listen(CommandStarting::class, function (CommandStarting $event): void {
            if ($this->isLongRunningCommand($event->command)) {
                return;
            }

            $this->runtime()->start(
                $event->command === 'test' ? 'test' : 'artisan',
                $event->command,
                [
                    'argument_names' => array_keys($event->input->getArguments()),
                    'option_names' => array_keys($event->input->getOptions()),
                ],
                'command:'.spl_object_id($event->input),
            );
        });

        $this->listen(CommandFinished::class, function (CommandFinished $event): void {
            if (! $this->isLongRunningCommand($event->command)) {
                $this->runtime()->finish($event->exitCode, 'command:'.spl_object_id($event->input));
            }
        });

        $this->listen(QueryExecuted::class, function (QueryExecuted $event): void {
            $location = $this->callSites->capture(includeCompiledView: true);
            $runnableSql = null;

            if (config('newdebugbar.collection.query_bindings') === 'full') {
                try {
                    // Use the grammar API shared by Laravel 10 and newer.
                    $runnableSql = $event->connection->query()->getGrammar()->substituteBindingsIntoRawSql(
                        $event->sql,
                        $event->connection->prepareBindings($event->bindings),
                    );
                } catch (Throwable) {
                    $runnableSql = null;
                }
            }

            $this->manager()->record('queries', [
                'sql' => $event->sql,
                'bindings' => $event->bindings,
                'runnable_sql' => $runnableSql,
                'duration_ms' => round((float) $event->time, 2),
                'connection' => $event->connectionName,
                'driver' => $event->connection->getDriverName(),
                'type' => $event->readWriteType ?? null,
                ...$location,
            ]);
        });

        $this->listen(TransactionBeginning::class, fn (TransactionBeginning $event) => $this->manager()->recordTransaction([
            'kind' => 'begin',
            'connection' => $event->connectionName,
        ]));
        $this->listen(TransactionCommitted::class, fn (TransactionCommitted $event) => $this->manager()->recordTransaction([
            'kind' => 'commit',
            'connection' => $event->connectionName,
        ]));
        $this->listen(TransactionRolledBack::class, fn (TransactionRolledBack $event) => $this->manager()->recordTransaction([
            'kind' => 'rollback',
            'connection' => $event->connectionName,
        ]));

        $this->listen(RequestSending::class, function (RequestSending $event): void {
            $this->manager()->record('http_client', [
                'phase' => 'sending',
                'request_id' => spl_object_id($event->request),
            ]);
        });

        $this->listen(ResponseReceived::class, function (ResponseReceived $event): void {
            $handlerStats = $event->response->handlerStats();
            $totalTime = $handlerStats['total_time'] ?? null;
            $location = $this->callSites->capture();
            $status = $event->response->status();
            $requestBody = $event->request->body();
            $responseBody = $event->response->body();

            $this->manager()->record('http_client', [
                'phase' => 'completed',
                'request_id' => spl_object_id($event->request),
                'method' => strtoupper($event->request->method()),
                'url' => $this->safeUrl->clean($event->request->url()),
                'status' => $status,
                'reason' => $event->response->reason(),
                'duration_ms' => is_numeric($totalTime) ? round((float) $totalTime * 1_000, 2) : null,
                'failed' => $status >= 400,
                'request' => [
                    'headers' => $event->request->headers(),
                    'body' => $this->httpMessageBody($event->request->headers(), $requestBody),
                    'body_size_bytes' => strlen($requestBody),
                ],
                'response' => [
                    'headers' => $event->response->headers(),
                    'body' => $this->httpMessageBody($event->response->headers(), $responseBody),
                    'body_size_bytes' => strlen($responseBody),
                ],
                ...$location,
            ]);
        });

        $this->listen(ConnectionFailed::class, function (ConnectionFailed $event): void {
            $exception = $event->exception ?? null;
            $location = $this->callSites->capture();
            $requestBody = $event->request->body();
            $this->manager()->record('http_client', [
                'phase' => 'failed',
                'request_id' => spl_object_id($event->request),
                'method' => strtoupper($event->request->method()),
                'url' => $this->safeUrl->clean($event->request->url()),
                'status' => null,
                'duration_ms' => null,
                'failed' => true,
                'exception_class' => $exception instanceof Throwable ? $exception::class : ConnectionException::class,
                'exception_message' => $exception instanceof Throwable ? $exception->getMessage() : 'Connection failed.',
                'request' => [
                    'headers' => $event->request->headers(),
                    'body' => $this->httpMessageBody($event->request->headers(), $requestBody),
                    'body_size_bytes' => strlen($requestBody),
                ],
                'response' => null,
                ...$location,
            ]);
        });

        $this->listen(JobQueued::class, function (JobQueued $event): void {
            $jobQueue = is_object($event->job) ? ($event->job->queue ?? null) : null;
            $jobDelay = is_object($event->job) ? ($event->job->delay ?? null) : null;
            $queue = $event->queue ?? $jobQueue;
            $delay = $this->jobDelaySeconds($event->delay ?? $jobDelay);
            $jobId = $this->queuedJobId($event);
            $communication = $this->communications->inspect($event->job) ?? [];
            $job = (string) ($communication['communication_class'] ?? $this->jobName($event->job));
            $correlationKey = $this->background->key($event->connectionName, $queue, $jobId);
            $status = $delay !== null && $delay > 0 ? 'delayed' : 'queued';
            $facts = [
                'origin_profile_id' => $this->manager()->currentProfileId(),
                'job_id' => $jobId,
                'job' => $job,
                'connection' => $event->connectionName,
                'queue' => $queue,
                'delay_seconds' => $delay,
                ...$communication,
            ];

            $this->manager()->record('queue', [
                'kind' => 'queued',
                'status' => $status,
                'job' => $job,
                'connection' => $event->connectionName,
                'queue' => $queue,
                'job_id' => $jobId,
                'delay_seconds' => $delay,
                'correlation_key' => $correlationKey,
                ...$communication,
                'duration_ms' => 0.0,
            ]);

            if (($communication['communication_type'] ?? null) === 'mail') {
                $this->manager()->record('mail', [
                    'phase' => 'queued',
                    'status' => $status,
                    'source' => $communication['communication_class'],
                    'recipient_count' => $communication['recipient_count'] ?? 0,
                    'attachment_count' => 0,
                    'connection' => $event->connectionName,
                    'queue' => $queue,
                    'job_id' => $jobId,
                    'delay_seconds' => $delay,
                    'correlation_key' => $correlationKey,
                ]);
            }

            if (($communication['communication_type'] ?? null) === 'notification') {
                $channels = $communication['channels'] ?? [];

                foreach ($channels === [] ? [null] : $channels as $channel) {
                    $this->manager()->record('notifications', [
                        'status' => $status,
                        'notification' => $communication['communication_class'],
                        'channel' => $channel,
                        'notifiable_type' => $communication['notifiable_types'][0] ?? null,
                        'notifiable_types' => $communication['notifiable_types'] ?? [],
                        'notifiable_count' => $communication['notifiable_count'] ?? 0,
                        'connection' => $event->connectionName,
                        'queue' => $queue,
                        'job_id' => $jobId,
                        'delay_seconds' => $delay,
                        'correlation_key' => $correlationKey,
                    ]);
                }
            }

            $this->background->recordDispatch($facts);
        });

        $this->listen(JobProcessing::class, function (JobProcessing $event): void {
            $attempt = $event->job->attempts();
            $jobId = $this->workerJobId($event->job);
            $activity = $this->background->markProcessing(
                $event->connectionName,
                $event->job->getQueue(),
                $jobId,
                $attempt,
            );
            $context = [
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job_id' => $jobId,
                'attempt' => $attempt,
                'correlation_key' => $activity['key'] ?? null,
                'origin_profile_id' => $activity['origin_profile_id'] ?? null,
                'communication_type' => $activity['communication_type'] ?? null,
                'communication_class' => $activity['communication_class'] ?? null,
                'channels' => $activity['channels'] ?? [],
                'notifiable_types' => $activity['notifiable_types'] ?? [],
            ];
            $this->rememberJobContext($event->job, $context);
            $this->runtime()->start(
                'queue',
                $event->job->resolveName(),
                $context,
                'queue:'.spl_object_id($event->job),
            );
            $this->manager()->record('queue', [
                'phase' => 'processing',
                'execution_id' => spl_object_id($event->job),
            ]);
        });

        $this->listen(JobProcessed::class, function (JobProcessed $event): void {
            $context = $this->jobContext($event->job);
            $this->forgetJobContext($event->job);
            $released = $event->job->isReleased();
            $status = $released
                ? 'waiting'
                : (($context['communication_type'] ?? null) === null ? 'completed' : 'sent');
            $this->manager()->record('queue', [
                'phase' => 'processed',
                'execution_id' => spl_object_id($event->job),
                'kind' => $released ? 'released' : 'executed',
                'status' => $status,
                'job' => $event->job->resolveName(),
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job_id' => $this->workerJobId($event->job),
                'attempt' => $event->job->attempts(),
                ...$this->correlationContext($context),
            ]);
            $profileId = $this->runtime()->finish(0, 'queue:'.spl_object_id($event->job));
            $profileId ??= is_string($context['origin_profile_id'] ?? null)
                ? $context['origin_profile_id']
                : null;
            $this->background->recordOutcome(
                is_string($context['correlation_key'] ?? null) ? $context['correlation_key'] : null,
                $status,
                $profileId,
                $event->job->attempts(),
            );
        });

        $this->listen(JobExceptionOccurred::class, function (JobExceptionOccurred $event): void {
            $context = $this->jobContext($event->job);
            $this->forgetJobContext($event->job);
            $failed = $event->job instanceof SyncJob || $event->job->hasFailed();
            $this->manager()->record('queue', [
                'phase' => 'failed',
                'execution_id' => spl_object_id($event->job),
                'kind' => 'failed',
                'status' => $failed ? 'failed' : 'waiting',
                'job' => $event->job->resolveName(),
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job_id' => $this->workerJobId($event->job),
                'attempt' => $event->job->attempts(),
                'exception_class' => $event->exception::class,
                'will_retry' => ! $failed,
                ...$this->correlationContext($context),
            ]);
            $profileId = $this->runtime()->fail($event->exception, 'queue:'.spl_object_id($event->job));
            $profileId ??= is_string($context['origin_profile_id'] ?? null)
                ? $context['origin_profile_id']
                : null;
            $this->background->recordOutcome(
                is_string($context['correlation_key'] ?? null) ? $context['correlation_key'] : null,
                $failed ? 'failed' : 'waiting',
                $profileId,
                $event->job->attempts(),
                $event->exception::class,
            );
        });

        $this->listen(MessageSending::class, function (MessageSending $event): void {
            $source = $event->data['__laravel_mailable'] ?? $event->data['__laravel_notification'] ?? null;
            $location = $this->callSites->capture();

            $this->manager()->record('mail', [
                'phase' => 'sending',
                'message_id' => spl_object_id($event->message),
                'source' => is_string($source) ? $source : null,
                'mailer' => $this->configuredMailDriver(),
                'transport' => $this->configuredMailTransport(),
                ...$this->correlationContext($this->currentJobContext()),
                ...$location,
            ]);
        });

        $this->listen(MessageSent::class, function (MessageSent $event): void {
            $message = $event->message;
            $source = $event->data['__laravel_mailable'] ?? $event->data['__laravel_notification'] ?? null;
            $location = $this->callSites->capture();

            $this->manager()->record('mail', [
                'phase' => 'sent',
                'message_id' => spl_object_id($message),
                'status' => 'sent',
                'source' => is_string($source) ? $source : null,
                'mailer' => $this->configuredMailDriver(),
                'transport' => $this->configuredMailTransport(),
                'transport_message_id' => $event->sent->getMessageId(),
                'recipient_count' => count($message->getTo()) + count($message->getCc()) + count($message->getBcc()),
                'attachment_count' => count($message->getAttachments()),
                'has_html' => $message->getHtmlBody() !== null,
                'has_text' => $message->getTextBody() !== null,
                'preview' => $this->mailPreview->capture($message),
                ...$this->correlationContext($this->currentJobContext()),
                ...$location,
            ]);
        });

        $this->listen(NotificationSending::class, function (NotificationSending $event): void {
            $location = $this->callSites->capture();

            $this->manager()->record('notifications', [
                'phase' => 'sending',
                ...$this->notificationDeliveryMetadata(
                    $event->notifiable,
                    $event->notification,
                    (string) $event->channel,
                ),
                ...$location,
            ]);
        });

        $this->listen(NotificationSent::class, function (NotificationSent $event): void {
            $location = $this->callSites->capture();

            $this->manager()->record('notifications', [
                'phase' => 'sent',
                'status' => 'sent',
                ...$this->notificationDeliveryMetadata(
                    $event->notifiable,
                    $event->notification,
                    (string) $event->channel,
                    terminal: true,
                ),
                ...$this->notificationResponse($event->response),
                ...$this->correlationContext($this->currentJobContext()),
                ...$location,
            ]);
        });

        $this->listen(NotificationFailed::class, function (NotificationFailed $event): void {
            $location = $this->callSites->capture();

            $this->manager()->record('notifications', [
                'phase' => 'failed',
                'status' => 'failed',
                ...$this->notificationDeliveryMetadata(
                    $event->notifiable,
                    $event->notification,
                    (string) $event->channel,
                    terminal: true,
                ),
                ...$this->notificationFailure($event->data),
                ...$this->correlationContext($this->currentJobContext()),
                ...$location,
            ]);
        });

        $this->listen(CommandExecuted::class, function (CommandExecuted $event): void {
            $callsite = $this->callSites->capture()['callsite'];
            $command = strtoupper((string) $event->command);
            $keys = $this->redisKeys($command, (array) $event->parameters);

            $this->manager()->record('redis', [
                'command' => $command,
                'connection' => $event->connectionName,
                'duration_ms' => round((float) $event->time, 2),
                ...$keys,
                'failed' => false,
                'callsite' => $callsite,
            ]);
        });

        $this->listen(CommandFailed::class, function (CommandFailed $event): void {
            $callsite = $this->callSites->capture()['callsite'];
            $command = strtoupper((string) $event->command);
            $keys = $this->redisKeys($command, (array) $event->parameters);

            $this->manager()->record('redis', [
                'command' => $command,
                'connection' => $event->connectionName,
                'duration_ms' => 0.0,
                ...$keys,
                'failed' => true,
                'exception_class' => $event->exception::class,
                'callsite' => $callsite,
            ]);
        });

        $this->listen('eloquent.*', function (string $name, array $payload): void {
            $model = $payload[0] ?? null;
            $event = str($name)->between('eloquent.', ':')->toString();

            if (! $model instanceof Model || in_array($event, ['booting', 'booted'], true)) {
                return;
            }

            $location = $this->callSites->capture(includeCompiledView: true);
            $operation = $this->modelOperation($model, $event);

            $this->manager()->record('models', [
                'event' => $event,
                'model' => $model::class,
                'connection' => $model->getConnectionName(),
                'table' => $model->getTable(),
                'key_name' => $model->getKeyName(),
                'key' => $this->modelKey($model),
                'operation_id' => $operation['id'] ?? null,
                'operation' => $operation['type'] ?? null,
                ...$this->modelChanges($model, $event),
                'callsite' => $location['callsite'],
            ]);

            $this->finishModelOperation($model, $event, $operation);
        });

        $this->listenForCacheStart(RetrievingKey::class, 'read');
        $this->listenForCacheBatchStart(RetrievingManyKeys::class, 'read');
        $this->listenForCacheStart(WritingKey::class, 'write');
        $this->listenForCacheBatchStart(WritingManyKeys::class, 'write');
        $this->listenForCacheStart(ForgettingKey::class, 'forget');
        $this->listenForCacheFlushStart();

        $this->listenForCacheEvent(CacheHit::class, 'hit', 'read');
        $this->listenForCacheEvent(CacheMissed::class, 'miss', 'read');
        $this->listenForCacheEvent(KeyWritten::class, 'write', 'write');
        $this->listenForCacheEvent(KeyForgotten::class, 'forget', 'forget');
        $this->listenForCacheEvent(KeyWriteFailed::class, 'write_failed', 'write', failed: true);
        $this->listenForCacheEvent(KeyForgetFailed::class, 'forget_failed', 'forget', failed: true);
        $this->listenForCacheFlushEvent(CacheFlushed::class, 'flush');
        $this->listenForCacheFlushEvent(CacheFlushFailed::class, 'flush_failed', failed: true);
        $this->listenForCacheFailover();

        $this->listen('composing: *', function (string $name, array $payload): void {
            $view = $payload[0] ?? null;
            $viewName = str($name)->after('composing: ')->toString();
            $path = is_object($view) && method_exists($view, 'getPath') ? $view->getPath() : null;
            $data = is_object($view) && method_exists($view, 'getData') ? $view->getData() : [];

            $this->manager()->record('views', [
                'name' => $viewName,
                'source' => is_string($path) ? $this->callSites->templateLocation($path) : null,
                'composers' => $this->listenerDetails('composing: '.$viewName),
                'data' => is_array($data) ? $this->normalizeViewData($data) : [],
                'timing' => 'composition_marker',
            ]);
        });

        $this->listen(MessageLogged::class, function (MessageLogged $event): void {
            $location = $this->callSites->capture();
            $context = $event->context;
            $exception = $context['exception'] ?? null;
            $relatedException = null;

            if ($exception instanceof Throwable) {
                $normalized = $this->manager()->recordException($exception);
                $relatedException = array_intersect_key($normalized, array_flip(['class', 'message', 'file', 'line']));
                unset($context['exception']);
            }

            $this->manager()->record('logs', [
                'level' => $event->level,
                'message' => $event->message,
                'channel' => $this->logChannels->take($event->level, $event->message, $event->context),
                'context' => $context,
                'related_exception' => $relatedException,
                'occurred_at' => now()->format('Y-m-d\TH:i:s.vP'),
                ...$location,
            ]);
        });

        $this->listen('*', function (string $name, array $payload): void {
            if ($this->shouldIgnoreGeneralEvent($name)) {
                return;
            }

            $location = $this->callSites->capture();

            $this->manager()->record('events', [
                'name' => $name,
                'listeners' => $this->listenerDetails($name),
                'broadcast' => ($payload[0] ?? null) instanceof ShouldBroadcast,
                'payload_types' => array_map(
                    fn (mixed $item): string => is_object($item) ? $item::class : get_debug_type($item),
                    $payload,
                ),
                'payload_shape' => $this->eventPayloadShape($payload),
                ...$location,
            ]);
        });
    }

    private function listenForCacheStart(string $eventClass, string $operation): void
    {
        if (! class_exists($eventClass)) {
            return;
        }

        $this->listen($eventClass, function (object $event) use ($operation): void {
            $this->rememberCacheOperation(
                operation: $operation,
                store: $this->cacheEventStore($event),
                key: $event->key ?? null,
                context: $this->cacheStartContext(
                    durationScope: 'operation',
                    value: property_exists($event, 'value') ? $event->value : null,
                    hasValue: property_exists($event, 'value'),
                    seconds: property_exists($event, 'seconds') ? $event->seconds : null,
                ),
            );
        });
    }

    private function listenForCacheBatchStart(string $eventClass, string $operation): void
    {
        if (! class_exists($eventClass)) {
            return;
        }

        $this->listen($eventClass, function (object $event) use ($operation): void {
            $keys = is_array($event->keys ?? null) ? array_values($event->keys) : [];

            if ($keys === []) {
                return;
            }

            $location = $this->callSites->capture();
            $startedAt = hrtime(true);
            $durationId = 'cache-batch-'.(++$this->cacheOperationSequence);
            $values = is_array($event->values ?? null) ? $event->values : [];

            foreach ($keys as $index => $key) {
                $valueExists = $operation === 'write'
                    && (array_key_exists($key, $values) || array_key_exists($index, $values));
                $value = array_key_exists($key, $values) ? $values[$key] : ($values[$index] ?? null);

                $this->rememberCacheOperation(
                    operation: $operation,
                    store: $this->cacheEventStore($event),
                    key: $key,
                    context: [
                        'started_at_ns' => $startedAt,
                        'duration_scope' => 'batch',
                        'duration_id' => $durationId,
                        'batch_size' => count($keys),
                        'seconds' => property_exists($event, 'seconds') ? $event->seconds : null,
                        ...$this->cacheValue($value, $valueExists),
                        ...$location,
                    ],
                );
            }
        });
    }

    private function listenForCacheFlushStart(): void
    {
        if (! class_exists(CacheFlushing::class)) {
            return;
        }

        $this->listen(CacheFlushing::class, function (object $event): void {
            $this->rememberCacheOperation(
                operation: 'flush',
                store: $this->cacheEventStore($event),
                key: null,
                context: $this->cacheStartContext('operation'),
            );
        });
    }

    private function listenForCacheEvent(
        string $eventClass,
        string $operation,
        string $pendingOperation,
        bool $failed = false,
    ): void {
        if (! class_exists($eventClass)) {
            return;
        }

        $this->listen($eventClass, function (object $event) use ($operation, $pendingOperation, $failed): void {
            $storeName = $this->cacheEventStore($event);
            $key = $event->key ?? null;
            $context = $this->takeCacheOperation($pendingOperation, $storeName, $key);
            $hasValue = property_exists($event, 'value');
            $capturedValue = $context === null ? [] : $this->cacheValueFromContext($context);

            if ($capturedValue === []) {
                $capturedValue = $this->cacheValue($hasValue ? $event->value : null, $hasValue);
            }
            $location = $context === null ? $this->callSites->capture() : [
                'callsite' => $context['callsite'] ?? null,
                'stack' => $context['stack'] ?? [],
            ];

            $this->manager()->record('cache', [
                'operation' => $operation,
                ...$this->cacheKey($key),
                'store' => $storeName,
                'driver' => $this->cacheDriver($storeName),
                ...$this->cacheTags(is_array($event->tags ?? null) ? $event->tags : []),
                'seconds' => $context['seconds'] ?? (property_exists($event, 'seconds') ? $event->seconds : null),
                ...$capturedValue,
                ...$this->cacheTiming($context),
                ...$location,
                'failed' => $failed,
            ]);

            if (! $failed && $this->cacheStoreUsesRedis($storeName)) {
                $this->manager()->excludeRedisCacheOperation($operation);
            }
        });
    }

    private function listenForCacheFlushEvent(string $eventClass, string $operation, bool $failed = false): void
    {
        if (! class_exists($eventClass)) {
            return;
        }

        $this->listen($eventClass, function (object $event) use ($operation, $failed): void {
            $storeName = $this->cacheEventStore($event);
            $context = $this->takeCacheOperation('flush', $storeName, null);
            $location = $context === null ? $this->callSites->capture() : [
                'callsite' => $context['callsite'] ?? null,
                'stack' => $context['stack'] ?? [],
            ];

            $this->manager()->record('cache', [
                'operation' => $operation,
                ...$this->cacheKey(null),
                'store' => $storeName,
                'driver' => $this->cacheDriver($storeName),
                ...$this->cacheTags(is_array($event->tags ?? null) ? $event->tags : []),
                'seconds' => null,
                ...$this->cacheTiming($context),
                ...$location,
                'failed' => $failed,
            ]);

            if (! $failed && $this->cacheStoreUsesRedis($storeName)) {
                $this->manager()->excludeRedisCacheOperation('flush');
            }
        });
    }

    private function listenForCacheFailover(): void
    {
        if (! class_exists(CacheFailedOver::class)) {
            return;
        }

        $this->listen(CacheFailedOver::class, function (object $event): void {
            $exception = $event->exception ?? null;
            $storeName = $this->cacheEventStore($event);

            $this->manager()->record('cache', [
                'operation' => 'failover',
                ...$this->cacheKey(null),
                'store' => $storeName,
                'driver' => $this->cacheDriver($storeName),
                ...$this->cacheTags([]),
                'seconds' => null,
                'duration_ms' => null,
                'duration_scope' => null,
                'duration_id' => null,
                'callsite' => $exception instanceof Throwable ? $this->callSites->fromThrowable($exception) : null,
                'stack' => [],
                'failed' => true,
                'exception_class' => $exception instanceof Throwable ? $exception::class : null,
                'exception_message' => $exception instanceof Throwable ? $exception->getMessage() : null,
            ]);
        });
    }

    /** @return array<string, mixed> */
    private function cacheStartContext(
        string $durationScope,
        mixed $value = null,
        bool $hasValue = false,
        mixed $seconds = null,
    ): array {
        return [
            'started_at_ns' => hrtime(true),
            'duration_scope' => $durationScope,
            'duration_id' => 'cache-operation-'.(++$this->cacheOperationSequence),
            'batch_size' => 1,
            'seconds' => $seconds,
            ...$this->cacheValue($value, $hasValue),
            ...$this->callSites->capture(),
        ];
    }

    /** @param array<string, mixed> $context */
    private function rememberCacheOperation(string $operation, ?string $store, mixed $key, array $context): void
    {
        while ($this->pendingCacheOperationCount >= self::MAX_PENDING_CACHE_OPERATIONS) {
            $identity = array_key_first($this->pendingCacheOperations);

            if ($identity === null) {
                $this->pendingCacheOperationCount = 0;

                break;
            }

            array_shift($this->pendingCacheOperations[$identity]);
            $this->pendingCacheOperationCount--;

            if ($this->pendingCacheOperations[$identity] === []) {
                unset($this->pendingCacheOperations[$identity]);
            }
        }

        $identity = $this->cacheOperationIdentity($operation, $store, $key);
        $this->pendingCacheOperations[$identity][] = $context;
        $this->pendingCacheOperationCount++;
    }

    /** @return array<string, mixed>|null */
    private function takeCacheOperation(string $operation, ?string $store, mixed $key): ?array
    {
        $identity = $this->cacheOperationIdentity($operation, $store, $key);

        if (! isset($this->pendingCacheOperations[$identity])) {
            return null;
        }

        $context = array_pop($this->pendingCacheOperations[$identity]);
        $this->pendingCacheOperationCount--;

        if ($this->pendingCacheOperations[$identity] === []) {
            unset($this->pendingCacheOperations[$identity]);
        }

        return is_array($context) ? $context : null;
    }

    private function cacheOperationIdentity(string $operation, ?string $store, mixed $key): string
    {
        $keyIdentity = $key === null ? 'no-key' : $this->redactor->cleanKey($key);

        return $operation.'|'.($store ?? 'default').'|'.$keyIdentity;
    }

    /** @param array<string, mixed>|null $context @return array<string, mixed> */
    private function cacheTiming(?array $context): array
    {
        if ($context === null || ! isset($context['started_at_ns']) || ! is_int($context['started_at_ns'])) {
            return [
                'duration_ms' => null,
                'duration_scope' => null,
                'duration_id' => null,
                'batch_size' => null,
            ];
        }

        return [
            'duration_ms' => round(max(0, hrtime(true) - $context['started_at_ns']) / 1_000_000, 3),
            'duration_scope' => $context['duration_scope'] ?? 'operation',
            'duration_id' => $context['duration_id'] ?? null,
            'batch_size' => $context['batch_size'] ?? 1,
        ];
    }

    /** @return array<string, mixed> */
    private function cacheValue(mixed $value, bool $hasValue): array
    {
        if (! $hasValue) {
            return [];
        }

        return ['value' => $this->redactor->clean($value)];
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function cacheValueFromContext(array $context): array
    {
        return array_key_exists('value', $context) ? ['value' => $context['value']] : [];
    }

    /** @return array{key_hash: string|null, key: string|null, key_policy: string} */
    private function cacheKey(mixed $key): array
    {
        if ($key === null) {
            return ['key_hash' => null, 'key' => null, 'key_policy' => $this->keyPolicy()];
        }

        return [
            'key_hash' => $this->redactor->cleanKey($key),
            'key' => $this->keyPolicy() === 'full' ? $this->redactor->cleanKey($key, 'full') : null,
            'key_policy' => $this->keyPolicy(),
        ];
    }

    private function cacheEventStore(object $event): ?string
    {
        return is_string($event->storeName ?? null) && $event->storeName !== '' ? $event->storeName : null;
    }

    /** @param class-string|string $event */
    private function listen(string $event, Closure $listener): void
    {
        $this->events->listen($event, function (...$arguments) use ($listener): void {
            try {
                $listener(...$arguments);
            } catch (Throwable) {
                // Debug collection must never interrupt the host application.
            }
        });
    }

    private function modelKey(Model $model): mixed
    {
        $attributes = $model->getAttributes();
        $key = $model->getKeyName();

        return array_key_exists($key, $attributes) ? $attributes[$key] : null;
    }

    /** @return array{id: int, type: string}|null */
    private function modelOperation(Model $model, string $event): ?array
    {
        $operations = $this->modelOperations ??= new WeakMap;
        $active = $operations[$model] ?? null;
        $startedType = match ($event) {
            'creating' => 'created',
            'updating' => 'updated',
            'deleting' => 'deleted',
            'restoring' => 'restored',
            'forceDeleting' => 'forceDeleted',
            default => null,
        };

        if ($startedType !== null) {
            $preserveOuterOperation = is_array($active) && (
                ($active['type'] === 'restored' && $startedType === 'updated')
                || ($active['type'] === 'forceDeleted' && $startedType === 'deleted')
            );

            if (! $preserveOuterOperation) {
                $active = [
                    'id' => ++$this->nextModelOperationId,
                    'type' => $startedType,
                ];
                $operations[$model] = $active;
            }
        }

        if ($event === 'trashed' && is_array($active) && $active['type'] === 'deleted') {
            $active['type'] = 'trashed';
            $operations[$model] = $active;
        }

        return is_array($active) ? $active : null;
    }

    /** @param array{id: int, type: string}|null $operation */
    private function finishModelOperation(Model $model, string $event, ?array $operation): void
    {
        if ($operation === null || $this->modelOperations === null) {
            return;
        }

        $finished = match ($operation['type']) {
            'created', 'updated' => $event === 'saved',
            'deleted', 'trashed' => $event === 'deleted',
            'restored' => $event === 'restored',
            'forceDeleted' => $event === 'forceDeleted',
            default => false,
        };

        if ($finished) {
            unset($this->modelOperations[$model]);
        }
    }

    /** @return array{change_attribute_count: int, changes: array<string, mixed>, changes_truncated: bool} */
    private function modelChanges(Model $model, string $event): array
    {
        if (! in_array($event, ['created', 'updated', 'deleted', 'restored', 'forceDeleted', 'trashed'], true)) {
            return [
                'change_attribute_count' => 0,
                'changes' => [],
                'changes_truncated' => false,
            ];
        }

        $changes = $model->getChanges();

        return [
            'change_attribute_count' => count($changes),
            'changes' => array_slice($changes, 0, self::MAX_MODEL_CHANGED_ATTRIBUTES, true),
            'changes_truncated' => count($changes) > self::MAX_MODEL_CHANGED_ATTRIBUTES,
        ];
    }

    /** @return array<string, mixed> */
    private function notificationDeliveryMetadata(
        mixed $notifiable,
        object $notification,
        string $channel,
        bool $terminal = false,
    ): array {
        $notificationId = isset($notification->id) && is_scalar($notification->id)
            ? (string) $notification->id
            : null;
        $notificationObjectId = spl_object_id($notification);
        $notifiableObjectId = is_object($notifiable) ? spl_object_id($notifiable) : 0;
        $notifiableType = is_object($notifiable) ? $notifiable::class : get_debug_type($notifiable);
        $notifiableId = $this->notificationNotifiableId($notifiable);
        $groupIdentity = implode('|', [
            $notification::class,
            $notificationId === null ? 'object:'.$notificationObjectId : 'id:'.$notificationId,
            $notifiableType,
            $notifiableId === null ? 'object:'.$notifiableObjectId : 'id:'.(string) $notifiableId,
        ]);
        $groupId = substr(hash('sha256', $groupIdentity), 0, 24);
        $attemptId = $groupId.'|'.$channel;

        return [
            'attempt_id' => $attemptId,
            'group_id' => $groupId,
            'notification_id' => $notificationId,
            'notification_object_id' => $notificationObjectId,
            'notification' => $notification::class,
            'notification_source' => $this->notificationClassLocation($notification),
            'notification_data' => $this->notificationPublicData($notification),
            'locale' => isset($notification->locale) && is_string($notification->locale)
                ? $notification->locale
                : null,
            'queueable' => $notification instanceof ShouldQueue,
            'queue_connection' => isset($notification->connection) && is_scalar($notification->connection)
                ? (string) $notification->connection
                : null,
            'queue_name' => isset($notification->queue) && is_scalar($notification->queue)
                ? (string) $notification->queue
                : null,
            'channel' => $channel,
            'notifiable_type' => $notifiableType,
            'notifiable_id' => $notifiableId,
            'notifiable_name' => $this->objectDisplayName($notifiable),
            'notifiable_object_id' => $notifiableObjectId,
            'destination' => $this->notificationDestinationForAttempt(
                $attemptId,
                $notifiable,
                $notification,
                $channel,
                $terminal,
            ),
            'routes' => $notifiable instanceof AnonymousNotifiable ? $notifiable->routes : [],
        ];
    }

    private function notificationNotifiableId(mixed $notifiable): mixed
    {
        if (! is_object($notifiable) || ! method_exists($notifiable, 'getKey')) {
            return null;
        }

        try {
            $key = $notifiable->getKey();

            return is_scalar($key) ? $key : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param list<string> $attributes */
    private function objectDisplayName(
        mixed $value,
        array $attributes = ['name', 'full_name', 'display_name'],
    ): ?string {
        if (! is_object($value)) {
            return null;
        }

        $values = $value instanceof Model
            ? $value->getAttributes()
            : get_object_vars($value);

        foreach ($attributes as $attribute) {
            $value = $values[$attribute] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function notificationDestinationForAttempt(
        string $attemptId,
        mixed $notifiable,
        object $notification,
        string $channel,
        bool $terminal,
    ): mixed {
        $destination = array_key_exists($attemptId, $this->notificationDestinations)
            ? $this->notificationDestinations[$attemptId]
            : $this->notificationDestination($notifiable, $notification, $channel);

        if ($terminal) {
            unset($this->notificationDestinations[$attemptId]);

            return $destination;
        }

        if (count($this->notificationDestinations) >= self::MAX_PENDING_NOTIFICATION_DESTINATIONS) {
            array_shift($this->notificationDestinations);
        }

        $this->notificationDestinations[$attemptId] = $destination;

        return $destination;
    }

    private function notificationDestination(mixed $notifiable, object $notification, string $channel): mixed
    {
        if (! is_object($notifiable)) {
            return null;
        }

        if ($channel === 'database') {
            return [
                'type' => $notifiable::class,
                'id' => $this->notificationNotifiableId($notifiable),
                'name' => $this->objectDisplayName($notifiable),
            ];
        }

        if (! method_exists($notifiable, 'routeNotificationFor')) {
            return null;
        }

        try {
            $destination = $notifiable instanceof AnonymousNotifiable
                ? $notifiable->routeNotificationFor($channel)
                : $notifiable->routeNotificationFor($channel, $notification);

            return $this->normalizeNotificationDataValue($destination);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private function notificationPublicData(object $notification): array
    {
        $data = get_object_vars($notification);
        $frameworkProperties = ['id', 'locale'];

        if (in_array(Queueable::class, class_uses_recursive($notification), true)) {
            $frameworkProperties = [...$frameworkProperties, ...self::FRAMEWORK_NOTIFICATION_PROPERTIES];
        }

        foreach ($frameworkProperties as $property) {
            unset($data[$property]);
        }

        foreach ($data as $key => $value) {
            $data[$key] = $this->normalizeNotificationDataValue($value);
        }

        return $data;
    }

    private function normalizeNotificationDataValue(mixed $value, int $depth = 0): mixed
    {
        if ($value instanceof Model) {
            return [
                'type' => $value::class,
                'id' => $this->modelKey($value),
            ];
        }

        if ($depth >= 5 || ! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizeNotificationDataValue($item, $depth + 1);
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function notificationResponse(mixed $response): array
    {
        if ($response instanceof SentMessage) {
            try {
                $messageId = $response->getMessageId();
            } catch (Throwable) {
                $messageId = null;
            }

            return [
                'response_type' => $response::class,
                'response' => $messageId === null ? null : ['message_id' => $messageId],
                'mail_message_id' => is_string($messageId) ? $messageId : null,
            ];
        }

        if ($response instanceof Model) {
            return [
                'response_type' => $response::class,
                'response' => [
                    'type' => $response::class,
                    'id' => $this->modelKey($response),
                    'attributes' => $response->getAttributes(),
                ],
                'mail_message_id' => null,
            ];
        }

        return [
            'response_type' => $response === null ? null : get_debug_type($response),
            'response' => $this->normalizeNotificationDataValue($response),
            'mail_message_id' => null,
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function notificationFailure(array $data): array
    {
        $exception = $data['exception'] ?? null;
        unset($data['exception']);

        foreach ($data as $key => $value) {
            $data[$key] = $this->normalizeNotificationDataValue($value);
        }

        return [
            'failure_data' => $data,
            'exception_class' => $exception instanceof Throwable ? $exception::class : null,
            'exception_message' => $exception instanceof Throwable ? $exception->getMessage() : null,
            'exception_location' => $exception instanceof Throwable ? $this->callSites->fromThrowable($exception) : null,
        ];
    }

    /** @return array{file: string, line: int}|null */
    private function notificationClassLocation(object $notification): ?array
    {
        try {
            $reflection = new ReflectionClass($notification);
            $file = $reflection->getFileName();

            return is_string($file) ? $this->callSites->location($file, $reflection->getStartLine()) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<array-key, mixed> $data @return array<array-key, mixed> */
    private function normalizeViewData(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = $this->normalizeViewDataValue($value);
        }

        return $data;
    }

    private function normalizeViewDataValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth >= 5) {
            return $value;
        }

        if ($value instanceof Arrayable) {
            try {
                $value = $value->toArray();
            } catch (Throwable) {
                return $value;
            }
        }

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizeViewDataValue($item, $depth + 1);
        }

        return $value;
    }

    private function manager(): ProfileManager
    {
        return $this->container->make(ProfileManager::class);
    }

    private function runtime(): RuntimeProfiler
    {
        return $this->container->make(RuntimeProfiler::class);
    }

    /** @param array<string, mixed> $context */
    private function rememberJobContext(object $job, array $context): void
    {
        $id = spl_object_id($job);
        $this->activeJobContexts[$id] = $context;
        $this->activeJobOrder = array_values(array_filter(
            $this->activeJobOrder,
            static fn (int $activeId): bool => $activeId !== $id,
        ));
        $this->activeJobOrder[] = $id;
    }

    /** @return array<string, mixed> */
    private function jobContext(object $job): array
    {
        $context = $this->manager()->runtimeProfileContext();

        return $context !== [] ? $context : ($this->activeJobContexts[spl_object_id($job)] ?? []);
    }

    /** @return array<string, mixed> */
    private function currentJobContext(): array
    {
        $context = $this->manager()->runtimeProfileContext();

        if ($context !== []) {
            return $context;
        }

        if ($this->activeJobOrder === []) {
            return [];
        }

        $id = $this->activeJobOrder[array_key_last($this->activeJobOrder)];

        return $this->activeJobContexts[$id] ?? [];
    }

    private function forgetJobContext(object $job): void
    {
        $id = spl_object_id($job);
        unset($this->activeJobContexts[$id]);
        $this->activeJobOrder = array_values(array_filter(
            $this->activeJobOrder,
            static fn (int $activeId): bool => $activeId !== $id,
        ));
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function correlationContext(array $context): array
    {
        return array_intersect_key($context, array_flip([
            'correlation_key',
            'origin_profile_id',
            'communication_type',
            'communication_class',
            'channels',
            'notifiable_types',
        ]));
    }

    private function queuedJobId(JobQueued $event): string|int|null
    {
        try {
            $uuid = $event->payload()['uuid'] ?? null;

            if (is_string($uuid) && $uuid !== '') {
                return $uuid;
            }
        } catch (Throwable) {
            // A malformed payload must not prevent normal queue dispatch.
        }

        return is_string($event->id) || is_int($event->id) ? $event->id : null;
    }

    private function workerJobId(object $job): string|int|null
    {
        try {
            $uuid = method_exists($job, 'uuid') ? $job->uuid() : null;

            if (is_string($uuid) && $uuid !== '') {
                return $uuid;
            }
        } catch (Throwable) {
            // Fall back to the provider identifier for non-standard drivers.
        }

        $id = method_exists($job, 'getJobId') ? $job->getJobId() : null;

        return is_string($id) || is_int($id) ? $id : null;
    }

    private function jobDelaySeconds(mixed $delay): ?int
    {
        if (is_numeric($delay)) {
            return max(0, (int) $delay);
        }

        if ($delay instanceof \DateTimeInterface) {
            return max(0, $delay->getTimestamp() - now()->getTimestamp());
        }

        return null;
    }

    private function shouldIgnoreGeneralEvent(string $name): bool
    {
        if (in_array($name, self::EXCLUDED_GENERAL_EVENTS, true)) {
            return true;
        }

        return str_starts_with($name, 'eloquent.')
            || str_starts_with($name, 'composing: ')
            || str_starts_with($name, 'creating: ')
            || str_starts_with($name, 'bootstrapped: ')
            || str_starts_with($name, 'bootstrapping: ');
    }

    private function jobName(mixed $job): string
    {
        if (is_object($job)) {
            return $job::class;
        }

        return is_string($job) && $job !== '' ? $job : get_debug_type($job);
    }

    private function isLongRunningCommand(string $command): bool
    {
        return in_array($command, [
            'horizon',
            'mcp:inspector',
            'mcp:start',
            'octane:start',
            'queue:listen',
            'queue:work',
            'reverb:start',
            'schedule:work',
            'serve',
        ], true);
    }

    private function authorizationArgumentType(mixed $argument): string
    {
        if (is_object($argument)) {
            return $argument::class;
        }

        if (
            is_string($argument)
            && (class_exists($argument) || interface_exists($argument) || enum_exists($argument))
        ) {
            return $argument;
        }

        return get_debug_type($argument);
    }

    /** @return array{type: class-string, identifier_name: string|null, identifier: scalar|null, name: string|null}|null */
    private function authorizationUser(mixed $user): ?array
    {
        if (! is_object($user)) {
            return null;
        }

        $identifierName = null;
        $identifier = null;

        if ($user instanceof Authenticatable) {
            try {
                $identifierName = $user->getAuthIdentifierName();
                $identifier = $user->getAuthIdentifier();
            } catch (Throwable) {
                $identifierName = null;
                $identifier = null;
            }
        }

        return [
            'type' => $user::class,
            'identifier_name' => is_string($identifierName) && $identifierName !== '' ? $identifierName : null,
            'identifier' => is_scalar($identifier) ? $identifier : null,
            'name' => $this->objectDisplayName($user),
        ];
    }

    /** @return array{position: int, kind: string, type: string, identifier?: scalar|null, name?: string|null, value?: mixed} */
    private function authorizationArgument(mixed $argument, int $position): array
    {
        $type = $this->authorizationArgumentType($argument);

        if ($argument instanceof Model) {
            try {
                $routeKeyName = $argument->getRouteKeyName();
                $routeKey = $argument->getRouteKey();
            } catch (Throwable) {
                $routeKeyName = null;
                $routeKey = null;
            }

            return [
                'position' => $position,
                'kind' => 'model',
                'type' => $type,
                'identifier' => $this->modelKey($argument),
                'route_key_name' => is_string($routeKeyName) && $routeKeyName !== '' ? $routeKeyName : null,
                'route_key' => is_scalar($routeKey) ? $routeKey : null,
                'name' => $this->objectDisplayName($argument, ['name', 'full_name', 'display_name', 'title', 'label']),
            ];
        }

        if (is_string($argument) && (class_exists($argument) || interface_exists($argument) || enum_exists($argument))) {
            return [
                'position' => $position,
                'kind' => 'class',
                'type' => $type,
            ];
        }

        if (is_object($argument) && ! $argument instanceof UnitEnum) {
            return [
                'position' => $position,
                'kind' => 'object',
                'type' => $type,
            ];
        }

        return [
            'position' => $position,
            'kind' => 'value',
            'type' => $type,
            'value' => $this->redactor->clean($argument),
        ];
    }

    /** @return array{legacy: string, kind: 'policy'|'callback', name: string, source: array{file: string, line: int}|null} */
    private function authorizationHandler(GateEvaluated $event): array
    {
        $fallback = [
            'legacy' => 'callback',
            'kind' => 'callback',
            'name' => 'Gate callback',
            'source' => null,
        ];

        try {
            $gate = $this->container->make(GateContract::class);
            $argument = $event->arguments[0] ?? null;

            if (is_object($argument) || is_string($argument)) {
                $policy = $gate->getPolicyFor($argument);

                if (is_object($policy) || is_string($policy)) {
                    $class = is_object($policy) ? $policy::class : $policy;
                    $method = str_contains($event->ability, '-') ? Str::camel($event->ability) : $event->ability;

                    if (is_callable([$policy, $method])) {
                        $name = $class.'@'.$method;

                        return [
                            'legacy' => $name,
                            'kind' => 'policy',
                            'name' => $name,
                            'source' => $this->methodLocation($class, $method),
                        ];
                    }
                }
            }

            $abilities = $gate->abilities();
            $detail = $this->listenerDetail($abilities[$event->ability] ?? null);

            if ($detail !== null) {
                return [
                    ...$fallback,
                    'name' => $detail['name'] === 'Closure' ? 'Gate callback' : $detail['name'],
                    'source' => $detail['source'] ?? null,
                ];
            }
        } catch (Throwable) {
            // Fall through to the honest generic callback label.
        }

        return $fallback;
    }

    /** @return list<array<string, mixed>> */
    private function listenerDetails(string $event): array
    {
        if (! method_exists($this->events, 'getRawListeners')) {
            return [];
        }

        $raw = $this->events->getRawListeners();
        $listeners = [];

        foreach ((array) ($raw[$event] ?? []) as $listener) {
            $detail = $this->listenerDetail($listener);

            if ($detail !== null) {
                $signature = json_encode([
                    $detail['name'],
                    $detail['source'] ?? null,
                    $detail['queued'],
                ]);
                $key = is_string($signature) ? $signature : $detail['name'];

                if (isset($listeners[$key])) {
                    $listeners[$key]['registrations']++;

                    continue;
                }

                $listeners[$key] = [
                    ...$detail,
                    'registrations' => 1,
                    'outcome' => $detail['queued'] ? 'queued' : 'completed',
                ];
            }
        }

        return array_slice(array_values($listeners), 0, 25);
    }

    /** @return array<string, mixed>|null */
    private function listenerDetail(mixed $listener): ?array
    {
        if (is_string($listener)) {
            if (str_starts_with($listener, self::class) || str_starts_with($listener, 'Illuminate\\')) {
                return null;
            }

            [$class, $method] = str_contains($listener, '@')
                ? explode('@', $listener, 2)
                : [$listener, method_exists($listener, 'handle') ? 'handle' : '__invoke'];

            return [
                'name' => $class.'@'.$method,
                'source' => $this->methodLocation($class, $method),
                'queued' => is_a($class, ShouldQueue::class, true),
            ];
        }

        if (is_array($listener) && count($listener) >= 2) {
            $class = is_object($listener[0]) ? $listener[0]::class : (string) $listener[0];
            $method = (string) $listener[1];

            return [
                'name' => $class.'@'.$method,
                'source' => $this->methodLocation($class, $method),
                'queued' => is_a($class, ShouldQueue::class, true),
            ];
        }

        if ($listener instanceof Closure) {
            $reflection = new ReflectionFunction($listener);
            $file = $reflection->getFileName();
            $source = is_string($file) ? $this->callSites->location($file, $reflection->getStartLine()) : null;

            return $source === null ? null : ['name' => 'Closure', 'source' => $source, 'queued' => false];
        }

        if (is_object($listener)) {
            return [
                'name' => $listener::class,
                'source' => $this->methodLocation($listener::class, '__invoke'),
                'queued' => $listener instanceof ShouldQueue,
            ];
        }

        return null;
    }

    /** @param list<mixed> $payload @return list<array<string, mixed>> */
    private function eventPayloadShape(array $payload): array
    {
        $shape = [];

        foreach (array_slice($payload, 0, 10) as $index => $item) {
            $fields = match (true) {
                is_array($item) => array_keys($item),
                is_object($item) => array_keys(get_object_vars($item)),
                default => [],
            };
            $fieldCount = count($fields);

            $shape[] = [
                'position' => $index + 1,
                'type' => is_object($item) ? $item::class : get_debug_type($item),
                'fields' => array_values(array_map('strval', array_slice($fields, 0, 25))),
                'field_count' => $fieldCount,
                'truncated' => $fieldCount > 25,
            ];
        }

        return $shape;
    }

    /** @return array{file: string, line: int}|null */
    private function methodLocation(string $class, string $method): ?array
    {
        try {
            $reflection = new ReflectionMethod($class, $method);
            $file = $reflection->getFileName();

            return is_string($file) ? $this->callSites->location($file, $reflection->getStartLine()) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function httpMessageBody(array $headers, string $body): mixed
    {
        if ($body === '') {
            return null;
        }

        $contentType = '';

        foreach ($headers as $name => $values) {
            if (strcasecmp((string) $name, 'Content-Type') === 0) {
                $contentType = strtolower(implode('; ', (array) $values));

                break;
            }
        }

        if (str_contains($contentType, 'multipart/form-data')) {
            return '[multipart body omitted]';
        }

        if (str_starts_with($contentType, 'image/')
            || str_starts_with($contentType, 'audio/')
            || str_starts_with($contentType, 'video/')
            || str_contains($contentType, 'application/octet-stream')) {
            return '[binary body omitted]';
        }

        if (str_contains($contentType, 'json')) {
            $decoded = json_decode($body, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($body, $parameters);

            return $parameters;
        }

        return $body;
    }

    /** @param list<mixed> $parameters @return array{key_count: int, key_hashes: list<string>, keys: list<string>, key_policy: string} */
    private function redisKeys(string $command, array $parameters): array
    {
        $multiKeyCommands = ['DEL', 'UNLINK', 'EXISTS', 'MGET', 'PFCOUNT'];
        $firstKeyCommands = [
            'APPEND', 'BITCOUNT', 'DECR', 'DECRBY', 'EXPIRE', 'EXPIREAT', 'GET', 'GETDEL', 'GETEX',
            'GETSET', 'HDEL', 'HEXISTS', 'HGET', 'HGETALL', 'HINCRBY', 'HINCRBYFLOAT', 'HLEN', 'HMGET',
            'HMSET', 'HSCAN', 'HSET', 'HSETNX', 'INCR', 'INCRBY', 'INCRBYFLOAT', 'LINDEX', 'LINSERT',
            'LLEN', 'LPOP', 'LPUSH', 'LRANGE', 'LREM', 'LSET', 'LTRIM', 'PERSIST', 'PEXPIRE', 'PSETEX',
            'PTTL', 'RPOP', 'RPUSH', 'SADD', 'SCARD', 'SET', 'SETEX', 'SETNX', 'SISMEMBER', 'SMEMBERS',
            'SPOP', 'SREM', 'SSCAN', 'STRLEN', 'TTL', 'TYPE', 'ZADD', 'ZCARD', 'ZCOUNT', 'ZINCRBY',
            'ZRANGE', 'ZRANK', 'ZREM', 'ZSCAN', 'ZSCORE',
        ];
        $keys = [];

        if (in_array($command, $multiKeyCommands, true)) {
            $keys = count($parameters) === 1 && is_array($parameters[0]) ? $parameters[0] : $parameters;
        } elseif ($command === 'MSET') {
            if (count($parameters) === 1 && is_array($parameters[0])) {
                $keys = array_keys($parameters[0]);
            } else {
                foreach ($parameters as $index => $parameter) {
                    if ($index % 2 === 0) {
                        $keys[] = $parameter;
                    }
                }
            }
        } elseif (in_array($command, $firstKeyCommands, true) && array_key_exists(0, $parameters)) {
            $keys = [$parameters[0]];
        }

        $keyCount = count($keys);
        $keys = array_slice($keys, 0, $this->nestedItemLimit());
        $hashes = array_values(array_unique(array_map(
            fn (mixed $key): string => $this->redactor->cleanKey($key),
            $keys,
        )));
        $policy = $this->keyPolicy();

        return [
            'key_count' => $keyCount,
            'key_retained' => count($keys),
            'key_dropped' => max(0, $keyCount - count($keys)),
            'key_hashes' => $hashes,
            'keys' => $policy === 'full' ? array_values(array_unique(array_map(
                fn (mixed $key): string => $this->redactor->cleanKey($key, 'full'),
                $keys,
            ))) : [],
            'key_policy' => $policy,
        ];
    }

    private function keyPolicy(): string
    {
        return config('newdebugbar.collection.key_policy', 'full') === 'hash' ? 'hash' : 'full';
    }

    private function configuredMailDriver(): ?string
    {
        $driver = config('mail.default');

        return is_string($driver) && $driver !== '' ? $driver : null;
    }

    private function configuredMailTransport(): ?string
    {
        $driver = $this->configuredMailDriver();
        $transport = $driver === null ? null : config('mail.mailers.'.$driver.'.transport');

        return is_string($transport) && $transport !== '' ? $transport : null;
    }

    private function cacheStoreUsesRedis(?string $store): bool
    {
        $stores = config('cache.stores', []);
        $definition = is_array($stores) && is_string($store) ? ($stores[$store] ?? null) : null;

        return is_array($definition) && ($definition['driver'] ?? null) === 'redis';
    }

    private function cacheDriver(?string $store): ?string
    {
        $store ??= is_string(config('cache.default')) ? config('cache.default') : null;
        $stores = config('cache.stores', []);
        $definition = is_array($stores) && $store !== null ? ($stores[$store] ?? null) : null;
        $driver = is_array($definition) ? ($definition['driver'] ?? null) : null;

        return is_string($driver) && $driver !== '' ? $driver : null;
    }

    /** @param list<mixed> $tags @return array{tag_count: int, tag_retained: int, tag_dropped: int, tag_hashes: list<string>, tags: list<string>} */
    private function cacheTags(array $tags): array
    {
        $tagCount = count($tags);
        $tags = array_slice($tags, 0, $this->nestedItemLimit());
        $policy = $this->keyPolicy();

        return [
            'tag_count' => $tagCount,
            'tag_retained' => count($tags),
            'tag_dropped' => max(0, $tagCount - count($tags)),
            'tag_hashes' => array_values(array_unique(array_map(
                fn (mixed $tag): string => $this->redactor->cleanKey($tag),
                $tags,
            ))),
            'tags' => $policy === 'full' ? array_values(array_unique(array_map(
                fn (mixed $tag): string => $this->redactor->cleanKey($tag, 'full'),
                $tags,
            ))) : [],
        ];
    }

    private function nestedItemLimit(): int
    {
        return max(0, (int) config('newdebugbar.collection.max_items_per_array', 100));
    }
}
