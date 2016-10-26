<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Traits\SyncTrait;
use App\Http\Requests;
use App\Models\Acta;
use App\Models\Requisicion;
use App\Models\Configuracion;
use JWTAuth;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB, \PDF, \Storage, \ZipArchive, Exception;

class RequisicionesUnidadController extends Controller
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
            $recurso = Requisicion::where('clues','=', $usuario->get('clues'));

            $recurso = $recurso->select(DB::RAW('(select max(requisiciones.acta_id)) as acta_id'),
                                        DB::RAW('(select fecha_validacion from actas where actas.id = (select max(requisiciones.acta_id))) as fecha_validacion'),
                                        DB::RAW('(select max(requisiciones.id)) as id'),
                                        DB::RAW('(select max(requisiciones.clues)) as clues'),
                                        DB::RAW('(select sum(requisiciones.gran_total)) as gran_total'),
                                        DB::RAW('(select sum(requisiciones.gran_total_validado)) as gran_total_validado'),
                                        DB::RAW('(select max(requisiciones.estatus)) as estatus'),
                                        DB::RAW('(select estatus from actas where id = acta_id) as estatus_acta'),
                                        DB::RAW('(select max(created_at)) as created_at'));
            $recurso = $recurso->groupby("acta_id", "clues");


            /*if($query){
                $recurso = $recurso->where(function($condition)use($query){
                    $condition->where('clues','LIKE','%'.$query.'%');
                });
            }*/

            if($filtro){
                if(isset($filtro['estatus'])){
                    if($filtro['estatus'] == 'validado'){
                        $recurso = $recurso->where(DB::RAW('(select estatus from actas where id = acta_id)'),'3');
                    }else if($filtro['estatus'] == 'pendiente'){
                        $recurso = $recurso->whereNull(DB::RAW('(select estatus from actas where id = acta_id)'))
                                           ->orWhere(DB::RAW('(select estatus from actas where id = acta_id)'), "1");
                    }else if($filtro['estatus'] == 'pedido'){
                        $recurso = $recurso->where(DB::RAW('(select estatus from actas where id = acta_id)'),'4');
                    }
                }
            }

            $recurso = $recurso->get();
            $totales = $recurso->count();

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
            'array'         => "array",
            'min'           => "min",
            'unique'        => "unique",
            'date'          => "date"
        ];

        $reglas_requisicion = [
            'lotes'             =>'required',
            'tipo_requisicion'  =>'required',
            'dias_surtimiento'  =>'required',
            'sub_total'         =>'required',
            'gran_total'        =>'required',
            'iva'               =>'required'

        ];

        $inputs = Input::all();

        try {

            DB::beginTransaction();

            $usuario = JWTAuth::parseToken()->getPayload();
            $configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();

            if(isset($inputs['requisiciones'])){
                if(count($inputs['requisiciones']) > 6){
                    DB::rollBack();
                    throw new \Exception("No pueden haber mas de seis tipos requesiciones");
                }
                $max_acta = Requisicion::max("acta_id");
                $max_acta = $max_acta+1;
                foreach ($inputs['requisiciones'] as $inputs_requisicion) {
                    $inputs_requisicion['acta_id'] = $max_acta;
                    $inputs_requisicion['empresa'] = $configuracion->empresa_clave;
                    $inputs_requisicion['dias_surtimiento'] = 15;
                    $inputs_requisicion['sub_total'] = 0;
                    $inputs_requisicion['iva'] = 0;
                    $inputs_requisicion['gran_total'] = 0;
                    $inputs_requisicion['clues'] = $inputs_requisicion['clues'];
                    $inputs_requisicion['estatus'] = 1;
                    $requisicion = Requisicion::create($inputs_requisicion);

                    if(isset($inputs_requisicion['insumos'])){
                        $insumos = [];

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

            return Response::json([ 'data' => $requisicion ],200);

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
        $usuario = JWTAuth::parseToken()->getPayLoad();
        $configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();

        $requisicion = Requisicion::find($id);
        $requisiciones = Requisicion::where("acta_id", $requisicion->acta_id)->with("insumos")->get();
        $resultado = array("requisiciones" => $requisiciones);

        return Response::json([ 'data' => $resultado, 'configuracion'=>$configuracion ], 200);
    }


    public function importar(){
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

            $filename = $destinationPath . 'acta.json';
            $filezip  = $destinationPath.'archivo_zip.zip';
            $handle = fopen($filename, "r");
            $contents = fread($handle, filesize($filename));
            $DecryptedData=$this->decryptData($contents);
            fclose($handle);
            Storage::delete($filezip);
            Storage::delete($filename);
            $json = json_decode($DecryptedData, true);

            $acta = $json;

            $estatus_acta = $acta['estatus'];

            $requisiciones = $acta['requisiciones'];

            $actaDuplicada = Acta::where("folio", $acta['folio'])->first();

            if($actaDuplicada && $actaDuplicada->count() > 0 && $estatus_acta = 4)
            {
                return Response::json(['error' => 'No se puede actualizar los datos, por que su solicitud esta en estatus de pedido'], HttpResponse::HTTP_CONFLICT);
            }else
            {

                if($actaDuplicada &&$actaDuplicada->count() > 0)
                {
                    $actaDelete = Acta::with('requisiciones')->find($actaDuplicada->id);

                    foreach($actaDelete->requisiciones as $index => $value)
                    {
                        $requisicion = Requisicion::find($value->id);
                        $requisicion->insumos()->sync([]);
                        $requisicion->delete();
                    }
                    $actaDuplicada->delete();
                }


                try {

                    DB::beginTransaction();

                    $usuario = JWTAuth::parseToken()->getPayload();
                    $clues = $usuario->get('clues');
                    $configuracion = Configuracion::where('clues',$clues)->first();
                    $max_requisicion = Requisicion::max("acta_id");
                    $max_requisicion++;

                    $actaStore = Acta::create($acta);
                    $actaStore->id = $max_requisicion;
                    $actaStore->update();
                    $requisicion_auxiliar   = array();

                    foreach($requisiciones as $index => $value)
                    {
                        $bandera = 0;
                        $insumos_auxiliar   = array();
                        $subtotal   = 0;
                        $total      = 0;
                        $iva        = 0;

                        $subtotal_validado   = 0;
                        $total_validado      = 0;
                        $iva_validado        = 0;

                        //$subtotal_recibido   = 0;
                        //$total_recibido      = 0;
                        //$iva_recibido        = 0;

                        foreach($requisiciones[$index]['insumos_clues'] as $index2 => $value2)
                        {
                            if($value2['pivot']['clues'] == $clues)
                            {
                                $indice_insumos = count($insumos_auxiliar);
                                $insumos_auxiliar[$indice_insumos] = $value2;
                                unset($insumos_auxiliar[$indice_insumos]['pivot']['clues']);
                                $subtotal   += $insumos_auxiliar[$indice_insumos]['pivot']['total'];
                                $total      += $insumos_auxiliar[$indice_insumos]['pivot']['total'];

                                $subtotal_validado   += $insumos_auxiliar[$indice_insumos]['pivot']['total_validado'];
                                $total_validado      += $insumos_auxiliar[$indice_insumos]['pivot']['total_validado'];

                                //$subtotal_recibido   += $insumos_auxiliar[$indice_insumos]['pivot']['total_recibido'];
                                //$total_recibido      += $insumos_auxiliar[$indice_insumos]['pivot']['total_recibido'];

                                if($insumos_auxiliar[$indice_insumos]['tipo'] == 2)
                                {
                                    $iva            += ($insumos_auxiliar[$indice_insumos]['pivot']['total'] * 0.16);
                                    $iva_validado   += ($insumos_auxiliar[$indice_insumos]['pivot']['total_validado'] * 0.16);
                          //          $iva_recibido   += ($insumos_auxiliar[$indice_insumos]['pivot']['total_recibido'] * 0.16);
                                }
                                $total += $iva;
                                $total_validado += $iva_validado;
                            //    $total_recibido += $iva_recibido;
                                $bandera = 1;
                            }
                        }
                        if($bandera == 1)
                        {
                            $indice = count($requisicion_auxiliar);
                            $requisicion_auxiliar[$indice] = $requisiciones[$index];
                            $requisicion_auxiliar[$indice]['sub_total']     = $subtotal;
                            $requisicion_auxiliar[$indice]['gran_total']    = $total;
                            $requisicion_auxiliar[$indice]['iva']           = $iva;

                            $requisicion_auxiliar[$indice]['sub_total_validado']     = $subtotal_validado;
                            $requisicion_auxiliar[$indice]['gran_total_validado']    = $total_validado;
                            $requisicion_auxiliar[$indice]['iva_validado']           = $iva_validado;

                            /*$requisicion_auxiliar[$indice]['sub_total_recibido']     = $subtotal_recibido;
                            $requisicion_auxiliar[$indice]['gran_total_recibido']    = $total_validado;
                            $requisicion_auxiliar[$indice]['iva_recibido']           = $iva_recibido;*/

                            $requisicion_auxiliar[$indice]['lotes']              = count($insumos_auxiliar);

                            $requisicion_auxiliar[$indice]['clues'] = $usuario->get('clues');
                            unset($requisicion_auxiliar[$indice]['insumos']);
                            $requisicion_auxiliar[$indice]['insumos_clues'] = $insumos_auxiliar;


                        }
                    }

                    foreach($requisicion_auxiliar as $index => $value)
                    {
                        $requisicion_auxiliar[$index]['acta_id'] = $actaStore->id;
                        if($estatus_acta !=1 )
                            $requisicion_auxiliar[$index]['estatus'] = 2;

                        unset($requisicion_auxiliar[$index]['id']);
                        $requisicion = Requisicion::create($requisicion_auxiliar[$index]);
                        $insumos = array();
                        foreach($requisicion_auxiliar[$index]["insumos_clues"] as $index2 => $value2)
                        {
                            unset($value2['pivot']['requisicion_id']);
                            $insumos[] = $value2['pivot'];
                        }
                        $requisicion->insumos()->sync($insumos);
                    }

                    DB::commit();
                    return Response::json([ 'data' => $requisicion ],200);

                } catch (\Exception $e) {
                    DB::rollBack();
                    return Response::json(['error' => $e->getMessage(), 'line' => $e->getLine()], HttpResponse::HTTP_CONFLICT);
                }
            }
        }
    }


    function encryptData($value){
        $key = "1C6B37CFCDF98AB8FA29E47E4B8EF1F3";
        $text = $value;
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $crypttext = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $text, MCRYPT_MODE_ECB, $iv);
        return $crypttext;
    }

    function decryptData($value){
        $key = "1C6B37CFCDF98AB8FA29E47E4B8EF1F3";
        $crypttext = $value;
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $decrypttext = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $crypttext, MCRYPT_MODE_ECB, $iv);
        return trim($decrypttext);
    }

    public function generarJSON($id){
        $requisicion = Requisicion::find($id);
        $requisiciones = Requisicion::where("acta_id", $requisicion->acta_id)->with("insumos")->get();
        $resultado = array("requisiciones" => $requisiciones);


        if($requisicion->estatus > 2){
            return Response::json(['error' => 'No se puede generar el archivo por que el acta no se encuentra finalizada'], HttpResponse::HTTP_CONFLICT);
        }

        Storage::makeDirectory("export");

        Storage::put('export/json/'.$requisicion->clues."_".$requisicion->acta_id ,json_encode($resultado));

        $filename = storage_path()."/app/export/json/".$requisicion->clues."_".$requisicion->acta_id;
        $handle = fopen($filename, "r");
        $contents = fread($handle, filesize($filename));
        $EncryptedData=$this->encryptData($contents);
        Storage::put('export/json/'.$requisicion->clues."_".$requisicion->acta_id, $EncryptedData);
        fclose($handle);

        $storage_path = storage_path();

        $zip = new ZipArchive();
        $zippath = $storage_path."/app/";
        $zipname = "requisicion.".$requisicion->clues."_".$requisicion->acta_id.".zip";

        $zip_status = $zip->open($zippath.$zipname,ZIPARCHIVE::CREATE);

        if($zip_status === true) {

            $zip->addFile(storage_path().'/app/export/json/'.$requisicion->clues."_".$requisicion->acta_id,'requisicion.json');
            $zip->close();
            Storage::deleteDirectory("export");

            ///Then download the zipped file.
            header('Content-Type: application/zip');
            header('Content-disposition: attachment; filename='.$zipname);
            header('Content-Length: ' . filesize($zippath.$zipname));
            readfile($zippath.$zipname);
            Storage::delete($zipname);
            exit();
        }else{
            return Response::json(['error' => 'El archivo zip, no se encuentra'], HttpResponse::HTTP_CONFLICT);
        }

    }



    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id){
        $mensajes = [
            'required'      => "required",
            'unique'        => "unique",
            'date'          => "date"
        ];

        $reglas_requisicion = [
            'lotes'             =>'required',
            'tipo_requisicion'  =>'required',
            'dias_surtimiento'  =>'required',
            'sub_total'         =>'required',
            'gran_total'        =>'required',
            'iva'               =>'required'
        ];

        $usuario = JWTAuth::parseToken()->getPayload();
        $configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();

        $inputs = Input::all();

        try {

            DB::beginTransaction();

            $requisicion_index = Requisicion::find($id);

            if(isset($inputs['requisiciones'])){
                if(count($inputs['requisiciones']) > 6){
                    throw new \Exception("No pueden haber mas de seis requesiciones por acta");
                }

                $requisiciones_guardadas = [];
                $requisiciones_vigentes = [];
                $id_acta = 0;
                foreach ($inputs['requisiciones'] as $inputs_requisicion) {

                    $inputs_requisicion['dias_surtimiento'] = 15;
                    $v = Validator::make($inputs_requisicion, $reglas_requisicion, $mensajes);
                    if ($v->fails()) {
                        DB::rollBack();
                        return Response::json(['error' => $v->errors(), 'error_type'=>'form_validation'], HttpResponse::HTTP_CONFLICT);
                    }

                    if(isset($inputs_requisicion['id'])){
                        $requisiciones_vigentes[] = $inputs_requisicion['id'];
                        $requisicion = Requisicion::find($inputs_requisicion['id']);
                        $requisicion->update($inputs_requisicion);
                        $requisiciones_guardadas[$requisicion->id] = true;
                    }else{

                        $inputs_requisicion['empresa'] = $configuracion->empresa_clave;
                        $inputs_requisicion['acta_id'] = $requisicion_index->acta_id;
                        $requisicion = Requisicion::create($inputs_requisicion);
                        $requisiciones_vigentes[] = $requisicion->id;
                    }
                    $id_acta = $requisicion_index->acta_id;

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
                $eliminar_requisiciones = $requisiciones_vigentes;

                if(count($eliminar_requisiciones)){
                    Requisicion::whereNotIn('id',$eliminar_requisiciones)->where("acta_id", $id_acta)->delete();
                }
            }

            DB::commit();

            $requisicion = Requisicion::find($id);
            $requisiciones = Requisicion::where("acta_id", $requisicion->acta_id)->with("insumos")->get();
            $resultado = array("requisiciones" => $requisiciones);

            return Response::json([ 'data' => $resultado, 'respuesta_code' =>'updated' ],200);

        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage(), 'line' => $e->getLine()], HttpResponse::HTTP_CONFLICT);
        }
    }



    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id){
        try {

            $requisicion = Requisicion::find($id);
            $requisiciones = Requisicion::where("acta_id", $requisicion->acta_id)->with("insumos")->get();

            foreach ($requisiciones as $requisicion_aux) {
                $requisicion_aux->insumos()->sync([]);
                $requisicion_aux->insumosClues()->sync([]);
            }

            Requisicion::where('acta_id',$requisicion->acta_id)->delete();
            return Response::json(['data'=>'Elemento eliminado con exito'],200);
        } catch (Exception $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        }
    }
}
