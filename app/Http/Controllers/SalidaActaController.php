<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Traits\SyncTrait;
use App\Http\Requests;
use App\Models\Acta;
use App\Models\Usuario;
use App\Models\StockInsumo;
use JWTAuth;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB, Exception;

class SalidaActaController extends Controller
{
    public function index(Request $request){
        try {
            $usuario = JWTAuth::parseToken()->getPayload();
            $acta = Acta::join("requisiciones", "requisiciones.acta_id", "=", "actas.id")
                        ->join("requisicion_insumo_clues", "requisiciones.id", "=", "requisicion_insumo_clues.requisicion_id")
                        ->Leftjoin("salidas", "salidas.acta_id", "=", "actas.id")
                        ->Leftjoin("salida_detalles", "salidas.id", "=", "salida_detalles.salida_id")
                        ->where("actas.estatus", 4)
                        ->where("actas.folio", 'like', $usuario->get('clues')."%")
                        ->where("cantidad_validada", ">", "cantidad_suministrada")
                        ->where("requisicion_insumo_clues.clues", "!=", [$usuario->get('clues')])
                        ->groupBy("actas.folio")
                        ->select(DB::RAW("sum(requisicion_insumo_clues.cantidad_validada) as cantidad_validada"),
                                'actas.folio',
                                'actas.id',
                                DB::RAW("IF((sum(salida_detalles.cantidad_surtido) = null), 0,(sum(salida_detalles.cantidad_surtido))) as cantidad_suministrada"));


            $query = Input::get('query');

            if($query){
                $acta = $acta->where(function($condition)use($query){
                    $condition->where('folio','LIKE','%'.$query.'%');
                });
            }

            $acta = $acta->get();
            //$acta = Acta::with("requisiciones")->insumos()->sum('cantidad')->get();
            return Response::json([ 'data' => $acta ],200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage(), 'line' => $e->getLine()], HttpResponse::HTTP_CONFLICT);
        }
    }

    public function show(Request $request, $id)
    {
        //Aqui es la distribucion
        $usuario = JWTAuth::parseToken()->getPayload();
        $acta = Acta::crossJoin("requisiciones", "requisiciones.acta_id", "=", "actas.id")
                    ->crossJoin("requisicion_insumo_clues", "requisicion_insumo_clues.requisicion_id", "=", "requisiciones.id")
                    ->crossJoin("insumos", "insumos.id", "=", "requisicion_insumo_clues.insumo_id")

                    ->where("actas.estatus", 4)
                    ->where("actas.id", '=', $id)
                    ->where("requisicion_insumo_clues.clues", "!=", [$usuario->get('clues')])
                    ->select("insumos.id",
                             "insumos.clave",
                             "insumos.descripcion",
                             "requisicion_insumo_clues.cantidad_validada",
                             "requisicion_insumo_clues.clues",
                             DB::RAW("(select clues_nombre from configuracion where configuracion.clues=requisicion_insumo_clues.clues) as clues_nombre"),
                             DB::RAW("(select if(sum(salida_detalles.cantidad_surtido) is null, 0, sum(salida_detalles.cantidad_surtido)) from salidas, salida_detalles
                                        where salidas.id=salida_detalles.salida_id and salidas.acta_id=actas.id and salida_detalles.insumo_id=insumos.id and salidas.estatus=2) as surtido"),
                             DB::RAW("(select if(sum(disponible) is null, 0, sum(disponible)) from stock_insumos where clues='".$usuario->get('clues')."' and insumo_id=insumos.id) as stock"));


        $acta = $acta->get();

        return Response::json([ 'data' => $acta ],200);
    }

    public function obtenerInventario(Request $request, $id)
    {

        $usuario = JWTAuth::parseToken()->getPayload();
        $insumo = StockInsumo::where("insumo_id", $id)
                            ->where("clues", $usuario->get('clues'))
                            ->where("disponible", ">", 0)
                            ->select(DB::RAW("sum(disponible) as inventario"))
                            ->groupBy("insumo_id");

        $insumo = $insumo->first();
        return Response::json([ 'data' => $insumo ],200);
    }
}
