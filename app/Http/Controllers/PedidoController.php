<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Traits\SyncTrait;
use App\Http\Requests;
use App\Models\Acta;
use App\Models\Entrega;
use App\Models\StockInsumo;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\Configuracion;
use JWTAuth;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB, \PDF, \Storage, \ZipArchive, Exception;

class PedidoController extends Controller
{
    use SyncTrait;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request){
        try{
            //DB::enableQueryLog();
            $usuario = JWTAuth::parseToken()->getPayload();

            $elementos_por_pagina = 50;
            $pagina = Input::get('pagina');
            if(!$pagina){
                $pagina = 1;
            }

            $query = Input::get('query');
            $filtro = Input::get('filtro');

            $recurso = Acta::where('folio','like',$usuario->get('id').'/%')
                            ->where('estatus',4);

            if($query){
                $recurso = $recurso->where(function($condition)use($query){
                    $condition->where('folio','LIKE','%'.$query.'%')
                            ->orWhere('lugar_reunion','LIKE','%'.$query.'%')
                            ->orWhere('ciudad','LIKE','%'.$query.'%');
                });
            }

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
            
            $recurso = $recurso->with('requisiciones')
                                ->skip(($pagina-1)*$elementos_por_pagina)->take($elementos_por_pagina)
                                ->orderBy('estatus','asc')
                                ->orderBy('created_at','desc')
                                ->get();

            //$queries = DB::getQueryLog();
            //$last_query = end($queries);
            return Response::json(['data'=>$recurso,'totales'=>$totales],200);
        }catch(Exception $ex){
            return Response::json(['error'=>$e->getMessage()],500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request){
        $mensajes = [
            'required'      => "required",
            'array'         => "array",
            'min'           => "min",
            'unique'        => "unique",
            'date'          => "date"
        ];

        $reglas_entrega = [
            'proveedor_id'              =>'required',
            'fecha_entrega'             =>'required',
            'hora_entrega'              =>'required',
            //'fecha_proxima_entrega'   =>'sometimes_required',
            'nombre_recibe'             =>'required',
            'nombre_entrega'            =>'required'
        ];

        $inputs = Input::all();
        //var_dump(json_encode($inputs));die;

        $v = Validator::make($inputs, $reglas_entrega, $mensajes);
        if ($v->fails()) {
            return Response::json(['error' => $v->errors(), 'error_type'=>'form_validation'], HttpResponse::HTTP_CONFLICT);
        }

        //return Response::json(['data' => $inputs, 'error_type'=>'data_validation', 'error'=>'No seguir. Probando'], HttpResponse::HTTP_CONFLICT);

        try {

            DB::beginTransaction();

            //$max_acta = Acta::max('id');
            $usuario = JWTAuth::parseToken()->getPayload();
            $configuracion = Configuracion::where('clues',$usuario->get('id'))->first();

            $proveedor_id = $inputs['proveedor_id'];

            //Se obtiene el acta con la entrega abierta del proveedor a guardar
            $acta = Acta::with([
                        'entregas'=>function($query)use($proveedor_id){
                            $query->where('proveedor_id',$proveedor_id)->where('estatus','<',3);
                        },
                        'requisiciones.insumos'=>function($query)use($proveedor_id){
                            $query->wherePivot('cantidad_validada','>',0)->wherePivot('proveedor_id',$proveedor_id);
                        }
                    ])->find($inputs['acta_id']);

            //Checamos si son necesarias mas entregas.
            $suma_pedido = 0;
            $suma_entregado = 0;
            foreach ($acta->requisiciones as $requisicion) {
                foreach ($requisicion->insumos as $insumo) {
                    $suma_pedido += $insumo->pivot->cantidad_validada;
                    $suma_entregado += $insumo->pivot->cantidad_recibida;
                }
            }

            if($suma_entregado >= $suma_pedido){
                return Response::json(['error' =>'Este proveedor ya ha entregado la totalidad de los insumos', 'error_type'=>'data_validation'], HttpResponse::HTTP_CONFLICT);
            }

            //Si la entrega existe se prepara para modificar de lo contrario se crea una nueva
            if(count($acta->entregas)){
                $entrega = $acta->entregas[0];
            }else{
                $entrega = new Entrega();
            }

            $entrega->proveedor_id          = $proveedor_id;
            $entrega->fecha_entrega         = $inputs['fecha_entrega'];
            $entrega->hora_entrega          = $inputs['hora_entrega'];
            if(isset($inputs['fecha_proxima_entrega'])){
                $entrega->fecha_proxima_entrega = $inputs['fecha_proxima_entrega'];
            }
            $entrega->nombre_recibe         = $inputs['nombre_recibe'];
            $entrega->nombre_entrega        = $inputs['nombre_entrega'];
            $entrega->estatus               = $inputs['estatus'];

            if($acta->entregas()->save($entrega)){
                $entrega->load('stock');
                $stock_guardado = [];
                foreach ($entrega->stock as $stock) {
                    $stock_guardado[$stock->insumo_id] = $stock;
                }
                $guardar_stock = [];
                $cantidades_insumos = [];
                //foreach ($inputs['ingresos_requisicion'] as $requisicion) {
                    foreach ($inputs['ingresos_requisicion'] as $ingreso) {
                        if(!isset($stock_guardado[$ingreso['insumo_id']])){
                            $nuevo_ingreso = new StockInsumo();
                        }else{
                            $nuevo_ingreso = $stock_guardado[$ingreso['insumo_id']];
                        }

                        $nuevo_ingreso->clues               = $configuracion->clues;
                        $nuevo_ingreso->insumo_id           = $ingreso['insumo_id'];
                        $nuevo_ingreso->lote                = $ingreso['lote'];
                        $nuevo_ingreso->fecha_caducidad     = $ingreso['fecha_caducidad'];
                        $nuevo_ingreso->cantidad_entregada  = $ingreso['cantidad'];

                        $cantidades_insumos[$nuevo_ingreso->insumo_id] = $nuevo_ingreso->cantidad_entregada;

                        $guardar_stock[] = $nuevo_ingreso;
                    }
                //}
                $entrega->stock()->saveMany($guardar_stock);

                if($entrega->estatus == 2){
                    $acta->load('requisiciones.insumos');

                    for($i = 0, $total = count($acta->requisiciones); $i < $total; $i++) {
                        $requisicion = $acta->requisiciones[$i];
                        if(count($requisicion->insumos)){
                            $requisicion_insumos_sync = [];
                            for ($j=0, $total_insumos = count($requisicion->insumos); $j < $total_insumos ; $j++) { 
                                $insumo = $requisicion->insumos[$j];
                                $insumo_sync = [
                                    'requisicion_id'    => $insumo->pivot->requisicion_id,
                                    'insumo_id'         => $insumo->pivot->insumo_id,
                                    'cantidad'          => $insumo->pivot->cantidad,
                                    'total'             => $insumo->pivot->total,
                                    'cantidad_validada' => $insumo->pivot->cantidad_validada,
                                    'total_validado'    => $insumo->pivot->total_validado,
                                    'cantidad_recibida' => $insumo->pivot->cantidad_recibida,
                                    'total_recibido'    => $insumo->pivot->total_recibido,
                                    'proveedor_id'      => $insumo->pivot->proveedor_id
                                ];
                                if($insumo_sync['proveedor_id'] == $proveedor_id){
                                    if(!$insumo_sync['cantidad_recibida']){
                                        $insumo_sync['cantidad_recibida'] = 0;
                                        $insumo_sync['total_recibido'] = 0;
                                    }
                                    if(isset($cantidades_insumos[$insumo_sync['insumo_id']])){
                                        $insumo_sync['cantidad_recibida'] += $cantidades_insumos[$insumo_sync['insumo_id']];
                                        $insumo_sync['total_recibido'] += ($cantidades_insumos[$insumo_sync['insumo_id']] * $insumo->precio);
                                    }
                                }
                                $requisicion_insumos_sync[] = $insumo_sync;
                            }
                            $requisicion->insumos()->sync([]);
                            $requisicion->insumos()->sync($requisicion_insumos_sync);
                            $sub_total = $requisicion->insumos()->sum('total_recibido');
                            if($requisicion->tipo_requisicion == 3){
                                $iva = $sub_total*16/100;
                            }else{
                                $iva = 0;
                            }
                            $requisicion->sub_total_recibido = $sub_total;
                            $requisicion->iva_recibido = $iva;
                            $requisicion->gran_total_recibido = $sub_total + $iva;
                            $requisicion->save();
                        }
                    }

                    $entrega->load('stock');
                    $actualizar_stock = [];
                    for($i = 0, $total = count($entrega->stock); $i < $total; $i++) {
                        $insumo = $entrega->stock[$i];
                        $insumo->stock = 1; //Stock activo = 1, inactivo = null
                        $insumo->usado = 0;
                        $insumo->disponible = $insumo->cantidad_entregada;
                        $actualizar_stock[] = $insumo;
                    }
                    $entrega->stock()->saveMany($actualizar_stock);
                }
            }

            DB::commit();

            if($entrega->estatus == 2){
                $resultado = $this->actualizarEntregaCentral($entrega->id);
                if(!$resultado['estatus']){
                    return Response::json(['error' => 'Error al intentar sincronizar la entrega del pedido', 'error_type' => 'data_validation', 'message'=>$resultado['message']], HttpResponse::HTTP_CONFLICT);
                }
                $entrega = Entrega::find($entrega->id);
            }

            return Response::json([ 'data' => $entrega ],200);

        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage(), 'line' => $e->getLine()], HttpResponse::HTTP_CONFLICT);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id){
        $acta = Acta::with([
            'requisiciones'=>function($query){
                $query->orderBy('tipo_requisicion');
            },'requisiciones.insumos'=>function($query){
                $query->where('cantidad_validada','>',0)->orderBy('lote');
            },'entregas.stock'])->find($id);

        $usuario = JWTAuth::parseToken()->getPayload();
        $configuracion = Configuracion::where('clues',$usuario->get('id'))->first();

        $proveedores = Proveedor::all()->lists('nombre','id');

        return Response::json([ 'data' => $acta, 'configuracion'=>$configuracion, 'proveedores' => $proveedores],200);
    }
    
    public function sincronizar($id){
        try {
            $entrega = Entrega::find($id);
            if(!$entrega){
                return Response::json(['error' => 'Entrega no encontrada.', 'error_type' => 'data_validation'], HttpResponse::HTTP_CONFLICT);
            }
            if($entrega->estatus == 2){
                $resultado = $this->actualizarEntregaCentral($id);
                if(!$resultado['estatus']){
                    return Response::json(['error' => 'Error al intentar sincronizar la entrega', 'error_type' => 'data_validation', 'message'=>$resultado['message'],'line'=>$resultado['line'],'extra_data'=>$resultado['extra_data']], HttpResponse::HTTP_CONFLICT);
                }
                $entrega = Entrega::find($id);
            }else{
                return Response::json(['error' => 'La entrega no esta lista para ser enviada.', 'error_type' => 'data_validation'], HttpResponse::HTTP_CONFLICT);
            }
            return Response::json([ 'data' => $entrega ],200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage(), 'line' => $e->getLine()], HttpResponse::HTTP_CONFLICT);
        }
    }
}
