<p align="center">
  <img src="https://freeimage.host/i/3cp00Ob" alt="Laravel MCP Server Logo" width="300">
</p>

# Laravel MCP Server (bramato/laravel-mcp-server)

A base Laravel package for building [Model Context Protocol (MCP)](https://gomcp.org/) servers.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/bramato/laravel-mcp-server.svg?style=flat-square)](https://packagist.org/packages/bramato/laravel-mcp-server)
[![Total Downloads](https://img.shields.io/packagist/dt/bramato/laravel-mcp-server.svg?style=flat-square)](https://packagist.org/packages/bramato/laravel-mcp-server)

<!-- Consider adding build status/code coverage badges here later -->

This package provides the basic infrastructure to handle MCP requests over different transports (Stdio, HTTP, WebSocket via Reverb) using the excellent [sajya/server](https://github.com/sajya/server) library for JSON-RPC processing.

You need to implement your own `ResourceInterface` and `ToolInterface` classes (using the `Bramato\LaravelMcpServer\Mcp\Interfaces` namespace) and register them in the configuration file to expose your application's data and functionalities according to the MCP specification.

## Installation

You can install the package via composer:

```bash
composer require bramato/laravel-mcp-server
```

Next, you should publish the configuration file using the `vendor:publish` Artisan command:

```bash
php artisan vendor:publish --provider="Bramato\LaravelMcpServer\Providers\McpServiceProvider" --tag="config"
```

This will create a `config/mcp.php` file in your application's config directory, where you can customize the server's behavior.

## Configuration

The `config/mcp.php` file allows you to configure:

-   `enabled_transports`: Choose which transports (`stdio`, `http`, `websocket`) are active.
-   `transports`: Configure details for each transport (e.g., the HTTP path in `transports.http.path`).
-   `authentication`: Basic authentication setup (supports `none` or `token` via `authentication.driver`). For token authentication, specify the header name in `authentication.options.header`.
-   `resources`: **Register your MCP Resources.** Provide an array of fully qualified class names that implement `Bramato\LaravelMcpServer\Mcp\Interfaces\ResourceInterface`.
-   `tools`: **Register your MCP Tools.** Provide an array of fully qualified class names that implement `Bramato\LaravelMcpServer\Mcp\Interfaces\ToolInterface`. This acts as a whitelist for executable tools.
-   `logging`: Configure the logging channel and level for package-specific logs.
-   `rpc_handler_options`: Options to pass to the underlying `sajya/server` instance (refer to Sajya documentation).

## Creating Resources and Tools

This package provides the interfaces, but you need to implement the actual logic.

**Example: Creating a Resource**

1.  Create your Resource class (e.g., in `app/Mcp/Resources/UserProfileResource.php`):

    ```php
    namespace App\Mcp\Resources;

    use Bramato\LaravelMcpServer\Mcp\Interfaces\ResourceInterface;
    use Illuminate\Support\Facades\Auth;

    class UserProfileResource implements ResourceInterface
    {
        public function getUri(): string { return 'user://profile'; } // Unique URI for this resource
        public function getName(): string { return 'User Profile'; }
        public function getDescription(): string { return 'Provides information about the authenticated user.'; }
        public function getMimeType(): string { return 'application/json'; }

        public function getContents(): mixed
        {
            $user = Auth::user(); // Example: Get authenticated user
            if (!$user) {
                return null; // Or throw an exception mapped to an error
            }
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ];
        }
    }
    ```

2.  Register it in `config/mcp.php`:

    ```php
    // config/mcp.php
    return [
        // ... other config
        'resources' => [
            \App\Mcp\Resources\UserProfileResource::class,
        ],
        // ...
    ];
    ```

**Example: Creating a Tool**

1.  Create your Tool class (e.g., in `app/Mcp/Tools/SendNotificationTool.php`):

    ```php
    namespace App\Mcp\Tools;

    use Bramato\LaravelMcpServer\Mcp\Interfaces\ToolInterface;
    // use App\Jobs\ProcessNotificationJob; // Example Job
    use Illuminate\Support\Facades\Log;

    class SendNotificationTool implements ToolInterface
    {
        public function getName(): string { return 'sendNotification'; }
        public function getDescription(): string { return 'Sends a notification to a user.'; }

        public function getInputSchema(): array
        {
            // Define expected parameters using JSON Schema format
            return [
                'type' => 'object',
                'properties' => [
                    'user_id' => ['type' => 'integer', 'description' => 'ID of the target user'],
                    'message' => ['type' => 'string', 'description' => 'The notification message content'],
                ],
                'required' => ['user_id', 'message'],
            ];
        }

        public function execute(array $arguments): mixed
        {
            Log::info('Executing sendNotification tool', $arguments);
            // ** Add your logic here **
            // Example: Validate input, find user, dispatch a Job
            // ProcessNotificationJob::dispatch($arguments['user_id'], $arguments['message']);
            return ['status' => 'queued', 'user_id' => $arguments['user_id']];
        }
    }
    ```

2.  Register it in `config/mcp.php`:

    ```php
    // config/mcp.php
    return [
        // ... other config
        'tools' => [
            \App\Mcp\Tools\SendNotificationTool::class,
        ],
    ];
    ```

## Usage

How to run the server depends on the enabled transport:

-   **Stdio**: Run the Artisan command: `php artisan mcp:server --transport=stdio`.
-   **HTTP**: Ensure your web server (e.g., `php artisan serve` or Nginx/Apache) is running. Send JSON-RPC POST requests to the path configured in `mcp.transports.http.path` (default: `/mcp-rpc`).
-   **WebSocket (Reverb)**:
    1.  Install and configure Laravel Reverb in your main application: `php artisan reverb:install`.
    2.  Ensure the `websocket` transport is listed in `enabled_transports` in `config/mcp.php`.
    3.  Start the Reverb server: `php artisan reverb:start`.
    4.  Connect your MCP WebSocket client to the Reverb server endpoint (check your Reverb configuration).

## Testing

The package includes a basic test suite using PHPUnit and Orchestra Testbench.

```bash
# From the package directory (packages/bramato/laravel-mcp-server)
../../vendor/bin/phpunit

# Or from the project root
./vendor/bin/phpunit packages/bramato/laravel-mcp-server/tests
```

## Security Considerations

The MCP specification does not define authentication or authorization mechanisms between client and server. This package provides:

-   **Tool Whitelisting**: Only tools explicitly listed in the `tools` array in `config/mcp.php` can be executed.
-   **Basic Token Authentication**: You can enable token-based authentication via the `authentication` config section.

**Important**: It is **your responsibility** to implement fine-grained authorization logic within your `ToolInterface::execute` and `ResourceInterface::getContents` methods. Use Laravel's Gates and Policies or other authorization mechanisms to ensure that the requesting client has the necessary permissions to access specific resources or execute specific tools.

## Contributing

Please see `CONTRIBUTING.md` for details (if available).

## License

The MIT License (MIT). Please see the [LICENSE.md](LICENSE.md) file for more information.
