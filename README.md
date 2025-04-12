# Laravel MCP Server Package (bramato/laravel-mcp-server)

A base Laravel package for building [Model Context Protocol (MCP)](https://gomcp.org/) servers.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/bramato/laravel-mcp-server.svg?style=flat-square)](https://packagist.org/packages/bramato/laravel-mcp-server)
[![Total Downloads](https://img.shields.io/packagist/dt/bramato/laravel-mcp-server.svg?style=flat-square)](https://packagist.org/packages/bramato/laravel-mcp-server)

<!-- Add build status, code coverage etc. later -->

This package provides the basic infrastructure to handle MCP requests over different transports (Stdio, HTTP, WebSocket via Reverb) using the [sajya/server](https://github.com/sajya/server) library for JSON-RPC processing.

You need to implement your own `ResourceInterface` and `ToolInterface` classes (using the `Bramato\LaravelMcpServer\Mcp\Interfaces` namespace - **o `Bramato\...` se scegliamo Opzione A**) and register them in the configuration to expose your application's data and functionalities.

## Installation

You can install the package via composer:

```bash
composer require bramato/laravel-mcp-server
```

Next, you should publish the configuration file:

```bash
php artisan vendor:publish --provider="Bramato\LaravelMcpServer\Providers\McpServiceProvider" --tag="config"
# Nota: Il provider FQNS rimarrebbe McpLaravel... se scegliamo Opzione B
# Altrimenti cambierebbe in "Bramato\LaravelMcpServer\Providers\McpServiceProvider"
```

This will create a `config/mcp.php` file in your application's config directory.

## Configuration

The `config/mcp.php` file allows you to configure:

-   **Enabled Transports**: Choose which transports (`stdio`, `http`, `websocket`) are active.
-   **Transport Settings**: Configure details like the HTTP path.
-   **Authentication**: Basic authentication setup (currently supports `none` or `token`).
-   **Resources**: Register your classes implementing `Bramato\LaravelMcpServer\Mcp\Interfaces\ResourceInterface`. (**o `Bramato\...`**)
-   **Tools**: Register your classes implementing `Bramato\LaravelMcpServer\Mcp\Interfaces\ToolInterface`. This acts as a whitelist. (**o `Bramato\...`**)
-   **Logging**: Configure logging channel and level.

**Example: Registering a Tool**

1.  Create your Tool class:

    ```php
    // app/Mcp/Tools/MyTool.php
    namespace App\Mcp\Tools;

    use Bramato\LaravelMcpServer\Mcp\Interfaces\ToolInterface; // O Bramato\...

    class MyTool implements ToolInterface
    {
        public function getName(): string { return 'myCoolTool'; }
        public function getDescription(): string { return 'Does something cool.'; }
        public function getInputSchema(): array { return [/* JSON Schema */]; }
        public function execute(array $arguments): mixed { /* Your logic here */ return ['status' => 'done']; }
    }
    ```

2.  Register it in `config/mcp.php`:

    ```php
    // config/mcp.php
    return [
        // ... other config
        'tools' => [
            \App\Mcp\Tools\MyTool::class,
        ],
    ];
    ```

## Usage

-   **Stdio**: Run `php artisan mcp:server --transport=stdio`.
-   **HTTP**: Send POST requests to the path configured in `mcp.transports.http.path` (default: `/mcp-rpc`). Ensure your web server (e.g., `php artisan serve`) is running.
-   **WebSocket (Reverb)**:
    -   Install and configure Reverb: `php artisan reverb:install`.
    -   Enable the `websocket` transport in `config/mcp.php`.
    -   Start the Reverb server: `php artisan reverb:start`.
    -   Connect your WebSocket client to the Reverb server endpoint.

## Testing

```bash
# From the package directory
../../vendor/bin/phpunit

# Or from the project root
./vendor/bin/phpunit packages/bramato/laravel-mcp-server/tests
```

## Security

The MCP specification does not define authentication or authorization mechanisms between client and server. This package provides:

-   Tool whitelisting via the `tools` array in the configuration.
-   Basic token authentication (configure `authentication.driver` to `token` and `authentication.options.header`).

**It is your responsibility to implement fine-grained authorization** within your `ToolInterface::execute` and `ResourceInterface::getContents` methods, potentially using Laravel's Gates and Policies.

## Contributing

Please see CONTRIBUTING for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
