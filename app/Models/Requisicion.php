<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use \DB;

class Requisicion extends Model {
	protected $table = 'requisiciones';
	protected $fillable = ['acta_id', 'numero', 'pedido', 'lotes', 'empresa', 'tipo_requisicion', 'dias_surtimiento', 'sub_total', 'gran_total', 'iva', 'firma_solicita', 'firma_director'];

	public function detalles(){
        return $this->hasMany('App\Models\DetalleRequisicion','requisicion_id');
    }
}