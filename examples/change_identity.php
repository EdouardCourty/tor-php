<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TorPHP\TorHttpClient;

$torHttpClient = new TorHttpClient();
$initialResponse = $torHttpClient->request('GET', 'http://check.torproject.org/api/ip')->toArray();

$torHttpClient->newIdentity();

$secondaryResponse = $torHttpClient->request('GET', 'http://check.torproject.org/api/ip')->toArray();

echo 'Non-Tor IP: ' . $initialResponse['IP'] . \PHP_EOL;
echo 'Tor IP: ' . $secondaryResponse['IP'] . \PHP_EOL;
