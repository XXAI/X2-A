<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use App\Models\Acta;
use App\Models\Requisicion;
use App\Models\Configuracion;
use App\Models\Usuario;
use JWTAuth;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB, \PDF, \Storage, \ZipArchive;

class RequisicionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request){
        try{
            //DB::enableQueryLog();
            $usuario = JWTAuth::parseToken()->getPayload();
            $configuracion = Configuracion::where('clues',$usuario->get('id'))->first();
            $empresa = $configuracion->empresa_clave;

            $requisiciones = Requisicion::whereNull('acta_id')->where('clues',$usuario->get('id'))->with('insumosClues')->get();
            
            $clues = Usuario::select('id AS clues','nombre','municipio','localidad','jurisdiccion')
                            ->where('empresa_clave',$empresa)->get();

            return Response::json(['data'=>$requisiciones, 'clues'=>$clues, 'configuracion'=>$configuracion],200);
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
            'lugar_reunion'     =>'required'
        ];
        
        $parametros = Input::all();
        $inputs = $parametros['requisiciones'];
        $inputs_acta = null;
        $acta = null;

        if(isset($parametros['acta'])){
            $inputs_acta = $parametros['acta'];
            $v = Validator::make($inputs_acta, $reglas_acta, $mensajes);
            if ($v->fails()) {
                return Response::json(['error' => $v->errors(), 'error_type'=>'form_validation'], HttpResponse::HTTP_CONFLICT);
            }
        }
        
