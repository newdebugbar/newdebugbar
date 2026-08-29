<?php

namespace NewDebugBar;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Log\Logger;
use Illuminate\Log\LogManager;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Facades\Mcp;
use Livewire\Livewire;
use NewDebugBar\Analysis\CacheAnalyzer;
use NewDebugBar\Analysis\HttpClientAnalyzer;
use NewDebugBar\Analysis\LogAnalyzer;
use NewDebugBar\Analysis\ProfileAnalyzer;
use NewDebugBar\Analysis\QueryAnalyzer;
use NewDebugBar\Analysis\SectionAnalyzer;
use NewDebugBar\Analysis\TimelineBuilder;
use NewDebugBar\Collectors\CacheCollector;
use NewDebugBar\Collectors\ExceptionCollector;
use NewDebugBar\Collectors\ItemCollector;
use NewDebugBar\Collectors\LivewireCollector;
use NewDebugBar\Collectors\LogCollector;
use NewDebugBar\Collectors\MailCollector;
use NewDebugBar\Collectors\NotificationCollector;
use NewDebugBar\Collectors\OutboundHttpCollector;
use NewDebugBar\Collectors\QueryCollector;
use NewDebugBar\Collectors\QueueCollector;
use NewDebugBar\Collectors\RedisCollector;
use NewDebugBar\Collectors\ValidationCollector;
use NewDebugBar\Http\Controllers\AssetController;
use NewDebugBar\Http\Controllers\CsrfTokenController;
use NewDebugBar\Http\Controllers\MailPreviewController;
use NewDebugBar\Http\Middleware\ProfileRequest;
use NewDebugBar\Livewire\DebugBar;
use NewDebugBar\Mcp\NewDebugBarServer;
use NewDebugBar\Presentation\McpProfilePresenter;
use NewDebugBar\Presentation\ProfilePresenter;
use NewDebugBar\Presentation\ProfileSummaryPresenter;
use NewDebugBar\Storage\BackgroundActivityStore;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Support\CallSiteResolver;
use NewDebugBar\Support\EventRegistrar;
use NewDebugBar\Support\ExceptionNormalizer;
use NewDebugBar\Support\LivewireRegistrar;
use NewDebugBar\Support\LogChannelTap;
use NewDebugBar\Support\LogChannelTracker;
use NewDebugBar\Support\MailPreview;
use NewDebugBar\Support\ProfileFinalizer;
use NewDebugBar\Support\QueryExplainer;
use NewDebugBar\Support\QueuedCommunicationInspector;
use NewDebugBar\Support\Redactor;
use NewDebugBar\Support\RequestContext;
use NewDebugBar\Support\RuntimeContext;
use NewDebugBar\Support\RuntimeProfiler;
use NewDebugBar\Support\SafeUrl;
use Throwable;

use function Livewire\on;

