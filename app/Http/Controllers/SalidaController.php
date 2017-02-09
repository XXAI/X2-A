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
use App\Models\Acta;
use App\Models\SalidaDetalles;
use App\Models\SalidaDetallesDesglose;
use App\Models\StockInsumo;
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

            $recurso = Salida::where('clues','=',$usuario->get('clues'))->where("salidas.tipo_salida_id", 1)->with('acta', 'tipoSalida', "salidaDetalle");

            $totales = $recurso->count();

            $recurso = $recurso->skip(($pagina-1)*$elementos_por_pagina)->take($elementos_por_pagina)
                                ->orderBy('estatus','asc')
                                //->orderBy('created_at','desc')
                                ->get();

            return Response::json(['data'=>$recurso,'totales'=>$totales],200);
        }catch(Exception $ex){
            return Response::json(['error'=>$ex->getMessage()],500);
        }
    }

    public function show(Request $request, $id){

        $recurso = Salida::where("id",$id)->with('acta', "salidaDetalle");

        $recurso = $recurso->first();

        return Response::json(['data'=>$recurso],200);
    }
    public function store(Request $request){
        $mensajes = [
            'required'      => "required",
            'array'         => "array",
            'min'           => "min",
            'unique'        => "unique",
            'date'          => "date"
        ];

        $reglas_acta = [
            'ciudad'            =>'required',
            'fecha'             =>'required',
            'hora_inicio'       =>'required',
            'hora_termino'      =>'required',
            'lugar_reunion'     =>'required'
        ];

        $reglas_configuracion = [
            'director_unidad'               => 'required',
            'administrador'                 => 'required',
            'encargado_almacen'             => 'required',
            'coordinador_comision_abasto'   => 'required',
            'lugar_entrega'                 => 'required'
        ];

        $parametros = Input::all();

        try {

            DB::beginTransaction();

            $usuario = JWTAuth::parseToken()->getPayload();
            $configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();
            $empresa = $configuracion->empresa_clave;
            $clues = $configuracion->clues;

            $arreglo_salida = array();
            $arreglo_salida['acta_id'] = $parametros['acta_id'];
            $arreglo_salida['tipo_salida_id'] = $parametros['selectedTipoSalida'];
            $arreglo_salida['clues'] = $usuario->get('clues');

            $arreglo_salida['estatus'] = 1;


            $salida = Salida::create($arreglo_salida);

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

                $count_insumos = 0;
                foreach ($inputs_salida['unidades'] as $inputs_insumos) {

                    //Verificacion de pedido y solicitudes realizadas
                    $acta = Acta::crossJoin("requisiciones", "requisiciones.acta_id", "=", "actas.id")
                        ->crossJoin("requisicion_insumo_clues", "requisicion_insumo_clues.requisicion_id", "=", "requisiciones.id")
                        ->where("actas.estatus", 4)
                        ->where("actas.id", '=', $parametros['acta_id'])
                        ->where("actas.id", '=', $parametros['acta_id'])
                        ->where("requisicion_insumo_clues.insumo_id", '=', $inputs_salida['id'])
                        ->select(
                            "requisicion_insumo_clues.cantidad_validada",
                            DB::RAW("(select clues_nombre from configuracion where configuracion.clues=requisicion_insumo_clues.clues) as clues_nombre"),
                            DB::RAW("(select sum(salida_detalles.cantidad_surtido) from salidas, salida_detalles
                                        where salidas.id=salida_detalles.salida_id and salidas.acta_id=actas.id and salida_detalles.insumo_id=requisicion_insumo_clues.insumo_id and salidas.estatus=2) as cantidad_suministrada"));


                    $acta = $acta->first();

                    //

                    $insumo = array();
                    $insumo['salida_id'] = $salida->id;
                    $insumo['clues'] = $inputs_insumos['clues_unidad'];
                    $insumo['cantidad_solicitada'] = $inputs_insumos['surtido_salida_unidad'];
                    $insumo['cantidad_surtido'] = $inputs_insumos['surtido_salida_unidad'];
                    $cantidad_solicitada = ($acta['cantidad_suministrada'] + $inputs_insumos['surtido_salida_unidad']);
                    $insumo['cantidad_no_surtido'] = ($acta['cantidad_validada'] - $cantidad_solicitada);
                    $insumo['insumo_id'] = $inputs_salida['id'];

                    $count_insumos += $inputs_insumos['surtido_salida_unidad'];
                    $cantidad_total_surtido += $inputs_insumos['surtido_salida_unidad'];

                    if($inventario < $inputs_insumos['surtido_salida_unidad'])
                    {
                        DB::rollBack();
                        return Response::json(["error"=>"No puede sutir más de lo que se encuentra en inventario", "insumo"=> $inputs_salida['id'] ],409);
                    }


                    if(($acta['cantidad_validada'] < ($acta['cantidad_suministrada'] + $inputs_insumos['surtido_salida_unidad'])))
                    {
                        DB::rollBack();
                        return Response::json(["error"=>"No puede sutir más de la cantidad validada", "insumo"=> $inputs_salida['id'] ],409);
                    }



                    $salida_detalle = SalidaDetalles::create($insumo);
                }

            }

            if($cantidad_total_surtido == 0)
            {
                DB::rollBack();
                throw new \Exception("No puede guardar una solicitud en ceros");
            }


            DB::commit();

            return Response::json([ 'data' => $salida ],200);
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage(), 'line' => $e->getLine()], HttpResponse::HTTP_CONFLICT);
        }
    }

    public function update(Request $request, $id){
        $mensajes = [
            'required'      => "required",
            'array'         => "array",
            'min'           => "min",
            'unique'        => "unique",
            'date'          => "date"
        ];

        $reglas_acta = [
            'ciudad'            =>'required',
            'fecha'             =>'required',
            'hora_inicio'       =>'required',
            'hora_termino'      =>'required',
            'lugar_reunion'     =>'required'
        ];

        $reglas_configuracion = [
            'director_unidad'               => 'required',
            'administrador'                 => 'required',
            'encargado_almacen'             => 'required',
            'coordinador_comision_abasto'   => 'required',
            'lugar_entrega'                 => 'required'
        ];

        $parametros = Input::all();


        try {

            DB::beginTransaction();

            $usuario = JWTAuth::parseToken()->getPayload();
            $configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();
            $empresa = $configuracion->empresa_clave;
            $clues = $configuracion->clues;

            $arreglo_salida = array();

            $salida = Salida::find($id);
            $salida->acta_id = $parametros['acta_id'];
            $salida->tipo_salida_id = $parametros['selectedTipoSalida'];

            if($parametros['estatus'] == 2)
            {
                $salida->realiza    = $parametros['realiza'];
                $salida->autoriza   = $parametros['autoriza'];
                $salida->recibe     = $parametros['recibe'];
                $salida->estatus    = $parametros['estatus'];
            }

            $salida = $salida->save();

            SalidaDetalles::where("salida_id", $id)->delete();


            $cantidad_total_surtido = 0;
            foreach ($parametros['insumos'] as $inputs_salida) {

                //Verifica invetario existente
                $inventario = 0;

                $insumo_inventario = StockInsumo::where("insumo_id", $inputs_salida['id'])
                    ->where("clues", $usuario->get('clues'))
                    ->where("disponible", ">", 0)
                    ->select(DB::RAW("sum(disponible) as inventario"))
                    ->groupBy("insumo_id");

                $insumo_inventario = $insumo_inventario->first();

                if($insumo_inventario)
                {
                    $inventario = $insumo_inventario['inventario'];
                }

                $count_insumos = 0;
                foreach ($inputs_salida['unidades'] as $inputs_insumos) {

                    //Verificacion de pedido y solicitudes realizadas
                    $acta = Acta::crossJoin("requisiciones", "requisiciones.acta_id", "=", "actas.id")
                        ->crossJoin("requisicion_insumo_clues", "requisicion_insumo_clues.requisicion_id", "=", "requisiciones.id")
                        ->where("actas.estatus", 4)
                        ->where("actas.id", '=', $parametros['acta_id'])
                        ->where("actas.id", '=', $parametros['acta_id'])
                        ->where("requisicion_insumo_clues.insumo_id", '=', $inputs_salida['id'])
                        ->select(
                            "requisicion_insumo_clues.cantidad_validada",
                            DB::RAW("(select clues_nombre from configuracion where configuracion.clues=requisicion_insumo_clues.clues) as clues_nombre"),
                            DB::RAW("(select sum(salida_detalles.cantidad_surtido) from salidas, salida_detalles
                                        where salidas.id=salida_detalles.salida_id and salidas.acta_id=actas.id and salida_detalles.insumo_id=requisicion_insumo_clues.insumo_id and salidas.estatus=2) as cantidad_suministrada"));

                    //
                    $acta = $acta->first();

                    $insumo = array();
                    $insumo['salida_id'] = $id;
                    $insumo['clues'] = $inputs_insumos['clues_unidad'];
                    $insumo['cantidad_solicitada'] = $inputs_insumos['surtido_salida_unidad'];
                    $insumo['cantidad_surtido'] = $inputs_insumos['surtido_salida_unidad'];
                    $cantidad_solicitada = ($acta['cantidad_suministrada'] + $inputs_insumos['surtido_salida_unidad']);
                    $insumo['cantidad_no_surtido'] = ($acta['cantidad_validada'] - $cantidad_solicitada);
                    $insumo['insumo_id'] = $inputs_salida['id'];

                    $count_insumos += $inputs_insumos['surtido_salida_unidad'];
                    $cantidad_total_surtido += $inputs_insumos['surtido_salida_unidad'];

                    if($inventario < $inputs_insumos['surtido_salida_unidad'])
                    {
                        DB::rollBack();
                        return Response::json(["error"=>"No puede sutir más de lo que se encuentra en inventario", "insumo"=> $inputs_salida['id'] ],409);
                    }


                    if(($acta['cantidad_validada'] < ($acta['cantidad_suministrada'] + $inputs_insumos['surtido_salida_unidad'])))
                    {
                        DB::rollBack();
                        return Response::json(["error"=>"No puede sutir más de la cantidad validada", "insumo"=> $inputs_salida['id'] ],409);
                    }



                    $salida_detalle = SalidaDetalles::create($insumo);

                    if($parametros['estatus'] == 2)
                    {
                        if($inputs_insumos['surtido_salida_unidad'] > 0)
                        {
                            $stock = StockInsumo::where("clues",$usuario->get('clues'))
                                                ->where('insumo_id',$inputs_salida['id'])
                                                ->where('fecha_caducidad',">", date('Y-m-d'))
                                                ->orderBy('fecha_caducidad')
                                                ->get();
                            $cantidad_evaluar = $inputs_insumos['surtido_salida_unidad'];
                            $arreglo_desglose = array();
                            $arreglo_desglose['salida_detalle_id'] = $salida_detalle->id;
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
                            //return Response::json([ 'data' => $stock, "clues"=>$inputs_insumos['clues_unidad'], "id"=>$inputs_salida['id'], 'fecha'=>date('Y-m-d') ],409);
                        }
                    }

                }

            }

            if($cantidad_total_surtido == 0)
            {
                DB::rollBack();
                return Response::json(["error"=>"No puede guardar una solicitud en ceros" ],409);
                //throw new \Exception("No puede guardar una solicitud en ceros");
            }


            DB::commit();

            return Response::json([ 'data' => $salida ],200);
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage(), 'line' => $e->getLine()], HttpResponse::HTTP_CONFLICT);
        }
    }
}
