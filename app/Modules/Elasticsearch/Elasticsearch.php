<?php

namespace App\Modules\Elasticsearch;


use Elasticsearch\ClientBuilder;


class Elasticsearch
{
    public $client;

    public function __Construct()
    {
        $hosts = [
            'https://shiveh:atpq238rz@search-elastic7-dev.map.ir:443'
        ];
        $this->client = ClientBuilder::create()
            ->setHosts($hosts)
            ->build();
    }

    /**
     * @return \Elasticsearch\Client
     */

    public  function search($params)
    {
        return $this->client->search($params);
    }
    public function count($params)
    {
        return $this->client->count($params);
    }
}


