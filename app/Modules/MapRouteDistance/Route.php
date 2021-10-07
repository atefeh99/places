<?php

namespace App\Modules\MapRouteDistance;

use App\Modules\MapRouteDistance\CURL;

class Route
{
    private $coordinates, $alternatives, $steps, $overview, $type, $apiKey;
    public $response;

    public function __construct($url)
    {
        $this->url = $url;
    }

    public function get($coordinates, $alternatives, $steps = true, $overview = true, $type = 'Route')
    {
        $this->coordinates = $coordinates;
        $this->alternatives = $alternatives;
        $this->steps = $steps;
        $this->overview = $overview;
        $this->type = $type;
//        $this->apiKey = $apiKey;

        $curl = new CURL(
            $this->makeURL()
        );

//        $curl->setHeaders([
//            'Content-Type: application/json',
//            'x-api-key:' . $this->apiKey
//        ]);
        $curl->post();
        return $curl->parse();
    }

    private function getSteps()
    {
        if ($this->steps) {
            return 'true';
        } else {
            return 'false';
        }
    }

    private function getAlternatives()
    {
        if ($this->alternatives) {
            return 'true';
        } else {
            return 'false';
        }
    }

    private function makeURL()
    {
        switch ($this->type) {
            case 'BicycleRoute' :
                return $this->url
                    . '/bicycle/v1/driving/'
                    . $this->coordinates
                    . '?alternatives=' . $this->getAlternatives()
                    . '&steps=' . $this->getSteps()
                    . '&overview=' . $this->overview;
            case 'FootRoute' :
                return $this->url
                    . '/foot/v1/driving/'
                    . $this->coordinates
                    . '?alternatives=' . $this->alternatives
                    . '&steps=' . $this->getSteps()
                    . '&overview=' . $this->overview;
            case 'RouteTarh' :
                return $this->url
                    . '/tarh/v1/driving/'
                    . $this->coordinates
                    . '?alternatives=' . $this->getAlternatives()
                    . '&steps=' . $this->getSteps()
                    . '&overview=' . $this->overview;
            case 'RouteZojOFard' :
                return $this->url
                    . '/zojofard/v1/driving/'
                    . $this->coordinates
                    . '?alternatives=' . $this->getAlternatives()
                    . '&steps=' . $this->getSteps()
                    . '&overview=' . $this->overview;
            default:
                return $this->url
                    . '/route/v1/driving/'
                    . $this->coordinates
                    . '?alternatives=' . $this->getAlternatives()
                    . '&steps=' . $this->getSteps()
                    . '&overview=' . $this->overview;
        }
    }

}
