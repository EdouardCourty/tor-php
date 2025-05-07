# TOR-PHP

[![PHP CI](https://github.com/EdouardCourty/tor-php/actions/workflows/php_ci.yml/badge.svg)](https://github.com/EdouardCourty/tor-php/actions/workflows/php_ci.yml)

Tor-PHP provides a tor-proxied HTTP client for PHP, built on top of Symfony's HttpClient component. <br />
It allows you to make HTTP requests through the Tor network, enabling anonymous browsing and web scraping. <br />

It also allows you to interact with your Tor node using the Tor Control Protocol, enabling you to manage your Tor circuits and perform other operations.

The changelog for this project can be found [here](./CHANGELOG.md).

## Installation

1. Install Tor on your system : [official website page](https://community.torproject.org/onion-services/setup/install/) <br />
    _TLDR_: <br />
    - On Ubuntu/Debian: `sudo apt-get install tor` <br />
    - On MacOS: `brew install tor` <br />

_If you want to use this library to its fullest, make sure the Tor ControlPort is enabled._

2. Install the library
    ```shell
    composer require ecourty/tor-php
    ```

## Usage example

1. Checking your IP address through Tor and non-Tor connections

    ```php
    <?php
    
    use TorPHP\TorHttpClient;
    use \Symfony\Component\HttpClient\HttpClient;
    
    $torHttpClient = new TorHttpClient(host: 'localhost', port: 9050, controlPort: 9051);
    
    $response = $torHttpClient->request('GET', 'https://api.ipify.org?format=json');
    $torIp = $response->toArray()['ip'];
    
    $nonTorHttpClient = HttpClient::create();
    $nonTorIp = $torHttpClient->request('GET', 'https://api.ipify.org?format=json')->toArray()['ip'];
    
    echo "Tor IP: $torIp\n";
    echo "Non-Tor IP: $nonTorIp\n";
    
    /**
     * Tor IP: 86.134.78.91
     * Non-Tor IP: 164.21.35.156
     */
    ```

2. Requesting a new identity
    ```php
    <?php
    
    use TorPHP\TorHttpClient;
    
    $torHttpClient = new TorHttpClient(); // Default values set to the Tor default ports
    
    $response = $torHttpClient->request('GET', 'https://whatever.url/page/1');
    $torHttpClient->newIdentity();

    // Will use the new identity (new Tor circuit)
    $response = $torHttpClient->request('GET', 'https://something.else/url');
    ```

3. Managing your Tor node

    ```php
    <?php

    use TorPHP\TorControlClient;
    use TorPHP\Model\PortMapping;

    $torControlClient = new TorControlClient(); // Default values set to the Tor default ports

    // List all available circuits
    $circuits = $torControlClient->getCircuits();

    $torControlClient->setConfigValue('SocksPort', '9080'); // Change a configuration value
    $socksPort = $torControlClient->getConfigValue('SocksPort'); // Get a configuration value

    // Add a new onion service
    $onionService = $torControlClient->addOnionService([new PortMapping(host: 'localhost', localPort: 3000, remotePort: 80)]);

    // List all onion services
    $onionServices = $torControlClient->listOnionServices();

    // Delete an onion service
    $torControlClient->deleteOnionService($onionService->id);
    ```
&copy; Edouard Courty 2025
