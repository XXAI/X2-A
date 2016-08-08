<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use \DB;
use App\Models\Configuracion;

class Insumo extends Model {
	public function scopePorEmpresa($query){
		$configuracion = Configuracion::find(1);
		$empresa = $configuracion->empresa_clave;
		return $query->select('id','pedido_'.$empresa.' AS pedido','requisicion','lote','clave','descripcion',
				'marca_'.$empresa.' AS marca','unidad','precio_'.$empresa.' AS precio','tipo','cause');
	}
}