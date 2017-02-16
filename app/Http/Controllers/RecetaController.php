<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Traits\SyncTrait;
use App\Http\Requests;

use App\Models\Requisicion;
use App\Models\Configuracion;
use App\Models\Usuario;
use App\Models\Receta;
use App\Models\RecetaDetalles;
use App\Models\Salida;
use App\Models\SalidaDetalles;
use App\Models\StockInsumo;
use App\Models\SalidaDetallesDesglose;

use App\Models\ConfiguracionAplicacion;
use JWTAuth;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB, \PDF, \Storage, Exception;

class RecetaController extends Controller
{
    use SyncTrait;
    public function index(Request $request){

        try{
            $usuario = JWTAuth::parseToken()->getPayload();
            $configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();

            $elementos_por_pagina = 50;
            $pagina = Input::get('pagina');
            if(!$pagina){
                $pagina = 1;
            }

            $query = Input::get('query');
            $filtro = Input::get('filtro');

            $recurso = Salida::with("salidaDetalle", "receta", "receta.recetaDetalle")->where('clues','=',$usuario->get('id'))->where("tipo_salida_id", 4);
            //$recurso = Salida::with("salidaDetalle")->where('clues','=',$usuario->get('id'));
            $totales = $recurso->count();

            $recurso = $recurso->skip(($pagina-1)*$elementos_por_pagina)->take($elementos_por_pagina)
                ->orderBy('created_at','asc')
                //->orderBy('created_at','desc')
                ->get();

            return Response::json(['data'=>$recurso,'totales'=>$totales],200);

        }catch(Exception $ex){
            return Response::json(['error'=>$ex->getMessage()],500);
        }
    }

    public function show(Request $request, $id){
        $data = Salida::with("salidaDetalle","receta", "receta.recetaDetalle", "receta.recetaDetalle.insumo")->find($id);

        $usuario = JWTAuth::parseToken()->getPayload();
        $configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();

        return Response::json([ 'data' => $data, 'configuracion'=>$configuracion],200);
    }

