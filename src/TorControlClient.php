<?php

declare(strict_types=1);

namespace TorPHP;

use TorPHP\Exception\SocketException;
use TorPHP\Helper\PrivateKeyHelper;
use TorPHP\Model\Circuit;
use TorPHP\Model\OnionService;
use TorPHP\Model\PortMapping;
use TorPHP\Transport\TorSocketClient;

/**
 * A client for interacting with Tor via the ControlPort.
 *
 * @author Edouard Courty
 */
class TorControlClient
{
    private const string SUCCESS_RESPONSE_PREFIX = '250';

    private const string DEFAULT_HOST = '127.0.0.1';
    private const int DEFAULT_PORT = 9051;
    private const float DEFAULT_TIMEOUT = 30.0;

    private readonly TorSocketClient $socketClient;

    public function __construct(
        string $host = self::DEFAULT_HOST,
        int $port = self::DEFAULT_PORT,
        float $timeout = self::DEFAULT_TIMEOUT,
        #[\SensitiveParameter] private readonly ?string $password = null,
        #[\SensitiveParameter] private readonly ?string $authenticationCookie = null,
    ) {
        $this->socketClient = new TorSocketClient(host: $host, port: $port, timeout: $timeout);
        $this->socketClient->connect();

        $this->authenticate();
    }

    /**
     * Helper method to assert the response from Tor ControlPort.
     * Returns the response string if successful.
     */
    private function assertSuccessResponse(): string
    {
        $response = $this->socketClient->readLine();

        if ($response === null || str_starts_with($response, self::SUCCESS_RESPONSE_PREFIX) === false) {
            $message = \sprintf(
                'Unexpected response from Tor ControlPort: %s',
                $response ?? 'No response',
            );
            throw new SocketException(message: $message);
        }

        return $response;
    }

    /**
     * Helper method to authenticate against the Tor ControlPort using password or cookie.
     */
    private function authenticate(): void
    {
        if ($this->password !== null) {
            $cmd = \sprintf("AUTHENTICATE \"%s\"\r\n", addslashes($this->password));
        } elseif ($this->authenticationCookie !== null) {
            // Check if cookie file is available and readable
            if (file_exists($this->authenticationCookie) === false || is_readable($this->authenticationCookie) === false) {
                throw new SocketException(
                    message: \sprintf('Authentication cookie file "%s" not found or not readable', $this->authenticationCookie),
                );
            }

            // Get the cookie file content
            $cookieContent = file_get_contents($this->authenticationCookie);
            if ($cookieContent === false) {
                throw new SocketException(
                    message: \sprintf('Failed to read authentication cookie file "%s"', $this->authenticationCookie),
                );
            }

            $cookieHex = bin2hex($cookieContent);
            $cmd = \sprintf("AUTHENTICATE %s\r\n", $cookieHex);
        } else {
            $cmd = "AUTHENTICATE\r\n";
        }

        $this->socketClient->write($cmd);
        $this->assertSuccessResponse();
    }

    /**
     * Subscribes to given control events.
     *
     * @param string[] $events
     */
    public function setEvents(array $events): void
    {
        $list = implode(' ', $events);

        $this->socketClient->write(\sprintf("SETEVENTS %s\r\n", $list));
        $this->assertSuccessResponse();
    }

    /**
     * Signals Tor to build a new circuit (NEWNYM).
     */
    public function signalNewnym(): void
    {
        $this->socketClient->write("SIGNAL NEWNYM\r\n");
        $this->assertSuccessResponse();
    }

    /**
     * Blocks until at least one CIRC BUILT event is received, or times out.
     */
    public function waitForCircuitBuild(int $timeoutSeconds = 15): void
    {
        $start = time();

        while (time() - $start < $timeoutSeconds) {
            $line = $this->socketClient->readLine();

            if ($line !== null && preg_match('/^650 CIRC .* BUILT/', mb_trim($line))) {
                return;
            }
        }

        throw new SocketException(message: 'Timed out waiting for new circuit build');
    }

    /**
     * Closes the control session.
     */
    public function close(): void
    {
        $this->socketClient->close();
    }

