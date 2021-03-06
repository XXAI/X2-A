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
use App\Models\ConfiguracionAplicacion;
use App\Models\Inventario;
use App\Models\HistorialInventario;
use JWTAuth;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB, \PDF, \Storage, \ZipArchive;
use \Excel;

class InventarioController extends Controller
{
    use SyncTrait;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request){
        try{
            
            $usuario = JWTAuth::parseToken()->getPayload();
            
            $inventario = Inventario::join("insumos", "insumos.clave", "=", "inventario.llave")
                                    ->where("clues", $usuario->get('clues'))
                                    ->groupBy("insumos.clave")
                                    ->get();
            
            return Response::json(['data'=>$inventario],200);
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

    public function importar(Request $request)
    {
        $usuario = JWTAuth::parseToken()->getPayload();
       // return $usuario->get('clues');
        if(Input::hasFile('excel')){
            $inputs=Input::all();
            $mes= $inputs['mes'];
            $anio=$inputs['anio'];
            $path_provisional = "/app/imports/unidades/";
            $destinationPath = storage_path().$path_provisional;
            $fila;

            $upload_success = Input::file('excel')->move($destinationPath, $usuario->get('clues').'-'.$anio.'-'.$mes.'inventario.xls');
            $highestRow=0;
            $highestColumn=0;
            $excel=[];
            try
            {
                $xls=Excel::load($destinationPath.$usuario->get('clues').'-'.$anio.'-'.$mes.'inventario.xls',function ($reader) {})->get();

                if(!empty($xls) && $xls->count()){
                    $contador=0;
                    $contadorBueno=0;

                        if(!empty($xls)){
                             DB::table('inventario')
                                ->where('anio',$anio)
                                ->where('clues',$usuario->get('clues'))
                                //->whereNotNull($mes)
                                ->delete(); 
                            foreach ($xls as $key=> $v) {
                                  
                                $contadorBueno++;
                                $meses=['1','2','3','4','5','6','7','8','9','10','11','12'];
                                
                                /*$arreglo_inventario = array();
                                $arreglo_inventario['anio']     = $anio;
                                $arreglo_inventario['mes']      = $mes;
                                $arreglo_inventario['clues']    = $usuario->get('clues');
                                $arreglo_inventario['llave']    = $v->clave;
                                $arreglo_inventario['valor']    = $v->enero;*/
                                
                                //$inventario = Inventario::create($arreglo_inventario);
                                $inventario         = new Inventario();
                                $inventario->anio   = $anio;
                                $inventario->mes    = $mes;
                                $inventario->clues  = $usuario->get('clues');
                                $inventario->llave  = $v->clave;
                                $inventario->valor  = $v->enero;
                                /*$inventario->$meses[0]=$v->enero;
                                $inventario->$meses[1]=$v->febrero;
                                $inventario->$meses[2]=$v->marzo;
                                $inventario->$meses[3]=$v->abril;
                                $inventario->$meses[4]=$v->mayo;
                                $inventario->$meses[5]=$v->junio;
                                $inventario->$meses[6]=$v->julio;
                                $inventario->$meses[7]=$v->agosto;
                                $inventario->$meses[8]=$v->septiembre;
                                $inventario->$meses[9]=$v->octubre;
                                $inventario->$meses[10]=$v->noviembre;
                                $inventario->$meses[11]=$v->diciembre;*/
                                $inventario->save();
                                
                                $historial_inventario           = new HistorialInventario();
                                $historial_inventario->anio     = $anio;
                                $historial_inventario->mes      = $mes;
                                $historial_inventario->clues    = $usuario->get('clues');
                                $historial_inventario->llave    = $v->clave;
                                $historial_inventario->valor    = $v->enero;
                                $historial_inventario->save();
                                
                            }                            
                        }
                    
                    if(!empty($fila)){
                        return "si cargo xls".$fila;
                    }
                    return Response::json([ 'data' => $inventario ],200);
                }
            }
            catch(Exception $ex){
            return Response::json(['error'=>$e->getMessage()],500);
             }
           
            return Response::json([ 'data' => $xls  ],200);
        }
         return Response::json([ 'data' => $json  ],200);
    }
    
    public function generarExcel() {
        $meses = ['01'=>'ENERO','02'=>'FEBRERO','03'=>'MARZO','04'=>'ABRIL','05'=>'MAYO','06'=>'JUNIO','07'=>'JULIO','08'=>'AGOSTO','09'=>'SEPTIEMBRE','10'=>'OCTUBRE','11'=>'NOVIEMBRE','12'=>'DICIEMBRE'];
        $data = [];

        $usuario = JWTAuth::parseToken()->getPayload();
        $configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();
        $empresa = $configuracion->empresa_clave;

        /*$clues = Configuracion::select('clues','clues_nombre as nombre','municipio','localidad','jurisdiccion','lista_base_id')
                        //->where('empresa_clave',$empresa)
                        ->with(['cuadroBasico'=>function($query)use($empresa){
                            $query->select('lista_base_insumos_id',$empresa.' AS llave');
                        }])
                        ->whereIn('tipo_clues',[1,2])->get();*/
        //return Response::json([ 'data' => $configuracion  ],200);
        
        $inputs = Input::all();
        $anio   = $inputs['anio'];
        
        $json = HistorialInventario:://join("insumos", "insumos.clave","=", "historial_inventario.llave")
                                    where("clues", $usuario->get('clues'))
                                    ->where("anio", $anio)
                                    //->groupBy("insumos.clave")
                                    ->select("mes",
                                             "llave",
                                             DB::RAW("(select descripcion from insumos where insumos.clave=historial_inventario.llave limit 1) as nombre_insumo"),
                                             "valor",
                                             "created_at"
                                            )    
                                    ->orderBy("historial_inventario.llave", "asc")    
                                    ->orderBy("mes", "asc")    
                                    ->orderBy("created_at", "asc")    
                                    ->get();
        
        
        
        $arreglo_datos = array();
        foreach($json as $key => $valor) {
            if(isset($arreglo_datos[$valor->mes]))
            {
                $arreglo_datos[$valor->mes]['insumos'][] = $valor;
            }else{
                 $arreglo_datos[$valor->mes] = array();
                 $arreglo_datos[$valor->mes]['claves'] = array();
                 $arreglo_datos[$valor->mes]['lista_claves'] = array();
                 $arreglo_datos[$valor->mes]['fechas'] = array();
                 $arreglo_datos[$valor->mes]['insumos'][] = $valor;
             }
             
            if(!in_array($valor->llave, $arreglo_datos[$valor->mes]['lista_claves']))
             {
                $indice = count($arreglo_datos[$valor->mes]['lista_claves']);
                $arreglo_datos[$valor->mes]['lista_claves'][] = $valor->llave;
                 
                $arreglo_datos[$valor->mes]['lista_claves_nombre'][$indice]['clave'] = $valor->llave;
                $arreglo_datos[$valor->mes]['lista_claves_nombre'][$indice]['nombre'] = $valor->nombre_insumo;
             }
            
             if(!in_array($valor->llave, $arreglo_datos[$valor->mes]['claves']))
             {
                $arreglo_datos[$valor->mes]['claves'][$valor->llave][] = $valor->valor;
             }else
             {
                 
                $arreglo_datos[$valor->mes]['claves'][$valor->llave][] = $valor->valor;
             }
             
             if(!in_array(explode(",",$valor->created_at)[0], $arreglo_datos[$valor->mes]['fechas']))
             {    
                $arreglo_datos[$valor->mes]['fechas'][] = explode(",",$valor->created_at)[0]; 
             }
         }
        
       
        //return Response::json([ 'data' => $arreglo_datos  ],200);
        
        //EMPIEZA
        Excel::create("Reporte", function($excel) use($arreglo_datos, $configuracion) {
            
            $meses = array(
            "0"=>"ENERO",
            "1"=>"FEBRERO",
            "2"=>"MARZO",
            "3"=>"ABRIL",
            "4"=>"MAYO",
            "5"=>"JUNIO",
            "6"=>"JULIO",
            "7"=>"AGOSTO",
            "8"=>"SEPTIEMBRE",
            "9"=>"OCTUBRE",
            "10"=>"NOVIEMBRE",
            "11"=>"DICIEMBRE"    
            );
            foreach($arreglo_datos as $key => $value) {
                
                $mes  = '';
                switch($key) {
                    case 1: $mes = "ENERO"; break;
                    case 2: $mes = "FEBRERO"; break;
                    case 3: $mes = "MARZO"; break;
                    case 4: $mes = "ABRIL"; break;
                    case 5: $mes = "MAYO"; break;
                    case 6: $mes = "JUNIO"; break;
                    case 7: $mes = "JULIO"; break;
                    case 8: $mes = "AGOSTO"; break;
                    case 9: $mes = "SEPTIEMBRE"; break;
                    case 10: $mes = "OCTUBRE"; break;
                    case 11: $mes = "NOVIEMBRE"; break;
                    case 12: $mes = "DICIEMBRE"; break;
                    
                }
                $excel->sheet($mes, function($sheet) use($value, $meses, $configuracion) {
                    $sheet->setAutoSize(true);

                    $sheet->mergeCells('A1:B1');
                    $sheet->row(1, array('UNIDAD: ('.$configuracion['clues'].") ".$configuracion['clues_nombre']));

                    $sheet->mergeCells('A2:B2'); 
                    $sheet->row(2, array('FECHA: '.date("d")." DE ".$meses[(date("n")-1)]." DEL ".date("Y")));

                    $sheet->mergeCells('A3:B3'); 
                    $sheet->row(3, array(''));

                    $sheet->mergeCells('A4:B4');
                    $sheet->row(4, array(''));
                    
                    $arreglo_titulo = $value['fechas'];
                    array_unshift($arreglo_titulo, "CLAVE", "DESCRIPCION");
                    
                    $sheet->row(5, $arreglo_titulo);
                    
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

                
                    $contador_filas = 6;
                    
                    //Empieza el llenado
                    
                    foreach($value['lista_claves'] as $key_insumos => $value_insumos) {
                        $arreglo_tabla = array();
                        $arreglo_tabla[] = $value_insumos;
                        $arreglo_tabla[] = $value['lista_claves_nombre'][$key_insumos]['nombre'];
                        
                        foreach($value['claves'][$value_insumos] as $key_tabla => $value_tabla) {
                            $arreglo_tabla[] = $value_tabla;
                        }
                        
                        $sheet->row($contador_filas, $arreglo_tabla);
                        $contador_filas++;
                    }
                    //

                
            
                });

                
            }
        })->export('xls');
        
    
    }
}
