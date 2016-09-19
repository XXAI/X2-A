<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use \DB;

class Entrega extends Model {
	protected $fillable = ['acta_id','proveedor_id','fecha_entrega','hora_entrega','fecha_proxima_entrega','nombre_recibe','nombre_entrega','estatus'];

	public function stock(){
		return $this->hasMany('App\Models\StockInsumo','entrega_id');
	}
}