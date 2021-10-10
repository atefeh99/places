<?php

namespace App\Http\Services;

use App\Modules\Elasticsearch\Elasticsearch;
use App\Modules\MapRouteDistance\Route;
use Illuminate\Support\Facades\Log;

class DistanceService
{

    /**
     * @param $lon1
     * @param $lat1
     * @param $subcategory
     * @param $buf
     * @return array[]|null[]
     */
    public static function numberOfNearestPlaces($lon1, $lat1, $subcategory, $buf)
    {
        $params = Elasticsearch::setParams($lon1, $lat1, $subcategory, $buf, null, null, false, false);
        $data = Elasticsearch::count($params);
        return ['data' => $data, 'count' => $data];
    }

    /**
     * @param $lon1
     * @param $lat1
     * @param $subcategory
     * @param $buf
     * @param $take
     * @param $skip
     * @param $sort
     * @return array
     */

    public static function listOfNearestPlaces($lon1, $lat1, $subcategory, $buf, $take, $skip, $sort)
    {
        // $air_dist = $sort;
        $params = Elasticsearch::setParams($lon1, $lat1, $subcategory, $buf, $take, $skip, true, $sort);
        $places = Elasticsearch::getPlaces($params);
        $count = 0;
        if ($places) {
            $data = $places;
            $count = count($places);

        } else {
            $data = null;
        }
        return ['data' => $data, 'count' => $count];
    }

    /**
     * @param $lon1
     * @param $lat1
     * @param $subcategory
     * @return array[]|null[]
     */
    public static function airNearest($lon1, $lat1, $subcategory)
    {
        $params = Elasticsearch::setParams($lon1, $lat1, $subcategory, null, 1, 0, true, true);
        $data = Elasticsearch::getPlaces($params);

        return ['data' => $data[0]];
    }

    /**
     * @param $lon1
     * @param $lat1
     * @param $subcategory
     * @param $api_key
     * @return array|null[]
     */

    public static function routeNearest($lon1, $lat1, $subcategory,$api_key)
    {
        $data = null;
        $params = Elasticsearch::setParams($lon1, $lat1, $subcategory, null, 100, 0, false, true);
        $places = Elasticsearch::getPlaces($params);
        if ($places) {
            $res = self::setDistance($places, $lon1, $lat1,$api_key);
            if ($res !== 'unauthorized') {
                $sorted_res = self::sortByDistance($res);
                $data = $sorted_res[0];
            } else {
                $data = $res;
            }
        }

        return ['data' => $data];
    }

    /**
     * @param $places
     * @param $lon1
     * @param $lat1
     * @param $api_key
     * @return array|string
     */

    public static function setDistance($places, $lon1, $lat1,$api_key)
    {

        $result = array();
        $distance = null;
        foreach ($places as $place) {
            $lon2 = $place->location['coordinates'][0];
            $lat2 = $place->location['coordinates'][1];
            $distance = self::getDistance($lon1, $lat1, $lon2, $lat2,$api_key);
            if ($distance == null) {
                return 'unauthorized';
            }
            $place->distance['amount'] = $distance;

            $result[] = $place;
        }
        return $result;
    }


    /**
     * @param $lon1
     * @param $lat1
     * @param $lon2
     * @param $lat2
     * @param $api_key
     * @return mixed|null
     * use map.ir api to calculate route distance
     */

    public static function getDistance($lon1, $lat1, $lon2, $lat2,$api_key)
    {
        $url = env('MAP_URL');
        $coordinates = $lon1 . ',' . $lat1 . ';' . $lon2 . ',' . $lat2;
        $alternatives = false;
        $steps = false;
        $overview = 'false';
        $type = 'Route';
        $route = new Route($url);
        $dist = null;
        $re = $route->get($coordinates, $alternatives,$api_key, $steps, $overview, $type);
//        dd($re);
        if (isset($re['code']) && $re['code'] == "Ok") {
            $dist = $re['routes'][0]['legs'][0]['distance'];
        }
        return $dist;
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
                if ($array[$i]->distance['amount'] > $array[$j]->distance['amount']) {
                    $temp = $array[$j];
                    $array[$j] = $array[$i];
                    $array[$i] = $temp;
                }
            }
        }
        return $array;
    }

}
