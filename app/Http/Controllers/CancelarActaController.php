<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use App\Models\Acta;
use App\Models\Configuracion;
use App\Models\Requisicion;
use JWTAuth;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB, \PDF, \Storage, \ZipArchive, DateTime, Exception;

class CancelarActaController extends Controller{    

    public function cancelarActa(Request $request, $id){
        try{
            $usuario = JWTAuth::parseToken()->getPayload();
            $configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();

            $acta = Acta::find($id);

            if(!$acta){
                return Response::json(['error' => 'Acta a cancelar no encontrada','error_type'=>'data_validation'], 500);
            }

            DB::beginTransaction();
            DB::commit();

            return Response::json([ 'data' => $nueva_acta], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage(), 'line' => $e->getLine()], HttpResponse::HTTP_CONFLICT);
        }
    }
}
