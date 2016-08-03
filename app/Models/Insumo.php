<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use \DB;

class Insumo extends Model {
	public function scopePorEmpresa($query){
		$empresa = env('EMPRESA');
		return $query->select('id','pedido_'.$empresa.' AS pedido','requisicion','lote','clave','descripcion',
				'marca_'.$empresa.' AS marca','unidad','precio_'.$empresa.' AS precio','tipo','cause');
	}
}