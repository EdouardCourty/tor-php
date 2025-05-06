<?php

declare(strict_types=1);

namespace TorPHP\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use TorPHP\TorClient;

/**
 * @coversDefaultClass \TorPHP\TorClient
 */
class TorClientTest extends TestCase
{
    /**
     * @covers ::request
     */
    public function testTorConnection(): void
    {
        $nonTorHttpClient = HttpClient::createForBaseUri('https://check.torproject.org');
        $nonTorResponse = $nonTorHttpClient->request('GET', '/api/ip')->toArray();

        $this->assertArrayHasKey('IP', $nonTorResponse, 'Non-Tor response should contain IP key');
        $nonTorIp = $nonTorResponse['IP'];

        $this->assertArrayHasKey('IsTor', $nonTorResponse, 'Non-Tor response should contain IsTor key');
        $this->assertFalse($nonTorResponse['IsTor'], 'Non-Tor response should not be from Tor network');

        $torClient = new TorClient(httpClient: $nonTorHttpClient);
        $torIp = $torClient->request('GET', '/api/ip')->toArray();

        $this->assertArrayHasKey('IP', $torIp, 'Tor response should contain IP key');
        $this->assertNotSame($nonTorIp, $torIp['IP'], 'Tor IP should be different from non-Tor IP');

        $this->assertArrayHasKey('IsTor', $torIp, 'Tor response should contain IsTor key');
        $this->assertTrue($torIp['IsTor'], 'Tor response should be from Tor network');
    }

    /**
     * @covers ::request
     */
    public function testNoProxy(): void
    {
        $nonTorHttpClient = HttpClient::createForBaseUri('https://check.torproject.org');
        $nonTorResponse = $nonTorHttpClient->request('GET', '/api/ip')->toArray();

        $this->assertArrayHasKey('IP', $nonTorResponse, 'Non-Tor response should contain IP key');
        $nonTorIp = $nonTorResponse['IP'];

        $topClient = new TorClient(httpClient: $nonTorHttpClient);
        $noProxyResponse = $topClient->request('GET', '/api/ip', [
            'no_proxy' => 'torproject.org',
        ])->toArray();

        $this->assertArrayHasKey('IP', $noProxyResponse, 'Tor response should contain IP key');
        $this->assertArrayHasKey('IsTor', $noProxyResponse, 'Tor response should contain IsTor key');

        $this->assertFalse($noProxyResponse['IsTor'], 'Tor response should not be from Tor network');
        $this->assertSame($nonTorIp, $noProxyResponse['IP'], 'Tor IP should be the same as non-Tor IP');
    }

    /**
     * @covers ::newIdentity
     */
    public function testNewIdentity(): void
    {
        $httpClient = HttpClient::createForBaseUri('https://check.torproject.org');
        $torClient = new TorClient(controlPassword: 'password', httpClient: $httpClient);

        $torIp = $torClient->request('GET', '/api/ip')->toArray()['IP'];

        $torClient->newIdentity();
        $torIpAfterNewIdentity = $torClient->request('GET', '/api/ip')->toArray()['IP'];

        $this->assertNotSame($torIp, $torIpAfterNewIdentity, 'Tor IP should change after new identity');
    }

    /**
     * @covers ::withOptions
     */
    public function testWithOptions(): void
    {
        $baseTimeout = 20.0;
        $baseUserAgent = 'TorPHP';
        $baseNoProxyValue = 'torproject.org';

        $httpClient = HttpClient::create(
            defaultOptions: [
                'timeout' => $baseTimeout,
                'headers' => [
                    'User-Agent' => $baseUserAgent,
                ],
                'no_proxy' => $baseNoProxyValue,
            ],
        );

        $torClient = new TorClient(httpClient: $httpClient);
        $clientOptions = $this->extractClientOptions($torClient);

        $this->assertSame($baseTimeout, $clientOptions['timeout']);
        $this->assertArrayHasKey('headers', $clientOptions, 'Headers key must exist');

        $userAgentHeader = array_find($clientOptions['headers'], static function (string $header) use ($baseUserAgent) {
            return $header === 'User-Agent: ' . $baseUserAgent;
        });
        $this->assertStringContainsString($baseUserAgent, $userAgentHeader);

        $newTimeout = 10.0;
        $newClient = $torClient->withOptions([
            'timeout' => $newTimeout, // No headers are passed, should be kept between clients
        ]);

        $newClientOptions = $this->extractClientOptions($newClient);

        $this->assertSame($newTimeout, $newClientOptions['timeout'], 'Timeout should be updated');
        $this->assertSame($baseNoProxyValue, $newClientOptions['no_proxy'], 'No proxy should be kept');

        $this->assertArrayHasKey('headers', $newClientOptions, 'Headers key must exist');

        $userAgentHeader = array_find($clientOptions['headers'], static function (string $header) use ($baseUserAgent) {
            return $header === 'User-Agent: ' . $baseUserAgent;
        });
        $this->assertStringContainsString($baseUserAgent, $userAgentHeader);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractClientOptions(TorClient $torClient): array
    {
        $httpClient = new \ReflectionProperty(TorClient::class, 'httpClient');
        $client = $httpClient->getValue($torClient);

        $clientReflection = new \ReflectionProperty($client, 'defaultOptions');

        return $clientReflection->getValue($client);
    }
}
