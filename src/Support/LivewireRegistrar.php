<?php

namespace NewDebugBar\Support;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use NewDebugBar\Livewire\DebugBar;
use NewDebugBar\ProfileManager;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use Stringable;
use Throwable;
use UnitEnum;
use WeakMap;

use function Livewire\on;

/** Records host Livewire lifecycle evidence without changing component payloads. */
final class LivewireRegistrar
{
    /** @var WeakMap<Component, array<string, mixed>> */
    private WeakMap $componentState;

    private int $activitySequence = 0;

    public function __construct(
        private readonly Container $container,
        private readonly CallSiteResolver $callSites,
    ) {
        $this->componentState = new WeakMap;
    }

    public function register(): void
    {
        $this->listen('mount', function (
            Component $component,
            array $_params,
            mixed $_key,
            mixed $parent,
        ): void {
            if (! $this->isHostComponent($component)) {
                return;
            }

            $this->remember(
                $component,
                'parent_id',
                $parent instanceof Component && $this->isHostComponent($parent) ? $parent->getId() : null,
            );
            $this->recordComponent($component);
            $this->recordActivity($component, [
                'type' => 'mount',
                'status' => 'complete',
                'name' => $this->componentTitle($component).' mounted',
            ]);
        });

        $this->listen('hydrate', function (Component $component): void {
            if (! $this->isHostComponent($component)) {
                return;
            }

            $this->remember($component, 'validation_before', $this->validationMessages($component));
            $this->remember($component, 'validation_recorded', false);
            $this->recordComponent($component);
        });

        $this->listen('update', function (
            Component $component,
            string $property,
            mixed $submitted,
        ): ?Closure {
            if (! $this->isHostComponent($component)) {
                return null;
            }

            $startedAt = hrtime(true);
            $before = data_get($component->all(), $property);

            return function () use ($component, $property, $submitted, $before, $startedAt): void {
                $this->recordActivity($component, [
                    'type' => 'change',
                    'status' => 'complete',
                    'name' => $this->humanize($property).' changed',
                    'property' => $property,
                    'before' => $before,
                    'submitted' => $submitted,
                    'server' => data_get($component->all(), $property),
                    'duration_ms' => $this->duration($startedAt),
                ]);
            };
        });

        $this->listen('call', function (
            Component $component,
            string $method,
            array $params,
            mixed $_context,
            mixed $_earlyReturn,
            array $metadata = [],
            int $index = 0,
        ): ?Closure {
            if (! $this->isHostComponent($component)) {
                return null;
            }

            $startedAt = hrtime(true);
            $eventName = $method === '__dispatch' && is_string($params[0] ?? null) ? $params[0] : null;

            return function (mixed $result = null) use (
                $component,
                $method,
                $params,
                $metadata,
                $index,
                $eventName,
                $startedAt,
            ): mixed {
                $this->recordActivity($component, [
                    'type' => $eventName === null ? 'action' : 'event_received',
                    'status' => 'complete',
                    'name' => $eventName === null
                        ? $this->humanize($method).' ran'
                        : $this->humanize($eventName).' event received',
                    'method' => $method,
                    'message_order' => $index,
                    'params' => $params,
                    'metadata' => $metadata,
                    'event' => $eventName,
                    'return_type' => get_debug_type($result),
                    'duration_ms' => $this->duration($startedAt),
                ]);

                return $result;
            };
        });

        $this->listen('render', function (Component $component, mixed $view): ?Closure {
            if (! $this->isHostComponent($component)) {
                return null;
            }

            $startedAt = hrtime(true);
            $this->remember($component, 'view', $this->viewDetails($view));

            return function () use ($component, $startedAt): void {
                $this->recordActivity($component, [
                    'type' => 'render',
                    'status' => 'complete',
                    'name' => $this->componentTitle($component).' rendered',
                    'duration_ms' => $this->duration($startedAt),
                ]);
            };
        });

        $this->listen('dehydrate', function (Component $component, mixed $context): void {
            if (! $this->isHostComponent($component)) {
                return;
            }

            $this->recordValidationStateChange($component);
            $this->recordComponent($component);
            $effects = is_array($context->effects ?? null) ? $context->effects : [];

            foreach ($effects['dispatches'] ?? [] as $dispatch) {
                if (! is_array($dispatch) || ! is_string($dispatch['name'] ?? null)) {
                    continue;
                }

                $this->recordActivity($component, [
                    'type' => 'event',
                    'status' => 'complete',
                    'name' => $this->humanize($dispatch['name']).' event dispatched',
                    'event' => $dispatch['name'],
                    'params' => $dispatch['params'] ?? [],
                    'mode' => isset($dispatch['component']) ? 'component' : (isset($dispatch['self']) ? 'self' : 'bubble'),
                    'declared_target' => $dispatch['component'] ?? $dispatch['ref'] ?? $dispatch['el'] ?? null,
                ]);
            }

            foreach (['redirect', 'download', 'streams'] as $effect) {
                if (! array_key_exists($effect, $effects)) {
                    continue;
                }

                $this->recordActivity($component, [
                    'type' => $effect === 'streams' ? 'stream' : $effect,
                    'status' => 'complete',
                    'name' => $this->componentTitle($component).' '.($effect === 'streams' ? 'streamed a response' : $effect.'ed'),
                    'effect' => $effect,
                ]);
            }
        });

        $this->listen('exception', function (mixed $target, Throwable $exception): void {
            $component = $target instanceof Component
                ? $target
                : (is_object($target) && method_exists($target, 'getComponent') ? $target->getComponent() : null);

            if (! $component instanceof Component || ! $this->isHostComponent($component)) {
                return;
            }

            if ($exception instanceof ValidationException) {
                $this->remember($component, 'validation_recorded', true);
            }

            $this->recordActivity($component, [
                'type' => 'failure',
                'status' => $exception instanceof ValidationException ? 'failed_validation' : 'failed',
                'name' => $exception instanceof ValidationException
                    ? $this->componentTitle($component).' failed validation'
                    : $this->componentTitle($component).' failed',
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'callsite' => $this->callSites->fromThrowable($exception),
            ]);
        });
    }

