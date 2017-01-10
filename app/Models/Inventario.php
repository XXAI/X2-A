<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class Inventario extends Model {
	protected $table = 'inventario';
	public $incrementing  = false;

	public function insumo(){
		return $this->hasOne('App\Models\Insumo','llave','llave');
	}
}