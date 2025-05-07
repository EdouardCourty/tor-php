<?php

declare(strict_types=1);

namespace TorPHP\Tests\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TorPHP\Model\PortMapping;

/**
 * @coversDefaultClass \TorPHP\Model\PortMapping
 */
class PortMappingTest extends TestCase
{
    /**
     * @covers ::fromString
     */
    #[DataProvider('provideStrings')]
    public function testFromString(string $string, PortMapping $expectedPortMapping): void
    {
        $portMapping = PortMapping::fromString($string);

        $this->assertEquals($expectedPortMapping, $portMapping);
    }

    /**
     * @covers ::toString
     */
    public function testToString(): void
    {
        $portMapping = new PortMapping(
            host: 'localhost',
            localPort: 3000,
            remotePort: 80,
        );

        $this->assertEquals('80,localhost:3000', $portMapping->toString());
    }

    public static function provideStrings(): \Generator
    {
        yield [
            '80,localhost:3000',
            new PortMapping(
                host: 'localhost',
                localPort: 3000,
                remotePort: 80,
            ),
        ];

        yield [
            '443,127.0.0.1:4500',
            new PortMapping(
                host: '127.0.0.1',
                localPort: 4500,
                remotePort: 443,
            ),
        ];
    }
}
