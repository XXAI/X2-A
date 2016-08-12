<?php

namespace App\Http\Controllers;

use JWTAuth, JWTFactory;
use Tymon\JWTAuth\Exceptions\JWTException;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use App\Models\Acta;
use App\Models\Requisicion;
use App\Models\Insumo;
use App\Models\Configuracion;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request){
        $total_actas_capturadas = Acta::count();
        //$total_actas_finalizadas =  Acta::where('estatus',2)->count();
        //$total_requisiciones = Requisicion::count();

        $actas = Acta::with('requisiciones')->where('estatus',2)->get();
        $total_actas_finalizadas = count($actas);
        $total_requisiciones = 0;
        $total_requisitado = 0;

        foreach ($actas as $acta) {
            $total_requisiciones += count($acta->requisiciones);
            $total_requisitado += $acta->requisiciones()->sum('gran_total');
            /*foreach ($acta->requisiciones as $requisicion) {
                # code...
            }*/
        }
        
        $configuracion = Configuracion::find(1);

        $datos = [
            'total_actas_capturadas'    => $total_actas_capturadas,
            'total_actas_finalizadas'   => $total_actas_finalizadas,
            'total_requisiciones'       => $total_requisiciones,
            'total_requisitado'         => $total_requisitado,
            'configuracion'             => $configuracion
        ];

        return Response::json(['data'=>$datos],200);
    }
}
