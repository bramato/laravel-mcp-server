<?php

use Illuminate\Support\Facades\Route;

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Bridge Server Configuration
    |--------------------------------------------------------------------------
    |
    | This file defines the mapping between MCP tools exposed by the Python
    | bridge server and the corresponding Laravel API endpoints or routes.
    | The `php artisan mcp:generate-bridge` command uses this configuration
    | to generate the Python server script.
    |
    */

    // Default settings for the generated Python server
    'server' => [
        'name' => env('MCP_BRIDGE_SERVER_NAME', 'LaravelMcpBridge'),
        'output_file' => base_path('mcp_bridge_server.py'), // Default location for the generated script
        'requirements_file' => base_path('mcp_bridge_requirements.txt'), // Default location for requirements
        'env_file' => base_path('.env.mcp_bridge'), // Default location for .env file
    ],

    // Authentication settings for Python -> Laravel calls
    'authentication' => [
        'enabled' => true, // Set to true if Laravel endpoints require authentication
        'header' => 'Authorization', // Header name (e.g., 'Authorization', 'X-API-Key')
        'prefix' => 'Bearer ', // Prefix for the token (e.g., 'Bearer ', '')
        'env_variable' => 'MCP_BRIDGE_API_TOKEN', // Environment variable in the Python .env file holding the token
    ],

    // Base URL for the Laravel application (used by the Python script)
    'laravel_base_url_env' => 'LARAVEL_APP_URL', // Environment variable in Python .env holding the Laravel URL

    // Define the MCP tools to be generated
    'tools' => [

        /*
        |--------------------------------------------------------------------------
        | Example Tool Definition
        |--------------------------------------------------------------------------
        |
        | 'python_tool_name' => [
        |     'description' => 'A brief description for the AI explaining what the tool does.',
        |     'parameters' => [
        |         // Parameters accepted by the Python function (@mcp.tool)
        |         // Format: 'param_name' => ['type' => 'python_type', 'required' => bool, 'description' => 'Param description for AI']
        |         'user_id' => ['type' => 'int', 'required' => true, 'description' => 'The ID of the user.'],
        |         'include_details' => ['type' => 'bool', 'required' => false, 'default' => false, 'description' => 'Whether to include extra details.'],
        |     ],
        |     'laravel_handler' => [
        |         // How Laravel handles this request
        |         'type' => 'route', // 'route' or 'url'
        |         'value' => 'api.users.show', // Route name or URL path (relative to base_url)
        |         'method' => 'GET', // HTTP method (GET, POST, PUT, DELETE, etc.)
        |         'auth_required' => true, // Does this specific endpoint require authentication? Overrides global setting if false.
        |         'parameter_mapping' => [
        |             // How Python parameters map to Laravel route parameters or request data
        |             // Route parameters: 'laravel_route_param_name' => 'python_param_name'
        |             'id' => 'user_id',
        |             // Query/Body parameters: 'laravel_request_key' => 'python_param_name'
        |             // For GET/DELETE, these become query parameters. For POST/PUT, they become JSON body.
        |             'details' => 'include_details',
        |         ]
        |     ],
        | ],
        */

        // Add your actual tool definitions here
        'get_server_status' => [
            'description' => 'Retrieves the current status of the Laravel application.',
            'parameters' => [], // No parameters for this simple example
            'laravel_handler' => [
                'type' => 'route', // Assuming you have a named route 'api.status'
                'value' => 'api.status',
                'method' => 'GET',
                'auth_required' => false, // Example: This specific endpoint doesn't need auth
                'parameter_mapping' => []
            ],
        ],

    ],

];
