<?php

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

//Ruta para visualizar rápidamente el servicio mediante get
$router->get('/', function () {
    return view('welcome');
});
//--------------------------------------------------------

$router->get('tracking/{trackingNumber}', 'EstafetaController@tracking');
$router->post('labels', 'EstafetaController@createLabel');
$router->post('coverage', 'EstafetaController@getCoverage');
