<?php

namespace NewDebugBar\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Throwable;

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
            'status' => $schema->string()->enum(['ok', 'partial', 'not_found'])->required(),
            'data' => $schema->object()->required(),
        ];
    }

    /** @param array<string, mixed> $content */
    protected function response(array $content): ResponseFactory
    {
        if (($content['status'] ?? null) === 'partial') {
            return Response::make(Response::error($content['data']['background_error']))
                ->withStructuredContent($content);
        }

        return Response::structured($content);
    }

    /** @param callable(): array<string, mixed> $content */
    protected function safeResponse(callable $content): ResponseFactory
    {
        try {
            return $this->response($content());
        } catch (Throwable) {
            return Response::make(Response::error('The debug profile could not be processed.'));
        }
    }
}
