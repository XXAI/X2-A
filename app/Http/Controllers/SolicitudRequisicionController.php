<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use App\Models\Acta;
use App\Models\Requisicion;
use App\Models\DetalleRequisicion;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB;

class SolicitudRequisicionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try{
            //DB::enableQueryLog();
            $elementos_por_pagina = 50;
            $pagina = Input::get('pagina');
            if(!$pagina){
                $pagina = 1;
            }

            $query = Input::get('query');
            $filtro = Input::get('filtro');

            $recurso = Acta::getModel();

            if($query){
                $recurso = $recurso->where(function($condition)use($query){
                    $condition->where('folio','LIKE','%'.$query.'%')
                            ->orWhere('lugar_reunion','LIKE','%'.$query.'%')
                            ->orWhere('empresa','LIKE','%'.$query.'%')
                            ->orWhere('ciudad','LIKE','%'.$query.'%');
                });
            }

            $totales = $recurso->count();
            
            $recurso = $recurso->skip(($pagina-1)*$elementos_por_pagina)->take($elementos_por_pagina)
                                ->orderBy('id','desc')->get();

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
    public function store(Request $request)
    {
        $mensajes = [
            'required'      => "required",
            'unique'        => "unique",
            'date'          => "date"
        ];

        $reglas_acta = [
            'ciudad'        =>'required',
            'fecha_inicio'  =>'required',
            'hora_termino'  =>'required',
            'lugar_reunion' =>'required',
            'empresa'       =>'required'
        ];

        $reglas_requisicion = [
            'acta_id'           =>'required',
            'pedido'            =>'required',
            'lotes'             =>'required',
            'empresa'           =>'required',
            'tipo_requisicion'  =>'required',
            'dias_surtimiento'  =>'required',
            'sub_total'         =>'required',
            'gran_total'        =>'required',
            'iva'               =>'required',
            'firma_solicita'    =>'required',
            'firma_director'    =>'required'
        ];

        $reglas_detalles = [
            'requisicion_id'    =>'required',
            'insumo_id'         =>'required',
            'lote'              =>'required',
            'cantidad'          =>'required',
            'total'             =>'required'
        ];

        $inputs = Input::all();
        //$inputs = Input::only('id','servidor_id','password','nombre', 'apellidos');
        //var_dump(json_encode($inputs));die;

        $v = Validator::make($inputs, $reglas_acta, $mensajes);
        if ($v->fails()) {
            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
        }

        try {

            DB::beginTransaction();

            $max_acta = Acta::max('id');

            $inputs['folio'] = env('CLUES') . '/' . ($max_acta+1) . '/' . date('Y');
            $acta = Acta::create($inputs);

            if(isset($inputs['requisiciones'])){
                foreach ($inputs['requisiciones'] as $inputs_requisicion) {
                    $inputs_requisicion['acta_id'] = $acta->id;
                    $v = Validator::make($inputs_requisicion, $reglas_requisicion, $mensajes);
                    if ($v->fails()) {
                        DB::rollBack();
                        return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
                    }

                    $max_requisicion = Requisicion::where('tipo_requisicion',$inputs_requisicion['tipo_requisicion'])->max('numero');
                    if(!$max_requisicion){
                        $max_requisicion = 0;
                    }
                    $inputs_requisicion['numero'] = $max_requisicion+1;
                    $requisicion = Requisicion::create($inputs_requisicion);

                    if(isset($inputs_requisicion['detalles'])){
                        foreach ($inputs_requisicion['detalles'] as $inputs_detalle) {
                            $inputs_detalle['requisicion_id'] = $requisicion->id;
                            $v = Validator::make($inputs_detalle, $reglas_detalles, $mensajes);
                            if ($v->fails()) {
                                DB::rollBack();
                                return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
                            }
                            $detalle = DetalleRequisicion::create($inputs_detalle);
                        }
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
    public function show($id)
    {
        return Response::json([ 'data' => Acta::with('requisiciones.detalles')->find($id) ],200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
