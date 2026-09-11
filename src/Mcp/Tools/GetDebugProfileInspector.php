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

/** Reads bounded evidence from one named inspector in an exact profile. */
#[IsReadOnly]
#[IsOpenWorld(false)]
final class GetDebugProfileInspector extends DebugTool
{
    private const DEFAULT_LIMIT = 5;

    protected const DESCRIPTION = 'Read one focused inspector from an exact debug profile. Redis items include bounded key evidence and application call sites. Exception items include bounded cause locations without private messages or full cause stacks. Use get-debug-profile-data when the inspector response omits deeper evidence, including complete Models evidence and retained exception causes.';

    public function __construct(private readonly McpProfilePresenter $profiles) {}

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'profile_id' => $schema->string()->format('uuid')->required(),
            'inspector' => $schema->string()->enum($this->profiles->inspectorNames())->required(),
            'cursor' => $schema->integer()->min(0)->default(0),
            'limit' => $schema->integer()->min(1)->max($this->profiles->maxItems())->default($this->defaultLimit()),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $input = $request->validate([
            'profile_id' => 'required|string|regex:'.ProfileStore::ID_REGEX,
            'inspector' => 'required|string|in:'.implode(',', $this->profiles->inspectorNames()),
            'cursor' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:'.$this->profiles->maxItems(),
        ]);

        return $this->safeResponse(fn (): array => $this->profiles->inspector(
            $input['profile_id'],
            $input['inspector'],
            (int) ($input['cursor'] ?? 0),
            (int) ($input['limit'] ?? $this->defaultLimit()),
        ));
    }

    private function defaultLimit(): int
    {
        return min(self::DEFAULT_LIMIT, $this->profiles->maxItems());
    }
}
