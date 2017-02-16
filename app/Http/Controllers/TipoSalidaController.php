<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Traits\SyncTrait;
use App\Http\Requests;

use App\Models\TipoSalida;
use App\Models\ConfiguracionAplicacion;
use JWTAuth;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB, \PDF, \Storage, \ZipArchive, Exception;
use \Excel;

class TipoSalidaController extends Controller
{
    use SyncTrait;
    public function index(Request $request){

        try{

            $recurso = TipoSalida::all();

            return Response::json(['data'=>$recurso],200);
        }catch(Exception $ex){
            return Response::json(['error'=>$ex->getMessage()],500);
        }

    }
}
