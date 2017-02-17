<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Traits\SyncTrait;
use App\Http\Requests;
use App\Models\Acta;
use App\Models\Entrada;
use App\Models\StockInsumo;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\Configuracion;
use App\Models\Usuario;
use JWTAuth;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB, \PDF, \Storage, \ZipArchive, Exception;

class EntradaLibreController extends Controller
{
    use SyncTrait;

    public function catalogos(Request $request){
        try{
            $usuario = JWTAuth::parseToken()->getPayload();
            $proveedores = Proveedor::all();
            $configuracion = Configuracion::with('cuadroBasico')->where('clues',$usuario->get('clues'))->first();

            $data = [
                'proveedores' => $proveedores,
                'configuracion' => $configuracion
            ];

            return Response::json(['data'=>$data],200);
        }catch(Exception $ex){
            return Response::json(['error'=>$ex->getMessage(),'line'=>$ex->getLine()],500);
        }
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request){
        try{
            DB::enableQueryLog();
            $usuario = JWTAuth::parseToken()->getPayload();

            $elementos_por_pagina = 50;
            $pagina = Input::get('pagina');
            if(!$pagina){
                $pagina = 1;
            }

            $query = Input::get('query');
            $filtro = Input::get('filtro');

            $recurso = Entrada::with('proveedor')->where('entradas.clues',$usuario->get('clues'))->whereNull('entradas.acta_id');

            /*
            if($query){
                if(is_numeric($query)){
                    $actas = Requisicion::where ('numero',intval($query))->lists('acta_id');
                    $recurso = $recurso->whereIn('id',$actas);
                }else{
                    $recurso = $recurso->where(function($condition)use($query){
                        $condition->where('folio','LIKE','%'.$query.'%')
                                ->orWhere('lugar_reunion','LIKE','%'.$query.'%')
                                ->orWhere('ciudad','LIKE','%'.$query.'%');
                    });
                }
            }

            if($filtro){
                if(isset($filtro['estatus'])){
                    if($filtro['estatus'] == 'nuevos'){
                        $recurso = $recurso->whereNull('total_claves_recibidas');
                    }else if($filtro['estatus'] == 'incompletos'){
                        $recurso = $recurso->whereRaw('total_claves_recibidas < total_claves_validadas');
                    }else if($filtro['estatus'] == 'completos'){
                        $recurso = $recurso->whereRaw('total_claves_validadas = total_claves_recibidas');
                    }
                }
            }*/

            $totales = $recurso->count();
            
            $recurso = $recurso->select('entradas.*', DB::raw('sum(stock_insumos.cantidad_recibida) as cantidad_recibida'), 
                                        DB::raw('sum(stock_insumos.cantidad_recibida*insumos.precio) as total'))
                                ->leftjoin('stock_insumos','stock_insumos.entrada_id','=','entradas.id')
                                ->leftjoin('insumos','insumos.id','=','stock_insumos.insumo_id')
                                ->groupBy('entradas.id')
                                ->skip(($pagina-1)*$elementos_por_pagina)->take($elementos_por_pagina)
                                //->orderBy('estatus','asc')
                                ->orderBy('entradas.created_at','desc')
                                ->get();

            //$queries = DB::getQueryLog();
            //$last_query = end($queries);
            return Response::json(['data'=>$recurso,'totales'=>$totales],200);
        }catch(Exception $ex){
            return Response::json(['error'=>$ex->getMessage()],500);
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
            'required_if'   => "required",
            'array'         => "array",
            'min'           => "min",
            'unique'        => "unique",
            'date'          => "date"
        ];

        $reglas_entrega = [
            'proveedor_id'              =>'required',
            'fecha_recibe'             =>'required',
            'hora_recibe'              =>'required',
            'nombre_recibe'             =>'required_if:estatus,2',
            'nombre_entrega'            =>'required_if:estatus,2'
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
            $configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();

            $proveedor_id = $inputs['proveedor_id'];

            //Se obtiene el acta con la entrega abierta del proveedor a guardar
            $entrada = new Entrada();

            $entrada->proveedor_id          = $proveedor_id;
            $entrada->fecha_recibe          = $inputs['fecha_recibe'];
            $entrada->hora_recibe           = $inputs['hora_recibe'];
            $entrada->clues                 = $configuracion->clues;
            if($inputs['estatus'] > 1){
                $entrada->nombre_recibe         = $inputs['nombre_recibe'];
                $entrada->nombre_entrega        = $inputs['nombre_entrega'];
                if(isset($inputs['observaciones'])){
                    $entrada->observaciones         = $inputs['observaciones'];
                }
            }
            $entrada->estatus               = $inputs['estatus'];

            if($entrada->save()){
                //$entrada->load('stock');

                $guardar_stock = [];
                $eliminar_stock = [];
                $cantidades_insumos = [];
                $total_cantidad_recibida = 0;
                foreach ($inputs['insumos'] as $index => $ingreso) {
                    $insumo_id = $ingreso['insumo_id'];

                    $cantidades_insumos[$insumo_id] = $ingreso['cantidad'];

                    //iteramos sobre el que tiene mayor número de items
                    $total_lotes_form = count($ingreso['lotes']);

                    for($i = 0; $i < $total_lotes_form; $i++){
                        $nuevo_ingreso = new StockInsumo();

                        $nuevo_ingreso->clues               = $configuracion->clues;
                        $nuevo_ingreso->insumo_id           = $insumo_id;

                        if(isset($ingreso['lotes'][$i]['lote'])){
                            $nuevo_ingreso->lote                = $ingreso['lotes'][$i]['lote'];
                        }else{
                            $nuevo_ingreso->lote                = null;
                        }

                        if(isset($ingreso['lotes'][$i]['fecha_caducidad'])){
                            $nuevo_ingreso->fecha_caducidad     = $ingreso['lotes'][$i]['fecha_caducidad'];
                        }else{
                            $nuevo_ingreso->fecha_caducidad     = null;
                        }

                        $nuevo_ingreso->cantidad_recibida   = $ingreso['lotes'][$i]['cantidad'];

                        $total_cantidad_recibida += $ingreso['lotes'][$i]['cantidad'];

                        $guardar_stock[] = $nuevo_ingreso;
                    }
                }
                
                if(count($guardar_stock)){
                    $entrada->stock()->saveMany($guardar_stock);
                }

                if($entrada->estatus == 2){
                    $entrada->total_claves_recibidas = count($inputs['insumos']);
                    $entrada->total_claves_validadas = count($inputs['insumos']);
                    $entrada->total_cantidad_recibida = $total_cantidad_recibida;
                    $entrada->total_cantidad_validada = $total_cantidad_recibida;
                    $entrada->porcentaje_claves = 100;
                    $entrada->porcentaje_cantidad = 100;

                    $entrada->save();

                    $entrada->load('stock');
                    $actualizar_stock = [];
                    for($i = 0, $total = count($entrada->stock); $i < $total; $i++) {
                        $insumo = $entrada->stock[$i];
                        $insumo->stock = 1; //Stock activo = 1, inactivo = null
                        $insumo->usado = 0;
                        $insumo->disponible = $insumo->cantidad_recibida;
                        $actualizar_stock[] = $insumo;
                    }
                    $entrada->stock()->saveMany($actualizar_stock);
                }
            }

            DB::commit();

            $datos_usuario = Usuario::find($usuario->get('id'));

            if($entrada->estatus == 2){
                if($datos_usuario->tipo_conexion){
                    $resultado = $this->actualizarEntradaCentral($entrada->id);
                    if(!$resultado['estatus']){
                        return Response::json(['error' => 'Error al intentar sincronizar la recepción del pedido', 'error_type' => 'data_validation', 'message'=>$resultado['message'], 'data'=>$entrada], HttpResponse::HTTP_CONFLICT);
                    }
                }
                $entrada = Entrada::find($entrada->id);
            }
            return Response::json([ 'data' => $entrada ],200);

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
    public function update(Request $request, $id){
        $mensajes = [
            'required'      => "required",
            'required_if'   => "required",
            'array'         => "array",
            'min'           => "min",
            'unique'        => "unique",
            'date'          => "date"
        ];

        $reglas_entrega = [
            'proveedor_id'              =>'required',
            'fecha_recibe'             =>'required',
            'hora_recibe'              =>'required',
            'nombre_recibe'             =>'required_if:estatus,2',
            'nombre_entrega'            =>'required_if:estatus,2'
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
            $configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();

            $proveedor_id = $inputs['proveedor_id'];

            //Se obtiene el acta con la entrega abierta del proveedor a guardar
            $entrada = Entrada::find($id);

            $entrada->proveedor_id          = $proveedor_id;
            $entrada->fecha_recibe          = $inputs['fecha_recibe'];
            $entrada->hora_recibe           = $inputs['hora_recibe'];
            $entrada->clues                 = $configuracion->clues;
            if($inputs['estatus'] > 1){
                $entrada->nombre_recibe         = $inputs['nombre_recibe'];
                $entrada->nombre_entrega        = $inputs['nombre_entrega'];
                if(isset($inputs['observaciones'])){
                    $entrada->observaciones         = $inputs['observaciones'];
                }
            }
            $entrada->estatus               = $inputs['estatus'];

            if($entrada->save()){
                $entrada->load('stock');
                $stock_guardado = [];
                foreach ($entrada->stock as $stock) {
                    if(!isset($stock_guardado[$stock->insumo_id])){
                        $stock_guardado[$stock->insumo_id] = [];
                    }
                    $stock_guardado[$stock->insumo_id][] = $stock;
                }

                $guardar_stock = [];
                $eliminar_stock = [];
                $cantidades_insumos = [];
                $total_cantidad_recibida = 0;
                foreach ($inputs['insumos'] as $index => $ingreso) {
                    $insumo_id = $ingreso['insumo_id'];

                    $cantidades_insumos[$insumo_id] = $ingreso['cantidad'];

                    //iteramos sobre el que tiene mayor número de items
                    $total_lotes_form = count($ingreso['lotes']);

                    if(isset($stock_guardado[$insumo_id])){
                        $total_lotes_db = count($stock_guardado[$insumo_id]);
                    }else{
                        $total_lotes_db = 0;
                    }

                    if($total_lotes_form > $total_lotes_db){
                        for($i = 0; $i < $total_lotes_form; $i++){
                            $nuevo_ingreso = new StockInsumo();

                            $nuevo_ingreso->clues               = $configuracion->clues;
                            $nuevo_ingreso->insumo_id           = $insumo_id;

                            if(isset($ingreso['lotes'][$i]['lote'])){
                                $nuevo_ingreso->lote                = $ingreso['lotes'][$i]['lote'];
                            }else{
                                $nuevo_ingreso->lote                = null;
                            }

                            if(isset($ingreso['lotes'][$i]['fecha_caducidad'])){
                                $nuevo_ingreso->fecha_caducidad     = $ingreso['lotes'][$i]['fecha_caducidad'];
                            }else{
                                $nuevo_ingreso->fecha_caducidad     = null;
                            }

                            $nuevo_ingreso->cantidad_recibida   = $ingreso['lotes'][$i]['cantidad'];

                            $total_cantidad_recibida += $ingreso['lotes'][$i]['cantidad'];

                            $guardar_stock[] = $nuevo_ingreso;
                        }
                    }else{
                        for($i = 0; $i < $total_lotes_db; $i++){
                            if(!isset($ingreso['lotes'][$i])){
                                $eliminar_stock[] = $stock_guardado[$insumo_id][$i]->id;
                            }else{
                                $nuevo_ingreso = $stock_guardado[$insumo_id][$i];

                                $nuevo_ingreso->clues               = $configuracion->clues;
                                $nuevo_ingreso->insumo_id           = $insumo_id;
                                
                                if(isset($ingreso['lotes'][$i]['lote'])){
                                    $nuevo_ingreso->lote                = $ingreso['lotes'][$i]['lote'];
                                }else{
                                    $nuevo_ingreso->lote                = null;
                                }

                                if(isset($ingreso['lotes'][$i]['fecha_caducidad'])){
                                    $nuevo_ingreso->fecha_caducidad     = $ingreso['lotes'][$i]['fecha_caducidad'];
                                }else{
                                    $nuevo_ingreso->fecha_caducidad     = null;
                                }

                                $nuevo_ingreso->cantidad_recibida   = $ingreso['lotes'][$i]['cantidad'];

                                $total_cantidad_recibida += $ingreso['lotes'][$i]['cantidad'];

                                $guardar_stock[] = $nuevo_ingreso;
                            }
                        }
                    }
                }

                if(count($eliminar_stock)){
                    StockInsumo::whereIn('id',$eliminar_stock)->delete();
                }
                
                if(count($guardar_stock)){
                    $entrada->stock()->saveMany($guardar_stock);
                }

                if($entrada->estatus == 2){
                    $entrada->total_claves_recibidas = count($inputs['insumos']);
                    $entrada->total_claves_validadas = count($inputs['insumos']);
                    $entrada->total_cantidad_recibida = $total_cantidad_recibida;
                    $entrada->total_cantidad_validada = $total_cantidad_recibida;
                    $entrada->porcentaje_claves = 100;
                    $entrada->porcentaje_cantidad = 100;

                    $entrada->save();

                    $entrada->load('stock');
                    $actualizar_stock = [];
                    for($i = 0, $total = count($entrada->stock); $i < $total; $i++) {
                        $insumo = $entrada->stock[$i];
                        $insumo->stock = 1; //Stock activo = 1, inactivo = null
                        $insumo->usado = 0;
                        $insumo->disponible = $insumo->cantidad_recibida;
                        $actualizar_stock[] = $insumo;
                    }
                    $entrada->stock()->saveMany($actualizar_stock);
                }
            }

            DB::commit();

            $datos_usuario = Usuario::find($usuario->get('id'));

            if($entrada->estatus == 2){
                if($datos_usuario->tipo_conexion){
                    $resultado = $this->actualizarEntradaCentral($entrada->id);
                    if(!$resultado['estatus']){
                        return Response::json(['error' => 'Error al intentar sincronizar la recepción del pedido', 'error_type' => 'data_validation', 'message'=>$resultado['message'], 'data'=>$entrada], HttpResponse::HTTP_CONFLICT);
                    }
                }
                $entrada = Entrada::find($entrada->id);
            }
            return Response::json([ 'data' => $entrada ],200);

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
        $entrada = Entrada::with('stock.insumo')->find($id); 

        $usuario = JWTAuth::parseToken()->getPayload();

        $configuracion = Configuracion::with('cuadroBasico')->where('clues',$usuario->get('clues'))->first();
        $proveedores = Proveedor::all();

        $data = [  
            'entrada' => $entrada,
            'proveedores' => $proveedores,
            'configuracion' => $configuracion
        ];

        return Response::json([ 'data' => $data ],200);
    }

    public function showEntrada(Request $request, $id){
        $entrada = Entrada::with('stock.insumo','acta')->find($id);

        $proveedor_id = $entrada->proveedor_id;

        $entrada->acta->load(['requisiciones.insumos'=>function($query)use($proveedor_id){
            $query->select('id')->wherePivot('cantidad_recibida','>',0)->wherePivot('proveedor_id',$proveedor_id);
        }]);

        $usuario = JWTAuth::parseToken()->getPayload();
        $configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();

        $proveedor = Proveedor::find($proveedor_id);

        return Response::json([ 'data' => $entrada, 'configuracion'=>$configuracion, 'proveedor' => $proveedor],200);
    }
    
    public function sincronizar($id){
        try {
            $usuario = JWTAuth::parseToken()->getPayload();
            $datos_usuario = Usuario::find($usuario->get('id'));
            if($datos_usuario->tipo_conexion){
                $entrada = Entrada::find($id);
                if(!$entrada){
                    return Response::json(['error' => 'Entrada no encontrada.', 'error_type' => 'data_validation'], HttpResponse::HTTP_CONFLICT);
                }
                if($entrada->estatus == 2){
                    $resultado = $this->actualizarEntradaCentral($id);
                    if(!$resultado['estatus']){
                        return Response::json(['error' => 'Error al intentar sincronizar la entrada', 'error_type' => 'data_validation', 'message'=>$resultado['message'],'line'=>$resultado['line'],'extra_data'=>$resultado['extra_data']], HttpResponse::HTTP_CONFLICT);
                    }
                    $entrada = Entrada::find($id);
                }else{
                    return Response::json(['error' => 'La entrada no esta lista para ser enviada.', 'error_type' => 'data_validation'], HttpResponse::HTTP_CONFLICT);
                }
                return Response::json([ 'data' => $entrada ],200);
            }else{
                return Response::json(['error' => 'Su usuario no esta cofigurado para realizar la sincronización', 'error_type' => 'data_validation', 'message'=>'Usuario offline'], HttpResponse::HTTP_CONFLICT);
            }
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage(), 'line' => $e->getLine()], HttpResponse::HTTP_CONFLICT);
        }
    }
}
