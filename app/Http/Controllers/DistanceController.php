<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Services\RouteDistanceService;
use App\Http\Services\AirDistanceService;
use App\Helper\OdataQueryParser;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\Responder;


class DistanceController extends Controller
{
    /**
     * @param $request
     * @return mixed
     * get parameters from requests
     */

    public static function filter($request)
    {
        $responder = new Responder();
        $validate = Validator::make($request->all(), [
            '$filter' => 'string|required'
        ]);
        if ($validate->fails()) {
            return $responder->respondInvalidParams('1000', $validate->errors(), 'bad request');
        }


        $odata_query = OdataQueryParser::parse($request->fullUrl());
        if (OdataQueryParser::isFails()) {
            return $responder->respondInvalidParams('1001', OdataQueryParser::getErrors(), 'bad request');
        }

        $lon = env('DEFAULT_LON');
        $lat = env('DEFAULT_LAT');
        $type = env('DEFAULT_TYPE');
        $buf = env('DEFAULT_BUFFER');

        if (isset($odata_query['skip'])) {
            $validate = Validator::make(['skip' => $odata_query['skip']], [
                'skip' => 'integer|required'
            ]);
            if ($validate->fails()) {
                return $responder->respondInvalidParams('1021', $validate->errors(), 'bad request');
            }
            $skip = $odata_query['skip'];
        } else {
            $skip = env('DEFAULT_SKIP');
        }

        if (isset($odata_query['top'])) {
            $validate = Validator::make(['top' => $odata_query['top']], [
                'top' => 'integer|required|lte:20'
            ]);
            if ($validate->fails()) {
                return $responder->respondInvalidParams('1022', $validate->errors(), 'bad request');
            }
            $take = $odata_query['top'];
        } else {
            $take = env('DEFAULT_TOP');
        }

        if (isset($odata_query['filter'])) {
            foreach ($odata_query['filter'] as $item) {
                if ($item['left'] == 'lon' && $item['operator'] == '=') {
                    $validate = Validator::make(['lon' => $item['right']], [
                        'lon' => 'numeric|required'
                    ]);
                    if ($validate->fails()) {
                        return $responder->respondInvalidParams('1002', $validate->errors(), 'bad request');
                    }
                    $lon = (float)$item['right'];
                }

                if ($item['left'] == 'lat' && $item['operator'] == '=') {
                    $validate = Validator::make(['lat' => $item['right']], [
                        'lat' => 'numeric|required'
                    ]);
                    if ($validate->fails()) {
                        return $responder->respondInvalidParams('1003', $validate->errors(), 'bad request');
                    }
                    $lat = (float)$item['right'];
                }

                if ($item['left'] == 'type' && $item['operator'] == '=') {
                    $validate = Validator::make(['type' => $item['right']], [
                        'type' => 'string|required'
                    ]);
                    if ($validate->fails()) {
                        return $responder->respondInvalidParams('1004', $validate->errors(), 'bad request');
                    }
                    $type = $item['right'];
                }

                if ($item['left'] == 'buffer' && $item['operator'] == '=') {
                    $validate = Validator::make(['buffer' => (float)$item['right']], [
                        'buffer' => 'numeric|required|between:0,15000.0'
                    ]);
                    if ($validate->fails()) {
                        return $responder->respondInvalidParams('1005', $validate->errors(), 'bad request');
                    }
                    $buf = $item['right'];
                }
            }
        }
        return ['lon' => $lon, 'lat' => $lat, 'type' => $type, 'buffer' => $buf, 'take' => $take, 'skip' => $skip];
    }

    /**
     * @param Request $request
     * @return mixed
     * count nearest places of type specified in request in the buffer distance
     */

    public function numberOfNearestPlaces(Request $request)
    {
        $responder = new Responder();
        $input = self::filter($request);

        if (!(is_object($input))) {
            $lon1 = $input['lon'];
            $lat1 = $input['lat'];
            $type = $input['type'];
            $buf = $input['buffer'];
            $uri = $this->getUri($request);

            if ($uri === 'air') {
                $result = AirDistanceService::numberOfNearestPlaces($lon1, $lat1, $type, $buf);
            } else {
                $result = RouteDistanceService::numberOfNearestPlaces($lon1, $lat1, $type, $buf);
            }

            if ($result['data'] !== 'unauthorized' and $result['data']) {
                return response()->json($result['count'], 200);
            } elseif (is_null($result['data'])) {
                return $responder->respondNoFound('not found', 1014);
            } else {
                return $responder->respondError('unauthorized', 401, 1015);
            }
        } else {
            return $input;
        }

    }


    /**
     * @param Request $request
     * @param $path
     * @param $method
     * @return mixed
     * the nearest place of type specified in request
     */

    public function nearestPlace(Request $request, $path, $method)
    {
        $responder = new Responder();
        $input = self::filter($request);
        if (!(is_object($input))) {
            $lon1 = $input['lon'];
            $lat1 = $input['lat'];
            $type = $input['type'];
            $buf = $input['buffer'];
            $take = $input['take'];
            $skip = $input['skip'];

            if ($path === 'air-nearest') {
                if ($method === '$value') {
                    $result = AirDistanceService::nearestPlace($lon1, $lat1, $type);
                } else {
                    $result = AirDistanceService::listOfNearestPlaces($lon1, $lat1, $type, $buf, $take, $skip);
                }
            } else {
                if ($method === '$value') {
                    $result = RouteDistanceService::nearestPlace($lon1, $lat1, $type);
                } else {
                    $result = RouteDistanceService::listOfNearestPlaces($lon1, $lat1, $type, $buf, $take, $skip);
                }
            }


            if ($result['data'] !== 'unauthorized' and $result['data'] and $method === '$value') {
                return $responder->respondItemResult($result['data']);
            } elseif ($result['data'] !== 'unauthorized' and $result['data'] and $method === '$list') {
                return $responder->respondArrayResult($result['data'], $result['count']);
            } elseif (!$result['data']) {
                return $responder->respondNoFound('not found', 1012);
            } else {
                return $responder->respondError('unauthorized', 401, 1013);
            }
        } else {
            return $input;
        }
    }
}
