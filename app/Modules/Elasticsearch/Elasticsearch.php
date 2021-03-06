<?php

namespace App\Modules\Elasticsearch;


use App\CustomClasses\DBobject\Place;
use Elasticsearch\ClientBuilder;


class Elasticsearch
{
    /**
     * @param $lon1
     * @param $lat1
     * @param $subcategory
     * @param $buf
     * @param $take
     * @param $skip
     * @param $air_dist
     * @param $sort
     * @return array
     */
    public static function setParams($lon1, $lat1, $subcategory, $buf, $take, $skip, $air_dist, $sort): array
    {
        $query_parts = [
            'index' => env('ELASTIC_INDEX_FA'),
            'body' => [
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['term' => ['layer.exact' => 'poi']],

                        ]
                    ]
                ],
            ]
        ];

        if ($take && $skip >= 0) {
            $query_parts['body']['from'] = $skip;
            $query_parts['body']['size'] = $take;
        }
        if ($lon1 and $lat1) {
            if ($sort == 'true') {
                $sort = ['_geo_distance' => [
                    'locations.location01' => [
                        'lat' => $lat1,
                        'lon' => $lon1],
                    'order' => 'asc',
                    'unit' => 'm']];
                $query_parts['body']['sort'] = $sort;
            }
            if ($air_dist) {
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
                    ]
                ];
                $query_parts['body']['script_fields'] = $script_fields;
                $query_parts['body']['_source'] = true;
            }


        }
        if ($subcategory) {
            $subcategory = ['term' => ['subcategory' => $subcategory]];
            array_push($query_parts['body']['query']['bool']['filter'], $subcategory);
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
     * @param $params
     * @param $flag
     * @return array|null
     */
    public static function getPlaces($params)
    {
        $query = self::search($params);
//        dd($query);
        if ($query['hits']['total']['value'] == 0) {
            return null;
        } else {
            $places = array();
            foreach ($query['hits']['hits'] as $q) {
                $src = $q['_source'];
                if (isset($query['hits']['hits'][0]['fields'])) {
                    $dist = $q['fields']['distance_in_meters'][0];

                } else {
                    $dist = null;
                }
                $place = self::createPlace($src, $dist);
                $place_lat = $place->location['location01']['lat'];
                $place_lon = $place->location['location01']['lon'];
                unset($place->location);
                if ($src['type'] == 'polygon') {
                    $place->geometry['type'] = $src['polygons']['type'];
                    $place->geometry['coordinates'] = $src['polygons']['coordinates'];
                }
//                if ($src['type'] == 'point') {
//                    $place->geometry['type'] = $src['type'];
//                    $place->geometry['coordinates'][] = $place_lon;
//                    $place->geometry['coordinates'][] = $place_lat;
//                }
                $place->location['type'] = $src['type'];
                $place->location['coordinates'] = [
                    $place_lon,
                    $place_lat
                ];
                $amount = $place->distance;
                unset($place->distance);
                $place->distance['amount'] = $amount;
                $place->distance['unit'] = 'meters';

                $places[] = $place;

            }
            return $places;
        }

    }


    public static function search($params)
    {
        $hosts = [env("ELASTIC_HOST") . ":" . env("ELASTIC_PORT")];
        $client = ClientBuilder::create()->setHosts($hosts)->build();
//        dd($params);
//        dd($client->search($params));
        return $client->search($params);
    }

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
//            $place['geometry'],
            $distance
        );

    }

    public static function count($params)
    {
        $hosts = [env("ELASTIC_HOST") . ":" . env("ELASTIC_PORT")];
        $client = ClientBuilder::create()->setHosts($hosts)->build();
        return $client->count($params)['count'];
    }
}


