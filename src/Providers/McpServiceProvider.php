<?php

namespace Bramato\LaravelMcpServer\Providers;

use Illuminate\Support\ServiceProvider;
use Bramato\LaravelMcpServer\Mcp\Interfaces\ResourceInterface;
use Bramato\LaravelMcpServer\Mcp\Interfaces\ServerInterface;
use Bramato\LaravelMcpServer\Mcp\Interfaces\ToolInterface;
use Bramato\LaravelMcpServer\Mcp\Server;
use Sajya\Server as SajyaRpcServer; // Alias per Sajya\Server
use Illuminate\Support\Facades\Event;
use Laravel\Reverb\Events\MessageReceived;
use Bramato\LaravelMcpServer\Listeners\HandleReverbMessage;
use Illuminate\Support\Facades\Log;

class McpServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/mcp.php',
            'mcp'
        );

        // Registra l'handler JSON-RPC (Sajya)
        $this->app->singleton(SajyaRpcServer::class, function ($app) {
            // Qui si potrebbero passare opzioni da config('mcp.rpc_handler_options') a Sajya
            return new SajyaRpcServer();
        });

        // Registra la nostra implementazione del Server MCP
        $this->app->singleton(ServerInterface::class, function ($app) {
            $server = new Server(
                $app->make(SajyaRpcServer::class)
            );

            // Registra Resources e Tools dalla configurazione
            $config = $app['config']['mcp'];

            foreach ($config['resources'] ?? [] as $resourceClass) {
                if (class_exists($resourceClass) && is_subclass_of($resourceClass, ResourceInterface::class)) {
                    $server->registerResource($app->make($resourceClass));
                }
            }

            foreach ($config['tools'] ?? [] as $toolClass) {
                if (class_exists($toolClass) && is_subclass_of($toolClass, ToolInterface::class)) {
                    $server->registerTool($app->make($toolClass));
                }
            }

            return $server;
        });

        // Registra alias per facilità d'uso (opzionale)
        $this->app->alias(ServerInterface::class, 'mcp.server');

        // Registra i comandi Artisan
        $this->commands([
            \Bramato\LaravelMcpServer\Console\Commands\McpServerCommand::class,
        ]);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/mcp.php' => config_path('mcp.php'),
            ], 'config');

            // Pubblicazione di Routes, Migrations, Views se necessario in futuro
            // $this->publishes([...], 'routes');
        }

        // Caricamento route (se si implementa trasporto HTTP)
        if (in_array('http', config('mcp.enabled_transports', []))) {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/mcp.php');
        }

        // Registrazione listener eventi Reverb (solo se websocket è abilitato)
        if (in_array('websocket', config('mcp.enabled_transports', []))) {
            if (class_exists(MessageReceived::class)) {
                Event::listen(
                    MessageReceived::class,
                    HandleReverbMessage::class
                );

                Log::debug('MCP Service Provider: Registered Reverb MessageReceived listener.');
            } else {
                Log::warning('MCP Service Provider: WebSocket transport enabled, but Reverb event class not found. Is Reverb installed and discovered?');
            }
        }
    }
}
