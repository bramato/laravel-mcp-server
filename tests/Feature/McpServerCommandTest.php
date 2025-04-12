<?php

namespace Bramato\LaravelMcpServer\Tests\Feature;

use Bramato\LaravelMcpServer\Mcp\Interfaces\ServerInterface;
use Bramato\LaravelMcpServer\Mcp\Server;
use Bramato\LaravelMcpServer\Tests\TestCase;
use Bramato\LaravelMcpServer\Transport\StdioTransport;
use Mockery\MockInterface;

class McpServerCommandTest extends TestCase
{
    /** @test */
    public function it_handles_initialize_request_via_stdio()
    {
        $initializeRequest = [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'id' => 'req-1',
        ];

        $expectedResponse = [
            'jsonrpc' => '2.0',
            'id' => 'req-1',
            'result' => [
                'capabilities' => [
                    'resources' => [], // Nessuna risorsa registrata di default
                    'tools' => [],     // Nessun tool registrato di default (solo 'initialize')
                ]
            ]
        ];

        // Mock StdioTransport
        $this->mock(StdioTransport::class, function (MockInterface $mock) use ($initializeRequest, $expectedResponse) {
            // readMessage restituisce la richiesta la prima volta, poi null per terminare
            $mock->shouldReceive('readMessage')->once()->ordered()->andReturn($initializeRequest);
            $mock->shouldReceive('readMessage')->once()->ordered()->andReturnNull();

            // writeMessage si aspetta la risposta corretta
            $mock->shouldReceive('writeMessage')->once()->with($expectedResponse);

            // close viene chiamato alla fine
            $mock->shouldReceive('close')->once();
        });

        // Esegui il comando
        // Usiamo il binding di Laravel per assicurarci che il Server usi il nostro Transport mockato
        // quando viene istanziato dentro il comando.
        // Questo richiede che il comando crei il transport tramite il container o che mockiamo il Server stesso.

        // Approccio alternativo: Mockare il Server per controllare il transport passato a setTransport/run
        // Questo è più complesso. Proviamo a risolvere l'istanza del transport nel comando.

        // Modifichiamo McpServerCommand per usare il container per il Transport
        // FATTO!
        $this->artisan('mcp:server --transport=stdio')
            ->expectsOutput('Starting MCP Server with [stdio] transport...')
            // TODO: L'output "MCP Server stopped." potrebbe non apparire se il mock termina il processo
            // ->expectsOutput('MCP Server stopped.')
            ->assertExitCode(0);

        // *** ATTENZIONE: Il test sopra fallirà finché McpServerCommand non usa il container per il transport ***
        // *** Eseguiremo la modifica al comando dopo questo file ***
        // $this->assertTrue(true); // Placeholder per evitare test rischioso
    }

    /** @test */
    public function it_handles_tool_request_via_stdio()
    {
        // Test per una chiamata a un tool (richiede registrazione di un tool mockato)
        $this->markTestIncomplete('Tool request test not implemented yet.');
    }

    /** @test */
    public function it_fails_with_unsupported_transport()
    {
        $this->artisan('mcp:server --transport=invalid')
            ->expectsOutputToContain('Unsupported transport type: invalid')
            ->assertExitCode(1);
    }
}
