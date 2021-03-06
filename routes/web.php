<?php
use App\Http\Controllers\ExampleController;
use App\Http\Controllers\DistanceController;

/** @var \Laravel\Lumen\Routing\Router $router */


/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('/count', 'DistanceController@count');
$router->get('/list', 'DistanceController@index');
$router->get('/{nearest}', 'DistanceController@nearestPlace');