    private function recordComponent(Component $component): void
    {
        $reflection = new ReflectionClass($component);
        $state = $this->componentState[$component] ?? [];
        $implementation = $this->componentImplementation($component, $reflection);
        $source = $implementation['source'];
        $view = $implementation['type'] === 'single_file'
            ? ['name' => 'Same file', 'source' => $source]
            : ($state['view'] ?? null);

        $this->manager()->record('livewire', [
            'kind' => 'component',
            'component' => [
                'id' => $component->getId(),
                'name' => $component->getName(),
                'title' => $this->componentTitle($component),
                'class' => $component::class,
                'implementation' => $implementation['type'],
                'source' => $source,
                'view' => $view,
                'parent_id' => $state['parent_id'] ?? null,
                'properties' => $this->propertyDescriptors($component, $reflection),
            ],
        ]);
    }

    /** @return array{type: 'class'|'single_file', source: array{file: string, line: int}|null} */
    private function componentImplementation(Component $component, ReflectionClass $reflection): array
    {
        try {
            $finder = $this->container->make('livewire.finder');
            $class = $finder->resolveClassComponentClassName($component->getName());
            $path = $class === null
                ? $finder->resolveSingleFileComponentPath($component->getName())
                : null;

            if (is_string($path)) {
                return [
                    'type' => 'single_file',
                    'source' => $this->callSites->templateLocation($path),
                ];
            }
        } catch (Throwable) {
            // Component metadata should never interrupt a host Livewire request.
        }

        return [
            'type' => 'class',
            'source' => is_string($reflection->getFileName())
                ? $this->callSites->location($reflection->getFileName(), $reflection->getStartLine())
                : null,
        ];
    }

    /** @param array<string, mixed> $activity */
    private function recordActivity(Component $component, array $activity): void
    {
        $this->manager()->record('livewire', [
            'kind' => 'activity',
            'activity' => [
                'id' => $component->getId().'-server-'.(++$this->activitySequence),
                'component_id' => $component->getId(),
                'component_name' => $component->getName(),
                'component_title' => $this->componentTitle($component),
                'evidence' => 'observed',
                ...$activity,
            ],
        ]);
    }

    private function recordValidationStateChange(Component $component): void
    {
        $state = $this->componentState[$component] ?? [];

        if (($state['validation_recorded'] ?? false) === true) {
            return;
        }

        $before = is_array($state['validation_before'] ?? null)
            ? $state['validation_before']
            : [];
        $messages = $this->validationMessages($component);

        if ($messages === [] || $messages === $before) {
            return;
        }

        $fields = array_values(array_map('strval', array_keys($messages)));
        $reflection = new ReflectionClass($component);
        $source = is_string($reflection->getFileName())
            ? $this->callSites->location($reflection->getFileName(), $reflection->getStartLine())
            : null;

        $this->manager()->record('validation', [
            'source' => 'livewire',
            'fields' => $fields,
            'rules' => array_fill_keys($fields, []),
            'messages' => $messages,
            'error_bag' => 'default',
            'exception_class' => null,
            'exception_message' => null,
            'exception_status' => null,
            'redirect_requested' => false,
            'callsite' => $source,
        ]);
        $this->recordActivity($component, [
            'type' => 'failure',
            'status' => 'failed_validation',
            'name' => $this->componentTitle($component).' failed validation',
            'fields' => $fields,
            'messages' => $messages,
            'callsite' => $source,
        ]);
        $this->remember($component, 'validation_recorded', true);
    }

