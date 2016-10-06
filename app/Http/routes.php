<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::post('obtener-token',    'AutenticacionController@autenticar');
Route::post('refresh-token',    'AutenticacionController@refreshToken');
Route::get('check-token',       'AutenticacionController@verificar');

Route::group(['middleware' => 'jwt'], function () {

    Route::resource('dashboard',       'DashboardController',      ['only' => ['index']]);
	Route::resource('usuarios',        'UsuarioController',        ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('configuracion',   'ConfiguracionController',  ['only' => ['show','update']]);
	Route::resource('roles', 	       'RolController',    	       ['only' => ['index']]);
    Route::resource('insumos',         'InsumoController',         ['only' => ['index']]);
    Route::resource('clues',           'CluesController',          ['only' => ['index']]);

    Route::resource('permisos', 'PermisoController', ['only' => ['index', 'show', 'store','update','destroy']]);

    Route::resource('actas',                    'ActaController',                   ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('requisicionesunidades',    'RequisicionesUnidadController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('requisiciones',            'RequisicionController',            ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('solicitudes',              'SolicitudController',              ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('pedidos',                  'PedidoController',                 ['only' => ['index', 'show', 'store']]);

    Route::get('acta-pdf/{id}',                         'ActaController@generarActaPDF');
    Route::get('requisiciones-pdf/{id}',                'ActaController@generarRequisicionPDF');
    Route::get('exportar-csv/{id}',                     'ActaController@generarJSON');
    Route::post('importar-csv',                         'ActaController@actualizarActa');
    Route::post('importar-csv-unidad',                  'RequisicionController@importar');
    Route::post('importar-zip-unidad',                  'RequisicionesUnidadController@importar');
    Route::get('sincronizar-validacion/{id}',           'ActaController@sincronizar');
    Route::get('exportar-csv-unidad/{id}',              'RequisicionesUnidadController@generarJSON');

    Route::get('sincronizar-entrega/{id}',           'PedidoController@sincronizar');

    Route::get('requisiciones-jurisdiccion-pdf','RequisicionController@generarRequisicionPDF');
    
    Route::group(['prefix' => 'sync','namespace' => 'Sync'], function () {
        Route::get('manual',    'SincronizacionController@manual');        
        Route::get('auto',      'SincronizacionController@auto');

        Route::post('importar', 'SincronizacionController@importarSync');
    });
    
});
