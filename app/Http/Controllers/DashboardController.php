<?php

namespace App\Http\Controllers;

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
    public function index(){
        $total_actas_capturadas = Acta::count();
        $total_actas_finalizadas =  Acta::where('estatus',2)->count();
        $total_requisiciones = Requisicion::count();

        $configuracion = Configuracion::find(1);

        $datos = [
            'total_actas_capturadas'    => $total_actas_capturadas,
            'total_actas_finalizadas'   => $total_actas_finalizadas,
            'total_requisiciones'       => $total_requisiciones,
            'configuracion'             => $configuracion
        ];

        return Response::json(['data'=>$datos],200);
    }
}
