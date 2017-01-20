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

class ClonarActasController extends Controller{
    
    public function clonar(Request $request, $id){
        try{
            $usuario = JWTAuth::parseToken()->getPayload();
            $configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();

            if($configuracion->tipo_clues == 2){
                return Response::json(['error' => 'La herramienta para clonar no se encuentra disponible en este momento','error_type'=>'data_validation'], 500);
            }

            $acta = Acta::with('requisiciones.insumos','requisiciones.insumosClues')->find($id);

            if(!$acta){
                return Response::json(['error' => 'Acta a clonar no encontrada','error_type'=>'data_validation'], 500);
            }

            DB::beginTransaction();

            $nueva_acta = Acta::with('requisiciones.insumos')->whereNull('numero')->where('folio','like',$usuario->get('clues').'/%')->first();

            if(!$nueva_acta){
                $acta_creada = false;
                $nueva_acta = new Acta();
                $nueva_acta->folio          = $usuario->get('clues') . '/00/' . date('Y');
                $nueva_acta->fecha          = date('Y-m-d');
                $nueva_acta->estatus        = 1;
                $nueva_acta->empresa        = $acta->empresa;
                $nueva_acta->hora_inicio    = '10:00:00';
                $nueva_acta->hora_termino   = '12:00:00';
                $nueva_acta->ciudad         = $acta->ciudad;
                $nueva_acta->lugar_reunion  = $acta->lugar_reunion;
            }else{
                $acta_creada = true;
            }

            if($nueva_acta->save()){
                //$lista_proveedores = [];

                foreach ($acta->requisiciones as $requisicion) {
                    if($acta_creada){
                        $nueva_requisicion = Requisicion::where('acta_id',$nueva_acta->id)->where('tipo_requisicion',$requisicion->tipo_requisicion)->first();
                    }else{
                        $nueva_requisicion = false;
                    }

                    if(!$nueva_requisicion){
                        $nueva_requisicion = new Requisicion();
                    }

                    $nueva_requisicion->estatus = null;
                    $nueva_requisicion->sub_total             = $requisicion->sub_total;
                    $nueva_requisicion->gran_total            = $requisicion->gran_total;
                    $nueva_requisicion->iva                   = $requisicion->iva;
                    $nueva_requisicion->pedido                = $requisicion->pedido;
                    $nueva_requisicion->empresa               = $requisicion->empresa;
                    $nueva_requisicion->lotes                 = $requisicion->lotes;
                    $nueva_requisicion->tipo_requisicion      = $requisicion->tipo_requisicion;
                    $nueva_requisicion->dias_surtimiento      = $requisicion->dias_surtimiento;

                    if($acta->estatus > 2){
                        $nueva_requisicion->sub_total         = $requisicion->sub_total_validado;
                        $nueva_requisicion->gran_total        = $requisicion->gran_total_validado;
                        $nueva_requisicion->iva               = $requisicion->iva_validado;
                    }

                    //$nueva_requisicion->updated_at            = $requisicion->updated_at;
                    $nueva_acta->requisiciones()->save($nueva_requisicion);

                    $insumos = [];
                    foreach ($requisicion->insumos as $req_insumo) {

                        if($acta->estatus > 2 && $req_insumo->pivot->cantidad_validada <= 0){
                            continue;
                        }

                        if($acta->estatus > 2){
                            $req_insumo->pivot->cantidad = $req_insumo->pivot->cantidad_validada;
                            $req_insumo->pivot->total = $req_insumo->pivot->total_validado;
                        }

                        $insumos[] = [
                            'insumo_id'         => $req_insumo->id,
                            'cantidad'          => $req_insumo->pivot->cantidad,
                            'total'             => $req_insumo->pivot->total,
                            'cantidad_validada' => null,
                            'total_validado'    => null,
                            'proveedor_id'      => null
                        ];
                    }
                    $nueva_requisicion->insumos()->sync([]);
                    $nueva_requisicion->insumos()->sync($insumos);
                    $nueva_requisicion->lotes = count($insumos);
                    $nueva_requisicion->save();
                }
            }else{
                throw new Exception("Acta no creada", 1);
            }

            DB::commit();

            return Response::json([ 'data' => $nueva_acta], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage(), 'line' => $e->getLine()], HttpResponse::HTTP_CONFLICT);
        }
    }
}
