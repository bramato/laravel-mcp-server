<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enabled Transports
    |--------------------------------------------------------------------------
    |
    | Specify which MCP communication transports are enabled.
    | Available options: 'stdio', 'http', 'websocket'
    |
    */
    'enabled_transports' => ['stdio'],

    /*
    |--------------------------------------------------------------------------
    | Transport Specific Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for each enabled transport.
    |
    */
    'transports' => [
        'stdio' => [
            // No specific config for stdio yet
        ],
        'http' => [
            'path' => '/mcp-rpc', // Default path for HTTP transport
        ],
        'websocket' => [
            // Configuration for Reverb or other WS servers will go here
            // 'host' => '127.0.0.1',
            // 'port' => 8080,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Driver
    |--------------------------------------------------------------------------
    |
    | Define the authentication method for incoming MCP requests.
    | Options: 'none', 'token', 'middleware_class'
    | 'token' requires 'options.header' to be set.
    | 'middleware_class' requires 'options.class' to be set.
    |
    */
    'authentication' => [
        'driver' => 'none', // Default to no authentication
        'options' => [
            // 'header' => 'X-MCP-Token',
            // 'class' => App\Http\Middleware\MyMcpAuthMiddleware::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Registered MCP Resources
    |--------------------------------------------------------------------------
    |
    | An array of class names that implement the ResourceInterface.
    | These resources will be available via the MCP server.
    | Acts as a whitelist.
    |
    */
    'resources' => [
        // Example: App\Mcp\Resources\UserListResource::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Registered MCP Tools
    |--------------------------------------------------------------------------
    |
    | An array of class names that implement the ToolInterface.
    | These tools will be executable via the MCP server.
    | Acts as a whitelist.
    |
    */
    'tools' => [
        // Example: App\Mcp\Tools\SendWelcomeEmailTool::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging level and channels for MCP specific events.
    |
    */
    'logging' => [
        'channel' => null, // Use default Laravel log channel
        'level' => 'info', // Default log level
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON-RPC Handler Options
    |--------------------------------------------------------------------------
    |
    | Specific options to pass to the underlying JSON-RPC handler (e.g., Sajya).
    |
    */
    'rpc_handler_options' => [
        // Options specific to the sajya/server package or chosen handler
    ],
];
