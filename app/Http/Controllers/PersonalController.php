<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Traits\SyncTrait;
use App\Http\Requests;

use App\Models\Configuracion;
use App\Models\ConfiguracionAplicacion;
use JWTAuth;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB,  \Storage, Exception;

class PersonalController extends Controller
{
    use SyncTrait;
    public function index(Request $request){

        try{
            $usuario = JWTAuth::parseToken()->getPayload();

            $query = Input::get('query');
            $filtro = Input::get('filtro');

            $personal = DB::table("salidas")
                            ->where("clues", $usuario->get('clues'));

            if($filtro == 1)
            {
                $personal = $personal->where("autoriza", "like", "%".$query."%");
                $personal = $personal->groupBy("autoriza");
            }

            if($filtro == 2)
            {
                $personal = $personal->where("realiza", "like", "%".$query."%");
                $personal = $personal->groupBy("realiza");
            }
            if($filtro == 3)
            {
                $personal = $personal->where("recibe", "like", "%".$query."%");
                $personal = $personal->groupBy("recibe");
            }

            $personal= $personal->get();

            return Response::json(['data'=>$personal],200);
        }catch(Exception $ex){
            return Response::json(['error'=>$ex->getMessage()],500);
        }
    }
}
