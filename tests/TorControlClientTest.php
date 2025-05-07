<?php

declare(strict_types=1);

namespace TorPHP\Tests;

use PHPUnit\Framework\TestCase;
use TorPHP\TorControlClient;

/**
 * @coversDefaultClass \TorPHP\TorControlClient
 */
class TorControlClientTest extends TestCase
{
    /**
     * @covers ::getConfigValue
     * @covers ::setConfigValue
     */
    public function testGetConfigValue(): void
    {
        $torControlClient = new TorControlClient(password: 'Password');

        $torControlClient->setConfigValue('HTTPTunnelPort', '9099');
        $torControlClient->setConfigValue('HTTPTunnelPort', '9011');

        $httpTunnelPort = $torControlClient->getConfigValue('HTTPTunnelPort');
        $this->assertSame('9011', $httpTunnelPort, 'HTTP Tunnel Port should be 9999');

        $torControlClient->setConfigValue('HTTPTunnelPort', '9080');
    }

    /**
     * @covers ::getCircuits
     */
    public function testGetCircuits(): void
    {
        $torControlClient = new TorControlClient(password: 'Password');

        $circuits = $torControlClient->getCircuits();

        $this->assertNotEmpty($circuits, 'Circuits should not be empty');
    }
}
