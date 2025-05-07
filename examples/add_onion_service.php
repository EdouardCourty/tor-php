<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TorPHP\Model\PortMapping;
use TorPHP\TorControlClient;

$torHttpClient = new TorControlClient();

/**
 * This assumes you have some kind of web server running on localhost:8089
 *
 * Remark: This Tor hidden service will not be persistent across Tor restarts.
 * To make a persistent Tor service, you need to register the service in the torrc file.
 *
 * @see https://community.torproject.org/onion-services/setup/
 */
$portMapping = new PortMapping('127.0.0.1', 8089, 80);
$hiddenService = $torHttpClient->addOnionService(portMappings: [$portMapping]); // No private key passed, Tor will generate one for us

echo '===== ONION SERVICE CREATED =====' . \PHP_EOL;

echo '> URL: ' . $hiddenService->url . \PHP_EOL;
echo '> Private Key: ' . $hiddenService->privateKey . \PHP_EOL;
