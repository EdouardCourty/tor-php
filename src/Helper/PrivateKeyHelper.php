<?php

declare(strict_types=1);

namespace TorPHP\Helper;

/**
 * Helper class for private key management.
 *
 * @author Edouard Courty
 */
class PrivateKeyHelper
{
    public const string NO_PRIVATE_KEY = 'NEW:' . self::PRIVATE_KEY_PREFIX;

    private const string PRIVATE_KEY_PREFIX = 'ED25519-V3';

    /**
     * Parse the private key to ensure it has the correct prefix.
     */
    public static function parsePrivateKey(string $privateKey): string
    {
        if (str_starts_with($privateKey, self::PRIVATE_KEY_PREFIX) === true) {
            return $privateKey;
        }

        return \sprintf('%s:%s', self::PRIVATE_KEY_PREFIX, $privateKey);
    }
}
