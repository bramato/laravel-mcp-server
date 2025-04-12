<?php

namespace Bramato\LaravelMcpServer\Console\Commands;

use Illuminate\Console\Command;
use Bramato\LaravelMcpServer\Mcp\Interfaces\ServerInterface;
use Bramato\LaravelMcpServer\Transport\StdioTransport;
// Import other transports here when implemented
// use Bramato\LaravelMcpServer\Transport\HttpTransport;
// use Bramato\LaravelMcpServer\Transport\ReverbWebSocketTransport;
use InvalidArgumentException;

class McpServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:server {--transport=stdio : The transport mechanism to use (stdio, http, websocket)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Starts the MCP server using the specified transport';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $transportType = $this->option('transport');

        try {
            $transport = match ($transportType) {
                'stdio' => $this->laravel->make(StdioTransport::class),
                // 'http' => $this->laravel->make(HttpTransport::class), // Placeholder
                // 'websocket' => $this->laravel->make(ReverbWebSocketTransport::class), // Placeholder
                default => throw new InvalidArgumentException("Unsupported transport type: {$transportType}"),
            };

            $this->info("Starting MCP Server with [{$transportType}] transport...");

            /** @var ServerInterface $server */
            $server = $this->laravel->make(ServerInterface::class);

            $server->setTransport($transport);
            $server->run(); // This will block for stdio

            $this->info("MCP Server stopped.");
            return Command::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            $this->line("Supported transports are: stdio"); // Update as more are added
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $this->error("An error occurred while running the MCP server:");
            $this->error($e->getMessage());
            report($e); // Report exception using Laravel helper
            return Command::FAILURE;
        }
    }
}
