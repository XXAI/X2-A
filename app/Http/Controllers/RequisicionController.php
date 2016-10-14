<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Traits\SyncTrait;
use App\Http\Requests;
use App\Models\Acta;
use App\Models\Requisicion;
use App\Models\Configuracion;
use App\Models\Insumo;
use App\Models\Usuario;
use JWTAuth;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB, \PDF, \Storage, \ZipArchive;

class RequisicionController extends Controller
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
            $configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();
            $empresa = $configuracion->empresa_clave;

            /*if(!Input::get('catalogos')){
                $requisiciones = Requisicion::whereNull('acta_id')->where('clues',$usuario->get('clues'))->with('insumosClues')->get();
            }else{
                $requisiciones = [];
            }*/

            $clues = Configuracion::select('clues','clues_nombre as nombre','municipio','localidad','jurisdiccion')
                            ->where('empresa_clave',$empresa)
                            ->where('jurisdiccion',$configuracion->jurisdiccion)
                            ->whereIn('tipo_clues',[1,2])
                            ->get();
            //Arreglo de solo las clues, para identificar los insumos, meter en un if por tipo de usuario
            $listado_clues = $clues->lists('clues');

            if(!Input::get('catalogos')){
                $insumos = DB::table('requisicion_insumo_clues')
                                ->leftjoin('insumos','insumos.id','=','requisicion_insumo_clues.insumo_id')
                                ->select('insumos.*','requisicion_insumo_clues.*')
                                ->whereNull('requisicion_id')->whereIn('clues',$listado_clues)->get();
            }else{
                $insumos = [];
            }
            
            return Response::json(['data'=>$insumos, 'clues'=>$clues, 'configuracion'=>$configuracion],200);
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

        $reglas_configuracion = [
            'director_unidad'               => 'required',
            'administrador'                 => 'required',
            'encargado_almacen'             => 'required',
            'coordinador_comision_abasto'   => 'required',
            'lugar_entrega'                 => 'required'
        ];
        
        $parametros = Input::all();
        //$inputs = $parametros['requisiciones'];
        $inputs = $parametros['insumos'];
        $inputs_acta = null;
        $acta = null;

        if(isset($parametros['acta'])){
            $inputs_acta = $parametros['acta'];
            $v = Validator::make($inputs_acta, $reglas_acta, $mensajes);
            if ($v->fails()) {
                return Response::json(['error' => $v->errors(), 'error_type'=>'form_validation'], HttpResponse::HTTP_CONFLICT);
            }
        }

        //if(!isset($parametros['requisiciones'])){
        if(!isset($parametros['insumos'])){
            return Response::json(['error' => 'No hay datos de requisiciones para guardar', 'error_type'=>'form_validation'], HttpResponse::HTTP_CONFLICT);
        }
        
        try {

            DB::beginTransaction();

            $usuario = JWTAuth::parseToken()->getPayload();
            $configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();
            $empresa = $configuracion->empresa_clave;
            $clues = $configuracion->clues;

            $clues = Configuracion::select('clues')
                            ->where('empresa_clave',$empresa)
                            ->where('jurisdiccion',$configuracion->jurisdiccion)
                            ->whereIn('tipo_clues',[1,2])
                            ->get();
            //Arreglo de solo las clues, para identificar los insumos, meter en un if por tipo de usuario
            $listado_clues = $clues->lists('clues');

            //$requisiciones = Requisicion::whereNull('acta_id')->where('clues',$clues)->get();//->with('insumosClues')
            $arreglo_insumos_clues = DB::table('requisicion_insumo_clues')->whereNull('requisicion_id')->whereIn('clues',$listado_clues)->get();

            /*
            $arreglo_requisiciones = [];
            foreach ($requisiciones as $requisicion) {
                //$arreglo_requisiciones[$requisicion->pedido] = $requisicion;
                $arreglo_requisiciones[$requisicion->tipo_requisicion] = $requisicion;
            }
            */
            $insumos_guardados = [];
            foreach ($arreglo_insumos_clues as $insumo) {
                if(!isset($insumos_guardados[$insumo->clues])){
                    $insumos_guardados[$insumo->clues] = [];
                }
                $insumos_guardados[$insumo->clues][$insumo->insumo_id] = $insumo;
            }

            //if(isset($inputs)){
                /*if(count($inputs) > 4){
                    throw new \Exception("No pueden haber mas de cuatro requisiciones");
                }*/

                //$requisiciones_guardadas = [];
                //foreach ($inputs as $inputs_requisicion) {
                $lista_insumos = [];
                foreach ($inputs as $input_insumo) {
                    $guardar_insumo = [];

                    if(!isset($input_insumo['usuario'])){
                        $guardar_insumo['usuario'] = $usuario->get('id');
                    }

                    if(isset($insumos_guardados[$input_insumo['clues']])){
                        if(isset($insumos_guardados[$input_insumo['clues']][$input_insumo['insumo_id']])){
                            $insumo_base = $insumos_guardados[$input_insumo['clues']][$input_insumo['insumo_id']];
                            $guardar_insumo['insumo_id'] = $insumo_base->insumo_id;
                            $guardar_insumo['clues'] = $insumo_base->clues;
                            $guardar_insumo['cantidad'] = $insumo_base->cantidad;
                            $guardar_insumo['total'] = $insumo_base->total;
                            $guardar_insumo['usuario'] = $insumo_base->usuario;
                        }
                    }

                    $guardar_insumo['insumo_id'] = $input_insumo['insumo_id'];
                    $guardar_insumo['clues'] = $input_insumo['clues'];
                    $guardar_insumo['cantidad'] = $input_insumo['cantidad'];
                    $guardar_insumo['total'] = $input_insumo['total'];
                    //$guardar_insumo['usuario'] = $input_insumo['usuario'];

                    $lista_insumos[] = $guardar_insumo;
                    
                    /*
                    $inputs_requisicion['dias_surtimiento'] = 15;
                    $inputs_requisicion['empresa'] = $empresa;
                    $inputs_requisicion['clues'] = $clues;
                    $inputs_requisicion['lotes'] = 0;
                    $inputs_requisicion['sub_total'] = 0;
                    $inputs_requisicion['gran_total'] = 0;
                    $inputs_requisicion['iva'] = 0;
                    */

                    /*
                    if(isset($arreglo_requisiciones[$inputs_requisicion['tipo_requisicion']])){
                        $requisicion = $arreglo_requisiciones[$inputs_requisicion['tipo_requisicion']];
                        $requisicion->update($inputs_requisicion);
                        //$requisiciones_guardadas[$requisicion->id] = true;
                    }else{
                        $requisicion = Requisicion::create($inputs_requisicion);
                        $requisiciones[] = $requisicion;
                    }
                    $requisiciones_guardadas[$requisicion->id] = true;
                    */

                    //if(isset($inputs_requisicion['insumos'])){
                    /*
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
                    }*/
                }

                DB::table('requisicion_insumo_clues')->whereNull('requisicion_id')->whereIn('clues',$listado_clues)->delete();
                DB::table('requisicion_insumo_clues')->insert($lista_insumos);
                /*
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
                */
            //}

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

                $v = Validator::make($inputs_acta, $reglas_configuracion, $mensajes);
                if ($v->fails()) {
                    DB::rollBack();
                    return Response::json(['error' => 'Faltan datos de Configuración por capturar.', 'error_type'=>'data_validation', 'validacion'=>$v->errors()], HttpResponse::HTTP_CONFLICT);
                }
                
                $acta = Acta::create($inputs_acta);
                /*
                $actas = Acta::where('folio','like',$configuracion->clues.'/%')->lists('id');
                $max_requisicion = Requisicion::whereIn('acta_id',$actas)->max('numero');
                if(!$max_requisicion){
                    $max_requisicion = 0;
                }
                */

                foreach ($requisiciones as $index => $requisicion) {
                    //$max_requisicion += 1;
                    //$requisiciones[$index]->numero = $max_requisicion;
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

            if($acta){
                if($acta->estatus == 2){
                    $resultado = $this->actualizarCentral($acta->folio);
                }
            }
            
            //return Response::json([ 'data' => $requisiciones ,'acta' => $acta ],200);
            return Response::json([ 'data' => $lista_insumos ,'acta' => $acta ],200);
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage(), 'line' => $e->getLine()], HttpResponse::HTTP_CONFLICT);
        }
    }

    public function generarRequisicionPDF(){
        $meses = ['01'=>'ENERO','02'=>'FEBRERO','03'=>'MARZO','04'=>'ABRIL','05'=>'MAYO','06'=>'JUNIO','07'=>'JULIO','08'=>'AGOSTO','09'=>'SEPTIEMBRE','10'=>'OCTUBRE','11'=>'NOVIEMBRE','12'=>'DICIEMBRE'];
        $data = [];

        $usuario = JWTAuth::parseToken()->getPayload();
        $configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();

        $data['acta'] = new Acta;
        $requisiciones = Requisicion::whereNull('acta_id')->where('clues',$usuario->get('clues'))->with('insumosClues')->get();

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

    function decryptData($value){
        $key = "1C6B37CFCDF98AB8FA29E47E4B8EF1F3";
        $crypttext = $value;
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $decrypttext = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $crypttext, MCRYPT_MODE_ECB, $iv);
        return trim($decrypttext);
    }

    public function importar(Request $request)
    {

        if(Input::hasFile('zipfile')){
            $path_provisional = "/app/imports/unidades/";
            $destinationPath = storage_path().$path_provisional;
            $upload_success = Input::file('zipfile')->move($destinationPath, 'archivo_zip.zip');

            $zip = new ZipArchive;
            $res = $zip->open($destinationPath.'archivo_zip.zip');
            if ($res === TRUE) {
                $zip->extractTo($destinationPath);
                $zip->close();
            } else {
                return Response::json(['error' => 'No se pudo extraer el archivo'], HttpResponse::HTTP_CONFLICT);
            }

            $filename = $destinationPath . 'requisicion.json';
            $handle = fopen($filename, "r");
            $contents = fread($handle, filesize($filename));
            $DecryptedData=$this->decryptData($contents);
            fclose($handle);

            //$str = file_get_contents($destinationPath.'acta.json');
            $json = json_decode($DecryptedData, true);
            Storage::delete($destinationPath.'archivo_zip.zip');
            Storage::delete($filename);
            return Response::json([ 'data' => $json  ],200);
        }
    }
}
