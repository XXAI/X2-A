<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use \DB;

class DetalleRequisicion extends Model {
	protected $table = 'detalles_requisicion';
	protected $fillable = ['requisicion_id','insumo_id','lote','cantidad','total'];

	public function insumo(){
        return $this->hasOne('App\Models\Insumo','id','insumo_id');
    }
}