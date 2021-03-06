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
use App\Models\Inventario;
use App\Models\ConfiguracionAplicacion;
use JWTAuth;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB, \PDF, \Storage, \ZipArchive;
use \Excel;

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
            $anio = date('Y');
            $clues = Configuracion::select('clues','clues_nombre as nombre','municipio','localidad','jurisdiccion','lista_base_id')
                            //->where('empresa_clave',$empresa)
                            ->with(['cuadroBasico'=>function($query)use($empresa){
                                $query->select('lista_base_insumos_id',$empresa.' AS llave');
                            }])
                            ->whereIn('tipo_clues',[1,2]);
            /*
            ,'inventario'=>function($query)use($anio){
                                $query->groupBy('clues','llave')->where('anio',$anio);
            }
            */
            if($configuracion->caravana_region){
                $clues = $clues->where('caravana_region',$configuracion->caravana_region);
            }else{
                $clues = $clues->where('jurisdiccion',$configuracion->jurisdiccion)->whereNull('caravana_region');
            }

            $clues = $clues->get();

            //$query = DB::getQueryLog();
            //$lastQuery = end($query);

            //Arreglo de solo las clues, para identificar los insumos pertenecientes al mismo grupo de clues
            $listado_clues = $clues->lists('clues');

            if(!Input::get('catalogos')){
                $insumos = DB::table('requisicion_insumo_clues')
                                ->leftjoin('insumos','insumos.id','=','requisicion_insumo_clues.insumo_id')
                                ->select('insumos.*','requisicion_insumo_clues.*')
                                ->whereNull('requisicion_id')
                                ->whereIn('requisicion_insumo_clues.clues',$listado_clues)
                                ->where('usuario',$usuario->get('id'))
                                ->get();
            }else{
                $insumos = [];
            }

            $anio = date('Y');
            $inventario_raw = Inventario::whereIn('clues',$listado_clues)->where('anio',$anio)->get();
            $inventario = [];

            for($index = 0, $total = count($inventario_raw); $index < $total; $index++) {
                $inventario_item = $inventario_raw[$index];
                if(!isset($inventario[$inventario_item->clues])){
                    $inventario[$inventario_item->clues] = [];
                }
                $inventario[$inventario_item->clues][$inventario_item->llave] = $inventario_item;
            }

            if($configuracion->empresa_clave == 'exfarma'){
                $captura_habilitada = ConfiguracionAplicacion::obtenerValor('habilitar_captura_exfarma');
            }else{
                $captura_habilitada = ConfiguracionAplicacion::obtenerValor('habilitar_captura');
            }
            
            return Response::json(['data'=>$insumos, 'clues'=>$clues, 'configuracion'=>$configuracion, 'captura_habilitada'=>$captura_habilitada->valor, 'inventario' => $inventario],200);
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

            $listado_clues = Configuracion::select('clues')
                            ->where('empresa_clave',$empresa)
                            ->where('jurisdiccion',$configuracion->jurisdiccion)
                            ->whereIn('tipo_clues',[1,2])
                            ->get();
            
            $listado_clues = $listado_clues->lists('clues');

            $arreglo_insumos_clues = DB::table('requisicion_insumo_clues')
                                        ->whereNull('requisicion_id')
                                        ->whereIn('clues',$listado_clues)
                                        ->get();

            $insumos_guardados = [];
            foreach ($arreglo_insumos_clues as $insumo) {
                if(!isset($insumos_guardados[$insumo->clues])){
                    $insumos_guardados[$insumo->clues] = [];
                }
                $insumos_guardados[$insumo->clues][$insumo->insumo_id] = $insumo;
            }

            $lista_insumos = [];
            $insumos_repetidos = [];


            foreach ($inputs as $input_insumo) {

                $guardar_insumo = [];

                if(!isset($input_insumo['usuario'])){
                    $guardar_insumo['usuario'] = $usuario->get('id');
                }else{
                    $guardar_insumo['usuario'] = $input_insumo['usuario'];
                }

                if(isset($insumos_guardados[$input_insumo['clues']])){
                    if(isset($insumos_guardados[$input_insumo['clues']][$input_insumo['insumo_id']])){
                        $insumo_base = $insumos_guardados[$input_insumo['clues']][$input_insumo['insumo_id']];

                        if($insumo_base->usuario == $usuario->get('id')){
                            $guardar_insumo['insumo_id'] = $insumo_base->insumo_id;
                            $guardar_insumo['clues'] = $insumo_base->clues;
                            $guardar_insumo['cantidad'] = $insumo_base->cantidad;
                            $guardar_insumo['total'] = $insumo_base->total;
                            $guardar_insumo['usuario'] = $insumo_base->usuario;
                        }else{
                            if(!isset($insumos_repetidos[$input_insumo['clues']])){
                                $insumos_repetidos[$input_insumo['clues']]=[];
                            }
                            $insumos_repetidos[$input_insumo['clues']][] = $input_insumo['insumo_id'];
                            continue;
                        }
                    }
                }

                $guardar_insumo['insumo_id'] = $input_insumo['insumo_id'];
                $guardar_insumo['clues'] = $input_insumo['clues'];
                $guardar_insumo['cantidad'] = $input_insumo['cantidad'];
                $guardar_insumo['total'] = $input_insumo['total'];
                if(isset($input_insumo['requisicion_id_unidad']))
                    $guardar_insumo['requisicion_id_unidad'] = $input_insumo['requisicion_id_unidad'];
                else
                    $guardar_insumo['requisicion_id_unidad'] = 0;


                $lista_insumos[] = $guardar_insumo;
            }

            DB::table('requisicion_insumo_clues')
                ->whereNull('requisicion_id')
                ->whereIn('clues',$listado_clues)
                ->where('usuario',$usuario->get('id'))
                ->delete();
            DB::table('requisicion_insumo_clues')->insert($lista_insumos);

            if($inputs_acta){
                if($configuracion->empresa_clave == 'exfarma'){
                    $habilitar_captura = ConfiguracionAplicacion::obtenerValor('habilitar_captura_exfarma');
                }else{
                    $habilitar_captura = ConfiguracionAplicacion::obtenerValor('habilitar_captura');
                }
                
                if(!$habilitar_captura->valor){
                    DB::rollBack();
                    return Response::json(['error' => 'Esta opción no esta disponible por el momento.', 'error_type'=>'data_validation'], HttpResponse::HTTP_CONFLICT);
                }

                $anio = date('Y');
                $max_acta = Acta::where('folio','like',$clues.'/%/'.$anio)->max('numero');
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
                
                $requisiciones = Requisicion::whereNull('acta_id')->where('clues',$clues)->get();//->with('insumosClues')

                $insumos_guardados = DB::table('requisicion_insumo_clues')
                                ->leftjoin('insumos','insumos.id','=','requisicion_insumo_clues.insumo_id')
                                ->select('insumos.*','requisicion_insumo_clues.*')
                                ->whereNull('requisicion_id')->whereIn('clues',$listado_clues)
                                //->groupBy('insumos.cause','insumos.tipo','insumos.controlado')
                                ->get();

                $arreglo_requisiciones = [];
                foreach ($requisiciones as $requisicion) {
                    $arreglo_requisiciones[$requisicion->tipo_requisicion] = $requisicion;
                }

                $requisiciones_guardadas = [];
                $tipo_requisicion_guardada = [];
                $lotes_requisicion = [];
                $requisicion_insumos_sync = [];
                foreach ($insumos_guardados as $insumo) {
                    $inputs_requisicion = [];
                    $guardar_insumo = [];
                    $tipo_requisicion = 0;

                    if($insumo->tipo == 1 && $insumo->cause == 1 && $insumo->surfactante == 1){
                        $tipo_requisicion = 5;
                    }else if($insumo->tipo == 1 && $insumo->cause == 0 && $insumo->surfactante == 1){
                        $tipo_requisicion = 6;
                    }else if($insumo->tipo == 1 && $insumo->cause == 1 && $insumo->controlado == 0){
                        $tipo_requisicion = 1;
                    }else if($insumo->tipo == 1 && $insumo->cause == 0){
                        $tipo_requisicion = 2;
                    }else if($insumo->tipo == 2 && $insumo->cause == 0){
                        $tipo_requisicion = 3;
                    }else if($insumo->tipo == 1 && $insumo->cause == 1 && $insumo->controlado == 1){
                        $tipo_requisicion = 4;
                    }

                    if($tipo_requisicion && !isset($tipo_requisicion_guardada[$tipo_requisicion])){
                        $inputs_requisicion['dias_surtimiento'] = 15;
                        $inputs_requisicion['empresa'] = $empresa;
                        $inputs_requisicion['clues'] = $clues;
                        $inputs_requisicion['tipo_requisicion'] = $tipo_requisicion;
                        $inputs_requisicion['pedido'] = $insumo->pedido;
                        $inputs_requisicion['lotes'] = 0;
                        $inputs_requisicion['sub_total'] = 0;
                        $inputs_requisicion['gran_total'] = 0;
                        $inputs_requisicion['iva'] = 0;
                        if(isset($arreglo_requisiciones[$tipo_requisicion])){
                            $requisicion = $arreglo_requisiciones[$tipo_requisicion];
                            $requisicion->update($inputs_requisicion);
                        }else{
                            $requisicion = Requisicion::create($inputs_requisicion);
                            $requisiciones[] = $requisicion;
                            $arreglo_requisiciones[$requisicion->tipo_requisicion] = $requisicion;
                        }
                        $requisiciones_guardadas[$requisicion->id] = true;
                        $tipo_requisicion_guardada[$tipo_requisicion] = $requisicion->id;
                    }

                    $guardar_insumo['requisicion_id'] = $tipo_requisicion_guardada[$tipo_requisicion];
                    $guardar_insumo['insumo_id'] = $insumo->insumo_id;
                    $guardar_insumo['clues'] = $insumo->clues;
                    $guardar_insumo['cantidad'] = $insumo->cantidad;
                    $guardar_insumo['total'] = $insumo->total;
                    $guardar_insumo['usuario'] = $insumo->usuario;
                    $guardar_insumo['requisicion_id_unidad'] = $insumo->requisicion_id_unidad;

                    $requisicion_insumos_sync[] = $guardar_insumo;
                }

                if(count($requisiciones) > 6){
                    throw new \Exception("No pueden haber mas de seis requisiciones");
                }

                if(count($requisiciones) == 0){
                    DB::rollBack();
                    return Response::json(['error' => 'Se debe capturar al menos un insumo.', 'error_type'=>'data_validation'], HttpResponse::HTTP_CONFLICT);
                }

                DB::table('requisicion_insumo_clues')->whereNull('requisicion_id')->whereIn('clues',$listado_clues)->delete();
                DB::table('requisicion_insumo_clues')->insert($requisicion_insumos_sync);

                foreach ($requisiciones as $index => $requisicion) {
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
                    $requisiciones[$index]->insumos()->sync([]);
                    $requisiciones[$index]->insumos()->sync($requisicion_insumos);

                    $total = $requisiciones[$index]->insumos()->sum('total');
                    $iva = 0;

                    $requisiciones[$index]->lotes = count($requisicion_insumos);
                    $requisiciones[$index]->sub_total = $total;

                    if($requisiciones[$index]->tipo_requisicion == 3){
                        $iva = $total*16/100;
                    }

                    $requisiciones[$index]->iva = $iva;
                    $requisiciones[$index]->gran_total = $total + $iva;
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
            return Response::json([ 'data' => $lista_insumos ,'acta' => $acta, 'insumos_repetidos'=>$insumos_repetidos ],200);
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

    public function importar(Request $request){

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

    public function generarExcel() {
        $meses = ['01'=>'ENERO','02'=>'FEBRERO','03'=>'MARZO','04'=>'ABRIL','05'=>'MAYO','06'=>'JUNIO','07'=>'JULIO','08'=>'AGOSTO','09'=>'SEPTIEMBRE','10'=>'OCTUBRE','11'=>'NOVIEMBRE','12'=>'DICIEMBRE'];
        $data = [];

        $usuario = JWTAuth::parseToken()->getPayload();
        $configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();
        $empresa = $configuracion->empresa_clave;

        $clues = Configuracion::select('clues','clues_nombre as nombre','municipio','localidad','jurisdiccion','lista_base_id')
                        //->where('empresa_clave',$empresa)
                        ->with(['cuadroBasico'=>function($query)use($empresa){
                            $query->select('lista_base_insumos_id',$empresa.' AS llave');
                        }])
                        ->whereIn('tipo_clues',[1,2]);
        
        if($configuracion->caravana_region){
            $clues = $clues->where('caravana_region',$configuracion->caravana_region);
        }else{
            $clues = $clues->where('jurisdiccion',$configuracion->jurisdiccion)->whereNull('caravana_region');
        }

        $clues = $clues->get();

        //$query = DB::getQueryLog();
        //$lastQuery = end($query);

        //Arreglo de solo las clues, para identificar los insumos pertenecientes al mismo grupo de clues
        $listado_clues = $clues->lists('clues');
        $insumos = DB::table('requisicion_insumo_clues')
                        ->leftjoin('insumos','insumos.id','=','requisicion_insumo_clues.insumo_id')
                        ->select('insumos.*','requisicion_insumo_clues.*')
                        ->whereNull('requisicion_id')
                        ->whereIn('requisicion_insumo_clues.clues',$listado_clues)
                        ->orderBy('insumos.lote','ASC')
                        ->get();

        $data['requisiciones'] = [];
        $clues_capturadas = [];
        for ($index = 0, $total = count($insumos) ; $index < $total ; $index++) { 
            $insumo = $insumos[$index];
            
            $tipo_requisicion = 0;

            if($insumo->tipo == 1 && $insumo->cause == 1 && $insumo->surfactante == 1){
                $tipo_requisicion = 5;
            }else if($insumo->tipo == 1 && $insumo->cause == 0 && $insumo->surfactante == 1){
                $tipo_requisicion = 6;
            }else if($insumo->tipo == 1 && $insumo->cause == 1 && $insumo->controlado == 0){
                $tipo_requisicion = 1;
            }else if($insumo->tipo == 1 && $insumo->cause == 0){
                $tipo_requisicion = 2;
            }else if($insumo->tipo == 2 && $insumo->cause == 0){
                $tipo_requisicion = 3;
            }else if($insumo->tipo == 1 && $insumo->cause == 1 && $insumo->controlado == 1){
                $tipo_requisicion = 4;
            }

            if(!isset($data['requisiciones'][$tipo_requisicion])){
                $data['requisiciones'][$tipo_requisicion] = [
                    'pedido' => $insumo->pedido,
                    'insumos_por_clues' => []
                ];
            }

            if(!isset($data['requisiciones'][$tipo_requisicion]['insumos_por_clues'][$insumo->clues])){
                $data['requisiciones'][$tipo_requisicion]['insumos_por_clues'][$insumo->clues] = [];
            }
            $data['requisiciones'][$tipo_requisicion]['insumos_por_clues'][$insumo->clues][] = $insumo;

            if(!isset($clues_capturadas[$insumo->clues])){
                $clues_capturadas[$insumo->clues] = true;
            }
        }

        $data['datos_clues'] = [];
        for ($index = 0, $total = count($clues) ; $index < $total ; $index++) {
            $datos_clues = $clues[$index];
            if(isset($clues_capturadas[$datos_clues->clues])){
                $data['datos_clues'][$datos_clues->clues] = $datos_clues;
            }
        }

        $data['unidad'] = mb_strtoupper($configuracion->clues_nombre,'UTF-8');
        $data['empresa'] = $configuracion->empresa_nombre;
        $data['empresa_clave'] = $configuracion->empresa_clave;

        $fecha = explode('-',date('Y-m-d'));
        $fecha[1] = $meses[$fecha[1]];
        $data['fecha'] = $fecha;
        
        $nombre_archivo = 'Requisiciones-'.$configuracion->clues;

        //return Response::json(['data' => $data], 200);

        Excel::create($nombre_archivo, function($excel) use($data) {
            $requisiciones = $data['requisiciones'];

            foreach($requisiciones as $tipo_requisicion => $requisicion) {
                $tipo  = '';
                switch($tipo_requisicion) {
                    case 1: $tipo = "MEDICAMENTOS CAUSES"; break;
                    case 2: $tipo = "MEDICAMENTOS NO CAUSES"; break;
                    case 3: $tipo = "MATERIAL DE CURACION"; break;
                    case 4: $tipo = "MEDICAMENTOS CONTROLADOS"; break;
                    case 5: $tipo = "FACTOR SURFACTANTE (CAUSES)"; break;
                    case 6: $tipo = "FACTOR SURFACTANTE (NO CAUSES)"; break;
                    
                }
                
                $excel->sheet($tipo, function($sheet) use($requisicion,$tipo_requisicion,$data) {
                            $sheet->setAutoSize(true);

                            $sheet->mergeCells('A1:I1');
                            $sheet->row(1, array('UNIDAD: '.$data['unidad']));
                            //$sheet->row(1, array('PROVEEDOR DESIGNADO: '.mb_strtoupper($pedido_proveedor['proveedor'],'UTF-8')));

                            $sheet->mergeCells('A2:I2'); 
                            $sheet->row(2, array('PEDIDO: '.$requisicion['pedido']));
                            //$sheet->row(2, array('REQUISICIÓN NO.: '.$requisicion->numero));

                            $sheet->mergeCells('A3:I3'); 
                            $sheet->row(3, array('PROVEEDOR DESIGNADO: '.mb_strtoupper($data['empresa'],'UTF-8')));

                            $sheet->mergeCells('A4:I4'); 
                            $sheet->row(4, array('FECHA: '.$data['fecha'][2]." DE ".$data['fecha'][1]." DEL ".$data['fecha'][0]));
                            

                            $sheet->mergeCells('A5:I5'); 
                            $sheet->row(5, array(''));

                            $sheet->mergeCells('A6:I6');
                            $sheet->row(6, array(''));

                            $sheet->row(7, array(
                                'CLUES','NOMBRE','No. DE LOTE', 'CLAVE','DESCRIPCIÓN DE LOS INSUMOS','CANTIDAD','UNIDAD DE MEDIDA','PRECIO UNITARIO','PRECIO TOTAL'
                            ));
                            $sheet->row(1, function($row) {
                                $row->setBackground('#DDDDDD');
                                $row->setFontWeight('bold');
                                $row->setFontSize(16);
                            });

                            $sheet->row(2, function($row) {
                                $row->setBackground('#DDDDDD');
                                $row->setFontWeight('bold');
                                $row->setFontSize(14);
                            });
                             $sheet->row(3, function($row) {
                                $row->setBackground('#DDDDDD');
                                $row->setFontWeight('bold');
                                $row->setFontSize(14);
                            });
                             $sheet->row(4, function($row) {
                                $row->setBackground('#DDDDDD');
                                $row->setFontWeight('bold');
                                $row->setFontSize(14);
                            });

                            $sheet->row(5, function($row) {
                                $row->setBackground('#DDDDDD');
                                $row->setFontWeight('bold');
                                $row->setFontSize(14);
                            });

                            $sheet->row(6, function($row) {
                                $row->setBackground('#DDDDDD');
                                $row->setFontWeight('bold');
                                $row->setFontSize(14);
                            });

                            $sheet->row(7, function($row) {
                                // call cell manipulation methods
                                $row->setBackground('#DDDDDD');
                                $row->setFontWeight('bold');

                            });

                            $contador_filas = 7;
                            //for ($index = 0, $total = count($requisicion['insumos_por_clues']) ; $index < $total ; $index++) { 
                            foreach($requisicion['insumos_por_clues'] as $clues => $insumos){
                                for ($index = 0, $total = count($insumos) ; $index < $total ; $index++) { 
                                    $insumo = $insumos[$index];
                                    $sheet->appendRow(array(
                                        $data['datos_clues'][$clues]->clues,
                                        $data['datos_clues'][$clues]->nombre,
                                        $insumo->lote, 
                                        $insumo->clave,
                                        $insumo->descripcion,
                                        $insumo->cantidad,
                                        $insumo->unidad,
                                        $insumo->precio,
                                        $insumo->total
                                    ));

                                    $contador_filas += 1;
                                }
                            }

                            $sheet->appendRow(array(
                                '', 
                                '',
                                '',
                                '',
                                '',
                                '',
                                '',
                                'SUBTOTAL',
                                '=SUM(I8:I'.$contador_filas.')'
                            ));
                            

                            if($tipo_requisicion == 3){
                                $iva = '=I'.($contador_filas+1).'*16/100';
                            }else{
                                $iva = '0.0';
                            }

                            $sheet->appendRow(array(
                                '', 
                                '',
                                '',
                                '',
                                '',
                                '',
                                '',
                                'IVA',
                                $iva
                            ));

                            $sheet->appendRow(array(
                                '', 
                                '',
                                '',
                                '',
                                '',
                                '',
                                '',
                                'TOTAL',
                                '=SUM(I'.($contador_filas+1).':I'.($contador_filas+2).')'
                            ));

                            $contador_filas += 3;

                            $sheet->setBorder("A1:I$contador_filas", 'thin');

                            $sheet->cells("H1:I$contador_filas", function($cells) {
                                $cells->setAlignment('right');
                            });

                            $sheet->cells("C7:C$contador_filas", function($cells) {
                                $cells->setAlignment('center');
                            });
                            $sheet->cells("D7:D$contador_filas", function($cells) {
                                $cells->setAlignment('center');
                            });
                            $sheet->cells("F7:F$contador_filas", function($cells) {
                                $cells->setAlignment('center');
                            });

                            $sheet->setColumnFormat(array(
                                "H8:I$contador_filas" => '"$"#,##0.00_-'
                            ));
                });
            }
        })->export('xls');
    }
}
