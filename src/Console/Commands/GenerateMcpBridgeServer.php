<?php

namespace Vendor\Package\Console\Commands; // Sostituisci Vendor\Package con il tuo namespace

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class GenerateMcpBridgeServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:generate-bridge {--force : Overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the Python MCP bridge server script based on the configuration.';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Create a new command instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Generating Python MCP Bridge Server...');

        $config = config('mcp_bridge');

        if (! $config) {
            $this->error('MCP bridge configuration file (config/mcp_bridge.php) not found or empty.');
            return Command::FAILURE;
        }

        $outputFile = $config['server']['output_file'] ?? base_path('mcp_bridge_server.py');
        $requirementsFile = $config['server']['requirements_file'] ?? base_path('mcp_bridge_requirements.txt');
        $envFile = $config['server']['env_file'] ?? base_path('.env.mcp_bridge');

        // Check if files exist and handle --force option
        if (! $this->option('force')) {
            if ($this->files->exists($outputFile)) {
                $this->error("Output file [{$outputFile}] already exists. Use --force to overwrite.");
                return Command::FAILURE;
            }
            if ($this->files->exists($requirementsFile)) {
                $this->error("Requirements file [{$requirementsFile}] already exists. Use --force to overwrite.");
                return Command::FAILURE;
            }
            if ($this->files->exists($envFile)) {
                $this->error("Env file [{$envFile}] already exists. Use --force to overwrite.");
                return Command::FAILURE;
            }
        }

        // --- Placeholder for Generation Logic ---
        $pythonScriptContent = $this->generatePythonScript($config);
        $requirementsContent = $this->generateRequirementsContent();
        $envContent = $this->generateEnvContent($config);
        // --- End Placeholder ---

        // Write files
        $this->files->put($outputFile, $pythonScriptContent);
        $this->files->put($requirementsFile, $requirementsContent);
        $this->files->put($envFile, $envContent);

        $this->info("Python MCP Bridge Server generated successfully!");
        $this->comment("Script: {$outputFile}");
        $this->comment("Requirements: {$requirementsFile}");
        $this->comment("Environment file: {$envFile}");
        $this->warn("Remember to:");
        $this->warn("1. Fill in the required values in {$envFile}");
        $this->warn("2. Create a Python virtual environment and run: pip install -r {$requirementsFile}");
        $this->warn("3. Run the Python server: python {$outputFile}");

        return Command::SUCCESS;
    }

    /**
     * Generate the content for the Python script.
     *
     * @param array $config
     * @return string
     */
    protected function generatePythonScript(array $config): string
    {
        // Load the template/stub content
        // $stub = $this->files->get(__DIR__.'/stubs/mcp_bridge_server.py.stub');

        // TODO: Implement template loading and tool generation logic
        $stub = $this->getPythonStub(); // Placeholder for now

        $toolsCode = $this->generateToolsCode($config['tools'] ?? [], $config);

        return str_replace(
            ['# {{MCP_TOOLS}}', '# {{SERVER_NAME}}', '# {{LARAVEL_BASE_URL_ENV}}', '# {{AUTH_SETTINGS}}'],
            [
                $toolsCode,
                $config['server']['name'] ?? 'LaravelMcpBridge',
                $config['laravel_base_url_env'] ?? 'LARAVEL_APP_URL',
                json_encode($config['authentication'] ?? ['enabled' => false]) // Pass auth settings to Python helper
            ],
            $stub
        );
    }

    /**
     * Generate the code for all the MCP tools.
     *
     * @param array $toolsConfig
     * @param array $globalConfig
     * @return string
     */
    protected function generateToolsCode(array $toolsConfig, array $globalConfig): string
    {
        $code = [];
        foreach ($toolsConfig as $toolName => $toolConfig) {
            $code[] = $this->generateSingleToolCode($toolName, $toolConfig, $globalConfig);
        }
        return implode("\n\n", $code);
    }

    /**
     * Generate the code for a single MCP tool function.
     *
     * @param string $toolName
     * @param array $toolConfig
     * @param array $globalConfig
     * @return string
     */
    protected function generateSingleToolCode(string $toolName, array $toolConfig, array $globalConfig): string
    {
        $description = addslashes($toolConfig['description'] ?? 'No description provided.');
        $parameters = $toolConfig['parameters'] ?? [];
        $handler = $toolConfig['laravel_handler'] ?? [];
        $authRequired = $handler['auth_required'] ?? ($globalConfig['authentication']['enabled'] ?? false);

        $pyParams = [];
        $pyArgsDoc = [];
        $pyArgsMapping = []; // For passing to the helper function

        foreach ($parameters as $paramName => $paramConfig) {
            $pyType = $this->mapPhpTypeToPython($paramConfig['type']);
            $pyParam = $paramName . ': ' . $pyType;
            if (!($paramConfig['required'] ?? true)) {
                $defaultValue = $paramConfig['default'] ?? null;
                $pyParam .= ' = ' . $this->formatPythonDefaultValue($defaultValue, $pyType);
            }
            $pyParams[] = $pyParam;
            $pyArgsDoc[] = "        {$paramName} ({$pyType}): {" . ($paramConfig['description'] ?? 'No description') . "}";
            $pyArgsMapping[$paramName] = $paramName; // Simple 1:1 mapping for now
        }

        $pyParamsStr = implode(', ', $pyParams);
        $pyArgsDocStr = implode("\n", $pyArgsDoc);

        $handlerConfigJson = json_encode($handler, JSON_UNESCAPED_SLASHES);
        $argsMappingJson = json_encode($pyArgsMapping, JSON_UNESCAPED_SLASHES);

        $toolCode = <<<PYTHON
@mcp.tool()
def {$toolName}({$pyParamsStr}) -> Dict[str, Any]:
    """
    {$description}

    Args:
{$pyArgsDocStr}

    Returns:
        Dict[str, Any]: The response from the Laravel API.
    """
    handler_config = {$handlerConfigJson}
    args = {$argsMappingJson}
    auth_required = {$this->formatPythonBool($authRequired)}

    # Prepare arguments for the helper function
    call_args = {{k: locals()[v] for k, v in args.items()}}

    return call_laravel_api(handler_config, call_args, auth_required)
PYTHON;

        return $toolCode;
    }

    /**
     * Generate the content for the requirements.txt file.
     *
     * @return string
     */
    protected function generateRequirementsContent(): string
    {
        return <<<'TXT'
mcp[cli]>=1.2.0 # Or specify your desired version
requests>=2.0
python-dotenv>=1.0
TXT;
    }

    /**
     * Generate the content for the .env.mcp_bridge file.
     *
     * @param array $config
     * @return string
     */
    protected function generateEnvContent(array $config): string
    {
        $envLines = [];
        $laravelUrlEnv = $config['laravel_base_url_env'] ?? 'LARAVEL_APP_URL';
        $authTokenEnv = $config['authentication']['env_variable'] ?? 'MCP_BRIDGE_API_TOKEN';

        // Get the actual URL from Laravel's configuration
        $laravelUrlValue = config('app.url', 'http://localhost'); // Fallback to http://localhost if not set

        $envLines[] = "# --- MCP Bridge Server Environment Variables ---";
        // Use the value from config('app.url')
        $envLines[] = "{$laravelUrlEnv}={$laravelUrlValue} # Populated from Laravel config('app.url')";

        if ($config['authentication']['enabled'] ?? false) {
            $envLines[] = "{$authTokenEnv}= # ADD your generated Laravel API token here";
        }

        return implode("\n", $envLines) . "\n";
    }

    /**
     * Get the Python script stub content.
     *
     * @return string
     */
    protected function getPythonStub(): string
    {
        // In a real scenario, load this from a .stub file
        return <<<'PYTHON'
# -*- coding: utf-8 -*-
"""Generated Python MCP Bridge Server."""

import os
import json
import requests
from dotenv import load_dotenv
from typing import Dict, Any, Optional
from mcp.server.fastmcp import FastMCP

# --- Configuration --- #
SERVER_NAME = "# {{SERVER_NAME}}"
LARAVEL_BASE_URL_ENV = "# {{LARAVEL_BASE_URL_ENV}}"
AUTH_SETTINGS_JSON = '# {{AUTH_SETTINGS}}'

# Load environment variables from .env.mcp_bridge (or .env)
env_path = os.path.join(os.path.dirname(__file__), '.env.mcp_bridge')
if not os.path.exists(env_path):
    env_path = os.path.join(os.path.dirname(__file__), '.env')
load_dotenv(dotenv_path=env_path)

LARAVEL_BASE_URL = os.getenv(LARAVEL_BASE_URL_ENV)
AUTH_SETTINGS = json.loads(AUTH_SETTINGS_JSON)
API_TOKEN_ENV = AUTH_SETTINGS.get('env_variable', 'MCP_BRIDGE_API_TOKEN')
API_TOKEN = os.getenv(API_TOKEN_ENV) if AUTH_SETTINGS.get('enabled', False) else None
AUTH_HEADER = AUTH_SETTINGS.get('header', 'Authorization')
AUTH_PREFIX = AUTH_SETTINGS.get('prefix', 'Bearer ')

if not LARAVEL_BASE_URL:
    print(f"Error: Environment variable {LARAVEL_BASE_URL_ENV} is not set.")
    exit(1)

if AUTH_SETTINGS.get('enabled', False) and not API_TOKEN:
    print(f"Warning: Authentication is enabled, but environment variable {API_TOKEN_ENV} is not set.")

# Create the MCP server instance
mcp = FastMCP(SERVER_NAME, dependencies=["requests", "python-dotenv"])

# --- Helper Function for API Calls --- #
def call_laravel_api(handler_config: Dict[str, Any], args: Dict[str, Any], auth_required: bool) -> Dict[str, Any]:
    """Makes a call to the configured Laravel API endpoint."""
    method = handler_config.get('method', 'GET').upper()
    handler_type = handler_config.get('type', 'url')
    handler_value = handler_config.get('value')
    mapping = handler_config.get('parameter_mapping', {})

    if not handler_value:
        return {"error": "Missing 'value' in laravel_handler config"}

    url = ""
    route_params = {}
    query_params = {}
    json_body = {}

    # Build URL
    if handler_type == 'route':
        # Limitation: We can't resolve route names directly in Python.
        # We assume the 'value' is a path relative to the base URL,
        # potentially with placeholders like {id}.
        path = handler_value # Use the route name as a path template
        for laravel_param, python_param in mapping.items():
            if python_param in args:
                 # Check if it's a route parameter placeholder in the path
                placeholder = "{" + laravel_param + "}"
                if placeholder in path:
                    path = path.replace(placeholder, str(args[python_param]))
                    route_params[laravel_param] = args[python_param]
        url = LARAVEL_BASE_URL.rstrip('/') + '/' + path.lstrip('/')
    elif handler_type == 'url':
        url = LARAVEL_BASE_URL.rstrip('/') + '/' + handler_value.lstrip('/')
    else:
         return {"error": f"Unsupported laravel_handler type: {handler_type}"}

    # Prepare parameters/body
    for laravel_key, python_param in mapping.items():
        if python_param in args and laravel_key not in route_params:
             # If not a route param, it's query or body
            if method in ['GET', 'DELETE', 'HEAD', 'OPTIONS']:
                query_params[laravel_key] = args[python_param]
            else: # POST, PUT, PATCH
                json_body[laravel_key] = args[python_param]

    # Prepare headers
    headers = {
        'Accept': 'application/json',
        'Content-Type': 'application/json' # Assume JSON for POST/PUT/PATCH
    }
    if auth_required and API_TOKEN:
        headers[AUTH_HEADER] = AUTH_PREFIX + API_TOKEN
    elif auth_required and not API_TOKEN:
         print(f"Warning: Auth required for {method} {url} but token is missing.")
         # Optionally return an error here
         # return {"error": "Authentication required but token is missing"}

    # Make the request
    try:
        print(f"Calling Laravel API: {method} {url}") # For debugging
        response = requests.request(
            method=method,
            url=url,
            headers=headers,
            params=query_params if query_params else None,
            json=json_body if json_body else None,
            timeout=30 # Add a timeout
        )
        response.raise_for_status() # Raise HTTPError for bad responses (4xx or 5xx)

        # Try to parse JSON, return text if not possible
        try:
            return response.json()
        except json.JSONDecodeError:
            return {"response_text": response.text}

    except requests.exceptions.RequestException as e:
        print(f"Error calling Laravel API: {e}")
        # Try to get more info from response if available
        error_details = str(e)
        if e.response is not None:
            try:
                error_details = e.response.json()
            except json.JSONDecodeError:
                 error_details = e.response.text
        return {
            "error": f"Failed to call Laravel API: {method} {url}",
            "details": error_details,
            "status_code": e.response.status_code if e.response is not None else None
         }

# --- Generated MCP Tools --- #

# {{MCP_TOOLS}}

# --- Run the Server --- #
if __name__ == "__main__":
    print(f"Starting MCP Server '{SERVER_NAME}'...")
    print(f"Bridging to Laravel API at: {LARAVEL_BASE_URL}")
    print(f"Authentication: {'Enabled' if AUTH_SETTINGS.get('enabled') else 'Disabled'}")
    if not LARAVEL_BASE_URL:
        print("Error: Laravel base URL not configured. Set the {LARAVEL_BASE_URL_ENV} environment variable.")
    else:
        mcp.run(transport='stdio') # Use 'stdio' for Claude Desktop
PYTHON;
    }

    /**
     * Map PHP/Simple types to Python types.
     *
     * @param string $type
     * @return string
     */
    protected function mapPhpTypeToPython(string $type): string
    {
        return match (strtolower($type)) {
            'int', 'integer' => 'int',
            'float', 'double', 'real' => 'float',
            'bool', 'boolean' => 'bool',
            'array' => 'list', // Default to list, could be Dict too
            'object' => 'Dict[str, Any]',
            'string' => 'str',
            default => 'Any' // Fallback
        };
    }

    /**
     * Format a default value for Python code.
     *
     * @param mixed $value
     * @param string $pyType
     * @return string
     */
    protected function formatPythonDefaultValue(mixed $value, string $pyType): string
    {
        if (is_null($value)) {
            return 'None';
        }
        return match ($pyType) {
            'int', 'float' => $value, // Numbers don't need quotes
            'bool' => $this->formatPythonBool($value),
            'str' => "'" . addslashes($value) . "'",
            'list', 'Dict[str, Any]' => json_encode($value), // Represent arrays/objects as JSON strings
            default => 'None', // Fallback for Any or unknown
        };
    }

    /**
     * Format a boolean value for Python code.
     *
     * @param mixed $value
     * @return string
     */
    protected function formatPythonBool(mixed $value): string
    {
        return $value ? 'True' : 'False';
    }
}
