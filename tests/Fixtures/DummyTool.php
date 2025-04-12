<?php

namespace Bramato\LaravelMcpServer\Tests\Fixtures;

use Bramato\LaravelMcpServer\Mcp\Interfaces\ToolInterface;

class DummyTool implements ToolInterface
{
    public function getName(): string
    {
        return 'dummyTool';
    }

    public function getDescription(): string
    {
        return 'A simple tool for testing.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'param1' => ['type' => 'string', 'description' => 'First parameter'],
                'param2' => ['type' => 'integer', 'default' => 10],
            ],
            'required' => ['param1'],
        ];
    }

    public function execute(array $arguments): mixed
    {
        if (!isset($arguments['param1'])) {
            throw new \InvalidArgumentException('Missing required parameter: param1');
        }

        return [
            'message' => 'Executed dummy tool',
            'received_param1' => $arguments['param1'],
            'received_param2' => $arguments['param2'] ?? 10, // Usa default se non fornito
        ];
    }
}
