<?php

namespace Bramato\LaravelMcpServer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class McpConfigCursorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:config:cursor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate MCP client configuration JSON for Cursor (HTTP transport)';

    /**
     * Execute the console command.
     *
     * @param ConfigRepository $config
     * @return int
     */
    public function handle(ConfigRepository $config): int
    {
        $enabledTransports = $config->get('mcp.enabled_transports', []);
        $httpPath = $config->get('mcp.transports.http.path', '/mcp-rpc');
        $authDriver = $config->get('mcp.authentication.driver', 'none');
        $authTokenHeader = $config->get('mcp.authentication.options.header', 'Authorization');
        $appName = $config->get('app.name', 'Laravel');
        $appUrl = $config->get('app.url');

        if (!in_array('http', $enabledTransports)) {
            $this->error('HTTP transport is not enabled in config/mcp.php (enabled_transports).');
            $this->line('Please enable the HTTP transport to generate a configuration for Cursor.');
            return Command::FAILURE;
        }

        if (empty($appUrl)) {
            $this->warn('APP_URL is not set in your .env file. Using http://localhost:8000 as a default.');
            $this->warn('Please set APP_URL for the correct endpoint URL.');
            $appUrl = 'http://localhost:8000';
        }

        // Ensure path starts with a slash and URL doesn't end with one
        $httpPath = '/' . ltrim($httpPath, '/');
        $endpointUrl = rtrim($appUrl, '/') . $httpPath;

        $cursorConfig = [
            // Suggest a name for the configuration entry in Cursor
            'name' => $appName . ' MCP (HTTP)',
            'protocol' => 'mcp',
            'transport' => [
                'type' => 'http',
                'url' => $endpointUrl,
            ],
        ];

        if ($authDriver === 'token') {
            $cursorConfig['transport']['authentication'] = [
                'type' => 'token',
                // Assuming Cursor expects the header name
                'header' => $authTokenHeader,
                // Add a placeholder for the actual token, user needs to fill this
                'token' => 'YOUR_AUTH_TOKEN_HERE'
            ];
            $this->warn('Token authentication is enabled. Remember to replace YOUR_AUTH_TOKEN_HERE in the JSON with your actual token.');
        }

        $this->line('Copy the following JSON and add it to your Cursor MCP client configurations:');
        $this->newLine();

        // Output the JSON directly to the console
        $this->output->writeln(json_encode($cursorConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
    }
}