    /** @return array<string, list<string>> */
    private function validationMessages(Component $component): array
    {
        try {
            $messages = $component->getErrorBag()->toArray();

            return is_array($messages) ? $messages : [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    private function propertyDescriptors(Component $component, ReflectionClass $reflection): array
    {
        $descriptors = [];

        foreach ($component->all() as $name => $value) {
            $property = $reflection->hasProperty((string) $name)
                ? $reflection->getProperty((string) $name)
                : null;
            $reason = $this->readOnlyReason($property);
            $primitive = $this->isPrimitive($value);
            $writeAllowed = $reason === null;
            $descriptors[] = [
                'path' => (string) $name,
                'type' => $this->valueType($value),
                'php_type' => $property?->getType() === null ? null : (string) $property->getType(),
                'server_value' => $primitive ? $this->displayValue($value) : null,
                'writable' => $writeAllowed && $primitive,
                'array_leaf_writable' => $writeAllowed && is_array($value),
                'write_allowed' => $writeAllowed,
                'write_reason' => $reason,
            ];
        }

        return $descriptors;
    }

    private function readOnlyReason(?ReflectionProperty $property): ?string
    {
        if ($property === null) {
            return 'unknown';
        }

        if ($property->isReadOnly()) {
            return 'readonly';
        }

        if ($property->getAttributes(Locked::class, ReflectionAttribute::IS_INSTANCEOF) !== []) {
            return 'locked';
        }

        if ($property->getAttributes(Reactive::class, ReflectionAttribute::IS_INSTANCEOF) !== []) {
            return 'reactive';
        }

        return null;
    }

    private function valueType(mixed $value): string
    {
        return match (true) {
            $value === null => 'Null',
            is_bool($value) => 'Boolean',
            is_int($value) => 'Integer',
            is_float($value) => 'Float',
            is_string($value) => 'String',
            is_array($value) => 'Array',
            $value instanceof DateTimeInterface => 'Date',
            $value instanceof UnitEnum => 'Enum',
            default => class_basename($value),
        };
    }

    private function isPrimitive(mixed $value): bool
    {
        return $value === null
            || is_bool($value)
            || is_int($value)
            || is_float($value)
            || is_string($value);
    }

    private function displayValue(mixed $value): mixed
    {
        return match (true) {
            $value === null, is_scalar($value), is_array($value) => $value,
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof Stringable => (string) $value,
            is_object($value) => '['.$value::class.']',
            is_resource($value) => '[resource]',
            default => '['.get_debug_type($value).']',
        };
    }

    /** @return array{name: string|null, source: array{file: string, line: int}|null} */
    private function viewDetails(mixed $view): array
    {
        $name = is_object($view) && method_exists($view, 'name') ? $view->name() : null;
        $path = is_object($view) && method_exists($view, 'getPath') ? $view->getPath() : null;

        return [
            'name' => is_string($name) ? $name : null,
            'source' => is_string($path) ? $this->callSites->templateLocation($path) : null,
        ];
    }

    private function isHostComponent(?Component $component): bool
    {
        return $component instanceof Component
            && ! $component instanceof DebugBar
            && $component->getName() !== 'newdebugbar.toolbar';
    }

    private function componentTitle(Component $component): string
    {
        return $this->humanize(str($component->getName())->afterLast('.')->toString());
    }

    private function humanize(string $value): string
    {
        return str($value)
            ->replace(['.', '-', '_'], ' ')
            ->headline()
            ->toString();
    }

    private function remember(Component $component, string $key, mixed $value): void
    {
        $state = $this->componentState[$component] ?? [];
        $state[$key] = $value;
        $this->componentState[$component] = $state;
    }

    private function duration(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1_000_000, 3);
    }

    private function manager(): ProfileManager
    {
        return $this->container->make(ProfileManager::class);
    }

    private function listen(string $event, Closure $listener): void
    {
        on($event, function (...$arguments) use ($listener): ?Closure {
            try {
                $finish = $listener(...$arguments);
            } catch (Throwable) {
                return null;
            }

            if (! is_callable($finish)) {
                return null;
            }

            return static function (...$finishArguments) use ($finish): mixed {
                try {
                    return $finish(...$finishArguments);
                } catch (Throwable) {
                    return $finishArguments[0] ?? null;
                }
            };
        });
    }
}
