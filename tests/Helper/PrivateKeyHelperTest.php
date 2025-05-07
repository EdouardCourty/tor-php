<?php

declare(strict_types=1);

namespace TorPHP\Tests\Helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TorPHP\Helper\PrivateKeyHelper;

/**
 * @coversDefaultClass \TorPHP\Helper\PrivateKeyHelper
 */
class PrivateKeyHelperTest extends TestCase
{
    /**
     * @covers ::parsePrivateKey
     */
    #[DataProvider('provideKeys')]
    public function testParsePrivateKey(string $input, string $expectedOutput): void
    {
        $computedOutput = PrivateKeyHelper::parsePrivateKey($input);

        $this->assertSame($expectedOutput, $computedOutput);
    }

    public static function provideKeys(): \Generator
    {
        yield [
            'ED25519-V3:key1',
            'ED25519-V3:key1',
        ];

        yield [
            'key2',
            'ED25519-V3:key2',
        ];
    }
}
