<?php

namespace Bramato\LaravelMcpServer\Console\Commands;

use Illuminate\Console\Command;
use Bramato\LaravelMcpServer\Mcp\Interfaces\ServerInterface;

class McpListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List registered MCP resources and tools';

    /**
     * Execute the console command.
     *
     * @param ServerInterface $mcpServer
     * @return int
     */
    public function handle(ServerInterface $mcpServer): int
    {
        $this->info('Registered MCP Resources:');

        $resources = $mcpServer->getResources();

        if (empty($resources)) {
            $this->line('  No resources registered.');
        } else {
            $resourceTable = [];
            foreach ($resources as $uri => $resource) {
                $resourceTable[] = [
                    'URI' => $uri,
                    'Name' => $resource->getName(),
                    'Class' => get_class($resource),
                    'Description' => $resource->getDescription(),
                ];
            }
            $this->table(['URI', 'Name', 'Class', 'Description'], $resourceTable);
        }

        $this->newLine();
        $this->info('Registered MCP Tools:');

        $tools = $mcpServer->getTools();

        if (empty($tools)) {
            $this->line('  No tools registered.');
        } else {
            $toolTable = [];
            foreach ($tools as $name => $tool) {
                $toolTable[] = [
                    'Name' => $name,
                    'Class' => get_class($tool),
                    'Description' => $tool->getDescription(),
                ];
            }
            $this->table(['Name', 'Class', 'Description'], $toolTable);
        }

        return Command::SUCCESS;
    }
}