/** Registers profiling services only in explicitly allowed environments. */
final class NewDebugBarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/newdebugbar.php', 'newdebugbar');

        $this->app->singleton(Redactor::class, fn (): Redactor => new Redactor(
            maxDepth: (int) config('newdebugbar.collection.max_depth', 5),
            maxStringLength: (int) config('newdebugbar.collection.max_string_length', 2_000),
            maxArrayItems: (int) config('newdebugbar.collection.max_items_per_array', 100),
        ));

        $this->app->singleton(QueryAnalyzer::class, fn (): QueryAnalyzer => new QueryAnalyzer(
            (float) config('newdebugbar.slow_query_ms', 100),
        ));
        $this->app->singleton(HttpClientAnalyzer::class, fn (): HttpClientAnalyzer => new HttpClientAnalyzer(
            (float) config('newdebugbar.slow_http_request_ms', 250),
        ));
        $this->app->singleton(CacheAnalyzer::class, fn (): CacheAnalyzer => new CacheAnalyzer(
            minimumReads: (int) config('newdebugbar.findings.minimum_cache_operations', 5),
            highMissRate: (float) config('newdebugbar.findings.high_cache_miss_rate', 0.8),
        ));
        $this->app->singleton(LogAnalyzer::class);
        $this->app->singleton(LogChannelTracker::class);
        $this->app->singleton(ProfileAnalyzer::class, fn ($app): ProfileAnalyzer => new ProfileAnalyzer(
            queries: $app->make(QueryAnalyzer::class),
            slowRequestMs: (float) config('newdebugbar.slow_request_ms', 1_000),
            minimumCacheOperations: (int) config('newdebugbar.findings.minimum_cache_operations', 5),
            highCacheMissRate: (float) config('newdebugbar.findings.high_cache_miss_rate', 0.8),
            maxFindings: (int) config('newdebugbar.findings.max_findings', 50),
        ));
        $this->app->singleton(ProfileSummaryPresenter::class);
        $this->app->singleton(SectionAnalyzer::class);
        $this->app->singleton(TimelineBuilder::class);
        $this->app->singleton(CallSiteResolver::class, fn (): CallSiteResolver => new CallSiteResolver(
            projectPath: (string) (config('newdebugbar.collection.application_path') ?: base_path()),
            packagePath: dirname(__DIR__),
            enabled: (bool) config('newdebugbar.collection.call_sites', true),
            maxFrames: (int) config('newdebugbar.collection.call_site_frames', 5),
            scanLimit: (int) config('newdebugbar.collection.call_site_scan_limit', 40),
            compiledViewPath: (string) (config('view.compiled') ?: storage_path('framework/views')),
        ));
        $this->app->singleton(ExceptionNormalizer::class, fn (): ExceptionNormalizer => new ExceptionNormalizer(
            projectPath: (string) (config('newdebugbar.collection.application_path') ?: base_path()),
            packagePath: dirname(__DIR__),
            maxApplicationFrames: (int) config('newdebugbar.collection.exception_application_frames', 12),
            maxVendorFrames: (int) config('newdebugbar.collection.exception_vendor_frames', 12),
            sourceContextLines: (int) config('newdebugbar.collection.exception_source_context_lines', 9),
        ));
        $this->app->singleton(RequestContext::class, fn (): RequestContext => new RequestContext(
            maxKeys: (int) config('newdebugbar.collection.max_items_per_array', 100),
        ));
        $this->app->singleton(QueryExplainer::class);
        $this->app->singleton(MailPreview::class, fn (): MailPreview => new MailPreview(
            maxBodyBytes: (int) config('newdebugbar.mail_preview.max_body_bytes', 50_000),
            maxRecipients: (int) config('newdebugbar.collection.max_items_per_array', 100),
            maxAttachmentBytes: (int) config('newdebugbar.mail_preview.max_attachment_bytes', 2_000_000),
        ));
        $this->app->scoped(RuntimeProfiler::class);
        $this->app->singleton(RuntimeContext::class);
        $this->app->singleton(SafeUrl::class);
        $this->app->scoped(ProfileManager::class, function ($app): ProfileManager {
            $maxItems = (int) config('newdebugbar.collection.max_items_per_collector', 500);
            $redactor = $app->make(Redactor::class);

            return new ProfileManager([
                new QueryCollector(
                    $redactor,
                    $maxItems,
                    (string) config('newdebugbar.collection.query_bindings', 'full'),
                ),
                new OutboundHttpCollector($redactor, $maxItems),
                new QueueCollector($redactor, $maxItems),
                new MailCollector($redactor, $maxItems),
                new NotificationCollector($redactor, $maxItems),
                new RedisCollector($redactor, $maxItems),
                new ItemCollector($redactor, $maxItems, 'models', 'Models'),
                new CacheCollector($redactor, $maxItems),
                new ItemCollector($redactor, $maxItems, 'views', 'Views'),
                new ItemCollector($redactor, $maxItems, 'events', 'Events'),
                new ItemCollector($redactor, $maxItems, 'authorization', 'Authorization'),
                new ValidationCollector($redactor, $maxItems),
                new LogCollector($redactor, $maxItems),
                new ExceptionCollector($redactor, $maxItems),
                new LivewireCollector($redactor),
            ],
                $redactor,
                $app->make(ExceptionNormalizer::class),
                $app->make(RuntimeContext::class),
                $app->make(RequestContext::class),
                $app->make(CallSiteResolver::class),
            );
        });

        $this->app->singleton(ProfileStore::class, fn ($app): ProfileStore => new ProfileStore(
            files: $app->make(Filesystem::class),
            path: config('newdebugbar.storage.path') ?: storage_path('framework/newdebugbar'),
            maxProfiles: (int) config('newdebugbar.storage.max_profiles', 20),
            maxAgeMinutes: (int) config('newdebugbar.storage.max_age_minutes', 60),
        ));
        $this->app->singleton(BackgroundActivityStore::class, function ($app): BackgroundActivityStore {
            $profilePath = config('newdebugbar.storage.path') ?: storage_path('framework/newdebugbar');
            $maxProfiles = (int) config('newdebugbar.storage.max_profiles', 20);

            return new BackgroundActivityStore(
                files: $app->make(Filesystem::class),
                path: rtrim($profilePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'background',
                maxActivities: max(20, $maxProfiles * 5),
                maxAgeMinutes: (int) config('newdebugbar.storage.max_age_minutes', 60),
            );
        });
        $this->app->singleton(QueuedCommunicationInspector::class, fn (): QueuedCommunicationInspector => new QueuedCommunicationInspector(
            maxItems: (int) config('newdebugbar.collection.max_items_per_array', 100),
        ));
        $this->app->singleton(McpProfilePresenter::class, fn ($app): McpProfilePresenter => new McpProfilePresenter(
            store: $app->make(ProfileStore::class),
            profiles: $app->make(ProfilePresenter::class),
            summaries: $app->make(ProfileSummaryPresenter::class),
            redactor: $app->make(Redactor::class),
            projectPath: base_path(),
            maxItems: (int) config('newdebugbar.mcp.max_items', 50),
            maxBytes: (int) config('newdebugbar.mcp.max_bytes', 100_000),
        ));
    }

    public function boot(Router $router, Dispatcher $events): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'newdebugbar');

        $this->publishes([
            __DIR__.'/../config/newdebugbar.php' => config_path('newdebugbar.php'),
        ], 'newdebugbar-config');

        if (! $this->isEnabledEnvironment()) {
            return;
        }

        $this->registerLogChannelTracking();

        (new EventRegistrar(
            $events,
            $this->app,
            $this->app->make(CallSiteResolver::class),
            $this->app->make(SafeUrl::class),
            $this->app->make(Redactor::class),
            $this->app->make(MailPreview::class),
            $this->app->make(QueuedCommunicationInspector::class),
            $this->app->make(BackgroundActivityStore::class),
            $this->app->make(LogChannelTracker::class),
        ))->register();
        (new LivewireRegistrar(
            $this->app,
            $this->app->make(CallSiteResolver::class),
        ))->register();
        $exceptions = $this->app->make(ExceptionHandler::class);

        if (method_exists($exceptions, 'renderable')) {
            $exceptions->renderable(function (ValidationException $exception, Request $request) {
                $this->app->make(ProfileManager::class)->recordValidationException($exception);

                return null;
            });
        }
        on('exception', function (mixed $target, Throwable $exception): void {
            if ($exception instanceof ValidationException) {
                $this->app->make(ProfileManager::class)->recordValidationException($exception);
            }
        });
        $events->listen(
            RequestHandled::class,
            fn (RequestHandled $event) => $this->app->make(ProfileFinalizer::class)->handle($event),
        );
        Livewire::component('newdebugbar.toolbar', DebugBar::class);
        $this->registerMcpServer();
        $router->get('/__newdebugbar/csrf', CsrfTokenController::class)
            ->middleware('web')
            ->name('newdebugbar.csrf');
        $router->get('/__newdebugbar/assets/{path}', AssetController::class)
            ->where('path', '.*')
            ->name('newdebugbar.asset');
        $router->get('/__newdebugbar/mail/{profile}/{index}/{format}', MailPreviewController::class)
            ->where('profile', ProfileStore::ID_PATTERN)
            ->whereNumber('index')
            ->whereIn('format', ['html', 'text', 'eml'])
            ->name('newdebugbar.mail-preview');
        $router->get(
            '/__newdebugbar/mail/{profile}/{index}/attachment/{attachment}',
            [MailPreviewController::class, 'attachment'],
        )
            ->where('profile', ProfileStore::ID_PATTERN)
            ->whereNumber('index')
            ->whereNumber('attachment')
            ->name('newdebugbar.mail-attachment');
        $kernel = $this->app->make(HttpKernel::class);

        if (method_exists($kernel, 'pushMiddleware')) {
            $kernel->pushMiddleware(ProfileRequest::class);
        }
    }

    private function isEnabledEnvironment(): bool
    {
        $environments = config('newdebugbar.environments', ['local']);

        return config('newdebugbar.enabled', true)
            && is_array($environments)
            && $this->app->environment($environments);
    }

    private function registerLogChannelTracking(): void
    {
        $channels = config('logging.channels', []);

        if (! is_array($channels)) {
            return;
        }

        foreach ($channels as $channel => $configuration) {
            if (! is_string($channel) || ! is_array($configuration)) {
                continue;
            }

            $tap = LogChannelTap::class.':'.$channel;
            $taps = array_values(array_filter((array) ($configuration['tap'] ?? []), 'is_string'));

            if (! in_array($tap, $taps, true)) {
                $configuration['tap'] = [...$taps, $tap];
                $channels[$channel] = $configuration;
            }
        }

        config(['logging.channels' => $channels]);

        if (! $this->app->resolved('log')) {
            return;
        }

        $manager = $this->app->make('log');

        if (! $manager instanceof LogManager) {
            return;
        }

        $tap = $this->app->make(LogChannelTap::class);

        foreach ($manager->getChannels() as $channel => $logger) {
            if ($logger instanceof Logger) {
                $tap($logger, (string) $channel);
            }
        }
    }

    private function registerMcpServer(): void
    {
        Mcp::local('newdebugbar', NewDebugBarServer::class);
    }
}
