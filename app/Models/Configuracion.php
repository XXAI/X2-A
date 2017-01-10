<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class Configuracion extends Model {
	protected $table = 'configuracion';
	protected $fillable = ['director_unidad','administrador','encargado_almacen','coordinador_comision_abasto','lugar_entrega'];
	public $incrementing  = false;

	public function cuadroBasico(){
		return $this->hasMany('App\Models\ListaBaseInsumosDetalle','lista_base_insumos_id','lista_base_id');
	}

	public function inventario(){
		return $this->hasMany('App\Models\Inventario','clues','clues')->orderBy('anio','DESC');
	}
}