    public function store(Request $request){
        $mensajes = [
            'required'      => "required",
            'array'         => "array",
            'min'           => "min",
            'unique'        => "unique",
            'date'          => "date"
        ];

        $reglas_receta = [
            'autoriza'            =>'required',
            'responsable'             =>'required',
            'recibe'       =>'required'
        ];

        $reglas_configuracion = [
            'director_unidad'               => 'required',
            'administrador'                 => 'required',
            'encargado_almacen'             => 'required',
            'coordinador_comision_abasto'   => 'required',
            'lugar_entrega'                 => 'required'
        ];

        $parametros = Input::all();
        //return Response::json([ 'data' => $parametros ],200);
        try {

            DB::beginTransaction();

            $usuario = JWTAuth::parseToken()->getPayload();
            $configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();
            $empresa = $configuracion->empresa_clave;
            $clues = $configuracion->clues;
            //return Response::json([ 'data' => $configuracion->id ],200);

            $arreglo_salida = array();
            $arreglo_salida['acta_id']          = 0;
            $arreglo_salida['tipo_salida_id']   = 4;
            $arreglo_salida['clues']            = $usuario->get('clues');
            $arreglo_salida['realiza']          = $parametros['responsable'];
            $arreglo_salida['autoriza']         = $parametros['autoriza'];
            $arreglo_salida['recibe']           = $parametros['recibe'];
            $arreglo_salida['estatus']          = 2;


            $salida = Salida::create($arreglo_salida);
            $arreglo_receta = array();

            $arreglo_receta['usuario_id']       = 24;
            $arreglo_receta['salida_id']        = $salida->id;
            $arreglo_receta['folio_interno']    = str_pad((Receta::where("clues", $usuario->get('clues'))->count() + 1), 10, "0", STR_PAD_LEFT);
            $arreglo_receta['clues']            = $usuario->get('clues');
            $arreglo_receta['tipo_receta']      = 1;
            $arreglo_receta['medico']           = $parametros['responsable'];
            $arreglo_receta['paciente']         = $parametros['recibe'];
            $arreglo_receta['diagnostico']      = $parametros['diagnostico'];
            $receta = Receta::create($arreglo_receta);

            $cantidad_total_surtido = 0;
            foreach ($parametros['insumos'] as $inputs_salida) {

                /*Verifica invetario existente*/
                $inventario = 0;
                $insumo_inventario = StockInsumo::where("insumo_id", $inputs_salida['id'])
                    ->where("clues", $usuario->get('clues'))
                    ->where("disponible", ">", 0)
                    ->select(DB::RAW("sum(disponible) as inventario"))
                    ->groupBy("insumo_id");

                $insumo_inventario = $insumo_inventario->first();
                if($insumo_inventario)
                    $inventario = $insumo_inventario['inventario'];
                /**/

                $receta_detalles = array();
                $receta_detalles['receta_id']       = $receta->id;
                $receta_detalles['insumo_id']       = $inputs_salida['id'];
                $receta_detalles['cantidad']        = $inputs_salida['cantidad'];
                $receta_detalles['cantidad_gotas']  = $inputs_salida['cantidad'];
                $receta_detalles['duplicar_dosis']  = ($inputs_salida['duplicar_dosis'])? 1:0;
                $receta_detalles['frecuencia']      = $inputs_salida['frecuencia'];
                $receta_detalles['duracion']        = $inputs_salida['dias'];
                $receta_detalles['observaciones']   = "";
                $receta_detalles_input = RecetaDetalles::create($receta_detalles);

                $salida_detalles = array();
                $salida_detalles['salida_id']               = $salida->id;
                $salida_detalles['clues']                   = $usuario->get('clues');
                $cantidad_dosis                             = ($inputs_salida['cantidad'] * (($inputs_salida['presentacion_id'] == 15)?(($inputs_salida['duplicar_dosis'])?2:1):1));
                $salida_detalles['cantidad_solicitada']     = round(((($cantidad_dosis * $inputs_salida['cantidad_unidad']) * (24 / $inputs_salida['frecuencia']) * $inputs_salida['dias']) / $inputs_salida['cantidad_presentacion']), 0, PHP_ROUND_HALF_UP); ;
                $salida_detalles['cantidad_surtido']        = (($inventario>0)?(($inventario>$salida_detalles['cantidad_solicitada'])? $salida_detalles['cantidad_solicitada']: $inventario):0);
                $salida_detalles['cantidad_no_surtido']     = (($salida_detalles['cantidad_surtido']==$salida_detalles['cantidad_solicitada'])? 0: ($salida_detalles['cantidad_solicitada'] - $salida_detalles['cantidad_surtido']));
                $salida_detalles['insumo_id']               = $inputs_salida['id'];
                $salida_detalles_input = SalidaDetalles::create($salida_detalles);

                if($salida_detalles['cantidad_surtido'] > 0)
                {
                    $stock = StockInsumo::where("clues",$usuario->get('clues'))
                        ->where('insumo_id',$inputs_salida['id'])
                        ->where('fecha_caducidad',">", date('Y-m-d'))
                        ->orderBy('fecha_caducidad')
                        ->get();
                    $cantidad_evaluar = $salida_detalles['cantidad_surtido'];
                    $arreglo_desglose = array();
                    $arreglo_desglose['salida_detalle_id'] = $salida_detalles_input->id;
                    foreach($stock as $value)
                    {
                        if($cantidad_evaluar == 0)
                        {
                            break;
                        }else{
                            $stock_insumo = StockInsumo::find($value['id']);
                            if($value['disponible'] <= $cantidad_evaluar)
                            {
                                $stock_insumo->disponible   =   0;
                                $stock_insumo->usado        =   ($value['usado'] + $value['disponible']);
                                $cantidad_evaluar          -=   $value['disponible'];
                                $stock_insumo->update();

                                $arreglo_desglose['id_stock_insumo']    = $value['id'];
                                $arreglo_desglose['cantidad']           = $value['disponible'];
                                SalidaDetallesDesglose::create($arreglo_desglose);

                            }else{
                                $stock_insumo->disponible   =   ($value['disponible'] - $cantidad_evaluar);
                                $stock_insumo->usado        =   ($value['usado'] + $cantidad_evaluar);
                                $cantidad_save              =   $cantidad_evaluar;
                                $cantidad_evaluar           =   0;
                                $stock_insumo->update();

                                $arreglo_desglose['id_stock_insumo']    = $value['id'];
                                $arreglo_desglose['cantidad']           = $cantidad_save;
                                SalidaDetallesDesglose::create($arreglo_desglose);
                            }

                        }
                    }
                 }
            }

            DB::commit();

            return Response::json([ 'data' => $salida ],200);
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage(), 'line' => $e->getLine()], HttpResponse::HTTP_CONFLICT);
        }
    }

}
