<?php 
namespace App\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\Insumo;
use App\Models\Configuracion;
use Illuminate\Support\Facades\Input;

use Response,  Validator, DB;
use Illuminate\Http\Response as HttpResponse;


class InsumoController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index(){
		$query = Input::get('query');
		$configuracion = Configuracion::find(1);
		$empresa = $configuracion->empresa_clave;;
		if($query){
            $insumos = Insumo::where(function($condition)use($query){
			                $condition->where('clave','LIKE','%'.$query.'%')
			                        ->orWhere('descripcion','LIKE','%'.$query.'%');
			            });
        }else {
			$insumos = Insumo::getModel();
		}

		$insumos = $insumos->select('id','pedido_'.$empresa.' AS pedido','requisicion','lote','clave','descripcion',
						'marca_'.$empresa.' AS marca','unidad','cantidad','precio_'.$empresa.' AS precio','tipo',
						'cause')
						->where(function($condition)use($empresa){
							$condition->whereNotNull('pedido_'.$empresa)->whereNotNull('precio_'.$empresa);
						})
						->get();

		return Response::json(['data'=>$insumos],200);
	}
}
