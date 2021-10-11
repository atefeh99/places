<?php

namespace App\Http\Controllers;

use App\Exceptions\UnauthorizedUserException;
use Illuminate\Http\Request;
use App\Http\Services\RouteDistanceService;
use App\Http\Services\DistanceService;
use App\Helper\OdataQueryParser;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\Responder;
use App\Exceptions\Handler;


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
        $subcategory = env('DEFAULT_SUBCATEGORY');
        $buf = env('DEFAULT_BUFFER');
        $sort = env('SORTED');

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
//        if (isset($odata_query['orderBy'])) {
//            $validate = Validator::make(['orderBy' => $odata_query['orderBy']], [
//                'orderBy' => 'required'
//            ]);
//            if ($validate->fails()) {
//                return $responder->respondInvalidParams('1025', $validate->errors(), 'bad request');
//            }
////            dd($odata_query['orderBy']);
//            $sort = true;
//        } else {
//            $sort = env('SORTED');
//        }

        if (isset($odata_query['filter'])) {
            foreach ($odata_query['filter'] as $item) {
                if ($item['left'] == 'lon' && $item['operator'] == '=') {
                    $validate = Validator::make(['lon' => $item['right']], [
                        'lon' => 'numeric|required|between:-180,180'
                    ]);
                    if ($validate->fails()) {
                        return $responder->respondInvalidParams('1002', $validate->errors(), 'bad request');
                    }
                    $lon = (float)$item['right'];
                }

                if ($item['left'] == 'lat' && $item['operator'] == '=') {
                    $validate = Validator::make(['lat' => $item['right']], [
                        'lat' => 'numeric|required|between:-90,90'
                    ]);
                    if ($validate->fails()) {
                        return $responder->respondInvalidParams('1003', $validate->errors(), 'bad request');
                    }
                    $lat = (float)$item['right'];
                }

                if ($item['left'] == 'subcategory' && $item['operator'] == '=') {
                    $validate = Validator::make(['subcategory' => $item['right']], [
                        'subcategory' => 'string|required'
                    ]);
                    if ($validate->fails()) {
                        return $responder->respondInvalidParams('1004', $validate->errors(), 'bad request');
                    }
                    $subcategory = $item['right'];
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
                if ($item['left'] == 'sort' && $item['operator'] == '=') {
                    $validate = Validator::make(['sort' => $item['right']], [
                        'sort' => 'string|required'
                    ]);
                    if ($validate->fails()) {
                        return $responder->respondInvalidParams('2003', $validate->errors(), 'bad request');
                    }
                    $sort = $item['right'];
                }
            }
        }
        return ['lon' => $lon, 'lat' => $lat, 'subcategory' => $subcategory, 'buffer' => $buf, 'take' => $take, 'skip' => $skip, 'sort' => $sort];
    }

    /**
     * @param Request $request
     * @return mixed
     * count nearest places of subcategory specified in request in the buffer distance
     */

    public function count(Request $request)
    {
        $responder = new Responder();
        $input = self::filter($request);
        if (!(is_object($input))) {
            $lon1 = $input['lon'];
            $lat1 = $input['lat'];
            $subcategory = $input['subcategory'];
            $buf = $input['buffer'];
            $result = DistanceService::numberOfNearestPlaces($lon1, $lat1, $subcategory, $buf);
            if ($result['data']) {
                return $responder->respondItemResult(["count" => $result['count']]);
            } else {
                return $responder->respondNoFound('not found', 1009);
            }
        } else {
            return $input;
        }

    }

    /**
     * @param Request $request
     * @return mixed
     */

    public function index(Request $request)
    {
        $responder = new Responder();
        $input = self::filter($request);
        if (!(is_object($input))) {
            $lon1 = $input['lon'];
            $lat1 = $input['lat'];
            $subcategory = $input['subcategory'];
            $buf = $input['buffer'];
            $take = $input['take'];
            $skip = $input['skip'];
            $sort = $input['sort'];
            $result = DistanceService::listOfNearestPlaces($lon1, $lat1, $subcategory, $buf, $take, $skip, $sort);
            if ($result['data']) {
                return $responder->respondArrayResult($result['data'], $result['count']);
            } else {
                return $responder->respondNoFound('not found', 1012);
            }

        } else {
            return $input;
        }
    }


    /**
     * @param Request $request
     * @param $nearest
     * the nearest place of subcategory specified in request
     */

    public function nearestPlace(Request $request, $nearest)
    {
        $input = self::filter($request);
        $responder = new Responder();

        if (!(is_object($input))) {
            $lon1 = $input['lon'];
            $lat1 = $input['lat'];
            $subcategory = $input['subcategory'];
            if ($nearest === 'air-nearest') {
                $result = DistanceService::airNearest($lon1, $lat1, $subcategory);

            } else {
                $api_key = $request->header('x-api-key');
                if (!isset($api_key)) {
                    throw new UnauthorizedUserException(trans('messages.custom.unauthorized_user'), 2001);
                }
                $result = DistanceService::routeNearest($lon1, $lat1, $subcategory, $api_key);
            }

            if ($result['data'] !== 'unauthorized' and $result['data']) {
                return $responder->respondItemResult($result['data']);
            } elseif (!$result['data']) {
                return $responder->respondNoFound('not found', 1014);
            } else {
                return $responder->respondError('unauthorized', 401, 1500);
            }

        } else {
            return $input;
        }

    }


}