    /**
     * Fetches the current Tor circuits the node has.
     *
     * @return Circuit[]
     */
    public function getCircuits(): array
    {
        $this->socketClient->write("GETINFO circuit-status\r\n");
        $lines = $this->socketClient->readBlock(until: '250 OK');

        $circuits = [];

        foreach ($lines as $line) {
            // Strip any leading "250+" prefix
            $line = (string) preg_replace('/^250\+/', '', $line);

            if (str_starts_with($line, 'circuit-status=')) {
                continue;
            }

            $parts = preg_split('/\s+/', $line, 4) ?: [];

            if (\count($parts) < 4) {
                continue;
            }

            [$id, $status, $pathStr, $metaStr] = $parts;

            $nodes = array_map(
                static fn (string $hop): string => mb_ltrim($hop, '$'),
                explode(',', $pathStr),
            );

            $meta = [];
            if ($metaStr !== '') {
                $pairs = preg_split('/\s+/', $metaStr) ?: [];

                foreach ($pairs as $pair) {
                    if (str_contains($pair, '=') === false) {
                        continue;
                    }

                    [$k, $v] = explode('=', $pair, 2);
                    $meta[$k] = $v;
                }
            }

            $circuits[] = new Circuit(
                id: (int) $id,
                status: $status,
                nodes: $nodes,
                metadata: $meta,
            );
        }

        return $circuits;
    }

    /**
     * Gets a Tor configuration value via GETCONF.
     */
    public function getConfigValue(string $key): string
    {
        $this->socketClient->write(\sprintf("GETCONF %s\r\n", $key));
        $raw = $this->assertSuccessResponse();

        // Remove the "250" prefix from the response
        $cleaned = (string) preg_replace('/^250\s+/', '', $raw);
        $trimmed = mb_trim($cleaned);

        if (str_contains($trimmed, '=') === true) {
            [, $value] = explode('=', $trimmed, 2);

            return $value;
        }

        // If no '=' found, return the raw response
        return $trimmed;
    }

    /**
     * Sets one or more Tor configuration values via SETCONF.
     */
    public function setConfigValue(string $key, string $value): void
    {
        $this->socketClient->write(\sprintf("SETCONF %s=%s\r\n", $key, $value));
        $this->assertSuccessResponse();
    }

    /**
     * Lists configured onion services.
     *
     * @return string[] List of onion service IDs
     */
    public function listOnionServices(): array
    {
        $this->socketClient->write("GETINFO onions/current\r\n");
        $lines = $this->socketClient->readBlock(until: '250 OK');

        foreach ($lines as $line) {
            // Strip any "250-" or "250 " prefix
            $trimmed = (string) preg_replace('/^250[- ]?/', '', $line);

            if (str_starts_with($trimmed, 'onions/current=')) {
                [, $list] = explode('=', $trimmed, 2);

                return $list === '' ? [] : explode(',', $list);
            }
        }

        return [];
    }

    /**
     * Adds (or updates) an onion service.
     *
     * @param array<PortMapping|string> $portMappings Array of PortMapping or this string format ['<remotePort>,<host>:<localPort>', ...]
     * @param string|null               $privateKey   Either null or a base64-encoded private-key (can be prefixed with "ED25519-V3:")
     */
    public function addOnionService(array $portMappings, ?string $privateKey = null): OnionService
    {
        $privateKeyArgument = $privateKey === null
            ? PrivateKeyHelper::NO_PRIVATE_KEY
            : PrivateKeyHelper::parsePrivateKey($privateKey);

        $cmd = 'ADD_ONION ' . $privateKeyArgument . ' Flags=Detach ';
        foreach ($portMappings as $mapping) {
            $mappingData = $mapping instanceof PortMapping
                ? $mapping->toString()
                : PortMapping::fromString($mapping)->toString(); // Checks if the format is correct

            $cmd .= ' Port=' . $mappingData;
        }
        $cmd .= "\r\n";

        $this->socketClient->write($cmd);
        $lines = $this->socketClient->readBlock(until: '250 OK');

        $serviceId = '';
        $returnedPrivateKey = '';

        foreach ($lines as $line) {
            // Strip any leading "250-" or "250 " prefix
            $trimmed = (string) preg_replace('/^250[- ]?/', '', $line);
            if (str_starts_with($trimmed, 'ServiceID=')) {
                [, $serviceId] = explode('=', $trimmed, 2);
            } elseif (str_starts_with($trimmed, 'PrivateKey=')) {
                [, $returnedPrivateKey] = explode('=', $trimmed, 2);
            }
        }

        if ($serviceId === '') {
            throw new SocketException(message: 'Failed to parse ADD_ONION response: missing ServiceID');
        }

        return new OnionService(
            id: $serviceId,
            url: \sprintf('http://%s.onion', $serviceId),
            privateKey: $returnedPrivateKey,
        );
    }

    /**
     * Removes an existing onion service.
     */
    public function deleteOnionService(string $serviceId): void
    {
        $this->socketClient->write(\sprintf('DEL_ONION %s\r\n', $serviceId));
        $this->assertSuccessResponse();
    }
}
