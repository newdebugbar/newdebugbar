<?php

namespace NewDebugBar\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use NewDebugBar\Support\ProfileAccess;
use Throwable;

/** Rechecks local package access and returns visible errors when an MCP read fails. */
abstract class DebugTool extends Tool
{
    protected const DESCRIPTION = '';

    final public function description(): string
    {
        return static::DESCRIPTION;
    }

    /** @return array<string, mixed> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'version' => $schema->integer()->required(),
            'status' => $schema->string()->enum(['ok', 'not_found'])->required(),
            'data' => $schema->object()->required(),
        ];
    }

    /** @param array<string, mixed> $content */
    protected function response(array $content): ResponseFactory
    {
        return Response::structured($content);
    }

    /** @param callable(): array<string, mixed> $content */
    protected function safeResponse(callable $content): ResponseFactory
    {
        if (! app(ProfileAccess::class)->enabled()) {
            return Response::make(Response::error('New Debug Bar is not enabled in this environment.'));
        }

        try {
            return $this->response($content());
        } catch (Throwable) {
            return Response::make(Response::error('The debug profile could not be processed.'));
        }
    }
}
