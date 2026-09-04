<?php

namespace NewDebugBar\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use NewDebugBar\Presentation\McpProfilePresenter;
use NewDebugBar\Storage\ProfileStore;

/** Lets agents walk every retained profile value without returning the full profile at once. */
#[IsReadOnly]
#[IsOpenWorld(false)]
final class GetDebugProfileData extends DebugTool
{
    private const DEFAULT_LIMIT = 10;

    protected const DESCRIPTION = 'Read any captured or derived profile value by JSON Pointer. Start at /sections, then follow returned paths to lists, objects, and exact scalar values. Use /sections/models/payload/model_groups for complete folded model operations, identifiers, sources, timings, query correlation, and guidance. Use /sections/redis/payload/items/{index}/callsite for a retained Redis client call site. Use /sections/exceptions/payload/items/{index}/causes for retained exception cause messages, frames, and source context. Use /sections/views/payload/items/{index}/data for retained view data; renderable views and lazy component methods are class labels, not executed values.';

    public function __construct(private readonly McpProfilePresenter $profiles) {}

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'profile_id' => $schema->string()->format('uuid')->required(),
            'path' => $schema->string()
                ->max(1_000)
                ->description('JSON Pointer to inspect. Use /sections to discover all diagnostic sections and an empty string for the profile root.')
                ->default('/sections'),
            'cursor' => $schema->integer()->min(0)->default(0),
            'limit' => $schema->integer()->min(1)->max($this->profiles->maxItems())->default($this->defaultLimit()),
        ];
    }

    /** @return array<string, Type> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'version' => $schema->integer()->required(),
            'status' => $schema->string()->enum(['ok', 'not_found'])->required(),
            'data' => $schema->object([
                'profile_id' => $schema->string()->required(),
                'path' => $schema->string()->required(),
                'type' => $schema->string()->enum(['object', 'list', 'string', 'integer', 'number', 'boolean', 'null', 'unknown']),
                'count' => $schema->integer()->min(0),
                'length_bytes' => $schema->integer()->min(0),
                'chunked' => $schema->boolean(),
                'value' => $schema->union(['string', 'integer', 'number', 'boolean', 'null']),
                'entries' => $schema->array()->items($schema->object([
                    'key' => $schema->string()->required(),
                    'path' => $schema->string()->required(),
                    'type' => $schema->string()->required(),
                    'count' => $schema->integer()->min(0),
                    'length_bytes' => $schema->integer()->min(0),
                    'chunked' => $schema->boolean(),
                    'value' => $schema->union(['string', 'integer', 'number', 'boolean', 'null']),
                ])),
                'chunks' => $schema->array()->items($schema->string()),
                'pagination' => $schema->object([
                    'cursor' => $schema->integer()->min(0)->required(),
                    'returned' => $schema->integer()->min(0)->required(),
                    'total' => $schema->integer()->min(0)->required(),
                    'truncated' => $schema->boolean()->required(),
                    'next_cursor' => $schema->union(['integer', 'null'])->required(),
                    'omitted_due_to_bytes' => $schema->integer()->min(0),
                ]),
            ])->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $input = $request->validate([
            'profile_id' => 'required|string|regex:'.ProfileStore::ID_REGEX,
            'path' => ['nullable', 'string', 'max:1000', 'regex:/\A(?:\/.*)?\z/s'],
            'cursor' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:'.$this->profiles->maxItems(),
        ]);

        return $this->safeResponse(fn (): array => $this->profiles->data(
            $input['profile_id'],
            $input['path'] ?? '/sections',
            (int) ($input['cursor'] ?? 0),
            (int) ($input['limit'] ?? $this->defaultLimit()),
        ));
    }

    private function defaultLimit(): int
    {
        return min(self::DEFAULT_LIMIT, $this->profiles->maxItems());
    }
}
