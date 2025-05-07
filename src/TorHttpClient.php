<?php

declare(strict_types=1);

namespace TorPHP;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * A simple HTTP client that uses Tor as a proxy.
 *
 * @author Edouard Courty
 */
class TorHttpClient implements HttpClientInterface
{
    private const string TOR_DEFAULT_HOST = '127.0.0.1';

    private const int TOR_DEFAULT_PROXY_PORT = 9050;
    private const int TOR_DEFAULT_CONTROL_PORT = 9051;

    private HttpClientInterface $baseHttpClient;
    private HttpClientInterface $httpClient;

    /** @var array<string, mixed> */
    private array $clientOptions;

    /**
     * If both controlPassword and controlAuthenticationCookie are set, the control password will be used.
     *
     * @param HttpClientInterface|null $httpClient Re-use an existing HttpClient instance
     */
    public function __construct(
        private readonly string $host = self::TOR_DEFAULT_HOST,
        private readonly int $port = self::TOR_DEFAULT_PROXY_PORT,
        private readonly int $controlPort = self::TOR_DEFAULT_CONTROL_PORT,
        #[\SensitiveParameter] private readonly ?string $controlPassword = null,
        #[\SensitiveParameter] private readonly ?string $controlAuthenticationCookie = null,
        ?HttpClientInterface $httpClient = null,
    ) {
        $this->baseHttpClient = $httpClient ?? HttpClient::create();
        $this->clientOptions = [
            'proxy' => \sprintf('socks5h://%s:%d', $this->host, $this->port),
        ];

        $this->buildHttpClient();
    }

    /**
     * @param array<string, mixed> $options
     *
     * @see HttpClientInterface::request()
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return $this->httpClient->request($method, $url, $options);
    }

    /**
     * @see HttpClientInterface::stream()
     */
    public function stream(iterable|ResponseInterface $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->httpClient->stream($responses, $timeout);
    }

    /**
     * Create a new TorHttpClient instance with the new options.
     * This method keeps the current TorHttpClient options and merges them with the new ones.
     *
     * @param array<string, mixed> $options
     *
     * @see HttpClientInterface::withOptions()
     */
    public function withOptions(array $options): static
    {
        $defaultOptionsReflection = new \ReflectionProperty($this->baseHttpClient, 'defaultOptions');
        $currentHttpClientOptions = $defaultOptionsReflection->getValue($this->baseHttpClient);

        $options = array_merge($currentHttpClientOptions, $options);

        return new self( // @phpstan-ignore-line
            $this->host,
            $this->port,
            $this->controlPort,
            $this->controlPassword,
            $this->controlAuthenticationCookie,
            $this->baseHttpClient->withOptions($options),
        );
    }

    /**
     * Request a new Tor identity, and reset the HTTP client to use the new connection.
     */
    public function newIdentity(): void
    {
        $torControlClient = new TorControlClient(
            host: $this->host,
            port: $this->controlPort,
            timeout: 15,
            password: $this->controlPassword,
            authenticationCookie: $this->controlAuthenticationCookie,
        );

        $torControlClient->setEvents(['CIRC']);
        $torControlClient->signalNewnym();

        $this->buildHttpClient();

        try {
            // Tor won't actually build a new circuit until we make a request
            $response = $this->httpClient->request('HEAD', 'https://check.torproject.org/');
            $response->getStatusCode(); // Triggers the connection
        } catch (\Throwable) {
            // Do nothing
        }

        $torControlClient->waitForCircuitBuild();
        $torControlClient->close();
    }

    /**
     * Helper method to build the HTTP client with the current options.
     */
    private function buildHttpClient(): void
    {
        $this->httpClient = $this->baseHttpClient->withOptions($this->clientOptions);
    }
}
