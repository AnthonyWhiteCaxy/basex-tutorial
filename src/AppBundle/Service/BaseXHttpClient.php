<?php

namespace AppBundle\Service;

use GuzzleHttp\Client;

class BaseXHttpClient
{
    /**
     * @var Client
     */
    private $client;

    public function __construct($user, $password, $host = '127.0.0.1', $port = 8984, $scheme = 'http')
    {
        $this->client = new Client([
            'auth'      => [$user, $password],
            'base_uri'  => sprintf('%s://%s:%s/rest/', $scheme, $host, $port),
        ]);
    }

    public function getClient()
    {
        return $this->client;
    }
}
