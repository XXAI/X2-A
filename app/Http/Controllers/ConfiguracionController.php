<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use App\Models\Configuracion;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB, \PDF, \Storage, \ZipArchive;

class ConfiguracionController extends Controller
{
    
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id){
        $empresas = [
            ['clave'=>'disur','nombre'=>'DISTRIBUIDORA DISUR, S.A. DE C.V.'],
            ['clave'=>'exfarma','nombre'=>'EXFARMA, S.A. DE C.V.']
        ];
        return Response::json([ 'data' => Configuracion::find(1), 'catalogos' => ['empresas' => $empresas ]],200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id){
        $mensajes = [
            'required'      => "required",
            'unique'        => "unique",
            'date'          => "date"
        ];

        $reglas = [
            'clues'             =>'required',
            'clues_nombre'      =>'required',
            'empresa_clave'     =>'required',
            'director_unidad'   =>'required'
        ];

        $empresas = [
            'disur'=> 'DISTRIBUIDORA DISUR, S.A. DE C.V.',
            'exfarma'=> 'EXFARMA, S.A. DE C.V.'
        ];

        $inputs = Input::all();

        $v = Validator::make($inputs, $reglas, $mensajes);
        if ($v->fails()) {
            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
        }

        try {
            $inputs['empresa_nombre'] = $empresas[$inputs['empresa_clave']];
            $configuracion = Configuracion::find(1);

            $configuracion->update($inputs);

            return Response::json([ 'data' => $configuracion ],200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage(), 'line' => $e->getLine()], HttpResponse::HTTP_CONFLICT);
        }
    }
}