        try {

            DB::beginTransaction();

            $usuario = JWTAuth::parseToken()->getPayload();
            $configuracion = Configuracion::where('clues',$usuario->get('id'))->first();
            $empresa = $configuracion->empresa_clave;
            $clues = $configuracion->clues;

            $requisiciones = Requisicion::whereNull('acta_id')->where('clues',$clues)->get();//->with('insumosClues')

            $arreglo_requisiciones = [];
            foreach ($requisiciones as $requisicion) {
                $arreglo_requisiciones[$requisicion->pedido] = $requisicion;
            }

            if(isset($inputs)){
                if(count($inputs) > 3){
                    throw new \Exception("No pueden haber mas de tres requisiciones");
                }

                $requisiciones_guardadas = [];
                foreach ($inputs as $inputs_requisicion) {
                    $inputs_requisicion['dias_surtimiento'] = 15;
                    $inputs_requisicion['empresa'] = $empresa;
                    $inputs_requisicion['clues'] = $clues;
                    $inputs_requisicion['lotes'] = 0;
                    $inputs_requisicion['sub_total'] = 0;
                    $inputs_requisicion['gran_total'] = 0;
                    $inputs_requisicion['iva'] = 0;
                    
                    if(isset($arreglo_requisiciones[$inputs_requisicion['pedido']])){
                        $requisicion = $arreglo_requisiciones[$inputs_requisicion['pedido']];
                        $requisicion->update($inputs_requisicion);
                        $requisiciones_guardadas[$requisicion->id] = true;
                    }else{
                        $requisicion = Requisicion::create($inputs_requisicion);
                    }

                    if(isset($inputs_requisicion['insumos'])){
                        $insumos = [];
                        $lotes = [];
                        foreach ($inputs_requisicion['insumos'] as $req_insumo) {
                            if(!isset($lotes[$req_insumo['lote']])){
                                $lotes[$req_insumo['lote']] = true;
                            }
                            $insumos[] = [
                                'insumo_id' => $req_insumo['insumo_id'],
                                'cantidad' => $req_insumo['cantidad'],
                                'total' => $req_insumo['total'],
                                'clues' => $req_insumo['clues']
                            ];
                        }
                        $requisicion->insumosClues()->sync([]);
                        $requisicion->insumosClues()->sync($insumos);

                        $sub_total = $requisicion->insumosClues()->sum('total');

                        $requisicion->lotes = count($lotes);
                        $requisicion->sub_total = $sub_total;

                        if($requisicion->tipo_requisicion == 3){
                            $requisicion->iva = $sub_total*16/100;
                        }else{
                            $requisicion->iva = 0;
                        }
                        $requisicion->gran_total = $sub_total + $requisicion->iva;
                        $requisicion->save();
                    }else{
                        $requisicion->insumosClues()->sync([]);
                        $requisicion->sub_total = 0;
                        $requisicion->iva = 0;
                        $requisicion->gran_total = 0;
                        $requisicion->lotes = 0;
                        $requisicion->save();
                    }
                }

                $eliminar_requisiciones = [];
                foreach ($requisiciones as $requisicion) {
                    if(!isset($requisiciones_guardadas[$requisicion->id])){
                        $eliminar_requisiciones[] = $requisicion->id;
                        $requisicion->insumosClues()->sync([]);
                    }
                }
                if(count($eliminar_requisiciones)){
                    Requisicion::whereIn('id',$eliminar_requisiciones)->delete();
                }
            }

            if($inputs_acta){
                $max_acta = Acta::where('folio','like',$clues.'/%')->max('numero');
                if(!$max_acta){
                    $max_acta = 0;
                }
                $inputs_acta['folio'] = $configuracion->clues . '/'.($max_acta+1).'/' . date('Y');
                $inputs_acta['numero'] = ($max_acta+1);
                $inputs_acta['estatus'] = 2;
                $inputs_acta['empresa'] = $configuracion->empresa_clave;
                $inputs_acta['lugar_entrega'] = $configuracion->lugar_entrega;
                $inputs_acta['director_unidad'] = $configuracion->director_unidad;
                $inputs_acta['administrador'] = $configuracion->administrador;
                $inputs_acta['encargado_almacen'] = $configuracion->encargado_almacen;
                $inputs_acta['coordinador_comision_abasto'] = $configuracion->coordinador_comision_abasto;
                
                $acta = Acta::create($inputs_acta);

                $actas = Acta::where('folio','like',$configuracion->clues.'/%')->lists('id');

                $max_requisicion = Requisicion::whereIn('acta_id',$actas)->max('numero');
                if(!$max_requisicion){
                    $max_requisicion = 0;
                }

                foreach ($requisiciones as $index => $requisicion) {
                    $max_requisicion += 1;
                    $requisiciones[$index]->numero = $max_requisicion;
                    $requisicion_insumos = [];
                    foreach ($requisicion->insumosClues as $insumo) {
                        if(!isset($requisicion_insumos[$insumo->pivot->insumo_id])){
                            $requisicion_insumos[$insumo->pivot->insumo_id] = [
                                    'insumo_id' => $insumo->pivot->insumo_id,
                                    'cantidad' => 0,
                                    'total' => 0
                            ];
                        }
                        $requisicion_insumos[$insumo->pivot->insumo_id]['cantidad'] += $insumo->pivot->cantidad;
                        $requisicion_insumos[$insumo->pivot->insumo_id]['total'] += $insumo->pivot->total;
                    }
                    $requisiciones[$index]->insumos()->sync($requisicion_insumos);
                }

                $acta->requisiciones()->saveMany($requisiciones);
            }

            DB::commit();

            return Response::json([ 'data' => $requisiciones ,'acta' => $acta ],200);
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
    /*
    public function show($id){
        return Response::json([ 'data' => Acta::with('requisiciones.insumos')->find($id) ],200);
    }
    */

    public function generarRequisicionPDF(){
        $meses = ['01'=>'ENERO','02'=>'FEBRERO','03'=>'MARZO','04'=>'ABRIL','05'=>'MAYO','06'=>'JUNIO','07'=>'JULIO','08'=>'AGOSTO','09'=>'SEPTIEMBRE','10'=>'OCTUBRE','11'=>'NOVIEMBRE','12'=>'DICIEMBRE'];
        $data = [];

        $usuario = JWTAuth::parseToken()->getPayload();
        $configuracion = Configuracion::where('clues',$usuario->get('id'))->first();

        $data['acta'] = new Acta;
        $requisiciones = Requisicion::whereNull('acta_id')->where('clues',$usuario->get('id'))->with('insumosClues')->get();

        $data['acta']->folio = $configuracion->clues . '/'.'00'.'/' . date('Y');
        $data['acta']->estatus = 1;
        $data['acta']->empresa = $configuracion->empresa_clave;
        $data['acta']->lugar_entrega = $configuracion->lugar_entrega;
        $data['acta']->director_unidad = $configuracion->director_unidad;
        $data['acta']->administrador = $configuracion->administrador;

        $fecha = explode('-',date("Y-m-d"));
        $fecha[1] = $meses[$fecha[1]];
        $data['acta']->fecha = $fecha;


        
        foreach ($requisiciones as $index => $requisicion) {
            $requisicion_insumos = [];
            foreach ($requisicion->insumosClues as $insumo) {
                if(!isset($requisicion_insumos[$insumo->pivot->insumo_id])){
                    $requisicion_insumos[$insumo->pivot->insumo_id] = $insumo;
                }else{
                    $requisicion_insumos[$insumo->pivot->insumo_id]->pivot->cantidad += $insumo->pivot->cantidad;
                    $requisicion_insumos[$insumo->pivot->insumo_id]->pivot->total += $insumo->pivot->total;
                }
            }
            $requisiciones[$index]->insumos = $requisicion_insumos;
            $requisiciones[$index]->insumosClues = null;
        }
        $data['acta']->requisiciones = $requisiciones;

        /*if($data['acta']->estatus != 2){
            return Response::json(['error' => 'No se puede generar el archivo por que el acta no se encuentra finalizada'], HttpResponse::HTTP_CONFLICT);
        }*/

        $data['unidad'] = mb_strtoupper($configuracion->clues_nombre,'UTF-8');
        $data['empresa'] = $configuracion->empresa_nombre;
        $data['empresa_clave'] = $configuracion->empresa_clave;

        $pdf = PDF::loadView('pdf.requisiciones', $data);
        return $pdf->stream($data['acta']->folio.'Requisiciones.pdf');
    }
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    /*
    public function update(Request $request, $id){
        $mensajes = [
            'required'      => "required",
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

        $usuario = JWTAuth::parseToken()->getPayload();
        $configuracion = Configuracion::where('clues',$usuario->get('id'))->first();

        $inputs = Input::all();
        //$inputs = Input::only('id','servidor_id','password','nombre', 'apellidos');
        //var_dump(json_encode($inputs));die;

        $v = Validator::make($inputs, $reglas_acta, $mensajes);
        if ($v->fails()) {
            return Response::json(['error' => $v->errors(), 'error_type'=>'form_validation'], HttpResponse::HTTP_CONFLICT);
        }

        try {

            DB::beginTransaction();

            $acta = Acta::find($id);

            if($acta->estatus == 2){
                throw new \Exception("El Acta no se puede editar ya que se encuentra con estatus de finalizada");
            }

            if($inputs['estatus'] == 2 && $acta->estatus != 2){
                $max_acta = Acta::where('folio','like',$configuracion->clues.'/%')->max('numero');
                if(!$max_acta){
                    $max_acta = 0;
                }
                $inputs['folio'] = $configuracion->clues . '/'.($max_acta+1).'/' . date('Y');
                $inputs['numero'] = ($max_acta+1);
            }

            $inputs['lugar_entrega'] = $configuracion->lugar_entrega;
            $inputs['director_unidad'] = $configuracion->director_unidad;
            $inputs['administrador'] = $configuracion->administrador;
            $inputs['encargado_almacen'] = $configuracion->encargado_almacen;
            $inputs['coordinador_comision_abasto'] = $configuracion->coordinador_comision_abasto;
            
            $acta->update($inputs);

            if(isset($inputs['requisiciones'])){
                if(count($inputs['requisiciones']) > 3){
                    throw new \Exception("No pueden haber mas de tres requisiciones por acta");
                }

                $acta->load('requisiciones');
                $requisiciones_guardadas = [];
                foreach ($inputs['requisiciones'] as $inputs_requisicion) {
                    $inputs_requisicion['dias_surtimiento'] = 15;
                    //$inputs_requisicion['firma_director'] = $configuracion->director_unidad;
                    $v = Validator::make($inputs_requisicion, $reglas_requisicion, $mensajes);
                    if ($v->fails()) {
                        DB::rollBack();
                        return Response::json(['error' => $v->errors(), 'error_type'=>'form_validation'], HttpResponse::HTTP_CONFLICT);
                    }

                    if($acta->estatus == 2 && !isset($inputs_requisicion['numero'])){
                        $actas = Acta::where('folio','like',$configuracion->clues.'/%')->lists('id');
                        $max_requisicion = Requisicion::whereIn('acta_id',$actas)->max('numero');
                        if(!$max_requisicion){
                            $max_requisicion = 0;
                        }
                        $inputs_requisicion['numero'] = $max_requisicion+1;
                    }

                    if(isset($inputs_requisicion['id'])){
                        $requisicion = Requisicion::find($inputs_requisicion['id']);
                        $requisicion->update($inputs_requisicion);
                        $requisiciones_guardadas[$requisicion->id] = true;
                    }else{
                        $inputs_requisicion['acta_id'] = $acta->id;
                        $inputs_requisicion['empresa'] = $configuracion->empresa_clave;
                        $requisicion = Requisicion::create($inputs_requisicion);
                    }

                    if(isset($inputs_requisicion['insumos'])){
                        $insumos = [];
                        foreach ($inputs_requisicion['insumos'] as $req_insumo) {
                            $insumos[] = [
                                'insumo_id' => $req_insumo['insumo_id'],
                                'cantidad' => $req_insumo['cantidad'],
                                'total' => $req_insumo['total']
                            ];
                        }
                        $requisicion->insumos()->sync([]);
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
                    }else{
                        $requisicion->insumos()->sync([]);
                        $requisicion->sub_total = 0;
                        $requisicion->iva = 0;
                        $requisicion->gran_total = 0;
                        $requisicion->save();

                    }
                }
                $eliminar_requisiciones = [];
                foreach ($acta->requisiciones as $requisicion) {
                    if(!isset($requisiciones_guardadas[$requisicion->id])){
                        $eliminar_requisiciones[] = $requisicion->id;
                        $requisicion->insumos()->sync([]);
                    }
                }
                if(count($eliminar_requisiciones)){
                    Requisicion::whereIn('id',$eliminar_requisiciones)->delete();
                }
            }

            DB::commit();
            $acta->load('requisiciones.insumos');
            return Response::json([ 'data' => $acta, 'respuesta_code' =>'updated' ],200);

        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage(), 'line' => $e->getLine()], HttpResponse::HTTP_CONFLICT);
        }
    }
    */
}
