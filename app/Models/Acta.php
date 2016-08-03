<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use \DB;

class Acta extends Model {
	protected $fillable = ['folio','ciudad','fecha','hora_inicio','hora_termino','lugar_reunion','empresa','estatus'];

	public function requisiciones(){
        return $this->hasMany('App\Models\Requisicion','acta_id');
    }
}