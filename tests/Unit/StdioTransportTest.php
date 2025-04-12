<?php

namespace Bramato\LaravelMcpServer\Tests\Unit;

use Bramato\LaravelMcpServer\Tests\TestCase;
use Bramato\LaravelMcpServer\Transport\StdioTransport;

class StdioTransportTest extends TestCase
{
    private $inputStream;
    private $outputStream;
    private $transport;

    protected function setUp(): void
    {
        parent::setUp();

        // Usare stream in memoria per simulare stdin/stdout
        $this->inputStream = fopen('php://memory', 'r+');
        $this->outputStream = fopen('php://memory', 'r+');

        // "Sovrascrivere" fopen per restituire i nostri stream in memoria
        // Questo richiede un approccio più avanzato (mocking di funzioni globali o stream wrapper)
        // Per ora, modifichiamo l'istanza dopo la creazione (meno pulito)
        $this->transport = new StdioTransport();

        // Riflessione per accedere alle proprietà protette stdin/stdout e sostituirle
        $reflector = new \ReflectionClass(StdioTransport::class);

        $stdinProp = $reflector->getProperty('stdin');
        $stdinProp->setAccessible(true);
        fclose($stdinProp->getValue($this->transport)); // Chiudi lo stream reale aperto dal costruttore
        $stdinProp->setValue($this->transport, $this->inputStream);

        $stdoutProp = $reflector->getProperty('stdout');
        $stdoutProp->setAccessible(true);
        fclose($stdoutProp->getValue($this->transport)); // Chiudi lo stream reale aperto dal costruttore
        $stdoutProp->setValue($this->transport, $this->outputStream);
    }

    protected function tearDown(): void
    {
        // Assicura che gli stream mock siano chiusi anche se il test fallisce
        if (is_resource($this->inputStream)) {
            fclose($this->inputStream);
        }
        if (is_resource($this->outputStream)) {
            fclose($this->outputStream);
        }
        parent::tearDown();
    }

    /** @test */
    public function it_can_write_and_read_a_message()
    {
        $message = ['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1];
        $expectedJson = json_encode($message) . "\n";

        // Scrivi nello stream di input simulato (come se provenisse da stdin)
        fwrite($this->inputStream, $expectedJson);
        rewind($this->inputStream); // Torna all'inizio dello stream per la lettura

        // Leggi il messaggio tramite il transport
        $readMessage = $this->transport->readMessage();

        $this->assertEquals($message, $readMessage);

        // Ora testa la scrittura
        $responseMessage = ['jsonrpc' => '2.0', 'result' => 'ok', 'id' => 1];
        $expectedOutputJson = json_encode($responseMessage) . "\n";

        $this->transport->writeMessage($responseMessage);

        // Leggi dallo stream di output simulato per verificare
        rewind($this->outputStream);
        $output = stream_get_contents($this->outputStream);

        $this->assertEquals($expectedOutputJson, $output);
    }

    /** @test */
    public function it_handles_json_parse_error_on_read()
    {
        $invalidJson = "{\"jsonrpc\": \"2.0\", \"method\": \"test\", \"id\": }"; // Errore di sintassi
        fwrite($this->inputStream, $invalidJson . "\n");
        rewind($this->inputStream);

        // Leggi il messaggio - dovrebbe restituire null e loggare/scrivere errore
        $readMessage = $this->transport->readMessage();
        $this->assertNull($readMessage);

        // Verifica che un errore JSON-RPC sia stato scritto sull'output
        rewind($this->outputStream);
        $output = stream_get_contents($this->outputStream);
        $this->assertNotEmpty($output);

        $errorResponse = json_decode($output, true);
        $this->assertIsArray($errorResponse);
        $this->assertEquals('2.0', $errorResponse['jsonrpc'] ?? null);
        $this->assertNull($errorResponse['id'] ?? null);
        $this->assertIsArray($errorResponse['error'] ?? null);
        $this->assertEquals(-32700, $errorResponse['error']['code'] ?? null); // Parse error
        $this->assertEquals('Parse error', $errorResponse['error']['message'] ?? null);
    }

    /** @test */
    public function it_returns_null_on_end_of_file()
    {
        // Non scrivere nulla nello stream di input
        rewind($this->inputStream);

        // Tentare di leggere dovrebbe restituire null (EOF)
        $readMessage = $this->transport->readMessage();
        $this->assertNull($readMessage);
    }
}
