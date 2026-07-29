<?php

namespace Checkout;

use CheckoutComWC\Vendor\GuzzleHttp\Client as GuzzleHttpClient;
final class DefaultHttpClientBuilder implements HttpClientBuilderInterface
{
    private $client;
    public function __construct($config)
    {
        $this->client = new GuzzleHttpClient($config);
    }
    /**
     * @return \GuzzleHttpClient
     */
    public function getClient()
    {
        return $this->client;
    }
}