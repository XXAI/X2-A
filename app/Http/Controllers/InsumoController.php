<?php 
namespace App\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use JWTAuth;

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
	public function index(Request $request){
		$query = Input::get('query');
		
		$usuario = JWTAuth::parseToken()->getPayload();
        $configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();

		$empresa = $configuracion->empresa_clave;;
		if($query){
            $insumos = Insumo::where(function($condition)use($query){
			                $condition->where('clave','LIKE','%'.$query.'%')
			                        ->orWhere('descripcion','LIKE','%'.$query.'%')
			                        ->orWhere('lote','LIKE','%'.$query.'%');
			            });
        }else {
			$insumos = Insumo::getModel();
		}

		if(Input::get('clues')){
			$clues = Input::get('clues');
		}else{
			$clues = $configuracion->clues;
		}

		$insumos = $insumos->select('id','llave','pedido','requisicion','lote','clave','descripcion', 'llave',
						'marca','unidad','cantidad','precio','tipo','cause','controlado','surfactante','sustancia_id','presentacion_id','unidad_medida_id','familia_id','es_unidosis','tipo_sustancia', 'cantidad_unidad', 'cantidad_presentacion')
						->where('proveedor',$empresa)
						->with(['inventario' => function($query) use ($clues){
							$query->where('clues',$clues);
						}])
						->orderBy('tipo')
						->orderBy('precio')
						->get();

		return Response::json(['data'=>$insumos],200);
	}
}
