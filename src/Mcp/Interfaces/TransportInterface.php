<?php

namespace Bramato\LaravelMcpServer\Mcp\Interfaces;

interface TransportInterface
{
    /**
     * Read a message from the transport layer.
     * Should return null if the connection is closed or no message is available yet (non-blocking).
     * The returned array should represent the decoded JSON-RPC message.
     *
     * @return array|null
     */
    public function readMessage(): ?array;

    /**
     * Write a message to the transport layer.
     * The input array should represent the JSON-RPC message to be encoded and sent.
     *
     * @param array $message
     * @return void
     */
    public function writeMessage(array $message): void;

    /**
     * Close the transport connection.
     *
     * @return void
     */
    public function close(): void;
}
