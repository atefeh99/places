<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\{HttpException, MethodNotAllowedHttpException, NotFoundHttpException};
use Elasticsearch\Common\Exceptions\{
    Missing404Exception,
    RequestTimeout408Exception,
    NoNodesAvailableException,
};
use Illuminate\Http\Response;
use App\Modules\Slack\Slack;
use Exception;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Throwable;


class Handler extends ExceptionHandler
{

    protected $dontReport = [];


    public function report(Throwable $exception)
    {
        $debug = env('APP_DEBUG');

        if ($debug) {
            return parent::report($exception);
        }
    }


    public function render($request, Throwable $e)
    {
        $response = parent::render($request, $e);
        $debug = env('APP_DEBUG');
        if (!$debug) {
            $return_object = [
                'data' => [
                    'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'message' => trans('messages.custom.' . Response::HTTP_INTERNAL_SERVER_ERROR),
                    'code' => 101
                ],
                'status' => [
                    Response::HTTP_INTERNAL_SERVER_ERROR
                ]
            ];

            if ($e instanceof UnauthorizedUserException) {
                $return_object = [
                    'data' => [
                        'status' => Response::HTTP_UNAUTHORIZED,
                        'message' => $e->getMessage(),
                        'code' => $e->getErrorCode()
                    ],
                    'status' => Response::HTTP_UNAUTHORIZED
                ];
            } elseif ($e instanceof Missing404Exception) {
                $return_object = [
                    'status' => 404,
                    'message' => $e->getMessage(),
                    'code' => 2001
                ];

            } elseif ($e instanceof NoNodesAvailableException) {
                $return_object = [
                    'status' => 503,
                    'message' => trans('messages.custom.503'),
                    'code' => 2002
                ];
            } elseif ($e instanceof RequestTimeout408Exception) {
                $return_object = [
                    'status' => 408,
                    'message' => trans('messages.custom.408'),
                    'code' => 2003
                ];
            } /*invalid param */ elseif ($e instanceof \InvalidArgumentException) {
                $return_object = [
                    'status' => 400,
                    'message' => $e->getMessage(),
                    'code' => 2004
                ];
            } /*invalid method */ elseif ($e instanceof MethodNotAllowedHttpException) {
                $return_object = [
                    'status' => 405,
                    'message' => trans('messages.custom.405'),
                    'code' => 2005
                ];
            } elseif ($e instanceof AuthorizationException) {
                $return_object = [
                    'status' => 401,
                    'message' => trans('messages.custom.401'),
                    'code' => 2006
                ];
            } elseif ($e instanceof ValidationException) {
                $return_object = [
                    'status' => 400,
                    'message' => trans('messages.custom.400'),
                    'code' => 2007
                ];
            } /* wasn't able to find a route to for the request*/ elseif ($e instanceof NotFoundHttpException) {
                $return_object = [
                    'status' => 404,
                    'message' => trans('messages.custom.404'),
                    'code' => 2008
                ];
            } elseif ($e instanceof HttpException) {
                $return_object = [
                    'status' => 400,
                    'message' => trans('messages.custom.400'),
                    'code' => 2009
                ];
            } /*invalid uri */ elseif ($e instanceof BadRequestException) {
                $return_object = [
                    'status' => $e->getCode(),
                    'message' => $e->getMessage(),
                    'fields' => $e->getFields(),
                    'code' => $e->getStatusCode()
                ];
            } elseif ($e instanceof Exception) {
                $return_object = [
                    'status' => 500,
                    'message' => 'Server Error',
                    'code' => 2010
                ];
            }
            $debug = env('APP_DEBUG');
            $response = parent::render($request, $e);

            /*not found the index */

            if ($return_object['status'] >= 500) {
                Slack::send($e, $request, $response);
            }
            return response()
                ->json($return_object['data'], $return_object['status'])
                ->header('Access-Control-Allow-Origin', '*');
        }
        return $response;


    }


}
