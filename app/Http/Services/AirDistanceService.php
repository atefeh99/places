<?php

namespace App\Http\Services;

use App\Modules\Elasticsearch\Elasticsearch;
use App\CustomClasses\DBobject\Place;

class AirDistanceService
{
    /**
     * @param $place
     * @param $distance
     * @return Place
     */
    public static function createPlace($place, $distance)
    {
        return new Place($place['province'],
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
            $distance
        );

    }

    /**
     * @param $params
     * @param $flag
     * @return array|null
     */
    public static function getPlaces($params, $flag)
    {
        $elastic_client = new Elasticsearch();
        $query = $elastic_client->search($params);
        if ($query['hits']['total']['value'] == 0) {
            return null;
        } else {
            if ($flag) {
                return $query['hits']['total']['value'];
            } else {
                $places = array();
                $script_fields_exist = isset($query['hits']['hits'][0]['fields']);
                if (!$script_fields_exist) {
                    foreach ($query['hits']['hits'] as $q) {
                        $src = $q['_source'];
                        $place = self::createPlace($src, null);
                        $places[] = $place;
                    }
                } else {
                    foreach ($query['hits']['hits'] as $q) {
                        $place_fields = $q['fields']['place_fields'][0];
                        $dist = $q['fields']['distance_in_meters'][0];
                        $place = self::createPlace($place_fields, $dist . ' m');
                        $places[] = $place;
                    }
                }
                return $places;
            }
        }
    }

    /**
     * @param $lon1
     * @param $lat1
     * @param $type
     * @param $buf
     * @param $take
     * @param $skip
     * @return array
     * set parameters for elasticsearch query
     */
    public static function setParams($lon1, $lat1, $type, $buf, $take, $skip): array
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
            $script_fields = [
                'distance_in_meters' => [
                    'script' => [
                        'lang' => 'painless',
                        'source' => "doc['locations.location01'].arcDistance(params.lat, params.lon)",
                        'params' => [
                            'lat' => $lat1,
                            'lon' => $lon1
                        ]
                    ]
                ],
                'place_fields' => [
                    'script' => "params['_source']"
                ]
            ];
            $query_parts['body']['script_fields'] = $script_fields;
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
     * @param $lon1
     * @param $lat1
     * @param $type
     * @param $buf
     * @return array[]|null[]
     */

    public static function numberOfNearestPlaces($lon1, $lat1, $type, $buf)
    {
        $params = self::setParams($lon1, $lat1, $type, $buf, 3000, 0);
        $data = self::getPlaces($params, true);
        return ['data' => $data, 'count' => $data];
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
        $params = self::setParams($lon1, $lat1, $type, $buf, $take, $skip);
        $places = self::getPlaces($params, false);
        $count = 0;
        if ($places and $places !== 'unauthorized') {
            $data = $places;
            $count = count($places);
        } elseif ($places === 'unauthorized') {
            $data = 'unauthorized';
        } else {
            $data = null;
        }
        return ['data' => $data, 'count' => $count];
    }

    /**
     * @param $lon1
     * @param $lat1
     * @param $type
     * @return array[]|null[]
     */
    public static function nearestPlace($lon1, $lat1, $type)
    {
        $params = self::setParams($lon1, $lat1, $type, null, 1, 0);
        $data = self::getPlaces($params, false);
        return ['data' => $data];
    }

}
