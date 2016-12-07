<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Traits\SyncTrait;
use App\Http\Requests;


use App\Models\Requisicion;
use App\Models\Configuracion;
use App\Models\Usuario;
use App\Models\Salida;
use App\Models\ConfiguracionAplicacion;
use JWTAuth;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB, \PDF, \Storage, \ZipArchive, Exception;
use \Excel;

class SalidaController extends Controller
{
    use SyncTrait;
    public function index(Request $request){

        try{
            $usuario = JWTAuth::parseToken()->getPayload();

            $elementos_por_pagina = 50;
            $pagina = Input::get('pagina');
            if(!$pagina){
                $pagina = 1;
            }

            $query = Input::get('query');
            $filtro = Input::get('filtro');

            $recurso = Salida::where('clues','=',$usuario->get('clues'))->with('acta', 'tipoSalida', "salidaDetalle");

            /*if($query){
                $recurso = $recurso->where(function($condition)use($query){
                    $condition->where('folio','LIKE','%'.$query.'%')
                        ->orWhere('clues','LIKE','%'.$query.'%');
                        //->orWhere('tipo','=','%'.$query.'%');
                });
            }*/

            /*if($filtro){
                if(isset($filtro['estatus'])){
                    if($filtro['estatus'] == 'validados'){
                        $recurso = $recurso->where('estatus','3');
                    }else if($filtro['estatus'] == 'enviados'){
                        $recurso = $recurso->where('estatus','2');
                    }
                }
            }*/

            $totales = $recurso->count();

            $recurso = $recurso->skip(($pagina-1)*$elementos_por_pagina)->take($elementos_por_pagina)
                                ->orderBy('estatus','asc')
                                //->orderBy('created_at','desc')
                                ->get();

            //$queries = DB::getQueryLog();
            //$last_query = end($queries);


            return Response::json(['data'=>$recurso,'totales'=>$totales],200);
        }catch(Exception $ex){
            return Response::json(['error'=>$ex->getMessage()],500);
        }

    }
}
