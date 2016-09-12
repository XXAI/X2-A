<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Traits\SyncTrait;
use App\Http\Requests;
use App\Models\Acta;
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

        $reglas_acta = [
            'ciudad'            =>'required',
            'fecha'             =>'required',
            'hora_inicio'       =>'required',
            'hora_termino'      =>'required',
            'lugar_reunion'     =>'required',
            //'firma_solicita'    =>'required',
            //'cargo_solicita'    =>'required',
            'requisiciones'     =>'required|array|min:1'
        ];

        $reglas_requisicion = [
            'acta_id'           =>'required',
            'pedido'            =>'required',
            'lotes'             =>'required',
            'tipo_requisicion'  =>'required',
            'dias_surtimiento'  =>'required',
            'sub_total'         =>'required',
            'gran_total'        =>'required',
            'iva'               =>'required'
            //'firma_solicita'    =>'required',
            //'cargo_solicita'    =>'required'
        ];

        $inputs = Input::all();
        //$inputs = Input::only('id','servidor_id','password','nombre', 'apellidos');
        //var_dump(json_encode($inputs));die;

        $v = Validator::make($inputs, $reglas_acta, $mensajes);
        if ($v->fails()) {
            return Response::json(['error' => $v->errors(), 'error_type'=>'form_validation'], HttpResponse::HTTP_CONFLICT);
        }

        try {

            DB::beginTransaction();

            //$max_acta = Acta::max('id');
            $usuario = JWTAuth::parseToken()->getPayload();
            $configuracion = Configuracion::where('clues',$usuario->get('id'))->first();

            $inputs['folio'] = $configuracion->clues . '/'.'00'.'/' . date('Y');
            $inputs['estatus'] = 1;
            $inputs['empresa'] = $configuracion->empresa_clave;
            $inputs['lugar_entrega'] = $configuracion->lugar_entrega;
            $inputs['director_unidad'] = $configuracion->director_unidad;
            $inputs['administrador'] = $configuracion->administrador;
            $inputs['encargado_almacen'] = $configuracion->encargado_almacen;
            $inputs['coordinador_comision_abasto'] = $configuracion->coordinador_comision_abasto;
            $acta = Acta::create($inputs);

            if(isset($inputs['requisiciones'])){
                if(count($inputs['requisiciones']) > 4){
                    DB::rollBack();
                    throw new \Exception("No pueden haber mas de cuatro requesiciones por acta");
                }

                foreach ($inputs['requisiciones'] as $inputs_requisicion) {
                    $inputs_requisicion['acta_id'] = $acta->id;
                    //$inputs_requisicion['firma_director'] = $configuracion->director_unidad;
                    $v = Validator::make($inputs_requisicion, $reglas_requisicion, $mensajes);
                    if ($v->fails()) {
                        DB::rollBack();
                        return Response::json(['error' => $v->errors(), 'error_type'=>'form_validation'], HttpResponse::HTTP_CONFLICT);
                    }

                    //$max_requisicion = Requisicion::max('numero');
                    //if(!$max_requisicion){
                        //$max_requisicion = 0;
                    //}
                    //$inputs_requisicion['numero'] = $max_requisicion+1;
                    $inputs_requisicion['empresa'] = $configuracion->empresa_clave;
                    $inputs_requisicion['dias_surtimiento'] = 15;
                    $inputs_requisicion['sub_total'] = 0;
                    $inputs_requisicion['iva'] = 0;
                    $inputs_requisicion['gran_total'] = 0;
                    $requisicion = Requisicion::create($inputs_requisicion);

                    if(isset($inputs_requisicion['insumos'])){
                        $insumos = [];
                        //$suma = 0;
                        //$iva = 0;
                        foreach ($inputs_requisicion['insumos'] as $req_insumo) {
                            $insumos[] = [
                                'insumo_id' => $req_insumo['insumo_id'],
                                'cantidad' => $req_insumo['cantidad'],
                                'total' => $req_insumo['total']
                            ];
                        }
                        $requisicion->insumos()->sync($insumos);

                        $sub_total = $requisicion->insumos()->sum('total');
                        $requisicion->sub_total = $sub_total;
                        if($requisicion->tipo_requisicion == 3){
                            $requisicion->iva = $sub_total*16/100;
                        }else{
                            $requisicion->iva = 0;
                        }
                        $requisicion->gran_total = $sub_total + $requisicion->iva;
                        $requisicion->save();
                    }
                }
            }

            DB::commit();

            return Response::json([ 'data' => $acta ],200);

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
            },'requisiciones.insumosClues'])->find($id);

        $usuario = JWTAuth::parseToken()->getPayload();
        $configuracion = Configuracion::where('clues',$usuario->get('id'))->first();

        $proveedores = Proveedor::all();

        return Response::json([ 'data' => $acta, 'configuracion'=>$configuracion, 'proveedores' => $proveedores],200);
    }

    public function sincronizar($id){
        try {
            $acta = Acta::find($id);
            if(!$acta){
                return Response::json(['error' => 'Acta no encontrada.', 'error_type' => 'data_validation'], HttpResponse::HTTP_CONFLICT);
            }
            if($acta->estatus == 2){
                $resultado = $this->actualizarCentral($acta->folio);
                if(!$resultado['estatus']){
                    return Response::json(['error' => 'Error al intentar sincronizar el acta', 'error_type' => 'data_validation', 'message'=>$resultado['message']], HttpResponse::HTTP_CONFLICT);
                }
                $acta = Acta::find($id);
            }
            return Response::json([ 'data' => $acta ],200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage(), 'line' => $e->getLine()], HttpResponse::HTTP_CONFLICT);
        }
    }
}
