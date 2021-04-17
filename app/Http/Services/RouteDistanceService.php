<?php

namespace App\Http\Services;

use App\CustomClasses\DBobject\Place;
use App\Modules\Elasticsearch\Elasticsearch;
use App\Modules\MapRouteDistance\Route;

class RouteDistanceService
{
    /**
     * @param $params
     * @return array|null
     */
    public static function getPlaces($params)
    {
        $elastic_client = new Elasticsearch();
        $query = $elastic_client->search($params);
        if ($query['hits']['total']['value'] == 0) {
            return null;
        } else {
            $places = array();
            foreach ($query['hits']['hits'] as $q) {
                $places[] = $q['_source'];
            }
        }
        return $places;
    }

    /**
     * @param $lon1
     * @param $lat1
     * @param $type
     * @param $buf
     * @param $skip
     * @param $take
     * @return array
     * set parameters for elasticsearch query
     */

    public static function setParams($lon1, $lat1, $type, $buf, $skip, $take): array
    {
        $query_parts = [
            'index' => 'map_data_fa_v2-1399-08-18',
            'body' => [
                'from' => $skip,
                'size' => $take,
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['term' => ['layer.exact' => 'poi']],

                        ]
                    ]
                ],

            ]
        ];
        if ($lon1 and $lat1) {
            $sort = ['_geo_distance' => [
                'locations.location01' => [
                    'lat' => $lat1,
                    'lon' => $lon1],
                'order' => 'asc',
                'unit' => 'm']];

            $query_parts['body']['sort'] = $sort;
        }
        if ($type) {
            $type = ['term' => ['subcategory' => $type]];
            array_push($query_parts['body']['query']['bool']['filter'], $type);
        }
        if ($buf and $lon1 and $lat1) {
            $distance_filter = ['geo_distance' => [
                'distance' => $buf,
                'locations.location01' => [
                    'lat' => $lat1,
                    'lon' => $lon1]]];
            array_push($query_parts['body']['query']['bool']['filter'], $distance_filter);

        }
        return $query_parts;
    }

    /**
     * @param $place
     * @param $distance
     * @return Place
     */

    public static function createPlace($place, $distance)
    {
        return  new Place($place['province'],
            $place['county'],
            $place['district'],
            $place['city'],
            $place['region'],
            $place['neighborhood'],
            $place['village'],
            $place['name'],
            $place['address'],
            $place['type'],
            $place['subcategory'],
            $place['locations'],
            $distance . ' m'
        );

    }

    /**
     * @param $places
     * @param $lon1
     * @param $lat1
     * @param $buf
     * @return array|string
     */

    public static function getNearest($places, $lon1, $lat1, $buf)
    {
        $result = array();
        $distance = null;
        foreach ($places as $place) {
            $lon2 = $place['locations']['location01']['lon'];
            $lat2 = $place['locations']['location01']['lat'];
            $distance = self::getDistance($lon1, $lat1, $lon2, $lat2);
            if ($distance == null) {
                return 'unauthorized';
            }
            if ($buf) {
                if ($distance <= $buf) {
                    $place = self::createPlace($place, $distance);
                    $result[] = $place;
                } else {
                    continue;
                }
            } else {
                $place = self::createPlace($place, $distance);
                $result[] = $place;
            }
        }
        return $result;
    }

    /**
     * @param $lon1
     * @param $lat1
     * @param $lon2
     * @param $lat2
     * @return mixed|null
     * use map.ir api to calculate route distance
     */

    public static function getDistance($lon1, $lat1, $lon2, $lat2)
    {
        $url = 'https://map.ir/';
        $coordinates = $lon1 . ',' . $lat1 . ';' . $lon2 . ',' . $lat2;
        $api_key = env('API_KEY');
        $alternatives = false;
        $steps = false;
        $overview = 'false';
        $type = 'Route';
        $route = new Route($url);
        $dist = null;
        $re = $route->get($coordinates, $alternatives, $steps, $overview, $type, $api_key);
        if ($re['code'] == "Ok") {
            $dist = $re['routes'][0]['legs'][0]['distance'];
        }
        return $dist;
    }

    /**
     * @param $lon1
     * @param $lat1
     * @param $type
     * @param $buf
     * @return array
     */

    public static function numberOfNearestPlaces($lon1, $lat1, $type, $buf)
    {
        $buf = (float)$buf;
        $count = null;
        $params = self::setParams($lon1, $lat1, $type, $buf, 0,3000);
        $places = self::getPlaces($params);
        if ($places) {
            $data = self::getNearest($places, $lon1, $lat1, $buf);

            if ($data !== 'unauthorized') {
                $count = count($data);
            } else {
                $data = 'unauthorized';
            }
        } else {
            $data = null;
        }
        return ['data' => $data, 'count' => $count];


    }

    /**
     * @param $lon1
     * @param $lat1
     * @param $type
     * @param $buf
     * @param $take
     * @param $skip
     * @return array
     */
    public static function listOfNearestPlaces($lon1, $lat1, $type, $buf, $take, $skip)
    {
        $buf = (float)$buf;
        $count = null;
        $params = self::setParams($lon1, $lat1, $type, $buf, $skip, $take);
        $places = self::getPlaces($params);
        if ($places) {
            $bound_to_buffer_data = self::getNearest($places, $lon1, $lat1, $buf);
            if ($bound_to_buffer_data !== 'unauthorized') {
                $data = self::sortByDistance($bound_to_buffer_data);
                $count=count($data);
            } else {
                $data = 'unauthorized';
            }
        } else {
            $data = null;
        }
        return ['data' => $data, 'count' => $count];
    }

    /**
     * @param $lon1
     * @param $lat1
     * @param $type
     * @return array|null[]
     */

    public static function nearestPlace($lon1, $lat1, $type)
    {
        $data = null;

        $params = self::setParams($lon1, $lat1, $type, null, 0,100);
        $places = self::getPlaces($params);
        if ($places) {
            $res = self::getNearest($places, $lon1, $lat1, null);

            if ($res !== 'unauthorized') {
                $sorted_res = self::sortByDistance($res);
                $data = $sorted_res[0];
            }
            else {
                $data = $res;
            }
        }

        return ['data' => $data];
    }

    /**
     * @param $array
     * @return mixed
     * sort the data by distances
     */
    public static function sortByDistance($array)
    {
        for ($i = 0; $i <= count($array) - 1; $i++) {
            for ($j = $i + 1; $j <= count($array) - 1; $j++) {
                if ($array[$i]->distance > $array[$j]->distance) {
                    $temp = $array[$j];
                    $array[$j] = $array[$i];
                    $array[$i] = $temp;
                }
            }
        }
        return $array;
    }
}
