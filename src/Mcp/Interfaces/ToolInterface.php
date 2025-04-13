<?php

namespace Bramato\LaravelMcpServer\Mcp\Interfaces;

use Illuminate\Support\Collection;

interface ToolInterface
{
    /**
     * Get the name of the tool.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get a description of what the tool does.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Get the JSON schema for the tool's input arguments.
     *
     * @return array
     */
    public function getInputSchema(): array;

    /**
     * Execute the tool with the given arguments.
     *
     * @param Collection $arguments
     * @return mixed The result of the tool execution.
     */
    public function execute(Collection $arguments): mixed;
}
