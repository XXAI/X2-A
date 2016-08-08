<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class Configuracion extends Model {
	protected $table = 'configuracion';
	protected $fillable = ['clues','clues_nombre','empresa_clave','empresa_nombre','director_unidad','solicitante_nombre','solicitante_cargo','ciudad'];
}