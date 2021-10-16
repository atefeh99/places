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
                    'code' => 3000
                ],
                'status' => [
                    Response::HTTP_INTERNAL_SERVER_ERROR
                ]
            ];

            if ($e instanceof UnauthorizedUserException) {
                $return_object = [
                    'data' => [
                        'status' => Response::HTTP_UNAUTHORIZED,
                        'message' => trans('messages.custom.401'),
                        'code' => $e->getErrorCode()
                    ],
                    'status' => 401
                ];
            } elseif ($e instanceof Missing404Exception) {
                $return_object = [
                    'data' => [
                        'status' => Response::HTTP_NOT_FOUND,
                        'message' => trans('messages.custom.error.databaseError'),
                        'code' => 3001
                    ],
                    'status' => Response::HTTP_NOT_FOUND,
                ];

            } elseif ($e instanceof NoNodesAvailableException) {
                $return_object = [
                    'data' => [
                        'status' => Response::HTTP_NOT_FOUND,
                        'message' => trans('messages.custom.503'),
                        'code' => 3002

                    ],
                    'status' => 503,

                ];
            } elseif ($e instanceof RequestTimeout408Exception) {
                $return_object = [
                    'data' => [
                        'status' => Response::HTTP_REQUEST_TIMEOUT,
                        'message' =>  trans('messages.custom.408'),
                        'code' => 3003

                    ],
                    'status' => 408,

                ];
            } /*invalid param */ elseif ($e instanceof \InvalidArgumentException) {
                $return_object = [
                    'data' => [
                        'status' => Response::HTTP_BAD_REQUEST,
                        'message' => trans('messages.custom.400'),
                        'code' =>  3004

                    ],
                    'status' => 400,

                ];
            } /*invalid method */ elseif ($e instanceof MethodNotAllowedHttpException) {
                $return_object = [
                    'data' => [
                        'status' => Response::HTTP_METHOD_NOT_ALLOWED,
                        'message' =>  trans('messages.custom.405'),
                        'code' =>  3005

                    ],
                    'status' => 405,

                ];
            } elseif ($e instanceof AuthorizationException) {
                $return_object = [
                    'data' => [
                        'status' => Response::HTTP_UNAUTHORIZED,
                        'message' =>  trans('messages.custom.401'),
                        'code' =>  3006

                    ],
                    'status' => 401,

                ];
            } elseif ($e instanceof ValidationException) {
                $return_object = [
                    'data' => [
                        'status' => Response::HTTP_UNAUTHORIZED,
                        'message' =>  trans('messages.custom.401'),
                        'code' => 3007

                    ],
                    'status' => 401,

                ];
            } /* wasn't able to find a route to for the request*/ elseif ($e instanceof NotFoundHttpException) {
                $return_object = [
                    'data' => [
                        'status' => Response::HTTP_NOT_FOUND,
                        'message' => trans('messages.custom.404'),
                        'code' => 3008

                    ],
                    'status' => 404,
                ];
            } elseif ($e instanceof HttpException) {
                $return_object = [
                    'data' => [
                        'status' => Response::HTTP_BAD_REQUEST,
                        'message' => trans('messages.custom.400'),
                        'code' => 3009

                    ],
                    'status' => 400,

                ];
            } /*invalid uri */ elseif ($e instanceof BadRequestException) {
                $return_object = [
                    'data' => [
                        'status' => Response::HTTP_BAD_REQUEST,
                        'fields' => $e->getFields(),
                        'message' => trans('messages.custom.error.badParams'),
                        'code' => 3010
                    ],
                    'status' => $e->getStatusCode(),

                ];
            } elseif ($e instanceof Exception) {
                $return_object = [
                    'data' => [
                        'status'=> $e->getStatusCode(),
                        'message' => 'Server Error',
                        'code' => 2011
                    ],
                    'status' => 500,

                ];
            }
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
