<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use \DB;

class StockInsumo extends Model {
	protected $table = 'stock_insumos';
	protected $fillable = ['entrega_id','insumo_id','lote','fecha_caducidad','cantidad_entregada'];

	public function insumo(){
		return $this->hasOne('App\Models\Insumo','id','insumo_id');
	}
}