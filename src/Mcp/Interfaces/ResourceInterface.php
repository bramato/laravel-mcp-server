<?php

namespace Bramato\LaravelMcpServer\Mcp\Interfaces;

interface ResourceInterface
{
    /**
     * Get the unique URI identifying this resource.
     *
     * @return string
     */
    public function getUri(): string;

    /**
     * Get a human-readable name for the resource.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get a description of the resource.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Get the content of the resource.
     * The format depends on the mime type.
     *
     * @return mixed
     */
    public function getContents(): mixed;

    /**
     * Get the MIME type of the resource content.
     *
     * @return string
     */
    public function getMimeType(): string;
}
