<?php

namespace Bramato\LaravelMcpServer\Mcp\Interfaces;

interface ServerInterface
{
    /**
     * Set the transport mechanism for the server.
     *
     * @param TransportInterface $transport
     * @return void
     */
    public function setTransport(TransportInterface $transport): void;

    /**
     * Run the server (typically for long-running transports like Stdio or WebSocket listeners).
     *
     * @return void
     */
    public function run(): void;

    /**
     * Handle a single incoming request.
     *
     * @param string $requestJson The raw JSON-RPC request string.
     * @param mixed|null $connectionContext Optional context about the connection (e.g., WebSocket connection ID).
     * @return string|null The raw JSON-RPC response string, or null for notifications.
     */
    public function handleRequest(string $requestJson, mixed $connectionContext = null): ?string;

    /**
     * Register an MCP Resource with the server.
     *
     * @param ResourceInterface $resource
     * @return void
     */
    public function registerResource(ResourceInterface $resource): void;

    /**
     * Register an MCP Tool with the server.
     *
     * @param ToolInterface $tool
     * @return void
     */
    public function registerTool(ToolInterface $tool): void;

    /**
     * Get the currently registered MCP Resources.
     *
     * @return array<string, ResourceInterface> Array keyed by resource URI.
     */
    public function getResources(): array;

    /**
     * Get the currently registered MCP Tools.
     *
     * @return array<string, ToolInterface> Array keyed by tool name.
     */
    public function getTools(): array;
}
