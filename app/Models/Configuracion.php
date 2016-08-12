<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class Configuracion extends Model {
	protected $table = 'configuracion';
	protected $fillable = ['director_unidad','administrador','encargado_almacen','coordinador_comision_abasto','lugar_entrega'];
}