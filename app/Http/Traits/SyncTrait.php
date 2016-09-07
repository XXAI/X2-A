<?php
namespace App\Http\Traits;

use App\Models\Acta;
use App\Models\Requisicion;
use DB, Exception;

trait SyncTrait{
	public function actualizarCentral($folio){
        try{
            $acta_local = Acta::with('requisiciones.insumos','requisiciones.insumosClues')->where('folio',$folio)->first();

            $default = DB::getPdo(); // Default conn
            $secondary = DB::connection('mysql_sync')->getPdo();

            DB::setPdo($secondary);
            DB::beginTransaction();

            //$conexion_remota = DB::connection('mysql_sync');
            //$conexion_remota->beginTransaction();

            $datos_acta = $acta_local->toArray();
            $datos_acta['empresa_clave'] = $datos_acta['empresa'];
            $folio_array = explode('/', $datos_acta['folio']);
            $datos_acta['clues'] = $folio_array[0];
            $datos_acta['estatus_sincronizacion'] = 1;

            $acta_central = new Acta();
            $acta_central = $acta_central->setConnection('mysql_sync');
            $acta_central = $acta_central->where('folio',$folio)->first();

            if($acta_central){
                throw new Exception('El acta ya se encuentra en el servidor central', 1);
            }

            $acta_central = new Acta();
            $acta_central->folio = $datos_acta['folio'];
            $acta_central->clues = $datos_acta['clues'];
            $acta_central->ciudad = $datos_acta['ciudad'];
            $acta_central->fecha = $datos_acta['fecha'];
            $acta_central->hora_inicio = $datos_acta['hora_inicio'];
            $acta_central->hora_termino = $datos_acta['hora_termino'];
            $acta_central->lugar_reunion = $datos_acta['lugar_reunion'];
            $acta_central->lugar_entrega = $datos_acta['lugar_entrega'];
            $acta_central->empresa_clave = $datos_acta['empresa_clave'];
            $acta_central->estatus = $datos_acta['estatus'];
            $acta_central->estatus_sincronizacion = $datos_acta['estatus_sincronizacion'];
            $acta_central->director_unidad = $datos_acta['director_unidad'];
            $acta_central->administrador = $datos_acta['administrador'];
            $acta_central->encargado_almacen = $datos_acta['encargado_almacen'];
            $acta_central->coordinador_comision_abasto = $datos_acta['coordinador_comision_abasto'];
            $acta_central->numero = $datos_acta['numero'];
            $acta_central->created_at = $datos_acta['created_at'];
            $acta_central->updated_at = $datos_acta['updated_at'];
            

            if($acta_central->save()){
                foreach ($acta_local->requisiciones as $requisicion) {
                    $requisicion_central = new Requisicion();
                    $requisicion_central->pedido                = $requisicion->pedido;
                    $requisicion_central->lotes                 = $requisicion->lotes;
                    $requisicion_central->tipo_requisicion      = $requisicion->tipo_requisicion;
                    $requisicion_central->dias_surtimiento      = $requisicion->dias_surtimiento;
                    $requisicion_central->sub_total             = $requisicion->sub_total;
                    $requisicion_central->gran_total            = $requisicion->gran_total;
                    $requisicion_central->iva                   = $requisicion->iva;
                    $requisicion_central->sub_total_validado    = $requisicion->sub_total;
                    $requisicion_central->gran_total_validado   = $requisicion->gran_total;
                    $requisicion_central->iva_validado          = $requisicion->iva;
                    $requisicion_central->created_at            = $requisicion->created_at;
                    $requisicion_central->updated_at            = $requisicion->updated_at;

                    $acta_central->requisiciones()->save($requisicion_central);

                    $insumos = [];
                    foreach ($requisicion->insumos as $req_insumo) {
                        $insumos[] = [
                            'insumo_id'         => $req_insumo->id,
                            'cantidad'          => $req_insumo->pivot->cantidad,
                            'total'             => $req_insumo->pivot->total,
                            'cantidad_validada' => $req_insumo->pivot->cantidad,
                            'total_validado'    => $req_insumo->pivot->total
                        ];
                    }
                    $requisicion_central->insumos()->sync($insumos);

                    $insumos = [];
                    foreach ($requisicion->insumosClues as $req_insumo) {
                        $insumos[] = [
                            'insumo_id'         => $req_insumo->id,
                            'clues'             => $req_insumo->pivot->clues,
                            'cantidad'          => $req_insumo->pivot->cantidad,
                            'total'             => $req_insumo->pivot->total,
                            'cantidad_validada' => $req_insumo->pivot->cantidad,
                            'total_validado'    => $req_insumo->pivot->total
                        ];
                    }
                    $requisicion_central->insumosClues()->sync($insumos);
                }
            }

            //$conexion_remota->commit();
            DB::commit();
            DB::setPdo($default);

            $acta_local->estatus_sincronizacion = 1;
            $acta_local->save();

            return ['estatus'=>true];
            //return Response::json(['acta_central'=>$acta_central,'acta_central'=>$acta_central],200);
        }catch(Exception $e){
            //$conexion_remota->rollback();
            DB::rollBack();
            DB::setPdo($default);
            return ['estatus'=>false,'message'=>$e->getMessage()];
            //return Response::json(['error' => $e->getMessage(), 'line' => $e->getLine()], HttpResponse::HTTP_CONFLICT);
        }
    }
}