<?php

namespace Bramato\LaravelMcpServer\Tests\Fixtures;

use Bramato\LaravelMcpServer\Mcp\Interfaces\ResourceInterface;

class DummyResource implements ResourceInterface
{
    public function getUri(): string
    {
        return 'test://dummy';
    }

    public function getName(): string
    {
        return 'Dummy Resource';
    }

    public function getDescription(): string
    {
        return 'A simple resource for testing.';
    }

    public function getContents(): mixed
    {
        return ['foo' => 'bar', 'time' => time()];
    }

    public function getMimeType(): string
    {
        return 'application/json';
    }
}
